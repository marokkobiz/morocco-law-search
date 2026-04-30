const { initializeDatabase, pool } = require("../db/db");
const { realEstateSources } = require("../data/realEstateSources");
const { importSources } = require("../services/lawImportService");

const importRealEstateLaws = async () => {
  await initializeDatabase();
  await importSources(realEstateSources, "real-estate law");
  await pool.end();
};

importRealEstateLaws().catch(async (error) => {
  console.error(error);
  await pool.end();
  process.exit(1);
});
