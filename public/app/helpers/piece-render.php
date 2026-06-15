<?php

declare(strict_types=1);

function piece_render_document(array $piece, array $version): string
{
    $title = htmlspecialchars((string) ($piece['title'] ?? 'Art piece'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $engine = strtolower((string) ($version['engine'] ?? $piece['engine'] ?? 'p5'));
    $html = (string) ($version['html_code'] ?? '');
    $css = (string) ($version['css_code'] ?? '');
    $code = (string) ($version['generated_code'] ?? '');
    $jsonCode = json_encode($code, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $jsonEngine = json_encode($engine);

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
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
{$css}
</style>
</head>
<body>
<div id="runtime-root">{$html}</div>
<div id="piece-error" role="alert"></div>
<script>
const PIECE_ENGINE = {$jsonEngine};
const PIECE_CODE = {$jsonCode};
function showPieceError(error) {
  const el = document.getElementById('piece-error');
  if (!el) return;
  el.textContent = (error && (error.stack || error.message)) ? (error.stack || error.message) : String(error);
  el.style.display = 'block';
  try { window.parent.postMessage({ type: 'sketch-status', valid: false, error: el.textContent }, '*'); } catch (_) {}
}
window.addEventListener('error', (event) => showPieceError(event.error || event.message));
window.addEventListener('unhandledrejection', (event) => showPieceError(event.reason || 'Unhandled promise rejection'));
function runPieceCode() {
  try {
    const fn = new Function(PIECE_CODE + "\\n//# sourceURL=piece-runtime.js");
    fn();
  } catch (error) {
    showPieceError(error);
  }
}
function findCanvas(id) {
  return document.getElementById(id) || document.querySelector('canvas') || (() => {
    const canvas = document.createElement('canvas');
    canvas.id = id;
    const parent = document.getElementById('canvas-container') || document.getElementById('sketch-container') || document.getElementById('runtime-root');
    parent.appendChild(canvas);
    return canvas;
  })();
}
function sizeCanvas(canvas) {
  const w = Math.max(1, canvas.parentElement?.clientWidth || window.innerWidth || 1280);
  const h = Math.max(1, canvas.parentElement?.clientHeight || window.innerHeight || 720);
  canvas.width = w;
  canvas.height = h;
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
function bootCanvasRuntime(extra) {
  runPieceCode();
  if (typeof window.sketch !== 'function') return;
  const canvas = findCanvas(PIECE_ENGINE === 'c2' ? 'c2-canvas' : 'scene');
  canvas.style.cssText = 'display:block;width:100%;height:100%;';
  sizeCanvas(canvas);
  window.addEventListener('resize', () => sizeCanvas(canvas));
  try { window.sketch({ canvas, startFrame, ...(extra || {}) }); } catch (error) { showPieceError(error); }
}
function bootP5() {
  const script = document.createElement('script');
  script.src = 'https://cdnjs.cloudflare.com/ajax/libs/p5.js/1.9.0/p5.min.js';
  script.onload = () => {
    runPieceCode();
    try {
      if (typeof window.sketch === 'function' && typeof window.p5 === 'function') {
        const parent = document.getElementById('canvas-container') || document.getElementById('sketch-container') || document.getElementById('runtime-root');
        new window.p5(window.sketch, parent);
      }
    } catch (error) { showPieceError(error); }
  };
  script.onerror = () => showPieceError('Could not load p5.js runtime.');
  document.head.appendChild(script);
}
function bootC2() {
  const script = document.createElement('script');
  script.src = 'https://cdn.jsdelivr.net/npm/c2.js@1.0.9/dist/c2.min.js';
  script.onload = () => {
    bootCanvasRuntime({ c2: window.c2 });
  };
  script.onerror = () => showPieceError('Could not load c2.js runtime.');
  document.head.appendChild(script);
}
async function bootThree() {
  try {
    const mod = await import('https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js');
    const { OrbitControls } = await import('https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/controls/OrbitControls.js');
    window.THREE = mod;
    runPieceCode();
    if (typeof window.sketch !== 'function') return;
    const canvas = findCanvas('scene');
    canvas.style.cssText = 'display:block;width:100%;height:100%;';
    sizeCanvas(canvas);
    window.addEventListener('resize', () => sizeCanvas(canvas));
    const state = { scene: null, camera: null, renderer: null };
    const instrumentedThree = { ...mod };
    instrumentedThree.Scene = class extends mod.Scene {
      constructor() { super(); state.scene = this; }
    };
    instrumentedThree.PerspectiveCamera = class extends mod.PerspectiveCamera {
      constructor(...args) { super(...args); state.camera = this; }
    };
    instrumentedThree.WebGLRenderer = class extends mod.WebGLRenderer {
      constructor(params) {
        super({ ...(params || {}), canvas });
        state.renderer = this;
      }
    };
    const width = canvas.width || window.innerWidth || 1280;
    const height = canvas.height || window.innerHeight || 720;
    function autoFit() {
      if (!state.scene || !state.camera) return;
      const box = new mod.Box3();
      state.scene.traverse((obj) => {
        if (obj.isHelper || obj.isLight || obj.isCamera) return;
        if ((obj.isMesh || obj.isLine || obj.isPoints || obj.isSprite) && obj.geometry) {
          obj.geometry.computeBoundingBox?.();
          if (obj.geometry.boundingBox)
            box.union(obj.geometry.boundingBox.clone().applyMatrix4(obj.matrixWorld));
        }
      });
      if (box.isEmpty()) return;
      const center = new mod.Vector3(); box.getCenter(center);
      const size = new mod.Vector3();   box.getSize(size);
      const maxDim = Math.max(size.x, size.y, size.z) || 1;
      const fov = state.camera.fov * (Math.PI / 180);
      const dist = Math.abs(maxDim / 2 / Math.tan(fov / 2)) * 2.2;
      state.camera.position.set(center.x + dist, center.y + dist * 0.4, center.z + dist);
      state.camera.lookAt(center);
      state.camera.updateMatrixWorld(true);
    }
    function ensureFallbackLighting() {
      if (!state.scene?.traverse) return;
      let hasRealLight = false;
      let hasFallback = false;
      const fallbacks = [];
      state.scene.traverse((obj) => {
        if (!obj.isLight) return;
        if (obj.name?.startsWith('__viewer_fallback_')) { hasFallback = true; fallbacks.push(obj); }
        else hasRealLight = true;
      });
      if (hasRealLight) {
        fallbacks.forEach((obj) => state.scene.remove(obj));
        return;
      }
      if (hasFallback) return;
      const amb = new mod.AmbientLight(0xffffff, 0.7);
      amb.name = '__viewer_fallback_ambient__';
      state.scene.add(amb);
      const dir = new mod.DirectionalLight(0xffffff, 0.8);
      dir.position.set(5, 10, 7.5);
      dir.name = '__viewer_fallback_dir__';
      state.scene.add(dir);
    }
    window.sketch({ THREE: instrumentedThree, canvas, startFrame, width, height, size: { width, height }, OrbitControls });
    ensureFallbackLighting();
    autoFit();
    window.parent.postMessage({ type: 'sketch-status', valid: true }, '*');
  } catch (error) {
    showPieceError(error);
  }
}
if (PIECE_ENGINE === 'p5') {
  bootP5();
} else if (PIECE_ENGINE === 'three') {
  bootThree();
} else if (PIECE_ENGINE === 'c2') {
  bootC2();
} else {
  runPieceCode();
  if (typeof window.sketch === 'function') {
    try { window.sketch(); } catch (error) { showPieceError(error); }
  }
}
</script>
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
