<?php

declare(strict_types=1);

require_once __DIR__ . '/SyndicationPayload.php';
require_once __DIR__ . '/ContentHelpers.php';
require_once __DIR__ . '/BlueskyAdapter.php';
require_once __DIR__ . '/WordPressComAdapter.php';
require_once __DIR__ . '/WordPressSelfAdapter.php';
require_once __DIR__ . '/BloggerAdapter.php';
require_once __DIR__ . '/SubstackAdapter.php';
require_once __DIR__ . '/LinkedInAdapter.php';
require_once __DIR__ . '/FacebookAdapter.php';
require_once __DIR__ . '/InstagramAdapter.php';

class AdapterFactory
{
    private static array $adapters = [];

    public static function get(string $platform): ?PlatformAdapter
    {
        if (!isset(self::$adapters[$platform])) {
            self::$adapters[$platform] = self::create($platform);
        }
        return self::$adapters[$platform];
    }

    private static function create(string $platform): ?PlatformAdapter
    {
        return match ($platform) {
            'bluesky' => new BlueskyAdapter(),
            'wordpress_com' => new WordPressComAdapter(),
            'wordpress_self' => new WordPressSelfAdapter(),
            'blogger' => new BloggerAdapter(),
            'substack' => new SubstackAdapter(),
            'linkedin' => new LinkedInAdapter(),
            'facebook' => new FacebookAdapter(),
            'instagram' => new InstagramAdapter(),
            default => null,
        };
    }

    public static function allPlatforms(): array
    {
        return [
            'wordpress_com' => 'WordPress.com',
            'wordpress_self' => 'WordPress (self-hosted)',
            'blogger' => 'Blogger',
            'substack' => 'Substack',
            'bluesky' => 'Bluesky',
            'linkedin' => 'LinkedIn',
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
        ];
    }
}
