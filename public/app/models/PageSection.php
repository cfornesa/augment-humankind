<?php

declare(strict_types=1);

class PageSection
{
    public static function allForPage(int $pageId): array
    {
        $stmt = db()->prepare(
            'SELECT * FROM page_sections WHERE page_id = ? ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute([$pageId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): array|false
    {
        $stmt = db()->prepare('SELECT * FROM page_sections WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function create(int $pageId, string $heading, string $content, int $sortOrder = 0, ?string $wrapperClass = null): int
    {
        $stmt = db()->prepare(
            'INSERT INTO page_sections (page_id, heading, content, wrapper_class, sort_order) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$pageId, $heading ?: null, $content, $wrapperClass ?: null, $sortOrder]);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, string $heading, string $content, ?string $wrapperClass = null): void
    {
        $stmt = db()->prepare(
            'UPDATE page_sections SET heading = ?, content = ?, wrapper_class = ? WHERE id = ?'
        );
        $stmt->execute([$heading ?: null, $content, $wrapperClass ?: null, $id]);
    }

    public static function delete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM page_sections WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function reorder(int $pageId, array $ids): void
    {
        $stmt = db()->prepare(
            'UPDATE page_sections SET sort_order = ? WHERE id = ? AND page_id = ?'
        );
        foreach (array_values($ids) as $index => $id) {
            $stmt->execute([$index, $id, $pageId]);
        }
    }
}
