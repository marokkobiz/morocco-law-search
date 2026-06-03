const { pool } = require("../db/db");
const { importSources } = require("./lawImportService");
const { otherLawSources } = require("../data/otherLawSources");

const OFFICIAL_BULLETIN_CATEGORY = "official-bulletin";
const OFFICIAL_BULLETIN_SOURCE_NAME =
  "Secretariat General du Gouvernement - Bulletin officiel";
const SGG_BULLETIN_BASE_URL = "https://www.sgg.gov.ma/BO/FR/2873";
const DEFAULT_LOOKAHEAD = 80;
const DEFAULT_BACKFILL = 24;
const DEFAULT_TIMEOUT_MS = 8000;

let isUpdateRunning = false;
let updateTimer = null;

const toPositiveNumber = (value, fallback) => {
  const number = Number(value);
  return Number.isFinite(number) && number > 0 ? number : fallback;
};

const toNonNegativeNumber = (value, fallback) => {
  const number = Number(value);
  return Number.isFinite(number) && number >= 0 ? number : fallback;
};

const normalizeBulletinId = (number, suffix = "") => {
  const numeric = Number(number);
  if (!Number.isFinite(numeric) || numeric <= 0) {
    return null;
  }

  return `${numeric}${suffix ? "-bis" : ""}`;
};

const parseBulletinId = (value = "") => {
  const text = String(value);
  const match =
    text.match(/\bBO[_\s-]*(\d{3,5})(?:[-\s]*(bis))?/i) ||
    text.match(/bulletin officiel\D*(\d{3,5})(?:[-\s]*(bis))?/i);

  if (!match) {
    return null;
  }

  return normalizeBulletinId(match[1], match[2]);
};

const getBulletinNumber = (bulletinId = "") => {
  const match = String(bulletinId).match(/^(\d{3,5})/);
  return match ? Number(match[1]) : 0;
};

const compareBulletinSources = (left, right) => {
  const leftNumber = getBulletinNumber(parseBulletinId(left.lawReference) || left.bulletinId);
  const rightNumber = getBulletinNumber(parseBulletinId(right.lawReference) || right.bulletinId);

  if (leftNumber !== rightNumber) {
    return leftNumber - rightNumber;
  }

  return String(left.lawReference || "").localeCompare(String(right.lawReference || ""));
};

const buildOfficialBulletinSource = ({ bulletinId, sourceUrl, year }) => ({
  bulletinId,
  documentTitle: `Bulletin officiel n ${bulletinId} - Textes generaux`,
  lawReference: `BO n ${bulletinId}`,
  category: OFFICIAL_BULLETIN_CATEGORY,
  sourceName: OFFICIAL_BULLETIN_SOURCE_NAME,
  sourceUrl,
  tags: ["official-bulletin", "public-law", "administration", String(year)]
});

const getCatalogBulletinIds = () =>
  otherLawSources
    .filter((source) => source.category === OFFICIAL_BULLETIN_CATEGORY)
    .map((source) => parseBulletinId(source.lawReference) || parseBulletinId(source.sourceUrl))
    .filter(Boolean);

const getExistingBulletinSources = async () => {
  const [rows] = await pool.query(
    `
      SELECT
        source_url AS sourceUrl,
        MIN(document_title) AS documentTitle,
        MIN(law_reference) AS lawReference,
        MAX(imported_at) AS importedAt
      FROM laws
      WHERE category = ?
      GROUP BY source_url
    `,
    [OFFICIAL_BULLETIN_CATEGORY]
  );

  return rows
    .map((row) => {
      const bulletinId = parseBulletinId(row.lawReference) || parseBulletinId(row.sourceUrl);

      if (!bulletinId || !row.sourceUrl) {
        return null;
      }

      return {
        bulletinId,
        documentTitle: row.documentTitle || `Bulletin officiel n ${bulletinId} - Textes generaux`,
        lawReference: row.lawReference || `BO n ${bulletinId}`,
        category: OFFICIAL_BULLETIN_CATEGORY,
        sourceName: OFFICIAL_BULLETIN_SOURCE_NAME,
        sourceUrl: row.sourceUrl,
        tags: ["official-bulletin", "public-law", "administration"],
        importedAt: row.importedAt
      };
    })
    .filter(Boolean);
};

const getDiscoveryRange = (knownBulletinIds, options = {}) => {
  const currentYear = new Date().getFullYear();
  const knownNumbers = knownBulletinIds.map(getBulletinNumber).filter(Boolean);
  const highestKnownNumber = knownNumbers.length > 0 ? Math.max(...knownNumbers) : 7500;
  const lookAhead = toNonNegativeNumber(options.lookAhead, DEFAULT_LOOKAHEAD);
  const backfill = toNonNegativeNumber(options.backfill, DEFAULT_BACKFILL);
  const startNumber = Math.max(1, highestKnownNumber - backfill);
  const endNumber = highestKnownNumber + lookAhead;
  const years = Array.isArray(options.years) && options.years.length > 0
    ? options.years.map(Number).filter(Number.isFinite)
    : [currentYear, currentYear - 1];

  return {
    startNumber,
    endNumber,
    highestKnownNumber,
    years: [...new Set(years)]
  };
};

const buildCandidateUrls = (bulletinId, years) => {
  const fileSuffixes = ["fr", "Fr"];
  const urls = [];

  for (const year of years) {
    for (const fileSuffix of fileSuffixes) {
      urls.push(`${SGG_BULLETIN_BASE_URL}/${year}/BO_${bulletinId}_${fileSuffix}.pdf`);
    }
  }

  return urls;
};

const isPdfUrlReachable = async (url, timeoutMs = DEFAULT_TIMEOUT_MS) => {
  if (typeof fetch !== "function") {
    throw new Error("Official bulletin updates require Node.js 18+ global fetch support.");
  }

  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), timeoutMs);

  try {
    const response = await fetch(url, {
      method: "GET",
      headers: {
        Range: "bytes=0-0"
      },
      signal: controller.signal
    });
    const contentType = response.headers.get("content-type") || "";
    const reachable = [200, 206].includes(response.status) && /pdf/i.test(contentType);

    if (response.body && typeof response.body.cancel === "function") {
      await response.body.cancel();
    }

    return reachable;
  } catch {
    return false;
  } finally {
    clearTimeout(timeout);
  }
};

const findWorkingSggUrl = async (bulletinId, years, timeoutMs) => {
  const candidateUrls = buildCandidateUrls(bulletinId, years);

  for (const url of candidateUrls) {
    if (await isPdfUrlReachable(url, timeoutMs)) {
      return {
        url,
        year: Number(url.match(/\/(\d{4})\/BO_/)?.[1]) || new Date().getFullYear()
      };
    }
  }

  return null;
};

const mapWithConcurrency = async (items, concurrency, mapper) => {
  const results = new Array(items.length);
  let cursor = 0;

  const workers = Array.from({ length: Math.min(concurrency, items.length) }, async () => {
    while (cursor < items.length) {
      const index = cursor;
      cursor += 1;
      results[index] = await mapper(items[index], index);
    }
  });

  await Promise.all(workers);
  return results;
};

const discoverOfficialBulletinSources = async (options = {}) => {
  const existingSources = options.existingSources || await getExistingBulletinSources();
  const existingBulletinIds = new Set(existingSources.map((source) => source.bulletinId));
  const catalogBulletinIds = getCatalogBulletinIds();
  const knownBulletinIds = [...new Set([...existingBulletinIds, ...catalogBulletinIds])];
  const range = getDiscoveryRange(knownBulletinIds, options);
  const timeoutMs = toPositiveNumber(options.timeoutMs, DEFAULT_TIMEOUT_MS);
  const concurrency = toPositiveNumber(options.concurrency, 8);
  const candidateIds = [];

  for (let number = range.startNumber; number <= range.endNumber; number += 1) {
    const plainId = normalizeBulletinId(number);
    const bisId = normalizeBulletinId(number, "bis");

    if (plainId && !existingBulletinIds.has(plainId)) {
      candidateIds.push(plainId);
    }

    if (bisId && !existingBulletinIds.has(bisId)) {
      candidateIds.push(bisId);
    }
  }

  const discovered = await mapWithConcurrency(candidateIds, concurrency, async (bulletinId) => {
    const match = await findWorkingSggUrl(bulletinId, range.years, timeoutMs);

    if (!match) {
      return null;
    }

    return buildOfficialBulletinSource({
      bulletinId,
      sourceUrl: match.url,
      year: match.year
    });
  });

  const sources = discovered.filter(Boolean).sort(compareBulletinSources);

  return {
    ...range,
    checkedCount: candidateIds.length,
    sources
  };
};

const getRecentExistingSources = (sources, recentCount) =>
  [...sources]
    .sort((left, right) => getBulletinNumber(right.bulletinId) - getBulletinNumber(left.bulletinId))
    .slice(0, recentCount)
    .map((source) => ({
      documentTitle: source.documentTitle,
      lawReference: source.lawReference,
      category: OFFICIAL_BULLETIN_CATEGORY,
      sourceName: source.sourceName || OFFICIAL_BULLETIN_SOURCE_NAME,
      sourceUrl: source.sourceUrl,
      tags: source.tags || ["official-bulletin", "public-law", "administration"]
    }));

const importOfficialBulletinUpdates = async (options = {}) => {
  if (isUpdateRunning) {
    return {
      status: "skipped",
      reason: "official-bulletin-update-already-running",
      importedArticles: 0,
      importedSources: 0
    };
  }

  isUpdateRunning = true;

  try {
    const existingSources = await getExistingBulletinSources();
    const discovery = await discoverOfficialBulletinSources({
      ...options,
      existingSources
    });
    const recentReimportCount = options.reimportExisting
      ? toNonNegativeNumber(options.recentReimportCount, 6)
      : toNonNegativeNumber(options.recentReimportCount, 0);
    const recentSources = getRecentExistingSources(existingSources, recentReimportCount);
    const sourcesToImport = [...recentSources, ...discovery.sources];

    if (sourcesToImport.length === 0) {
      return {
        status: "current",
        checkedCount: discovery.checkedCount,
        importedArticles: 0,
        importedSources: 0,
        newSources: [],
        reimportedSources: []
      };
    }

    const importedArticles = await importSources(sourcesToImport, "official bulletin update");

    return {
      status: "updated",
      checkedCount: discovery.checkedCount,
      importedArticles,
      importedSources: sourcesToImport.length,
      newSources: discovery.sources.map((source) => source.lawReference),
      reimportedSources: recentSources.map((source) => source.lawReference)
    };
  } finally {
    isUpdateRunning = false;
  }
};

const scheduleOfficialBulletinUpdates = (options = {}) => {
  const intervalHours = Number(options.intervalHours || 0);
  const runOnStart = Boolean(options.runOnStart);

  if (!runOnStart && !(Number.isFinite(intervalHours) && intervalHours > 0)) {
    return null;
  }

  const runUpdate = async () => {
    try {
      const summary = await importOfficialBulletinUpdates(options);
      console.log("[law-updates] Official bulletin update summary:", summary);
    } catch (error) {
      console.error("[law-updates] Official bulletin update failed");
      console.error(error);
    }
  };

  if (runOnStart) {
    setTimeout(runUpdate, 1500);
  }

  if (Number.isFinite(intervalHours) && intervalHours > 0) {
    updateTimer = setInterval(runUpdate, intervalHours * 60 * 60 * 1000);
    return updateTimer;
  }

  return null;
};

module.exports = {
  OFFICIAL_BULLETIN_CATEGORY,
  buildOfficialBulletinSource,
  discoverOfficialBulletinSources,
  importOfficialBulletinUpdates,
  scheduleOfficialBulletinUpdates
};
