<?php

declare(strict_types=1);

// Hydrate fields for display
$engine = strtolower((string) ($version['engine'] ?? $piece['engine'] ?? 'p5'));
$engineLabel = match ($engine) {
    'p5' => 'P5.js',
    'c2' => 'C2.js',
    'three' => 'Three.js',
    'svg' => 'SVG',
    'aframe' => 'A-Frame',
    default => strtoupper($engine),
};

$isThree = ($engine === 'three');
$versionNum = $version['version_number'] ?? 1;
$prompt = $version['prompt'] ?? $piece['prompt'] ?? '';
$description = $piece['description'] ?? '';
$aiProfileName = $version['ai_profile_name'] ?? '(Blank)';
$aiPersonaName = $version['ai_persona_name'] ?? '(Blank)';

// Determine details for URL/origin
$origin = ($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$pieceId = (int) $piece['id'];
$versionId = isset($_GET['version']) ? (int) $_GET['version'] : null;
$versionParam = $versionId ? '?version=' . $versionId : '';

$embedUrl = $origin . '/embed/pieces/' . $pieceId . $versionParam;

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
  position: absolute;
  bottom: calc(1rem + env(safe-area-inset-bottom));
  right: calc(1rem + env(safe-area-inset-right));
  z-index: 130;
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

/* C2.js click-to-interact overlay */
.c2-interact-hint-btn {
  position: absolute;
  bottom: calc(1rem + env(safe-area-inset-bottom));
  left: calc(1rem + env(safe-area-inset-left));
  z-index: 130;
  display: inline-flex;
  align-items: center;
  border: 1px solid rgba(255, 255, 255, 0.15);
  background: rgba(0, 0, 0, 0.55);
  color: #fff;
  padding: 0.5rem 1rem;
  font-size: 0.8rem;
  font-weight: 500;
  border-radius: 9999px;
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  cursor: pointer;
  transition: background 0.2s, border-color 0.2s;
}
.c2-interact-hint-btn:hover {
  background: rgba(0, 0, 0, 0.7);
  border-color: #fff;
}
.c2-interactive-overlay {
  position: absolute;
  inset: 0;
  z-index: 140;
  background: #05070f;
}
.c2-interactive-overlay[hidden] {
  display: none !important;
}
.c2-interactive-frame {
  width: 100%;
  height: 100%;
  border: 0;
  display: block;
}
.c2-interactive-close-btn {
  position: absolute;
  top: calc(0.75rem + env(safe-area-inset-top));
  right: calc(0.75rem + env(safe-area-inset-right));
  z-index: 150;
  display: inline-flex;
  height: 2.5rem;
  width: 2.5rem;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(255, 255, 255, 0.15);
  background: rgba(0, 0, 0, 0.6);
  color: #fff;
  border-radius: 9999px;
  cursor: pointer;
}
.c2-interactive-close-btn:hover {
  background: rgba(0, 0, 0, 0.8);
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
        <div class="header-info">
            <span class="eyebrow">Immersive View</span>
            <h1 class="title"><?= e($piece['title'] ?? 'Untitled') ?></h1>
        </div>
    </header>

    <!-- Canvas Stage Viewport -->
    <div class="stage-wrapper">
        <div id="immersive-stage"></div>

        <!-- Fullscreen controls (hidden in static embeds, and iOS embeds without handshakes) -->
        <?php if (!$isStaticEmbed): ?>
            <button id="fullscreen-toggle-btn" class="fullscreen-toggle-btn" onclick="toggleFullscreen()" aria-label="Expand immersive view">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>
            </button>
        <?php endif; ?>

        <?php if ($engine === 'c2'): ?>
            <!-- Click-to-interact overlay: the gallery room only ever shows this
                 piece as a texture-projected frame (off-screen canvas, no pointer
                 events reach it). Opening the same on-screen render document used
                 by the non-immersive /pieces/{id} view here gives full click/touch/
                 drag without building pointer-forwarding/raycasting into the 3D
                 scene. -->
            <div id="c2-interactive-overlay" class="c2-interactive-overlay" hidden>
                <button id="c2-interactive-close-btn" class="c2-interactive-close-btn" aria-label="Close interactive view">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
                <iframe id="c2-interactive-frame" class="c2-interactive-frame" title="Interactive view" sandbox="allow-scripts allow-same-origin"></iframe>
            </div>
            <button id="c2-interact-hint-btn" class="c2-interact-hint-btn" type="button">
                Click to interact
            </button>
        <?php endif; ?>
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

<script type="module">
import { mountThreeImmersivePiece, mountAFrameImmersivePiece, mountGalleryPiece } from '/assets/js/immersive-gallery.js';

// Setup full screen toggling variables
const shell = document.getElementById('immersive-shell');

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

window.toggleFullscreen = function() {
    const isCurrentlyFull = shell.classList.contains('fullscreen');
    if (isCurrentlyFull) {
        if (document.fullscreenElement) {
            document.exitFullscreen().catch(() => syncFullscreenState(false));
        } else {
            syncFullscreenState(false);
        }
    } else {
        shell.requestFullscreen().then(() => {
            syncFullscreenState(true);
        }).catch(() => {
            // fallback if fullscreen API blocked or unsupported (like on iOS Safari)
            syncFullscreenState(true);
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

function syncFullscreenState(isFull) {
    const btn = document.getElementById('fullscreen-toggle-btn');
    if (!btn) return;

    if (isFull) {
        shell.classList.add('fullscreen');
        watchImmersiveViewport();
        btn.innerHTML = `<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14h6v6m10-6h-6v6M4 10h6V4m10 6h-6V4"/></svg>`;
        btn.setAttribute('aria-label', 'Return to gallery view');
        document.body.style.overflow = 'hidden';
        document.documentElement.style.overflow = 'hidden';
    } else {
        shell.classList.remove('fullscreen');
        unwatchImmersiveViewport();
        btn.innerHTML = `<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>`;
        btn.setAttribute('aria-label', 'Expand immersive view');
        document.body.style.overflow = '';
        document.documentElement.style.overflow = '';
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

// Exit fullscreen on Escape
window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        if (shell.classList.contains('fullscreen')) {
            toggleFullscreen();
        }
    }
});

// If loaded with direct fullscreen parameter
if (<?= $isFullscreenInit ? 'true' : 'false' ?>) {
    syncFullscreenState(true);
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

// Click-to-interact overlay (c2 only): the gallery room only ever shows
// this piece as a texture-projected frame, so opening the same on-screen
// render document used by /pieces/{id} is the only way to get real
// click/touch/drag here.
let openInteractiveOverlay = null;
<?php if ($engine === 'c2'): ?>
const c2InteractiveSrcdoc = <?= json_encode(piece_render_document($piece, $version)) ?>;
const c2Overlay = document.getElementById('c2-interactive-overlay');
const c2Frame = document.getElementById('c2-interactive-frame');
const c2CloseBtn = document.getElementById('c2-interactive-close-btn');
const c2HintBtn = document.getElementById('c2-interact-hint-btn');

openInteractiveOverlay = function () {
    if (!c2Frame.getAttribute('srcdoc')) {
        c2Frame.setAttribute('srcdoc', c2InteractiveSrcdoc);
    }
    c2Overlay.hidden = false;
};

function closeInteractiveOverlay() {
    c2Overlay.hidden = true;
}

c2CloseBtn.addEventListener('click', closeInteractiveOverlay);
c2HintBtn.addEventListener('click', openInteractiveOverlay);
window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !c2Overlay.hidden) closeInteractiveOverlay();
});
<?php endif; ?>

try {
    if (engine === 'three') {
        mountThreeImmersivePiece(stage, code, htmlCode, cssCode, handleRuntimeError);
    } else if (engine === 'aframe') {
        mountAFrameImmersivePiece(stage, code, htmlCode, cssCode, handleRuntimeError);
    } else {
        mountGalleryPiece(stage, code, htmlCode, cssCode, engine, title, sourceUrl, prompt, description, handleRuntimeError, openInteractiveOverlay);
    }
} catch (e) {
    handleRuntimeError(e);
}
</script>

</body>
</html>
