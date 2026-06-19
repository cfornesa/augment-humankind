<?php

declare(strict_types=1);

$pageTitle = 'Art Media | ' . app_site_name() . ' Portfolio';
$pageDescription = 'Explore the art media that organize pieces within the ' . app_site_name() . ' portfolio.';
$ogImage = $categories[0]['thumbnail_value'] ?? null;
$canonicalUrl = seo_absolute_url('/portfolio/art-media');
$bodyClass = bodyClass('portfolio-categories');

require __DIR__ . '/../partials/header.php';
?>
    <section class="collection-page">
        <div class="collection-header">
            <a href="/portfolio" class="work-back">&#8592; Back to gallery</a>
            <h1 class="collection-title">Art Media</h1>
        </div>

        <?php if (empty($categories)): ?>
            <p class="gallery-empty">No art media have been created yet.</p>
        <?php else: ?>
            <div class="collection-grid" aria-label="Art media list">
                <?php foreach ($categories as $catIndex => $cat): ?>
                    <a href="/portfolio/art-media/<?= e($cat['slug']) ?>" class="collection-card" aria-label="View art medium <?= e($cat['name']) ?>">
                        <div class="collection-thumb-wrap">
                            <?php if ($cat['thumbnail_value']): ?>
                                <img
                                    src="<?= e($cat['thumbnail_value']) ?>"
                                    alt="<?= e($cat['name']) ?>"
                                    loading="<?= $catIndex === 0 ? 'eager' : 'lazy' ?>"
                                    decoding="async"
                                >
                            <?php else: ?>
                                <div class="collection-thumb-placeholder" aria-hidden="true"></div>
                            <?php endif ?>
                        </div>
                        <div class="collection-card-meta">
                            <span class="collection-card-name"><?= e($cat['name']) ?></span>
                            <?php if ($cat['description']): ?>
                                <span class="collection-card-desc">
                                    <?= e(mb_substr($cat['description'], 0, 100)) ?><?= mb_strlen($cat['description']) > 100 ? '…' : '' ?>
                                </span>
                            <?php endif ?>
                        </div>
                    </a>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </section>
<?php
require __DIR__ . '/../partials/footer.php';
