<?php

declare(strict_types=1);

class MediaAdminController
{
    private static function wantsJson(): bool
    {
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        return str_contains($accept, 'application/json') || (string) ($_POST['ajax'] ?? '') === '1';
    }

    private static function nativePosterUrl(array $file): ?string
    {
        $posterId = (int) ($file['poster_media_file_id'] ?? 0);
        if ($posterId <= 0 || !MediaFile::isActiveOfKind($posterId, 'image')) {
            return null;
        }
        return '/image/' . $posterId;
    }

    private static function nativeMediaCard(array $file): array
    {
        $id = (int) $file['id'];
        $mime = (string) ($file['mime_type'] ?? '');
        $isVideo = str_starts_with($mime, 'video/');
        $isHtml = $mime === 'text/html' || str_starts_with($mime, 'iframe');
        $isModel = str_starts_with($mime, 'model/');
        $posterUrl = $isVideo ? self::nativePosterUrl($file) : null;

        return $file + [
            'source' => 'file',
            'preview' => $isVideo ? $posterUrl : ($isHtml || $isModel ? null : '/image/' . $id),
            'direct_url' => '/media/' . $id,
            'label' => trim((string) ($file['title'] ?? '')) !== '' ? (string) $file['title'] : ('Asset #' . $id),
            'alt_text_supported' => MediaFile::supportsAltText(),
            'title_supported' => MediaFile::supportsTitle(),
            'status' => $file['status'] ?? 'ready',
            'poster_media_file_id' => $file['poster_media_file_id'] ?? null,
            'poster_url' => $posterUrl,
        ];
    }

    private static function nativeLibraryItem(array $file): array
    {
        $mime = (string) ($file['mime_type'] ?? '');
        if (str_starts_with($mime, 'video/')) {
            $kind = 'video';
        } elseif ($mime === 'text/html' || str_starts_with($mime, 'iframe')) {
            $kind = 'iframe';
        } elseif (str_starts_with($mime, 'model/')) {
            $kind = 'model';
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
            'status' => $file['status'] ?? 'ready',
            'poster_media_file_id' => $file['poster_media_file_id'] ?? null,
            'poster_url' => self::nativePosterUrl($file),
        ];
    }

    private static function assetLibraryItem(array $asset): array
    {
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
        $asset['status'] = 'ready';
        $asset['poster_media_file_id'] = null;
        $asset['poster_url'] = null;

        return $asset;
    }

    private static function nativeResponseItem(int $id): array
    {
        $row = MediaFile::find($id);
        if (!$row) {
            throw new RuntimeException('Media file not found.');
        }
        return self::nativeLibraryItem($row);
    }

    public static function index(): void
    {
        admin_check();
        $nativeAltTextSupported = MediaFile::supportsAltText();
        $nativeTitleSupported = MediaFile::supportsTitle();
        $warning = $_GET['warning'] ?? '';

        $files = array_map([self::class, 'nativeMediaCard'], MediaFile::all());

        $assets = array_map(static function (array $asset): array {
            $id = (int) $asset['id'];

            return [
                'id' => 'asset-' . $id,
                'source' => 'asset',
                'mime_type' => (string) ($asset['mime_type'] ?? ''),
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

        $files = array_map([self::class, 'nativeLibraryItem'], MediaFile::ready());
        $assets = array_map([self::class, 'assetLibraryItem'], MediaAsset::all());

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
            $asset = upload_media_auto($_FILES['media_file'], [
                'status' => 'draft',
                'confirmed_at' => null,
                'poster_media_file_id' => null,
                'alt_text' => null,
            ]);
            echo json_encode(['ok' => true, 'asset' => self::nativeResponseItem((int) $asset['id'])]);
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
        $id = MediaFile::create($data, $mime, $name, [
            'status' => 'draft',
            'confirmed_at' => null,
            'poster_media_file_id' => null,
            'alt_text' => null,
        ]);
        echo json_encode(['ok' => true, 'asset' => self::nativeResponseItem($id)]);
        exit;
    }

    public static function confirmFile(string $id): void
    {
        admin_check();
        header('Content-Type: application/json');

        $fileId = (int) $id;
        $row = MediaFile::find($fileId);
        if (!$row || $row['deleted_at'] !== null) {
            echo json_encode(['ok' => false, 'error' => 'Asset not found.']);
            exit;
        }
        if (($row['status'] ?? 'ready') !== 'draft') {
            echo json_encode(['ok' => false, 'error' => 'Only draft assets can be confirmed here.']);
            exit;
        }

        $title = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 255);
        $altText = mb_substr(trim((string) ($_POST['alt_text'] ?? '')), 0, 500);
        if ($altText === '') {
            echo json_encode(['ok' => false, 'error' => 'Add a description before confirming this asset.']);
            exit;
        }

        $posterMediaId = (int) ($_POST['poster_media_file_id'] ?? 0);
        $posterMediaId = $posterMediaId > 0 ? $posterMediaId : null;
        if (str_starts_with((string) ($row['mime_type'] ?? ''), 'video/')) {
            if ($posterMediaId !== null && !MediaFile::supportsPosterMediaFileId()) {
                echo json_encode(['ok' => false, 'error' => 'Video posters aren\'t supported until the media schema migration is applied.']);
                exit;
            }
            if ($posterMediaId !== null && !MediaFile::isActiveOfKind($posterMediaId, 'image')) {
                echo json_encode(['ok' => false, 'error' => 'Video posters must be image assets.']);
                exit;
            }
        } else {
            $posterMediaId = null;
        }

        try {
            MediaFile::confirmDraft(
                $fileId,
                $title !== '' ? $title : null,
                $altText,
                $posterMediaId
            );
            echo json_encode(['ok' => true, 'asset' => self::nativeResponseItem($fileId)]);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'error' => 'Could not confirm the asset.']);
        }
        exit;
    }

    public static function discardDraft(string $id): void
    {
        admin_check();
        header('Content-Type: application/json');

        $fileId = (int) $id;
        $row = MediaFile::find($fileId);
        if (!$row || $row['deleted_at'] !== null) {
            echo json_encode(['ok' => false, 'error' => 'Draft not found.']);
            exit;
        }
        if (($row['status'] ?? 'ready') !== 'draft') {
            echo json_encode(['ok' => false, 'error' => 'Only draft assets can be discarded.']);
            exit;
        }

        MediaFile::discardDraft($fileId);
        echo json_encode(['ok' => true]);
        exit;
    }

    public static function uploadPoster(): void
    {
        admin_check();
        header('Content-Type: application/json');

        if (empty($_FILES['media_file']['name'])) {
            echo json_encode(['ok' => false, 'error' => 'No poster image provided.']);
            exit;
        }

        try {
            $asset = upload_media($_FILES['media_file'], ALLOWED_IMAGE_MIME, 8 * 1024 * 1024, 'Poster image', [
                'status' => 'ready',
                'confirmed_at' => date('Y-m-d H:i:s'),
                'poster_media_file_id' => null,
                'alt_text' => null,
            ]);
            echo json_encode(['ok' => true, 'asset' => self::nativeResponseItem((int) $asset['id'])]);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
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
        if (self::wantsJson()) {
            header('Content-Type: application/json');
            $asset = MediaAsset::find((int) $id);
            echo json_encode(['ok' => true, 'asset' => $asset ? self::assetLibraryItem($asset) : null]);
            exit;
        }
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
            if (self::wantsJson()) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => 'Native media metadata is unavailable on this database.']);
                exit;
            }
            header('Location: /admin/media?warning=' . urlencode('native-media-metadata-unavailable'));
            exit;
        }
        $fileId = (int) $id;
        $title = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 255);
        $altText = mb_substr(trim((string) ($_POST['alt_text'] ?? '')), 0, 500);
        $posterMediaId = (int) ($_POST['poster_media_file_id'] ?? 0);
        $posterMediaId = $posterMediaId > 0 ? $posterMediaId : null;

        $row = MediaFile::find($fileId);
        if (!$row || $row['deleted_at'] !== null) {
            if (self::wantsJson()) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => 'Asset not found.']);
                exit;
            }
            header('Location: /admin/media');
            exit;
        }
        if (str_starts_with((string) ($row['mime_type'] ?? ''), 'video/')) {
            if ($posterMediaId !== null && !MediaFile::supportsPosterMediaFileId()) {
                if (self::wantsJson()) {
                    header('Content-Type: application/json');
                    echo json_encode(['ok' => false, 'error' => 'Video posters aren\'t supported until the media schema migration is applied.']);
                    exit;
                }
                header('Location: /admin/media?warning=' . urlencode('media-poster-unavailable'));
                exit;
            }
            if ($posterMediaId !== null && !MediaFile::isActiveOfKind($posterMediaId, 'image')) {
                if (self::wantsJson()) {
                    header('Content-Type: application/json');
                    echo json_encode(['ok' => false, 'error' => 'Video posters must be image assets.']);
                    exit;
                }
                header('Location: /admin/media');
                exit;
            }
        } else {
            $posterMediaId = null;
        }

        MediaFile::updateMetadata(
            $fileId,
            $title !== '' ? $title : null,
            $altText !== '' ? $altText : null
        );
        MediaFile::updatePoster($fileId, $posterMediaId);
        if (self::wantsJson()) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'asset' => self::nativeResponseItem($fileId)]);
            exit;
        }
        header('Location: /admin/media');
        exit;
    }
}
