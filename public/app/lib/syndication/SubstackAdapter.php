<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class SubstackAdapter implements PlatformAdapter
{
    private const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36';
    private Client $client;

    public function __construct()
    {
        $this->client = new Client(['timeout' => 15, 'allow_redirects' => false]);
    }

    public function publish(array $connection, SyndicationPayload $payload): SyndicationResult
    {
        $storedCookie = $this->decryptToken($connection['encrypted_access_token'] ?? '');
        $cookieHeader = $this->normalizeCookieHeader($storedCookie);
        $meta = parseConnectionMeta($connection['metadata'] ?? null);
        $publicationId = trim((string) ($meta['publicationId'] ?? ''));
        $publicationHost = $this->normalizePublicationHost((string) ($meta['publicationHost'] ?? ''));

        if (!$cookieHeader || !$publicationId || !$publicationHost) {
            throw new SyndicationConfigurationError('Substack integration not configured');
        }

        usleep(1500000); // 1.5s delay
        $cookieHeader = $this->signInForPublication($cookieHeader, $publicationHost);
        $userId = $this->fetchCurrentUserId($cookieHeader);
        if (!$userId) {
            $this->markConnectionExpired($connection, $publicationId, $publicationHost);
            throw new SyndicationAuthExpiredError('Substack Session Expired');
        }

        $title = trim($payload->title) ?: 'Untitled';
        $draftBody = $this->buildSubstackDraftBody(buildSyndicatedContent($payload, ['prependFeaturedImage' => true]));
        $publicationOrigin = "https://{$publicationHost}";
        $publicationReferer = "{$publicationOrigin}/publish/post";

        try {
            $draftResponse = $this->client->post($this->publicationApiUrl($publicationHost, '/drafts'), [
                'headers' => $this->buildHeaders($cookieHeader, $publicationOrigin, $publicationReferer),
                'json' => [
                    'draft_title' => $title,
                    'draft_subtitle' => '',
                    'draft_body' => json_encode($draftBody),
                    'draft_bylines' => [['id' => $userId, 'is_guest' => false]],
                    'draft_podcast_url' => null,
                    'draft_podcast_duration' => null,
                    'draft_section_id' => null,
                    'section_chosen' => false,
                    'audience' => 'everyone',
                    'type' => 'newsletter',
                    'write_comment_permissions' => 'everyone',
                ],
            ]);
            $draft = json_decode((string) $draftResponse->getBody(), true);
            $draftId = (string) ($draft['id'] ?? '');
            if (!$draftId) {
                throw new Exception('Substack draft API error: missing draft id');
            }

            // Prepublish
            $this->client->get($this->publicationApiUrl($publicationHost, "/drafts/{$draftId}/prepublish"), [
                'headers' => $this->buildHeaders($cookieHeader, $publicationOrigin, $publicationReferer),
            ]);

            // Publish
            $publishResponse = $this->client->post($this->publicationApiUrl($publicationHost, "/drafts/{$draftId}/publish"), [
                'headers' => $this->buildHeaders($cookieHeader, $publicationOrigin, $publicationReferer),
                'json' => ['send' => false, 'share_automatically' => false],
            ]);
            $data = json_decode((string) $publishResponse->getBody(), true);
            $externalId = (string) ($data['id'] ?? '');
            $externalUrl = $data['url'] ?? $data['canonical_url'] ?? $draft['url'] ?? $draft['canonical_url'] ?? '';
            if (!$externalUrl && !empty($data['slug'])) {
                $externalUrl = "https://{$publicationHost}/p/{$data['slug']}";
            }

            return new SyndicationResult($externalId, $externalUrl);
        } catch (GuzzleException $e) {
            if ($e->getResponse() && $e->getResponse()->getStatusCode() === 401) {
                $this->markConnectionExpired($connection, $publicationId, $publicationHost);
                throw new SyndicationAuthExpiredError('Substack Session Expired');
            }
            throw new Exception('Substack API error: ' . $e->getMessage());
        }
    }

    public function refreshToken(array $connection): ?TokenRefreshResult
    {
        return null;
    }

    private function normalizePublicationHost(string $value): string
    {
        $trimmed = trim($value);
        if (!$trimmed) return '';
        try {
            $parsed = parse_url(str_contains($trimmed, '://') ? $trimmed : "https://{$trimmed}");
            return strtolower($parsed['host'] ?? '');
        } catch (Throwable) {
            return strtolower(preg_replace('/^https?:\/\//i', '', explode('/', $trimmed)[0]) ?? '');
        }
    }

    private function normalizeCookieHeader(string $raw): string
    {
        $trimmed = trim(preg_replace('/^cookie:\s*/i', '', $raw) ?? '');
        if (!$trimmed) return '';
        if (str_contains($trimmed, '=') && str_contains($trimmed, ';')) {
            return $trimmed;
        }
        if (str_starts_with($trimmed, 'connect.sid=')) {
            return $trimmed;
        }
        return "connect.sid={$trimmed}";
    }

    private function buildHeaders(string $cookieHeader, string $origin, string $referer): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => self::USER_AGENT,
            'Cookie' => $cookieHeader,
            'Referer' => $referer,
            'Origin' => $origin,
        ];
    }

    private function publicationApiUrl(string $publicationHost, string $path): string
    {
        return "https://{$publicationHost}/api/v1{$path}";
    }

    private function signInForPublication(string $cookieHeader, string $publicationHost): string
    {
        $subdomain = $this->getPublicationSubdomain($publicationHost);
        if (!$subdomain) return $cookieHeader;

        try {
            $response = $this->client->get("https://substack.com/sign-in?redirect=%2F&for_pub=" . urlencode($subdomain), [
                'headers' => $this->buildHeaders($cookieHeader, 'https://substack.com', 'https://substack.com/'),
            ]);
            $setCookies = $response->getHeader('Set-Cookie');
            if (!empty($setCookies)) {
                return $this->mergeCookieHeaders($cookieHeader, $setCookies);
            }
        } catch (Throwable) {
            // ignore
        }
        return $cookieHeader;
    }

    private function getPublicationSubdomain(string $publicationHost): string
    {
        if (str_ends_with($publicationHost, '.substack.com')) {
            return substr($publicationHost, 0, -strlen('.substack.com'));
        }
        return $publicationHost;
    }

    private function mergeCookieHeaders(string $baseCookieHeader, array $setCookieHeaders): string
    {
        $cookieMap = [];
        foreach (explode(';', $baseCookieHeader) as $chunk) {
            $part = trim($chunk);
            if (!$part) continue;
            $separatorIndex = strpos($part, '=');
            if ($separatorIndex === false || $separatorIndex <= 0) continue;
            $name = trim(substr($part, 0, $separatorIndex));
            $value = trim(substr($part, $separatorIndex + 1));
            if ($name) $cookieMap[$name] = $value;
        }

        foreach ($setCookieHeaders as $setCookie) {
            $pair = explode(';', $setCookie)[0] ?? '';
            $pair = trim($pair);
            if (!$pair) continue;
            $separatorIndex = strpos($pair, '=');
            if ($separatorIndex === false || $separatorIndex <= 0) continue;
            $name = trim(substr($pair, 0, $separatorIndex));
            $value = trim(substr($pair, $separatorIndex + 1));
            if ($name) $cookieMap[$name] = $value;
        }

        $parts = [];
        foreach ($cookieMap as $name => $value) {
            $parts[] = "{$name}={$value}";
        }
        return implode('; ', $parts);
    }

    private function fetchCurrentUserId(string $cookieHeader): ?int
    {
        try {
            $response = $this->client->get('https://substack.com/api/v1/user/profile/self', [
                'headers' => $this->buildHeaders($cookieHeader, 'https://substack.com', 'https://substack.com/'),
            ]);
            if ($response->getStatusCode() === 401) {
                return null;
            }
            $data = json_decode((string) $response->getBody(), true);
            $userId = (int) ($data['id'] ?? 0);
            return $userId > 0 ? $userId : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function markConnectionExpired(array $connection, string $publicationId, string $publicationHost): void
    {
        try {
            $meta = parseConnectionMeta($connection['metadata'] ?? null);
            $meta['publicationId'] = $publicationId;
            $meta['publicationHost'] = $publicationHost;
            $meta['authStatus'] = 'expired';
            $meta['statusMessage'] = 'Substack session expired. Update your session cookie to reconnect.';
            $meta['lastAuthFailureAt'] = date('Y-m-d H:i:s');

            $stmt = db()->prepare(
                'UPDATE platform_connections SET metadata = ?, updated_at = NOW() WHERE id = ?'
            );
            $stmt->execute([json_encode($meta), $connection['id']]);
        } catch (Throwable) {
            // ignore
        }
    }

    private function buildSubstackDraftBody(string $html): array
    {
        // Simplified HTML to Substack document conversion
        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        $body = $doc->getElementsByTagName('body')->item(0);
        $content = $body ? $this->extractBlockNodes($body->childNodes) : [];

        return [
            'type' => 'doc',
            'content' => $content ?: [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => '']]]],
        ];
    }

    private function extractBlockNodes(DOMNodeList $nodes): array
    {
        $blocks = [];
        foreach ($nodes as $node) {
            if ($node instanceof DOMText) {
                $text = trim($node->textContent);
                if ($text) {
                    $blocks[] = ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]];
                }
                continue;
            }
            if ($node instanceof DOMElement) {
                $tag = strtolower($node->tagName);
                if ($tag === 'p') {
                    $text = trim($node->textContent);
                    if ($text) {
                        $blocks[] = ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]];
                    }
                } elseif (preg_match('/^h[1-6]$/', $tag)) {
                    $blocks[] = ['type' => 'heading', 'attrs' => ['level' => (int) substr($tag, 1)], 'content' => [['type' => 'text', 'text' => trim($node->textContent)]]];
                } elseif ($tag === 'blockquote') {
                    $blocks[] = ['type' => 'blockquote', 'content' => $this->extractBlockNodes($node->childNodes)];
                } elseif ($tag === 'ul') {
                    $items = [];
                    foreach ($node->childNodes as $li) {
                        if ($li instanceof DOMElement && strtolower($li->tagName) === 'li') {
                            $items[] = ['type' => 'listItem', 'content' => $this->extractBlockNodes($li->childNodes)];
                        }
                    }
                    $blocks[] = ['type' => 'bulletList', 'content' => $items];
                } elseif ($tag === 'ol') {
                    $items = [];
                    foreach ($node->childNodes as $li) {
                        if ($li instanceof DOMElement && strtolower($li->tagName) === 'li') {
                            $items[] = ['type' => 'listItem', 'content' => $this->extractBlockNodes($li->childNodes)];
                        }
                    }
                    $blocks[] = ['type' => 'orderedList', 'attrs' => ['order' => (int) ($node->getAttribute('start') ?: 1)], 'content' => $items];
                } elseif ($tag === 'pre') {
                    $blocks[] = ['type' => 'codeBlock', 'content' => [['type' => 'text', 'text' => trim($node->textContent)]]];
                } elseif ($tag === 'hr') {
                    $blocks[] = ['type' => 'horizontalRule'];
                } elseif ($tag === 'img') {
                    $src = $node->getAttribute('src');
                    if ($src) {
                        $blocks[] = ['type' => 'image', 'attrs' => ['src' => $src, 'alt' => $node->getAttribute('alt') ?: null, 'title' => $node->getAttribute('title') ?: null]];
                    }
                } elseif ($tag === 'figure') {
                    $img = null;
                    foreach ($node->childNodes as $child) {
                        if ($child instanceof DOMElement && strtolower($child->tagName) === 'img') {
                            $img = $child;
                            break;
                        }
                    }
                    if ($img) {
                        $blocks[] = ['type' => 'image', 'attrs' => ['src' => $img->getAttribute('src'), 'alt' => $img->getAttribute('alt') ?: null]];
                    }
                }
            }
        }
        return $blocks;
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
