<?php

declare(strict_types=1);

/**
 * Applies the additive platform assimilation schema to the current PHP target DB.
 *
 * This script connects only to DB_* and never opens a PLATFORM_* connection.
 * It is intentionally idempotent: columns, indexes, and tables are checked
 * before creation so it can be rerun after partial success.
 */

function loadEnvFile(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if ($name === '') {
            continue;
        }

        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }

        if (($_ENV[$name] ?? getenv($name) ?: '') === '') {
            $_ENV[$name] = $value;
            putenv($name . '=' . $value);
        }
    }
}

function envValue(string $key, string $default = ''): string
{
    $value = $_ENV[$key] ?? getenv($key);
    return is_string($value) && $value !== '' ? $value : $default;
}

function targetPdo(): PDO
{
    $host = envValue('DB_HOST', 'localhost');
    $port = envValue('DB_PORT', '3306');
    $name = envValue('DB_NAME');
    $user = envValue('DB_USER');
    $pass = envValue('DB_PASS');

    if ($name === '' || $user === '') {
        throw new RuntimeException('Missing DB_NAME or DB_USER.');
    }

    $platformName = envValue('PLATFORM_DB_NAME');
    if ($platformName !== '' && $platformName === $name) {
        throw new RuntimeException('Refusing to apply schema: DB_NAME and PLATFORM_DB_NAME are identical.');
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if (strtolower(envValue('DB_SSL')) === 'true') {
        if (class_exists('Pdo\Mysql') && defined('Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')) {
            $options[constant('Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')] = false;
        } elseif (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
            $options[constant('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')] = false;
        }
    }

    return new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $pass, $options);
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}

function indexExists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1');
    $stmt->execute([$table, $index]);
    return (bool) $stmt->fetchColumn();
}

function addColumn(PDO $pdo, string $table, string $column, string $definition, array &$applied): void
{
    if (!tableExists($pdo, $table)) {
        throw new RuntimeException("Required existing table {$table} does not exist.");
    }
    if (columnExists($pdo, $table, $column)) {
        return;
    }
    $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    $applied[] = "column {$table}.{$column}";
}

function addIndex(PDO $pdo, string $table, string $index, string $definition, array &$applied): void
{
    if (!tableExists($pdo, $table)) {
        throw new RuntimeException("Required existing table {$table} does not exist.");
    }
    if (indexExists($pdo, $table, $index)) {
        return;
    }
    $pdo->exec("ALTER TABLE `{$table}` ADD {$definition}");
    $applied[] = "index {$table}.{$index}";
}

function createTablesFromSql(PDO $pdo, string $sqlPath, array &$applied): void
{
    $sql = file_get_contents($sqlPath);
    if ($sql === false) {
        throw new RuntimeException("Could not read {$sqlPath}.");
    }

    $sql = str_replace('id INT NOT NULL PRIMARY KEY DEFAULT 1', 'id INT NOT NULL PRIMARY KEY', $sql);
    preg_match_all('/CREATE TABLE IF NOT EXISTS `?([a-z0-9_]+)`?\s*\((?:.|\n)*?\)\s*ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;/i', $sql, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $table = $match[1];
        if (tableExists($pdo, $table)) {
            continue;
        }
        $pdo->exec($match[0]);
        $applied[] = "table {$table}";
    }
}

loadEnvFile(dirname(__DIR__) . '/.env');

$dryRun = in_array('--dry-run', $argv, true);
$applied = [];

try {
    $pdo = targetPdo();
    $pdo->exec('SET NAMES utf8mb4');

    if ($dryRun) {
        echo "Connected to target DB. Dry run does not apply schema changes.\n";
        exit(0);
    }

    addColumn($pdo, 'categories', 'category_scope', "VARCHAR(32) NOT NULL DEFAULT 'portfolio'", $applied);
    addColumn($pdo, 'categories', 'platform_source_id', 'INT NULL', $applied);
    addColumn($pdo, 'categories', 'platform_original_slug', 'VARCHAR(191) NULL', $applied);
    addColumn($pdo, 'categories', 'platform_created_at', 'DATETIME(3) NULL', $applied);
    addColumn($pdo, 'categories', 'platform_updated_at', 'DATETIME(3) NULL', $applied);
    addColumn($pdo, 'categories', 'created_at', 'DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)', $applied);
    addColumn($pdo, 'categories', 'updated_at', 'DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)', $applied);
    addIndex($pdo, 'categories', 'idx_categories_scope', 'INDEX `idx_categories_scope` (`category_scope`)', $applied);
    addIndex($pdo, 'categories', 'uniq_categories_platform_source', 'UNIQUE KEY `uniq_categories_platform_source` (`category_scope`, `platform_source_id`)', $applied);
    $pdo->exec("UPDATE categories SET category_scope = 'portfolio' WHERE category_scope IS NULL OR category_scope = ''");

    addColumn($pdo, 'pages', 'platform_source_id', 'INT NULL', $applied);
    addColumn($pdo, 'pages', 'platform_original_slug', 'VARCHAR(96) NULL', $applied);
    addColumn($pdo, 'pages', 'content_format', "VARCHAR(16) NOT NULL DEFAULT 'html'", $applied);
    addColumn($pdo, 'pages', 'content_text', 'TEXT NULL', $applied);
    addColumn($pdo, 'pages', 'author_user_id', 'VARCHAR(191) NULL', $applied);
    addColumn($pdo, 'pages', 'platform_created_at', 'DATETIME(3) NULL', $applied);
    addColumn($pdo, 'pages', 'platform_updated_at', 'DATETIME(3) NULL', $applied);
    addIndex($pdo, 'pages', 'uniq_pages_platform_source', 'UNIQUE KEY `uniq_pages_platform_source` (`platform_source_id`)', $applied);

    addColumn($pdo, 'navigation_items', 'platform_source_id', 'INT NULL', $applied);
    addColumn($pdo, 'navigation_items', 'platform_original_url', 'VARCHAR(2048) NULL', $applied);
    addColumn($pdo, 'navigation_items', 'platform_kind', 'VARCHAR(32) NULL', $applied);
    addColumn($pdo, 'navigation_items', 'open_in_new_tab', 'TINYINT(1) NOT NULL DEFAULT 0', $applied);
    addIndex($pdo, 'navigation_items', 'uniq_navigation_platform_source', 'UNIQUE KEY `uniq_navigation_platform_source` (`platform_source_id`)', $applied);

    createTablesFromSql($pdo, dirname(__DIR__) . '/migrations/2026-06-14-platform-assimilation.sql', $applied);

    addColumn($pdo, 'platform_collections', 'iframe_code', 'TEXT NULL', $applied);
    addColumn($pdo, 'platform_collections', 'thumbnail_url', 'VARCHAR(500) NULL AFTER iframe_code', $applied);

    addColumn($pdo, 'platform_connections', 'access_token_format', "VARCHAR(32) NOT NULL DEFAULT 'platform_encrypted'", $applied);
    addColumn($pdo, 'platform_connections', 'refresh_token_format', "VARCHAR(32) NOT NULL DEFAULT 'platform_encrypted'", $applied);

    echo json_encode([
        'status' => 'ok',
        'applied' => $applied,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Schema application failed: " . $e->getMessage() . "\n");
    exit(1);
}
