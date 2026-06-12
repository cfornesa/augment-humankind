<?php

declare(strict_types=1);

class ExhibitController
{
    public static function show(string $slug): void
    {
        $exhibit = Exhibit::findBySlug($slug);
        if (!$exhibit) {
            http_response_code(404);
            require dirname(__DIR__) . '/views/404.php';
            return;
        }
        $artworks = Exhibit::artworks($exhibit['id']);
        require dirname(__DIR__) . '/views/exhibit.php';
    }
}
