<?php

declare(strict_types=1);

class BlogAdminController
{
    public static function postsIndex(): void
    {
        admin_check();
        BlogPost::publishDuePosts();
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
        $error = null;
        require dirname(__DIR__, 2) . '/views/admin/posts/form.php';
    }

    public static function postStore(): void
    {
        admin_check();

        try {
            $data = self::resolvePostData(null);
            $postId = BlogPost::create($data);
            BlogPost::syncCategories($postId, $data['category_ids']);
            header('Location: /admin/posts');
        } catch (Throwable $e) {
            $categories = BlogCategory::all();
            $post = self::draftPostFromPost(null);
            $assignedCategoryIds = $post['category_ids'];
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
            header('Location: /admin/posts');
        } catch (Throwable $e) {
            $post = self::draftPostFromPost((int) $id);
            $categories = BlogCategory::all();
            $assignedCategoryIds = $post['category_ids'];
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

    private static function resolvePostData(?int $existingId): array
    {
        $content = trim($_POST['content'] ?? '');
        if ($content === '') {
            throw new InvalidArgumentException('Content is required.');
        }

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

        $title = trim($_POST['title'] ?? '');
        $featuredImageUrl = trim($_POST['featured_image_url'] ?? '');

        $data = [
            'title' => $title !== '' ? $title : null,
            'content' => $content,
            'content_text' => trim(strip_tags($content)),
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
            'content' => $_POST['content'] ?? ($existing['content'] ?? ''),
            'status' => $_POST['status'] ?? ($existing['status'] ?? 'draft'),
            'scheduled_at' => $_POST['scheduled_at'] ?? ($existing['scheduled_at'] ?? ''),
            'featured_image_url' => trim((string) ($_POST['featured_image_url'] ?? ($existing['featured_image_url'] ?? ''))),
            'category_ids' => array_map('intval', $_POST['category_ids'] ?? ($existingId ? BlogPost::categoryIds($existingId) : [])),
        ];
    }
}
