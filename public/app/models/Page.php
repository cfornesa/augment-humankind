<?php

declare(strict_types=1);

class Page
{
    private const RESERVED_SLUGS = [
        'admin', 'portfolio', 'contact', 'media', 'image', 'home',
    ];

    public static function all(): array
    {
        return db()->query(
            'SELECT * FROM pages WHERE deleted_at IS NULL ORDER BY sort_order ASC, id ASC'
        )->fetchAll();
    }

    public static function navItems(): array
    {
        return db()->query(
            'SELECT id, title, slug, nav_label, status, show_in_nav, sort_order
             FROM pages
             WHERE deleted_at IS NULL AND status = "published" AND show_in_nav = 1
             ORDER BY sort_order ASC, id ASC'
        )->fetchAll();
    }

    public static function find(int $id): array|false
    {
        $stmt = db()->prepare('SELECT * FROM pages WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function findBySlug(string $slug): array|false
    {
        $stmt = db()->prepare('SELECT * FROM pages WHERE slug = ? AND deleted_at IS NULL');
        $stmt->execute([$slug]);
        return $stmt->fetch();
    }

    public static function findPublishedBySlug(string $slug): array|false
    {
        $stmt = db()->prepare('SELECT * FROM pages WHERE slug = ? AND status = ? AND deleted_at IS NULL');
        $stmt->execute([$slug, 'published']);
        return $stmt->fetch();
    }

    public static function safeFindPublishedBySlug(string $slug): array|false
    {
        try {
            return self::findPublishedBySlug($slug);
        } catch (Throwable) {
            return false;
        }
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO pages
                (title, slug, status, template, nav_label, show_in_nav,
                 meta_title, meta_description, og_title, og_description, og_image, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['title'],
            $data['slug'],
            $data['status'],
            $data['template'],
            $data['nav_label'] ?: null,
            !empty($data['show_in_nav']) ? 1 : 0,
            $data['meta_title'] ?: null,
            $data['meta_description'] ?: null,
            $data['og_title'] ?: null,
            $data['og_description'] ?: null,
            $data['og_image'] ?: null,
            $data['sort_order'] ?? 0,
        ]);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE pages SET
                title = ?, slug = ?, status = ?, template = ?, nav_label = ?, show_in_nav = ?,
                meta_title = ?, meta_description = ?, og_title = ?, og_description = ?, og_image = ?, sort_order = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['title'],
            $data['slug'],
            $data['status'],
            $data['template'],
            $data['nav_label'] ?: null,
            !empty($data['show_in_nav']) ? 1 : 0,
            $data['meta_title'] ?: null,
            $data['meta_description'] ?: null,
            $data['og_title'] ?: null,
            $data['og_description'] ?: null,
            $data['og_image'] ?: null,
            $data['sort_order'] ?? 0,
            $id,
        ]);
    }

    public static function softDelete(int $id): void
    {
        $stmt = db()->prepare('UPDATE pages SET deleted_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function hardDelete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM pages WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function restore(int $id): void
    {
        $stmt = db()->prepare('UPDATE pages SET deleted_at = NULL WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function trashed(): array
    {
        return db()->query(
            'SELECT * FROM pages WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC'
        )->fetchAll();
    }

    public static function trashedCount(): int
    {
        return (int) db()->query(
            'SELECT COUNT(*) FROM pages WHERE deleted_at IS NOT NULL'
        )->fetchColumn();
    }

    public static function reorder(array $ids): void
    {
        $stmt = db()->prepare('UPDATE pages SET sort_order = ? WHERE id = ? AND deleted_at IS NULL');
        foreach (array_values($ids) as $index => $id) {
            $stmt->execute([$index, $id]);
        }
    }

    public static function validateSlug(string $slug, int $excludeId = 0): string
    {
        $slug = slugify($slug);
        if ($slug === '') {
            throw new InvalidArgumentException('Slug is required.');
        }
        if (in_array($slug, self::RESERVED_SLUGS, true) && !self::isExistingSystemPage($slug, $excludeId)) {
            throw new InvalidArgumentException('That slug is reserved by the site.');
        }

        $stmt = db()->prepare('SELECT id FROM pages WHERE slug = ? AND id != ? AND deleted_at IS NULL');
        $stmt->execute([$slug, $excludeId]);
        if ($stmt->fetch()) {
            throw new InvalidArgumentException('That slug is already in use.');
        }

        return $slug;
    }

    private static function isExistingSystemPage(string $slug, int $excludeId): bool
    {
        $stmt = db()->prepare('SELECT id FROM pages WHERE slug = ?');
        $stmt->execute([$slug]);
        $existingId = $stmt->fetchColumn();
        return $existingId !== false && (int) $existingId === $excludeId;
    }
}
