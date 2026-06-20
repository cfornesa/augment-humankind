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
    flex-direction: column;
    gap: 1rem;
    background: var(--yellow);
    border: 3px solid var(--line);
    box-shadow: 4px 4px 0 var(--line);
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    font-weight: 800;
}

.ai-banner-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.ai-banner span {
    font-size: 0.95rem;
    color: var(--ink);
}

.ai-banner-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
}

.ai-save-status {
    font-size: 0.85rem;
    font-weight: 700;
    white-space: nowrap;
}

.ai-save-status.is-success {
    color: #2f6d2f;
}

.ai-save-status.is-error {
    color: #b3261e;
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

.ai-plan-container,
.ai-diff-container {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    font-weight: 400;
}

.ai-plan-container:empty,
.ai-diff-container:empty {
    display: none;
}

.ai-plan-container {
    background: var(--white);
    border: 2px solid var(--line);
    padding: 0.5rem 0.75rem;
}

.ai-plan-container strong {
    display: block;
    margin-bottom: 0.35rem;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--ink);
}

.ai-plan-container pre {
    margin: 0;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.8rem;
    line-height: 1.45;
    white-space: pre-wrap;
    word-break: break-word;
    color: var(--ink);
}

.ai-diff-block {
    background: var(--white);
    border: 2px solid var(--line);
    padding: 0.5rem 0.75rem;
    max-height: 16rem;
    overflow: auto;
}

.ai-diff-block strong {
    display: block;
    margin-bottom: 0.35rem;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--ink);
}

.ai-diff-pre {
    margin: 0;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.8rem;
    line-height: 1.45;
    white-space: pre-wrap;
    word-break: break-word;
}

.ai-diff-pre .diff-added {
    background: rgba(140, 207, 63, 0.35);
    color: var(--ink);
}

.ai-diff-pre .diff-removed {
    background: rgba(245, 162, 59, 0.35);
    color: var(--ink);
    text-decoration: line-through;
}

.ai-diff-pre .diff-same {
    color: var(--ink-soft);
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
        <div class="ai-banner-row">
            <span>AI suggested changes are loaded. Review the plan and diff below, code tabs, and preview before deciding:</span>
            <div class="ai-banner-actions">
                <button type="button" id="btn-ai-accept" class="admin-btn">Accept Changes</button>
                <button type="button" id="btn-ai-reject" class="admin-btn admin-btn-ghost">Reject</button>
                <span id="ai-save-status" class="ai-save-status" role="status" aria-live="polite"></span>
            </div>
        </div>
        <div id="ai-plan-container" class="ai-plan-container" aria-label="AI's stated plan"></div>
        <div id="ai-diff-container" class="ai-diff-container" aria-label="Code changes"></div>
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
                            <div class="field">
                                <label for="sort_order">Position</label>
                                <input id="sort_order" name="sort_order" type="number" min="0"
                                       value="<?= (int) ($piece['sort_order'] ?? 0) ?>">
                                <small>0 = first. Existing pieces shift to make room.</small>
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
                            <input id="thumbnail_url" name="thumbnail_url" type="text" maxlength="2048"
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

                        <div class="field">
                            <label for="meta_ai_profile_id">AI Profile Used</label>
                            <select id="meta_ai_profile_id" name="ai_profile_id">
                                <option value="">(Blank)</option>
                                <?php foreach ($profiles as $p): ?>
                                    <option value="<?= (int) $p['id'] ?>" <?= ((int) ($piece['current_version']['ai_profile_id'] ?? 0) === (int) $p['id']) ? 'selected' : '' ?>>
                                        <?= e($p['profile_name']) ?> (<?= e($p['vendor']) ?> - <?= e($p['model']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small>Which AI Profile produced the current version's code. Edit if the recorded value is wrong or missing.</small>
                        </div>

                        <div class="field">
                            <label for="meta_ai_persona_id">AI Persona Used</label>
                            <select id="meta_ai_persona_id" name="ai_persona_id">
                                <option value="">(Blank)</option>
                                <?php foreach (($personas ?? []) as $persona): ?>
                                    <option value="<?= (int) $persona['id'] ?>" <?= ((int) ($piece['current_version']['ai_persona_id'] ?? 0) === (int) $persona['id']) ? 'selected' : '' ?>>
                                        <?= e((string) $persona['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
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
                            <label for="ai_persona_id">AI Persona <span style="font-weight:400;color:var(--ink-soft);">(optional)</span></label>
                            <select id="ai_persona_id">
                                <option value="">None — use the base refinement prompt</option>
                                <?php foreach (($personas ?? []) as $persona): ?>
                                    <option value="<?= (int) $persona['id'] ?>"><?= e((string) $persona['name']) ?></option>
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
    // Computed server-side from the actual request (not window.location.origin):
    // the preview/capture iframes below use srcdoc, which gets an opaque
    // origin — window.location.origin would literally be the string "null"
    // inside them, even with sandbox="allow-same-origin".
    var RUNTIME_ORIGIN = <?= json_encode(
        ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
    ) ?>;
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
    var currentPieceId = <?= $isEdit ? (int) $piece['id'] : 'null' ?>;

    var btnRefineAi = document.getElementById('btn-refine-ai');
    var aiProfileField = document.getElementById('ai_profile_id');
    var aiPersonaField = document.getElementById('ai_persona_id');
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
    var lastRefineProfileId = null;
    var lastRefinePersonaId = null;

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
    function renderDocumentJS(title, engine, html, css, js, preserveDrawingBuffer) {
        var jsonEngine = JSON.stringify(engine);
        var jsonCode = JSON.stringify(js);
        var jsonPreserve = JSON.stringify(!!preserveDrawingBuffer);
        return '<!DOCTYPE html>\n<html lang="en">\n<head>\n<meta charset="utf-8">\n<meta name="viewport" content="width=device-width, initial-scale=1">\n<title>' + title + '</title>\n<script type="importmap">\n{\n  "imports": {\n    "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js",\n    "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"\n  }\n}\n<\/script>\n<style>\nhtml,body{margin:0;padding:0;width:100%;height:100%;overflow:hidden;background:#0d0d0f;color:#fff;}\nbody{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}\n#runtime-root{width:100vw;height:100vh;overflow:hidden;}\n#piece-error{position:fixed;inset:auto 1rem 1rem 1rem;z-index:9999;padding:1rem;border:1px solid #fca5a5;background:#450a0a;color:#fee2e2;font:14px/1.4 ui-monospace,SFMono-Regular,Menlo,monospace;white-space:pre-wrap;display:none;}\ncanvas{display:block;width:100%;height:100%;}\n' + css + '\n</style>\n</head>\n<body>\n<div id="runtime-root">' + html + '</div>\n<div id="piece-error" role="alert"></div>\n<script>\nconst PIECE_ENGINE = ' + jsonEngine + ';\nconst PIECE_CODE = ' + jsonCode + ';\nconst PIECE_PRESERVE_DRAWING_BUFFER = ' + jsonPreserve + ';\n<\/script>\n<script src="' + RUNTIME_ORIGIN + '/assets/js/piece-runtime.js"><\/script>\n</body>\n</html>';
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

    // 3b. Line diff helper — lets the admin see exactly what an AI Refine
    // suggestion changed (not just the end result), since a prompt
    // instruction alone is no guarantee the AI left unrelated parts of the
    // piece untouched.
    function computeLineDiff(oldText, newText) {
        var oldLines = (oldText || '').split('\n');
        var newLines = (newText || '').split('\n');
        var n = oldLines.length, m = newLines.length;
        var dp = [];
        for (var i = 0; i <= n; i++) { dp.push(new Array(m + 1).fill(0)); }
        for (var i2 = n - 1; i2 >= 0; i2--) {
            for (var j2 = m - 1; j2 >= 0; j2--) {
                dp[i2][j2] = oldLines[i2] === newLines[j2]
                    ? dp[i2 + 1][j2 + 1] + 1
                    : Math.max(dp[i2 + 1][j2], dp[i2][j2 + 1]);
            }
        }
        var result = [];
        var i = 0, j = 0;
        while (i < n && j < m) {
            if (oldLines[i] === newLines[j]) {
                result.push({ type: 'same', text: oldLines[i] });
                i++; j++;
            } else if (dp[i + 1][j] >= dp[i][j + 1]) {
                result.push({ type: 'removed', text: oldLines[i] });
                i++;
            } else {
                result.push({ type: 'added', text: newLines[j] });
                j++;
            }
        }
        while (i < n) { result.push({ type: 'removed', text: oldLines[i] }); i++; }
        while (j < m) { result.push({ type: 'added', text: newLines[j] }); j++; }
        return result;
    }

    function renderDiffBlock(container, label, oldText, newText) {
        if ((oldText || '') === (newText || '')) return;
        var blockEl = document.createElement('div');
        blockEl.className = 'ai-diff-block';
        var heading = document.createElement('strong');
        heading.textContent = label + ' — changed';
        blockEl.appendChild(heading);
        var pre = document.createElement('pre');
        pre.className = 'ai-diff-pre';
        computeLineDiff(oldText, newText).forEach(function (d) {
            var line = document.createElement('div');
            line.className = 'diff-' + d.type;
            var prefix = d.type === 'added' ? '+ ' : d.type === 'removed' ? '- ' : '  ';
            line.textContent = prefix + d.text;
            pre.appendChild(line);
        });
        blockEl.appendChild(pre);
        container.appendChild(blockEl);
    }

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

        // Clear any old banner and leftover save-status message from a
        // previous attempt.
        aiBanner.style.display = 'none';
        var aiSaveStatusEl = document.getElementById('ai-save-status');
        aiSaveStatusEl.textContent = '';
        aiSaveStatusEl.className = 'ai-save-status';

        var pieceOriginalPromptField = document.getElementById('prompt');
        var payload = {
            prompt: prompt,
            engine: engine,
            profile_id: parseInt(profileId, 10),
            persona_id: aiPersonaField && aiPersonaField.value ? parseInt(aiPersonaField.value, 10) : 0,
            html_code: htmlField.value,
            css_code: cssField.value,
            generated_code: jsField.value,
            // The piece's own creative prompt, so the AI knows the original
            // intent it's refining, not just the raw code.
            original_prompt: pieceOriginalPromptField ? pieceOriginalPromptField.value.trim() : ''
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
            // Snapshot what was there immediately before this suggestion, so
            // the diff view below shows exactly what THIS refine changed —
            // not the cumulative change across multiple refine attempts.
            var beforeRefine = {
                html: htmlField.value,
                css: cssField.value,
                js: jsField.value
            };

            // Success! Store backup if not already in preview mode
            if (!originalCode) {
                originalCode = beforeRefine;
            }

            // Remember which profile/persona produced this suggestion, so
            // accepting it can carry that attribution into the new version
            // saved when the piece form is submitted.
            lastRefineProfileId = data.profile_id || null;
            lastRefinePersonaId = data.persona_id || null;

            // Set suggested code to textareas
            htmlField.value = data.html_code || '';
            cssField.value = data.css_code || '';
            jsField.value = data.generated_code || '';

            // Render the AI's stated plan (which specific elements it
            // intended to touch, decided before it wrote any patch) — the
            // same before-acting visibility a plan gives, shown above the
            // diff so its intent can be sanity-checked alongside the result.
            var planContainer = document.getElementById('ai-plan-container');
            planContainer.innerHTML = '';
            if (data.plan) {
                var planHeading = document.createElement('strong');
                planHeading.textContent = "AI's plan";
                var planPre = document.createElement('pre');
                planPre.textContent = data.plan;
                planContainer.appendChild(planHeading);
                planContainer.appendChild(planPre);
            }

            // Render a line diff so unrequested changes (e.g. to decorations,
            // colors, or anything the instruction didn't name) are visible
            // before deciding to accept — a prompt instruction alone can't
            // guarantee the AI left everything else untouched.
            var diffContainer = document.getElementById('ai-diff-container');
            diffContainer.innerHTML = '';
            renderDiffBlock(diffContainer, 'HTML', beforeRefine.html, htmlField.value);
            renderDiffBlock(diffContainer, 'CSS', beforeRefine.css, cssField.value);
            renderDiffBlock(diffContainer, 'JS', beforeRefine.js, jsField.value);
            if (!diffContainer.children.length) {
                var noChange = document.createElement('p');
                noChange.textContent = 'No code differences detected.';
                diffContainer.appendChild(noChange);
            }

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

    // 5. Accept / Reject AI suggest actions.
    // Accepting immediately saves the change as a new version — no separate
    // "Save Changes" submit needed — with an inline status message, since
    // requiring a second manual save step after already deciding to accept
    // was just unnecessary friction.
    var aiSaveStatus = document.getElementById('ai-save-status');

    function clearAiSaveStatus() {
        aiSaveStatus.textContent = '';
        aiSaveStatus.className = 'ai-save-status';
    }

    btnAiAccept.addEventListener('click', function () {
        // Carry the profile/persona that produced this suggestion into the
        // Metadata tab's "used to generate this" fields, so the version
        // this creates records them.
        var metaProfileField = document.getElementById('meta_ai_profile_id');
        var metaPersonaField = document.getElementById('meta_ai_persona_id');
        if (metaProfileField && lastRefineProfileId) {
            metaProfileField.value = String(lastRefineProfileId);
        }
        if (metaPersonaField) {
            metaPersonaField.value = lastRefinePersonaId ? String(lastRefinePersonaId) : '';
        }

        if (!currentPieceId) {
            // Brand new, not-yet-saved piece — there's no piece row to
            // attach a version to yet. Accept locally; the normal "Save
            // Changes" submit creates the piece and its first version.
            aiBanner.style.display = 'none';
            originalCode = null;
            document.getElementById('ai-diff-container').innerHTML = '';
            document.getElementById('ai-plan-container').innerHTML = '';
            return;
        }

        var refinementPrompt = aiPromptField.value.trim();
        btnAiAccept.disabled = true;
        btnAiReject.disabled = true;
        aiSaveStatus.className = 'ai-save-status';
        aiSaveStatus.textContent = 'Saving…';

        fetch('/admin/pieces/' + currentPieceId + '/refine-save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                html_code: htmlField.value,
                css_code: cssField.value,
                generated_code: jsField.value,
                refinement_prompt: refinementPrompt,
                profile_id: lastRefineProfileId,
                persona_id: lastRefinePersonaId
            })
        })
        .then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok || !data.success) {
                    throw new Error(data.error || 'Save failed.');
                }
                return data;
            });
        })
        .then(function (data) {
            aiSaveStatus.className = 'ai-save-status is-success';
            aiSaveStatus.textContent = data.changed
                ? 'Saved as Version ' + data.version_number + '.'
                : 'No changes to save.';
            aiBanner.style.display = 'none';
            originalCode = null;
            document.getElementById('ai-diff-container').innerHTML = '';
            document.getElementById('ai-plan-container').innerHTML = '';
            setTimeout(clearAiSaveStatus, 6000);
        })
        .catch(function (err) {
            // Keep the banner open on failure so the admin can retry Accept
            // or fall back to Reject — the suggestion isn't lost.
            aiSaveStatus.className = 'ai-save-status is-error';
            aiSaveStatus.textContent = 'Save failed: ' + err.message;
        })
        .finally(function () {
            btnAiAccept.disabled = false;
            btnAiReject.disabled = false;
        });
    });

    btnAiReject.addEventListener('click', function () {
        clearAiSaveStatus();
        if (originalCode) {
            htmlField.value = originalCode.html;
            cssField.value = originalCode.css;
            jsField.value = originalCode.js;
            updateLivePreview();
        }
        aiBanner.style.display = 'none';
        originalCode = null;
        document.getElementById('ai-diff-container').innerHTML = '';
        document.getElementById('ai-plan-container').innerHTML = '';
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

    // 7. Reusable performCapture helper
    async function performCapture(pieceId) {
        var engine = engineField.value || 'p5';
        var html = htmlField.value || '';
        var css = cssField.value || '';
        var js = jsField.value || '';

        if (!html && !js) {
            throw new Error('Add HTML and JS code in the code tabs before capturing a thumbnail.');
        }

        var srcdoc = renderDocumentJS(titleField.value || 'Art piece', engine, html, css, js, true);

        // Mount an invisible-but-in-viewport iframe — genuinely off-screen
        // positioning (large negative left/top) is throttled or skipped for
        // requestAnimationFrame by some browsers as a power-saving measure,
        // which would stop p5/c2's own draw loop from ever running here
        // regardless of how long this code waits.
        var captureFrame = document.createElement('iframe');
        captureFrame.style.cssText = 'position:fixed;left:0;top:0;width:960px;height:540px;border:none;opacity:0;pointer-events:none;z-index:-1;';
        captureFrame.sandbox = 'allow-scripts allow-same-origin';
        document.body.appendChild(captureFrame);

        captureFrame.srcdoc = srcdoc;
        await new Promise(function (resolve) {
            captureFrame.onload = resolve;
            setTimeout(resolve, 800);
        });

        // For p5/c2, a canvas element existing isn't enough — it's created
        // before the first real draw() call, so require the ready marker
        // piece-runtime.js sets once something's actually been painted.
        // Other engines keep relying on the poll window itself.
        var requireReadyMarker = (engine === 'p5' || engine === 'c2' || engine === 'three');
        var canvas = null;
        for (var attempt = 0; attempt < 16 && !canvas; attempt++) {
            await new Promise(function (resolve) { setTimeout(resolve, 500); });
            var iframeDoc = captureFrame.contentDocument;
            var foundCanvas = iframeDoc && iframeDoc.querySelector('canvas');
            if (foundCanvas && (!requireReadyMarker || foundCanvas.dataset.creatrReady === '1')) {
                canvas = foundCanvas;
            }
            if (!canvas) {
                var svg = iframeDoc && iframeDoc.querySelector('svg');
                if (svg) {
                    canvas = await convertSvgToCanvas(svg, 960, 540);
                }
            }
        }

        if (!canvas) {
            document.body.removeChild(captureFrame);
            throw new Error('No canvas found. Make sure the piece renders to a canvas or svg element.');
        }

        var imageData;
        try {
            imageData = canvas.toDataURL('image/png');
        } catch (e) {
            document.body.removeChild(captureFrame);
            throw new Error('Canvas capture failed: ' + e.message);
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

        return await resp.json();
    }

    // 8. Auto-capture on form submit if dirty
    var initialHTML = htmlField.value;
    var initialCSS = cssField.value;
    var initialJS = jsField.value;
    var initialEngine = engineField.value;

    var form = document.getElementById('piece-editor-form');
    var isSubmitting = false;

    if (form) {
        form.addEventListener('submit', async function (e) {
            if (isSubmitting) return;

            var pieceId = <?= $isEdit ? (int) $piece['id'] : 'null' ?>;
            if (!pieceId) {
                // Creating a new piece: we don't have an ID yet, so we cannot capture the thumbnail beforehand.
                return;
            }

            var isDirty = (
                htmlField.value !== initialHTML ||
                cssField.value !== initialCSS ||
                jsField.value !== initialJS ||
                engineField.value !== initialEngine
            );

            if (isDirty) {
                e.preventDefault();
                isSubmitting = true;

                var submitBtn = form.querySelector('button[type="submit"]');
                var origText = submitBtn ? submitBtn.textContent : '';
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Saving & Capturing Thumbnail…';
                }

                try {
                    var result = await performCapture(pieceId);
                    document.getElementById('thumbnail_url').value = result.url;
                } catch (err) {
                    console.error('Auto thumbnail capture failed:', err);
                    if (!confirm('Auto thumbnail capture failed: ' + err.message + '\n\nDo you want to save the changes anyway without updating the thumbnail?')) {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.textContent = origText;
                        }
                        isSubmitting = false;
                        return;
                    }
                }

                form.submit();
            }
        });
    }

    // 9. Manual capture button click
    var captureBtn = document.getElementById('btn-capture-thumbnail');
    if (captureBtn) {
        captureBtn.addEventListener('click', async function () {
            var pieceId = captureBtn.dataset.pieceId;
            captureBtn.textContent = 'Capturing…';
            captureBtn.disabled = true;

            try {
                var result = await performCapture(pieceId);

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
