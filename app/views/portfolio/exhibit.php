<?php

declare(strict_types=1);

$pageTitle = ($exhibit['name'] ?: 'Exhibit') . ' | Augment Humankind Portfolio';
$pageDescription = seo_excerpt($exhibit['description'] ?? null, 170)
    ?: 'Works gathered in the ' . ($exhibit['name'] ?: 'selected') . ' exhibit.';
$ogImage = $exhibit['thumbnail_value'] ?: ($artworks[0]['thumbnail_value'] ?? null);
$canonicalUrl = seo_absolute_url('/portfolio/exhibit/' . $exhibit['slug']);
$bodyClass = bodyClass('portfolio-exhibit');

require __DIR__ . '/../partials/header.php';
?>
    <section class="collection-detail-page">
        <a href="/portfolio" class="work-back">&#8592; Return to the gallery</a>

        <div class="collection-detail-header" aria-labelledby="exhibit-title">
            <?php if ($exhibit['thumbnail_value']): ?>
                <div class="collection-detail-thumb">
                    <img
                        src="<?= e($exhibit['thumbnail_value']) ?>"
                        alt="<?= e($exhibit['name']) ?>"
                        decoding="async"
                    >
                </div>
            <?php endif ?>
            <div class="collection-detail-info">
                <h1 class="collection-detail-title" id="exhibit-title"><?= e($exhibit['name']) ?></h1>
                <?php if ($exhibit['description']): ?>
                    <div class="collection-detail-desc">
                        <?= $exhibit['description'] ?>
                    </div>
                <?php endif ?>
            </div>
        </div>

        <?php if (empty($artworks)): ?>
            <p class="gallery-empty">No works in this exhibit yet.</p>
        <?php else: ?>
            <div class="artwork-grid collection-artworks" aria-label="Works in this exhibit">
                <?php foreach ($artworks as $i => $work): ?>
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
                    <a href="/portfolio/work/<?= e($work['slug']) ?>"
                       aria-label="View work <?= e($work['title'] . ($work['year'] ? ', ' . $work['year'] : '')) ?>"
                       class="artwork-card <?= $sizeClass ?>">
                        <div class="artwork-thumb-wrap">
                            <?php if ($work['thumbnail_value']): ?>
                                <img
                                    src="<?= e($work['thumbnail_value']) ?>"
                                    alt="<?= e($work['title']) ?>"
                                    loading="<?= $i < 2 ? 'eager' : 'lazy' ?>"
                                    decoding="async"
                                >
                            <?php else: ?>
                                <div class="collection-thumb-placeholder" aria-hidden="true"></div>
                            <?php endif ?>
                        </div>
                        <div class="artwork-meta">
                            <span class="artwork-title"><?= e($work['title']) ?></span>
                            <?php if ($work['year']): ?>
                                <span class="artwork-year"><?= e($work['year']) ?></span>
                            <?php endif ?>
                        </div>
                    </a>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </section>
<?php
require __DIR__ . '/../partials/footer.php';
