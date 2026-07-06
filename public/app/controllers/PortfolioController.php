<?php

declare(strict_types=1);

class PortfolioController
{
    private const GALLERY_PREVIEW_LIMIT = 3;
    private const ARCHIVE_PAGE_SIZE = 3;

    public static function gallery(): void
    {
        $collections = self::decorateCollections(
            Collection::latestActive(self::GALLERY_PREVIEW_LIMIT)
        );
        $collectionTotal = Collection::countWithAtLeastOneExhibit();
        $exhibits = self::decorateExhibits(
            Exhibit::latestActive(self::GALLERY_PREVIEW_LIMIT)
        );
        $exhibitTotal = Exhibit::countVisible();
        $platformCollections = self::decoratePlatformCollections(
            PlatformCollection::latestActive(self::GALLERY_PREVIEW_LIMIT)
        );
        $platformCollectionTotal = PlatformCollection::countVisible();
        $pieces = self::decoratePieces(
            PlatformArtPiece::latestActive(self::GALLERY_PREVIEW_LIMIT)
        );
        $pieceTotal = PlatformArtPiece::countActive();
        require dirname(__DIR__) . '/views/portfolio/gallery.php';
    }

    public static function collectionsIndex(): void
    {
        $q    = trim((string) ($_GET['q'] ?? ''));
        // Defaults to the same curated sort_order the admin exhibit
        // collections list uses, so this page's order matches the admin by
        // default. Only diverges when the visitor explicitly picks a sort.
        $sort = (string) ($_GET['sort'] ?? 'curated');
        if (!in_array($sort, ['curated', 'newest', 'oldest', 'az', 'za', 'relevance'], true)) {
            $sort = 'curated';
        }
        if ($sort === 'relevance' && $q === '') {
            $sort = 'curated';
        }
        [$modelSort, $dir] = match ($sort) {
            'newest'    => ['newest',     'desc'],
            'oldest'    => ['created',    'asc'],
            'az'        => ['az',         'asc'],
            'za'        => ['za',         'desc'],
            'relevance' => ['relevance',  'desc'],
            default     => ['sort_order', 'asc'],
        };
        $filterQs = http_build_query(array_filter(['q' => $q, 'sort' => $sort !== 'curated' ? $sort : '']));
        $fetchUrl = '/portfolio/exhibit-collections' . ($filterQs !== '' ? '?' . $filterQs : '');

        self::renderArchive(
            itemType: 'collections',
            pageTitle: 'Exhibit Collections | ' . app_site_name(),
            pageDescription: public_copy_value('portfolio_copy.archives.exhibit_collections.meta_description'),
            canonicalPath: '/portfolio/exhibit-collections',
            eyebrow: public_copy_value('portfolio_copy.archives.exhibit_collections.eyebrow'),
            heading: public_copy_value('portfolio_copy.archives.exhibit_collections.heading'),
            intro: public_copy_value('portfolio_copy.archives.exhibit_collections.intro'),
            fetchItems: static fn (int $offset, int $limit): array => self::decorateCollections(
                Collection::searchFiltered($q, $modelSort, $dir, $offset, $limit)
            ),
            fetchUrl: $fetchUrl,
            showFilterBar: true,
            filterQ: $q,
            filterSort: $sort,
        );
    }

    public static function exhibitsIndex(): void
    {
        $q    = trim((string) ($_GET['q'] ?? ''));
        // Defaults to the same curated sort_order the admin exhibits list
        // uses, so this page's order matches the admin by default. Only
        // diverges when the visitor explicitly picks a sort.
        $sort = (string) ($_GET['sort'] ?? 'curated');
        if (!in_array($sort, ['curated', 'newest', 'oldest', 'az', 'za', 'relevance'], true)) {
            $sort = 'curated';
        }
        if ($sort === 'relevance' && $q === '') {
            $sort = 'curated';
        }
        [$modelSort, $dir] = match ($sort) {
            'newest'    => ['newest',     'desc'],
            'oldest'    => ['created',    'asc'],
            'az'        => ['az',         'asc'],
            'za'        => ['za',         'desc'],
            'relevance' => ['relevance',  'desc'],
            default     => ['sort_order', 'asc'],
        };
        $filterQs = http_build_query(array_filter(['q' => $q, 'sort' => $sort !== 'curated' ? $sort : '']));
        $fetchUrl = '/portfolio/exhibits' . ($filterQs !== '' ? '?' . $filterQs : '');

        self::renderArchive(
            itemType: 'exhibits',
            pageTitle: 'Portfolio Exhibits | ' . app_site_name(),
            pageDescription: public_copy_value('portfolio_copy.archives.exhibits.meta_description'),
            canonicalPath: '/portfolio/exhibits',
            eyebrow: public_copy_value('portfolio_copy.archives.exhibits.eyebrow'),
            heading: public_copy_value('portfolio_copy.archives.exhibits.heading'),
            intro: public_copy_value('portfolio_copy.archives.exhibits.intro'),
            fetchItems: static fn (int $offset, int $limit): array => self::decorateExhibits(
                Exhibit::searchFiltered($q, $modelSort, $dir, $offset, $limit)
            ),
            fetchUrl: $fetchUrl,
            showFilterBar: true,
            filterQ: $q,
            filterSort: $sort,
        );
    }

    public static function platformCollectionsIndex(): void
    {
        $q    = trim((string) ($_GET['q'] ?? ''));
        // Defaults to the same curated sort_order the admin platform
        // collections list uses, so this page's order matches the admin by
        // default. Only diverges when the visitor explicitly picks a sort.
        $sort = (string) ($_GET['sort'] ?? 'curated');
        if (!in_array($sort, ['curated', 'newest', 'oldest', 'az', 'za', 'relevance'], true)) {
            $sort = 'curated';
        }
        if ($sort === 'relevance' && $q === '') {
            $sort = 'curated';
        }
        [$modelSort, $dir] = match ($sort) {
            'newest'    => ['newest',     'desc'],
            'oldest'    => ['newest',     'asc'],
            'az'        => ['name',       'asc'],
            'za'        => ['name',       'desc'],
            'relevance' => ['relevance',  'desc'],
            default     => ['sort_order', 'asc'],
        };
        $filterQs = http_build_query(array_filter(['q' => $q, 'sort' => $sort !== 'curated' ? $sort : '']));
        $fetchUrl = '/portfolio/platform-collections' . ($filterQs !== '' ? '?' . $filterQs : '');

        self::renderArchive(
            itemType: 'platform-collections',
            pageTitle: 'Platform Collections | ' . app_site_name(),
            pageDescription: public_copy_value('portfolio_copy.archives.platform_collections.meta_description'),
            canonicalPath: '/portfolio/platform-collections',
            eyebrow: public_copy_value('portfolio_copy.archives.platform_collections.eyebrow'),
            heading: public_copy_value('portfolio_copy.archives.platform_collections.heading'),
            intro: public_copy_value('portfolio_copy.archives.platform_collections.intro'),
            fetchItems: static fn (int $offset, int $limit): array => self::decoratePlatformCollections(
                PlatformCollection::searchFiltered($q, $modelSort, $dir, $offset, $limit)
            ),
            fetchUrl: $fetchUrl,
            showFilterBar: true,
            filterQ: $q,
            filterSort: $sort,
        );
    }

    public static function piecesIndex(): void
    {
        $q      = trim((string) ($_GET['q'] ?? ''));
        $engine = trim((string) ($_GET['engine'] ?? ''));
        // Defaults to the same curated sort_order the admin pieces list
        // uses, so this page's order matches the admin by default. Only
        // diverges when the visitor explicitly picks a sort.
        $sort   = (string) ($_GET['sort'] ?? 'curated');
        if (!in_array($sort, ['curated', 'newest', 'oldest', 'az', 'za', 'relevance'], true)) {
            $sort = 'curated';
        }
        if ($sort === 'relevance' && $q === '') {
            $sort = 'curated';
        }
        if (!in_array($engine, array_merge(art_piece_supported_engines(), ['c2_interactive']), true)) {
            $engine = '';
        }
        [$modelSort, $dir] = match ($sort) {
            'newest'    => ['newest',     'desc'],
            'oldest'    => ['newest',     'asc'],
            'az'        => ['title',      'asc'],
            'za'        => ['title',      'desc'],
            'relevance' => ['relevance',  'desc'],
            default     => ['sort_order', 'asc'],
        };
        $filterQs = http_build_query(array_filter(['q' => $q, 'engine' => $engine, 'sort' => $sort !== 'curated' ? $sort : '']));
        $fetchUrl = '/portfolio/pieces' . ($filterQs !== '' ? '?' . $filterQs : '');

        self::renderArchive(
            itemType: 'pieces',
            pageTitle: 'Portfolio Art Pieces | ' . app_site_name(),
            pageDescription: public_copy_value('portfolio_copy.archives.pieces.meta_description'),
            canonicalPath: '/portfolio/pieces',
            eyebrow: public_copy_value('portfolio_copy.archives.pieces.eyebrow'),
            heading: public_copy_value('portfolio_copy.archives.pieces.heading'),
            intro: public_copy_value('portfolio_copy.archives.pieces.intro'),
            fetchItems: static fn (int $offset, int $limit): array => self::decoratePieces(
                PlatformArtPiece::searchFilteredByGenerationMode($q ?: null, $engine ?: null, $modelSort, $dir, $offset, $limit)
            ),
            fetchUrl: $fetchUrl,
            showFilterBar: true,
            showEngineFilter: true,
            filterQ: $q,
            filterSort: $sort,
            filterEngine: $engine,
        );
    }

    public static function categories(): void
    {
        self::renderArchive(
            itemType: 'art-media',
            pageTitle: 'Art Media | ' . app_site_name(),
            pageDescription: public_copy_value('portfolio_copy.archives.art_media.meta_description'),
            canonicalPath: '/portfolio/art-media',
            eyebrow: public_copy_value('portfolio_copy.archives.art_media.eyebrow'),
            heading: public_copy_value('portfolio_copy.archives.art_media.heading'),
            intro: public_copy_value('portfolio_copy.archives.art_media.intro'),
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
        ?callable $fetchTotal = null,
        ?string $fetchUrl = null,
        bool $showFilterBar = false,
        string $filterQ = '',
        string $filterSort = 'newest',
        bool $showEngineFilter = false,
        string $filterEngine = '',
    ): void {
        $offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;
        $limit  = isset($_GET['limit'])  ? max(1, min(24, (int) $_GET['limit'])) : self::ARCHIVE_PAGE_SIZE;

        if ($fetchTotal !== null) {
            $items      = $fetchItems($offset, $limit);
            $total      = $fetchTotal();
            $nextOffset = $offset + count($items);
            $hasMore    = $nextOffset < $total;
        } else {
            $batch      = $fetchItems($offset, $limit + 1);
            $hasMore    = count($batch) > $limit;
            $items      = $hasMore ? array_slice($batch, 0, $limit) : $batch;
            $nextOffset = $offset + $limit;
        }

        $fetchUrl = $fetchUrl ?? $canonicalPath;

        // expose filter vars to view
        $q              = $filterQ;
        $sort           = $filterSort;
        $engine         = $filterEngine;
        $showEngineFilter = $showEngineFilter;

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
