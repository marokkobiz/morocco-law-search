const {
  getLawById,
  getLawLibraryOverview,
  searchLawsByKeyword
} = require("../models/lawModel");
const { getStoredTranslation, saveTranslation } = require("../models/translationModel");
const {
  buildExternalTranslationUrl,
  isTranslationUnavailableError,
  translateLaw
} = require("../services/translationService");

const searchLaws = async (req, res) => {
  try {
    const { q } = req.query;
    const laws = await searchLawsByKeyword(q || "");

    res.json({
      query: q || "",
      count: laws.length,
      results: laws
    });
  } catch (error) {
    console.error(error);
    res.status(500).json({
      message: "Failed to search laws"
    });
  }
};

const translateLawArticle = async (req, res) => {
  const lawId = Number(req.params.id);
  const targetLanguage =
    typeof req.query.target === "string" && req.query.target.trim()
      ? req.query.target.trim().toLowerCase()
      : "en";

  try {
    if (!Number.isInteger(lawId) || lawId <= 0) {
      return res.status(400).json({
        message: "Invalid law id"
      });
    }

    const law = await getLawById(lawId);

    if (!law) {
      return res.status(404).json({
        message: "Law not found"
      });
    }

    const storedTranslation = await getStoredTranslation(law.id, targetLanguage);

    if (storedTranslation) {
      return res.json({
        articleNumber: law.article_number,
        documentTitle: law.document_title,
        sourceUrl: law.source_url,
        ...storedTranslation
      });
    }

    const translation = await translateLaw(law, targetLanguage);

    await saveTranslation({
      lawId: law.id,
      sourceLanguage: translation.sourceLanguage,
      targetLanguage: translation.targetLanguage,
      translatedTitle: translation.translatedTitle,
      translatedContent: translation.translatedContent
    });

    return res.json({
      id: law.id,
      articleNumber: law.article_number,
      documentTitle: law.document_title,
      sourceUrl: law.source_url,
      cached: false,
      ...translation
    });
  } catch (error) {
    const law = Number.isInteger(lawId) && lawId > 0 ? await getLawById(lawId).catch(() => null) : null;
    const fallbackUrl = law ? buildExternalTranslationUrl(law, targetLanguage) : null;
    const isExpectedTranslationOutage = isTranslationUnavailableError(error);

    if (isExpectedTranslationOutage) {
      console.warn("Inline translation unavailable because free providers are rate-limited or unreachable.");
    } else {
      console.error(error);
    }

    return res.status(isExpectedTranslationOutage ? 503 : 500).json({
      message: isExpectedTranslationOutage
        ? "Inline translation is temporarily unavailable."
        : "Failed to translate law",
      fallbackUrl
    });
  }
};

const getLibraryOverview = async (req, res) => {
  try {
    const overview = await getLawLibraryOverview();
    res.json(overview);
  } catch (error) {
    console.error(error);
    res.status(500).json({
      message: "Failed to load library overview"
    });
  }
};

module.exports = {
  searchLaws,
  translateLawArticle,
  getLibraryOverview
};
