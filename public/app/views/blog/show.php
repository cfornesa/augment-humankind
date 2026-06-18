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
        <div class="blog-hero-header-row">
            <div class="blog-hero-header-left">
                <p class="eyebrow">
                    <?= e(date('M j, Y', strtotime((string) $post['created_at']) ?: time())) ?>
                    <?php if (!empty($post['source_name'])): ?>
                        · via <?= e((string) $post['source_name']) ?>
                    <?php endif; ?>
                </p>
                <h1><?= e((string) (($post['title'] ?? '') ?: 'Untitled post')) ?></h1>
            </div>
            <?php if ($isAdmin): ?>
            <div class="blog-hero-header-right">
                <a href="/admin/posts/<?= $postId ?>/edit" class="post-action-btn edit-btn" aria-label="Edit post">
                    <?= icon('pencil') ?><span class="btn-label">Edit</span>
                </a>
            </div>
            <?php endif; ?>
        </div>
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

    <?php if (!empty($postSections)): ?>
        <?php foreach ($postSections as $section): ?>
            <?php if ($section['wrapper_class']): ?>
                <section class="<?= e($section['wrapper_class']) ?>"<?= $section['heading'] ? ' aria-labelledby="post-section-' . (int)$section['id'] . '"' : '' ?>>
                    <?php if ($section['heading']): ?><h2 id="post-section-<?= (int)$section['id'] ?>"><?= e($section['heading']) ?></h2><?php endif; ?>
                    <?= $section['content'] ?>
                </section>
            <?php elseif ($section['heading']): ?>
                <section class="managed-section" aria-labelledby="post-section-<?= (int)$section['id'] ?>">
                    <h2 id="post-section-<?= (int)$section['id'] ?>"><?= e($section['heading']) ?></h2>
                    <div class="managed-section-body"><?= $section['content'] ?></div>
                </section>
            <?php else: ?>
                <div class="managed-section blog-post-content">
                    <div class="managed-section-body"><?= $section['content'] ?></div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php elseif (!empty($post['content'])): ?>
        <div class="managed-section blog-post-content">
            <div class="managed-section-body">
                <?php if (($post['content_format'] ?? 'plain') === 'html'): ?>
                    <?= (string) $post['content'] ?>
                <?php else: ?>
                    <?= nl2br(e((string) $post['content'])) ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="post-actions-bottom">
        <div class="post-actions-left">
            <!-- Left side empty to mirror card layout structure -->
        </div>
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

    <footer class="blog-post-footer">
        <p><?= (int) ($post['comment_count'] ?? 0) ?> comments · <?= (int) ($post['reaction_count'] ?? 0) ?> reactions</p>
        <?php if (!empty($post['source_canonical_url'])): ?>
            <p><a href="<?= e((string) $post['source_canonical_url']) ?>" rel="noopener">Original source</a></p>
        <?php endif; ?>
    </footer>

    <section class="blog-comments" aria-labelledby="blog-comments-title">
        <h2 id="blog-comments-title">Comments</h2>
        <div id="post-comments-<?= $postId ?>">
            <?php
            $commentsUrl = '/api/posts/' . $postId . '/comments';
            $emptyCommentMessage = 'No comments yet.';
            require dirname(__DIR__) . '/partials/comment-list.php';
            ?>
            <?php
            $commentUrl = $commentsUrl;
            $signinRedirect = $_SERVER['REQUEST_URI'] ?? $postUrl;
            ?>
            <?php require __DIR__ . '/_comment-form.php'; ?>
        </div>
    </section>
</article>
<script src="/embed.js" defer></script>
<?php
require dirname(__DIR__) . '/partials/footer.php';
