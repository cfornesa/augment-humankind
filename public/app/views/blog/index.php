<?php

declare(strict_types=1);

require dirname(__DIR__) . '/partials/header.php';
?>
<section class="page-hero blog-hero" aria-labelledby="blog-title">
    <p class="eyebrow">Blog</p>
    <h1 id="blog-title">Field notes, posts, and signals.</h1>
    <p>Published writing and imported notes from the reconciled platform archive.</p>
    <form class="blog-search" action="/search" method="get">
        <label class="sr-only" for="blog-search-q">Search posts</label>
        <input id="blog-search-q" name="q" type="search" placeholder="Search posts">
        <button class="button button-primary" type="submit">Search</button>
    </form>
</section>

<?php if (!empty($categories)): ?>
    <nav class="blog-category-strip" aria-label="Blog categories">
        <a href="/blog/categories">All categories</a>
        <?php foreach ($categories as $category): ?>
            <a href="/blog/category/<?= e((string) $category['slug']) ?>"><?= e((string) $category['name']) ?></a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>

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
