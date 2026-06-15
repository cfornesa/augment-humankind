<?php

declare(strict_types=1);

class FeedSourcesAdminController
{
    public static function index(): void
    {
        admin_check();
        $sources = FeedSource::all();
        $pending = FeedSource::pendingImports();
        require dirname(__DIR__, 2) . '/views/admin/feed-sources/index.php';
    }

    public static function create(): void
    {
        admin_check();
        $source = null;
        $error = null;
        require dirname(__DIR__, 2) . '/views/admin/feed-sources/form.php';
    }

    public static function store(): void
    {
        admin_check();

        try {
            $data = self::resolveSourceData();
            FeedSource::create($data);
            header('Location: /admin/feed-sources');
        } catch (Throwable $e) {
            $source = self::draftSourceFromPost();
            $error = $e->getMessage();
            require dirname(__DIR__, 2) . '/views/admin/feed-sources/form.php';
        }
        exit;
    }

    public static function edit(string $id): void
    {
        admin_check();
        $source = FeedSource::find((int) $id);
        if (!$source) {
            header('Location: /admin/feed-sources');
            exit;
        }
        $error = null;
        require dirname(__DIR__, 2) . '/views/admin/feed-sources/form.php';
    }

    public static function update(string $id): void
    {
        admin_check();
        $existing = FeedSource::find((int) $id);
        if (!$existing) {
            header('Location: /admin/feed-sources');
            exit;
        }

        try {
            $data = self::resolveSourceData();
            FeedSource::update((int) $id, $data);
            header('Location: /admin/feed-sources');
        } catch (Throwable $e) {
            $source = self::draftSourceFromPost((int) $id);
            $error = $e->getMessage();
            require dirname(__DIR__, 2) . '/views/admin/feed-sources/form.php';
        }
        exit;
    }

    public static function delete(string $id): void
    {
        admin_check();
        FeedSource::delete((int) $id);
        header('Location: /admin/feed-sources');
        exit;
    }

    public static function ingest(string $id): void
    {
        admin_check();

        try {
            $newIds = ingest_feed((int) $id);
            $count = count($newIds);
            if ($count > 0) {
                FeedSource::incrementItemsImported((int) $id, $count);
            }
            header('Location: /admin/feed-sources?tab=pending');
        } catch (Throwable $e) {
            header('Location: /admin/feed-sources?error=' . urlencode($e->getMessage()));
        }
        exit;
    }

    public static function approveImport(): void
    {
        admin_check();

        $seenId = (int) ($_POST['seen_id'] ?? 0);
        $sourceId = (int) ($_POST['source_id'] ?? 0);

        try {
            $seen = FeedSource::importItem($seenId, $sourceId);
            if (!$seen) {
                header('Location: /admin/feed-sources?tab=pending');
                exit;
            }

            $postId = BlogPost::create([
                'author_id' => 'feed-source-' . $sourceId,
                'author_user_id' => null,
                'author_name' => $seen['author_name'] ?: ($seen['source_author_name'] ?: ($seen['source_name'] ?: 'Feed Import')),
                'author_image_url' => $seen['source_image_url'] ?? null,
                'title' => $seen['title'] ?: ('Imported: ' . substr($seen['guid_hash'], 0, 12) . '...'),
                'content' => $seen['content'] ?: '<p>Imported from feed.</p>',
                'content_text' => $seen['content_text'] ?: text_from_feed_html((string) ($seen['content'] ?? 'Imported from feed.')),
                'content_format' => 'html',
                'status' => 'draft',
                'scheduled_at' => null,
                'featured_image_url' => null,
                'source_feed_id' => $sourceId,
                'source_guid' => $seen['guid'] ?? $seen['guid_hash'],
                'source_canonical_url' => $seen['source_url'] ?? null,
            ]);

            FeedSource::markAsProcessed($seenId, $postId);
            header('Location: /admin/feed-sources?tab=pending');
        } catch (Throwable $e) {
            header('Location: /admin/feed-sources?tab=pending&error=' . urlencode($e->getMessage()));
        }
        exit;
    }

    public static function rejectImport(): void
    {
        admin_check();

        $seenId = (int) ($_POST['seen_id'] ?? 0);
        FeedSource::rejectImport($seenId);
        header('Location: /admin/feed-sources?tab=pending');
        exit;
    }

    private static function resolveSourceData(): array
    {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            throw new InvalidArgumentException('Name is required.');
        }

        $feedUrl = trim($_POST['feed_url'] ?? '');
        if ($feedUrl === '' || !filter_var($feedUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('A valid feed URL is required.');
        }

        $cadence = $_POST['cadence'] ?? 'daily';
        if (!in_array($cadence, ['hourly', 'daily', 'weekly', 'monthly'], true)) {
            $cadence = 'daily';
        }

        return [
            'name' => $name,
            'author_name' => trim($_POST['author_name'] ?? '') ?: null,
            'username' => trim($_POST['username'] ?? '') ?: null,
            'bio' => trim($_POST['bio'] ?? '') ?: null,
            'image_url' => trim($_POST['image_url'] ?? '') ?: null,
            'site_url' => trim($_POST['site_url'] ?? '') ?: null,
            'feed_url' => $feedUrl,
            'cadence' => $cadence,
            'enabled' => isset($_POST['enabled']) ? 1 : 0,
            'profile_photo_url' => trim($_POST['profile_photo_url'] ?? '') ?: null,
        ];
    }

    private static function draftSourceFromPost(?int $existingId = null): array
    {
        $existing = $existingId ? FeedSource::find($existingId) : null;

        return [
            'id' => $existingId,
            'name' => trim((string) ($_POST['name'] ?? ($existing['name'] ?? ''))),
            'author_name' => trim((string) ($_POST['author_name'] ?? ($existing['author_name'] ?? ''))),
            'username' => trim((string) ($_POST['username'] ?? ($existing['username'] ?? ''))),
            'bio' => trim((string) ($_POST['bio'] ?? ($existing['bio'] ?? ''))),
            'image_url' => trim((string) ($_POST['image_url'] ?? ($existing['image_url'] ?? ''))),
            'site_url' => trim((string) ($_POST['site_url'] ?? ($existing['site_url'] ?? ''))),
            'feed_url' => trim((string) ($_POST['feed_url'] ?? ($existing['feed_url'] ?? ''))),
            'cadence' => $_POST['cadence'] ?? ($existing['cadence'] ?? 'daily'),
            'enabled' => isset($_POST['enabled']) ? 1 : ($existing['enabled'] ?? 1),
            'profile_photo_url' => trim((string) ($_POST['profile_photo_url'] ?? ($existing['profile_photo_url'] ?? ''))),
        ];
    }
}
