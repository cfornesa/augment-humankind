<?php

declare(strict_types=1);

class FeedSource
{
    public static function all(): array
    {
        if (!self::tableExists()) {
            return [];
        }

        return db()->query(
            'SELECT * FROM feed_sources ORDER BY created_at DESC, id DESC'
        )->fetchAll();
    }

    public static function find(int $id): array|false
    {
        if (!self::tableExists()) {
            return false;
        }

        $stmt = db()->prepare('SELECT * FROM feed_sources WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: false;
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO feed_sources
                (name, author_name, username, bio, image_url, site_url, feed_url,
                 cadence, enabled, profile_photo_url)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'],
            $data['author_name'] ?? null,
            $data['username'] ?? null,
            $data['bio'] ?? null,
            $data['image_url'] ?? null,
            $data['site_url'] ?? null,
            $data['feed_url'],
            $data['cadence'] ?? 'daily',
            $data['enabled'] ?? 1,
            $data['profile_photo_url'] ?? null,
        ]);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE feed_sources SET
                name = ?, author_name = ?, username = ?, bio = ?, image_url = ?,
                site_url = ?, feed_url = ?, cadence = ?, enabled = ?,
                profile_photo_url = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['name'],
            $data['author_name'] ?? null,
            $data['username'] ?? null,
            $data['bio'] ?? null,
            $data['image_url'] ?? null,
            $data['site_url'] ?? null,
            $data['feed_url'],
            $data['cadence'] ?? 'daily',
            $data['enabled'] ?? 1,
            $data['profile_photo_url'] ?? null,
            $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM feed_sources WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function updateFetchStatus(int $id, string $status, ?string $error = null): void
    {
        $stmt = db()->prepare(
            'UPDATE feed_sources SET
                last_fetched_at = NOW(),
                next_fetch_at = ?,
                last_status = ?,
                last_error = ?
             WHERE id = ?'
        );
        $nextFetch = self::calculateNextFetch($status);
        $stmt->execute([$nextFetch, $status, $error, $id]);
    }

    public static function incrementItemsImported(int $id, int $count): void
    {
        $stmt = db()->prepare(
            'UPDATE feed_sources SET items_imported = items_imported + ? WHERE id = ?'
        );
        $stmt->execute([$count, $id]);
    }

    public static function pendingImports(): array
    {
        if (!self::tableExists()) {
            return [];
        }

        if (self::feedImportTableExists()) {
            return db()->query(
                "SELECT fii.*, fs.name AS source_name
                 FROM feed_import_items fii
                 JOIN feed_sources fs ON fs.id = fii.source_id
                 WHERE fii.status = 'pending'
                 ORDER BY fii.created_at DESC, fii.id DESC"
            )->fetchAll();
        }

        return db()->query(
            "SELECT fi.*, fs.name AS source_name
             FROM feed_items_seen fi
             JOIN feed_sources fs ON fs.id = fi.source_id
             WHERE fi.post_id IS NULL
             ORDER BY fi.seen_at DESC"
        )->fetchAll();
    }

    public static function markAsProcessed(int $seenId, int $postId): void
    {
        if (self::feedImportTableExists()) {
            $stmt = db()->prepare(
                "UPDATE feed_import_items SET status = 'approved', post_id = ? WHERE seen_id = ?"
            );
            $stmt->execute([$postId, $seenId]);
        }
        $stmt = db()->prepare(
            'UPDATE feed_items_seen SET post_id = ? WHERE id = ?'
        );
        $stmt->execute([$postId, $seenId]);
    }

    public static function rejectImport(int $seenId): void
    {
        if (self::feedImportTableExists()) {
            $stmt = db()->prepare(
                "UPDATE feed_import_items SET status = 'rejected' WHERE seen_id = ?"
            );
            $stmt->execute([$seenId]);
            return;
        }
        $stmt = db()->prepare(
            'UPDATE feed_items_seen SET post_id = -1 WHERE id = ?'
        );
        $stmt->execute([$seenId]);
    }

    public static function importItem(int $seenId, int $sourceId): array|false
    {
        if (self::feedImportTableExists()) {
            $stmt = db()->prepare(
                'SELECT fii.*, fs.name AS source_name, fs.author_name AS source_author_name,
                        fs.image_url AS source_image_url
                 FROM feed_import_items fii
                 JOIN feed_sources fs ON fs.id = fii.source_id
                 WHERE fii.seen_id = ? AND fii.source_id = ? AND fii.status = ?
                 LIMIT 1'
            );
            $stmt->execute([$seenId, $sourceId, 'pending']);
            return $stmt->fetch() ?: false;
        }

        $stmt = db()->prepare(
            'SELECT fi.*, fs.name AS source_name, fs.author_name AS source_author_name,
                    fs.image_url AS source_image_url
             FROM feed_items_seen fi
             JOIN feed_sources fs ON fs.id = fi.source_id
             WHERE fi.id = ? AND fi.source_id = ? AND fi.post_id IS NULL
             LIMIT 1'
        );
        $stmt->execute([$seenId, $sourceId]);
        return $stmt->fetch() ?: false;
    }

    private static function calculateNextFetch(string $cadence): ?string
    {
        $intervals = [
            'hourly' => '+1 hour',
            'daily' => '+1 day',
            'weekly' => '+7 days',
            'monthly' => '+30 days',
        ];
        return date('Y-m-d H:i:s', strtotime($intervals[$cadence] ?? '+1 day'));
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
            $stmt->execute(['feed_sources']);
            return $exists = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return $exists = false;
        }
    }

    private static function feedImportTableExists(): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }
        try {
            $stmt = db()->prepare(
                'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $stmt->execute(['feed_import_items']);
            return $exists = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return $exists = false;
        }
    }
}
