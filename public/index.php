<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/vendor/autoload.php';

set_exception_handler(static function (Throwable $e): void {
    error_log((string) $e);

    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $isApiRequest = str_starts_with($path, '/api/');

    http_response_code(500);

    if ($e instanceof PDOException && !$isApiRequest) {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<title>Site not configured</title></head><body style="font-family:system-ui,sans-serif;'
            . 'max-width:640px;margin:4rem auto;line-height:1.6;padding:0 1.5rem;">'
            . '<h1>This site isn\'t configured yet</h1>'
            . '<p>The database connection failed. If you\'re setting up a new deployment, check that '
            . '<code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code>, and <code>DB_PASS</code> '
            . 'are set correctly in your <code>.env</code> file, and that the database has been created '
            . 'from the setup sequence documented in README.md.</p>'
            . '</body></html>';
        return;
    }

    if ($isApiRequest) {
        header('Content-Type: application/json');
        echo json_encode([
            'error' => $e instanceof PDOException
                ? 'Site not configured.'
                : 'Internal server error.',
        ]);
        return;
    }

    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<title>Server error</title></head><body style="font-family:system-ui,sans-serif;'
        . 'max-width:640px;margin:4rem auto;line-height:1.6;padding:0 1.5rem;">'
        . '<h1>Something went wrong</h1>'
        . '<p>The request could not be completed. Please try again in a moment.</p>'
        . '</body></html>';
});

$routes = [
    '/' => 'home',
    '/services' => 'services',
    '/notes' => 'notes',
    '/contact' => 'contact',
];

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (PHP_SAPI === 'cli-server') {
    if (str_starts_with($path, '/vendor/') || str_starts_with(basename($path), '.')) {
        http_response_code(403);
        exit;
    }

    $assetPath = __DIR__ . $path;
    if (is_file($assetPath)) {
        return false;
    }
}

$path = rtrim($path, '/') ?: '/';

// Baseline security headers on every response. /embed/* is excluded from
// X-Frame-Options because embeds exist to be iframed cross-origin.
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
if (!str_starts_with($path, '/embed/')) {
    header('X-Frame-Options: SAMEORIGIN');
}

loadEnvFile(__DIR__ . '/.env');
loadEnvFile(dirname(__DIR__) . '/.env');

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/helpers/schema.php';
require_once __DIR__ . '/app/helpers/seo.php';
require_once __DIR__ . '/app/helpers/audit-log.php';
require_once __DIR__ . '/app/helpers/rate-limit.php';
require_once __DIR__ . '/app/helpers/auth.php';
require_once __DIR__ . '/app/helpers/admin-navigation.php';
require_once __DIR__ . '/app/models/AdminIdentity.php';
require_once __DIR__ . '/app/models/SiteSettings.php';
require_once __DIR__ . '/app/helpers/bootstrap_state.php';

$bootstrapExempt = $path === '/admin' || str_starts_with($path, '/admin/')
    || str_starts_with($path, '/api/')
    || str_starts_with($path, '/embed/')
    || str_starts_with($path, '/immersive/')
    || str_starts_with($path, '/assets/')
    || str_starts_with($path, '/vendor/')
    || str_starts_with($path, '/auth/');

if (!$bootstrapExempt && !site_bootstrap_complete()) {
    http_response_code(503);
    header('Retry-After: 300');
    require __DIR__ . '/app/views/setup_gate.php';
    exit;
}

if ($path === '/portfolio' || str_starts_with($path, '/portfolio/')
    || $path === '/pieces' || str_starts_with($path, '/pieces/')
    || $path === '/collections' || str_starts_with($path, '/collections/')
    || str_starts_with($path, '/embed/')
    || str_starts_with($path, '/immersive/')
    || str_starts_with($path, '/api/')
    || $path === '/blog' || str_starts_with($path, '/blog/')
    || str_starts_with($path, '/og/')
    || $path === '/search'
    || $path === '/feeds'
    || str_starts_with($path, '/posts/')
    || str_starts_with($path, '/categories/')
    || str_starts_with($path, '/p/')
    || str_starts_with($path, '/feeds/')
    || in_array($path, ['/feed.xml', '/atom', '/feed.json', '/jsonfeed', '/export.json', '/export/json'], true)
    || $path === '/admin' || str_starts_with($path, '/admin/')
    || $path === '/user' || str_starts_with($path, '/user/')
    || str_starts_with($path, '/auth/')
    || preg_match('#^/(media|image)/[0-9]+$#', $path)
    || preg_match('#^/[a-z0-9-]+/feed\.(xml|json)$#', $path)) {
    require_once __DIR__ . '/app/bootstrap.php';
    require __DIR__ . '/app/router.php';
}

$page = $routes[$path] ?? null;

$managedSlug = null;
if ($page === 'home' || $page === 'services' || $page === 'notes' || $page === 'contact') {
    $managedSlug = $page;
} elseif ($page === null && preg_match('#^/([a-z0-9-]+)$#', $path, $slugMatch)) {
    $managedSlug = $slugMatch[1];
}

if ($managedSlug !== null) {
    require_once __DIR__ . '/app/bootstrap.php';
    require_once __DIR__ . '/app/helpers/seo.php';
    require_once __DIR__ . '/app/models/SiteSettings.php';
    require_once __DIR__ . '/app/models/Page.php';
    require_once __DIR__ . '/app/models/PageSection.php';
    require_once __DIR__ . '/app/models/Form.php';
    require_once __DIR__ . '/app/helpers/encryption.php';
    require_once __DIR__ . '/app/helpers/rate-limit.php';
    require_once __DIR__ . '/app/controllers/PageController.php';

    if (PageController::redirectIfSlugMoved($managedSlug)) {
        exit;
    }

    if (PageController::show($managedSlug)) {
        exit;
    }

    if (Page::safeFindBySlug($managedSlug)) {
        http_response_code(404);
        $page = '404';
    }
}

if ($page === null) {
    http_response_code(404);
    $page = '404';
}

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/models/SiteSettings.php';
$siteSettings = SiteSettings::current() ?: [];
$siteName = trim((string) ($siteSettings['site_title'] ?? '')) !== ''
    ? trim((string) $siteSettings['site_title'])
    : (configValue('APP_NAME') ?: 'My Site');
$inquiryTypes = [
    'collaboration' => 'Collaboration',
    'hiring' => 'Hiring',
    'project_help' => 'Project help',
    'strategy_help' => 'Strategy help',
    'other' => 'Other',
];
$contactValues = [
    'name' => '',
    'email' => '',
    'organization' => '',
    'inquiry_type' => '',
    'message' => '',
];
$contactErrors = [];
$contactSuccess = false;

loadEnvFile(__DIR__ . '/.env');
loadEnvFile(dirname(__DIR__) . '/.env');

$pageMeta = [
    'home' => [
        'title' => $siteName,
        'description' => 'Welcome to ' . $siteName . '.',
    ],
    'services' => [
        'title' => 'Services | ' . $siteName,
        'description' => 'An overview of what ' . $siteName . ' offers.',
    ],
    'notes' => [
        'title' => 'Notes | ' . $siteName,
        'description' => 'Notes and updates from ' . $siteName . '.',
    ],
    'contact' => [
        'title' => 'Contact | ' . $siteName,
        'description' => 'Get in touch with ' . $siteName . '.',
    ],
    '404' => [
        'title' => 'Page not found | ' . $siteName,
        'description' => 'The requested page could not be found.',
    ],
];

// Placeholder services shown only when no managed "/services" page exists
// in the database yet. Replace via the admin pages editor.
$services = [
    [
        'number' => '01',
        'name' => 'Service One',
        'summary' => 'Describe your first service or offering here.',
        'bestFor' => 'Describe who this is best for.',
        'deliverables' => ['Deliverable one', 'Deliverable two'],
    ],
    [
        'number' => '02',
        'name' => 'Service Two',
        'summary' => 'Describe your second service or offering here.',
        'bestFor' => 'Describe who this is best for.',
        'deliverables' => ['Deliverable one', 'Deliverable two'],
    ],
    [
        'number' => '03',
        'name' => 'Service Three',
        'summary' => 'Describe your third service or offering here.',
        'bestFor' => 'Describe who this is best for.',
        'deliverables' => ['Deliverable one', 'Deliverable two'],
    ],
];

require_once __DIR__ . '/app/helpers/navigation.php';
$navigationItems = ah_public_navigation_items();

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function isActive(string $href, string $path): string
{
    return $href === $path ? ' aria-current="page"' : '';
}

function bodyClass(string $page): string
{
    return 'page-' . preg_replace('/[^a-z0-9-]/', '', $page);
}

function loadEnvFile(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if ($name === '') {
            continue;
        }

        $existingValue = $_ENV[$name] ?? getenv($name);
        if (is_string($existingValue) && $existingValue !== '') {
            // Normalize real process env into $_ENV: variables_order often
            // excludes E, and db()/configValue read $_ENV first.
            $_ENV[$name] = $existingValue;
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $_ENV[$name] = $value;
        putenv($name . '=' . $value);
    }
}

function configValue(string $key, string $default = ''): string
{
    $value = $_ENV[$key] ?? getenv($key);
    return is_string($value) && $value !== '' ? $value : $default;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function postedValue(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function currentHostname(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $host = strtolower((string) $host);
    return explode(':', $host)[0] ?? '';
}

function verifyRecaptcha(string $token, array &$errors): bool
{
    $secret = configValue('RECAPTCHA_SECRET_KEY');
    $minimumScore = (float) configValue('RECAPTCHA_MIN_SCORE', '0.5');
    if ($secret === '') {
        $errors[] = 'The contact form is missing reCAPTCHA configuration.';
        return false;
    }

    if ($token === '') {
        $errors[] = 'Please retry the form verification before submitting.';
        return false;
    }

    $requestBody = http_build_query([
        'secret' => $secret,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $requestBody,
            'timeout' => 8,
        ],
    ]);

    $rawResponse = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);
    if ($rawResponse === false) {
        $errors[] = 'The form verification service could not be reached. Please try again.';
        return false;
    }

    $response = json_decode($rawResponse, true);
    if (!is_array($response) || empty($response['success'])) {
        $errors[] = 'The form verification failed. Please try again.';
        return false;
    }

    if (($response['action'] ?? '') !== 'contact_submit') {
        $errors[] = 'The form verification did not match this contact form.';
        return false;
    }

    $hostname = strtolower((string) ($response['hostname'] ?? ''));
    $currentHost = currentHostname();
    if ($currentHost !== '' && $hostname !== '' && $hostname !== $currentHost) {
        $errors[] = 'The form verification did not match this website.';
        return false;
    }

    $score = isset($response['score']) ? (float) $response['score'] : 0.0;
    if ($score < $minimumScore) {
        $errors[] = 'The form verification score was too low. Please try again.';
        return false;
    }

    return true;
}

function smtpConfiguration(array &$errors): array
{
    $requiredConfig = [
        'SMTP_HOST',
        'SMTP_PORT',
        'SMTP_ENCRYPTION',
        'SMTP_USERNAME',
        'SMTP_PASSWORD',
        'SMTP_FROM_EMAIL',
        'SMTP_FROM_NAME',
        'CONTACT_TO_EMAIL',
    ];

    $config = [];
    foreach ($requiredConfig as $key) {
        $config[$key] = configValue($key);
        if ($config[$key] === '') {
            $errors[] = 'The contact form email configuration is incomplete.';
            return [];
        }
    }

    $encryption = strtolower($config['SMTP_ENCRYPTION']);
    $port = (int) $config['SMTP_PORT'];

    // SMTP_USERNAME is intentionally not required to look like an email
    // address or to match SMTP_FROM_EMAIL: many providers (AWS SES, Mailgun,
    // SendGrid) issue opaque API-style usernames distinct from the From
    // address. Only SMTP_FROM_EMAIL, which actually appears in the message,
    // needs to be a valid address.
    if (!filter_var($config['SMTP_FROM_EMAIL'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'The contact form email configuration is incomplete.';
        return [];
    }

    if (!filter_var($config['CONTACT_TO_EMAIL'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'The contact form email configuration is incomplete.';
        return [];
    }

    if (!in_array($encryption, ['smtps', 'ssl', 'starttls', 'tls'], true)) {
        $errors[] = 'The contact form email configuration is incomplete.';
        return [];
    }

    if (($encryption === 'smtps' || $encryption === 'ssl') && $port !== 465) {
        $errors[] = 'The contact form email configuration is incomplete.';
        return [];
    }

    if (($encryption === 'starttls' || $encryption === 'tls') && $port !== 587) {
        $errors[] = 'The contact form email configuration is incomplete.';
        return [];
    }

    return $config;
}

function sendContactEmail(array $values, array $inquiryTypes, array &$errors): bool
{
    $config = smtpConfiguration($errors);
    if ($config === []) {
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $config['SMTP_HOST'];
        $mail->Port = (int) $config['SMTP_PORT'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['SMTP_USERNAME'];
        $mail->Password = $config['SMTP_PASSWORD'];

        $encryption = strtolower($config['SMTP_ENCRYPTION']);
        if ($encryption === 'tls' || $encryption === 'starttls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($encryption === 'ssl' || $encryption === 'smtps') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }

        $mail->CharSet = 'UTF-8';
        $mail->setFrom($config['SMTP_FROM_EMAIL'], $config['SMTP_FROM_NAME']);
        $mail->addAddress($config['CONTACT_TO_EMAIL']);
        $mail->addReplyTo($values['email'], $values['name']);
        $siteNameForEmail = configValue('APP_NAME') ?: 'Site';
        $mail->Subject = $siteNameForEmail . ' inquiry: ' . ($inquiryTypes[$values['inquiry_type']] ?? 'Other');
        $mail->Body = implode("\n", [
            'New ' . $siteNameForEmail . ' inquiry',
            '',
            'Received: ' . gmdate('Y-m-d H:i:s') . ' UTC',
            'Name: ' . $values['name'],
            'Email: ' . $values['email'],
            'Organization: ' . ($values['organization'] !== '' ? $values['organization'] : 'Not provided'),
            'Inquiry type: ' . ($inquiryTypes[$values['inquiry_type']] ?? 'Other'),
            '',
            'Message:',
            $values['message'],
        ]);

        $mail->send();
    } catch (MailerException) {
        $errors[] = 'The message could not be sent right now. Please try again later.';
        return false;
    }

    return true;
}

function validateContactForm(array &$values, array $inquiryTypes, array &$errors): void
{
    $values = [
        'name' => postedValue('name'),
        'email' => postedValue('email'),
        'organization' => postedValue('organization'),
        'inquiry_type' => postedValue('inquiry_type'),
        'message' => postedValue('message'),
    ];

    if (postedValue('website') !== '') {
        $errors[] = 'The form could not be submitted.';
    }

    if (!hash_equals($_SESSION['csrf_token'] ?? '', postedValue('csrf_token'))) {
        $errors[] = 'The form session expired. Please try again.';
    }

    if (strlen($values['name']) < 2 || strlen($values['name']) > 120) {
        $errors[] = 'Enter your name using 2 to 120 characters.';
    }

    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || strlen($values['email']) > 254) {
        $errors[] = 'Enter a valid email address.';
    }

    if (strlen($values['organization']) > 160) {
        $errors[] = 'Keep the organization field under 160 characters.';
    }

    if (!array_key_exists($values['inquiry_type'], $inquiryTypes)) {
        $errors[] = 'Choose an inquiry type.';
    }

    if (strlen($values['message']) < 20 || strlen($values['message']) > 3000) {
        $errors[] = 'Enter a message between 20 and 3000 characters.';
    }
}

if ($page === 'contact') {
    csrfToken();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $limit = rate_limit_consume('contact_submit', rate_limit_subject_for_scope('contact_submit'));
        if (!$limit['allowed']) {
            http_response_code(429);
            header('Retry-After: ' . $limit['retry_after']);
            $contactErrors[] = 'Too many inquiries were submitted from this browser. Please wait a while and try again.';
        }

        validateContactForm($contactValues, $inquiryTypes, $contactErrors);

        if ($contactErrors === []) {
            verifyRecaptcha(postedValue('g-recaptcha-response'), $contactErrors);
        }

        if ($contactErrors === []) {
            $contactSuccess = sendContactEmail($contactValues, $inquiryTypes, $contactErrors);
        }

        if ($contactSuccess) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $contactValues = [
                'name' => '',
                'email' => '',
                'organization' => '',
                'inquiry_type' => '',
                'message' => '',
            ];
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageMeta[$page]['title']) ?></title>
    <meta name="description" content="<?= e($pageMeta[$page]['description']) ?>">
    <link rel="stylesheet" href="/assets/styles.css">
    <?php if ($page === 'contact' && configValue('RECAPTCHA_SITE_KEY') !== ''): ?>
        <script src="https://www.google.com/recaptcha/api.js?render=<?= e(configValue('RECAPTCHA_SITE_KEY')) ?>" defer></script>
    <?php endif; ?>
</head>
<body class="<?= e(bodyClass($page)) ?>">
    <a class="skip-link" href="#main">Skip to content</a>

    <header class="site-header" aria-label="Site header">
        <a class="brand" href="/" aria-label="<?= e($siteName) ?> home">
            <span class="brand-text"><?= e($siteName) ?></span>
        </a>
        <nav class="site-nav" aria-label="Primary navigation">
            <?php foreach ($navigationItems as $item): ?>
                <?php
                    $href = (string) ($item['url'] ?? '#');
                    $label = (string) ($item['label'] ?? $href);
                    $target = (string) ($item['target'] ?? '');
                ?>
                <a href="<?= e($href) ?>"<?= isActive($href, $path) ?><?= $target === '_blank' ? ' target="_blank" rel="noopener"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </header>

    <main id="main">
        <?php if ($page === 'home'): ?>
            <section class="hero section-grid" aria-labelledby="hero-title">
                <div class="hero-copy">
                    <p class="eyebrow">Welcome</p>
                    <h1 id="hero-title"><?= e($siteName) ?></h1>
                    <p class="hero-statement">This is a starter homepage. Replace this content from the admin pages editor, or set a site title from the admin Site Identity screen (or the <code>APP_NAME</code> env var) and your managed pages will take over this content entirely.</p>
                    <div class="hero-actions" aria-label="Primary actions">
                        <a class="button button-primary" href="/services">Explore services</a>
                        <a class="button button-secondary" href="/contact">Start a conversation</a>
                    </div>
                </div>
            </section>

            <section class="mission-band" aria-labelledby="mission-title">
                <p class="eyebrow">About</p>
                <h2 id="mission-title">Tell visitors what this site is for.</h2>
                <p>This paragraph is placeholder copy shown only when no managed home page exists in the database yet. Edit it from the admin Pages screen once your site is configured.</p>
            </section>

            <section class="service-preview" aria-labelledby="service-preview-title">
                <div class="section-heading">
                    <p class="eyebrow">What you offer</p>
                    <h2 id="service-preview-title">Summarize your services here.</h2>
                </div>
                <div class="service-strip">
                    <?php foreach ($services as $service): ?>
                        <article class="service-card">
                            <span class="service-number"><?= e($service['number']) ?></span>
                            <h3><?= e($service['name']) ?></h3>
                            <p><?= e($service['summary']) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
                <a class="text-link" href="/services">See the full services page</a>
            </section>
        <?php elseif ($page === 'services'): ?>
            <section class="page-hero" aria-labelledby="services-title">
                <p class="eyebrow">Services</p>
                <h1 id="services-title">Placeholder services page.</h1>
                <p>Replace this with your real offerings from the admin pages editor.</p>
            </section>

            <section class="services-detail" aria-label="Service offerings">
                <?php foreach ($services as $service): ?>
                    <article class="service-detail">
                        <div class="service-kicker">
                            <span><?= e($service['number']) ?></span>
                            <h2><?= e($service['name']) ?></h2>
                        </div>
                        <p class="service-summary"><?= e($service['summary']) ?></p>
                        <p><strong>Best for:</strong> <?= e($service['bestFor']) ?></p>
                        <div>
                            <h3>Typical deliverables</h3>
                            <ul class="deliverable-list">
                                <?php foreach ($service['deliverables'] as $deliverable): ?>
                                    <li><?= e($deliverable) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>

            <section class="callout" aria-labelledby="service-boundary-title">
                <h2 id="service-boundary-title">Set expectations</h2>
                <p>Use this section to clarify what visitors should and shouldn't expect from working with you.</p>
                <a class="button button-primary" href="/contact">Discuss a project</a>
            </section>
        <?php elseif ($page === 'notes'): ?>
            <section class="page-hero notes-hero" aria-labelledby="notes-title">
                <p class="eyebrow">Notes</p>
                <h1 id="notes-title">Placeholder notes page.</h1>
                <p>This section will hold short notes, updates, or a journal. Replace this content from the admin pages editor.</p>
            </section>

            <section class="notes-empty" aria-labelledby="first-notes-title">
                <div class="note-card">
                    <p class="eyebrow">Coming soon</p>
                    <h2 id="first-notes-title">Your first note goes here.</h2>
                    <p>Replace this placeholder once you've configured your site's content.</p>
                </div>
            </section>
        <?php elseif ($page === 'contact'): ?>
            <section class="page-hero contact-hero" aria-labelledby="contact-title">
                <p class="eyebrow">Contact</p>
                <h1 id="contact-title">Get in touch.</h1>
                <p>Send a note using the form below.</p>
            </section>

            <section class="contact-layout" aria-labelledby="contact-form-title">
                <div class="contact-form-panel">
                    <div class="section-heading contact-heading">
                        <p class="eyebrow">Inquiry</p>
                        <h2 id="contact-form-title">Tell me what you are trying to make possible.</h2>
                    </div>

                    <?php if ($contactSuccess): ?>
                        <div id="form-success" class="form-status form-status-success" role="status" aria-live="polite">
                            <h3>Message sent.</h3>
                            <p>Thanks for reaching out. We'll review your note and respond if it's a fit.</p>
                        </div>
                    <?php endif; ?>

                    <?php if ($contactErrors !== []): ?>
                        <div id="form-errors" class="form-status form-status-error" role="alert" aria-live="assertive">
                            <h3>Check the form.</h3>
                            <ul>
                                <?php foreach (array_unique($contactErrors) as $error): ?>
                                    <li><?= e($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if (configValue('RECAPTCHA_SITE_KEY') === ''): ?>
                        <div id="form-config" class="form-status form-status-error" role="alert" aria-live="assertive">
                            <h3>Configuration needed.</h3>
                            <p>The contact form needs a reCAPTCHA site key before it can accept submissions.</p>
                        </div>
                    <?php endif; ?>

                    <form class="contact-form" method="post" action="/contact" novalidate data-recaptcha-site-key="<?= e(configValue('RECAPTCHA_SITE_KEY')) ?>"<?= $contactErrors !== [] ? ' aria-describedby="form-errors privacy-note"' : ' aria-describedby="privacy-note"' ?>>
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="g-recaptcha-response" value="">
                        <div class="field field-honeypot" aria-hidden="true">
                            <label for="website">Website</label>
                            <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                        </div>

                        <div class="field-grid">
                            <div class="field">
                                <label for="name">Name</label>
                                <input id="name" name="name" type="text" autocomplete="name" required maxlength="120" value="<?= e($contactValues['name']) ?>">
                            </div>
                            <div class="field">
                                <label for="email">Email</label>
                                <input id="email" name="email" type="email" autocomplete="email" required maxlength="254" value="<?= e($contactValues['email']) ?>">
                            </div>
                        </div>

                        <div class="field-grid">
                            <div class="field">
                                <label for="organization">Organization <span>optional</span></label>
                                <input id="organization" name="organization" type="text" autocomplete="organization" maxlength="160" value="<?= e($contactValues['organization']) ?>">
                            </div>
                            <div class="field">
                                <label for="inquiry_type">Inquiry type</label>
                                <select id="inquiry_type" name="inquiry_type" required>
                                    <option value="">Choose one</option>
                                    <?php foreach ($inquiryTypes as $value => $label): ?>
                                        <option value="<?= e($value) ?>"<?= $contactValues['inquiry_type'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="field">
                            <label for="message">Message</label>
                            <textarea id="message" name="message" rows="8" required minlength="20" maxlength="3000"><?= e($contactValues['message']) ?></textarea>
                        </div>

                        <p id="privacy-note" class="privacy-note">This form is protected by reCAPTCHA and the Google <a href="https://policies.google.com/privacy">Privacy Policy</a> and <a href="https://policies.google.com/terms">Terms of Service</a> apply.</p>

                        <button class="button button-primary" type="submit"<?= configValue('RECAPTCHA_SITE_KEY') === '' ? ' disabled' : '' ?>>Send inquiry</button>
                    </form>
                </div>

                <aside class="contact-brief" aria-labelledby="brief-title">
                    <h2 id="brief-title">Helpful context</h2>
                    <ul class="method-list">
                        <li>What your team does and who would use the workflow.</li>
                        <li>The AI idea, task, or decision you want help clarifying.</li>
                        <li>What would count as a useful first version.</li>
                        <li>Any data, privacy, or approval constraints that matter.</li>
                    </ul>
                </aside>
            </section>
        <?php else: ?>
            <section class="page-hero" aria-labelledby="missing-title">
                <p class="eyebrow">404</p>
                <h1 id="missing-title">Page not found.</h1>
                <p>The page may have moved, or the address may be incorrect.</p>
                <a class="button button-primary" href="/">Return home</a>
            </section>
        <?php endif; ?>
    </main>

    <footer class="site-footer">
        <p>&copy; <?= date('Y') ?> <?= e($siteName) ?></p>
        <nav aria-label="Footer navigation">
            <a href="/">Home</a>
            <a href="/portfolio">Portfolio</a>
            <a href="/blog">Blog</a>
            <a href="/contact">Contact</a>
        </nav>
    </footer>
    <?php if ($page === 'contact' && configValue('RECAPTCHA_SITE_KEY') !== ''): ?>
        <script>
            window.addEventListener('DOMContentLoaded', function () {
                var form = document.querySelector('.contact-form');
                if (!form || !window.grecaptcha) {
                    return;
                }

                form.addEventListener('submit', function (event) {
                    var tokenInput = form.querySelector('input[name="g-recaptcha-response"]');
                    var siteKey = form.getAttribute('data-recaptcha-site-key');
                    if (!tokenInput || !siteKey || tokenInput.value) {
                        return;
                    }

                    event.preventDefault();
                    window.grecaptcha.ready(function () {
                        window.grecaptcha.execute(siteKey, { action: 'contact_submit' }).then(function (token) {
                            tokenInput.value = token;
                            form.submit();
                        });
                    });
                });
            });
        </script>
    <?php endif; ?>
</body>
</html>
