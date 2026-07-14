<?php

declare(strict_types=1);

require dirname(__DIR__) . '/partials/header.php';

$version = $version ?? null;
$engineLabel = art_piece_effective_generation_mode_label($piece, is_array($version) ? $version : null);
$sonicFeel = is_array($version) ? art_piece_sonic_feel($version['sonic_params'] ?? null) : '';
$isAdmin = (bool) admin_identity();
$publicPieceScriptVersion = (int) @filemtime(dirname(__DIR__, 3) . '/assets/js/public-piece-download.js');
$pieceFullscreenScriptVersion = (int) @filemtime(dirname(__DIR__, 3) . '/assets/js/piece-fullscreen.js');
$pieceId = (int) ($piece['id'] ?? 0);
$versionId = isset($_GET['version']) ? max(0, (int) $_GET['version']) : 0;
$versionParam = $versionId > 0 ? '?version=' . $versionId : '';
$pieceDownloadEstimates = is_array($version)
    ? piece_export_download_estimates($piece, $version)
    : ['full' => 'size varies', 'no_camera' => 'size varies'];
$origin = ($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$embedTitle = htmlspecialchars((string) ($piece['title'] ?? 'Art piece'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
$regularEmbedCode = sprintf(
    '<iframe src="%s/embed/pieces/%d%s" width="100%%" style="width:100%%;aspect-ratio:16/9;display:block;" title="%s" frameborder="0" loading="lazy" allow="camera; microphone; fullscreen" allowfullscreen sandbox="allow-scripts allow-same-origin allow-popups"></iframe>',
    $origin,
    $pieceId,
    $versionParam,
    $embedTitle
);
?>
<section class="page-hero" aria-labelledby="piece-title">
    <p class="eyebrow"><?= e(public_copy_value('public_art_copy.piece_detail.eyebrow')) ?></p>
    <div class="blog-hero-header-row">
        <div class="blog-hero-header-left">
            <h1 id="piece-title"><?= e($piece['title'] ?? 'Untitled') ?></h1>
        </div>
        <?php if ($isAdmin): ?>
        <div class="blog-hero-header-right">
            <a href="/admin/pieces/<?= (int) $piece['id'] ?>/edit" class="post-action-btn edit-btn" aria-label="Edit piece">
                <?= icon('pencil') ?><span class="btn-label">Edit</span>
            </a>
        </div>
        <?php endif; ?>
    </div>
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

<?php require dirname(__DIR__) . '/partials/piece-stage.php'; ?>

<section class="piece-page-embed-actions" aria-label="Embed this piece">
    <button type="button" class="piece-page-embed-button" data-surface-embed-copy data-embed-code="<?= e($regularEmbedCode) ?>">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
        <span>Embed</span>
    </button>
    <span class="piece-page-embed-status" data-surface-embed-status role="status" aria-live="polite"></span>
    <textarea class="piece-page-embed-manual" data-surface-embed-manual readonly hidden aria-label="Embed code for manual copying"></textarea>
</section>

<?php if ($version): ?>
<section class="piece-prompt" aria-labelledby="prompt-title">
    <h2 id="prompt-title">Prompt</h2>
    <p class="piece-page-immersive-action">
        <a href="/immersive/pieces/<?= (int) $piece['id'] ?>?returnTo=<?= rawurlencode($_SERVER['REQUEST_URI'] ?? ('/pieces/' . (int) $piece['id'])) ?>" target="_blank" rel="noopener" class="piece-immersive-link"><?= e(public_copy_value('public_art_copy.shared_ui.view_immersive_label')) ?></a>
    </p>
    <p>Engine: <?= e($engineLabel) ?></p>
    <p>AI Profile: <?= e($version['ai_profile_name'] ?? '(Blank)') ?></p>
    <p>AI Persona: <?= e($version['ai_persona_name'] ?? '(Blank)') ?></p>
    <?php if (!empty($version['prompt'])): ?>
        <p>Prompt: <?= e($version['prompt']) ?></p>
    <?php endif; ?>
    <?php if ($sonicFeel !== ''): ?>
        <p>Sound Feel: <?= e($sonicFeel) ?></p>
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
                <?php $versionSonicFeel = art_piece_sonic_feel($v['sonic_params'] ?? null); ?>
                <?php if ($versionSonicFeel !== ''): ?>
                    <p>Sound Feel: <?= e($versionSonicFeel) ?></p>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<?php if (!empty($piece['comments_enabled'])): ?>
<section class="comments-section blog-comments" aria-labelledby="piece-comments-title">
    <h2 id="piece-comments-title"><?= e(public_copy_value('public_art_copy.shared_ui.comments_heading')) ?></h2>
    <?php
    $commentsUrl = '/api/pieces/' . (int) $piece['id'] . '/comments';
    $emptyCommentMessage = public_copy_value('public_art_copy.shared_ui.comments_empty');
    require dirname(__DIR__) . '/partials/comment-list.php';
    ?>
    <?php
    $commentUrl = $commentsUrl;
    $signinRedirect = $_SERVER['REQUEST_URI'] ?? ('/pieces/' . (int) $piece['id']);
    require dirname(__DIR__) . '/partials/comment-form.php';
    ?>
</section>
<?php endif; ?>
<script src="/assets/js/public-piece-download.js?v=<?= $publicPieceScriptVersion ?>"></script>
<script src="/assets/js/piece-fullscreen.js?v=<?= $pieceFullscreenScriptVersion ?>"></script>
<?php
require dirname(__DIR__) . '/partials/footer.php';
