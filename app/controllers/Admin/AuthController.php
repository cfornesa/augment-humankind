<?php

declare(strict_types=1);

class AuthController
{
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
        $config = oauth_provider_config($provider);
        if ($config['client_id'] === '' || $config['client_secret'] === '') {
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
        $state = $_SESSION['oauth_state'] ?? null;

        if (!is_array($state) || ($state['provider'] ?? null) !== $provider || ($state['value'] ?? '') !== ($_GET['state'] ?? '')) {
            header('Location: /admin/login?error=state');
            exit;
        }
        unset($_SESSION['oauth_state']);

        $code = trim((string) ($_GET['code'] ?? ''));
        if ($code === '') {
            header('Location: /admin/login?error=oauth');
            exit;
        }

        try {
            $profile = self::fetchOauthProfile($provider, $code);
            if (!oauth_allowed_identity($provider, $profile)) {
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

            admin_login_identity($identity);
            header('Location: /admin');
            exit;
        } catch (Throwable $e) {
            error_log('[admin-oauth] ' . $provider . ': ' . $e->getMessage());

            $query = 'error=oauth';
            if (oauth_is_local_request()) {
                $query .= '&detail=' . rawurlencode(oauth_debug_detail($e));
            }

            header('Location: /admin/login?' . $query);
            exit;
        }
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

        $artworkCount  = (int) db()->query('SELECT COUNT(*) FROM artworks WHERE deleted_at IS NULL')->fetchColumn();
        $categoryCount = (int) db()->query('SELECT COUNT(*) FROM categories WHERE deleted_at IS NULL')->fetchColumn();
        $exhibitCount  = (int) db()->query('SELECT COUNT(*) FROM exhibits WHERE deleted_at IS NULL')->fetchColumn();
        $pageCount     = (int) db()->query('SELECT COUNT(*) FROM pages WHERE deleted_at IS NULL')->fetchColumn();

        require dirname(__DIR__, 2) . '/views/admin/dashboard.php';
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
                'User-Agent' => 'AugmentHumankindAdminOAuth/1.0',
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
                    'User-Agent' => 'AugmentHumankindAdminOAuth/1.0',
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
