<?php

declare(strict_types=1);

class PlatformCollectionsAdminController
{
    public static function index(): void
    {
        admin_check();

        $collections = PlatformCollection::all();
        foreach ($collections as &$collection) {
            $collection['thumbnail_url'] = PlatformCollection::firstThumbnail((int) $collection['id']);
        }
        unset($collection);

        require dirname(__DIR__, 2) . '/views/admin/platform-collections/index.php';
    }

    public static function create(): void
    {
        admin_check();
        $collection = null;
        $allPieces = PlatformArtPiece::allForAdmin();
        $allAssets = MediaAsset::all();
        $assignedItems = [];
        $error = null;

        require dirname(__DIR__, 2) . '/views/admin/platform-collections/form.php';
    }

    public static function store(): void
    {
        admin_check();
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        if ($name === '') {
            $collection = null;
            $allPieces = PlatformArtPiece::allForAdmin();
            $allAssets = MediaAsset::all();
            $assignedItems = [];
            $error = 'Name is required.';
            require dirname(__DIR__, 2) . '/views/admin/platform-collections/form.php';
            return;
        }

        if ($slug === '') {
            $slug = slugify($name);
        }

        try {
            $id = PlatformCollection::create([
                'name' => $name,
                'slug' => self::uniqueSlug($slug, null),
                'description' => trim($_POST['description'] ?? '') ?: null,
                'artist_statement' => trim($_POST['artist_statement'] ?? '') ?: null,
                'biography' => trim($_POST['biography'] ?? '') ?: null,
                'rows' => (int) ($_POST['rows'] ?? 1),
                'cols' => (int) ($_POST['cols'] ?? 1),
                'iframe_code' => trim($_POST['iframe_code'] ?? '') ?: null,
                'comments_enabled' => isset($_POST['comments_enabled']) ? 1 : 0,
            ]);

            $items = self::resolveSelectedItems();
            PlatformCollection::syncItems($id, $items);

            header('Location: /admin/platform-collections');
            exit;
        } catch (Throwable $e) {
            $collection = null;
            $allPieces = PlatformArtPiece::allForAdmin();
            $allAssets = MediaAsset::all();
            $assignedItems = self::resolveSelectedItems();
            $error = $e->getMessage();
            require dirname(__DIR__, 2) . '/views/admin/platform-collections/form.php';
        }
    }

    public static function edit(string $id): void
    {
        admin_check();
        $collection = PlatformCollection::find((int) $id);
        if (!$collection) {
            header('Location: /admin/platform-collections');
            exit;
        }

        $allPieces = PlatformArtPiece::allForAdmin();
        $allAssets = MediaAsset::all();
        $assignedItems = PlatformCollection::itemsFor((int) $id);
        $error = null;

        require dirname(__DIR__, 2) . '/views/admin/platform-collections/form.php';
    }

    public static function update(string $id): void
    {
        admin_check();
        $existing = PlatformCollection::find((int) $id);
        if (!$existing) {
            header('Location: /admin/platform-collections');
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        if ($name === '') {
            $collection = $existing;
            $allPieces = PlatformArtPiece::allForAdmin();
            $allAssets = MediaAsset::all();
            $assignedItems = PlatformCollection::itemsFor((int) $id);
            $error = 'Name is required.';
            require dirname(__DIR__, 2) . '/views/admin/platform-collections/form.php';
            return;
        }

        if ($slug === '') {
            $slug = slugify($name);
        }

        try {
            $newSlug = self::uniqueSlug($slug, (int) $id);
            PlatformCollection::update((int) $id, [
                'name' => $name,
                'slug' => $newSlug,
                'description' => trim($_POST['description'] ?? '') ?: null,
                'artist_statement' => trim($_POST['artist_statement'] ?? '') ?: null,
                'biography' => trim($_POST['biography'] ?? '') ?: null,
                'rows' => (int) ($_POST['rows'] ?? 1),
                'cols' => (int) ($_POST['cols'] ?? 1),
                'iframe_code' => trim($_POST['iframe_code'] ?? '') ?: null,
                'sort_order' => (int) ($existing['sort_order'] ?? 0),
                'comments_enabled' => isset($_POST['comments_enabled']) ? 1 : 0,
                'thumbnail_url' => trim($_POST['thumbnail_url'] ?? '') ?: ($existing['thumbnail_url'] ?? null),
            ]);

            $items = self::resolveSelectedItems();
            PlatformCollection::syncItems((int) $id, $items);

            if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => true, 'slug' => $newSlug]);
                exit;
            }

            header('Location: /admin/platform-collections');
            exit;
        } catch (Throwable $e) {
            $collection = $existing;
            $allPieces = PlatformArtPiece::allForAdmin();
            $allAssets = MediaAsset::all();
            $assignedItems = self::resolveSelectedItems();
            $error = $e->getMessage();
            require dirname(__DIR__, 2) . '/views/admin/platform-collections/form.php';
        }
    }

    public static function delete(string $id): void
    {
        admin_check();
        PlatformCollection::softDelete((int) $id);
        header('Location: /admin/platform-collections');
        exit;
    }

    public static function reorder(): void
    {
        admin_check();
        $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
        PlatformCollection::reorder($ids);
        header('Content-Type: application/json');
        echo '{"ok":true}';
        exit;
    }

    public static function library(): void
    {
        admin_check();
        header('Content-Type: application/json');

        $collections = array_map(static function (array $collection): array {
            return [
                'slug' => $collection['slug'] ?? '',
                'name' => $collection['name'] ?? 'Untitled Collection',
                'item_count' => (int) ($collection['item_count'] ?? 0),
                'thumbnail_url' => PlatformCollection::firstThumbnail((int) $collection['id']),
            ];
        }, PlatformCollection::all());

        echo json_encode($collections);
        exit;
    }

    private static function resolveSelectedItems(): array
    {
        $items = [];
        foreach ($_POST['items'] ?? [] as $itemStr) {
            if (str_contains($itemStr, ':')) {
                [$type, $id] = explode(':', $itemStr, 2);
                $items[] = [
                    'item_type' => $type,
                    'item_id' => (int) $id,
                ];
            }
        }
        return $items;
    }

    private static function uniqueSlug(string $slug, ?int $excludeId): string
    {
        $original = $slug;
        $count = 1;
        while (true) {
            $stmt = db()->prepare(
                'SELECT id FROM platform_collections WHERE slug = ?' . ($excludeId ? ' AND id != ?' : '')
            );
            $params = $excludeId ? [$slug, $excludeId] : [$slug];
            $stmt->execute($params);
            if (!$stmt->fetch()) {
                return $slug;
            }
            $slug = $original . '-' . $count;
            $count++;
        }
    }

    public static function captureThumbnail(string $id): void
    {
        admin_check();
        header('Content-Type: application/json');

        $collection = PlatformCollection::find((int) $id);
        if (!$collection) {
            http_response_code(404);
            echo json_encode(['error' => 'Platform Collection not found.']);
            exit;
        }

        $raw = trim((string) ($_POST['image_data'] ?? ''));
        if ($raw === '') {
            http_response_code(400);
            echo json_encode(['error' => 'No image data received.']);
            exit;
        }

        if (str_contains($raw, ',')) {
            $raw = substr($raw, strpos($raw, ',') + 1);
        }

        $binary = base64_decode($raw, strict: true);
        if ($binary === false || strlen($binary) < 100) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid image data.']);
            exit;
        }

        $mediaId = MediaFile::create($binary, 'image/png', 'collection-thumbnail.png');
        $url = '/image/' . $mediaId;
        PlatformCollection::update((int) $id, array_merge($collection, [
            'thumbnail_url' => $url,
            'comments_enabled' => (int)(bool) ($collection['comments_enabled'] ?? 0),
        ]));

        echo json_encode(['ok' => true, 'url' => $url]);
        exit;
    }
}
