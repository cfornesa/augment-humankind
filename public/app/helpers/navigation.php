<?php

declare(strict_types=1);

function ah_fallback_navigation_items(): array
{
    return [
        ['label' => 'Mission', 'url' => '/', 'target' => null],
        ['label' => 'Services', 'url' => '/services', 'target' => null],
        ['label' => 'Field Notes', 'url' => '/notes', 'target' => null],
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

        $items = NavigationItem::publicItems();
        return $items !== [] ? $items : ah_fallback_navigation_items();
    } catch (Throwable) {
        return ah_fallback_navigation_items();
    }
}
