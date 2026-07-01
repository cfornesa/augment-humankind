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
        $adminNavItems = function_exists('admin_navigation_ordered_items') ? admin_navigation_ordered_items() : [];
        $themeOptions = self::themeOptions();
        $colorGroups = self::colorGroups();
        require dirname(__DIR__, 2) . '/views/admin/site-identity/index.php';
    }

    private static function themeOptions(): array
    {
        return [
            'bauhaus'     => 'Bauhaus — Heavy borders, hard shadows, all-caps',
            'traditional' => 'Traditional — Serif body, hairline borders',
            'minimalist'  => 'Minimalist — No borders, generous whitespace',
            'academic'    => 'Academic — Old-style serif, scholarly feel',
            'airy'        => 'Airy — Light weights, soft shadows, rounded',
            'nature'      => 'Nature — Friendly Nunito sans, soft radius',
            'comfort'     => 'Comfort — Quicksand, pillowy radius',
            'audacious'   => 'Audacious — Bebas Neue, oversized borders',
            'artistic'    => 'Artistic — Caveat handwriting, hand-drawn feel',
            'celestial'   => 'Celestial — Cosmic dark, parchment & amber glow',
        ];
    }

    private static function colorGroups(): array
    {
        return [
            'Light Mode' => [
                'color_background'             => 'Background',
                'color_foreground'             => 'Foreground / ink',
                'color_muted'                  => 'Muted background',
                'color_muted_foreground'       => 'Muted foreground',
                'color_primary'                => 'Primary',
                'color_primary_foreground'     => 'Primary foreground',
                'color_secondary'              => 'Secondary',
                'color_secondary_foreground'   => 'Secondary foreground',
                'color_accent'                 => 'Accent',
                'color_accent_foreground'      => 'Accent foreground',
                'color_destructive'            => 'Destructive',
                'color_destructive_foreground' => 'Destructive foreground',
            ],
            'Dark Mode' => [
                'color_background_dark'             => 'Background',
                'color_foreground_dark'             => 'Foreground / ink',
                'color_muted_dark'                  => 'Muted background',
                'color_muted_foreground_dark'       => 'Muted foreground',
                'color_primary_dark'                => 'Primary',
                'color_primary_foreground_dark'     => 'Primary foreground',
                'color_secondary_dark'              => 'Secondary',
                'color_secondary_foreground_dark'   => 'Secondary foreground',
                'color_accent_dark'                 => 'Accent',
                'color_accent_foreground_dark'      => 'Accent foreground',
                'color_destructive_dark'            => 'Destructive',
                'color_destructive_foreground_dark' => 'Destructive foreground',
            ],
        ];
    }

    public static function settingsUpdate(): void
    {
        admin_check();

        $tab = in_array($_POST['tab'] ?? '', ['settings', 'design'], true) ? $_POST['tab'] : 'settings';

        try {
            $data = self::resolveSettingsData();
            self::updateSettings($data);
            header('Location: /admin/site-identity?tab=' . urlencode($tab));
        } catch (Throwable $e) {
            header('Location: /admin/site-identity?tab=' . urlencode($tab) . '&error=' . urlencode($e->getMessage()));
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

    public static function navigationOrderUpdate(): void
    {
        admin_check();

        try {
            $ids = array_values(array_filter(array_map(
                'trim',
                explode(',', (string) ($_POST['ids'] ?? ''))
            )));
            $valid = array_column(admin_navigation_registry(), 'key');
            $ordered = [];
            foreach ($ids as $id) {
                if (in_array($id, $valid, true) && !in_array($id, $ordered, true)) {
                    $ordered[] = $id;
                }
            }
            foreach ($valid as $id) {
                if (!in_array($id, $ordered, true)) {
                    $ordered[] = $id;
                }
            }
            self::updateSettings(['admin_nav_order_json' => json_encode($ordered, JSON_THROW_ON_ERROR)]);
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
        } catch (Throwable $e) {
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Settings and Design are separate <form> elements that both POST here.
     * Only resolve a field if its key was actually submitted — otherwise a
     * Design save (which has no site_title/hero_heading/etc. inputs) would
     * wipe every Settings field back to its default, and vice versa.
     */
    private static function resolveSettingsData(): array
    {
        $data = [];

        $textFieldDefaults = [
            'site_title' => 'My Site',
            'hero_heading' => '',
            'hero_subheading' => '',
            'about_heading' => '',
            'about_body' => '',
            'copyright_line' => '',
            'footer_credit' => '',
            'cta_label' => '',
        ];
        foreach ($textFieldDefaults as $field => $default) {
            if (array_key_exists($field, $_POST)) {
                $data[$field] = trim((string) $_POST[$field]) ?: $default;
            }
        }

        if (array_key_exists('cta_href', $_POST)) {
            $data['cta_href'] = trim((string) $_POST['cta_href']) ?: '/';
        }
        if (array_key_exists('canonical_public_url', $_POST)) {
            $data['canonical_public_url'] = self::normalizeOptionalUrl($_POST['canonical_public_url'] ?? null);
        }
        if (array_key_exists('logo_url', $_POST)) {
            $data['logo_url'] = trim((string) $_POST['logo_url']) ?: null;
        }
        if (array_key_exists('logo_dark_url', $_POST)) {
            $data['logo_dark_url'] = trim((string) $_POST['logo_dark_url']) ?: null;
        }
        if (array_key_exists('logo_layout', $_POST)) {
            $data['logo_layout'] = trim((string) $_POST['logo_layout']) ?: 'text_only';
        }
        if (array_key_exists('default_theme_mode', $_POST)) {
            $data['default_theme_mode'] = trim((string) $_POST['default_theme_mode']) ?: 'system';
        }
        if (array_key_exists('theme', $_POST)) {
            $data['theme'] = mb_substr(trim((string) $_POST['theme']), 0, 32) ?: null;
        }
        if (array_key_exists('palette', $_POST)) {
            $data['palette'] = mb_substr(trim((string) $_POST['palette']), 0, 32) ?: null;
        }
        if (array_key_exists('custom_css', $_POST)) {
            $data['custom_css'] = (string) $_POST['custom_css'];
        }

        foreach (self::colorColumns() as $col) {
            if (!array_key_exists($col, $_POST)) {
                continue;
            }
            $val = trim((string) $_POST[$col]);
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

    /**
     * Only writes columns actually present in $data. Settings, Design, and
     * navigationOrderUpdate() each submit a different subset of this row's
     * columns — never overwrite a column the caller didn't pass, or every
     * partial save (e.g. just admin_nav_order_json) wipes the rest of the row.
     */
    private static function updateSettings(array $data): void
    {
        $allFields = [
            'site_title', 'hero_heading', 'hero_subheading', 'about_heading',
            'about_body', 'copyright_line', 'footer_credit', 'cta_label',
            'cta_href', 'logo_url', 'logo_dark_url', 'logo_layout', 'default_theme_mode',
            'theme', 'palette', 'custom_css', 'canonical_public_url', 'admin_nav_order_json',
            ...self::colorColumns(),
        ];

        $available = SiteSettings::availableColumns();
        if ($available === []) {
            throw new RuntimeException('Site settings table is missing the expected editable columns.');
        }

        $columnFields = array_values(array_filter(
            $allFields,
            static fn (string $field): bool => in_array($field, $available, true)
        ));
        if ($columnFields === []) {
            throw new RuntimeException('Site settings table is missing the expected editable columns.');
        }

        $fieldsToUpdate = array_values(array_filter(
            $columnFields,
            static fn (string $field): bool => array_key_exists($field, $data)
        ));

        $fallbackPayload = [];
        foreach ($allFields as $field) {
            if (!in_array($field, $available, true) && $field !== 'settings_json' && array_key_exists($field, $data)) {
                $fallbackPayload[$field] = $data[$field];
            }
        }

        if ($fallbackPayload !== [] && in_array('settings_json', $available, true)) {
            $existing = SiteSettings::current() ?: [];
            $jsonState = json_decode((string) ($existing['settings_json'] ?? ''), true);
            if (!is_array($jsonState)) {
                $jsonState = [];
            }
            foreach ($fallbackPayload as $field => $value) {
                $jsonState[$field] = $value;
            }
            $data['settings_json'] = json_encode($jsonState, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (!in_array('settings_json', $fieldsToUpdate, true)) {
                $fieldsToUpdate[] = 'settings_json';
            }
        }

        if ($fieldsToUpdate === []) {
            return;
        }

        $sets = [];
        $params = [];
        foreach ($fieldsToUpdate as $field) {
            $sets[] = "$field = ?";
            $params[] = $data[$field] ?? null;
        }
        $params[] = 1; // id

        $stmt = db()->prepare(
            'UPDATE site_settings SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute($params);
    }

    private static function normalizeOptionalUrl(?string $value): ?string
    {
        $clean = trim((string) $value);
        if ($clean === '') {
            return null;
        }
        if (!filter_var($clean, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Canonical Public URL must be a valid absolute URL.');
        }
        return rtrim($clean, '/');
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
