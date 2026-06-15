<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class BlueskyAdapter implements PlatformAdapter
{
    private const BSKY_HOST = 'https://bsky.social';
    private Client $client;

    public function __construct()
    {
        $this->client = new Client(['timeout' => 15]);
    }

    public function publish(array $connection, SyndicationPayload $payload): SyndicationResult
    {
        $appPassword = $this->decryptToken($connection['encrypted_access_token'] ?? '');
        $meta = parseConnectionMeta($connection['metadata'] ?? null);
        $handle = $meta['handle'] ?? '';
        if (!$handle) {
            throw new SyndicationConfigurationError('Bluesky connection is missing handle in metadata');
        }

        $session = $this->createSession($handle, $appPassword);
        $accessJwt = $session['accessJwt'];
        $did = $session['did'];

        $rawText = trim($payload->socialPostDrafts['bluesky'] ?? '')
            ?: buildSocialPostText('bluesky', ['title' => $payload->title, 'content' => $payload->contentHtml], $payload->categorySlugs ?? [], $payload->canonicalUrl);
        $text = ensureCanonicalUrl($rawText, $payload->canonicalUrl, 'bluesky');

        $urlFacet = $this->buildUrlFacet($text, $payload->canonicalUrl);
        $card = buildLinkCardMetadata(['title' => $payload->title, 'contentHtml' => $payload->contentHtml, 'canonicalUrl' => $payload->canonicalUrl]);

        $record = [
            '$type' => 'app.bsky.feed.post',
            'text' => $text,
            'createdAt' => gmdate('c'),
            'langs' => ['en'],
        ];
        if ($urlFacet) {
            $record['facets'] = [$urlFacet];
        }

        $thumb = null;
        if ($payload->featuredImageUrl) {
            $thumb = $this->uploadBlobFromUrl($payload->featuredImageUrl, $accessJwt);
        }

        $record['embed'] = [
            '$type' => 'app.bsky.embed.external',
            'external' => [
                'uri' => $card['source'],
                'title' => $card['title'],
                'description' => $card['description'],
            ],
        ];
        if ($thumb) {
            $record['embed']['external']['thumb'] = $thumb;
        }

        try {
            $response = $this->client->post(self::BSKY_HOST . '/xrpc/com.atproto.repo.createRecord', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessJwt,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'repo' => $did,
                    'collection' => 'app.bsky.feed.post',
                    'record' => $record,
                ],
            ]);
            $data = json_decode((string) $response->getBody(), true);
            $uri = $data['uri'] ?? '';
            $recordKey = basename($uri);
            $externalUrl = 'https://bsky.app/profile/' . $handle . '/post/' . $recordKey;

            return new SyndicationResult($uri, $externalUrl);
        } catch (GuzzleException $e) {
            throw new Exception('Bluesky post error: ' . $e->getMessage());
        }
    }

    public function refreshToken(array $connection): ?TokenRefreshResult
    {
        return null; // Bluesky uses App Passwords, not expiring tokens
    }

    private function createSession(string $handle, string $appPassword): array
    {
        try {
            $response = $this->client->post(self::BSKY_HOST . '/xrpc/com.atproto.server.createSession', [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => ['identifier' => $handle, 'password' => $appPassword],
            ]);
            return json_decode((string) $response->getBody(), true);
        } catch (GuzzleException $e) {
            throw new Exception('Bluesky session error: ' . $e->getMessage());
        }
    }

    private function uploadBlobFromUrl(string $imageUrl, string $accessJwt): ?array
    {
        try {
            $imageResponse = $this->client->get($imageUrl, ['timeout' => 15]);
            $contentType = $imageResponse->getHeader('Content-Type')[0] ?? 'image/jpeg';
            $buffer = (string) $imageResponse->getBody();

            $response = $this->client->post(self::BSKY_HOST . '/xrpc/com.atproto.repo.uploadBlob', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessJwt,
                    'Content-Type' => $contentType,
                ],
                'body' => $buffer,
                'timeout' => 30,
            ]);
            $data = json_decode((string) $response->getBody(), true);
            return $data['blob'] ?? null;
        } catch (Throwable) {
            return null;
        }
    }

    private function buildUrlFacet(string $text, string $url): ?array
    {
        $charStart = strpos($text, $url);
        if ($charStart === false) {
            return null;
        }
        $prefix = substr($text, 0, $charStart);
        $byteStart = strlen($prefix);
        $byteEnd = $byteStart + strlen($url);
        return [
            'index' => ['byteStart' => $byteStart, 'byteEnd' => $byteEnd],
            'features' => [['$type' => 'app.bsky.richtext.facet#link', 'uri' => $url]],
        ];
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
