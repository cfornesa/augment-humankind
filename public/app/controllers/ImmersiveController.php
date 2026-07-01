<?php

declare(strict_types=1);

class ImmersiveController
{
    public static function piece(string $id): void
    {
        $data = EmbedController::loadPieceVersion((int) $id, isset($_GET['version']) ? (int) $_GET['version'] : null);
        if ($data === null) {
            self::notFound();
        }

        $piece = $data['piece'];
        $version = $data['version'];
        $adminEditUrl = self::pieceAdminEditUrl($piece, $version);
        $pageTitle = (($piece['title'] ?? '') ?: 'Art Piece') . ' | Immersive';
        header('Content-Type: text/html; charset=utf-8');
        
        require dirname(__DIR__) . '/views/immersive/piece.php';
        exit;
    }

    public static function collection(string $slug): void
    {
        $collection = PlatformCollection::findBySlug($slug);
        if (!$collection) {
            self::notFound();
        }

        if (!empty($collection['iframe_code'])) {
            $pageTitle = (($collection['name'] ?? '') ?: 'Collection') . ' | Immersive';
            header('Content-Type: text/html; charset=utf-8');
            ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<style>
html, body {
  margin: 0;
  padding: 0;
  width: 100%;
  height: 100%;
  overflow: hidden;
  background: #000;
}
iframe {
  width: 100%;
  height: 100%;
  border: none;
  display: block;
}
</style>
</head>
<body>
<?= $collection['iframe_code'] ?>
</body>
</html>
            <?php
            exit;
        }

        $items = self::hydrateItems($collection['items'] ?? []);
        $adminEditUrl = self::collectionAdminEditUrl($collection);
        $rows = isset($collection['rows']) && (int) $collection['rows'] > 0 ? (int) $collection['rows'] : 1;
        $cols = isset($collection['cols']) && (int) $collection['cols'] > 0 ? (int) $collection['cols'] : (empty($items) ? 1 : count($items));
        
        $pageTitle = (($collection['name'] ?? '') ?: 'Collection') . ' | Immersive';
        header('Content-Type: text/html; charset=utf-8');

        require dirname(__DIR__) . '/views/immersive/collection.php';
        exit;
    }

    public static function redirectCollection(string $slug): void
    {
        header('Location: /immersive/collections/' . $slug, true, 301);
        exit;
    }

    public static function image(string $encodedRef): void
    {
        $imageSrc = self::decodeImageRef($encodedRef);
        if ($imageSrc === null) {
            self::notFound();
        }

        $title = trim((string) ($_GET['title'] ?? ''));
        $alt = trim((string) ($_GET['alt'] ?? ''));
        $caption = trim((string) ($_GET['caption'] ?? ''));
        $description = trim((string) ($_GET['description'] ?? ''));
        $managedImageMeta = self::managedImageMetadata($imageSrc);
        if ($managedImageMeta !== null) {
            $resolvedTitle = trim((string) ($managedImageMeta['title'] ?? ''));
            $resolvedAlt = trim((string) ($managedImageMeta['alt'] ?? ''));

            if (
                $resolvedTitle !== ''
                && ($title === '' || self::sameLooseText($title, $description) || self::sameLooseText($title, $alt))
            ) {
                $title = $resolvedTitle;
            }

            if ($alt === '' && $resolvedAlt !== '') {
                $alt = $resolvedAlt;
            }
        }
        $imageAdminEditUrl = self::imageAdminEditUrl($imageSrc);
        $pageTitle = ($title !== '' ? $title : ($alt !== '' ? $alt : 'Image')) . ' | Immersive';

        header('Content-Type: text/html; charset=utf-8');
        require dirname(__DIR__) . '/views/immersive/image.php';
        exit;
    }

    private static function hydrateItems(array $items): array
    {
        $hydrated = [];
        foreach ($items as $item) {
            $type = (string) ($item['item_type'] ?? '');
            $id = (int) ($item['item_id'] ?? 0);
            if ($type === 'art_piece') {
                $piece = PlatformArtPiece::find($id);
                $hydrated[] = [
                    'type' => 'art_piece',
                    'piece' => $piece,
                    'version' => $piece['current_version'] ?? null,
                    'admin_edit_url' => self::platformPieceAdminEditUrl($piece),
                ];
            } elseif ($type === 'media_asset') {
                $media = MediaAsset::find($id);
                $hydrated[] = [
                    'type' => 'media_asset',
                    'media' => $media,
                    'admin_edit_url' => self::mediaAssetAdminEditUrl($media),
                ];
            }
        }
        return $hydrated;
    }

    private static function pieceAdminEditUrl(array $piece, array $version): ?string
    {
        if (!self::canShowAdminControls()) {
            return null;
        }

        $pieceId = (int) ($piece['id'] ?? 0);
        if ($pieceId <= 0) {
            return null;
        }

        $requestedVersionId = isset($_GET['version']) ? (int) $_GET['version'] : 0;
        if ($requestedVersionId > 0) {
            $versionId = (int) ($version['id'] ?? 0);
            if ($versionId > 0) {
                return '/admin/pieces/' . $pieceId . '/versions/' . $versionId . '/edit';
            }
        }

        return '/admin/pieces/' . $pieceId . '/edit';
    }

    private static function collectionAdminEditUrl(array $collection): ?string
    {
        if (!self::canShowAdminControls()) {
            return null;
        }

        $collectionId = (int) ($collection['id'] ?? 0);
        if ($collectionId <= 0) {
            return null;
        }

        return '/admin/platform-collections/' . $collectionId . '/edit';
    }

    private static function platformPieceAdminEditUrl(array|false|null $piece): ?string
    {
        if (!self::canShowAdminControls() || empty($piece)) {
            return null;
        }

        $pieceId = (int) ($piece['id'] ?? 0);
        if ($pieceId <= 0) {
            return null;
        }

        return '/admin/pieces/' . $pieceId . '/edit';
    }

    private static function mediaAssetAdminEditUrl(array|false|null $media): ?string
    {
        if (!self::canShowAdminControls() || empty($media)) {
            return null;
        }

        $mediaId = (int) ($media['id'] ?? 0);
        if ($mediaId <= 0) {
            return null;
        }

        return '/admin/media?open=asset-' . $mediaId;
    }

    private static function imageAdminEditUrl(string $imageSrc): ?string
    {
        if (!self::canShowAdminControls()) {
            return null;
        }

        $path = self::sameOriginImagePath($imageSrc);
        if ($path === null) {
            return null;
        }

        if (preg_match('#^/(?:image|media)/([0-9]+)$#', $path, $matches)) {
            return '/admin/media?open=file-' . (int) $matches[1];
        }
        if (preg_match('#^/api/media-assets/([0-9]+)$#', $path, $matches)) {
            return '/admin/media?open=asset-' . (int) $matches[1];
        }

        return null;
    }

    private static function managedImageMetadata(string $imageSrc): ?array
    {
        $path = self::sameOriginImagePath($imageSrc);
        if ($path === null) {
            return null;
        }

        if (preg_match('#^/(?:image|media)/([0-9]+)$#', $path, $matches)) {
            $file = MediaFile::find((int) $matches[1]);
            if (!$file) {
                return null;
            }
            return [
                'title' => (string) ($file['title'] ?? ''),
                'alt' => (string) ($file['alt_text'] ?? ''),
            ];
        }

        if (preg_match('#^/api/media-assets/([0-9]+)$#', $path, $matches)) {
            $asset = MediaAsset::find((int) $matches[1]);
            if (!$asset) {
                return null;
            }
            return [
                'title' => (string) ($asset['title'] ?? ''),
                'alt' => (string) ($asset['alt_text'] ?? ''),
            ];
        }

        return null;
    }

    private static function sameOriginImagePath(string $imageSrc): ?string
    {
        $trimmed = trim($imageSrc);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $trimmed)) {
            $host = strtolower((string) parse_url($trimmed, PHP_URL_HOST));
            $requestHost = strtolower((string) parse_url((($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')), PHP_URL_HOST));
            if ($host === '' || $requestHost === '' || $host !== $requestHost) {
                return null;
            }
        } elseif (!str_starts_with($trimmed, '/')) {
            return null;
        }

        $path = (string) parse_url($trimmed, PHP_URL_PATH);
        return $path !== '' ? $path : null;
    }

    private static function sameLooseText(string $left, string $right): bool
    {
        $normalizedLeft = mb_strtolower(trim($left));
        $normalizedRight = mb_strtolower(trim($right));
        return $normalizedLeft !== '' && $normalizedRight !== '' && $normalizedLeft === $normalizedRight;
    }

    private static function canShowAdminControls(): bool
    {
        return admin_identity() !== null
            && (!isset($_GET['embed']) || $_GET['embed'] !== '1')
            && (!isset($_GET['static']) || $_GET['static'] !== '1');
    }

    private static function decodeImageRef(string $encodedRef): ?string
    {
        $normalized = strtr($encodedRef, '-_', '+/');
        $padding = strlen($normalized) % 4;
        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);
        if (!is_string($decoded) || trim($decoded) === '') {
            return null;
        }
        $decoded = trim($decoded);

        if (preg_match('#^(javascript|data|vbscript):#i', $decoded)) {
            return null;
        }
        if (str_starts_with($decoded, '//')) {
            return null;
        }
        if (preg_match('#^https?://#i', $decoded)) {
            return $decoded;
        }
        if ($decoded[0] !== '/') {
            return '/' . ltrim($decoded, '/');
        }
        return $decoded;
    }

    private static function notFound(): never
    {
        http_response_code(404);
        require dirname(__DIR__) . '/views/404.php';
        exit;
    }
}
