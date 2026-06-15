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
            PlatformCollection::update((int) $id, [
                'name' => $name,
                'slug' => self::uniqueSlug($slug, (int) $id),
                'description' => trim($_POST['description'] ?? '') ?: null,
                'artist_statement' => trim($_POST['artist_statement'] ?? '') ?: null,
                'biography' => trim($_POST['biography'] ?? '') ?: null,
                'rows' => (int) ($_POST['rows'] ?? 1),
                'cols' => (int) ($_POST['cols'] ?? 1),
                'iframe_code' => trim($_POST['iframe_code'] ?? '') ?: null,
            ]);

            $items = self::resolveSelectedItems();
            PlatformCollection::syncItems((int) $id, $items);

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
}
