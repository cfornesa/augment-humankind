<?php

declare(strict_types=1);

class ArtworkMediaItem
{
    public static function allForArtwork(int $artworkId): array
    {
        try {
            $stmt = db()->prepare(
                'SELECT ami.*, mf.mime_type, mf.byte_size, mf.original_name, mf.deleted_at AS media_deleted_at,
                        pmf.mime_type AS poster_mime_type
                 FROM artwork_media_items ami
                 LEFT JOIN media_files mf ON mf.id = ami.media_file_id
                 LEFT JOIN media_files pmf ON pmf.id = ami.poster_media_file_id
                 WHERE ami.artwork_id = ?
                 ORDER BY ami.sort_order ASC, ami.id ASC'
            );
            $stmt->execute([$artworkId]);
            return $stmt->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    public static function syncForArtwork(int $artworkId, array $items): void
    {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $delete = $pdo->prepare('DELETE FROM artwork_media_items WHERE artwork_id = ?');
            $delete->execute([$artworkId]);

            if ($items) {
                $insert = $pdo->prepare(
                    'INSERT INTO artwork_media_items
                        (artwork_id, media_kind, media_file_id, iframe_html, poster_media_file_id, alt_text, title, caption, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );

                foreach (array_values($items) as $index => $item) {
                    $insert->execute([
                        $artworkId,
                        $item['media_kind'],
                        $item['media_file_id'] ?: null,
                        $item['iframe_html'] ?: null,
                        $item['poster_media_file_id'] ?: null,
                        $item['alt_text'] ?: null,
                        $item['title'] ?: null,
                        $item['caption'] ?: null,
                        $index,
                    ]);
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function buildLegacyItem(array $artwork): array|null
    {
        $pieceType = (string) ($artwork['piece_type'] ?? '');
        $pieceValue = trim((string) ($artwork['piece_value'] ?? ''));
        if ($pieceValue === '') {
            return null;
        }

        if ($pieceType === 'embed') {
            return [
                'id' => null,
                'artwork_id' => (int) ($artwork['id'] ?? 0),
                'media_kind' => 'iframe',
                'media_file_id' => null,
                'iframe_html' => $pieceValue,
                'poster_media_file_id' => null,
                'alt_text' => null,
                'title' => null,
                'caption' => null,
                'sort_order' => 0,
                'mime_type' => null,
                'byte_size' => null,
                'original_name' => null,
                'media_deleted_at' => null,
                'poster_mime_type' => null,
                'source_url' => null,
                'poster_url' => null,
                'display_kind' => 'iframe',
            ];
        }

        return [
            'id' => null,
            'artwork_id' => (int) ($artwork['id'] ?? 0),
            'media_kind' => 'image',
            'media_file_id' => self::extractMediaIdFromUrl($pieceValue),
            'iframe_html' => null,
            'poster_media_file_id' => null,
            'alt_text' => $artwork['title'] ?? null,
            'title' => null,
            'caption' => null,
            'sort_order' => 0,
            'mime_type' => null,
            'byte_size' => null,
            'original_name' => null,
            'media_deleted_at' => null,
            'poster_mime_type' => null,
            'source_url' => $pieceValue,
            'poster_url' => null,
            'display_kind' => 'image',
        ];
    }

    public static function normalizeForDisplay(array $item): array
    {
        $mediaKind = (string) ($item['media_kind'] ?? 'image');
        $mediaFileId = isset($item['media_file_id']) ? (int) $item['media_file_id'] : 0;
        $posterId = isset($item['poster_media_file_id']) ? (int) $item['poster_media_file_id'] : 0;

        $item['source_url'] = $item['source_url']
            ?? ($mediaFileId > 0 ? '/media/' . $mediaFileId : null);
        $item['poster_url'] = $item['poster_url']
            ?? ($posterId > 0 ? '/media/' . $posterId : null);
        $item['display_kind'] = $mediaKind === 'iframe'
            ? 'iframe'
            : ($mediaKind === 'video' ? 'video' : 'image');

        return $item;
    }

    public static function extractMediaIdFromUrl(string $url): ?int
    {
        if (preg_match('#^/(?:image|media)/([0-9]+)$#', trim($url), $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
