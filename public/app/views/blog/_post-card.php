<?php

declare(strict_types=1);

$postUrl = '/blog/posts/' . (int) $post['id'];
$postId  = (int) $post['id'];
$excerpt = seo_excerpt($post['content_text'] ?? $post['content'] ?? '', 240) ?? '';
$isAdmin = (bool) admin_identity();
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

        <nav class="post-actions" aria-label="Post actions">
            <?php if ($isAdmin): ?>
            <a href="/admin/posts/<?= $postId ?>/edit" class="post-action-btn">Edit</a>
            <?php endif; ?>
            <a href="<?= e($postUrl) ?>" class="post-action-btn">Open post</a>
            <button class="post-action-btn post-expand-btn"
                    data-post-id="<?= $postId ?>"
                    aria-expanded="false">Expand</button>
            <button class="post-action-btn post-comments-btn"
                    data-post-id="<?= $postId ?>"
                    aria-expanded="false">Comments (<?= (int) ($post['comment_count'] ?? 0) ?>)</button>
            <button class="post-action-btn post-share-btn"
                    data-title="<?= htmlspecialchars((string) (($post['title'] ?? '') ?: 'Untitled post'), ENT_QUOTES, 'UTF-8') ?>"
                    data-url="<?= e($postUrl) ?>">Share</button>
            <button class="post-action-btn post-embed-btn"
                    data-post-id="<?= $postId ?>">Embed</button>
        </nav>

        <div class="post-expand-panel" id="post-expand-<?= $postId ?>" hidden>
            <div class="post-content-body"></div>
        </div>

        <div class="post-comments-panel" id="post-comments-<?= $postId ?>" hidden>
            <div class="post-comments-list"></div>
            <form class="post-comment-form" data-post-id="<?= $postId ?>">
                <input type="text" name="author_name" placeholder="Your name (optional)" maxlength="80" autocomplete="name">
                <textarea name="content" placeholder="Write a comment…" maxlength="500" required></textarea>
                <input type="text" name="hp_field" class="field-honeypot" tabindex="-1" autocomplete="off" aria-hidden="true">
                <button type="submit" class="post-action-btn">Post comment</button>
            </form>
        </div>
    </div>
</article>
