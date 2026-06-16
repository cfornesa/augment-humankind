<?php

declare(strict_types=1);

/**
 * One-time migration: move all art_piece and platform_collection thumbnails
 * that are stored as filesystem paths or external URLs into the media_files
 * LONGBLOB table and update each row's thumbnail_url to /image/{id}.
 *
 * Default mode is dry-run. Pass --execute to commit changes.
 *
 * Usage:
 *   php scripts/migrate-thumbnails-to-db.php
 *   php scripts/migrate-thumbnails-to-db.php --execute
 */

$execute = in_array('--execute', $argv ?? [], true);

// ── Bootstrap ────────────────────────────────────────────────────────────────

$scriptDir = dirname(__DIR__);

// Load .env — mirrors app: tries public/.env then the repo root (parent of public/)
function load_env(string $path): void
{
    if (!is_readable($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if ($k === '') {
            continue;
        }
        if (str_starts_with($v, '"') && str_ends_with($v, '"')) {
            $v = substr($v, 1, -1);
        } elseif (str_starts_with($v, "'") && str_ends_with($v, "'")) {
            $v = substr($v, 1, -1);
        }
        if (($_ENV[$k] ?? getenv($k) ?: '') === '') {
            $_ENV[$k] = $v;
            putenv($k . '=' . $v);
        }
    }
}

load_env($scriptDir . '/public/.env');
load_env($scriptDir . '/.env');

function env(string $key, string $default = ''): string
{
    $v = $_ENV[$key] ?? getenv($key);
    return is_string($v) && $v !== '' ? $v : $default;
}

// Guzzle (for fetching external URLs)
$autoload = $scriptDir . '/public/vendor/autoload.php';
if (!file_exists($autoload)) {
    fwrite(STDERR, "Composer autoloader not found at {$autoload}. Run: composer install\n");
    exit(1);
}
require $autoload;

// PDO connection to the PHP site DB
function make_pdo(): PDO
{
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $host = env('DB_HOST', '127.0.0.1');
    $port = env('DB_PORT', '3306');
    $name = env('DB_NAME');
    $user = env('DB_USER');
    $pass = env('DB_PASS');
    if ($name === '' || $user === '') {
        fwrite(STDERR, "DB_NAME and DB_USER must be set in .env\n");
        exit(1);
    }
    return new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $pass, $options);
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function insert_media_file(PDO $pdo, string $binary, string $mime, string $name): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO media_files (data, mime_type, byte_size, original_name) VALUES (?, ?, ?, ?)'
    );
    $stmt->bindParam(1, $binary, PDO::PARAM_LOB);
    $stmt->bindValue(2, $mime);
    $stmt->bindValue(3, mb_strlen($binary, '8bit'), PDO::PARAM_INT);
    $stmt->bindValue(4, $name);
    $stmt->execute();
    return (int) $pdo->lastInsertId();
}

function update_thumbnail(PDO $pdo, string $table, int $rowId, string $url, bool $execute): void
{
    if ($execute) {
        $pdo->prepare("UPDATE {$table} SET thumbnail_url = ? WHERE id = ?")->execute([$url, $rowId]);
    }
}

function migrate_row(
    PDO $pdo,
    string $table,
    int $rowId,
    string $currentUrl,
    string $publicRoot,
    bool $execute
): void {
    $label = "{$table}#{$rowId}";

    // Already in media_files
    if (str_starts_with($currentUrl, '/image/') || str_starts_with($currentUrl, '/media/')) {
        echo "  SKIP    {$label}: already in media_files ({$currentUrl})\n";
        return;
    }

    $binary = null;
    $mime   = 'image/png';

    if (preg_match('#^/api/media/([^/]+)$#', $currentUrl, $m)) {
        // Platform media asset served by the PHP app via media_assets.file_data
        $filename = $m[1];
        $stmt = $pdo->prepare('SELECT file_data, mime_type FROM media_assets WHERE filename = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$filename]);
        $asset = $stmt->fetch();
        if (!$asset || empty($asset['file_data'])) {
            echo "  WARN    {$label}: media_assets row not found or file_data empty for {$filename}, skipping\n";
            return;
        }
        $binary = is_resource($asset['file_data']) ? stream_get_contents($asset['file_data']) : $asset['file_data'];
        $mime   = $asset['mime_type'] ?: 'image/png';
    } elseif (str_starts_with($currentUrl, '/uploads/')) {
        // Filesystem path relative to public root
        $filePath = rtrim($publicRoot, '/') . $currentUrl;
        if (!is_readable($filePath)) {
            echo "  WARN    {$label}: file not found on disk ({$filePath}), skipping\n";
            return;
        }
        $binary = file_get_contents($filePath);
        if ($binary === false || strlen($binary) < 100) {
            echo "  WARN    {$label}: could not read or file too small ({$filePath}), skipping\n";
            return;
        }
        // Detect MIME from extension
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            default       => 'image/png',
        };
    } elseif (str_starts_with($currentUrl, 'http://') || str_starts_with($currentUrl, 'https://')) {
        // External URL — fetch it
        try {
            $client   = new GuzzleHttp\Client(['timeout' => 10.0, 'connect_timeout' => 5.0]);
            $response = $client->get($currentUrl);
            if ($response->getStatusCode() !== 200) {
                echo "  WARN    {$label}: HTTP {$response->getStatusCode()} fetching {$currentUrl}, skipping\n";
                return;
            }
            $binary = (string) $response->getBody();
            if (strlen($binary) < 100) {
                echo "  WARN    {$label}: fetched body too small from {$currentUrl}, skipping\n";
                return;
            }
            $ct = $response->getHeaderLine('Content-Type');
            if (str_contains($ct, 'jpeg') || str_contains($ct, 'jpg')) {
                $mime = 'image/jpeg';
            } elseif (str_contains($ct, 'gif')) {
                $mime = 'image/gif';
            } elseif (str_contains($ct, 'webp')) {
                $mime = 'image/webp';
            } else {
                $mime = 'image/png';
            }
        } catch (Throwable $e) {
            echo "  WARN    {$label}: fetch failed for {$currentUrl} — {$e->getMessage()}, skipping\n";
            return;
        }
    } else {
        echo "  WARN    {$label}: unrecognised URL pattern ({$currentUrl}), skipping\n";
        return;
    }

    if ($execute) {
        $mediaId  = insert_media_file($pdo, $binary, $mime, 'thumbnail.png');
        $newUrl   = '/image/' . $mediaId;
        update_thumbnail($pdo, $table, $rowId, $newUrl, true);

        // Delete local file after successful DB insert
        if (str_starts_with($currentUrl, '/uploads/')) {
            $filePath = rtrim($publicRoot, '/') . $currentUrl;
            if (is_file($filePath)) {
                @unlink($filePath);
            }
        }

        echo "  MIGRATE {$label}: {$currentUrl} → {$newUrl} (media_files id={$mediaId})\n";
    } else {
        $bytes = strlen($binary);
        echo "  DRY-RUN {$label}: would migrate {$currentUrl} ({$bytes} bytes, {$mime})\n";
    }
}

// ── Main ─────────────────────────────────────────────────────────────────────

echo $execute
    ? "=== Thumbnail migration (EXECUTE mode) ===\n\n"
    : "=== Thumbnail migration (DRY-RUN — pass --execute to commit) ===\n\n";

$pdo        = make_pdo();
$publicRoot = $scriptDir . '/public';

$tables = [
    'art_pieces'           => 'art_pieces',
    'platform_collections' => 'platform_collections',
];

$total  = 0;
$issues = 0;

foreach ($tables as $table => $_ ) {
    // Check table exists
    $exists = (bool) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '{$table}'"
    )->fetchColumn();
    if (!$exists) {
        echo "[{$table}] table not found, skipping.\n\n";
        continue;
    }

    $rows = $pdo->query(
        "SELECT id, thumbnail_url FROM {$table}
         WHERE thumbnail_url IS NOT NULL AND thumbnail_url != ''
           AND thumbnail_url NOT LIKE '/image/%'
           AND thumbnail_url NOT LIKE '/media/%'
         ORDER BY id ASC"
    )->fetchAll();

    if (empty($rows)) {
        echo "[{$table}] No rows need migration.\n\n";
        continue;
    }

    echo "[{$table}] " . count($rows) . " row(s) to process:\n";
    foreach ($rows as $row) {
        $total++;
        migrate_row($pdo, $table, (int) $row['id'], $row['thumbnail_url'], $publicRoot, $execute);
    }
    echo "\n";
}

// Clean up empty uploads/thumbnails directory if execute mode
if ($execute) {
    $thumbDir = $publicRoot . '/uploads/thumbnails';
    if (is_dir($thumbDir)) {
        $remaining = array_diff(scandir($thumbDir) ?: [], ['.', '..']);
        if (empty($remaining)) {
            rmdir($thumbDir);
            echo "Removed empty directory: {$thumbDir}\n";
        } else {
            echo "Note: {$thumbDir} still contains " . count($remaining) . " file(s) that were not migrated.\n";
        }
    }
}

echo $execute
    ? "\nDone. Processed {$total} thumbnail(s).\n"
    : "\nDry-run complete. {$total} thumbnail(s) would be processed. Run with --execute to commit.\n";
