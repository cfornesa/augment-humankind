<?php

declare(strict_types=1);

$pageTitle = 'Pieces';

$q      = $q      ?? '';
$engine = $engine ?? '';
$sort   = $sort   ?? 'sort_order';
$dir    = $dir    ?? 'asc';

function pieces_sort_link(string $col, string $label, string $cur, string $curDir, array $carry): string {
    $next  = ($cur === $col && $curDir === 'asc') ? 'desc' : 'asc';
    $arrow = $cur === $col ? ($curDir === 'asc' ? ' &#8593;' : ' &#8595;') : '';
    $qs    = http_build_query(array_merge($carry, ['sort' => $col, 'dir' => $next]));
    return '<a href="?' . $qs . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . $arrow . '</a>';
}

$carry         = array_filter(['q' => $q, 'engine' => $engine], fn($v) => $v !== '');
$isDefaultSort = ($sort === 'sort_order');

ob_start();
?>
<div class="admin-container">
    <span id="reorder-status" class="reorder-status" aria-live="polite"></span>
    <div class="admin-header-row">
        <h1>Art Pieces</h1>
        <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
            <?php if (!empty($pieces)): ?>
                <button type="button" id="btn-regen-all" class="admin-btn admin-btn-ghost">Regenerate All Thumbnails</button>
                <span id="regen-status" style="font-weight: bold; font-size: 0.9rem;"></span>
            <?php endif; ?>
            <a href="/admin/pieces/generate" class="admin-btn">Generate with AI</a>
            <a href="/admin/pieces/create" class="admin-btn admin-btn-ghost">Create Piece</a>
        </div>
    </div>

    <form class="admin-filter-bar" action="/admin/pieces" method="get" role="search">
        <label class="sr-only" for="admin-pieces-q">Search pieces</label>
        <input id="admin-pieces-q" class="admin-filter-input" name="q" type="search"
               value="<?= e($q) ?>" placeholder="Search title, description, prompt…" autocomplete="off">
        <select name="engine" class="admin-filter-select" aria-label="Engine">
            <option value="" <?= $engine === '' ? 'selected' : '' ?>>All engines</option>
            <option value="p5"    <?= $engine === 'p5'    ? 'selected' : '' ?>>P5.js</option>
            <option value="c2"    <?= $engine === 'c2'    ? 'selected' : '' ?>>C2.js</option>
            <option value="three" <?= $engine === 'three' ? 'selected' : '' ?>>Three.js</option>
            <option value="svg"   <?= $engine === 'svg'   ? 'selected' : '' ?>>SVG</option>
        </select>
        <button class="admin-btn admin-btn-sm" type="submit">Filter</button>
        <?php if ($q !== '' || $engine !== '' || !$isDefaultSort): ?>
            <a href="/admin/pieces" class="admin-filter-reset">Reset view</a>
        <?php endif; ?>
    </form>

    <?php if (empty($pieces)): ?>
        <p><?= ($q !== '' || $engine !== '') ? 'No pieces matched your filters.' : 'No art pieces yet. <a href="/admin/pieces/create">Create the first one</a>.' ?></p>
    <?php else: ?>
        <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th></th>
                    <th>Thumbnail</th>
                    <th><?= pieces_sort_link('title', 'Title', $sort, $dir, $carry) ?></th>
                    <th>ID</th>
                    <th><?= pieces_sort_link('engine', 'Engine', $sort, $dir, $carry) ?></th>
                    <th>Art Media</th>
                    <th><?= pieces_sort_link('status', 'Status', $sort, $dir, $carry) ?></th>
                    <th>Versions</th>
                    <th><?= pieces_sort_link('created', 'Created', $sort, $dir, $carry) ?></th>
                    <th><?= pieces_sort_link('updated', 'Updated', $sort, $dir, $carry) ?></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody data-reorder-url="/admin/pieces/reorder" class="<?= !$isDefaultSort ? 'drag-handles-hidden' : '' ?>">
                <?php foreach ($pieces as $piece): ?>
                    <tr data-id="<?= (int) $piece['id'] ?>">
                        <td class="drag-handle" title="Drag to reorder">&#8597;</td>
                        <td class="cell-thumb" style="width: 70px;">
                            <?php if (!empty($piece['thumbnail_url'])): ?>
                                <img src="<?= e($piece['thumbnail_url']) ?>" alt="<?= e((string)($piece['thumbnail_alt_text'] ?? $piece['title'] ?? '')) ?>" style="width: 60px; height: 45px; object-fit: cover; border: 1px solid var(--line); display: block;">
                            <?php else: ?>
                                <div style="width: 60px; height: 45px; border: 1px dashed var(--line); background: var(--paper); display: flex; align-items: center; justify-content: center; font-size: 10px; color: var(--ink-soft);">None</div>
                            <?php endif; ?>
                        </td>
                        <td><?= e($piece['title'] ?? 'Untitled') ?></td>
                        <td><code><?= (int) $piece['id'] ?></code></td>
                        <td><?= e(strtoupper($piece['engine'] ?? 'p5')) ?></td>
                        <td>
                            <?php if (empty($piece['categories'])): ?>
                                <span class="admin-hint">None</span>
                            <?php else: ?>
                                <?= e(implode(', ', array_column($piece['categories'], 'name'))) ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?= e($piece['status'] ?? 'active') ?>">
                                <?= e($piece['status'] ?? 'active') ?>
                            </span>
                        </td>
                        <td><?= (int) ($piece['version_count'] ?? 0) ?></td>
                        <td><?= e($piece['created_at'] ?? '') ?></td>
                        <td><?= e($piece['updated_at'] ?? '') ?></td>
                        <td class="admin-actions-cell">
                                <a href="/pieces/<?= (int) $piece['id'] ?>" target="_blank" class="admin-btn admin-btn-sm admin-btn-ghost">View</a>
                                <a href="/immersive/pieces/<?= (int) $piece['id'] ?>" target="_blank" class="admin-btn admin-btn-sm admin-btn-ghost">Immersive</a>
                                <button type="button" class="admin-btn admin-btn-sm admin-btn-ghost btn-capture-piece-thumb" data-id="<?= (int) $piece['id'] ?>">Generate Thumbnail</button>
                                <a href="/admin/pieces/<?= (int) $piece['id'] ?>/versions" class="admin-btn admin-btn-sm admin-btn-ghost">Versions</a>
                                <a href="/admin/pieces/<?= (int) $piece['id'] ?>/edit" class="admin-btn admin-btn-sm admin-btn-ghost">Edit</a>
                                <?php if (($piece['status'] ?? 'active') === 'draft'): ?>
                                    <form method="post" action="/admin/pieces/<?= (int) $piece['id'] ?>/set-status" class="inline-form">
                                        <input type="hidden" name="status" value="active">
                                        <button type="submit" class="admin-btn admin-btn-sm">Publish</button>
                                    </form>
                                <?php elseif (($piece['status'] ?? 'active') === 'active'): ?>
                                    <form method="post" action="/admin/pieces/<?= (int) $piece['id'] ?>/set-status" class="inline-form">
                                        <input type="hidden" name="status" value="archived">
                                        <button type="submit" class="admin-btn admin-btn-sm admin-btn-ghost">Archive</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="/admin/pieces/<?= (int) $piece['id'] ?>/set-status" class="inline-form">
                                        <input type="hidden" name="status" value="active">
                                        <button type="submit" class="admin-btn admin-btn-sm admin-btn-ghost">Restore</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" action="/admin/pieces/<?= (int) $piece['id'] ?>/delete" class="inline-form" onsubmit="return confirm('Move this piece to trash?')">
                                    <button type="submit" class="admin-btn admin-btn-sm admin-btn-danger">Delete</button>
                                </form>
                            </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    async function convertSvgToCanvas(svgElement, width, height) {
        return new Promise(function (resolve, reject) {
            try {
                var svgClone = svgElement.cloneNode(true);
                svgClone.setAttribute('width', width);
                svgClone.setAttribute('height', height);
                if (!svgClone.getAttribute('viewBox')) {
                    var w = svgElement.getAttribute('width') || svgElement.clientWidth || width;
                    var h = svgElement.getAttribute('height') || svgElement.clientHeight || height;
                    svgClone.setAttribute('viewBox', '0 0 ' + w + ' ' + h);
                }

                // Copy computed styles for accurate vector rendering
                var liveEls = [svgElement].concat(Array.from(svgElement.querySelectorAll("*")));
                var cloneEls = [svgClone].concat(Array.from(svgClone.querySelectorAll("*")));
                var props = ["transform", "transform-origin", "opacity", "fill", "stroke", "stroke-width", "cx", "cy", "r", "x", "y", "width", "height", "d", "stop-color", "offset", "filter", "display"];
                liveEls.forEach(function (liveEl, i) {
                    var cloneEl = cloneEls[i];
                    if (!cloneEl) return;
                    var s = window.getComputedStyle(liveEl);
                    props.forEach(function (p) {
                        var val = s.getPropertyValue(p);
                        if (val) cloneEl.style.setProperty(p, val);
                    });
                });

                // Disable animations and transitions in the cloned SVG during capture
                var styleEl = document.createElementNS("http://www.w3.org/2000/svg", "style");
                styleEl.textContent = "* { animation: none !important; transition: none !important; }";
                svgClone.insertBefore(styleEl, svgClone.firstChild);

                var serialized = new XMLSerializer().serializeToString(svgClone);
                var svgData = "data:image/svg+xml;charset=utf-8," + encodeURIComponent(serialized);
                var img = new Image();
                img.onload = function () {
                    var canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    var ctx = canvas.getContext('2d');
                    if (ctx) {
                        ctx.fillStyle = '#0d0d0f';
                        ctx.fillRect(0, 0, width, height);
                        ctx.drawImage(img, 0, 0, width, height);
                    }
                    resolve(canvas);
                };
                img.onerror = function () {
                    reject(new Error('Failed to load SVG image source.'));
                };
                img.src = svgData;
            } catch (e) {
                reject(e);
            }
        });
    }

    // 1. Render Helper (identical to form.php)
    function renderDocumentJS(title, engine, html, css, js) {
        var jsonEngine = JSON.stringify(engine);
        var jsonCode = JSON.stringify(js);
        return '<!DOCTYPE html>\n<html lang="en">\n<head>\n<meta charset="utf-8">\n<meta name="viewport" content="width=device-width, initial-scale=1">\n<title>' + title + '</title>\n<script type="importmap">\n{\n  "imports": {\n    "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js",\n    "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"\n  }\n}\n<\/script>\n<style>\nhtml,body{margin:0;padding:0;width:100%;height:100%;overflow:hidden;background:#0d0d0f;color:#fff;}\nbody{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}\n#runtime-root{width:100vw;height:100vh;overflow:hidden;}\n#piece-error{position:fixed;inset:auto 1rem 1rem 1rem;z-index:9999;padding:1rem;border:1px solid #fca5a5;background:#450a0a;color:#fee2e2;font:14px/1.4 ui-monospace,SFMono-Regular,Menlo,monospace;white-space:pre-wrap;display:none;}\ncanvas{display:block;width:100%;height:100%;}\n' + css + '\n</style>\n</head>\n<body>\n<div id="runtime-root">' + html + '</div>\n<div id="piece-error" role="alert"></div>\n<script>\nconst PIECE_ENGINE = ' + jsonEngine + ';\nconst PIECE_CODE = ' + jsonCode + ';\nfunction showPieceError(error) {\n  const el = document.getElementById("piece-error");\n  if (!el) return;\n  el.textContent = (error && (error.stack || error.message)) ? (error.stack || error.message) : String(error);\n  el.style.display = "block";\n}\nwindow.addEventListener("error", (event) => showPieceError(event.error || event.message));\nwindow.addEventListener("unhandledrejection", (event) => showPieceError(event.reason || "Unhandled promise rejection"));\nfunction runPieceCode() {\n  try {\n    const fn = new Function(PIECE_CODE + "\\n//# sourceURL=piece-runtime.js");\n    fn();\n  } catch (error) {\n    showPieceError(error);\n  }\n}\nfunction findCanvas(id) {\n  return document.getElementById(id) || document.querySelector("canvas") || (() => {\n    const canvas = document.createElement("canvas");\n    canvas.id = id;\n    const parent = document.getElementById("canvas-container") || document.getElementById("sketch-container") || document.getElementById("runtime-root");\n    parent.appendChild(canvas);\n    return canvas;\n  })();\n}\nfunction sizeCanvas(canvas) {\n  const w = Math.max(1, canvas.parentElement?.clientWidth || window.innerWidth || 1280);\n  const h = Math.max(1, canvas.parentElement?.clientHeight || window.innerHeight || 720);\n  canvas.width = w;\n  canvas.height = h;\n}\nfunction startFrame(callback) {\n  let count = 0;\n  function tick() {\n    count++;\n    try { callback(count); } catch (error) { showPieceError(error); return; }\n    requestAnimationFrame(tick);\n  }\n  requestAnimationFrame(tick);\n}\nfunction bootCanvasRuntime(extra) {\n  runPieceCode();\n  if (typeof window.sketch !== "function") return;\n  const canvas = findCanvas(PIECE_ENGINE === "c2" ? "c2-canvas" : "scene");\n  canvas.style.cssText = "display:block;width:100%;height:100%;";\n  sizeCanvas(canvas);\n  window.addEventListener("resize", () => sizeCanvas(canvas));\n  try { window.sketch({ canvas, startFrame, ...(extra || {}) }); } catch (error) { showPieceError(error); }\n}\nfunction bootP5() {\n  const script = document.createElement("script");\n  script.src = "https://cdnjs.cloudflare.com/ajax/libs/p5.js/1.9.0/p5.min.js";\n  script.onload = () => {\n    runPieceCode();\n    try {\n      if (typeof window.sketch === "function" && typeof window.p5 === "function") {\n        const parent = document.getElementById("canvas-container") || document.getElementById("sketch-container") || document.getElementById("runtime-root");\n        new window.p5(window.sketch, parent);\n      }\n    } catch (error) { showPieceError(error); }\n  };\n  script.onerror = () => showPieceError("Could not load p5.js runtime.");\n  document.head.appendChild(script);\n}\nfunction bootC2() {\n  const script = document.createElement("script");\n  script.src = "https://cdn.jsdelivr.net/npm/c2.js@1.0.9/dist/c2.min.js";\n  script.onload = () => {\n    bootCanvasRuntime({ c2: window.c2 });\n  };\n  script.onerror = () => showPieceError("Could not load c2.js runtime.");\n  document.head.appendChild(script);\n}\nasync function bootThree() {\n  try {\n    const mod = await import("https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js");\n    const { OrbitControls } = await import("https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/controls/OrbitControls.js");\n    window.THREE = mod;\n    runPieceCode();\n    if (typeof window.sketch !== "function") return;\n    const canvas = findCanvas("scene");\n    canvas.style.cssText = "display:block;width:100%;height:100%;";\n    sizeCanvas(canvas);\n    const state = { scene: null, camera: null, renderer: null };\n    let controls = null;\n    let rafIds = [];\n    const instrumentedThree = { ...mod };\n    instrumentedThree.Scene = class extends mod.Scene {\n      constructor() { super(); state.scene = this; }\n    };\n    instrumentedThree.PerspectiveCamera = class extends mod.PerspectiveCamera {\n      constructor(...args) { super(...args); state.camera = this; }\n    };\n    instrumentedThree.WebGLRenderer = class extends mod.WebGLRenderer {\n      constructor(params) {\n        super({ ...(params || {}), canvas });\n        state.renderer = this;\n        const _origSetSize = this.setSize.bind(this);\n        this.setSize = (w, h) => _origSetSize(w, h, false);\n        const _origRender = this.render.bind(this);\n        this.render = (sc, cam) => {\n          if (sc) state.scene = sc;\n          if (cam) state.camera = cam;\n          return _origRender(sc, cam);\n        };\n      }\n    };\n    const width = canvas.width || window.innerWidth || 1280;\n    const height = canvas.height || window.innerHeight || 720;\n    function autoFit() {\n      if (!state.scene || !state.camera) return;\n      const box = new mod.Box3();\n      state.scene.traverse((obj) => {\n        if (obj.isHelper || obj.isLight || obj.isCamera) return;\n        if (obj.isPoints) return;\n        if (obj.material) {\n          const mat = obj.material;\n          if (mat.side === 1 || (Array.isArray(mat) && mat.some(m => m.side === 1))) return;\n        }\n        const name = (obj.name || "").toLowerCase();\n        if (name.includes("sky") || name.includes("background") || name.includes("env") || name.includes("floor") || name.includes("ground") || name.includes("grid") || name.includes("dome") || name.includes("space") || name.includes("star")) return;\n        if ((obj.isMesh || obj.isLine || obj.isSprite) && obj.geometry) {\n          obj.geometry.computeBoundingBox?.();\n          if (obj.geometry.boundingBox) {\n            const worldBox = obj.geometry.boundingBox.clone().applyMatrix4(obj.matrixWorld);\n            const worldSize = new mod.Vector3();\n            worldBox.getSize(worldSize);\n            if (worldSize.x >= 30 || worldSize.y >= 30 || worldSize.z >= 30) return;\n            if (obj.geometry.type === "PlaneGeometry" || obj.geometry.type === "PlaneBufferGeometry") {\n              if (worldSize.x >= 15 || worldSize.y >= 15 || worldSize.z >= 15) return;\n            }\n            box.union(worldBox);\n          }\n        }\n      });\n      if (box.isEmpty()) return;\n      const center = new mod.Vector3(); box.getCenter(center);\n      const size = new mod.Vector3();   box.getSize(size);\n      if (state.camera.position.lengthSq() > 0.01) {\n        if (controls) { controls.target.copy(center); controls.update(); }\n        return;\n      }\n      const maxDim = Math.max(size.x, size.y, size.z) || 1;\n      const fov = state.camera.fov * (Math.PI / 180);\n      const dist = Math.abs(maxDim / 2 / Math.tan(fov / 2)) * 0.63;\n      state.camera.position.set(center.x + dist, center.y + dist * 0.4, center.z + dist);\n      state.camera.lookAt(center);\n      state.camera.updateMatrixWorld(true);\n      if (controls) { controls.target.copy(center); controls.update(); }\n    }\n    function ensureFallbackLighting() {\n      if (!state.scene?.traverse) return;\n      let hasRealLight = false;\n      let hasFallback = false;\n      const fallbacks = [];\n      state.scene.traverse((obj) => {\n        if (!obj.isLight) return;\n        if (obj.name?.startsWith("__viewer_fallback_")) { hasFallback = true; fallbacks.push(obj); }\n        else hasRealLight = true;\n      });\n      if (hasRealLight) {\n        fallbacks.forEach((obj) => state.scene.remove(obj));\n        return;\n      }\n      if (hasFallback) return;\n      const amb = new mod.AmbientLight(0xffffff, 0.7);\n      amb.name = "__viewer_fallback_ambient__";\n      state.scene.add(amb);\n      const dir = new mod.DirectionalLight(0xffffff, 0.8);\n      dir.position.set(5, 10, 7.5);\n      dir.name = "__viewer_fallback_dir__";\n      state.scene.add(dir);\n    }\n    function startFrame(handler) {\n      let count = 0;\n      function tick() {\n        count++;\n        try { handler(count); } catch (error) { showPieceError(error); return; }\n        if (count === 15) autoFit();\n        const id = requestAnimationFrame(tick);\n        rafIds.push(id);\n      }\n      const id = requestAnimationFrame(tick);\n      rafIds.push(id);\n      return () => { rafIds.forEach((rafId) => cancelAnimationFrame(rafId)); rafIds = []; };\n    }\n    window.THREE = instrumentedThree;\n    window.sketch({ THREE: instrumentedThree, canvas, startFrame, width, height, size: { width, height }, OrbitControls });\n    ensureFallbackLighting();\n    autoFit();\n\n    if (state.camera && state.renderer) {\n      controls = new OrbitControls(state.camera, canvas);\n      controls.enableDamping = true;\n      controls.enablePan = true;\n      const camDir = new mod.Vector3();\n      state.camera.getWorldDirection(camDir);\n      const camLen = state.camera.position.length();\n      controls.target.copy(state.camera.position).addScaledVector(camDir, Math.max(camLen * 0.8, 3));\n      autoFit();\n      controls.update();\n\n      let consecutiveErrors = 0;\n      const animateControls = () => {\n        const id = requestAnimationFrame(animateControls);\n        rafIds.push(id);\n        try {\n          ensureFallbackLighting();\n          controls.update();\n          state.renderer.render(state.scene, state.camera);\n          consecutiveErrors = 0;\n        } catch (renderError) {\n          consecutiveErrors++;\n          if (consecutiveErrors === 1) showPieceError(renderError);\n          if (consecutiveErrors >= 5) cancelAnimationFrame(id);\n        }\n      };\n      animateControls();\n    }\n\n    window.addEventListener("resize", () => {\n      sizeCanvas(canvas);\n      if (state.renderer && state.camera) {\n        state.camera.aspect = canvas.width / canvas.height;\n        state.camera.updateProjectionMatrix();\n        state.renderer.setSize(canvas.width, canvas.height, false);\n      }\n    });\n\n    window.parent.postMessage({ type: "sketch-status", valid: true }, "*");\n  } catch (error) {\n    showPieceError(error);\n  }\n}\nif (PIECE_ENGINE === "p5") {\n  bootP5();\n} else if (PIECE_ENGINE === "three") {\n  bootThree();\n} else if (PIECE_ENGINE === "c2") {\n  bootC2();\n} else {\n  runPieceCode();\n  if (typeof window.sketch === "function") {\n    try { window.sketch(); } catch (error) { showPieceError(error); }\n  }\n}\n<\/script>\n</body>\n</html>';
    }

    // Register click via event delegation on document
    document.addEventListener('click', async function (event) {
        var btnIndividual = event.target.closest('.btn-capture-piece-thumb');
        if (btnIndividual) {
            event.preventDefault();
            var id = btnIndividual.dataset.id;
            var row = btnIndividual.closest('tr');
            var cellThumb = row.querySelector('.cell-thumb');
            var originalText = btnIndividual.textContent;
            btnIndividual.disabled = true;
            btnIndividual.textContent = 'Generating…';
            btnIndividual.style.color = 'var(--ink-soft)';

            var captureFrame = null;

            try {
                // Fetch piece details
                var resp = await fetch('/embed/pieces/' + id + '/data');
                if (!resp.ok) {
                    throw new Error('Fetch failed: ' + resp.status);
                }
                var data = await resp.json();

                if (!data.generatedCode && !data.htmlCode) {
                    throw new Error('This piece has no code yet — open it in Edit and add JS before generating a thumbnail.');
                }

                var srcdoc = renderDocumentJS(
                    data.title || 'Art piece',
                    data.engine || 'p5',
                    data.htmlCode || '',
                    data.cssCode || '',
                    data.generatedCode || ''
                );

                // Inject preserveDrawingBuffer:true for Three.js
                if (data.engine === 'three') {
                    srcdoc = srcdoc.replace(
                        'super({ ...(params || {}), canvas });',
                        'super({ ...(params || {}), canvas, preserveDrawingBuffer: true });'
                    );
                }

                // Mount off-screen iframe
                captureFrame = document.createElement('iframe');
                captureFrame.style.cssText = 'position:fixed;left:-9999px;top:-9999px;width:960px;height:540px;border:none;';
                captureFrame.sandbox = 'allow-scripts allow-same-origin';
                document.body.appendChild(captureFrame);

                captureFrame.srcdoc = srcdoc;
                await new Promise(function (resolve) {
                    captureFrame.onload = resolve;
                    setTimeout(resolve, 800);
                });

                // Three.js loads ~1MB from CDN cold; give it an extra head-start before polling
                var initialWait = (data.engine === 'three') ? 6000 : 500;
                await new Promise(function (resolve) { setTimeout(resolve, initialWait); });

                // Poll for canvas/svg up to ~20s to allow CDN runtimes to load and render
                var canvas = null;
                for (var attempt = 0; attempt < 40 && !canvas; attempt++) {
                    await new Promise(function (resolve) { setTimeout(resolve, 500); });
                    var iframeDoc = captureFrame.contentDocument;
                    canvas = iframeDoc && iframeDoc.querySelector('canvas');
                    if (!canvas) {
                        var svg = iframeDoc && iframeDoc.querySelector('svg');
                        if (svg) {
                            canvas = await convertSvgToCanvas(svg, 960, 540);
                        }
                    }
                }

                if (!canvas) {
                    throw new Error('No canvas or svg element found after waiting. The piece code may have an error, or the runtime did not finish loading.');
                }

                var imageData = canvas.toDataURL('image/png');
                document.body.removeChild(captureFrame);
                captureFrame = null;

                // Upload
                var formData = new FormData();
                formData.append('image_data', imageData);

                var uploadResp = await fetch('/admin/pieces/' + id + '/capture-thumbnail', {
                    method: 'POST',
                    body: formData
                });

                if (!uploadResp.ok) {
                    var err = await uploadResp.json();
                    throw new Error(err.error || 'Server error');
                }

                var res = await uploadResp.json();
                
                // Update cell thumbnail
                if (cellThumb) {
                    cellThumb.innerHTML = '<img src="' + res.url + '?t=' + Date.now() + '" alt="" style="width: 60px; height: 45px; object-fit: cover; border: 1px solid var(--line); display: block;">';
                }
                
                btnIndividual.textContent = 'Generated!';
                btnIndividual.style.color = 'var(--green)';
                setTimeout(function () {
                    btnIndividual.textContent = originalText;
                    btnIndividual.style.color = '';
                    btnIndividual.disabled = false;
                }, 3000);
            } catch (err) {
                console.error('Individual capture failed for ID ' + id + ':', err);
                alert('Thumbnail generation failed: ' + err.message);
                btnIndividual.textContent = 'Failed';
                btnIndividual.style.color = 'var(--red)';
                setTimeout(function () {
                    btnIndividual.textContent = originalText;
                    btnIndividual.style.color = '';
                    btnIndividual.disabled = false;
                }, 3000);
                if (captureFrame && captureFrame.parentNode) {
                    document.body.removeChild(captureFrame);
                }
            }
            return;
        }

        var btnRegen = event.target.closest('#btn-regen-all');
        if (!btnRegen) return;

        event.preventDefault();
        console.log('Regenerate All Thumbnails click event intercepted.');

        var status = document.getElementById('regen-status');
        if (!status) {
            console.error('regen-status element not found in DOM.');
            alert('Configuration Error: regen-status container not found.');
            return;
        }

        var rows = document.querySelectorAll('tbody tr[data-id]');
        var pieceIds = Array.from(rows).map(function (row) {
            return row.dataset.id;
        });

        console.log('Found ' + pieceIds.length + ' pieces to process:', pieceIds);

        if (pieceIds.length === 0) {
            status.style.color = 'var(--ink)';
            status.textContent = 'No pieces found.';
            return;
        }

        if (!confirm('Are you sure you want to regenerate thumbnails for all ' + pieceIds.length + ' pieces sequentially? This renders each piece in a background frame.')) {
            console.log('Regeneration cancelled by user.');
            return;
        }

        btnRegen.disabled = true;
        btnRegen.textContent = 'Regenerating…';
        status.style.color = 'var(--yellow)';

        var successCount = 0;
        var failCount = 0;

        for (var i = 0; i < pieceIds.length; i++) {
            var id = pieceIds[i];
            console.log('Regenerating piece ' + (i + 1) + '/' + pieceIds.length + ' (ID: ' + id + ')');
            status.textContent = 'Processing ' + (i + 1) + '/' + pieceIds.length + ' (ID: ' + id + ')';

            try {
                // Fetch piece details
                var resp = await fetch('/embed/pieces/' + id + '/data');
                if (!resp.ok) {
                    throw new Error('Fetch failed: ' + resp.status);
                }
                var data = await resp.json();

                if (!data.generatedCode && !data.htmlCode) {
                    throw new Error('No code');
                }

                var srcdoc = renderDocumentJS(
                    data.title || 'Art piece',
                    data.engine || 'p5',
                    data.htmlCode || '',
                    data.cssCode || '',
                    data.generatedCode || ''
                );

                // Inject preserveDrawingBuffer:true for Three.js
                if (data.engine === 'three') {
                    srcdoc = srcdoc.replace(
                        'super({ ...(params || {}), canvas });',
                        'super({ ...(params || {}), canvas, preserveDrawingBuffer: true });'
                    );
                }

                // Mount off-screen iframe
                var captureFrame = document.createElement('iframe');
                captureFrame.style.cssText = 'position:fixed;left:-9999px;top:-9999px;width:960px;height:540px;border:none;';
                captureFrame.sandbox = 'allow-scripts allow-same-origin';
                document.body.appendChild(captureFrame);

                captureFrame.srcdoc = srcdoc;
                await new Promise(function (resolve) {
                    captureFrame.onload = resolve;
                    setTimeout(resolve, 800);
                });

                // Three.js loads ~1MB from CDN cold; give it an extra head-start before polling
                var initialWait = (data.engine === 'three') ? 6000 : 500;
                await new Promise(function (resolve) { setTimeout(resolve, initialWait); });

                // Poll for canvas/svg up to ~20s
                var canvas = null;
                for (var attempt = 0; attempt < 40 && !canvas; attempt++) {
                    await new Promise(function (resolve) { setTimeout(resolve, 500); });
                    var iframeDoc = captureFrame.contentDocument;
                    canvas = iframeDoc && iframeDoc.querySelector('canvas');
                    if (!canvas) {
                        var svg = iframeDoc && iframeDoc.querySelector('svg');
                        if (svg) {
                            canvas = await convertSvgToCanvas(svg, 960, 540);
                        }
                    }
                }

                if (!canvas) {
                    throw new Error('No canvas or svg element found');
                }

                var imageData = canvas.toDataURL('image/png');
                document.body.removeChild(captureFrame);

                // Upload
                var formData = new FormData();
                formData.append('image_data', imageData);

                var uploadResp = await fetch('/admin/pieces/' + id + '/capture-thumbnail', {
                    method: 'POST',
                    body: formData
                });

                if (!uploadResp.ok) {
                    var err = await uploadResp.json();
                    throw new Error(err.error || 'Server error');
                }

                console.log('Successfully regenerated thumbnail for piece ID ' + id);
                successCount++;
            } catch (err) {
                console.error('Failed for piece ID ' + id + ':', err);
                failCount++;
            }

            // Brief delay to allow garbage collection and context release
            await new Promise(function (resolve) { setTimeout(resolve, 500); });
        }

        status.style.color = failCount > 0 ? 'var(--red)' : 'var(--green)';
        status.textContent = 'Done! Success: ' + successCount + ', Failed: ' + failCount;
        btnRegen.disabled = false;
        btnRegen.textContent = 'Regenerate All Thumbnails';

        if (successCount > 0) {
            setTimeout(function () {
                window.location.reload();
            }, 2000);
        }
    });
})();
</script>
<?php
$content = ob_get_clean();

require dirname(__DIR__) . '/layout.php';
