<?php

declare(strict_types=1);

class CronController
{
    public static function publishPosts(): void
    {
        self::requireCronSecret('cron_publish_posts');

        $startedAt = microtime(true);
        $subjectHash = operational_request_subject_hash('cron_publish_posts');

        try {
            $published = BlogPost::publishDuePosts();
            if ($published) {
                BlogAdminController::processPendingSyndications($published);
            }

            audit_log_event('cron', 'cron_publish_posts', 'success', [
                'subject_hash' => $subjectHash,
                'http_status' => 200,
                'metadata' => [
                    'posts_published' => count($published),
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ],
            ]);

            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['posts_published' => count($published), 'ids' => $published], JSON_PRETTY_PRINT);
            exit;
        } catch (Throwable $e) {
            audit_log_event('cron', 'cron_publish_posts', 'error', [
                'subject_hash' => $subjectHash,
                'http_status' => 500,
                'metadata' => [
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'error' => $e->getMessage(),
                ],
            ]);

            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Failed to publish scheduled posts.']);
            exit;
        }
    }

    public static function refreshFeeds(): void
    {
        self::requireCronSecret('cron_refresh_feeds');

        $startedAt = microtime(true);
        $subjectHash = operational_request_subject_hash('cron_refresh_feeds');

        // Feed ingest creates new posts; content-safe blog gating skips it.
        // (Scheduled publishing of existing posts stays active regardless.)
        if (!feature_enabled('blog')) {
            audit_log_event('cron', 'cron_refresh_feeds', 'success', [
                'subject_hash' => $subjectHash,
                'http_status' => 200,
                'metadata' => ['skipped' => 'blog disabled'],
            ]);

            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'skipped' => 'blog disabled'], JSON_PRETTY_PRINT);
            exit;
        }

        try {
            $sourceCount = 0;
            $importedCount = 0;
            foreach (FeedSource::allEnabled() as $source) {
                if (!FeedSource::isDue($source)) {
                    continue;
                }
                $sourceCount++;
                $importedCount += count(ingest_feed((int) $source['id']));
            }

            audit_log_event('cron', 'cron_refresh_feeds', 'success', [
                'subject_hash' => $subjectHash,
                'http_status' => 200,
                'metadata' => [
                    'sources_processed' => $sourceCount,
                    'items_imported' => $importedCount,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ],
            ]);

            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode([
                'sources_processed' => $sourceCount,
                'items_imported' => $importedCount,
            ], JSON_PRETTY_PRINT);
            exit;
        } catch (Throwable $e) {
            audit_log_event('cron', 'cron_refresh_feeds', 'error', [
                'subject_hash' => $subjectHash,
                'http_status' => 500,
                'metadata' => [
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'error' => $e->getMessage(),
                ],
            ]);

            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Failed to refresh feeds.']);
            exit;
        }
    }

    private static function requireCronSecret(string $scope): void
    {
        $secret = $_ENV['CRON_SECRET'] ?? null;
        if (!is_string($secret) || $secret === '') {
            $fromGetenv = getenv('CRON_SECRET');
            $secret = $fromGetenv !== false ? (string) $fromGetenv : '';
        }

        $provided = $_SERVER['HTTP_X_CRON_SECRET'] ?? '';
        $provided = is_string($provided) ? $provided : '';
        if ($secret !== '' && hash_equals($secret, $provided)) {
            return;
        }

        audit_log_event('cron', $scope, 'unauthorized', [
            'subject_hash' => operational_request_subject_hash($scope),
            'http_status' => 401,
            'metadata' => ['reason' => 'cron_secret_mismatch'],
        ]);

        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}
