<?php

declare(strict_types=1);

$pageTitle = 'Portfolio Gallery | Augment Humankind';
$pageDescription = 'Browse exhibits, collections, and generative pieces from the Augment Humankind portfolio.';
$ogImage = $collections[0]['thumbnail_value'] ?? ($exhibits[0]['thumbnail_value'] ?? null);
$canonicalUrl = seo_absolute_url('/portfolio');
$bodyClass = bodyClass('portfolio-gallery');

require __DIR__ . '/../partials/header.php';
?>
    <style>
    .js-enhanced .collections-overflow,
    .js-enhanced .platform-collections-overflow,
    .js-enhanced .pieces-overflow {
        display: none;
    }
    .portfolio-grid-3 {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1.5rem;
    }
    @media (max-width: 768px) {
        .portfolio-grid-3 {
            grid-template-columns: 1fr;
        }
    }
    </style>

    <section class="gallery-page">
        <div class="gallery-intro">
            <p class="eyebrow">Portfolio</p>
            <h1>Gallery</h1>
        </div>

        <!-- 1. Native Collections -->
        <?php if (!empty($collections)): ?>
            <div class="gallery-section" aria-labelledby="gallery-collections-heading" style="margin-bottom: 3rem;">
                <div class="gallery-section-header">
                    <h2 class="category-name" id="gallery-collections-heading">Collections</h2>
                    <span class="section-rule" aria-hidden="true"></span>
                </div>
                <div class="portfolio-grid-3" id="gallery-collections-grid">
                    <?php foreach ($collections as $colIndex => $col): ?>
                        <?php $overflow = $colIndex >= 3 ? ' collections-overflow' : ''; ?>
                        <a href="/portfolio/collection/<?= e($col['slug']) ?>" class="artwork-card<?= $overflow ?>" aria-label="View collection <?= e($col['name']) ?>">
                            <div class="artwork-thumb-wrap">
                                <?php if ($thumb = Collection::previewImage($col)): ?>
                                    <img
                                        src="<?= e($thumb) ?>"
                                        alt="<?= e($col['name']) ?>"
                                        loading="lazy"
                                        decoding="async"
                                    >
                                <?php else: ?>
                                    <div class="collection-thumb-placeholder" aria-hidden="true"></div>
                                <?php endif ?>
                            </div>
                            <div class="artwork-meta">
                                <span class="artwork-title"><?= e($col['name']) ?></span>
                            </div>
                        </a>
                    <?php endforeach ?>
                </div>
                <?php if (count($collections) > 3): ?>
                    <button
                        class="see-more-btn"
                        id="collections-see-more"
                        type="button"
                        aria-expanded="false"
                        aria-controls="gallery-collections-grid"
                    >See More</button>
                <?php endif ?>
            </div>
        <?php endif ?>

        <!-- 2. Exhibits -->
        <div class="gallery-section" aria-labelledby="gallery-exhibits-heading" style="margin-bottom: 3rem;">
            <div class="gallery-section-header">
                <h2 class="category-name" id="gallery-exhibits-heading">Exhibits</h2>
                <span class="section-rule" aria-hidden="true"></span>
            </div>

            <?php if (empty($exhibits)): ?>
                <p class="gallery-empty">No exhibits have been added yet.</p>
            <?php else: ?>
                <div class="portfolio-grid-3" id="gallery-exhibit-grid">
                    <?php foreach ($exhibits as $i => $ex): ?>
                        <?php $overflow = $i >= 3 ? ' gallery-work-overflow' : ''; ?>
                        <a href="/portfolio/exhibit/<?= e($ex['slug']) ?>"
                           aria-label="View exhibit <?= e($ex['title'] . ($ex['year'] ? ', ' . $ex['year'] : '')) ?>"
                           class="exhibit-card<?= $overflow ?>">
                            <div class="artwork-thumb-wrap">
                                <?php if ($thumb = Exhibit::previewImage($ex)): ?>
                                    <img
                                        src="<?= e($thumb) ?>"
                                        alt="<?= e($ex['title']) ?>"
                                        loading="lazy"
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
                <?php if (count($exhibits) > 3): ?>
                    <button
                        class="see-more-btn"
                        id="works-see-more"
                        type="button"
                        aria-expanded="false"
                        aria-controls="gallery-exhibit-grid"
                    >See More</button>
                <?php endif ?>
            <?php endif ?>
        </div>

        <!-- 3. Platform Collections -->
        <?php if (!empty($platformCollections)): ?>
            <div class="gallery-section" aria-labelledby="gallery-platform-collections-heading" style="margin-bottom: 3rem;">
                <div class="gallery-section-header">
                    <h2 class="category-name" id="gallery-platform-collections-heading">Platform Collections</h2>
                    <span class="section-rule" aria-hidden="true"></span>
                </div>
                <div class="portfolio-grid-3" id="gallery-platform-collections-grid">
                    <?php foreach ($platformCollections as $pColIndex => $pCol): ?>
                        <?php $overflow = $pColIndex >= 3 ? ' platform-collections-overflow' : ''; ?>
                        <a href="/immersive/collections/<?= e($pCol['slug']) ?>?returnTo=<?= rawurlencode($_SERVER['REQUEST_URI'] ?? '') ?>" class="artwork-card<?= $overflow ?>" aria-label="View platform collection <?= e($pCol['name']) ?>">
                            <div class="artwork-thumb-wrap">
                                <?php if ($pCol['thumbnail_url']): ?>
                                    <img
                                        src="<?= e($pCol['thumbnail_url']) ?>"
                                        alt="<?= e($pCol['name']) ?>"
                                        loading="lazy"
                                        decoding="async"
                                    >
                                <?php else: ?>
                                    <div class="collection-thumb-placeholder" aria-hidden="true"></div>
                                <?php endif ?>
                            </div>
                            <div class="artwork-meta">
                                <span class="artwork-title"><?= e($pCol['name']) ?></span>
                                <span class="artwork-type" style="font-size: 0.8rem; color: var(--ink-soft); display: block; margin-top: 0.2rem;">Immersive Gallery</span>
                            </div>
                        </a>
                    <?php endforeach ?>
                </div>
                <?php if (count($platformCollections) > 3): ?>
                    <button
                        class="see-more-btn"
                        id="platform-collections-see-more"
                        type="button"
                        aria-expanded="false"
                        aria-controls="gallery-platform-collections-grid"
                    >See More</button>
                <?php endif ?>
            </div>
        <?php endif ?>

        <!-- 4. Art Pieces -->
        <?php if (!empty($pieces)): ?>
            <div class="gallery-section" aria-labelledby="gallery-pieces-heading" style="margin-bottom: 3rem;">
                <div class="gallery-section-header">
                    <h2 class="category-name" id="gallery-pieces-heading">Art Pieces</h2>
                    <span class="section-rule" aria-hidden="true"></span>
                </div>
                <div class="portfolio-grid-3" id="gallery-pieces-grid">
                    <?php foreach ($pieces as $pieceIndex => $piece): ?>
                        <?php $overflow = $pieceIndex >= 3 ? ' pieces-overflow' : ''; ?>
                        <a href="/pieces/<?= (int) $piece['id'] ?>" class="artwork-card<?= $overflow ?>" aria-label="View piece <?= e($piece['title']) ?>">
                            <div class="artwork-thumb-wrap">
                                <?php if ($piece['thumbnail_url']): ?>
                                    <img
                                        src="<?= e($piece['thumbnail_url']) ?>"
                                        alt="<?= e($piece['title']) ?>"
                                        loading="lazy"
                                        decoding="async"
                                    >
                                <?php else: ?>
                                    <div class="collection-thumb-placeholder" aria-hidden="true"></div>
                                <?php endif ?>
                            </div>
                            <div class="artwork-meta">
                                <span class="artwork-title"><?= e($piece['title'] ?? 'Untitled') ?></span>
                                <span class="artwork-type" style="font-size: 0.8rem; color: var(--ink-soft); display: block; margin-top: 0.2rem;">
                                    <?= e(strtoupper($piece['current_version']['engine'] ?? 'p5')) ?>
                                </span>
                            </div>
                        </a>
                    <?php endforeach ?>
                </div>
                <?php if (count($pieces) > 3): ?>
                    <button
                        class="see-more-btn"
                        id="pieces-see-more"
                        type="button"
                        aria-expanded="false"
                        aria-controls="gallery-pieces-grid"
                    >See More</button>
                <?php endif ?>
            </div>
        <?php endif ?>

    </section>
<?php
require __DIR__ . '/../partials/footer.php';
