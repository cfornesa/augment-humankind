<?php

declare(strict_types=1);

class PageController
{
    /**
     * Render a published managed page by slug.
     *
     * Returns true if the page was found and rendered (caller should exit).
     * Returns false if no published page exists for this slug, so the
     * caller can fall back to its own static content or 404 handling.
     */
    public static function show(string $slug): bool
    {
        $page = Page::safeFindPublishedBySlug($slug);
        if (!$page) {
            return false;
        }

        $sections = PageSection::allForPage((int) $page['id']);
        require dirname(__DIR__) . '/views/managed_page.php';
        return true;
    }
}
