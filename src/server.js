const app = require("./app");
const { initializeDatabase } = require("./db/db");
const { scheduleOfficialBulletinUpdates } = require("./services/officialBulletinUpdateService");

const PORT = 3000;

const startServer = async () => {
  try {
    await initializeDatabase();

    app.listen(PORT, () => {
      console.log(`Server is running on port ${PORT}`);
    });

    scheduleOfficialBulletinUpdates({
      intervalHours: Number(process.env.LAW_UPDATE_INTERVAL_HOURS || 0),
      runOnStart: process.env.LAW_UPDATE_RUN_ON_START === "true",
      lookAhead: Number(process.env.BO_LOOKAHEAD || 80),
      backfill: Number(process.env.BO_BACKFILL || 24),
      timeoutMs: Number(process.env.BO_REQUEST_TIMEOUT_MS || 8000),
      concurrency: Number(process.env.BO_DISCOVERY_CONCURRENCY || 8),
      recentReimportCount: Number(process.env.BO_REIMPORT_RECENT || 0),
      reimportExisting: process.env.BO_REIMPORT_EXISTING === "true"
    });
  } catch (error) {
    console.error(error);
    process.exit(1);
  }
};

startServer();
