<?php

declare(strict_types=1);

function seo_excerpt(?string $text, int $limit = 160): ?string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)) ?? '');
    if ($text === '') {
        return null;
    }

    if (mb_strlen($text) <= $limit) {
        return $text;
    }

    return rtrim(mb_substr($text, 0, $limit - 1)) . '…';
}

function seo_origin(): string
{
    $canonical = class_exists('SiteSettings') ? SiteSettings::canonicalPublicUrl() : null;
    if ($canonical) {
        return $canonical;
    }

    $envPublic = trim((string) ($_ENV['PUBLIC_SITE_URL'] ?? getenv('PUBLIC_SITE_URL') ?: ''));
    if ($envPublic !== '') {
        return rtrim($envPublic, '/');
    }

    $https  = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    return $scheme . '://' . $host;
}

function seo_absolute_url(?string $path): ?string
{
    $path = trim((string) $path);
    if ($path === '') {
        return null;
    }

    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    if ($path[0] !== '/') {
        $path = '/' . $path;
    }

    return seo_origin() . $path;
}

function seo_current_url(): string
{
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
    $query = parse_url($requestUri, PHP_URL_QUERY);
    if ($query) {
        $path .= '?' . $query;
    }
    return seo_absolute_url($path) ?? seo_origin() . '/';
}

/**
 * The site's display name: admin-configured site_title, falling back to
 * APP_NAME, falling back to "My Site". Used anywhere page titles, the
 * public header/footer brand, or outbound messages need a site name
 * without hardcoding one.
 */
function app_site_name(): string
{
    $settings = class_exists('SiteSettings') ? SiteSettings::current() : false;
    $title = trim((string) (($settings ?: [])['site_title'] ?? ''));
    return $title !== '' ? $title : (configValue('APP_NAME') ?: 'My Site');
}

/**
 * Site title/description for feed scopes, sourced from site_settings with
 * fallback to the existing hardcoded blog defaults.
 */
function seo_site_meta(): array
{
    $settings = SiteSettings::current();
    $description = trim((string) ($settings['hero_subheading'] ?? ''));

    $siteName = app_site_name();

    return [
        'title' => $siteName,
        'description' => $description !== ''
            ? $description
            : 'Posts, notes, imported feed items, and updates from ' . $siteName . '.',
    ];
}

function seo_author_name(): string
{
    $owner = PlatformUser::owner();
    if ($owner && !empty($owner['name'])) {
        return (string) $owner['name'];
    }

    return configValue('APP_NAME') ?: 'My Site';
}
