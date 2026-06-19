<?php

declare(strict_types=1);

loadEnv(dirname(__DIR__) . '/.env');
loadEnv(dirname(__DIR__) . '/public/.env');
require_once dirname(__DIR__) . '/public/app/bootstrap.php';

$pdo = db();
$applied = [];

if (!tableExists($pdo, 'categories') || !tableExists($pdo, 'art_pieces')) {
    fwrite(STDOUT, "Required tables not present; nothing to do.\n");
    exit(0);
}

if (!tableExists($pdo, 'art_piece_categories')) {
    $pdo->exec(
        'CREATE TABLE art_piece_categories (
            art_piece_id INT NOT NULL,
            category_id INT NOT NULL,
            PRIMARY KEY (art_piece_id, category_id),
            CONSTRAINT fk_art_piece_categories_piece FOREIGN KEY (art_piece_id) REFERENCES art_pieces(id) ON DELETE CASCADE,
            CONSTRAINT fk_art_piece_categories_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $applied[] = 'create art_piece_categories';
}

if (columnExists($pdo, 'categories', 'category_scope')) {
    $pdo->exec("UPDATE categories SET category_scope = 'portfolio' WHERE category_scope IS NULL OR category_scope = ''");
}

if (columnExists($pdo, 'art_pieces', 'category_id')) {
    $pdo->exec(
        "INSERT IGNORE INTO art_piece_categories (art_piece_id, category_id)
         SELECT id, category_id
         FROM art_pieces
         WHERE category_id IS NOT NULL"
    );
    $applied[] = 'backfill from art_pieces.category_id';
}

if ($applied === []) {
    fwrite(STDOUT, "No schema changes were needed.\n");
    exit(0);
}

foreach ($applied as $change) {
    fwrite(STDOUT, "[ok] {$change}\n");
}

function tableExists(PDO $pdo, string $tableName): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    $stmt->execute([$tableName]);
    return (bool) $stmt->fetchColumn();
}

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
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if ($name === '') {
            continue;
        }

        // Never let a .env file override a variable already set in the
        // process environment (e.g. exported by the caller for a test run)
        // — a prior version of this function overwrote unconditionally,
        // which could silently redirect a script meant for one database
        // onto whatever .env happens to be on disk.
        if (($_ENV[$name] ?? getenv($name) ?: '') === '') {
            $_ENV[$name] = $value;
            putenv($name . '=' . $value);
        }
    }
}

function columnExists(PDO $pdo, string $tableName, string $columnName): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?
         LIMIT 1'
    );
    $stmt->execute([$tableName, $columnName]);
    return (bool) $stmt->fetchColumn();
}
