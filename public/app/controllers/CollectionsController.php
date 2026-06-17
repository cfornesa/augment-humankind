<?php

declare(strict_types=1);

class CollectionsController
{
    private const PAGE_SIZE = 12;

    public static function index(): void
    {
        $q      = trim((string) ($_GET['q'] ?? ''));
        $sort   = (string) ($_GET['sort'] ?? 'newest');
        $offset = max(0, (int) ($_GET['offset'] ?? 0));

        if (!in_array($sort, ['newest', 'oldest', 'az', 'za'], true)) {
            $sort = 'newest';
        }

        [$modelSort, $dir] = match ($sort) {
            'oldest' => ['newest', 'asc'],
            'az'     => ['name',   'asc'],
            'za'     => ['name',   'desc'],
            default  => ['newest', 'desc'],
        };

        $batch = PlatformCollection::searchFiltered($q, $modelSort, $dir, $offset, self::PAGE_SIZE + 1);

        $hasMore     = count($batch) > self::PAGE_SIZE;
        $collections = $hasMore ? array_slice($batch, 0, self::PAGE_SIZE) : $batch;
        $nextOffset  = $offset + self::PAGE_SIZE;

        foreach ($collections as &$collection) {
            $collection['thumbnail_url'] = PlatformCollection::firstThumbnail((int) $collection['id']);
        }
        unset($collection);

        $filterParams = array_filter(['q' => $q, 'sort' => $sort !== 'newest' ? $sort : '']);
        $filterQs     = http_build_query(array_filter($filterParams));
        $fetchUrl     = '/collections' . ($filterQs !== '' ? '?' . $filterQs : '');

        if (($_GET['partial'] ?? '') === '1') {
            header('Content-Type: text/html; charset=utf-8');
            require dirname(__DIR__) . '/views/collections/_batch.php';
            exit;
        }

        $pageTitle = 'Collections | Augment Humankind';
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

        $pageTitle = (($collection['name'] ?? '') ?: 'Collection') . ' | Augment Humankind';
        $pageDescription = seo_excerpt($collection['description'] ?? '', 160)
            ?? 'A curated collection from Augment Humankind.';
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
