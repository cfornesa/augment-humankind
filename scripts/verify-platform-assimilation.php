<?php

declare(strict_types=1);

// Load env from .env file
function loadEnv(string $path): void {
    if (!is_readable($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if ($name === '') continue;
        if (str_starts_with($value, '"') && str_ends_with($value, '"')) $value = substr($value, 1, -1);
        if (str_starts_with($value, "'") && str_ends_with($value, "'")) $value = substr($value, 1, -1);
        if (($_ENV[$name] ?? getenv($name) ?: '') !== '') continue;
        $_ENV[$name] = $value;
        putenv($name . '=' . $value);
    }
}

loadEnv(__DIR__ . '/../.env');
require __DIR__ . '/../public/app/config/database.php';

$pdo = db();

echo "=== TABLE COUNTS ===\n";
$tables = [
    'artworks', 'categories', 'exhibits', 'media_files', 'pages', 'page_sections',
    'navigation_items', 'users', 'accounts', 'sessions', 'posts', 'comments', 'reactions',
    'post_categories', 'feed_sources', 'feed_items_seen', 'site_settings', 'site_assets',
    'media_assets', 'profile_photo_assets', 'user_ai_vendor_settings', 'user_ai_vendor_keys',
    'platform_connections', 'platform_oauth_apps', 'post_syndications',
    'art_pieces', 'art_piece_versions', 'platform_exhibits', 'platform_exhibit_items',
    'platform_migration_map'
];
foreach ($tables as $table) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        echo "$table: $count\n";
    } catch (PDOException $e) {
        echo "$table: ERROR - " . $e->getMessage() . "\n";
    }
}

echo "\n=== MIGRATION MAP ===\n";
$mapCount = $pdo->query('SELECT COUNT(*) FROM platform_migration_map')->fetchColumn();
$mapEntities = $pdo->query('SELECT entity_type, COUNT(*) as c FROM platform_migration_map GROUP BY entity_type')->fetchAll(PDO::FETCH_ASSOC);
echo "Total map rows: $mapCount\n";
foreach ($mapEntities as $row) {
    echo "  {$row['entity_type']}: {$row['c']}\n";
}

echo "\n=== SAMPLES ===\n";
$art = $pdo->query('SELECT id, title FROM art_pieces WHERE deleted_at IS NULL LIMIT 1')->fetch();
echo "art_pieces: " . ($art ? "{$art['title']} (id: {$art['id']})" : 'none') . "\n";

$piece = $pdo->query('SELECT id, title FROM art_pieces WHERE deleted_at IS NULL AND status="active" LIMIT 1')->fetch();
echo "platform art piece: " . ($piece ? $piece['title'] : 'none') . "\n";

$ai = $pdo->query('SELECT COUNT(*) FROM user_ai_vendor_keys')->fetchColumn();
echo "AI keys stored: $ai\n";

$settings = $pdo->query('SELECT site_title FROM site_settings WHERE id=1')->fetch();
echo "site_settings: " . ($settings ? $settings['site_title'] : 'none') . "\n";

$versions = $pdo->query('SELECT COUNT(*) FROM art_piece_versions')->fetchColumn();
echo "art_piece_versions: $versions\n";

$exhibits = $pdo->query('SELECT COUNT(*) FROM platform_exhibits WHERE deleted_at IS NULL')->fetchColumn();
echo "platform_exhibits: $exhibits\n";

echo "\n=== GRACEFUL DEGRADATION TEST ===\n";
// Test a model with empty table
echo "Testing SiteSettings::current()...\n";
require __DIR__ . '/../public/app/models/SiteSettings.php';
$current = SiteSettings::current();
echo "site_settings row 1: " . ($current ? "yes (title: {$current['site_title']})" : "no") . "\n";

// Test PlatformArtPiece::all() with actual data
echo "Testing PlatformArtPiece::all()...\n";
require __DIR__ . '/../public/app/models/PlatformArtPiece.php';
$allPieces = PlatformArtPiece::all();
echo "Active art pieces: " . count($allPieces) . "\n";

// Test PlatformUser::owner()
echo "Testing PlatformUser::owner()...\n";
require __DIR__ . '/../public/app/models/PlatformUser.php';
$owner = PlatformUser::owner();
echo "Owner: " . ($owner ? $owner['name'] : 'none') . "\n";

echo "\n=== DATABASE CONNECTIVITY ===\n";
echo "PDO driver: " . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . "\n";
echo "Server version: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n";
