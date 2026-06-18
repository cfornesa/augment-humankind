<?php

declare(strict_types=1);

class PostSection
{
    public static function allForPost(int $postId): array
    {
        $stmt = db()->prepare(
            'SELECT * FROM post_sections WHERE post_id = ? ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute([$postId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): array|false
    {
        $stmt = db()->prepare('SELECT * FROM post_sections WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function create(int $postId, string $heading, string $content, int $sortOrder = 0, ?string $wrapperClass = null): int
    {
        $stmt = db()->prepare(
            'INSERT INTO post_sections (post_id, heading, content, wrapper_class, sort_order) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$postId, $heading ?: null, $content, $wrapperClass ?: null, $sortOrder]);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, string $heading, string $content, ?string $wrapperClass = null): void
    {
        $stmt = db()->prepare(
            'UPDATE post_sections SET heading = ?, content = ?, wrapper_class = ? WHERE id = ?'
        );
        $stmt->execute([$heading ?: null, $content, $wrapperClass ?: null, $id]);
    }

    public static function delete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM post_sections WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function deleteAllForPost(int $postId): void
    {
        $stmt = db()->prepare('DELETE FROM post_sections WHERE post_id = ?');
        $stmt->execute([$postId]);
    }

    public static function reorder(int $postId, array $ids): void
    {
        $stmt = db()->prepare(
            'UPDATE post_sections SET sort_order = ? WHERE id = ? AND post_id = ?'
        );
        foreach (array_values($ids) as $index => $id) {
            $stmt->execute([$index, $id, $postId]);
        }
    }
}
