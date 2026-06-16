<?php

declare(strict_types=1);

$path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';

require_once dirname(__DIR__, 2) . '/helpers/navigation.php';
$navigationItems = ah_public_navigation_items();

$bodyClass = $bodyClass ?? 'page-managed';
$pageDescription = $pageDescription ?? '';
$ogTitle = $ogTitle ?? $pageTitle;
$ogDescription = $ogDescription ?? $pageDescription;
$ogImage = $ogImage ?? null;
$canonicalUrl = $canonicalUrl ?? null;
$extraHeadHtml = $extraHeadHtml ?? '';

// Load site settings for CSS color injection (gracefully skips if table missing)
$_ahS = (class_exists('SiteSettings') ? SiteSettings::current() : false) ?: [];

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
    <?php if ($canonicalUrl): ?>
        <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <?php endif; ?>
    <meta property="og:title" content="<?= e($ogTitle) ?>">
    <meta property="og:description" content="<?= e($ogDescription) ?>">
    <?php if ($ogImage): ?>
        <meta property="og:image" content="<?= e($ogImage) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="/assets/styles.css">
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
</head>
<body class="<?= e($bodyClass) ?>">
    <a class="skip-link" href="#main">Skip to content</a>

    <header class="site-header" aria-label="Site header">
        <a class="brand" href="/" aria-label="Augment Humankind home">
            <img src="/assets/friendly-guide.png" alt="" class="brand-mark" aria-hidden="true">
            <span class="brand-text">Augment Humankind</span>
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
            <?php if (function_exists('user_logged_in') && user_logged_in()): ?>
                <?php
                $navUsername    = $_SESSION['user_username'] ?? '';
                $navDisplayName = $_SESSION['user_display_name'] ?? '';
                if ($navUsername === '' && function_exists('current_user')) {
                    $_navU = current_user();
                    $navUsername    = (string) ($_navU['username'] ?? '');
                    $navDisplayName = (string) ($_navU['name'] ?? $navUsername);
                    unset($_navU);
                }
                ?>
                <?php if ($navUsername !== ''): ?>
                <a href="/user/<?= e($navUsername) ?>" class="user-nav-link" style="font-weight:700;">Profile</a>
                <?php endif ?>
                <a href="/user/settings" class="user-nav-link">Settings</a>
            <?php endif ?>
        </nav>
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
