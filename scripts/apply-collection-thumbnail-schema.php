<?php

declare(strict_types=1);

loadEnv(dirname(__DIR__) . '/.env');
loadEnv(dirname(__DIR__) . '/public/.env');
require_once dirname(__DIR__) . '/public/app/bootstrap.php';

$pdo = db();
$applied = [];

if (!tableExists($pdo, 'platform_collections')) {
    fwrite(STDOUT, "platform_collections table not present; nothing to do.\n");
    exit(0);
}

if (!columnExists($pdo, 'platform_collections', 'thumbnail_url')) {
    $pdo->exec(
        'ALTER TABLE platform_collections ADD COLUMN thumbnail_url VARCHAR(500) NULL AFTER iframe_code'
    );
    $applied[] = 'add thumbnail_url column to platform_collections';
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
        // process environment — a prior version of this function
        // overwrote unconditionally, which could silently redirect a
        // script meant for one database onto whatever .env is on disk.
        if (($_ENV[$name] ?? getenv($name) ?: '') !== '') {
            continue;
        }

        $_ENV[$name] = $value;
        putenv($name . '=' . $value);
    }
}
