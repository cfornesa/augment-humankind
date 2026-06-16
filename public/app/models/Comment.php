<?php

declare(strict_types=1);

class Comment
{
    public static function find(int $id): array|false
    {
        if (!self::tableExists()) {
            return false;
        }

        $stmt = db()->prepare('SELECT * FROM comments WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function recent(int $limit = 100): array
    {
        if (!self::tableExists()) {
            return [];
        }

        $stmt = db()->prepare(
            'SELECT c.*, p.title AS post_title
             FROM comments c
             LEFT JOIN posts p ON p.id = c.post_id
             WHERE c.deleted_at IS NULL
             ORDER BY c.created_at DESC, c.id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function trashed(): array
    {
        if (!self::tableExists()) {
            return [];
        }

        return db()->query(
            'SELECT c.*, p.title AS post_title
             FROM comments c
             LEFT JOIN posts p ON p.id = c.post_id
             WHERE c.deleted_at IS NOT NULL
             ORDER BY c.deleted_at DESC'
        )->fetchAll();
    }

    public static function trashedCount(): int
    {
        if (!self::tableExists()) {
            return 0;
        }

        return (int) db()->query('SELECT COUNT(*) FROM comments WHERE deleted_at IS NOT NULL')->fetchColumn();
    }

    public static function softDelete(int $id): void
    {
        $stmt = db()->prepare('UPDATE comments SET deleted_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function restore(int $id): void
    {
        $stmt = db()->prepare('UPDATE comments SET deleted_at = NULL WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function hardDelete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM comments WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function updateContent(int $id, string $content): void
    {
        $stmt = db()->prepare('UPDATE comments SET content = ? WHERE id = ?');
        $stmt->execute([$content, $id]);
    }

    public static function commentsFor(string $itemType, int $itemId): array
    {
        if (!self::tableExists()) {
            return [];
        }

        try {
            $stmt = db()->prepare(
                'SELECT * FROM comments
                 WHERE item_type = ? AND item_id = ? AND deleted_at IS NULL
                 ORDER BY created_at ASC, id ASC'
            );
            $stmt->execute([$itemType, $itemId]);
            return $stmt->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    public static function insertComment(
        string  $itemType,
        int     $itemId,
        string  $authorName,
        string  $content,
        ?int    $postId = null,
        ?string $authorId = null,
        ?string $authorUserId = null,
        ?string $authorImageUrl = null
    ): void {
        $resolvedAuthorId = $authorId ?? $authorUserId ?? ('anon-' . bin2hex(random_bytes(8)));
        $stmt = db()->prepare(
            'INSERT INTO comments
                (post_id, item_type, item_id, author_id, author_user_id, author_name, author_image_url, content, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(3))'
        );
        $stmt->execute([
            $postId,
            $itemType,
            $itemId,
            $resolvedAuthorId,
            $authorUserId,
            $authorName,
            $authorImageUrl,
            $content,
        ]);
    }

    public static function toApiPayload(array $comment): array
    {
        return [
            'id' => (int) ($comment['id'] ?? 0),
            'author_name' => (string) ($comment['author_name'] ?? 'Anonymous'),
            'content' => (string) ($comment['content'] ?? ''),
            'created_at' => (string) ($comment['created_at'] ?? ''),
            'can_manage' => comment_belongs_to_current_actor($comment),
        ];
    }

    public static function toApiPayloadList(array $comments): array
    {
        return array_map(
            static fn (array $comment): array => self::toApiPayload($comment),
            $comments
        );
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
            $stmt->execute(['comments']);
            return $exists = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return $exists = false;
        }
    }
}
