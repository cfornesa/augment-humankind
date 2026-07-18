<?php

declare(strict_types=1);

function oauth_provider_registry(): array
{
    return [
        'github' => [
            'label' => 'GitHub',
            'client_id_env' => 'GITHUB_CLIENT_ID',
            'client_secret_env' => 'GITHUB_CLIENT_SECRET',
            'auth_url' => 'https://github.com/login/oauth/authorize',
            'token_url' => 'https://github.com/login/oauth/access_token',
            'user_url' => 'https://api.github.com/user',
            'emails_url' => 'https://api.github.com/user/emails',
            'scope' => 'read:user user:email',
            'admin_allowlist_env' => 'ADMIN_GITHUB_USERNAMES',
            'admin_allowlist_kind' => 'login',
        ],
        'google' => [
            'label' => 'Google',
            'client_id_env' => 'GOOGLE_CLIENT_ID',
            'client_secret_env' => 'GOOGLE_CLIENT_SECRET',
            'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'userinfo_url' => 'https://openidconnect.googleapis.com/v1/userinfo',
            'scope' => 'openid email profile',
            'select_account' => true,
            'admin_allowlist_env' => 'ADMIN_GOOGLE_EMAILS',
            'admin_allowlist_kind' => 'email',
        ],
        'microsoft' => [
            'label' => 'Microsoft',
            'client_id_env' => 'MICROSOFT_CLIENT_ID',
            'client_secret_env' => 'MICROSOFT_CLIENT_SECRET',
            'auth_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'token_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'userinfo_url' => 'https://graph.microsoft.com/oidc/userinfo',
            'scope' => 'openid email profile',
            'select_account' => true,
            // /oidc/userinfo may omit email for personal accounts; the id_token carries it.
            'id_token_claims_fallback' => true,
            'admin_allowlist_env' => 'ADMIN_MICROSOFT_EMAILS',
            'admin_allowlist_kind' => 'email',
        ],
        'facebook' => [
            'label' => 'Facebook',
            'client_id_env' => 'FACEBOOK_CLIENT_ID',
            'client_secret_env' => 'FACEBOOK_CLIENT_SECRET',
            'auth_url' => 'https://www.facebook.com/v18.0/dialog/oauth',
            'token_url' => 'https://graph.facebook.com/v18.0/oauth/access_token',
            'userinfo_url' => 'https://graph.facebook.com/v18.0/me?fields=id,name,email,picture.type(large)',
            'scope' => 'public_profile,email',
            // Users can decline the email permission; the account is then keyed
            // solely on the app-scoped user id.
            'email_optional' => true,
            'admin_allowlist_env' => 'ADMIN_FACEBOOK_IDS',
            'admin_allowlist_kind' => 'subject',
        ],
    ];
}

function oauth_provider_config(string $provider): array
{
    $provider = strtolower($provider);
    $registry = oauth_provider_registry();
    $entry = $registry[$provider] ?? null;
    if ($entry === null) {
        throw new InvalidArgumentException('Unsupported OAuth provider.');
    }

    $entry['provider'] = $provider;
    $entry['client_id'] = oauth_env((string) $entry['client_id_env']);
    $entry['client_secret'] = oauth_env((string) $entry['client_secret_env']);

    return $entry;
}

function oauth_enabled_providers(): array
{
    $enabled = [];
    foreach (array_keys(oauth_provider_registry()) as $provider) {
        $config = oauth_provider_config($provider);
        if ($config['client_id'] !== '' && $config['client_secret'] !== '') {
            $enabled[$provider] = $config;
        }
    }

    return $enabled;
}

function shared_oauth_redirect_uri(string $provider): string
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/auth/' . strtolower($provider) . '/callback';
}

function oauth_http_request(string $method, string $url, array $headers = [], ?string $body = null): array
{
    $headerLines = [];
    foreach ($headers as $key => $value) {
        $headerLines[] = is_string($key) ? ($key . ': ' . $value) : $value;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_HEADER => true,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $response = curl_exec($ch);
        if ($response === false) {
            $message = curl_error($ch);
            throw new RuntimeException($message ?: 'OAuth request failed.');
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        return [
            'status' => $status,
            'body' => substr($response, $headerSize),
        ];
    }

    $context = stream_context_create([
        'http' => [
            'method' => strtoupper($method),
            'header' => implode("\r\n", $headerLines),
            'content' => $body ?? '',
            'ignore_errors' => true,
            'timeout' => 20,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        throw new RuntimeException('OAuth request failed.');
    }

    $status = 0;
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('#HTTP/\S+\s+([0-9]{3})#', $line, $matches)) {
            $status = (int) $matches[1];
            break;
        }
    }

    return [
        'status' => $status,
        'body' => $response,
    ];
}

function oauth_allowed_identity(string $provider, array $profile): bool
{
    $provider = strtolower($provider);

    // The magic-link pseudo-provider has no registry entry; admins are the
    // addresses listed in ADMIN_EMAILS.
    if ($provider === 'email') {
        $allowlistEnv = 'ADMIN_EMAILS';
        $kind = 'email';
    } else {
        $registry = oauth_provider_registry();
        $entry = $registry[$provider] ?? null;
        if ($entry === null) {
            return false;
        }
        $allowlistEnv = (string) ($entry['admin_allowlist_env'] ?? '');
        $kind = (string) ($entry['admin_allowlist_kind'] ?? '');
    }

    $subject = match ($kind) {
        'login' => (string) ($profile['login'] ?? ''),
        'email' => (string) ($profile['email'] ?? ''),
        'subject' => (string) ($profile['provider_subject'] ?? ''),
        default => '',
    };
    if ($allowlistEnv === '' || $subject === '') {
        return false;
    }

    $allowed = array_filter(array_map('trim', explode(',', oauth_env($allowlistEnv))));
    return $allowed !== [] && in_array(strtolower($subject), array_map('strtolower', $allowed), true);
}

function oauth_env(string $key): string
{
    $value = trim((string) ($_ENV[$key] ?? ''));
    if ($value !== '') {
        return $value;
    }

    $envValue = getenv($key);
    if ($envValue === false) {
        return '';
    }

    return trim((string) $envValue);
}

// Payload-only decode. Safe for id_tokens received directly from the
// provider's token endpoint over TLS (never for tokens arriving via the
// browser), so signature verification is intentionally omitted.
function oauth_decode_jwt_claims(string $jwt): array
{
    $segments = explode('.', $jwt);
    if (count($segments) < 2) {
        return [];
    }
    $payload = base64_decode(strtr($segments[1], '-_', '+/'), false);
    if ($payload === false) {
        return [];
    }
    $claims = json_decode($payload, true);

    return is_array($claims) ? $claims : [];
}

/**
 * Exchange an authorization code and load a normalized profile:
 * provider_subject, email (nullable), display_name, avatar_url,
 * plus login for GitHub.
 */
function oauth_fetch_profile(string $provider, string $code, string $userAgent): array
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
            'User-Agent' => $userAgent,
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
                'User-Agent' => $userAgent,
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

    if ($provider === 'facebook') {
        $userResponse = oauth_http_request('GET', $config['userinfo_url'], [
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept' => 'application/json',
        ]);
        $user = json_decode($userResponse['body'], true);
        if (!is_array($user) || empty($user['id'])) {
            throw new RuntimeException('Facebook profile could not be loaded from the provider response.');
        }

        $email = isset($user['email']) && $user['email'] !== '' ? (string) $user['email'] : null;

        return [
            'provider_subject' => (string) $user['id'],
            'email' => $email,
            'display_name' => (string) (($user['name'] ?? '') !== '' ? $user['name'] : ($email ?? 'Facebook user')),
            'avatar_url' => (string) ($user['picture']['data']['url'] ?? ''),
        ];
    }

    // OIDC providers: Google, Microsoft.
    $userResponse = oauth_http_request('GET', $config['userinfo_url'], [
        'Authorization' => 'Bearer ' . $accessToken,
        'Accept' => 'application/json',
    ]);
    $user = json_decode($userResponse['body'], true);
    if (!is_array($user)) {
        $user = [];
    }

    if (!empty($config['id_token_claims_fallback']) && (empty($user['sub']) || empty($user['email']))) {
        $claims = oauth_decode_jwt_claims((string) ($tokenPayload['id_token'] ?? ''));
        $fallback = [
            'sub' => $claims['sub'] ?? '',
            'name' => $claims['name'] ?? '',
            // Microsoft personal accounts surface the address as preferred_username.
            'email' => (string) ($claims['email'] ?? $claims['preferred_username'] ?? ''),
        ];
        foreach ($fallback as $key => $value) {
            if (empty($user[$key]) && $value !== '') {
                $user[$key] = $value;
            }
        }
    }

    if (empty($user['sub']) || empty($user['email'])) {
        throw new RuntimeException(($config['label'] ?? 'OIDC') . ' profile could not be loaded from the provider response.');
    }

    return [
        'provider_subject' => (string) $user['sub'],
        'email' => (string) $user['email'],
        'display_name' => (string) (($user['name'] ?? '') !== '' ? $user['name'] : $user['email']),
        'avatar_url' => (string) ($user['picture'] ?? ''),
    ];
}

function oauth_is_local_request(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $server = strtolower((string) ($_SERVER['SERVER_NAME'] ?? ''));

    foreach ([$host, $server] as $value) {
        if ($value === '') {
            continue;
        }
        if (str_contains($value, 'localhost') || str_contains($value, '127.0.0.1')) {
            return true;
        }
    }

    return false;
}

function oauth_debug_detail(Throwable $error): string
{
    $message = trim($error->getMessage());
    if ($message === '') {
        return 'OAuth request failed.';
    }

    return preg_replace('/\s+/', ' ', $message) ?: 'OAuth request failed.';
}

// ─── Platform OAuth helpers ─────────────────────────────────────────────────

function platform_oauth_provider_config(string $provider): array
{
    $provider = platform_oauth_provider_key($provider);
    $credentials = PlatformOAuthApp::decryptedCredentialsForPlatform($provider);

    return match ($provider) {
        'wordpress_com' => [
            'client_id' => $credentials['client_id'] ?? '',
            'client_secret' => $credentials['client_secret'] ?? '',
            'blog_url' => $credentials['blog_url'] ?? null,
            'auth_url' => 'https://public-api.wordpress.com/oauth2/authorize',
            'token_url' => 'https://public-api.wordpress.com/oauth2/token',
            'scope' => 'global',
        ],
        'blogger' => [
            'client_id' => $credentials['client_id'] ?? '',
            'client_secret' => $credentials['client_secret'] ?? '',
            'blog_url' => $credentials['blog_url'] ?? null,
            'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'scope' => 'https://www.googleapis.com/auth/blogger',
        ],
        'linkedin' => [
            'client_id' => $credentials['client_id'] ?? '',
            'client_secret' => $credentials['client_secret'] ?? '',
            'auth_url' => 'https://www.linkedin.com/oauth/v2/authorization',
            'token_url' => 'https://www.linkedin.com/oauth/v2/accessToken',
            'scope' => 'w_member_social openid profile email',
        ],
        'facebook' => [
            'client_id' => $credentials['client_id'] ?? '',
            'client_secret' => $credentials['client_secret'] ?? '',
            'auth_url' => 'https://www.facebook.com/v18.0/dialog/oauth',
            'token_url' => 'https://graph.facebook.com/v18.0/oauth/access_token',
            'scope' => 'pages_manage_posts',
        ],
        'instagram' => [
            'client_id' => $credentials['client_id'] ?? '',
            'client_secret' => $credentials['client_secret'] ?? '',
            'auth_url' => 'https://www.facebook.com/v18.0/dialog/oauth',
            'token_url' => 'https://graph.facebook.com/v18.0/oauth/access_token',
            'scope' => 'instagram_basic,instagram_content_publish',
        ],
        default => throw new InvalidArgumentException('Unsupported platform OAuth provider.'),
    };
}

function platform_oauth_redirect_uri(string $provider): string
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/admin/platform-connections/auth/' . platform_oauth_route_slug($provider) . '/callback';
}

function platform_oauth_supported_providers(): array
{
    return ['wordpress-com', 'blogger', 'linkedin', 'facebook', 'instagram'];
}

function platform_oauth_provider_key(string $provider): string
{
    return str_replace('-', '_', strtolower(trim($provider)));
}

function platform_oauth_route_slug(string $provider): string
{
    return str_replace('_', '-', strtolower(trim($provider)));
}
