<?php

declare(strict_types=1);

require dirname(__DIR__) . '/partials/header.php';
?>
<section class="page-hero" aria-labelledby="pieces-title">
    <p class="eyebrow">Generative Art</p>
    <h1 id="pieces-title">Art Pieces</h1>
    <p>Creative experiments and generative art works.</p>
</section>

<section class="managed-section">
    <div class="managed-section-body">
        <form class="content-filter-bar" action="/pieces" method="get" role="search">
            <div class="filter-bar-primary">
                <label class="sr-only" for="pieces-q">Search pieces</label>
                <input id="pieces-q" class="filter-search-input" name="q" type="search"
                    value="<?= e($q) ?>" placeholder="Search pieces…" autocomplete="off">
                <button class="button button-primary filter-submit" type="submit">Search</button>
            </div>
            <details class="filter-bar-secondary" <?= ($engine !== '' || $sort !== 'curated') ? 'open' : '' ?>>
                <summary class="filter-toggle">Filters &amp; Sort</summary>
                <div class="filter-bar-options">
                    <fieldset class="filter-fieldset">
                        <legend>Type</legend>
                        <div class="filter-chip-group" role="group">
                            <?php foreach (['' => 'All', 'p5' => 'P5.js', 'c2' => 'C2.js', 'c2_interactive' => 'C2.js Interactive', 'three' => 'Three.js', 'svg' => 'SVG', 'aframe' => 'A-Frame'] as $val => $label): ?>
                                <label class="filter-chip <?= $engine === $val ? 'filter-chip-active' : '' ?>">
                                    <input type="radio" name="engine" value="<?= e($val) ?>"
                                        <?= $engine === $val ? 'checked' : '' ?>
                                        class="sr-only">
                                    <?= e($label) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                    <fieldset class="filter-fieldset">
                        <legend>Sort</legend>
                        <div class="filter-chip-group" role="group">
                            <?php foreach (['curated' => 'Default order', 'newest' => 'Newest first', 'oldest' => 'Oldest first', 'az' => 'A–Z', 'za' => 'Z–A', 'unsorted' => 'Unsorted'] as $val => $label): ?>
                                <label class="filter-chip <?= $sort === $val ? 'filter-chip-active' : '' ?>">
                                    <input type="radio" name="sort" value="<?= e($val) ?>"
                                        <?= $sort === $val ? 'checked' : '' ?>
                                        class="sr-only">
                                    <?= e($label) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                    <?php if ($q !== '' || $engine !== '' || $sort !== 'curated'): ?>
                        <a href="/pieces" class="filter-reset">Clear filters</a>
                    <?php endif; ?>
                </div>
            </details>
        </form>

        <?php if (empty($pieces)): ?>
            <p class="admin-empty">
                <?= $q !== '' || $engine !== '' ? 'No pieces matched your search.' : 'No art pieces published yet.' ?>
            </p>
        <?php else: ?>
            <div data-lazy-listing
                 data-fetch-url="<?= e($fetchUrl) ?>"
                 data-next-offset="<?= (int) $nextOffset ?>"
                 data-has-more="<?= $hasMore ? 'true' : 'false' ?>"
                 data-page-size="<?= PiecesController::PAGE_SIZE ?>">
                <div class="piece-grid" data-listing-grid>
                    <?php foreach ($pieces as $piece): ?>
                        <?php require __DIR__ . '/_piece-card.php'; ?>
                    <?php endforeach; ?>
                </div>
                <div data-listing-sentinel <?= !$hasMore ? 'class="is-hidden"' : '' ?>></div>
                <p data-listing-status class="sr-only" aria-live="polite"></p>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php
require dirname(__DIR__) . '/partials/footer.php';
