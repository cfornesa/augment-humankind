<?php

declare(strict_types=1);

ob_start();
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';
require dirname(__DIR__) . '/public/index.php';
ob_end_clean();

require dirname(__DIR__) . '/public/app/helpers/oauth.php';

$keys = [
    'GITHUB_CLIENT_ID',
    'GITHUB_CLIENT_SECRET',
    'GOOGLE_CLIENT_ID',
    'GOOGLE_CLIENT_SECRET',
    'ADMIN_GITHUB_USERNAMES',
    'ADMIN_GOOGLE_EMAILS',
];

echo "OAuth environment check\n";
echo "=======================\n";

foreach ($keys as $key) {
    $value = oauth_env($key);
    echo str_pad($key, 24) . ': ' . ($value === '' ? 'missing' : 'present') . PHP_EOL;
}

echo PHP_EOL;
echo "Expected callback URLs\n";
echo "----------------------\n";
echo 'GitHub: ' . oauth_redirect_uri('github') . PHP_EOL;
echo 'Google: ' . oauth_redirect_uri('google') . PHP_EOL;

echo PHP_EOL;
echo "Allowed admin identities\n";
echo "------------------------\n";
echo 'GitHub usernames: ' . (oauth_env('ADMIN_GITHUB_USERNAMES') ?: '(none)') . PHP_EOL;
echo 'Google emails:    ' . (oauth_env('ADMIN_GOOGLE_EMAILS') ?: '(none)') . PHP_EOL;
