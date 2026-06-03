const { initializeDatabase, pool } = require("../db/db");
const { otherLawSources } = require("../data/otherLawSources");
const { importSources } = require("../services/lawImportService");

const importPendingOtherLaws = async () => {
  await initializeDatabase();

  const [rows] = await pool.query("SELECT DISTINCT source_url FROM laws WHERE source_url IS NOT NULL AND source_url <> ''");
  const existingSourceUrls = new Set(rows.map((row) => row.source_url));
  const pendingSources = otherLawSources.filter((source) => !existingSourceUrls.has(source.sourceUrl));

  if (pendingSources.length === 0) {
    console.log("No pending law sources to import.");
    await pool.end();
    return;
  }

  console.log(`Importing ${pendingSources.length} pending law source(s)...`);
  await importSources(pendingSources, "pending Moroccan law");
  await pool.end();
};

importPendingOtherLaws().catch(async (error) => {
  console.error(error);
  await pool.end();
  process.exit(1);
});
