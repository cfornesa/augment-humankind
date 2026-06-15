<?php

declare(strict_types=1);

require dirname(__DIR__) . '/partials/header.php';
?>
<article class="blog-post">
    <header class="page-hero blog-post-hero">
        <p class="eyebrow">
            <?= e(date('M j, Y', strtotime((string) $post['created_at']) ?: time())) ?>
            <?php if (!empty($post['source_name'])): ?>
                · via <?= e((string) $post['source_name']) ?>
            <?php endif; ?>
        </p>
        <h1><?= e((string) (($post['title'] ?? '') ?: 'Untitled post')) ?></h1>
        <?php if (!empty($post['categories'])): ?>
            <nav class="blog-chips" aria-label="Post categories">
                <?php foreach ($post['categories'] as $category): ?>
                    <a href="/blog/category/<?= e((string) $category['slug']) ?>"><?= e((string) $category['name']) ?></a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
    </header>

    <?php if (!empty($post['featured_image_url'])): ?>
        <figure class="blog-featured-image">
            <img src="<?= e((string) $post['featured_image_url']) ?>" alt="">
        </figure>
    <?php endif; ?>

    <div class="managed-section blog-post-content">
        <div class="managed-section-body">
            <?php if (($post['content_format'] ?? 'plain') === 'html'): ?>
                <?= (string) $post['content'] ?>
            <?php else: ?>
                <?= nl2br(e((string) $post['content'])) ?>
            <?php endif; ?>
        </div>
    </div>

    <footer class="blog-post-footer">
        <p><?= (int) ($post['comment_count'] ?? 0) ?> comments · <?= (int) ($post['reaction_count'] ?? 0) ?> reactions</p>
        <?php if (!empty($post['source_canonical_url'])): ?>
            <p><a href="<?= e((string) $post['source_canonical_url']) ?>" rel="noopener">Original source</a></p>
        <?php endif; ?>
    </footer>

    <section class="blog-comments" aria-labelledby="blog-comments-title">
        <h2 id="blog-comments-title">Comments</h2>
        <?php if (empty($comments)): ?>
            <p class="admin-empty">No comments yet.</p>
        <?php else: ?>
            <?php foreach ($comments as $comment): ?>
                <article class="blog-comment">
                    <header>
                        <strong><?= e((string) $comment['author_name']) ?></strong>
                        <span><?= e(date('M j, Y', strtotime((string) $comment['created_at']) ?: time())) ?></span>
                    </header>
                    <p><?= nl2br(e((string) $comment['content'])) ?></p>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</article>
<script src="/embed.js" defer></script>
<?php
require dirname(__DIR__) . '/partials/footer.php';
