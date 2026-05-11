const { pool } = require("../db/db");

<<<<<<< HEAD
const SEARCH_RESULT_LIMIT = 40;
const SUGGESTION_LIMIT = 8;
const SEARCH_CACHE_TTL_MS = 60 * 1000;
const OVERVIEW_CACHE_TTL_MS = 5 * 60 * 1000;
const searchCache = new Map();
const suggestionCache = new Map();
let overviewCache = null;

=======
>>>>>>> b1ae2aafedb997d727fa9e599470ad675d3d192c
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

<<<<<<< HEAD
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

const buildSearchCacheKey = (keyword, limit) => `${keyword.toLowerCase()}::${limit}`;
const buildSuggestionCacheKey = (keyword, limit) => `${keyword.toLowerCase()}::${limit}`;

const searchLawsByKeyword = async (keyword, limit = SEARCH_RESULT_LIMIT) => {
  const normalizedKeyword = keyword.trim();

  if (!normalizedKeyword) {
    return {
      results: [],
      hasMore: false,
      limit
    };
  }

  const normalizedLimit = Math.max(1, Math.min(Number(limit) || SEARCH_RESULT_LIMIT, 100));
  const cacheKey = buildSearchCacheKey(normalizedKeyword, normalizedLimit);
  const now = Date.now();

  clearExpiredCacheEntries(searchCache, now);

  const cachedEntry = searchCache.get(cacheKey);

  if (cachedEntry && cachedEntry.expiresAt > now) {
    return cachedEntry.value;
=======
const searchLawsByKeyword = async (keyword) => {
  const normalizedKeyword = keyword.trim();

  if (!normalizedKeyword) {
    return [];
>>>>>>> b1ae2aafedb997d727fa9e599470ad675d3d192c
  }

  const searchTerm = `%${normalizedKeyword}%`;
  const prefixTerm = `${normalizedKeyword}%`;
<<<<<<< HEAD
  const booleanSearchTerm = buildBooleanSearchTerm(normalizedKeyword);
=======
>>>>>>> b1ae2aafedb997d727fa9e599470ad675d3d192c
  const terms = normalizedKeyword
    .split(/\s+/)
    .map((term) => term.trim())
    .filter(Boolean);

  const scoreClauses = [
<<<<<<< HEAD
    "CASE WHEN title = ? THEN 160 ELSE 0 END",
    "CASE WHEN title LIKE ? THEN 120 ELSE 0 END",
    "CASE WHEN document_title LIKE ? THEN 85 ELSE 0 END",
    "CASE WHEN law_reference LIKE ? THEN 70 ELSE 0 END",
    "CASE WHEN article_number = ? THEN 60 ELSE 0 END",
    booleanSearchTerm
      ? "COALESCE(MATCH(title, document_title, law_reference, content) AGAINST (? IN BOOLEAN MODE), 0) * 25"
      : "0"
  ];

  const documentMatchClauses = [
    "CASE WHEN document_title = ? THEN 120 ELSE 0 END",
    "CASE WHEN law_reference = ? THEN 110 ELSE 0 END",
    "CASE WHEN document_title LIKE ? THEN 80 ELSE 0 END",
    "CASE WHEN law_reference LIKE ? THEN 70 ELSE 0 END",
    "CASE WHEN category LIKE ? THEN 25 ELSE 0 END"
=======
    "CASE WHEN LOWER(title) = LOWER(?) THEN 120 ELSE 0 END",
    "CASE WHEN LOWER(title) LIKE LOWER(?) THEN 80 ELSE 0 END",
    "CASE WHEN LOWER(document_title) LIKE LOWER(?) THEN 70 ELSE 0 END",
    "CASE WHEN LOWER(law_reference) LIKE LOWER(?) THEN 60 ELSE 0 END",
    "CASE WHEN LOWER(article_number) = LOWER(?) THEN 50 ELSE 0 END",
    "CASE WHEN LOWER(content) LIKE LOWER(?) THEN 30 ELSE 0 END"
>>>>>>> b1ae2aafedb997d727fa9e599470ad675d3d192c
  ];

  const queryParams = [
    normalizedKeyword,
    prefixTerm,
    searchTerm,
    searchTerm,
    normalizedKeyword,
<<<<<<< HEAD
    normalizedKeyword,
    normalizedKeyword,
    searchTerm,
    searchTerm,
    searchTerm
  ];

  if (booleanSearchTerm) {
    queryParams.push(booleanSearchTerm);
  }

  for (const term of terms) {
    scoreClauses.push("CASE WHEN title LIKE ? THEN 24 ELSE 0 END");
    queryParams.push(`%${term}%`);

    scoreClauses.push("CASE WHEN document_title LIKE ? THEN 16 ELSE 0 END");
    queryParams.push(`%${term}%`);

    scoreClauses.push("CASE WHEN law_reference LIKE ? THEN 14 ELSE 0 END");
    queryParams.push(`%${term}%`);

    scoreClauses.push("CASE WHEN article_number LIKE ? THEN 10 ELSE 0 END");
=======
    searchTerm
  ];

  for (const term of terms) {
    scoreClauses.push("CASE WHEN LOWER(title) LIKE LOWER(?) THEN 25 ELSE 0 END");
    queryParams.push(`%${term}%`);

    scoreClauses.push("CASE WHEN LOWER(document_title) LIKE LOWER(?) THEN 18 ELSE 0 END");
    queryParams.push(`%${term}%`);

    scoreClauses.push("CASE WHEN LOWER(law_reference) LIKE LOWER(?) THEN 16 ELSE 0 END");
    queryParams.push(`%${term}%`);

    scoreClauses.push("CASE WHEN LOWER(content) LIKE LOWER(?) THEN 10 ELSE 0 END");
>>>>>>> b1ae2aafedb997d727fa9e599470ad675d3d192c
    queryParams.push(`%${term}%`);
  }

  const whereClauses = [
<<<<<<< HEAD
    "title LIKE ?",
    "document_title LIKE ?",
    "law_reference LIKE ?",
    "article_number LIKE ?"
  ];

  queryParams.push(searchTerm, searchTerm, searchTerm, searchTerm);

  if (booleanSearchTerm) {
    whereClauses.push("MATCH(title, document_title, law_reference, content) AGAINST (? IN BOOLEAN MODE)");
    queryParams.push(booleanSearchTerm);
  } else {
    whereClauses.push("content LIKE ?");
    queryParams.push(searchTerm);
  }

  queryParams.push(normalizedLimit + 1);

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
      CASE
        WHEN article_number LIKE 'Article premier%' THEN 1
        ELSE CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(article_number, ' ', 2), ' ', -1) AS UNSIGNED)
      END AS article_sort_number,
=======
    "LOWER(title) LIKE LOWER(?)",
    "LOWER(document_title) LIKE LOWER(?)",
    "LOWER(law_reference) LIKE LOWER(?)",
    "LOWER(article_number) LIKE LOWER(?)",
    "LOWER(content) LIKE LOWER(?)"
  ];

  const query = `
    SELECT
      ${baseLawFields},
>>>>>>> b1ae2aafedb997d727fa9e599470ad675d3d192c
      (${scoreClauses.join(" + ")}) AS relevance_score
    FROM laws
    WHERE ${whereClauses.join(" OR ")}
    ORDER BY
<<<<<<< HEAD
      document_match_score DESC,
      document_title ASC,
      article_sort_number ASC,
      relevance_score DESC,
      article_number ASC,
      title ASC
    LIMIT ?
  `;

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
=======
      relevance_score DESC,
      document_title ASC,
      CASE
        WHEN LOWER(article_number) LIKE 'article premier%' THEN 1
        ELSE CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(article_number, ' ', 2), ' ', -1) AS UNSIGNED)
      END ASC,
      article_number ASC,
      title ASC
  `;

  queryParams.push(searchTerm, searchTerm, searchTerm, searchTerm, searchTerm);

  const [rows] = await pool.query(query, queryParams);
  return rows;
>>>>>>> b1ae2aafedb997d727fa9e599470ad675d3d192c
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

<<<<<<< HEAD
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
        WHERE title LIKE ? OR title LIKE ?

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
      prefixTerm,
      containsTerm,
      prefixTerm,
      containsTerm,
      prefixTerm,
      containsTerm,
      prefixTerm,
      containsTerm,
      prefixTerm,
      containsTerm,
      prefixTerm,
      containsTerm,
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

=======
const getLawLibraryOverview = async () => {
>>>>>>> b1ae2aafedb997d727fa9e599470ad675d3d192c
  const [[totals]] = await pool.query(
    `
      SELECT
        COUNT(*) AS totalArticles,
        COUNT(DISTINCT document_title) AS totalDocuments,
        COUNT(DISTINCT category) AS totalCategories
      FROM laws
    `
  );

  const [categoryRows] = await pool.query(
    `
      SELECT
        category,
        COUNT(*) AS articleCount,
        COUNT(DISTINCT document_title) AS documentCount
      FROM laws
      WHERE category IS NOT NULL AND category <> ''
      GROUP BY category
      ORDER BY articleCount DESC, category ASC
      LIMIT 8
    `
  );

<<<<<<< HEAD
  const payload = {
=======
  return {
>>>>>>> b1ae2aafedb997d727fa9e599470ad675d3d192c
    totalArticles: Number(totals.totalArticles || 0),
    totalDocuments: Number(totals.totalDocuments || 0),
    totalCategories: Number(totals.totalCategories || 0),
    categories: categoryRows.map((row) => ({
      category: row.category,
      articleCount: Number(row.articleCount || 0),
      documentCount: Number(row.documentCount || 0)
    }))
  };
<<<<<<< HEAD

  overviewCache = {
    value: payload,
    expiresAt: now + OVERVIEW_CACHE_TTL_MS
  };

  return payload;
};

module.exports = {
  SEARCH_RESULT_LIMIT,
  searchLawsByKeyword,
  getSearchSuggestions,
=======
};

module.exports = {
  searchLawsByKeyword,
>>>>>>> b1ae2aafedb997d727fa9e599470ad675d3d192c
  getLawById,
  getLawLibraryOverview
};
