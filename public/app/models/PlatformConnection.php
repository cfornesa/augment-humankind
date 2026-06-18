<?php

declare(strict_types=1);

class PlatformConnection
{
    public static function all(): array
    {
        if (!self::tableExists()) {
            return [];
        }

        return db()->query(
            "SELECT pc.*, u.name AS user_name
             FROM platform_connections pc
             LEFT JOIN users u ON u.id = pc.user_id
             ORDER BY pc.created_at DESC, pc.id DESC"
        )->fetchAll();
    }

    public static function find(int $id): array|false
    {
        if (!self::tableExists()) {
            return false;
        }

        $stmt = db()->prepare('SELECT * FROM platform_connections WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: false;
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO platform_connections
                (user_id, platform, encrypted_access_token, encrypted_refresh_token,
                 access_token_format, refresh_token_format, expires_at, metadata, enabled)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['user_id'] ?? null,
            $data['platform'],
            $data['encrypted_access_token'] ?? null,
            $data['encrypted_refresh_token'] ?? null,
            !empty($data['encrypted_access_token']) ? 'platform_aes_256_gcm' : 'none',
            !empty($data['encrypted_refresh_token']) ? 'platform_aes_256_gcm' : 'none',
            $data['expires_at'] ?? null,
            $data['metadata'] ?? null,
            $data['enabled'] ?? 1,
        ]);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE platform_connections SET
                user_id = ?, platform = ?, encrypted_access_token = ?,
                encrypted_refresh_token = ?, access_token_format = ?,
                refresh_token_format = ?, expires_at = ?, metadata = ?, enabled = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['user_id'] ?? null,
            $data['platform'],
            $data['encrypted_access_token'] ?? null,
            $data['encrypted_refresh_token'] ?? null,
            !empty($data['encrypted_access_token']) ? 'platform_aes_256_gcm' : 'none',
            !empty($data['encrypted_refresh_token']) ? 'platform_aes_256_gcm' : 'none',
            $data['expires_at'] ?? null,
            $data['metadata'] ?? null,
            $data['enabled'] ?? 1,
            $id,
        ]);
    }

    public static function updateTokens(int $id, string $accessToken, ?string $refreshToken, ?string $expiresAt): void
    {
        $encryptedAccess = encrypt_string($accessToken, ai_encryption_key());
        $encryptedRefresh = $refreshToken !== null && $refreshToken !== ''
            ? encrypt_string($refreshToken, ai_encryption_key())
            : null;

        $stmt = db()->prepare(
            "UPDATE platform_connections SET
                encrypted_access_token = ?,
                access_token_format = 'platform_aes_256_gcm',
                encrypted_refresh_token = COALESCE(?, encrypted_refresh_token),
                refresh_token_format = CASE WHEN ? IS NULL THEN refresh_token_format ELSE 'platform_aes_256_gcm' END,
                expires_at = COALESCE(?, expires_at)
             WHERE id = ?"
        );
        $stmt->execute([$encryptedAccess, $encryptedRefresh, $encryptedRefresh, $expiresAt, $id]);
    }

    public static function allEnabled(): array
    {
        if (!self::tableExists()) {
            return [];
        }

        try {
            return db()->query(
                "SELECT * FROM platform_connections WHERE enabled = 1 ORDER BY platform ASC"
            )->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    public static function delete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM platform_connections WHERE id = ?');
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
            $stmt->execute(['platform_connections']);
            return $exists = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return $exists = false;
        }
    }
}

class PostSyndication
{
    public static function all(): array
    {
        if (!self::tableExists()) {
            return [];
        }

        return db()->query(
            "SELECT ps.*, p.title AS post_title, pc.platform
             FROM post_syndications ps
             JOIN posts p ON p.id = ps.post_id
             JOIN platform_connections pc ON pc.id = ps.platform_connection_id
             ORDER BY ps.created_at DESC"
        )->fetchAll();
    }

    public static function find(int $id): array|false
    {
        if (!self::tableExists()) {
            return false;
        }

        $stmt = db()->prepare('SELECT * FROM post_syndications WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: false;
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO post_syndications
                (post_id, platform_connection_id, external_id, external_url, status, error_message)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['post_id'],
            $data['platform_connection_id'],
            $data['external_id'] ?? null,
            $data['external_url'] ?? null,
            $data['status'] ?? 'pending',
            $data['error_message'] ?? null,
        ]);
        return (int) db()->lastInsertId();
    }

    public static function recordResult(array $data): int
    {
        $existing = self::findByPostAndConnection((int) $data['post_id'], (int) $data['platform_connection_id']);
        if ($existing) {
            self::update((int) $existing['id'], [
                'post_id' => $data['post_id'],
                'platform_connection_id' => $data['platform_connection_id'],
                'external_id' => $data['external_id'] ?? null,
                'external_url' => $data['external_url'] ?? null,
                'status' => $data['status'] ?? 'pending',
                'error_message' => $data['error_message'] ?? null,
                'synced_at' => $data['synced_at'] ?? null,
            ]);
            return (int) $existing['id'];
        }
        return self::create($data);
    }

    public static function findByPostAndConnection(int $postId, int $connectionId): array|false
    {
        if (!self::tableExists()) {
            return false;
        }
        $stmt = db()->prepare(
            'SELECT * FROM post_syndications WHERE post_id = ? AND platform_connection_id = ? LIMIT 1'
        );
        $stmt->execute([$postId, $connectionId]);
        return $stmt->fetch() ?: false;
    }

    public static function update(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE post_syndications SET
                post_id = ?, platform_connection_id = ?, external_id = ?,
                external_url = ?, status = ?, error_message = ?, synced_at = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['post_id'],
            $data['platform_connection_id'],
            $data['external_id'] ?? null,
            $data['external_url'] ?? null,
            $data['status'] ?? 'pending',
            $data['error_message'] ?? null,
            $data['synced_at'] ?? null,
            $id,
        ]);
    }

    public static function pendingForPosts(array $postIds): array
    {
        if (!$postIds || !self::tableExists()) {
            return [];
        }
        $in = implode(',', array_fill(0, count($postIds), '?'));
        $stmt = db()->prepare(
            "SELECT * FROM post_syndications WHERE status = 'pending' AND post_id IN ($in)"
        );
        $stmt->execute($postIds);
        return $stmt->fetchAll();
    }

    public static function syncedConnectionIdsForPost(int $postId): array
    {
        if (!self::tableExists()) {
            return [];
        }
        try {
            $stmt = db()->prepare(
                "SELECT platform_connection_id FROM post_syndications WHERE post_id = ? AND status = 'synced'"
            );
            $stmt->execute([$postId]);
            return array_column($stmt->fetchAll(), 'platform_connection_id');
        } catch (Throwable) {
            return [];
        }
    }

    public static function delete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM post_syndications WHERE id = ?');
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
            $stmt->execute(['post_syndications']);
            return $exists = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return $exists = false;
        }
    }
}
