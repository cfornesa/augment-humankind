<?php
/**
 * One-time seed: populate site_settings.custom_css / custom_js / custom_html_body
 * with the Celestial theme code, and fix the star-field z-index bug in the same pass.
 *
 * Run from repo root:
 *   php scripts/seed-celestial-theme-code.php
 *
 * Safe to re-run: overwrites values but does not drop or alter columns.
 */
declare(strict_types=1);

// Load .env manually (bootstrap expects index.php to have done this).
// Process environment always wins, so DB_* overrides (e.g. from
// scripts/setup-database.php targeting a scratch DB) are respected.
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name); $value = trim($value);
        $current = $_ENV[$name] ?? getenv($name);
        if (is_string($current) && $current !== '') {
            $_ENV[$name] = $current;
            continue;
        }
        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
    }
}
require_once __DIR__ . '/../public/app/bootstrap.php';

$pdo = db();

// ─── 1. Custom CSS ────────────────────────────────────────────────────────────
// The Celestial animation CSS, transformed:
//   - body::before star field removed (stars now live on #celestial-background)
//   - #celestial-background updated to carry the star-field background-image
// @font-face declarations stay in styles.css (fonts load regardless of theme).

$starFieldBackground = <<<'CSS'
        background-color: transparent;
        background-image:
            /* Nebula atmosphere */
            radial-gradient(ellipse 65% 45% at 25% 35%, rgba(40, 55, 160, 0.07) 0%, transparent 70%),
            radial-gradient(ellipse 55% 65% at 75% 62%, rgba(80, 30, 130, 0.05) 0%, transparent 70%),
            radial-gradient(ellipse 40% 30% at 50% 85%, rgba(30, 60, 150, 0.04) 0%, transparent 60%),
            /* Bright anchor stars (3px) */
            radial-gradient(3px 3px at 22%  7%, rgba(210, 225, 255, 0.88) 0%, transparent 100%),
            radial-gradient(3px 3px at 73% 14%, rgba(215, 228, 255, 0.82) 0%, transparent 100%),
            radial-gradient(3px 3px at 92% 33%, rgba(208, 222, 255, 0.78) 0%, transparent 100%),
            radial-gradient(3px 3px at  8% 88%, rgba(212, 226, 255, 0.72) 0%, transparent 100%),
            radial-gradient(3px 3px at 48% 51%, rgba(200, 155,  60, 0.68) 0%, transparent 100%),
            /* Cold/white stars (2px) */
            radial-gradient(2px 2px at  5%  8%, rgba(200, 220, 255, 0.65) 0%, transparent 100%),
            radial-gradient(2px 2px at 32% 15%, rgba(205, 222, 255, 0.58) 0%, transparent 100%),
            radial-gradient(2px 2px at 61% 11%, rgba(198, 218, 255, 0.62) 0%, transparent 100%),
            radial-gradient(2px 2px at 25% 42%, rgba(202, 220, 255, 0.54) 0%, transparent 100%),
            radial-gradient(2px 2px at 58% 28%, rgba(200, 218, 255, 0.50) 0%, transparent 100%),
            radial-gradient(2px 2px at 87% 31%, rgba(204, 222, 255, 0.57) 0%, transparent 100%),
            radial-gradient(2px 2px at 19% 68%, rgba(200, 220, 255, 0.49) 0%, transparent 100%),
            radial-gradient(2px 2px at 66% 72%, rgba(202, 218, 255, 0.48) 0%, transparent 100%),
            radial-gradient(2px 2px at 28% 91%, rgba(198, 216, 255, 0.52) 0%, transparent 100%),
            /* Cold/white stars (1px) */
            radial-gradient(1px 1px at 18%  3%, rgba(200, 220, 255, 0.46) 0%, transparent 100%),
            radial-gradient(1px 1px at 47%  4%, rgba(205, 222, 255, 0.42) 0%, transparent 100%),
            radial-gradient(1px 1px at 79%  7%, rgba(200, 218, 255, 0.52) 0%, transparent 100%),
            radial-gradient(1px 1px at 93% 18%, rgba(202, 220, 255, 0.44) 0%, transparent 100%),
            radial-gradient(1px 1px at 11% 25%, rgba(200, 216, 255, 0.40) 0%, transparent 100%),
            radial-gradient(1px 1px at 41% 33%, rgba(205, 222, 255, 0.46) 0%, transparent 100%),
            radial-gradient(1px 1px at 72% 37%, rgba(200, 220, 255, 0.43) 0%, transparent 100%),
            radial-gradient(1px 1px at  6% 52%, rgba(202, 218, 255, 0.45) 0%, transparent 100%),
            radial-gradient(1px 1px at 35% 75%, rgba(200, 216, 255, 0.41) 0%, transparent 100%),
            radial-gradient(1px 1px at 52% 63%, rgba(205, 220, 255, 0.53) 0%, transparent 100%),
            radial-gradient(1px 1px at 81% 58%, rgba(200, 218, 255, 0.46) 0%, transparent 100%),
            radial-gradient(1px 1px at 95% 67%, rgba(202, 220, 255, 0.40) 0%, transparent 100%),
            radial-gradient(1px 1px at 13% 84%, rgba(200, 216, 255, 0.45) 0%, transparent 100%),
            radial-gradient(1px 1px at 45% 87%, rgba(205, 222, 255, 0.44) 0%, transparent 100%),
            radial-gradient(1px 1px at 63% 93%, rgba(200, 218, 255, 0.49) 0%, transparent 100%),
            radial-gradient(1px 1px at 78% 86%, rgba(202, 220, 255, 0.43) 0%, transparent 100%),
            /* Amber stars (2px) */
            radial-gradient(2px 2px at  7% 12%, rgba(200, 155, 60, 0.45) 0%, transparent 100%),
            radial-gradient(2px 2px at 23% 31%, rgba(200, 155, 60, 0.38) 0%, transparent 100%),
            radial-gradient(2px 2px at 84%  9%, rgba(200, 155, 60, 0.42) 0%, transparent 100%),
            radial-gradient(2px 2px at 14% 73%, rgba(200, 155, 60, 0.36) 0%, transparent 100%),
            radial-gradient(2px 2px at 29% 91%, rgba(200, 155, 60, 0.34) 0%, transparent 100%),
            radial-gradient(2px 2px at 88% 79%, rgba(200, 155, 60, 0.37) 0%, transparent 100%),
            radial-gradient(2px 2px at 49% 72%, rgba(200, 155, 60, 0.32) 0%, transparent 100%),
            /* Amber stars (1px) */
            radial-gradient(1px 1px at 51%  6%, rgba(200, 155, 60, 0.48) 0%, transparent 100%),
            radial-gradient(1px 1px at 68% 22%, rgba(200, 155, 60, 0.35) 0%, transparent 100%),
            radial-gradient(1px 1px at 91% 44%, rgba(200, 155, 60, 0.30) 0%, transparent 100%),
            radial-gradient(1px 1px at 37% 58%, rgba(200, 155, 60, 0.38) 0%, transparent 100%),
            radial-gradient(1px 1px at 76% 67%, rgba(200, 155, 60, 0.40) 0%, transparent 100%),
            radial-gradient(1px 1px at 55% 82%, rgba(200, 155, 60, 0.34) 0%, transparent 100%),
            radial-gradient(1px 1px at 62% 47%, rgba(200, 155, 60, 0.28) 0%, transparent 100%),
            radial-gradient(1px 1px at  3% 55%, rgba(200, 155, 60, 0.40) 0%, transparent 100%),
            radial-gradient(1px 1px at 44% 37%, rgba(200, 155, 60, 0.26) 0%, transparent 100%),
            radial-gradient(1px 1px at 57% 19%, rgba(200, 155, 60, 0.36) 0%, transparent 100%),
            radial-gradient(1px 1px at 16% 61%, rgba(200, 155, 60, 0.29) 0%, transparent 100%),
            radial-gradient(1px 1px at 71% 83%, rgba(200, 155, 60, 0.33) 0%, transparent 100%),
            /* Measurement rings */
            repeating-radial-gradient(
                circle at 50% 50%,
                transparent 0px, transparent 179px,
                rgba(200, 155, 60, 0.08) 180px, rgba(200, 155, 60, 0.08) 181px
            ),
            repeating-radial-gradient(
                circle at 50% 50%,
                transparent 0px, transparent 269px,
                rgba(200, 220, 255, 0.04) 270px, rgba(200, 220, 255, 0.04) 271px
            );
CSS;

$customCss = <<<CSS
/* ─── Celestial layout theme ──────────────────────────────────────────────── */

/* html carries background so body can be transparent */
[data-layout-theme="celestial"] html {
    background: hsl(var(--paper));
}
[data-layout-theme="celestial"] body {
    background: transparent;
    font-family: 'Lora', Georgia, serif;
}
[data-layout-theme="celestial"] h1,
[data-layout-theme="celestial"] h2,
[data-layout-theme="celestial"] h3,
[data-layout-theme="celestial"] .brand {
    font-family: 'Pinyon Script', 'Lora', cursive;
    font-weight: 400;
    letter-spacing: 0.02em;
}
[data-layout-theme="celestial"] code,
[data-layout-theme="celestial"] pre,
[data-layout-theme="celestial"] kbd {
    font-family: 'Courier Prime', 'Courier New', monospace;
}

/* Star field lives on #celestial-background so nebula washes blend against it */
#celestial-background {
    position: fixed;
    inset: 0;
    pointer-events: none;
    z-index: 0;
    overflow: hidden;
{$starFieldBackground}
}

.nebula-wash {
    position: absolute;
    inset: -20%;
    pointer-events: none;
    filter: blur(80px);
    mix-blend-mode: screen;
    will-change: transform, opacity;
}
.nebula-wash--1 {
    background: radial-gradient(circle at 30% 25%, rgba(0, 180, 210, 0.25) 0%, transparent 60%);
    animation: nebula-drift-1 55s ease-in-out infinite alternate;
}
.nebula-wash--2 {
    background: radial-gradient(circle at 75% 65%, rgba(210, 45, 140, 0.28) 0%, transparent 60%);
    animation: nebula-drift-2 65s ease-in-out infinite alternate;
}
.nebula-wash--3 {
    background: radial-gradient(circle at 45% 85%, rgba(215, 145, 45, 0.22) 0%, transparent 55%);
    animation: nebula-drift-3 75s ease-in-out infinite alternate;
}

@keyframes nebula-drift-1 {
    0%   { transform: translate3d(0, 0, 0) scale(1);    opacity: 0.35; }
    100% { transform: translate3d(5%, 3%, 0) scale(1.15); opacity: 0.75; }
}
@keyframes nebula-drift-2 {
    0%   { transform: translate3d(0, 0, 0) scale(1.1);   opacity: 0.45; }
    100% { transform: translate3d(-4%, 5%, 0) scale(0.95); opacity: 0.85; }
}
@keyframes nebula-drift-3 {
    0%   { transform: translate3d(0, 0, 0) scale(0.95);  opacity: 0.25; }
    100% { transform: translate3d(3%, -5%, 0) scale(1.1); opacity: 0.65; }
}

/* Astrolabe SVG */
.astrolabe-grid {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 85vmax;
    height: 85vmax;
    max-width: 950px;
    max-height: 950px;
    pointer-events: none;
    z-index: 1;
    opacity: 0.16;
    color: rgba(200, 155, 60, 0.6);
    animation: astrolabe-rotation 300s linear infinite;
    will-change: transform;
}
@keyframes astrolabe-rotation {
    from { transform: translate3d(-50%, -50%, 0) rotate(0deg); }
    to   { transform: translate3d(-50%, -50%, 0) rotate(360deg); }
}

/* JS-generated cosmos stars container */
#cosmos-stars {
    position: fixed;
    top: 50%;
    left: 50%;
    width: 150vmax;
    height: 150vmax;
    transform: translate3d(-50%, -50%, 0);
    pointer-events: none;
    z-index: 0;
    overflow: hidden;
    contain: layout paint;
    animation: star-rotation 60s linear infinite;
    will-change: transform;
}
@keyframes star-rotation {
    from { transform: translate3d(-50%, -50%, 0) rotate(0deg); }
    to   { transform: translate3d(-50%, -50%, 0) rotate(360deg); }
}
.cosmos-star {
    position: absolute;
    border-radius: 50%;
    animation: cosmos-star-twinkle ease-in-out infinite;
}
@keyframes cosmos-star-twinkle {
    0%, 100% { opacity: 0.12; }
    50%       { opacity: 0.88; }
}

/* Low-power overrides */
.low-power .nebula-wash,
.low-power .astrolabe-grid {
    animation: none !important;
}
.low-power .nebula-wash--1 { opacity: 0.55; }
.low-power .nebula-wash--2 { opacity: 0.65; }
.low-power .nebula-wash--3 { opacity: 0.45; }
.low-power .astrolabe-grid { opacity: 0.12; transform: translate3d(-50%, -50%, 0) rotate(15deg); }
.low-power #cosmos-stars {
    animation: none !important;
    transform: translate3d(-50%, -50%, 0) rotate(0deg) !important;
}

/* Accessibility: kill all animations when prefers-reduced-motion is set */
@media (prefers-reduced-motion: reduce) {
    [data-layout-theme="celestial"] .nebula-wash,
    [data-layout-theme="celestial"] .astrolabe-grid,
    [data-layout-theme="celestial"] #cosmos-stars,
    [data-layout-theme="celestial"] .cosmos-star {
        animation: none !important;
    }
    [data-layout-theme="celestial"] #cosmos-stars {
        transform: translate3d(-50%, -50%, 0) !important;
    }
}
@media (prefers-contrast: more) {
    #cosmos-stars,
    #cosmos-canvas {
        opacity: 0.18;
    }
}

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

// ─── 2. Custom HTML (body injection) ─────────────────────────────────────────
$customHtmlBody = <<<'HTML'
<div id="celestial-background" aria-hidden="true">
    <div class="nebula-wash nebula-wash--1"></div>
    <div class="nebula-wash nebula-wash--2"></div>
    <div class="nebula-wash nebula-wash--3"></div>
    <svg class="astrolabe-grid" viewBox="0 0 100 100" aria-hidden="true" focusable="false">
        <circle cx="50" cy="50" r="48" fill="none" stroke="currentColor" stroke-width="0.1" stroke-dasharray="1 3"/>
        <circle cx="50" cy="50" r="35" fill="none" stroke="currentColor" stroke-width="0.1"/>
        <circle cx="50" cy="50" r="20" fill="none" stroke="currentColor" stroke-width="0.08" stroke-dasharray="2 1"/>
        <line x1="50" y1="2" x2="50" y2="98" stroke="currentColor" stroke-width="0.05"/>
        <line x1="2" y1="50" x2="98" y2="50" stroke="currentColor" stroke-width="0.05"/>
        <path d="M 16 16 L 84 84 M 84 16 L 16 84" stroke="currentColor" stroke-dasharray="1 5" stroke-width="0.05"/>
    </svg>
</div>
HTML;

// ─── 3. Custom JS ─────────────────────────────────────────────────────────────
$cosmosJsPath = __DIR__ . '/../public/assets/js/cosmos.js';
if (!file_exists($cosmosJsPath)) {
    die("✗ cosmos.js not found at {$cosmosJsPath}\n");
}
$customJs = file_get_contents($cosmosJsPath);

// ─── 4. Write to DB ───────────────────────────────────────────────────────────
$stmt = $pdo->prepare(
    'UPDATE site_settings SET custom_css = ?, custom_js = ?, custom_html_body = ? WHERE id = 1'
);
$stmt->execute([$customCss, $customJs, $customHtmlBody]);

$affected = $stmt->rowCount();
echo "✓ Updated site_settings id=1 ({$affected} row(s)).\n";

// Verify
$row = $pdo->query(
    'SELECT LENGTH(custom_css) AS css_len, LENGTH(custom_js) AS js_len, LENGTH(custom_html_body) AS html_len FROM site_settings WHERE id = 1'
)->fetch();
echo "  custom_css:       " . number_format((int)$row['css_len'])  . " bytes\n";
echo "  custom_js:        " . number_format((int)$row['js_len'])   . " bytes\n";
echo "  custom_html_body: " . number_format((int)$row['html_len']) . " bytes\n";
echo "Done.\n";
