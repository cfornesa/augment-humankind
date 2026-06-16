<?php

declare(strict_types=1);

class BlogController
{
    public static function index(): void
    {
        BlogPost::publishDuePosts();
        $posts = BlogPost::published();
        $categories = BlogCategory::all();
        $pageTitle = 'Blog | Augment Humankind';
        $pageDescription = 'Posts, notes, imported feed items, and updates from Augment Humankind.';
        $bodyClass = 'page-blog';
        $canonicalUrl = seo_absolute_url('/blog');
        require dirname(__DIR__) . '/views/blog/index.php';
    }

    public static function show(string $id): void
    {
        BlogPost::publishDuePosts();
        $post = ctype_digit($id) ? BlogPost::findPublished((int) $id) : false;
        if (!$post) {
            self::notFound();
        }

        $pageTitle = (($post['title'] ?? '') ?: 'Post') . ' | Augment Humankind';
        $pageDescription = seo_excerpt($post['content_text'] ?? $post['content'] ?? '', 160)
            ?? 'A post from Augment Humankind.';
        $bodyClass = 'page-blog-post';
        $canonicalUrl = seo_absolute_url('/blog/posts/' . (int) $post['id']);
        $ogImage = $post['featured_image_url'] ?? null;
        $comments = Comment::commentsFor('post', (int) $post['id']);
        require dirname(__DIR__) . '/views/blog/show.php';
    }

    public static function categories(): void
    {
        $categories = BlogCategory::all();
        $pageTitle = 'Blog Categories | Augment Humankind';
        $pageDescription = 'Browse Augment Humankind blog categories.';
        $bodyClass = 'page-blog-categories';
        $canonicalUrl = seo_absolute_url('/blog/categories');
        require dirname(__DIR__) . '/views/blog/categories.php';
    }

    public static function category(string $slug): void
    {
        BlogPost::publishDuePosts();
        $category = BlogCategory::findBySlug($slug);
        if (!$category) {
            self::notFound();
        }

        $posts = BlogPost::byCategory($slug);
        $pageTitle = $category['name'] . ' | Blog | Augment Humankind';
        $pageDescription = seo_excerpt($category['description'] ?? '', 160)
            ?? ('Posts in ' . $category['name'] . '.');
        $bodyClass = 'page-blog-category';
        $canonicalUrl = seo_absolute_url('/blog/category/' . $slug);
        require dirname(__DIR__) . '/views/blog/category.php';
    }

    public static function search(): void
    {
        BlogPost::publishDuePosts();
        $query = trim((string) ($_GET['q'] ?? ''));
        $posts = $query === '' ? [] : BlogPost::search($query);
        $pageTitle = 'Search | Augment Humankind';
        $pageDescription = 'Search published Augment Humankind posts.';
        $bodyClass = 'page-search';
        $canonicalUrl = seo_absolute_url('/search');
        require dirname(__DIR__) . '/views/blog/search.php';
    }

    public static function full(string $id): void
    {
        $post = ctype_digit($id) ? BlogPost::findPublished((int) $id) : false;
        if (!$post) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Not found']);
            exit;
        }
        header('Content-Type: application/json');
        echo json_encode(['content' => (string) $post['content']]);
        exit;
    }

    public static function commentsJson(string $id): void
    {
        if (!ctype_digit($id)) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Not found']);
            exit;
        }
        $comments = Comment::commentsFor('post', (int) $id);
        header('Content-Type: application/json');
        echo json_encode(Comment::toApiPayloadList($comments));
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

        if (!user_logged_in()) {
            http_response_code(401);
            echo json_encode(['error' => 'Sign in to comment.']);
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

        $actor = current_comment_actor();
        if (!$actor) {
            http_response_code(401);
            echo json_encode(['error' => 'Sign in to comment.']);
            exit;
        }
        $postId = (int) $id;

        try {
            Comment::insertComment(
                'post',
                $postId,
                (string) $actor['name'],
                $content,
                $postId,
                (string) $actor['id'],
                $actor['user_id'] !== null ? (string) $actor['user_id'] : null,
                (string) ($actor['image'] ?? '')
            );
            $commentId = (int) db()->lastInsertId();
            $created = Comment::find($commentId);
        } catch (Throwable) {
            http_response_code(500);
            echo json_encode(['error' => 'Could not save comment.']);
            exit;
        }

        echo json_encode([
            'ok' => true,
            'comment' => $created ? Comment::toApiPayload($created) : null,
        ]);
        exit;
    }

    public static function feeds(): void
    {
        refresh_due_feeds();
        $pageTitle = 'Feeds | Augment Humankind';
        $pageDescription = 'Subscribe to Augment Humankind feeds.';
        $bodyClass = 'page-blog-feeds';
        $canonicalUrl = seo_absolute_url('/blog/feeds');
        require dirname(__DIR__) . '/views/blog/feeds.php';
    }

    public static function atom(): void
    {
        BlogPost::publishDuePosts();
        $origin = seo_origin();
        $entries = self::postEntries(BlogPost::published(50), $origin);
        header('Content-Type: application/atom+xml; charset=utf-8');
        echo self::atomXml($entries, self::siteScope($origin));
    }

    public static function jsonFeed(): void
    {
        BlogPost::publishDuePosts();
        $origin = seo_origin();
        $entries = self::postEntries(BlogPost::published(50), $origin);
        header('Content-Type: application/feed+json; charset=utf-8');
        echo json_encode(self::jsonFeedPayload($entries, self::siteScope($origin)), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    public static function mf2(): void
    {
        BlogPost::publishDuePosts();
        $origin = seo_origin();
        $entries = self::postEntries(BlogPost::published(50), $origin);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(self::mf2Payload($entries, $origin), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    public static function categoryFeed(string $format, string $slug): void
    {
        BlogPost::publishDuePosts();
        $category = BlogCategory::findBySlug($slug);
        if (!$category) {
            self::notFound();
        }

        $origin = seo_origin();
        $entries = self::postEntries(BlogPost::byCategory($slug, 50), $origin);
        $scope = self::categoryScope($origin, $category);

        if ($format === 'json') {
            header('Content-Type: application/feed+json; charset=utf-8');
            echo json_encode(self::jsonFeedPayload($entries, $scope), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            return;
        }

        header('Content-Type: application/atom+xml; charset=utf-8');
        echo self::atomXml($entries, $scope);
    }

    public static function redirectCategoryFeed(string $format, string $slug): void
    {
        self::permanentRedirect('/blog/category/' . $slug . '/feed.' . $format);
    }

    public static function redirectPageFeed(string $format, string $slug): void
    {
        self::permanentRedirect('/' . $slug . '/feed.' . $format);
    }

    public static function redirectPost(string $sourceId): void
    {
        if (ctype_digit($sourceId)) {
            $post = BlogPost::findByPlatformSourceId((int) $sourceId);
            if ($post) {
                self::permanentRedirect('/blog/posts/' . (int) $post['id']);
            }
        }
        self::permanentRedirect('/blog');
    }

    public static function redirectCategory(string $slug): void
    {
        $category = BlogCategory::findBySlug($slug);
        if ($category) {
            self::permanentRedirect('/blog/category/' . $category['slug']);
        }
        self::notFound();
    }

    public static function redirectPage(string $slug): void
    {
        $page = Page::safeFindPublishedBySlug($slug);
        if (!$page) {
            try {
                $stmt = db()->prepare(
                    'SELECT slug FROM pages
                     WHERE platform_original_slug = ? AND status = ? AND deleted_at IS NULL
                     LIMIT 1'
                );
                $stmt->execute([$slug, 'published']);
                $targetSlug = $stmt->fetchColumn();
                if (is_string($targetSlug) && $targetSlug !== '') {
                    self::permanentRedirect('/' . $targetSlug);
                }
            } catch (Throwable) {
                self::notFound();
            }
            self::notFound();
        }
        self::permanentRedirect('/' . $page['slug']);
    }

    public static function permanentRedirect(string $location): void
    {
        header('Location: ' . $location, true, 301);
        exit;
    }

    private static function notFound(): never
    {
        http_response_code(404);
        require dirname(__DIR__) . '/views/404.php';
        exit;
    }

    public static function atomXml(array $entries, array $scope): string
    {
        $updated = $entries[0]['updated'] ?? gmdate('c');
        $xml = '<?xml version="1.0" encoding="utf-8"?>' . "\n";
        $xml .= '<feed xmlns="http://www.w3.org/2005/Atom">' . "\n";
        $xml .= '<title>' . e($scope['title']) . '</title>' . "\n";
        if (($scope['description'] ?? '') !== '') {
            $xml .= '<subtitle>' . e($scope['description']) . '</subtitle>' . "\n";
        }
        $xml .= '<id>' . e($scope['id']) . '</id>' . "\n";
        $xml .= '<link rel="alternate" href="' . e($scope['alternate']) . '" />' . "\n";
        $xml .= '<link rel="self" href="' . e($scope['feed_url_xml']) . '" />' . "\n";
        $xml .= '<updated>' . e(self::atomDate((string) $updated)) . '</updated>' . "\n";
        if (($scope['author_name'] ?? '') !== '') {
            $xml .= '<author><name>' . e($scope['author_name']) . '</name></author>' . "\n";
        }
        foreach ($entries as $entry) {
            $xml .= '<entry>' . "\n";
            $xml .= '<title>' . e($entry['title']) . '</title>' . "\n";
            $xml .= '<id>' . e($entry['id']) . '</id>' . "\n";
            $xml .= '<link href="' . e($entry['url']) . '" />' . "\n";
            $xml .= '<updated>' . e(self::atomDate($entry['updated'])) . '</updated>' . "\n";
            $xml .= '<published>' . e(self::atomDate($entry['published'])) . '</published>' . "\n";
            if ($entry['author_name'] !== '') {
                $xml .= '<author><name>' . e($entry['author_name']) . '</name></author>' . "\n";
            }
            if ($entry['summary'] !== '') {
                $xml .= '<summary>' . e($entry['summary']) . '</summary>' . "\n";
            }
            foreach ($entry['categories'] as $category) {
                $xml .= '<category term="' . e($category['slug']) . '" label="' . e($category['name']) . '" />' . "\n";
            }
            $xml .= '<content type="html">' . e($entry['content_html']) . '</content>' . "\n";
            $xml .= '</entry>' . "\n";
        }
        return $xml . '</feed>' . "\n";
    }

    public static function jsonFeedPayload(array $entries, array $scope): array
    {
        $payload = [
            'version' => 'https://jsonfeed.org/version/1.1',
            'title' => $scope['title'],
            'home_page_url' => $scope['alternate'],
            'feed_url' => $scope['feed_url_json'],
        ];
        if (($scope['description'] ?? '') !== '') {
            $payload['description'] = $scope['description'];
        }
        if (($scope['author_name'] ?? '') !== '') {
            $payload['authors'] = [['name' => $scope['author_name']]];
        }
        $payload['items'] = array_map(static function (array $entry): array {
            $item = [
                'id' => $entry['id'],
                'url' => $entry['url'],
                'title' => $entry['title'],
                'content_html' => $entry['content_html'],
                'content_text' => $entry['content_text'],
                'summary' => $entry['summary'],
                'date_published' => self::atomDate($entry['published']),
            ];
            if ($entry['updated'] !== $entry['published']) {
                $item['date_modified'] = self::atomDate($entry['updated']);
            }
            if ($entry['author_name'] !== '') {
                $item['authors'] = [['name' => $entry['author_name']]];
            }
            if ($entry['categories'] !== []) {
                $item['tags'] = array_map(
                    static fn (array $category): string => $category['name'],
                    $entry['categories']
                );
            }
            return $item;
        }, $entries);

        return $payload;
    }

    private static function mf2Payload(array $entries, string $origin): array
    {
        $authorName = seo_author_name();
        return [
            'items' => array_map(static function (array $entry) use ($origin, $authorName): array {
                $properties = [
                    'name' => [$entry['title']],
                    'content' => [[
                        'html' => $entry['content_html'],
                        'value' => $entry['content_text'],
                    ]],
                    'url' => [$entry['url']],
                    'published' => [self::atomDate($entry['published'])],
                    'author' => [[
                        'type' => ['h-card'],
                        'properties' => [
                            'name' => [$authorName],
                            'url' => [$origin],
                        ],
                    ]],
                ];
                if ($entry['categories'] !== []) {
                    $properties['category'] = array_map(
                        static fn (array $category): string => $category['name'],
                        $entry['categories']
                    );
                }
                return ['type' => ['h-entry'], 'properties' => $properties];
            }, $entries),
        ];
    }

    /**
     * Normalize posts into the entry shape shared by atomXml(),
     * jsonFeedPayload(), and mf2Payload().
     */
    private static function postEntries(array $posts, string $origin): array
    {
        return array_map(static function (array $post) use ($origin): array {
            $url = $origin . '/blog/posts/' . (int) $post['id'];
            $created = (string) $post['created_at'];
            return [
                'id' => $url,
                'url' => $url,
                'title' => (string) (($post['title'] ?? '') ?: 'Untitled post'),
                'published' => $created,
                'updated' => $created,
                'content_html' => (string) $post['content'],
                'content_text' => (string) ($post['content_text'] ?? strip_tags((string) $post['content'])),
                'summary' => seo_excerpt($post['content_text'] ?? $post['content'] ?? '', 220) ?? '',
                'author_name' => (string) $post['author_name'],
                'categories' => $post['categories'] ?? [],
            ];
        }, $posts);
    }

    private static function siteScope(string $origin): array
    {
        $meta = seo_site_meta();
        return [
            'id' => $origin . '/blog',
            'title' => $meta['title'],
            'description' => $meta['description'],
            'alternate' => $origin . '/blog',
            'feed_url_xml' => $origin . '/feed.xml',
            'feed_url_json' => $origin . '/feed.json',
            'author_name' => seo_author_name(),
        ];
    }

    private static function categoryScope(string $origin, array $category): array
    {
        $meta = seo_site_meta();
        $slug = (string) $category['slug'];
        $description = trim((string) ($category['description'] ?? ''));
        return [
            'id' => $origin . '/blog/category/' . $slug,
            'title' => $meta['title'] . ' — ' . $category['name'],
            'description' => $description !== '' ? $description : 'Posts in "' . $category['name'] . '".',
            'alternate' => $origin . '/blog/category/' . $slug,
            'feed_url_xml' => $origin . '/blog/category/' . $slug . '/feed.xml',
            'feed_url_json' => $origin . '/blog/category/' . $slug . '/feed.json',
            'author_name' => seo_author_name(),
        ];
    }

    private static function atomDate(string $value): string
    {
        $timestamp = strtotime($value);
        return $timestamp === false ? gmdate('c') : gmdate('c', $timestamp);
    }
}
