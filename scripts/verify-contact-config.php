<?php
declare(strict_types=1);

ob_start();
$_SERVER['REQUEST_URI'] = '/contact';
$_SERVER['REQUEST_METHOD'] = 'GET';
require dirname(__DIR__) . '/public/index.php';
ob_end_clean();

$checks = [];

foreach (['RECAPTCHA_SITE_KEY', 'RECAPTCHA_SECRET_KEY'] as $key) {
    $checks[$key] = configValue($key) !== '';
}

$minimumScore = configValue('RECAPTCHA_MIN_SCORE', '0.5');
$checks['RECAPTCHA_MIN_SCORE'] = is_numeric($minimumScore)
    && (float) $minimumScore >= 0.0
    && (float) $minimumScore <= 1.0;

$smtpErrors = [];
$checks['HOSTINGER_SMTP'] = smtpConfiguration($smtpErrors) !== [];

$failed = array_keys(array_filter($checks, static fn (bool $passed): bool => !$passed));

if ($failed !== []) {
    fwrite(STDERR, "Contact form configuration needs attention:\n");
    foreach ($failed as $key) {
        fwrite(STDERR, "- " . $key . "\n");
    }

    if ($smtpErrors !== []) {
        fwrite(STDERR, "- SMTP settings must use smtp.hostinger.com, a matching SMTP_USERNAME and SMTP_FROM_EMAIL, a valid CONTACT_TO_EMAIL, and a supported port/encryption pair.\n");
    }

    exit(1);
}

echo "Contact form configuration shape is valid.\n";
