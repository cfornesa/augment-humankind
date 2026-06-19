<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class WordPressComAdapter implements PlatformAdapter
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
        $siteId = $meta['blogId'] ?? null;

        if (!$siteId) {
            try {
                $response = $this->client->get('https://public-api.wordpress.com/rest/v1.1/me/sites', [
                    'headers' => ['Authorization' => 'Bearer ' . $token],
                ]);
                $data = json_decode((string) $response->getBody(), true);
                $first = $data['sites'][0] ?? null;
                if ($first) {
                    $siteId = (string) $first['ID'];
                }
            } catch (Throwable) {
                // ignore
            }
        }

        if (!$siteId) {
            throw new SyndicationConfigurationError('WordPress.com: no blog found on this account');
        }

        $body = [
            'title' => $payload->title,
            'content' => buildSyndicatedContent($payload),
            'status' => 'publish',
            'format' => 'standard',
        ];
        if ($payload->featuredImageUrl) {
            $body['featured_image'] = $payload->featuredImageUrl;
        }

        try {
            $response = $this->client->post("https://public-api.wordpress.com/rest/v1.1/sites/{$siteId}/posts/new", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
            ]);
            $data = json_decode((string) $response->getBody(), true);
            return new SyndicationResult((string) ($data['ID'] ?? ''), $data['URL'] ?? '');
        } catch (GuzzleException $e) {
            throw new Exception('WordPress.com API error: ' . $e->getMessage());
        }
    }

    public function refreshToken(array $connection): ?TokenRefreshResult
    {
        $refreshToken = $connection['encrypted_refresh_token'] ?? '';
        if (!$refreshToken) {
            return null;
        }
        $refreshToken = $this->decryptToken($refreshToken);

        $credentials = PlatformOAuthApp::decryptedCredentialsForPlatform('wordpress_com');
        $clientId = $credentials['client_id'] ?? '';
        $clientSecret = $credentials['client_secret'] ?? '';

        if (!$clientId || !$clientSecret) {
            return null;
        }

        try {
            $response = $this->client->post('https://public-api.wordpress.com/oauth2/token', [
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
