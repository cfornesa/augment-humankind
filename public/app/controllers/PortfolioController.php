<?php

declare(strict_types=1);

class PortfolioController
{
    private const GALLERY_PREVIEW_LIMIT = 3;
    private const ARCHIVE_PAGE_SIZE = 12;

    public static function gallery(): void
    {
        $collections = self::decorateCollections(
            Collection::paginateWithAtLeastOneExhibit(0, self::GALLERY_PREVIEW_LIMIT)
        );
        $collectionTotal = Collection::countWithAtLeastOneExhibit();
        $exhibits = self::decorateExhibits(
            Exhibit::paginateSorted(0, self::GALLERY_PREVIEW_LIMIT)
        );
        $exhibitTotal = Exhibit::countVisible();
        $platformCollections = self::decoratePlatformCollections(
            PlatformCollection::paginate(0, self::GALLERY_PREVIEW_LIMIT)
        );
        $platformCollectionTotal = PlatformCollection::countVisible();
        $pieces = self::decoratePieces(
            PlatformArtPiece::paginate(0, self::GALLERY_PREVIEW_LIMIT)
        );
        $pieceTotal = PlatformArtPiece::countActive();
        require dirname(__DIR__) . '/views/portfolio/gallery.php';
    }

    public static function collectionsIndex(): void
    {
        self::renderArchive(
            itemType: 'collections',
            pageTitle: 'Exhibit Collections | Augment Humankind',
            pageDescription: 'Browse native exhibit collections from Augment Humankind.',
            canonicalPath: '/portfolio/exhibit-collections',
            eyebrow: 'Portfolio',
            heading: 'Exhibit Collections',
            intro: 'Native exhibit collections, each gathering related exhibits into a durable archive page.',
            fetchItems: static fn (int $offset, int $limit): array => self::decorateCollections(
                Collection::paginateWithAtLeastOneExhibit($offset, $limit)
            ),
            fetchTotal: static fn (): int => Collection::countWithAtLeastOneExhibit()
        );
    }

    public static function exhibitsIndex(): void
    {
        self::renderArchive(
            itemType: 'exhibits',
            pageTitle: 'Portfolio Exhibits | Augment Humankind',
            pageDescription: 'Browse exhibits from the Augment Humankind portfolio.',
            canonicalPath: '/portfolio/exhibits',
            eyebrow: 'Portfolio',
            heading: 'Exhibits',
            intro: 'Individual exhibits with media, metadata, and collection context.',
            fetchItems: static fn (int $offset, int $limit): array => self::decorateExhibits(
                Exhibit::paginateSorted($offset, $limit)
            ),
            fetchTotal: static fn (): int => Exhibit::countVisible()
        );
    }

    public static function platformCollectionsIndex(): void
    {
        self::renderArchive(
            itemType: 'platform-collections',
            pageTitle: 'Platform Collections | Augment Humankind',
            pageDescription: 'Browse migrated platform collections from Augment Humankind.',
            canonicalPath: '/portfolio/platform-collections',
            eyebrow: 'Portfolio',
            heading: 'Platform Collections',
            intro: 'Migrated platform-native collections, each with its own public detail page and immersive mode.',
            fetchItems: static fn (int $offset, int $limit): array => self::decoratePlatformCollections(
                PlatformCollection::paginate($offset, $limit)
            ),
            fetchTotal: static fn (): int => PlatformCollection::countVisible()
        );
    }

    public static function piecesIndex(): void
    {
        self::renderArchive(
            itemType: 'pieces',
            pageTitle: 'Portfolio Art Pieces | Augment Humankind',
            pageDescription: 'Browse generative art pieces from Augment Humankind.',
            canonicalPath: '/portfolio/pieces',
            eyebrow: 'Portfolio',
            heading: 'Art Pieces',
            intro: 'Generative art pieces and creative experiments from the migrated platform archive.',
            fetchItems: static fn (int $offset, int $limit): array => self::decoratePieces(
                PlatformArtPiece::paginate($offset, $limit)
            ),
            fetchTotal: static fn (): int => PlatformArtPiece::countActive()
        );
    }

    public static function categories(): void
    {
        self::renderArchive(
            itemType: 'art-media',
            pageTitle: 'Art Media | Augment Humankind',
            pageDescription: 'Browse art media terms used to organize pieces within the Augment Humankind portfolio.',
            canonicalPath: '/portfolio/art-media',
            eyebrow: 'Portfolio',
            heading: 'Art Media',
            intro: 'Piece-oriented taxonomy terms that group related art pieces across the portfolio.',
            fetchItems: static fn (int $offset, int $limit): array => Category::paginate($offset, $limit),
            fetchTotal: static fn (): int => Category::countVisible()
        );
    }

    public static function category(string $slug): void
    {
        $category = Category::findBySlug($slug);
        if (!$category) {
            require dirname(__DIR__) . '/views/404.php';
            return;
        }

        $pieces = self::decoratePieces(Category::pieces((int) $category['id']));
        require dirname(__DIR__) . '/views/portfolio/category.php';
    }

    public static function redirectCollectionsArchive(): void
    {
        self::permanentRedirect('/portfolio/exhibit-collections');
    }

    public static function redirectCategoriesArchive(): void
    {
        self::permanentRedirect('/portfolio/art-media');
    }

    public static function redirectCategory(string $slug): void
    {
        self::permanentRedirect('/portfolio/art-media/' . rawurlencode($slug));
    }

    public static function collection(string $slug): void
    {
        $collection = Collection::findBySlug($slug);
        if (!$collection) {
            require dirname(__DIR__) . '/views/404.php';
            return;
        }

        $exhibits = Collection::exhibits((int) $collection['id']);
        require dirname(__DIR__) . '/views/portfolio/collection.php';
    }

    public static function exhibit(string $slug): void
    {
        $exhibit = Exhibit::findBySlug($slug);
        if (!$exhibit) {
            require dirname(__DIR__) . '/views/404.php';
            return;
        }

        $mediaItems = $exhibit['media_items'] ?? Exhibit::resolvedMediaItems($exhibit);
        require dirname(__DIR__) . '/views/portfolio/exhibit.php';
    }

    private static function renderArchive(
        string $itemType,
        string $pageTitle,
        string $pageDescription,
        string $canonicalPath,
        string $eyebrow,
        string $heading,
        string $intro,
        callable $fetchItems,
        callable $fetchTotal
    ): void {
        $offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;
        $limit = isset($_GET['limit']) ? max(1, min(24, (int) $_GET['limit'])) : self::ARCHIVE_PAGE_SIZE;
        $items = $fetchItems($offset, $limit);
        $total = $fetchTotal();
        $nextOffset = $offset + count($items);
        $hasMore = $nextOffset < $total;

        if (self::isPartialRequest()) {
            require dirname(__DIR__) . '/views/portfolio/archive-batch.php';
            return;
        }

        $bodyClass = bodyClass('portfolio-archive');
        $canonicalUrl = seo_absolute_url($canonicalPath);
        require dirname(__DIR__) . '/views/portfolio/archive.php';
    }

    private static function isPartialRequest(): bool
    {
        return ($_GET['partial'] ?? '') === '1';
    }

    private static function permanentRedirect(string $path): never
    {
        header('Location: ' . $path, true, 301);
        exit;
    }

    private static function decorateCollections(array $collections): array
    {
        foreach ($collections as &$collection) {
            $collection['preview_image'] = Collection::previewImage($collection);
            $collection['exhibit_count'] = count(Collection::exhibits((int) $collection['id']));
            $collection['summary'] = seo_excerpt($collection['description'] ?? null, 140);
        }
        unset($collection);

        return $collections;
    }

    private static function decorateExhibits(array $exhibits): array
    {
        foreach ($exhibits as &$exhibit) {
            $exhibit['preview_image'] = Exhibit::previewImage($exhibit);
            $exhibit['summary'] = seo_excerpt($exhibit['description'] ?? null, 140);
        }
        unset($exhibit);

        return $exhibits;
    }

    private static function decoratePlatformCollections(array $collections): array
    {
        foreach ($collections as &$collection) {
            $collection['thumbnail_url'] = PlatformCollection::firstThumbnail((int) $collection['id']);
            $collection['summary'] = seo_excerpt($collection['description'] ?? null, 140);
        }
        unset($collection);

        return $collections;
    }

    private static function decoratePieces(array $pieces): array
    {
        foreach ($pieces as &$piece) {
            $piece['summary'] = seo_excerpt($piece['description'] ?? null, 140);
        }
        unset($piece);

        return $pieces;
    }
}
