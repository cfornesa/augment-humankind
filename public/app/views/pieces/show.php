<?php

declare(strict_types=1);

require dirname(__DIR__) . '/partials/header.php';

$version = $version ?? null;
$hasCode = $version && (!empty($version['html_code']) || !empty($version['css_code']) || !empty($version['generated_code']));
$engineLabel = match (strtolower((string) ($version['engine'] ?? $piece['engine'] ?? 'p5'))) {
    'p5' => 'P5.js',
    'c2' => 'C2.js',
    'three' => 'Three.js',
    'svg' => 'SVG',
    'aframe' => 'A-Frame',
    default => strtoupper((string) ($version['engine'] ?? $piece['engine'] ?? '')),
};
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

<?php $status = $piece['status'] ?? 'active'; require dirname(__DIR__) . '/partials/status-banner.php'; ?>

<section class="piece-stage" aria-label="Generative art piece">
    <?php if ($hasCode): ?>
        <div class="piece-canvas-container">
            <?= piece_render_iframe($piece, $version, 560) ?>
        </div>
        <div class="piece-action-row">
            <a href="/immersive/pieces/<?= (int) $piece['id'] ?>?returnTo=<?= rawurlencode($_SERVER['REQUEST_URI'] ?? '') ?>" target="_blank" rel="noopener" class="piece-immersive-link">View in Immersive / VR Mode</a>
            <a href="/pieces/<?= (int) $piece['id'] ?>/download" class="piece-immersive-link">Download HTML</a>
        </div>
    <?php else: ?>
        <div class="piece-placeholder">
            <p>This piece has no rendered version yet.</p>
        </div>
    <?php endif; ?>
</section>

<?php if ($version): ?>
<section class="piece-prompt" aria-labelledby="prompt-title">
    <h2 id="prompt-title">Prompt</h2>
    <p>Engine: <?= e($engineLabel) ?></p>
    <p>AI Profile: <?= e($version['ai_profile_name'] ?? '(Blank)') ?></p>
    <p>AI Persona: <?= e($version['ai_persona_name'] ?? '(Blank)') ?></p>
    <?php if (!empty($version['prompt'])): ?>
        <p>Prompt: <?= e($version['prompt']) ?></p>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if (!empty($piece['versions']) && count($piece['versions']) > 1): ?>
<section class="piece-versions" aria-labelledby="versions-title">
    <h2 id="versions-title">Versions</h2>
    <ul>
        <?php foreach ($piece['versions'] as $v): ?>
            <li>
                <p>
                    <strong>Version <?= (int) $v['version_number'] ?></strong>
                    <?php if ((int) ($piece['current_version_id'] ?? 0) === (int) $v['id']): ?>
                        <strong>(current)</strong>
                    <?php endif; ?>
                </p>
                <p>AI Profile: <?= e($v['ai_profile_name'] ?? '(Blank)') ?></p>
                <p>AI Persona: <?= e($v['ai_persona_name'] ?? '(Blank)') ?></p>
                <?php if (!empty($v['prompt'])): ?>
                    <p>Prompt: <?= e($v['prompt']) ?></p>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<?php if (!empty($piece['comments_enabled'])): ?>
<section class="comments-section blog-comments" aria-labelledby="piece-comments-title">
    <h2 id="piece-comments-title">Comments</h2>
    <?php
    $commentsUrl = '/api/pieces/' . (int) $piece['id'] . '/comments';
    $emptyCommentMessage = 'No comments yet. Be the first.';
    require dirname(__DIR__) . '/partials/comment-list.php';
    ?>
    <?php
    $commentUrl = $commentsUrl;
    $signinRedirect = $_SERVER['REQUEST_URI'] ?? ('/pieces/' . (int) $piece['id']);
    require dirname(__DIR__) . '/partials/comment-form.php';
    ?>
</section>
<?php endif; ?>
<?php
require dirname(__DIR__) . '/partials/footer.php';
