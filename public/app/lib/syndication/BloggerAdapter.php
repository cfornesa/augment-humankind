<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class BloggerAdapter implements PlatformAdapter
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client(['timeout' => 15]);
    }

    public function publish(array $connection, SyndicationPayload $payload): SyndicationResult
    {
        $token = $this->decryptToken($connection['encrypted_access_token'] ?? '');
        $meta = parseConnectionMeta($connection['metadata'] ?? null);
        $blogId = $meta['blogId'] ?? '';

        if (!$blogId) {
            throw new SyndicationConfigurationError('Blogger connection is missing blogId in metadata');
        }

        try {
            $response = $this->client->post("https://www.googleapis.com/blogger/v3/blogs/{$blogId}/posts/", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'kind' => 'blogger#post',
                    'title' => $payload->title,
                    'content' => buildSyndicatedContent($payload, ['prependFeaturedImage' => true]),
                ],
            ]);
            $data = json_decode((string) $response->getBody(), true);
            return new SyndicationResult($data['id'] ?? '', $data['url'] ?? '');
        } catch (GuzzleException $e) {
            throw new Exception('Blogger API error: ' . $e->getMessage());
        }
    }

    public function refreshToken(array $connection): ?TokenRefreshResult
    {
        $refreshToken = $connection['encrypted_refresh_token'] ?? '';
        if (!$refreshToken) {
            return null;
        }
        $refreshToken = $this->decryptToken($refreshToken);

        $clientId = $_ENV['BLOGGER_GOOGLE_CLIENT_ID'] ?? getenv('BLOGGER_GOOGLE_CLIENT_ID') ?? '';
        $clientSecret = $_ENV['BLOGGER_GOOGLE_CLIENT_SECRET'] ?? getenv('BLOGGER_GOOGLE_CLIENT_SECRET') ?? '';

        if (!$clientId || !$clientSecret) {
            return null;
        }

        try {
            $response = $this->client->post('https://oauth2.googleapis.com/token', [
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'form_params' => [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                ],
            ]);
            $data = json_decode((string) $response->getBody(), true);
            $expiresAt = !empty($data['expires_in']) ? date('Y-m-d H:i:s', time() + (int) $data['expires_in']) : null;
            return new TokenRefreshResult($data['access_token'], $data['refresh_token'] ?? null, $expiresAt);
        } catch (Throwable) {
            return null;
        }
    }

    private function decryptToken(string $encrypted): string
    {
        if (!$encrypted) return '';
        try {
            return decrypt_string($encrypted, ai_encryption_key());
        } catch (Throwable) {
            return $encrypted;
        }
    }
}
