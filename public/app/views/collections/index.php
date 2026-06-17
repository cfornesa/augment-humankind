<?php

declare(strict_types=1);

require dirname(__DIR__) . '/partials/header.php';
?>
<section class="page-hero" aria-labelledby="collections-title">
    <p class="eyebrow">Curated Collections</p>
    <h1 id="collections-title">Collections</h1>
    <p>Curated collections of generative art pieces and images, viewable in an immersive 3D gallery.</p>
</section>

<section class="managed-section">
    <div class="managed-section-body">
        <form class="content-filter-bar" action="/collections" method="get" role="search">
            <div class="filter-bar-primary">
                <label class="sr-only" for="collections-q">Search collections</label>
                <input id="collections-q" class="filter-search-input" name="q" type="search"
                    value="<?= e($q) ?>" placeholder="Search collections…" autocomplete="off">
                <button class="button button-primary filter-submit" type="submit">Search</button>
                <?php if ($q !== '' || $sort !== 'newest'): ?>
                    <a href="/collections" class="filter-reset">Clear</a>
                <?php endif; ?>
            </div>
            <details class="filter-bar-secondary" <?= $sort !== 'newest' ? 'open' : '' ?>>
                <summary class="filter-toggle">Sort</summary>
                <div class="filter-bar-options">
                    <fieldset class="filter-fieldset">
                        <legend>Sort</legend>
                        <div class="filter-chip-group" role="group">
                            <?php foreach (['newest' => 'Newest first', 'oldest' => 'Oldest first', 'az' => 'A–Z', 'za' => 'Z–A'] as $val => $label): ?>
                                <label class="filter-chip <?= $sort === $val ? 'filter-chip-active' : '' ?>">
                                    <input type="radio" name="sort" value="<?= e($val) ?>"
                                        <?= $sort === $val ? 'checked' : '' ?>
                                        class="sr-only">
                                    <?= e($label) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                </div>
            </details>
        </form>

        <?php if (empty($collections)): ?>
            <p class="admin-empty">
                <?= $q !== '' ? 'No collections matched your search.' : 'No collections published yet.' ?>
            </p>
        <?php else: ?>
            <div data-lazy-listing
                 data-fetch-url="<?= e($fetchUrl) ?>"
                 data-next-offset="<?= (int) $nextOffset ?>"
                 data-has-more="<?= $hasMore ? 'true' : 'false' ?>"
                 data-page-size="<?= CollectionsController::PAGE_SIZE ?>">
                <div class="piece-grid" data-listing-grid>
                    <?php foreach ($collections as $collection): ?>
                        <?php require __DIR__ . '/_collection-card.php'; ?>
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
