<?php

declare(strict_types=1);

class SiteSettings
{
    private static ?array $columnCache = null;

    private static function decodeSettingsJson(?string $raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function current(): array|false
    {
        if (!self::tableExists()) {
            return false;
        }

        try {
            $row = db()->query('SELECT * FROM site_settings WHERE id = 1 LIMIT 1')->fetch();
            if (!is_array($row)) {
                return false;
            }
            $fallback = self::decodeSettingsJson($row['settings_json'] ?? null);
            if ($fallback === []) {
                return $row;
            }

            $available = self::availableColumns();
            foreach ($fallback as $key => $value) {
                if (!is_string($key)) {
                    continue;
                }
                $columnExists = in_array($key, $available, true);
                $currentValue = $row[$key] ?? null;
                if (!$columnExists || $currentValue === null || $currentValue === '') {
                    $row[$key] = $value;
                }
            }
            return $row;
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

    /**
     * Writes one key into the settings_json fallback blob without touching
     * any other column or fallback key. Arrays/objects are stored as-is so
     * current() surfaces them decoded.
     */
    public static function updateJsonSetting(string $key, mixed $value): void
    {
        if (!self::tableExists() || !self::hasColumn('settings_json')) {
            throw new RuntimeException('Site settings storage is unavailable — run the database setup first.');
        }

        $row = db()->query('SELECT settings_json FROM site_settings WHERE id = 1 LIMIT 1')->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('Site settings row is missing — run the database setup first.');
        }

        $state = self::decodeSettingsJson($row['settings_json'] ?? null);
        $state[$key] = $value;

        $stmt = db()->prepare(
            'UPDATE site_settings SET settings_json = ?, updated_at = NOW() WHERE id = 1'
        );
        $stmt->execute([json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
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
