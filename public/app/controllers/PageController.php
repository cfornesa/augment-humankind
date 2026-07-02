<?php

declare(strict_types=1);

class PageController
{
    private static function canPreviewDrafts(): bool
    {
        return !empty($_SESSION['admin_identity_id']);
    }

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
        if (!$page && $slug === 'home') {
            $page = Page::findBySystemKey('home');
            if ($page && ($page['status'] ?? 'draft') !== 'published') {
                $page = false;
            }
        }
        $isPreview = false;
        if (!$page && self::canPreviewDrafts()) {
            $candidate = Page::safeFindBySlug($slug);
            if ($candidate && ($candidate['status'] ?? 'draft') === 'draft') {
                $page = $candidate;
                $isPreview = true;
            } elseif ($slug === 'home') {
                $candidate = Page::findBySystemKey('home');
                if ($candidate && ($candidate['status'] ?? 'draft') === 'draft') {
                    $page = $candidate;
                    $isPreview = true;
                }
            }
        }
        if (!$page) {
            return false;
        }

        $sections = PageSection::allForPage((int) $page['id']);
        require dirname(__DIR__) . '/views/managed_page.php';
        return true;
    }

    public static function redirectIfSlugMoved(string $slug): bool
    {
        $redirect = Page::redirectForSlug($slug);
        if (!$redirect) {
            return false;
        }

        header('Location: /' . rawurlencode((string) $redirect['target_slug']), true, 301);
        return true;
    }

    /**
     * Single-entry Atom/JSON Feed for a published managed page, mirroring
     * the platform's per-page feed routes.
     */
    public static function feed(string $format, string $slug): void
    {
        $page = Page::safeFindPublishedBySlug($slug);
        if (!$page) {
            self::notFound();
        }

        $origin = seo_origin();
        $entry = self::pageEntry($page, $origin);
        $meta = seo_site_meta();
        $scope = [
            'id' => $origin . '/' . $page['slug'],
            'title' => $meta['title'] . ' — ' . $entry['title'],
            'description' => 'Updates to the "' . $entry['title'] . '" page.',
            'alternate' => $origin . '/' . $page['slug'],
            'feed_url_xml' => $origin . '/' . $page['slug'] . '/feed.xml',
            'feed_url_json' => $origin . '/' . $page['slug'] . '/feed.json',
            'author_name' => $entry['author_name'],
        ];

        if ($format === 'json') {
            header('Content-Type: application/feed+json; charset=utf-8');
            echo json_encode(
                BlogController::jsonFeedPayload([$entry], $scope),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            );
            return;
        }

        header('Content-Type: application/atom+xml; charset=utf-8');
        echo BlogController::atomXml([$entry], $scope);
    }

    private static function pageEntry(array $page, string $origin): array
    {
        $url = $origin . '/' . $page['slug'];

        $html = '';
        foreach (PageSection::allForPage((int) $page['id']) as $section) {
            if (!empty($section['heading'])) {
                $html .= '<h2>' . e($section['heading']) . '</h2>';
            }
            $html .= (string) $section['content'];
        }
        $contentText = strip_tags($html);

        return [
            'id' => $url,
            'url' => $url,
            'title' => (string) (($page['title'] ?? '') ?: 'Untitled page'),
            'published' => (string) $page['created_at'],
            'updated' => (string) ($page['updated_at'] ?? $page['created_at']),
            'content_html' => $html,
            'content_text' => $contentText,
            'summary' => seo_excerpt($contentText, 220) ?? '',
            'author_name' => seo_author_name(),
            'categories' => [],
        ];
    }

    private static function notFound(): never
    {
        http_response_code(404);
        require dirname(__DIR__) . '/views/404.php';
        exit;
    }
}
