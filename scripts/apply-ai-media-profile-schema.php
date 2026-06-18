<?php

declare(strict_types=1);

function loadEnv(string $path): void
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
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }
        if (($_ENV[$name] ?? getenv($name) ?: '') === '') {
            $_ENV[$name] = $value;
            putenv($name . '=' . $value);
        }
    }
}

function envGet(string $key, string $default = ''): string
{
    $value = $_ENV[$key] ?? getenv($key);
    return is_string($value) && $value !== '' ? $value : $default;
}

function targetPdo(): PDO
{
    $host = envGet('DB_HOST', 'localhost');
    $port = envGet('DB_PORT', '3306');
    $name = envGet('DB_NAME');
    $user = envGet('DB_USER');
    $pass = envGet('DB_PASS');

    if ($name === '' || $user === '') {
        throw new RuntimeException('Missing DB_NAME or DB_USER in .env');
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if (strtolower(envGet('DB_SSL')) === 'true') {
        if (class_exists('Pdo\\Mysql') && defined('Pdo\\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')) {
            $options[constant('Pdo\\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')] = false;
        } elseif (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
            $options[constant('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')] = false;
        }
    }

    return new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        $options
    );
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
    );
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}

function ensureTable(PDO $pdo, string $table, string $sql): void
{
    if (!tableExists($pdo, $table)) {
        $pdo->exec($sql);
        echo "Created table {$table}\n";
    } else {
        echo "Table {$table} already present\n";
    }
}

function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!columnExists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        echo "Added {$table}.{$column}\n";
    } else {
        echo "Column {$table}.{$column} already present\n";
    }
}

loadEnv(dirname(__DIR__) . '/.env');

$targetDb = envGet('DB_NAME');
$platformDb = envGet('PLATFORM_DB_NAME');
if ($targetDb === '' || $platformDb === '') {
    throw new RuntimeException('Both DB_NAME and PLATFORM_DB_NAME must be configured.');
}
if ($targetDb === $platformDb) {
    throw new RuntimeException('Refusing to run because DB_NAME and PLATFORM_DB_NAME are the same.');
}

$pdo = targetPdo();

ensureTable(
    $pdo,
    'ai_personas',
    "CREATE TABLE `ai_personas` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `name` VARCHAR(128) NOT NULL,
        `system_prompt` TEXT NOT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY `idx_ai_personas_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

ensureColumn($pdo, 'user_ai_vendor_settings', 'capabilities', "VARCHAR(128) NOT NULL DEFAULT 'text,code' AFTER `enabled`");
ensureColumn($pdo, 'art_pieces', 'thumbnail_alt_text', "VARCHAR(500) NULL DEFAULT NULL AFTER `thumbnail_url`");
ensureColumn($pdo, 'media_files', 'title', "VARCHAR(255) NULL DEFAULT NULL AFTER `original_name`");
ensureColumn($pdo, 'media_files', 'alt_text', "VARCHAR(500) NULL DEFAULT NULL");

ensureColumn($pdo, 'users', 'theme', 'VARCHAR(32) NULL');
ensureColumn($pdo, 'users', 'palette', 'VARCHAR(32) NULL');
ensureColumn($pdo, 'users', 'color_background', 'VARCHAR(64) NULL');
ensureColumn($pdo, 'users', 'color_foreground', 'VARCHAR(64) NULL');
ensureColumn($pdo, 'users', 'color_background_dark', 'VARCHAR(64) NULL');
ensureColumn($pdo, 'users', 'color_foreground_dark', 'VARCHAR(64) NULL');
ensureColumn($pdo, 'users', 'color_primary', 'VARCHAR(64) NULL');
ensureColumn($pdo, 'users', 'color_primary_foreground', 'VARCHAR(64) NULL');
ensureColumn($pdo, 'users', 'color_secondary', 'VARCHAR(64) NULL');
ensureColumn($pdo, 'users', 'color_secondary_foreground', 'VARCHAR(64) NULL');
ensureColumn($pdo, 'users', 'color_accent', 'VARCHAR(64) NULL');
ensureColumn($pdo, 'users', 'color_accent_foreground', 'VARCHAR(64) NULL');
ensureColumn($pdo, 'users', 'color_muted', 'VARCHAR(64) NULL');
ensureColumn($pdo, 'users', 'color_muted_foreground', 'VARCHAR(64) NULL');
ensureColumn($pdo, 'users', 'color_destructive', 'VARCHAR(64) NULL');
ensureColumn($pdo, 'users', 'color_destructive_foreground', 'VARCHAR(64) NULL');

ensureColumn($pdo, 'users', 'preferred_art_piece_profile_id', 'INT NULL');
ensureColumn($pdo, 'users', 'preferred_text_improve_profile_id', 'INT NULL');
ensureColumn($pdo, 'users', 'preferred_alt_text_profile_id', 'INT NULL');

if (columnExists($pdo, 'users', 'preferred_art_piece_vendor')) {
    $pdo->exec(
        "UPDATE users u
            JOIN user_ai_vendor_settings s
              ON s.user_id = u.id AND s.vendor = u.preferred_art_piece_vendor
           SET u.preferred_art_piece_profile_id = s.id
         WHERE u.preferred_art_piece_vendor IS NOT NULL
           AND u.preferred_art_piece_profile_id IS NULL"
    );
    echo "Backfilled preferred_art_piece_profile_id from preferred_art_piece_vendor\n";
}

if (columnExists($pdo, 'users', 'preferred_vendor_text_improve')) {
    $pdo->exec(
        "UPDATE users u
            JOIN user_ai_vendor_settings s
              ON s.user_id = u.id AND s.vendor = u.preferred_vendor_text_improve
           SET u.preferred_text_improve_profile_id = s.id
         WHERE u.preferred_vendor_text_improve IS NOT NULL
           AND u.preferred_text_improve_profile_id IS NULL"
    );
    echo "Backfilled preferred_text_improve_profile_id from preferred_vendor_text_improve\n";
}

if (columnExists($pdo, 'users', 'preferred_vendor_alt_text')) {
    $pdo->exec(
        "UPDATE users u
            JOIN user_ai_vendor_settings s
              ON s.user_id = u.id AND s.vendor = u.preferred_vendor_alt_text
           SET u.preferred_alt_text_profile_id = s.id
         WHERE u.preferred_vendor_alt_text IS NOT NULL
           AND u.preferred_alt_text_profile_id IS NULL"
    );
    echo "Backfilled preferred_alt_text_profile_id from preferred_vendor_alt_text\n";
}

echo "Schema apply complete for target database {$targetDb}\n";
