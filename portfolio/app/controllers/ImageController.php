<?php

declare(strict_types=1);

class ImageController
{
    public static function serve(string $id): void
    {
        $asset = MediaFile::getData((int) $id);
        if (!$asset || $asset['deleted_at'] !== null || !str_starts_with((string) ($asset['mime_type'] ?? ''), 'image/')) {
            http_response_code(404);
            exit;
        }

        MediaController::serve($id);
    }
}
