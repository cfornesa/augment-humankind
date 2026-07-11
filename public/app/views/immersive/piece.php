<?php

declare(strict_types=1);

// Hydrate fields for display
$engine = strtolower((string) ($version['engine'] ?? $piece['engine'] ?? 'p5'));
// Cache-busted by file mtime, matching the ?v= pattern already used for
// piece-runtime.js (piece-render.php) — without this, browsers (WebKit/
// Safari especially) can keep serving a stale cached copy of
// immersive-gallery.js indefinitely after a deploy.
$galleryRuntimeVersion = (int) @filemtime(dirname(__DIR__, 3) . '/assets/js/immersive-gallery.js');
// Same cache-busting need applies to sonic-controller.js/Tone.js: unlike
// immersive-gallery.js above (a static <script> src), these are loaded via
// a dynamically-created <script> tag inside immersive-gallery.js itself, so
// there's no URL for the browser to see change on a normal page load unless
// we inject a versioned override here.
$sonicControllerVersion = (int) @filemtime(dirname(__DIR__, 3) . '/assets/js/sonic-controller.js');
$toneVersion = (int) @filemtime(dirname(__DIR__, 3) . '/assets/vendor/tone/Tone.js');

// Determine details for URL/origin
$origin = ($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$pieceId = (int) $piece['id'];
$versionId = isset($_GET['version']) ? (int) $_GET['version'] : null;
$versionParam = $versionId ? '?version=' . $versionId : '';

$embedUrl = $origin . '/embed/pieces/' . $pieceId . $versionParam;
$pieceDownloadUrl = '/pieces/' . $pieceId . '/download' . $versionParam;
$pngFilenameBase = pathinfo(piece_export_filename($piece), PATHINFO_FILENAME);
$pngFilename = ($pngFilenameBase !== '' ? $pngFilenameBase : 'piece-' . $pieceId) . '.png';
$publicPieceScriptVersion = (int) @filemtime(dirname(__DIR__, 3) . '/assets/js/public-piece-download.js');
$generationMode = art_piece_version_generation_mode($version, $piece);
$engineLabel = art_piece_generation_mode_label($generationMode);
$c2Interactive = $generationMode === 'c2_interactive';
$isThree = ($engine === 'three');
$versionNum = $version['version_number'] ?? 1;
$prompt = $version['prompt'] ?? $piece['prompt'] ?? '';
$description = $piece['description'] ?? '';
$aiProfileName = $version['ai_profile_name'] ?? '(Blank)';
$aiPersonaName = $version['ai_persona_name'] ?? '(Blank)';
$sonicFeel = art_piece_sonic_feel($version['sonic_params'] ?? null);
$sonicParamsDecoded = !empty($version['sonic_params']) ? json_decode((string) $version['sonic_params'], true) : null;
$pieceControlCapabilities = piece_sound_capability_contract(
    $generationMode,
    is_array($sonicParamsDecoded) ? $sonicParamsDecoded : [],
    piece_camera_overlay_enabled($version)
);
// The contract owns the "no sonic_params means no sound" rule.
$soundToggleAvailable = !empty($pieceControlCapabilities['sound']);
$cameraViewAvailable = !empty($pieceControlCapabilities['camera_view']);
$handControlAvailable = !empty($pieceControlCapabilities['hand_control']);
$downloadVoiceOptions = [];
if (!empty($pieceControlCapabilities['keyboard'])) {
    $downloadVoiceOptions['melodic'] = 'Keyboard (piano)';
}
if (!empty($pieceControlCapabilities['hand_tracking'])) {
    $downloadVoiceOptions['hand_tracking'] = 'Hand-tracking (camera theremin)';
}

// Build the three different iterations of embed codes mirroring legacy Node.js
$titleSafe = htmlspecialchars($piece['title'] ?? 'Art piece', ENT_QUOTES | ENT_HTML5, 'UTF-8');

// 1. Plain embed
$plainEmbedCode = sprintf(
    '<iframe src="%s/embed/pieces/%d%s" width="100%%" style="width:100%%;aspect-ratio:16/9;display:block;" title="%s" frameborder="0" loading="lazy" sandbox="allow-scripts allow-same-origin"></iframe>',
    $origin,
    $pieceId,
    $versionParam,
    $titleSafe
);

// 2. Interactive (Custom) embed
$sandbox = 'allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox allow-top-navigation-by-user-activation';
$customSrc = $origin . '/immersive/pieces/' . $pieceId . '?embed=1' . ($versionId ? '&version=' . $versionId : '');
$customIframe = sprintf(
    '<iframe src="%s" width="100%%" style="width:100%%;aspect-ratio:16/9;min-height:300px;display:block;" title="%s" frameborder="0" loading="lazy" allowfullscreen allow="fullscreen" sandbox="%s"></iframe>',
    $customSrc,
    $titleSafe,
    $sandbox
);
$versionAttr = $versionId ? ' version="' . $versionId . '"' : '';
$galleryEmbedCode = sprintf(
    '<creatr-art-piece piece-id="%d"%s origin="%s">%s</creatr-art-piece><script src="%s/embed.js" defer></script>',
    $pieceId,
    $versionAttr,
    $origin,
    $customIframe,
    $origin
);

// 3. Interactive (CMS) embed
$cmsSrc = $origin . '/immersive/pieces/' . $pieceId . '?embed=1' . ($versionId ? '&version=' . $versionId : '') . '&cms=1';
$cmsIframe = sprintf(
    '<iframe src="%s" width="100%%" style="width:100%%;aspect-ratio:16/9;min-height:300px;display:block;" title="%s" frameborder="0" loading="lazy" allowfullscreen allow="fullscreen" sandbox="%s"></iframe>',
    $cmsSrc,
    $titleSafe,
    $sandbox
);
$galleryCmsEmbedCode = sprintf(
    '<creatr-art-piece piece-id="%d"%s origin="%s">%s</creatr-art-piece><script src="%s/embed.js" defer></script>',
    $pieceId,
    $versionAttr,
    $origin,
    $cmsIframe,
    $origin
);

$isEmbedMode = isset($_GET['embed']) && $_GET['embed'] === '1';
$isStaticEmbed = isset($_GET['static']) && $_GET['static'] === '1';
$isCmsEmbed = isset($_GET['cms']) && $_GET['cms'] === '1';
$isFullscreenInit = isset($_GET['fullscreen']) && $_GET['fullscreen'] === '1';

// Back link calculation
$backUrl = '/pieces/' . $pieceId;
if (isset($_GET['returnTo']) && str_starts_with($_GET['returnTo'], '/')) {
    $backUrl = $_GET['returnTo'];
} elseif (isset($_GET['post']) && is_numeric($_GET['post'])) {
    $backUrl = '/posts/' . (int) $_GET['post'];
}
$showAdminEditButton = isset($adminEditUrl) && is_string($adminEditUrl) && $adminEditUrl !== '';
$showReadOnlyFullViewButton = !$isEmbedMode && !$isStaticEmbed
    && ($engine === 'p5' || $engine === 'svg' || ($engine === 'c2' && !$c2Interactive));
$showC2InteractiveOverlay = $engine === 'c2' && $c2Interactive;
$fullViewPieceSrcdoc = ($showReadOnlyFullViewButton || $showC2InteractiveOverlay) ? piece_render_document($piece, $version) : null;
$pieceViewActionLabel = null;
if ($showC2InteractiveOverlay) {
    $pieceViewActionLabel = 'Open interactive view';
} elseif ($showReadOnlyFullViewButton) {
    $pieceViewActionLabel = 'View piece full size';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<script type="importmap">
{
  "imports": {
    "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js",
    "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"
  }
}
</script>
<style>
:root {
  --bg: #050b16;
  --panel-bg: rgba(13, 13, 17, 0.76);
  --border-color: rgba(255, 255, 255, 0.14);
  --text-primary: #f8f5ee;
  --text-soft: #a1a1aa;
}
html, body {
  margin: 0;
  padding: 0;
  width: 100%;
  height: 100%;
  background: var(--bg);
  color: var(--text-primary);
  font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

/* Immersive Shell CSS-driven Toggle */
.immersive-shell {
  min-height: 100vh;
  background: var(--bg);
  display: flex;
  flex-direction: column;
}

/* Header */
.immersive-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  padding: 0.75rem 1rem;
  background: #050b16;
  flex-shrink: 0;
}
@media (min-width: 640px) {
  .immersive-header {
    padding: 0.75rem 1.5rem;
  }
}
.back-btn {
  display: inline-flex;
  align-items: center;
  border: 1px solid rgba(255, 255, 255, 0.15);
  background: rgba(255, 255, 255, 0.05);
  color: #fff;
  padding: 0.5rem 1.1rem;
  font-size: 0.875rem;
  font-weight: 500;
  border-radius: 9999px;
  text-decoration: none;
  transition: background 0.2s;
  cursor: pointer;
}
.back-btn:hover {
  background: rgba(255, 255, 255, 0.1);
}
.back-btn svg {
  margin-right: 0.5rem;
  flex-shrink: 0;
}
.header-actions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}
.header-info {
  text-align: right;
  min-width: 0;
}
.header-info .eyebrow {
  display: block;
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.22em;
  color: rgba(255, 255, 255, 0.55);
  margin-bottom: 0.125rem;
}
.header-info .title {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 500;
  color: rgba(255, 255, 255, 0.8);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
@media (min-width: 640px) {
  .header-info .title {
    font-size: 1rem;
  }
}
.immersive-admin-link {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  border: 1px solid rgba(255, 255, 255, 0.15);
  background: rgba(255, 255, 255, 0.08);
  color: #fff;
  padding: 0.55rem 0.95rem;
  border-radius: 9999px;
  text-decoration: none;
  font-size: 0.8rem;
  font-weight: 600;
  white-space: nowrap;
  transition: background 0.2s, border-color 0.2s;
}
.immersive-admin-link:hover {
  background: rgba(255, 255, 255, 0.14);
  border-color: rgba(255, 255, 255, 0.3);
}

/* Stage Wrapper & Canvas Stage */
.stage-wrapper {
  position: relative;
  width: 100%;
  height: 45vh;
  min-height: 320px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  overflow: hidden;
  background: #000;
  flex-shrink: 0;
}
@media (min-width: 768px) {
  .stage-wrapper {
    height: 55vh;
  }
}
#immersive-stage {
  width: 100%;
  height: 100%;
  display: block;
}

/* Fullscreen Toggle Button */
.fullscreen-toggle-btn {
  position: static;
  display: inline-flex;
  height: 2.75rem;
  width: 2.75rem;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(255, 255, 255, 0.15);
  background: rgba(0, 0, 0, 0.55);
  color: #fff;
  border-radius: 0.75rem;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  cursor: pointer;
  transition: background 0.2s, border-color 0.2s;
}
.fullscreen-toggle-btn:hover {
  background: rgba(0, 0, 0, 0.7);
  border-color: #fff;
}

/* Copy Codes Toolbar */
.copy-section {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  padding: 0.75rem 1rem;
  background: rgba(255, 255, 255, 0.01);
  flex-shrink: 0;
}
@media (min-width: 640px) {
  .copy-section {
    padding: 0.75rem 1.5rem;
  }
}
.embed-copy-btn {
  display: inline-flex;
  align-items: center;
  border: 1px solid rgba(255, 255, 255, 0.15);
  background: rgba(255, 255, 255, 0.05);
  color: rgba(255, 255, 255, 0.7);
  padding: 0.4rem 0.9rem;
  font-size: 0.75rem;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.14em;
  border-radius: 9999px;
  cursor: pointer;
  transition: background 0.2s, color 0.2s;
}
.embed-copy-btn:hover {
  background: rgba(255, 255, 255, 0.1);
  color: #fff;
}
.embed-copy-btn svg {
  margin-right: 0.375rem;
  flex-shrink: 0;
}

/* Metadata Card */
.metadata-section {
  padding: 1.5rem 1rem;
  background: rgba(255, 255, 255, 0.02);
  flex-grow: 1;
  overflow-y: auto;
}
@media (min-width: 640px) {
  .metadata-section {
    padding: 2rem 1.5rem;
  }
}
.metadata-card {
  border: 1px solid rgba(255, 255, 255, 0.1);
  background: rgba(0, 0, 0, 0.2);
  border-radius: 1rem;
  padding: 1.5rem;
  max-width: 800px;
  margin: 0 auto;
}
.card-icon {
  display: inline-flex;
  height: 2.75rem;
  width: 2.75rem;
  align-items: center;
  justify-content: center;
  border-radius: 9999px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  background: rgba(255, 255, 255, 0.05);
  color: #fff;
  margin-bottom: 1rem;
}
.card-title {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 600;
  word-break: break-all;
}
.card-desc {
  margin: 0.75rem 0 0 0;
  font-size: 0.9rem;
  line-height: 1.6;
  color: rgba(255, 255, 255, 0.7);
}
.card-grid {
  margin: 1.5rem 0 0 0;
  display: flex;
  flex-direction: column;
  gap: 1rem;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
  padding-top: 1.25rem;
}
.card-grid dt {
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.18em;
  color: rgba(255, 255, 255, 0.45);
  margin: 0 0 0.25rem 0;
}
.card-grid dd {
  margin: 0;
  font-size: 0.9rem;
  line-height: 1.5;
  color: rgba(255, 255, 255, 0.8);
  word-break: break-all;
}
.card-grid dd.prompt-val {
  font-style: italic;
  white-space: pre-wrap;
  font-family: inherit;
}
.card-grid dd.code-val {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}
.error-val {
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.1);
  padding: 0.75rem;
  border-radius: 0.375rem;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  white-space: pre-wrap;
}

/* Fullscreen Mode Overlay Class on shell */
/* Safari's address bar show/hide changes the visible viewport dynamically —
   100vh/100vw don't track that, so the overlay can be cropped or oversized.
   --immersive-viewport-{width,height} are kept in sync with
   window.visualViewport (see syncImmersiveViewportVars below); 100dvh/100dvw
   is the fallback when that hasn't run yet (e.g. before JS executes). */
.immersive-shell.fullscreen {
  overflow: hidden;
  height: var(--immersive-viewport-height, 100dvh);
  width: var(--immersive-viewport-width, 100dvw);
}
.immersive-shell.fullscreen .stage-wrapper {
  position: fixed;
  inset: 0;
  width: var(--immersive-viewport-width, 100dvw);
  height: var(--immersive-viewport-height, 100dvh);
  z-index: 120;
  border-bottom: none;
}
.immersive-shell.fullscreen .immersive-header,
.immersive-shell.fullscreen .copy-section,
.immersive-shell.fullscreen .metadata-section {
  display: none !important;
}

/* Embed Mode Class on shell */
.immersive-shell.embed-mode {
  overflow: hidden;
  height: var(--immersive-viewport-height, 100dvh);
  width: var(--immersive-viewport-width, 100dvw);
  background: #000;
}
.immersive-shell.embed-mode .stage-wrapper {
  position: fixed;
  inset: 0;
  width: var(--immersive-viewport-width, 100dvw);
  height: var(--immersive-viewport-height, 100dvh);
  border-bottom: none;
}
.immersive-shell.embed-mode .immersive-header,
.immersive-shell.embed-mode .copy-section,
.immersive-shell.embed-mode .metadata-section {
  display: none !important;
}

/* Toast styling */
#toast-container {
  position: fixed;
  bottom: 2rem;
  left: 50%;
  transform: translateX(-50%) translateY(100px);
  z-index: 200;
  background: rgba(13, 13, 17, 0.92);
  border: 1px solid rgba(255, 255, 255, 0.2);
  color: #fff;
  padding: 0.65rem 1.25rem;
  border-radius: 9999px;
  font-size: 0.8rem;
  font-weight: 500;
  pointer-events: none;
  transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.25s;
  opacity: 0;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.65);
  display: flex;
  align-items: center;
  gap: 0.5rem;
  letter-spacing: 0.03em;
}
#toast-container.show {
  transform: translateX(-50%) translateY(0);
  opacity: 1;
}

/* Custom safety rule for shadow containment */
canvas[aria-hidden="true"] {
  display: none !important;
}

/* Shared immersive stage toolbar (immersive-chrome.php) */
<?= immersive_stage_toolbar_css() ?>
@media (max-width: 700px), (max-height: 560px) {
  .immersive-header {
    flex-wrap: wrap;
    align-items: flex-start;
    padding: 0.7rem 0.85rem;
    gap: 0.7rem;
  }
  .back-btn,
  .immersive-admin-link {
    padding: 0.45rem 0.85rem;
    font-size: 0.78rem;
  }
  .header-actions {
    flex: 1 1 100%;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 0.65rem;
  }
  .header-info {
    flex: 1 1 14rem;
    min-width: 0;
    text-align: left;
    order: -1;
  }
  .header-info .title {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    white-space: normal;
    overflow: hidden;
    text-overflow: unset;
    line-height: 1.3;
  }
  .stage-wrapper {
    height: 40vh;
    min-height: 250px;
  }
}

/* A-Frame's device-orientation-permission-ui dialog hardcodes a white card
   and bright accent buttons but never sets its own text color, so it
   inherited this page's --text-primary (near-white) and went invisible
   (white-on-white). Override it to match this page's own dark panel styling
   instead of A-Frame's plain white default. */
.a-dialog {
  background-color: var(--panel-bg) !important;
  border: 1px solid var(--border-color);
}
.a-dialog-text {
  color: var(--text-primary) !important;
}
.a-dialog-button {
  color: #15374a !important;
}
</style>

<!-- Load p5 and c2 library runtimes on-demand -->
<?php if ($engine === 'p5'): ?>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/p5.js/1.9.0/p5.min.js"></script>
<?php elseif ($engine === 'c2'): ?>
  <script src="https://cdn.jsdelivr.net/npm/c2.js@1.0.9/dist/c2.min.js"></script>
<?php endif; ?>

</head>
<body>

<div id="immersive-shell" class="immersive-shell <?= $isEmbedMode ? 'embed-mode' : '' ?> <?= $isFullscreenInit ? 'fullscreen' : '' ?>">

    <!-- Header (only shown in standard mode) -->
    <header class="immersive-header">
        <a href="<?= e($backUrl) ?>" class="back-btn">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Back
        </a>
        <div class="header-actions">
            <?php if ($showAdminEditButton): ?>
                <a href="<?= e($adminEditUrl) ?>" class="immersive-admin-link" aria-label="Edit this piece in admin">
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4z"/></svg>
                    Edit
                </a>
            <?php endif; ?>
            <div class="header-info">
                <span class="eyebrow">Immersive View</span>
                <h1 class="title"><?= e($piece['title'] ?? 'Untitled') ?></h1>
            </div>
        </div>
    </header>

    <!-- Canvas Stage Viewport -->
    <div class="stage-wrapper">
        <div id="immersive-stage"></div>

        <?= immersive_stage_toolbar_markup([
            'view_action' => $pieceViewActionLabel !== null ? [
                'label' => $pieceViewActionLabel,
                'icon' => $showC2InteractiveOverlay ? 'interactive' : 'view',
            ] : null,
            'download_options' => !$isStaticEmbed ? [
                'choices' => array_map(
                    static fn(string $label, string $value): array => ['label' => $label, 'value' => $value],
                    array_values($downloadVoiceOptions),
                    array_keys($downloadVoiceOptions)
                ),
                'action' => [
                    'label' => public_copy_value('public_art_copy.shared_ui.download_piece_label'),
                    'attrs' => [
                        'href' => $pieceDownloadUrl,
                        'data-immersive-download-piece' => $pieceDownloadUrl,
                        'download' => true,
                    ],
                ],
            ] : null,
            'screenshot_action' => !$isStaticEmbed ? [
                'attrs' => [
                    'id' => 'immersive-download-png-btn',
                    'data-immersive-download-png' => true,
                    'data-download-filename' => $pngFilename,
                ],
            ] : null,
            'sound_action' => !$isStaticEmbed
                && !empty($version['sonic_params'])
                && (($sonicParamsDecoded = json_decode((string) $version['sonic_params'], true)) && ($sonicParamsDecoded['enabled'] ?? true) !== false)
                ? ['enabled' => true] : null,
            'camera_view' => !$isStaticEmbed && $cameraViewAvailable,
            'hand_control' => !$isStaticEmbed && $handControlAvailable,
            'show_fullscreen' => !$isStaticEmbed,
            'fullscreen_onclick' => 'toggleFullscreen()',
        ]) ?>

    </div>

    <!-- Copy Embeds Toolbar (only shown in standard mode) -->
    <section class="copy-section">
        <button type="button" class="embed-copy-btn" onclick="copyEmbed('plain')">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
            Embed Piece
        </button>
        <button type="button" class="embed-copy-btn" onclick="copyEmbed('gallery')">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
            Embed Interactive (Custom)
        </button>
        <button type="button" class="embed-copy-btn" onclick="copyEmbed('galleryCms')">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
            Embed Interactive (CMS)
        </button>
    </section>

    <!-- Metadata Panel / Card (only shown in standard mode) -->
    <section class="metadata-section">
        <div class="metadata-card">
            <div class="card-icon">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
            </div>
            <h2 class="card-title"><?= e($piece['title'] ?? 'Untitled') ?></h2>
            <p class="card-desc">
                <?php if ($isThree): ?>
                    This Three.js piece runs in a live immersive 3D canvas with orbital camera controls and keyboard WASD movement.
                <?php elseif ($engine === 'aframe'): ?>
                    This A-Frame piece runs in a live WebXR scene. Look around by dragging, and move with WASD or arrow keys.
                <?php else: ?>
                    This <?= e($engineLabel) ?> piece is mounted inside a 3D gallery room frame. Walk around the room using arrow keys/WASD or by clicking the floor.
                <?php endif; ?>
            </p>
            <dl class="card-grid">
                <div>
                    <dt>Engine</dt>
                    <dd><?= e($engineLabel) ?></dd>
                </div>
                <div>
                    <dt>Version</dt>
                    <dd>Version <?= (int) $versionNum ?></dd>
                </div>
                <div>
                    <dt>Interaction</dt>
                    <dd>
                        <?php if ($isThree): ?>
                            Drag to orbit, scroll/pinch to zoom, arrow keys/WASD to fly, click scene points to fly-to target.
                        <?php elseif ($engine === 'aframe'): ?>
                            Drag to look around. Arrow keys/WASD to walk. Gyroscope on mobile when permission granted.
                        <?php else: ?>
                            Drag to orbit, scroll to zoom, arrow keys/WASD or click floor to walk.
                        <?php endif; ?>
                    </dd>
                </div>
                <div>
                    <dt>AI Profile</dt>
                    <dd><?= e($aiProfileName) ?></dd>
                </div>
                <div>
                    <dt>AI Persona</dt>
                    <dd><?= e($aiPersonaName) ?></dd>
                </div>
                <?php if ($prompt): ?>
                    <div>
                        <dt>Creative Prompt</dt>
                        <dd class="prompt-val">"<?= e($prompt) ?>"</dd>
                    </div>
                <?php endif; ?>
                <?php if ($sonicFeel !== ''): ?>
                    <div>
                        <dt>Sound Feel</dt>
                        <dd><?= e($sonicFeel) ?></dd>
                    </div>
                <?php endif; ?>
                <?php if ($description !== ''): ?>
                    <div>
                        <dt>About this piece</dt>
                        <dd><?= e($description) ?></dd>
                    </div>
                <?php endif; ?>
                <div>
                    <dt>Embed Source</dt>
                    <dd class="code-val"><?= e($embedUrl) ?></dd>
                </div>
                <div id="runtime-error-item" style="display: none;">
                    <dt style="color: #ef4444;">Runtime Error</dt>
                    <dd id="runtime-error-val" class="error-val"></dd>
                </div>
            </dl>
        </div>
    </section>

    <!-- Versions history (only shown in standard mode) -->
    <?php if (!empty($piece['versions']) && count($piece['versions']) > 1): ?>
    <section class="metadata-section versions-section" aria-labelledby="immersive-versions-title">
        <div class="metadata-card">
            <h2 class="card-title" id="immersive-versions-title">Versions</h2>
            <dl class="card-grid">
                <?php foreach ($piece['versions'] as $v): ?>
                    <div>
                        <dt>
                            Version <?= (int) $v['version_number'] ?>
                            <?php if ((int) ($piece['current_version_id'] ?? 0) === (int) $v['id']): ?>
                                (current)
                            <?php endif; ?>
                        </dt>
                        <dd>
                            AI Profile: <?= e($v['ai_profile_name'] ?? '(Blank)') ?><br>
                            AI Persona: <?= e($v['ai_persona_name'] ?? '(Blank)') ?>
                            <?php if (!empty($v['prompt'])): ?>
                                <br>Prompt: <?= e($v['prompt']) ?>
                            <?php endif; ?>
                            <?php $versionSonicFeel = art_piece_sonic_feel($v['sonic_params'] ?? null); ?>
                            <?php if ($versionSonicFeel !== ''): ?>
                                <br>Sound Feel: <?= e($versionSonicFeel) ?>
                            <?php endif; ?>
                        </dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </div>
    </section>
    <?php endif; ?>
</div>

<!-- Custom Toast Message Container -->
<div id="toast-container" aria-live="polite">
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#8ccf3f" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
    <span id="toast-message"></span>
</div>

<script src="/assets/js/public-piece-download.js?v=<?= $publicPieceScriptVersion ?>"></script>
<script src="/assets/js/sonic-controller.js?v=<?= $sonicControllerVersion ?>"></script>
<script type="module">
window.__creatrSonicControllerSrc = '/assets/js/sonic-controller.js?v=<?= $sonicControllerVersion ?>';
window.__creatrToneSrc = '/assets/vendor/tone/Tone.js?v=<?= $toneVersion ?>';
import { mountThreeImmersivePiece, mountAFrameImmersivePiece, mountGalleryPiece, setupImmersiveStageChrome } from '/assets/js/immersive-gallery.js?v=<?= $galleryRuntimeVersion ?>';

// Setup full screen toggling variables
const shell = document.getElementById('immersive-shell');
let lockedScrollY = 0;
let immersiveScrollLocked = false;

// iOS Safari has no Fullscreen API support at all (not even webkit-prefixed —
// it only ever existed for <video>), so shell.requestFullscreen() always
// rejects there and we fall back to a CSS fixed-overlay. That overlay needs
// to track the *actual* visible viewport (Safari's address bar dynamically
// shows/hides, changing it), which 100vh/100vw don't do.
function isIPhoneWebKitBrowser() {
    if (typeof navigator === 'undefined') return false;
    const ua = navigator.userAgent || '';
    const maxTouchPoints = navigator.maxTouchPoints || 0;
    const isIPad = /\biPad\b/i.test(ua) || (/\bMacintosh\b/i.test(ua) && maxTouchPoints > 1);
    return /\biPhone\b/i.test(ua) && /AppleWebKit/i.test(ua) && !isIPad;
}

function syncImmersiveViewportVars() {
    const viewport = window.visualViewport;
    const width = Math.round(viewport ? viewport.width : window.innerWidth);
    const height = Math.round(viewport ? viewport.height : window.innerHeight);
    shell.style.setProperty('--immersive-viewport-width', Math.max(width, 1) + 'px');
    shell.style.setProperty('--immersive-viewport-height', Math.max(height, 1) + 'px');
}

function clearImmersiveViewportVars() {
    shell.style.removeProperty('--immersive-viewport-width');
    shell.style.removeProperty('--immersive-viewport-height');
}

let removeViewportListeners = null;
function watchImmersiveViewport() {
    if (removeViewportListeners) return;
    syncImmersiveViewportVars();
    window.addEventListener('resize', syncImmersiveViewportVars);
    window.visualViewport?.addEventListener('resize', syncImmersiveViewportVars);
    window.visualViewport?.addEventListener('scroll', syncImmersiveViewportVars);
    removeViewportListeners = () => {
        window.removeEventListener('resize', syncImmersiveViewportVars);
        window.visualViewport?.removeEventListener('resize', syncImmersiveViewportVars);
        window.visualViewport?.removeEventListener('scroll', syncImmersiveViewportVars);
        removeViewportListeners = null;
    };
}
function unwatchImmersiveViewport() {
    removeViewportListeners?.();
    clearImmersiveViewportVars();
}

function lockImmersiveScroll() {
    if (immersiveScrollLocked) return;
    lockedScrollY = window.scrollY || window.pageYOffset || 0;
    document.body.style.position = 'fixed';
    document.body.style.top = '-' + lockedScrollY + 'px';
    document.body.style.left = '0';
    document.body.style.right = '0';
    document.body.style.width = '100%';
    document.body.style.overflow = 'hidden';
    document.documentElement.style.overflow = 'hidden';
    immersiveScrollLocked = true;
}

function unlockImmersiveScroll() {
    if (!immersiveScrollLocked) return;
    document.body.style.position = '';
    document.body.style.top = '';
    document.body.style.left = '';
    document.body.style.right = '';
    document.body.style.width = '';
    document.body.style.overflow = '';
    document.documentElement.style.overflow = '';
    window.scrollTo(0, lockedScrollY);
    immersiveScrollLocked = false;
}

window.toggleFullscreen = function() {
    const isCurrentlyFull = shell.classList.contains('fullscreen');
    if (isCurrentlyFull) {
        if (document.fullscreenElement) {
            document.exitFullscreen().catch(() => syncFullscreenState(false));
        } else {
            syncFullscreenState(false);
        }
    } else {
        if (isIPhoneWebKitBrowser()) {
            syncFullscreenState(true, { mode: 'focus' });
            try {
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({ type: 'creatr-toggle-fullscreen', value: true }, '*');
                }
            } catch (e) {}
            return;
        }
        shell.requestFullscreen().then(() => {
            syncFullscreenState(true);
        }).catch(() => {
            // fallback if fullscreen API blocked or unsupported (like on iOS Safari)
            syncFullscreenState(true, { mode: 'focus' });
            // If this page is itself nested in an iframe (e.g. embedded via
            // <creatr-art-piece>/<creatr-exhibit-wall>), the CSS overlay above
            // is only as big as the iframe — ask the wrapper to promote us
            // to a true viewport-filling overlay on the host page instead.
            if (isIPhoneWebKitBrowser()) {
                try {
                    if (window.parent && window.parent !== window) {
                        window.parent.postMessage({ type: 'creatr-toggle-fullscreen', value: true }, '*');
                    }
                } catch (e) {}
            }
        });
    }
};

function syncFullscreenState(isFull, options = {}) {
    const btn = document.getElementById('fullscreen-toggle-btn');
    if (!btn) return;

    if (isFull) {
        shell.dataset.immersiveMode = options.mode || 'fullscreen';
        shell.classList.add('fullscreen');
        watchImmersiveViewport();
        btn.innerHTML = `<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14h6v6m10-6h-6v6M4 10h6V4m10 6h-6V4"/></svg>`;
        btn.setAttribute('aria-label', 'Return to page');
        lockImmersiveScroll();
    } else {
        shell.classList.remove('fullscreen');
        delete shell.dataset.immersiveMode;
        unwatchImmersiveViewport();
        btn.innerHTML = `<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>`;
        btn.setAttribute('aria-label', 'Expand immersive view');
        unlockImmersiveScroll();
        if (isIPhoneWebKitBrowser()) {
            try {
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({ type: 'creatr-toggle-fullscreen', value: false }, '*');
                }
            } catch (e) {}
        }
    }
    // Resize standard event dispatch so Three.js & other canvases react to viewport changes
    window.dispatchEvent(new Event('resize'));
}

document.addEventListener('fullscreenchange', () => {
    const isFull = !!document.fullscreenElement;
    syncFullscreenState(isFull);
});

// Exit fullscreen on Escape — unless the download menu or the full-view
// overlay is open (each handles its own Escape close first).
window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        if (document.querySelector('[data-immersive-download-menu]:not([hidden])')) {
            return;
        }
        const stageEl = document.getElementById('immersive-stage');
        if (stageEl?.dataset.keyboardNavigationDisabled === 'true') {
            return;
        }
        if (shell.classList.contains('fullscreen')) {
            toggleFullscreen();
        }
    }
});

// If loaded with direct fullscreen parameter
if (<?= $isFullscreenInit ? 'true' : 'false' ?>) {
    syncFullscreenState(true, { mode: isIPhoneWebKitBrowser() ? 'focus' : 'fullscreen' });
}

// Embed copy codes setup
const embedCodes = {
    plain: <?= json_encode($plainEmbedCode) ?>,
    gallery: <?= json_encode($galleryEmbedCode) ?>,
    galleryCms: <?= json_encode($galleryCmsEmbedCode) ?>
};

window.copyEmbed = function(type) {
    const code = embedCodes[type];
    const label = type === 'plain' ? 'Embed Piece' : (type === 'gallery' ? 'Embed Interactive (Custom)' : 'Embed Interactive (CMS)');
    
    if (code) {
        navigator.clipboard.writeText(code).then(() => {
            showToast(label + ' code is ready to paste.');
        }).catch(err => {
            console.error('Failed to copy code:', err);
            showToast('Copy failed. Select and copy the code manually.');
        });
    }
};

let toastTimeout = null;
function showToast(message) {
    const toast = document.getElementById('toast-container');
    const msg = document.getElementById('toast-message');
    if (!toast || !msg) return;

    if (toastTimeout) clearTimeout(toastTimeout);
    
    msg.textContent = message;
    toast.classList.add('show');
    
    toastTimeout = setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

// Intercept wrapper communication handshake for iOS iframe fullscreen launcher
window.addEventListener('message', (e) => {
    if (e.data && e.data.type === 'creatr-parent-exit-fullscreen') {
        syncFullscreenState(false);
    }
});
try {
    if (window.parent && window.parent !== window) {
        window.parent.postMessage({ type: 'creatr-iframe-ready' }, '*');
    }
} catch (e) {}

// Boot piece code on canvas
const code = <?= json_encode($version['generated_code'] ?? '') ?>;
const htmlCode = <?= json_encode($version['html_code'] ?? '') ?>;
const cssCode = <?= json_encode($version['css_code'] ?? '') ?>;
const engine = <?= json_encode($engine) ?>;
const title = <?= json_encode($piece['title'] ?? '') ?>;
const prompt = <?= json_encode($prompt) ?>;
const description = <?= json_encode($description) ?>;
const sourceUrl = <?= json_encode($embedUrl) ?>;
// Optional Tone.js sonification params ({tempo, scale, instrument, feel}) — null
// unless the piece-sound feature is on and this version stored a sonic block.
const sonicParams = <?= json_encode(
    (!empty($version['sonic_params']) && ($sonicParamsDecoded = json_decode((string) $version['sonic_params'], true)) && ($sonicParamsDecoded['enabled'] ?? true) !== false)
        ? $sonicParamsDecoded
        : null
) ?>;
const viewerControlsOptions = { showViewerControls: <?= (!$isEmbedMode && !$isStaticEmbed) ? 'true' : 'false' ?>, sonicParams, pieceId: <?= (int) ($piece['id'] ?? 0) ?>, cameraOverlay: <?= $cameraViewAvailable ? 'true' : 'false' ?>, handControl: <?= $handControlAvailable ? 'true' : 'false' ?> };

const stage = document.getElementById('immersive-stage');

function handleRuntimeError(err) {
    console.error("Immersive runtime error:", err);
    const item = document.getElementById('runtime-error-item');
    const val = document.getElementById('runtime-error-val');
    if (item && val) {
        val.textContent = err.stack || err.message || String(err);
        item.style.display = 'block';
    }
}

// C2 interactive pieces open inside the shared full-view overlay (slideshow
// shell) with an interactive iframe: the gallery room only ever shows the
// piece as a texture-projected frame, so the overlay's on-screen render
// document is the only way to get real click/touch/drag here.
try {
    let immersiveViewer = null;
    if (engine === 'three') {
        immersiveViewer = mountThreeImmersivePiece(stage, code, htmlCode, cssCode, handleRuntimeError, viewerControlsOptions);
    } else if (engine === 'aframe') {
        immersiveViewer = mountAFrameImmersivePiece(stage, code, htmlCode, cssCode, handleRuntimeError, viewerControlsOptions);
    } else {
        immersiveViewer = mountGalleryPiece(stage, code, htmlCode, cssCode, engine, title, sourceUrl, prompt, description, handleRuntimeError, null, {
            ...viewerControlsOptions,
            fullView: <?= ($showReadOnlyFullViewButton || $showC2InteractiveOverlay) ? json_encode([
                'items' => [[
                    'type' => 'iframe',
                    'srcdoc' => $fullViewPieceSrcdoc,
                    'interactive' => $showC2InteractiveOverlay,
                    'title' => (string) ($piece['title'] ?? ''),
                ]],
                'overlayOptions' => ['showDownloadControls' => false],
            ], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) : 'null' ?>,
        });
    }
    setupImmersiveStageChrome(stage, {
        onViewAction() {
            immersiveViewer?.openFullViewAt?.(0);
        },
        getAudioController: () => immersiveViewer?.getAudioController?.(),
        getPieceInteractionController: () => immersiveViewer?.getPieceInteractionController?.(),
        cameraOverlayAllowed: <?= $cameraViewAvailable ? 'true' : 'false' ?>,
        handControlAllowed: <?= $handControlAvailable ? 'true' : 'false' ?>,
    });

    const downloadPieceLink = document.querySelector('[data-immersive-download-piece]');
    if (downloadPieceLink) {
        downloadPieceLink.addEventListener('click', () => {
            const baseHref = downloadPieceLink.dataset.immersiveDownloadPiece || downloadPieceLink.getAttribute('href') || '';
            const state = immersiveViewer?.getViewState?.() || {};
            const choices = document.querySelectorAll('[data-immersive-download-menu] [data-piece-download-voice]');
            const chosen = Array.from(choices)
                .filter((choice) => choice.checked)
                .map((choice) => choice.dataset.pieceDownloadVoice)
                .filter((value) => value === 'melodic' || value === 'hand_tracking');
            try {
                const encoded = btoa(String.fromCharCode(...new TextEncoder().encode(JSON.stringify(state))))
                    .replace(/\+/g, '-')
                    .replace(/\//g, '_')
                    .replace(/=+$/g, '');
                const url = new URL(baseHref, window.location.href);
                url.searchParams.set('surface', 'immersive');
                url.searchParams.set('dl_voices', chosen.join(','));
                if (encoded) url.searchParams.set('viewState', encoded);
                downloadPieceLink.href = url.pathname + url.search;
            } catch (_) {
                const url = new URL(baseHref, window.location.href);
                url.searchParams.set('surface', 'immersive');
                url.searchParams.set('dl_voices', chosen.join(','));
                downloadPieceLink.href = url.pathname + url.search;
            }
        });
    }

    // Download PNG from the live immersive view. The default capture is the
    // stage canvas from the user's current perspective; an open interactive
    // overlay captures its iframe state instead.
    const downloadPngBtn = document.querySelector('[data-immersive-download-png]');
    if (downloadPngBtn && window.CreatrPieceDownload) {
        const dl = window.CreatrPieceDownload;
        const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

        async function captureImmersivePng() {
            // Full-view overlay open: snapshot the user's current state from
            // the overlay iframe instead (covers interactive C2 pieces too).
            if (immersiveViewer?.isFullViewOpen?.()) {
                const overlayFrame = document.querySelector('[data-full-view-viewport] iframe');
                if (overlayFrame?.contentDocument) {
                    const doc = overlayFrame.contentDocument;
                    const surface = await dl.waitForCaptureReady(doc);
                    return surface.type === 'svg'
                        ? dl.exportSvg(surface.node)
                        : dl.exportCanvasWithValidation(doc, surface.node);
                }
            }
            const surface = immersiveViewer?.getCaptureSurface?.();
            if (!surface || !surface.canvas) {
                throw new Error('No downloadable canvas is available yet.');
            }
            surface.beforeCapture?.();
            let exported = await dl.exportCanvas(surface.canvas);
            if (!dl.hasVisiblePixels(exported)) {
                // svg pieces rasterize on a ~100ms cadence; give one redraw
                // a chance before giving up.
                await wait(120);
                surface.beforeCapture?.();
                exported = await dl.exportCanvas(surface.canvas);
            }
            if (!dl.hasVisiblePixels(exported)) {
                throw new Error('Could not produce a non-blank PNG right now. Please try again.');
            }
            return exported;
        }

        downloadPngBtn.addEventListener('click', async () => {
            if (downloadPngBtn.disabled) return;
            const filename = downloadPngBtn.dataset.downloadFilename || 'piece.png';
            const labelEl = downloadPngBtn.querySelector('span');
            const originalLabel = labelEl ? labelEl.textContent : '';
            const originalAriaLabel = downloadPngBtn.getAttribute('aria-label') || 'Take screenshot';
            downloadPngBtn.disabled = true;
            downloadPngBtn.setAttribute('aria-busy', 'true');
            downloadPngBtn.setAttribute('aria-label', 'Preparing PNG...');
            if (labelEl) {
                labelEl.textContent = 'Preparing PNG...';
            }
            try {
                const exported = await captureImmersivePng();
                const blob = await dl.canvasToBlob(exported);
                dl.downloadBlob(blob, filename);
            } catch (error) {
                const message = error instanceof Error ? error.message : 'Could not download the PNG right now.';
                showToast(/tainted canvases/i.test(message)
                    ? 'This piece still contains an image or texture the browser will not export safely.'
                    : message);
            } finally {
                downloadPngBtn.disabled = false;
                downloadPngBtn.removeAttribute('aria-busy');
                downloadPngBtn.setAttribute('aria-label', originalAriaLabel);
                if (labelEl) {
                    labelEl.textContent = originalLabel;
                }
            }
        });
    }
} catch (e) {
    handleRuntimeError(e);
}
</script>

</body>
</html>
