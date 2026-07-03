<?php

declare(strict_types=1);

$_blogQ       = (string) ($_GET['q'] ?? '');
$_blogCat     = $activeCat ?? '';
$_blogSort    = $sort ?? 'newest';
$_blogFilters = $_blogQ !== '' || $_blogCat !== '' || $_blogSort !== 'newest';

require dirname(__DIR__) . '/partials/header.php';
?>
<section class="page-hero blog-hero" aria-labelledby="blog-title">
    <p class="eyebrow">Blog</p>
    <h1 id="blog-title">Field notes, posts, and signals.</h1>
    <p>Published writing and imported notes from the reconciled platform archive.</p>
</section>

<form class="content-filter-bar" action="/blog" method="get" role="search">
    <div class="filter-bar-primary">
        <label class="sr-only" for="blog-filter-q">Search posts</label>
        <input id="blog-filter-q" name="q" type="search" class="filter-search-input"
               value="<?= e($_blogQ) ?>" placeholder="Search posts…" autocomplete="off">
        <button class="button button-primary filter-submit" type="submit">Search</button>
    </div>
    <details class="filter-bar-secondary" <?= $_blogFilters ? 'open' : '' ?>>
        <summary class="filter-toggle">Filters &amp; Sort</summary>
        <div class="filter-bar-options">
            <?php if (!empty($categories)): ?>
            <fieldset class="filter-fieldset">
                <legend>Category</legend>
                <div class="filter-chip-group" role="group">
                    <label class="filter-chip <?= $_blogCat === '' ? 'filter-chip-active' : '' ?>">
                        <input type="radio" name="cat" value=""
                               <?= $_blogCat === '' ? 'checked' : '' ?> class="sr-only"
                               onchange="this.form.submit()">
                        All
                    </label>
                    <?php foreach ($categories as $blogCatItem): ?>
                        <label class="filter-chip <?= $_blogCat === $blogCatItem['slug'] ? 'filter-chip-active' : '' ?>">
                            <input type="radio" name="cat" value="<?= e((string) $blogCatItem['slug']) ?>"
                                   <?= $_blogCat === $blogCatItem['slug'] ? 'checked' : '' ?> class="sr-only"
                                   onchange="this.form.submit()">
                            <?= e((string) $blogCatItem['name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <?php endif; ?>
            <fieldset class="filter-fieldset">
                <legend>Sort</legend>
                <div class="filter-chip-group" role="group">
                    <?php
                        $_blogSortOptions = ['newest' => 'Newest first', 'oldest' => 'Oldest first'];
                        if ($_blogQ !== '') {
                            $_blogSortOptions = ['relevance' => 'Relevance'] + $_blogSortOptions;
                        }
                    ?>
                    <?php foreach ($_blogSortOptions as $v => $l): ?>
                        <label class="filter-chip <?= $_blogSort === $v ? 'filter-chip-active' : '' ?>">
                            <input type="radio" name="sort" value="<?= $v ?>"
                                   <?= $_blogSort === $v ? 'checked' : '' ?> class="sr-only"
                                   onchange="this.form.submit()">
                            <?= e($l) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <?php if ($_blogFilters): ?>
                <a href="/blog" class="filter-reset">Reset</a>
            <?php endif; ?>
        </div>
    </details>
</form>

<section class="blog-list" aria-label="Published posts">
    <?php if (empty($posts)): ?>
        <p class="admin-empty">No published blog posts have been migrated yet.</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <?php require __DIR__ . '/_post-card.php'; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
<?php
require dirname(__DIR__) . '/partials/footer.php';
