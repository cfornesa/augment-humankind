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
                 ORDER BY c.name ASC"
            )->fetchAll();
        } catch (Throwable) {
            return [];
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
