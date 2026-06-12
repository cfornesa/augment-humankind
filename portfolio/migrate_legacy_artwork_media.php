<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/models/Artwork.php';
require __DIR__ . '/app/models/ArtworkMediaItem.php';

$rows = db()->query(
    'SELECT a.*
     FROM artworks a
     LEFT JOIN artwork_media_items ami ON ami.artwork_id = a.id
     WHERE ami.id IS NULL AND a.deleted_at IS NULL
     ORDER BY a.id ASC'
)->fetchAll();

if (!$rows) {
    echo "No legacy artworks require media backfill.\n";
    exit(0);
}

foreach ($rows as $artwork) {
    $legacyItem = ArtworkMediaItem::buildLegacyItem($artwork);
    if ($legacyItem === null) {
        continue;
    }

    $payload = [[
        'media_kind' => $legacyItem['media_kind'],
        'media_file_id' => $legacyItem['media_file_id'],
        'iframe_html' => $legacyItem['iframe_html'],
        'poster_media_file_id' => null,
        'alt_text' => $legacyItem['alt_text'],
    ]];

    ArtworkMediaItem::syncForArtwork((int) $artwork['id'], $payload);
    echo "Backfilled artwork #{$artwork['id']} ({$artwork['slug']}).\n";
}

echo "Done.\n";
