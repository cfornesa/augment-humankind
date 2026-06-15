<?php

declare(strict_types=1);

const SOCIAL_CHAR_LIMITS = [
    'bluesky' => 300,
    'linkedin' => 3000,
    'facebook' => 63206,
    'instagram' => 2200,
];

function stripHtml(string $html): string
{
    return preg_replace('/\s+/', ' ', strip_tags($html)) ?? '';
}

function slugsToHashtags(array $slugs): string
{
    return implode(' ', array_map(static fn(string $s): string => '#' . str_replace('-', '', $s), $slugs));
}

function buildSocialPostText(string $platform, array $post, array $categorySlugs, string $canonicalUrl): string
{
    $limit = SOCIAL_CHAR_LIMITS[$platform] ?? 300;
    $plainBody = stripHtml($post['content'] ?? '');
    $hashtags = slugsToHashtags($categorySlugs);

    if ($platform === 'bluesky') {
        $urlPart = ' ' . $canonicalUrl;
        $hashtagPart = $hashtags ? ' ' . $hashtags : '';
        $budget = $limit - strlen($urlPart) - strlen($hashtagPart) - 1;
        $text = !empty($post['title']) ? trim($post['title']) . ': ' . $plainBody : $plainBody;
        if (strlen($text) > $budget) {
            $text = substr($text, 0, $budget - 1) . '…';
        }
        return $text . $hashtagPart . $urlPart;
    }

    if ($platform === 'linkedin') {
        $titlePart = !empty($post['title']) ? trim($post['title']) . "\n\n" : '';
        $body = strlen($plainBody) > $limit ? substr($plainBody, 0, $limit - 1) . '…' : $plainBody;
        return implode("\n\n", array_filter([$titlePart . $body, $hashtags, $canonicalUrl]));
    }

    if ($platform === 'instagram') {
        $titlePart = !empty($post['title']) ? trim($post['title']) . "\n\n" : '';
        $urlPart = "\n\n" . $canonicalUrl;
        $hashtagPart = $hashtags ? "\n\n" . $hashtags : '';
        $excerptBudget = $limit - strlen($titlePart) - strlen($urlPart) - strlen($hashtagPart);
        $excerpt = strlen($plainBody) > $excerptBudget ? substr($plainBody, 0, $excerptBudget - 1) . '…' : $plainBody;
        return trim($titlePart . $excerpt . $hashtagPart . $urlPart);
    }

    // facebook
    $titlePart = !empty($post['title']) ? trim($post['title']) . "\n\n" : '';
    return implode("\n\n", array_filter([$titlePart . $plainBody, $hashtags, $canonicalUrl]));
}

function buildPostExcerpt(string $html, int $limit = 180): string
{
    $plain = stripHtml($html);
    if (strlen($plain) <= $limit) {
        return $plain;
    }
    return rtrim(substr($plain, 0, max(0, $limit - 1))) . '…';
}

function ensureCanonicalUrl(string $text, string $canonicalUrl, string $platform): string
{
    $trimmed = trim($text);
    if (!$trimmed) {
        return $canonicalUrl;
    }
    if (str_contains($trimmed, $canonicalUrl)) {
        return $trimmed;
    }

    $limit = SOCIAL_CHAR_LIMITS[$platform] ?? 300;
    $separator = $platform === 'bluesky' ? ' ' : "\n\n";
    $suffix = $separator . $canonicalUrl;
    if (strlen($trimmed) + strlen($suffix) <= $limit) {
        return $trimmed . $suffix;
    }

    $budget = $limit - strlen($suffix);
    if ($budget <= 1) {
        return substr($canonicalUrl, 0, $limit);
    }
    return rtrim(substr($trimmed, 0, $budget - 1)) . '…' . $suffix;
}

function buildLinkCardMetadata(array $payload): array
{
    $fallbackTitle = 'Original post';
    try {
        $fallbackTitle = parse_url($payload['canonicalUrl'], PHP_URL_HOST) ?? $fallbackTitle;
    } catch (Throwable) {
        // Keep fallback
    }

    return [
        'source' => $payload['canonicalUrl'],
        'title' => trim($payload['title'] ?? '') ?: $fallbackTitle,
        'description' => buildPostExcerpt($payload['contentHtml'] ?? ''),
    ];
}

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function resolveSiteLabel(?string $siteTitle, string $canonicalUrl): string
{
    $trimmed = trim((string) $siteTitle);
    if ($trimmed) return $trimmed;

    try {
        return parse_url($canonicalUrl, PHP_URL_HOST) ?? $canonicalUrl;
    } catch (Throwable) {
        return $canonicalUrl;
    }
}

function buildSourceFooter(?string $siteTitle, string $canonicalUrl): array
{
    $label = resolveSiteLabel($siteTitle, $canonicalUrl);
    $escapedLabel = escapeHtml($label);
    $escapedUrl = escapeHtml($canonicalUrl);

    return [
        'html' => '<p><em>Original source at ' . $escapedLabel . ': <a href="' . $escapedUrl . '" class="u-url" rel="noopener noreferrer nofollow" target="_blank">' . $escapedUrl . '</a></em></p>',
        'text' => 'Original source at ' . $label . ': ' . $canonicalUrl,
    ];
}

function buildSyndicatedContent(SyndicationPayload $payload, array $options = []): string
{
    $body = $payload->contentHtml;
    if (!empty($options['prependFeaturedImage']) && $payload->featuredImageUrl && $payload->contentFormat === 'html') {
        $imgTag = '<img src="' . escapeHtml($payload->featuredImageUrl) . '" alt="">';
        if (!str_starts_with(trim($body), $imgTag)) {
            $body = $imgTag . "\n" . $body;
        }
    }
    if ($payload->contentFormat === 'html') {
        return trim($body) . "\n" . $payload->sourceFooterHtml;
    }
    return trim($body) . "\n\n" . $payload->sourceFooterText;
}

function shouldAppendSourceFooter(array $post): bool
{
    return empty($post['source_feed_id']);
}

function parseConnectionMeta(?string $raw): array
{
    if ($raw === null) return [];
    if (is_string($raw)) {
        try {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        } catch (Throwable) {
            return [];
        }
    }
    if (is_array($raw)) return $raw;
    return [];
}
