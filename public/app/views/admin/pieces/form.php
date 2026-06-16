<?php

declare(strict_types=1);

$isEdit = !empty($piece['id']);
$pageTitle = $isEdit ? 'Edit Piece' : 'Create Piece';

ob_start();
$piece = $piece ?? [];
$profiles = $profiles ?? [];
$preferredProfileId = $preferredProfileId ?? null;
?>
<style>
.editor-workspace {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    margin-top: 1rem;
    width: 100%;
}

.workspace-mobile-toggle {
    display: none;
    gap: 0.5rem;
    border-bottom: 3px solid var(--line);
    padding-bottom: 1rem;
    margin-bottom: 0.5rem;
}

.workspace-layout {
    display: grid;
    grid-template-columns: 1.1fr 0.9fr;
    gap: 2rem;
    align-items: start;
    width: 100%;
}

.workspace-pane {
    background: var(--white);
    border: 3px solid var(--line);
    box-shadow: 6px 6px 0 var(--line);
    padding: 1.5rem;
}

.pane-preview {
    position: sticky;
    top: 2rem;
    padding: 0;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    height: calc(100vh - 160px);
    min-height: 500px;
    background: #0d0d0f;
}

.preview-container {
    display: flex;
    flex-direction: column;
    height: 100%;
    width: 100%;
}

.preview-header {
    background: var(--line);
    color: var(--white);
    padding: 0.75rem 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: bold;
}

.preview-header h3 {
    margin: 0;
    font-size: 1rem;
}

.preview-dimensions {
    font-family: monospace;
    font-size: 0.85rem;
    background: rgba(255, 255, 255, 0.15);
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
}

.preview-stage {
    flex: 1;
    position: relative;
    background: #0d0d0f;
    min-height: 0;
    width: 100%;
    height: 100%;
}

.preview-stage iframe {
    width: 100%;
    height: 100%;
    border: 0;
    display: block;
}

.ai-banner {
    display: none;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    background: var(--yellow);
    border: 3px solid var(--line);
    box-shadow: 4px 4px 0 var(--line);
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    font-weight: 800;
}

.ai-banner span {
    font-size: 0.95rem;
    color: var(--ink);
}

.ai-banner-actions {
    display: flex;
    gap: 0.5rem;
}

.ai-banner .admin-btn {
    min-height: 2.2rem;
    padding: 0.35rem 1rem;
    font-size: 0.9rem;
    box-shadow: 3px 3px 0 var(--line);
}

.ai-banner .admin-btn:hover {
    box-shadow: 1px 1px 0 var(--line);
    transform: translate(2px, 2px);
}

.piece-tab-panel {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.piece-tab-panel.is-hidden {
    display: none !important;
}

.code-field {
    width: 100%;
    box-sizing: border-box;
    font-family: monospace;
    font-size: 0.9rem;
    line-height: 1.4;
    padding: 0.75rem;
    border: 3px solid var(--line);
    background: #fafafa;
    color: #1a1a1a;
}

.code-field:focus {
    outline: none;
    border-color: var(--yellow);
}

@media (max-width: 1023px) {
    .workspace-mobile-toggle {
        display: flex;
    }
    
    .workspace-layout {
        grid-template-columns: 1fr;
    }
    
    .pane-preview {
        position: static;
        height: calc(100vh - 180px);
        min-height: 400px;
    }
    
    .workspace-layout .pane-editor.is-hidden-mobile,
    .workspace-layout .pane-preview.is-hidden-mobile {
        display: none !important;
    }
}
</style>

<div class="admin-container">
    <div class="admin-header-row">
        <h1><?= $isEdit ? 'Edit Piece' : 'Create Piece' ?></h1>
        <div style="display: flex; gap: 0.5rem;">
            <?php if ($isEdit): ?>
                <a href="/admin/pieces/<?= (int) $piece['id'] ?>/versions" class="admin-btn admin-btn-ghost">Versions</a>
            <?php endif; ?>
            <a href="/admin/pieces" class="admin-btn admin-btn-ghost">Back</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="form-status form-status-error" role="alert">
            <p><?= e($error) ?></p>
        </div>
    <?php endif; ?>

    <!-- AI Refine Preview Banner -->
    <div id="ai-suggestion-banner" class="ai-banner" role="status">
        <span>AI suggested changes are loaded. Review code tabs and preview before deciding:</span>
        <div class="ai-banner-actions">
            <button type="button" id="btn-ai-accept" class="admin-btn">Accept Changes</button>
            <button type="button" id="btn-ai-reject" class="admin-btn admin-btn-ghost">Reject</button>
        </div>
    </div>

    <div class="editor-workspace">
        <!-- Responsive toggle bar for mobile/tablet -->
        <div class="workspace-mobile-toggle" role="navigation" aria-label="Viewport Views">
            <button type="button" id="toggle-view-editor" class="admin-btn active" style="flex: 1; justify-content: center;">Edit Code</button>
            <button type="button" id="toggle-view-preview" class="admin-btn" style="flex: 1; justify-content: center;">Full Canvas</button>
        </div>

        <div class="workspace-layout">
            <!-- Left Pane: Editor Form -->
            <div class="workspace-pane pane-editor">
                <form method="post" class="admin-form" id="piece-editor-form">
                    <div class="admin-tabs piece-edit-tabs" role="tablist" style="margin-bottom: 1.5rem; display: flex; flex-wrap: wrap; gap: 0.25rem;">
                        <button type="button" class="admin-tab active" data-tab="meta" role="tab" aria-selected="true">Metadata</button>
                        <button type="button" class="admin-tab" data-tab="html" role="tab" aria-selected="false">HTML</button>
                        <button type="button" class="admin-tab" data-tab="css" role="tab" aria-selected="false">CSS</button>
                        <button type="button" class="admin-tab" data-tab="js" role="tab" aria-selected="false">JS</button>
                        <button type="button" class="admin-tab" data-tab="ai" role="tab" aria-selected="false" style="border-color: var(--yellow); background: rgba(254, 224, 72, 0.1);">AI Refine ✨</button>
                    </div>

                    <!-- Metadata Tab -->
                    <div id="tab-meta" class="piece-tab-panel" role="tabpanel">
                        <div class="field">
                            <label for="title">Title</label>
                            <input id="title" name="title" type="text" required maxlength="255"
                                   value="<?= e($piece['title'] ?? '') ?>">
                        </div>

                        <div class="field-grid">
                            <div class="field">
                                <label for="engine">Engine</label>
                                <select id="engine" name="engine">
                                    <option value="p5" <?= ($piece['engine'] ?? 'p5') === 'p5' ? 'selected' : '' ?>>P5.js</option>
                                    <option value="c2" <?= ($piece['engine'] ?? '') === 'c2' ? 'selected' : '' ?>>C2.js</option>
                                    <option value="three" <?= ($piece['engine'] ?? '') === 'three' ? 'selected' : '' ?>>Three.js</option>
                                    <option value="svg" <?= ($piece['engine'] ?? '') === 'svg' ? 'selected' : '' ?>>SVG</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="status">Status</label>
                                <select id="status" name="status">
                                    <option value="active" <?= ($piece['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="draft" <?= ($piece['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                                    <option value="archived" <?= ($piece['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Archived</option>
                                </select>
                            </div>
                        </div>

                        <fieldset class="form-fieldset">
                            <legend>Art Media</legend>
                            <?php if (empty($artMedia ?? [])): ?>
                                <p class="admin-hint">No art media terms yet. Create them under <a href="/admin/art-media">Art Media</a>.</p>
                            <?php else: ?>
                                <div class="checkbox-sortable-list">
                                    <?php foreach (($artMedia ?? []) as $medium): ?>
                                        <?php $isChecked = in_array((int) $medium['id'], $assignedCategoryIds ?? [], true); ?>
                                        <label class="checkbox-sortable-item">
                                            <input
                                                type="checkbox"
                                                name="category_ids[]"
                                                value="<?= (int) $medium['id'] ?>"
                                                <?= $isChecked ? 'checked' : '' ?>
                                            >
                                            <span><?= e($medium['name']) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </fieldset>

                        <div class="field">
                            <label for="thumbnail_url">Thumbnail URL</label>
                            <input id="thumbnail_url" name="thumbnail_url" type="url" maxlength="2048"
                                   value="<?= e($piece['thumbnail_url'] ?? '') ?>">
                            <?php if (!empty($piece['thumbnail_url'])): ?>
                                <img id="capture-preview-img" src="<?= e($piece['thumbnail_url'] ?? '') ?>"
                                     style="max-width:200px;max-height:120px;border:2px solid var(--line);display:block;margin-top:0.5rem;" alt="">
                            <?php endif ?>
                            <?php if (!empty($piece['id'])): ?>
                                <button type="button" id="btn-capture-thumbnail" class="admin-btn admin-btn-sm"
                                        style="margin-top:0.5rem;"
                                        data-piece-id="<?= (int) $piece['id'] ?>">
                                    Capture Thumbnail
                                </button>
                            <?php endif ?>
                        </div>

                        <div class="field">
                            <label class="checkbox-label">
                                <input type="checkbox" name="comments_enabled" value="1"
                                       <?= !empty($piece['comments_enabled']) ? 'checked' : '' ?>>
                                Enable comments on this piece
                            </label>
                        </div>

                        <div class="field">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="4"><?= e($piece['description'] ?? '') ?></textarea>
                        </div>

                        <div class="field">
                            <label for="prompt">Prompt</label>
                            <textarea id="prompt" name="prompt" rows="4"><?= e($piece['prompt'] ?? '') ?></textarea>
                            <small>The creative prompt that originally generated this piece.</small>
                        </div>
                    </div>

                    <?php
                    $cv = $piece['current_version'] ?? [];
                    $versionNum = $cv['version_number'] ?? null;
                    ?>
                    <!-- HTML Tab -->
                    <div id="tab-html" class="piece-tab-panel is-hidden" role="tabpanel">
                        <div class="field">
                            <label for="html_code">HTML</label>
                            <textarea id="html_code" name="html_code" rows="18" class="code-field" aria-describedby="html-desc"><?= e($cv['html_code'] ?? '') ?></textarea>
                            <small id="html-desc">
                                <?php if ($versionNum): ?>
                                    Edits the current version (v<?= (int) $versionNum ?>) in place.
                                <?php else: ?>
                                    Saving will create version 1 of this piece if HTML/CSS/JS is filled in.
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>

                    <!-- CSS Tab -->
                    <div id="tab-css" class="piece-tab-panel is-hidden" role="tabpanel">
                        <div class="field">
                            <label for="css_code">CSS</label>
                            <textarea id="css_code" name="css_code" rows="18" class="code-field" aria-describedby="css-desc"><?= e($cv['css_code'] ?? '') ?></textarea>
                            <small id="css-desc">Supports standard style rules. Do not target base body/html coordinates unless using SVG.</small>
                        </div>
                    </div>

                    <!-- JS Tab -->
                    <div id="tab-js" class="piece-tab-panel is-hidden" role="tabpanel">
                        <div class="field">
                            <label for="generated_code">JS (Generated Code)</label>
                            <textarea id="generated_code" name="generated_code" rows="18" class="code-field" aria-describedby="js-desc"><?= e($cv['generated_code'] ?? '') ?></textarea>
                            <small id="js-desc">Use standard engine variables. Do not redeclare window.sketch or imports.</small>
                        </div>
                    </div>

                    <!-- AI Refine Tab -->
                    <div id="tab-ai" class="piece-tab-panel is-hidden" role="tabpanel">
                        <div class="field">
                            <label for="ai_profile_id">AI Profile</label>
                            <select id="ai_profile_id">
                                <option value="">-- Select AI Profile --</option>
                                <?php foreach ($profiles as $p): ?>
                                    <option value="<?= (int) $p['id'] ?>" <?= ($p['id'] == ($preferredProfileId ?? 0)) ? 'selected' : '' ?>>
                                        <?= e($p['profile_name']) ?> (<?= e($p['vendor']) ?> - <?= e($p['model']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="ai_refine_prompt">What would you like to change about this piece?</label>
                            <textarea id="ai_refine_prompt" rows="6" placeholder="Describe the changes you want to make, e.g., 'Turn the background to deep blue and make the shapes expand and contract.'"></textarea>
                            <small>This will send your prompt and the current code blocks in the HTML/CSS/JS tabs above to the AI, then suggest changes that you can inspect, edit, and accept or reject.</small>
                        </div>
                        <div style="margin-top: 1rem;">
                            <button type="button" id="btn-refine-ai" class="admin-btn" style="background: var(--yellow);">Request AI Changes</button>
                        </div>
                    </div>

                    <div class="form-actions" style="margin-top: 1.5rem; border-top: 3px solid var(--line); padding-top: 1.5rem;">
                        <button type="submit" class="admin-btn"><?= $isEdit ? 'Update' : 'Create' ?> Piece</button>
                        <a href="/admin/pieces" class="admin-btn admin-btn-ghost">Cancel</a>
                    </div>
                </form>
            </div>

            <!-- Right Pane: Live Preview -->
            <div class="workspace-pane pane-preview is-hidden-mobile">
                <div class="preview-container">
                    <div class="preview-header">
                        <h3>Interactive Live Canvas</h3>
                        <div class="preview-dimensions" id="preview-dimensions">WebGL/Canvas Active</div>
                    </div>
                    <div class="preview-stage" id="preview-stage-wrapper">
                        <!-- Preview iframe is mounted here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    // DOM Elements
    var tabs = document.querySelectorAll('.piece-edit-tabs .admin-tab');
    var panels = {
        meta: document.getElementById('tab-meta'),
        html: document.getElementById('tab-html'),
        css: document.getElementById('tab-css'),
        js: document.getElementById('tab-js'),
        ai: document.getElementById('tab-ai')
    };

    var htmlField = document.getElementById('html_code');
    var cssField = document.getElementById('css_code');
    var jsField = document.getElementById('generated_code');
    var engineField = document.getElementById('engine');
    var titleField = document.getElementById('title');
    var previewStage = document.getElementById('preview-stage-wrapper');

    var btnRefineAi = document.getElementById('btn-refine-ai');
    var aiProfileField = document.getElementById('ai_profile_id');
    var aiPromptField = document.getElementById('ai_refine_prompt');

    var aiBanner = document.getElementById('ai-suggestion-banner');
    var btnAiAccept = document.getElementById('btn-ai-accept');
    var btnAiReject = document.getElementById('btn-ai-reject');

    var toggleEditor = document.getElementById('toggle-view-editor');
    var togglePreview = document.getElementById('toggle-view-preview');
    var paneEditor = document.querySelector('.pane-editor');
    var panePreview = document.querySelector('.pane-preview');

    var originalCode = null;
    var previewTimeout = null;

    // 1. Tab Switching
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (t) { 
                t.classList.remove('active'); 
                t.setAttribute('aria-selected', 'false');
            });
            tab.classList.add('active');
            tab.setAttribute('aria-selected', 'true');
            Object.keys(panels).forEach(function (key) {
                if (key === tab.dataset.tab) {
                    panels[key].classList.remove('is-hidden');
                } else {
                    panels[key].classList.add('is-hidden');
                }
            });
        });
    });

    // 2. Render Helper
    function renderDocumentJS(title, engine, html, css, js) {
        var jsonEngine = JSON.stringify(engine);
        var jsonCode = JSON.stringify(js);
        return '<!DOCTYPE html>\n<html lang="en">\n<head>\n<meta charset="utf-8">\n<meta name="viewport" content="width=device-width, initial-scale=1">\n<title>' + title + '</title>\n<script type="importmap">\n{\n  "imports": {\n    "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js",\n    "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"\n  }\n}\n<\/script>\n<style>\nhtml,body{margin:0;padding:0;width:100%;height:100%;overflow:hidden;background:#0d0d0f;color:#fff;}\nbody{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}\n#runtime-root{width:100vw;height:100vh;overflow:hidden;}\n#piece-error{position:fixed;inset:auto 1rem 1rem 1rem;z-index:9999;padding:1rem;border:1px solid #fca5a5;background:#450a0a;color:#fee2e2;font:14px/1.4 ui-monospace,SFMono-Regular,Menlo,monospace;white-space:pre-wrap;display:none;}\ncanvas{display:block;width:100%;height:100%;}\n' + css + '\n</style>\n</head>\n<body>\n<div id="runtime-root">' + html + '</div>\n<div id="piece-error" role="alert"></div>\n<script>\nconst PIECE_ENGINE = ' + jsonEngine + ';\nconst PIECE_CODE = ' + jsonCode + ';\nfunction showPieceError(error) {\n  const el = document.getElementById("piece-error");\n  if (!el) return;\n  el.textContent = (error && (error.stack || error.message)) ? (error.stack || error.message) : String(error);\n  el.style.display = "block";\n}\nwindow.addEventListener("error", (event) => showPieceError(event.error || event.message));\nwindow.addEventListener("unhandledrejection", (event) => showPieceError(event.reason || "Unhandled promise rejection"));\nfunction runPieceCode() {\n  try {\n    const fn = new Function(PIECE_CODE + "\\n//# sourceURL=piece-runtime.js");\n    fn();\n  } catch (error) {\n    showPieceError(error);\n  }\n}\nfunction findCanvas(id) {\n  return document.getElementById(id) || document.querySelector("canvas") || (() => {\n    const canvas = document.createElement("canvas");\n    canvas.id = id;\n    const parent = document.getElementById("canvas-container") || document.getElementById("sketch-container") || document.getElementById("runtime-root");\n    parent.appendChild(canvas);\n    return canvas;\n  })();\n}\nfunction sizeCanvas(canvas) {\n  const w = Math.max(1, canvas.parentElement?.clientWidth || window.innerWidth || 1280);\n  const h = Math.max(1, canvas.parentElement?.clientHeight || window.innerHeight || 720);\n  canvas.width = w;\n  canvas.height = h;\n}\nfunction startFrame(callback) {\n  let count = 0;\n  function tick() {\n    count++;\n    try { callback(count); } catch (error) { showPieceError(error); return; }\n    requestAnimationFrame(tick);\n  }\n  requestAnimationFrame(tick);\n}\nfunction bootCanvasRuntime(extra) {\n  runPieceCode();\n  if (typeof window.sketch !== "function") return;\n  const canvas = findCanvas(PIECE_ENGINE === "c2" ? "c2-canvas" : "scene");\n  canvas.style.cssText = "display:block;width:100%;height:100%;";\n  sizeCanvas(canvas);\n  window.addEventListener("resize", () => sizeCanvas(canvas));\n  try { window.sketch({ canvas, startFrame, ...(extra || {}) }); } catch (error) { showPieceError(error); }\n}\nfunction bootP5() {\n  const script = document.createElement("script");\n  script.src = "https://cdnjs.cloudflare.com/ajax/libs/p5.js/1.9.0/p5.min.js";\n  script.onload = () => {\n    runPieceCode();\n    try {\n      if (typeof window.sketch === "function" && typeof window.p5 === "function") {\n        const parent = document.getElementById("canvas-container") || document.getElementById("sketch-container") || document.getElementById("runtime-root");\n        new window.p5(window.sketch, parent);\n      }\n    } catch (error) { showPieceError(error); }\n  };\n  script.onerror = () => showPieceError("Could not load p5.js runtime.");\n  document.head.appendChild(script);\n}\nfunction bootC2() {\n  const script = document.createElement("script");\n  script.src = "https://cdn.jsdelivr.net/npm/c2.js@1.0.9/dist/c2.min.js";\n  script.onload = () => {\n    bootCanvasRuntime({ c2: window.c2 });\n  };\n  script.onerror = () => showPieceError("Could not load c2.js runtime.");\n  document.head.appendChild(script);\n}\nasync function bootThree() {\n  try {\n    const mod = await import("https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js");\n    const { OrbitControls } = await import("https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/controls/OrbitControls.js");\n    window.THREE = mod;\n    runPieceCode();\n    if (typeof window.sketch !== "function") return;\n    const canvas = findCanvas("scene");\n    canvas.style.cssText = "display:block;width:100%;height:100%;";\n    sizeCanvas(canvas);\n    const state = { scene: null, camera: null, renderer: null };\n    let controls = null;\n    let rafIds = [];\n    const instrumentedThree = { ...mod };\n    instrumentedThree.Scene = class extends mod.Scene {\n      constructor() { super(); state.scene = this; }\n    };\n    instrumentedThree.PerspectiveCamera = class extends mod.PerspectiveCamera {\n      constructor(...args) { super(...args); state.camera = this; }\n    };\n    instrumentedThree.WebGLRenderer = class extends mod.WebGLRenderer {\n      constructor(params) {\n        super({ ...(params || {}), canvas });\n        state.renderer = this;\n        const _origSetSize = this.setSize.bind(this);\n        this.setSize = (w, h) => _origSetSize(w, h, false);\n        const _origRender = this.render.bind(this);\n        this.render = (sc, cam) => {\n          if (sc) state.scene = sc;\n          if (cam) state.camera = cam;\n          return _origRender(sc, cam);\n        };\n      }\n    };\n    const width = canvas.width || window.innerWidth || 1280;\n    const height = canvas.height || window.innerHeight || 720;\n    function autoFit() {\n      if (!state.scene || !state.camera) return;\n      const box = new mod.Box3();\n      state.scene.traverse((obj) => {\n        if (obj.isHelper || obj.isLight || obj.isCamera) return;\n        if (obj.isPoints) return;\n        if (obj.material) {\n          const mat = obj.material;\n          if (mat.side === 1 || (Array.isArray(mat) && mat.some(m => m.side === 1))) return;\n        }\n        const name = (obj.name || '').toLowerCase();\n        if (name.includes('sky') || name.includes('background') || name.includes('env') || name.includes('floor') || name.includes('ground') || name.includes('grid') || name.includes('dome') || name.includes('space') || name.includes('star')) return;\n        if ((obj.isMesh || obj.isLine || obj.isSprite) && obj.geometry) {\n          obj.geometry.computeBoundingBox?.();\n          if (obj.geometry.boundingBox) {\n            const worldBox = obj.geometry.boundingBox.clone().applyMatrix4(obj.matrixWorld);\n            const worldSize = new mod.Vector3();\n            worldBox.getSize(worldSize);\n            if (worldSize.x >= 30 || worldSize.y >= 30 || worldSize.z >= 30) return;\n            if (obj.geometry.type === 'PlaneGeometry' || obj.geometry.type === 'PlaneBufferGeometry') {\n              if (worldSize.x >= 15 || worldSize.y >= 15 || worldSize.z >= 15) return;\n            }\n            box.union(worldBox);\n          }\n        }\n      });\n      if (box.isEmpty()) return;\n      const center = new mod.Vector3(); box.getCenter(center);\n      const size = new mod.Vector3();   box.getSize(size);\n      if (state.camera.position.lengthSq() > 0.01) {\n        if (controls) { controls.target.copy(center); controls.update(); }\n        return;\n      }\n      const maxDim = Math.max(size.x, size.y, size.z) || 1;\n      const fov = state.camera.fov * (Math.PI / 180);\n      const dist = Math.abs(maxDim / 2 / Math.tan(fov / 2)) * 0.63;\n      state.camera.position.set(center.x + dist, center.y + dist * 0.4, center.z + dist);\n      state.camera.lookAt(center);\n      state.camera.updateMatrixWorld(true);\n      if (controls) { controls.target.copy(center); controls.update(); }\n    }\n    function ensureFallbackLighting() {\n      if (!state.scene?.traverse) return;\n      let hasRealLight = false;\n      let hasFallback = false;\n      const fallbacks = [];\n      state.scene.traverse((obj) => {\n        if (!obj.isLight) return;\n        if (obj.name?.startsWith("__viewer_fallback_")) { hasFallback = true; fallbacks.push(obj); }\n        else hasRealLight = true;\n      });\n      if (hasRealLight) {\n        fallbacks.forEach((obj) => state.scene.remove(obj));\n        return;\n      }\n      if (hasFallback) return;\n      const amb = new mod.AmbientLight(0xffffff, 0.7);\n      amb.name = "__viewer_fallback_ambient__";\n      state.scene.add(amb);\n      const dir = new mod.DirectionalLight(0xffffff, 0.8);\n      dir.position.set(5, 10, 7.5);\n      dir.name = "__viewer_fallback_dir__";\n      state.scene.add(dir);\n    }\n    function startFrame(handler) {\n      let count = 0;\n      function tick() {\n        count++;\n        try { handler(count); } catch (error) { showPieceError(error); return; }\n        if (count === 15) autoFit();\n        const id = requestAnimationFrame(tick);\n        rafIds.push(id);\n      }\n      const id = requestAnimationFrame(tick);\n      rafIds.push(id);\n      return () => { rafIds.forEach((rafId) => cancelAnimationFrame(rafId)); rafIds = []; };\n    }\n    window.THREE = instrumentedThree;\n    window.sketch({ THREE: instrumentedThree, canvas, startFrame, width, height, size: { width, height }, OrbitControls });\n    ensureFallbackLighting();\n    autoFit();\n\n    if (state.camera && state.renderer) {\n      controls = new OrbitControls(state.camera, canvas);\n      controls.enableDamping = true;\n      controls.enablePan = true;\n      const camDir = new mod.Vector3();\n      state.camera.getWorldDirection(camDir);\n      const camLen = state.camera.position.length();\n      controls.target.copy(state.camera.position).addScaledVector(camDir, Math.max(camLen * 0.8, 3));\n      autoFit();\n      controls.update();\n\n      let consecutiveErrors = 0;\n      const animateControls = () => {\n        const id = requestAnimationFrame(animateControls);\n        rafIds.push(id);\n        try {\n          ensureFallbackLighting();\n          controls.update();\n          state.renderer.render(state.scene, state.camera);\n          consecutiveErrors = 0;\n        } catch (renderError) {\n          consecutiveErrors++;\n          if (consecutiveErrors === 1) showPieceError(renderError);\n          if (consecutiveErrors >= 5) cancelAnimationFrame(id);\n        }\n      };\n      animateControls();\n    }\n\n    window.addEventListener("resize", () => {\n      sizeCanvas(canvas);\n      if (state.renderer && state.camera) {\n        state.camera.aspect = canvas.width / canvas.height;\n        state.camera.updateProjectionMatrix();\n        state.renderer.setSize(canvas.width, canvas.height, false);\n      }\n    });\n\n    window.parent.postMessage({ type: "sketch-status", valid: true }, "*");\n  } catch (error) {\n    showPieceError(error);\n  }\n}\nif (PIECE_ENGINE === "p5") {\n  bootP5();\n} else if (PIECE_ENGINE === "three") {\n  bootThree();\n} else if (PIECE_ENGINE === "c2") {\n  bootC2();\n} else {\n  runPieceCode();\n  if (typeof window.sketch === "function") {\n    try { window.sketch(); } catch (error) { showPieceError(error); }\n  }\n}\n<\/script>\n</body>\n</html>';
    }

    // 3. Update Live Preview function
    function updateLivePreview() {
        var title = titleField.value || 'Art piece';
        var engine = engineField.value || 'p5';
        var html = htmlField.value || '';
        var css = cssField.value || '';
        var js = jsField.value || '';

        // Generate full document srcdoc
        var srcdoc = renderDocumentJS(title, engine, html, css, js);

        // Remove old iframe and mount a fresh one to reset JS state completely
        previewStage.innerHTML = '';
        var iframe = document.createElement('iframe');
        iframe.style.width = '100%';
        iframe.style.height = '100%';
        iframe.style.border = '0';
        iframe.style.display = 'block';
        iframe.sandbox = 'allow-scripts allow-same-origin';
        iframe.title = title;
        iframe.srcdoc = srcdoc;
        previewStage.appendChild(iframe);
    }

    // Debounce preview updates for manual edits
    function queuePreviewUpdate() {
        if (previewTimeout) {
            clearTimeout(previewTimeout);
        }
        previewTimeout = setTimeout(function () {
            updateLivePreview();
        }, 500);
    }

    // Hook listeners
    htmlField.addEventListener('input', queuePreviewUpdate);
    cssField.addEventListener('input', queuePreviewUpdate);
    jsField.addEventListener('input', queuePreviewUpdate);
    engineField.addEventListener('change', updateLivePreview);
    titleField.addEventListener('input', queuePreviewUpdate);

    // Initial load
    updateLivePreview();

    // 4. AI Refinement (Reframe)
    btnRefineAi.addEventListener('click', function () {
        var prompt = aiPromptField.value.trim();
        var profileId = aiProfileField.value;
        var engine = engineField.value;

        if (!profileId) {
            alert('Please select an active AI vendor profile.');
            return;
        }
        if (!prompt) {
            alert('Please enter a refinement instruction.');
            return;
        }

        // Set loading state
        btnRefineAi.disabled = true;
        var originalBtnText = btnRefineAi.textContent;
        btnRefineAi.textContent = 'Requesting AI Changes...';

        // Clear any old banner
        aiBanner.style.display = 'none';

        var payload = {
            prompt: prompt,
            engine: engine,
            profile_id: parseInt(profileId, 10),
            html_code: htmlField.value,
            css_code: cssField.value,
            generated_code: jsField.value
        };

        fetch('/admin/pieces/refine-ai', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok) {
                    throw new Error(data.error || 'AI Refinement failed.');
                }
                return data;
            });
        })
        .then(function (data) {
            // Success! Store backup if not already in preview mode
            if (!originalCode) {
                originalCode = {
                    html: htmlField.value,
                    css: cssField.value,
                    js: jsField.value
                };
            }

            // Set suggested code to textareas
            htmlField.value = data.html_code || '';
            cssField.value = data.css_code || '';
            jsField.value = data.generated_code || '';

            // Update live preview
            updateLivePreview();

            // Display AI Suggestion banner
            aiBanner.style.display = 'flex';
            
            // Switch tab to JS or HTML so user can inspect the suggestion
            var jsTab = document.querySelector('.piece-edit-tabs button[data-tab="js"]');
            if (jsTab) {
                jsTab.click();
            }
        })
        .catch(function (err) {
            alert('AI Refinement Error: ' + err.message);
        })
        .finally(function () {
            btnRefineAi.disabled = false;
            btnRefineAi.textContent = originalBtnText;
        });
    });

    // 5. Accept / Reject AI suggest actions
    btnAiAccept.addEventListener('click', function () {
        // Just hide the banner, keeping the suggested values (and any manual tweaks) in the textareas
        aiBanner.style.display = 'none';
        originalCode = null;
    });

    btnAiReject.addEventListener('click', function () {
        if (originalCode) {
            htmlField.value = originalCode.html;
            cssField.value = originalCode.css;
            jsField.value = originalCode.js;
            updateLivePreview();
        }
        aiBanner.style.display = 'none';
        originalCode = null;
    });

    // 6. Mobile toggle view
    toggleEditor.addEventListener('click', function () {
        toggleEditor.classList.add('active');
        togglePreview.classList.remove('active');
        paneEditor.classList.remove('is-hidden-mobile');
        panePreview.classList.add('is-hidden-mobile');
    });

    togglePreview.addEventListener('click', function () {
        togglePreview.classList.add('active');
        toggleEditor.classList.remove('active');
        panePreview.classList.remove('is-hidden-mobile');
        paneEditor.classList.add('is-hidden-mobile');
        setTimeout(function() {
            window.dispatchEvent(new Event('resize'));
        }, 50);
    });

    // 7. Thumbnail capture
    var captureBtn = document.getElementById('btn-capture-thumbnail');
    if (captureBtn) {
        captureBtn.addEventListener('click', async function () {
            var pieceId = captureBtn.dataset.pieceId;
            var engine = engineField.value || 'p5';
            var html = htmlField.value || '';
            var css = cssField.value || '';
            var js = jsField.value || '';

            if (!html && !js) {
                alert('Add HTML and JS code in the code tabs before capturing a thumbnail.');
                return;
            }

            captureBtn.textContent = 'Capturing…';
            captureBtn.disabled = true;

            try {
                var srcdoc = renderDocumentJS(titleField.value || 'Art piece', engine, html, css, js);

                // Inject preserveDrawingBuffer:true for Three.js so toDataURL() works
                if (engine === 'three') {
                    srcdoc = srcdoc.replace(
                        'super({ ...(params || {}), canvas });',
                        'super({ ...(params || {}), canvas, preserveDrawingBuffer: true });'
                    );
                }

                // Mount in off-screen iframe
                var captureFrame = document.createElement('iframe');
                captureFrame.style.cssText = 'position:fixed;left:-9999px;top:-9999px;width:960px;height:540px;border:none;';
                captureFrame.sandbox = 'allow-scripts';
                document.body.appendChild(captureFrame);

                captureFrame.srcdoc = srcdoc;
                await new Promise(function (resolve) {
                    captureFrame.onload = resolve;
                    setTimeout(resolve, 800);
                });

                // Wait ~3s for animation to stabilise
                await new Promise(function (resolve) { setTimeout(resolve, 3000); });

                var iframeDoc = captureFrame.contentDocument;
                var canvas = iframeDoc && iframeDoc.querySelector('canvas');

                if (!canvas) {
                    throw new Error('No canvas found. Make sure the piece renders to a canvas element.');
                }

                var imageData;
                try {
                    imageData = canvas.toDataURL('image/png');
                } catch (e) {
                    throw new Error('Canvas capture failed: ' + e.message + '. For Three.js this is expected until the piece is saved once.');
                }

                document.body.removeChild(captureFrame);

                var formData = new FormData();
                formData.append('image_data', imageData);

                var resp = await fetch('/admin/pieces/' + pieceId + '/capture-thumbnail', {
                    method: 'POST',
                    body: formData
                });

                if (!resp.ok) {
                    var err = await resp.json();
                    throw new Error(err.error || 'Server error ' + resp.status);
                }

                var result = await resp.json();

                document.getElementById('thumbnail_url').value = result.url;

                var existing = document.getElementById('capture-preview-img');
                if (!existing) {
                    existing = document.createElement('img');
                    existing.id = 'capture-preview-img';
                    existing.style.cssText = 'max-width:200px;max-height:120px;border:2px solid var(--line);display:block;margin-top:0.5rem;';
                    document.getElementById('thumbnail_url').insertAdjacentElement('afterend', existing);
                }
                existing.src = result.url + '?t=' + Date.now();

                captureBtn.textContent = 'Captured!';
                setTimeout(function () { captureBtn.textContent = 'Capture Thumbnail'; }, 3000);
            } catch (err) {
                alert('Thumbnail capture failed: ' + err.message);
                captureBtn.textContent = 'Capture Thumbnail';
            } finally {
                captureBtn.disabled = false;
            }
        });
    }
})();
</script>
<?php
$content = ob_get_clean();

require dirname(__DIR__) . '/layout.php';
