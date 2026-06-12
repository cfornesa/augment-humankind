<?php

declare(strict_types=1);

$pageTitle = $page['meta_title'] ?: ($page['title'] . ' | Augment Humankind');
$pageDescription = $page['meta_description']
    ?: (seo_excerpt($sections[0]['content'] ?? '', 160) ?? ($page['title'] . ' — Augment Humankind.'));
$bodyClass = bodyClass($page['slug']);
$ogTitle = $page['og_title'] ?: $pageTitle;
$ogDescription = $page['og_description'] ?: $pageDescription;
$ogImage = $page['og_image'] ?: null;
$canonicalUrl = seo_absolute_url('/' . $page['slug']);

require __DIR__ . '/partials/header.php';
?>
    <?php if (empty($sections)): ?>
        <section class="page-hero" aria-labelledby="managed-empty-title">
            <p class="eyebrow"><?= e($page['title']) ?></p>
            <h1 id="managed-empty-title">This page has not been written yet.</h1>
        </section>
    <?php else: ?>
        <?php foreach ($sections as $section): ?>
            <?php if ($section['heading']): ?>
                <section class="managed-section" aria-labelledby="managed-section-<?= (int) $section['id'] ?>">
                    <h2 id="managed-section-<?= (int) $section['id'] ?>"><?= e($section['heading']) ?></h2>
                    <div class="managed-section-body"><?= $section['content'] ?></div>
                </section>
            <?php else: ?>
                <?= $section['content'] ?>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
<?php
require __DIR__ . '/partials/footer.php';
