<?php

declare(strict_types=1);

class ExhibitMediaItem
{
    public static function allForExhibit(int $exhibitId): array
    {
        $stmt = db()->prepare(
            'SELECT ami.*, mf.mime_type, mf.byte_size, mf.original_name, mf.deleted_at AS media_deleted_at,
                    pmf.mime_type AS poster_mime_type
             FROM exhibit_media_items ami
             LEFT JOIN media_files mf ON mf.id = ami.media_file_id
             LEFT JOIN media_files pmf ON pmf.id = ami.poster_media_file_id
             WHERE ami.exhibit_id = ?
             ORDER BY ami.sort_order ASC, ami.id ASC'
        );
        $stmt->execute([$exhibitId]);
        return $stmt->fetchAll();
    }

    public static function syncForExhibit(int $exhibitId, array $items): void
    {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $delete = $pdo->prepare('DELETE FROM exhibit_media_items WHERE exhibit_id = ?');
            $delete->execute([$exhibitId]);

            if ($items) {
                $insert = $pdo->prepare(
                    'INSERT INTO exhibit_media_items
                        (exhibit_id, media_kind, media_file_id, iframe_html, poster_media_file_id,
                         alt_text, title, caption, content_html, content_wrapper_class, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );

                foreach (array_values($items) as $index => $item) {
                    $insert->execute([
                        $exhibitId,
                        $item['media_kind'],
                        $item['media_file_id'] ?: null,
                        $item['iframe_html'] ?: null,
                        $item['poster_media_file_id'] ?: null,
                        $item['alt_text'] ?: null,
                        $item['title'] ?: null,
                        $item['caption'] ?: null,
                        $item['content_html'] ?: null,
                        $item['content_wrapper_class'] ?: null,
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

    public static function normalizeForDisplay(array $item): array
    {
        $mediaKind = (string) ($item['media_kind'] ?? 'image');
        $mediaFileId = isset($item['media_file_id']) ? (int) $item['media_file_id'] : 0;
        $posterId = isset($item['poster_media_file_id']) ? (int) $item['poster_media_file_id'] : 0;

        $item['source_url'] = $item['source_url']
            ?? ($mediaFileId > 0 ? '/media/' . $mediaFileId : null);
        $item['poster_url'] = $item['poster_url']
            ?? ($posterId > 0 ? '/media/' . $posterId : null);
        $item['display_kind'] = match ($mediaKind) {
            'iframe'  => 'iframe',
            'video'   => 'video',
            'content' => 'content',
            default   => 'image',
        };

        return $item;
    }
}
