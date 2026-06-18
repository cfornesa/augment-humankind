<?php

declare(strict_types=1);

function platform_ui_definitions(): array
{
    return [
        'wordpress_com' => [
            'label' => 'WordPress.com',
            'kind' => 'oauth',
            'setup_href' => 'https://developer.wordpress.com/apps/new/',
            'setup_instruction' => 'Register an OAuth app, then connect the hosted blog you want to publish to.',
            'fields' => [],
        ],
        'wordpress_self' => [
            'label' => 'WordPress (self-hosted)',
            'kind' => 'credentials',
            'setup_href' => 'https://wordpress.org/documentation/article/application-passwords/',
            'setup_instruction' => 'Create an Application Password in WordPress under Users → Profile.',
            'fields' => ['site_url', 'username', 'app_password'],
        ],
        'blogger' => [
            'label' => 'Blogger',
            'kind' => 'oauth',
            'setup_href' => 'https://console.cloud.google.com/apis/credentials',
            'setup_instruction' => 'Create Google OAuth credentials and enable the Blogger API before connecting.',
            'fields' => [],
        ],
        'substack' => [
            'label' => 'Substack',
            'kind' => 'credentials',
            'setup_href' => 'https://substack.com/',
            'setup_instruction' => 'Enter the connect.sid cookie plus the publication ID and hostname for the publication you own.',
            'fields' => ['session_cookie', 'publication_id', 'publication_host'],
        ],
        'bluesky' => [
            'label' => 'Bluesky',
            'kind' => 'credentials',
            'setup_href' => 'https://bsky.app/settings/app-passwords',
            'setup_instruction' => 'Create an App Password in Bluesky Settings → App Passwords and enter your handle.',
            'fields' => ['handle', 'app_password'],
        ],
        'linkedin' => [
            'label' => 'LinkedIn',
            'kind' => 'oauth',
            'setup_href' => 'https://www.linkedin.com/developers/apps/',
            'setup_instruction' => 'Configure a LinkedIn developer app with profile sign-in and posting scopes, then connect your account.',
            'fields' => [],
        ],
        'facebook' => [
            'label' => 'Facebook Page',
            'kind' => 'oauth',
            'setup_href' => 'https://developers.facebook.com/apps/',
            'setup_instruction' => 'Create a Meta app with Facebook Login and page publishing permissions, then connect the page owner account.',
            'fields' => [],
        ],
        'instagram' => [
            'label' => 'Instagram',
            'kind' => 'oauth',
            'setup_href' => 'https://developers.facebook.com/docs/instagram-platform/instagram-api-with-facebook-login/content-publishing/',
            'setup_instruction' => 'Use the same Meta app as Facebook and connect the Business or Creator account linked to your page.',
            'fields' => [],
        ],
    ];
}

function platform_ui_definition(string $platform): ?array
{
    $defs = platform_ui_definitions();
    return $defs[$platform] ?? null;
}

function parse_connection_meta(?string $metadata): array
{
    if ($metadata === null || trim($metadata) === '') {
        return [];
    }

    $decoded = json_decode($metadata, true);
    return is_array($decoded) ? $decoded : [];
}
