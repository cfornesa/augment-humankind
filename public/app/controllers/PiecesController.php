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

        $comments = (int)($piece['comments_enabled'] ?? 0)
            ? Comment::commentsFor('art_piece', (int) $piece['id'])
            : [];

        require dirname(__DIR__) . '/views/pieces/show.php';
    }

    public static function commentsJson(string $id): void
    {
        header('Content-Type: application/json');
        if (!ctype_digit($id)) {
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
            exit;
        }
        $comments = Comment::commentsFor('art_piece', (int) $id);
        echo json_encode(array_map(static fn (array $c): array => [
            'author_name' => (string) $c['author_name'],
            'content'     => (string) $c['content'],
            'created_at'  => (string) $c['created_at'],
        ], $comments));
        exit;
    }

    public static function commentSubmit(string $id): void
    {
        header('Content-Type: application/json');
        if (!ctype_digit($id)) {
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
            exit;
        }
        $piece = PlatformArtPiece::find((int) $id);
        if (!$piece || !(int)($piece['comments_enabled'] ?? 0)) {
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
            exit;
        }

        $hp = trim((string) ($_POST['hp_field'] ?? ''));
        if ($hp !== '') {
            echo json_encode(['ok' => true]);
            exit;
        }

        $content = trim((string) ($_POST['content'] ?? ''));
        if ($content === '' || mb_strlen($content) > 500) {
            http_response_code(422);
            echo json_encode(['error' => 'Comment must be 1–500 characters.']);
            exit;
        }

        $authorName = mb_substr(trim((string) ($_POST['author_name'] ?? '')), 0, 80);
        if ($authorName === '') {
            $authorName = 'Anonymous';
        }

        try {
            Comment::insertComment('art_piece', (int) $id, $authorName, $content);
        } catch (Throwable) {
            http_response_code(500);
            echo json_encode(['error' => 'Could not save comment.']);
            exit;
        }

        echo json_encode(['ok' => true, 'author_name' => $authorName, 'content' => $content]);
        exit;
    }

    private static function notFound(): never
    {
        http_response_code(404);
        require dirname(__DIR__) . '/views/404.php';
        exit;
    }
}
