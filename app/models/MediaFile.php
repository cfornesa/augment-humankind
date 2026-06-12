<?php

declare(strict_types=1);

class MediaFile
{
    public static function create(string $data, string $mimeType, ?string $originalName = null): int
    {
        $pdo  = db();
        $stmt = $pdo->prepare(
            'INSERT INTO media_files (data, mime_type, byte_size, original_name) VALUES (?, ?, ?, ?)'
        );
        $stmt->bindParam(1, $data, PDO::PARAM_LOB);
        $stmt->bindValue(2, $mimeType);
        $stmt->bindValue(3, mb_strlen($data, '8bit'), PDO::PARAM_INT);
        $stmt->bindValue(4, $originalName ?: null);
        $stmt->execute();
        return (int) $pdo->lastInsertId();
    }

    public static function all(): array
    {
        return db()->query(
            'SELECT id, mime_type, byte_size, original_name, deleted_at, created_at
             FROM media_files
             WHERE deleted_at IS NULL
             ORDER BY created_at DESC'
        )->fetchAll();
    }

    public static function trashed(): array
    {
        return db()->query(
            'SELECT id, mime_type, byte_size, original_name, deleted_at, created_at
             FROM media_files
             WHERE deleted_at IS NOT NULL
             ORDER BY deleted_at DESC'
        )->fetchAll();
    }

    public static function trashedCount(): int
    {
        return (int) db()->query(
            'SELECT COUNT(*) FROM media_files WHERE deleted_at IS NOT NULL'
        )->fetchColumn();
    }

    public static function find(int $id): array|false
    {
        $stmt = db()->prepare(
            'SELECT id, mime_type, byte_size, original_name, deleted_at, created_at
             FROM media_files
             WHERE id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function getData(int $id): array|false
    {
        $stmt = db()->prepare(
            'SELECT id, mime_type, byte_size, original_name, deleted_at, data
             FROM media_files
             WHERE id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function softDelete(int $id): void
    {
        $stmt = db()->prepare('UPDATE media_files SET deleted_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function hardDelete(int $id): void
    {
        if (self::find($id)) {
            $stmt = db()->prepare('DELETE FROM media_files WHERE id = ?');
            $stmt->execute([$id]);
        }
    }

    public static function restore(int $id): void
    {
        $stmt = db()->prepare('UPDATE media_files SET deleted_at = NULL WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function isActiveOfKind(int $id, string $kind): bool
    {
        $row = self::find($id);
        if (!$row || $row['deleted_at'] !== null) {
            return false;
        }

        return match ($kind) {
            'image' => str_starts_with((string) ($row['mime_type'] ?? ''), 'image/'),
            'video' => str_starts_with((string) ($row['mime_type'] ?? ''), 'video/'),
            default => false,
        };
    }
}
