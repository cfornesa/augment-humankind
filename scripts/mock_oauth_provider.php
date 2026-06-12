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

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "Not found\n";
