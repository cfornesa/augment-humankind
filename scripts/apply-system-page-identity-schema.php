<?php

declare(strict_types=1);

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

        if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }

        $_ENV[$name] = $value;
        putenv($name . '=' . $value);
    }
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?
          LIMIT 1'
    );
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}

function indexExists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND INDEX_NAME = ?
          LIMIT 1'
    );
    $stmt->execute([$table, $index]);
    return (bool) $stmt->fetchColumn();
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.TABLES
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
          LIMIT 1'
    );
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

function patchCelestialStackingCss(string $css): string
{
    $old = <<<'CSS'
/* Page content stacks above the cosmic background */
[data-layout-theme="celestial"] .site-header,
[data-layout-theme="celestial"] main,
[data-layout-theme="celestial"] .site-footer {
    position: relative;
    z-index: 1;
}
CSS;

    $new = <<<'CSS'
/* Page chrome/content stack above the cosmic background without blocking links */
[data-layout-theme="celestial"] main,
[data-layout-theme="celestial"] .site-footer {
    position: relative;
    z-index: 1;
}
[data-layout-theme="celestial"] .site-header {
    position: relative;
    z-index: 30;
}
[data-layout-theme="celestial"] .site-header.nav-open {
    z-index: 80;
}
[data-layout-theme="celestial"] .site-header.nav-open .site-nav,
[data-layout-theme="celestial"] .account-menu-panel {
    z-index: 90;
}
CSS;

    if (str_contains($css, $new)) {
        return $css;
    }

    return str_replace($old, $new, $css);
}

loadEnvFile(dirname(__DIR__) . '/.env');
loadEnvFile(__DIR__ . '/../public/.env');

$host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
$port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
$name = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: '';
$user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: '';
$pass = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '';

if ($name === '' || $user === '') {
    fwrite(STDERR, "Missing DB_NAME or DB_USER in .env\n");
    exit(1);
}

$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
    $user,
    $pass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$applied = [];

if (!columnExists($pdo, 'pages', 'system_key')) {
    $pdo->exec('ALTER TABLE pages ADD COLUMN system_key VARCHAR(100) NULL AFTER id');
    $applied[] = 'pages.system_key';
}

if (!indexExists($pdo, 'pages', 'uniq_pages_system_key')) {
    $pdo->exec('ALTER TABLE pages ADD UNIQUE KEY uniq_pages_system_key (system_key)');
    $applied[] = 'pages.uniq_pages_system_key';
}

if (!tableExists($pdo, 'page_slug_redirects')) {
    $pdo->exec(
        "CREATE TABLE page_slug_redirects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            old_slug VARCHAR(255) NOT NULL,
            page_id INT NOT NULL,
            system_key VARCHAR(100) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_page_slug_redirect_old_slug (old_slug),
            KEY idx_page_slug_redirect_page (page_id),
            FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
        )"
    );
    $applied[] = 'page_slug_redirects';
}

$systemPages = [
    'home' => ['slug' => 'home', 'aliases' => []],
    'about' => ['slug' => 'about', 'aliases' => ['bio']],
];

$resolvedSystemPages = [];

foreach ($systemPages as $systemKey => $config) {
    $current = null;
    $stmt = $pdo->prepare('SELECT * FROM pages WHERE system_key = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$systemKey]);
    $current = $stmt->fetch() ?: null;

    $slugs = array_values(array_unique(array_merge([$config['slug']], $config['aliases'])));
    $placeholders = implode(',', array_fill(0, count($slugs), '?'));
    $candidateStmt = $pdo->prepare(
        "SELECT *
           FROM pages
          WHERE deleted_at IS NULL
            AND system_key IS NULL
            AND slug IN ($placeholders)
          ORDER BY CASE WHEN slug != ? THEN 0 ELSE 1 END, id ASC
          LIMIT 1"
    );
    $candidateStmt->execute([...$slugs, $config['slug']]);
    $candidate = $candidateStmt->fetch() ?: null;

    if (
        $current
        && $candidate
        && (int) $current['id'] !== (int) $candidate['id']
        && (string) $current['slug'] === (string) $config['slug']
        && (string) $candidate['slug'] !== (string) $config['slug']
    ) {
        $pdo->beginTransaction();
        $clear = $pdo->prepare('UPDATE pages SET system_key = NULL WHERE id = ? AND system_key = ?');
        $clear->execute([(int) $current['id'], $systemKey]);
        $assign = $pdo->prepare('UPDATE pages SET system_key = ? WHERE id = ? AND system_key IS NULL');
        $assign->execute([$systemKey, (int) $candidate['id']]);
        $pdo->commit();
        $current = $candidate;
        $applied[] = "pages.system_key transfer {$systemKey}";
    } elseif (!$current && $candidate) {
        $assign = $pdo->prepare('UPDATE pages SET system_key = ? WHERE id = ? AND system_key IS NULL');
        $assign->execute([$systemKey, (int) $candidate['id']]);
        $current = $candidate;
        $applied[] = "pages.system_key {$systemKey}";
    }

    if ($current && (string) $current['slug'] !== (string) $config['slug']) {
        $redirect = $pdo->prepare(
            'INSERT INTO page_slug_redirects (old_slug, page_id, system_key)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE page_id = VALUES(page_id), system_key = VALUES(system_key)'
        );
        $redirect->execute([(string) $config['slug'], (int) $current['id'], $systemKey]);
        $applied[] = "page_slug_redirects {$config['slug']}";
    }

    if ($current) {
        $resolvedSystemPages[$systemKey] = $current;
    }
}

foreach ($systemPages as $systemKey => $config) {
    $current = $resolvedSystemPages[$systemKey] ?? null;
    if (!$current || (string) $current['slug'] === (string) $config['slug']) {
        continue;
    }

    $quarantine = $pdo->prepare(
        'UPDATE pages
            SET status = ?, show_in_nav = 0
          WHERE slug = ?
            AND deleted_at IS NULL
            AND system_key IS NULL
            AND (status != ? OR show_in_nav != 0)'
    );
    $quarantine->execute(['draft', (string) $config['slug'], 'draft']);
    if ($quarantine->rowCount() > 0) {
        $applied[] = "pages.quarantine_duplicate {$config['slug']}";
    }
}

$settingsStmt = $pdo->query('SELECT id, custom_css FROM site_settings ORDER BY id ASC LIMIT 1');
$settings = $settingsStmt->fetch();
if ($settings && is_string($settings['custom_css'] ?? null)) {
    $patchedCss = patchCelestialStackingCss((string) $settings['custom_css']);
    if ($patchedCss !== (string) $settings['custom_css']) {
        $stmt = $pdo->prepare('UPDATE site_settings SET custom_css = ? WHERE id = ?');
        $stmt->execute([$patchedCss, (int) $settings['id']]);
        $applied[] = 'site_settings.custom_css celestial stacking';
    }
}

if (tableExists($pdo, 'site_theme_code') && columnExists($pdo, 'site_theme_code', 'custom_css')) {
    $themeRows = $pdo->query(
        "SELECT theme_name, custom_css, default_css
           FROM site_theme_code
          WHERE theme_name = 'celestial'"
    )->fetchAll();
    foreach ($themeRows as $row) {
        $patchedCustomCss = patchCelestialStackingCss((string) ($row['custom_css'] ?? ''));
        $patchedDefaultCss = patchCelestialStackingCss((string) ($row['default_css'] ?? ''));
        if (
            $patchedCustomCss === (string) ($row['custom_css'] ?? '')
            && $patchedDefaultCss === (string) ($row['default_css'] ?? '')
        ) {
            continue;
        }

        $stmt = $pdo->prepare('UPDATE site_theme_code SET custom_css = ?, default_css = ? WHERE theme_name = ?');
        $stmt->execute([$patchedCustomCss, $patchedDefaultCss, (string) $row['theme_name']]);
        $applied[] = 'site_theme_code.custom_css celestial stacking';
    }
}

echo json_encode([
    'status' => 'ok',
    'applied' => $applied,
], JSON_PRETTY_PRINT) . PHP_EOL;
