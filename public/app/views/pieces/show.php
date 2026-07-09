<?php

declare(strict_types=1);

require dirname(__DIR__) . '/partials/header.php';

$version = $version ?? null;
$hasCode = $version && (!empty($version['html_code']) || !empty($version['css_code']) || !empty($version['generated_code']));
$engineLabel = art_piece_effective_generation_mode_label($piece, is_array($version) ? $version : null);
$sonicFeel = is_array($version) ? art_piece_sonic_feel($version['sonic_params'] ?? null) : '';
// Every engine can carry sonic_params now: three/aframe sonify camera
// motion, c2_interactive sonifies pointer motion, everything else gets the
// idle random-note pattern (see createPieceRuntimeAudioController).
$soundToggleAvailable = is_array($version) && !empty($version['sonic_params']);
$isAdmin = (bool) admin_identity();
$pngFilenameBase = pathinfo(piece_export_filename($piece), PATHINFO_FILENAME);
$pngFilename = ($pngFilenameBase !== '' ? $pngFilenameBase : 'piece-' . (int) ($piece['id'] ?? 0)) . '.png';
$publicPieceScriptVersion = (int) @filemtime(dirname(__DIR__, 3) . '/assets/js/public-piece-download.js');
$pieceFullscreenScriptVersion = (int) @filemtime(dirname(__DIR__, 3) . '/assets/js/piece-fullscreen.js');
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

<section class="piece-stage" aria-label="Generative art piece">
    <?php if ($hasCode): ?>
        <div data-piece-download-root>
            <div class="piece-canvas-container">
                <?= piece_render_iframe($piece, $version, 560, ['data-piece-download-frame' => 'true']) ?>
                <?php if ($soundToggleAvailable): ?>
                <button type="button" class="piece-sound-toggle" data-piece-sound-toggle aria-pressed="false" aria-label="Unmute sound">
                    <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4z"/><line x1="22" y1="9" x2="16" y2="15"/><line x1="16" y1="9" x2="22" y2="15"/></svg>
                </button>
                <?php endif; ?>
                <button type="button" class="piece-fullscreen-toggle" data-piece-fullscreen-toggle aria-expanded="false" aria-label="Expand piece to fullscreen">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>
                </button>
            </div>
            <div class="piece-fullscreen-bar" data-piece-fullscreen-bar role="toolbar" aria-label="Piece downloads" hidden>
                <a href="/pieces/<?= (int) $piece['id'] ?>/download" class="piece-immersive-link"><?= e(public_copy_value('public_art_copy.shared_ui.download_piece_label')) ?></a>
                <button type="button" class="piece-immersive-link piece-download-button" data-piece-download-trigger data-download-filename="<?= e($pngFilename) ?>"><?= e(public_copy_value('public_art_copy.shared_ui.download_png_label')) ?></button>
                <button type="button" class="piece-immersive-link piece-download-button" data-piece-fullscreen-close>Close</button>
            </div>
            <div class="piece-action-row">
                <a href="/immersive/pieces/<?= (int) $piece['id'] ?>?returnTo=<?= rawurlencode($_SERVER['REQUEST_URI'] ?? '') ?>" target="_blank" rel="noopener" class="piece-immersive-link"><?= e(public_copy_value('public_art_copy.shared_ui.view_immersive_label')) ?></a>
                <a href="/pieces/<?= (int) $piece['id'] ?>/download" class="piece-immersive-link"><?= e(public_copy_value('public_art_copy.shared_ui.download_piece_label')) ?></a>
                <button type="button" class="piece-immersive-link piece-download-button" data-piece-download-trigger data-download-filename="<?= e($pngFilename) ?>"><?= e(public_copy_value('public_art_copy.shared_ui.download_png_label')) ?></button>
            </div>
            <p class="piece-download-status" data-piece-download-status role="status" aria-live="polite" hidden></p>
        </div>
    <?php else: ?>
        <div class="piece-placeholder">
            <p><?= e(public_copy_value('public_art_copy.piece_detail.placeholder_empty')) ?></p>
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
