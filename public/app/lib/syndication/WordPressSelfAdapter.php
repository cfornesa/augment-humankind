<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class WordPressSelfAdapter implements PlatformAdapter
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client(['timeout' => 15]);
    }

    public function publish(array $connection, SyndicationPayload $payload): SyndicationResult
    {
        $meta = parseConnectionMeta($connection['metadata'] ?? null);
        $siteUrl = rtrim((string) ($meta['siteUrl'] ?? ''), '/');

        if (!$siteUrl) {
            throw new SyndicationConfigurationError('Self-hosted WordPress connection is missing siteUrl in metadata');
        }

        $basicCredential = $this->decryptToken($connection['encrypted_access_token'] ?? '');

        $featuredMediaId = null;
        if ($payload->featuredImageUrl) {
            $featuredMediaId = $this->uploadFeaturedMedia($siteUrl, $basicCredential, $payload->featuredImageUrl);
        }

        $body = [
            'title' => $payload->title,
            'content' => buildSyndicatedContent($payload),
            'status' => 'publish',
        ];
        if ($featuredMediaId) {
            $body['featured_media'] = $featuredMediaId;
        }

        try {
            $response = $this->client->post($siteUrl . '/wp-json/wp/v2/posts', [
                'headers' => [
                    'Authorization' => 'Basic ' . $basicCredential,
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
            ]);
            $data = json_decode((string) $response->getBody(), true);
            return new SyndicationResult((string) ($data['id'] ?? ''), $data['link'] ?? '');
        } catch (GuzzleException $e) {
            throw new Exception('WordPress self-hosted API error: ' . $e->getMessage());
        }
    }

    public function refreshToken(array $connection): ?TokenRefreshResult
    {
        return null; // App Passwords do not expire
    }

    private function uploadFeaturedMedia(string $siteUrl, string $basicCredential, string $imageUrl): ?int
    {
        try {
            $imageResponse = $this->client->get($imageUrl, ['timeout' => 15]);
            $contentType = $imageResponse->getHeader('Content-Type')[0] ?? 'image/jpeg';
            $buffer = (string) $imageResponse->getBody();
            $filename = basename(parse_url($imageUrl, PHP_URL_PATH) ?: 'image.jpg');

            $response = $this->client->post($siteUrl . '/wp-json/wp/v2/media', [
                'headers' => [
                    'Authorization' => 'Basic ' . $basicCredential,
                    'Content-Type' => $contentType,
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                ],
                'body' => $buffer,
                'timeout' => 30,
            ]);
            $data = json_decode((string) $response->getBody(), true);
            return $data['id'] ?? null;
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
