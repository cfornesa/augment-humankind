<?php

declare(strict_types=1);

class GalleryController
{
    public static function index(): void
    {
        $exhibits = Exhibit::allWithAtLeastOneArtwork();
        $artworks = Artwork::allSorted();
        require dirname(__DIR__) . '/views/gallery.php';
    }
}
