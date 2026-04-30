const { initializeDatabase, pool } = require("../db/db");
const { getStoredTranslation, saveTranslation } = require("../models/translationModel");
const { translateLaw } = require("../services/translationService");

const DEFAULT_TARGET_LANGUAGES = ["en", "ar"];

const parseTargetLanguages = () => {
  const languageArg = process.argv.find((arg) => arg.startsWith("--languages="));

  if (!languageArg) {
    return DEFAULT_TARGET_LANGUAGES;
  }

  return languageArg
    .replace("--languages=", "")
    .split(",")
    .map((language) => language.trim().toLowerCase())
    .filter(Boolean);
};

const parseLimit = () => {
  const limitArg = process.argv.find((arg) => arg.startsWith("--limit="));

  if (!limitArg) {
    return null;
  }

  const limit = Number(limitArg.replace("--limit=", ""));
  return Number.isInteger(limit) && limit > 0 ? limit : null;
};

const getLaws = async (limit) => {
  const limitSql = limit ? "LIMIT ?" : "";
  const params = limit ? [limit] : [];
  const [rows] = await pool.query(
    `
      SELECT
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
      FROM laws
      WHERE category = 'real-estate'
      ORDER BY document_title ASC,
        CASE
          WHEN LOWER(article_number) LIKE 'article premier%' THEN 1
          ELSE CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(article_number, ' ', 2), ' ', -1) AS UNSIGNED)
        END ASC,
        article_number ASC
      ${limitSql}
    `,
    params
  );

  return rows;
};

const translateAndStoreLaw = async (law, targetLanguage) => {
  const storedTranslation = await getStoredTranslation(law.id, targetLanguage);

  if (storedTranslation) {
    return "cached";
  }

  const translation = await translateLaw(law, targetLanguage);

  await saveTranslation({
    lawId: law.id,
    sourceLanguage: translation.sourceLanguage,
    targetLanguage: translation.targetLanguage,
    translatedTitle: translation.translatedTitle,
    translatedContent: translation.translatedContent
  });

  return "translated";
};

const translateRealEstateLaws = async () => {
  const targetLanguages = parseTargetLanguages();
  const limit = parseLimit();

  await initializeDatabase();

  const laws = await getLaws(limit);
  let translatedCount = 0;
  let cachedCount = 0;
  let failedCount = 0;

  console.log(
    `Preparing translations for ${laws.length} real-estate articles into ${targetLanguages.join(", ")}.`
  );

  for (const law of laws) {
    for (const targetLanguage of targetLanguages) {
      try {
        const status = await translateAndStoreLaw(law, targetLanguage);

        if (status === "cached") {
          cachedCount += 1;
        } else {
          translatedCount += 1;
        }

        console.log(`${status}: ${law.title} -> ${targetLanguage}`);
      } catch (error) {
        failedCount += 1;
        console.error(`failed: ${law.title} -> ${targetLanguage}`);
        console.error(error.message);
      }
    }
  }

  console.log(
    `Translation run complete. translated=${translatedCount}, cached=${cachedCount}, failed=${failedCount}`
  );

  await pool.end();
};

translateRealEstateLaws().catch(async (error) => {
  console.error(error);
  await pool.end();
  process.exit(1);
});
