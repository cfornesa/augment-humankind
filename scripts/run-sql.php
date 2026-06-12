<?php

declare(strict_types=1);

/**
 * Database seed/migration utility for Augment Humankind.
 * Run this to apply SQL files to the remote database using PDO.
 *
 * Usage: php scripts/run-sql.php seed_homepage.sql
 *        php scripts/run-sql.php scripts/migrate-home-nav-label.sql
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

        $existingValue = $_ENV[$name] ?? getenv($name);
        if (is_string($existingValue) && $existingValue !== '') {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $_ENV[$name] = $value;
        putenv($name . '=' . $value);
    }
}

$envPath = dirname(__DIR__) . '/.env';
if (!is_readable($envPath)) {
    fwrite(STDERR, "Could not read .env file at {$envPath}\n");
    exit(1);
}

loadEnvFile($envPath);

$host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
$name = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: '';
$user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: '';
$pass = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '';

if ($name === '' || $user === '') {
    fwrite(STDERR, "Missing DB_NAME or DB_USER in .env\n");
    exit(1);
}

$sqlFile = $argv[1] ?? '';
if ($sqlFile === '' || !is_readable($sqlFile)) {
    fwrite(STDERR, "Usage: php scripts/run-sql.php <path-to-sql-file>\n");
    exit(1);
}

$sql = file_get_contents($sqlFile);
if ($sql === false) {
    fwrite(STDERR, "Could not read SQL file: {$sqlFile}\n");
    exit(1);
}

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    // Execute multiple statements
    $pdo->exec($sql);
    echo "SQL applied successfully.\n";
} catch (PDOException $e) {
    fwrite(STDERR, "Database error: " . $e->getMessage() . "\n");
    exit(1);
}
