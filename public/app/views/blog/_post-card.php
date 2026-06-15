<?php

declare(strict_types=1);

$postUrl = '/blog/posts/' . (int) $post['id'];
$excerpt = seo_excerpt($post['content_text'] ?? $post['content'] ?? '', 240) ?? '';
?>
<article class="blog-card">
    <?php if (!empty($post['featured_image_url'])): ?>
        <a href="<?= e($postUrl) ?>" class="blog-card-image">
            <img src="<?= e((string) $post['featured_image_url']) ?>" alt="">
        </a>
    <?php endif; ?>
    <div class="blog-card-body">
        <p class="eyebrow">
            <?= e(date('M j, Y', strtotime((string) $post['created_at']) ?: time())) ?>
            <?php if (!empty($post['source_name'])): ?>
                · via <?= e((string) $post['source_name']) ?>
            <?php endif; ?>
        </p>
        <h2><a href="<?= e($postUrl) ?>"><?= e((string) (($post['title'] ?? '') ?: 'Untitled post')) ?></a></h2>
        <?php if ($excerpt !== ''): ?>
            <p><?= e($excerpt) ?></p>
        <?php endif; ?>
        <?php if (!empty($post['categories'])): ?>
            <nav class="blog-chips" aria-label="Post categories">
                <?php foreach ($post['categories'] as $category): ?>
                    <a href="/blog/category/<?= e((string) $category['slug']) ?>"><?= e((string) $category['name']) ?></a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
        <p class="blog-meta">
            <?= (int) ($post['comment_count'] ?? 0) ?> comments ·
            <?= (int) ($post['reaction_count'] ?? 0) ?> reactions
        </p>
    </div>
</article>
