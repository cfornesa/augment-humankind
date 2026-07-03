<?php

declare(strict_types=1);

class PortfolioAdminController
{
    public static function exhibitsIndex(): void
    {
        admin_check();

        $q    = trim((string) ($_GET['q'] ?? ''));
        $sort = (string) ($_GET['sort'] ?? 'sort_order');
        $dir  = strtolower((string) ($_GET['dir'] ?? 'asc'));

        $allowedSorts = ['sort_order', 'title', 'created'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'sort_order';
        }
        if (!in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'asc';
        }

        if ($q !== '' || $sort !== 'sort_order') {
            $exhibits = Exhibit::searchFiltered($q, $sort === 'sort_order' ? 'created' : $sort, $dir, 0, 500, true);
        } else {
            $exhibits = Exhibit::all();
        }

        require dirname(__DIR__, 2) . '/views/admin/exhibits/index.php';
    }

    public static function exhibitCreate(): void
    {
        admin_check();
        $allCollections = Collection::all();
        $assignedCollectionIds = [];
        $exhibit = ['media_items' => []];
        $error = null;
        require dirname(__DIR__, 2) . '/views/admin/exhibits/form.php';
    }

    public static function exhibitStore(): void
    {
        admin_check();

        try {
            $data = self::resolveExhibitData(null);
            $exhibitId = Exhibit::create($data);
            reorder_shift_position($exhibitId, $data['sort_order'], 'exhibits');
            ExhibitMediaItem::syncForExhibit($exhibitId, $data['media_items']);
            Collection::syncForExhibit($exhibitId, $data['collection_ids']);
            header('Location: /admin/exhibits');
        } catch (Throwable $e) {
            $allCollections = Collection::all();
            $exhibit = self::draftExhibitFromPost(null);
            $assignedCollectionIds = $exhibit['collection_ids'];
            $error = $e->getMessage();
            require dirname(__DIR__, 2) . '/views/admin/exhibits/form.php';
        }
        exit;
    }

    public static function exhibitEdit(string $id): void
    {
        admin_check();
        $exhibit = Exhibit::find((int) $id);
        if (!$exhibit) {
            header('Location: /admin/exhibits');
            exit;
        }
        $allCollections = Collection::all();
        $assignedCollectionIds = Collection::collectionIdsForExhibit((int) $id);
        $error = null;
        require dirname(__DIR__, 2) . '/views/admin/exhibits/form.php';
    }

    public static function exhibitUpdate(string $id): void
    {
        admin_check();
        $existing = Exhibit::find((int) $id);
        if (!$existing) {
            header('Location: /admin/exhibits');
            exit;
        }

        try {
            $data = self::resolveExhibitData((int) $id);
            $sortOrder = $data['sort_order'];
            $data['sort_order'] = (int) ($existing['sort_order'] ?? 0); // Keep it temporarily to prevent duplicate order during UPDATE
            Exhibit::update((int) $id, $data);
            reorder_shift_position((int) $id, $sortOrder, 'exhibits');
            ExhibitMediaItem::syncForExhibit((int) $id, $data['media_items']);
            Collection::syncForExhibit((int) $id, $data['collection_ids']);
            header('Location: /admin/exhibits');
        } catch (Throwable $e) {
            $exhibit = self::draftExhibitFromPost((int) $id);
            $allCollections = Collection::all();
            $assignedCollectionIds = $exhibit['collection_ids'];
            $error = $e->getMessage();
            require dirname(__DIR__, 2) . '/views/admin/exhibits/form.php';
        }
        exit;
    }

    public static function exhibitDelete(string $id): void
    {
        admin_check();
        Exhibit::softDelete((int) $id);
        header('Location: /admin/exhibits');
        exit;
    }

    public static function exhibitReorder(): void
    {
        admin_check();
        $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
        Exhibit::reorder($ids);
        header('Content-Type: application/json');
        echo '{"ok":true}';
        exit;
    }

    public static function categoriesIndex(): void
    {
        admin_check();
        $categories = Category::all();
        $taxonomyLabel = 'Art Medium';
        $taxonomyPlural = 'Art Media';
        $taxonomyIndexPath = '/admin/art-media';
        $taxonomyCreatePath = '/admin/art-media/create';
        $taxonomyReorderPath = '/admin/art-media/reorder';
        $taxonomyDeleteMessage = 'Move this art medium to the recycle bin? Pieces will become unassigned.';
        $taxonomyCanCreate = feature_art_media_creation_enabled();
        require dirname(__DIR__, 2) . '/views/admin/categories/index.php';
    }

    public static function categoryCreate(): void
    {
        admin_check();
        if (!feature_art_media_creation_enabled()) {
            feature_blocked_response('pieces', 'GET', '/admin/art-media/create');
            exit;
        }
        $category = null;
        $error = null;
        $taxonomyLabel = 'Art Medium';
        $taxonomyPlural = 'Art Media';
        $taxonomyIndexPath = '/admin/art-media';
        $taxonomyCreatePath = '/admin/art-media/create';
        $taxonomyEditBasePath = '/admin/art-media';
        $showTaxonomyThumbnail = true;
        require dirname(__DIR__, 2) . '/views/admin/categories/form.php';
    }

    public static function categoryStore(): void
    {
        admin_check();
        if (!feature_art_media_creation_enabled()) {
            feature_blocked_response('pieces', 'POST', '/admin/art-media/create');
            exit;
        }
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $category = null;
            $error = 'Name is required.';
            $taxonomyLabel = 'Art Medium';
            $taxonomyPlural = 'Art Media';
            $taxonomyIndexPath = '/admin/art-media';
            $taxonomyCreatePath = '/admin/art-media/create';
            $taxonomyEditBasePath = '/admin/art-media';
            $showTaxonomyThumbnail = true;
            require dirname(__DIR__, 2) . '/views/admin/categories/form.php';
            return;
        }

        try {
            [$thumbType, $thumbValue] = self::resolveThumbnail(null);
            Category::create(
                $name,
                self::resolvedCategorySlug($name, null),
                0,
                $thumbType,
                $thumbValue,
                trim($_POST['description'] ?? '') ?: null
            );
            header('Location: /admin/art-media');
        } catch (Throwable $e) {
            $category = null;
            $error = $e->getMessage();
            $taxonomyLabel = 'Art Medium';
            $taxonomyPlural = 'Art Media';
            $taxonomyIndexPath = '/admin/art-media';
            $taxonomyCreatePath = '/admin/art-media/create';
            $taxonomyEditBasePath = '/admin/art-media';
            $showTaxonomyThumbnail = true;
            require dirname(__DIR__, 2) . '/views/admin/categories/form.php';
            return;
        }
        exit;
    }

    public static function categoryCreateInline(): void
    {
        admin_check();
        header('Content-Type: application/json');
        if (!feature_art_media_creation_enabled()) {
            http_response_code(403);
            echo json_encode(['error' => 'Art media creation is disabled while both Pieces and Exhibits are off.']);
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Name is required.']);
            exit;
        }

        try {
            $slug = unique_category_slug($name);
            $id = Category::create($name, $slug);
            echo json_encode(['success' => true, 'id' => $id, 'name' => $name, 'slug' => $slug]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    public static function categoryEdit(string $id): void
    {
        admin_check();
        $category = Category::find((int) $id);
        if (!$category) {
            header('Location: /admin/art-media');
            exit;
        }
        $error = null;
        $taxonomyLabel = 'Art Medium';
        $taxonomyPlural = 'Art Media';
        $taxonomyIndexPath = '/admin/art-media';
        $taxonomyCreatePath = '/admin/art-media/create';
        $taxonomyEditBasePath = '/admin/art-media';
        $showTaxonomyThumbnail = true;
        require dirname(__DIR__, 2) . '/views/admin/categories/form.php';
    }

    public static function categoryUpdate(string $id): void
    {
        admin_check();
        $existing = Category::find((int) $id);
        if (!$existing) {
            header('Location: /admin/art-media');
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $category = $existing;
            $error = 'Name is required.';
            $taxonomyLabel = 'Art Medium';
            $taxonomyPlural = 'Art Media';
            $taxonomyIndexPath = '/admin/art-media';
            $taxonomyCreatePath = '/admin/art-media/create';
            $taxonomyEditBasePath = '/admin/art-media';
            $showTaxonomyThumbnail = true;
            require dirname(__DIR__, 2) . '/views/admin/categories/form.php';
            return;
        }

        try {
            [$thumbType, $thumbValue] = self::resolveThumbnail($existing);
            Category::update(
                (int) $id,
                $name,
                self::resolvedCategorySlug($name, (int) $id),
                (int) ($existing['sort_order'] ?? 0),
                $thumbType,
                $thumbValue,
                trim($_POST['description'] ?? '') ?: null
            );
            header('Location: /admin/art-media');
        } catch (Throwable $e) {
            $category = $existing;
            $error = $e->getMessage();
            $taxonomyLabel = 'Art Medium';
            $taxonomyPlural = 'Art Media';
            $taxonomyIndexPath = '/admin/art-media';
            $taxonomyCreatePath = '/admin/art-media/create';
            $taxonomyEditBasePath = '/admin/art-media';
            $showTaxonomyThumbnail = true;
            require dirname(__DIR__, 2) . '/views/admin/categories/form.php';
            return;
        }
        exit;
    }

    public static function categoryDelete(string $id): void
    {
        admin_check();
        Category::softDelete((int) $id);
        header('Location: /admin/art-media');
        exit;
    }

    public static function categoryReorder(): void
    {
        admin_check();
        $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
        Category::reorder($ids);
        header('Content-Type: application/json');
        echo '{"ok":true}';
        exit;
    }

    public static function collectionsIndex(): void
    {
        admin_check();

        $q    = trim((string) ($_GET['q'] ?? ''));
        $sort = (string) ($_GET['sort'] ?? 'sort_order');
        $dir  = strtolower((string) ($_GET['dir'] ?? 'asc'));

        $allowedSorts = ['sort_order', 'name', 'created'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'sort_order';
        }
        if (!in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'asc';
        }

        if ($q !== '' || $sort !== 'sort_order') {
            $collections = Collection::searchFiltered($q, $sort === 'sort_order' ? 'created' : $sort, $dir, 0, 500, true);
        } else {
            $collections = Collection::allWithExhibitCount();
        }

        $collectionLabel = 'Exhibit Collection';
        $collectionPlural = 'Exhibit Collections';
        $collectionIndexPath = '/admin/exhibit-collections';
        $collectionCreatePath = '/admin/exhibit-collections/create';
        $collectionReorderPath = '/admin/exhibit-collections/reorder';
        $collectionDeleteMessage = 'Move this exhibit collection to the recycle bin?';
        require dirname(__DIR__, 2) . '/views/admin/collections/index.php';
    }

    public static function collectionCreate(): void
    {
        admin_check();
        $collection = null;
        $allExhibits = Exhibit::all();
        $assigned = [];
        $error = null;
        $collectionLabel = 'Exhibit Collection';
        $collectionPlural = 'Exhibit Collections';
        $collectionIndexPath = '/admin/exhibit-collections';
        $collectionCreatePath = '/admin/exhibit-collections/create';
        $collectionEditBasePath = '/admin/exhibit-collections';
        require dirname(__DIR__, 2) . '/views/admin/collections/form.php';
    }

    public static function collectionStore(): void
    {
        admin_check();
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $collection = null;
            $allExhibits = Exhibit::all();
            $assigned = [];
            $error = 'Name is required.';
            $collectionLabel = 'Exhibit Collection';
            $collectionPlural = 'Exhibit Collections';
            $collectionIndexPath = '/admin/exhibit-collections';
            $collectionCreatePath = '/admin/exhibit-collections/create';
            $collectionEditBasePath = '/admin/exhibit-collections';
            require dirname(__DIR__, 2) . '/views/admin/collections/form.php';
            return;
        }

        try {
            [$thumbType, $thumbValue] = self::resolveThumbnail(null);
            $sortOrder = isset($_POST['sort_order']) ? max(0, (int) $_POST['sort_order'] - 1) : 0;
            $id = Collection::create([
                'name' => $name,
                'slug' => self::resolvedCollectionSlug($name, null),
                'description' => trim($_POST['description'] ?? ''),
                'thumbnail_type' => $thumbType,
                'thumbnail_value' => $thumbValue,
                'sort_order' => $sortOrder,
                'comments_enabled' => isset($_POST['comments_enabled']) ? 1 : 0,
                'status' => self::resolvedStatus($_POST['status'] ?? null),
            ]);
            reorder_shift_position($id, $sortOrder, 'collections');
            Collection::syncExhibits($id, array_map('intval', $_POST['exhibit_ids'] ?? []));
            header('Location: /admin/exhibit-collections');
        } catch (Throwable $e) {
            $collection = null;
            $allExhibits = Exhibit::all();
            $assigned = [];
            $error = $e->getMessage();
            $collectionLabel = 'Exhibit Collection';
            $collectionPlural = 'Exhibit Collections';
            $collectionIndexPath = '/admin/exhibit-collections';
            $collectionCreatePath = '/admin/exhibit-collections/create';
            $collectionEditBasePath = '/admin/exhibit-collections';
            require dirname(__DIR__, 2) . '/views/admin/collections/form.php';
            return;
        }
        exit;
    }

    public static function collectionCreateInline(): void
    {
        admin_check();
        header('Content-Type: application/json');

        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Name is required.']);
            exit;
        }

        try {
            $slug = unique_collection_slug($name);
            $id = Collection::create([
                'name' => $name,
                'slug' => $slug,
                'description' => '',
                'thumbnail_type' => null,
                'thumbnail_value' => null,
                'sort_order' => 0,
            ]);
            echo json_encode(['success' => true, 'id' => $id, 'name' => $name, 'slug' => $slug]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    public static function collectionEdit(string $id): void
    {
        admin_check();
        $collection = Collection::find((int) $id);
        if (!$collection) {
            header('Location: /admin/exhibit-collections');
            exit;
        }
        $allExhibits = Exhibit::all();
        $assigned = Collection::exhibitIds((int) $id);
        $error = null;
        $collectionLabel = 'Exhibit Collection';
        $collectionPlural = 'Exhibit Collections';
        $collectionIndexPath = '/admin/exhibit-collections';
        $collectionCreatePath = '/admin/exhibit-collections/create';
        $collectionEditBasePath = '/admin/exhibit-collections';
        require dirname(__DIR__, 2) . '/views/admin/collections/form.php';
    }

    public static function collectionUpdate(string $id): void
    {
        admin_check();
        $existing = Collection::find((int) $id);
        if (!$existing) {
            header('Location: /admin/exhibit-collections');
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $collection = $existing;
            $allExhibits = Exhibit::all();
            $assigned = Collection::exhibitIds((int) $id);
            $error = 'Name is required.';
            $collectionLabel = 'Exhibit Collection';
            $collectionPlural = 'Exhibit Collections';
            $collectionIndexPath = '/admin/exhibit-collections';
            $collectionCreatePath = '/admin/exhibit-collections/create';
            $collectionEditBasePath = '/admin/exhibit-collections';
            require dirname(__DIR__, 2) . '/views/admin/collections/form.php';
            return;
        }

        try {
            $sortOrder = isset($_POST['sort_order']) ? max(0, (int) $_POST['sort_order'] - 1) : ($existing['sort_order'] ?? 0);
            [$thumbType, $thumbValue] = self::resolveThumbnail($existing);
            Collection::update((int) $id, [
                'name' => $name,
                'slug' => self::resolvedCollectionSlug($name, (int) $id),
                'description' => trim($_POST['description'] ?? ''),
                'thumbnail_type' => $thumbType,
                'thumbnail_value' => $thumbValue,
                'sort_order' => (int) ($existing['sort_order'] ?? 0),
                'comments_enabled' => isset($_POST['comments_enabled']) ? 1 : 0,
                'status' => self::resolvedStatus($_POST['status'] ?? null),
            ]);
            reorder_shift_position((int) $id, $sortOrder, 'collections');
            Collection::syncExhibits((int) $id, array_map('intval', $_POST['exhibit_ids'] ?? []));
            header('Location: /admin/exhibit-collections');
        } catch (Throwable $e) {
            $collection = $existing;
            $allExhibits = Exhibit::all();
            $assigned = Collection::exhibitIds((int) $id);
            $error = $e->getMessage();
            $collectionLabel = 'Exhibit Collection';
            $collectionPlural = 'Exhibit Collections';
            $collectionIndexPath = '/admin/exhibit-collections';
            $collectionCreatePath = '/admin/exhibit-collections/create';
            $collectionEditBasePath = '/admin/exhibit-collections';
            require dirname(__DIR__, 2) . '/views/admin/collections/form.php';
            return;
        }
        exit;
    }

    public static function collectionDelete(string $id): void
    {
        admin_check();
        Collection::softDelete((int) $id);
        header('Location: /admin/exhibit-collections');
        exit;
    }

    public static function collectionReorder(): void
    {
        admin_check();
        $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
        Collection::reorder($ids);
        header('Content-Type: application/json');
        echo '{"ok":true}';
        exit;
    }

    private static function resolveExhibitData(?int $existingId): array
    {
        $existing = $existingId ? Exhibit::find($existingId) : null;
        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            throw new InvalidArgumentException('Title is required.');
        }

        $mediaItems = self::resolveExhibitMediaItems();
        if ($mediaItems === [] && $existing) {
            $mediaItems = Exhibit::resolvedMediaItems($existing);
        }
        if ($mediaItems === []) {
            throw new InvalidArgumentException('Add at least one exhibit slide.');
        }

        [$thumbType, $thumbValue] = self::resolveThumbnail($existing);

        return [
            'title' => $title,
            'slug' => self::resolvedExhibitSlug($title, $existingId),
            'year' => trim($_POST['year'] ?? ''),
            'artist_name' => trim($_POST['artist_name'] ?? ''),
            'medium' => trim($_POST['medium'] ?? ''),
            'dimensions' => trim($_POST['dimensions'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'placard_notes' => trim($_POST['placard_notes'] ?? ''),
            'thumbnail_type' => $thumbType,
            'thumbnail_value' => $thumbValue,
            'sort_order' => max(0, (int) ($_POST['sort_order'] ?? 1) - 1),
            'status' => self::resolvedStatus($_POST['status'] ?? null),
            'comments_enabled' => isset($_POST['comments_enabled']) ? 1 : 0,
            'media_items' => $mediaItems,
            'collection_ids' => array_map('intval', $_POST['collection_ids'] ?? []),
        ];
    }

    private static function resolvedStatus(?string $status): string
    {
        $status = trim((string) $status);
        return in_array($status, ['active', 'draft', 'archived'], true) ? $status : 'active';
    }

    private static function resolveExhibitMediaItems(): array
    {
        $kinds = $_POST['media_kind'] ?? [];
        $mediaIds = $_POST['media_file_id'] ?? [];
        $posterIds = $_POST['poster_media_file_id'] ?? [];
        $alts = $_POST['alt_text'] ?? [];
        $titles = $_POST['slide_title'] ?? [];
        $captions = $_POST['caption'] ?? [];
        $iframeHtml = $_POST['iframe_html'] ?? [];
        $contentHtml = $_POST['content_html'] ?? [];
        $contentWrapperClasses = $_POST['content_wrapper_class'] ?? [];
        $items = [];

        foreach ($kinds as $index => $kindRaw) {
            $kind = trim((string) $kindRaw);
            if ($kind === '') {
                continue;
            }
            if (!in_array($kind, ['image', 'video', 'iframe', 'content'], true)) {
                throw new InvalidArgumentException('Invalid exhibit slide type.');
            }

            $mediaFileId = (int) ($mediaIds[$index] ?? 0);
            $posterMediaId = (int) ($posterIds[$index] ?? 0);
            $alt = trim((string) ($alts[$index] ?? ''));
            $slideTitle = trim((string) ($titles[$index] ?? ''));
            $caption = trim((string) ($captions[$index] ?? ''));
            $iframe = trim((string) ($iframeHtml[$index] ?? ''));

            if ($kind === 'content') {
                $html = trim((string) ($contentHtml[$index] ?? ''));
                $wc = trim((string) ($contentWrapperClasses[$index] ?? ''));
                $allowed = ['mission-band', 'callout', 'content-cards', 'managed-section'];
                $items[] = [
                    'media_kind' => 'content',
                    'media_file_id' => null,
                    'iframe_html' => null,
                    'poster_media_file_id' => null,
                    'alt_text' => $alt ?: null,
                    'title' => $slideTitle ?: null,
                    'caption' => $caption ?: null,
                    'content_html' => $html ?: null,
                    'content_wrapper_class' => in_array($wc, $allowed, true) ? $wc : null,
                ];
                continue;
            }

            if ($kind === 'iframe') {
                if ($iframe === '' || stripos($iframe, '<iframe') === false || Exhibit::extractIframeSourcePublic($iframe) === null) {
                    throw new InvalidArgumentException('Iframe slides require valid iframe HTML with a usable src.');
                }
                $items[] = [
                    'media_kind' => 'iframe',
                    'media_file_id' => null,
                    'iframe_html' => $iframe,
                    'poster_media_file_id' => null,
                    'alt_text' => $alt ?: null,
                    'title' => $slideTitle ?: null,
                    'caption' => $caption ?: null,
                    'content_html' => null,
                    'content_wrapper_class' => null,
                ];
                continue;
            }

            if ($mediaFileId <= 0) {
                throw new InvalidArgumentException(ucfirst($kind) . ' slides require a media asset.');
            }
            if (!MediaFile::isActiveOfKind($mediaFileId, $kind)) {
                throw new InvalidArgumentException('Selected asset does not match the slide type.');
            }
            if ($posterMediaId > 0 && !MediaFile::isActiveOfKind($posterMediaId, 'image')) {
                throw new InvalidArgumentException('Video posters must be image assets.');
            }

            $items[] = [
                'media_kind' => $kind,
                'media_file_id' => $mediaFileId,
                'iframe_html' => null,
                'poster_media_file_id' => $posterMediaId > 0 ? $posterMediaId : null,
                'alt_text' => $alt ?: null,
                'title' => $slideTitle ?: null,
                'caption' => $caption ?: null,
                'content_html' => null,
                'content_wrapper_class' => null,
            ];
        }

        return $items;
    }

    private static function draftExhibitFromPost(?int $existingId): array
    {
        $existing = $existingId ? Exhibit::find($existingId) : null;

        return [
            'id' => $existingId,
            'title' => trim((string) ($_POST['title'] ?? ($existing['title'] ?? ''))),
            'slug' => trim((string) ($_POST['slug'] ?? ($existing['slug'] ?? ''))),
            'year' => trim((string) ($_POST['year'] ?? ($existing['year'] ?? ''))),
            'artist_name' => trim((string) ($_POST['artist_name'] ?? ($existing['artist_name'] ?? ''))),
            'medium' => trim((string) ($_POST['medium'] ?? ($existing['medium'] ?? ''))),
            'dimensions' => trim((string) ($_POST['dimensions'] ?? ($existing['dimensions'] ?? ''))),
            'collection_ids' => array_map('intval', $_POST['collection_ids'] ?? ($existing ? Collection::collectionIdsForExhibit((int) $existing['id']) : [])),
            'description' => trim((string) ($_POST['description'] ?? ($existing['description'] ?? ''))),
            'placard_notes' => trim((string) ($_POST['placard_notes'] ?? ($existing['placard_notes'] ?? ''))),
            'sort_order' => (int) ($_POST['sort_order'] ?? ($existing['sort_order'] ?? 0)),
            'thumbnail_value' => trim((string) ($_POST['thumbnail_link'] ?? ($existing['thumbnail_value'] ?? ''))),
            'media_items' => self::draftMediaItemsFromPost($existing),
        ];
    }

    private static function draftMediaItemsFromPost(?array $existing): array
    {
        $kinds = $_POST['media_kind'] ?? [];
        if ($kinds === []) {
            return $existing ? Exhibit::resolvedMediaItems($existing) : [];
        }

        $items = [];
        foreach ($kinds as $index => $kindRaw) {
            $kind = trim((string) $kindRaw);
            if ($kind === '') {
                continue;
            }

            $mediaFileId = (int) (($_POST['media_file_id'] ?? [])[$index] ?? 0);
            $posterMediaFileId = (int) (($_POST['poster_media_file_id'] ?? [])[$index] ?? 0);
            $items[] = ExhibitMediaItem::normalizeForDisplay([
                'media_kind' => $kind,
                'media_file_id' => $mediaFileId > 0 ? $mediaFileId : null,
                'iframe_html' => trim((string) (($_POST['iframe_html'] ?? [])[$index] ?? '')),
                'poster_media_file_id' => $posterMediaFileId > 0 ? $posterMediaFileId : null,
                'alt_text' => trim((string) (($_POST['alt_text'] ?? [])[$index] ?? '')),
                'title' => trim((string) (($_POST['slide_title'] ?? [])[$index] ?? '')),
                'caption' => trim((string) (($_POST['caption'] ?? [])[$index] ?? '')),
                'content_html' => trim((string) (($_POST['content_html'] ?? [])[$index] ?? '')),
                'content_wrapper_class' => trim((string) (($_POST['content_wrapper_class'] ?? [])[$index] ?? '')),
                'source_url' => $mediaFileId > 0 ? '/media/' . $mediaFileId : null,
                'poster_url' => $posterMediaFileId > 0 ? '/media/' . $posterMediaFileId : null,
            ]);
        }

        return $items;
    }

    private static function resolveThumbnail(?array $existing): array
    {
        $type = $_POST['thumbnail_type'] ?? 'link';
        if ($type !== 'link') {
            return [null, null];
        }

        $value = trim($_POST['thumbnail_link'] ?? '');
        if ($value === '') {
            return [null, null];
        }

        return ['link', $value];
    }

    private static function resolvedExhibitSlug(string $title, ?int $existingId): string
    {
        $postedSlug = trim($_POST['slug'] ?? '');
        return $postedSlug !== ''
            ? slugify($postedSlug)
            : unique_exhibit_slug($title, $existingId ?? 0);
    }

    private static function resolvedCategorySlug(string $name, ?int $existingId): string
    {
        $postedSlug = trim($_POST['slug'] ?? '');
        return $postedSlug !== ''
            ? slugify($postedSlug)
            : unique_category_slug($name, $existingId ?? 0);
    }

    private static function resolvedCollectionSlug(string $name, ?int $existingId): string
    {
        $postedSlug = trim($_POST['slug'] ?? '');
        return $postedSlug !== ''
            ? slugify($postedSlug)
            : unique_collection_slug($name, $existingId ?? 0);
    }
}
