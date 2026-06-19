<?php

declare(strict_types=1);

function oauth_provider_config(string $provider): array
{
    $provider = strtolower($provider);

    return match ($provider) {
        'github' => [
            'client_id' => oauth_env('GITHUB_CLIENT_ID'),
            'client_secret' => oauth_env('GITHUB_CLIENT_SECRET'),
            'auth_url' => 'https://github.com/login/oauth/authorize',
            'token_url' => 'https://github.com/login/oauth/access_token',
            'user_url' => 'https://api.github.com/user',
            'emails_url' => 'https://api.github.com/user/emails',
            'scope' => 'read:user user:email',
        ],
        'google' => [
            'client_id' => oauth_env('GOOGLE_CLIENT_ID'),
            'client_secret' => oauth_env('GOOGLE_CLIENT_SECRET'),
            'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'userinfo_url' => 'https://openidconnect.googleapis.com/v1/userinfo',
            'scope' => 'openid email profile',
        ],
        default => throw new InvalidArgumentException('Unsupported OAuth provider.'),
    };
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

    if ($provider === 'github') {
        $allowed = array_filter(array_map('trim', explode(',', oauth_env('ADMIN_GITHUB_USERNAMES'))));
        return $allowed !== [] && in_array(strtolower((string) ($profile['login'] ?? '')), array_map('strtolower', $allowed), true);
    }

    if ($provider === 'google') {
        $allowed = array_filter(array_map('trim', explode(',', oauth_env('ADMIN_GOOGLE_EMAILS'))));
        return $allowed !== [] && in_array(strtolower((string) ($profile['email'] ?? '')), array_map('strtolower', $allowed), true);
    }

    return false;
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
