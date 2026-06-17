<?php

declare(strict_types=1);

class BlogPost
{
    public static function published(int $limit = 25, int $offset = 0, string $sort = 'newest', string $cat = '', string $q = ''): array
    {
        if (!self::tableExists('posts')) {
            return [];
        }

        $order = $sort === 'oldest' ? 'p.created_at ASC, p.id ASC' : 'p.created_at DESC, p.id DESC';
        $params = [];

        $sql = "SELECT p.*, fs.name AS source_name, fs.site_url AS source_site_url
                FROM posts p
                LEFT JOIN feed_sources fs ON fs.id = p.source_feed_id";

        if ($cat !== '') {
            $sql .= " INNER JOIN post_categories pc2 ON pc2.post_id = p.id
                      INNER JOIN categories c2 ON c2.id = pc2.category_id
                          AND c2.slug = ? AND c2.category_scope = 'blog' AND c2.deleted_at IS NULL";
            $params[] = $cat;
        }

        $sql .= " WHERE p.status = ? AND p.deleted_at IS NULL";
        $params[] = 'published';

        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $sql .= " AND (p.title LIKE ? OR p.content_text LIKE ? OR p.content LIKE ?)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($cat !== '') {
            $sql .= " GROUP BY p.id";
        }

        $sql .= " ORDER BY {$order} LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = db()->prepare($sql);
        foreach ($params as $i => $val) {
            $stmt->bindValue($i + 1, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return self::attachMeta($stmt->fetchAll());
    }

    public static function search(string $query, int $limit = 25): array
    {
        $query = trim($query);
        if ($query === '' || !self::tableExists('posts')) {
            return [];
        }

        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $query) . '%';
        $stmt = db()->prepare(
            'SELECT p.*, fs.name AS source_name, fs.site_url AS source_site_url
             FROM posts p
             LEFT JOIN feed_sources fs ON fs.id = p.source_feed_id
             WHERE p.status = ? AND p.deleted_at IS NULL
               AND (p.title LIKE ? OR p.content_text LIKE ? OR p.content LIKE ?)
             ORDER BY p.created_at DESC, p.id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, 'published');
        $stmt->bindValue(2, $like);
        $stmt->bindValue(3, $like);
        $stmt->bindValue(4, $like);
        $stmt->bindValue(5, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return self::attachMeta($stmt->fetchAll());
    }

    public static function findPublished(int $id): array|false
    {
        if (!self::tableExists('posts')) {
            return false;
        }

        $stmt = db()->prepare(
            'SELECT p.*, fs.name AS source_name, fs.site_url AS source_site_url
             FROM posts p
             LEFT JOIN feed_sources fs ON fs.id = p.source_feed_id
             WHERE p.id = ? AND p.status = ? AND p.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([$id, 'published']);
        $post = $stmt->fetch();
        if (!$post) {
            return false;
        }
        $posts = self::attachMeta([$post]);
        return $posts[0] ?? false;
    }

    public static function findByPlatformSourceId(int $sourceId): array|false
    {
        if (!self::tableExists('posts')) {
            return false;
        }

        $stmt = db()->prepare('SELECT id FROM posts WHERE platform_source_id = ? LIMIT 1');
        $stmt->execute([$sourceId]);
        $id = $stmt->fetchColumn();
        return $id === false ? false : self::findPublished((int) $id);
    }

    public static function byCategory(string $slug, int $limit = 25): array
    {
        if (!self::tableExists('posts') || !self::tableExists('post_categories')) {
            return [];
        }

        $stmt = db()->prepare(
            'SELECT p.*, fs.name AS source_name, fs.site_url AS source_site_url
             FROM posts p
             JOIN post_categories pc ON pc.post_id = p.id
             JOIN categories c ON c.id = pc.category_id
             LEFT JOIN feed_sources fs ON fs.id = p.source_feed_id
             WHERE c.slug = ? AND c.category_scope = ? AND c.deleted_at IS NULL
               AND p.status = ? AND p.deleted_at IS NULL
             ORDER BY p.created_at DESC, p.id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $slug);
        $stmt->bindValue(2, 'blog');
        $stmt->bindValue(3, 'published');
        $stmt->bindValue(4, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return self::attachMeta($stmt->fetchAll());
    }

    public static function commentsFor(int $postId): array
    {
        if (!self::tableExists('comments')) {
            return [];
        }

        try {
            $stmt = db()->prepare(
                'SELECT *
                 FROM comments
                 WHERE post_id = ? AND deleted_at IS NULL
                 ORDER BY created_at ASC, id ASC'
            );
            $stmt->execute([$postId]);
            return $stmt->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    public static function allForAdmin(?string $status = null): array
    {
        if (!self::tableExists('posts')) {
            return [];
        }

        $sql = 'SELECT p.*, fs.name AS source_name, fs.site_url AS source_site_url
                FROM posts p
                LEFT JOIN feed_sources fs ON fs.id = p.source_feed_id
                WHERE p.deleted_at IS NULL';
        $params = [];
        if ($status !== null) {
            $sql .= ' AND p.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY p.created_at DESC, p.id DESC';

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return self::attachMeta($stmt->fetchAll());
    }

    public static function find(int $id): array|false
    {
        if (!self::tableExists('posts')) {
            return false;
        }

        $stmt = db()->prepare(
            'SELECT p.*, fs.name AS source_name, fs.site_url AS source_site_url
             FROM posts p
             LEFT JOIN feed_sources fs ON fs.id = p.source_feed_id
             WHERE p.id = ? AND p.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([$id]);
        $post = $stmt->fetch();
        if (!$post) {
            return false;
        }
        $posts = self::attachMeta([$post]);
        return $posts[0] ?? false;
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO posts
                (author_id, author_user_id, author_name, author_image_url,
                 title, content, content_text, content_format, status, source_feed_id,
                 source_guid, source_canonical_url, scheduled_at, featured_image_url, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $data['author_id'],
            $data['author_user_id'],
            $data['author_name'],
            $data['author_image_url'],
            $data['title'],
            $data['content'],
            $data['content_text'],
            $data['content_format'],
            $data['status'],
            $data['source_feed_id'] ?? null,
            $data['source_guid'] ?? null,
            $data['source_canonical_url'] ?? null,
            $data['scheduled_at'],
            $data['featured_image_url'],
        ]);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE posts
             SET title = ?, content = ?, content_text = ?, content_format = ?,
                 status = ?, scheduled_at = ?, featured_image_url = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['title'],
            $data['content'],
            $data['content_text'],
            $data['content_format'],
            $data['status'],
            $data['scheduled_at'],
            $data['featured_image_url'],
            $id,
        ]);
    }

    public static function trashed(): array
    {
        return db()->query(
            'SELECT * FROM posts WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC'
        )->fetchAll();
    }

    public static function trashedCount(): int
    {
        return (int) db()->query(
            'SELECT COUNT(*) FROM posts WHERE deleted_at IS NOT NULL'
        )->fetchColumn();
    }

    public static function softDelete(int $id): void
    {
        $stmt = db()->prepare('UPDATE posts SET deleted_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function restore(int $id): void
    {
        $stmt = db()->prepare('UPDATE posts SET deleted_at = NULL WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function hardDelete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM posts WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function categoryIds(int $postId): array
    {
        $stmt = db()->prepare('SELECT category_id FROM post_categories WHERE post_id = ?');
        $stmt->execute([$postId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function syncCategories(int $postId, array $categoryIds): void
    {
        $pdo = db();
        $del = $pdo->prepare('DELETE FROM post_categories WHERE post_id = ?');
        $del->execute([$postId]);

        if (empty($categoryIds)) {
            return;
        }

        $ins = $pdo->prepare('INSERT INTO post_categories (post_id, category_id) VALUES (?, ?)');
        foreach (array_unique(array_map('intval', $categoryIds)) as $categoryId) {
            $ins->execute([$postId, $categoryId]);
        }
    }

    /**
     * Flip due `scheduled` posts to `published`, matching the platform's
     * publishDuePosts() behaviour: scheduled_at is cleared and created_at is
     * overwritten to the publish moment so sort order reflects publish time.
     */
    public static function publishDuePosts(): array
    {
        if (!self::tableExists('posts')) {
            return [];
        }

        $now = date('Y-m-d H:i:s');
        $stmt = db()->prepare(
            "SELECT id FROM posts
             WHERE status = 'scheduled' AND scheduled_at IS NOT NULL AND scheduled_at <= ?
               AND deleted_at IS NULL"
        );
        $stmt->execute([$now]);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if ($ids === []) {
            return [];
        }

        $update = db()->prepare(
            "UPDATE posts SET status = 'published', scheduled_at = NULL, created_at = ? WHERE id = ?"
        );
        foreach ($ids as $id) {
            $update->execute([$now, $id]);
        }

        return $ids;
    }

    private static function attachMeta(array $posts): array
    {
        if ($posts === []) {
            return [];
        }

        $ids = array_map(static fn (array $post): int => (int) $post['id'], $posts);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $categoryRows = [];
        try {
            $stmt = db()->prepare(
                "SELECT pc.post_id, c.name, c.slug
                 FROM post_categories pc
                 JOIN categories c ON c.id = pc.category_id AND c.deleted_at IS NULL
                 WHERE c.category_scope = 'blog' AND pc.post_id IN ({$placeholders})
                 ORDER BY c.name ASC"
            );
            $stmt->execute($ids);
            $categoryRows = $stmt->fetchAll();
        } catch (Throwable) {
            $categoryRows = [];
        }

        $commentCounts = [];
        $reactionCounts = [];
        try {
            $stmt = db()->prepare(
                "SELECT post_id, COUNT(*) AS count
                 FROM comments
                 WHERE deleted_at IS NULL AND post_id IN ({$placeholders})
                 GROUP BY post_id"
            );
            $stmt->execute($ids);
            foreach ($stmt->fetchAll() as $row) {
                $commentCounts[(int) $row['post_id']] = (int) $row['count'];
            }
        } catch (Throwable) {
            $commentCounts = [];
        }

        try {
            $stmt = db()->prepare(
                "SELECT post_id, COUNT(*) AS count
                 FROM reactions
                 WHERE post_id IN ({$placeholders})
                 GROUP BY post_id"
            );
            $stmt->execute($ids);
            foreach ($stmt->fetchAll() as $row) {
                $reactionCounts[(int) $row['post_id']] = (int) $row['count'];
            }
        } catch (Throwable) {
            $reactionCounts = [];
        }

        $categories = [];
        foreach ($categoryRows as $row) {
            $categories[(int) $row['post_id']][] = [
                'name' => (string) $row['name'],
                'slug' => (string) $row['slug'],
            ];
        }

        foreach ($posts as &$post) {
            $id = (int) $post['id'];
            $post['categories'] = $categories[$id] ?? [];
            $post['comment_count'] = $commentCounts[$id] ?? 0;
            $post['reaction_count'] = $reactionCounts[$id] ?? 0;
        }
        unset($post);

        return $posts;
    }

    private static function tableExists(string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }
        try {
            $stmt = db()->prepare(
                'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $stmt->execute([$table]);
            return $cache[$table] = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return $cache[$table] = false;
        }
    }
}
