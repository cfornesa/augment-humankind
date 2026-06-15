<?php

declare(strict_types=1);

$displayTitle = $title !== '' ? $title : ($alt !== '' ? $alt : 'Immersive image');
$fixedSentence = 'This image uses the browser-based 3D immersive gallery scene '
    . 'with a normalized presentation surface and centered default framing.';
$imageDescription = $caption !== '' ? $caption . ' ' . $fixedSentence : $fixedSentence;

$jsItems = [[
    'kind' => 'image',
    'title' => $displayTitle,
    'imageUrl' => $imageSrc,
]];

// Determine details for URL/origin
$origin = ($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$titleSafe = htmlspecialchars($displayTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$altSafe = htmlspecialchars($alt, ENT_QUOTES | ENT_HTML5, 'UTF-8');

// Build the three different iterations of embed codes mirroring legacy Node.js
// 1. Plain embed
$plainEmbedCode = sprintf(
    '<img src="%s" alt="%s" style="max-width:100%%;height:auto;display:block;" />',
    $imageSrc,
    $altSafe
);

// Helper function to build custom query params
$queryParams = ['embed' => '1'];
if ($alt !== '') $queryParams['alt'] = $alt;
if ($title !== '') $queryParams['title'] = $title;
if ($caption !== '') $queryParams['caption'] = $caption;

// 2. Custom gallery embed
$customQuery = http_build_query($queryParams);
$customSrc = $origin . '/immersive/images/' . $encodedRef . '?' . $customQuery;
$customIframe = sprintf(
    '<iframe src="%s" width="100%%" style="width:100%%;aspect-ratio:16/9;min-height:300px;display:block;" title="%s" frameborder="0" loading="lazy" allowfullscreen allow="fullscreen" sandbox="allow-scripts allow-same-origin"></iframe>',
    $customSrc,
    $titleSafe
);
$galleryEmbedCode = sprintf(
    '<creatr-immersive-image ref="%s" origin="%s">%s</creatr-immersive-image><script src="%s/embed.js" defer></script>',
    $encodedRef,
    $origin,
    $customIframe,
    $origin
);

// 3. CMS gallery embed
$queryParamsCms = array_merge($queryParams, ['cms' => '1']);
$cmsQuery = http_build_query($queryParamsCms);
$cmsSrc = $origin . '/immersive/images/' . $encodedRef . '?' . $cmsQuery;
$cmsIframe = sprintf(
    '<iframe src="%s" width="100%%" style="width:100%%;aspect-ratio:16/9;min-height:300px;display:block;" title="%s" frameborder="0" loading="lazy" allowfullscreen allow="fullscreen" sandbox="allow-scripts allow-same-origin"></iframe>',
    $cmsSrc,
    $titleSafe
);
$galleryCmsEmbedCode = sprintf(
    '<creatr-immersive-image ref="%s" origin="%s">%s</creatr-immersive-image><script src="%s/embed.js" defer></script>',
    $encodedRef,
    $origin,
    $cmsIframe,
    $origin
);

$isEmbedMode = isset($_GET['embed']) && $_GET['embed'] === '1';
$isStaticEmbed = isset($_GET['static']) && $_GET['static'] === '1';
$isCmsEmbed = isset($_GET['cms']) && $_GET['cms'] === '1';
$isFullscreenInit = isset($_GET['fullscreen']) && $_GET['fullscreen'] === '1';

// Back link calculation
$backUrl = '/portfolio';
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
    "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js"
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
  height: 55vh;
  min-height: 420px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  overflow: hidden;
  background: #000;
  flex-shrink: 0;
}
#immersive-stage {
  width: 100%;
  height: 100%;
  display: block;
}

/* Fullscreen Toggle Button */
.fullscreen-toggle-btn {
  position: absolute;
  bottom: 1rem;
  right: 1rem;
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

/* Fullscreen Mode Overlay Class on shell */
.immersive-shell.fullscreen {
  overflow: hidden;
  height: 100vh;
  width: 100vw;
}
.immersive-shell.fullscreen .stage-wrapper {
  position: fixed;
  inset: 0;
  width: 100vw;
  height: 100vh;
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
  height: 100vh;
  width: 100vw;
  background: #000;
}
.immersive-shell.embed-mode .stage-wrapper {
  position: fixed;
  inset: 0;
  width: 100vw;
  height: 100vh;
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
</style>

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
            <span class="eyebrow">Immersive Image</span>
            <h1 class="title"><?= e($displayTitle) ?></h1>
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
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            </div>
            <h2 class="card-title"><?= e($displayTitle) ?></h2>
            <p class="card-desc"><?= e($imageDescription) ?></p>
            
            <dl class="card-grid">
                <div>
                    <dt>Alt Text</dt>
                    <dd><?= e($alt !== '' ? $alt : 'No alt text provided in this view.') ?></dd>
                </div>
                <div>
                    <dt>Interaction</dt>
                    <dd>Drag to orbit/pan, scroll to zoom, arrow keys/WASD or click floor to walk along the wall.</dd>
                </div>
                <div>
                    <dt>Source</dt>
                    <dd class="code-val"><?= e($imageSrc) ?></dd>
                </div>
            </dl>
        </div>
    </section>
</div>

<!-- Custom Toast Message Container -->
<div id="toast-container" aria-live="polite">
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#8ccf3f" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
    <span id="toast-message"></span>
</div>

<script type="module">
import { mountExhibitWall } from '/assets/js/immersive-gallery.js';

// Setup full screen toggling variables
const shell = document.getElementById('immersive-shell');

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
        });
    }
};

function syncFullscreenState(isFull) {
    const btn = document.getElementById('fullscreen-toggle-btn');
    if (!btn) return;

    if (isFull) {
        shell.classList.add('fullscreen');
        btn.innerHTML = `<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14h6v6m10-6h-6v6M4 10h6V4m10 6h-6V4"/></svg>`;
        btn.setAttribute('aria-label', 'Return to gallery view');
        document.body.style.overflow = 'hidden';
        document.documentElement.style.overflow = 'hidden';
    } else {
        shell.classList.remove('fullscreen');
        btn.innerHTML = `<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>`;
        btn.setAttribute('aria-label', 'Expand immersive view');
        document.body.style.overflow = '';
        document.documentElement.style.overflow = '';
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

// Boot standalone image
const stage = document.getElementById('immersive-stage');
const items = <?= json_encode($jsItems, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

try {
    mountExhibitWall(stage, items, 1, 1);
} catch (error) {
    console.error('Failed to mount immersive image:', error);
    stage.innerHTML = '<img src="<?= e($imageSrc) ?>" alt="<?= e($alt !== '' ? $alt : $displayTitle) ?>" style="max-width:100%;max-height:100%;object-fit:contain;display:block;margin:auto;">';
}
</script>

</body>
</html>
