<?php

declare(strict_types=1);

class PortfolioController
{
    public static function gallery(): void
    {
        $collections = Collection::allWithAtLeastOneExhibit();
        $exhibits = Exhibit::allSorted();
        require dirname(__DIR__) . '/views/portfolio/gallery.php';
    }

    public static function categories(): void
    {
        $categories = Category::all();
        require dirname(__DIR__) . '/views/portfolio/categories.php';
    }

    public static function category(string $slug): void
    {
        $category = Category::findBySlug($slug);
        if (!$category) {
            require dirname(__DIR__) . '/views/404.php';
            return;
        }

        $exhibits = Category::exhibits((int) $category['id']);
        require dirname(__DIR__) . '/views/portfolio/category.php';
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
}
