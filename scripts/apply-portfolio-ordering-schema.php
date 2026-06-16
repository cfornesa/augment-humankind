<?php

declare(strict_types=1);

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

function ensureSortOrderColumn(PDO $pdo, string $table): bool
{
    if (!tableExists($pdo, $table)) {
        throw new RuntimeException("Required table {$table} does not exist.");
    }

    if (columnExists($pdo, $table, 'sort_order')) {
        return false;
    }

    $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0");
    return true;
}

function backfillSortOrder(PDO $pdo, string $table, string $idColumn = 'id'): void
{
    $rows = $pdo->query("SELECT `{$idColumn}` FROM `{$table}` ORDER BY created_at DESC, `{$idColumn}` DESC")->fetchAll();
    $update = $pdo->prepare("UPDATE `{$table}` SET sort_order = ? WHERE `{$idColumn}` = ?");

    foreach ($rows as $index => $row) {
        $update->execute([$index, $row[$idColumn]]);
    }
}

loadEnvFile(dirname(__DIR__) . '/.env');

$applied = [];

try {
    $pdo = targetPdo();

    if (ensureSortOrderColumn($pdo, 'platform_collections')) {
        backfillSortOrder($pdo, 'platform_collections');
        $applied[] = 'platform_collections.sort_order';
    }

    if (ensureSortOrderColumn($pdo, 'art_pieces')) {
        backfillSortOrder($pdo, 'art_pieces');
        $applied[] = 'art_pieces.sort_order';
    }

    echo json_encode([
        'status' => 'ok',
        'applied' => $applied,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Schema application failed: " . $e->getMessage() . "\n");
    exit(1);
}
