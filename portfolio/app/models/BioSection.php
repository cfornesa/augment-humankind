<?php

declare(strict_types=1);

class BioSection
{
    public static function all(): array
    {
        return db()->query(
            'SELECT * FROM bio_sections ORDER BY sort_order ASC, id ASC'
        )->fetchAll();
    }

    public static function find(int $id): array|false
    {
        $stmt = db()->prepare('SELECT * FROM bio_sections WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function create(string $heading, string $content, int $sortOrder = 0): int
    {
        $stmt = db()->prepare(
            'INSERT INTO bio_sections (heading, content, sort_order) VALUES (?, ?, ?)'
        );
        $stmt->execute([$heading ?: null, $content, $sortOrder]);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, string $heading, string $content, int $sortOrder): void
    {
        $stmt = db()->prepare(
            'UPDATE bio_sections SET heading = ?, content = ?, sort_order = ? WHERE id = ?'
        );
        $stmt->execute([$heading ?: null, $content, $sortOrder, $id]);
    }

    public static function delete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM bio_sections WHERE id = ?');
        $stmt->execute([$id]);
    }
}
