<?php

declare(strict_types=1);

class PortfolioAdminController
{
    public static function artworksIndex(): void
    {
        admin_check();
        $artworks = Artwork::all();
        $categories = Category::all();
        require dirname(__DIR__, 2) . '/views/admin/artworks/index.php';
    }

    public static function artworkCreate(): void
    {
        admin_check();
        $categories = Category::all();
        $allExhibits = Exhibit::all();
        $assignedCategoryIds = [];
        $assignedExhibitIds = [];
        $artwork = ['media_items' => []];
        $error = null;
        require dirname(__DIR__, 2) . '/views/admin/artworks/form.php';
    }

    public static function artworkStore(): void
    {
        admin_check();

        try {
            $data = self::resolveArtworkData(null);
            $artworkId = Artwork::create($data);
            ArtworkMediaItem::syncForArtwork($artworkId, $data['media_items']);
            Artwork::syncCategories($artworkId, $data['category_ids']);
            Exhibit::syncForArtwork($artworkId, $data['exhibit_ids']);
            header('Location: /admin/artworks');
        } catch (Throwable $e) {
            $categories = Category::all();
            $allExhibits = Exhibit::all();
            $artwork = self::draftArtworkFromPost(null);
            $assignedCategoryIds = $artwork['category_ids'];
            $assignedExhibitIds = $artwork['exhibit_ids'];
            $error = $e->getMessage();
            require dirname(__DIR__, 2) . '/views/admin/artworks/form.php';
        }
        exit;
    }

    public static function artworkEdit(string $id): void
    {
        admin_check();
        $artwork = Artwork::find((int) $id);
        if (!$artwork) {
            header('Location: /admin/artworks');
            exit;
        }
        $categories = Category::all();
        $allExhibits = Exhibit::all();
        $assignedCategoryIds = Artwork::categoryIds((int) $id);
        $assignedExhibitIds = Exhibit::exhibitIdsForArtwork((int) $id);
        $error = null;
        require dirname(__DIR__, 2) . '/views/admin/artworks/form.php';
    }

    public static function artworkUpdate(string $id): void
    {
        admin_check();

        try {
            $data = self::resolveArtworkData((int) $id);
            Artwork::update((int) $id, $data);
            ArtworkMediaItem::syncForArtwork((int) $id, $data['media_items']);
            Artwork::syncCategories((int) $id, $data['category_ids']);
            Exhibit::syncForArtwork((int) $id, $data['exhibit_ids']);
            header('Location: /admin/artworks');
        } catch (Throwable $e) {
            $artwork = self::draftArtworkFromPost((int) $id);
            $categories = Category::all();
            $allExhibits = Exhibit::all();
            $assignedCategoryIds = $artwork['category_ids'];
            $assignedExhibitIds = $artwork['exhibit_ids'];
            $error = $e->getMessage();
            require dirname(__DIR__, 2) . '/views/admin/artworks/form.php';
        }
        exit;
    }

    public static function artworkDelete(string $id): void
    {
        admin_check();
        Artwork::softDelete((int) $id);
        header('Location: /admin/artworks');
        exit;
    }

    public static function artworkReorder(): void
    {
        admin_check();
        $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
        Artwork::reorder($ids);
        header('Content-Type: application/json');
        echo '{"ok":true}';
        exit;
    }

    public static function categoriesIndex(): void
    {
        admin_check();
        $categories = Category::all();
        require dirname(__DIR__, 2) . '/views/admin/categories/index.php';
    }

    public static function categoryCreate(): void
    {
        admin_check();
        $category = null;
        $error = null;
        require dirname(__DIR__, 2) . '/views/admin/categories/form.php';
    }

    public static function categoryStore(): void
    {
        admin_check();
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $category = null;
            $error = 'Name is required.';
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
            header('Location: /admin/categories');
        } catch (Throwable $e) {
            $category = null;
            $error = $e->getMessage();
            require dirname(__DIR__, 2) . '/views/admin/categories/form.php';
            return;
        }
        exit;
    }

    public static function categoryCreateInline(): void
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
            header('Location: /admin/categories');
            exit;
        }
        $error = null;
        require dirname(__DIR__, 2) . '/views/admin/categories/form.php';
    }

    public static function categoryUpdate(string $id): void
    {
        admin_check();
        $existing = Category::find((int) $id);
        if (!$existing) {
            header('Location: /admin/categories');
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $category = $existing;
            $error = 'Name is required.';
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
            header('Location: /admin/categories');
        } catch (Throwable $e) {
            $category = $existing;
            $error = $e->getMessage();
            require dirname(__DIR__, 2) . '/views/admin/categories/form.php';
            return;
        }
        exit;
    }

    public static function categoryDelete(string $id): void
    {
        admin_check();
        Category::softDelete((int) $id);
        header('Location: /admin/categories');
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

    public static function exhibitsIndex(): void
    {
        admin_check();
        $exhibits = Exhibit::allWithArtworkCount();
        require dirname(__DIR__, 2) . '/views/admin/exhibits/index.php';
    }

    public static function exhibitCreate(): void
    {
        admin_check();
        $exhibit = null;
        $allArtworks = Artwork::all();
        $assigned = [];
        $error = null;
        require dirname(__DIR__, 2) . '/views/admin/exhibits/form.php';
    }

    public static function exhibitStore(): void
    {
        admin_check();
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $exhibit = null;
            $allArtworks = Artwork::all();
            $assigned = [];
            $error = 'Name is required.';
            require dirname(__DIR__, 2) . '/views/admin/exhibits/form.php';
            return;
        }

        try {
            [$thumbType, $thumbValue] = self::resolveThumbnail(null);
            $id = Exhibit::create([
                'name' => $name,
                'slug' => self::resolvedExhibitSlug($name, null),
                'description' => trim($_POST['description'] ?? ''),
                'thumbnail_type' => $thumbType,
                'thumbnail_value' => $thumbValue,
                'sort_order' => 0,
            ]);
            Exhibit::syncArtworks($id, array_map('intval', $_POST['artwork_ids'] ?? []));
            header('Location: /admin/exhibits');
        } catch (Throwable $e) {
            $exhibit = null;
            $allArtworks = Artwork::all();
            $assigned = [];
            $error = $e->getMessage();
            require dirname(__DIR__, 2) . '/views/admin/exhibits/form.php';
            return;
        }
        exit;
    }

    public static function exhibitCreateInline(): void
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
            $slug = unique_exhibit_slug($name);
            $id = Exhibit::create([
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

    public static function exhibitEdit(string $id): void
    {
        admin_check();
        $exhibit = Exhibit::find((int) $id);
        if (!$exhibit) {
            header('Location: /admin/exhibits');
            exit;
        }
        $allArtworks = Artwork::all();
        $assigned = Exhibit::artworkIds((int) $id);
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

        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $exhibit = $existing;
            $allArtworks = Artwork::all();
            $assigned = Exhibit::artworkIds((int) $id);
            $error = 'Name is required.';
            require dirname(__DIR__, 2) . '/views/admin/exhibits/form.php';
            return;
        }

        try {
            [$thumbType, $thumbValue] = self::resolveThumbnail($existing);
            Exhibit::update((int) $id, [
                'name' => $name,
                'slug' => self::resolvedExhibitSlug($name, (int) $id),
                'description' => trim($_POST['description'] ?? ''),
                'thumbnail_type' => $thumbType,
                'thumbnail_value' => $thumbValue,
                'sort_order' => (int) ($existing['sort_order'] ?? 0),
            ]);
            Exhibit::syncArtworks((int) $id, array_map('intval', $_POST['artwork_ids'] ?? []));
            header('Location: /admin/exhibits');
        } catch (Throwable $e) {
            $exhibit = $existing;
            $allArtworks = Artwork::all();
            $assigned = Exhibit::artworkIds((int) $id);
            $error = $e->getMessage();
            require dirname(__DIR__, 2) . '/views/admin/exhibits/form.php';
            return;
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

    private static function resolveArtworkData(?int $existingId): array
    {
        $existing = $existingId ? Artwork::find($existingId) : null;
        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            throw new InvalidArgumentException('Title is required.');
        }

        $mediaItems = self::resolveArtworkMediaItems();
        if ($mediaItems === [] && $existing) {
            $mediaItems = Artwork::resolvedMediaItems($existing);
        }
        if ($mediaItems === []) {
            throw new InvalidArgumentException('Add at least one artwork slide.');
        }

        [$thumbType, $thumbValue] = self::resolveThumbnail($existing);

        return [
            'title' => $title,
            'slug' => self::resolvedArtworkSlug($title, $existingId),
            'year' => trim($_POST['year'] ?? ''),
            'artist_name' => trim($_POST['artist_name'] ?? ''),
            'medium' => trim($_POST['medium'] ?? ''),
            'dimensions' => trim($_POST['dimensions'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'placard_notes' => trim($_POST['placard_notes'] ?? ''),
            'thumbnail_type' => $thumbType,
            'thumbnail_value' => $thumbValue,
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
            'media_items' => $mediaItems,
            'category_ids' => array_map('intval', $_POST['category_ids'] ?? []),
            'exhibit_ids' => array_map('intval', $_POST['exhibit_ids'] ?? []),
        ];
    }

    private static function resolveArtworkMediaItems(): array
    {
        $kinds = $_POST['media_kind'] ?? [];
        $mediaIds = $_POST['media_file_id'] ?? [];
        $posterIds = $_POST['poster_media_file_id'] ?? [];
        $alts = $_POST['alt_text'] ?? [];
        $titles = $_POST['slide_title'] ?? [];
        $captions = $_POST['caption'] ?? [];
        $iframeHtml = $_POST['iframe_html'] ?? [];
        $items = [];

        foreach ($kinds as $index => $kindRaw) {
            $kind = trim((string) $kindRaw);
            if ($kind === '') {
                continue;
            }
            if (!in_array($kind, ['image', 'video', 'iframe'], true)) {
                throw new InvalidArgumentException('Invalid artwork slide type.');
            }

            $mediaFileId = (int) ($mediaIds[$index] ?? 0);
            $posterMediaId = (int) ($posterIds[$index] ?? 0);
            $alt = trim((string) ($alts[$index] ?? ''));
            $slideTitle = trim((string) ($titles[$index] ?? ''));
            $caption = trim((string) ($captions[$index] ?? ''));
            $iframe = trim((string) ($iframeHtml[$index] ?? ''));

            if ($kind === 'iframe') {
                if ($iframe === '' || stripos($iframe, '<iframe') === false || Artwork::extractIframeSourcePublic($iframe) === null) {
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
            ];
        }

        return $items;
    }

    private static function draftArtworkFromPost(?int $existingId): array
    {
        $existing = $existingId ? Artwork::find($existingId) : null;

        return [
            'id' => $existingId,
            'title' => trim((string) ($_POST['title'] ?? ($existing['title'] ?? ''))),
            'slug' => trim((string) ($_POST['slug'] ?? ($existing['slug'] ?? ''))),
            'year' => trim((string) ($_POST['year'] ?? ($existing['year'] ?? ''))),
            'artist_name' => trim((string) ($_POST['artist_name'] ?? ($existing['artist_name'] ?? ''))),
            'medium' => trim((string) ($_POST['medium'] ?? ($existing['medium'] ?? ''))),
            'dimensions' => trim((string) ($_POST['dimensions'] ?? ($existing['dimensions'] ?? ''))),
            'category_ids' => array_map('intval', $_POST['category_ids'] ?? ($existing ? Artwork::categoryIds((int) $existing['id']) : [])),
            'exhibit_ids' => array_map('intval', $_POST['exhibit_ids'] ?? ($existing ? Exhibit::exhibitIdsForArtwork((int) $existing['id']) : [])),
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
            return $existing ? Artwork::resolvedMediaItems($existing) : [];
        }

        $items = [];
        foreach ($kinds as $index => $kindRaw) {
            $kind = trim((string) $kindRaw);
            if ($kind === '') {
                continue;
            }

            $mediaFileId = (int) (($_POST['media_file_id'] ?? [])[$index] ?? 0);
            $posterMediaFileId = (int) (($_POST['poster_media_file_id'] ?? [])[$index] ?? 0);
            $items[] = ArtworkMediaItem::normalizeForDisplay([
                'media_kind' => $kind,
                'media_file_id' => $mediaFileId > 0 ? $mediaFileId : null,
                'iframe_html' => trim((string) (($_POST['iframe_html'] ?? [])[$index] ?? '')),
                'poster_media_file_id' => $posterMediaFileId > 0 ? $posterMediaFileId : null,
                'alt_text' => trim((string) (($_POST['alt_text'] ?? [])[$index] ?? '')),
                'title' => trim((string) (($_POST['slide_title'] ?? [])[$index] ?? '')),
                'caption' => trim((string) (($_POST['caption'] ?? [])[$index] ?? '')),
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

    private static function resolvedArtworkSlug(string $title, ?int $existingId): string
    {
        $postedSlug = trim($_POST['slug'] ?? '');
        return $postedSlug !== ''
            ? slugify($postedSlug)
            : unique_slug($title, $existingId ?? 0);
    }

    private static function resolvedCategorySlug(string $name, ?int $existingId): string
    {
        $postedSlug = trim($_POST['slug'] ?? '');
        return $postedSlug !== ''
            ? slugify($postedSlug)
            : unique_category_slug($name, $existingId ?? 0);
    }

    private static function resolvedExhibitSlug(string $name, ?int $existingId): string
    {
        $postedSlug = trim($_POST['slug'] ?? '');
        return $postedSlug !== ''
            ? slugify($postedSlug)
            : unique_exhibit_slug($name, $existingId ?? 0);
    }
}
