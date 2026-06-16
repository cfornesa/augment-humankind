<?php

declare(strict_types=1);

class SiteIdentityAdminController
{
    public static function index(): void
    {
        admin_check();
        $settings = SiteSettings::current() ?: [];
        $assets = SiteAsset::all();
        $mediaAssets = MediaAsset::all();
        require dirname(__DIR__, 2) . '/views/admin/site-identity/index.php';
    }

    public static function settingsUpdate(): void
    {
        admin_check();

        try {
            $data = self::resolveSettingsData();
            self::updateSettings($data);
            header('Location: /admin/site-identity');
        } catch (Throwable $e) {
            header('Location: /admin/site-identity?error=' . urlencode($e->getMessage()));
        }
        exit;
    }

    public static function assetCreate(): void
    {
        admin_check();

        try {
            $data = self::resolveAssetData();
            SiteAsset::create($data);
            header('Location: /admin/site-identity');
        } catch (Throwable $e) {
            header('Location: /admin/site-identity?error=' . urlencode($e->getMessage()));
        }
        exit;
    }

    public static function assetDelete(string $id): void
    {
        admin_check();
        SiteAsset::delete((int) $id);
        header('Location: /admin/site-identity');
        exit;
    }

    public static function mediaAssetDelete(string $id): void
    {
        admin_check();
        MediaAsset::softDelete((int) $id);
        header('Location: /admin/site-identity');
        exit;
    }

    private static function resolveSettingsData(): array
    {
        $data = [
            'site_title' => trim($_POST['site_title'] ?? '') ?: 'Augment Humankind',
            'hero_heading' => trim($_POST['hero_heading'] ?? '') ?: '',
            'hero_subheading' => trim($_POST['hero_subheading'] ?? '') ?: '',
            'about_heading' => trim($_POST['about_heading'] ?? '') ?: '',
            'about_body' => trim($_POST['about_body'] ?? '') ?: '',
            'copyright_line' => trim($_POST['copyright_line'] ?? '') ?: '',
            'footer_credit' => trim($_POST['footer_credit'] ?? '') ?: '',
            'cta_label' => trim($_POST['cta_label'] ?? '') ?: '',
            'cta_href' => trim($_POST['cta_href'] ?? '') ?: '/',
            'logo_url' => trim($_POST['logo_url'] ?? '') ?: null,
            'logo_dark_url' => trim($_POST['logo_dark_url'] ?? '') ?: null,
            'logo_layout' => trim($_POST['logo_layout'] ?? '') ?: 'text_only',
            'default_theme_mode' => trim($_POST['default_theme_mode'] ?? '') ?: 'system',
            'theme'   => mb_substr(trim($_POST['theme'] ?? ''), 0, 32) ?: null,
            'palette' => mb_substr(trim($_POST['palette'] ?? ''), 0, 32) ?: null,
        ];

        foreach (self::colorColumns() as $col) {
            $val = trim($_POST[$col] ?? '');
            // Accept "H S% L%" or bare "H S L" — reject anything else to prevent injection
            if ($val !== '' && !preg_match('/^[\d.]+\s+[\d.]+%?\s+[\d.]+%?$/', $val)) {
                $val = '';
            }
            $data[$col] = $val !== '' ? $val : null;
        }

        return $data;
    }

    private static function colorColumns(): array
    {
        return [
            'color_background', 'color_foreground',
            'color_muted', 'color_muted_foreground',
            'color_primary', 'color_primary_foreground',
            'color_secondary', 'color_secondary_foreground',
            'color_accent', 'color_accent_foreground',
            'color_destructive', 'color_destructive_foreground',
            'color_background_dark', 'color_foreground_dark',
            'color_muted_dark', 'color_muted_foreground_dark',
            'color_primary_dark', 'color_primary_foreground_dark',
            'color_secondary_dark', 'color_secondary_foreground_dark',
            'color_accent_dark', 'color_accent_foreground_dark',
            'color_destructive_dark', 'color_destructive_foreground_dark',
        ];
    }

    private static function updateSettings(array $data): void
    {
        $fields = [
            'site_title', 'hero_heading', 'hero_subheading', 'about_heading',
            'about_body', 'copyright_line', 'footer_credit', 'cta_label',
            'cta_href', 'logo_url', 'logo_dark_url', 'logo_layout', 'default_theme_mode',
            'theme', 'palette',
            ...self::colorColumns(),
        ];

        $sets = [];
        $params = [];
        foreach ($fields as $field) {
            $sets[] = "$field = ?";
            $params[] = $data[$field] ?? null;
        }
        $params[] = 1; // id

        $stmt = db()->prepare(
            'UPDATE site_settings SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute($params);
    }

    private static function resolveAssetData(): array
    {
        $key = trim($_POST['asset_key'] ?? '');
        if ($key === '') {
            throw new InvalidArgumentException('Asset key is required.');
        }

        $file = $_FILES['asset_file'] ?? null;
        if (!$file || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new InvalidArgumentException('Asset file is required.');
        }

        $data = file_get_contents($file['tmp_name']);
        if ($data === false) {
            throw new InvalidArgumentException('Failed to read asset file.');
        }

        return [
            'asset_key' => $key,
            'filename' => $file['name'] ?? null,
            'mime_type' => $file['type'] ?? 'application/octet-stream',
            'byte_size' => strlen($data),
            'data' => $data,
            'file_data' => $data,
        ];
    }
}
