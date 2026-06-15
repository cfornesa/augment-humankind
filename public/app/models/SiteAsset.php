<?php

declare(strict_types=1);

class SiteAsset
{
    public static function all(): array
    {
        if (!self::tableExists()) {
            return [];
        }

        return db()->query(
            'SELECT * FROM site_assets ORDER BY asset_key ASC'
        )->fetchAll();
    }

    public static function find(int $id): array|false
    {
        if (!self::tableExists()) {
            return false;
        }

        $stmt = db()->prepare('SELECT * FROM site_assets WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: false;
    }

    public static function findByKey(string $key): array|false
    {
        if (!self::tableExists()) {
            return false;
        }

        $stmt = db()->prepare('SELECT * FROM site_assets WHERE asset_key = ? LIMIT 1');
        $stmt->execute([$key]);
        return $stmt->fetch() ?: false;
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO site_assets (asset_key, filename, mime_type, byte_size, data, file_data)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['asset_key'],
            $data['filename'] ?? null,
            $data['mime_type'],
            $data['byte_size'] ?? null,
            $data['data'] ?? null,
            $data['file_data'] ?? null,
        ]);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE site_assets SET
                asset_key = ?, filename = ?, mime_type = ?, byte_size = ?,
                data = ?, file_data = ?, updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([
            $data['asset_key'],
            $data['filename'] ?? null,
            $data['mime_type'],
            $data['byte_size'] ?? null,
            $data['data'] ?? null,
            $data['file_data'] ?? null,
            $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM site_assets WHERE id = ?');
        $stmt->execute([$id]);
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
            $stmt->execute(['site_assets']);
            return $exists = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return $exists = false;
        }
    }
}
