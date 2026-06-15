<?php

declare(strict_types=1);

require dirname(__DIR__) . '/partials/header.php';
?>
<section class="page-hero" aria-labelledby="blog-category-title">
    <p class="eyebrow">Blog category</p>
    <h1 id="blog-category-title"><?= e((string) $category['name']) ?></h1>
    <?php if (!empty($category['description'])): ?>
        <p><?= e((string) $category['description']) ?></p>
    <?php endif; ?>
</section>

<section class="blog-list" aria-label="Published posts in this category">
    <?php if (empty($posts)): ?>
        <p class="admin-empty">No published posts are in this category yet.</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <?php require __DIR__ . '/_post-card.php'; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
<?php
require dirname(__DIR__) . '/partials/footer.php';
