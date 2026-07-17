<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/schema.php';

/**
 * Structured media references selected via the "Add media reference" picker
 * on the piece generate/refine forms (docs/migrations/2026-07-13-*). Each row
 * is one picker selection scoped to a single art_piece_versions row: which
 * media_files asset, and what the admin said it should be used for.
 */
class ArtPieceVersionMediaRef
{
    private static function tableExists(): bool
    {
        try {
            $exists = ah_table_exists('art_piece_version_media_refs');
        } catch (Throwable) {
            $exists = false;
        }
        if (!$exists) {
            error_log(
                'ArtPieceVersionMediaRef: art_piece_version_media_refs table missing — '
                . 'media refs are being dropped; run scripts/setup-database.php --yes'
            );
        }
        return $exists;
    }

    public static function allForVersion(int $versionId): array
    {
        if (!self::tableExists()) {
            return [];
        }

        $stmt = db()->prepare(
            "SELECT r.id, r.art_piece_version_id, r.media_file_id, r.intent_text, r.sort_order,
                    m.mime_type, m.original_name
             FROM art_piece_version_media_refs r
             LEFT JOIN media_files m ON m.id = r.media_file_id
             WHERE r.art_piece_version_id = ?
             ORDER BY r.sort_order ASC, r.id ASC"
        );
        $stmt->execute([$versionId]);
        return $stmt->fetchAll();
    }

    /**
     * Replaces every ref row for a version with the given structured list.
     * $refs is a list of ['media_id' => int, 'intent_text' => ?string].
     */
    public static function replaceForVersion(int $versionId, array $refs): void
    {
        if (!self::tableExists()) {
            return;
        }

        db()->prepare('DELETE FROM art_piece_version_media_refs WHERE art_piece_version_id = ?')
            ->execute([$versionId]);

        if ($refs === []) {
            return;
        }

        $stmt = db()->prepare(
            'INSERT INTO art_piece_version_media_refs
                (art_piece_version_id, media_file_id, intent_text, sort_order)
             VALUES (?, ?, ?, ?)'
        );
        foreach (array_values($refs) as $index => $ref) {
            $mediaId = (int) ($ref['media_id'] ?? 0);
            if ($mediaId <= 0) {
                continue;
            }
            $stmt->execute([
                $versionId,
                $mediaId,
                trim((string) ($ref['intent_text'] ?? '')) ?: null,
                $index,
            ]);
        }
    }
}
