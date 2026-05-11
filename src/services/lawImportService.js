const pdfParse = require("pdf-parse");
const { pool } = require("../db/db");

const normalizeArticleToken = (token, previousArticleNumber) => {
  const normalizedToken = token.trim().toLowerCase();

  if (normalizedToken === "premier") {
    return "Article 1";
  }

  const match = normalizedToken.match(/^(\d+)(?:\s*(bis|ter|quater))?$/i);

  if (!match) {
    return `Article ${token.trim()}`;
  }

  const rawDigits = match[1];
  const suffix = match[2] ? ` ${match[2]}` : "";
  const previousValue = Number(previousArticleNumber || 0);

  for (let length = 1; length <= rawDigits.length; length += 1) {
    const candidate = Number(rawDigits.slice(0, length));

    if (candidate === previousValue + 1) {
      return `Article ${candidate}${suffix}`;
    }
  }

  for (let length = 1; length <= rawDigits.length; length += 1) {
    const candidate = Number(rawDigits.slice(0, length));

    if (candidate > previousValue) {
      return `Article ${candidate}${suffix}`;
    }
  }

  return `Article ${Number(rawDigits)}${suffix}`;
};

const normalizeLine = (line) =>
  line
    .replace(/\u0000/g, " ")
    .replace(/\t/g, " ")
    .replace(/\s+/g, " ")
    .trim();

<<<<<<< HEAD
const extractArticleHeading = (line) => {
  const articleMatch = line.match(
    /^Article\s*(premier|\d+(?:\s*(?:bis|ter|quater))?)\s*:?\s*(.*)$/i
  );

  if (articleMatch) {
    return {
      token: articleMatch[1],
      inlineContent: (articleMatch[2] || "").trim()
    };
  }

  const abbreviatedMatch = line.match(/^Art\.\s*(\d+(?:\s*(?:bis|ter|quater))?)\.?\s*(.*)$/i);

  if (abbreviatedMatch) {
    return {
      token: abbreviatedMatch[1],
      inlineContent: (abbreviatedMatch[2] || "").trim()
    };
  }

  return null;
};
=======
const isStandaloneArticleHeading = (line) =>
  /^Article\s*(premier|\d+(?:\s*(?:bis|ter|quater))?)\s*:?\s*$/i.test(line);
>>>>>>> b1ae2aafedb997d727fa9e599470ad675d3d192c

const parseArticlesFromText = (text) => {
  const rawLines = text.split(/\r?\n/);
  const articles = [];
  let previousNumericArticle = 0;
  let currentArticle = null;
  let inFootnoteBlock = false;

  for (const rawLine of rawLines) {
    const line = normalizeLine(rawLine);

    if (!line) {
      inFootnoteBlock = false;
      continue;
    }

    if (/^-\s*\d+\s*-$/.test(line)) {
      inFootnoteBlock = false;
      continue;
    }

    if (/^\d+\s*-\s/.test(line) || /^Bulletin Officiel/i.test(line)) {
      inFootnoteBlock = true;
      continue;
    }

    if (inFootnoteBlock) {
      continue;
    }

<<<<<<< HEAD
    const articleHeading = extractArticleHeading(line);

    if (articleHeading) {
=======
    if (isStandaloneArticleHeading(line)) {
>>>>>>> b1ae2aafedb997d727fa9e599470ad675d3d192c
      if (currentArticle && currentArticle.contentParts.length > 0) {
        articles.push({
          articleNumber: currentArticle.articleNumber,
          content: currentArticle.contentParts.join(" ").trim()
        });
      }

<<<<<<< HEAD
      const articleNumber = normalizeArticleToken(articleHeading.token, previousNumericArticle);
=======
      const tokenMatch = line.match(
        /^Article\s*(premier|\d+(?:\s*(?:bis|ter|quater))?)\s*:?\s*$/i
      );
      const articleNumber = normalizeArticleToken(tokenMatch[1], previousNumericArticle);
>>>>>>> b1ae2aafedb997d727fa9e599470ad675d3d192c
      const numericMatch = articleNumber.match(/Article\s+(\d+)/i);

      if (numericMatch) {
        previousNumericArticle = Number(numericMatch[1]);
      }

      currentArticle = {
        articleNumber,
        contentParts: []
      };
<<<<<<< HEAD

      if (articleHeading.inlineContent) {
        currentArticle.contentParts.push(articleHeading.inlineContent);
      }

=======
>>>>>>> b1ae2aafedb997d727fa9e599470ad675d3d192c
      continue;
    }

    if (currentArticle) {
      currentArticle.contentParts.push(line);
    }
  }

  if (currentArticle && currentArticle.contentParts.length > 0) {
    articles.push({
      articleNumber: currentArticle.articleNumber,
      content: currentArticle.contentParts.join(" ").trim()
    });
  }

  return articles.filter((article) => article.content.length > 30);
};

const insertArticle = async (source, article) => {
  const sql = `
    INSERT INTO laws (
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
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
      title = VALUES(title),
      content = VALUES(content),
      tags = VALUES(tags),
      document_title = VALUES(document_title),
      law_reference = VALUES(law_reference),
      category = VALUES(category),
      source_name = VALUES(source_name),
      language = VALUES(language),
      imported_at = CURRENT_TIMESTAMP
  `;

  const title = `${source.documentTitle} - ${article.articleNumber}`;

  await pool.query(sql, [
    title,
    article.articleNumber,
    article.content,
    JSON.stringify(source.tags),
    source.documentTitle,
    source.lawReference,
    source.category,
    source.sourceName,
    source.sourceUrl,
    source.language || "fr"
  ]);
};

const importSource = async (source) => {
  const response = await fetch(source.sourceUrl);

  if (!response.ok) {
    throw new Error(`Failed to download ${source.documentTitle}: ${response.status}`);
  }

  const arrayBuffer = await response.arrayBuffer();
  const pdfData = await pdfParse(Buffer.from(arrayBuffer));
  const articles = parseArticlesFromText(pdfData.text);

  await pool.query("DELETE FROM laws WHERE source_url = ?", [source.sourceUrl]);

  let importedCount = 0;

  for (const article of articles) {
    await insertArticle(source, article);
    importedCount += 1;
  }

  return importedCount;
};

const importSources = async (sources, label) => {
  let totalImported = 0;
  const failures = [];

  for (const source of sources) {
    try {
      const count = await importSource(source);
      totalImported += count;
      console.log(`Imported ${count} articles from ${source.documentTitle}`);
    } catch (error) {
      failures.push(source.documentTitle);
      console.error(`Failed to import ${source.documentTitle}`);
      console.error(error);
    }
  }

  if (failures.length > 0) {
    console.log(`Skipped ${failures.length} source(s): ${failures.join(", ")}`);
  }

  if (failures.length === sources.length) {
    throw new Error(`All ${label} sources failed to import.`);
  }

  console.log(`Imported ${totalImported} ${label} articles in total.`);
  return totalImported;
};

module.exports = {
  importSource,
  importSources,
  parseArticlesFromText
};
