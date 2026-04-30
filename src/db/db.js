require("dotenv").config();

const fs = require("fs");
const path = require("path");
const mysql = require("mysql2/promise");

const dbConfig = {
  user: process.env.DB_USER || "root",
  host: process.env.DB_HOST || "localhost",
  database: process.env.DB_NAME || "morocco_law_search",
  password: process.env.DB_PASSWORD || "",
  port: Number(process.env.DB_PORT) || 3306,
};

const pool = mysql.createPool({
  ...dbConfig,
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0
});

const ensureColumn = async (connection, tableName, columnName, definition) => {
  const [rows] = await connection.query(
    `SHOW COLUMNS FROM \`${tableName}\` LIKE ?`,
    [columnName]
  );

  if (rows.length === 0) {
    await connection.query(
      `ALTER TABLE \`${tableName}\` ADD COLUMN \`${columnName}\` ${definition}`
    );
  }
};

const ensureIndex = async (connection, tableName, indexName, createSql) => {
  const [rows] = await connection.query(
    `SHOW INDEX FROM \`${tableName}\` WHERE Key_name = ?`,
    [indexName]
  );

  if (rows.length === 0) {
    await connection.query(createSql);
  }
};

const runSchemaFile = async (connection, schemaSql) => {
  const statements = schemaSql
    .split(";")
    .map((statement) => statement.trim())
    .filter(Boolean);

  for (const statement of statements) {
    await connection.query(statement);
  }
};

const initializeDatabase = async () => {
  const connection = await mysql.createConnection({
    user: dbConfig.user,
    host: dbConfig.host,
    password: dbConfig.password,
    port: dbConfig.port
  });

  try {
    await connection.query(
      `CREATE DATABASE IF NOT EXISTS \`${dbConfig.database}\``
    );

    const schemaPath = path.join(__dirname, "schema.sql");
    const schemaSql = fs.readFileSync(schemaPath, "utf8");

    await connection.query(`USE \`${dbConfig.database}\``);
    await runSchemaFile(connection, schemaSql);
    await ensureColumn(connection, "laws", "document_title", "VARCHAR(255) NULL");
    await ensureColumn(connection, "laws", "law_reference", "VARCHAR(100) NULL");
    await ensureColumn(connection, "laws", "category", "VARCHAR(100) NULL");
    await ensureColumn(connection, "laws", "source_name", "VARCHAR(255) NULL");
    await ensureColumn(connection, "laws", "source_url", "VARCHAR(512) NULL");
    await ensureColumn(connection, "laws", "language", "VARCHAR(10) NOT NULL DEFAULT 'fr'");
    await ensureColumn(
      connection,
      "laws",
      "imported_at",
      "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP"
    );
    await ensureIndex(
      connection,
      "laws",
      "uniq_law_source_article",
      "CREATE UNIQUE INDEX uniq_law_source_article ON laws (source_url, article_number)"
    );
    await ensureIndex(
      connection,
      "law_translations",
      "uniq_law_translation",
      "CREATE UNIQUE INDEX uniq_law_translation ON law_translations (law_id, target_language)"
    );
  } finally {
    await connection.end();
  }
};

module.exports = {
  pool,
  initializeDatabase
};
