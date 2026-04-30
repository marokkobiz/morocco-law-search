const { pool } = require("../db/db");

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

const searchLawsByKeyword = async (keyword) => {
  const normalizedKeyword = keyword.trim();

  if (!normalizedKeyword) {
    return [];
  }

  const searchTerm = `%${normalizedKeyword}%`;
  const prefixTerm = `${normalizedKeyword}%`;
  const terms = normalizedKeyword
    .split(/\s+/)
    .map((term) => term.trim())
    .filter(Boolean);

  const scoreClauses = [
    "CASE WHEN LOWER(title) = LOWER(?) THEN 120 ELSE 0 END",
    "CASE WHEN LOWER(title) LIKE LOWER(?) THEN 80 ELSE 0 END",
    "CASE WHEN LOWER(document_title) LIKE LOWER(?) THEN 70 ELSE 0 END",
    "CASE WHEN LOWER(law_reference) LIKE LOWER(?) THEN 60 ELSE 0 END",
    "CASE WHEN LOWER(article_number) = LOWER(?) THEN 50 ELSE 0 END",
    "CASE WHEN LOWER(content) LIKE LOWER(?) THEN 30 ELSE 0 END"
  ];

  const queryParams = [
    normalizedKeyword,
    prefixTerm,
    searchTerm,
    searchTerm,
    normalizedKeyword,
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
    queryParams.push(`%${term}%`);
  }

  const whereClauses = [
    "LOWER(title) LIKE LOWER(?)",
    "LOWER(document_title) LIKE LOWER(?)",
    "LOWER(law_reference) LIKE LOWER(?)",
    "LOWER(article_number) LIKE LOWER(?)",
    "LOWER(content) LIKE LOWER(?)"
  ];

  const query = `
    SELECT
      ${baseLawFields},
      (${scoreClauses.join(" + ")}) AS relevance_score
    FROM laws
    WHERE ${whereClauses.join(" OR ")}
    ORDER BY
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

const getLawLibraryOverview = async () => {
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

  return {
    totalArticles: Number(totals.totalArticles || 0),
    totalDocuments: Number(totals.totalDocuments || 0),
    totalCategories: Number(totals.totalCategories || 0),
    categories: categoryRows.map((row) => ({
      category: row.category,
      articleCount: Number(row.articleCount || 0),
      documentCount: Number(row.documentCount || 0)
    }))
  };
};

module.exports = {
  searchLawsByKeyword,
  getLawById,
  getLawLibraryOverview
};
