<?php

declare(strict_types=1);

class MediaAdminController
{
    public static function index(): void
    {
        admin_check();
        $files = MediaFile::all();
        require dirname(__DIR__, 2) . '/views/admin/media.php';
    }

    public static function library(): void
    {
        admin_check();
        header('Content-Type: application/json');

        $files = array_map(static function (array $file): array {
            $mime = (string) ($file['mime_type'] ?? '');
            $kind = str_starts_with($mime, 'video/') ? 'video' : 'image';

            return $file + [
                'kind' => $kind,
                'url' => '/media/' . $file['id'],
                'legacy_url' => $kind === 'image' ? '/image/' . $file['id'] : null,
            ];
        }, MediaFile::all());

        echo json_encode($files);
        exit;
    }

    public static function upload(): void
    {
        admin_check();
        header('Content-Type: application/json');

        if (empty($_FILES['media_file']['name'])) {
            echo json_encode(['ok' => false, 'error' => 'No file provided.']);
            exit;
        }

        try {
            $asset = upload_media_auto($_FILES['media_file']);
            echo json_encode([
                'ok' => true,
                'id' => $asset['id'],
                'mime_type' => $asset['mime_type'],
                'url' => $asset['url'],
                'legacy_url' => $asset['legacy_url'],
                'kind' => str_starts_with($asset['mime_type'], 'video/') ? 'video' : 'image',
            ]);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public static function import(): void
    {
        admin_check();
        header('Content-Type: application/json');

        $url = trim($_POST['url'] ?? '');
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            echo json_encode(['ok' => false, 'error' => 'Invalid URL.']);
            exit;
        }

        $limit = 8 * 1024 * 1024;
        $context = stream_context_create(['http' => ['timeout' => 20, 'follow_location' => true]]);
        $data = @file_get_contents($url, false, $context, 0, $limit + 1);
        if ($data === false || $data === '') {
            echo json_encode(['ok' => false, 'error' => 'Could not fetch the URL.']);
            exit;
        }
        if (strlen($data) > $limit) {
            echo json_encode(['ok' => false, 'error' => 'Image exceeds 8 MB limit.']);
            exit;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($data);
        if (!is_string($mime) || !isset(ALLOWED_IMAGE_MIME[$mime])) {
            echo json_encode(['ok' => false, 'error' => 'URL does not point to a supported image type.']);
            exit;
        }

        $name = basename(parse_url($url, PHP_URL_PATH) ?: 'imported-image');
        $id = MediaFile::create($data, $mime, $name);
        echo json_encode(['ok' => true, 'id' => $id, 'url' => "/media/$id", 'legacy_url' => "/image/$id", 'kind' => 'image']);
        exit;
    }

    public static function trash(string $id): void
    {
        admin_check();
        MediaFile::softDelete((int) $id);
        header('Location: /admin/media');
        exit;
    }

    public static function destroy(string $id): void
    {
        admin_check();
        MediaFile::hardDelete((int) $id);
        header('Location: /admin/media');
        exit;
    }
}
