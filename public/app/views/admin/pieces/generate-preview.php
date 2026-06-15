<?php

declare(strict_types=1);

$pageTitle = 'AI Generation Preview';
ob_start();

$previewPiece = ['title' => 'AI Generated ' . strtoupper($engine)];
$previewVersion = [
    'html_code' => $htmlCode,
    'css_code' => $cssCode,
    'generated_code' => $generatedCode,
    'engine' => $engine
];

$defaultTitle = 'AI ' . strtoupper($engine) . ' Piece - ' . date('M d, Y H:i');
?>
<div class="admin-container">
    <div class="admin-header-row">
        <h1>AI Generation Preview</h1>
        <div style="display: flex; gap: 0.5rem;">
            <a href="/admin/pieces/generate" class="admin-btn admin-btn-ghost">Discard &amp; Back</a>
        </div>
    </div>

    <div style="background: var(--ink); border: 1px solid var(--line); border-radius: 4px; padding: 0.5rem; margin-bottom: 2rem; box-shadow: var(--shadow);">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem; border-bottom: 1px solid var(--line); margin-bottom: 0.5rem; font-size: 0.85rem; color: #a1a1aa;">
            <span>Live Sandbox Preview (Engine: <strong><?= e(strtoupper($engine)) ?></strong>)</span>
            <button type="button" class="admin-btn admin-btn-ghost" style="padding: 2px 8px; font-size: 0.75rem;" onclick="reloadPreviewIframe()">Reload Sandbox</button>
        </div>
        <div id="preview-iframe-wrapper">
            <?= piece_render_iframe($previewPiece, $previewVersion, 450) ?>
        </div>
    </div>

    <form method="post" action="/admin/pieces/generate/save" class="admin-form">
        <!-- Hidden inputs for AI generation details -->
        <input type="hidden" name="engine" value="<?= e($engine) ?>">
        <input type="hidden" name="generation_vendor" value="<?= e($profile['vendor'] ?? '') ?>">
        <input type="hidden" name="generation_model" value="<?= e($profile['model'] ?? '') ?>">
        <input type="hidden" name="generation_attempt_count" value="<?= (int) $attemptCount ?>">

        <div class="admin-tabs piece-preview-tabs" role="tablist" style="margin-bottom: 1.5rem;">
            <button type="button" class="admin-tab active" data-tab="meta">Metadata</button>
            <button type="button" class="admin-tab" data-tab="html">HTML Code</button>
            <button type="button" class="admin-tab" data-tab="css">CSS Code</button>
            <button type="button" class="admin-tab" data-tab="js">JavaScript Code</button>
        </div>

        <!-- Metadata Tab -->
        <div id="tab-meta" class="piece-tab-panel">
            <div class="field">
                <label for="title">Title</label>
                <input id="title" name="title" type="text" required maxlength="255" value="<?= e($defaultTitle) ?>">
            </div>

            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="draft" selected>Draft (Recommended)</option>
                    <option value="active">Active (Visible in public list)</option>
                    <option value="archived">Archived</option>
                </select>
            </div>

            <div class="field">
                <label for="description">Description (Optional)</label>
                <textarea id="description" name="description" rows="3" placeholder="Brief details about what this piece shows or how it behaves..."></textarea>
            </div>

            <div class="field">
                <label for="prompt">Generation Prompt</label>
                <textarea id="prompt" name="prompt" rows="3" readonly style="background: rgba(255,255,255,0.05); color: var(--ink-soft); cursor: not-allowed;"><?= e($prompt) ?></textarea>
            </div>
        </div>

        <!-- HTML Code Tab -->
        <div id="tab-html" class="piece-tab-panel is-hidden">
            <div class="field">
                <label for="html_code">HTML Mounting Node</label>
                <textarea id="html_code" name="html_code" rows="16" class="code-field" style="font-family: monospace; font-size: 0.9rem;" oninput="updatePreview()"><?= e($htmlCode) ?></textarea>
                <small>The element(s) loaded into the page container.</small>
            </div>
        </div>

        <!-- CSS Code Tab -->
        <div id="tab-css" class="piece-tab-panel is-hidden">
            <div class="field">
                <label for="css_code">CSS Styles</label>
                <textarea id="css_code" name="css_code" rows="16" class="code-field" style="font-family: monospace; font-size: 0.9rem;" oninput="updatePreview()"><?= e($cssCode) ?></textarea>
                <small>Scoped CSS used to style the canvas or mounting nodes.</small>
            </div>
        </div>

        <!-- JS Code Tab -->
        <div id="tab-js" class="piece-tab-panel is-hidden">
            <div class="field">
                <label for="generated_code">JavaScript (window.sketch)</label>
                <textarea id="generated_code" name="generated_code" rows="16" class="code-field" style="font-family: monospace; font-size: 0.9rem;" oninput="updatePreview()"><?= e($generatedCode) ?></textarea>
                <small>The primary drawing/setup loops and handlers.</small>
            </div>
        </div>

        <div class="form-actions" style="margin-top: 2rem; display: flex; gap: 0.5rem;">
            <button type="submit" class="admin-btn">Save and Insert (Create Version 1)</button>
            <a href="/admin/pieces/generate" class="admin-btn admin-btn-ghost">Discard &amp; Restart</a>
        </div>
    </form>

    <script>
    (function () {
        var tabs = document.querySelectorAll('.piece-preview-tabs .admin-tab');
        var panels = {
            meta: document.getElementById('tab-meta'),
            html: document.getElementById('tab-html'),
            css: document.getElementById('tab-css'),
            js: document.getElementById('tab-js')
        };
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                tabs.forEach(function (t) { t.classList.remove('active'); });
                tab.classList.add('active');
                Object.keys(panels).forEach(function (key) {
                    if (key === tab.dataset.tab) {
                        panels[key].classList.remove('is-hidden');
                    } else {
                        panels[key].classList.add('is-hidden');
                    }
                });
            });
        });
    })();

    function reloadPreviewIframe() {
        var wrapper = document.getElementById('preview-iframe-wrapper');
        if (!wrapper) return;
        var iframe = wrapper.querySelector('iframe');
        if (iframe) {
            iframe.srcdoc = iframe.srcdoc; // Triggers reload
        }
    }

    var updateTimeout = null;
    function updatePreview() {
        clearTimeout(updateTimeout);
        updateTimeout = setTimeout(function () {
            var html = document.getElementById('html_code').value;
            var css = document.getElementById('css_code').value;
            var js = document.getElementById('generated_code').value;
            var engine = document.querySelector('input[name="engine"]').value;

            // Call backend endpoint or rebuild local srcdoc structure
            var docTemplate = `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AI Generation Preview</title>
<style>
html,body{margin:0;padding:0;width:100%;height:100%;overflow:hidden;background:#0d0d0f;color:#fff;}
body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}
#runtime-root{width:100vw;height:100vh;overflow:hidden;}
#piece-error{position:fixed;inset:auto 1rem 1rem 1rem;z-index:9999;padding:1rem;border:1px solid #fca5a5;background:#450a0a;color:#fee2e2;font:14px/1.4 ui-monospace,SFMono-Regular,Menlo,monospace;white-space:pre-wrap;display:none;}
canvas{display:block;width:100%;height:100%;}
\${css}
</style>
</head>
<body>
<div id="runtime-root">\${html}</div>
<div id="piece-error" role="alert"></div>
<script type="importmap">
{
  "imports": {
    "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js",
    "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"
  }
}
</script>
<script>
const PIECE_ENGINE = \${JSON.stringify(engine)};
const PIECE_CODE = \${JSON.stringify(js)};
function showPieceError(error) {
  const el = document.getElementById('piece-error');
  if (!el) return;
  el.textContent = (error && (error.stack || error.message)) ? (error.stack || error.message) : String(error);
  el.style.display = 'block';
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
    const state = { scene: null, camera: null, renderer: null };
    let controls = null;
    let rafIds = [];
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
      if (controls) { controls.target.copy(center); controls.update(); }
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
    function startFrame(handler) {
      let count = 0;
      function tick() {
        count++;
        try { handler(count); } catch (error) { showPieceError(error); return; }
        if (count === 15) autoFit();
        const id = requestAnimationFrame(tick);
        rafIds.push(id);
      }
      const id = requestAnimationFrame(tick);
      rafIds.push(id);
      return () => { rafIds.forEach((rafId) => cancelAnimationFrame(rafId)); rafIds = []; };
    }
    window.sketch({ THREE: instrumentedThree, canvas, startFrame, width, height, size: { width, height }, OrbitControls });
    ensureFallbackLighting();
    autoFit();

    if (state.camera && state.renderer) {
      controls = new OrbitControls(state.camera, canvas);
      controls.enableDamping = true;
      controls.enablePan = true;
      const camDir = new mod.Vector3();
      state.camera.getWorldDirection(camDir);
      const camLen = state.camera.position.length();
      controls.target.copy(state.camera.position).addScaledVector(camDir, Math.max(camLen * 0.8, 3));
      autoFit();
      controls.update();

      let consecutiveErrors = 0;
      const animateControls = () => {
        const id = requestAnimationFrame(animateControls);
        rafIds.push(id);
        try {
          ensureFallbackLighting();
          controls.update();
          state.renderer.render(state.scene, state.camera);
          consecutiveErrors = 0;
        } catch (renderError) {
          consecutiveErrors++;
          if (consecutiveErrors === 1) showPieceError(renderError);
          if (consecutiveErrors >= 5) cancelAnimationFrame(id);
        }
      };
      animateControls();
    }

    window.addEventListener('resize', () => {
      sizeCanvas(canvas);
      if (state.renderer && state.camera) {
        state.camera.aspect = canvas.width / canvas.height;
        state.camera.updateProjectionMatrix();
        state.renderer.setSize(canvas.width, canvas.height, false);
      }
    });

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
  bootCanvasRuntime({ c2: {} });
} else {
  runPieceCode();
  if (typeof window.sketch === 'function') {
    try { window.sketch(); } catch (error) { showPieceError(error); }
  }
}
<\/script>
</body>
</html>`;

            var wrapper = document.getElementById('preview-iframe-wrapper');
            if (wrapper) {
                var iframe = wrapper.querySelector('iframe');
                if (iframe) {
                    iframe.srcdoc = docTemplate;
                }
            }
        }, 600);
    }
    </script>
</div>
<?php
$content = ob_get_clean();

require dirname(__DIR__) . '/layout.php';
