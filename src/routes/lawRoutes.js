const express = require("express");
const {
  getLibraryOverview,
  searchLaws,
  translateLawArticle
} = require("../controllers/lawController");

const router = express.Router();

router.get("/overview", getLibraryOverview);
router.get("/search", searchLaws);
router.get("/:id/translate", translateLawArticle);

module.exports = router;
