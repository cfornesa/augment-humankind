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

        <?php if (!empty($showFilterBar)):
            $activeEngine   = $engine ?? '';
            $activeSort     = $sort ?? 'newest';
            $activeQ        = $q ?? '';
            $filtersActive  = $activeQ !== '' || $activeSort !== 'newest' || $activeEngine !== '';
        ?>
        <form class="content-filter-bar" action="<?= e($canonicalPath) ?>" method="get">
            <div class="filter-bar-primary">
                <label class="sr-only" for="archive-filter-q">Search</label>
                <input id="archive-filter-q" name="q" type="search" class="filter-search-input"
                       value="<?= e($activeQ) ?>" placeholder="Search…" autocomplete="off">
                <button class="button button-primary filter-submit" type="submit">Search</button>
            </div>
            <details class="filter-bar-secondary" <?= $filtersActive ? 'open' : '' ?>>
                <summary class="filter-toggle">Filters &amp; Sort</summary>
                <div class="filter-bar-options">
                    <?php if (!empty($showEngineFilter)): ?>
                    <fieldset class="filter-fieldset">
                        <legend>Type</legend>
                        <div class="filter-chip-group" role="group">
                            <?php foreach (['' => 'All', 'p5' => 'P5.js', 'c2' => 'C2.js', 'three' => 'Three.js', 'svg' => 'SVG'] as $v => $l): ?>
                                <label class="filter-chip <?= $activeEngine === $v ? 'filter-chip-active' : '' ?>">
                                    <input type="radio" name="engine" value="<?= $v ?>"
                                           <?= $activeEngine === $v ? 'checked' : '' ?> class="sr-only"
                                           onchange="this.form.submit()">
                                    <?= e($l) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                    <?php endif; ?>
                    <fieldset class="filter-fieldset">
                        <legend>Sort</legend>
                        <div class="filter-chip-group" role="group">
                            <?php foreach (['newest' => 'Newest first', 'oldest' => 'Oldest first', 'az' => 'A–Z', 'za' => 'Z–A'] as $v => $l): ?>
                                <label class="filter-chip <?= $activeSort === $v ? 'filter-chip-active' : '' ?>">
                                    <input type="radio" name="sort" value="<?= $v ?>"
                                           <?= $activeSort === $v ? 'checked' : '' ?> class="sr-only"
                                           onchange="this.form.submit()">
                                    <?= e($l) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                    <?php if ($filtersActive): ?>
                        <a href="<?= e($canonicalPath) ?>" class="filter-reset">Reset</a>
                    <?php endif; ?>
                </div>
            </details>
        </form>
        <?php endif; ?>

        <?php if (empty($items)): ?>
            <p class="gallery-empty">Nothing has been published here yet.</p>
        <?php else: ?>
            <div
                class="portfolio-archive-listing"
                data-lazy-listing
                data-page-size="<?= (int) $limit ?>"
                data-next-offset="<?= (int) $nextOffset ?>"
                data-has-more="<?= $hasMore ? 'true' : 'false' ?>"
                data-fetch-url="<?= e($fetchUrl) ?>"
            >
                <div class="portfolio-grid-3" data-listing-grid>
                    <?php require __DIR__ . '/archive-cards.php'; ?>
                </div>
                <div class="portfolio-listing-footer">
                    <p class="portfolio-listing-status sr-only" data-listing-status aria-live="polite"></p>
                    <div class="portfolio-listing-sentinel<?= $hasMore ? '' : ' is-hidden' ?>" data-listing-sentinel aria-hidden="true"></div>
                </div>
            </div>
        <?php endif ?>
    </section>
<?php
require __DIR__ . '/../partials/footer.php';
