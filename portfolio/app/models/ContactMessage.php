<?php

declare(strict_types=1);

class ContactMessage
{
    public static function all(): array
    {
        return db()->query(
            "SELECT * FROM contact_messages WHERE deleted_at IS NULL
             ORDER BY is_pinned DESC,
                      (CASE WHEN is_pinned = 1 THEN sort_order ELSE 0 END) ASC,
                      created_at DESC"
        )->fetchAll();
    }

    public static function trashed(): array
    {
        return db()->query(
            'SELECT * FROM contact_messages WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC'
        )->fetchAll();
    }

    public static function trashedCount(): int
    {
        return (int) db()->query(
            'SELECT COUNT(*) FROM contact_messages WHERE deleted_at IS NOT NULL'
        )->fetchColumn();
    }

    public static function softDelete(int $id): void
    {
        $stmt = db()->prepare('UPDATE contact_messages SET deleted_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function hardDelete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM contact_messages WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function restore(int $id): void
    {
        $stmt = db()->prepare('UPDATE contact_messages SET deleted_at = NULL WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function toggleRead(int $id): void
    {
        $stmt = db()->prepare('UPDATE contact_messages SET is_read = NOT is_read WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function toggleFlagged(int $id): void
    {
        $stmt = db()->prepare('UPDATE contact_messages SET is_flagged = NOT is_flagged WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function togglePinned(int $id): void
    {
        $stmt = db()->prepare('SELECT is_pinned FROM contact_messages WHERE id = ?');
        $stmt->execute([$id]);
        $isPinned = (bool) $stmt->fetchColumn();

        if ($isPinned) {
            $stmt = db()->prepare('UPDATE contact_messages SET is_pinned = 0 WHERE id = ?');
            $stmt->execute([$id]);
            return;
        }

        $minOrder = (int) db()->query(
            'SELECT COALESCE(MIN(sort_order), 0) FROM contact_messages WHERE is_pinned = 1'
        )->fetchColumn();

        $stmt = db()->prepare('UPDATE contact_messages SET is_pinned = 1, sort_order = ? WHERE id = ?');
        $stmt->execute([$minOrder - 1, $id]);
    }

    public static function reorder(array $ids): void
    {
        $stmt = db()->prepare('UPDATE contact_messages SET sort_order = ? WHERE id = ?');
        foreach (array_values($ids) as $i => $id) {
            $stmt->execute([$i, $id]);
        }
    }
}
