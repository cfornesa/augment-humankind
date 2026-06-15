<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class InstagramAdapter implements PlatformAdapter
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client(['timeout' => 15]);
    }

    public function publish(array $connection, SyndicationPayload $payload): SyndicationResult
    {
        if (!$payload->featuredImageUrl) {
            throw new SyndicationConfigurationError('Instagram posts require a featured image URL');
        }

        $pageAccessToken = $this->decryptToken($connection['encrypted_access_token'] ?? '');
        $meta = parseConnectionMeta($connection['metadata'] ?? null);
        $igUserId = $meta['igUserId'] ?? '';

        if (!$igUserId) {
            throw new SyndicationConfigurationError('Instagram connection is missing igUserId in metadata');
        }

        $rawCaption = trim($payload->socialPostDrafts['instagram'] ?? '')
            ?: buildSocialPostText('instagram', ['title' => $payload->title, 'content' => $payload->contentHtml], $payload->categorySlugs ?? [], $payload->canonicalUrl);
        $caption = ensureCanonicalUrl($rawCaption, $payload->canonicalUrl, 'instagram');

        try {
            // Step 1: Create media container
            $containerResponse = $this->client->post("https://graph.facebook.com/v20.0/{$igUserId}/media", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $pageAccessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'image_url' => $payload->featuredImageUrl,
                    'caption' => $caption,
                ],
            ]);
            $containerData = json_decode((string) $containerResponse->getBody(), true);
            $creationId = $containerData['id'] ?? '';

            // Step 2: Publish the container
            $publishResponse = $this->client->post("https://graph.facebook.com/v20.0/{$igUserId}/media_publish", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $pageAccessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => ['creation_id' => $creationId],
            ]);
            $publishData = json_decode((string) $publishResponse->getBody(), true);
            $mediaId = $publishData['id'] ?? '';

            $igUsername = $meta['igUsername'] ?? '';
            $externalUrl = $igUsername ? "https://www.instagram.com/{$igUsername}/" : "https://www.instagram.com/";

            return new SyndicationResult($mediaId, $externalUrl);
        } catch (GuzzleException $e) {
            throw new Exception('Instagram API error: ' . $e->getMessage());
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
