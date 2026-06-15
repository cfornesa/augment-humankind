<?php

declare(strict_types=1);

class Reaction
{
    public static function recent(int $limit = 100): array
    {
        if (!self::tableExists()) {
            return [];
        }

        $stmt = db()->prepare(
            'SELECT r.*, p.title AS post_title, u.name AS user_name
             FROM reactions r
             LEFT JOIN posts p ON p.id = r.post_id
             LEFT JOIN users u ON u.id = r.user_id
             ORDER BY r.created_at DESC, r.id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function delete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM reactions WHERE id = ?');
        $stmt->execute([$id]);
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
            $stmt->execute(['reactions']);
            return $exists = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return $exists = false;
        }
    }
}
