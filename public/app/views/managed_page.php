<?php

declare(strict_types=1);

$pageTitle = $page['meta_title'] ?: ($page['title'] . ' | ' . app_site_name());
$pageDescription = $page['meta_description']
    ?: (seo_excerpt($sections[0]['content'] ?? '', 160) ?? ($page['title'] . ' — ' . app_site_name() . '.'));
$bodyClass = bodyClass($page['slug']);
$ogTitle = $page['og_title'] ?: $pageTitle;
$ogDescription = $page['og_description'] ?: $pageDescription;
$ogImage = $page['og_image'] ?: null;
$canonicalUrl = seo_absolute_url('/' . $page['slug']);

require __DIR__ . '/partials/header.php';
?>
    <?php if (!empty($isPreview)): ?>
        <section class="form-status form-status-success" aria-label="Draft preview notice">
            <h3>Draft Preview</h3>
            <p>This page is still in draft status. Only signed-in admins can see this preview at the public URL.</p>
        </section>
    <?php endif; ?>
    <?php if ($page['slug'] === 'home' || $page['slug'] === 'about'): ?>
        <?php $siteSettings = class_exists('SiteSettings') ? (SiteSettings::current() ?: []) : []; ?>
        <?php if ($page['slug'] === 'home'): ?>
            <?php
            $heroHeading = trim((string) ($siteSettings['hero_heading'] ?? ''));
            $heroSubheading = trim((string) ($siteSettings['hero_subheading'] ?? ''));
            $ctaLabel = trim((string) ($siteSettings['cta_label'] ?? ''));
            $ctaHref = trim((string) ($siteSettings['cta_href'] ?? '')) ?: '/';
            ?>
            <?php if ($heroHeading !== '' || $heroSubheading !== '' || $ctaLabel !== ''): ?>
                <section class="hero section-grid" aria-labelledby="home-hero-title">
                    <div class="hero-copy">
                        <?php if ($heroHeading !== ''): ?><h1 id="home-hero-title"><?= e($heroHeading) ?></h1><?php endif; ?>
                        <?php if ($heroSubheading !== ''): ?><p class="hero-statement"><?= e($heroSubheading) ?></p><?php endif; ?>
                        <?php if ($ctaLabel !== ''): ?>
                            <div class="hero-actions" aria-label="Primary action">
                                <a class="button button-primary" href="<?= e($ctaHref) ?>"><?= e($ctaLabel) ?></a>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
        <?php else: /* about */ ?>
            <?php
            $aboutHeading = trim((string) ($siteSettings['about_heading'] ?? ''));
            $aboutBody = trim((string) ($siteSettings['about_body'] ?? ''));
            ?>
            <?php if ($aboutHeading !== '' || $aboutBody !== ''): ?>
                <section class="mission-band" aria-labelledby="about-heading">
                    <?php if ($aboutHeading !== ''): ?><h1 id="about-heading"><?= e($aboutHeading) ?></h1><?php endif; ?>
                    <?php if ($aboutBody !== ''): ?><p><?= e($aboutBody) ?></p><?php endif; ?>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
    <?php if (empty($sections)): ?>
        <section class="page-hero" aria-labelledby="managed-empty-title">
            <p class="eyebrow"><?= e($page['title']) ?></p>
            <h1 id="managed-empty-title">This page has not been written yet.</h1>
        </section>
    <?php else: ?>
        <?php foreach ($sections as $section): ?>
            <?php if ($section['wrapper_class']): ?>
                <section class="<?= e($section['wrapper_class']) ?>"<?= $section['heading'] ? ' aria-labelledby="managed-section-' . (int) $section['id'] . '"' : '' ?>>
                    <?php if ($section['heading']): ?>
                        <h2 id="managed-section-<?= (int) $section['id'] ?>"><?= e($section['heading']) ?></h2>
                    <?php endif; ?>
                    <?= $section['content'] ?>
                </section>
            <?php elseif ($section['heading']): ?>
                <section class="managed-section" aria-labelledby="managed-section-<?= (int) $section['id'] ?>">
                    <h2 id="managed-section-<?= (int) $section['id'] ?>"><?= e($section['heading']) ?></h2>
                    <div class="managed-section-body"><?= $section['content'] ?></div>
                </section>
            <?php else: ?>
                <?= $section['content'] ?>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
<script src="/embed.js" defer></script>
<?php
require __DIR__ . '/partials/footer.php';
