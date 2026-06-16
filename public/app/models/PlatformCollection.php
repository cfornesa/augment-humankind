<?php

declare(strict_types=1);

class PlatformCollection
{
    public static function all(): array
    {
        if (!self::tableExists()) {
            return [];
        }

        $rows = db()->query(
            "SELECT pc.*, COUNT(pcii.item_id) AS item_count
             FROM platform_collections pc
             LEFT JOIN platform_collection_items pcii ON pcii.collection_id = pc.id
             WHERE pc.deleted_at IS NULL
             GROUP BY pc.id
             ORDER BY pc.sort_order ASC, pc.id ASC"
        )->fetchAll();

        return $rows;
    }

    public static function paginate(int $offset, int $limit): array
    {
        if (!self::tableExists()) {
            return [];
        }

        $stmt = db()->prepare(
            "SELECT pc.*, COUNT(pcii.item_id) AS item_count
             FROM platform_collections pc
             LEFT JOIN platform_collection_items pcii ON pcii.collection_id = pc.id
             WHERE pc.deleted_at IS NULL
             GROUP BY pc.id
             ORDER BY pc.sort_order ASC, pc.id ASC
             LIMIT ?, ?"
        );
        $stmt->bindValue(1, max(0, $offset), PDO::PARAM_INT);
        $stmt->bindValue(2, max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function countVisible(): int
    {
        if (!self::tableExists()) {
            return 0;
        }

        return (int) db()->query(
            'SELECT COUNT(*) FROM platform_collections WHERE deleted_at IS NULL'
        )->fetchColumn();
    }

    public static function findBySlug(string $slug): array|false
    {
        if (!self::tableExists()) {
            return false;
        }

        $stmt = db()->prepare(
            "SELECT * FROM platform_collections
             WHERE slug = ? AND deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }

        $row['items'] = self::itemsFor((int) $row['id']);
        return $row;
    }

    public static function find(int $id): array|false
    {
        if (!self::tableExists()) {
            return false;
        }

        $stmt = db()->prepare(
            "SELECT * FROM platform_collections
             WHERE id = ? AND deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }

        $row['items'] = self::itemsFor((int) $row['id']);
        return $row;
    }

    public static function itemsFor(int $collectionId): array
    {
        if (!self::itemsTableExists()) {
            return [];
        }

        $stmt = db()->prepare(
            "SELECT item_type, item_id, sort_order
             FROM platform_collection_items
             WHERE collection_id = ?
             ORDER BY sort_order ASC, item_id ASC"
        );
        $stmt->execute([$collectionId]);
        return $stmt->fetchAll();
    }

    public static function firstThumbnail(int $collectionId): ?string
    {
        foreach (self::itemsFor($collectionId) as $item) {
            $type = (string) ($item['item_type'] ?? '');
            $id = (int) ($item['item_id'] ?? 0);
            if ($type === 'art_piece') {
                $piece = PlatformArtPiece::find($id);
                if ($piece && !empty($piece['thumbnail_url'])) {
                    return $piece['thumbnail_url'];
                }
            } elseif ($type === 'media_asset') {
                $media = MediaAsset::find($id);
                if ($media) {
                    return $media['url'] ?: '/api/media-assets/' . (int) $media['id'];
                }
            }
        }
        return null;
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO platform_collections
                (slug, name, description, artist_statement, biography, `rows`, cols, iframe_code, sort_order, comments_enabled)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['slug'],
            $data['name'],
            $data['description'] ?? null,
            $data['artist_statement'] ?? null,
            $data['biography'] ?? null,
            $data['rows'] ?? 1,
            $data['cols'] ?? 1,
            $data['iframe_code'] ?? null,
            $data['sort_order'] ?? self::nextSortOrder(),
            isset($data['comments_enabled']) ? (int)(bool) $data['comments_enabled'] : 0,
        ]);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE platform_collections SET
                slug = ?, name = ?, description = ?, artist_statement = ?,
                biography = ?, `rows` = ?, cols = ?, iframe_code = ?, sort_order = ?, comments_enabled = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['slug'],
            $data['name'],
            $data['description'] ?? null,
            $data['artist_statement'] ?? null,
            $data['biography'] ?? null,
            $data['rows'] ?? 1,
            $data['cols'] ?? 1,
            $data['iframe_code'] ?? null,
            $data['sort_order'] ?? 0,
            isset($data['comments_enabled']) ? (int)(bool) $data['comments_enabled'] : 0,
            $id,
        ]);
    }

    public static function reorder(array $ids): void
    {
        $stmt = db()->prepare('UPDATE platform_collections SET sort_order = ? WHERE id = ? AND deleted_at IS NULL');
        foreach (array_values($ids) as $index => $id) {
            $stmt->execute([$index, $id]);
        }
    }

    public static function softDelete(int $id): void
    {
        $stmt = db()->prepare('UPDATE platform_collections SET deleted_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function restore(int $id): void
    {
        $stmt = db()->prepare('UPDATE platform_collections SET deleted_at = NULL WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function hardDelete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM platform_collections WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function trashed(): array
    {
        if (!self::tableExists()) {
            return [];
        }

        return db()->query(
            "SELECT pc.*, COUNT(pcii.item_id) AS item_count
             FROM platform_collections pc
             LEFT JOIN platform_collection_items pcii ON pcii.collection_id = pc.id
             WHERE pc.deleted_at IS NOT NULL
             GROUP BY pc.id
             ORDER BY pc.deleted_at DESC"
        )->fetchAll();
    }

    public static function trashedCount(): int
    {
        if (!self::tableExists()) {
            return 0;
        }

        return (int) db()->query(
            'SELECT COUNT(*) FROM platform_collections WHERE deleted_at IS NOT NULL'
        )->fetchColumn();
    }

    public static function syncItems(int $collectionId, array $items): void
    {
        if (!self::itemsTableExists()) {
            return;
        }

        $del = db()->prepare('DELETE FROM platform_collection_items WHERE collection_id = ?');
        $del->execute([$collectionId]);

        if (empty($items)) {
            return;
        }

        $ins = db()->prepare(
            'INSERT INTO platform_collection_items (collection_id, item_type, item_id, sort_order)
             VALUES (?, ?, ?, ?)'
        );
        foreach (array_values($items) as $index => $item) {
            $itemType = $item['item_type'] ?? 'piece';
            $itemId = (int) ($item['item_id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }
            $ins->execute([$collectionId, $itemType, $itemId, $index]);
        }
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
            $stmt->execute(['platform_collections']);
            return $exists = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return $exists = false;
        }
    }

    private static function itemsTableExists(): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }
        try {
            $stmt = db()->prepare(
                'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $stmt->execute(['platform_collection_items']);
            return $exists = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return $exists = false;
        }
    }

    private static function nextSortOrder(): int
    {
        return (int) db()->query(
            'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM platform_collections'
        )->fetchColumn();
    }
}
