<?php

declare(strict_types=1);

function piece_render_document(array $piece, array $version, array $options = []): string
{
    $title = htmlspecialchars((string) ($piece['title'] ?? 'Art piece'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $engine = strtolower((string) ($version['engine'] ?? $piece['engine'] ?? 'p5'));
    $html = (string) ($version['html_code'] ?? '');
    if ($engine === 'aframe') {
        $html = piece_aframe_add_crossorigin_to_asset_images($html);
    }
    $css = (string) ($version['css_code'] ?? '');
    $code = (string) ($version['generated_code'] ?? '');
    $jsonCode = json_encode($code, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $jsonEngine = json_encode($engine);
    $jsonHtml = json_encode($html, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $jsonCss = json_encode($css, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $jsonContext = json_encode([
        'viewerMode' => (string) ($options['viewer_mode'] ?? 'default'),
        'interactive' => !empty($options['interactive']),
        'disableMotion' => !empty($options['disable_motion']),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $aframeCss = $engine === 'aframe'
        ? "a-scene{display:block;width:100%;height:100%;}\n.a-canvas{display:block;width:100%!important;height:100%!important;}\n"
        : '';

    $requestOrigin = piece_request_origin();
    $baseTag = '<base href="' . htmlspecialchars(rtrim($requestOrigin, '/') . '/', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '">';
    // The base tag and our own runtime script must load from wherever THIS
    // request is actually being served — never from seo_origin() (the site's
    // configured canonical URL, which can differ from the actual host in
    // local/dev), and never from window.location.origin at runtime either:
    // this document is frequently embedded via <iframe srcdoc>
    // (piece_render_iframe() below), and srcdoc documents get an opaque
    // origin — window.location.origin literally evaluates to the string
    // "null" in that context, even with sandbox="allow-same-origin".
    // Computing it server-side from the actual request avoids both traps and
    // keeps root-relative CMS media paths on the preview host.
    // Cache-busted by file mtime, matching the ?v= pattern already used for
    // admin-piece-capture.js's own <script> tags. Without this, browsers
    // (WebKit/Safari especially) can keep serving a stale cached copy of
    // piece-runtime.js indefinitely after a deploy, since this URL was
    // previously requested with no version hint at all — every fix to the
    // runtime since whenever a device first cached it would silently never
    // take effect on that device.
    $runtimeVersion = (int) @filemtime(dirname(__DIR__, 2) . '/assets/js/piece-runtime.js');
    $runtimeScriptUrl = htmlspecialchars($requestOrigin . '/assets/js/piece-runtime.js?v=' . $runtimeVersion, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
{$baseTag}
<title>{$title}</title>
<script type="importmap">
{
  "imports": {
    "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js",
    "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"
  }
}
</script>
<style>
html,body{margin:0;padding:0;width:100%;height:100%;overflow:hidden;background:#0d0d0f;color:#fff;}
body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}
#runtime-root{width:100vw;height:100vh;overflow:hidden;}
#piece-error{position:fixed;inset:auto 1rem 1rem 1rem;z-index:9999;padding:1rem;border:1px solid #fca5a5;background:#450a0a;color:#fee2e2;font:14px/1.4 ui-monospace,SFMono-Regular,Menlo,monospace;white-space:pre-wrap;display:none;}
canvas{display:block;width:100%;height:100%;}
/* A-Frame's device-orientation-permission-ui dialog hardcodes a white card
   and bright accent buttons but never sets its own text color, so it
   inherited this document's color:#fff above and went invisible
   (white-on-white). This document has no theme toggle and no --panel-bg/
   --text-primary variables (unlike the immersive views, which get the same
   override using those) — literal colors here instead. */
.a-dialog{background-color:#162a3a !important;border:1px solid rgba(255,255,255,0.15);}
.a-dialog-text{color:#dde8ef !important;}
.a-dialog-button{color:#15374a !important;}
{$aframeCss}
{$css}
</style>
</head>
<body>
<div id="runtime-root">{$html}</div>
<div id="piece-error" role="alert"></div>
<script>
const PIECE_ENGINE = {$jsonEngine};
const PIECE_CODE = {$jsonCode};
const PIECE_HTML_CODE = {$jsonHtml};
const PIECE_CSS_CODE = {$jsonCss};
window.CREATR_PIECE_CONTEXT = {$jsonContext};
window.PIECE_PRESERVE_DRAWING_BUFFER = true;
</script>
<script src="{$runtimeScriptUrl}"></script>
</body>
</html>
HTML;
}

function piece_render_iframe(array $piece, array $version, int $height = 520): string
{
    $srcdoc = htmlspecialchars(piece_render_document($piece, $version), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $title = htmlspecialchars((string) ($piece['title'] ?? 'Art piece'), ENT_QUOTES, 'UTF-8');
    return '<iframe srcdoc="' . $srcdoc . '" style="width:100%;height:' . $height . 'px;border:0;display:block;" sandbox="allow-scripts allow-same-origin" title="' . $title . '"></iframe>';
}

function piece_export_filename(array $piece): string
{
    $base = function_exists('slugify') ? slugify((string) ($piece['title'] ?? '')) : '';
    if ($base === '') {
        $base = 'piece-' . (int) ($piece['id'] ?? 0);
    }
    return $base . '.html';
}

function piece_export_document(array $piece, array $version): string
{
    $title = htmlspecialchars((string) ($piece['title'] ?? 'Art piece'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $engine = strtolower((string) ($version['engine'] ?? $piece['engine'] ?? 'p5'));
    $html = piece_export_rewrite_media_urls((string) ($version['html_code'] ?? ''));
    if ($engine === 'aframe') {
        $html = piece_aframe_add_crossorigin_to_asset_images($html);
    }
    $css = piece_escape_inline_css(piece_export_rewrite_media_urls((string) ($version['css_code'] ?? '')));
    $code = piece_escape_inline_script(piece_export_rewrite_media_urls((string) ($version['generated_code'] ?? '')));
    $imports = piece_export_imports($engine);
    $bootstrap = piece_export_bootstrap($engine);

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title}</title>
{$imports}
<style>
html,body{margin:0;padding:0;width:100%;height:100%;overflow:hidden;background:#0d0d0f;color:#fff;}
body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}
#runtime-root{width:100vw;height:100vh;overflow:hidden;}
#piece-error{position:fixed;inset:auto 1rem 1rem 1rem;z-index:9999;padding:1rem;border:1px solid #fca5a5;background:#450a0a;color:#fee2e2;font:14px/1.4 ui-monospace,SFMono-Regular,Menlo,monospace;white-space:pre-wrap;display:none;}
canvas{display:block;width:100%;height:100%;}
{$css}
</style>
</head>
<body>
<div id="runtime-root">{$html}</div>
<div id="piece-error" role="alert"></div>
<script>
function showPieceError(error){const el=document.getElementById('piece-error');if(!el)return;el.textContent=(error&&(error.stack||error.message))?(error.stack||error.message):String(error);el.style.display='block';}
window.addEventListener('error',event=>showPieceError(event.error||event.message));
window.addEventListener('unhandledrejection',event=>showPieceError(event.reason||'Unhandled promise rejection'));
</script>
<script>
{$code}
</script>
{$bootstrap}
</body>
</html>
HTML;
}

function piece_escape_inline_script(string $code): string
{
    return str_replace('</script', '<\/script', $code);
}

function piece_escape_inline_css(string $css): string
{
    return str_replace('</style', '<\/style', $css);
}

function piece_export_rewrite_media_urls(string $content): string
{
    $origin = piece_request_origin();
    if ($origin === '') {
        return $content;
    }

    return preg_replace_callback(
        '#(?<![A-Za-z0-9._~/-])/?(?:image/[0-9]+|api/media-assets/[0-9]+|media/[A-Za-z0-9._~/%+-]+)(?:\?[A-Za-z0-9._~%=&+-]*)?#',
        static fn(array $match): string => $origin . '/' . ltrim($match[0], '/'),
        $content
    ) ?? $content;
}

function piece_request_origin(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function piece_aframe_add_crossorigin_to_asset_images(string $html): string
{
    if ($html === '' || stripos($html, '<img') === false) {
        return $html;
    }

    return preg_replace_callback('/<img\b[^>]*>/i', static function (array $match): string {
        $tag = $match[0];
        if (preg_match('/\bcrossorigin\s*=/i', $tag)) {
            return $tag;
        }
        return preg_replace('/\s*\/?>$/', ' crossorigin="anonymous"$0', $tag, 1) ?? $tag;
    }, $html) ?? $html;
}

function piece_export_imports(string $engine): string
{
    return match ($engine) {
        'p5' => '<script src="https://cdnjs.cloudflare.com/ajax/libs/p5.js/1.9.0/p5.min.js"></script>',
        'c2' => '<script src="https://cdn.jsdelivr.net/npm/c2.js@1.0.9/dist/c2.min.js"></script>',
        'three' => '<script type="importmap">' . "\n" . '{"imports":{"three":"https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js","three/addons/":"https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"}}' . "\n" . '</script>',
        'aframe' => '<script src="https://aframe.io/releases/1.6.0/aframe.min.js"></script>',
        default => '',
    };
}

function piece_export_bootstrap(string $engine): string
{
    return match ($engine) {
        'p5' => <<<'HTML'
<script>
try {
  const parent = document.getElementById('canvas-container') || document.getElementById('runtime-root');
  if (typeof window.sketch === 'function' && typeof window.p5 === 'function') new window.p5(window.sketch, parent);
} catch (error) { showPieceError(error); }
</script>
HTML,
        'c2' => <<<'HTML'
<script>
try {
  const canvas = document.getElementById('piece-canvas') || document.querySelector('canvas');
  const context = canvas.getContext('2d');
  const imageCache = new Map();
  function sizeCanvas() {
    const rect = canvas.getBoundingClientRect();
    canvas.width = Math.max(1, Math.floor(rect.width || window.innerWidth || 1280));
    canvas.height = Math.max(1, Math.floor(rect.height || window.innerHeight || 720));
  }
  function startFrame(callback) {
    let count = 0;
    function tick() {
      count++;
      try { callback(count); } catch (error) { showPieceError(error); return; }
      requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  }
  function loadImage(src) {
    if (imageCache.has(src)) return imageCache.get(src);
    const image = new Image();
    image.decoding = 'async';
    image.loading = 'eager';
    image.dataset.creatrLoaded = '0';
    const loaded = new Promise((resolve, reject) => {
      image.onload = () => { image.dataset.creatrLoaded = '1'; resolve(image); };
      image.onerror = () => {
        const message = 'Could not load image asset: ' + src;
        showPieceError(message);
        reject(new Error(message));
      };
    });
    loaded.catch(() => {});
    loaded.__creatrImage = image;
    image.src = src;
    imageCache.set(src, loaded);
    return loaded;
  }
  function resolveImageRef(image) {
    return image && image.__creatrImage ? image.__creatrImage : image;
  }
  function drawImage(image, x, y, width, height) {
    image = resolveImageRef(image);
    if (!image || image.dataset?.creatrLoaded !== '1') return false;
    context.drawImage(image, x, y, width, height);
    return true;
  }
  function drawImageCover(image, x, y, width, height) {
    image = resolveImageRef(image);
    if (!image || image.dataset?.creatrLoaded !== '1') return false;
    const sourceWidth = image.naturalWidth || image.width;
    const sourceHeight = image.naturalHeight || image.height;
    if (!sourceWidth || !sourceHeight || !width || !height) return false;
    const sourceAspect = sourceWidth / sourceHeight;
    const targetAspect = width / height;
    let sx = 0, sy = 0, sw = sourceWidth, sh = sourceHeight;
    if (sourceAspect > targetAspect) { sw = sourceHeight * targetAspect; sx = (sourceWidth - sw) / 2; }
    else { sh = sourceWidth / targetAspect; sy = (sourceHeight - sh) / 2; }
    context.drawImage(image, sx, sy, sw, sh, x, y, width, height);
    return true;
  }
  canvas.style.touchAction = 'none';
  sizeCanvas();
  window.addEventListener('resize', sizeCanvas);
  if (typeof window.sketch === 'function') window.sketch({ c2: window.c2, canvas, startFrame, loadImage, drawImage, drawImageCover });
} catch (error) { showPieceError(error); }
</script>
HTML,
        'three' => <<<'HTML'
<script type="module">
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
try {
  const canvas = document.getElementById('scene') || document.querySelector('canvas') || (() => {
    const created = document.createElement('canvas');
    created.id = 'scene';
    (document.getElementById('container') || document.getElementById('runtime-root')).appendChild(created);
    return created;
  })();
  canvas.style.display = 'block';
  canvas.style.width = '100%';
  canvas.style.height = '100%';
  canvas.style.touchAction = 'none';
  canvas.addEventListener('webglcontextlost', (event) => {
    event.preventDefault();
    showPieceError('WebGL context was lost. The scene may be too complex for this browser.');
  });
  function sizeCanvas() {
    const parent = canvas.parentElement || document.getElementById('runtime-root');
    canvas.width = Math.max(1, parent.clientWidth || window.innerWidth || 1280);
    canvas.height = Math.max(1, parent.clientHeight || window.innerHeight || 720);
  }
  const state = { scene: null, camera: null, renderer: null };
  const instrumentedThree = { ...THREE };
  instrumentedThree.Scene = class extends THREE.Scene {
    constructor(...args) { super(...args); state.scene = this; }
  };
  instrumentedThree.PerspectiveCamera = class extends THREE.PerspectiveCamera {
    constructor(...args) { super(...args); state.camera = this; }
  };
  instrumentedThree.WebGLRenderer = class extends THREE.WebGLRenderer {
    constructor(params) {
      super({ ...(params || {}), canvas, preserveDrawingBuffer: true });
      state.renderer = this;
      const originalSetSize = this.setSize.bind(this);
      this.setSize = (width, height) => originalSetSize(width, height, false);
      const originalRender = this.render.bind(this);
      this.render = (scene, camera) => {
        if (scene) state.scene = scene;
        if (camera) state.camera = camera;
        return originalRender(scene, camera);
      };
    }
  };
  let pieceDrivesOwnRender = false;
  let rafIds = [];
  function startFrame(callback) {
    pieceDrivesOwnRender = true;
    let count = 0;
    function tick() {
      count++;
      try {
        callback(count);
      } catch (error) {
        pieceDrivesOwnRender = false;
        showPieceError(error);
        return;
      }
      const id = requestAnimationFrame(tick);
      rafIds.push(id);
    }
    const id = requestAnimationFrame(tick);
    rafIds.push(id);
    return () => { rafIds.forEach((rafId) => cancelAnimationFrame(rafId)); rafIds = []; };
  }
  sizeCanvas();
  window.THREE = instrumentedThree;
  if (typeof window.sketch === 'function') window.sketch({ THREE: instrumentedThree, canvas, startFrame, width: canvas.width, height: canvas.height, size: { width: canvas.width, height: canvas.height }, OrbitControls });
  if (state.camera && state.renderer && state.scene) {
    const controls = new OrbitControls(state.camera, canvas);
    controls.enableDamping = true;
    controls.enablePan = true;
    let isOrbitActive = false;
    let userHasInteracted = false;
    controls.addEventListener('start', () => { isOrbitActive = true; userHasInteracted = true; });
    controls.addEventListener('end', () => { isOrbitActive = false; });
    function animateControls() {
      const id = requestAnimationFrame(animateControls);
      rafIds.push(id);
      try {
        controls.update();
        if (isOrbitActive) userHasInteracted = true;
        if (!pieceDrivesOwnRender || userHasInteracted) {
          state.renderer.render(state.scene, state.camera);
        }
      } catch (error) {
        showPieceError(error);
      }
    }
    animateControls();
  }
  window.addEventListener('resize', () => {
    sizeCanvas();
    if (state.camera && state.renderer) {
      state.camera.aspect = canvas.width / canvas.height;
      state.camera.updateProjectionMatrix();
      state.renderer.setSize(canvas.width, canvas.height, false);
    }
  });
} catch (error) { showPieceError(error); }
</script>
HTML,
        'aframe' => <<<'HTML'
<script>
try {
  const scene = document.querySelector('a-scene#scene') || document.querySelector('a-scene');
  function startFrame(callback) {
    let count = 0;
    function tick() {
      count++;
      try { callback(count); } catch (error) { showPieceError(error); return; }
      requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  }
  if (scene && typeof window.sketch === 'function') window.sketch({ AFRAME: window.AFRAME, scene, startFrame });
} catch (error) { showPieceError(error); }
</script>
HTML,
        default => <<<'HTML'
<script>
try { if (typeof window.sketch === 'function') window.sketch(); } catch (error) { showPieceError(error); }
</script>
HTML,
    };
}
