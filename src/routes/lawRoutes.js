const express = require("express");
const {
  getLibraryOverview,
<<<<<<< HEAD
  getSuggestions,
=======
>>>>>>> b1ae2aafedb997d727fa9e599470ad675d3d192c
  searchLaws,
  translateLawArticle
} = require("../controllers/lawController");

const router = express.Router();

router.get("/overview", getLibraryOverview);
<<<<<<< HEAD
router.get("/suggestions", getSuggestions);
=======
>>>>>>> b1ae2aafedb997d727fa9e599470ad675d3d192c
router.get("/search", searchLaws);
router.get("/:id/translate", translateLawArticle);

module.exports = router;
