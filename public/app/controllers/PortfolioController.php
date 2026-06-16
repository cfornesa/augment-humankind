<?php

declare(strict_types=1);

class PortfolioController
{
    private const GALLERY_PREVIEW_LIMIT = 3;
    private const ARCHIVE_PAGE_SIZE = 3;

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
        $comments = (int)($collection['comments_enabled'] ?? 0)
            ? Comment::commentsFor('collection', (int) $collection['id'])
            : [];
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
        $comments = (int)($exhibit['comments_enabled'] ?? 0)
            ? Comment::commentsFor('exhibit', (int) $exhibit['id'])
            : [];
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

    public static function exhibitCommentsJson(string $slug): void
    {
        header('Content-Type: application/json');
        $exhibit = Exhibit::findBySlug($slug);
        if (!$exhibit) {
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
            exit;
        }
        $comments = Comment::commentsFor('exhibit', (int) $exhibit['id']);
        echo json_encode(Comment::toApiPayloadList($comments));
        exit;
    }

    public static function exhibitCommentSubmit(string $slug): void
    {
        header('Content-Type: application/json');
        $exhibit = Exhibit::findBySlug($slug);
        if (!$exhibit || !(int)($exhibit['comments_enabled'] ?? 0)) {
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
            exit;
        }
        self::processCommentSubmit('exhibit', (int) $exhibit['id']);
    }

    public static function collectionCommentsJson(string $slug): void
    {
        header('Content-Type: application/json');
        $collection = Collection::findBySlug($slug);
        if (!$collection) {
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
            exit;
        }
        $comments = Comment::commentsFor('collection', (int) $collection['id']);
        echo json_encode(Comment::toApiPayloadList($comments));
        exit;
    }

    public static function collectionCommentSubmit(string $slug): void
    {
        header('Content-Type: application/json');
        $collection = Collection::findBySlug($slug);
        if (!$collection || !(int)($collection['comments_enabled'] ?? 0)) {
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
            exit;
        }
        self::processCommentSubmit('collection', (int) $collection['id']);
    }

    private static function processCommentSubmit(string $itemType, int $itemId): never
    {
        if (!user_logged_in()) {
            http_response_code(401);
            echo json_encode(['error' => 'Sign in to comment.']);
            exit;
        }

        $hp = trim((string) ($_POST['hp_field'] ?? ''));
        if ($hp !== '') {
            echo json_encode(['ok' => true]);
            exit;
        }

        $content = trim((string) ($_POST['content'] ?? ''));
        if ($content === '' || mb_strlen($content) > 500) {
            http_response_code(422);
            echo json_encode(['error' => 'Comment must be 1–500 characters.']);
            exit;
        }

        $actor = current_comment_actor();
        if (!$actor) {
            http_response_code(401);
            echo json_encode(['error' => 'Sign in to comment.']);
            exit;
        }

        try {
            Comment::insertComment(
                $itemType,
                $itemId,
                (string) $actor['name'],
                $content,
                null,
                (string) $actor['id'],
                $actor['user_id'] !== null ? (string) $actor['user_id'] : null,
                (string) ($actor['image'] ?? '')
            );
            $commentId = (int) db()->lastInsertId();
            $created = Comment::find($commentId);
        } catch (Throwable) {
            http_response_code(500);
            echo json_encode(['error' => 'Could not save comment.']);
            exit;
        }

        echo json_encode([
            'ok' => true,
            'comment' => $created ? Comment::toApiPayload($created) : null,
        ]);
        exit;
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
