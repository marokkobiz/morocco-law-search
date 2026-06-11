<?php

declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: php scripts/sqlite_to_mysql_dump.php <sqlite-path> <mysql-dump-path> [--corpus-only]\n");
    exit(1);
}

$sqlitePath = $argv[1];
$dumpPath = $argv[2];
$corpusOnly = in_array('--corpus-only', $argv, true);

if (!is_file($sqlitePath)) {
    fwrite(STDERR, "SQLite database not found: {$sqlitePath}\n");
    exit(1);
}

$sqlite = new PDO('sqlite:'.$sqlitePath);
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sqlite->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$tables = $sqlite->query(
    "select name from sqlite_master where type = 'table' and name not like 'sqlite_%' order by name"
)->fetchAll(PDO::FETCH_COLUMN);

if ($corpusOnly) {
    $allowedTables = [
        'document_relations',
        'import_runs',
        'law_translations',
        'laws',
        'legal_articles',
        'legal_chunks',
        'legal_document_versions',
        'legal_documents',
        'legal_sources',
    ];
    $tables = array_values(array_intersect($allowedTables, $tables));
}

$out = fopen($dumpPath, 'wb');

if ($out === false) {
    fwrite(STDERR, "Unable to write dump: {$dumpPath}\n");
    exit(1);
}

fwrite($out, "-- Generated from {$sqlitePath}\n");
fwrite($out, "-- Mode: ".($corpusOnly ? 'corpus-only' : 'full')."\n");
fwrite($out, "SET FOREIGN_KEY_CHECKS=0;\n");
fwrite($out, "SET NAMES utf8mb4;\n\n");

foreach ($tables as $table) {
    $columns = $sqlite->query("PRAGMA table_info(".$sqlite->quote($table).")")->fetchAll();

    fwrite($out, "DROP TABLE IF EXISTS `{$table}`;\n");
    fwrite($out, "CREATE TABLE `{$table}` (\n");

    $definitions = [];
    $primaryKeys = [];

    foreach ($columns as $column) {
        $name = (string) $column['name'];
        $type = mysqlType((string) $column['type'], (int) $column['pk'], $name);
        $nullable = (int) $column['notnull'] === 1 ? ' NOT NULL' : ' NULL';
        $default = mysqlDefault($column['dflt_value']);
        $definitions[] = "  `{$name}` {$type}{$nullable}{$default}";

        if ((int) $column['pk'] > 0) {
            $primaryKeys[(int) $column['pk']] = $name;
        }
    }

    if ($primaryKeys) {
        ksort($primaryKeys);
        $definitions[] = '  PRIMARY KEY ('.implode(', ', array_map(fn ($key) => "`{$key}`", $primaryKeys)).')';
    }

    fwrite($out, implode(",\n", $definitions)."\n");
    fwrite($out, ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n");

    $count = (int) $sqlite->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    echo "{$table}: {$count} rows\n";

    if ($count === 0) {
        continue;
    }

    $columnNames = array_map(fn ($column) => (string) $column['name'], $columns);
    $quotedColumns = implode(', ', array_map(fn ($name) => "`{$name}`", $columnNames));
    $statement = $sqlite->query("SELECT * FROM `{$table}`");
    $batch = [];
    $batchSize = 250;

    while ($row = $statement->fetch()) {
        $values = [];

        foreach ($columnNames as $columnName) {
            $values[] = mysqlValue($row[$columnName]);
        }

        $batch[] = '('.implode(', ', $values).')';

        if (count($batch) >= $batchSize) {
            fwrite($out, "INSERT INTO `{$table}` ({$quotedColumns}) VALUES\n".implode(",\n", $batch).";\n");
            $batch = [];
        }
    }

    if ($batch) {
        fwrite($out, "INSERT INTO `{$table}` ({$quotedColumns}) VALUES\n".implode(",\n", $batch).";\n");
    }

    fwrite($out, "\n");
}

fwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n");
fclose($out);

echo "Dump written to {$dumpPath}\n";

function mysqlType(string $sqliteType, int $isPrimaryKey, string $name): string
{
    $type = strtoupper(trim($sqliteType));

    if ($isPrimaryKey === 1 && str_contains($type, 'INT')) {
        return 'BIGINT UNSIGNED';
    }

    if (str_contains($type, 'INT')) {
        return 'BIGINT';
    }

    if (str_contains($type, 'BOOL')) {
        return 'TINYINT(1)';
    }

    if (str_contains($type, 'REAL') || str_contains($type, 'FLOA') || str_contains($type, 'DOUB')) {
        return 'DOUBLE';
    }

    if (str_contains($type, 'DEC')) {
        return 'DECIMAL(20,6)';
    }

    if (str_contains($type, 'BLOB')) {
        return 'LONGBLOB';
    }

    if (str_contains($type, 'DATE') || str_contains($type, 'TIME')) {
        return 'VARCHAR(32)';
    }

    if (preg_match('/CHAR|CLOB|TEXT|VARCHAR/i', $type) === 1 || $type === '') {
        return match ($name) {
            'content', 'article_text', 'summary', 'notes', 'raw_text', 'embedding' => 'LONGTEXT',
            default => 'TEXT',
        };
    }

    return 'TEXT';
}

function mysqlDefault(mixed $default): string
{
    if ($default === null) {
        return '';
    }

    $value = trim((string) $default);

    if ($value === '' || strcasecmp($value, 'NULL') === 0) {
        return ' DEFAULT NULL';
    }

    if (is_numeric($value)) {
        return ' DEFAULT '.$value;
    }

    if (str_starts_with($value, "'") && str_ends_with($value, "'")) {
        return ' DEFAULT '.$value;
    }

    return '';
}

function mysqlValue(mixed $value): string
{
    if ($value === null) {
        return 'NULL';
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    return "'".str_replace(["\\", "'", "\0"], ["\\\\", "\\'", ''], (string) $value)."'";
}
