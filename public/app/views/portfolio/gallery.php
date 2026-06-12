<?php

declare(strict_types=1);

$pageTitle = 'Portfolio Gallery | Augment Humankind';
$pageDescription = 'Browse exhibits and works from the Augment Humankind portfolio.';
$ogImage = $exhibits[0]['thumbnail_value'] ?? ($artworks[0]['thumbnail_value'] ?? null);
$canonicalUrl = seo_absolute_url('/portfolio');
$bodyClass = bodyClass('portfolio-gallery');

require __DIR__ . '/../partials/header.php';
?>
    <section class="gallery-page">
        <div class="gallery-intro">
            <p class="eyebrow">Portfolio</p>
            <h1>Gallery</h1>
        </div>

        <?php if (!empty($exhibits)): ?>
            <div class="gallery-section" aria-labelledby="gallery-exhibits-heading">
                <div class="gallery-section-header">
                    <h2 class="category-name" id="gallery-exhibits-heading">Exhibits</h2>
                    <span class="section-rule" aria-hidden="true"></span>
                </div>
                <div class="artwork-grid">
                    <?php foreach ($exhibits as $exIndex => $ex): ?>
                        <?php
                        $sizeClass = match ($exIndex % 7) {
                            0       => 'size-large',
                            1, 2    => 'size-small',
                            3       => 'size-medium',
                            4       => 'size-wide',
                            5, 6    => 'size-small',
                            default => 'size-medium',
                        };
                        ?>
                        <a href="/portfolio/exhibit/<?= e($ex['slug']) ?>" class="artwork-card <?= $sizeClass ?>" aria-label="View exhibit <?= e($ex['name']) ?>">
                            <div class="artwork-thumb-wrap">
                                <?php if ($ex['thumbnail_value']): ?>
                                    <img
                                        src="<?= e($ex['thumbnail_value']) ?>"
                                        alt="<?= e($ex['name']) ?>"
                                        loading="<?= $exIndex === 0 ? 'eager' : 'lazy' ?>"
                                        decoding="async"
                                    >
                                <?php else: ?>
                                    <div class="collection-thumb-placeholder" aria-hidden="true"></div>
                                <?php endif ?>
                            </div>
                            <div class="artwork-meta">
                                <span class="artwork-title"><?= e($ex['name']) ?></span>
                            </div>
                        </a>
                    <?php endforeach ?>
                </div>
            </div>
        <?php endif ?>

        <div class="gallery-section" aria-labelledby="gallery-works-heading">
            <div class="gallery-section-header">
                <h2 class="category-name" id="gallery-works-heading">Works</h2>
                <span class="section-rule" aria-hidden="true"></span>
            </div>

            <?php if (empty($artworks)): ?>
                <p class="gallery-empty">No works have been added yet.</p>
            <?php else: ?>
                <div class="exhibits-grid" id="gallery-work-grid">
                    <?php foreach ($artworks as $i => $work): ?>
                        <?php $overflow = $i >= 3 ? ' gallery-work-overflow' : ''; ?>
                        <a href="/portfolio/work/<?= e($work['slug']) ?>"
                           aria-label="View work <?= e($work['title'] . ($work['year'] ? ', ' . $work['year'] : '')) ?>"
                           class="exhibit-card<?= $overflow ?>">
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
                <?php if (count($artworks) > 3): ?>
                    <button
                        class="see-more-btn"
                        id="works-see-more"
                        type="button"
                        aria-expanded="false"
                        aria-controls="gallery-work-grid"
                    >See More</button>
                <?php endif ?>
            <?php endif ?>
        </div>
    </section>
<?php
require __DIR__ . '/../partials/footer.php';
