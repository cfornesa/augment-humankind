<?php

declare(strict_types=1);

// Applies docs/migrations/2026-06-19-media-draft-confirm.sql, which was
// written but never run against the live database (no apply script existed
// for it, unlike sibling media/portfolio migrations).

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

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if (strtolower(envValue('DB_SSL')) === 'true') {
        if (class_exists('Pdo\\Mysql') && defined('Pdo\\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')) {
            $options[constant('Pdo\\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')] = false;
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

function indexExists(PDO $pdo, string $table, string $indexName): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1');
    $stmt->execute([$table, $indexName]);
    return (bool) $stmt->fetchColumn();
}

function ensureColumn(PDO $pdo, string $table, string $column, string $definition): bool
{
    if (columnExists($pdo, $table, $column)) {
        return false;
    }
    $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    return true;
}

function ensureIndex(PDO $pdo, string $table, string $indexName, string $column): bool
{
    if (indexExists($pdo, $table, $indexName)) {
        return false;
    }
    $pdo->exec("ALTER TABLE `{$table}` ADD KEY `{$indexName}` (`{$column}`)");
    return true;
}

loadEnvFile(dirname(__DIR__) . '/.env');

$applied = [];

try {
    $pdo = targetPdo();

    if (!tableExists($pdo, 'media_files')) {
        throw new RuntimeException('Required table media_files does not exist.');
    }

    if (ensureColumn($pdo, 'media_files', 'status', "ENUM('draft', 'ready') NOT NULL DEFAULT 'ready' AFTER `alt_text`")) {
        $applied[] = 'media_files.status';
    }
    if (ensureColumn($pdo, 'media_files', 'poster_media_file_id', 'INT NULL DEFAULT NULL AFTER `status`')) {
        $applied[] = 'media_files.poster_media_file_id';
    }
    if (ensureColumn($pdo, 'media_files', 'confirmed_at', 'DATETIME NULL DEFAULT NULL AFTER `poster_media_file_id`')) {
        $applied[] = 'media_files.confirmed_at';
    }
    if (ensureIndex($pdo, 'media_files', 'idx_media_files_status', 'status')) {
        $applied[] = 'media_files.idx_media_files_status';
    }
    if (ensureIndex($pdo, 'media_files', 'idx_media_files_poster', 'poster_media_file_id')) {
        $applied[] = 'media_files.idx_media_files_poster';
    }

    $pdo->exec(
        "UPDATE media_files
         SET status = 'ready',
             confirmed_at = COALESCE(confirmed_at, created_at)
         WHERE status <> 'ready'
            OR confirmed_at IS NULL"
    );

    echo json_encode([
        'status' => 'ok',
        'applied' => $applied,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Schema application failed: " . $e->getMessage() . "\n");
    exit(1);
}
