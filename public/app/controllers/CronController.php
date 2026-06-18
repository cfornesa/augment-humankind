<?php

declare(strict_types=1);

class CronController
{
    public static function publishPosts(): void
    {
        $secret = $_ENV['CRON_SECRET'] ?? getenv('CRON_SECRET') ?? '';
        $provided = $_SERVER['HTTP_X_CRON_SECRET'] ?? '';
        if ($secret === '' || !hash_equals($secret, $provided)) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $published = BlogPost::publishDuePosts();
        if ($published) {
            BlogAdminController::processPendingSyndications($published);
        }

        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['posts_published' => count($published), 'ids' => $published], JSON_PRETTY_PRINT);
        exit;
    }
}
