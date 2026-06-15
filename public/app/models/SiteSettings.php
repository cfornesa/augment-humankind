<?php

declare(strict_types=1);

class SiteSettings
{
    public static function current(): array|false
    {
        if (!self::tableExists()) {
            return false;
        }

        try {
            return db()->query('SELECT * FROM site_settings WHERE id = 1 LIMIT 1')->fetch();
        } catch (Throwable) {
            return false;
        }
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
            $stmt->execute(['site_settings']);
            return $exists = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return $exists = false;
        }
    }
}
