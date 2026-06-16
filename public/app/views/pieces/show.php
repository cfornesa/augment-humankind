<?php

declare(strict_types=1);

require dirname(__DIR__) . '/partials/header.php';

$version = $version ?? null;
$hasCode = $version && (!empty($version['html_code']) || !empty($version['css_code']) || !empty($version['generated_code']));
?>
<section class="page-hero" aria-labelledby="piece-title">
    <p class="eyebrow">Art Piece</p>
    <h1 id="piece-title"><?= e($piece['title'] ?? 'Untitled') ?></h1>
    <?php if (!empty($piece['categories'])): ?>
        <p>
            <?php foreach ($piece['categories'] as $index => $category): ?>
                <?php if ($index > 0): ?> · <?php endif; ?>
                <a href="/portfolio/art-media/<?= e($category['slug']) ?>"><?= e($category['name']) ?></a>
            <?php endforeach; ?>
        </p>
    <?php endif; ?>
    <?php if (!empty($piece['description'])): ?>
        <p><?= e($piece['description']) ?></p>
    <?php endif; ?>
</section>

<section class="piece-stage" aria-label="Generative art piece">
    <?php if ($hasCode): ?>
        <div class="piece-canvas-container">
            <?= piece_render_iframe($piece, $version, 560) ?>
        </div>
        <a href="/immersive/pieces/<?= (int) $piece['id'] ?>?returnTo=<?= rawurlencode($_SERVER['REQUEST_URI'] ?? '') ?>" target="_blank" rel="noopener" class="piece-immersive-link">View in Immersive / VR Mode</a>
    <?php else: ?>
        <div class="piece-placeholder">
            <p>This piece has no rendered version yet.</p>
        </div>
    <?php endif; ?>
</section>

<?php if ($version && !empty($version['prompt'])): ?>
<section class="piece-prompt" aria-labelledby="prompt-title">
    <h2 id="prompt-title">Prompt</h2>
    <pre><?= e($version['prompt']) ?></pre>
</section>
<?php endif; ?>

<?php if (!empty($piece['versions']) && count($piece['versions']) > 1): ?>
<section class="piece-versions" aria-labelledby="versions-title">
    <h2 id="versions-title">Versions</h2>
    <ul>
        <?php foreach ($piece['versions'] as $v): ?>
            <li>
                Version <?= (int) $v['version_number'] ?>
                <?php if ((int) ($piece['current_version_id'] ?? 0) === (int) $v['id']): ?>
                    <strong>(current)</strong>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<?php if (!empty($piece['comments_enabled'])): ?>
<section class="comments-section blog-comments" aria-labelledby="piece-comments-title">
    <h2 id="piece-comments-title">Comments</h2>
    <div class="post-comments-list">
        <?php if (empty($comments)): ?>
            <p class="admin-empty">No comments yet. Be the first.</p>
        <?php else: ?>
            <?php foreach ($comments as $comment): ?>
                <div class="post-comment-item">
                    <strong><?= e($comment['author_name']) ?> · <span style="font-weight:700;color:var(--ink-soft)"><?= e(date('M j, Y', strtotime((string) $comment['created_at']) ?: time())) ?></span></strong>
                    <p style="margin:0"><?= nl2br(e((string) $comment['content'])) ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <form class="post-comment-form"
          data-comment-url="/api/pieces/<?= (int) $piece['id'] ?>/comments">
        <input type="text" name="author_name" placeholder="Your name (optional)" maxlength="80" autocomplete="name">
        <textarea name="content" placeholder="Write a comment…" maxlength="500" required></textarea>
        <input type="text" name="hp_field" class="field-honeypot" tabindex="-1" autocomplete="off" aria-hidden="true">
        <button type="submit" class="post-action-btn">Post comment</button>
    </form>
</section>
<?php endif; ?>
<?php
require dirname(__DIR__) . '/partials/footer.php';
