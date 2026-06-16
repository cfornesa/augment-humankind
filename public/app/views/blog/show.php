<?php

declare(strict_types=1);

$postId    = (int) $post['id'];
$postTitle = htmlspecialchars((string) (($post['title'] ?? '') ?: 'Untitled post'), ENT_QUOTES, 'UTF-8');
$postUrl   = '/blog/posts/' . $postId;
$isAdmin   = (bool) admin_identity();

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

        <div class="post-actions post-actions-show">
            <?php if ($isAdmin): ?>
            <a href="/admin/posts/<?= $postId ?>/edit" class="post-action-btn" aria-label="Edit post">
                <?= icon('pencil') ?><span class="btn-label">Edit</span>
            </a>
            <?php endif; ?>
            <div class="post-actions-right">
                <button class="post-action-btn post-embed-btn"
                        data-post-id="<?= $postId ?>"
                        aria-label="Copy embed code">
                    <?= icon('code') ?><span class="btn-label">Embed</span>
                </button>
                <button class="post-action-btn post-share-btn"
                        data-title="<?= $postTitle ?>"
                        data-url="<?= e($postUrl) ?>"
                        aria-label="Share post">
                    <?= icon('share-2') ?><span class="btn-label">Share</span>
                </button>
            </div>
        </div>
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
        <div id="post-comments-<?= $postId ?>">
            <div class="post-comments-list">
                <?php if (empty($comments)): ?>
                    <p class="admin-empty">No comments yet.</p>
                <?php else: ?>
                    <?php foreach ($comments as $comment): ?>
                        <div class="post-comment-item">
                            <strong><?= e((string) $comment['author_name']) ?> · <span style="font-weight:700;color:var(--ink-soft)"><?= e(date('M j, Y', strtotime((string) $comment['created_at']) ?: time())) ?></span></strong>
                            <p style="margin:0"><?= nl2br(e((string) $comment['content'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php require __DIR__ . '/_comment-form.php'; ?>
        </div>
    </section>
</article>
<script src="/embed.js" defer></script>
<?php
require dirname(__DIR__) . '/partials/footer.php';
