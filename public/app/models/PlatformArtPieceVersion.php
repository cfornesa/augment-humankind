<?php

declare(strict_types=1);

class PlatformArtPieceVersion
{
    public static function allForPiece(int $pieceId): array
    {
        if (!self::tableExists()) {
            return [];
        }

        $stmt = db()->prepare(
            "SELECT id, art_piece_id, version_number, prompt, structured_spec,
                    html_code, css_code, generated_code, engine,
                    generation_vendor, generation_model, validation_status,
                    generation_attempt_count, notes, created_at
             FROM art_piece_versions
             WHERE art_piece_id = ?
             ORDER BY version_number DESC, created_at DESC"
        );
        $stmt->execute([$pieceId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): array|false
    {
        if (!self::tableExists()) {
            return false;
        }

        $stmt = db()->prepare(
            "SELECT id, art_piece_id, version_number, prompt, structured_spec,
                    html_code, css_code, generated_code, engine,
                    generation_vendor, generation_model, validation_status,
                    generation_attempt_count, notes, created_at
             FROM art_piece_versions
             WHERE id = ?
             LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: false;
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO art_piece_versions
                (art_piece_id, version_number, prompt, structured_spec,
                 html_code, css_code, generated_code, engine,
                 generation_vendor, generation_model, validation_status,
                 generation_attempt_count, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['art_piece_id'],
            $data['version_number'] ?? 1,
            $data['prompt'] ?? null,
            $data['structured_spec'] ?? null,
            $data['html_code'] ?? null,
            $data['css_code'] ?? null,
            $data['generated_code'] ?? null,
            $data['engine'] ?? 'p5',
            $data['generation_vendor'] ?? null,
            $data['generation_model'] ?? null,
            $data['validation_status'] ?? 'validated',
            $data['generation_attempt_count'] ?? 1,
            $data['notes'] ?? null,
        ]);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE art_piece_versions SET
                prompt = ?, structured_spec = ?, html_code = ?, css_code = ?,
                generated_code = ?, engine = ?, generation_vendor = ?,
                generation_model = ?, validation_status = ?,
                generation_attempt_count = ?, notes = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['prompt'] ?? null,
            $data['structured_spec'] ?? null,
            $data['html_code'] ?? null,
            $data['css_code'] ?? null,
            $data['generated_code'] ?? null,
            $data['engine'] ?? 'p5',
            $data['generation_vendor'] ?? null,
            $data['generation_model'] ?? null,
            $data['validation_status'] ?? 'validated',
            $data['generation_attempt_count'] ?? 1,
            $data['notes'] ?? null,
            $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM art_piece_versions WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function nextVersionNumber(int $pieceId): int
    {
        $stmt = db()->prepare(
            'SELECT MAX(version_number) FROM art_piece_versions WHERE art_piece_id = ?'
        );
        $stmt->execute([$pieceId]);
        $max = (int) $stmt->fetchColumn();
        return $max + 1;
    }

    private static function tableExists(): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }
        try {
            $stmt = db()->prepare(
                'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $stmt->execute(['art_piece_versions']);
            return $exists = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return $exists = false;
        }
    }
}
