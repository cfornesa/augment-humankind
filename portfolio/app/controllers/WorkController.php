<?php

declare(strict_types=1);

class WorkController
{
    public static function show(string $slug): void
    {
        $artwork = Artwork::findBySlug($slug);
        if (!$artwork) {
            http_response_code(404);
            require dirname(__DIR__) . '/views/404.php';
            return;
        }
        $mediaItems = $artwork['media_items'] ?? Artwork::resolvedMediaItems($artwork);
        $pieceState = $mediaItems !== []
            ? ['valid' => true, 'message' => 'Rendering artwork carousel.']
            : Artwork::inspectPiece($artwork);
        require dirname(__DIR__) . '/views/work.php';
    }
}
