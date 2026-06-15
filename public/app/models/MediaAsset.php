<?php

declare(strict_types=1);

class MediaAsset
{
    public static function all(): array
    {
        if (!self::tableExists()) {
            return [];
        }

        return db()->query(
            'SELECT * FROM media_assets WHERE deleted_at IS NULL ORDER BY uploaded_at DESC, id DESC'
        )->fetchAll();
    }

    public static function find(int $id): array|false
    {
        if (!self::tableExists()) {
            return false;
        }

        $stmt = db()->prepare(
            'SELECT * FROM media_assets WHERE id = ? AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: false;
    }

    public static function findByFilename(string $filename): array|false
    {
        if (!self::tableExists()) {
            return false;
        }

        $stmt = db()->prepare(
            'SELECT * FROM media_assets WHERE filename = ? AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([$filename]);
        return $stmt->fetch() ?: false;
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO media_assets (url, filename, mime_type, byte_size, alt_text, title, file_data)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['url'] ?? null,
            $data['filename'],
            $data['mime_type'],
            $data['byte_size'] ?? null,
            $data['alt_text'] ?? null,
            $data['title'] ?? null,
            $data['file_data'] ?? null,
        ]);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE media_assets SET
                url = ?, filename = ?, mime_type = ?, byte_size = ?,
                alt_text = ?, title = ?, file_data = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['url'] ?? null,
            $data['filename'],
            $data['mime_type'],
            $data['byte_size'] ?? null,
            $data['alt_text'] ?? null,
            $data['title'] ?? null,
            $data['file_data'] ?? null,
            $id,
        ]);
    }

    public static function updateMetadata(int $id, ?string $title, ?string $altText): void
    {
        $stmt = db()->prepare('UPDATE media_assets SET title = ?, alt_text = ? WHERE id = ?');
        $stmt->execute([$title, $altText, $id]);
    }

    public static function softDelete(int $id): void
    {
        $stmt = db()->prepare('UPDATE media_assets SET deleted_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function restore(int $id): void
    {
        $stmt = db()->prepare('UPDATE media_assets SET deleted_at = NULL WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function hardDelete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM media_assets WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function trashed(): array
    {
        if (!self::tableExists()) {
            return [];
        }

        return db()->query(
            'SELECT * FROM media_assets WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC'
        )->fetchAll();
    }

    public static function trashedCount(): int
    {
        if (!self::tableExists()) {
            return 0;
        }

        return (int) db()->query(
            'SELECT COUNT(*) FROM media_assets WHERE deleted_at IS NOT NULL'
        )->fetchColumn();
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
            $stmt->execute(['media_assets']);
            return $exists = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return $exists = false;
        }
    }
}
