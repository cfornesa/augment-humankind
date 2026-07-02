<?php

declare(strict_types=1);

$pageTitle = $page['meta_title'] ?: ($page['title'] . ' | ' . app_site_name());
$pageDescription = $page['meta_description']
    ?: (seo_excerpt($sections[0]['content'] ?? '', 160) ?? ($page['title'] . ' — ' . app_site_name() . '.'));
$bodyClass = bodyClass($page['slug']);
$systemKey = class_exists('Page') ? Page::systemKeyForPage($page) : null;
$ogTitle = $page['og_title'] ?: $pageTitle;
$ogDescription = $page['og_description'] ?: $pageDescription;
$ogImage = $page['og_image'] ?: null;
$canonicalUrl = seo_absolute_url('/' . $page['slug']);

require __DIR__ . '/partials/header.php';
?>
    <?php if (!empty($isPreview)):
        $status = 'draft';
        $statusBannerNote = 'This page is still in draft status. Only signed-in admins can see this preview at the public URL.';
        require __DIR__ . '/partials/status-banner.php';
    endif; ?>
    <?php if ($systemKey === 'home'): ?>
        <?php
        $siteSettings = class_exists('SiteSettings') ? (SiteSettings::current() ?: []) : [];
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
    <?php endif; ?>
    <?php $descriptionText = trim((string) ($page['description'] ?? '')); ?>
    <?php if (!empty($page['show_description_section']) && $descriptionText !== ''): ?>
        <section class="mission-band" aria-labelledby="page-description-heading">
            <h1 id="page-description-heading"><?= e($page['title']) ?></h1>
            <?php foreach (preg_split('/\R{2,}/', $descriptionText) ?: [] as $descriptionParagraph): ?>
                <?php if (trim($descriptionParagraph) === '') { continue; } ?>
                <p><?= nl2br(e(trim($descriptionParagraph))) ?></p>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
    <?php if (empty($sections)): ?>
        <section class="page-hero" aria-labelledby="managed-empty-title">
            <p class="eyebrow"><?= e($page['title']) ?></p>
            <h1 id="managed-empty-title">This page has not been written yet.</h1>
        </section>
    <?php else: ?>
        <?php foreach ($sections as $section): ?>
            <?php if (($section['section_kind'] ?? 'content') === 'form'): ?>
                <?php require __DIR__ . '/partials/form-section.php'; ?>
                <?php continue; ?>
            <?php endif; ?>
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
