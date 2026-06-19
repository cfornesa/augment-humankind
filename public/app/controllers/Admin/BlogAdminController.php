<?php

declare(strict_types=1);

class BlogAdminController
{
    public static function postsIndex(): void
    {
        admin_check();
        $publishedIds = BlogPost::publishDuePosts();
        if ($publishedIds) {
            self::processPendingSyndications($publishedIds);
        }
        $status = $_GET['status'] ?? null;
        if (!in_array($status, ['draft', 'published', 'scheduled'], true)) {
            $status = null;
        }
        $posts = BlogPost::allForAdmin($status);
        require dirname(__DIR__, 2) . '/views/admin/posts/index.php';
    }

    public static function postCreate(): void
    {
        admin_check();
        $categories = BlogCategory::all();
        $assignedCategoryIds = [];
        $post = ['status' => 'draft'];
        $sections = [];
        $error = null;
        $platformConnections = PlatformConnection::allEnabled();
        $publishedConnectionIds = [];
        require dirname(__DIR__, 2) . '/views/admin/posts/form.php';
    }

    public static function postStore(): void
    {
        admin_check();

        try {
            $data = self::resolvePostData(null);
            $postId = BlogPost::create($data);
            BlogPost::syncCategories($postId, $data['category_ids']);
            self::syncSections($postId);
            $failures = self::handleSyndication($postId, $data['status']);
            $qs = $failures ? '?syndication_error=' . urlencode(implode(' | ', $failures)) : '';
            header('Location: /admin/posts' . $qs);
        } catch (Throwable $e) {
            $categories = BlogCategory::all();
            $post = self::draftPostFromPost(null);
            $assignedCategoryIds = $post['category_ids'];
            $sections = self::sectionsFromPost();
            $platformConnections = PlatformConnection::allEnabled();
            $publishedConnectionIds = [];
            $error = $e->getMessage();
            require dirname(__DIR__, 2) . '/views/admin/posts/form.php';
        }
        exit;
    }

    public static function postEdit(string $id): void
    {
        admin_check();
        $post = BlogPost::find((int) $id);
        if (!$post) {
            header('Location: /admin/posts');
            exit;
        }
        $categories = BlogCategory::all();
        $assignedCategoryIds = array_map('intval', BlogPost::categoryIds((int) $id));
        $sections = PostSection::allForPost((int) $id);
        if (empty($sections) && !empty($post['content'])) {
            $sections = [['id' => '', 'heading' => '', 'content' => $post['content'], 'wrapper_class' => '', 'sort_order' => 0]];
        }
        $platformConnections = PlatformConnection::allEnabled();
        $publishedConnectionIds = array_map('intval', PostSyndication::syncedConnectionIdsForPost((int) $id));
        $error = null;
        require dirname(__DIR__, 2) . '/views/admin/posts/form.php';
    }

    public static function postUpdate(string $id): void
    {
        admin_check();
        $existing = BlogPost::find((int) $id);
        if (!$existing) {
            header('Location: /admin/posts');
            exit;
        }

        try {
            $data = self::resolvePostData((int) $id);
            BlogPost::update((int) $id, $data);
            BlogPost::syncCategories((int) $id, $data['category_ids']);
            self::syncSections((int) $id);
            $failures = self::handleSyndication((int) $id, $data['status']);
            $qs = $failures ? '?syndication_error=' . urlencode(implode(' | ', $failures)) : '';
            header('Location: /admin/posts' . $qs);
        } catch (Throwable $e) {
            $post = self::draftPostFromPost((int) $id);
            $categories = BlogCategory::all();
            $assignedCategoryIds = $post['category_ids'];
            $sections = self::sectionsFromPost();
            $platformConnections = PlatformConnection::allEnabled();
            $publishedConnectionIds = array_map('intval', PostSyndication::syncedConnectionIdsForPost((int) $id));
            $error = $e->getMessage();
            require dirname(__DIR__, 2) . '/views/admin/posts/form.php';
        }
        exit;
    }

    public static function postDelete(string $id): void
    {
        admin_check();
        BlogPost::softDelete((int) $id);
        header('Location: /admin/posts');
        exit;
    }

    public static function commentsIndex(): void
    {
        admin_check();
        $tab = $_GET['tab'] ?? 'comments';
        if (!in_array($tab, ['comments', 'reactions'], true)) {
            $tab = 'comments';
        }
        $comments = Comment::recent();
        $reactions = Reaction::recent();
        require dirname(__DIR__, 2) . '/views/admin/comments/index.php';
    }

    public static function commentDelete(string $id): void
    {
        admin_check();
        Comment::softDelete((int) $id);
        header('Location: /admin/comments');
        exit;
    }

    public static function reactionDelete(string $id): void
    {
        admin_check();
        Reaction::delete((int) $id);
        header('Location: /admin/comments?tab=reactions');
        exit;
    }

    private static function syncSections(int $postId): void
    {
        $incoming = $_POST['sections'] ?? [];
        $keepIds = [];

        foreach ($incoming as $i => $s) {
            if (!empty($s['_delete'])) {
                if (!empty($s['id'])) {
                    PostSection::delete((int) $s['id']);
                }
                continue;
            }

            $sectionContent = trim($s['content'] ?? '');
            if ($sectionContent === '') {
                continue;
            }

            $wc = self::sanitiseWrapperClass($s['wrapper_class'] ?? '');
            $heading = trim($s['heading'] ?? '');

            if (!empty($s['id'])) {
                PostSection::update((int) $s['id'], $heading, $sectionContent, $wc);
                $keepIds[] = (int) $s['id'];
            } else {
                $keepIds[] = PostSection::create($postId, $heading, $sectionContent, (int) $i, $wc);
            }
        }

        if ($keepIds) {
            PostSection::reorder($postId, $keepIds);
        }
    }

    private static function sectionsFromPost(): array
    {
        $raw = $_POST['sections'] ?? [];
        $result = [];
        foreach ($raw as $i => $s) {
            if (!empty($s['_delete'])) {
                continue;
            }
            $result[] = [
                'id'            => $s['id'] ?? '',
                'heading'       => trim($s['heading'] ?? ''),
                'content'       => trim($s['content'] ?? ''),
                'wrapper_class' => trim($s['wrapper_class'] ?? ''),
                'sort_order'    => (int) $i,
            ];
        }
        return $result;
    }

    private static function sanitiseWrapperClass(string $raw): ?string
    {
        $allowed = ['mission-band', 'callout', 'content-cards', 'managed-section'];
        $value = trim($raw);
        return in_array($value, $allowed, true) ? $value : null;
    }

    private static function resolvePostData(?int $existingId): array
    {
        $status = $_POST['status'] ?? 'draft';
        if (!in_array($status, ['draft', 'published', 'scheduled'], true)) {
            throw new InvalidArgumentException('Invalid status.');
        }

        $scheduledAt = null;
        if ($status === 'scheduled') {
            $raw = trim($_POST['scheduled_at'] ?? '');
            if ($raw === '') {
                throw new InvalidArgumentException('Scheduled posts require a scheduled date/time.');
            }
            $timestamp = strtotime($raw);
            if ($timestamp === false) {
                throw new InvalidArgumentException('Invalid scheduled date/time.');
            }
            $scheduledAt = date('Y-m-d H:i:s', $timestamp);
        }

        // Validate at least one section with content
        $hasSections = false;
        foreach ($_POST['sections'] ?? [] as $s) {
            if (empty($s['_delete']) && trim($s['content'] ?? '') !== '') {
                $hasSections = true;
                break;
            }
        }
        if (!$hasSections) {
            throw new InvalidArgumentException('At least one section with content is required.');
        }

        $title = trim($_POST['title'] ?? '');
        $featuredImageUrl = trim($_POST['featured_image_url'] ?? '');

        $data = [
            'title' => $title !== '' ? $title : null,
            'content' => '',
            'content_text' => '',
            'content_format' => 'html',
            'status' => $status,
            'scheduled_at' => $scheduledAt,
            'featured_image_url' => $featuredImageUrl !== '' ? $featuredImageUrl : null,
            'category_ids' => array_map('intval', $_POST['category_ids'] ?? []),
        ];

        if ($existingId === null) {
            $owner = PlatformUser::owner();
            if (!$owner) {
                throw new RuntimeException('No owner user found. Sign in to /admin via GitHub or Google at least once before creating posts.');
            }
            $data['author_id'] = $owner['id'];
            $data['author_user_id'] = $owner['id'];
            $data['author_name'] = $owner['name'] ?? 'Owner';
            $data['author_image_url'] = $owner['image'] ?? null;
        }

        return $data;
    }

    private static function draftPostFromPost(?int $existingId): array
    {
        $existing = $existingId ? BlogPost::find($existingId) : null;

        return [
            'id' => $existingId,
            'title' => trim((string) ($_POST['title'] ?? ($existing['title'] ?? ''))),
            'content' => '',
            'status' => $_POST['status'] ?? ($existing['status'] ?? 'draft'),
            'scheduled_at' => $_POST['scheduled_at'] ?? ($existing['scheduled_at'] ?? ''),
            'featured_image_url' => trim((string) ($_POST['featured_image_url'] ?? ($existing['featured_image_url'] ?? ''))),
            'category_ids' => array_map('intval', $_POST['category_ids'] ?? ($existingId ? BlogPost::categoryIds($existingId) : [])),
        ];
    }

    public static function categoryCreateInline(): void
    {
        admin_check();
        header('Content-Type: application/json');
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            echo json_encode(['error' => 'Name required']);
            exit;
        }
        try {
            $slug = slugify($name);
            $id = BlogCategory::create($name, $slug);
            echo json_encode(['ok' => true, 'id' => $id, 'name' => $name]);
        } catch (Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    public static function postCalendar(): void
    {
        admin_check();
        $weekParam = trim($_GET['week'] ?? '');
        if (preg_match('/^(\d{4})-W(\d{2})$/', $weekParam, $m)) {
            $monday = new DateTimeImmutable();
            $monday = $monday->setISODate((int) $m[1], (int) $m[2], 1);
        } else {
            $monday = new DateTimeImmutable('monday this week');
            if ((int) (new DateTimeImmutable())->format('N') === 1) {
                $monday = new DateTimeImmutable('today');
            }
        }
        $sunday = $monday->modify('+6 days');
        $fromStr = $monday->format('Y-m-d');
        $toStr = $sunday->format('Y-m-d');
        $posts = BlogPost::forDateRange($fromStr, $toStr);
        $currentWeekLabel = $monday->format('Y') . '-W' . $monday->format('W');
        $prevWeek = $monday->modify('-7 days')->format('Y') . '-W' . $monday->modify('-7 days')->format('W');
        $nextWeek = $monday->modify('+7 days')->format('Y') . '-W' . $monday->modify('+7 days')->format('W');
        require dirname(__DIR__, 2) . '/views/admin/posts/calendar.php';
    }

    private static function handleSyndication(int $postId, string $status): array
    {
        $failures = [];
        $connectionIds = array_map('intval', array_filter($_POST['platform_connection_ids'] ?? []));
        if (!$connectionIds) {
            return $failures;
        }

        if ($status === 'published') {
            $post = BlogPost::find($postId);
            if (!$post) {
                return $failures;
            }
            $settings = SiteSettings::current();
            $siteTitle = $settings['site_title'] ?? app_site_name();
            $canonicalUrl = seo_absolute_url('/blog/posts/' . $postId) ?? '';
            $payload = SyndicationPayload::fromPost($post, $canonicalUrl, $siteTitle);

            // Post content is stored in post_sections, not posts.content
            if ($payload->contentHtml === '') {
                $sections = PostSection::allForPost($postId);
                $payload->contentHtml = implode("\n\n", array_filter(
                    array_map(static fn($s) => trim($s['content'] ?? ''), $sections)
                ));
            }

            // Make featured image URL absolute so Bluesky/LinkedIn can fetch it
            if ($payload->featuredImageUrl !== null && !preg_match('#^https?://#i', $payload->featuredImageUrl)) {
                $payload->featuredImageUrl = seo_origin() . '/' . ltrim($payload->featuredImageUrl, '/');
            }

            $filteredDrafts = [];
            foreach ($_POST['platform_texts'] ?? [] as $platform => $text) {
                $text = trim((string) $text);
                if ($text !== '') {
                    $filteredDrafts[preg_replace('/[^a-z0-9_]/', '', strtolower($platform))] = $text;
                }
            }
            if ($filteredDrafts) {
                $payload->socialPostDrafts = array_merge($payload->socialPostDrafts ?? [], $filteredDrafts);
            }

            foreach ($connectionIds as $cid) {
                $connection = PlatformConnection::find($cid);
                if (!$connection) {
                    continue;
                }
                $adapter = AdapterFactory::get($connection['platform']);
                if (!$adapter) {
                    continue;
                }
                $startedAt = microtime(true);
                try {
                    $refresh = $adapter->refreshToken($connection);
                    if ($refresh) {
                        PlatformConnection::updateTokens(
                            $cid,
                            $refresh->accessToken,
                            $refresh->refreshToken,
                            $refresh->expiresAt
                        );
                        $connection = PlatformConnection::find($cid) ?: $connection;
                    }
                    $result = $adapter->publish($connection, $payload);
                    PostSyndication::recordResult([
                        'post_id'               => $postId,
                        'platform_connection_id' => $cid,
                        'external_id'            => $result->externalId,
                        'external_url'           => $result->externalUrl,
                        'status'                 => 'synced',
                        'synced_at'              => date('Y-m-d H:i:s'),
                    ]);
                    audit_log_event('syndication_publish', 'syndication_publish', 'success', [
                        'actor_admin_identity_id' => (int) (admin_identity()['id'] ?? 0) ?: null,
                        'target_type' => 'platform_connection',
                        'target_id' => (string) $cid,
                        'http_status' => 200,
                        'metadata' => [
                            'platform' => $connection['platform'] ?? '',
                            'post_id' => $postId,
                            'token_refreshed' => $refresh !== null,
                            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                        ],
                    ]);
                } catch (Throwable $e) {
                    $platformLabel = ucfirst($connection['platform'] ?? 'unknown');
                    $failures[] = $platformLabel . ': ' . $e->getMessage();
                    PostSyndication::recordResult([
                        'post_id'               => $postId,
                        'platform_connection_id' => $cid,
                        'status'                 => 'failed',
                        'error_message'          => $e->getMessage(),
                    ]);
                    audit_log_event('syndication_publish', 'syndication_publish', 'error', [
                        'actor_admin_identity_id' => (int) (admin_identity()['id'] ?? 0) ?: null,
                        'target_type' => 'platform_connection',
                        'target_id' => (string) $cid,
                        'http_status' => 500,
                        'metadata' => [
                            'platform' => $connection['platform'] ?? '',
                            'post_id' => $postId,
                            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                            'error' => $e->getMessage(),
                        ],
                    ]);
                }
            }
        } elseif ($status === 'scheduled') {
            foreach ($connectionIds as $cid) {
                PostSyndication::recordResult([
                    'post_id'               => $postId,
                    'platform_connection_id' => $cid,
                    'status'                 => 'pending',
                ]);
            }
        }

        return $failures;
    }

    public static function processPendingSyndications(array $postIds): void
    {
        if (!$postIds) {
            return;
        }
        $pending = PostSyndication::pendingForPosts($postIds);
        if (!$pending) {
            return;
        }

        $postCache = [];
        $settings = SiteSettings::current();
        $siteTitle = $settings['site_title'] ?? app_site_name();

        foreach ($pending as $record) {
            $postId = (int) $record['post_id'];
            $cid    = (int) $record['platform_connection_id'];

            if (!isset($postCache[$postId])) {
                $postCache[$postId] = BlogPost::find($postId) ?: null;
            }
            $post = $postCache[$postId];
            if (!$post) {
                continue;
            }

            $connection = PlatformConnection::find($cid);
            if (!$connection) {
                continue;
            }
            $adapter = AdapterFactory::get($connection['platform']);
            if (!$adapter) {
                continue;
            }
            $startedAt = microtime(true);

            $canonicalUrl = seo_absolute_url('/blog/posts/' . $postId) ?? '';
            $payload = SyndicationPayload::fromPost($post, $canonicalUrl, $siteTitle);
            if ($payload->contentHtml === '') {
                $sections = PostSection::allForPost($postId);
                $payload->contentHtml = implode("\n\n", array_filter(
                    array_map(static fn($s) => trim($s['content'] ?? ''), $sections)
                ));
            }
            if ($payload->featuredImageUrl !== null && !preg_match('#^https?://#i', $payload->featuredImageUrl)) {
                $payload->featuredImageUrl = seo_origin() . '/' . ltrim($payload->featuredImageUrl, '/');
            }

            try {
                $refresh = $adapter->refreshToken($connection);
                if ($refresh) {
                    PlatformConnection::updateTokens(
                        $cid,
                        $refresh->accessToken,
                        $refresh->refreshToken,
                        $refresh->expiresAt
                    );
                    $connection = PlatformConnection::find($cid) ?: $connection;
                }
                $result = $adapter->publish($connection, $payload);
                PostSyndication::recordResult([
                    'post_id'               => $postId,
                    'platform_connection_id' => $cid,
                    'external_id'            => $result->externalId,
                    'external_url'           => $result->externalUrl,
                    'status'                 => 'synced',
                    'synced_at'              => date('Y-m-d H:i:s'),
                ]);
                audit_log_event('syndication_publish', 'syndication_publish', 'success', [
                    'target_type' => 'platform_connection',
                    'target_id' => (string) $cid,
                    'http_status' => 200,
                    'metadata' => [
                        'platform' => $connection['platform'] ?? '',
                        'post_id' => $postId,
                        'token_refreshed' => $refresh !== null,
                        'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    ],
                ]);
            } catch (Throwable $e) {
                PostSyndication::recordResult([
                    'post_id'               => $postId,
                    'platform_connection_id' => $cid,
                    'status'                 => 'failed',
                    'error_message'          => $e->getMessage(),
                ]);
                audit_log_event('syndication_publish', 'syndication_publish', 'error', [
                    'target_type' => 'platform_connection',
                    'target_id' => (string) $cid,
                    'http_status' => 500,
                    'metadata' => [
                        'platform' => $connection['platform'] ?? '',
                        'post_id' => $postId,
                        'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                        'error' => $e->getMessage(),
                    ],
                ]);
            }
        }
    }
}
