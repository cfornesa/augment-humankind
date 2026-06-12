<?php

declare(strict_types=1);

class Artwork
{
    public static function allSorted(): array
    {
        $rows = db()->query(
            'SELECT a.*
             FROM artworks a
             WHERE a.deleted_at IS NULL
             ORDER BY a.sort_order ASC, a.id ASC'
        )->fetchAll();

        return self::attachExhibits(self::attachCategories($rows));
    }

    public static function all(): array
    {
        return self::allSorted();
    }

    public static function find(int $id): array|false
    {
        $stmt = db()->prepare(
            'SELECT a.*
             FROM artworks a
             WHERE a.id = ? AND a.deleted_at IS NULL'
        );
        $stmt->execute([$id]);
        $artwork = $stmt->fetch();
        return $artwork ? self::decorate($artwork) : false;
    }

    public static function findBySlug(string $slug): array|false
    {
        $stmt = db()->prepare(
            'SELECT a.*
             FROM artworks a
             WHERE a.slug = ? AND a.deleted_at IS NULL'
        );
        $stmt->execute([$slug]);
        $artwork = $stmt->fetch();
        return $artwork ? self::decorate($artwork) : false;
    }

    public static function trashed(): array
    {
        $rows = db()->query(
            'SELECT a.*
             FROM artworks a
             WHERE a.deleted_at IS NOT NULL
             ORDER BY a.deleted_at DESC'
        )->fetchAll();

        return self::attachExhibits(self::attachCategories($rows));
    }

    public static function trashedCount(): int
    {
        return (int) db()->query(
            'SELECT COUNT(*) FROM artworks WHERE deleted_at IS NOT NULL'
        )->fetchColumn();
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO artworks
                (title, artist_name, slug, year, medium, dimensions, description, placard_notes,
                 thumbnail_type, thumbnail_value, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['title'],
            $data['artist_name'] ?: null,
            $data['slug'],
            $data['year'] ?: null,
            $data['medium'] ?: null,
            $data['dimensions'] ?: null,
            $data['description'] ?: null,
            $data['placard_notes'] ?: null,
            $data['thumbnail_type'] ?: null,
            $data['thumbnail_value'] ?: null,
            $data['sort_order'] ?? 0,
        ]);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE artworks SET
                title = ?, artist_name = ?, slug = ?, year = ?, medium = ?, dimensions = ?,
                description = ?, placard_notes = ?,
                thumbnail_type = ?, thumbnail_value = ?, sort_order = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['title'],
            $data['artist_name'] ?: null,
            $data['slug'],
            $data['year'] ?: null,
            $data['medium'] ?: null,
            $data['dimensions'] ?: null,
            $data['description'] ?: null,
            $data['placard_notes'] ?: null,
            $data['thumbnail_type'] ?: null,
            $data['thumbnail_value'] ?: null,
            $data['sort_order'] ?? 0,
            $id,
        ]);
    }

    public static function resolvedMediaItems(array $artwork): array
    {
        $artworkId = (int) ($artwork['id'] ?? 0);
        if ($artworkId <= 0) {
            return [];
        }

        return array_map(
            static fn (array $item): array => ArtworkMediaItem::normalizeForDisplay($item),
            ArtworkMediaItem::allForArtwork($artworkId)
        );
    }

    public static function previewImage(array $artwork): ?string
    {
        if (!empty($artwork['thumbnail_value'])) {
            return (string) $artwork['thumbnail_value'];
        }

        foreach (self::resolvedMediaItems($artwork) as $item) {
            if (($item['display_kind'] ?? '') === 'image' && !empty($item['source_url'])) {
                return (string) $item['source_url'];
            }
            if (($item['display_kind'] ?? '') === 'video' && !empty($item['poster_url'])) {
                return (string) $item['poster_url'];
            }
        }

        return null;
    }

    private static function decorate(array $artwork): array
    {
        $artwork['media_items'] = self::resolvedMediaItems($artwork);
        $artwork['categories']  = self::categoriesFor((int) $artwork['id']);
        $artwork['exhibits']    = self::exhibitsFor((int) $artwork['id']);
        return $artwork;
    }

    public static function categoriesFor(int $artworkId): array
    {
        $stmt = db()->prepare(
            'SELECT c.id, c.name, c.slug
             FROM artwork_categories ac
             JOIN categories c ON c.id = ac.category_id AND c.deleted_at IS NULL
             WHERE ac.artwork_id = ?
             ORDER BY c.sort_order ASC, c.id ASC'
        );
        $stmt->execute([$artworkId]);
        return $stmt->fetchAll();
    }

    public static function categoryIds(int $id): array
    {
        $stmt = db()->prepare('SELECT category_id FROM artwork_categories WHERE artwork_id = ?');
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function syncCategories(int $id, array $categoryIds): void
    {
        $pdo = db();
        $del = $pdo->prepare('DELETE FROM artwork_categories WHERE artwork_id = ?');
        $del->execute([$id]);

        if (empty($categoryIds)) {
            return;
        }

        $ins = $pdo->prepare('INSERT INTO artwork_categories (artwork_id, category_id) VALUES (?, ?)');
        foreach (array_unique(array_map('intval', $categoryIds)) as $categoryId) {
            $ins->execute([$id, $categoryId]);
        }
    }

    private static function attachCategories(array $artworks): array
    {
        if ($artworks === []) {
            return $artworks;
        }

        $ids = array_map(static fn (array $a): int => (int) $a['id'], $artworks);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare(
            "SELECT ac.artwork_id, c.id, c.name, c.slug
             FROM artwork_categories ac
             JOIN categories c ON c.id = ac.category_id AND c.deleted_at IS NULL
             WHERE ac.artwork_id IN ($placeholders)
             ORDER BY c.sort_order ASC, c.id ASC"
        );
        $stmt->execute($ids);

        $byArtwork = [];
        foreach ($stmt->fetchAll() as $row) {
            $byArtwork[$row['artwork_id']][] = ['id' => $row['id'], 'name' => $row['name'], 'slug' => $row['slug']];
        }

        foreach ($artworks as &$artwork) {
            $artwork['categories'] = $byArtwork[$artwork['id']] ?? [];
        }

        return $artworks;
    }

    public static function exhibitsFor(int $artworkId): array
    {
        $stmt = db()->prepare(
            'SELECT e.id, e.name, e.slug
             FROM exhibit_artworks ea
             JOIN exhibits e ON e.id = ea.exhibit_id AND e.deleted_at IS NULL
             WHERE ea.artwork_id = ?
             ORDER BY e.sort_order ASC, e.id ASC'
        );
        $stmt->execute([$artworkId]);
        return $stmt->fetchAll();
    }

    private static function attachExhibits(array $artworks): array
    {
        if ($artworks === []) {
            return $artworks;
        }

        $ids = array_map(static fn (array $a): int => (int) $a['id'], $artworks);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare(
            "SELECT ea.artwork_id, e.id, e.name, e.slug
             FROM exhibit_artworks ea
             JOIN exhibits e ON e.id = ea.exhibit_id AND e.deleted_at IS NULL
             WHERE ea.artwork_id IN ($placeholders)
             ORDER BY e.sort_order ASC, e.id ASC"
        );
        $stmt->execute($ids);

        $byArtwork = [];
        foreach ($stmt->fetchAll() as $row) {
            $byArtwork[$row['artwork_id']][] = ['id' => $row['id'], 'name' => $row['name'], 'slug' => $row['slug']];
        }

        foreach ($artworks as &$artwork) {
            $artwork['exhibits'] = $byArtwork[$artwork['id']] ?? [];
        }

        return $artworks;
    }

    public static function reorder(array $ids): void
    {
        $stmt = db()->prepare('UPDATE artworks SET sort_order = ? WHERE id = ? AND deleted_at IS NULL');
        foreach (array_values($ids) as $index => $id) {
            $stmt->execute([$index, $id]);
        }
    }

    public static function softDelete(int $id): void
    {
        $stmt = db()->prepare('UPDATE artworks SET deleted_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function hardDelete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM artworks WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function restore(int $id): void
    {
        $stmt = db()->prepare('UPDATE artworks SET deleted_at = NULL WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function extractIframeSourcePublic(string $html): ?string
    {
        if (!preg_match('/<iframe\b[^>]*\bsrc=(["\'])(.*?)\1/i', $html, $matches)) {
            return null;
        }

        $src = trim(html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        return $src !== '' ? $src : null;
    }
}
