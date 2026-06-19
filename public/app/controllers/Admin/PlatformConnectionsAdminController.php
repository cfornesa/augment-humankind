<?php

declare(strict_types=1);

class PlatformConnectionsAdminController
{
    public static function index(): void
    {
        admin_check();
        $connections = PlatformConnection::all();
        $syndications = PostSyndication::all();
        $platforms = platform_ui_definitions();
        $oauthAppsByPlatform = PlatformOAuthApp::allByPlatform();
        $connectionsByPlatform = [];
        foreach ($connections as $connection) {
            $connectionsByPlatform[$connection['platform']] = $connection;
        }
        $latestSyndications = [];
        foreach ($syndications as $syndication) {
            $platform = (string) ($syndication['platform'] ?? '');
            if ($platform !== '' && !isset($latestSyndications[$platform])) {
                $latestSyndications[$platform] = $syndication;
            }
        }
        require dirname(__DIR__, 2) . '/views/admin/platform-connections/index.php';
    }

    public static function create(): void
    {
        admin_check();
        $users = self::allUsers();
        $connection = null;
        $error = null;
        $platforms = platform_ui_definitions();
        $selectedPlatform = (string) ($_GET['platform'] ?? '');
        require dirname(__DIR__, 2) . '/views/admin/platform-connections/form.php';
    }

    public static function store(): void
    {
        admin_check();

        try {
            $data = self::resolveConnectionData();
            PlatformConnection::create($data);
            header('Location: /admin/platform-connections');
        } catch (Throwable $e) {
            $users = self::allUsers();
            $connection = null;
            $error = $e->getMessage();
            require dirname(__DIR__, 2) . '/views/admin/platform-connections/form.php';
        }
        exit;
    }

    public static function edit(string $id): void
    {
        admin_check();
        $connection = PlatformConnection::find((int) $id);
        if (!$connection) {
            header('Location: /admin/platform-connections');
            exit;
        }
        $users = self::allUsers();
        $error = null;
        $platforms = platform_ui_definitions();
        $selectedPlatform = (string) ($connection['platform'] ?? '');
        require dirname(__DIR__, 2) . '/views/admin/platform-connections/form.php';
    }

    public static function update(string $id): void
    {
        admin_check();
        $existing = PlatformConnection::find((int) $id);
        if (!$existing) {
            header('Location: /admin/platform-connections');
            exit;
        }

        try {
            $data = self::resolveConnectionData($existing);
            PlatformConnection::update((int) $id, $data);
            header('Location: /admin/platform-connections');
        } catch (Throwable $e) {
            $users = self::allUsers();
            $connection = $existing;
            $error = $e->getMessage();
            require dirname(__DIR__, 2) . '/views/admin/platform-connections/form.php';
        }
        exit;
    }

    public static function delete(string $id): void
    {
        admin_check();
        PlatformConnection::delete((int) $id);
        header('Location: /admin/platform-connections');
        exit;
    }

    public static function syndicationCreate(): void
    {
        admin_check();
        $posts = self::allPosts();
        $connections = PlatformConnection::all();
        $syndication = null;
        $error = null;
        require dirname(__DIR__, 2) . '/views/admin/platform-connections/syndication-form.php';
    }

    public static function syndicationStore(): void
    {
        admin_check();

        try {
            $data = self::resolveSyndicationData();
            PostSyndication::create($data);
            header('Location: /admin/platform-connections');
        } catch (Throwable $e) {
            $posts = self::allPosts();
            $connections = PlatformConnection::all();
            $syndication = null;
            $error = $e->getMessage();
            require dirname(__DIR__, 2) . '/views/admin/platform-connections/syndication-form.php';
        }
        exit;
    }

    public static function syndicationDelete(string $id): void
    {
        admin_check();
        PostSyndication::delete((int) $id);
        header('Location: /admin/platform-connections');
        exit;
    }

    public static function publish(): void
    {
        admin_check();

        $postId = (int) ($_POST['post_id'] ?? 0);
        $connectionId = (int) ($_POST['connection_id'] ?? 0);
        $actorId = (int) (admin_identity()['id'] ?? 0);
        $startedAt = microtime(true);

        if ($postId <= 0 || $connectionId <= 0) {
            header('Location: /admin/platform-connections?error=' . urlencode('Post and connection are required'));
            exit;
        }

        try {
            $post = BlogPost::find($postId);
            if (!$post) {
                throw new Exception('Post not found');
            }

            $connection = PlatformConnection::find($connectionId);
            if (!$connection) {
                throw new Exception('Connection not found');
            }

            $platform = $connection['platform'] ?? '';
            $adapter = AdapterFactory::get($platform);
            if (!$adapter) {
                throw new Exception("No adapter for platform: {$platform}");
            }

            $siteTitle = app_site_name();
            $settings = SiteSettings::current();
            if ($settings && !empty($settings['site_title'])) {
                $siteTitle = $settings['site_title'];
            }

            $canonicalUrl = seo_absolute_url('/blog/posts/' . $postId) ?? '';
            $payload = SyndicationPayload::fromPost($post, $canonicalUrl, $siteTitle);
            $refresh = $adapter->refreshToken($connection);
            if ($refresh) {
                PlatformConnection::updateTokens(
                    (int) $connectionId,
                    $refresh->accessToken,
                    $refresh->refreshToken,
                    $refresh->expiresAt
                );
                $connection = PlatformConnection::find($connectionId) ?: $connection;
            }
            $result = $adapter->publish($connection, $payload);

            audit_log_event('syndication_publish', 'syndication_publish', 'success', [
                'actor_admin_identity_id' => $actorId > 0 ? $actorId : null,
                'target_type' => 'platform_connection',
                'target_id' => (string) $connectionId,
                'http_status' => 302,
                'metadata' => [
                    'platform' => $platform,
                    'post_id' => $postId,
                    'token_refreshed' => $refresh !== null,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ],
            ]);

            PostSyndication::recordResult([
                'post_id' => $postId,
                'platform_connection_id' => $connectionId,
                'external_id' => $result->externalId,
                'external_url' => $result->externalUrl,
                'status' => 'synced',
                'synced_at' => date('Y-m-d H:i:s'),
            ]);

            header('Location: /admin/platform-connections?tab=syndications');
        } catch (Throwable $e) {
            // Record failed syndication
            try {
                PostSyndication::recordResult([
                    'post_id' => $postId,
                    'platform_connection_id' => $connectionId,
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            } catch (Throwable) {
                // ignore
            }
            audit_log_event('syndication_publish', 'syndication_publish', 'error', [
                'actor_admin_identity_id' => $actorId > 0 ? $actorId : null,
                'target_type' => 'platform_connection',
                'target_id' => (string) $connectionId,
                'http_status' => 302,
                'metadata' => [
                    'post_id' => $postId,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'error' => $e->getMessage(),
                ],
            ]);
            header('Location: /admin/platform-connections?error=' . urlencode($e->getMessage()));
        }
        exit;
    }

    public static function oauthStart(string $platform): void
    {
        admin_check();
        $platformKey = platform_oauth_provider_key($platform);

        try {
            $config = platform_oauth_provider_config($platformKey);
            if ($config['client_id'] === '' || $config['client_secret'] === '') {
                header('Location: /admin/platform-connections?error=' . urlencode('OAuth app credentials are not configured for ' . $platformKey));
                exit;
            }

            $state = bin2hex(random_bytes(16));
            $_SESSION['platform_oauth_state'] = [
                'platform' => $platformKey,
                'value' => $state,
                'blog_url' => $config['blog_url'] ?? null,
            ];

            $params = [
                'client_id' => $config['client_id'],
                'redirect_uri' => platform_oauth_redirect_uri($platformKey),
                'response_type' => 'code',
                'scope' => $config['scope'],
                'state' => $state,
            ];

            if ($platformKey === 'wordpress_com' && !empty($config['blog_url'])) {
                $params['blog'] = (string) $config['blog_url'];
            }

            if ($platformKey === 'blogger') {
                $params['access_type'] = 'offline';
                $params['prompt'] = 'consent';
            }
            if ($platformKey === 'facebook' || $platformKey === 'instagram') {
                $params['scope'] = 'pages_manage_posts,pages_read_engagement,instagram_content_publish,instagram_basic';
            }

            header('Location: ' . $config['auth_url'] . '?' . http_build_query($params));
            exit;
        } catch (Throwable $e) {
            header('Location: /admin/platform-connections?error=' . urlencode($e->getMessage()));
            exit;
        }
    }

    public static function oauthCallback(string $platform): void
    {
        admin_check();
        $platformKey = platform_oauth_provider_key($platform);

        $state = $_SESSION['platform_oauth_state'] ?? null;
        if (!is_array($state) || ($state['platform'] ?? null) !== $platformKey || ($state['value'] ?? '') !== ($_GET['state'] ?? '')) {
            header('Location: /admin/platform-connections?error=' . urlencode('Invalid or expired OAuth state'));
            exit;
        }
        unset($_SESSION['platform_oauth_state']);

        $code = trim((string) ($_GET['code'] ?? ''));
        if ($code === '') {
            header('Location: /admin/platform-connections?error=' . urlencode('OAuth authorization code missing'));
            exit;
        }

        try {
            $config = platform_oauth_provider_config($platformKey);
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
                    'redirect_uri' => platform_oauth_redirect_uri($platformKey),
                    'grant_type' => 'authorization_code',
                ])
            );
            $tokenPayload = json_decode($tokenResponse['body'], true);
            $accessToken = is_array($tokenPayload) ? (string) ($tokenPayload['access_token'] ?? '') : '';
            if ($accessToken === '') {
                $errorDescription = is_array($tokenPayload) ? (string) ($tokenPayload['error_description'] ?? $tokenPayload['error'] ?? '') : '';
                throw new RuntimeException('OAuth token exchange failed.' . ($errorDescription !== '' ? ' ' . $errorDescription : ''));
            }

            $refreshToken = is_array($tokenPayload) ? (string) ($tokenPayload['refresh_token'] ?? '') : '';
            $expiresIn = is_array($tokenPayload) ? (int) ($tokenPayload['expires_in'] ?? 0) : 0;
            $expiresAt = $expiresIn > 0 ? date('Y-m-d H:i:s', time() + $expiresIn) : null;

            $encryptedAccess = encrypt_string($accessToken, ai_encryption_key());
            $encryptedRefresh = $refreshToken !== '' ? encrypt_string($refreshToken, ai_encryption_key()) : null;

            $owner = PlatformUser::owner();
            $userId = $owner ? $owner['id'] : null;
            if ($userId === null) {
                throw new RuntimeException('No owner user is available for platform connections.');
            }

            $connectedPlatforms = self::storeOAuthConnection(
                $platformKey,
                $userId,
                $accessToken,
                $encryptedAccess,
                $encryptedRefresh,
                $expiresAt,
                $tokenPayload,
                $state['blog_url'] ?? null
            );

            header('Location: /admin/platform-connections?success=oauth&platform=' . urlencode(implode(',', $connectedPlatforms)));
            exit;
        } catch (Throwable $e) {
            error_log('[platform-oauth] ' . $platformKey . ': ' . $e->getMessage());
            header('Location: /admin/platform-connections?error=' . urlencode('OAuth failed for ' . $platformKey . '. ' . $e->getMessage()));
            exit;
        }
    }

    public static function diagnostics(): void
    {
        admin_check();

        $results = [];
        foreach (platform_oauth_supported_providers() as $provider) {
            try {
                $config = platform_oauth_provider_config($provider);
                $hasCredentials = $config['client_id'] !== '' && $config['client_secret'] !== '';
                $platformKey = platform_oauth_provider_key($provider);
                $definition = platform_ui_definition($platformKey);
                $results[$provider] = [
                    'label' => $definition['label'] ?? ucwords(str_replace(['-', '_'], ' ', $provider)),
                    'configured' => $hasCredentials,
                    'client_id_set' => $config['client_id'] !== '',
                    'client_secret_set' => $config['client_secret'] !== '',
                    'redirect_uri' => platform_oauth_redirect_uri($provider),
                    'blog_url_set' => !empty($config['blog_url']),
                    'error' => null,
                ];

                if ($hasCredentials) {
                    // Lightweight connectivity check: try to reach the token endpoint with a dummy request
                    // Most providers will reject it, but a 4xx means the endpoint is reachable
                    $test = @oauth_http_request(
                        'POST',
                        $config['token_url'],
                        [
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/x-www-form-urlencoded',
                        ],
                        http_build_query([
                            'client_id' => $config['client_id'],
                            'client_secret' => $config['client_secret'],
                            'grant_type' => 'authorization_code',
                            'code' => 'invalid_test_code',
                            'redirect_uri' => platform_oauth_redirect_uri($provider),
                        ])
                    );
                    $results[$provider]['endpoint_reachable'] = $test['status'] >= 200 && $test['status'] < 500;
                    $results[$provider]['endpoint_status'] = $test['status'];
                } else {
                    $results[$provider]['endpoint_reachable'] = false;
                    $results[$provider]['endpoint_status'] = null;
                }
            } catch (Throwable $e) {
                $results[$provider] = [
                    'configured' => false,
                    'client_id_set' => false,
                    'client_secret_set' => false,
                    'redirect_uri' => platform_oauth_redirect_uri($provider),
                    'error' => $e->getMessage(),
                    'endpoint_reachable' => false,
                    'endpoint_status' => null,
                ];
            }
        }

        $credentialResults = [];
        foreach (['wordpress_self', 'substack', 'bluesky'] as $platform) {
            $definition = platform_ui_definition($platform);
            $connection = self::findConnectionByPlatform($platform);
            $metadata = $connection ? parse_connection_meta($connection['metadata'] ?? null) : [];
            $hasCredential = $connection !== false && !empty($connection['encrypted_access_token']);

            $fields = [];
            switch ($platform) {
                case 'wordpress_self':
                    $fields = [
                        'Site URL' => !empty($metadata['siteUrl']),
                        'Username' => !empty($metadata['username']),
                        'App Password' => $hasCredential,
                    ];
                    break;
                case 'substack':
                    $fields = [
                        'Publication ID' => !empty($metadata['publicationId']),
                        'Publication Host' => !empty($metadata['publicationHost']),
                        'Session Cookie' => $hasCredential,
                    ];
                    break;
                case 'bluesky':
                    $fields = [
                        'Handle' => !empty($metadata['handle']),
                        'App Password' => $hasCredential,
                    ];
                    break;
            }

            $credentialResults[$platform] = [
                'label' => $definition['label'] ?? ucwords(str_replace(['-', '_'], ' ', $platform)),
                'fields' => $fields,
                'configured' => $hasCredential && !in_array(false, $fields, true),
            ];
        }

        $pageTitle = 'Platform Connection Diagnostics';
        require dirname(__DIR__, 2) . '/views/admin/platform-connections/diagnostics.php';
    }

    private static function findConnectionByPlatform(string $platform): array|false
    {
        try {
            $stmt = db()->prepare('SELECT * FROM platform_connections WHERE platform = ? ORDER BY id DESC LIMIT 1');
            $stmt->execute([$platform]);
            return $stmt->fetch() ?: false;
        } catch (Throwable) {
            return false;
        }
    }

    private static function allUsers(): array
    {
        try {
            return db()->query("SELECT id, name, email FROM users ORDER BY name ASC")->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    private static function allPosts(): array
    {
        try {
            return db()->query("SELECT id, title FROM posts WHERE deleted_at IS NULL ORDER BY created_at DESC")->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    private static function resolveConnectionData(?array $existing = null): array
    {
        $platform = trim($_POST['platform'] ?? '');
        if ($platform === '') {
            throw new InvalidArgumentException('Platform is required.');
        }
        $definition = platform_ui_definition($platform);
        if ($definition === null) {
            throw new InvalidArgumentException('Unsupported platform.');
        }

        $metadata = parse_connection_meta($existing['metadata'] ?? null);
        $encryptedAccess = $existing['encrypted_access_token'] ?? null;
        $encryptedRefresh = $existing['encrypted_refresh_token'] ?? null;

        if (($definition['kind'] ?? '') === 'credentials') {
            switch ($platform) {
                case 'bluesky':
                    $handle = trim((string) ($_POST['handle'] ?? ($metadata['handle'] ?? '')));
                    if ($handle === '') {
                        throw new InvalidArgumentException('Bluesky handle is required.');
                    }
                    $metadata['handle'] = $handle;
                    $encryptedAccess = self::encryptedTokenFromPost('app_password', $encryptedAccess);
                    if (!$encryptedAccess) {
                        throw new InvalidArgumentException('Bluesky App Password is required.');
                    }
                    break;

                case 'wordpress_self':
                    $siteUrl = trim((string) ($_POST['site_url'] ?? ($metadata['siteUrl'] ?? '')));
                    $username = trim((string) ($_POST['username'] ?? ($metadata['username'] ?? '')));
                    $appPassword = trim((string) ($_POST['app_password'] ?? ''));
                    if ($siteUrl === '' || !filter_var($siteUrl, FILTER_VALIDATE_URL)) {
                        throw new InvalidArgumentException('A valid WordPress site URL is required.');
                    }
                    if ($username === '') {
                        throw new InvalidArgumentException('WordPress username is required.');
                    }
                    if ($appPassword !== '') {
                        $encryptedAccess = encrypt_string(base64_encode($username . ':' . $appPassword), ai_encryption_key());
                    } elseif (!$encryptedAccess) {
                        throw new InvalidArgumentException('WordPress Application Password is required.');
                    }
                    $metadata['siteUrl'] = rtrim($siteUrl, '/');
                    $metadata['username'] = $username;
                    break;

                case 'substack':
                    $publicationId = trim((string) ($_POST['publication_id'] ?? ($metadata['publicationId'] ?? '')));
                    $publicationHost = trim((string) ($_POST['publication_host'] ?? ($metadata['publicationHost'] ?? '')));
                    if ($publicationId === '') {
                        throw new InvalidArgumentException('Substack publication ID is required.');
                    }
                    if ($publicationHost === '') {
                        throw new InvalidArgumentException('Substack publication host is required.');
                    }
                    $encryptedAccess = self::encryptedTokenFromPost('session_cookie', $encryptedAccess);
                    if (!$encryptedAccess) {
                        throw new InvalidArgumentException('Substack session cookie is required.');
                    }
                    $metadata['publicationId'] = $publicationId;
                    $metadata['publicationHost'] = $publicationHost;
                    break;
            }
        }

        return [
            'user_id' => trim($_POST['user_id'] ?? '') ?: null,
            'platform' => $platform,
            'encrypted_access_token' => $encryptedAccess,
            'encrypted_refresh_token' => $encryptedRefresh,
            'expires_at' => trim($_POST['expires_at'] ?? '') ?: null,
            'metadata' => $metadata !== [] ? json_encode($metadata, JSON_THROW_ON_ERROR) : null,
            'enabled' => isset($_POST['enabled']) ? 1 : 0,
        ];
    }

    public static function oauthAppEdit(string $platform): void
    {
        admin_check();
        $platformKey = platform_oauth_provider_key($platform);
        $definition = platform_ui_definition($platformKey);
        if ($definition === null || ($definition['kind'] ?? '') !== 'oauth') {
            header('Location: /admin/platform-connections?error=' . urlencode('Unsupported OAuth app platform.'));
            exit;
        }

        $app = PlatformOAuthApp::findByPlatform($platformKey) ?: ['platform' => $platformKey];
        $error = null;
        require dirname(__DIR__, 2) . '/views/admin/platform-connections/oauth-app-form.php';
    }

    public static function oauthAppUpdate(string $platform): void
    {
        admin_check();
        $platformKey = platform_oauth_provider_key($platform);
        $definition = platform_ui_definition($platformKey);
        if ($definition === null || ($definition['kind'] ?? '') !== 'oauth') {
            header('Location: /admin/platform-connections?error=' . urlencode('Unsupported OAuth app platform.'));
            exit;
        }

        $clientId = trim((string) ($_POST['client_id'] ?? ''));
        $clientSecret = trim((string) ($_POST['client_secret'] ?? ''));
        $blogUrl = trim((string) ($_POST['blog_url'] ?? ''));
        $app = PlatformOAuthApp::findByPlatform($platformKey) ?: ['platform' => $platformKey];

        try {
            if ($blogUrl !== '' && !filter_var($blogUrl, FILTER_VALIDATE_URL)) {
                throw new InvalidArgumentException('Blog URL must be a valid URL.');
            }
            PlatformOAuthApp::upsert(
                $platformKey,
                $clientId !== '' ? $clientId : null,
                $clientSecret !== '' ? $clientSecret : null,
                $blogUrl !== '' ? rtrim($blogUrl, '/') : null,
                true
            );
            header('Location: /admin/platform-connections?success=oauth_app&platform=' . urlencode($platformKey));
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
            require dirname(__DIR__, 2) . '/views/admin/platform-connections/oauth-app-form.php';
            exit;
        }
    }

    private static function storeOAuthConnection(
        string $platformKey,
        string|int $userId,
        string $accessToken,
        string $encryptedAccess,
        ?string $encryptedRefresh,
        ?string $expiresAt,
        array $tokenPayload,
        ?string $stateBlogUrl
    ): array {
        return match ($platformKey) {
            'wordpress_com' => [self::upsertOAuthConnection(
                'wordpress_com',
                $userId,
                $encryptedAccess,
                $encryptedRefresh,
                $expiresAt,
                self::wordpressComMetadata($accessToken, $tokenPayload)
            )],
            'blogger' => [self::upsertOAuthConnection(
                'blogger',
                $userId,
                $encryptedAccess,
                $encryptedRefresh,
                $expiresAt,
                self::bloggerMetadata($accessToken, $stateBlogUrl)
            )],
            'linkedin' => [self::upsertOAuthConnection(
                'linkedin',
                $userId,
                $encryptedAccess,
                $encryptedRefresh,
                $expiresAt,
                self::linkedInMetadata($accessToken)
            )],
            'facebook', 'instagram' => self::upsertMetaConnections(
                $userId,
                $platformKey,
                $accessToken,
                $expiresAt,
                $tokenPayload
            ),
            default => throw new RuntimeException('Unsupported platform OAuth provider.'),
        };
    }

    private static function upsertOAuthConnection(
        string $platform,
        string|int $userId,
        string $encryptedAccess,
        ?string $encryptedRefresh,
        ?string $expiresAt,
        array $metadata
    ): string {
        $existing = self::findConnectionByPlatform($platform);
        $payload = [
            'user_id' => $userId,
            'platform' => $platform,
            'encrypted_access_token' => $encryptedAccess,
            'encrypted_refresh_token' => $encryptedRefresh,
            'expires_at' => $expiresAt,
            'metadata' => $metadata !== [] ? json_encode($metadata, JSON_THROW_ON_ERROR) : null,
            'enabled' => 1,
        ];

        if ($existing) {
            PlatformConnection::update((int) $existing['id'], $payload);
        } else {
            PlatformConnection::create($payload);
        }

        return $platform;
    }

    private static function wordpressComMetadata(string $accessToken, array $tokenPayload): array
    {
        $blogId = !empty($tokenPayload['blog_id']) ? (string) $tokenPayload['blog_id'] : '';
        $blogUrl = '';

        if ($blogId === '') {
            $sitesResponse = oauth_http_request('GET', 'https://public-api.wordpress.com/rest/v1.1/me/sites', [
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
            ]);
            $sitesPayload = json_decode($sitesResponse['body'], true);
            $firstSite = is_array($sitesPayload) ? ($sitesPayload['sites'][0] ?? null) : null;
            if (is_array($firstSite)) {
                $blogId = (string) ($firstSite['ID'] ?? '');
                $blogUrl = (string) ($firstSite['URL'] ?? '');
            }
        }

        if ($blogId === '') {
            throw new RuntimeException('WordPress.com OAuth succeeded, but no blog could be determined for this account.');
        }

        return [
            'blogId' => $blogId,
            'blogUrl' => $blogUrl !== '' ? $blogUrl : null,
        ];
    }

    private static function bloggerMetadata(string $accessToken, ?string $stateBlogUrl): array
    {
        $blogId = '';
        $blogUrl = trim((string) $stateBlogUrl);

        if ($blogUrl !== '') {
            $extracted = self::extractBloggerBlogIdFromHtml($blogUrl);
            if ($extracted !== null) {
                return $extracted;
            }

            $byUrlResponse = oauth_http_request(
                'GET',
                'https://www.googleapis.com/blogger/v3/blogs/byurl?url=' . rawurlencode($blogUrl),
                [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/json',
                ]
            );
            if ($byUrlResponse['status'] >= 200 && $byUrlResponse['status'] < 300) {
                $byUrlPayload = json_decode($byUrlResponse['body'], true);
                $blogId = is_array($byUrlPayload) ? (string) ($byUrlPayload['id'] ?? '') : '';
                $blogUrl = is_array($byUrlPayload) ? (string) ($byUrlPayload['url'] ?? $blogUrl) : $blogUrl;
            }
        }

        if ($blogId === '') {
            $blogsResponse = oauth_http_request('GET', 'https://www.googleapis.com/blogger/v3/users/self/blogs', [
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
            ]);
            $blogsPayload = json_decode($blogsResponse['body'], true);
            $firstBlog = is_array($blogsPayload) ? ($blogsPayload['items'][0] ?? null) : null;
            if (is_array($firstBlog)) {
                $blogId = (string) ($firstBlog['id'] ?? '');
                $blogUrl = (string) ($firstBlog['url'] ?? $blogUrl);
            }
        }

        if ($blogId === '') {
            throw new RuntimeException('Blogger OAuth succeeded, but no blog could be determined for this account.');
        }

        return [
            'blogId' => $blogId,
            'blogUrl' => $blogUrl !== '' ? $blogUrl : null,
        ];
    }

    private static function linkedInMetadata(string $accessToken): array
    {
        $userResponse = oauth_http_request('GET', 'https://api.linkedin.com/v2/userinfo', [
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept' => 'application/json',
        ]);
        $userPayload = json_decode($userResponse['body'], true);
        $personId = is_array($userPayload) ? (string) ($userPayload['sub'] ?? '') : '';
        if ($personId === '') {
            throw new RuntimeException('LinkedIn OAuth succeeded, but the member profile could not be loaded.');
        }

        return [
            'personId' => $personId,
            'name' => is_array($userPayload) ? ($userPayload['name'] ?? null) : null,
        ];
    }

    private static function upsertMetaConnections(
        string|int $userId,
        string $platformKey,
        string $accessToken,
        ?string $expiresAt,
        array $tokenPayload
    ): array {
        $config = platform_oauth_provider_config($platformKey);
        $userToken = $accessToken;

        $exchangeResponse = oauth_http_request(
            'GET',
            $config['token_url'] . '?' . http_build_query([
                'grant_type' => 'fb_exchange_token',
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'fb_exchange_token' => $accessToken,
            ]),
            ['Accept' => 'application/json']
        );
        $exchangePayload = json_decode($exchangeResponse['body'], true);
        if (is_array($exchangePayload) && !empty($exchangePayload['access_token'])) {
            $userToken = (string) $exchangePayload['access_token'];
            if (!empty($exchangePayload['expires_in'])) {
                $expiresAt = date('Y-m-d H:i:s', time() + (int) $exchangePayload['expires_in']);
            } elseif (!empty($tokenPayload['expires_in'])) {
                $expiresAt = date('Y-m-d H:i:s', time() + (int) $tokenPayload['expires_in']);
            }
        }

        $accountsResponse = oauth_http_request(
            'GET',
            'https://graph.facebook.com/v20.0/me/accounts?fields=id,name,access_token,username,instagram_business_account{id,username}&access_token=' . rawurlencode($userToken),
            ['Accept' => 'application/json']
        );
        $accountsPayload = json_decode($accountsResponse['body'], true);
        $page = is_array($accountsPayload) ? ($accountsPayload['data'][0] ?? null) : null;
        if (!is_array($page) || empty($page['id']) || empty($page['access_token'])) {
            throw new RuntimeException('Meta OAuth succeeded, but no managed Facebook Page was available.');
        }

        $pageAccessToken = (string) $page['access_token'];
        $encryptedPageAccessToken = encrypt_string($pageAccessToken, ai_encryption_key());

        self::upsertOAuthConnection('facebook', $userId, $encryptedPageAccessToken, null, $expiresAt, [
            'pageId' => (string) $page['id'],
            'pageName' => $page['name'] ?? null,
            'username' => $page['username'] ?? null,
        ]);

        $connected = ['facebook'];
        $igAccount = $page['instagram_business_account'] ?? null;
        if (is_array($igAccount) && !empty($igAccount['id'])) {
            self::upsertOAuthConnection('instagram', $userId, $encryptedPageAccessToken, null, $expiresAt, [
                'igUserId' => (string) $igAccount['id'],
                'igUsername' => $igAccount['username'] ?? null,
                'linkedPageId' => (string) $page['id'],
            ]);
            $connected[] = 'instagram';
        }

        return $connected;
    }

    private static function extractBloggerBlogIdFromHtml(string $blogUrl): ?array
    {
        $response = @file_get_contents($blogUrl);
        if (!is_string($response) || $response === '') {
            return null;
        }

        if (preg_match('/blogger\.com\/feeds\/(\d+)\/posts\/default/', $response, $matches) !== 1) {
            return null;
        }

        return [
            'blogId' => $matches[1],
            'blogUrl' => $blogUrl,
        ];
    }

    private static function encryptedTokenFromPost(string $field, ?string $existing): ?string
    {
        $value = trim($_POST[$field] ?? '');
        if ($value === '') {
            return $existing;
        }
        return encrypt_string($value, ai_encryption_key());
    }

    private static function resolveSyndicationData(): array
    {
        $postId = (int) ($_POST['post_id'] ?? 0);
        $connectionId = (int) ($_POST['platform_connection_id'] ?? 0);

        if ($postId <= 0) {
            throw new InvalidArgumentException('Post is required.');
        }
        if ($connectionId <= 0) {
            throw new InvalidArgumentException('Platform connection is required.');
        }

        return [
            'post_id' => $postId,
            'platform_connection_id' => $connectionId,
            'external_id' => trim($_POST['external_id'] ?? '') ?: null,
            'external_url' => trim($_POST['external_url'] ?? '') ?: null,
            'status' => $_POST['status'] ?? 'pending',
        ];
    }
}
