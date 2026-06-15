<?php

declare(strict_types=1);

class Exhibit
{
    public static function allSorted(): array
    {
        $rows = db()->query(
            'SELECT e.*
             FROM exhibits e
             WHERE e.deleted_at IS NULL
             ORDER BY e.sort_order ASC, e.id ASC'
        )->fetchAll();

        return self::attachCollections(self::attachCategories($rows));
    }

    public static function all(): array
    {
        return self::allSorted();
    }

    public static function find(int $id): array|false
    {
        $stmt = db()->prepare(
            'SELECT e.*
             FROM exhibits e
             WHERE e.id = ? AND e.deleted_at IS NULL'
        );
        $stmt->execute([$id]);
        $exhibit = $stmt->fetch();
        return $exhibit ? self::decorate($exhibit) : false;
    }

    public static function findBySlug(string $slug): array|false
    {
        $stmt = db()->prepare(
            'SELECT e.*
             FROM exhibits e
             WHERE e.slug = ? AND e.deleted_at IS NULL'
        );
        $stmt->execute([$slug]);
        $exhibit = $stmt->fetch();
        return $exhibit ? self::decorate($exhibit) : false;
    }

    public static function trashed(): array
    {
        $rows = db()->query(
            'SELECT e.*
             FROM exhibits e
             WHERE e.deleted_at IS NOT NULL
             ORDER BY e.deleted_at DESC'
        )->fetchAll();

        return self::attachCollections(self::attachCategories($rows));
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
            'INSERT INTO exhibits
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
            'UPDATE exhibits SET
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

    public static function resolvedMediaItems(array $exhibit): array
    {
        $exhibitId = (int) ($exhibit['id'] ?? 0);
        if ($exhibitId <= 0) {
            return [];
        }

        return array_map(
            static fn (array $item): array => ExhibitMediaItem::normalizeForDisplay($item),
            ExhibitMediaItem::allForExhibit($exhibitId)
        );
    }

    public static function previewImage(array $exhibit): ?string
    {
        if (!empty($exhibit['thumbnail_value'])) {
            return (string) $exhibit['thumbnail_value'];
        }

        foreach (self::resolvedMediaItems($exhibit) as $item) {
            if (($item['display_kind'] ?? '') === 'image' && !empty($item['source_url'])) {
                return (string) $item['source_url'];
            }
            if (($item['display_kind'] ?? '') === 'video' && !empty($item['poster_url'])) {
                return (string) $item['poster_url'];
            }
        }

        return null;
    }

    private static function decorate(array $exhibit): array
    {
        $exhibit['media_items'] = self::resolvedMediaItems($exhibit);
        $exhibit['categories']  = self::categoriesFor((int) $exhibit['id']);
        $exhibit['collections'] = self::collectionsFor((int) $exhibit['id']);
        return $exhibit;
    }

    public static function categoriesFor(int $exhibitId): array
    {
        $stmt = db()->prepare(
            'SELECT c.id, c.name, c.slug
             FROM exhibit_categories ec
             JOIN categories c ON c.id = ec.category_id AND c.deleted_at IS NULL
             WHERE ec.exhibit_id = ?
             ORDER BY c.sort_order ASC, c.id ASC'
        );
        $stmt->execute([$exhibitId]);
        return $stmt->fetchAll();
    }

    public static function categoryIds(int $id): array
    {
        $stmt = db()->prepare('SELECT category_id FROM exhibit_categories WHERE exhibit_id = ?');
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function syncCategories(int $id, array $categoryIds): void
    {
        $pdo = db();
        $del = $pdo->prepare('DELETE FROM exhibit_categories WHERE exhibit_id = ?');
        $del->execute([$id]);

        if (empty($categoryIds)) {
            return;
        }

        $ins = $pdo->prepare('INSERT INTO exhibit_categories (exhibit_id, category_id) VALUES (?, ?)');
        foreach (array_unique(array_map('intval', $categoryIds)) as $categoryId) {
            $ins->execute([$id, $categoryId]);
        }
    }

    private static function attachCategories(array $exhibits): array
    {
        if ($exhibits === []) {
            return $exhibits;
        }

        $ids = array_map(static fn (array $e): int => (int) $e['id'], $exhibits);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare(
            "SELECT ec.exhibit_id, c.id, c.name, c.slug
             FROM exhibit_categories ec
             JOIN categories c ON c.id = ec.category_id AND c.deleted_at IS NULL
             WHERE ec.exhibit_id IN ($placeholders)
             ORDER BY c.sort_order ASC, c.id ASC"
        );
        $stmt->execute($ids);

        $byExhibit = [];
        foreach ($stmt->fetchAll() as $row) {
            $byExhibit[$row['exhibit_id']][] = ['id' => $row['id'], 'name' => $row['name'], 'slug' => $row['slug']];
        }

        foreach ($exhibits as &$exhibit) {
            $exhibit['categories'] = $byExhibit[$exhibit['id']] ?? [];
        }

        return $exhibits;
    }

    public static function collectionsFor(int $exhibitId): array
    {
        $stmt = db()->prepare(
            'SELECT c.id, c.name, c.slug
             FROM collection_exhibits ce
             JOIN collections c ON c.id = ce.collection_id AND c.deleted_at IS NULL
             WHERE ce.exhibit_id = ?
             ORDER BY c.sort_order ASC, c.id ASC'
        );
        $stmt->execute([$exhibitId]);
        return $stmt->fetchAll();
    }

    private static function attachCollections(array $exhibits): array
    {
        if ($exhibits === []) {
            return $exhibits;
        }

        $ids = array_map(static fn (array $e): int => (int) $e['id'], $exhibits);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare(
            "SELECT ce.exhibit_id, c.id, c.name, c.slug
             FROM collection_exhibits ce
             JOIN collections c ON c.id = ce.collection_id AND c.deleted_at IS NULL
             WHERE ce.exhibit_id IN ($placeholders)
             ORDER BY c.sort_order ASC, c.id ASC"
        );
        $stmt->execute($ids);

        $byExhibit = [];
        foreach ($stmt->fetchAll() as $row) {
            $byExhibit[$row['exhibit_id']][] = ['id' => $row['id'], 'name' => $row['name'], 'slug' => $row['slug']];
        }

        foreach ($exhibits as &$exhibit) {
            $exhibit['collections'] = $byExhibit[$exhibit['id']] ?? [];
        }

        return $exhibits;
    }

    public static function reorder(array $ids): void
    {
        $stmt = db()->prepare('UPDATE exhibits SET sort_order = ? WHERE id = ? AND deleted_at IS NULL');
        foreach (array_values($ids) as $index => $id) {
            $stmt->execute([$index, $id]);
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

    public static function extractIframeSourcePublic(string $html): ?string
    {
        if (!preg_match('/<iframe\b[^>]*\bsrc=(["\'])(.*?)\1/i', $html, $matches)) {
            return null;
        }

        $src = trim(html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        return $src !== '' ? $src : null;
    }
}
