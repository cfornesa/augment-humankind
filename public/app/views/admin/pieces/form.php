<?php

declare(strict_types=1);

$isEdit = !empty($piece['id']);
$pageTitle = $isEdit ? 'Edit Piece' : 'Create Piece';

ob_start();
$piece = $piece ?? [];
$profiles = $profiles ?? [];
$preferredProfileId = $preferredProfileId ?? null;
$pieceEngine = (string) ($piece['engine'] ?? 'p5');
$aiRefineEnabled = feature_enabled('ai_pieces_code') && feature_ai_piece_engine_enabled($pieceEngine);
$cv = $piece['current_version'] ?? [];
$soundControlsAvailable = function_exists('art_piece_sonic_params_supported') && art_piece_sonic_params_supported();
$currentSonicFeel = $soundControlsAvailable ? art_piece_sonic_feel($cv['sonic_params'] ?? null) : '';
$currentSonicEnabled = $soundControlsAvailable && !empty($cv['sonic_params']);
$audioTabAvailable = $soundControlsAvailable;
$sonicExtras = $audioTabAvailable && function_exists('validate_art_piece_sonic_extras')
    ? validate_art_piece_sonic_extras((json_decode((string) ($cv['sonic_params'] ?? ''), true) ?: [])['extras'] ?? null)
    : null;
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
    flex-direction: column;
    align-items: flex-start;
    gap: 0.75rem;
}

.ai-banner span {
    font-size: 0.95rem;
    color: var(--ink);
    line-height: 1.4;
}

.ai-banner-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
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
    display: inline-block;
    vertical-align: middle;
    text-align: center;
    min-height: 2.2rem;
    padding: 0.35rem 1rem;
    font-size: 0.9rem;
    box-shadow: 3px 3px 0 var(--line);
    white-space: nowrap;
}

.ai-banner .admin-btn:hover {
    box-shadow: 1px 1px 0 var(--line);
    transform: translate(2px, 2px);
}

.ai-plan-container,
.ai-visual-container,
.ai-diff-container {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    font-weight: 400;
}

.ai-plan-container:empty,
.ai-visual-container:empty,
.ai-diff-container:empty {
    display: none;
}

.ai-visual-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
}

.ai-visual-shot {
    background: var(--white);
    border: 2px solid var(--line);
    padding: 0.5rem;
}

.ai-visual-shot strong,
.ai-visual-warning strong {
    display: block;
    margin-bottom: 0.35rem;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--ink);
}

.ai-visual-shot img {
    width: 100%;
    display: block;
    border: 1px solid var(--line);
    background: #0d0d0f;
}

.ai-visual-warning {
    background: var(--white);
    border: 2px solid var(--line);
    padding: 0.5rem 0.75rem;
    color: var(--ink);
    font-weight: 700;
}

.ai-visual-warning.is-low {
    border-color: #b3261e;
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
    .ai-visual-grid {
        grid-template-columns: 1fr;
    }

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
                <button type="button" id="btn-ai-stronger" class="admin-btn admin-btn-ghost" hidden>Request Stronger Change</button>
                <button type="button" id="btn-ai-reject" class="admin-btn admin-btn-ghost">Reject</button>
                <span id="ai-save-status" class="ai-save-status" role="status" aria-live="polite"></span>
            </div>
        </div>
        <div id="ai-visual-container" class="ai-visual-container" aria-label="Rendered comparison"></div>
        <div id="ai-plan-container" class="ai-plan-container" aria-label="AI's stated plan"></div>
        <div id="ai-diff-container" class="ai-diff-container" aria-label="Code changes"></div>
    </div>

    <dialog id="refine-attempt-failed-dialog" class="inline-create-dialog">
        <div class="dialog-header">
            <h2 id="refine-attempt-failed-title">Attempt 1 of 5 failed</h2>
        </div>
        <div class="dialog-body">
            <p id="refine-attempt-failed-message"></p>
            <p>This attempt's code has been saved as a non-current version you can review later, even if you stop here — nothing is lost. Spending another attempt will use more tokens.</p>
        </div>
        <div class="dialog-footer">
            <button type="button" class="admin-btn admin-btn-ghost" id="refine-attempt-give-up-btn">Give Up</button>
            <button type="button" class="admin-btn" id="refine-attempt-try-again-btn">Try Again</button>
        </div>
    </dialog>

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
                        <?php if ($aiRefineEnabled): ?>
                            <button type="button" class="admin-tab" data-tab="ai" role="tab" aria-selected="false" style="border-color: var(--yellow); background: rgba(254, 224, 72, 0.1);">AI Refine ✨</button>
                        <?php endif ?>
                        <?php if ($audioTabAvailable): ?>
                            <button type="button" class="admin-tab" data-tab="audio" role="tab" aria-selected="false">Interact</button>
                        <?php endif ?>
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
                                    <?php if (!$isEdit): ?><option value="c2_interactive">C2.js Interactive</option><?php endif; ?>
                                    <option value="three" <?= ($piece['engine'] ?? '') === 'three' ? 'selected' : '' ?>>Three.js</option>
                                    <option value="svg" <?= ($piece['engine'] ?? '') === 'svg' ? 'selected' : '' ?>>SVG</option>
                                    <option value="aframe" <?= ($piece['engine'] ?? '') === 'aframe' ? 'selected' : '' ?>>A-Frame</option>
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

                        <?php if (function_exists('art_piece_camera_overlay_supported') && art_piece_camera_overlay_supported()): ?>
                        <div class="field">
                            <?php
                            $cameraOverlayMode = art_piece_version_generation_mode($cv, $piece);
                            $cameraOverlayStored = isset($cv['camera_overlay']) && $cv['camera_overlay'] !== null && $cv['camera_overlay'] !== ''
                                ? (int) $cv['camera_overlay']
                                : art_piece_camera_overlay_default($cameraOverlayMode);
                            $cameraOverlayValue = $cameraOverlayStored !== null ? (string) $cameraOverlayStored : '';
                            ?>
                            <label for="camera_overlay">Camera overlay</label>
                            <select id="camera_overlay" name="camera_overlay">
                                <option value="" <?= $cameraOverlayValue === '' ? 'selected' : '' ?>>Default (follow hand-tracking)</option>
                                <option value="1" <?= $cameraOverlayValue === '1' ? 'selected' : '' ?>>On</option>
                                <option value="0" <?= $cameraOverlayValue === '0' ? 'selected' : '' ?>>Off</option>
                            </select>
                            <small class="admin-hint" style="display: block; margin-top: 0.25rem;">
                                Lets visitors show their camera behind/over the piece (with their permission). On by default for P5.js, plain C2.js, and SVG; authors can explicitly turn it off. On steerable engines it also unlocks hand control (camera steering).
                            </small>
                        </div>
                        <?php endif; ?>

                        <?php // Rendered only when a sound design exists — a hidden-but-
                              // submitting checkbox here is what once let a plain save
                              // fabricate sonic_params on sound-less pieces. ?>
                        <?php if (!empty($cv['sonic_params'])): ?>
                        <div id="sound-playback-toggle-wrap" class="field">
                            <?php
                            $sonicDecoded = json_decode((string) $cv['sonic_params'], true);
                            $soundPlayEnabled = !is_array($sonicDecoded) || ($sonicDecoded['enabled'] ?? true) !== false;
                            ?>
                            <?php // Presence marker: lets the controller tell "checkbox
                                  // rendered but unchecked" (persist enabled=false) apart
                                  // from "checkbox not rendered at all" (preserve stored
                                  // value) — e.g. a save right after AI Refine adds sound. ?>
                            <input type="hidden" name="sound_playback_present" value="1">
                            <label class="checkbox-label">
                                <input type="checkbox" id="sound_playback_active" name="sound_playback_active" value="1"
                                       <?= $soundPlayEnabled ? 'checked' : '' ?>>
                                Enable sound playback on this piece
                            </label>
                            <small class="admin-hint" style="display: block; margin-top: 0.25rem;">
                                This piece has an AI-generated sound design. Unchecking this disables sound playback globally on all public and immersive surfaces.
                            </small>
                        </div>
                        <?php endif; ?>

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
                    $versionNum = $cv['version_number'] ?? null;
                    ?>
                    <!-- HTML Tab -->
                    <div id="tab-html" class="piece-tab-panel is-hidden" role="tabpanel">
                        <div class="field">
<?php
$htmlCodeVal = $cv['html_code'] ?? '';
$engineVal = $piece['engine'] ?? 'p5';
if ($engineVal === 'p5') {
    $htmlCodeVal = '<div id="canvas-container"></div>';
} elseif ($engineVal === 'c2') {
    $htmlCodeVal = '<canvas id="piece-canvas"></canvas>';
} elseif ($engineVal === 'three') {
    $htmlCodeVal = '<div id="container"></div>';
}
?>
                            <label for="html_code">HTML</label>
                            <textarea id="html_code" name="html_code" rows="18" class="code-field" aria-describedby="html-desc"><?= e($htmlCodeVal) ?></textarea>
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
                    <?php if ($aiRefineEnabled): ?>
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
                        <?php if ($soundControlsAvailable): ?>
                        <div class="field">
                            <label class="checkbox-label">
                                <input type="checkbox" id="ai_sound_enabled" value="1">
                                Add or update instrumentation
                            </label>
                        </div>
                        <div class="field" id="ai-sound-feel-field" style="display:none;">
                            <label for="ai_sound_feel">Tone Feel</label>
                            <textarea id="ai_sound_feel" rows="2" maxlength="400" placeholder="E.g. 'Ethereal, minor scale theremin sound.'"><?= e($currentSonicFeel) ?></textarea>
                        </div>
                        <?php endif; ?>
                        <div style="margin-top: 1rem;">
                            <div id="ai-refine-status" role="status" aria-live="polite" style="min-height:1.4em; margin-bottom:0.5rem; font-size:0.875rem; color:var(--ink-soft);"></div>
                            <button type="button" id="btn-refine-ai" class="admin-btn" style="background: var(--yellow);">Request AI Changes</button>
                        </div>
                    </div>
                    <?php endif ?>

                    <?php if ($audioTabAvailable): ?>
                    <div id="tab-audio" class="piece-tab-panel is-hidden" role="tabpanel">
                        <?php if ($currentSonicEnabled): ?>
                        <?php // Presence marker (same pattern as sound_playback_present):
                              // these fieldsets' values live in sonic extras; when they
                              // were never rendered, the controller must preserve the
                              // stored extras instead of reading absent checkboxes as
                              // "everything off". ?>
                        <input type="hidden" name="sonic_extras_present" value="1">
                        <fieldset class="form-fieldset">
                            <legend>Public sound controls</legend>
                            <p class="admin-hint" style="margin-top:0;">Which of the sonification voices are exposed to visitors on the sound popover. Volume and the on/off toggle are always shown regardless of these.</p>
                            <label class="checkbox-label">
                                <input type="checkbox" name="sonic_voice_ambient" value="1" <?= $sonicExtras['voices']['ambient'] ? 'checked' : '' ?>>
                                Ambient soundscape (idle notes)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="sonic_voice_movement" value="1" <?= $sonicExtras['voices']['movement'] ? 'checked' : '' ?>>
                                Movement sounds (camera/pointer motion)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="sonic_voice_melodic" value="1" <?= $sonicExtras['voices']['melodic'] ? 'checked' : '' ?>>
                                Keyboard (melodic voice, piano UI)
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="sonic_voice_hand_tracking" value="1" <?= $sonicExtras['voices']['hand_tracking'] ? 'checked' : '' ?>>
                                Hand-tracking (camera theremin)
                            </label>
                        </fieldset>

                        <fieldset class="form-fieldset">
                            <legend>Public camera controls</legend>
                            <p class="admin-hint" style="margin-top:0;">Camera steering for visitors on steerable engines (Three.js, A-Frame, interactive C2). Available whenever the camera overlay (Metadata tab) or hand-tracking above unlocks it.</p>
                            <label class="checkbox-label">
                                <input type="checkbox" name="sonic_voice_hand_control" value="1" <?= ($sonicExtras['voices']['hand_control'] ?? true) ? 'checked' : '' ?>>
                                Hand control (camera steering / tilt fallback)
                            </label>
                        </fieldset>
                        <?php else: ?>
                        <fieldset class="form-fieldset">
                            <legend>Public camera controls</legend>
                            <p class="admin-hint" style="margin-top:0;">This piece has no sound design, so there are no voice settings here. The camera overlay permission lives on the Metadata tab; when it is on, visitors on steerable engines (Three.js, A-Frame, interactive C2) automatically get hand control (camera steering with a device-tilt fallback).</p>
                        </fieldset>
                        <?php endif; ?>

                        <?php if ($currentSonicEnabled): ?>
                        <div class="field">
                            <label for="sonic_default_volume">Default volume</label>
                            <input type="range" id="sonic_default_volume" name="sonic_default_volume" min="0" max="100" step="1" value="<?= (int) $sonicExtras['default_volume'] ?>">
                        </div>

                        <?php if (function_exists('feature_enabled') && feature_enabled('media_audio')): ?>
                        <fieldset class="form-fieldset">
                            <legend>Ambient sample (admin only — never shown publicly)</legend>
                            <p class="admin-hint" style="margin-top:0;">Loop an uploaded audio file for the ambient voice instead of a synthesized instrument. Movement and melodic voices are unaffected.</p>
                            <label class="checkbox-label">
                                <input type="checkbox" name="sonic_ambient_sample_enabled" value="1" id="sonic_ambient_sample_enabled" <?= $sonicExtras['synth']['ambient_sample']['enabled'] ? 'checked' : '' ?>>
                                Use uploaded sample for ambient voice
                            </label>
                            <div class="field">
                                <input type="hidden" name="sonic_ambient_sample_media_id" id="sonic_ambient_sample_media_id" value="<?= (int) ($sonicExtras['synth']['ambient_sample']['media_id'] ?? 0) ?>">
                                <button type="button" class="admin-btn admin-btn-ghost" id="sonic_ambient_sample_choose">Choose audio file</button>
                                <span id="sonic_ambient_sample_name"></span>
                                <audio id="sonic_ambient_sample_preview" controls style="display:<?= $sonicExtras['synth']['ambient_sample']['media_id'] ? 'block' : 'none' ?>;margin-top:0.5rem;" <?= $sonicExtras['synth']['ambient_sample']['media_id'] ? 'src="/media/' . (int) $sonicExtras['synth']['ambient_sample']['media_id'] . '"' : '' ?>></audio>
                            </div>
                        </fieldset>
                        <script>
                        (function () {
                            var chooseBtn = document.getElementById('sonic_ambient_sample_choose');
                            var idField = document.getElementById('sonic_ambient_sample_media_id');
                            var nameEl = document.getElementById('sonic_ambient_sample_name');
                            var preview = document.getElementById('sonic_ambient_sample_preview');
                            var enabledCheckbox = document.getElementById('sonic_ambient_sample_enabled');
                            chooseBtn?.addEventListener('click', function () {
                                if (!window.openMediaPicker) return;
                                window.openMediaPicker(function (result) {
                                    if (!result || !result.id) return;
                                    idField.value = result.id;
                                    if (nameEl) nameEl.textContent = result.title || ('Audio #' + result.id);
                                    if (preview) {
                                        preview.src = result.url || ('/media/' + result.id);
                                        preview.style.display = 'block';
                                    }
                                    if (enabledCheckbox) enabledCheckbox.checked = true;
                                }, 'select', { mode: 'audio' });
                            });
                        })();
                        </script>
                        <?php endif; ?>

                        <fieldset class="form-fieldset">
                            <legend>Synth controls (admin only — never shown publicly)</legend>
                            <div class="field-grid">
                                <div class="field">
                                    <label for="sonic_octave_min">Octave range: min</label>
                                    <input type="number" id="sonic_octave_min" name="sonic_octave_min" min="-1" max="7" value="<?= (int) $sonicExtras['synth']['octave_min'] ?>">
                                </div>
                                <div class="field">
                                    <label for="sonic_octave_max">Octave range: max</label>
                                    <input type="number" id="sonic_octave_max" name="sonic_octave_max" min="-1" max="7" value="<?= (int) $sonicExtras['synth']['octave_max'] ?>">
                                </div>
                                <div class="field">
                                    <label for="sonic_filter_type">Filter type</label>
                                    <select id="sonic_filter_type" name="sonic_filter_type">
                                        <option value="lowpass" <?= $sonicExtras['synth']['filter_type'] === 'lowpass' ? 'selected' : '' ?>>Lowpass</option>
                                        <option value="highpass" <?= $sonicExtras['synth']['filter_type'] === 'highpass' ? 'selected' : '' ?>>Highpass</option>
                                        <option value="bandpass" <?= $sonicExtras['synth']['filter_type'] === 'bandpass' ? 'selected' : '' ?>>Bandpass</option>
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="sonic_filter_cutoff">Filter cutoff (Hz)</label>
                                    <input type="number" id="sonic_filter_cutoff" name="sonic_filter_cutoff" min="20" max="20000" step="1" value="<?= (float) $sonicExtras['synth']['filter_cutoff'] ?>">
                                </div>
                                <div class="field">
                                    <label for="sonic_filter_resonance">Resonance (Q)</label>
                                    <input type="number" id="sonic_filter_resonance" name="sonic_filter_resonance" min="0.1" max="20" step="0.1" value="<?= (float) $sonicExtras['synth']['filter_resonance'] ?>">
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="form-fieldset">
                            <legend>Effects (admin only — never shown publicly)</legend>
                            <div class="field-grid">
                                <div class="field">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="sonic_fx_distortion_enabled" value="1" <?= $sonicExtras['synth']['effects']['distortion']['enabled'] ? 'checked' : '' ?>>
                                        Distortion
                                    </label>
                                    <label for="sonic_fx_distortion_amount">Amount</label>
                                    <input type="range" id="sonic_fx_distortion_amount" name="sonic_fx_distortion_amount" min="0" max="1" step="0.05" value="<?= (float) $sonicExtras['synth']['effects']['distortion']['amount'] ?>">
                                </div>
                                <div class="field">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="sonic_fx_chorus_enabled" value="1" <?= $sonicExtras['synth']['effects']['chorus']['enabled'] ? 'checked' : '' ?>>
                                        Chorus
                                    </label>
                                    <label for="sonic_fx_chorus_depth">Depth</label>
                                    <input type="range" id="sonic_fx_chorus_depth" name="sonic_fx_chorus_depth" min="0" max="1" step="0.05" value="<?= (float) $sonicExtras['synth']['effects']['chorus']['depth'] ?>">
                                    <label for="sonic_fx_chorus_rate">Rate (Hz)</label>
                                    <input type="number" id="sonic_fx_chorus_rate" name="sonic_fx_chorus_rate" min="0.1" max="20" step="0.1" value="<?= (float) $sonicExtras['synth']['effects']['chorus']['rate'] ?>">
                                </div>
                                <div class="field">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="sonic_fx_tremolo_enabled" value="1" <?= $sonicExtras['synth']['effects']['tremolo']['enabled'] ? 'checked' : '' ?>>
                                        Tremolo
                                    </label>
                                    <label for="sonic_fx_tremolo_depth">Depth</label>
                                    <input type="range" id="sonic_fx_tremolo_depth" name="sonic_fx_tremolo_depth" min="0" max="1" step="0.05" value="<?= (float) $sonicExtras['synth']['effects']['tremolo']['depth'] ?>">
                                    <label for="sonic_fx_tremolo_rate">Rate (Hz)</label>
                                    <input type="number" id="sonic_fx_tremolo_rate" name="sonic_fx_tremolo_rate" min="0.1" max="20" step="0.1" value="<?= (float) $sonicExtras['synth']['effects']['tremolo']['rate'] ?>">
                                </div>
                                <div class="field">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="sonic_fx_pitch_shift_enabled" value="1" <?= $sonicExtras['synth']['effects']['pitch_shift']['enabled'] ? 'checked' : '' ?>>
                                        Pitch shift
                                    </label>
                                    <label for="sonic_fx_pitch_shift_semitones">Semitones</label>
                                    <input type="number" id="sonic_fx_pitch_shift_semitones" name="sonic_fx_pitch_shift_semitones" min="-24" max="24" step="1" value="<?= (int) $sonicExtras['synth']['effects']['pitch_shift']['semitones'] ?>">
                                </div>
                                <div class="field">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="sonic_fx_bitcrusher_enabled" value="1" <?= $sonicExtras['synth']['effects']['bitcrusher']['enabled'] ? 'checked' : '' ?>>
                                        Bitcrusher
                                    </label>
                                    <label for="sonic_fx_bitcrusher_bits">Bits</label>
                                    <input type="number" id="sonic_fx_bitcrusher_bits" name="sonic_fx_bitcrusher_bits" min="1" max="16" step="1" value="<?= (int) $sonicExtras['synth']['effects']['bitcrusher']['bits'] ?>">
                                </div>
                                <div class="field">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="sonic_fx_flanger_enabled" value="1" <?= $sonicExtras['synth']['effects']['flanger']['enabled'] ? 'checked' : '' ?>>
                                        Flanger
                                    </label>
                                    <label for="sonic_fx_flanger_depth">Depth (s)</label>
                                    <input type="number" id="sonic_fx_flanger_depth" name="sonic_fx_flanger_depth" min="0" max="0.02" step="0.001" value="<?= (float) $sonicExtras['synth']['effects']['flanger']['depth'] ?>">
                                    <label for="sonic_fx_flanger_rate">Rate (Hz)</label>
                                    <input type="number" id="sonic_fx_flanger_rate" name="sonic_fx_flanger_rate" min="0.05" max="5" step="0.05" value="<?= (float) $sonicExtras['synth']['effects']['flanger']['rate'] ?>">
                                    <label for="sonic_fx_flanger_feedback">Feedback</label>
                                    <input type="number" id="sonic_fx_flanger_feedback" name="sonic_fx_flanger_feedback" min="0" max="0.95" step="0.05" value="<?= (float) $sonicExtras['synth']['effects']['flanger']['feedback'] ?>">
                                </div>
                                <div class="field">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="sonic_fx_ring_mod_enabled" value="1" <?= $sonicExtras['synth']['effects']['ring_mod']['enabled'] ? 'checked' : '' ?>>
                                        Ring modulator
                                    </label>
                                    <label for="sonic_fx_ring_mod_frequency">Carrier frequency (Hz)</label>
                                    <input type="number" id="sonic_fx_ring_mod_frequency" name="sonic_fx_ring_mod_frequency" min="1" max="5000" step="1" value="<?= (float) $sonicExtras['synth']['effects']['ring_mod']['frequency'] ?>">
                                </div>
                            </div>
                        </fieldset>
                        <?php endif; ?>
                    </div>
                    <?php endif ?>

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
                        <button type="button" id="preview-sound-toggle" class="admin-btn admin-btn-sm admin-btn-ghost" data-piece-sound-toggle aria-pressed="false" aria-label="Unmute sound" hidden>
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4z"/><line x1="22" y1="9" x2="16" y2="15"/><line x1="16" y1="9" x2="22" y2="15"/></svg>
                        </button>
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

<script src="/assets/js/admin-piece-capture.js?v=<?= (int) @filemtime(dirname(__DIR__, 4) . '/assets/js/admin-piece-capture.js') ?>"></script>
<script>
(function () {
    // Computed server-side from the actual request (not window.location.origin):
    // the preview/capture iframes below use srcdoc, which gets an opaque
    // origin — window.location.origin would literally be the string "null"
    // inside them, even with sandbox="allow-same-origin".
    var RUNTIME_ORIGIN = <?= json_encode(
        ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
    ) ?>;
    // DOM Elements
    var tabs = document.querySelectorAll('.piece-edit-tabs .admin-tab');
    var panels = {
        meta: document.getElementById('tab-meta'),
        html: document.getElementById('tab-html'),
        css: document.getElementById('tab-css'),
        js: document.getElementById('tab-js'),
        ai: document.getElementById('tab-ai'),
        audio: document.getElementById('tab-audio')
    };

    var htmlField = document.getElementById('html_code');
    var cssField = document.getElementById('css_code');
    var jsField = document.getElementById('generated_code');
    var engineField = document.getElementById('engine');
    var cameraOverlayField = document.getElementById('camera_overlay');
    var cameraOverlayWasChanged = false;
    var titleField = document.getElementById('title');
    var previewStage = document.getElementById('preview-stage-wrapper');
    var currentPieceId = <?= $isEdit ? (int) $piece['id'] : 'null' ?>;
    var isEditMode = <?= $isEdit ? 'true' : 'false' ?>;
    var starterTemplates = <?= json_encode(array_map(static fn (array $template): array => [
        'html' => (string) ($template['html_code'] ?? ''),
        'css' => (string) ($template['css_code'] ?? ''),
        'js' => (string) ($template['js_code'] ?? ''),
        'engine' => (string) ($template['engine'] ?? ''),
    ], $starterTemplates ?? []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    var lastTemplateMode = engineField.value || 'p5';

    var btnRefineAi = document.getElementById('btn-refine-ai');
    var aiRefineStatusEl = document.getElementById('ai-refine-status');
    var aiProfileField = document.getElementById('ai_profile_id');
    var aiPersonaField = document.getElementById('ai_persona_id');
    var aiPromptField = document.getElementById('ai_refine_prompt');
    var aiSoundToggle = document.getElementById('ai_sound_enabled');
    var aiSoundFeelField = document.getElementById('ai_sound_feel');
    var aiSoundFeelWrap = document.getElementById('ai-sound-feel-field');

    var aiBanner = document.getElementById('ai-suggestion-banner');
    var btnAiAccept = document.getElementById('btn-ai-accept');
    var btnAiStronger = document.getElementById('btn-ai-stronger');
    var btnAiReject = document.getElementById('btn-ai-reject');

    var toggleEditor = document.getElementById('toggle-view-editor');
    var togglePreview = document.getElementById('toggle-view-preview');
    var paneEditor = document.querySelector('.pane-editor');
    var panePreview = document.querySelector('.pane-preview');

    var originalCode = null;
    var previewTimeout = null;
    var lastRefineProfileId = null;
    var lastRefinePersonaId = null;
    var lastVisualDeltaLow = false;
    var lastRefineFeedback = '';
    var lastProposedCode = null;
    var lastProposedSonicParams = null;
    // The piece's currently-saved sound (if any) — never mutated; used to
    // reset the preview when an AI suggestion is rejected/cleared.
    var savedPreviewSonicParams = <?= json_encode(
        (is_array($cv) && !empty($cv['sonic_params'])) ? json_decode((string) $cv['sonic_params'], true) : null
    ) ?>;
    // What the preview actually renders — starts as the saved value, updated
    // to the proposed sonic_params after a successful AI Refine so the
    // preview always reflects whatever is about to be saved/accepted.
    var currentPreviewSonicParams = savedPreviewSonicParams;
    var previewSoundToggle = document.getElementById('preview-sound-toggle');
    var previewIframe = null;
    var isAiRequestInFlight = false;
    var lastDialogClosedAt = 0;
    // Carries the successful attempt's persisted draft version forward to
    // Accept, and the whole sequence's token forward so Accept can clean up
    // the failed siblings from the same sequence.
    var lastDraftVersionId = null;
    var lastSequenceToken = '';
    var ART_PIECE_MAX_ATTEMPTS = 5;
    // PRELIMINARY value — not evidence-based (the two real timeouts seen so
    // far were 2-3 minutes apart, not rapid-fire, and this app's own
    // ai_refine_piece rate limit has no minimum-spacing rule, just a
    // 6-per-15-minutes cap) — a debounce against accidental rapid
    // clicking regardless of cause. Needs a real re-test: if AI Refine
    // still hits the same 120s provider timeout pattern after this ships,
    // 30s did not address it and the actual cause is still unknown.
    var REFINE_RETRY_COOLDOWN_SECONDS = 30;

    // 1. Tab Switching
    if (aiSoundToggle && aiSoundFeelWrap) {
        aiSoundToggle.addEventListener('change', function () {
            aiSoundFeelWrap.style.display = this.checked ? 'block' : 'none';
        });
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (t) { 
                t.classList.remove('active'); 
                t.setAttribute('aria-selected', 'false');
            });
            tab.classList.add('active');
            tab.setAttribute('aria-selected', 'true');
            Object.keys(panels).forEach(function (key) {
                if (!panels[key]) return;
                if (key === tab.dataset.tab) {
                    panels[key].classList.remove('is-hidden');
                } else {
                    panels[key].classList.add('is-hidden');
                }
            });
        });
    });

    // 2. Render Helper
    function renderDocumentJS(title, engine, html, css, js, preserveDrawingBuffer, sonicParams) {
        return window.CreatrPieceCapture.renderDocument({
            title: title,
            engine: engine,
            html: html,
            css: css,
            js: js,
            runtimeOrigin: RUNTIME_ORIGIN,
            preserveDrawingBuffer: preserveDrawingBuffer !== false,
            sonicParams: sonicParams || null
        });
    }

    // Sound toggle: mirrors piece-fullscreen.js's pattern for the public
    // site, except the target iframe is replaced on every re-render, so the
    // click handler and message listener always read the current reference
    // (previewIframe) rather than closing over a stale one.
    var previewSoundEnabled = false;
    function setPreviewSoundBtnState(muted) {
        if (!previewSoundToggle) return;
        previewSoundToggle.setAttribute('aria-pressed', muted ? 'false' : 'true');
        previewSoundToggle.setAttribute('aria-label', muted ? 'Unmute sound' : 'Mute sound');
    }
    if (previewSoundToggle) {
        previewSoundToggle.addEventListener('click', function () {
            var next = !previewSoundEnabled;
            if (previewIframe && previewIframe.contentWindow) {
                previewIframe.contentWindow.postMessage({ type: 'creatr-sound-toggle', enabled: next }, '*');
            }
            previewSoundEnabled = next;
            setPreviewSoundBtnState(!next);
        });
        window.addEventListener('message', function (event) {
            if (!previewIframe || event.source !== previewIframe.contentWindow || !event.data || event.data.type !== 'creatr-sound-state') {
                return;
            }
            previewSoundEnabled = !!event.data.enabled;
            setPreviewSoundBtnState(!previewSoundEnabled);
        });
    }

    // 3. Update Live Preview function
    function updateLivePreview() {
        var title = titleField.value || 'Art piece';
        var selectedMode = engineField.value || 'p5';
        var engine = (starterTemplates[selectedMode] && starterTemplates[selectedMode].engine) || selectedMode;
        var html = htmlField.value || '';
        var css = cssField.value || '';
        var js = jsField.value || '';

        // Generate full document srcdoc
        var srcdoc = renderDocumentJS(title, engine, html, css, js, true, currentPreviewSonicParams);

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
        previewIframe = iframe;

        // A fresh iframe always boots muted (piece-runtime.js's own default),
        // so the toggle must reset to match rather than carry over stale state.
        previewSoundEnabled = false;
        if (previewSoundToggle) {
            previewSoundToggle.hidden = !currentPreviewSonicParams;
            setPreviewSoundBtnState(true);
        }
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

    function updateEngineHtmlVisibility(engine) {
        var htmlTabButton = document.querySelector('.piece-edit-tabs button[data-tab="html"]');
        if (!htmlTabButton) return;

        var selectedMode = engine || 'p5';
        var template = starterTemplates[selectedMode] || null;
        var previousTemplate = starterTemplates[lastTemplateMode] || null;
        var codeIsTemplateOrEmpty = !isEditMode && (
            (!htmlField.value.trim() && !cssField.value.trim() && !jsField.value.trim()) ||
            (previousTemplate && htmlField.value === previousTemplate.html && cssField.value === previousTemplate.css && jsField.value === previousTemplate.js)
        );
        if (template && codeIsTemplateOrEmpty) {
            htmlField.value = template.html;
            cssField.value = template.css;
            jsField.value = template.js;
            lastTemplateMode = selectedMode;
        }

        var renderEngine = (template && template.engine) || selectedMode;
        if (renderEngine === 'svg' || renderEngine === 'aframe') {
            htmlTabButton.style.display = '';
        } else if (!template || !codeIsTemplateOrEmpty) {
            htmlTabButton.style.display = 'none';
            if (htmlTabButton.classList.contains('active')) {
                var metaTabButton = document.querySelector('.piece-edit-tabs button[data-tab="meta"]');
                if (metaTabButton) {
                    metaTabButton.click();
                }
            }

            if (renderEngine === 'p5') {
                htmlField.value = '<div id="canvas-container"></div>';
            } else if (renderEngine === 'c2') {
                htmlField.value = '<canvas id="piece-canvas"></canvas>';
            } else if (renderEngine === 'three') {
                htmlField.value = '<div id="container"></div>';
            }
        } else {
            htmlTabButton.style.display = 'none';
        }
    }

    function updateNewPieceCameraDefault(engine) {
        if (isEditMode || !cameraOverlayField || cameraOverlayWasChanged) return;
        cameraOverlayField.value = ['p5', 'c2', 'svg'].includes(engine || 'p5') ? '1' : '';
    }

    // Hook listeners
    htmlField.addEventListener('input', queuePreviewUpdate);
    cssField.addEventListener('input', queuePreviewUpdate);
    jsField.addEventListener('input', queuePreviewUpdate);
    if (cameraOverlayField) {
        cameraOverlayField.addEventListener('change', function () {
            cameraOverlayWasChanged = true;
        });
    }
    engineField.addEventListener('change', function () {
        updateEngineHtmlVisibility(engineField.value);
        updateNewPieceCameraDefault(engineField.value);
        updateLivePreview();
    });
    titleField.addEventListener('input', queuePreviewUpdate);

    // Initial load
    updateEngineHtmlVisibility(engineField.value);
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

    function buildCaptureSource(code, seed) {
        return {
            title: titleField.value || 'Art piece',
            engine: engineField.value || 'p5',
            html: code.html || '',
            css: code.css || '',
            js: code.js || '',
            runtimeOrigin: RUNTIME_ORIGIN,
            preserveDrawingBuffer: true,
            seed: seed || 424242,
            width: 960,
            height: 540
        };
    }

    function clearAiSuggestionUi() {
        document.getElementById('ai-diff-container').innerHTML = '';
        document.getElementById('ai-plan-container').innerHTML = '';
        document.getElementById('ai-visual-container').innerHTML = '';
        lastProposedSonicParams = null;
        currentPreviewSonicParams = savedPreviewSonicParams;
        lastVisualDeltaLow = false;
        lastRefineFeedback = '';
        if (btnAiStronger) btnAiStronger.hidden = true;
        if (btnAiAccept) btnAiAccept.textContent = 'Accept Changes';
    }

function renderVisualComparison() {
        var visualContainer = document.getElementById('ai-visual-container');
        visualContainer.innerHTML = '';

        // Create the Toggle Bar Container
        var toggleBar = document.createElement('div');
        toggleBar.className = 'ai-visual-toggle-bar';
        toggleBar.style.cssText = 'display:flex;gap:0.75rem;margin-bottom:1rem;';

        var btnShowRefined = document.createElement('button');
        btnShowRefined.type = 'button';
        btnShowRefined.className = 'admin-btn active';
        btnShowRefined.style.cssText = 'flex:1;justify-content:center;background:var(--green);';
        btnShowRefined.textContent = 'Show Refined (Live Preview)';

        var btnShowOriginal = document.createElement('button');
        btnShowOriginal.type = 'button';
        btnShowOriginal.className = 'admin-btn admin-btn-ghost';
        btnShowOriginal.style.cssText = 'flex:1;justify-content:center;background:var(--white);';
        btnShowOriginal.textContent = 'Show Original (Live Preview)';

        toggleBar.appendChild(btnShowRefined);
        toggleBar.appendChild(btnShowOriginal);
        visualContainer.appendChild(toggleBar);

        // Add visual indicator / label
        var statusLabel = document.createElement('div');
        statusLabel.style.cssText = 'font-size:0.875rem;color:var(--ink-soft);margin-bottom:1rem;text-align:center;font-weight:bold;';
        statusLabel.textContent = 'Currently showing: REFINED preview (with proposed changes).';
        visualContainer.appendChild(statusLabel);

        // Click listeners to toggle between original and proposed code in the editor textareas and live preview
        btnShowRefined.addEventListener('click', function () {
            btnShowRefined.classList.add('active');
            btnShowRefined.classList.remove('admin-btn-ghost');
            btnShowRefined.style.background = 'var(--green)';
            btnShowOriginal.classList.remove('active');
            btnShowOriginal.classList.add('admin-btn-ghost');
            btnShowOriginal.style.background = 'var(--white)';
            statusLabel.textContent = 'Currently showing: REFINED preview (with proposed changes).';
            
            htmlField.value = lastProposedCode.html;
            cssField.value = lastProposedCode.css;
            jsField.value = lastProposedCode.js;
            currentPreviewSonicParams = lastProposedSonicParams;
            updateLivePreview();
        });

        btnShowOriginal.addEventListener('click', function () {
            btnShowOriginal.classList.add('active');
            btnShowOriginal.classList.remove('admin-btn-ghost');
            btnShowOriginal.style.background = 'var(--green)';
            btnShowRefined.classList.remove('active');
            btnShowRefined.classList.add('admin-btn-ghost');
            btnShowRefined.style.background = 'var(--white)';
            statusLabel.textContent = 'Currently showing: ORIGINAL preview (before changes).';
            
            htmlField.value = originalCode.html;
            cssField.value = originalCode.css;
            jsField.value = originalCode.js;
            currentPreviewSonicParams = savedPreviewSonicParams;
            updateLivePreview();
        });
    }

    // 4. AI Refinement (Reframe)
    //
    // requestAiRefine() starts a brand new retry sequence (its own
    // sequence_token, attempt 1) and hands off to performRefineAttempt(),
    // which both the initial click and every "Try Again" from the
    // attempt-failed dialog call into — each one spends exactly one AI
    // attempt server-side (refineAi() no longer loops internally) and the
    // user explicitly decides whether to spend another after seeing a
    // failure, instead of up to 5 being burned automatically and invisibly.
    function requestAiRefine(extraFeedback) {
        if (isAiRequestInFlight) {
            return;
        }
        if (Date.now() - lastDialogClosedAt < 500) {
            return;
        }

        var prompt = aiPromptField.value.trim();
        var profileId = aiProfileField.value;
        var engine = engineField.value;

        if (!profileId) {
            alert('Please select an active AI vendor profile.');
            return;
        }
        var soundOnly = aiSoundToggle && aiSoundToggle.checked && aiSoundFeelField && aiSoundFeelField.value.trim() !== '';
        if (!prompt && !soundOnly) {
            alert('Please enter a refinement instruction, or a Tone Feel with sound enabled.');
            return;
        }

        isAiRequestInFlight = true;

        // Switch to the AI Refine tab immediately so the loading indicator is visible
        var aiTab = document.querySelector('.piece-edit-tabs button[data-tab="ai"]');
        if (aiTab) {
            aiTab.click();
        }

        var beforeRefine = {
            html: htmlField.value,
            css: cssField.value,
            js: jsField.value
        };
        var promptForRequest = prompt;
        if (extraFeedback) {
            promptForRequest += "\n\nAdditional rendered-output feedback: " + extraFeedback;
        }
        lastRefineFeedback = extraFeedback || '';

        // Clear any old banner and leftover save-status message from a
        // previous attempt.
        aiBanner.style.display = 'none';
        var aiSaveStatusEl = document.getElementById('ai-save-status');
        aiSaveStatusEl.textContent = '';
        aiSaveStatusEl.className = 'ai-save-status';
        clearAiSuggestionUi();

        var visualPrompt = aiPromptField ? aiPromptField.value.trim() : '';
        var soundEnabled = aiSoundToggle && aiSoundToggle.checked;
        var soundFeel = aiSoundFeelField ? aiSoundFeelField.value.trim() : '';
        var purposeDomain = 'visual';
        if (visualPrompt && soundEnabled && soundFeel) {
            purposeDomain = 'audio_visual';
        } else if (soundEnabled && soundFeel) {
            purposeDomain = 'audio';
        }

        var pieceOriginalPromptField = document.getElementById('prompt');
        var basePayload = {
            prompt: promptForRequest,
            purpose_domain: purposeDomain,
            engine: engine,
            profile_id: parseInt(profileId, 10),
            persona_id: aiPersonaField && aiPersonaField.value ? parseInt(aiPersonaField.value, 10) : 0,
            html_code: htmlField.value,
            css_code: cssField.value,
            generated_code: jsField.value,
            // The piece's own creative prompt, so the AI knows the original
            // intent it's refining, not just the raw code.
            original_prompt: pieceOriginalPromptField ? pieceOriginalPromptField.value.trim() : '',
            // Lets the server persist each attempt as a draft version —
            // omitted (0) for a brand new, not-yet-saved piece, which the
            // server treats as "nothing to attach a version to yet" rather
            // than an error.
            piece_id: currentPieceId || 0
        };
        if (aiSoundToggle && aiSoundToggle.checked) {
            basePayload.sound_enabled = true;
            basePayload.sound_feel = aiSoundFeelField ? aiSoundFeelField.value.trim() : '';
        }

        var sequenceToken = (window.crypto && window.crypto.randomUUID)
            ? window.crypto.randomUUID()
            : ('seq-' + Date.now() + '-' + Math.random().toString(36).slice(2));

        btnRefineAi.disabled = true;
        if (btnAiStronger) btnAiStronger.disabled = true;

        performRefineAttempt({
            basePayload: basePayload,
            beforeRefine: beforeRefine,
            sequenceToken: sequenceToken,
            attemptNumber: 1,
            previousRawResponse: null,
            lastError: null
        });
    }

    function performRefineAttempt(ctx) {
        isAiRequestInFlight = true;
        var refineStartedAt = Date.now();
        function setRefineElapsed(label) {
            if (!aiRefineStatusEl) return;
            var totalSeconds = Math.floor((Date.now() - refineStartedAt) / 1000);
            var minutes = Math.floor(totalSeconds / 60);
            var seconds = totalSeconds % 60;
            aiRefineStatusEl.textContent = (label || 'Requesting AI Changes') + ' (attempt ' + ctx.attemptNumber + ' of ' + ART_PIECE_MAX_ATTEMPTS + ')... ' + minutes + ':' + (seconds < 10 ? '0' : '') + seconds + ' elapsed';
        }
        setRefineElapsed();
        var refineTimerInterval = setInterval(function () { setRefineElapsed(); }, 1000);

        function stopAndReenable() {
            clearInterval(refineTimerInterval);
            if (aiRefineStatusEl) aiRefineStatusEl.textContent = '';
            isAiRequestInFlight = false;
            btnRefineAi.disabled = false;
            if (btnAiStronger) btnAiStronger.disabled = false;
        }

        var payload = Object.assign({}, ctx.basePayload, {
            attempt_number: ctx.attemptNumber,
            previous_raw_response: ctx.previousRawResponse,
            last_error: ctx.lastError,
            sequence_token: ctx.sequenceToken
        });

        // One-time retry on a network-level failure only (the fetch()
        // promise itself rejecting — e.g. "Load failed" — not a
        // server-returned error). A single attempt is short now (one AI
        // call, not a chain of up to 5), but a stale/dropped keep-alive
        // connection is still possible exactly like it was for Save
        // (generate-preview.php's submitSave()), which a fresh fetch()
        // recovers from since it opens a new connection.
        function fetchRefine(isRetry) {
            return fetch('/admin/pieces/refine-ai', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }).catch(function (networkErr) {
                if (isRetry) throw networkErr;
                setRefineElapsed('Connection issue — retrying');
                return new Promise(function (resolve) {
                    setTimeout(resolve, 1000);
                }).then(function () {
                    return fetchRefine(true);
                });
            });
        }

        fetchRefine(false)
        .then(function (res) {
            // A response that isn't valid JSON (an HTML error page, an
            // empty body, etc.) means something between the browser and
            // this app's PHP — most likely the hosting infrastructure's own
            // proxy/request timeout, which is outside this app's control —
            // cut the connection before PHP ever got to respond. res.json()
            // on that body throws a cryptic native browser error
            // ("Unexpected token" in Chrome, "The string did not match the
            // expected pattern." in Safari) that says nothing about what
            // actually happened. Read the body as text first so this is
            // treated as a normal, explainable attempt failure (offering
            // Try Again/Give Up) instead of a dead end.
            return res.text().then(function (rawText) {
                try {
                    return JSON.parse(rawText);
                } catch (parseErr) {
                    return {
                        success: false,
                        error: 'The server did not return a usable response (status ' + res.status + '). This usually means the request took too long and was cut off before finishing.',
                        attempt_number: ctx.attemptNumber,
                        can_retry: ctx.attemptNumber < ART_PIECE_MAX_ATTEMPTS,
                        draft_version_id: null,
                        raw_response: null
                    };
                }
            });
        })
        .then(function (data) {
            if (!data.success) {
                stopAndReenable();
                handleRefineAttemptFailure(ctx, data);
                return;
            }
            ctx.statusLabel = 'Generating preview snapshots';
            setRefineElapsed('Generating preview snapshots');
            handleRefineAttemptSuccess(ctx, data, stopAndReenable);
        })
        .catch(function (err) {
            // A genuinely unexpected client-side error (e.g. the visual
            // capture/diff step itself throwing) — distinct from an AI
            // attempt failing, so it isn't routed through the retry
            // dialog; nothing was written to the textareas, same as today.
            stopAndReenable();
            alert('AI Refinement Error: ' + err.message);
        });
    }

    function handleRefineAttemptSuccess(ctx, data, onComplete) {
        // Store backup if not already in preview mode
        if (!originalCode) {
            originalCode = ctx.beforeRefine;
        }

        // Remember which profile/persona produced this suggestion, so
        // accepting it can carry that attribution into the new version
        // saved when the piece form is submitted — and which draft version
        // row already holds this exact attempt, so Accept can promote it
        // instead of inserting a duplicate.
        lastRefineProfileId = data.profile_id || null;
        lastRefinePersonaId = data.persona_id || null;
        var rawSonic = Object.prototype.hasOwnProperty.call(data, 'sonic_params') ? data.sonic_params : null;
        if (typeof rawSonic === 'string' && rawSonic.trim() !== '') {
            try {
                lastProposedSonicParams = JSON.parse(rawSonic);
            } catch (e) {
                lastProposedSonicParams = null;
            }
        } else if (rawSonic && typeof rawSonic === 'object') {
            lastProposedSonicParams = rawSonic;
        } else {
            lastProposedSonicParams = null;
        }
        currentPreviewSonicParams = lastProposedSonicParams;
        lastDraftVersionId = data.draft_version_id || null;
        lastSequenceToken = data.sequence_token || ctx.sequenceToken;

        var proposedCode = {
            html: data.html_code || '',
            css: data.css_code || '',
            js: data.generated_code || ''
        };

        // Cache the proposed code for toggle comparisons
        lastProposedCode = proposedCode;

        // Set suggested code to textareas immediately
        htmlField.value = proposedCode.html;
        cssField.value = proposedCode.css;
        jsField.value = proposedCode.js;

        // Render live preview immediately
        updateLivePreview();

        // Render the toggle compare bar
        renderVisualComparison();

        // Always show the AI Accept button and Request Stronger button
        btnAiAccept.textContent = 'Accept Changes';
        if (btnAiStronger) {
            btnAiStronger.hidden = false;
        }

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
        var beforeRefine = ctx.beforeRefine;
        renderDiffBlock(diffContainer, 'HTML', beforeRefine.html, htmlField.value);
        renderDiffBlock(diffContainer, 'CSS', beforeRefine.css, cssField.value);
        renderDiffBlock(diffContainer, 'JS', beforeRefine.js, jsField.value);
        if (!diffContainer.children.length) {
            var noChange = document.createElement('p');
            noChange.textContent = 'No code differences detected.';
            diffContainer.appendChild(noChange);
        }

        // Display AI Suggestion banner
        aiBanner.style.display = 'flex';

        // Switch tab to JS or HTML so user can inspect the suggestion
        var jsTab = document.querySelector('.piece-edit-tabs button[data-tab="js"]');
        if (jsTab) {
            jsTab.click();
        }
        if (onComplete) onComplete();
    }

    // Shows the styled attempt-failed dialog (reusing the
    // .inline-create-dialog pattern already used elsewhere in this app)
    // instead of a native alert() — reports the failure and, unless the
    // attempt cap is reached, offers to spend one more attempt with the
    // AI's own previous response/error as repair context, exactly like the
    // automatic internal retry used to do, just now an explicit choice.
    function handleRefineAttemptFailure(ctx, data) {
        var dialog = document.getElementById('refine-attempt-failed-dialog');
        if (!dialog) {
            alert('AI Refinement Error: ' + (data.error || 'Unknown error'));
            return;
        }

        var titleEl = document.getElementById('refine-attempt-failed-title');
        var messageEl = document.getElementById('refine-attempt-failed-message');
        var tryAgainBtn = document.getElementById('refine-attempt-try-again-btn');
        var giveUpBtn = document.getElementById('refine-attempt-give-up-btn');

        var attemptNumber = data.attempt_number || ctx.attemptNumber;
        var canRetry = data.can_retry !== false && attemptNumber < ART_PIECE_MAX_ATTEMPTS;

        titleEl.textContent = 'Attempt ' + attemptNumber + ' of ' + ART_PIECE_MAX_ATTEMPTS + ' failed';
        messageEl.textContent = data.error || 'Unknown error';
        tryAgainBtn.hidden = !canRetry;

        // Clone-and-replace to drop any listener from a previous failed
        // attempt in this same sequence, matching this app's existing
        // dialog pattern (openInlineCreateDialog() in main.js).
        var newTryAgainBtn = tryAgainBtn.cloneNode(true);
        tryAgainBtn.parentNode.replaceChild(newTryAgainBtn, tryAgainBtn);
        var newGiveUpBtn = giveUpBtn.cloneNode(true);
        giveUpBtn.parentNode.replaceChild(newGiveUpBtn, giveUpBtn);

        // Cooldown: disable Try Again with a visible countdown before the
        // next attempt can be spent, regardless of why this one failed
        // (see REFINE_RETRY_COOLDOWN_SECONDS — preliminary). Declared
        // before both listeners so Give Up can stop it if the dialog is
        // dismissed mid-countdown, rather than leaking the interval.
        var cooldownInterval = null;

        newGiveUpBtn.addEventListener('click', function () {
            dialog.close();
            if (cooldownInterval) clearInterval(cooldownInterval);
            // Nothing to reset: the textareas were never written to until
            // a successful attempt's code is shown for review, so a
            // sequence that never succeeded already leaves them exactly as
            // they were before "Request AI Changes" was clicked. The
            // failed attempt's draft version (if one was created) remains
            // in the Versions list for later review/fork — only deleted
            // when a later attempt in the same sequence is accepted.
        });

        if (canRetry) {
            newTryAgainBtn.addEventListener('click', function () {
                if (isAiRequestInFlight) {
                    return;
                }
                dialog.close();
                if (cooldownInterval) clearInterval(cooldownInterval);
                btnRefineAi.disabled = true;
                if (btnAiStronger) btnAiStronger.disabled = true;
                performRefineAttempt({
                    basePayload: ctx.basePayload,
                    beforeRefine: ctx.beforeRefine,
                    sequenceToken: ctx.sequenceToken,
                    attemptNumber: attemptNumber + 1,
                    previousRawResponse: data.raw_response || null,
                    lastError: data.error || null
                });
            });

            var cooldownRemaining = REFINE_RETRY_COOLDOWN_SECONDS;
            newTryAgainBtn.disabled = true;
            newTryAgainBtn.textContent = 'Try Again (' + cooldownRemaining + 's)';
            cooldownInterval = setInterval(function () {
                cooldownRemaining--;
                if (cooldownRemaining <= 0) {
                    clearInterval(cooldownInterval);
                    newTryAgainBtn.disabled = false;
                    newTryAgainBtn.textContent = 'Try Again';
                } else {
                    newTryAgainBtn.textContent = 'Try Again (' + cooldownRemaining + 's)';
                }
            }, 1000);
        }

        dialog.showModal();
    }

    if (btnRefineAi) {
        btnRefineAi.addEventListener('click', function () {
            requestAiRefine('');
        });
    }

    var failedDialog = document.getElementById('refine-attempt-failed-dialog');
    if (failedDialog) {
        failedDialog.addEventListener('close', function () {
            lastDialogClosedAt = Date.now();
        });
    }

    if (btnAiStronger) {
        btnAiStronger.addEventListener('click', function () {
            requestAiRefine('The previous patch rendered too similarly to the current piece. Make the requested change visibly stronger while preserving unrelated behavior and only editing what the instruction asks to change.');
        });
    }

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
            clearAiSuggestionUi();
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
                persona_id: lastRefinePersonaId,
                sonic_params: lastProposedSonicParams,
                // Promotes the exact draft version this successful attempt
                // already persisted, instead of inserting a duplicate, and
                // lets the server delete this sequence's failed-attempt
                // siblings now that one of them is being accepted.
                draft_version_id: lastDraftVersionId,
                sequence_token: lastSequenceToken
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
            lastDraftVersionId = null;
            lastSequenceToken = '';
            resetDirtyBaselines();
            clearAiSuggestionUi();

            savedPreviewSonicParams = currentPreviewSonicParams;
            var wrap = document.getElementById('sound-playback-toggle-wrap');
            var cb = document.getElementById('sound_playback_active');
            if (wrap && cb) {
                if (savedPreviewSonicParams) {
                    wrap.style.display = 'block';
                    cb.checked = (savedPreviewSonicParams.enabled !== false);
                } else {
                    wrap.style.display = 'none';
                    cb.checked = false;
                }
            }

            // Thumbnail capture happens only at "Update" now, not here —
            // capturing immediately on Accept used to upload straight to the
            // server without updating the page's own #thumbnail_url field,
            // so a later "Update" click with no further edits (isDirty now
            // false, since resetDirtyBaselines() above just matched it)
            // would resubmit that stale field and overwrite what Accept had
            // just saved. Flagging it here means "Update" always knows a
            // capture is owed, with fresh data, right before it submits.
            if (data.changed) {
                pendingThumbnailCapture = true;
            }
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
            currentPreviewSonicParams = savedPreviewSonicParams;
            updateLivePreview();
        }
        aiBanner.style.display = 'none';
        originalCode = null;
        // The rejected attempt's draft version stays in the Versions list
        // either way — only ever deleted on a later Accept in the same
        // sequence — but clear these so a fresh "Request AI Changes" click
        // starts its own sequence instead of reusing this one's ids.
        lastDraftVersionId = null;
        lastSequenceToken = '';
        clearAiSuggestionUi();
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
        if (!htmlField.value && !jsField.value) {
            throw new Error('Add HTML and JS code in the code tabs before capturing a thumbnail.');
        }

        // Builds its own genuinely visible overlay instead of relying on
        // previewStage's iframe — that iframe lives inside the mobile
        // "Preview" tab pane, which is display:none until the admin
        // explicitly taps it. Script execution there runs fine regardless
        // (so the old waitForRender-on-previewStage version still reported
        // "ready"), but the GPU paint never happens under display:none,
        // same as the clipped background-iframe bug this was meant to fix.
        var capture = await window.CreatrPieceCapture.captureWithOverlay(Object.assign(
            buildCaptureSource({
                html: htmlField.value || '',
                css: cssField.value || '',
                js: jsField.value || ''
            }, 8383),
            { width: 320, height: 180 }
        ));
        if (!capture.ok) {
            throw new Error(capture.error || 'Thumbnail capture failed.');
        }

        var formData = new FormData();
        formData.append('image_data', capture.dataUrl);

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
    // Set by a successful AI Refine Accept instead of capturing immediately
    // there — Accept's own capture used to upload straight to the server
    // without ever touching the #thumbnail_url hidden field, so a later
    // "Update" click with no further edits would see isDirty===false (Accept
    // already reset the dirty baselines) and submit the form normally,
    // resubmitting that stale hidden field and silently overwriting what
    // Accept had just correctly saved. Tracked separately from isDirty so
    // "Update" always knows a capture is owed regardless of baseline resets.
    var pendingThumbnailCapture = false;

    function resetDirtyBaselines() {
        initialHTML = htmlField.value;
        initialCSS = cssField.value;
        initialJS = jsField.value;
        initialEngine = engineField.value;
    }

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

            if (isDirty || pendingThumbnailCapture) {
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
                    pendingThumbnailCapture = false;
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

                if (submitBtn) submitBtn.textContent = 'Saving…';

                // fetch() instead of a traditional form.submit() — by this
                // point the tab may have already sat through a long AI
                // Refine + capture sequence, making a stale/dropped
                // keep-alive connection just as likely here as it was for
                // the AI Refine request itself (fetchRefine() above) and
                // for the initial-generation Save flow
                // (generate-preview.php's submitSave()). A fresh fetch()
                // opens a new connection instead of reusing a dead one; one
                // retry on a network-level failure only, mirroring those
                // same precedents.
                function submitFormViaFetch(isRetry) {
                    return fetch(form.action || window.location.href, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: new FormData(form)
                    }).catch(function (networkErr) {
                        if (isRetry) throw networkErr;
                        if (submitBtn) submitBtn.textContent = 'Connection issue — retrying…';
                        return new Promise(function (resolve) {
                            setTimeout(resolve, 1000);
                        }).then(function () {
                            return submitFormViaFetch(true);
                        });
                    });
                }

                try {
                    var saveRes = await submitFormViaFetch(false);
                    var saveData = await saveRes.json();
                    if (saveData.success) {
                        window.location.href = saveData.redirect || '/admin/pieces';
                    } else {
                        alert('Save failed: ' + (saveData.error || 'Unknown error'));
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.textContent = origText;
                        }
                        isSubmitting = false;
                    }
                } catch (err) {
                    alert('Save failed: the connection was lost. Please try again.');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = origText;
                    }
                    isSubmitting = false;
                }
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
