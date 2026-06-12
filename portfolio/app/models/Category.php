<?php

declare(strict_types=1);

class Category
{
    public static function all(): array
    {
        return db()->query(
            'SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY sort_order ASC, id ASC'
        )->fetchAll();
    }

    public static function find(int $id): array|false
    {
        $stmt = db()->prepare('SELECT * FROM categories WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function findBySlug(string $slug): array|false
    {
        $stmt = db()->prepare('SELECT * FROM categories WHERE slug = ? AND deleted_at IS NULL');
        $stmt->execute([$slug]);
        return $stmt->fetch();
    }

    public static function artworks(int $id): array
    {
        $stmt = db()->prepare(
            'SELECT a.*
             FROM artwork_categories ac
             JOIN artworks a ON a.id = ac.artwork_id AND a.deleted_at IS NULL
             WHERE ac.category_id = ?
             ORDER BY a.sort_order ASC, a.id ASC'
        );
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    public static function trashed(): array
    {
        return db()->query(
            'SELECT * FROM categories WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC'
        )->fetchAll();
    }

    public static function trashedCount(): int
    {
        return (int) db()->query(
            'SELECT COUNT(*) FROM categories WHERE deleted_at IS NOT NULL'
        )->fetchColumn();
    }

    public static function create(
        string $name,
        string $slug,
        int    $sortOrder     = 0,
        ?string $thumbType    = null,
        ?string $thumbValue   = null,
        ?string $description  = null
    ): int {
        $stmt = db()->prepare(
            'INSERT INTO categories (name, slug, sort_order, thumbnail_type, thumbnail_value, description)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$name, $slug, $sortOrder, $thumbType, $thumbValue, $description]);
        return (int) db()->lastInsertId();
    }

    public static function update(
        int    $id,
        string $name,
        string $slug,
        int    $sortOrder,
        ?string $thumbType   = null,
        ?string $thumbValue  = null,
        ?string $description = null
    ): void {
        $stmt = db()->prepare(
            'UPDATE categories
             SET name = ?, slug = ?, sort_order = ?,
                 thumbnail_type = ?, thumbnail_value = ?, description = ?
             WHERE id = ?'
        );
        $stmt->execute([$name, $slug, $sortOrder, $thumbType, $thumbValue, $description, $id]);
    }

    public static function softDelete(int $id): void
    {
        $stmt = db()->prepare('UPDATE categories SET deleted_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function hardDelete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM categories WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function restore(int $id): void
    {
        $stmt = db()->prepare('UPDATE categories SET deleted_at = NULL WHERE id = ?');
        $stmt->execute([$id]);
    }
}
