<?php

declare(strict_types=1);

class FeaturesAdminController
{
    private const TABS = ['pieces', 'exhibits', 'blog', 'ai'];

    public static function index(): void
    {
        admin_check();

        $registry = feature_registry();
        $flags = feature_flags();

        $contentCounts = [];
        foreach ($registry as $key => $meta) {
            if ($meta['model'] === null) {
                continue;
            }
            try {
                $contentCounts[$key] = class_exists($meta['model']) && method_exists($meta['model'], 'countExisting')
                    ? (int) $meta['model']::countExisting()
                    : 0;
            } catch (Throwable) {
                $contentCounts[$key] = 0;
            }
        }

        require dirname(__DIR__, 2) . '/views/admin/features/index.php';
    }

    public static function save(): void
    {
        admin_check();

        $tab = (string) ($_POST['tab'] ?? 'pieces');
        if (!in_array($tab, self::TABS, true)) {
            $tab = 'pieces';
        }

        try {
            $registry = feature_registry();
            $flags = feature_flags();
            $posted = $_POST['features'] ?? [];
            if (!is_array($posted)) {
                $posted = [];
            }

            // Each subtab posts only its own group. A child whose required
            // parent is off renders as a disabled checkbox (absent from the
            // POST), so its stored value is preserved rather than read —
            // the requires chain keeps it effectively off either way.
            $storedEnabled = function (string $key) use (&$flags, $registry, &$storedEnabled): bool {
                $value = array_key_exists($key, $flags)
                    ? (filter_var($flags[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true)
                    : true;
                if (!$value) {
                    return false;
                }
                foreach ($registry[$key]['requires'] ?? [] as $parent) {
                    if (isset($registry[$parent]) && !$storedEnabled($parent)) {
                        return false;
                    }
                }
                return true;
            };

            // Registry order lists parents before children, so cascaded
            // parent changes from this same POST are already in $flags.
            foreach ($registry as $key => $meta) {
                if ($meta['group'] !== $tab) {
                    continue;
                }
                $parentsEnabled = true;
                foreach ($meta['requires'] as $parent) {
                    if (!$storedEnabled($parent)) {
                        $parentsEnabled = false;
                        break;
                    }
                }
                if ($parentsEnabled) {
                    $flags[$key] = array_key_exists($key, $posted);
                }
            }

            SiteSettings::updateJsonSetting('features_json', $flags);
            feature_flags_override(null);

            audit_log_event('admin_settings', 'feature_flags_save', 'success', [
                'metadata' => ['tab' => $tab, 'flags' => $flags],
            ]);

            header('Location: /admin/features?tab=' . rawurlencode($tab) . '&saved=1', true, 303);
        } catch (Throwable $e) {
            audit_log_event('admin_settings', 'feature_flags_save', 'error', [
                'metadata' => ['tab' => $tab, 'message' => $e->getMessage()],
            ]);
            header('Location: /admin/features?tab=' . rawurlencode($tab) . '&error=' . rawurlencode($e->getMessage()), true, 303);
        }
        exit;
    }
}
