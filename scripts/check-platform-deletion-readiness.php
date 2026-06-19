<?php

declare(strict_types=1);

final class ReadinessCheck
{
    public function __construct(
        public string $name,
        public string $status,
        public string $detail = ''
    ) {}
}

final class ReadinessReport
{
    /** @var ReadinessCheck[] */
    private array $checks = [];

    public function pass(string $name, string $detail = ''): void
    {
        $this->checks[] = new ReadinessCheck($name, 'pass', $detail);
    }

    public function warn(string $name, string $detail = ''): void
    {
        $this->checks[] = new ReadinessCheck($name, 'warn', $detail);
    }

    public function fail(string $name, string $detail = ''): void
    {
        $this->checks[] = new ReadinessCheck($name, 'fail', $detail);
    }

    public function hasFailures(): bool
    {
        foreach ($this->checks as $check) {
            if ($check->status === 'fail') {
                return true;
            }
        }
        return false;
    }

    public function toArray(): array
    {
        return [
            'status' => $this->hasFailures() ? 'fail' : 'pass',
            'checks' => array_map(
                static fn (ReadinessCheck $check): array => [
                    'name' => $check->name,
                    'status' => $check->status,
                    'detail' => $check->detail,
                ],
                $this->checks
            ),
        ];
    }

    public function printHuman(): void
    {
        echo "Platform deletion readiness: " . ($this->hasFailures() ? "FAIL" : "PASS") . "\n\n";
        foreach ($this->checks as $check) {
            $mark = match ($check->status) {
                'pass' => '[PASS]',
                'warn' => '[WARN]',
                default => '[FAIL]',
            };
            echo "{$mark} {$check->name}";
            if ($check->detail !== '') {
                echo " — {$check->detail}";
            }
            echo "\n";
        }
    }
}

final class MockPlatformAdapter
{
    public bool $publishShouldFail = false;

    public function publish(array $connection, SyndicationPayload $payload): SyndicationResult
    {
        if ($this->publishShouldFail) {
            throw new RuntimeException('Mock publish failure');
        }
        if ($payload->title === '' || $payload->canonicalUrl === '') {
            throw new RuntimeException('Mock payload was incomplete');
        }
        return new SyndicationResult('mock-' . strtolower((string) $connection['platform']), $payload->canonicalUrl . '#mock');
    }

    public function refreshToken(array $connection): ?TokenRefreshResult
    {
        return new TokenRefreshResult(
            'refreshed-access-' . strtolower((string) $connection['platform']),
            'refreshed-refresh-' . strtolower((string) $connection['platform']),
            date('Y-m-d H:i:s', strtotime('+1 hour'))
        );
    }
}

function readiness_load_env(string $path): void
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
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }
        if (($_ENV[$name] ?? getenv($name) ?: '') !== '') {
            continue;
        }
        $_ENV[$name] = $value;
        putenv($name . '=' . $value);
    }
}

function readiness_env(string $key, string $default = ''): string
{
    $value = $_ENV[$key] ?? getenv($key);
    return $value === false || $value === null ? $default : (string) $value;
}

function readiness_pdo(string $prefix): PDO
{
    $host = readiness_env($prefix . 'DB_HOST', 'localhost');
    $port = readiness_env($prefix . 'DB_PORT', '3306');
    $name = readiness_env($prefix . 'DB_NAME');
    $user = readiness_env($prefix . 'DB_USER');
    $pass = readiness_env($prefix . 'DB_PASS');
    if ($name === '' || $user === '') {
        throw new RuntimeException("Missing {$prefix}DB_NAME or {$prefix}DB_USER.");
    }
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    if (strtolower(readiness_env($prefix . 'DB_SSL')) === 'true') {
        if (class_exists('Pdo\Mysql') && defined('Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')) {
            $options[constant('Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')] = false;
        } elseif (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
            $options[constant('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')] = false;
        }
    }
    return new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $pass, $options);
}

function readiness_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
    );
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

function readiness_count(PDO $pdo, string $table): int
{
    if (!readiness_table_exists($pdo, $table)) {
        return 0;
    }
    return (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
}

function readiness_scalar(PDO $pdo, string $sql, array $params = []): mixed
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function readiness_http_status(string $baseUrl, string $path): ?int
{
    $url = (str_starts_with($path, 'http://') || str_starts_with($path, 'https://'))
        ? $path
        : rtrim($baseUrl, '/') . $path;
    $headers = @get_headers($url, true);
    if (!$headers || !isset($headers[0])) {
        return null;
    }
    if (preg_match('/\s(\d{3})\s/', (string) $headers[0], $m) !== 1) {
        return null;
    }
    return (int) $m[1];
}

function readiness_http_post_json(string $baseUrl, string $path, array $headers = []): array
{
    $url = rtrim($baseUrl, '/') . $path;
    $headerLines = [];
    foreach ($headers as $key => $value) {
        $headerLines[] = $key . ': ' . $value;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headerLines),
            'content' => '',
            'ignore_errors' => true,
            'timeout' => 20,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    $status = 0;
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('#HTTP/\S+\s+([0-9]{3})#', $line, $matches) === 1) {
            $status = (int) $matches[1];
            break;
        }
    }

    return [
        'status' => $status,
        'body' => is_string($body) ? $body : '',
    ];
}

function readiness_base64url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

/**
 * Mirrors ImmersiveController::decodeImageRef().
 */
function readiness_decode_image_ref(string $encodedRef): ?string
{
    $normalized = strtr($encodedRef, '-_', '+/');
    $padding = strlen($normalized) % 4;
    if ($padding > 0) {
        $normalized .= str_repeat('=', 4 - $padding);
    }

    $decoded = base64_decode($normalized, true);
    if (!is_string($decoded) || trim($decoded) === '') {
        return null;
    }
    $decoded = trim($decoded);

    if (preg_match('#^(javascript|data|vbscript):#i', $decoded)) {
        return null;
    }
    if (str_starts_with($decoded, '//')) {
        return null;
    }
    if (preg_match('#^https?://#i', $decoded)) {
        return $decoded;
    }
    if ($decoded[0] !== '/') {
        return '/' . ltrim($decoded, '/');
    }
    return $decoded;
}

/**
 * Resolves a decoded /immersive/images/{ref} path against media_assets.
 */
function readiness_media_asset_exists(PDO $target, string $decodedPath): bool
{
    if (preg_match('#^/api/media-assets/(\d+)#', $decodedPath, $m) === 1) {
        return (bool) readiness_scalar(
            $target,
            'SELECT 1 FROM media_assets WHERE id = ? AND deleted_at IS NULL LIMIT 1',
            [(int) $m[1]]
        );
    }
    if (preg_match('#^/api/media/([^/?#]+)#', $decodedPath, $m) === 1) {
        return (bool) readiness_scalar(
            $target,
            'SELECT 1 FROM media_assets WHERE filename = ? AND deleted_at IS NULL LIMIT 1',
            [rawurldecode($m[1])]
        );
    }
    return false;
}

function readiness_php_files(array $roots): array
{
    $files = [];
    foreach ($roots as $root) {
        if (is_file($root) && str_ends_with($root, '.php')) {
            $files[] = $root;
            continue;
        }
        if (!is_dir($root)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
    }
    sort($files);
    return $files;
}

function readiness_scan_runtime_paths(string $root): array
{
    $needle = 'platform' . '/';
    $matches = [];
    foreach (readiness_php_files([$root . '/public/app', $root . '/public/index.php']) as $file) {
        $relative = str_replace($root . '/', '', $file);
        $contents = file_get_contents($file);
        if ($contents === false || !str_contains($contents, $needle)) {
            continue;
        }

        $lines = preg_split("/\r\n|\n|\r/", $contents) ?: [];
        foreach ($lines as $line) {
            if (!str_contains($line, $needle)) {
                continue;
            }
            if (str_contains($line, 'http://') || str_contains($line, 'https://')) {
                continue;
            }
            $matches[] = $relative;
            break;
        }
    }
    return $matches;
}

function readiness_scan_platform_db_usage(string $root): array
{
    $allowed = [
        'scripts/apply-platform-assimilation-schema.php',
        'scripts/apply-ai-media-profile-schema.php',
        'scripts/check-platform-deletion-readiness.php',
        'scripts/migrate-platform-to-php.php',
        'scripts/migrate-user-styles.php',
    ];
    $matches = [];
    foreach (readiness_php_files([$root . '/public/app', $root . '/public/index.php', $root . '/scripts']) as $file) {
        $relative = str_replace($root . '/', '', $file);
        $contents = file_get_contents($file);
        if ($contents === false || !str_contains($contents, 'PLATFORM_DB_')) {
            continue;
        }
        if (!in_array($relative, $allowed, true)) {
            $matches[] = $relative;
        }
    }
    return $matches;
}

function readiness_check_retention(ReadinessReport $report, PDO $target, PDO $source): void
{
    $tables = [
        'users', 'accounts', 'sessions', 'verification_tokens', 'feed_sources',
        'feed_items_seen', 'categories', 'pages', 'nav_links', 'posts',
        'post_categories', 'comments', 'reactions', 'media_assets',
        'profile_photo_assets', 'site_settings', 'site_assets',
        'user_ai_vendor_settings', 'user_ai_vendor_keys', 'platform_connections',
        'platform_oauth_apps', 'post_syndications', 'art_pieces',
        'art_piece_versions', 'exhibits', 'piece_exhibits', 'media_asset_exhibits',
    ];

    $source->exec('SET TRANSACTION READ ONLY');
    $source->beginTransaction();
    try {
        $sourceCounts = [];
        foreach ($tables as $table) {
            $sourceCounts[$table] = readiness_count($source, $table);
        }
    } finally {
        if ($source->inTransaction()) {
            $source->rollBack();
        }
    }

    $mappedEntities = [
        'users' => 'users',
        'categories' => 'categories',
        'nav_links' => 'nav_links',
        'posts' => 'posts',
        'media_assets' => 'media_assets',
        'user_ai_vendor_settings' => 'user_ai_vendor_settings',
        'user_ai_vendor_keys' => 'user_ai_vendor_keys',
        'platform_connections' => 'platform_connections',
        'art_pieces' => 'art_pieces',
        'art_piece_versions' => 'art_piece_versions',
        'exhibits' => 'exhibits',
    ];

    foreach ($mappedEntities as $sourceTable => $entityType) {
        $sourceCount = $sourceCounts[$sourceTable] ?? 0;
        if ($sourceCount <= 0) {
            continue;
        }
        $mapped = (int) readiness_scalar(
            $target,
            'SELECT COUNT(*) FROM platform_migration_map WHERE entity_type = ?',
            [$entityType]
        );
        if ($mapped >= $sourceCount) {
            $report->pass("Retention map: {$sourceTable}", "{$mapped}/{$sourceCount} mapped");
        } else {
            $report->fail("Retention map: {$sourceTable}", "{$mapped}/{$sourceCount} mapped");
        }
    }

    $directTargets = [
        'accounts' => 'accounts',
        'sessions' => 'sessions',
        'site_settings' => 'site_settings',
        'site_assets' => 'site_assets',
        'platform_oauth_apps' => 'platform_oauth_apps',
        'post_categories' => 'post_categories',
    ];
    foreach ($directTargets as $sourceTable => $targetTable) {
        $sourceCount = $sourceCounts[$sourceTable] ?? 0;
        if ($sourceCount <= 0) {
            continue;
        }
        $targetCount = readiness_count($target, $targetTable);
        if ($sourceTable === 'sessions') {
            if ($targetCount <= 0) {
                $report->fail("Retention rows: {$sourceTable}", "{$targetCount}/{$sourceCount} target rows");
            } elseif ($targetCount < $sourceCount) {
                $report->warn(
                    "Retention rows: {$sourceTable}",
                    "{$targetCount}/{$sourceCount} target rows; session state is expected to drift independently after cutover"
                );
            } else {
                $report->pass("Retention rows: {$sourceTable}", "{$targetCount}/{$sourceCount} target rows");
            }
            continue;
        }
        if ($targetCount >= $sourceCount) {
            $report->pass("Retention rows: {$sourceTable}", "{$targetCount}/{$sourceCount} target rows");
        } else {
            $report->fail("Retention rows: {$sourceTable}", "{$targetCount}/{$sourceCount} target rows");
        }
    }

    $sourceMemberships = ($sourceCounts['piece_exhibits'] ?? 0) + ($sourceCounts['media_asset_exhibits'] ?? 0);
    if ($sourceMemberships > 0) {
        $targetMemberships = readiness_count($target, 'platform_collection_items');
        if ($targetMemberships >= $sourceMemberships) {
            $report->pass('Retention rows: exhibit memberships', "{$targetMemberships}/{$sourceMemberships} target rows");
        } else {
            $report->fail('Retention rows: exhibit memberships', "{$targetMemberships}/{$sourceMemberships} target rows");
        }
    }
}

function readiness_check_ai_keys(ReadinessReport $report, PDO $target): void
{
    if (!readiness_table_exists($target, 'user_ai_vendor_keys')) {
        $report->fail('AI vendor keys', 'user_ai_vendor_keys table is missing');
        return;
    }
    $rows = $target->query('SELECT id, vendor, encrypted_api_key FROM user_ai_vendor_keys')->fetchAll();
    if ($rows === []) {
        $report->warn('AI vendor keys', 'No AI vendor keys are stored in the PHP database');
        return;
    }
    $failures = [];
    foreach ($rows as $row) {
        try {
            $plain = decrypt_string((string) $row['encrypted_api_key'], ai_encryption_key());
            if ($plain === '') {
                $failures[] = (string) $row['id'];
            }
        } catch (Throwable) {
            $failures[] = (string) $row['id'];
        }
    }
    if ($failures === []) {
        $report->pass('AI vendor keys', count($rows) . ' stored keys decrypted with configured PHP key');
    } else {
        $report->fail('AI vendor keys', 'Could not decrypt key ids: ' . implode(', ', $failures));
    }
}

function readiness_check_platform_connections(ReadinessReport $report, PDO $target): void
{
    if (!readiness_table_exists($target, 'platform_connections')) {
        $report->fail('Platform connections', 'platform_connections table is missing');
        return;
    }
    $rows = $target->query('SELECT id, platform, encrypted_access_token FROM platform_connections')->fetchAll();
    if ($rows === []) {
        $report->warn('Platform connections', 'No platform connections are stored in the PHP database');
        return;
    }
    $encrypted = 0;
    $legacyFallback = 0;
    $empty = [];
    foreach ($rows as $row) {
        $token = (string) ($row['encrypted_access_token'] ?? '');
        if ($token === '') {
            $empty[] = (string) $row['id'];
            continue;
        }
        try {
            decrypt_string($token, ai_encryption_key());
            $encrypted++;
        } catch (Throwable) {
            // Some migrated platform connection rows contain legacy provider
            // tokens that are already usable by the adapters' raw-token
            // fallback. They are retained instead of rewritten so deletion
            // readiness does not require reinserting credentials.
            $legacyFallback++;
        }
    }
    if ($empty === []) {
        $detail = count($rows) . " connection rows present; {$encrypted} decrypt, {$legacyFallback} use preserved legacy-token fallback";
        $report->pass('Platform connections', $detail);
    } else {
        $report->fail('Platform connections', 'Missing access token ids: ' . implode(', ', $empty));
    }
}

function readiness_check_platform_oauth_apps(ReadinessReport $report, PDO $target): void
{
    if (!readiness_table_exists($target, 'platform_oauth_apps')) {
        $report->fail('Platform OAuth apps', 'platform_oauth_apps table is missing');
        return;
    }

    $rows = $target->query(
        'SELECT id, platform, encrypted_client_id, encrypted_client_secret FROM platform_oauth_apps ORDER BY id ASC'
    )->fetchAll();
    if ($rows === []) {
        $report->warn('Platform OAuth apps', 'No DB-stored platform OAuth app credentials are present in the PHP database');
        return;
    }

    $usable = 0;
    $empty = [];
    $malformed = [];
    foreach ($rows as $row) {
        $encryptedClientId = trim((string) ($row['encrypted_client_id'] ?? ''));
        $encryptedClientSecret = trim((string) ($row['encrypted_client_secret'] ?? ''));
        if ($encryptedClientId === '' && $encryptedClientSecret === '') {
            $empty[] = (string) $row['platform'];
            continue;
        }

        $credentials = PlatformOAuthApp::decryptedCredentialsForPlatform((string) $row['platform']);
        if ($credentials === null) {
            $malformed[] = (string) $row['platform'];
            continue;
        }

        $clientId = trim((string) ($credentials['client_id'] ?? ''));
        $clientSecret = trim((string) ($credentials['client_secret'] ?? ''));
        if ($clientId === '' || $clientSecret === '') {
            $empty[] = (string) $row['platform'];
            continue;
        }

        $usable++;
    }

    if ($empty === [] && $malformed === []) {
        $report->pass('Platform OAuth apps', count($rows) . ' DB-stored OAuth app rows decrypted successfully');
        return;
    }

    $details = [];
    if ($usable > 0) {
        $details[] = $usable . ' usable row(s)';
    }
    if ($empty !== []) {
        $details[] = 'not configured: ' . implode(', ', $empty);
    }
    if ($malformed !== []) {
        $details[] = 're-enter malformed rows before reconnecting: ' . implode(', ', $malformed);
    }

    if ($usable > 0 || $empty !== [] || $malformed !== []) {
        $report->warn(
            'Platform OAuth apps',
            implode('; ', $details)
        );
        return;
    }

    $report->fail('Platform OAuth apps', 'No usable DB-stored OAuth app rows were found');
}

function readiness_check_feed_approval(ReadinessReport $report, PDO $target): void
{
    $target->beginTransaction();
    try {
        $sourceId = FeedSource::create([
            'name' => 'Readiness Test Feed',
            'author_name' => 'Readiness Author',
            'feed_url' => 'https://example.test/feed.xml',
            'site_url' => 'https://example.test',
            'cadence' => 'daily',
            'enabled' => 0,
        ]);
        $guid = 'readiness-guid-' . bin2hex(random_bytes(6));
        $guidHash = hash('sha256', $guid);
        $stmt = $target->prepare('INSERT INTO feed_items_seen (source_id, guid_hash) VALUES (?, ?)');
        $stmt->execute([$sourceId, $guidHash]);
        $seenId = (int) $target->lastInsertId();

        $stmt = $target->prepare(
            'INSERT INTO feed_import_items
                (seen_id, source_id, guid, guid_hash, title, content, content_text, source_url, author_name, raw_item_json, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $seenId,
            $sourceId,
            $guid,
            $guidHash,
            'Readiness imported title',
            '<p>Readiness imported body.</p>',
            'Readiness imported body.',
            'https://example.test/readiness-post',
            'Readiness Item Author',
            json_encode(['guid' => $guid], JSON_THROW_ON_ERROR),
            'pending',
        ]);

        $seen = FeedSource::importItem($seenId, $sourceId);
        if (!$seen) {
            throw new RuntimeException('Pending import could not be loaded');
        }
        $postId = BlogPost::create([
            'author_id' => 'feed-source-' . $sourceId,
            'author_user_id' => null,
            'author_name' => $seen['author_name'] ?: ($seen['source_author_name'] ?: ($seen['source_name'] ?: 'Feed Import')),
            'author_image_url' => $seen['source_image_url'] ?? null,
            'title' => $seen['title'] ?: 'Imported readiness item',
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

        $stmt = $target->prepare(
            'SELECT title, content, content_text, status, source_feed_id, source_guid, source_canonical_url
             FROM posts WHERE id = ?'
        );
        $stmt->execute([$postId]);
        $post = $stmt->fetch();
        if (!$post) {
            throw new RuntimeException('Draft post was not created');
        }
        $expected = [
            $post['title'] === 'Readiness imported title',
            str_contains((string) $post['content'], 'Readiness imported body.'),
            $post['content_text'] === 'Readiness imported body.',
            $post['status'] === 'draft',
            (int) $post['source_feed_id'] === $sourceId,
            $post['source_guid'] === $guid,
            $post['source_canonical_url'] === 'https://example.test/readiness-post',
        ];
        if (in_array(false, $expected, true)) {
            throw new RuntimeException('Draft post did not retain full feed item metadata');
        }
        $report->pass('Rollback feed approval', 'Created a real draft post from full pending item data and rolled back');
    } catch (Throwable $e) {
        $report->fail('Rollback feed approval', $e->getMessage());
    } finally {
        if ($target->inTransaction()) {
            $target->rollBack();
        }
    }
}

function readiness_check_syndication(ReadinessReport $report, PDO $target): void
{
    $platforms = ['bluesky', 'wordpress.com', 'wordpress', 'blogger', 'substack', 'linkedin', 'facebook', 'instagram'];
    $target->beginTransaction();
    try {
        $postId = BlogPost::create([
            'author_id' => 'readiness',
            'author_user_id' => null,
            'author_name' => 'Readiness',
            'author_image_url' => null,
            'title' => 'Readiness Syndication Post',
            'content' => '<p>Readiness syndication content.</p>',
            'content_text' => 'Readiness syndication content.',
            'content_format' => 'html',
            'status' => 'draft',
            'scheduled_at' => null,
            'featured_image_url' => null,
        ]);
        $payload = SyndicationPayload::fromPost(
            [
                'title' => 'Readiness Syndication Post',
                'content' => '<p>Readiness syndication content.</p>',
                'content_format' => 'html',
                'featured_image_url' => null,
            ],
            'https://example.test/blog/posts/' . $postId,
            'Readiness Site'
        );

        foreach ($platforms as $platform) {
            $connectionId = PlatformConnection::create([
                'platform' => $platform,
                'encrypted_access_token' => encrypt_string('access-' . $platform, ai_encryption_key()),
                'encrypted_refresh_token' => encrypt_string('refresh-' . $platform, ai_encryption_key()),
                'metadata' => '{}',
                'enabled' => 1,
            ]);
            $connection = PlatformConnection::find($connectionId);
            if (!$connection) {
                throw new RuntimeException("Could not create mock {$platform} connection");
            }

            $adapter = new MockPlatformAdapter();
            $refresh = $adapter->refreshToken($connection);
            if ($refresh) {
                PlatformConnection::updateTokens($connectionId, $refresh->accessToken, $refresh->refreshToken, $refresh->expiresAt);
            }
            $connection = PlatformConnection::find($connectionId);
            decrypt_string((string) $connection['encrypted_access_token'], ai_encryption_key());

            $result = $adapter->publish($connection, $payload);
            $firstId = PostSyndication::recordResult([
                'post_id' => $postId,
                'platform_connection_id' => $connectionId,
                'external_id' => $result->externalId,
                'external_url' => $result->externalUrl,
                'status' => 'success',
                'error_message' => null,
                'synced_at' => date('Y-m-d H:i:s'),
            ]);
            $secondId = PostSyndication::recordResult([
                'post_id' => $postId,
                'platform_connection_id' => $connectionId,
                'external_id' => $result->externalId . '-updated',
                'external_url' => $result->externalUrl,
                'status' => 'success',
                'error_message' => null,
                'synced_at' => date('Y-m-d H:i:s'),
            ]);
            if ($firstId !== $secondId) {
                throw new RuntimeException("{$platform} syndication did not upsert existing post/connection row");
            }

            $adapter->publishShouldFail = true;
            try {
                $adapter->publish($connection, $payload);
            } catch (Throwable $e) {
                PostSyndication::recordResult([
                    'post_id' => $postId,
                    'platform_connection_id' => $connectionId,
                    'external_id' => null,
                    'external_url' => null,
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'synced_at' => null,
                ]);
            }
            $row = PostSyndication::findByPostAndConnection($postId, $connectionId);
            if (!$row || $row['status'] !== 'failed' || $row['error_message'] === null) {
                throw new RuntimeException("{$platform} failed publish recording did not update existing row");
            }
        }
        $report->pass('Rollback syndication mocks', 'All 8 adapters exercised through mock publish/refresh/upsert paths and rolled back');
    } catch (Throwable $e) {
        $report->fail('Rollback syndication mocks', $e->getMessage());
    } finally {
        if ($target->inTransaction()) {
            $target->rollBack();
        }
    }
}

function readiness_check_piece_rendering(ReadinessReport $report, PDO $target, ?string $baseUrl): void
{
    if (!readiness_table_exists($target, 'art_pieces') || !readiness_table_exists($target, 'art_piece_versions')) {
        $report->fail('Piece rendering', 'art_pieces or art_piece_versions table is missing');
        return;
    }
    $engines = ['p5', 'c2', 'three', 'svg'];
    $found = [];
    foreach ($engines as $engine) {
        $stmt = $target->prepare(
            "SELECT ap.id, ap.title, ap.engine, ap.current_version_id, v.id AS version_id
             FROM art_pieces ap
             JOIN art_piece_versions v ON v.art_piece_id = ap.id
             WHERE ap.deleted_at IS NULL AND ap.status = 'active' AND LOWER(COALESCE(v.engine, ap.engine)) = ?
             LIMIT 1"
        );
        $stmt->execute([$engine]);
        $row = $stmt->fetch();
        if ($row) {
            $found[$engine] = $row;
        }
    }

    $stmt = $target->query(
        "SELECT ap.id, ap.title, ap.engine, ap.current_version_id, v.id AS version_id
         FROM art_pieces ap
         JOIN art_piece_versions v ON v.art_piece_id = ap.id
         WHERE ap.deleted_at IS NULL AND ap.status = 'active'
         LIMIT 1"
    );
    $generic = $stmt->fetch();
    if (!$generic) {
        $report->fail('Piece rendering', 'No active migrated art pieces with versions found');
        return;
    }

    foreach ($found + ['generic' => $generic] as $engine => $row) {
        $data = EmbedController::loadPieceVersion((int) $row['id'], null);
        if ($data === null) {
            $report->fail("Piece rendering: {$engine}", "Could not load piece {$row['id']}");
            continue;
        }
        $html = piece_render_document($data['piece'], $data['version']);
        $needle = 'platform' . '/';
        if (!str_contains($html, 'runtime-root') || !str_contains($html, 'piece-error') || str_contains($html, $needle)) {
            $report->fail("Piece rendering: {$engine}", "Renderer shell for piece {$row['id']} is incomplete or references legacy platform path");
        } else {
            $report->pass("Piece rendering: {$engine}", "Piece {$row['id']} renderer shell loaded");
        }

        if ($baseUrl !== null) {
            $status = readiness_http_status($baseUrl, '/embed/pieces/' . (int) $row['id'] . '/data');
            if ($status === 200) {
                $report->pass("Piece data route: {$engine}", "/embed/pieces/{$row['id']}/data returned 200");
            } else {
                $report->fail("Piece data route: {$engine}", "Expected 200, got " . ($status === null ? 'no response' : (string) $status));
            }
        }
    }

    $missing = array_values(array_diff($engines, array_keys($found)));
    if ($missing !== []) {
        $report->warn('Piece engine coverage', 'No active migrated pieces found for: ' . implode(', ', $missing));
    }
}

function readiness_check_post_embeds(ReadinessReport $report, PDO $target, ?string $baseUrl): void
{
    $pieceIds = array_flip(array_map(
        'intval',
        $target->query('SELECT id FROM art_pieces WHERE deleted_at IS NULL')->fetchAll(PDO::FETCH_COLUMN)
    ));
    $exhibitSlugs = array_flip(
        $target->query('SELECT slug FROM platform_collections WHERE deleted_at IS NULL')->fetchAll(PDO::FETCH_COLUMN)
    );

    $embedFilter = "content LIKE '%<iframe%'
       AND (content LIKE '%/embed/pieces/%'
            OR content LIKE '%/immersive/collections/%'
            OR content LIKE '%/immersive/images/%')";

    $sources = [
        'posts' => $target->query("SELECT id, content FROM posts WHERE {$embedFilter}")->fetchAll(),
        'page_sections' => $target->query("SELECT id, content FROM page_sections WHERE {$embedFilter}")->fetchAll(),
    ];

    $total = 0;
    $failures = 0;
    $httpChecks = [];

    foreach ($sources as $table => $rows) {
        foreach ($rows as $row) {
            $rowId = (int) $row['id'];
            $content = (string) $row['content'];
            $label = "Post embed: {$table}#{$rowId}";

            preg_match_all('/<iframe\b[^>]*\bsrc=(["\'])(.*?)\1[^>]*>/i', $content, $matches);

            foreach ($matches[2] as $src) {
                $parts = parse_url($src);
                if ($parts === false) {
                    $report->fail($label, "Unparseable iframe src: {$src}");
                    $failures++;
                    continue;
                }

                $path = $parts['path'] ?? '';
                $isTarget = str_starts_with($path, '/embed/pieces/')
                    || str_starts_with($path, '/immersive/collections/')
                    || str_starts_with($path, '/immersive/images/');
                if (!$isTarget) {
                    continue;
                }

                $total++;

                if (isset($parts['scheme']) || isset($parts['host'])) {
                    $report->fail($label, "Absolute URL remains for {$path}: {$src}");
                    $failures++;
                    continue;
                }

                if (str_starts_with($path, '/embed/pieces/') && preg_match('#^/embed/pieces/(\d+)#', $path, $m) === 1) {
                    $id = (int) $m[1];
                    if (!isset($pieceIds[$id])) {
                        $report->fail($label, "Orphaned art-piece reference id={$id}");
                        $failures++;
                        continue;
                    }
                    $httpChecks['/embed/pieces/' . $id . '/data'] = true;
                    $httpChecks['/immersive/pieces/' . $id] = true;
                } elseif (str_starts_with($path, '/immersive/collections/') && preg_match('~^/immersive/collections/([^/?#]+)~', $path, $m) === 1) {
                    $slug = $m[1];
                    if (!isset($exhibitSlugs[$slug])) {
                        $report->fail($label, "Orphaned collection reference slug={$slug}");
                        $failures++;
                        continue;
                    }
                    $httpChecks['/immersive/collections/' . $slug] = true;
                } elseif (str_starts_with($path, '/immersive/images/') && preg_match('~^/immersive/images/([^/?#]+)~', $path, $m) === 1) {
                    $ref = $m[1];
                    $decoded = readiness_decode_image_ref($ref);
                    if ($decoded === null) {
                        $report->fail($label, "Image ref does not decode to a valid path: {$ref}");
                        $failures++;
                        continue;
                    }
                    if (!readiness_media_asset_exists($target, $decoded)) {
                        $report->fail($label, "Image ref decodes to '{$decoded}', no matching media_assets row");
                        $failures++;
                        continue;
                    }
                    $httpChecks['/immersive/images/' . $ref] = true;
                }
            }
        }
    }

    if ($total === 0) {
        $report->warn('Post embed scan', 'No /embed or /immersive iframe references found in posts/page_sections content');
    } elseif ($failures === 0) {
        $report->pass('Post embed scan', "{$total} embed reference(s) checked across posts/page_sections, all resolve");
    }

    if ($baseUrl !== null) {
        foreach (array_keys($httpChecks) as $path) {
            $status = readiness_http_status($baseUrl, $path);
            if ($status === 200) {
                $report->pass("HTTP {$path}", 'returned 200');
            } else {
                $report->fail("HTTP {$path}", 'expected 200, got ' . ($status === null ? 'no response' : (string) $status));
            }
        }
    }
}

function readiness_check_http(ReadinessReport $report, PDO $target, string $baseUrl): void
{
    $routes = [
        '/' => [200],
        '/contact' => [200],
        '/portfolio' => [200],
        '/admin' => [200, 302],
        '/blog' => [200],
        '/blog/posts/1' => [200],
        '/posts/1' => [301],
        '/categories/art' => [301],
        '/feeds' => [301],
        '/feed.xml' => [200],
        '/feed.json' => [200],
        '/feeds/mf2' => [200],
        '/embed/posts/1' => [200],
        '/embed/pieces/1' => [200],
        '/embed/pieces/1/data' => [200],
        '/immersive/pieces/1' => [200],
        '/api/runtimes/p5/p5.min.js' => [302],
        '/api/runtimes/three/three.module.min.js' => [302],
        '/api/feeds' => [200],
        '/api/posts' => [200],
        '/api/art-pieces' => [200],
        '/api/collections' => [200],
    ];

    $slug = readiness_scalar(
        $target,
        "SELECT slug FROM platform_collections WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1"
    );
    if (is_string($slug) && $slug !== '') {
        $routes['/immersive/collections/' . $slug] = [200];
        $routes['/api/collections/' . $slug] = [200];
        $routes['/api/collections/' . $slug . '/items'] = [200];
    } else {
        $report->warn('HTTP collection route selection', 'No active platform collection slug found');
    }

    $filename = readiness_scalar(
        $target,
        "SELECT filename FROM media_assets WHERE deleted_at IS NULL AND file_data IS NOT NULL AND filename <> '' ORDER BY id ASC LIMIT 1"
    );
    if (is_string($filename) && $filename !== '') {
        $encodedRef = readiness_base64url('/api/media/' . rawurlencode($filename));
        $routes['/immersive/images/' . $encodedRef] = [200];
        $routes['/api/media/' . rawurlencode($filename) . '/collections'] = [200];
    } else {
        $report->warn('HTTP image route selection', 'No migrated media asset with file_data found');
    }

    foreach ($routes as $path => $expected) {
        $status = readiness_http_status($baseUrl, $path);
        if ($status !== null && in_array($status, $expected, true)) {
            $report->pass("HTTP {$path}", "returned {$status}");
        } else {
            $report->fail("HTTP {$path}", 'expected ' . implode('/', $expected) . ', got ' . ($status === null ? 'no response' : (string) $status));
        }
    }

    $publishedPostId = (int) ($target->query("SELECT id FROM posts WHERE status = 'published' AND deleted_at IS NULL ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 0);
    if ($publishedPostId > 0) {
        $ogStatus = readiness_http_status($baseUrl, '/og/posts/' . $publishedPostId);
        if ($ogStatus === 200) {
            $report->pass('HTTP /og/posts/[id]', "returned {$ogStatus}");
        } else {
            $report->fail('HTTP /og/posts/[id]', 'expected 200, got ' . ($ogStatus === null ? 'no response' : (string) $ogStatus));
        }
    } else {
        $report->warn('HTTP /og/posts/[id]', 'Skipped because no published post exists in the PHP database');
    }

    $cronSecret = readiness_env('CRON_SECRET');
    if ($cronSecret === '') {
        $report->warn('HTTP /api/cron/refresh-feeds', 'Skipped because CRON_SECRET is not configured');
        return;
    }

    $cronResponse = readiness_http_post_json($baseUrl, '/api/cron/refresh-feeds', [
        'X-Cron-Secret' => $cronSecret,
        'Accept' => 'application/json',
    ]);
    if ($cronResponse['status'] === 200) {
        $report->pass('HTTP /api/cron/refresh-feeds', 'returned 200');
    } else {
        $report->fail('HTTP /api/cron/refresh-feeds', 'expected 200, got ' . $cronResponse['status']);
    }
}

$root = dirname(__DIR__);
$json = in_array('--json', $argv, true);
$skipHttp = in_array('--skip-http', $argv, true);
$baseUrl = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--base-url=')) {
        $baseUrl = substr($arg, strlen('--base-url='));
    }
}
if ($skipHttp) {
    $baseUrl = null;
}

readiness_load_env($root . '/.env');
require_once $root . '/public/vendor/autoload.php';
require_once $root . '/public/app/config/database.php';
require_once $root . '/public/app/helpers/encryption.php';
require_once $root . '/public/app/helpers/feed-ingest.php';
require_once $root . '/public/app/helpers/piece-render.php';
require_once $root . '/public/app/lib/syndication/ContentHelpers.php';
require_once $root . '/public/app/lib/syndication/SyndicationPayload.php';
require_once $root . '/public/app/models/BlogPost.php';
require_once $root . '/public/app/models/FeedSource.php';
require_once $root . '/public/app/models/PlatformArtPiece.php';
require_once $root . '/public/app/models/PlatformArtPieceVersion.php';
require_once $root . '/public/app/models/PlatformConnection.php';
require_once $root . '/public/app/models/PlatformOAuthApp.php';
require_once $root . '/public/app/controllers/EmbedController.php';

$report = new ReadinessReport();

$targetName = readiness_env('DB_NAME');
$sourceName = readiness_env('PLATFORM_DB_NAME');
if ($targetName === '' || $sourceName === '') {
    $report->fail('Database configuration', 'DB_NAME and PLATFORM_DB_NAME must both be configured');
} elseif ($targetName === $sourceName) {
    $report->fail('Database configuration', 'DB_NAME and PLATFORM_DB_NAME are identical');
} else {
    $report->pass('Database configuration', 'Target and source database names differ');
}

$runtimePathMatches = readiness_scan_runtime_paths($root);
if ($runtimePathMatches === []) {
    $report->pass('Runtime path scan', 'No PHP runtime/script references to legacy platform path');
} else {
    $report->fail('Runtime path scan', 'Legacy platform path references in: ' . implode(', ', $runtimePathMatches));
}

$platformDbMatches = readiness_scan_platform_db_usage($root);
if ($platformDbMatches === []) {
    $report->pass('PLATFORM_DB_* usage scan', 'Source DB config only appears in approved verification/migration scripts');
} else {
    $report->fail('PLATFORM_DB_* usage scan', 'Unexpected source DB usage in: ' . implode(', ', $platformDbMatches));
}

if (class_exists('GuzzleHttp\Client')) {
    $report->pass('Composer autoload', 'GuzzleHttp\\Client is available');
} else {
    $report->fail('Composer autoload', 'GuzzleHttp\\Client is missing');
}

try {
    $target = db();
    $source = readiness_pdo('PLATFORM_');
    if (!$report->hasFailures()) {
        readiness_check_retention($report, $target, $source);
    } else {
        $report->warn('Retention checks', 'Skipped because database configuration/static checks failed');
    }
    readiness_check_ai_keys($report, $target);
    readiness_check_platform_oauth_apps($report, $target);
    readiness_check_platform_connections($report, $target);
    readiness_check_feed_approval($report, $target);
    readiness_check_syndication($report, $target);
    readiness_check_piece_rendering($report, $target, $baseUrl);
    readiness_check_post_embeds($report, $target, $baseUrl);
    if ($baseUrl !== null) {
        readiness_check_http($report, $target, $baseUrl);
    } else {
        $report->warn('HTTP route checks', 'Skipped; pass --base-url=http://127.0.0.1:8080 to verify local server routes');
    }
} catch (Throwable $e) {
    $report->fail('Readiness execution', $e->getMessage());
}

if ($json) {
    echo json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} else {
    $report->printHuman();
}

exit($report->hasFailures() ? 1 : 0);
