<?php

declare(strict_types=1);

/**
 * One-time repair for the 2 platform "system" nav_links (Feeds, Categories)
 * that were dropped by NavigationItem::removeDefunctSystemItems() after the
 * initial platform assimilation import.
 *
 * Default mode is dry-run. Use --execute to write to DB_*.
 * Usage: php scripts/repair-platform-nav-links.php [--execute]
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

$execute = in_array('--execute', $argv, true);

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
} catch (PDOException $e) {
    fwrite(STDERR, "Database error: " . $e->getMessage() . "\n");
    exit(1);
}

$check = $pdo->prepare('SELECT COUNT(*) FROM navigation_items WHERE platform_source_id IN (1, 2)');
$check->execute();
if ((int) $check->fetchColumn() > 0) {
    echo "Already repaired — navigation_items rows with platform_source_id 1/2 exist. Nothing to do.\n";
    exit(0);
}

$rows = [
    1 => ['label' => 'Feeds', 'url' => '/blog/feeds', 'original' => '/feeds', 'sort_order' => 70],
    2 => ['label' => 'Categories', 'url' => '/blog/categories', 'original' => '/categories', 'sort_order' => 80],
];

$insert = $pdo->prepare(
    "INSERT INTO navigation_items
        (source_type, system_key, page_id, label, url, target, is_visible, sort_order,
         platform_source_id, platform_original_url, platform_kind, open_in_new_tab, created_at, updated_at)
     VALUES ('external', NULL, NULL, ?, ?, NULL, 1, ?, ?, ?, 'system', 0, NOW(), NOW())"
);
$updateMap = $pdo->prepare(
    "UPDATE platform_migration_map SET target_id = ? WHERE entity_type = 'nav_links' AND source_id = ?"
);

foreach ($rows as $sourceId => $row) {
    if (!$execute) {
        echo "[dry-run] Would insert navigation_items: {$row['label']} -> {$row['url']} (sort_order={$row['sort_order']}, platform_source_id={$sourceId})\n";
        echo "[dry-run] Would update platform_migration_map (entity_type=nav_links, source_id={$sourceId}) -> new navigation_items id\n";
        continue;
    }

    $insert->execute([$row['label'], $row['url'], $row['sort_order'], $sourceId, $row['original']]);
    $newId = $pdo->lastInsertId();
    $updateMap->execute([$newId, (string) $sourceId]);
    echo "Inserted navigation_items id={$newId} for {$row['label']} -> {$row['url']}; updated platform_migration_map source_id={$sourceId}.\n";
}

if (!$execute) {
    echo "Dry run complete. Re-run with --execute to apply.\n";
}
