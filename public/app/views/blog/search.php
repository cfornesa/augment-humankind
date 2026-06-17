<?php

declare(strict_types=1);

require dirname(__DIR__) . '/partials/header.php';

$typeLabels = [
    ''                   => 'All',
    'posts'              => 'Posts',
    'pieces'             => 'Pieces',
    'platform-collections' => 'Platform Collections',
    'collections'        => 'Exhibit Collections',
    'exhibits'           => 'Exhibits',
    'pages'              => 'Pages',
];

$hasAnyResults = !empty($posts)
    || !empty($pieces)
    || !empty($platformCollections)
    || !empty($exhibitCollections)
    || !empty($exhibits)
    || !empty($pages);
?>
<section class="page-hero" aria-labelledby="search-title">
    <p class="eyebrow">Search</p>
    <h1 id="search-title">Search</h1>
    <form class="blog-search" action="/search" method="get" role="search">
        <label class="sr-only" for="search-q">Search site</label>
        <input id="search-q" name="q" type="search" value="<?= e($query) ?>" placeholder="Search posts, pieces, collections…">
        <?php if ($type !== ''): ?><input type="hidden" name="type" value="<?= e($type) ?>"><?php endif; ?>
        <?php if ($sort !== 'newest'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>
        <button class="button button-primary" type="submit">Search</button>
    </form>
</section>

<?php if ($query !== ''): ?>
<div class="search-controls">
    <nav class="search-type-strip" aria-label="Filter by content type">
        <?php foreach ($typeLabels as $val => $label): ?>
            <a href="/search?q=<?= urlencode($query) ?><?= $val !== '' ? '&type=' . urlencode($val) : '' ?><?= $sort !== 'newest' ? '&sort=' . urlencode($sort) : '' ?>"
               class="search-type-chip <?= $type === $val ? 'search-type-chip-active' : '' ?>">
                <?= e($label) ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="search-sort-row">
        <span class="blog-sort-label">Sort:</span>
        <a href="/search?q=<?= urlencode($query) ?><?= $type !== '' ? '&type=' . urlencode($type) : '' ?>&sort=newest"
           class="blog-sort-link <?= $sort === 'newest' ? 'blog-sort-active' : '' ?>">Newest first</a>
        <a href="/search?q=<?= urlencode($query) ?><?= $type !== '' ? '&type=' . urlencode($type) : '' ?>&sort=relevance"
           class="blog-sort-link <?= $sort === 'relevance' ? 'blog-sort-active' : '' ?>">Relevance</a>
    </div>
</div>
<?php endif; ?>

<section class="search-results" aria-label="Search results">
    <?php if ($query === ''): ?>
        <p class="admin-empty">Enter a search term above.</p>

    <?php elseif (!$hasAnyResults): ?>
        <p class="admin-empty">No results matched <strong><?= e($query) ?></strong>.</p>

    <?php else: ?>

        <?php if (!empty($posts)): ?>
        <div class="search-result-section">
            <h2 class="search-result-heading">Posts <span class="search-result-count"><?= count($posts) ?></span></h2>
            <?php foreach ($posts as $post): ?>
                <article class="search-result-item">
                    <a href="/blog/posts/<?= (int) $post['id'] ?>" class="search-result-title"><?= e($post['title'] ?? 'Untitled') ?></a>
                    <p class="search-result-excerpt"><?= e(seo_excerpt($post['content_text'] ?? $post['content'] ?? '', 140) ?? '') ?></p>
                    <span class="search-result-meta">Post &middot; <?= e(substr((string)($post['created_at'] ?? ''), 0, 10)) ?></span>
                </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($pieces)): ?>
        <div class="search-result-section">
            <h2 class="search-result-heading">Art Pieces <span class="search-result-count"><?= count($pieces) ?></span></h2>
            <?php foreach ($pieces as $piece): ?>
                <article class="search-result-item">
                    <a href="/pieces/<?= (int) $piece['id'] ?>" class="search-result-title"><?= e($piece['title'] ?? 'Untitled') ?></a>
                    <p class="search-result-excerpt"><?= e(seo_excerpt($piece['description'] ?? '', 140) ?? '') ?></p>
                    <span class="search-result-meta">Piece &middot; <?= e(strtoupper((string)($piece['engine'] ?? ''))) ?></span>
                </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($platformCollections)): ?>
        <div class="search-result-section">
            <h2 class="search-result-heading">Platform Collections <span class="search-result-count"><?= count($platformCollections) ?></span></h2>
            <?php foreach ($platformCollections as $col): ?>
                <article class="search-result-item">
                    <a href="/collections/<?= e($col['slug']) ?>" class="search-result-title"><?= e($col['name'] ?? 'Untitled') ?></a>
                    <p class="search-result-excerpt"><?= e(seo_excerpt($col['description'] ?? '', 140) ?? '') ?></p>
                    <span class="search-result-meta">Platform Collection</span>
                </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($exhibitCollections)): ?>
        <div class="search-result-section">
            <h2 class="search-result-heading">Exhibit Collections <span class="search-result-count"><?= count($exhibitCollections) ?></span></h2>
            <?php foreach ($exhibitCollections as $col): ?>
                <article class="search-result-item">
                    <a href="/portfolio/collection/<?= e($col['slug']) ?>" class="search-result-title"><?= e($col['name'] ?? 'Untitled') ?></a>
                    <p class="search-result-excerpt"><?= e(seo_excerpt($col['description'] ?? '', 140) ?? '') ?></p>
                    <span class="search-result-meta">Exhibit Collection</span>
                </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($exhibits)): ?>
        <div class="search-result-section">
            <h2 class="search-result-heading">Exhibits <span class="search-result-count"><?= count($exhibits) ?></span></h2>
            <?php foreach ($exhibits as $exhibit): ?>
                <article class="search-result-item">
                    <a href="/portfolio/exhibit/<?= e($exhibit['slug']) ?>" class="search-result-title"><?= e($exhibit['title'] ?? 'Untitled') ?></a>
                    <p class="search-result-excerpt"><?= e(seo_excerpt($exhibit['description'] ?? '', 140) ?? '') ?></p>
                    <span class="search-result-meta">Exhibit &middot; <?= e($exhibit['medium'] ?? '') ?></span>
                </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($pages)): ?>
        <div class="search-result-section">
            <h2 class="search-result-heading">Pages <span class="search-result-count"><?= count($pages) ?></span></h2>
            <?php foreach ($pages as $page): ?>
                <article class="search-result-item">
                    <a href="/<?= e($page['slug']) ?>" class="search-result-title"><?= e($page['title'] ?? 'Untitled') ?></a>
                    <p class="search-result-excerpt"><?= e($page['meta_description'] ?? '') ?></p>
                    <span class="search-result-meta">Page</span>
                </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</section>
<?php
require dirname(__DIR__) . '/partials/footer.php';
