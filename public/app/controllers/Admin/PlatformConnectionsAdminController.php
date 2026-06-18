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

            $siteTitle = 'Augment Humankind';
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
            header('Location: /admin/platform-connections?error=' . urlencode($e->getMessage()));
        }
        exit;
    }

    public static function oauthStart(string $platform): void
    {
        admin_check();

        try {
            $config = platform_oauth_provider_config($platform);
            if ($config['client_id'] === '' || $config['client_secret'] === '') {
                header('Location: /admin/platform-connections?error=' . urlencode('OAuth credentials not configured for ' . $platform));
                exit;
            }

            $state = bin2hex(random_bytes(16));
            $_SESSION['platform_oauth_state'] = [
                'platform' => $platform,
                'value' => $state,
            ];

            $params = [
                'client_id' => $config['client_id'],
                'redirect_uri' => platform_oauth_redirect_uri($platform),
                'response_type' => 'code',
                'scope' => $config['scope'],
                'state' => $state,
            ];

            if ($platform === 'blogger') {
                $params['access_type'] = 'offline';
                $params['prompt'] = 'consent';
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

        $state = $_SESSION['platform_oauth_state'] ?? null;
        if (!is_array($state) || ($state['platform'] ?? null) !== $platform || ($state['value'] ?? '') !== ($_GET['state'] ?? '')) {
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
            $config = platform_oauth_provider_config($platform);
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
                    'redirect_uri' => platform_oauth_redirect_uri($platform),
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

            // Find or create connection for this platform
            $owner = PlatformUser::owner();
            $userId = $owner ? $owner['id'] : null;
            $existing = self::findConnectionByPlatform($platform);

            if ($existing) {
                PlatformConnection::update((int) $existing['id'], [
                    'user_id' => $userId,
                    'platform' => $platform,
                    'encrypted_access_token' => $encryptedAccess,
                    'encrypted_refresh_token' => $encryptedRefresh,
                    'expires_at' => $expiresAt,
                    'metadata' => $existing['metadata'] ?? null,
                    'enabled' => 1,
                ]);
                $connectionId = $existing['id'];
            } else {
                $connectionId = PlatformConnection::create([
                    'user_id' => $userId,
                    'platform' => $platform,
                    'encrypted_access_token' => $encryptedAccess,
                    'encrypted_refresh_token' => $encryptedRefresh,
                    'expires_at' => $expiresAt,
                    'metadata' => null,
                    'enabled' => 1,
                ]);
            }

            header('Location: /admin/platform-connections?success=oauth&platform=' . urlencode($platform));
            exit;
        } catch (Throwable $e) {
            error_log('[platform-oauth] ' . $platform . ': ' . $e->getMessage());
            header('Location: /admin/platform-connections?error=' . urlencode('OAuth failed for ' . $platform . '. ' . $e->getMessage()));
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
                $results[$provider] = [
                    'configured' => $hasCredentials,
                    'client_id_set' => $config['client_id'] !== '',
                    'client_secret_set' => $config['client_secret'] !== '',
                    'redirect_uri' => platform_oauth_redirect_uri($provider),
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
