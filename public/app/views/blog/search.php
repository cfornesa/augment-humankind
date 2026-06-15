<?php

declare(strict_types=1);

require dirname(__DIR__) . '/partials/header.php';
?>
<section class="page-hero" aria-labelledby="search-title">
    <p class="eyebrow">Search</p>
    <h1 id="search-title">Search published posts</h1>
    <form class="blog-search" action="/search" method="get">
        <label class="sr-only" for="search-q">Search posts</label>
        <input id="search-q" name="q" type="search" value="<?= e($query) ?>" placeholder="Search posts">
        <button class="button button-primary" type="submit">Search</button>
    </form>
</section>

<section class="blog-list" aria-label="Search results">
    <?php if ($query === ''): ?>
        <p class="admin-empty">Enter a search term.</p>
    <?php elseif (empty($posts)): ?>
        <p class="admin-empty">No published posts matched that search.</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <?php require __DIR__ . '/_post-card.php'; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
<?php
require dirname(__DIR__) . '/partials/footer.php';
