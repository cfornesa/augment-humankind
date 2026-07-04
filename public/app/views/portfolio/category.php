<?php

declare(strict_types=1);

$pageTitle = ($category['name'] ?: 'Art Medium') . ' | ' . app_site_name() . ' Portfolio';
$pageDescription = seo_excerpt($category['description'] ?? null, 170)
    ?: 'Pieces collected under the ' . ($category['name'] ?: 'selected') . ' art-medium term.';
$ogImage = $category['thumbnail_value'] ?: ($pieces[0]['thumbnail_url'] ?? null);
$canonicalUrl = seo_absolute_url('/portfolio/art-media/' . $category['slug']);
$bodyClass = bodyClass('portfolio-category');

require __DIR__ . '/../partials/header.php';
?>
    <section class="collection-detail-page">
        <a href="/portfolio/art-media" class="work-back">&#8592; All Art Media</a>

        <div class="collection-detail-header" aria-labelledby="category-title">
            <?php if ($category['thumbnail_value']): ?>
                <div class="collection-detail-thumb">
                    <img
                        src="<?= e($category['thumbnail_value']) ?>"
                        alt="<?= e($category['name']) ?>"
                        decoding="async"
                    >
                </div>
            <?php endif ?>
            <div class="collection-detail-info">
                <h1 class="collection-detail-title" id="category-title"><?= e($category['name']) ?></h1>
                <?php if ($category['description']): ?>
                    <div class="collection-detail-desc">
                        <?= $category['description'] ?>
                    </div>
                <?php endif ?>
            </div>
        </div>

        <?php if (empty($pieces)): ?>
            <p class="gallery-empty">No pieces use this art medium yet.</p>
        <?php else: ?>
            <div class="artwork-grid collection-artworks" aria-label="Pieces in this art medium">
                <?php foreach ($pieces as $i => $piece): ?>
                    <?php
                    $sizeClass = match ($i % 7) {
                        0       => 'size-large',
                        1, 2    => 'size-small',
                        3       => 'size-medium',
                        4       => 'size-wide',
                        5, 6    => 'size-small',
                        default => 'size-medium',
                    };
                    ?>
                    <a href="/pieces/<?= (int) $piece['id'] ?>"
                       aria-label="View piece <?= e($piece['title'] ?? 'Untitled') ?>"
                       class="artwork-card <?= $sizeClass ?>">
                        <div class="artwork-thumb-wrap">
                            <?php if (!empty($piece['thumbnail_url'])): ?>
                                <img
                                    src="<?= e($piece['thumbnail_url']) ?>"
                                    alt="<?= e($piece['title'] ?? 'Untitled') ?>"
                                    loading="<?= $i < 2 ? 'eager' : 'lazy' ?>"
                                    decoding="async"
                                >
                            <?php else: ?>
                                <div class="collection-thumb-placeholder" aria-hidden="true"></div>
                            <?php endif ?>
                        </div>
                        <div class="artwork-meta">
                            <span class="artwork-title"><?= e($piece['title'] ?? 'Untitled') ?></span>
                            <span class="artwork-type"><?= e(art_piece_effective_generation_mode_label($piece)) ?></span>
                        </div>
                    </a>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </section>
<?php
require __DIR__ . '/../partials/footer.php';
