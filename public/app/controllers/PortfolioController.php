<?php

declare(strict_types=1);

class PortfolioController
{
    public static function gallery(): void
    {
        $exhibits = Exhibit::allWithAtLeastOneArtwork();
        $artworks = Artwork::allSorted();
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

        $artworks = Category::artworks((int) $category['id']);
        require dirname(__DIR__) . '/views/portfolio/category.php';
    }

    public static function exhibit(string $slug): void
    {
        $exhibit = Exhibit::findBySlug($slug);
        if (!$exhibit) {
            require dirname(__DIR__) . '/views/404.php';
            return;
        }

        $artworks = Exhibit::artworks((int) $exhibit['id']);
        require dirname(__DIR__) . '/views/portfolio/exhibit.php';
    }

    public static function work(string $slug): void
    {
        $artwork = Artwork::findBySlug($slug);
        if (!$artwork) {
            require dirname(__DIR__) . '/views/404.php';
            return;
        }

        $mediaItems = $artwork['media_items'] ?? Artwork::resolvedMediaItems($artwork);
        require dirname(__DIR__) . '/views/portfolio/work.php';
    }
}
