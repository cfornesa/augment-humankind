<?php

declare(strict_types=1);

ob_start();
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';
require dirname(__DIR__) . '/public/index.php';
ob_end_clean();

require_once dirname(__DIR__) . '/public/app/helpers/oauth.php';
require_once dirname(__DIR__) . '/public/app/helpers/mailer.php';
require_once dirname(__DIR__) . '/public/app/helpers/magic-link.php';

$keys = [
    'GITHUB_CLIENT_ID',
    'GITHUB_CLIENT_SECRET',
    'GOOGLE_CLIENT_ID',
    'GOOGLE_CLIENT_SECRET',
    'MICROSOFT_CLIENT_ID',
    'MICROSOFT_CLIENT_SECRET',
    'FACEBOOK_CLIENT_ID',
    'FACEBOOK_CLIENT_SECRET',
    'ADMIN_GITHUB_USERNAMES',
    'ADMIN_GOOGLE_EMAILS',
    'ADMIN_MICROSOFT_EMAILS',
    'ADMIN_FACEBOOK_IDS',
    'ADMIN_EMAILS',
];

echo "OAuth environment check\n";
echo "=======================\n";

foreach ($keys as $key) {
    $value = oauth_env($key);
    echo str_pad($key, 24) . ': ' . ($value === '' ? 'missing' : 'present') . PHP_EOL;
}

echo PHP_EOL;
echo "Enabled providers (credentials present)\n";
echo "---------------------------------------\n";
$enabled = oauth_enabled_providers();
foreach (oauth_provider_registry() as $slug => $entry) {
    echo str_pad($entry['label'], 12) . ': ' . (isset($enabled[$slug]) ? 'enabled' : 'disabled') . PHP_EOL;
}
echo str_pad('Email link', 12) . ': '
    . (function_exists('magic_link_enabled') && magic_link_enabled() ? 'enabled (SMTP configured)' : 'disabled (SMTP not configured)')
    . PHP_EOL;

echo PHP_EOL;
echo "Expected callback URLs\n";
echo "----------------------\n";
foreach (oauth_provider_registry() as $slug => $entry) {
    echo str_pad($entry['label'], 12) . ': ' . shared_oauth_redirect_uri($slug) . PHP_EOL;
}

echo PHP_EOL;
echo "Allowed admin identities\n";
echo "------------------------\n";
echo 'GitHub usernames:   ' . (oauth_env('ADMIN_GITHUB_USERNAMES') ?: '(none)') . PHP_EOL;
echo 'Google emails:      ' . (oauth_env('ADMIN_GOOGLE_EMAILS') ?: '(none)') . PHP_EOL;
echo 'Microsoft emails:   ' . (oauth_env('ADMIN_MICROSOFT_EMAILS') ?: '(none)') . PHP_EOL;
echo 'Facebook ids:       ' . (oauth_env('ADMIN_FACEBOOK_IDS') ?: '(none)') . PHP_EOL;
echo 'Magic-link emails:  ' . (oauth_env('ADMIN_EMAILS') ?: '(none)') . PHP_EOL;
