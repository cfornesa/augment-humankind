<?php
/**
 * One-time seed: populate site_theme_code table with Celestial theme code
 * sourced from the already-seeded site_settings row.
 *
 * Run from repo root:
 *   php scripts/seed-theme-code-table.php
 *
 * Safe to re-run: uses INSERT … ON DUPLICATE KEY UPDATE (seed method).
 */
declare(strict_types=1);

$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name); $value = trim($value);
        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
    }
}
require_once __DIR__ . '/../public/app/bootstrap.php';
require_once __DIR__ . '/../public/app/models/SiteThemeCode.php';

$row = db()->query('SELECT custom_css, custom_js, custom_html_body FROM site_settings WHERE id = 1')->fetch();
if (!$row) {
    die("✗ No site_settings row with id=1 found.\n");
}

$css  = (string) ($row['custom_css']       ?? '');
$js   = (string) ($row['custom_js']        ?? '');
$html = (string) ($row['custom_html_body'] ?? '');

if ($css === '' && $js === '' && $html === '') {
    die("✗ site_settings.custom_css/js/html_body are all empty. Run seed-celestial-theme-code.php first.\n");
}

SiteThemeCode::seed('celestial', 'Celestial', $css, $js, $html, true);

echo "✓ Seeded site_theme_code with Celestial theme code.\n";
echo "  custom_css:       " . number_format(strlen($css))  . " bytes\n";
echo "  custom_js:        " . number_format(strlen($js))   . " bytes\n";
echo "  custom_html_body: " . number_format(strlen($html)) . " bytes\n";
echo "Done.\n";
