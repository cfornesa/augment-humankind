<?php

declare(strict_types=1);

class SiteThemeCode
{
    public static function forTheme(string $name): ?array
    {
        $stmt = db()->prepare('SELECT * FROM site_theme_code WHERE theme_name = ? LIMIT 1');
        $stmt->execute([$name]);
        return $stmt->fetch() ?: null;
    }

    /**
     * INSERT … ON DUPLICATE KEY UPDATE — never touches default_* columns.
     */
    public static function upsert(
        string $name,
        string $label,
        string $css,
        string $js,
        string $html,
        bool $isBuiltin = true
    ): void {
        db()->prepare(
            'INSERT INTO site_theme_code
                (theme_name, label, custom_css, custom_js, custom_html_body, is_builtin)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                label            = VALUES(label),
                custom_css       = VALUES(custom_css),
                custom_js        = VALUES(custom_js),
                custom_html_body = VALUES(custom_html_body),
                is_builtin       = VALUES(is_builtin),
                updated_at       = NOW()'
        )->execute([$name, $label, $css ?: null, $js ?: null, $html ?: null, $isBuiltin ? 1 : 0]);
    }

    /**
     * Seeds both custom_* AND default_* — call only once at install/seed time.
     */
    public static function seed(
        string $name,
        string $label,
        string $css,
        string $js,
        string $html,
        bool $isBuiltin = true
    ): void {
        db()->prepare(
            'INSERT INTO site_theme_code
                (theme_name, label, custom_css, custom_js, custom_html_body,
                 default_css, default_js, default_html_body, is_builtin)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                label             = VALUES(label),
                custom_css        = VALUES(custom_css),
                custom_js         = VALUES(custom_js),
                custom_html_body  = VALUES(custom_html_body),
                default_css       = VALUES(default_css),
                default_js        = VALUES(default_js),
                default_html_body = VALUES(default_html_body),
                is_builtin        = VALUES(is_builtin),
                updated_at        = NOW()'
        )->execute([
            $name, $label,
            $css ?: null, $js ?: null, $html ?: null,
            $css ?: null, $js ?: null, $html ?: null,
            $isBuiltin ? 1 : 0,
        ]);
    }

    /**
     * Copy default_* back into custom_* for the given theme.
     * Throws if the theme has no defaults.
     */
    public static function resetToDefaults(string $name): void
    {
        $row = self::forTheme($name);
        if (!$row) {
            throw new InvalidArgumentException("Theme '{$name}' not found.");
        }
        if ($row['default_css'] === null && $row['default_js'] === null && $row['default_html_body'] === null) {
            throw new InvalidArgumentException("Theme '{$name}' has no stored defaults to reset to.");
        }
        db()->prepare(
            'UPDATE site_theme_code
             SET custom_css = default_css, custom_js = default_js, custom_html_body = default_html_body,
                 updated_at = NOW()
             WHERE theme_name = ?'
        )->execute([$name]);
    }

    public static function getAll(): array
    {
        return db()->query('SELECT * FROM site_theme_code ORDER BY label ASC')->fetchAll();
    }

    public static function exists(string $name): bool
    {
        $stmt = db()->prepare('SELECT 1 FROM site_theme_code WHERE theme_name = ? LIMIT 1');
        $stmt->execute([$name]);
        return (bool) $stmt->fetchColumn();
    }
}
