<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class LinkedInAdapter implements PlatformAdapter
{
    private const DEFAULT_API_VERSION = '202605';
    private Client $client;

    public function __construct()
    {
        $this->client = new Client(['timeout' => 20]);
    }

    public function publish(array $connection, SyndicationPayload $payload): SyndicationResult
    {
        $accessToken = $this->decryptToken($connection['encrypted_access_token'] ?? '');
        $meta = parseConnectionMeta($connection['metadata'] ?? null);
        $personId = $meta['personId'] ?? '';

        if (!$personId) {
            throw new SyndicationConfigurationError('LinkedIn connection is missing personId in metadata');
        }

        $rawCommentary = trim($payload->socialPostDrafts['linkedin'] ?? '')
            ?: buildSocialPostText('linkedin', ['title' => $payload->title, 'content' => $payload->contentHtml], $payload->categorySlugs ?? [], $payload->canonicalUrl);
        $commentary = ensureCanonicalUrl($rawCommentary, $payload->canonicalUrl, 'linkedin');

        $authorUrn = 'urn:li:person:' . $personId;
        $card = buildLinkCardMetadata(['title' => $payload->title, 'contentHtml' => $payload->contentHtml, 'canonicalUrl' => $payload->canonicalUrl]);

        $thumbnail = null;
        if ($payload->featuredImageUrl) {
            try {
                $thumbnail = $this->uploadThumbnail($payload->featuredImageUrl, $authorUrn, $accessToken);
            } catch (Throwable) {
                // thumbnail unavailable
            }
        }

        $body = [
            'author' => $authorUrn,
            'commentary' => $commentary,
            'visibility' => 'PUBLIC',
            'distribution' => [
                'feedDistribution' => 'MAIN_FEED',
                'targetEntities' => [],
                'thirdPartyDistributionChannels' => [],
            ],
            'content' => [
                'article' => [
                    'source' => $card['source'],
                    'title' => $card['title'],
                    'description' => $card['description'],
                ],
            ],
            'lifecycleState' => 'PUBLISHED',
            'isReshareDisabledByAuthor' => false,
        ];
        if ($thumbnail) {
            $body['content']['article']['thumbnail'] = $thumbnail;
        }

        try {
            $response = $this->client->post('https://api.linkedin.com/rest/posts', [
                'headers' => $this->linkedInHeaders($accessToken),
                'json' => $body,
            ]);
            $postUrn = $response->getHeader('x-linkedin-id')[0] ?? $response->getHeader('x-restli-id')[0] ?? '';
            $postId = basename($postUrn);
            $externalUrl = $postId ? 'https://www.linkedin.com/feed/update/' . $postUrn . '/' : '';

            return new SyndicationResult($postUrn ?: 'unknown', $externalUrl);
        } catch (GuzzleException $e) {
            throw new Exception('LinkedIn API error: ' . $e->getMessage());
        }
    }

    public function refreshToken(array $connection): ?TokenRefreshResult
    {
        return null;
    }

    private function linkedInHeaders(string $accessToken): array
    {
        $version = trim((string) ($_ENV['LINKEDIN_API_VERSION'] ?? getenv('LINKEDIN_API_VERSION') ?? '')) ?: self::DEFAULT_API_VERSION;
        return [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
            'LinkedIn-Version' => $version,
            'X-Restli-Protocol-Version' => '2.0.0',
        ];
    }

    private function uploadThumbnail(string $imageUrl, string $ownerUrn, string $accessToken): string
    {
        $imageResponse = $this->client->get($imageUrl, ['timeout' => 15]);
        $contentType = $imageResponse->getHeader('Content-Type')[0] ?? 'image/jpeg';
        $buffer = (string) $imageResponse->getBody();

        $initResponse = $this->client->post('https://api.linkedin.com/rest/images?action=initializeUpload', [
            'headers' => $this->linkedInHeaders($accessToken),
            'json' => ['initializeUploadRequest' => ['owner' => $ownerUrn]],
            'timeout' => 20,
        ]);
        $initData = json_decode((string) $initResponse->getBody(), true);
        $uploadUrl = $initData['value']['uploadUrl'] ?? '';
        $imageUrn = $initData['value']['image'] ?? '';

        if (!$uploadUrl || !$imageUrn) {
            throw new Exception('LinkedIn thumbnail initialize response missing uploadUrl or image URN');
        }

        $uploadResponse = $this->client->put($uploadUrl, [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => $contentType,
            ],
            'body' => $buffer,
            'timeout' => 30,
        ]);

        if ($uploadResponse->getStatusCode() >= 400) {
            throw new Exception('LinkedIn thumbnail upload failed');
        }

        return $imageUrn;
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
