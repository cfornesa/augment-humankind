<?php

declare(strict_types=1);

$pageTitle = 'Portfolio Gallery | Augment Humankind';
$pageDescription = 'Browse exhibits, exhibit collections, platform collections, and generative pieces from the Augment Humankind portfolio.';
$ogImage = $collections[0]['preview_image'] ?? ($exhibits[0]['preview_image'] ?? null);
$canonicalUrl = seo_absolute_url('/portfolio');
$bodyClass = bodyClass('portfolio-gallery');

require __DIR__ . '/../partials/header.php';
?>
    <section class="gallery-page">
        <div class="gallery-intro">
            <p class="eyebrow">Portfolio</p>
            <h1>Gallery</h1>
            <p>The gallery is now a sampler. Each section links to a dedicated archive page that keeps loading more work as you scroll.</p>
        </div>

        <div class="gallery-section" aria-labelledby="gallery-collections-heading">
            <div class="gallery-section-header">
                <div>
                    <h2 class="category-name" id="gallery-collections-heading">Exhibit Collections</h2>
                    <p class="gallery-section-copy">Native exhibit collections built from related exhibits.</p>
                </div>
                <a href="/portfolio/exhibit-collections" class="gallery-section-link">Browse all exhibit collections</a>
                <span class="section-rule" aria-hidden="true"></span>
            </div>
            <?php if (empty($collections)): ?>
                <p class="gallery-empty">No exhibit collections have been added yet.</p>
            <?php else: ?>
                <div
                    class="portfolio-archive-listing"
                    data-lazy-listing
                    data-page-size="<?= count($collections) ?>"
                    data-next-offset="<?= count($collections) ?>"
                    data-has-more="<?= $collectionTotal > count($collections) ? 'true' : 'false' ?>"
                    data-fetch-url="/portfolio/exhibit-collections"
                >
                    <div class="portfolio-grid-3" data-listing-grid>
                        <?php $items = $collections; $itemType = 'collections'; require __DIR__ . '/archive-cards.php'; ?>
                    </div>
                    <div class="portfolio-listing-footer">
                        <p class="portfolio-listing-status" data-listing-status aria-live="polite">
                            Showing <?= count($collections) ?> of <?= (int) $collectionTotal ?>.
                        </p>
                        <div class="portfolio-listing-sentinel<?= $collectionTotal > count($collections) ? '' : ' is-hidden' ?>" data-listing-sentinel aria-hidden="true"></div>
                    </div>
                </div>
            <?php endif ?>
        </div>

        <div class="gallery-section" aria-labelledby="gallery-exhibits-heading">
            <div class="gallery-section-header">
                <div>
                    <h2 class="category-name" id="gallery-exhibits-heading">Exhibits</h2>
                    <p class="gallery-section-copy">Individual exhibits with their own carousel, placard, and context.</p>
                </div>
                <a href="/portfolio/exhibits" class="gallery-section-link">Browse all exhibits</a>
                <span class="section-rule" aria-hidden="true"></span>
            </div>

            <?php if (empty($exhibits)): ?>
                <p class="gallery-empty">No exhibits have been added yet.</p>
            <?php else: ?>
                <div
                    class="portfolio-archive-listing"
                    data-lazy-listing
                    data-page-size="<?= count($exhibits) ?>"
                    data-next-offset="<?= count($exhibits) ?>"
                    data-has-more="<?= $exhibitTotal > count($exhibits) ? 'true' : 'false' ?>"
                    data-fetch-url="/portfolio/exhibits"
                >
                    <div class="portfolio-grid-3" data-listing-grid>
                        <?php $items = $exhibits; $itemType = 'exhibits'; require __DIR__ . '/archive-cards.php'; ?>
                    </div>
                    <div class="portfolio-listing-footer">
                        <p class="portfolio-listing-status" data-listing-status aria-live="polite">
                            Showing <?= count($exhibits) ?> of <?= (int) $exhibitTotal ?>.
                        </p>
                        <div class="portfolio-listing-sentinel<?= $exhibitTotal > count($exhibits) ? '' : ' is-hidden' ?>" data-listing-sentinel aria-hidden="true"></div>
                    </div>
                </div>
            <?php endif ?>
        </div>

        <?php if (!empty($platformCollections)): ?>
            <div class="gallery-section" aria-labelledby="gallery-platform-collections-heading">
                <div class="gallery-section-header">
                    <div>
                        <h2 class="category-name" id="gallery-platform-collections-heading">Platform Collections</h2>
                        <p class="gallery-section-copy">Migrated platform-native collections with public detail pages and immersive room views.</p>
                    </div>
                    <a href="/portfolio/platform-collections" class="gallery-section-link">Browse all platform collections</a>
                    <span class="section-rule" aria-hidden="true"></span>
                </div>
                <div
                    class="portfolio-archive-listing"
                    data-lazy-listing
                    data-page-size="<?= count($platformCollections) ?>"
                    data-next-offset="<?= count($platformCollections) ?>"
                    data-has-more="<?= $platformCollectionTotal > count($platformCollections) ? 'true' : 'false' ?>"
                    data-fetch-url="/portfolio/platform-collections"
                >
                    <div class="portfolio-grid-3" data-listing-grid>
                        <?php $items = $platformCollections; $itemType = 'platform-collections'; require __DIR__ . '/archive-cards.php'; ?>
                    </div>
                    <div class="portfolio-listing-footer">
                        <p class="portfolio-listing-status" data-listing-status aria-live="polite">
                            Showing <?= count($platformCollections) ?> of <?= (int) $platformCollectionTotal ?>.
                        </p>
                        <div class="portfolio-listing-sentinel<?= $platformCollectionTotal > count($platformCollections) ? '' : ' is-hidden' ?>" data-listing-sentinel aria-hidden="true"></div>
                    </div>
                </div>
            </div>
        <?php endif ?>

        <?php if (!empty($pieces)): ?>
            <div class="gallery-section" aria-labelledby="gallery-pieces-heading">
                <div class="gallery-section-header">
                    <div>
                        <h2 class="category-name" id="gallery-pieces-heading">Art Pieces</h2>
                        <p class="gallery-section-copy">Migrated generative pieces, code-driven experiments, and runtime sketches.</p>
                    </div>
                    <a href="/portfolio/pieces" class="gallery-section-link">Browse all art pieces</a>
                    <span class="section-rule" aria-hidden="true"></span>
                </div>
                <div
                    class="portfolio-archive-listing"
                    data-lazy-listing
                    data-page-size="<?= count($pieces) ?>"
                    data-next-offset="<?= count($pieces) ?>"
                    data-has-more="<?= $pieceTotal > count($pieces) ? 'true' : 'false' ?>"
                    data-fetch-url="/portfolio/pieces"
                >
                    <div class="portfolio-grid-3" data-listing-grid>
                        <?php $items = $pieces; $itemType = 'pieces'; require __DIR__ . '/archive-cards.php'; ?>
                    </div>
                    <div class="portfolio-listing-footer">
                        <p class="portfolio-listing-status" data-listing-status aria-live="polite">
                            Showing <?= count($pieces) ?> of <?= (int) $pieceTotal ?>.
                        </p>
                        <div class="portfolio-listing-sentinel<?= $pieceTotal > count($pieces) ? '' : ' is-hidden' ?>" data-listing-sentinel aria-hidden="true"></div>
                    </div>
                </div>
            </div>
        <?php endif ?>

    </section>
<?php
require __DIR__ . '/../partials/footer.php';
