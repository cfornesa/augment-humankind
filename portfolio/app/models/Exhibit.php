<?php

declare(strict_types=1);

class Exhibit
{
    public static function all(): array
    {
        return db()->query(
            'SELECT * FROM exhibits WHERE deleted_at IS NULL ORDER BY sort_order ASC, id ASC'
        )->fetchAll();
    }

    public static function allWithArtworkCount(): array
    {
        return db()->query(
            'SELECT e.*, COUNT(ea.artwork_id) AS artwork_count
             FROM exhibits e
             LEFT JOIN exhibit_artworks ea ON ea.exhibit_id = e.id
             LEFT JOIN artworks a ON a.id = ea.artwork_id AND a.deleted_at IS NULL
             WHERE e.deleted_at IS NULL
             GROUP BY e.id
             ORDER BY e.sort_order ASC, e.id ASC'
        )->fetchAll();
    }

    public static function allWithAtLeastOneArtwork(): array
    {
        return db()->query(
            'SELECT e.*
             FROM exhibits e
             WHERE e.deleted_at IS NULL
               AND EXISTS (
                   SELECT 1 FROM exhibit_artworks ea
                   JOIN artworks a ON a.id = ea.artwork_id AND a.deleted_at IS NULL
                   WHERE ea.exhibit_id = e.id
               )
             ORDER BY e.sort_order ASC, e.id ASC'
        )->fetchAll();
    }

    public static function find(int $id): array|false
    {
        $stmt = db()->prepare('SELECT * FROM exhibits WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function findBySlug(string $slug): array|false
    {
        $stmt = db()->prepare('SELECT * FROM exhibits WHERE slug = ? AND deleted_at IS NULL');
        $stmt->execute([$slug]);
        return $stmt->fetch();
    }

    public static function artworks(int $id): array
    {
        $stmt = db()->prepare(
            'SELECT a.*
             FROM exhibit_artworks ea
             JOIN artworks a ON a.id = ea.artwork_id AND a.deleted_at IS NULL
             WHERE ea.exhibit_id = ?
             ORDER BY ea.sort_order ASC, a.sort_order ASC, a.id ASC'
        );
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    public static function artworkIds(int $id): array
    {
        $stmt = db()->prepare(
            'SELECT artwork_id FROM exhibit_artworks WHERE exhibit_id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function exhibitIdsForArtwork(int $artworkId): array
    {
        $stmt = db()->prepare(
            'SELECT exhibit_id FROM exhibit_artworks WHERE artwork_id = ?'
        );
        $stmt->execute([$artworkId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function trashed(): array
    {
        return db()->query(
            'SELECT * FROM exhibits WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC'
        )->fetchAll();
    }

    public static function trashedCount(): int
    {
        return (int) db()->query(
            'SELECT COUNT(*) FROM exhibits WHERE deleted_at IS NOT NULL'
        )->fetchColumn();
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO exhibits (name, slug, description, thumbnail_type, thumbnail_value, sort_order)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'],
            $data['slug'],
            $data['description'] ?: null,
            $data['thumbnail_type'] ?: null,
            $data['thumbnail_value'] ?: null,
            $data['sort_order'] ?? 0,
        ]);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE exhibits
             SET name = ?, slug = ?, description = ?,
                 thumbnail_type = ?, thumbnail_value = ?, sort_order = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['name'],
            $data['slug'],
            $data['description'] ?: null,
            $data['thumbnail_type'] ?: null,
            $data['thumbnail_value'] ?: null,
            $data['sort_order'] ?? 0,
            $id,
        ]);
    }

    public static function syncArtworks(int $id, array $artworkIds): void
    {
        $pdo = db();
        $del = $pdo->prepare('DELETE FROM exhibit_artworks WHERE exhibit_id = ?');
        $del->execute([$id]);

        if (empty($artworkIds)) {
            return;
        }

        $ins = $pdo->prepare(
            'INSERT INTO exhibit_artworks (exhibit_id, artwork_id, sort_order) VALUES (?, ?, ?)'
        );
        foreach (array_values($artworkIds) as $i => $artworkId) {
            $ins->execute([$id, (int) $artworkId, $i]);
        }
    }

    public static function syncForArtwork(int $artworkId, array $exhibitIds): void
    {
        $pdo = db();
        $del = $pdo->prepare('DELETE FROM exhibit_artworks WHERE artwork_id = ?');
        $del->execute([$artworkId]);

        if (empty($exhibitIds)) {
            return;
        }

        $ins = $pdo->prepare(
            'INSERT INTO exhibit_artworks (exhibit_id, artwork_id, sort_order)
             SELECT ?, ?, COALESCE(MAX(sort_order), -1) + 1
             FROM exhibit_artworks WHERE exhibit_id = ?'
        );
        foreach (array_unique(array_map('intval', $exhibitIds)) as $exhibitId) {
            $ins->execute([$exhibitId, $artworkId, $exhibitId]);
        }
    }

    public static function softDelete(int $id): void
    {
        $stmt = db()->prepare('UPDATE exhibits SET deleted_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function hardDelete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM exhibits WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function restore(int $id): void
    {
        $stmt = db()->prepare('UPDATE exhibits SET deleted_at = NULL WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function uniqueSlug(string $name, int $excludeId = 0): string
    {
        $base = slugify($name);
        $slug = $base;
        $i    = 2;
        while (true) {
            $stmt = db()->prepare(
                'SELECT id FROM exhibits WHERE slug = ? AND id != ?'
            );
            $stmt->execute([$slug, $excludeId]);
            if (!$stmt->fetch()) {
                break;
            }
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
