<?php

declare(strict_types=1);

class TrashController
{
    public static function index(): void
    {
        admin_check();
        $tab = $_GET['tab'] ?? 'exhibits';
        $exhibits = Exhibit::trashed();
        $artMedia = Category::trashed();
        $categories = BlogCategory::trashed();
        $collections = Collection::trashed();
        $mediaFiles = array_merge(
            array_map(static function (array $row): array {
                $row['_type'] = 'media';
                $row['label'] = 'ID ' . (int) $row['id'] . ' · ' . (string) ($row['mime_type'] ?? '');
                return $row;
            }, MediaFile::trashed()),
            array_map(static function (array $row): array {
                $row['_type'] = 'media_asset';
                $row['label'] = !empty($row['title'])
                    ? $row['title']
                    : (!empty($row['filename']) ? $row['filename'] : ('Media Asset #' . (int) $row['id']));
                return $row;
            }, MediaAsset::trashed())
        );
        $posts = BlogPost::trashed();
        $comments = Comment::trashed();
        require dirname(__DIR__, 2) . '/views/admin/trash.php';
    }

    public static function restore(): void
    {
        admin_check();
        $type = $_POST['type'] ?? '';
        $id = (int) ($_POST['id'] ?? 0);

        match ($type) {
            'exhibit' => Exhibit::restore($id),
            'art-medium' => Category::restore($id),
            'category' => BlogCategory::restore($id),
            'collection' => Collection::restore($id),
            'media' => MediaFile::restore($id),
            'media_asset' => MediaAsset::restore($id),
            'post' => BlogPost::restore($id),
            'comment' => Comment::restore($id),
            default => null,
        };

        header('Location: /admin/trash?tab=' . self::tabForType($type));
        exit;
    }

    public static function purge(): void
    {
        admin_check();
        $type = $_POST['type'] ?? '';
        $id = (int) ($_POST['id'] ?? 0);

        match ($type) {
            'exhibit' => Exhibit::hardDelete($id),
            'art-medium' => Category::hardDelete($id),
            'category' => BlogCategory::hardDelete($id),
            'collection' => Collection::hardDelete($id),
            'media' => MediaFile::hardDelete($id),
            'media_asset' => MediaAsset::hardDelete($id),
            'post' => BlogPost::hardDelete($id),
            'comment' => Comment::hardDelete($id),
            default => null,
        };

        header('Location: /admin/trash?tab=' . self::tabForType($type));
        exit;
    }

    public static function empty(): void
    {
        admin_check();
        $type = $_POST['type'] ?? '';

        switch ($type) {
            case 'exhibits':
                foreach (Exhibit::trashed() as $exhibit) {
                    Exhibit::hardDelete((int) $exhibit['id']);
                }
                break;
            case 'art-media':
                foreach (Category::trashed() as $artMedium) {
                    Category::hardDelete((int) $artMedium['id']);
                }
                break;
            case 'categories':
                foreach (BlogCategory::trashed() as $category) {
                    BlogCategory::hardDelete((int) $category['id']);
                }
                break;
            case 'collections':
                foreach (Collection::trashed() as $collection) {
                    Collection::hardDelete((int) $collection['id']);
                }
                break;
            case 'media':
                foreach (MediaFile::trashed() as $file) {
                    MediaFile::hardDelete((int) $file['id']);
                }
                foreach (MediaAsset::trashed() as $asset) {
                    MediaAsset::hardDelete((int) $asset['id']);
                }
                break;
            case 'posts':
                foreach (BlogPost::trashed() as $post) {
                    BlogPost::hardDelete((int) $post['id']);
                }
                break;
            case 'comments':
                foreach (Comment::trashed() as $comment) {
                    Comment::hardDelete((int) $comment['id']);
                }
                break;
        }

        header('Location: /admin/trash?tab=' . rawurlencode($type));
        exit;
    }

    private static function tabForType(string $type): string
    {
        return match ($type) {
            'exhibit' => 'exhibits',
            'art-medium' => 'art-media',
            'category' => 'categories',
            'collection' => 'collections',
            'post' => 'posts',
            'comment' => 'comments',
            'media', 'media_asset' => 'media',
            default => $type,
        };
    }
}
