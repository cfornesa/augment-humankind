<?php

declare(strict_types=1);

class ApiController
{
    public static function feedsCatalog(): void
    {
        $feeds = [
            ['title' => 'Atom', 'href' => '/api/feeds/atom', 'type' => 'application/atom+xml'],
            ['title' => 'JSON Feed', 'href' => '/api/feeds/json', 'type' => 'application/feed+json'],
            ['title' => 'Microformats2', 'href' => '/api/feeds/mf2', 'type' => 'application/json'],
        ];
        foreach (BlogCategory::all() as $category) {
            $slug = $category['slug'];
            $feeds[] = ['title' => $category['name'] . ' Atom', 'href' => "/blog/category/{$slug}/feed.xml", 'type' => 'application/atom+xml'];
            $feeds[] = ['title' => $category['name'] . ' JSON Feed', 'href' => "/blog/category/{$slug}/feed.json", 'type' => 'application/feed+json'];
        }
        self::json(['feeds' => $feeds]);
    }

    public static function posts(): void
    {
        self::json(['posts' => BlogPost::published(100)]);
    }

    public static function post(string $id): void
    {
        $post = BlogPost::findPublished((int) $id);
        $post ? self::json(['post' => $post]) : self::json(['error' => 'Not found'], 404);
    }

    public static function categories(): void
    {
        self::json(['categories' => BlogCategory::all()]);
    }

    public static function category(string $slug): void
    {
        $category = BlogCategory::findBySlug($slug);
        $category ? self::json(['category' => $category]) : self::json(['error' => 'Not found'], 404);
    }

    public static function categoryPosts(string $slug): void
    {
        $category = BlogCategory::findBySlug($slug);
        if (!$category) {
            self::json(['error' => 'Not found'], 404);
        }
        self::json(['category' => $category, 'posts' => BlogPost::byCategory($slug, 100)]);
    }

    public static function page(string $slug): void
    {
        $page = Page::safeFindPublishedBySlug($slug);
        if (!$page) {
            self::json(['error' => 'Not found'], 404);
        }
        $sections = PageSection::allForPage((int) $page['id']);
        self::json(['page' => $page, 'sections' => $sections]);
    }

    public static function artPieces(): void
    {
        self::json(['artPieces' => PlatformArtPiece::all()]);
    }

    public static function artPiece(string $id): void
    {
        $piece = PlatformArtPiece::find((int) $id);
        $piece ? self::json(['artPiece' => $piece]) : self::json(['error' => 'Not found'], 404);
    }

    public static function artPieceVersions(string $id): void
    {
        $piece = PlatformArtPiece::find((int) $id);
        if (!$piece) {
            self::json(['error' => 'Not found'], 404);
        }
        self::json(['artPiece' => $piece, 'versions' => PlatformArtPieceVersion::allForPiece((int) $id)]);
    }

    public static function collections(): void
    {
        self::json(['collections' => PlatformCollection::all()]);
    }

    public static function collection(string $slug): void
    {
        $collection = PlatformCollection::findBySlug($slug);
        $collection ? self::json(['collection' => $collection]) : self::json(['error' => 'Not found'], 404);
    }

    public static function redirectCollection(string $slug): void
    {
        header('Location: /api/collections/' . $slug, true, 301);
        exit;
    }

    public static function collectionItems(string $slug): void
    {
        $collection = PlatformCollection::findBySlug($slug);
        $collection ? self::json(['items' => $collection['items'] ?? []]) : self::json(['error' => 'Not found'], 404);
    }

    public static function mediaAsset(string $id): void
    {
        $asset = MediaAsset::find((int) $id);
        if (!$asset || empty($asset['file_data'])) {
            self::json(['error' => 'Not found'], 404);
        }
        header('Content-Type: ' . ($asset['mime_type'] ?: 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . str_replace('"', '', (string) ($asset['filename'] ?? 'asset')) . '"');
        echo $asset['file_data'];
        exit;
    }

    public static function mediaAssetByFilename(string $filename): void
    {
        $asset = MediaAsset::findByFilename($filename);
        if (!$asset || empty($asset['file_data'])) {
            self::json(['error' => 'Not found'], 404);
        }
        header('Content-Type: ' . ($asset['mime_type'] ?: 'application/octet-stream'));
        header('Cache-Control: public, max-age=31536000, immutable');
        echo $asset['file_data'];
        exit;
    }

    public static function mediaAssetCollections(string $filename): void
    {
        $asset = MediaAsset::findByFilename($filename);
        if (!$asset) {
            self::json(['error' => 'Not found'], 404);
        }
        unset($asset['file_data']);

        try {
            $stmt = db()->prepare(
                "SELECT pc.*
                 FROM platform_collections pc
                 JOIN platform_collection_items pci ON pci.collection_id = pc.id
                 WHERE pci.item_type = 'media_asset'
                   AND pci.item_id = ?
                   AND pc.deleted_at IS NULL
                 ORDER BY pc.created_at DESC, pc.id DESC"
            );
            $stmt->execute([(int) $asset['id']]);
            self::json(['media' => $asset, 'collections' => $stmt->fetchAll()]);
        } catch (Throwable) {
            self::json(['media' => $asset, 'collections' => []]);
        }
    }

    public static function runtimeAsset(string $path): void
    {
        $path = ltrim($path, '/');
        $map = [
            'p5/p5.min.js' => 'https://cdnjs.cloudflare.com/ajax/libs/p5.js/1.9.0/p5.min.js',
            'c2/c2.min.js' => 'https://cdn.jsdelivr.net/npm/c2.js@1.0.9/dist/c2.min.js',
            'three/three.module.min.js' => 'https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js',
            'three-examples/jsm/controls/OrbitControls.js' => 'https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/controls/OrbitControls.js',
        ];

        if (!isset($map[$path])) {
            self::json(['error' => 'Runtime not found'], 404);
        }

        header('Location: ' . $map[$path], true, 302);
        exit;
    }

    public static function profilePhoto(string $filename): void
    {
        try {
            $stmt = db()->prepare('SELECT * FROM profile_photo_assets WHERE filename = ? LIMIT 1');
            $stmt->execute([$filename]);
            $asset = $stmt->fetch() ?: false;
        } catch (Throwable) {
            $asset = false;
        }
        if (!$asset || empty($asset['file_data'])) {
            self::json(['error' => 'Not found'], 404);
        }
        header('Content-Type: ' . ($asset['mime_type'] ?: 'application/octet-stream'));
        header('Cache-Control: public, max-age=31536000, immutable');
        echo $asset['file_data'];
        exit;
    }

    private static function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
