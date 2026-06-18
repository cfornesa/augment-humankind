<?php

declare(strict_types=1);

class MediaAdminController
{
    public static function index(): void
    {
        admin_check();
        $nativeAltTextSupported = MediaFile::supportsAltText();
        $nativeTitleSupported = MediaFile::supportsTitle();
        $warning = $_GET['warning'] ?? '';

        $files = array_map(static function (array $file): array {
            $id = (int) $file['id'];
            $mime = (string) ($file['mime_type'] ?? '');
            $isVideo = str_starts_with($mime, 'video/');
            $isHtml = $mime === 'text/html' || str_starts_with($mime, 'iframe');

            return $file + [
                'source' => 'file',
                'preview' => $isVideo ? '/media/' . $id : ($isHtml ? '/media/' . $id : '/image/' . $id),
                'direct_url' => '/media/' . $id,
                'label' => trim((string) ($file['title'] ?? '')) !== '' ? (string) $file['title'] : ('Asset #' . $id),
                'alt_text_supported' => MediaFile::supportsAltText(),
                'title_supported' => MediaFile::supportsTitle(),
            ];
        }, MediaFile::all());

        $assets = array_map(static function (array $asset): array {
            $id = (int) $asset['id'];
            $mime = (string) ($asset['mime_type'] ?? '');
            $isVideo = str_starts_with($mime, 'video/');
            $isHtml = $mime === 'text/html' || str_starts_with($mime, 'iframe');

            return [
                'id' => 'asset-' . $id,
                'source' => 'asset',
                'mime_type' => $mime,
                'byte_size' => $asset['byte_size'] ?? 0,
                'created_at' => $asset['uploaded_at'] ?? null,
                'preview' => '/api/media-assets/' . $id,
                'direct_url' => '/api/media-assets/' . $id,
                'label' => !empty($asset['title']) ? $asset['title'] : 'Media Asset #' . $id,
                'asset_id' => $id,
                'title' => $asset['title'] ?? '',
                'alt_text' => $asset['alt_text'] ?? '',
            ];
        }, MediaAsset::all());

        $files = array_merge($files, $assets);

        require dirname(__DIR__, 2) . '/views/admin/media.php';
    }

    public static function library(): void
    {
        admin_check();
        header('Content-Type: application/json');

        $files = array_map(static function (array $file): array {
            $mime = (string) ($file['mime_type'] ?? '');
            if (str_starts_with($mime, 'video/')) {
                $kind = 'video';
            } elseif ($mime === 'text/html' || str_starts_with($mime, 'iframe')) {
                $kind = 'iframe';
            } else {
                $kind = 'image';
            }

            return $file + [
                'kind' => $kind,
                'url' => '/media/' . $file['id'],
                'legacy_url' => $kind === 'image' ? '/image/' . $file['id'] : ($kind === 'iframe' ? '/media/' . $file['id'] : null),
                'title' => $file['title'] ?? null,
                'alt_text' => $file['alt_text'] ?? '',
                'alt_text_supported' => MediaFile::supportsAltText(),
                'title_supported' => MediaFile::supportsTitle(),
            ];
        }, MediaFile::all());

        $assets = array_map(static function (array $asset): array {
            $mime = (string) ($asset['mime_type'] ?? '');
            if (str_starts_with($mime, 'video/')) {
                $kind = 'video';
            } elseif ($mime === 'text/html' || str_starts_with($mime, 'iframe')) {
                $kind = 'iframe';
            } else {
                $kind = 'image';
            }
            $url = '/api/media-assets/' . $asset['id'];

            unset($asset['file_data']);
            $asset['id'] = 'asset-' . $asset['id'];
            $asset['kind'] = $kind;
            $asset['url'] = $url;
            $asset['legacy_url'] = $url;

            return $asset;
        }, MediaAsset::all());

        echo json_encode(array_merge($files, $assets));
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

    public static function assetUpdate(string $id): void
    {
        admin_check();
        $title = trim((string) ($_POST['title'] ?? ''));
        $altText = trim((string) ($_POST['alt_text'] ?? ''));
        MediaAsset::updateMetadata(
            (int) $id,
            $title !== '' ? $title : null,
            $altText !== '' ? $altText : null
        );
        header('Location: /admin/media');
        exit;
    }

    public static function assetTrash(string $id): void
    {
        admin_check();
        MediaAsset::softDelete((int) $id);
        header('Location: /admin/media');
        exit;
    }

    public static function assetDestroy(string $id): void
    {
        admin_check();
        MediaAsset::hardDelete((int) $id);
        header('Location: /admin/media');
        exit;
    }

    public static function updateFile(string $id): void
    {
        admin_check();
        if (!MediaFile::supportsAltText() && !MediaFile::supportsTitle()) {
            header('Location: /admin/media?warning=' . urlencode('native-media-metadata-unavailable'));
            exit;
        }
        $title = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 255);
        $altText = mb_substr(trim((string) ($_POST['alt_text'] ?? '')), 0, 500);
        MediaFile::updateMetadata(
            (int) $id,
            $title !== '' ? $title : null,
            $altText !== '' ? $altText : null
        );
        header('Location: /admin/media');
        exit;
    }
}
