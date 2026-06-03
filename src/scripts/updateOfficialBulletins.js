const { initializeDatabase, pool } = require("../db/db");
const { importOfficialBulletinUpdates } = require("../services/officialBulletinUpdateService");

const getBooleanArg = (name) => process.argv.includes(name);

const getNumberArg = (name, fallback) => {
  const prefix = `${name}=`;
  const rawArg = process.argv.find((arg) => arg.startsWith(prefix));
  const rawValue = rawArg ? rawArg.slice(prefix.length) : undefined;
  const value = Number(rawValue || fallback);

  return Number.isFinite(value) && value >= 0 ? value : fallback;
};

const run = async () => {
  await initializeDatabase();

  const summary = await importOfficialBulletinUpdates({
    lookAhead: getNumberArg("--lookahead", Number(process.env.BO_LOOKAHEAD || 80)),
    backfill: getNumberArg("--backfill", Number(process.env.BO_BACKFILL || 24)),
    timeoutMs: getNumberArg("--timeout-ms", Number(process.env.BO_REQUEST_TIMEOUT_MS || 8000)),
    concurrency: getNumberArg("--concurrency", Number(process.env.BO_DISCOVERY_CONCURRENCY || 8)),
    recentReimportCount: getNumberArg("--recent", Number(process.env.BO_REIMPORT_RECENT || 0)),
    reimportExisting: getBooleanArg("--reimport-existing")
  });

  console.log(JSON.stringify(summary, null, 2));
};

run()
  .catch((error) => {
    console.error(error);
    process.exitCode = 1;
  })
  .finally(async () => {
    await pool.end();
  });
