<?php

declare(strict_types=1);

class CollectionsController
{
    public static function index(): void
    {
        $collections = PlatformCollection::all();
        foreach ($collections as &$collection) {
            $collection['thumbnail_url'] = PlatformCollection::firstThumbnail((int) $collection['id']);
        }
        unset($collection);

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
