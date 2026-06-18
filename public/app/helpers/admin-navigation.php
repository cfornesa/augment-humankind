<?php

declare(strict_types=1);

function admin_navigation_registry(): array
{
    return [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => '/admin', 'description' => 'Overview and quick links.'],
        ['key' => 'posts', 'label' => 'Posts', 'href' => '/admin/posts', 'description' => 'Publish and schedule blog posts.'],
        ['key' => 'comments', 'label' => 'Comments', 'href' => '/admin/comments', 'description' => 'Moderate comments and reactions.'],
        ['key' => 'feed', 'label' => 'External Feeds', 'href' => '/admin/feed-sources', 'description' => 'Connect, manage, and review imported feed items.'],
        ['key' => 'identity', 'label' => 'Identity', 'href' => '/admin/site-identity', 'description' => 'Site-wide copy and brand settings.'],
        ['key' => 'navigation', 'label' => 'Navigation', 'href' => '/admin/navigation', 'description' => 'Public navigation links and ordering.'],
        ['key' => 'ai_settings', 'label' => 'AI Settings', 'href' => '/admin/ai-settings', 'description' => 'Profiles, keys, and preferred AI vendors.'],
        ['key' => 'users', 'label' => 'Users', 'href' => '/admin/user-profiles', 'description' => 'Public user accounts and profiles.'],
        ['key' => 'connections', 'label' => 'Connections', 'href' => '/admin/platform-connections', 'description' => 'Outbound platform syndication connections.'],
        ['key' => 'pages', 'label' => 'Pages', 'href' => '/admin/pages', 'description' => 'Managed pages and sections.'],
        ['key' => 'exhibits', 'label' => 'Exhibits', 'href' => '/admin/exhibits', 'description' => 'Native exhibits.'],
        ['key' => 'pieces', 'label' => 'Pieces', 'href' => '/admin/pieces', 'description' => 'Platform art pieces and generation.'],
        ['key' => 'categories', 'label' => 'Categories', 'href' => '/admin/categories', 'description' => 'Blog categories.'],
        ['key' => 'art_media', 'label' => 'Art Media', 'href' => '/admin/art-media', 'description' => 'Portfolio taxonomy.'],
        ['key' => 'exhibit_collections', 'label' => 'Exhibit Collections', 'href' => '/admin/exhibit-collections', 'description' => 'Native collections.'],
        ['key' => 'platform_collections', 'label' => 'Platform Collections', 'href' => '/admin/platform-collections', 'description' => 'Migrated platform collections.'],
        ['key' => 'media', 'label' => 'Media', 'href' => '/admin/media', 'description' => 'Uploads and media assets.'],
        ['key' => 'trash', 'label' => 'Trash', 'href' => '/admin/trash', 'description' => 'Restore or purge deleted content.'],
    ];
}

function admin_navigation_default_order(): array
{
    return array_map(
        static fn (array $item): string => (string) $item['key'],
        admin_navigation_registry()
    );
}

function admin_navigation_ordered_items(): array
{
    $registry = admin_navigation_registry();
    $order = class_exists('SiteSettings') ? SiteSettings::adminNavOrder() : admin_navigation_default_order();
    $lookup = [];
    foreach ($registry as $item) {
        $lookup[$item['key']] = $item;
    }

    $ordered = [];
    foreach ($order as $key) {
        if (isset($lookup[$key])) {
            $ordered[] = $lookup[$key];
            unset($lookup[$key]);
        }
    }

    foreach ($registry as $item) {
        if (isset($lookup[$item['key']])) {
            $ordered[] = $item;
        }
    }

    return $ordered;
}

function admin_navigation_is_active(string $currentPath, string $href): bool
{
    $currentQuery = parse_url($currentPath, PHP_URL_QUERY) ?: '';
    $currentPath = parse_url($currentPath, PHP_URL_PATH) ?: $currentPath;
    $targetPath = parse_url($href, PHP_URL_PATH) ?: $href;
    $targetQuery = parse_url($href, PHP_URL_QUERY) ?: '';

    if ($targetPath === '/admin') {
        return $currentPath === '/admin';
    }

    if ($targetQuery !== '') {
        return $currentPath === $targetPath && $currentQuery === $targetQuery;
    }

    return $currentPath === $targetPath || str_starts_with($currentPath, rtrim($targetPath, '/') . '/');
}

function admin_navigation_label_map(): array
{
    $map = [];
    foreach (admin_navigation_registry() as $item) {
        $map[(string) $item['key']] = (string) $item['label'];
    }
    return $map;
}
