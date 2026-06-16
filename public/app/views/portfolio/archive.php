<?php

declare(strict_types=1);

$ogImage = $items[0]['preview_image']
    ?? ($items[0]['thumbnail_url'] ?? null)
    ?? ($items[0]['thumbnail_value'] ?? null)
    ?? null;

require __DIR__ . '/../partials/header.php';
?>
    <section class="gallery-page portfolio-archive-page">
        <div class="gallery-intro">
            <p class="eyebrow"><?= e($eyebrow) ?></p>
            <h1><?= e($heading) ?></h1>
            <p><?= e($intro) ?></p>
        </div>

        <?php if (empty($items)): ?>
            <p class="gallery-empty">Nothing has been published here yet.</p>
        <?php else: ?>
            <div
                class="portfolio-archive-listing"
                data-lazy-listing
                data-page-size="<?= (int) $limit ?>"
                data-next-offset="<?= (int) $nextOffset ?>"
                data-has-more="<?= $hasMore ? 'true' : 'false' ?>"
                data-fetch-url="<?= e($canonicalPath) ?>"
            >
                <div class="portfolio-grid-3" data-listing-grid>
                    <?php require __DIR__ . '/archive-cards.php'; ?>
                </div>
                <div class="portfolio-listing-footer">
                    <p class="portfolio-listing-status" data-listing-status aria-live="polite">
                        Showing <?= count($items) ?> of <?= (int) $total ?>.
                    </p>
                    <div class="portfolio-listing-sentinel<?= $hasMore ? '' : ' is-hidden' ?>" data-listing-sentinel aria-hidden="true"></div>
                </div>
            </div>
        <?php endif ?>
    </section>
<?php
require __DIR__ . '/../partials/footer.php';
