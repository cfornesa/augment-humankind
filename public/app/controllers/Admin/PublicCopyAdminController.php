<?php

declare(strict_types=1);

class PublicCopyAdminController
{
    private const TABS = ['gallery', 'archives', 'detail', 'art-archives', 'shared-ui'];

    public static function index(): void
    {
        admin_check();

        $sections = public_copy_admin_sections();
        $tab = (string) ($_GET['tab'] ?? 'gallery');
        if (!in_array($tab, self::TABS, true)) {
            $tab = 'gallery';
        }
        $saved = isset($_GET['saved']);
        $error = $_GET['error'] ?? null;

        require dirname(__DIR__, 2) . '/views/admin/public-copy/index.php';
    }

    public static function save(): void
    {
        admin_check();

        $tab = (string) ($_POST['tab'] ?? 'gallery');
        if (!in_array($tab, self::TABS, true)) {
            $tab = 'gallery';
        }

        try {
            $posted = $_POST['copy'] ?? [];
            if (!is_array($posted)) {
                $posted = [];
            }

            $portfolioCopy = [];
            $publicArtCopy = [];

            // Only process fields belonging to the active tab.
            foreach (public_copy_admin_sections() as $section) {
                if (($section['tab'] ?? '') !== $tab) {
                    continue;
                }
                foreach ($section['fields'] as $field) {
                    $path = (string) $field['path'];
                    $value = trim((string) ($posted[$path] ?? ''));

                    if (str_starts_with($path, 'portfolio_copy.')) {
                        public_copy_path_set($portfolioCopy, substr($path, strlen('portfolio_copy.')), $value);
                        continue;
                    }

                    if (str_starts_with($path, 'public_art_copy.')) {
                        public_copy_path_set($publicArtCopy, substr($path, strlen('public_art_copy.')), $value);
                    }
                }
            }

            if ($portfolioCopy !== []) {
                // Merge with stored value so other tabs' fields are preserved.
                $stored = SiteSettings::current();
                $existing = (is_array($stored) && isset($stored['portfolio_copy']) && is_array($stored['portfolio_copy']))
                    ? $stored['portfolio_copy']
                    : [];
                $merged = array_replace_recursive($existing, $portfolioCopy);
                SiteSettings::updateJsonSetting('portfolio_copy', $merged);
            }

            if ($publicArtCopy !== []) {
                $stored = SiteSettings::current();
                $existing = (is_array($stored) && isset($stored['public_art_copy']) && is_array($stored['public_art_copy']))
                    ? $stored['public_art_copy']
                    : [];
                $merged = array_replace_recursive($existing, $publicArtCopy);
                SiteSettings::updateJsonSetting('public_art_copy', $merged);
            }

            audit_log_event('admin_settings', 'public_copy_save', 'success', [
                'metadata' => [
                    'tab' => $tab,
                    'portfolio_copy_keys' => array_keys($portfolioCopy),
                    'public_art_copy_keys' => array_keys($publicArtCopy),
                ],
            ]);

            header('Location: /admin/public-copy?tab=' . rawurlencode($tab) . '&saved=1', true, 303);
        } catch (Throwable $e) {
            audit_log_event('admin_settings', 'public_copy_save', 'error', [
                'metadata' => ['tab' => $tab, 'message' => $e->getMessage()],
            ]);

            header('Location: /admin/public-copy?tab=' . rawurlencode($tab) . '&error=' . rawurlencode($e->getMessage()), true, 303);
        }
        exit;
    }

}
