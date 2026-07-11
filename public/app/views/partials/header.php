<?php

declare(strict_types=1);

$path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';

require_once dirname(__DIR__, 2) . '/helpers/navigation.php';
$navigationItems = ah_public_navigation_items();

$bodyClass = $bodyClass ?? 'page-managed';
$pageDescription = $pageDescription ?? '';
$ogTitle = $ogTitle ?? $pageTitle;
$ogDescription = $ogDescription ?? $pageDescription;
$ogType = $ogType ?? ($path === '/' ? 'website' : 'article');
$ogImage = $ogImage ?? null;
$canonicalUrl = $canonicalUrl ?? seo_current_url();
$extraHeadHtml = $extraHeadHtml ?? '';

// Load site settings for CSS color injection (gracefully skips if table missing)
$_ahS = (class_exists('SiteSettings') ? SiteSettings::current() : false) ?: [];
$_ahSiteTitle = app_site_name();
$_ahLogoLayout = (string) ($_ahS['logo_layout'] ?? 'text_only');
$_ahLogoUrl = trim((string) ($_ahS['logo_url'] ?? ''));
$_ahLogoDarkUrl = trim((string) ($_ahS['logo_dark_url'] ?? ''));
$_ahResolvedOgImage = $ogImage ? (seo_absolute_url($ogImage) ?? $ogImage) : null;

$_ahLightMap = [
    'color_background'             => '--paper',
    'color_foreground'             => '--ink',
    'color_muted'                  => '--paper-deep',
    'color_muted_foreground'       => '--ink-soft',
    'color_primary'                => '--green',
    'color_primary_foreground'     => '--green-fg',
    'color_secondary'              => '--cyan',
    'color_secondary_foreground'   => '--cyan-fg',
    'color_accent'                 => '--orange',
    'color_accent_foreground'      => '--orange-fg',
    'color_destructive'            => '--destructive',
    'color_destructive_foreground' => '--destructive-fg',
];
$_ahDarkMap = [
    'color_background_dark'             => '--paper',
    'color_foreground_dark'             => '--ink',
    'color_muted_dark'                  => '--paper-deep',
    'color_muted_foreground_dark'       => '--ink-soft',
    'color_primary_dark'                => '--green',
    'color_primary_foreground_dark'     => '--green-fg',
    'color_secondary_dark'              => '--cyan',
    'color_secondary_foreground_dark'   => '--cyan-fg',
    'color_accent_dark'                 => '--orange',
    'color_accent_foreground_dark'      => '--orange-fg',
    'color_destructive_dark'            => '--destructive',
    'color_destructive_foreground_dark' => '--destructive-fg',
];

$_ahLightVars = [];
foreach ($_ahLightMap as $_ahCol => $_ahVar) {
    if (!empty($_ahS[$_ahCol])) {
        $_ahLightVars[] = $_ahVar . ':hsl(' . htmlspecialchars((string) $_ahS[$_ahCol], ENT_QUOTES, 'UTF-8') . ')';
    }
}
$_ahDarkVars = [];
foreach ($_ahDarkMap as $_ahCol => $_ahVar) {
    if (!empty($_ahS[$_ahCol])) {
        $_ahDarkVars[] = $_ahVar . ':hsl(' . htmlspecialchars((string) $_ahS[$_ahCol], ENT_QUOTES, 'UTF-8') . ')';
    }
}
unset($_ahLightMap, $_ahDarkMap, $_ahCol, $_ahVar);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>
    document.documentElement.classList.add('js-enhanced');
    // Apply stored theme before first paint to prevent flash
    (function(){var t=localStorage.getItem('theme');if(t==='dark'||t==='light')document.documentElement.dataset.theme=t;})();
    <?php if (!empty($_ahS['theme'])): ?>document.documentElement.dataset.layoutTheme=<?= json_encode((string)$_ahS['theme']) ?>;<?php endif ?>
    </script>
    <title><?= e($pageTitle) ?></title>
    <script type="importmap">
    {
      "imports": {
        "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js"
      }
    }
    </script>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <meta property="og:url" content="<?= e($canonicalUrl) ?>">
    <meta property="og:type" content="<?= e($ogType) ?>">
    <meta property="og:site_name" content="<?= e($_ahSiteTitle) ?>">
    <meta property="og:title" content="<?= e($ogTitle) ?>">
    <meta property="og:description" content="<?= e($ogDescription) ?>">
    <meta name="twitter:card" content="<?= $_ahResolvedOgImage ? 'summary_large_image' : 'summary' ?>">
    <meta name="twitter:title" content="<?= e($ogTitle) ?>">
    <meta name="twitter:description" content="<?= e($ogDescription) ?>">
    <?php if ($_ahResolvedOgImage): ?>
        <meta property="og:image" content="<?= e($_ahResolvedOgImage) ?>">
        <meta name="twitter:image" content="<?= e($_ahResolvedOgImage) ?>">
    <?php endif; ?>
    <?php // Cache-busted like the piece JS: iOS Safari holds stylesheets far
          // past their freshness window, which left new .piece-*/.admin-* rules
          // unapplied for returning visitors. ?>
    <link rel="stylesheet" href="/assets/styles.css?v=<?= (int) @filemtime(dirname(__DIR__, 3) . '/assets/styles.css') ?>">
    <?php if ($_ahLightVars !== [] || $_ahDarkVars !== []): ?>
    <style>
        <?php if ($_ahLightVars !== []): ?>
        :root:not([data-theme="dark"]){<?= implode(';', $_ahLightVars) ?>}
        <?php endif ?>
        <?php if ($_ahDarkVars !== []): ?>
        @media(prefers-color-scheme:dark){:root:not([data-theme="light"]){<?= implode(';', $_ahDarkVars) ?>}}
        [data-theme="dark"]{<?= implode(';', $_ahDarkVars) ?>}
        <?php endif ?>
    </style>
    <?php endif ?>
    <?= $extraHeadHtml ?>
    <?php if (!empty($_ahS['custom_css'])): ?>
    <style><?= $_ahS['custom_css'] ?></style>
    <?php endif ?>
</head>
<body class="<?= e($bodyClass) ?>">
    <?php if (!empty($_ahS['custom_html_body'])): ?>
    <?= $_ahS['custom_html_body'] ?>
    <?php endif ?>
    <a class="skip-link" href="#main">Skip to content</a>

    <header class="site-header" aria-label="Site header">
        <a class="brand" href="/" aria-label="<?= e($_ahSiteTitle) ?> home">
            <?php if ($_ahLogoLayout !== 'text_only' && $_ahLogoUrl !== ''): ?>
                <span class="brand-mark">
                    <img src="<?= e($_ahLogoUrl) ?>" alt="" class="brand-logo-light" aria-hidden="true">
                    <?php if ($_ahLogoDarkUrl !== ''): ?>
                        <img src="<?= e($_ahLogoDarkUrl) ?>" alt="" class="brand-logo-dark" aria-hidden="true">
                    <?php endif; ?>
                </span>
            <?php endif; ?>
            <?php if ($_ahLogoLayout !== 'image' || $_ahLogoUrl === ''): ?>
                <span class="brand-text"><?= e($_ahSiteTitle) ?></span>
            <?php endif; ?>
        </a>
        <button class="menu-toggle" aria-label="Toggle navigation" aria-expanded="false" aria-controls="site-nav">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <nav class="site-nav" id="site-nav" aria-label="Primary navigation">
            <?php foreach ($navigationItems as $item): ?>
                <?php
                    $href = (string) ($item['url'] ?? '#');
                    $label = (string) ($item['label'] ?? $href);
                    $target = (string) ($item['target'] ?? '');
                ?>
                <a href="<?= e($href) ?>"<?= isActive($href, $path) ?><?= $target === '_blank' ? ' target="_blank" rel="noopener"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <?php
        $navUsername = '';
        $navDisplayName = '';
        $navUserImage = '';
        if (function_exists('current_user')) {
            $_navUser = current_user();
            $navUserImage = (string) ($_navUser['image'] ?? '');
            $navUsername = (string) ($_navUser['username'] ?? '') ?: (string) ($_navUser['id'] ?? '');
            $navDisplayName = (string) ($_navUser['name'] ?? '');
            unset($_navUser);
        }
        $adminMenuItems = function_exists('admin_navigation_ordered_items') ? admin_navigation_ordered_items() : [];
        $isLoggedIn = function_exists('user_logged_in') && user_logged_in();
        ?>
        <details class="account-menu">
            <summary class="account-menu-trigger" aria-label="<?= $isLoggedIn ? 'Open account menu' : 'Open sign-in menu' ?>">
                <?php if ($navUserImage !== ''): ?>
                    <img src="<?= e($navUserImage) ?>" alt="" class="account-menu-avatar">
                <?php else: ?>
                    <span class="account-menu-icon" aria-hidden="true">⌾</span>
                <?php endif ?>
            </summary>
            <div class="account-menu-panel">
                <?php if ($isLoggedIn): ?>
                    <?php if ($navUsername !== ''): ?>
                        <a href="/user/<?= e($navUsername) ?>" class="account-menu-link"><?= e($navDisplayName !== '' ? $navDisplayName : 'Profile') ?></a>
                    <?php endif; ?>
                    <a href="/user/settings" class="account-menu-link">Settings</a>
                    <a href="/user/logout" class="account-menu-link">Log out</a>
                    <?php if (function_exists('admin_identity') && admin_identity()): ?>
                        <span class="account-menu-divider">Admin</span>
                        <?php foreach ($adminMenuItems as $adminItem): ?>
                            <a href="<?= e($adminItem['href']) ?>" class="account-menu-link"><?= e($adminItem['label']) ?></a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="/user/login?redirect=<?= e(urlencode((string) ($_SERVER['REQUEST_URI'] ?? '/'))) ?>" class="account-menu-link">Log in</a>
                    <a href="/user/register" class="account-menu-link">Create account</a>
                <?php endif; ?>
            </div>
        </details>
    </header>

    <main id="main">
    <button class="theme-toggle" id="theme-toggle" type="button" aria-label="Toggle dark mode">
        <span class="theme-icon" aria-hidden="true"></span>
    </button>
    <script>
    (function(){
        var btn=document.getElementById('theme-toggle');
        var root=document.documentElement;
        var icon=btn&&btn.querySelector('.theme-icon');
        function update(){
            var isDark=root.dataset.theme==='dark'||(root.dataset.theme!=='light'&&window.matchMedia('(prefers-color-scheme:dark)').matches);
            if(icon)icon.textContent=isDark?'☀':'☾';
        }
        if(btn){
            btn.addEventListener('click',function(){
                var isDark=root.dataset.theme==='dark'||(root.dataset.theme!=='light'&&window.matchMedia('(prefers-color-scheme:dark)').matches);
                var next=isDark?'light':'dark';
                root.dataset.theme=next;
                localStorage.setItem('theme',next);
                update();
            });
        }
        update();
        if(window.matchMedia){window.matchMedia('(prefers-color-scheme:dark)').addEventListener('change',update);}
    })();
    </script>
