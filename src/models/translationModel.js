const { pool } = require("../db/db");

const formatTranslationRow = (row) => ({
  id: row.law_id,
  sourceLanguage: row.source_language,
  targetLanguage: row.target_language,
  translatedTitle: row.translated_title,
  translatedContent: row.translated_content,
  provider: row.provider,
  cached: true
});

const getStoredTranslation = async (lawId, targetLanguage) => {
  const [rows] = await pool.query(
    `
      SELECT
        law_id,
        source_language,
        target_language,
        translated_title,
        translated_content,
        provider
      FROM law_translations
      WHERE law_id = ? AND target_language = ?
      LIMIT 1
    `,
    [lawId, targetLanguage]
  );

  return rows[0] ? formatTranslationRow(rows[0]) : null;
};

const saveTranslation = async ({
  lawId,
  sourceLanguage,
  targetLanguage,
  translatedTitle,
  translatedContent,
  provider = "automatic"
}) => {
  await pool.query(
    `
      INSERT INTO law_translations (
        law_id,
        source_language,
        target_language,
        translated_title,
        translated_content,
        provider
      )
      VALUES (?, ?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE
        source_language = VALUES(source_language),
        translated_title = VALUES(translated_title),
        translated_content = VALUES(translated_content),
        provider = VALUES(provider),
        updated_at = CURRENT_TIMESTAMP
    `,
    [
      lawId,
      sourceLanguage,
      targetLanguage,
      translatedTitle,
      translatedContent,
      provider
    ]
  );
};

const countStoredTranslations = async (targetLanguage) => {
  const [rows] = await pool.query(
    `
      SELECT COUNT(*) AS count
      FROM law_translations
      WHERE target_language = ?
    `,
    [targetLanguage]
  );

  return rows[0].count;
};

module.exports = {
  getStoredTranslation,
  saveTranslation,
  countStoredTranslations
};
