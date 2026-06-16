<?php

declare(strict_types=1);

class BlogCategory
{
    public static function all(): array
    {
        if (!self::tableReady()) {
            return [];
        }

        try {
            return db()->query(
                "SELECT c.*,
                        COUNT(p.id) AS published_post_count
                 FROM categories c
                 LEFT JOIN post_categories pc ON pc.category_id = c.id
                 LEFT JOIN posts p ON p.id = pc.post_id AND p.status = 'published' AND p.deleted_at IS NULL
                 WHERE c.category_scope = 'blog' AND c.deleted_at IS NULL
                 GROUP BY c.id
                 ORDER BY c.sort_order ASC, c.name ASC"
            )->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    public static function find(int $id): array|false
    {
        if (!self::tableReady()) {
            return false;
        }

        try {
            $stmt = db()->prepare(
                "SELECT * FROM categories
                 WHERE id = ? AND category_scope = 'blog' AND deleted_at IS NULL
                 LIMIT 1"
            );
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Throwable) {
            return false;
        }
    }

    public static function findBySlug(string $slug): array|false
    {
        if (!self::tableReady()) {
            return false;
        }

        try {
            $stmt = db()->prepare(
                "SELECT * FROM categories
                 WHERE slug = ? AND category_scope = 'blog' AND deleted_at IS NULL
                 LIMIT 1"
            );
            $stmt->execute([$slug]);
            return $stmt->fetch();
        } catch (Throwable) {
            return false;
        }
    }

    public static function create(
        string $name,
        string $slug,
        int $sortOrder = 0,
        ?string $description = null
    ): int {
        $stmt = db()->prepare(
            'INSERT INTO categories (name, slug, sort_order, description, category_scope)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$name, $slug, $sortOrder, $description, 'blog']);
        return (int) db()->lastInsertId();
    }

    public static function update(
        int $id,
        string $name,
        string $slug,
        int $sortOrder,
        ?string $description = null
    ): void {
        $stmt = db()->prepare(
            "UPDATE categories
             SET name = ?, slug = ?, sort_order = ?, description = ?
             WHERE id = ? AND category_scope = 'blog'"
        );
        $stmt->execute([$name, $slug, $sortOrder, $description, $id]);
    }

    public static function reorder(array $ids): void
    {
        $stmt = db()->prepare(
            "UPDATE categories SET sort_order = ? WHERE id = ? AND deleted_at IS NULL AND category_scope = 'blog'"
        );
        foreach (array_values($ids) as $index => $id) {
            $stmt->execute([$index, $id]);
        }
    }

    public static function softDelete(int $id): void
    {
        $stmt = db()->prepare("UPDATE categories SET deleted_at = NOW() WHERE id = ? AND category_scope = 'blog'");
        $stmt->execute([$id]);
    }

    public static function trashed(): array
    {
        if (!self::tableReady()) {
            return [];
        }

        return db()->query(
            "SELECT * FROM categories
             WHERE category_scope = 'blog' AND deleted_at IS NOT NULL
             ORDER BY deleted_at DESC"
        )->fetchAll();
    }

    public static function restore(int $id): void
    {
        $stmt = db()->prepare("UPDATE categories SET deleted_at = NULL WHERE id = ? AND category_scope = 'blog'");
        $stmt->execute([$id]);
    }

    public static function hardDelete(int $id): void
    {
        $stmt = db()->prepare("DELETE FROM categories WHERE id = ? AND category_scope = 'blog'");
        $stmt->execute([$id]);
    }

    private static function tableReady(): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }

        try {
            $stmt = db()->query("SHOW COLUMNS FROM categories LIKE 'category_scope'");
            $ready = (bool) $stmt->fetch();
        } catch (Throwable) {
            $ready = false;
        }
        return $ready;
    }
}
