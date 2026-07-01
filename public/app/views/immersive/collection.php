<?php

declare(strict_types=1);

// Cache-busted by file mtime, matching the ?v= pattern already used for
// piece-runtime.js (piece-render.php) and immersive/piece.php — without
// this, browsers (WebKit/Safari especially) can keep serving a stale
// cached copy of immersive-gallery.js indefinitely after a deploy.
$galleryRuntimeVersion = (int) @filemtime(dirname(__DIR__, 3) . '/assets/js/immersive-gallery.js');

// Pattern used to decide whether a C2 piece is interactive (pointer/mouse/touch listeners).
$c2InteractivePattern = '/(?:addEventListener\s*\(\s*[\'"](?:pointerdown|pointerup|pointermove|mousedown|mouseup|mousemove|touchstart|touchmove|touchend|click)|on(?:click|mousedown|mouseup|mousemove|touchstart|touchmove|touchend|pointerdown|pointermove|pointerup)\s*=)/i';

// Hydrate fields for display
$hasP5 = false;
$hasC2 = false;
$collectionSlideshowStartIndex = !empty($items) ? 0 : null;
$immersiveCollectionReturnTo = (string) ($_SERVER['REQUEST_URI'] ?? '');
if ($immersiveCollectionReturnTo === '') {
    $immersiveCollectionReturnTo = '/immersive/collections/' . ($collection['slug'] ?? '');
}

$jsItems = [];
$detailItems = [];
foreach ($items as $index => $item) {
    if ($item['type'] === 'art_piece' && !empty($item['piece']) && !empty($item['version'])) {
        $piece = $item['piece'];
        $version = $item['version'];
        $engine = strtolower($version['engine'] ?? $piece['engine'] ?? 'p5');
        if ($engine === 'p5') {
            $hasP5 = true;
        }
        if ($engine === 'c2') {
            $hasC2 = true;
        }
        $itemEngineLabel = match ($engine) {
            'p5' => 'P5.js',
            'c2' => 'C2.js',
            'three' => 'Three.js',
            'svg' => 'SVG',
            'aframe' => 'A-Frame',
            default => strtoupper($engine),
        };
        $pieceDescription = $piece['description'] ?? '';
        $piecePrompt = (string) ($version['prompt'] ?? $piece['prompt'] ?? '');
        // C2 is interactive when its generated code registers pointer/mouse/touch listeners.
        $c2LikelyInteractive = $engine === 'c2'
            && preg_match($c2InteractivePattern, (string) ($version['generated_code'] ?? '') . "\n" . (string) ($version['html_code'] ?? '')) === 1;
        $immersiveHref = '/immersive/pieces/' . (int) ($piece['id'] ?? 0)
            . '?returnTo=' . rawurlencode($immersiveCollectionReturnTo);
        $pieceFullViewDescription = (string) ($pieceDescription !== '' ? $pieceDescription : $piecePrompt);
        // Three.js, A-Frame, and interactive C2 pieces get pointer-events enabled so the
        // user can interact with them in the overlay. P5, SVG, and non-interactive C2
        // remain read-only previews. srcdoc iframes run in their own browsing context,
        // so there is no WebGL context conflict with the exhibit wall.
        $pieceInteractive = in_array($engine, ['three', 'aframe'], true) || $c2LikelyInteractive;
        $fullView = [
            'type' => 'iframe',
            'interactive' => $pieceInteractive,
            'srcdoc' => piece_render_document($piece, $version),
            'title' => $piece['title'] ?? 'Untitled Piece',
            'subtitle' => $itemEngineLabel,
        ];
        $jsItems[] = [
            'kind' => 'piece',
            'title' => $piece['title'] ?? 'Untitled Piece',
            'engine' => $engine,
            'thumbnail_url' => $piece['thumbnail_url'] ?? '',
            'html_code' => $version['html_code'] ?? '',
            'css_code' => $version['css_code'] ?? '',
            'generated_code' => $version['generated_code'] ?? '',
            'description' => $pieceDescription,
            'immersive_href' => $immersiveHref,
            'full_view' => $fullView,
        ];
        $detailItems[] = [
            'title' => $piece['title'] ?? 'Untitled Piece',
            'badge' => $itemEngineLabel,
            'description' => $pieceDescription,
            'edit_url' => $item['admin_edit_url'] ?? null,
            'immersive_href' => $immersiveHref,
            'view_aria_label' => 'Open immersive view for ' . ($piece['title'] ?? 'this piece'),
        ];
    } elseif ($item['type'] === 'media_asset' && !empty($item['media'])) {
        $media = $item['media'];
        $src = $media['url'] ?: '/api/media-assets/' . (int) $media['id'];
        $altText = $media['alt_text'] ?? '';
        $jsItems[] = [
            'kind' => 'image',
            'title' => $media['title'] ?? 'Untitled Image',
            'imageUrl' => $src,
            'alt_text' => $altText,
            'description' => $altText,
            'full_view' => [
                'type' => 'image',
                'src' => $src,
                'alt' => $altText !== '' ? $altText : ($media['title'] ?? 'Untitled Image'),
                'title' => $media['title'] ?? 'Untitled Image',
                'subtitle' => 'Image',
            ],
        ];
        $detailItems[] = [
            'title' => $media['title'] ?? 'Untitled Image',
            'badge' => 'Image',
            'description' => $altText,
            'edit_url' => $item['admin_edit_url'] ?? null,
            'full_view_index' => $index,
            'view_aria_label' => 'View ' . ($media['title'] ?? 'this image') . ' full size',
        ];
    }
}

$description = $collection['description'] ?? '';
$artistStatement = $collection['artist_statement'] ?? '';
$biography = $collection['biography'] ?? '';
$slug = $collection['slug'] ?? '';
$exhibitName = $collection['name'] ?? 'Collection';

// Determine details for URL/origin
$origin = ($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

// Build the three different iterations of embed codes mirroring legacy Node.js
$titleSafe = htmlspecialchars($exhibitName, ENT_QUOTES | ENT_HTML5, 'UTF-8');

// 1. Plain embed
$plainEmbedCode = sprintf(
    '<iframe src="%s/immersive/collections/%s?embed=1" width="100%%" style="width:100%%;aspect-ratio:16/9;display:block;" title="%s" frameborder="0" loading="lazy" sandbox="allow-scripts allow-same-origin"></iframe>',
    $origin,
    $slug,
    $titleSafe
);

// 2. Interactive (Custom) embed
$sandbox = 'allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox allow-top-navigation-by-user-activation';
$customSrc = $origin . '/immersive/collections/' . $slug . '?embed=1';
$customIframe = sprintf(
    '<iframe src="%s" width="100%%" style="width:100%%;aspect-ratio:16/9;min-height:300px;display:block;" title="%s" frameborder="0" loading="lazy" allowfullscreen allow="fullscreen" sandbox="%s"></iframe>',
    $customSrc,
    $titleSafe,
    $sandbox
);
$galleryEmbedCode = sprintf(
    '<creatr-exhibit-wall slug="%s" origin="%s">%s</creatr-exhibit-wall><script src="%s/embed.js" defer></script>',
    $slug,
    $origin,
    $customIframe,
    $origin
);

// 3. Interactive (CMS) embed
$cmsSrc = $origin . '/immersive/collections/' . $slug . '?embed=1&cms=1';
$cmsIframe = sprintf(
    '<iframe src="%s" width="100%%" style="width:100%%;aspect-ratio:16/9;min-height:300px;display:block;" title="%s" frameborder="0" loading="lazy" allowfullscreen allow="fullscreen" sandbox="%s"></iframe>',
    $cmsSrc,
    $titleSafe,
    $sandbox
);
$galleryCmsEmbedCode = sprintf(
    '<creatr-exhibit-wall slug="%s" origin="%s">%s</creatr-exhibit-wall><script src="%s/embed.js" defer></script>',
    $slug,
    $origin,
    $cmsIframe,
    $origin
);

$isEmbedMode = isset($_GET['embed']) && $_GET['embed'] === '1';
$isStaticEmbed = isset($_GET['static']) && $_GET['static'] === '1';
$isCmsEmbed = isset($_GET['cms']) && $_GET['cms'] === '1';
$isFullscreenInit = isset($_GET['fullscreen']) && $_GET['fullscreen'] === '1';

// Back link calculation
$backUrl = '/collections/' . $slug;
if (isset($_GET['returnTo']) && str_starts_with($_GET['returnTo'], '/')) {
    $backUrl = $_GET['returnTo'];
} elseif (isset($_GET['post']) && is_numeric($_GET['post'])) {
    $backUrl = '/posts/' . (int) $_GET['post'];
}
$showAdminEditButton = isset($adminEditUrl) && is_string($adminEditUrl) && $adminEditUrl !== '';

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
.immersive-stage-action-btn {
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
.immersive-stage-action-btn:hover {
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

/* Works listing in details */
.meta-works {
  margin-top: 1.5rem;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  padding-top: 1.5rem;
}
.meta-works h3 {
  font-size: 1.1rem;
  margin: 0 0 1rem 0;
  font-weight: 600;
}
.work-card {
  border: 1px solid var(--border-color);
  background: rgba(255, 255, 255, 0.02);
  border-radius: 6px;
  padding: 0.8rem 1rem;
  margin-bottom: 0.75rem;
}
.work-card-title {
  font-size: 0.95rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}
.work-card-title-text {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-width: 0;
}
.work-badge {
  display: inline-block;
  font-size: 0.65rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #fff;
  border: 1px solid rgba(255, 255, 255, 0.2);
  background: rgba(255, 255, 255, 0.08);
  border-radius: 4px;
  padding: 0.1rem 0.5rem;
}
.work-card-edit-link {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  color: rgba(255, 255, 255, 0.78);
  text-decoration: none;
  font-size: 0.76rem;
  font-weight: 600;
  white-space: nowrap;
}
.work-card-edit-link:hover {
  color: #fff;
}
.work-card-actions {
  display: inline-flex;
  align-items: center;
  gap: 0.75rem;
  flex-shrink: 0;
}
.work-card-view-link {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  color: rgba(255, 255, 255, 0.78);
  text-decoration: none;
  font-size: 0.76rem;
  font-weight: 600;
  white-space: nowrap;
}
.work-card-view-link:hover {
  color: #fff;
}
.work-card-desc {
  font-size: 0.85rem;
  color: var(--text-soft);
  line-height: 1.5;
  margin-top: 0.5rem;
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
<?php if ($hasP5): ?>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/p5.js/1.9.0/p5.min.js"></script>
<?php endif; ?>
<?php if ($hasC2): ?>
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
                <a href="<?= e($adminEditUrl) ?>" class="immersive-admin-link" aria-label="Edit this collection in admin">
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4z"/></svg>
                    Edit
                </a>
            <?php endif; ?>
            <div class="header-info">
                <span class="eyebrow">Immersive Collection</span>
                <h1 class="title"><?= e($exhibitName) ?></h1>
            </div>
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
        <?php if (!$isEmbedMode && !$isStaticEmbed && $collectionSlideshowStartIndex !== null): ?>
            <button
                id="collection-full-view-btn"
                class="immersive-stage-action-btn"
                type="button"
                data-start-index="<?= (int) $collectionSlideshowStartIndex ?>"
            >
                View slideshow
            </button>
        <?php endif; ?>
    </div>

    <!-- Copy Embeds Toolbar (only shown in standard mode) -->
    <section class="copy-section">
        <button type="button" class="embed-copy-btn" onclick="copyEmbed('plain')">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
            Embed Collection
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
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/></svg>
            </div>
            <h2 class="card-title"><?= e($exhibitName) ?></h2>
            <?php if ($description): ?>
                <p class="card-desc"><?= e($description) ?></p>
            <?php endif; ?>
            
            <dl class="card-grid">
                <div>
                    <dt>Grid Size</dt>
                    <dd><?= (int) $rows ?> &times; <?= (int) $cols ?> slots</dd>
                </div>
                <div>
                    <dt>Progressive Rendering</dt>
                    <dd>Active slots are loaded dynamically based on proximity to the camera target: 1 on mobile, 2 on tablet, 3 on desktop. Remaining slots show thumbnails.</dd>
                </div>
                <div>
                    <dt>Interaction</dt>
                    <dd>Drag to orbit/pan, scroll to zoom, arrow keys/WASD or click floor to walk along the wall.</dd>
                </div>
                <?php if ($artistStatement !== ''): ?>
                    <div>
                        <dt>Artist Statement</dt>
                        <dd><?= e($artistStatement) ?></dd>
                    </div>
                <?php endif; ?>
                <?php if ($biography !== ''): ?>
                    <div>
                        <dt>Biography</dt>
                        <dd><?= e($biography) ?></dd>
                    </div>
                <?php endif; ?>
                <div>
                    <dt>Works</dt>
                    <dd><?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?></dd>
                </div>
            </dl>

            <?php if (!empty($detailItems)): ?>
                <div class="meta-works">
                    <h3>Exhibits in this Collection</h3>
                    <?php foreach ($detailItems as $work): ?>
                        <div class="work-card">
                            <div class="work-card-title">
                                <div class="work-card-title-text">
                                    <span><?= e($work['title']) ?></span>
                                    <span class="work-badge"><?= e($work['badge']) ?></span>
                                </div>
                                <div class="work-card-actions">
                                    <a
                                        href="<?= e((string) ($work['immersive_href'] ?? '#')) ?>"
                                        class="work-card-view-link"
                                        <?php if (!empty($work['full_view_index']) || array_key_exists('full_view_index', $work)): ?>
                                            data-full-view-index="<?= (int) ($work['full_view_index'] ?? 0) ?>"
                                        <?php endif; ?>
                                        aria-label="<?= e((string) ($work['view_aria_label'] ?? ('View ' . ($work['title'] ?? 'this work')))) ?>"
                                    >
                                        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                                        View
                                    </a>
                                    <?php if (!empty($work['edit_url'])): ?>
                                        <a href="<?= e((string) $work['edit_url']) ?>" class="work-card-edit-link" aria-label="Edit <?= e($work['title']) ?>">
                                            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4z"/></svg>
                                            Edit
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($work['description'] !== ''): ?>
                                <div class="work-card-desc"><?= e($work['description']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<!-- Custom Toast Message Container -->
<div id="toast-container" aria-live="polite">
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#8ccf3f" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
    <span id="toast-message"></span>
</div>

<script type="module">
import { mountExhibitWall } from '/assets/js/immersive-gallery.js?v=<?= $galleryRuntimeVersion ?>';

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
            // <creatr-exhibit-wall>), the CSS overlay above is only as big as
            // the iframe — ask the wrapper to promote us to a true
            // viewport-filling overlay on the host page instead.
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
    const label = type === 'plain' ? 'Embed Collection' : (type === 'gallery' ? 'Embed Interactive (Custom)' : 'Embed Interactive (CMS)');
    
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

// Boot progressive exhibit wall
const items = <?= json_encode($jsItems) ?>;
const rows = <?= (int) $rows ?>;
const cols = <?= (int) $cols ?>;
const viewerControlsOptions = {
    showViewerControls: <?= (!$isEmbedMode && !$isStaticEmbed) ? 'true' : 'false' ?>,
    labelPosition: 'above',
};

const stage = document.getElementById('immersive-stage');

try {
    const immersiveViewer = mountExhibitWall(stage, items, rows, cols, viewerControlsOptions);
    const slideshowBtn = document.getElementById('collection-full-view-btn');
    if (slideshowBtn && immersiveViewer?.openSlideshowAt) {
        slideshowBtn.addEventListener('click', () => {
            const startIndex = Number(slideshowBtn.getAttribute('data-start-index'));
            immersiveViewer.openSlideshowAt(Number.isFinite(startIndex) ? startIndex : 0);
        });
    }
    document.querySelectorAll('[data-full-view-index]').forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            const index = Number(link.getAttribute('data-full-view-index'));
            if (!Number.isFinite(index)) return;
            immersiveViewer?.openSlideshowAt?.(index);
        });
    });
} catch (e) {
    console.error("Failed to mount exhibit wall:", e);
}
</script>

</body>
</html>
