<?php

declare(strict_types=1);

class SyndicationPayload
{
    public string $title;
    public string $contentHtml;
    public string $contentFormat;
    public string $canonicalUrl;
    public string $sourceFooterHtml;
    public string $sourceFooterText;
    public ?string $featuredImageUrl;
    public ?array $socialPostDrafts;
    public ?array $categorySlugs;

    public function __construct(
        string $title,
        string $contentHtml,
        string $contentFormat,
        string $canonicalUrl,
        string $sourceFooterHtml,
        string $sourceFooterText,
        ?string $featuredImageUrl = null,
        ?array $socialPostDrafts = null,
        ?array $categorySlugs = null
    ) {
        $this->title = $title;
        $this->contentHtml = $contentHtml;
        $this->contentFormat = $contentFormat;
        $this->canonicalUrl = $canonicalUrl;
        $this->sourceFooterHtml = $sourceFooterHtml;
        $this->sourceFooterText = $sourceFooterText;
        $this->featuredImageUrl = $featuredImageUrl;
        $this->socialPostDrafts = $socialPostDrafts;
        $this->categorySlugs = $categorySlugs;
    }

    public static function fromPost(array $post, string $canonicalUrl, string $siteTitle): self
    {
        $contentHtml = (string) ($post['content'] ?? '');
        $contentFormat = (string) ($post['content_format'] ?? 'html');
        $title = (string) (($post['title'] ?? '') ?: 'Untitled');
        $featuredImageUrl = $post['featured_image_url'] ?? null;
        $socialPostDrafts = null;
        if (!empty($post['social_post_drafts'])) {
            $drafts = is_string($post['social_post_drafts']) ? json_decode($post['social_post_drafts'], true) : $post['social_post_drafts'];
            $socialPostDrafts = is_array($drafts) ? $drafts : null;
        }

        $footer = buildSourceFooter($siteTitle, $canonicalUrl);

        return new self(
            $title,
            $contentHtml,
            $contentFormat,
            $canonicalUrl,
            $footer['html'],
            $footer['text'],
            $featuredImageUrl,
            $socialPostDrafts
        );
    }
}

class SyndicationResult
{
    public string $externalId;
    public string $externalUrl;

    public function __construct(string $externalId, string $externalUrl)
    {
        $this->externalId = $externalId;
        $this->externalUrl = $externalUrl;
    }
}

class TokenRefreshResult
{
    public string $accessToken;
    public ?string $refreshToken;
    public ?string $expiresAt;

    public function __construct(string $accessToken, ?string $refreshToken = null, ?string $expiresAt = null)
    {
        $this->accessToken = $accessToken;
        $this->refreshToken = $refreshToken;
        $this->expiresAt = $expiresAt;
    }
}

interface PlatformAdapter
{
    public function publish(array $connection, SyndicationPayload $payload): SyndicationResult;
    public function refreshToken(array $connection): ?TokenRefreshResult;
}

class SyndicationConfigurationError extends Exception {}
class SyndicationAuthExpiredError extends Exception {}
