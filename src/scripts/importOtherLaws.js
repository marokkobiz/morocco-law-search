const { initializeDatabase, pool } = require("../db/db");
const { otherLawSources } = require("../data/otherLawSources");
const { importSources } = require("../services/lawImportService");

const importOtherLaws = async () => {
  await initializeDatabase();
  await importSources(otherLawSources, "additional Moroccan law");
  await pool.end();
};

importOtherLaws().catch(async (error) => {
  console.error(error);
  await pool.end();
  process.exit(1);
});
