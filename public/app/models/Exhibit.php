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

    public static function paginateSorted(int $offset, int $limit): array
    {
        $stmt = db()->prepare(
            'SELECT e.*
             FROM exhibits e
             WHERE e.deleted_at IS NULL
             ORDER BY e.sort_order ASC, e.id ASC
             LIMIT ?, ?'
        );
        $stmt->bindValue(1, max(0, $offset), PDO::PARAM_INT);
        $stmt->bindValue(2, max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return self::attachCollections(self::attachCategories($stmt->fetchAll()));
    }

    public static function countVisible(): int
    {
        return (int) db()->query(
            "SELECT COUNT(*) FROM exhibits WHERE deleted_at IS NULL AND status = 'active'"
        )->fetchColumn();
    }

    public static function countExisting(): int
    {
        return (int) db()->query(
            'SELECT COUNT(*) FROM exhibits WHERE deleted_at IS NULL'
        )->fetchColumn();
    }

    public static function latestActive(int $limit = 3): array
    {
        // GREATEST(created_at, updated_at), not plain created_at — must match
        // searchFiltered()'s 'newest' sort exactly, since the portfolio
        // gallery's "See More" button continues this preview by re-querying
        // searchFiltered(..., 'newest', ...) at an offset. A mismatched sort
        // here means the offset continuation reorders the list underneath
        // itself, causing cards to repeat or get skipped.
        $stmt = db()->prepare(
            "SELECT e.*
             FROM exhibits e
             WHERE e.deleted_at IS NULL AND e.status = 'active'
             ORDER BY GREATEST(e.created_at, e.updated_at) DESC, e.id DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return self::attachCollections(self::attachCategories($stmt->fetchAll()));
    }

    public static function paginateLatest(int $offset, int $limit): array
    {
        $stmt = db()->prepare(
            "SELECT e.*
             FROM exhibits e
             WHERE e.deleted_at IS NULL AND e.status = 'active'
             ORDER BY e.created_at DESC, e.id DESC
             LIMIT ?, ?"
        );
        $stmt->bindValue(1, max(0, $offset), PDO::PARAM_INT);
        $stmt->bindValue(2, max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return self::attachCollections(self::attachCategories($stmt->fetchAll()));
    }

    public static function searchFiltered(string $q, string $sort = 'newest', string $dir = 'desc', int $offset = 0, int $limit = 500, bool $adminMode = false): array
    {
        $like = $q !== '' ? '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%' : '%';

        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';
        $sortCol = match ($sort) {
            'title'      => 'e.title',
            'created'    => 'e.created_at',
            'updated'    => 'e.updated_at',
            'sort_order' => 'e.sort_order',
            'az'         => 'e.title',
            'za'         => 'e.title',
            default      => 'GREATEST(e.created_at, e.updated_at)',
        };
        if ($sort === 'sort_order') {
            $dir = 'ASC';
        } elseif ($sort === 'az') {
            $dir = 'ASC';
        } elseif ($sort === 'za') {
            $dir = 'DESC';
        }

        $statusClause = $adminMode ? '' : "AND e.status = 'active'";
        $stmt = db()->prepare(
            "SELECT e.*
             FROM exhibits e
             WHERE e.deleted_at IS NULL
               {$statusClause}
               AND (e.title LIKE ? OR e.description LIKE ?)
             ORDER BY {$sortCol} {$dir}, e.id {$dir}
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$like, $like, $limit, $offset]);
        return self::attachCollections(self::attachCategories($stmt->fetchAll()));
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
                 thumbnail_type, thumbnail_value, sort_order, comments_enabled, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
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
            isset($data['comments_enabled']) ? (int)(bool) $data['comments_enabled'] : 0,
            $data['status'] ?? 'active',
        ]);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE exhibits SET
                title = ?, artist_name = ?, slug = ?, year = ?, medium = ?, dimensions = ?,
                description = ?, placard_notes = ?,
                thumbnail_type = ?, thumbnail_value = ?, sort_order = ?, comments_enabled = ?,
                status = ?, updated_at = NOW()
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
            isset($data['comments_enabled']) ? (int)(bool) $data['comments_enabled'] : 0,
            $data['status'] ?? 'active',
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
