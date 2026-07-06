<?php

declare(strict_types=1);

$pageTitle = 'Portfolio Gallery | ' . app_site_name();
$pageDescription = public_copy_value('portfolio_copy.gallery.meta_description');
$ogImage = $collections[0]['preview_image'] ?? ($exhibits[0]['preview_image'] ?? null);
$canonicalUrl = seo_absolute_url('/portfolio');
$bodyClass = bodyClass('portfolio-gallery');

require __DIR__ . '/../partials/header.php';
?>
    <section class="gallery-page">
        <div class="gallery-intro">
            <p class="eyebrow"><?= e(public_copy_value('portfolio_copy.gallery.eyebrow')) ?></p>
            <h1><?= e(public_copy_value('portfolio_copy.gallery.title')) ?></h1>
            <p><?= e(public_copy_value('portfolio_copy.gallery.intro')) ?></p>
        </div>

        <div class="gallery-section" aria-labelledby="gallery-collections-heading">
            <div class="gallery-section-header">
                <div>
                    <h2 class="category-name" id="gallery-collections-heading"><?= e(public_copy_value('portfolio_copy.gallery.sections.exhibit_collections.heading')) ?></h2>
                    <p class="gallery-section-copy"><?= e(public_copy_value('portfolio_copy.gallery.sections.exhibit_collections.description')) ?></p>
                </div>
                <a href="/portfolio/exhibit-collections" class="gallery-section-link"><?= e(public_copy_value('portfolio_copy.gallery.sections.exhibit_collections.cta_label')) ?></a>
                <span class="section-rule" aria-hidden="true"></span>
            </div>
            <?php if (empty($collections)): ?>
                <p class="gallery-empty"><?= e(public_copy_value('portfolio_copy.gallery.sections.exhibit_collections.empty_state')) ?></p>
            <?php else: ?>
                <div
                    class="portfolio-archive-listing"
                    data-see-more-listing
                    data-next-offset="<?= count($collections) ?>"
                    data-has-more="<?= $collectionTotal > count($collections) ? 'true' : 'false' ?>"
                    data-fetch-url="/portfolio/exhibit-collections?sort=newest"
                >
                    <div class="portfolio-grid-3" data-listing-grid>
                        <?php $items = $collections; $itemType = 'collections'; require __DIR__ . '/archive-cards.php'; ?>
                    </div>
                    <div class="portfolio-listing-footer">
                        <p class="portfolio-listing-status" data-listing-status aria-live="polite">
                            Showing <?= count($collections) ?> of <?= (int) $collectionTotal ?>.
                        </p>
                        <?php if ($collectionTotal > count($collections)): ?>
                        <button class="gallery-see-more-btn post-action-btn" data-listing-see-more-btn>
                            See More
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif ?>
        </div>

        <div class="gallery-section" aria-labelledby="gallery-exhibits-heading">
            <div class="gallery-section-header">
                <div>
                    <h2 class="category-name" id="gallery-exhibits-heading"><?= e(public_copy_value('portfolio_copy.gallery.sections.exhibits.heading')) ?></h2>
                    <p class="gallery-section-copy"><?= e(public_copy_value('portfolio_copy.gallery.sections.exhibits.description')) ?></p>
                </div>
                <a href="/portfolio/exhibits" class="gallery-section-link"><?= e(public_copy_value('portfolio_copy.gallery.sections.exhibits.cta_label')) ?></a>
                <span class="section-rule" aria-hidden="true"></span>
            </div>

            <?php if (empty($exhibits)): ?>
                <p class="gallery-empty"><?= e(public_copy_value('portfolio_copy.gallery.sections.exhibits.empty_state')) ?></p>
            <?php else: ?>
                <div
                    class="portfolio-archive-listing"
                    data-see-more-listing
                    data-next-offset="<?= count($exhibits) ?>"
                    data-has-more="<?= $exhibitTotal > count($exhibits) ? 'true' : 'false' ?>"
                    data-fetch-url="/portfolio/exhibits?sort=newest"
                >
                    <div class="portfolio-grid-3" data-listing-grid>
                        <?php $items = $exhibits; $itemType = 'exhibits'; require __DIR__ . '/archive-cards.php'; ?>
                    </div>
                    <div class="portfolio-listing-footer">
                        <p class="portfolio-listing-status" data-listing-status aria-live="polite">
                            Showing <?= count($exhibits) ?> of <?= (int) $exhibitTotal ?>.
                        </p>
                        <?php if ($exhibitTotal > count($exhibits)): ?>
                        <button class="gallery-see-more-btn post-action-btn" data-listing-see-more-btn>
                            See More
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif ?>
        </div>

        <div class="gallery-section" aria-labelledby="gallery-platform-collections-heading">
            <div class="gallery-section-header">
                <div>
                    <h2 class="category-name" id="gallery-platform-collections-heading"><?= e(public_copy_value('portfolio_copy.gallery.sections.platform_collections.heading')) ?></h2>
                    <p class="gallery-section-copy"><?= e(public_copy_value('portfolio_copy.gallery.sections.platform_collections.description')) ?></p>
                </div>
                <a href="/portfolio/platform-collections" class="gallery-section-link"><?= e(public_copy_value('portfolio_copy.gallery.sections.platform_collections.cta_label')) ?></a>
                <span class="section-rule" aria-hidden="true"></span>
            </div>
            <?php if (empty($platformCollections)): ?>
                <p class="gallery-empty"><?= e(public_copy_value('portfolio_copy.gallery.sections.platform_collections.empty_state')) ?></p>
            <?php else: ?>
                <div
                    class="portfolio-archive-listing"
                    data-see-more-listing
                    data-next-offset="<?= count($platformCollections) ?>"
                    data-has-more="<?= $platformCollectionTotal > count($platformCollections) ? 'true' : 'false' ?>"
                    data-fetch-url="/portfolio/platform-collections?sort=newest"
                >
                    <div class="portfolio-grid-3" data-listing-grid>
                        <?php $items = $platformCollections; $itemType = 'platform-collections'; require __DIR__ . '/archive-cards.php'; ?>
                    </div>
                    <div class="portfolio-listing-footer">
                        <p class="portfolio-listing-status" data-listing-status aria-live="polite">
                            Showing <?= count($platformCollections) ?> of <?= (int) $platformCollectionTotal ?>.
                        </p>
                        <?php if ($platformCollectionTotal > count($platformCollections)): ?>
                        <button class="gallery-see-more-btn post-action-btn" data-listing-see-more-btn>
                            See More
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif ?>
        </div>

        <div class="gallery-section" aria-labelledby="gallery-pieces-heading">
            <div class="gallery-section-header">
                <div>
                    <h2 class="category-name" id="gallery-pieces-heading"><?= e(public_copy_value('portfolio_copy.gallery.sections.pieces.heading')) ?></h2>
                    <p class="gallery-section-copy"><?= e(public_copy_value('portfolio_copy.gallery.sections.pieces.description')) ?></p>
                </div>
                <a href="/portfolio/pieces" class="gallery-section-link"><?= e(public_copy_value('portfolio_copy.gallery.sections.pieces.cta_label')) ?></a>
                <span class="section-rule" aria-hidden="true"></span>
            </div>
            <?php if (empty($pieces)): ?>
                <p class="gallery-empty"><?= e(public_copy_value('portfolio_copy.gallery.sections.pieces.empty_state')) ?></p>
            <?php else: ?>
                <div
                    class="portfolio-archive-listing"
                    data-see-more-listing
                    data-next-offset="<?= count($pieces) ?>"
                    data-has-more="<?= $pieceTotal > count($pieces) ? 'true' : 'false' ?>"
                    data-fetch-url="/portfolio/pieces?sort=newest"
                >
                    <div class="portfolio-grid-3" data-listing-grid>
                        <?php $items = $pieces; $itemType = 'pieces'; require __DIR__ . '/archive-cards.php'; ?>
                    </div>
                    <div class="portfolio-listing-footer">
                        <p class="portfolio-listing-status" data-listing-status aria-live="polite">
                            Showing <?= count($pieces) ?> of <?= (int) $pieceTotal ?>.
                        </p>
                        <?php if ($pieceTotal > count($pieces)): ?>
                        <button class="gallery-see-more-btn post-action-btn" data-listing-see-more-btn>
                            See More
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif ?>
        </div>

    </section>
<?php
require __DIR__ . '/../partials/footer.php';
