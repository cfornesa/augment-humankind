<?php

declare(strict_types=1);

class TrashController
{
    public static function index(): void
    {
        admin_check();
        $tab = $_GET['tab'] ?? 'artworks';
        $artworks = Artwork::trashed();
        $categories = Category::trashed();
        $exhibits = Exhibit::trashed();
        $mediaFiles = MediaFile::trashed();
        require dirname(__DIR__, 2) . '/views/admin/trash.php';
    }

    public static function restore(): void
    {
        admin_check();
        $type = $_POST['type'] ?? '';
        $id = (int) ($_POST['id'] ?? 0);

        match ($type) {
            'artwork' => Artwork::restore($id),
            'category' => Category::restore($id),
            'exhibit' => Exhibit::restore($id),
            'media' => MediaFile::restore($id),
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
            'artwork' => Artwork::hardDelete($id),
            'category' => Category::hardDelete($id),
            'exhibit' => Exhibit::hardDelete($id),
            'media' => MediaFile::hardDelete($id),
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
            case 'artworks':
                foreach (Artwork::trashed() as $artwork) {
                    Artwork::hardDelete((int) $artwork['id']);
                }
                break;
            case 'categories':
                foreach (Category::trashed() as $category) {
                    Category::hardDelete((int) $category['id']);
                }
                break;
            case 'exhibits':
                foreach (Exhibit::trashed() as $exhibit) {
                    Exhibit::hardDelete((int) $exhibit['id']);
                }
                break;
            case 'media':
                foreach (MediaFile::trashed() as $file) {
                    MediaFile::hardDelete((int) $file['id']);
                }
                break;
        }

        header('Location: /admin/trash?tab=' . rawurlencode($type));
        exit;
    }

    private static function tabForType(string $type): string
    {
        return match ($type) {
            'artwork' => 'artworks',
            'category' => 'categories',
            'exhibit' => 'exhibits',
            default => $type,
        };
    }
}
