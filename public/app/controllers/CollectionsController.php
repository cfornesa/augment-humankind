<?php

declare(strict_types=1);

class CollectionsController
{
    private const PAGE_SIZE = 12;

    public static function index(): void
    {
        $q      = trim((string) ($_GET['q'] ?? ''));
        // Defaults to the same curated sort_order the admin platform
        // collections list uses, so this page's order matches the admin by
        // default. Only diverges when the visitor explicitly picks a sort.
        $sort   = (string) ($_GET['sort'] ?? 'curated');
        $offset = max(0, (int) ($_GET['offset'] ?? 0));

        if (!in_array($sort, ['curated', 'newest', 'oldest', 'az', 'za', 'relevance'], true)) {
            $sort = 'curated';
        }
        if ($sort === 'relevance' && $q === '') {
            $sort = 'curated';
        }

        [$modelSort, $dir] = match ($sort) {
            'newest'    => ['newest', 'desc'],
            'oldest'    => ['newest', 'asc'],
            'az'        => ['name',   'asc'],
            'za'        => ['name',   'desc'],
            'relevance' => ['relevance', 'desc'],
            default     => ['sort_order', 'asc'],
        };

        $batch = PlatformCollection::searchFiltered($q, $modelSort, $dir, $offset, self::PAGE_SIZE + 1);

        $hasMore     = count($batch) > self::PAGE_SIZE;
        $collections = $hasMore ? array_slice($batch, 0, self::PAGE_SIZE) : $batch;
        $nextOffset  = $offset + self::PAGE_SIZE;

        foreach ($collections as &$collection) {
            $collection['thumbnail_url'] = PlatformCollection::firstThumbnail((int) $collection['id']);
        }
        unset($collection);

        $filterParams = array_filter(['q' => $q, 'sort' => $sort !== 'curated' ? $sort : '']);
        $filterQs     = http_build_query(array_filter($filterParams));
        $fetchUrl     = '/collections' . ($filterQs !== '' ? '?' . $filterQs : '');

        if (($_GET['partial'] ?? '') === '1') {
            header('Content-Type: text/html; charset=utf-8');
            require dirname(__DIR__) . '/views/collections/_batch.php';
            exit;
        }

        $pageTitle = 'Collections | ' . app_site_name();
        $pageDescription = 'Curated collections of generative art pieces and images.';
        $bodyClass = 'page-collections';
        $canonicalUrl = seo_absolute_url('/collections');
        require dirname(__DIR__) . '/views/collections/index.php';
    }

    public static function show(string $slug): void
    {
        $collection = PlatformCollection::findBySlug($slug);
        if (!$collection) {
            self::notFound();
        }

        $items = self::hydrateItems($collection['items'] ?? []);

        $pageTitle = (($collection['name'] ?? '') ?: 'Collection') . ' | ' . app_site_name();
        $pageDescription = seo_excerpt($collection['description'] ?? '', 160)
            ?? 'A curated collection from ' . app_site_name() . '.';
        $bodyClass = 'page-collection';
        $canonicalUrl = seo_absolute_url('/collections/' . $slug);

        $ogImage = null;
        foreach ($items as $item) {
            if ($item['type'] === 'art_piece' && !empty($item['piece']['thumbnail_url'])) {
                $ogImage = $item['piece']['thumbnail_url'];
                break;
            }
        }

        require dirname(__DIR__) . '/views/collections/show.php';
    }

    private static function hydrateItems(array $items): array
    {
        $hydrated = [];
        foreach ($items as $item) {
            $type = (string) ($item['item_type'] ?? '');
            $id = (int) ($item['item_id'] ?? 0);
            if ($type === 'art_piece') {
                $piece = PlatformArtPiece::find($id);
                if ($piece) {
                    $hydrated[] = ['type' => 'art_piece', 'piece' => $piece];
                }
            } elseif ($type === 'media_asset') {
                $media = MediaAsset::find($id);
                if ($media) {
                    $hydrated[] = ['type' => 'media_asset', 'media' => $media];
                }
            }
        }
        return $hydrated;
    }

    private static function notFound(): never
    {
        http_response_code(404);
        require dirname(__DIR__) . '/views/404.php';
        exit;
    }
}
