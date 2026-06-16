<?php

declare(strict_types=1);

$postUrl = '/blog/posts/' . (int) $post['id'];
$postId  = (int) $post['id'];
$excerpt = seo_excerpt($post['content_text'] ?? $post['content'] ?? '', 240) ?? '';
$isAdmin = (bool) admin_identity();
$postTitle = htmlspecialchars((string) (($post['title'] ?? '') ?: 'Untitled post'), ENT_QUOTES, 'UTF-8');
?>
<article class="blog-card">
    <?php if (!empty($post['featured_image_url'])): ?>
        <a href="<?= e($postUrl) ?>" class="blog-card-image">
            <img src="<?= e((string) $post['featured_image_url']) ?>" alt="">
        </a>
    <?php endif; ?>
    <div class="blog-card-body">
        <div class="blog-card-header">
            <div class="blog-card-header-left">
                <p class="eyebrow">
                    <?= e(date('M j, Y', strtotime((string) $post['created_at']) ?: time())) ?>
                    <?php if (!empty($post['source_name'])): ?>
                        · via <?= e((string) $post['source_name']) ?>
                    <?php endif; ?>
                </p>
                <h2><a href="<?= e($postUrl) ?>"><?= e((string) (($post['title'] ?? '') ?: 'Untitled post')) ?></a></h2>
            </div>
            <div class="blog-card-header-right">
                <?php if ($isAdmin): ?>
                <a href="/admin/posts/<?= $postId ?>/edit" class="post-action-btn edit-btn" aria-label="Edit post">
                    <?= icon('pencil') ?><span class="btn-label">Edit</span>
                </a>
                <?php endif; ?>
                <button class="post-action-btn post-expand-btn"
                        data-post-id="<?= $postId ?>"
                        data-collapsed-label="Expand"
                        data-expanded-label="Collapse"
                        data-collapsed-aria-label="Expand post"
                        data-expanded-aria-label="Collapse inline post"
                        data-collapsed-icon="<?= e(icon('maximize')) ?>"
                        data-expanded-icon="<?= e(icon('minimize')) ?>"
                        aria-expanded="false"
                        aria-controls="post-expand-<?= $postId ?>"
                        aria-label="Expand post">
                    <span class="post-action-icon" aria-hidden="true"><?= icon('maximize') ?></span><span class="btn-label">Expand</span>
                </button>
            </div>
        </div>
        <div class="blog-card-preview">
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

        <div class="post-expand-panel" id="post-expand-<?= $postId ?>" hidden>
            <div class="post-content-body"></div>
        </div>

        <div class="post-actions-bottom">
            <div class="post-actions-left">
                <button class="post-action-btn post-comments-btn"
                        data-post-id="<?= $postId ?>"
                        aria-expanded="false"
                        aria-controls="post-comments-<?= $postId ?>"
                        aria-label="Toggle comments">
                    <?= icon('message-circle') ?><span class="btn-label">Comments (<?= (int) ($post['comment_count'] ?? 0) ?>)</span>
                </button>
                <a href="<?= e($postUrl) ?>" class="post-action-btn" aria-label="Open full post">
                    <?= icon('external-link') ?><span class="btn-label">Open post</span>
                </a>
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

        <div class="post-comments-panel" id="post-comments-<?= $postId ?>" hidden>
            <?php
            $comments = [];
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
    </div>
</article>
