<?php

declare(strict_types=1);

class PiecesController
{
    public static function index(): void
    {
        $pieces = PlatformArtPiece::all();
        $pageTitle = 'Art Pieces | Augment Humankind';
        $pageDescription = 'Generative art pieces and creative experiments.';
        $bodyClass = 'page-pieces';
        $canonicalUrl = seo_absolute_url('/pieces');
        require dirname(__DIR__) . '/views/pieces/index.php';
    }

    public static function show(string $id): void
    {
        $piece = PlatformArtPiece::find((int)$id);
        if (!$piece) {
            self::notFound();
        }

        $pageTitle = (($piece['title'] ?? '') ?: 'Art Piece') . ' | Augment Humankind';
        $pageDescription = seo_excerpt($piece['description'] ?? '', 160)
            ?? 'A generative art piece from Augment Humankind.';
        $bodyClass = 'page-piece';
        $canonicalUrl = seo_absolute_url('/pieces/' . $id);
        $ogImage = $piece['thumbnail_url'] ?? null;

        $version = $piece['current_version'] ?? null;
        if (!$version && !empty($piece['versions'])) {
            $version = $piece['versions'][0];
        }

        require dirname(__DIR__) . '/views/pieces/show.php';
    }

    private static function notFound(): never
    {
        http_response_code(404);
        require dirname(__DIR__) . '/views/404.php';
        exit;
    }
}
