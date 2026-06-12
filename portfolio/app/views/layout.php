<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    $siteName = 'Fornesus Art';
    $resolvedTitle = $metaTitle ?? $pageTitle ?? $siteName;
    $resolvedDescription = $metaDescription ?? 'An otherworldly archive of artworks, exhibits, and collected pages from Fornesus Art.';
    $resolvedOgTitle = $ogTitle ?? $resolvedTitle;
    $resolvedOgDescription = $ogDescription ?? $resolvedDescription;
    $resolvedCanonical = $canonicalUrl ?? seo_current_url();
    $resolvedImage = seo_absolute_url($metaImage ?? null);
    $resolvedImageAlt = $metaImageAlt ?? $resolvedOgTitle;
    $navigationItems = NavigationItem::publicItems();
    ?>
    <title><?= htmlspecialchars($resolvedTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($resolvedDescription) ?>">
    <link rel="canonical" href="<?= htmlspecialchars($resolvedCanonical) ?>">
    <?php if (!empty($metaRobots)): ?>
        <meta name="robots" content="<?= htmlspecialchars($metaRobots) ?>">
    <?php endif ?>
    <meta property="og:site_name" content="<?= htmlspecialchars($siteName) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($resolvedOgTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($resolvedOgDescription) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($resolvedCanonical) ?>">
    <meta name="twitter:card" content="<?= $resolvedImage ? 'summary_large_image' : 'summary' ?>">
    <meta name="twitter:title" content="<?= htmlspecialchars($resolvedOgTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($resolvedOgDescription) ?>">
    <meta name="theme-color" content="#000000">
    <?php if ($resolvedImage): ?>
        <meta property="og:image" content="<?= htmlspecialchars($resolvedImage) ?>">
        <meta property="og:image:alt" content="<?= htmlspecialchars($resolvedImageAlt) ?>">
        <meta name="twitter:image" content="<?= htmlspecialchars($resolvedImage) ?>">
        <meta name="twitter:image:alt" content="<?= htmlspecialchars($resolvedImageAlt) ?>">
    <?php endif ?>
    <script>document.documentElement.classList.add('js-enhanced');</script>
    <link rel="preload" href="/assets/fonts/lora-normal-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/assets/fonts/pinyon-script-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(dirname(__DIR__, 2) . '/public/assets/css/style.css') ?>">
</head>
<body>
    <div id="celestial-background" aria-hidden="true">
        <div class="nebula-wash nebula-wash--1"></div>
        <div class="nebula-wash nebula-wash--2"></div>
        <div class="nebula-wash nebula-wash--3"></div>
        <svg class="astrolabe-grid" viewBox="0 0 100 100">
            <circle cx="50" cy="50" r="48" fill="none" stroke="var(--amber-border)" stroke-width="0.1" stroke-dasharray="1 3" />
            <circle cx="50" cy="50" r="35" fill="none" stroke="var(--amber-border)" stroke-width="0.1" />
            <circle cx="50" cy="50" r="20" fill="none" stroke="var(--amber-border)" stroke-width="0.08" stroke-dasharray="2 1" />
            <line x1="50" y1="2" x2="50" y2="98" stroke="var(--amber-border)" stroke-width="0.05" />
            <line x1="2" y1="50" x2="98" y2="50" stroke="var(--amber-border)" stroke-width="0.05" />
            <path d="M 16 16 L 84 84 M 84 16 L 16 84" stroke="var(--amber-border)" stroke-dasharray="1 5" stroke-width="0.05" />
        </svg>
    </div>
    <a href="#main-content" class="skip-link">Skip to content</a>
    <header class="site-header">
        <a href="/" class="site-title"><?= htmlspecialchars($siteName) ?></a>
        <div class="site-nav-shell" data-site-nav-shell>
            <nav class="site-nav" aria-label="Primary" data-site-nav>
                <ul class="site-nav-list" data-site-nav-list>
                    <?php foreach ($navigationItems as $navItem): ?>
                        <?php
                        $isActive = ($activePage ?? '') === ($navItem['active_key'] ?? '');
                        $isExternal = !str_starts_with($navItem['url'], '/');
                        ?>
                        <li class="site-nav-item" data-site-nav-item>
                            <a href="<?= htmlspecialchars($navItem['url']) ?>"
                               class="<?= $isActive ? 'active' : '' ?>"
                               <?= $isActive ? ' aria-current="page"' : '' ?>
                               <?= !empty($navItem['target']) ? ' target="' . htmlspecialchars($navItem['target']) . '"' : '' ?>
                               <?= !empty($navItem['target']) ? ' rel="noreferrer noopener"' : '' ?>
                               <?= $isExternal ? ' data-external-link="true"' : '' ?>>
                                <?= htmlspecialchars($navItem['label']) ?>
                            </a>
                        </li>
                    <?php endforeach ?>
                </ul>
            </nav>
            <button type="button"
                    class="site-nav-toggle"
                    data-site-nav-toggle
                    aria-expanded="false"
                    aria-controls="site-nav-overflow"
                    aria-label="Open navigation menu"
                    hidden>
                <span class="site-nav-toggle-lines" aria-hidden="true">
                    <span></span>
                    <span></span>
                    <span></span>
                </span>
            </button>
            <div class="site-nav-overflow" id="site-nav-overflow" data-site-nav-overflow hidden>
                <ul class="site-nav-overflow-list" data-site-nav-overflow-list></ul>
            </div>
        </div>
    </header>

    <main id="main-content">
        <?= $content ?>
    </main>

    <footer class="site-footer">
        <span>Fornesus Art</span>
    </footer>

    <script src="/assets/js/main.js"></script>
    <script src="/assets/js/cosmos.js" defer></script>
</body>
</html>
