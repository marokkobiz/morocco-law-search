<?php

declare(strict_types=1);

$sourcePath = $argv[1] ?? __DIR__ . '/../database/database.sqlite';
$outputPath = $argv[2] ?? __DIR__ . '/../database/mysql_dump.sql';

if (!is_file($sourcePath)) {
    fwrite(STDERR, "Source SQLite database not found: {$sourcePath}" . PHP_EOL);
    exit(1);
}

$pdo = new PDO('sqlite:' . $sourcePath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('PRAGMA foreign_keys = ON');

$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

$handle = fopen($outputPath, 'wb');

if ($handle === false) {
    fwrite(STDERR, "Unable to open output file: {$outputPath}" . PHP_EOL);
    exit(1);
}

writeLine($handle, '-- Generated from ' . basename($sourcePath) . ' on ' . date('c'));
writeLine($handle, 'SET NAMES utf8mb4;');
writeLine($handle, 'SET FOREIGN_KEY_CHECKS=0;');
writeLine($handle, 'START TRANSACTION;');
writeLine($handle, '');

foreach ($tables as $table) {
    $columns = $pdo->query("PRAGMA table_info('{$table}')")->fetchAll();
    $foreignKeys = $pdo->query("PRAGMA foreign_key_list('{$table}')")->fetchAll();
    $indexes = $pdo->query("PRAGMA index_list('{$table}')")->fetchAll();

    writeLine($handle, 'DROP TABLE IF EXISTS ' . identifier($table) . ';');
    writeLine($handle, 'CREATE TABLE ' . identifier($table) . ' (');

    $definitions = [];

    foreach ($columns as $column) {
        $definitions[] = '  ' . columnDefinition($column);
    }

    foreach ($foreignKeys as $foreignKey) {
        $definitions[] = '  ' . foreignKeyDefinition($foreignKey);
    }

    $primaryKeyColumns = array_values(array_filter($columns, static fn (array $column): bool => (int) $column['pk'] > 0));

    if ($primaryKeyColumns !== []) {
        usort($primaryKeyColumns, static fn (array $left, array $right): int => ((int) $left['pk']) <=> ((int) $right['pk']));
        $definitions[] = '  PRIMARY KEY (' . implode(', ', array_map(static fn (array $column): string => identifier((string) $column['name']), $primaryKeyColumns)) . ')';
    }

    writeLine($handle, implode(",\n", $definitions));
    writeLine($handle, ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
    writeLine($handle, '');

    foreach ($indexes as $index) {
        if (($index['origin'] ?? null) === 'pk') {
            continue;
        }

        $indexColumns = $pdo->query("PRAGMA index_info('{$index['name']}')")->fetchAll();
        $columnNames = array_map(static fn (array $column): string => identifier((string) $column['name']), $indexColumns);

        if ($columnNames === []) {
            continue;
        }

        $hasUrlColumn = false;
        foreach ($indexColumns as $idxCol) {
            $colName = strtolower((string) $idxCol['name']);
            if (str_contains($colName, 'url')) {
                $hasUrlColumn = true;
                break;
            }
        }
        $unique = ($hasUrlColumn || (int) $index['unique'] !== 1) ? '' : 'UNIQUE ';
        writeLine($handle, 'CREATE ' . $unique . 'INDEX ' . identifier((string) $index['name']) . ' ON ' . identifier($table) . ' (' . implode(', ', $columnNames) . ');');
    }

    writeLine($handle, '');

    $statement = $pdo->query('SELECT * FROM ' . identifier($table));
    $columnsList = array_map(static fn (array $column): string => identifier((string) $column['name']), $columns);
    $batchSize = 250;
    $batch = [];

    while ($row = $statement->fetch()) {
        $rowValues = [];

        foreach ($columns as $column) {
            $rowValues[] = mysqlValue($row[$column['name']], $column);
        }

        $batch[] = '(' . implode(', ', $rowValues) . ')';

        if (count($batch) === $batchSize) {
            writeLine($handle, 'INSERT INTO ' . identifier($table) . ' (' . implode(', ', $columnsList) . ') VALUES');
            writeLine($handle, implode(",\n", $batch) . ';');
            writeLine($handle, '');
            $batch = [];
        }
    }

    if ($batch !== []) {
        writeLine($handle, 'INSERT INTO ' . identifier($table) . ' (' . implode(', ', $columnsList) . ') VALUES');
        writeLine($handle, implode(",\n", $batch) . ';');
        writeLine($handle, '');
    }
}

writeLine($handle, 'COMMIT;');
writeLine($handle, 'SET FOREIGN_KEY_CHECKS=1;');

fclose($handle);

function writeLine($handle, string $line): void
{
    fwrite($handle, $line . PHP_EOL);
}

function identifier(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function columnDefinition(array $column): string
{
    $name = identifier((string) $column['name']);
    $declaredType = strtolower(trim((string) $column['type']));
    $notNull = ((int) $column['notnull'] === 1) ? ' NOT NULL' : ' NULL';
    $default = defaultValue($column);
    $autoIncrement = ((int) $column['pk'] === 1 && str_contains($declaredType, 'int')) ? ' AUTO_INCREMENT' : '';
    $type = mysqlType($column);

    return $name . ' ' . $type . $notNull . $default . $autoIncrement;
}

function mysqlType(array $column): string
{
    $declaredType = strtolower(trim((string) $column['type']));
    $name = strtolower((string) $column['name']);

    if (str_contains($declaredType, 'int')) {
        return 'BIGINT UNSIGNED';
    }

    if (str_contains($declaredType, 'datetime') || str_contains($declaredType, 'timestamp')) {
        return 'DATETIME';
    }

    if (str_contains($declaredType, 'date')) {
        return 'DATE';
    }

    if (str_contains($declaredType, 'text')) {
        return textType($name);
    }

    if (str_contains($declaredType, 'varchar') || $declaredType === '') {
        return varcharType($name);
    }

    return 'LONGTEXT';
}

function varcharType(string $name): string
{
    if (str_contains($name, 'url') || str_contains($name, 'path')) {
        return 'VARCHAR(500)';
    }

    return 'VARCHAR(255)';
}

function textType(string $name): string
{
    if (in_array($name, ['content', 'translated_content', 'raw_text', 'metadata', 'details', 'errors', 'payload', 'exception', 'options', 'failed_job_ids', 'embedding'], true)) {
        return 'LONGTEXT';
    }

    return 'TEXT';
}

function defaultValue(array $column): string
{
    if ($column['dflt_value'] === null) {
        return '';
    }

    $default = normalizeDefault((string) $column['dflt_value']);

    if ($default === 'CURRENT_TIMESTAMP') {
        return ' DEFAULT CURRENT_TIMESTAMP';
    }

    if (is_numeric($default)) {
        return ' DEFAULT ' . $default;
    }

    return ' DEFAULT ' . quote($default);
}

function normalizeDefault(string $value): string
{
    $value = trim($value);

    if (preg_match('/^\'(.*)\'$/s', $value, $matches) === 1) {
        return str_replace("''", "'", $matches[1]);
    }

    if (preg_match('/^\"(.*)\"$/s', $value, $matches) === 1) {
        return str_replace('""', '"', $matches[1]);
    }

    return $value;
}

function foreignKeyDefinition(array $foreignKey): string
{
    $onDelete = strtoupper((string) $foreignKey['on_delete']);
    $onUpdate = strtoupper((string) $foreignKey['on_update']);

    $parts = [
        'FOREIGN KEY (' . identifier((string) $foreignKey['from']) . ')',
        'REFERENCES ' . identifier((string) $foreignKey['table']) . ' (' . identifier((string) $foreignKey['to']) . ')',
    ];

    if ($onDelete !== 'NO ACTION') {
        $parts[] = 'ON DELETE ' . $onDelete;
    }

    if ($onUpdate !== 'NO ACTION') {
        $parts[] = 'ON UPDATE ' . $onUpdate;
    }

    return implode(' ', $parts);
}

function mysqlValue(mixed $value, array $column): string
{
    if ($value === null) {
        return 'NULL';
    }

    $declaredType = strtolower(trim((string) $column['type']));
    $name = strtolower((string) $column['name']);

    if (str_contains($declaredType, 'int')) {
        return (string) (int) $value;
    }

    $value = (string) $value;

    if (str_contains($name, 'url')) {
        $value = strtolower($value);
    }

    return quote($value);
}

function quote(string $value): string
{
    return "'" . str_replace(["\\", "'", "\x00", "\n", "\r", "\x1a"], ["\\\\", "\\'", "\\0", "\\n", "\\r", "\\Z"], $value) . "'";
}