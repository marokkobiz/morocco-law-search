const { pool } = require("../db/db");

const SEARCH_RESULT_LIMIT = 40;
const SUGGESTION_LIMIT = 8;
const SEARCH_CACHE_TTL_MS = 60 * 1000;
const OVERVIEW_CACHE_TTL_MS = 5 * 60 * 1000;
const CHAT_ONLY_CATEGORIES = ["official-bulletin"];
const CHAT_ONLY_SEARCH_ALIASES = [
  "official bulletin",
  "bulletin officiel",
  "official-bulletin",
  "latest laws",
  "recent laws",
  "new laws",
  "legal updates",
  "nouvelles lois",
  "nouveaux textes",
  "dernieres lois"
];
const DOCUMENT_TITLE_HINTS = [
  {
    title: "Code penal",
    aliases: ["code penal", "penal code"]
  },
  {
    title: "Code de commerce",
    aliases: ["code de commerce", "commercial code"]
  },
  {
    title: "Code du travail",
    aliases: ["code du travail", "labor code", "labour code"]
  },
  {
    title: "Code de la famille",
    aliases: ["code de la famille", "family code"]
  },
  {
    title: "Code de procedure civile",
    aliases: ["code de procedure civile", "civil procedure code"]
  },
  {
    title: "Code des Obligations et des Contrats",
    aliases: [
      "code des obligations et des contrats",
      "obligations et contrats",
      "obligations and contracts"
    ]
  }
];
const searchCache = new Map();
const suggestionCache = new Map();
let overviewCache = null;

const baseLawFields = `
  id,
  title,
  article_number,
  content,
  tags,
  document_title,
  law_reference,
  category,
  source_name,
  source_url,
  language
`;

const clearExpiredCacheEntries = (cache, now = Date.now()) => {
  for (const [key, entry] of cache.entries()) {
    if (entry.expiresAt <= now) {
      cache.delete(key);
    }
  }
};

const buildBooleanSearchTerm = (keyword) => {
  const tokens = keyword
    .split(/\s+/)
    .map((token) => token.trim().replace(/[^\p{L}\p{N}-]/gu, ""))
    .filter((token) => token.length >= 2);

  if (tokens.length === 0) {
    return "";
  }

  return tokens.map((token) => `+${token}*`).join(" ");
};

const normalizeSearchText = (value) =>
  String(value || "")
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[-_]+/g, " ")
    .replace(/\s+/g, " ")
    .trim();

const normalizeReferenceText = (value) =>
  String(value || "")
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[°º]/g, "")
    .replace(/\s+/g, " ")
    .trim();

const extractArticleNumber = (keyword) => {
  const match = normalizeSearchText(keyword).match(/\b(?:article|art)\s*(premier|\d+(?:\s*(?:bis|ter|quater))?)\b/);

  if (!match) {
    return null;
  }

  if (match[1] === "premier") {
    return "Article 1";
  }

  return `Article ${match[1].replace(/\s+/g, " ")}`;
};

const extractReferencePatterns = (keyword) => {
  const normalizedKeyword = normalizeReferenceText(keyword);
  const referenceMatch = normalizedKeyword.match(
    /\b(loi|dahir|decret|arrete)\s*(?:n|no|num|numero)?\s*(\d{1,3}\s*[-/]\s*\d{2,4})\b/
  );

  if (!referenceMatch) {
    const standaloneReference = normalizedKeyword.match(/\b\d{1,3}\s*[-/]\s*\d{2,4}\b/);
    return standaloneReference ? [`%${standaloneReference[0].replace(/\s*[-/]\s*/, "-")}%`] : [];
  }

  const [, referenceType, rawReferenceNumber] = referenceMatch;
  const referenceNumber = rawReferenceNumber.replace(/\s*[-/]\s*/, "-");

  return [`%${referenceType}%${referenceNumber}%`, `%${referenceNumber}%`];
};

const extractDocumentTitleHints = (keyword) => {
  const normalizedKeyword = normalizeSearchText(keyword);

  return DOCUMENT_TITLE_HINTS
    .filter(({ aliases }) =>
      aliases.some((alias) => normalizedKeyword.includes(normalizeSearchText(alias)))
    )
    .map(({ title }) => title);
};

const isChatOnlySearchKeyword = (keyword) => {
  const normalizedKeyword = normalizeSearchText(keyword);

  return CHAT_ONLY_SEARCH_ALIASES.some((alias) => normalizedKeyword.includes(normalizeSearchText(alias)));
};

const buildChatOnlyFilter = () => ({
  sql: `(category IS NULL OR category NOT IN (${CHAT_ONLY_CATEGORIES.map(() => "?").join(", ")}))`,
  params: CHAT_ONLY_CATEGORIES
});

const buildSearchCacheKey = (keyword, limit, options = {}) =>
  `${keyword.toLowerCase()}::${limit}::${options.includeChatOnlySources ? "all" : "public"}`;
const buildSuggestionCacheKey = (keyword, limit) => `${keyword.toLowerCase()}::${limit}`;

const searchLawsByKeyword = async (keyword, limit = SEARCH_RESULT_LIMIT, options = {}) => {
  const normalizedKeyword = String(keyword || "").trim();

  if (!normalizedKeyword) {
    return {
      results: [],
      hasMore: false,
      limit
    };
  }

  const normalizedLimit = Math.max(1, Math.min(Number(limit) || SEARCH_RESULT_LIMIT, 100));

  if (!options.includeChatOnlySources && isChatOnlySearchKeyword(normalizedKeyword)) {
    return {
      results: [],
      hasMore: false,
      limit: normalizedLimit
    };
  }

  const cacheKey = buildSearchCacheKey(normalizedKeyword, normalizedLimit, options);
  const now = Date.now();

  clearExpiredCacheEntries(searchCache, now);

  const cachedEntry = searchCache.get(cacheKey);

  if (cachedEntry && cachedEntry.expiresAt > now) {
    return cachedEntry.value;
  }

  const searchTerm = `%${normalizedKeyword}%`;
  const prefixTerm = `${normalizedKeyword}%`;
  const booleanSearchTerm = buildBooleanSearchTerm(normalizedKeyword);
  const publicFilter = options.includeChatOnlySources ? { sql: "", params: [] } : buildChatOnlyFilter();
  const articleNumber = extractArticleNumber(normalizedKeyword);
  const referencePatterns = extractReferencePatterns(normalizedKeyword);
  const documentTitleHints = extractDocumentTitleHints(normalizedKeyword);
  const terms = normalizedKeyword
    .split(/\s+/)
    .map((term) => term.trim())
    .filter(Boolean);

  const documentParams = [
    normalizedKeyword,
    normalizedKeyword,
    searchTerm,
    searchTerm,
    normalizedKeyword,
    searchTerm,
    searchTerm
  ];
  const scoreClauses = [
    "CASE WHEN title = ? THEN 160 ELSE 0 END",
    "CASE WHEN title LIKE ? THEN 120 ELSE 0 END",
    "CASE WHEN document_title LIKE ? THEN 85 ELSE 0 END",
    "CASE WHEN source_name LIKE ? THEN 80 ELSE 0 END",
    "CASE WHEN category LIKE ? THEN 75 ELSE 0 END",
    "CASE WHEN law_reference LIKE ? THEN 70 ELSE 0 END",
    "CASE WHEN article_number = ? THEN 60 ELSE 0 END",
    booleanSearchTerm
      ? "COALESCE(MATCH(title, document_title, law_reference, content) AGAINST (? IN BOOLEAN MODE), 0) * 25"
      : "0"
  ];
  const scoreParams = [
    normalizedKeyword,
    prefixTerm,
    searchTerm,
    searchTerm,
    searchTerm,
    searchTerm,
    normalizedKeyword
  ];

  const documentMatchClauses = [
    "CASE WHEN document_title = ? THEN 120 ELSE 0 END",
    "CASE WHEN law_reference = ? THEN 110 ELSE 0 END",
    "CASE WHEN document_title LIKE ? THEN 80 ELSE 0 END",
    "CASE WHEN source_name LIKE ? THEN 75 ELSE 0 END",
    "CASE WHEN category = ? THEN 72 ELSE 0 END",
    "CASE WHEN category LIKE ? THEN 70 ELSE 0 END",
    "CASE WHEN law_reference LIKE ? THEN 70 ELSE 0 END",
    "CASE WHEN category LIKE ? THEN 25 ELSE 0 END"
  ];
  documentParams.push(searchTerm);

  for (const documentTitle of documentTitleHints) {
    documentMatchClauses.push("CASE WHEN document_title = ? THEN 220 ELSE 0 END");
    documentParams.push(documentTitle);

    scoreClauses.push("CASE WHEN document_title = ? THEN 90 ELSE 0 END");
    scoreParams.push(documentTitle);
  }

  for (const referencePattern of referencePatterns) {
    documentMatchClauses.push("CASE WHEN law_reference LIKE ? THEN 160 ELSE 0 END");
    documentParams.push(referencePattern);

    documentMatchClauses.push("CASE WHEN document_title LIKE ? THEN 120 ELSE 0 END");
    documentParams.push(referencePattern);
  }

  const articleMatchClauses = ["0"];
  const articleParams = [];

  if (articleNumber) {
    articleMatchClauses.push("CASE WHEN article_number = ? THEN 180 ELSE 0 END");
    articleParams.push(articleNumber);

    articleMatchClauses.push("CASE WHEN article_number LIKE ? THEN 90 ELSE 0 END");
    articleParams.push(`${articleNumber}%`);
  }

  if (booleanSearchTerm) {
    scoreParams.push(booleanSearchTerm);
  }

  for (const term of terms) {
    scoreClauses.push("CASE WHEN title LIKE ? THEN 24 ELSE 0 END");
    scoreParams.push(`%${term}%`);

    scoreClauses.push("CASE WHEN document_title LIKE ? THEN 16 ELSE 0 END");
    scoreParams.push(`%${term}%`);

    scoreClauses.push("CASE WHEN source_name LIKE ? THEN 16 ELSE 0 END");
    scoreParams.push(`%${term}%`);

    scoreClauses.push("CASE WHEN category LIKE ? THEN 14 ELSE 0 END");
    scoreParams.push(`%${term}%`);

    scoreClauses.push("CASE WHEN law_reference LIKE ? THEN 14 ELSE 0 END");
    scoreParams.push(`%${term}%`);

    scoreClauses.push("CASE WHEN article_number LIKE ? THEN 10 ELSE 0 END");
    scoreParams.push(`%${term}%`);
  }

  const whereClauses = [
    "title LIKE ?",
    "document_title LIKE ?",
    "source_name LIKE ?",
    "category LIKE ?",
    "law_reference LIKE ?",
    "article_number LIKE ?"
  ];
  const whereParams = [searchTerm, searchTerm, searchTerm, searchTerm, searchTerm, searchTerm];

  if (articleNumber) {
    whereClauses.push("article_number = ?");
    whereParams.push(articleNumber);
  }

  for (const referencePattern of referencePatterns) {
    whereClauses.push("law_reference LIKE ?");
    whereParams.push(referencePattern);
  }

  if (booleanSearchTerm) {
    whereClauses.push("MATCH(title, document_title, law_reference, content) AGAINST (? IN BOOLEAN MODE)");
    whereParams.push(booleanSearchTerm);
  } else {
    whereClauses.push("content LIKE ?");
    whereParams.push(searchTerm);
  }

  const query = `
    SELECT
      id,
      title,
      article_number,
      LEFT(content, 900) AS content,
      tags,
      document_title,
      law_reference,
      category,
      source_name,
      source_url,
      language,
      (${documentMatchClauses.join(" + ")}) AS document_match_score,
      (${articleMatchClauses.join(" + ")}) AS article_match_score,
      CASE
        WHEN article_number LIKE 'Article premier%' THEN 1
        ELSE CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(article_number, ' ', 2), ' ', -1) AS UNSIGNED)
      END AS article_sort_number,
      (${scoreClauses.join(" + ")}) AS relevance_score
    FROM laws
    WHERE (${whereClauses.join(" OR ")})
      ${publicFilter.sql ? `AND ${publicFilter.sql}` : ""}
    ORDER BY
      document_match_score DESC,
      article_match_score DESC,
      relevance_score DESC,
      document_title ASC,
      article_sort_number ASC,
      article_number ASC,
      title ASC
    LIMIT ?
  `;

  const queryParams = [
    ...documentParams,
    ...articleParams,
    ...scoreParams,
    ...whereParams,
    ...publicFilter.params,
    normalizedLimit + 1
  ];
  const [rows] = await pool.query(query, queryParams);
  const hasMore = rows.length > normalizedLimit;
  const results = hasMore ? rows.slice(0, normalizedLimit) : rows;
  const payload = {
    results,
    hasMore,
    limit: normalizedLimit
  };

  searchCache.set(cacheKey, {
    value: payload,
    expiresAt: now + SEARCH_CACHE_TTL_MS
  });

  return payload;
};

const getLawById = async (id) => {
  const [rows] = await pool.query(
    `
      SELECT ${baseLawFields}
      FROM laws
      WHERE id = ?
      LIMIT 1
    `,
    [id]
  );

  return rows[0] || null;
};

const getLatestOfficialBulletinArticles = async (limit = SEARCH_RESULT_LIMIT) => {
  const normalizedLimit = Math.max(1, Math.min(Number(limit) || SEARCH_RESULT_LIMIT, 100));

  const [rows] = await pool.query(
    `
      SELECT
        id,
        title,
        article_number,
        LEFT(content, 900) AS content,
        tags,
        document_title,
        law_reference,
        category,
        source_name,
        source_url,
        language,
        CAST(
          REPLACE(
            REPLACE(
              SUBSTRING_INDEX(SUBSTRING_INDEX(law_reference, '/', 1), 'n ', -1),
              '-bis',
              ''
            ),
            ' ',
            ''
          ) AS UNSIGNED
        ) AS bulletin_sort_number,
        CASE
          WHEN article_number LIKE 'Article premier%' THEN 1
          ELSE CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(article_number, ' ', 2), ' ', -1) AS UNSIGNED)
        END AS article_sort_number,
        1000 AS relevance_score
      FROM laws
      WHERE category = ?
      ORDER BY
        bulletin_sort_number DESC,
        document_title DESC,
        article_sort_number ASC,
        article_number ASC,
        id ASC
      LIMIT ?
    `,
    [CHAT_ONLY_CATEGORIES[0], normalizedLimit + 1]
  );
  const hasMore = rows.length > normalizedLimit;

  return {
    results: hasMore ? rows.slice(0, normalizedLimit) : rows,
    hasMore,
    limit: normalizedLimit
  };
};

const getSearchSuggestions = async (keyword, limit = SUGGESTION_LIMIT) => {
  const normalizedKeyword = keyword.trim();

  if (normalizedKeyword.length < 2) {
    return [];
  }

  const normalizedLimit = Math.max(1, Math.min(Number(limit) || SUGGESTION_LIMIT, 12));
  const cacheKey = buildSuggestionCacheKey(normalizedKeyword, normalizedLimit);
  const now = Date.now();

  clearExpiredCacheEntries(suggestionCache, now);

  const cachedEntry = suggestionCache.get(cacheKey);

  if (cachedEntry && cachedEntry.expiresAt > now) {
    return cachedEntry.value;
  }

  const prefixTerm = `${normalizedKeyword}%`;
  const containsTerm = `%${normalizedKeyword}%`;
  const publicFilter = buildChatOnlyFilter();

  const [rows] = await pool.query(
    `
      SELECT suggestion, suggestion_type
      FROM (
        SELECT
          document_title AS suggestion,
          'Document' AS suggestion_type,
          CASE
            WHEN document_title LIKE ? THEN 3
            WHEN document_title LIKE ? THEN 2
            ELSE 1
          END AS score
        FROM laws
        WHERE document_title IS NOT NULL AND document_title <> ''
          AND (document_title LIKE ? OR document_title LIKE ?)
          AND ${publicFilter.sql}

        UNION ALL

        SELECT
          title AS suggestion,
          'Article' AS suggestion_type,
          CASE
            WHEN title LIKE ? THEN 3
            WHEN title LIKE ? THEN 2
            ELSE 1
          END AS score
        FROM laws
        WHERE (title LIKE ? OR title LIKE ?)
          AND ${publicFilter.sql}

        UNION ALL

        SELECT
          category AS suggestion,
          'Area' AS suggestion_type,
          CASE
            WHEN category LIKE ? THEN 3
            WHEN category LIKE ? THEN 2
            ELSE 1
          END AS score
        FROM laws
        WHERE category IS NOT NULL AND category <> ''
          AND (category LIKE ? OR category LIKE ?)
          AND ${publicFilter.sql}

        UNION ALL

        SELECT
          law_reference AS suggestion,
          'Reference' AS suggestion_type,
          CASE
            WHEN law_reference LIKE ? THEN 3
            WHEN law_reference LIKE ? THEN 2
            ELSE 1
          END AS score
        FROM laws
        WHERE law_reference IS NOT NULL AND law_reference <> ''
          AND (law_reference LIKE ? OR law_reference LIKE ?)
          AND ${publicFilter.sql}
      ) AS suggestion_pool
      WHERE suggestion IS NOT NULL AND suggestion <> ''
      GROUP BY suggestion, suggestion_type, score
      ORDER BY score DESC, CHAR_LENGTH(suggestion) ASC, suggestion ASC
      LIMIT ?
    `,
    [
      prefixTerm,
      containsTerm,
      prefixTerm,
      containsTerm,
      ...publicFilter.params,
      prefixTerm,
      containsTerm,
      prefixTerm,
      containsTerm,
      ...publicFilter.params,
      prefixTerm,
      containsTerm,
      prefixTerm,
      containsTerm,
      ...publicFilter.params,
      prefixTerm,
      containsTerm,
      prefixTerm,
      containsTerm,
      ...publicFilter.params,
      normalizedLimit
    ]
  );

  const suggestions = rows.map((row) => ({
    text: row.suggestion,
    type: row.suggestion_type
  }));

  suggestionCache.set(cacheKey, {
    value: suggestions,
    expiresAt: now + SEARCH_CACHE_TTL_MS
  });

  return suggestions;
};

const getLawLibraryOverview = async () => {
  const now = Date.now();

  if (overviewCache && overviewCache.expiresAt > now) {
    return overviewCache.value;
  }

  const publicFilter = buildChatOnlyFilter();

  const [[totals]] = await pool.query(
    `
      SELECT
        COUNT(*) AS totalArticles,
        COUNT(DISTINCT document_title) AS totalDocuments,
        COUNT(DISTINCT category) AS totalCategories
      FROM laws
      WHERE ${publicFilter.sql}
    `,
    publicFilter.params
  );

  const [categoryRows] = await pool.query(
    `
      SELECT
        category,
        COUNT(*) AS articleCount,
        COUNT(DISTINCT document_title) AS documentCount
      FROM laws
      WHERE category IS NOT NULL AND category <> ''
        AND ${publicFilter.sql}
      GROUP BY category
      ORDER BY articleCount DESC, category ASC
    `,
    publicFilter.params
  );

  const payload = {
    totalArticles: Number(totals.totalArticles || 0),
    totalDocuments: Number(totals.totalDocuments || 0),
    totalCategories: Number(totals.totalCategories || 0),
    categories: categoryRows.map((row) => ({
      category: row.category,
      articleCount: Number(row.articleCount || 0),
      documentCount: Number(row.documentCount || 0)
    }))
  };

  overviewCache = {
    value: payload,
    expiresAt: now + OVERVIEW_CACHE_TTL_MS
  };

  return payload;
};

module.exports = {
  SEARCH_RESULT_LIMIT,
  CHAT_ONLY_CATEGORIES,
  searchLawsByKeyword,
  getLatestOfficialBulletinArticles,
  getSearchSuggestions,
  getLawById,
  getLawLibraryOverview
};
