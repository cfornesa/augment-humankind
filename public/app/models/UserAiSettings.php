<?php

declare(strict_types=1);

class UserAiVendorSettings
{
    public static function all(): array
    {
        if (!self::tableExists()) {
            return [];
        }

        return db()->query(
            "SELECT uavs.*, u.name AS user_name
             FROM user_ai_vendor_settings uavs
             JOIN users u ON u.id = uavs.user_id
             ORDER BY uavs.created_at DESC"
        )->fetchAll();
    }

    public static function allForUser(string $userId): array
    {
        if (!self::tableExists()) {
            return [];
        }

        $stmt = db()->prepare(
            'SELECT * FROM user_ai_vendor_settings WHERE user_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): array|false
    {
        if (!self::tableExists()) {
            return false;
        }

        $stmt = db()->prepare('SELECT * FROM user_ai_vendor_settings WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: false;
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO user_ai_vendor_settings
                (user_id, vendor, profile_name, endpoint_kind, enabled, model)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['user_id'],
            $data['vendor'],
            $data['profile_name'] ?? 'Default',
            $data['endpoint_kind'] ?? null,
            $data['enabled'] ?? 0,
            $data['model'] ?? null,
        ]);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE user_ai_vendor_settings SET
                vendor = ?, profile_name = ?, endpoint_kind = ?, enabled = ?, model = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['vendor'],
            $data['profile_name'] ?? 'Default',
            $data['endpoint_kind'] ?? null,
            $data['enabled'] ?? 0,
            $data['model'] ?? null,
            $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM user_ai_vendor_settings WHERE id = ?');
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
            $stmt->execute(['user_ai_vendor_settings']);
            return $exists = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return $exists = false;
        }
    }
}

class UserAiVendorKeys
{
    public static function all(): array
    {
        if (!self::tableExists()) {
            return [];
        }

        return db()->query(
            "SELECT uavk.*, u.name AS user_name
             FROM user_ai_vendor_keys uavk
             JOIN users u ON u.id = uavk.user_id
             ORDER BY uavk.created_at DESC"
        )->fetchAll();
    }

    public static function find(int $id): array|false
    {
        if (!self::tableExists()) {
            return false;
        }

        $stmt = db()->prepare('SELECT * FROM user_ai_vendor_keys WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: false;
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO user_ai_vendor_keys (user_id, vendor, encrypted_api_key)
             VALUES (?, ?, ?)'
        );
        $stmt->execute([
            $data['user_id'],
            $data['vendor'],
            $data['encrypted_api_key'],
        ]);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE user_ai_vendor_keys SET vendor = ?, encrypted_api_key = ? WHERE id = ?'
        );
        $stmt->execute([
            $data['vendor'],
            $data['encrypted_api_key'],
            $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM user_ai_vendor_keys WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function findForUserVendor(string $userId, string $vendor): array|false
    {
        if (!self::tableExists()) {
            return false;
        }

        $stmt = db()->prepare(
            'SELECT * FROM user_ai_vendor_keys WHERE user_id = ? AND vendor = ? LIMIT 1'
        );
        $stmt->execute([$userId, $vendor]);
        return $stmt->fetch() ?: false;
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
            $stmt->execute(['user_ai_vendor_keys']);
            return $exists = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return $exists = false;
        }
    }
}
