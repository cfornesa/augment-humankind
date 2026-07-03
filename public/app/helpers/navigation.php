<?php

declare(strict_types=1);

function ah_fallback_navigation_items(): array
{
    return [
        ['label' => 'Home', 'url' => '/', 'target' => null],
        ['label' => 'Services', 'url' => '/services', 'target' => null],
        ['label' => 'Notes', 'url' => '/notes', 'target' => null],
        ['label' => 'Blog', 'url' => '/blog', 'target' => null],
        ['label' => 'Contact', 'url' => '/contact', 'target' => null],
        ['label' => 'Portfolio', 'url' => '/portfolio', 'target' => null],
    ];
}

function ah_public_navigation_items(): array
{
    try {
        if (!function_exists('db')) {
            require_once dirname(__DIR__) . '/bootstrap.php';
        }
        require_once dirname(__DIR__) . '/models/Page.php';
        require_once dirname(__DIR__) . '/models/NavigationItem.php';
        require_once __DIR__ . '/features.php';

        $items = NavigationItem::publicItems();
        $items = $items !== [] ? $items : ah_fallback_navigation_items();
        return ah_navigation_apply_feature_gating($items);
    } catch (Throwable) {
        return ah_fallback_navigation_items();
    }
}

/**
 * Content-safe feature gating: a disabled feature's nav link stays while
 * published content exists (its public URLs keep working) and disappears
 * only once the feature is both off and empty.
 */
function ah_navigation_apply_feature_gating(array $items): array
{
    if (!function_exists('feature_enabled')) {
        return $items;
    }

    return array_values(array_filter($items, static function (array $item): bool {
        $key = $item['active_key'] ?? null;

        if ($key === 'blog') {
            return feature_enabled('blog') || feature_has_public_content('blog');
        }

        if ($key === 'portfolio') {
            foreach (['pieces', 'platform_collections', 'exhibits', 'exhibit_collections'] as $feature) {
                if (feature_enabled($feature) || feature_has_public_content($feature)) {
                    return true;
                }
            }
            return false;
        }

        return true;
    }));
}
