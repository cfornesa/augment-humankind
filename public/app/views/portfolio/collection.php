<?php

declare(strict_types=1);

$pageTitle = ($collection['name'] ?: 'Collection') . ' | Augment Humankind Portfolio';
$pageDescription = seo_excerpt($collection['description'] ?? null, 170)
    ?: 'Exhibits gathered in the ' . ($collection['name'] ?: 'selected') . ' collection.';
$ogImage = $collection['thumbnail_value'] ?: ($exhibits[0]['thumbnail_value'] ?? null);
$canonicalUrl = seo_absolute_url('/portfolio/collection/' . $collection['slug']);
$bodyClass = bodyClass('portfolio-exhibit');

require __DIR__ . '/../partials/header.php';
?>
    <section class="collection-detail-page">
        <a href="/portfolio/exhibit-collections" class="work-back">&#8592; Return to exhibit collections</a>

        <div class="collection-detail-header" aria-labelledby="exhibit-title">
            <?php if ($collection['thumbnail_value']): ?>
                <div class="collection-detail-thumb">
                    <img
                        src="<?= e($collection['thumbnail_value']) ?>"
                        alt="<?= e($collection['name']) ?>"
                        decoding="async"
                    >
                </div>
            <?php endif ?>
            <div class="collection-detail-info">
                <h1 class="collection-detail-title" id="exhibit-title"><?= e($collection['name']) ?></h1>
                <?php if ($collection['description']): ?>
                    <div class="collection-detail-desc">
                        <?= $collection['description'] ?>
                    </div>
                <?php endif ?>
            </div>
        </div>

        <?php if (empty($exhibits)): ?>
            <p class="gallery-empty">No exhibits in this collection yet.</p>
        <?php else: ?>
            <div class="artwork-grid collection-artworks" aria-label="Exhibits in this collection">
                <?php foreach ($exhibits as $i => $ex): ?>
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
                    <a href="/portfolio/exhibit/<?= e($ex['slug']) ?>"
                       aria-label="View exhibit <?= e($ex['title'] . ($ex['year'] ? ', ' . $ex['year'] : '')) ?>"
                       class="artwork-card <?= $sizeClass ?>">
                        <div class="artwork-thumb-wrap">
                            <?php if ($ex['thumbnail_value']): ?>
                                <img
                                    src="<?= e($ex['thumbnail_value']) ?>"
                                    alt="<?= e($ex['title']) ?>"
                                    loading="<?= $i < 2 ? 'eager' : 'lazy' ?>"
                                    decoding="async"
                                >
                            <?php else: ?>
                                <div class="collection-thumb-placeholder" aria-hidden="true"></div>
                            <?php endif ?>
                        </div>
                        <div class="artwork-meta">
                            <span class="artwork-title"><?= e($ex['title']) ?></span>
                            <?php if ($ex['year']): ?>
                                <span class="artwork-year"><?= e($ex['year']) ?></span>
                            <?php endif ?>
                        </div>
                    </a>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </section>
<?php
require __DIR__ . '/../partials/footer.php';
