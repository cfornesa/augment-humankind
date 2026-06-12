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

    public static function create(int $pageId, string $heading, string $content, int $sortOrder = 0): int
    {
        $stmt = db()->prepare(
            'INSERT INTO page_sections (page_id, heading, content, sort_order) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$pageId, $heading ?: null, $content, $sortOrder]);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, string $heading, string $content): void
    {
        $stmt = db()->prepare(
            'UPDATE page_sections SET heading = ?, content = ? WHERE id = ?'
        );
        $stmt->execute([$heading ?: null, $content, $id]);
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
