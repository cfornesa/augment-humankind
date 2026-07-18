<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function mock_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function mock_redirect(string $url): never
{
    header('Location: ' . $url, true, 302);
    exit;
}

function mock_param(string $key): string
{
    return trim((string) ($_GET[$key] ?? $_POST[$key] ?? ''));
}

if ($path === '/github/authorize') {
    $redirectUri = mock_param('redirect_uri');
    $state = mock_param('state');
    mock_redirect($redirectUri . '?' . http_build_query([
        'code' => 'mock-github-code',
        'state' => $state,
    ]));
}

if ($path === '/github/token' && $method === 'POST') {
    mock_json([
        'access_token' => 'mock-github-token',
        'token_type' => 'bearer',
        'scope' => 'read:user user:email',
    ]);
}

if ($path === '/github/user') {
    mock_json([
        'id' => 12345,
        'login' => 'tester',
        'name' => 'Test GitHub Admin',
        'email' => null,
        'avatar_url' => 'https://example.test/github-avatar.png',
    ]);
}

if ($path === '/github/emails') {
    mock_json([
        ['email' => 'tester@example.com', 'primary' => true, 'verified' => true],
    ]);
}

if ($path === '/google/authorize') {
    $redirectUri = mock_param('redirect_uri');
    $state = mock_param('state');
    mock_redirect($redirectUri . '?' . http_build_query([
        'code' => 'mock-google-code',
        'state' => $state,
    ]));
}

if ($path === '/google/token' && $method === 'POST') {
    mock_json([
        'access_token' => 'mock-google-token',
        'token_type' => 'Bearer',
        'expires_in' => 3600,
    ]);
}

if ($path === '/google/userinfo') {
    mock_json([
        'sub' => 'google-sub-987',
        'email' => 'test@example.com',
        'name' => 'Test Google Admin',
        'picture' => 'https://example.test/google-avatar.png',
    ]);
}

// Unsigned mock JWT: header.payload. with a fake signature segment —
// the app decodes payload claims only for tokens fetched over TLS.
function mock_id_token(array $claims): string
{
    $encode = static fn (array $data): string => rtrim(strtr(base64_encode((string) json_encode($data)), '+/', '-_'), '=');

    return $encode(['alg' => 'none', 'typ' => 'JWT']) . '.' . $encode($claims) . '.mock';
}

if ($path === '/microsoft/authorize') {
    $redirectUri = mock_param('redirect_uri');
    $state = mock_param('state');
    mock_redirect($redirectUri . '?' . http_build_query([
        'code' => 'mock-microsoft-code',
        'state' => $state,
    ]));
}

if ($path === '/microsoft/token' && $method === 'POST') {
    mock_json([
        'access_token' => 'mock-microsoft-token',
        'token_type' => 'Bearer',
        'expires_in' => 3600,
        'id_token' => mock_id_token([
            'sub' => 'microsoft-sub-555',
            'name' => 'Test Microsoft Admin',
            'preferred_username' => 'ms-test@example.com',
        ]),
    ]);
}

if ($path === '/microsoft/userinfo') {
    mock_json([
        'sub' => 'microsoft-sub-555',
        'email' => 'ms-test@example.com',
        'name' => 'Test Microsoft Admin',
    ]);
}

if ($path === '/facebook/authorize') {
    $redirectUri = mock_param('redirect_uri');
    $state = mock_param('state');
    mock_redirect($redirectUri . '?' . http_build_query([
        'code' => 'mock-facebook-code',
        'state' => $state,
    ]));
}

if ($path === '/facebook/token' && $method === 'POST') {
    mock_json([
        'access_token' => 'mock-facebook-token',
        'token_type' => 'bearer',
        'expires_in' => 3600,
    ]);
}

if ($path === '/facebook/me') {
    // Omit email with ?no_email=1 to exercise the declined-permission path.
    $payload = [
        'id' => '10999888777',
        'name' => 'Test Facebook Admin',
        'picture' => ['data' => ['url' => 'https://example.test/fb-avatar.png']],
    ];
    if (mock_param('no_email') === '') {
        $payload['email'] = 'fb-test@example.com';
    }
    mock_json($payload);
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "Not found\n";
