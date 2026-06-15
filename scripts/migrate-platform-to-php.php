<?php

declare(strict_types=1);

/**
 * One-way platform assimilation tool.
 *
 * Default mode is dry-run. Use --execute to write to the current PHP target DB.
 * Source database (PLATFORM_*) is read-only by design and policy.
 */

final class Env
{
    public static function load(string $path): void
    {
        if (!is_readable($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if ($name === '') {
                continue;
            }
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }
            if (($_ENV[$name] ?? getenv($name) ?: '') === '') {
                $_ENV[$name] = $value;
                putenv($name . '=' . $value);
            }
        }
    }

    public static function get(string $key, string $default = ''): string
    {
        $value = $_ENV[$key] ?? getenv($key);
        return is_string($value) && $value !== '' ? $value : $default;
    }
}

final class ReadOnlyPlatformSource
{
    public function __construct(private PDO $pdo)
    {
        $this->pdo->exec('SET SESSION TRANSACTION READ ONLY');
        $this->pdo->beginTransaction();
    }

    public function __destruct()
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function rows(string $table): array
    {
        if (!preg_match('/^[a-z0-9_]+$/', $table) || !$this->tableExists($table)) {
            return [];
        }
        return $this->pdo->query('SELECT * FROM `' . $table . '`')->fetchAll();
    }

    public function count(string $table): int
    {
        if (!preg_match('/^[a-z0-9_]+$/', $table) || !$this->tableExists($table)) {
            return 0;
        }
        return (int) $this->pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    }

    public function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }
}

final class TargetDb
{
    private array $columns = [];

    public function __construct(public PDO $pdo)
    {
    }

    public function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }

    public function columns(string $table): array
    {
        if (isset($this->columns[$table])) {
            return $this->columns[$table];
        }
        $stmt = $this->pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);
        return $this->columns[$table] = array_column($stmt->fetchAll(), 'COLUMN_NAME');
    }

    public function insert(string $table, array $row): int|string
    {
        $columns = array_values(array_intersect(array_keys($row), $this->columns($table)));
        if ($columns === []) {
            throw new RuntimeException('No insertable columns for ' . $table);
        }
        $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $columns) . '`) VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_map(static fn (string $column) => $row[$column], $columns));
        return $this->pdo->lastInsertId() ?: (string) ($row['id'] ?? '');
    }

    public function insertIgnore(string $table, array $row): int|string
    {
        $columns = array_values(array_intersect(array_keys($row), $this->columns($table)));
        if ($columns === []) {
            throw new RuntimeException('No insertable columns for ' . $table);
        }
        $sql = 'INSERT IGNORE INTO `' . $table . '` (`' . implode('`,`', $columns) . '`) VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_map(static fn (string $column) => $row[$column], $columns));
        return $this->pdo->lastInsertId() ?: (string) ($row['id'] ?? '');
    }

    public function map(string $type, string $sourceId, string $targetTable, string $targetId, ?string $notes = null): void
    {
        $this->insertIgnore('platform_migration_map', [
            'entity_type' => $type,
            'source_id' => $sourceId,
            'target_table' => $targetTable,
            'target_id' => $targetId,
            'notes' => $notes,
        ]);
    }

    public function mapped(string $type, string $sourceId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT target_id FROM platform_migration_map WHERE entity_type = ? AND source_id = ? LIMIT 1');
        $stmt->execute([$type, $sourceId]);
        $value = $stmt->fetchColumn();
        return is_string($value) && $value !== '' ? $value : null;
    }

    public function mappedCount(string $type): int
    {
        if (!$this->tableExists('platform_migration_map')) {
            return 0;
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM platform_migration_map WHERE entity_type = ?');
        $stmt->execute([$type]);
        return (int) $stmt->fetchColumn();
    }

    public function rowCount(string $table): int
    {
        if (!$this->tableExists($table)) {
            return 0;
        }
        return (int) $this->pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    }
}

function pdoFromPrefix(string $prefix): PDO
{
    $host = Env::get($prefix . 'DB_HOST', 'localhost');
    $port = Env::get($prefix . 'DB_PORT', '3306');
    $name = Env::get($prefix . 'DB_NAME');
    $user = Env::get($prefix . 'DB_USER');
    $pass = Env::get($prefix . 'DB_PASS');
    if ($name === '' || $user === '') {
        throw new RuntimeException('Missing ' . $prefix . 'DB_NAME or ' . $prefix . 'DB_USER');
    }
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    if (strtolower(Env::get($prefix . 'DB_SSL')) === 'true') {
        if (class_exists('Pdo\Mysql') && defined('Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')) {
            $options[constant('Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')] = false;
        } elseif (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
            $options[constant('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')] = false;
        }
    }
    return new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $pass, $options);
}

function textFromHtml(?string $value): string
{
    return trim(preg_replace('/\s+/', ' ', strip_tags((string) $value)) ?? '');
}

function uniqueSlug(TargetDb $target, string $base, string $table, string $scope = ''): string
{
    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($base))) ?: 'platform-item';
    $slug = trim($slug, '-');
    $candidate = $slug;
    $suffix = 2;
    while (true) {
        if ($table === 'categories') {
            $stmt = $target->pdo->prepare('SELECT 1 FROM categories WHERE slug = ? LIMIT 1');
            $stmt->execute([$candidate]);
        } else {
            $stmt = $target->pdo->prepare('SELECT 1 FROM pages WHERE slug = ? LIMIT 1');
            $stmt->execute([$candidate]);
        }
        if (!$stmt->fetchColumn()) {
            return $candidate;
        }
        $candidate = $slug . '-' . $suffix++;
    }
}

function uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

Env::load(dirname(__DIR__) . '/.env');

$execute = in_array('--execute', $argv, true);
$verifyOnly = in_array('--verify-only', $argv, true);
$dryRun = !$execute && !$verifyOnly;

$targetName = Env::get('DB_NAME');
$sourceName = Env::get('PLATFORM_DB_NAME');
if ($targetName === '' || $sourceName === '') {
    fwrite(STDERR, "Both DB_* and PLATFORM_DB_* must be configured.\n");
    exit(1);
}
if ($targetName === $sourceName && !in_array('--allow-same-database-read-only', $argv, true)) {
    fwrite(STDERR, "Refusing to run: DB_NAME and PLATFORM_DB_NAME are identical.\n");
    exit(1);
}

try {
    $source = new ReadOnlyPlatformSource(pdoFromPrefix('PLATFORM_'));
    $target = new TargetDb(pdoFromPrefix(''));
} catch (Throwable $e) {
    fwrite(STDERR, "Could not open database connections: " . $e->getMessage() . "\n");
    exit(1);
}

$tables = [
    'users',
    'accounts',
    'sessions',
    'verification_tokens',
    'feed_sources',
    'feed_items_seen',
    'categories',
    'pages',
    'nav_links',
    'posts',
    'post_categories',
    'comments',
    'reactions',
    'media_assets',
    'profile_photo_assets',
    'site_settings',
    'site_assets',
    'user_ai_vendor_settings',
    'user_ai_vendor_keys',
    'platform_connections',
    'platform_oauth_apps',
    'post_syndications',
    'art_pieces',
    'art_piece_versions',
    'exhibits',
    'piece_exhibits',
    'media_asset_exhibits',
];
$report = ['mode' => $verifyOnly ? 'verify-only' : ($dryRun ? 'dry-run' : 'execute'), 'source_counts' => [], 'imported' => [], 'skipped' => [], 'notes' => []];
foreach ($tables as $table) {
    $report['source_counts'][$table] = $source->count($table);
}

if ($verifyOnly) {
    $entityMap = [
        'users' => 'users',
        'categories' => 'categories',
        'nav_links' => 'navigation_items',
        'posts' => 'posts',
        'media_assets' => 'media_assets',
        'user_ai_vendor_settings' => 'user_ai_vendor_settings',
        'user_ai_vendor_keys' => 'user_ai_vendor_keys',
        'platform_connections' => 'platform_connections',
        'art_pieces' => 'art_pieces',
        'art_piece_versions' => 'art_piece_versions',
        'exhibits' => 'platform_collections',
    ];
    $report['target_counts'] = [];
    $report['mapped_counts'] = [];
    foreach ($entityMap as $entity => $table) {
        $report['target_counts'][$table] = $target->rowCount($table);
        $report['mapped_counts'][$entity] = $target->mappedCount($entity);
    }
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
}

if ($dryRun) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
}

foreach (['users', 'posts', 'platform_migration_map'] as $requiredTable) {
    if (!$target->tableExists($requiredTable)) {
        fwrite(STDERR, "Missing target table {$requiredTable}. Apply migrations/2026-06-14-platform-assimilation.sql first.\n");
        exit(1);
    }
}

$target->pdo->beginTransaction();
try {
    foreach ($source->rows('users') as $row) {
        if ($target->mapped('users', (string) $row['id']) !== null) {
            $report['skipped']['users'] = ($report['skipped']['users'] ?? 0) + 1;
            continue;
        }
        $id = uuid();
        $target->insert('users', [
            'id' => $id,
            'platform_source_id' => (string) $row['id'],
            'name' => $row['name'] ?? null,
            'username' => $row['username'] ?? null,
            'email' => $row['email'] ?? null,
            'email_verified' => $row['email_verified'] ?? null,
            'image' => $row['image'] ?? null,
            'bio' => $row['bio'] ?? null,
            'website' => $row['website'] ?? null,
            'social_links' => $row['social_links'] ?? null,
            'role' => $row['role'] ?? 'member',
            'status' => $row['status'] ?? 'active',
            'post_count' => $row['post_count'] ?? 0,
            'theme' => $row['theme'] ?? null,
            'palette' => $row['palette'] ?? null,
            'color_background' => $row['color_background'] ?? null,
            'color_foreground' => $row['color_foreground'] ?? null,
            'color_background_dark' => $row['color_background_dark'] ?? null,
            'color_foreground_dark' => $row['color_foreground_dark'] ?? null,
            'color_primary' => $row['color_primary'] ?? null,
            'color_primary_foreground' => $row['color_primary_foreground'] ?? null,
            'color_secondary' => $row['color_secondary'] ?? null,
            'color_secondary_foreground' => $row['color_secondary_foreground'] ?? null,
            'color_accent' => $row['color_accent'] ?? null,
            'color_accent_foreground' => $row['color_accent_foreground'] ?? null,
            'color_muted' => $row['color_muted'] ?? null,
            'color_muted_foreground' => $row['color_muted_foreground'] ?? null,
            'color_destructive' => $row['color_destructive'] ?? null,
            'color_destructive_foreground' => $row['color_destructive_foreground'] ?? null,
            'preferred_art_piece_profile_id' => $row['preferred_art_piece_profile_id'] ?? null,
            'preferred_text_improve_profile_id' => $row['preferred_text_improve_profile_id'] ?? null,
            'preferred_alt_text_profile_id' => $row['preferred_alt_text_profile_id'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'last_login_at' => $row['last_login_at'] ?? null,
        ]);
        $target->map('users', (string) $row['id'], 'users', $id);
        $report['imported']['users'] = ($report['imported']['users'] ?? 0) + 1;
    }

    foreach ($source->rows('accounts') as $row) {
        $userId = $target->mapped('users', (string) $row['user_id']);
        if ($userId === null) {
            $report['notes'][] = 'Skipped account for unmapped user ' . $row['user_id'];
            continue;
        }
        $target->insertIgnore('accounts', [
            'user_id' => $userId,
            'type' => $row['type'] ?? 'oauth',
            'provider' => $row['provider'] ?? '',
            'provider_account_id' => $row['provider_account_id'] ?? '',
            'refresh_token' => $row['refresh_token'] ?? null,
            'access_token' => $row['access_token'] ?? null,
            'expires_at' => $row['expires_at'] ?? null,
            'token_type' => $row['token_type'] ?? null,
            'scope' => $row['scope'] ?? null,
            'id_token' => $row['id_token'] ?? null,
            'session_state' => $row['session_state'] ?? null,
        ]);
        $report['imported']['accounts'] = ($report['imported']['accounts'] ?? 0) + 1;
    }

    foreach ($source->rows('sessions') as $row) {
        $userId = $target->mapped('users', (string) $row['user_id']);
        if ($userId === null) {
            $report['notes'][] = 'Skipped session for unmapped user ' . $row['user_id'];
            continue;
        }
        $target->insertIgnore('sessions', [
            'session_token' => $row['session_token'],
            'user_id' => $userId,
            'expires' => $row['expires'],
        ]);
        $report['imported']['sessions'] = ($report['imported']['sessions'] ?? 0) + 1;
    }

    foreach ($source->rows('verification_tokens') as $row) {
        $target->insertIgnore('verification_tokens', [
            'identifier' => $row['identifier'],
            'token' => $row['token'],
            'expires' => $row['expires'],
        ]);
        $report['imported']['verification_tokens'] = ($report['imported']['verification_tokens'] ?? 0) + 1;
    }

    foreach ($source->rows('feed_sources') as $row) {
        if ($target->mapped('feed_sources', (string) $row['id']) !== null) {
            $report['skipped']['feed_sources'] = ($report['skipped']['feed_sources'] ?? 0) + 1;
            continue;
        }
        $targetId = $target->insert('feed_sources', [
            'platform_source_id' => $row['id'],
            'name' => $row['name'],
            'author_name' => $row['author_name'] ?? null,
            'username' => $row['username'] ?? null,
            'bio' => $row['bio'] ?? null,
            'image_url' => $row['image_url'] ?? null,
            'site_url' => $row['site_url'] ?? null,
            'feed_url' => $row['feed_url'],
            'cadence' => $row['cadence'] ?? 'daily',
            'enabled' => $row['enabled'] ?? 1,
            'last_fetched_at' => $row['last_fetched_at'] ?? null,
            'next_fetch_at' => $row['next_fetch_at'] ?? null,
            'items_imported' => $row['items_imported'] ?? 0,
            'last_status' => $row['last_status'] ?? null,
            'last_error' => $row['last_error'] ?? null,
            'profile_photo_url' => $row['image_url'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ]);
        $target->map('feed_sources', (string) $row['id'], 'feed_sources', (string) $targetId);
        $report['imported']['feed_sources'] = ($report['imported']['feed_sources'] ?? 0) + 1;
    }

    foreach ($source->rows('categories') as $row) {
        if ($target->mapped('categories', (string) $row['id']) !== null) {
            $report['skipped']['categories'] = ($report['skipped']['categories'] ?? 0) + 1;
            continue;
        }
        $slug = uniqueSlug($target, (string) ($row['slug'] ?? $row['name'] ?? 'category'), 'categories', 'blog');
        $targetId = $target->insert('categories', [
            'name' => $row['name'],
            'slug' => $slug,
            'description' => $row['description'] ?? null,
            'category_scope' => 'blog',
            'platform_source_id' => $row['id'],
            'platform_original_slug' => $row['slug'] ?? null,
            'platform_created_at' => $row['created_at'] ?? null,
            'platform_updated_at' => $row['updated_at'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ]);
        $target->map('categories', (string) $row['id'], 'categories', (string) $targetId, $slug !== ($row['slug'] ?? '') ? 'slug remapped from conflict' : null);
        $report['imported']['categories'] = ($report['imported']['categories'] ?? 0) + 1;
    }

    foreach ($source->rows('pages') as $row) {
        if ($target->mapped('pages', (string) $row['id']) !== null) {
            $report['skipped']['pages'] = ($report['skipped']['pages'] ?? 0) + 1;
            continue;
        }
        $originalSlug = (string) ($row['slug'] ?? 'page');
        $slug = uniqueSlug($target, $originalSlug, 'pages');
        if ($slug !== $originalSlug) {
            $slug = uniqueSlug($target, 'platform-' . $originalSlug, 'pages');
        }
        $pageId = $target->insert('pages', [
            'title' => $row['title'],
            'slug' => $slug,
            'status' => $row['status'] ?? 'draft',
            'template' => 'standard',
            'show_in_nav' => $row['show_in_nav'] ?? 1,
            'platform_source_id' => $row['id'],
            'platform_original_slug' => $originalSlug,
            'content_format' => $row['content_format'] ?? 'html',
            'content_text' => $row['content_text'] ?? textFromHtml($row['content'] ?? ''),
            'author_user_id' => isset($row['author_user_id']) ? $target->mapped('users', (string) $row['author_user_id']) : null,
            'platform_created_at' => $row['created_at'] ?? null,
            'platform_updated_at' => $row['updated_at'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ]);
        $target->insert('page_sections', [
            'page_id' => $pageId,
            'heading' => null,
            'content' => $row['content'] ?? '',
            'sort_order' => 0,
            'created_at' => $row['created_at'] ?? null,
        ]);
        $target->map('pages', (string) $row['id'], 'pages', (string) $pageId, $slug !== $originalSlug ? 'slug remapped from conflict' : null);
        $report['imported']['pages'] = ($report['imported']['pages'] ?? 0) + 1;
    }

    $platformSystemNavUrlMap = [
        '/feeds' => '/blog/feeds',
        '/categories' => '/blog/categories',
    ];

    foreach ($source->rows('nav_links') as $row) {
        if ($target->mapped('nav_links', (string) $row['id']) !== null) {
            $report['skipped']['nav_links'] = ($report['skipped']['nav_links'] ?? 0) + 1;
            continue;
        }
        $pageId = !empty($row['page_id']) ? $target->mapped('pages', (string) $row['page_id']) : null;
        $kind = $row['kind'] ?? '';
        $originalUrl = $row['url'] ?? null;
        $url = $pageId
            ? null
            : ($kind === 'system' ? ($platformSystemNavUrlMap[$originalUrl] ?? $originalUrl ?? '#') : ($originalUrl ?? '#'));

        $targetId = $target->insert('navigation_items', [
            'source_type' => $pageId ? 'page' : 'external',
            'system_key' => null,
            'page_id' => $pageId,
            'label' => $row['label'],
            'url' => $url,
            'target' => !empty($row['open_in_new_tab']) ? '_blank' : null,
            'is_visible' => $row['visible'] ?? 1,
            'sort_order' => $row['sort_order'] ?? 0,
            'platform_source_id' => $row['id'],
            'platform_original_url' => $originalUrl,
            'platform_kind' => $kind ?: null,
            'open_in_new_tab' => $row['open_in_new_tab'] ?? 0,
        ]);
        $target->map('nav_links', (string) $row['id'], 'navigation_items', (string) $targetId);
        $report['imported']['nav_links'] = ($report['imported']['nav_links'] ?? 0) + 1;
    }

    foreach ($source->rows('posts') as $row) {
        if ($target->mapped('posts', (string) $row['id']) !== null) {
            $report['skipped']['posts'] = ($report['skipped']['posts'] ?? 0) + 1;
            continue;
        }
        $targetId = $target->insert('posts', [
            'platform_source_id' => $row['id'],
            'author_id' => isset($row['author_user_id']) && $row['author_user_id'] ? ($target->mapped('users', (string) $row['author_user_id']) ?? (string) $row['author_id']) : (string) $row['author_id'],
            'author_user_id' => isset($row['author_user_id']) && $row['author_user_id'] ? $target->mapped('users', (string) $row['author_user_id']) : null,
            'author_name' => $row['author_name'],
            'author_image_url' => $row['author_image_url'] ?? null,
            'title' => $row['title'] ?? null,
            'content' => $row['content'],
            'content_text' => $row['content_text'] ?? textFromHtml($row['content'] ?? ''),
            'content_format' => $row['content_format'] ?? 'plain',
            'status' => $row['status'] ?? 'published',
            'source_feed_id' => isset($row['source_feed_id']) && $row['source_feed_id'] ? $target->mapped('feed_sources', (string) $row['source_feed_id']) : null,
            'source_guid' => $row['source_guid'] ?? null,
            'source_canonical_url' => $row['source_canonical_url'] ?? null,
            'scheduled_at' => $row['scheduled_at'] ?? null,
            'pending_platform_ids' => $row['pending_platform_ids'] ?? null,
            'featured_image_url' => $row['featured_image_url'] ?? null,
            'social_post_drafts' => $row['social_post_drafts'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'deleted_at' => $row['deleted_at'] ?? null,
        ]);
        $target->map('posts', (string) $row['id'], 'posts', (string) $targetId);
        $report['imported']['posts'] = ($report['imported']['posts'] ?? 0) + 1;
    }

    foreach ($source->rows('post_categories') as $row) {
        $postId = $target->mapped('posts', (string) $row['post_id']);
        $categoryId = $target->mapped('categories', (string) $row['category_id']);
        if (!$postId || !$categoryId) {
            $report['notes'][] = 'Skipped post_category for unmapped relationship';
            continue;
        }
        $target->insertIgnore('post_categories', [
            'post_id' => $postId,
            'category_id' => $categoryId,
            'created_at' => $row['created_at'] ?? null,
        ]);
        $report['imported']['post_categories'] = ($report['imported']['post_categories'] ?? 0) + 1;
    }

    foreach ($source->rows('comments') as $row) {
        if ($target->mapped('comments', (string) $row['id']) !== null) {
            $report['skipped']['comments'] = ($report['skipped']['comments'] ?? 0) + 1;
            continue;
        }
        $postId = $target->mapped('posts', (string) $row['post_id']);
        if (!$postId) {
            $report['notes'][] = 'Skipped comment for unmapped post ' . $row['post_id'];
            continue;
        }
        $targetId = $target->insert('comments', [
            'platform_source_id' => $row['id'],
            'post_id' => $postId,
            'author_id' => (string) $row['author_id'],
            'author_user_id' => isset($row['author_user_id']) && $row['author_user_id'] ? $target->mapped('users', (string) $row['author_user_id']) : null,
            'author_name' => $row['author_name'],
            'author_image_url' => $row['author_image_url'] ?? null,
            'content' => $row['content'],
            'created_at' => $row['created_at'] ?? null,
        ]);
        $target->map('comments', (string) $row['id'], 'comments', (string) $targetId);
        $report['imported']['comments'] = ($report['imported']['comments'] ?? 0) + 1;
    }

    foreach ($source->rows('reactions') as $row) {
        $postId = $target->mapped('posts', (string) $row['post_id']);
        $userId = $target->mapped('users', (string) $row['user_id']);
        if (!$postId || !$userId) {
            $report['notes'][] = 'Skipped reaction for unmapped post/user';
            continue;
        }
        $targetId = $target->insertIgnore('reactions', [
            'platform_source_id' => $row['id'],
            'post_id' => $postId,
            'user_id' => $userId,
            'type' => $row['type'] ?? 'like',
            'created_at' => $row['created_at'] ?? null,
        ]);
        $target->map('reactions', (string) $row['id'], 'reactions', (string) $targetId);
        $report['imported']['reactions'] = ($report['imported']['reactions'] ?? 0) + 1;
    }

    foreach ($source->rows('feed_items_seen') as $row) {
        $sourceId = $target->mapped('feed_sources', (string) $row['source_id']);
        if (!$sourceId) {
            $report['notes'][] = 'Skipped feed_items_seen for unmapped source ' . $row['source_id'];
            continue;
        }
        $postId = isset($row['post_id']) && $row['post_id'] ? $target->mapped('posts', (string) $row['post_id']) : null;
        $targetId = $target->insertIgnore('feed_items_seen', [
            'platform_source_id' => $row['id'],
            'source_id' => $sourceId,
            'guid_hash' => $row['guid_hash'],
            'post_id' => $postId,
            'seen_at' => $row['seen_at'] ?? null,
        ]);
        $target->map('feed_items_seen', (string) $row['id'], 'feed_items_seen', (string) $targetId);
        $report['imported']['feed_items_seen'] = ($report['imported']['feed_items_seen'] ?? 0) + 1;
    }

    foreach ($source->rows('media_assets') as $row) {
        if ($target->mapped('media_assets', (string) $row['id']) !== null) {
            $report['skipped']['media_assets'] = ($report['skipped']['media_assets'] ?? 0) + 1;
            continue;
        }
        $targetId = $target->insert('media_assets', [
            'platform_source_id' => $row['id'],
            'url' => $row['url'] ?? null,
            'filename' => $row['filename'],
            'title' => $row['title'] ?? null,
            'mime_type' => $row['mime_type'],
            'alt_text' => $row['alt_text'] ?? null,
            'file_data' => $row['file_data'] ?? null,
            'deleted_at' => $row['deleted_at'] ?? null,
            'uploaded_at' => $row['uploaded_at'] ?? null,
        ]);
        $target->map('media_assets', (string) $row['id'], 'media_assets', (string) $targetId);
        $report['imported']['media_assets'] = ($report['imported']['media_assets'] ?? 0) + 1;
    }

    foreach ($source->rows('profile_photo_assets') as $row) {
        if ($target->mapped('profile_photo_assets', (string) $row['id']) !== null) {
            $report['skipped']['profile_photo_assets'] = ($report['skipped']['profile_photo_assets'] ?? 0) + 1;
            continue;
        }
        $userId = $target->mapped('users', (string) $row['user_id']);
        if (!$userId) {
            $report['notes'][] = 'Skipped profile photo for unmapped user ' . $row['user_id'];
            continue;
        }
        $targetId = $target->insert('profile_photo_assets', [
            'platform_source_id' => $row['id'],
            'user_id' => $userId,
            'url' => $row['url'] ?? null,
            'filename' => $row['filename'],
            'mime_type' => $row['mime_type'],
            'file_data' => $row['file_data'] ?? null,
            'uploaded_at' => $row['uploaded_at'] ?? null,
        ]);
        $target->map('profile_photo_assets', (string) $row['id'], 'profile_photo_assets', (string) $targetId);
        $report['imported']['profile_photo_assets'] = ($report['imported']['profile_photo_assets'] ?? 0) + 1;
    }

    foreach ($source->rows('site_settings') as $row) {
        $target->insertIgnore('site_settings', [
            'id' => $row['id'] ?? 1,
            'theme' => $row['theme'] ?? 'bauhaus',
            'palette' => $row['palette'] ?? 'bauhaus',
            'site_title' => $row['site_title'] ?? 'Augment Humankind',
            'hero_heading' => $row['hero_heading'] ?? '',
            'hero_subheading' => $row['hero_subheading'] ?? '',
            'about_heading' => $row['about_heading'] ?? '',
            'about_body' => $row['about_body'] ?? '',
            'copyright_line' => $row['copyright_line'] ?? '',
            'footer_credit' => $row['footer_credit'] ?? '',
            'cta_label' => $row['cta_label'] ?? '',
            'cta_href' => $row['cta_href'] ?? '/',
            'logo_url' => $row['logo_url'] ?? null,
            'logo_dark_url' => $row['logo_dark_url'] ?? null,
            'logo_layout' => $row['logo_layout'] ?? 'text_only',
            'default_theme_mode' => $row['default_theme_mode'] ?? 'system',
            'color_background' => $row['color_background'] ?? null,
            'color_foreground' => $row['color_foreground'] ?? null,
            'color_background_dark' => $row['color_background_dark'] ?? null,
            'color_foreground_dark' => $row['color_foreground_dark'] ?? null,
            'color_primary' => $row['color_primary'] ?? null,
            'color_primary_foreground' => $row['color_primary_foreground'] ?? null,
            'color_secondary' => $row['color_secondary'] ?? null,
            'color_secondary_foreground' => $row['color_secondary_foreground'] ?? null,
            'color_accent' => $row['color_accent'] ?? null,
            'color_accent_foreground' => $row['color_accent_foreground'] ?? null,
            'color_muted' => $row['color_muted'] ?? null,
            'color_muted_foreground' => $row['color_muted_foreground'] ?? null,
            'color_destructive' => $row['color_destructive'] ?? null,
            'color_destructive_foreground' => $row['color_destructive_foreground'] ?? null,
            'color_primary_dark' => $row['color_primary_dark'] ?? null,
            'color_primary_foreground_dark' => $row['color_primary_foreground_dark'] ?? null,
            'color_secondary_dark' => $row['color_secondary_dark'] ?? null,
            'color_secondary_foreground_dark' => $row['color_secondary_foreground_dark'] ?? null,
            'color_accent_dark' => $row['color_accent_dark'] ?? null,
            'color_accent_foreground_dark' => $row['color_accent_foreground_dark'] ?? null,
            'color_muted_dark' => $row['color_muted_dark'] ?? null,
            'color_muted_foreground_dark' => $row['color_muted_foreground_dark'] ?? null,
            'color_destructive_dark' => $row['color_destructive_dark'] ?? null,
            'color_destructive_foreground_dark' => $row['color_destructive_foreground_dark'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ]);
        $report['imported']['site_settings'] = ($report['imported']['site_settings'] ?? 0) + 1;
    }

    foreach ($source->rows('site_assets') as $row) {
        $target->insertIgnore('site_assets', [
            'asset_key' => $row['asset_key'],
            'filename' => $row['filename'] ?? null,
            'mime_type' => $row['mime_type'],
            'file_data' => $row['file_data'] ?? null,
            'data' => $row['file_data'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ]);
        $report['imported']['site_assets'] = ($report['imported']['site_assets'] ?? 0) + 1;
    }

    foreach ($source->rows('user_ai_vendor_settings') as $row) {
        if ($target->mapped('user_ai_vendor_settings', (string) $row['id']) !== null) {
            $report['skipped']['user_ai_vendor_settings'] = ($report['skipped']['user_ai_vendor_settings'] ?? 0) + 1;
            continue;
        }
        $userId = $target->mapped('users', (string) $row['user_id']);
        if (!$userId) {
            $report['notes'][] = 'Skipped AI vendor setting for unmapped user ' . $row['user_id'];
            continue;
        }
        $targetId = $target->insertIgnore('user_ai_vendor_settings', [
            'platform_source_id' => $row['id'],
            'user_id' => $userId,
            'vendor' => $row['vendor'],
            'profile_name' => $row['profile_name'] ?? 'Default',
            'endpoint_kind' => $row['endpoint_kind'] ?? null,
            'enabled' => $row['enabled'] ?? 0,
            'model' => $row['model'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ]);
        $target->map('user_ai_vendor_settings', (string) $row['id'], 'user_ai_vendor_settings', (string) $targetId);
        $report['imported']['user_ai_vendor_settings'] = ($report['imported']['user_ai_vendor_settings'] ?? 0) + 1;
    }

    foreach ($source->rows('user_ai_vendor_keys') as $row) {
        if ($target->mapped('user_ai_vendor_keys', (string) $row['id']) !== null) {
            $report['skipped']['user_ai_vendor_keys'] = ($report['skipped']['user_ai_vendor_keys'] ?? 0) + 1;
            continue;
        }
        $userId = $target->mapped('users', (string) $row['user_id']);
        if (!$userId) {
            $report['notes'][] = 'Skipped AI vendor key for unmapped user ' . $row['user_id'];
            continue;
        }
        $targetId = $target->insertIgnore('user_ai_vendor_keys', [
            'platform_source_id' => $row['id'],
            'user_id' => $userId,
            'vendor' => $row['vendor'],
            'encrypted_api_key' => $row['encrypted_api_key'],
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ]);
        $target->map('user_ai_vendor_keys', (string) $row['id'], 'user_ai_vendor_keys', (string) $targetId);
        $report['imported']['user_ai_vendor_keys'] = ($report['imported']['user_ai_vendor_keys'] ?? 0) + 1;
    }

    foreach ($source->rows('platform_connections') as $row) {
        if ($target->mapped('platform_connections', (string) $row['id']) !== null) {
            $report['skipped']['platform_connections'] = ($report['skipped']['platform_connections'] ?? 0) + 1;
            continue;
        }
        $userId = $target->mapped('users', (string) $row['user_id']);
        if (!$userId) {
            $report['notes'][] = 'Skipped platform connection for unmapped user ' . $row['user_id'];
            continue;
        }
        $targetId = $target->insertIgnore('platform_connections', [
            'platform_source_id' => $row['id'],
            'user_id' => $userId,
            'platform' => $row['platform'],
            'encrypted_access_token' => $row['encrypted_access_token'] ?? null,
            'encrypted_refresh_token' => $row['encrypted_refresh_token'] ?? null,
            'expires_at' => $row['expires_at'] ?? null,
            'metadata' => $row['metadata'] ?? null,
            'enabled' => $row['enabled'] ?? 1,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ]);
        $target->map('platform_connections', (string) $row['id'], 'platform_connections', (string) $targetId);
        $report['imported']['platform_connections'] = ($report['imported']['platform_connections'] ?? 0) + 1;
    }

    foreach ($source->rows('platform_oauth_apps') as $row) {
        if ($target->mapped('platform_oauth_apps', (string) $row['id']) !== null) {
            $report['skipped']['platform_oauth_apps'] = ($report['skipped']['platform_oauth_apps'] ?? 0) + 1;
            continue;
        }
        $targetId = $target->insertIgnore('platform_oauth_apps', [
            'platform_source_id' => $row['id'],
            'platform' => $row['platform'],
            'encrypted_client_id' => $row['encrypted_client_id'] ?? null,
            'encrypted_client_secret' => $row['encrypted_client_secret'] ?? null,
            'blog_url' => $row['blog_url'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ]);
        $target->map('platform_oauth_apps', (string) $row['id'], 'platform_oauth_apps', (string) $targetId);
        $report['imported']['platform_oauth_apps'] = ($report['imported']['platform_oauth_apps'] ?? 0) + 1;
    }

    foreach ($source->rows('post_syndications') as $row) {
        $postId = $target->mapped('posts', (string) $row['post_id']);
        $connectionId = $target->mapped('platform_connections', (string) $row['platform_connection_id']);
        if (!$postId || !$connectionId) {
            $report['notes'][] = 'Skipped post syndication for unmapped post/connection';
            continue;
        }
        $targetId = $target->insertIgnore('post_syndications', [
            'platform_source_id' => $row['id'],
            'post_id' => $postId,
            'platform_connection_id' => $connectionId,
            'external_id' => $row['external_id'] ?? null,
            'external_url' => $row['external_url'] ?? null,
            'status' => $row['status'] ?? 'pending',
            'error_message' => $row['error_message'] ?? null,
            'synced_at' => $row['synced_at'] ?? null,
            'created_at' => $row['created_at'] ?? null,
        ]);
        $target->map('post_syndications', (string) $row['id'], 'post_syndications', (string) $targetId);
        $report['imported']['post_syndications'] = ($report['imported']['post_syndications'] ?? 0) + 1;
    }

    foreach ($source->rows('art_pieces') as $row) {
        if ($target->mapped('art_pieces', (string) $row['id']) !== null) {
            $report['skipped']['art_pieces'] = ($report['skipped']['art_pieces'] ?? 0) + 1;
            continue;
        }
        $ownerUserId = $target->mapped('users', (string) $row['owner_user_id']);
        if (!$ownerUserId) {
            $report['notes'][] = 'Skipped art piece for unmapped owner ' . $row['owner_user_id'];
            continue;
        }
        $targetId = $target->insert('art_pieces', [
            'platform_source_id' => $row['id'],
            'owner_user_id' => $ownerUserId,
            'title' => $row['title'],
            'prompt' => $row['prompt'] ?? null,
            'engine' => $row['engine'] ?? 'p5',
            'status' => $row['status'] ?? 'active',
            'current_version_id' => null,
            'platform_current_version_source_id' => $row['current_version_id'] ?? null,
            'thumbnail_url' => $row['thumbnail_url'] ?? null,
            'description' => $row['description'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'deleted_at' => $row['deleted_at'] ?? null,
        ]);
        $target->map('art_pieces', (string) $row['id'], 'art_pieces', (string) $targetId);
        $report['imported']['art_pieces'] = ($report['imported']['art_pieces'] ?? 0) + 1;
    }

    foreach ($source->rows('art_piece_versions') as $row) {
        if ($target->mapped('art_piece_versions', (string) $row['id']) !== null) {
            $report['skipped']['art_piece_versions'] = ($report['skipped']['art_piece_versions'] ?? 0) + 1;
            continue;
        }
        $pieceId = $target->mapped('art_pieces', (string) $row['art_piece_id']);
        if (!$pieceId) {
            $report['notes'][] = 'Skipped art piece version for unmapped piece ' . $row['art_piece_id'];
            continue;
        }
        $targetId = $target->insert('art_piece_versions', [
            'platform_source_id' => $row['id'],
            'art_piece_id' => $pieceId,
            'prompt' => $row['prompt'] ?? null,
            'structured_spec' => $row['structured_spec'] ?? null,
            'html_code' => $row['html_code'] ?? null,
            'css_code' => $row['css_code'] ?? null,
            'generated_code' => $row['generated_code'] ?? null,
            'engine' => $row['engine'] ?? 'p5',
            'generation_vendor' => $row['generation_vendor'] ?? null,
            'generation_model' => $row['generation_model'] ?? null,
            'validation_status' => $row['validation_status'] ?? 'validated',
            'generation_attempt_count' => $row['generation_attempt_count'] ?? 1,
            'notes' => $row['notes'] ?? null,
            'created_at' => $row['created_at'] ?? null,
        ]);
        $target->map('art_piece_versions', (string) $row['id'], 'art_piece_versions', (string) $targetId);
        $report['imported']['art_piece_versions'] = ($report['imported']['art_piece_versions'] ?? 0) + 1;
    }

    foreach ($source->rows('art_pieces') as $row) {
        if (empty($row['current_version_id'])) {
            continue;
        }
        $pieceId = $target->mapped('art_pieces', (string) $row['id']);
        $versionId = $target->mapped('art_piece_versions', (string) $row['current_version_id']);
        if (!$pieceId || !$versionId) {
            $report['notes'][] = 'Skipped current version pointer for art piece ' . $row['id'];
            continue;
        }
        $stmt = $target->pdo->prepare('UPDATE art_pieces SET current_version_id = ? WHERE id = ?');
        $stmt->execute([$versionId, $pieceId]);
        $report['imported']['art_piece_current_versions'] = ($report['imported']['art_piece_current_versions'] ?? 0) + 1;
    }

    foreach ($source->rows('exhibits') as $row) {
        if ($target->mapped('exhibits', (string) $row['id']) !== null) {
            $report['skipped']['exhibits'] = ($report['skipped']['exhibits'] ?? 0) + 1;
            continue;
        }
        $targetId = $target->insert('platform_collections', [
            'platform_source_id' => $row['id'],
            'slug' => $row['slug'],
            'name' => $row['name'],
            'description' => $row['description'] ?? null,
            'artist_statement' => $row['artist_statement'] ?? null,
            'biography' => $row['biography'] ?? null,
            'rows' => $row['rows'] ?? 1,
            'cols' => $row['cols'] ?? 1,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'deleted_at' => $row['deleted_at'] ?? null,
        ]);
        $target->map('exhibits', (string) $row['id'], 'platform_collections', (string) $targetId);
        $report['imported']['exhibits'] = ($report['imported']['exhibits'] ?? 0) + 1;
    }

    foreach ($source->rows('piece_exhibits') as $row) {
        $exhibitId = $target->mapped('exhibits', (string) $row['exhibit_id']);
        $pieceId = $target->mapped('art_pieces', (string) $row['art_piece_id']);
        if (!$exhibitId || !$pieceId) {
            $report['notes'][] = 'Skipped piece exhibit membership for unmapped exhibit/piece';
            continue;
        }
        $target->insertIgnore('platform_collection_items', [
            'collection_id' => $exhibitId,
            'item_type' => 'art_piece',
            'item_id' => $pieceId,
            'source_id' => $row['art_piece_id'],
            'sort_order' => 0,
        ]);
        $report['imported']['piece_exhibits'] = ($report['imported']['piece_exhibits'] ?? 0) + 1;
    }

    foreach ($source->rows('media_asset_exhibits') as $row) {
        $exhibitId = $target->mapped('exhibits', (string) $row['exhibit_id']);
        $mediaId = $target->mapped('media_assets', (string) $row['media_asset_id']);
        if (!$exhibitId || !$mediaId) {
            $report['notes'][] = 'Skipped media exhibit membership for unmapped exhibit/media';
            continue;
        }
        $target->insertIgnore('platform_collection_items', [
            'collection_id' => $exhibitId,
            'item_type' => 'media_asset',
            'item_id' => $mediaId,
            'source_id' => $row['media_asset_id'],
            'sort_order' => 0,
        ]);
        $report['imported']['media_asset_exhibits'] = ($report['imported']['media_asset_exhibits'] ?? 0) + 1;
    }

    $target->pdo->commit();
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $e) {
    $target->pdo->rollBack();
    fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}
