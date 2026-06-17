<?php

declare(strict_types=1);

class Collection
{
    public static function all(): array
    {
        return db()->query(
            'SELECT * FROM collections WHERE deleted_at IS NULL ORDER BY sort_order ASC, id ASC'
        )->fetchAll();
    }

    public static function allWithExhibitCount(): array
    {
        return db()->query(
            'SELECT c.*, COUNT(ce.exhibit_id) AS exhibit_count
             FROM collections c
             LEFT JOIN collection_exhibits ce ON ce.collection_id = c.id
             LEFT JOIN exhibits e ON e.id = ce.exhibit_id AND e.deleted_at IS NULL
             WHERE c.deleted_at IS NULL
             GROUP BY c.id
             ORDER BY c.sort_order ASC, c.id ASC'
        )->fetchAll();
    }

    public static function allWithAtLeastOneExhibit(): array
    {
        return db()->query(
            'SELECT c.*
             FROM collections c
             WHERE c.deleted_at IS NULL
               AND EXISTS (
                   SELECT 1 FROM collection_exhibits ce
                   JOIN exhibits e ON e.id = ce.exhibit_id AND e.deleted_at IS NULL
                   WHERE ce.collection_id = c.id
               )
             ORDER BY c.sort_order ASC, c.id ASC'
        )->fetchAll();
    }

    public static function paginateWithAtLeastOneExhibit(int $offset, int $limit): array
    {
        $stmt = db()->prepare(
            'SELECT c.*
             FROM collections c
             WHERE c.deleted_at IS NULL
               AND EXISTS (
                   SELECT 1 FROM collection_exhibits ce
                   JOIN exhibits e ON e.id = ce.exhibit_id AND e.deleted_at IS NULL
                   WHERE ce.collection_id = c.id
               )
             ORDER BY c.sort_order ASC, c.id ASC
             LIMIT ?, ?'
        );
        $stmt->bindValue(1, max(0, $offset), PDO::PARAM_INT);
        $stmt->bindValue(2, max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function countWithAtLeastOneExhibit(): int
    {
        $stmt = db()->query(
            'SELECT COUNT(*)
             FROM collections c
             WHERE c.deleted_at IS NULL
               AND EXISTS (
                   SELECT 1 FROM collection_exhibits ce
                   JOIN exhibits e ON e.id = ce.exhibit_id AND e.deleted_at IS NULL
                   WHERE ce.collection_id = c.id
               )'
        );
        return (int) $stmt->fetchColumn();
    }

    public static function latestActive(int $limit = 3): array
    {
        $stmt = db()->prepare(
            'SELECT c.*
             FROM collections c
             WHERE c.deleted_at IS NULL
               AND EXISTS (
                   SELECT 1 FROM collection_exhibits ce
                   JOIN exhibits e ON e.id = ce.exhibit_id AND e.deleted_at IS NULL
                   WHERE ce.collection_id = c.id
               )
             ORDER BY c.created_at DESC, c.id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function paginateLatest(int $offset, int $limit): array
    {
        $stmt = db()->prepare(
            'SELECT c.*
             FROM collections c
             WHERE c.deleted_at IS NULL
               AND EXISTS (
                   SELECT 1 FROM collection_exhibits ce
                   JOIN exhibits e ON e.id = ce.exhibit_id AND e.deleted_at IS NULL
                   WHERE ce.collection_id = c.id
               )
             ORDER BY c.created_at DESC, c.id DESC
             LIMIT ?, ?'
        );
        $stmt->bindValue(1, max(0, $offset), PDO::PARAM_INT);
        $stmt->bindValue(2, max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function searchFiltered(string $q, string $sort = 'newest', string $dir = 'desc', int $offset = 0, int $limit = 500): array
    {
        $like = $q !== '' ? '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%' : '%';

        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';
        $sortCol = match ($sort) {
            'name'    => 'c.name',
            'created' => 'c.created_at',
            'az'      => 'c.name',
            'za'      => 'c.name',
            default   => 'c.created_at',
        };
        if ($sort === 'az') {
            $dir = 'ASC';
        } elseif ($sort === 'za') {
            $dir = 'DESC';
        }

        $stmt = db()->prepare(
            "SELECT c.*
             FROM collections c
             WHERE c.deleted_at IS NULL
               AND (c.name LIKE ? OR c.description LIKE ?)
             ORDER BY {$sortCol} {$dir}, c.id {$dir}
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$like, $like, $limit, $offset]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): array|false
    {
        $stmt = db()->prepare('SELECT * FROM collections WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function findBySlug(string $slug): array|false
    {
        $stmt = db()->prepare('SELECT * FROM collections WHERE slug = ? AND deleted_at IS NULL');
        $stmt->execute([$slug]);
        return $stmt->fetch();
    }

    public static function exhibits(int $id): array
    {
        $stmt = db()->prepare(
            'SELECT e.*
             FROM collection_exhibits ce
             JOIN exhibits e ON e.id = ce.exhibit_id AND e.deleted_at IS NULL
             WHERE ce.collection_id = ?
             ORDER BY ce.sort_order ASC, e.sort_order ASC, e.id ASC'
        );
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    public static function exhibitIds(int $id): array
    {
        $stmt = db()->prepare(
            'SELECT exhibit_id FROM collection_exhibits WHERE collection_id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function collectionIdsForExhibit(int $exhibitId): array
    {
        $stmt = db()->prepare(
            'SELECT collection_id FROM collection_exhibits WHERE exhibit_id = ?'
        );
        $stmt->execute([$exhibitId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function trashed(): array
    {
        return db()->query(
            'SELECT * FROM collections WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC'
        )->fetchAll();
    }

    public static function trashedCount(): int
    {
        return (int) db()->query(
            'SELECT COUNT(*) FROM collections WHERE deleted_at IS NOT NULL'
        )->fetchColumn();
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO collections (name, slug, description, thumbnail_type, thumbnail_value, sort_order, comments_enabled)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'],
            $data['slug'],
            $data['description'] ?: null,
            $data['thumbnail_type'] ?: null,
            $data['thumbnail_value'] ?: null,
            $data['sort_order'] ?? 0,
            isset($data['comments_enabled']) ? (int)(bool) $data['comments_enabled'] : 0,
        ]);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE collections
             SET name = ?, slug = ?, description = ?,
                 thumbnail_type = ?, thumbnail_value = ?, sort_order = ?, comments_enabled = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['name'],
            $data['slug'],
            $data['description'] ?: null,
            $data['thumbnail_type'] ?: null,
            $data['thumbnail_value'] ?: null,
            $data['sort_order'] ?? 0,
            isset($data['comments_enabled']) ? (int)(bool) $data['comments_enabled'] : 0,
            $id,
        ]);
    }

    public static function syncExhibits(int $id, array $exhibitIds): void
    {
        $pdo = db();
        $del = $pdo->prepare('DELETE FROM collection_exhibits WHERE collection_id = ?');
        $del->execute([$id]);

        if (empty($exhibitIds)) {
            return;
        }

        $ins = $pdo->prepare(
            'INSERT INTO collection_exhibits (collection_id, exhibit_id, sort_order) VALUES (?, ?, ?)'
        );
        foreach (array_values($exhibitIds) as $i => $exhibitId) {
            $ins->execute([$id, (int) $exhibitId, $i]);
        }
    }

    public static function syncForExhibit(int $exhibitId, array $collectionIds): void
    {
        $pdo = db();
        $del = $pdo->prepare('DELETE FROM collection_exhibits WHERE exhibit_id = ?');
        $del->execute([$exhibitId]);

        if (empty($collectionIds)) {
            return;
        }

        $ins = $pdo->prepare(
            'INSERT INTO collection_exhibits (collection_id, exhibit_id, sort_order)
             SELECT ?, ?, COALESCE(MAX(sort_order), -1) + 1
             FROM collection_exhibits WHERE collection_id = ?'
        );
        foreach (array_unique(array_map('intval', $collectionIds)) as $collectionId) {
            $ins->execute([$collectionId, $exhibitId, $collectionId]);
        }
    }

    public static function reorder(array $ids): void
    {
        $stmt = db()->prepare('UPDATE collections SET sort_order = ? WHERE id = ? AND deleted_at IS NULL');
        foreach (array_values($ids) as $index => $id) {
            $stmt->execute([$index, $id]);
        }
    }

    public static function softDelete(int $id): void
    {
        $stmt = db()->prepare('UPDATE collections SET deleted_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function hardDelete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM collections WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function restore(int $id): void
    {
        $stmt = db()->prepare('UPDATE collections SET deleted_at = NULL WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function previewImage(array $collection): ?string
    {
        if (!empty($collection['thumbnail_value'])) {
            return (string) $collection['thumbnail_value'];
        }
        $exhibits = self::exhibits((int) $collection['id']);
        foreach ($exhibits as $exhibit) {
            $img = Exhibit::previewImage($exhibit);
            if ($img) {
                return $img;
            }
        }
        return null;
    }
}
