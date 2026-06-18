<?php

declare(strict_types=1);

class SiteSettings
{
    private static ?array $columnCache = null;

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

    public static function canonicalPublicUrl(): ?string
    {
        $settings = self::current();
        $raw = trim((string) ($settings['canonical_public_url'] ?? ''));
        return $raw !== '' ? rtrim($raw, '/') : null;
    }

    public static function adminNavOrder(): array
    {
        $settings = self::current();
        $raw = trim((string) ($settings['admin_nav_order_json'] ?? ''));
        if ($raw === '') {
            return function_exists('admin_navigation_default_order')
                ? admin_navigation_default_order()
                : [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return function_exists('admin_navigation_default_order')
                ? admin_navigation_default_order()
                : [];
        }

        $keys = array_values(array_filter($decoded, static fn ($key): bool => is_string($key) && $key !== ''));
        return $keys !== []
            ? $keys
            : (function_exists('admin_navigation_default_order') ? admin_navigation_default_order() : []);
    }

    public static function availableColumns(): array
    {
        if (self::$columnCache !== null) {
            return self::$columnCache;
        }

        if (!self::tableExists()) {
            return self::$columnCache = [];
        }

        try {
            $stmt = db()->prepare(
                'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
            );
            $stmt->execute(['site_settings']);
            return self::$columnCache = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (Throwable) {
            return self::$columnCache = [];
        }
    }

    public static function hasColumn(string $column): bool
    {
        return in_array($column, self::availableColumns(), true);
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
