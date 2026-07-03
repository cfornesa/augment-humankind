<?php

declare(strict_types=1);

/**
 * Read-only portable launch readiness check.
 *
 * This does not run migrations or mutate the target database. Use it after
 * filling .env and running scripts/setup-database.php to confirm the current
 * checkout is ready for a fresh portable deployment.
 */

final class ReadinessReport
{
    private int $fails = 0;
    private int $warnings = 0;

    public function pass(string $label, string $detail = ''): void
    {
        $this->line('PASS', $label, $detail);
    }

    public function warn(string $label, string $detail = ''): void
    {
        $this->warnings++;
        $this->line('WARN', $label, $detail);
    }

    public function fail(string $label, string $detail = ''): void
    {
        $this->fails++;
        $this->line('FAIL', $label, $detail);
    }

    public function exitCode(): int
    {
        return $this->fails > 0 ? 1 : 0;
    }

    public function summarize(): void
    {
        echo PHP_EOL;
        if ($this->fails > 0) {
            echo "Portable launch readiness failed: {$this->fails} blocking issue(s), {$this->warnings} warning(s)." . PHP_EOL;
            return;
        }
        echo "Portable launch readiness passed with {$this->warnings} warning(s)." . PHP_EOL;
    }

    private function line(string $status, string $label, string $detail): void
    {
        echo '[' . $status . '] ' . $label;
        if ($detail !== '') {
            echo ' - ' . $detail;
        }
        echo PHP_EOL;
    }
}

require_once __DIR__ . '/../public/app/helpers/env.php';

function load_env_file(string $path): void
{
    ah_load_env_file($path);
}

function env_value(string $key, string $default = ''): string
{
    return ah_env($key, $default);
}

function pdo_for_env(): PDO
{
    $host = env_value('DB_HOST', 'localhost');
    $port = env_value('DB_PORT', '3306');
    $name = env_value('DB_NAME');
    $user = env_value('DB_USER');
    $pass = env_value('DB_PASS');

    if ($name === '' || $user === '') {
        throw new RuntimeException('Missing DB_NAME or DB_USER.');
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if (strtolower(env_value('DB_SSL')) === 'true') {
        if (class_exists('Pdo\\Mysql') && defined('Pdo\\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')) {
            $options[constant('Pdo\\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')] = false;
        } elseif (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
            $options[constant('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')] = false;
        }
    }

    return new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $pass, $options);
}

function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}

function row_exists(PDO $pdo, string $sql, array $params = []): bool
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (bool) $stmt->fetchColumn();
}

function check_env(ReadinessReport $report): void
{
    foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $key) {
        env_value($key) !== ''
            ? $report->pass("env {$key}", 'configured')
            : $report->fail("env {$key}", 'required for database-backed CMS operation');
    }

    $githubConfigured = env_value('GITHUB_CLIENT_ID') !== ''
        && env_value('GITHUB_CLIENT_SECRET') !== ''
        && env_value('ADMIN_GITHUB_USERNAMES') !== '';
    $googleConfigured = env_value('GOOGLE_CLIENT_ID') !== ''
        && env_value('GOOGLE_CLIENT_SECRET') !== ''
        && env_value('ADMIN_GOOGLE_EMAILS') !== '';

    if ($githubConfigured || $googleConfigured) {
        $report->pass('admin OAuth', 'at least one provider is configured');
    } else {
        $report->warn('admin OAuth', 'configure GitHub or Google credentials plus an ADMIN_* allowlist before first admin sign-in');
    }

    env_value('AI_SETTINGS_ENCRYPTION_KEY') !== ''
        ? $report->pass('encrypted feature key', 'AI_SETTINGS_ENCRYPTION_KEY is configured')
        : $report->warn('encrypted feature key', 'required only before storing AI keys, platform tokens, platform OAuth apps, or DB-owned reCAPTCHA secrets');
}

function check_database(ReadinessReport $report, PDO $pdo): void
{
    $requiredTables = [
        'admin_identities',
        'media_files',
        'categories',
        'exhibits',
        'collections',
        'pages',
        'page_sections',
        'navigation_items',
        'users',
        'posts',
        'comments',
        'site_settings',
        'forms',
        'form_fields',
        'newsletter_subscribers',
        'art_piece_starter_templates',
        'site_theme_code',
    ];

    foreach ($requiredTables as $table) {
        table_exists($pdo, $table)
            ? $report->pass("table {$table}", 'present')
            : $report->fail("table {$table}", 'missing; run php scripts/setup-database.php');
    }

    $requiredColumns = [
        'pages' => ['system_key', 'description', 'show_description_section', 'meta_description', 'og_description'],
        'page_sections' => ['section_kind', 'form_id', 'config_json', 'is_required'],
        'site_settings' => ['canonical_public_url', 'custom_css', 'custom_js', 'custom_html_body'],
        'forms' => ['form_key', 'encrypted_recaptcha_secret'],
        'art_piece_versions' => ['is_draft_attempt', 'attempt_sequence_token'],
    ];

    foreach ($requiredColumns as $table => $columns) {
        if (!table_exists($pdo, $table)) {
            continue;
        }
        foreach ($columns as $column) {
            column_exists($pdo, $table, $column)
                ? $report->pass("column {$table}.{$column}", 'present')
                : $report->fail("column {$table}.{$column}", 'missing; run php scripts/setup-database.php');
        }
    }

    if (table_exists($pdo, 'site_settings')) {
        row_exists($pdo, 'SELECT 1 FROM site_settings WHERE id = 1 LIMIT 1')
            ? $report->pass('site_settings row', 'id=1 present')
            : $report->fail('site_settings row', 'id=1 missing; run php scripts/setup-database.php');
    }

    if (table_exists($pdo, 'forms')) {
        foreach (['contact_form', 'newsletter_signup'] as $key) {
            row_exists($pdo, 'SELECT 1 FROM forms WHERE form_key = ? LIMIT 1', [$key])
                ? $report->pass("form seed {$key}", 'present')
                : $report->fail("form seed {$key}", 'missing; run php scripts/setup-database.php');
        }
    }

    if (table_exists($pdo, 'art_piece_starter_templates')) {
        row_exists($pdo, 'SELECT 1 FROM art_piece_starter_templates WHERE is_default = 1 LIMIT 1')
            ? $report->pass('art starter templates', 'default templates present')
            : $report->fail('art starter templates', 'default templates missing; run php scripts/setup-database.php');
    }
}

function check_oauth_docs(ReadinessReport $report): void
{
    $root = dirname(__DIR__);
    $files = [
        'env.example',
        'README.md',
        'docs/dependencies.md',
        'docs/api.md',
    ];
    $bad = [];
    $required = [
        '/auth/github/callback',
        '/auth/google/callback',
    ];

    foreach ($files as $file) {
        $contents = @file_get_contents($root . '/' . $file);
        if ($contents === false) {
            $report->warn("docs {$file}", 'not readable');
            continue;
        }
        $staleAdminPrefix = '/admin/auth/';
        if (str_contains($contents, $staleAdminPrefix . 'github/callback') || str_contains($contents, $staleAdminPrefix . 'google/callback')) {
            $bad[] = $file;
        }
    }

    if ($bad !== []) {
        $report->fail('OAuth callback docs', 'stale /admin/auth callback path found in ' . implode(', ', $bad));
        return;
    }

    $combined = '';
    foreach ($files as $file) {
        $contents = @file_get_contents($root . '/' . $file);
        $combined .= is_string($contents) ? $contents : '';
    }
    foreach ($required as $path) {
        if (!str_contains($combined, $path)) {
            $report->warn('OAuth callback docs', "{$path} is not documented");
            return;
        }
    }

    $report->pass('OAuth callback docs', 'shared /auth/{provider}/callback paths documented');
}

load_env_file(dirname(__DIR__) . '/public/.env');
load_env_file(dirname(__DIR__) . '/.env');

$report = new ReadinessReport();
check_env($report);
check_oauth_docs($report);

try {
    $pdo = pdo_for_env();
    $pdo->exec('SET NAMES utf8mb4');
    $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $report->pass('database connection', "connected to {$dbName}");
    check_database($report, $pdo);
} catch (Throwable $e) {
    $report->fail('database connection', $e->getMessage());
}

$report->summarize();
exit($report->exitCode());
