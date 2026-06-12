<?php

// One-time data migration: read image files from public/uploads/ and store as
// BLOB in media_files. Updates path references in artworks, categories, exhibits.
//
// Run from project root:
//   php -d memory_limit=256M migrate_images_to_blob.php
//
// Always run with DRY_RUN = true first, then set to false to commit changes.

declare(strict_types=1);

ini_set('memory_limit', '256M');

const DRY_RUN = false;

require __DIR__ . '/app/bootstrap.php';

$pdo = db();
try { $pdo->exec('SET SESSION max_allowed_packet = 67108864'); } catch (\Exception $e) {}

$rows = $pdo->query(
    'SELECT id, path, subfolder FROM media_files WHERE data IS NULL ORDER BY id'
)->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    echo "Nothing to migrate — all rows already have blob data.\n";
    exit(0);
}

echo (DRY_RUN ? "[DRY RUN] " : "") . "Migrating " . count($rows) . " file(s)...\n\n";

$updated = 0;
$skipped = 0;
$failed  = 0;

foreach ($rows as $row) {
    $physicalPath = __DIR__ . '/public' . $row['path'];

    if (!is_file($physicalPath)) {
        echo "SKIP  [{$row['id']}] {$row['path']} (file not found on disk)\n";
        $skipped++;
        continue;
    }

    $blob = file_get_contents($physicalPath);
    if ($blob === false) {
        echo "FAIL  [{$row['id']}] {$row['path']} (could not read file)\n";
        $failed++;
        continue;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->buffer($blob) ?: 'application/octet-stream';

    $newPath = '/image/' . $row['id'];

    if (!DRY_RUN) {
        $stmt = $pdo->prepare(
            'UPDATE media_files SET data = ?, mime_type = ? WHERE id = ?'
        );
        $stmt->bindParam(1, $blob, PDO::PARAM_LOB);
        $stmt->bindValue(2, $mime);
        $stmt->bindValue(3, $row['id'], PDO::PARAM_INT);
        $stmt->execute();
        unset($blob);

        $pdo->prepare(
            "UPDATE artworks SET thumbnail_value = ? WHERE thumbnail_type = 'upload' AND thumbnail_value = ?"
        )->execute([$newPath, $row['path']]);

        $pdo->prepare(
            "UPDATE artworks SET piece_value = ? WHERE piece_type = 'image_upload' AND piece_value = ?"
        )->execute([$newPath, $row['path']]);

        $pdo->prepare(
            "UPDATE categories SET thumbnail_value = ? WHERE thumbnail_type = 'upload' AND thumbnail_value = ?"
        )->execute([$newPath, $row['path']]);

        $pdo->prepare(
            "UPDATE exhibits SET thumbnail_value = ? WHERE thumbnail_type = 'upload' AND thumbnail_value = ?"
        )->execute([$newPath, $row['path']]);
    } else {
        unset($blob);
    }

    $bytes = number_format(filesize($physicalPath));
    echo "OK    [{$row['id']}] {$row['path']} → {$newPath} [{$mime}, {$bytes} bytes]\n";
    $updated++;
}

echo "\n";
echo "Done. Updated: $updated, Skipped: $skipped, Failed: $failed\n";

if (DRY_RUN) {
    echo "\nThis was a dry run. Set DRY_RUN = false to apply changes.\n";
}

if ($failed > 0) {
    echo "\nWARNING: $failed file(s) failed. Investigate before running cleanup SQL.\n";
    exit(1);
}

exit(0);
