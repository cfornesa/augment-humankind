<?php

declare(strict_types=1);

class EmbedController
{
    public static function post(string $id): void
    {
        $post = BlogPost::findPublished((int) $id);
        if (!$post) {
            self::notFound();
        }

        $title = trim((string) (($post['title'] ?? '') ?: 'Untitled post'));
        $author = trim((string) (($post['author_name'] ?? '') ?: seo_author_name()));
        $date = !empty($post['created_at']) ? date('M j, Y', strtotime((string) $post['created_at']) ?: time()) : '';
        $content = (string) ($post['content'] ?? '');
        $contentHtml = (($post['content_format'] ?? 'plain') === 'html')
            ? $content
            : nl2br(e($content));
        $canonical = '/blog/posts/' . (int) $post['id'];

        header('Content-Type: text/html; charset=utf-8');
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<style>
html,body{margin:0;background:#fff;color:#161616;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}
.post-embed{box-sizing:border-box;min-height:100vh;border:1px solid #e5e2db;background:#fff;padding:1.25rem;}
.post-meta{display:flex;flex-wrap:wrap;gap:.35rem .7rem;align-items:center;color:#666;font-size:.82rem;margin-bottom:1rem;}
.post-title{font-size:1.1rem;line-height:1.25;margin:0 0 .9rem;}
.post-content{font-size:.96rem;line-height:1.55;overflow-wrap:anywhere;}
.post-content img{max-width:100%;height:auto;}
.post-footer{border-top:1px solid #eee;margin-top:1rem;padding-top:.75rem;font-size:.76rem;text-transform:uppercase;letter-spacing:.04em;}
.post-footer a{color:#4a5f35;text-decoration:none;font-weight:700;}
.post-footer a:hover{text-decoration:underline;}
</style>
</head>
<body>
<article class="post-embed">
  <header>
    <div class="post-meta">
      <strong><?= e($author) ?></strong>
      <?php if ($date !== ''): ?><span><?= e($date) ?></span><?php endif; ?>
    </div>
    <h1 class="post-title"><?= e($title) ?></h1>
  </header>
  <div class="post-content"><?= $contentHtml ?></div>
  <footer class="post-footer"><a href="<?= e($canonical) ?>" target="_blank" rel="noopener">View post</a></footer>
</article>
<script src="/embed.js" defer></script>
</body>
</html>
<?php
        exit;
    }

    public static function piece(string $id): void
    {
        $data = self::loadPieceVersion((int) $id, self::requestedVersionId());
        if ($data === null) {
            self::notFound();
        }

        header('Content-Type: text/html; charset=utf-8');
        $piece = $data['piece'];
        $version = $data['version'];
        $title = htmlspecialchars((string) ($piece['title'] ?? 'Art piece'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $pieceStageReturnTo = '/embed/pieces/' . (int) $piece['id']
            . (!empty($version['id']) ? '?version=' . (int) $version['id'] : '');
        $stylesVersion = (int) @filemtime(dirname(__DIR__, 2) . '/assets/styles.css');
        $downloadVersion = (int) @filemtime(dirname(__DIR__, 2) . '/assets/js/public-piece-download.js');
        $fullscreenVersion = (int) @filemtime(dirname(__DIR__, 2) . '/assets/js/piece-fullscreen.js');
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= $title ?></title>
<link rel="stylesheet" href="/assets/styles.css?v=<?= $stylesVersion ?>">
<style>
html,body{margin:0;width:100%;height:100%;min-height:0;overflow:hidden;background:#000}
.piece-light-embed .piece-stage,.piece-light-embed [data-piece-download-root],.piece-light-embed .piece-canvas-container{width:100%;height:100%;min-height:0;margin:0;padding:0}
.piece-light-embed .piece-stage{max-width:none}
.piece-light-embed .piece-canvas-container{aspect-ratio:auto;border-radius:0}
.piece-light-embed .piece-canvas-container>iframe{width:100%!important;height:100%!important;min-height:0!important}
</style>
</head>
<body class="piece-light-embed">
<?php require dirname(__DIR__) . '/views/partials/piece-stage.php'; ?>
<script src="/assets/js/public-piece-download.js?v=<?= $downloadVersion ?>"></script>
<script src="/assets/js/piece-fullscreen.js?v=<?= $fullscreenVersion ?>"></script>
</body>
</html>
<?php
        exit;
    }

    public static function pieceData(string $id): void
    {
        $data = self::loadPieceVersion((int) $id, self::requestedVersionId());
        if ($data === null) {
            self::json(['error' => 'Not found'], 404);
        }

        self::json([
            'id' => (int) $data['piece']['id'],
            'title' => $data['piece']['title'] ?? '',
            'engine' => $data['version']['engine'] ?? $data['piece']['engine'] ?? 'p5',
            'currentVersionId' => (int) ($data['piece']['current_version_id'] ?? 0),
            'version' => $data['version'],
            'generatedCode' => $data['version']['generated_code'] ?? '',
            'htmlCode' => $data['version']['html_code'] ?? '',
            'cssCode' => $data['version']['css_code'] ?? '',
        ]);
    }

    public static function loadPieceVersion(int $pieceId, ?int $versionId = null): ?array
    {
        $piece = PlatformArtPiece::find($pieceId);
        if (!$piece) {
            return null;
        }

        $version = null;
        if ($versionId !== null) {
            $candidate = PlatformArtPieceVersion::find($versionId);
            if (!$candidate || (int) $candidate['art_piece_id'] !== (int) $piece['id']) {
                return null;
            }
            $version = $candidate;
        } else {
            $version = $piece['current_version'] ?? null;
        }

        if (!$version && !empty($piece['versions'])) {
            $version = $piece['versions'][0];
        }
        if (!$version) {
            return null;
        }

        return ['piece' => $piece, 'version' => $version];
    }

    private static function requestedVersionId(): ?int
    {
        $raw = $_GET['version'] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }
        $id = (int) $raw;
        return $id > 0 ? $id : null;
    }

    private static function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function notFound(): never
    {
        http_response_code(404);
        require dirname(__DIR__) . '/views/404.php';
        exit;
    }
}
