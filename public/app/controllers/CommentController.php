<?php

declare(strict_types=1);

class CommentController
{
    public static function update(string $id): void
    {
        header('Content-Type: application/json');
        $comment = self::editableCommentFromRoute($id);

        $content = trim((string) ($_POST['content'] ?? ''));
        if ($content === '' || mb_strlen($content) > 500) {
            http_response_code(422);
            echo json_encode(['error' => 'Comment must be 1–500 characters.']);
            exit;
        }

        try {
            Comment::updateContent((int) $comment['id'], $content);
            $updated = Comment::find((int) $comment['id']);
            if (!$updated || !empty($updated['deleted_at'])) {
                throw new RuntimeException('Updated comment could not be reloaded.');
            }
        } catch (Throwable) {
            http_response_code(500);
            echo json_encode(['error' => 'Could not update comment.']);
            exit;
        }

        echo json_encode([
            'ok' => true,
            'comment' => Comment::toApiPayload($updated),
        ]);
        exit;
    }

    public static function delete(string $id): void
    {
        header('Content-Type: application/json');
        $comment = self::editableCommentFromRoute($id);

        try {
            Comment::softDelete((int) $comment['id']);
        } catch (Throwable) {
            http_response_code(500);
            echo json_encode(['error' => 'Could not delete comment.']);
            exit;
        }

        echo json_encode(['ok' => true]);
        exit;
    }

    private static function editableCommentFromRoute(string $id): array
    {
        if (!ctype_digit($id)) {
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
            exit;
        }

        if (!user_logged_in()) {
            http_response_code(401);
            echo json_encode(['error' => 'Sign in to manage comments.']);
            exit;
        }

        $comment = Comment::find((int) $id);
        if (!$comment || !empty($comment['deleted_at'])) {
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
            exit;
        }

        if (!comment_belongs_to_current_actor($comment)) {
            http_response_code(403);
            echo json_encode(['error' => 'You can only manage your own comments.']);
            exit;
        }

        return $comment;
    }
}
