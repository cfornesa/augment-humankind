<?php

declare(strict_types=1);

class AuthController
{
    private static array $tableExistsCache = [];
    private static array $columnExistsCache = [];
    private static bool $schemaCachePrimed = false;

    public static function loginForm(): void
    {
        if (!empty($_SESSION['admin_identity_id'])) {
            header('Location: /admin');
            exit;
        }
        $error = $_GET['error'] ?? null;
        $detail = oauth_is_local_request() ? trim((string) ($_GET['detail'] ?? '')) : '';
        require dirname(__DIR__, 2) . '/views/admin/login.php';
    }

    public static function oauthStart(): void
    {
        $provider = self::requestedProvider();
        $subjectHash = rate_limit_subject_for_scope('admin_oauth_start');
        $limit = rate_limit_consume('admin_oauth_start', $subjectHash);
        if (!$limit['allowed']) {
            audit_log_event('admin_oauth', 'admin_oauth_start', 'throttled', [
                'subject_hash' => $subjectHash,
                'http_status' => 429,
                'metadata' => [
                    'provider' => $provider,
                    'retry_after' => $limit['retry_after'],
                    'request_count' => $limit['request_count'],
                ],
            ]);
            self::renderRateLimited((int) $limit['retry_after']);
        }

        $config = oauth_provider_config($provider);
        if ($config['client_id'] === '' || $config['client_secret'] === '') {
            audit_log_event('admin_oauth', 'admin_oauth_start', 'provider_unconfigured', [
                'subject_hash' => $subjectHash,
                'http_status' => 302,
                'metadata' => ['provider' => $provider],
            ]);
            header('Location: /admin/login?error=provider');
            exit;
        }

        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = [
            'provider' => $provider,
            'value' => $state,
        ];

        $params = [
            'client_id' => $config['client_id'],
            'redirect_uri' => oauth_redirect_uri($provider),
            'response_type' => 'code',
            'scope' => $config['scope'],
            'state' => $state,
        ];

        if ($provider === 'google') {
            $params['access_type'] = 'online';
            $params['prompt'] = 'select_account';
        }

        header('Location: ' . $config['auth_url'] . '?' . http_build_query($params));
        exit;
    }

    public static function oauthCallback(): void
    {
        $provider = self::requestedProvider();
        $subjectHash = rate_limit_subject_for_scope('admin_oauth_callback');
        $limit = rate_limit_consume('admin_oauth_callback', $subjectHash);
        if (!$limit['allowed']) {
            audit_log_event('admin_oauth', 'admin_oauth_callback', 'throttled', [
                'subject_hash' => $subjectHash,
                'http_status' => 429,
                'metadata' => [
                    'provider' => $provider,
                    'retry_after' => $limit['retry_after'],
                    'request_count' => $limit['request_count'],
                ],
            ]);
            self::renderRateLimited((int) $limit['retry_after']);
        }

        $state = $_SESSION['oauth_state'] ?? null;

        if (!is_array($state) || ($state['provider'] ?? null) !== $provider || ($state['value'] ?? '') !== ($_GET['state'] ?? '')) {
            audit_log_event('admin_oauth', 'admin_oauth_callback', 'invalid_state', [
                'subject_hash' => $subjectHash,
                'http_status' => 302,
                'metadata' => ['provider' => $provider],
            ]);
            header('Location: /admin/login?error=state');
            exit;
        }
        unset($_SESSION['oauth_state']);

        $code = trim((string) ($_GET['code'] ?? ''));
        if ($code === '') {
            audit_log_event('admin_oauth', 'admin_oauth_callback', 'missing_code', [
                'subject_hash' => $subjectHash,
                'http_status' => 302,
                'metadata' => ['provider' => $provider],
            ]);
            header('Location: /admin/login?error=oauth');
            exit;
        }

        try {
            $profile = self::fetchOauthProfile($provider, $code);
            if (!oauth_allowed_identity($provider, $profile)) {
                audit_log_event('admin_oauth', 'admin_oauth_callback', 'denied', [
                    'subject_hash' => $subjectHash,
                    'http_status' => 302,
                    'metadata' => ['provider' => $provider],
                ]);
                header('Location: /admin/login?error=denied');
                exit;
            }

            $identityId = AdminIdentity::upsertFromProfile([
                'provider' => $provider,
                'provider_subject' => (string) $profile['provider_subject'],
                'email' => $profile['email'] ?? null,
                'display_name' => (string) $profile['display_name'],
                'avatar_url' => $profile['avatar_url'] ?? null,
            ]);
            $identity = AdminIdentity::find($identityId);
            if (!$identity) {
                throw new RuntimeException('Identity could not be loaded after login.');
            }

            if (class_exists('PlatformUser')) {
                PlatformUser::upsertOwnerFromAdminProfile($provider, $profile);
            }

            admin_login_identity($identity);
            audit_log_event('admin_oauth', 'admin_oauth_callback', 'success', [
                'actor_admin_identity_id' => (int) $identity['id'],
                'subject_hash' => $subjectHash,
                'http_status' => 302,
                'metadata' => ['provider' => $provider],
            ]);
            header('Location: /admin');
            exit;
        } catch (Throwable $e) {
            error_log('[admin-oauth] ' . $provider . ': ' . $e->getMessage());
            audit_log_event('admin_oauth', 'admin_oauth_callback', 'error', [
                'subject_hash' => $subjectHash,
                'http_status' => 302,
                'metadata' => [
                    'provider' => $provider,
                    'error' => $e->getMessage(),
                ],
            ]);

            $query = 'error=oauth';
            if (oauth_is_local_request()) {
                $query .= '&detail=' . rawurlencode(oauth_debug_detail($e));
            }

            header('Location: /admin/login?' . $query);
            exit;
        }
    }

    private static function renderRateLimited(int $retryAfter): void
    {
        http_response_code(429);
        header('Retry-After: ' . $retryAfter);
        $error = 'rate_limit';
        $detail = 'Too many admin sign-in attempts. Please wait about ' . max(1, (int) ceil($retryAfter / 60)) . ' minute(s) and try again.';
        require dirname(__DIR__, 2) . '/views/admin/login.php';
        exit;
    }

    public static function logout(): void
    {
        admin_logout();
        header('Location: /admin/login');
        exit;
    }

    public static function dashboard(): void
    {
        admin_check();
        self::primeSchemaCache();

        $exhibitCount  = self::countRows('exhibits', activeOnly: true);
        $artMediaCount = self::countScopedCategories('portfolio');
        $categoryCount = self::countScopedCategories('blog');
        $collectionCount = self::countRows('collections', activeOnly: true);
        $pageCount     = self::countRows('pages', activeOnly: true);

        $publishedPosts = self::countRows('posts', ["status = 'published'"], activeOnly: true);
        $scheduledPosts = self::countRows('posts', ["status = 'scheduled'"], activeOnly: true);
        $draftPosts     = self::countRows('posts', ["status = 'draft'"], activeOnly: true);
        $commentCount   = self::countRows('comments', activeOnly: true);
        $reactionCount  = self::countRows('reactions', activeOnly: true);
        $connectionCount = self::countRows('platform_connections');
        $syndicationCount = self::countRows('post_syndications');
        $pieceCount     = self::countRows('art_pieces', activeOnly: true);
        $mediaCount     = self::countRows('media_files', activeOnly: true);
        $assetCount     = self::countRows('media_assets', activeOnly: true);
        $feedSourceCount = self::countRows('feed_sources', activeOnly: true);
        $pendingFeeds   = self::countRows('feed_import_items', ["status = 'pending'"]);
        $userCount      = self::countRows('users');
        $aiProfileCount = self::countRows('user_ai_vendor_settings');
        $aiKeyCount     = self::countRows('user_ai_vendor_keys');

        $trashCount = array_sum(array_map(
            static fn (string $table): int => self::countDeletedRows($table),
            [
                'exhibits',
                'categories',
                'collections',
                'pages',
                'posts',
                'media_files',
                'media_assets',
                'art_pieces',
                'comments',
                'feed_sources',
            ]
        ));

        require dirname(__DIR__, 2) . '/views/admin/dashboard.php';
    }

    public static function setup(): void
    {
        admin_check();
        $checklist = function_exists('site_bootstrap_checklist') ? site_bootstrap_checklist() : [];
        require dirname(__DIR__, 2) . '/views/admin/setup.php';
    }

    private static function countRows(string $table, array $where = [], bool $activeOnly = false): int
    {
        if (!self::tableExists($table)) {
            return 0;
        }
        if ($activeOnly && self::columnExists($table, 'deleted_at')) {
            $where[] = 'deleted_at IS NULL';
        }

        $sql = 'SELECT COUNT(*) FROM `' . str_replace('`', '``', $table) . '`';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        try {
            return (int) db()->query($sql)->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    private static function countDeletedRows(string $table): int
    {
        if (!self::tableExists($table) || !self::columnExists($table, 'deleted_at')) {
            return 0;
        }

        return self::countRows($table, ['deleted_at IS NOT NULL']);
    }

    private static function countScopedCategories(string $scope): int
    {
        if (!self::tableExists('categories')) {
            return 0;
        }

        try {
            $stmt = db()->prepare(
                "SELECT COUNT(*) FROM categories WHERE deleted_at IS NULL AND category_scope = ?"
            );
            $stmt->execute([$scope]);
            return (int) $stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    private static function primeSchemaCache(): void
    {
        if (self::$schemaCachePrimed) {
            return;
        }
        self::$schemaCachePrimed = true;

        try {
            $tables = db()->query(
                'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()'
            )->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable) {
            return;
        }

        foreach ($tables as $table) {
            self::$tableExistsCache[$table] = true;
        }

        try {
            $deletedAtTables = db()->query(
                "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'deleted_at'"
            )->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable) {
            return;
        }

        $deletedAtSet = array_flip($deletedAtTables);
        foreach ($tables as $table) {
            self::$columnExistsCache[$table . '.deleted_at'] = isset($deletedAtSet[$table]);
        }
    }

    private static function tableExists(string $table): bool
    {
        if (array_key_exists($table, self::$tableExistsCache)) {
            return self::$tableExistsCache[$table];
        }

        try {
            $stmt = db()->prepare(
                'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $stmt->execute([$table]);
            return self::$tableExistsCache[$table] = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return self::$tableExistsCache[$table] = false;
        }
    }

    private static function columnExists(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (array_key_exists($key, self::$columnExistsCache)) {
            return self::$columnExistsCache[$key];
        }

        try {
            $stmt = db()->prepare(
                'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
                 LIMIT 1'
            );
            $stmt->execute([$table, $column]);
            return self::$columnExistsCache[$key] = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return self::$columnExistsCache[$key] = false;
        }
    }

    private static function requestedProvider(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
        if (preg_match('#/admin/auth/(github|google)/#', $path, $matches)) {
            return $matches[1];
        }

        throw new InvalidArgumentException('Unknown OAuth provider.');
    }

    private static function fetchOauthProfile(string $provider, string $code): array
    {
        $config = oauth_provider_config($provider);
        $tokenResponse = oauth_http_request(
            'POST',
            $config['token_url'],
            [
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            http_build_query([
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'code' => $code,
                'redirect_uri' => oauth_redirect_uri($provider),
                'grant_type' => 'authorization_code',
            ])
        );
        $tokenPayload = json_decode($tokenResponse['body'], true);
        $accessToken = is_array($tokenPayload) ? (string) ($tokenPayload['access_token'] ?? '') : '';
        if ($accessToken === '') {
            $errorDescription = is_array($tokenPayload) ? (string) ($tokenPayload['error_description'] ?? $tokenPayload['error'] ?? '') : '';
            throw new RuntimeException('OAuth token exchange failed.' . ($errorDescription !== '' ? ' ' . $errorDescription : ''));
        }

        if ($provider === 'github') {
            $userResponse = oauth_http_request('GET', $config['user_url'], [
                'Accept' => 'application/vnd.github+json',
                'Authorization' => 'Bearer ' . $accessToken,
                'User-Agent' => 'PhpCmsAdminOAuth/1.0',
            ]);
            $user = json_decode($userResponse['body'], true);
            if (!is_array($user) || empty($user['id']) || empty($user['login'])) {
                throw new RuntimeException('GitHub profile could not be loaded from the provider response.');
            }

            $email = isset($user['email']) && $user['email'] !== '' ? (string) $user['email'] : null;
            if ($email === null) {
                $emailResponse = oauth_http_request('GET', $config['emails_url'], [
                    'Accept' => 'application/vnd.github+json',
                    'Authorization' => 'Bearer ' . $accessToken,
                    'User-Agent' => 'PhpCmsAdminOAuth/1.0',
                ]);
                $emails = json_decode($emailResponse['body'], true);
                if (is_array($emails)) {
                    foreach ($emails as $entry) {
                        if (!empty($entry['primary']) && !empty($entry['verified']) && !empty($entry['email'])) {
                            $email = (string) $entry['email'];
                            break;
                        }
                    }
                }
            }

            return [
                'provider_subject' => (string) $user['id'],
                'login' => (string) $user['login'],
                'email' => $email,
                'display_name' => (string) ($user['name'] ?: $user['login']),
                'avatar_url' => (string) ($user['avatar_url'] ?? ''),
            ];
        }

        $userResponse = oauth_http_request('GET', $config['userinfo_url'], [
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept' => 'application/json',
        ]);
        $user = json_decode($userResponse['body'], true);
        if (!is_array($user) || empty($user['sub']) || empty($user['email'])) {
            throw new RuntimeException('Google profile could not be loaded from the provider response.');
        }

        return [
            'provider_subject' => (string) $user['sub'],
            'email' => (string) $user['email'],
            'display_name' => (string) ($user['name'] ?? $user['email']),
            'avatar_url' => (string) ($user['picture'] ?? ''),
        ];
    }
}
