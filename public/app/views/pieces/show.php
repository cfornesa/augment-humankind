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
$sonicParamsDecoded = !empty($version['sonic_params']) ? json_decode((string) $version['sonic_params'], true) : null;
$pieceGenerationMode = is_array($version) ? art_piece_version_generation_mode($version, $piece) : 'p5';
$pieceControlCapabilities = is_array($version)
    ? piece_sound_capability_contract(
        $pieceGenerationMode,
        is_array($sonicParamsDecoded) ? $sonicParamsDecoded : [],
        piece_camera_overlay_enabled($version)
    )
    : [];
// The contract owns the "no sonic_params means no sound" rule.
$soundToggleAvailable = !empty($pieceControlCapabilities['sound']);
$handTrackingAvailable = !empty($pieceControlCapabilities['hand_tracking']);
$handControlAvailable = !empty($pieceControlCapabilities['hand_control']);
$cameraViewAvailable = !empty($pieceControlCapabilities['camera_view']);
$pieceControlsAvailable = $soundToggleAvailable || $cameraViewAvailable || $handControlAvailable;
// Which optional sound panels the admin has allowed for this piece, offered
// to the downloader as a ceiling-bounded choice for their own ZIP (see
// piece_export_apply_requested_voices() in piece-render.php) — never
// expandable past what's checked here.
$downloadVoiceOptions = [];
if (!empty($pieceControlCapabilities['keyboard'])) {
    $downloadVoiceOptions['melodic'] = 'Keyboard (piano)';
}
if ($handTrackingAvailable) {
    $downloadVoiceOptions['hand_tracking'] = 'Hand-tracking (camera theremin)';
}
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

<?php // Inlined so the piece chrome (sound panel, piano, fullscreen overlay)
      // never depends on a stale-cached external stylesheet — see
      // piece_view_critical_css() in immersive-chrome.php. ?>
<style><?= piece_view_critical_css() ?></style>
<section class="piece-stage" aria-label="Generative art piece">
    <?php if ($hasCode): ?>
        <div data-piece-download-root data-piece-id="<?= (int) $piece['id'] ?>">
            <div class="piece-canvas-container">
                <div class="piece-export-overlay" data-piece-download-picker-wrap role="toolbar" aria-label="Piece download controls">
                    <button type="button" class="piece-export-icon-btn" data-piece-download-trigger data-download-filename="<?= e($pngFilename) ?>" aria-label="Take screenshot">
                        <?= immersive_stage_toolbar_icon_svg('screenshot') ?>
                    </button>
                    <div class="piece-download-picker-wrap">
                        <button type="button" class="piece-export-icon-btn" data-piece-download-picker-trigger aria-haspopup="true" aria-expanded="false" aria-controls="piece-download-menu" aria-label="Open download menu">
                            <?= immersive_stage_toolbar_icon_svg('download') ?>
                        </button>
                        <div id="piece-download-menu" class="piece-download-picker" data-piece-download-picker role="region" aria-label="ZIP download options" hidden>
                            <p class="piece-download-picker-heading">Include in this download:</p>
                            <?php foreach ($downloadVoiceOptions as $key => $label): ?>
                            <label class="piece-download-picker-choice">
                                <input type="checkbox" data-piece-download-voice="<?= e($key) ?>" checked>
                                <span><?= e($label) ?></span>
                            </label>
                            <?php endforeach; ?>
                            <a href="/pieces/<?= (int) $piece['id'] ?>/download" class="piece-download-picker-action" data-piece-download-link>
                                <?= immersive_stage_toolbar_icon_svg('download-small') ?>
                                <span><?= e(public_copy_value('public_art_copy.shared_ui.download_piece_label')) ?></span>
                            </a>
                        </div>
                    </div>
                </div>
                <?= piece_render_iframe($piece, $version, 560, array_filter([
                    'data-piece-download-frame' => 'true',
                    'allow' => ($handTrackingAvailable || $cameraViewAvailable || $handControlAvailable) ? 'camera; microphone' : 'microphone',
                ])) ?>
                <?php if ($pieceControlsAvailable): ?>
                <div class="piece-sound-controls">
                    <div class="piece-sound-buttons">
                        <?php if ($soundToggleAvailable): ?>
                        <button type="button" class="piece-sound-toggle" data-piece-sound-toggle aria-pressed="false" aria-label="Unmute sound">
                            <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4z"/><line x1="22" y1="9" x2="16" y2="15"/><line x1="16" y1="9" x2="22" y2="15"/></svg>
                        </button>
                        <?php endif; ?>
                        <button type="button" class="piece-sound-panel-trigger" data-piece-sound-panel-trigger aria-haspopup="true" aria-expanded="false" aria-controls="piece-sound-panel" aria-label="Piece controls">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                    </div>
                    <div id="piece-sound-panel" class="piece-sound-panel" data-piece-sound-panel role="region" aria-label="Piece controls" hidden>
                        <?php if ($soundToggleAvailable): ?>
                        <div class="piece-sound-row">
                            <span>Sound</span>
                            <button type="button" class="piece-sound-switch" data-piece-sound-mute-toggle role="switch" aria-checked="false">Off</button>
                        </div>
                        <div class="piece-sound-row">
                            <label for="piece-sound-volume" style="flex:0 0 auto;">Volume</label>
                            <input type="range" id="piece-sound-volume" class="piece-sound-volume" data-piece-sound-volume min="0" max="100" step="1" value="50" aria-label="Volume">
                        </div>
                        <?= immersive_stage_voice_instrument_picker_markup('piece-voice-picker', 'data-piece-voice-picker') ?>
                        <div class="piece-sound-row" data-piece-sound-keyboard-row>
                            <span>Keyboard</span>
                            <button type="button" class="piece-sound-keyboard-toggle" data-piece-sound-keyboard-toggle aria-pressed="false">Play notes</button>
                        </div>
                        <div class="piece-piano-wrap" data-piece-sound-keys hidden>
                            <?= immersive_stage_piano_keyboard_markup('piece-piano', 'data-piece-piano') ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($handTrackingAvailable): ?>
                        <div class="piece-sound-row" data-piece-sound-hand-row>
                            <span>Hand-tracking</span>
                            <button type="button" class="piece-sound-keyboard-toggle" data-piece-sound-hand-toggle aria-pressed="false">Camera theremin</button>
                        </div>
                        <?php endif; ?>
                        <?php if ($handControlAvailable): ?>
                        <?php // Hand control (camera steering + tilt fallback) rides the
                              // camera permission or hand-tracking voice — see the
                              // capability contract. Hidden until the iframe's handshake
                              // confirms the engine registered a handPoint hook. ?>
                        <div class="piece-sound-row" data-piece-sound-hand-control-row hidden>
                            <span>Hand control</span>
                            <button type="button" class="piece-sound-keyboard-toggle" data-piece-sound-hand-control-toggle aria-pressed="false">Steer the piece</button>
                        </div>
                        <?php endif; ?>
                        <?php if ($cameraViewAvailable): ?>
                        <?php // Camera overlay: its own per-piece permission (Metadata tab),
                              // no longer tied to the hand-tracking voice. Rows stay hidden
                              // until the iframe's handshake confirms hook support. ?>
                        <div class="piece-sound-row" data-piece-sound-camera-bg-row hidden>
                            <span>Camera view</span>
                            <button type="button" class="piece-sound-keyboard-toggle" data-piece-sound-camera-bg-toggle aria-pressed="false">Show camera</button>
                        </div>
                        <div class="piece-sound-row" data-piece-sound-camera-opacity-row hidden>
                            <label for="piece-camera-opacity">Camera opacity</label>
                            <input id="piece-camera-opacity" type="range" min="0" max="100" value="35" data-piece-sound-camera-opacity aria-label="Camera overlay opacity">
                        </div>
                        <?php endif; ?>
                        <?php if ($soundToggleAvailable): ?>
                        <?= immersive_stage_mic_panel_markup('piece-mic', 'data-piece-mic', 'piece-sound-row', 'piece-sound-keyboard-toggle') ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <button type="button" class="piece-fullscreen-toggle" data-piece-fullscreen-toggle aria-expanded="false" aria-label="Expand piece to fullscreen">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>
                </button>
            </div>
            <div class="piece-action-row">
                <a href="/immersive/pieces/<?= (int) $piece['id'] ?>?returnTo=<?= rawurlencode($_SERVER['REQUEST_URI'] ?? '') ?>" target="_blank" rel="noopener" class="piece-immersive-link"><?= e(public_copy_value('public_art_copy.shared_ui.view_immersive_label')) ?></a>
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
