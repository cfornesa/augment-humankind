<?php

declare(strict_types=1);

class UserAuthController
{
    public static function loginForm(): void
    {
        if (user_logged_in()) {
            header('Location: /user/' . urlencode($_SESSION['user_username'] ?? ''));
            exit;
        }
        $error = $_GET['error'] ?? null;
        $redirect = trim((string) ($_GET['redirect'] ?? ''));
        require dirname(__DIR__) . '/views/user/login.php';
    }

    public static function oauthStart(): void
    {
        $provider = self::requestedProvider();
        $config = oauth_provider_config($provider);
        if ($config['client_id'] === '' || $config['client_secret'] === '') {
            header('Location: /user/login?error=provider');
            exit;
        }

        $redirect = trim((string) ($_GET['redirect'] ?? ''));
        $state = bin2hex(random_bytes(16));
        $_SESSION['user_oauth_state'] = [
            'provider' => $provider,
            'value' => $state,
            'redirect' => $redirect,
        ];

        $params = [
            'client_id' => $config['client_id'],
            'redirect_uri' => shared_oauth_redirect_uri($provider),
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

    public static function handlesPendingCallback(string $provider): bool
    {
        $state = $_SESSION['user_oauth_state'] ?? null;
        return is_array($state) && ($state['provider'] ?? null) === $provider;
    }

    public static function handleCallback(string $provider): void
    {
        $state = $_SESSION['user_oauth_state'] ?? null;

        if (!is_array($state) || ($state['provider'] ?? null) !== $provider || ($state['value'] ?? '') !== ($_GET['state'] ?? '')) {
            header('Location: /user/login?error=state');
            exit;
        }
        $redirect = (string) ($state['redirect'] ?? '');
        unset($_SESSION['user_oauth_state']);

        $code = trim((string) ($_GET['code'] ?? ''));
        if ($code === '') {
            header('Location: /user/login?error=oauth');
            exit;
        }

        try {
            $profile = self::fetchOauthProfile($provider, $code);
            $user = self::upsertMember($provider, $profile);
            user_login($user);

            // Mirror of AuthController::handleCallback granting the member session on
            // admin login: if this same OAuth identity is on the admin allowlist, also
            // grant the admin session here. Member signup itself stays open to anyone —
            // only identities that already pass oauth_allowed_identity() (the same gate
            // admin login uses) get the admin session, so this can't be used to
            // self-escalate.
            if (class_exists('AdminIdentity') && oauth_allowed_identity($provider, $profile)) {
                $identityId = AdminIdentity::upsertFromProfile([
                    'provider' => $provider,
                    'provider_subject' => (string) $profile['provider_subject'],
                    'email' => $profile['email'] ?? null,
                    'display_name' => (string) $profile['display_name'],
                    'avatar_url' => $profile['avatar_url'] ?? null,
                ]);
                $identity = AdminIdentity::find($identityId);
                if ($identity) {
                    admin_login_identity($identity);
                }
            }

            $dest = ($redirect !== '' && str_starts_with($redirect, '/')) ? $redirect : '/user/' . urlencode($user['username'] ?? '');
            header('Location: ' . $dest);
            exit;
        } catch (Throwable $e) {
            error_log('[user-oauth] ' . $provider . ': ' . $e->getMessage());
            $query = 'error=oauth';
            if (oauth_is_local_request()) {
                $query .= '&detail=' . rawurlencode(oauth_debug_detail($e));
            }
            header('Location: /user/login?' . $query);
            exit;
        }
    }

    public static function logout(): void
    {
        user_logout();
        header('Location: /');
        exit;
    }

    private static function upsertMember(string $provider, array $profile): array
    {
        $providerSubject = (string) ($profile['provider_subject'] ?? '');
        $email = trim((string) ($profile['email'] ?? ''));

        // Look for existing account link
        $stmt = db()->prepare(
            'SELECT user_id FROM accounts WHERE provider = ? AND provider_account_id = ? LIMIT 1'
        );
        $stmt->execute([$provider, $providerSubject]);
        $accountRow = $stmt->fetch();

        if ($accountRow) {
            $userId = (string) $accountRow['user_id'];
            // Refresh display info
            $stmt2 = db()->prepare(
                'UPDATE users SET name = ?, image = ?, last_login_at = NOW(), updated_at = NOW() WHERE id = ?'
            );
            $stmt2->execute([
                $profile['display_name'] ?? null,
                $profile['avatar_url'] ?? null,
                $userId,
            ]);
        } else {
            // Check if email already exists
            $existing = null;
            if ($email !== '') {
                $stmt3 = db()->prepare('SELECT id, username FROM users WHERE email = ? LIMIT 1');
                $stmt3->execute([$email]);
                $existing = $stmt3->fetch() ?: null;
            }

            if ($existing) {
                $userId = (string) $existing['id'];
            } else {
                $userId = self::uuid();
                $username = self::usernameFromProfile($provider, $profile);
                $stmt4 = db()->prepare(
                    'INSERT INTO users (id, name, username, email, image, role, status, last_login_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
                );
                $stmt4->execute([
                    $userId,
                    $profile['display_name'] ?? 'Member',
                    $username,
                    $email !== '' ? $email : null,
                    $profile['avatar_url'] ?? null,
                    'member',
                    'active',
                ]);
            }

            // Link the account
            $stmt5 = db()->prepare(
                'INSERT IGNORE INTO accounts (user_id, type, provider, provider_account_id)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt5->execute([$userId, 'oauth', $provider, $providerSubject]);
        }

        $stmt6 = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt6->execute([$userId]);
        $user = $stmt6->fetch();
        if (!$user) {
            throw new RuntimeException('User could not be loaded after login.');
        }

        // Populate style columns with site defaults when they are all null
        // (new user at signup, or existing user who signed up before defaults were captured)
        if (($user['theme'] ?? null) === null) {
            $user = self::applyStyleDefaults($userId, $user);
        }

        return $user;
    }

    private static function applyStyleDefaults(string $userId, array $user): array
    {
        $defaults = self::siteStyleDefaults();

        $sets = [];
        $params = [];
        foreach ($defaults as $col => $val) {
            if ($val !== null && ($user[$col] ?? null) === null) {
                $sets[] = "`$col` = ?";
                $params[] = $val;
            }
        }

        if ($sets === []) {
            return $user;
        }

        $params[] = $userId;
        db()->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?')
            ->execute($params);

        // Reload with populated values
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: $user;
    }

    private static function siteStyleDefaults(): array
    {
        $s = (class_exists('SiteSettings') ? SiteSettings::current() : false) ?: [];

        $cols = [
            'theme', 'palette',
            'color_background', 'color_foreground',
            'color_background_dark', 'color_foreground_dark',
            'color_primary', 'color_primary_foreground',
            'color_secondary', 'color_secondary_foreground',
            'color_accent', 'color_accent_foreground',
            'color_muted', 'color_muted_foreground',
            'color_destructive', 'color_destructive_foreground',
        ];

        $result = [];
        foreach ($cols as $col) {
            $val = (isset($s[$col]) && $s[$col] !== '') ? (string) $s[$col] : null;
            if ($val === null && $col === 'theme') {
                $val = 'bauhaus';
            }
            if ($val === null && $col === 'palette') {
                $val = 'bauhaus';
            }
            $result[$col] = $val;
        }

        return $result;
    }

    private static function usernameFromProfile(string $provider, array $profile): string
    {
        $base = $provider === 'github'
            ? (string) ($profile['login'] ?? $profile['display_name'] ?? '')
            : (string) ($profile['display_name'] ?? '');
        $base = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9_]+/', '-', $base), '-'));
        $base = $base !== '' ? substr($base, 0, 60) : 'user';

        // Ensure uniqueness
        $candidate = $base;
        $suffix = 2;
        while (true) {
            $stmt = db()->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$candidate]);
            if (!$stmt->fetchColumn()) {
                break;
            }
            $candidate = $base . '-' . $suffix++;
        }
        return $candidate;
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
                'redirect_uri' => shared_oauth_redirect_uri($provider),
                'grant_type' => 'authorization_code',
            ])
        );
        $tokenPayload = json_decode($tokenResponse['body'], true);
        $accessToken = is_array($tokenPayload) ? (string) ($tokenPayload['access_token'] ?? '') : '';
        if ($accessToken === '') {
            $err = is_array($tokenPayload) ? (string) ($tokenPayload['error_description'] ?? $tokenPayload['error'] ?? '') : '';
            throw new RuntimeException('OAuth token exchange failed.' . ($err !== '' ? ' ' . $err : ''));
        }

        if ($provider === 'github') {
            $userResponse = oauth_http_request('GET', $config['user_url'], [
                'Accept' => 'application/vnd.github+json',
                'Authorization' => 'Bearer ' . $accessToken,
                'User-Agent' => 'PhpCmsOAuth/1.0',
            ]);
            $user = json_decode($userResponse['body'], true);
            if (!is_array($user) || empty($user['id']) || empty($user['login'])) {
                throw new RuntimeException('GitHub profile could not be loaded.');
            }

            $email = isset($user['email']) && $user['email'] !== '' ? (string) $user['email'] : null;
            if ($email === null) {
                $emailResponse = oauth_http_request('GET', $config['emails_url'], [
                    'Accept' => 'application/vnd.github+json',
                    'Authorization' => 'Bearer ' . $accessToken,
                    'User-Agent' => 'PhpCmsOAuth/1.0',
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
            throw new RuntimeException('Google profile could not be loaded.');
        }

        return [
            'provider_subject' => (string) $user['sub'],
            'email' => (string) $user['email'],
            'display_name' => (string) ($user['name'] ?? $user['email']),
            'avatar_url' => (string) ($user['picture'] ?? ''),
        ];
    }

    private static function requestedProvider(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
        if (preg_match('#/user/auth/(github|google)/#', $path, $matches)) {
            return $matches[1];
        }
        if (preg_match('#/user/auth/(github|google)/start#', $path, $matches)) {
            return $matches[1];
        }
        throw new InvalidArgumentException('Unknown OAuth provider.');
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
