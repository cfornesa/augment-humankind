<?php

declare(strict_types=1);

class CategoriesController
{
    public static function index(): void
    {
        $categories = Category::all();
        require dirname(__DIR__) . '/views/categories.php';
    }

    public static function show(string $slug): void
    {
        $category = Category::findBySlug($slug);
        if (!$category) {
            http_response_code(404);
            require dirname(__DIR__) . '/views/404.php';
            return;
        }
        $artworks = Category::artworks($category['id']);
        require dirname(__DIR__) . '/views/category.php';
    }
}
