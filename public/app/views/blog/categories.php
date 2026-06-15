<?php

declare(strict_types=1);

require dirname(__DIR__) . '/partials/header.php';
?>
<section class="page-hero" aria-labelledby="blog-categories-title">
    <p class="eyebrow">Blog</p>
    <h1 id="blog-categories-title">Categories</h1>
</section>

<section class="blog-category-grid" aria-label="Blog categories">
    <?php if (empty($categories)): ?>
        <p class="admin-empty">No blog categories have been migrated yet.</p>
    <?php else: ?>
        <?php foreach ($categories as $category): ?>
            <article class="blog-category-card">
                <h2><a href="/blog/category/<?= e((string) $category['slug']) ?>"><?= e((string) $category['name']) ?></a></h2>
                <?php if (!empty($category['description'])): ?>
                    <p><?= e((string) $category['description']) ?></p>
                <?php endif; ?>
                <p class="blog-meta"><?= (int) $category['published_post_count'] ?> posts</p>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
<?php
require dirname(__DIR__) . '/partials/footer.php';
