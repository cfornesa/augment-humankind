<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class FacebookAdapter implements PlatformAdapter
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client(['timeout' => 15]);
    }

    public function publish(array $connection, SyndicationPayload $payload): SyndicationResult
    {
        $pageAccessToken = $this->decryptToken($connection['encrypted_access_token'] ?? '');
        $meta = parseConnectionMeta($connection['metadata'] ?? null);
        $pageId = $meta['pageId'] ?? '';

        if (!$pageId) {
            throw new SyndicationConfigurationError('Facebook connection is missing pageId in metadata');
        }

        $rawText = trim($payload->socialPostDrafts['facebook'] ?? '')
            ?: buildSocialPostText('facebook', ['title' => $payload->title, 'content' => $payload->contentHtml], $payload->categorySlugs ?? [], $payload->canonicalUrl);
        $text = ensureCanonicalUrl($rawText, $payload->canonicalUrl, 'facebook');

        try {
            $response = $this->client->post("https://graph.facebook.com/v20.0/{$pageId}/feed", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $pageAccessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'message' => $text,
                    'link' => $payload->canonicalUrl,
                ],
            ]);
            $data = json_decode((string) $response->getBody(), true);
            $externalId = $data['id'] ?? '';
            $postPart = explode('_', $externalId)[1] ?? $externalId;
            $pageUsername = $meta['username'] ?? '';
            $externalUrl = $pageUsername
                ? "https://www.facebook.com/{$pageUsername}/posts/{$postPart}"
                : "https://www.facebook.com/{$pageId}/posts/{$postPart}";

            return new SyndicationResult($externalId, $externalUrl);
        } catch (GuzzleException $e) {
            throw new Exception('Facebook Graph API error: ' . $e->getMessage());
        }
    }

    public function refreshToken(array $connection): ?TokenRefreshResult
    {
        return null;
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
