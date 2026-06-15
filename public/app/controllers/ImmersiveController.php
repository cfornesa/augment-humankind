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
                $hydrated[] = ['type' => 'art_piece', 'piece' => $piece, 'version' => $piece['current_version'] ?? null];
            } elseif ($type === 'media_asset') {
                $hydrated[] = ['type' => 'media_asset', 'media' => MediaAsset::find($id)];
            }
        }
        return $hydrated;
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
