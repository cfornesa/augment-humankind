<?php

declare(strict_types=1);

class NavigationController
{
    public static function index(): void
    {
        admin_check();
        $navigationReady = NavigationItem::isAvailable();
        $visibleItems = NavigationItem::adminItems(true);
        $hiddenItems = NavigationItem::adminItems(false);
        $navigationMode = $navigationReady ? 'registry' : 'legacy';
        $navigationError = $_GET['error'] ?? null;
        $adminNavItems = function_exists('admin_navigation_ordered_items') ? admin_navigation_ordered_items() : [];
        require dirname(__DIR__, 2) . '/views/admin/navigation.php';
    }

    public static function externalStore(): void
    {
        admin_check();
        if (!NavigationItem::isAvailable()) {
            header('Location: /admin/navigation?error=migration');
            exit;
        }

        $label = trim($_POST['label'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $visibility = $_POST['visibility'] ?? 'visible';

        if ($label === '' || $url === '') {
            header('Location: /admin/navigation?error=missing');
            exit;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            header('Location: /admin/navigation?error=url');
            exit;
        }

        NavigationItem::createExternal($label, $url, $visibility === 'visible', !empty($_POST['open_in_new_tab']));
        header('Location: /admin/navigation');
        exit;
    }

    public static function labelUpdate(string $id): void
    {
        admin_check();
        if (!NavigationItem::isAvailable()) {
            header('Location: /admin/navigation?error=migration');
            exit;
        }

        $label = trim($_POST['label'] ?? '');
        if ($label === '') {
            header('Location: /admin/navigation?error=label');
            exit;
        }

        NavigationItem::updateExternalLabel((int) $id, $label);
        header('Location: /admin/navigation');
        exit;
    }

    public static function reorder(): void
    {
        admin_check();
        if (!NavigationItem::isAvailable()) {
            header('Content-Type: application/json');
            http_response_code(409);
            echo '{"ok":false,"error":"migration-required"}';
            exit;
        }

        $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
        $visibility = $_POST['visibility'] ?? 'visible';
        NavigationItem::reorder($visibility === 'visible', $ids);
        header('Content-Type: application/json');
        echo '{"ok":true}';
        exit;
    }

    public static function toggle(string $id): void
    {
        admin_check();
        if (!NavigationItem::isAvailable()) {
            header('Location: /admin/navigation?error=migration');
            exit;
        }
        NavigationItem::toggleVisibility((int) $id);
        header('Location: /admin/navigation');
        exit;
    }

    public static function delete(string $id): void
    {
        admin_check();
        if (!NavigationItem::isAvailable()) {
            header('Location: /admin/navigation?error=migration');
            exit;
        }
        NavigationItem::deleteExternal((int) $id);
        header('Location: /admin/navigation');
        exit;
    }

    public static function toggleTarget(string $id): void
    {
        admin_check();
        if (!NavigationItem::isAvailable()) {
            header('Location: /admin/navigation?error=migration');
            exit;
        }
        NavigationItem::toggleExternalTarget((int) $id);
        header('Location: /admin/navigation');
        exit;
    }
}
