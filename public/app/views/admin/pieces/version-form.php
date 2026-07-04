<?php

declare(strict_types=1);

$isEdit = !empty($version['id']);
$pageTitle = ($isEdit ? 'Edit Version' : 'Add Version') . ' — ' . ($piece['title'] ?? 'Piece');

ob_start();
$version = $version ?? [];
?>
<div class="admin-container">
    <div class="admin-header-row">
        <h1><?= $isEdit ? 'Edit Version' : 'Add Version' ?>: <?= e($piece['title'] ?? 'Untitled') ?></h1>
        <a href="/admin/pieces/<?= (int) $piece['id'] ?>/versions" class="admin-btn admin-btn-ghost">Back</a>
    </div>

    <?php if ($error): ?>
        <div class="form-status form-status-error" role="alert">
            <p><?= e($error) ?></p>
        </div>
    <?php endif; ?>

    <form method="post" class="admin-form">
        <div class="field">
            <label for="prompt">Prompt <span class="required">*</span></label>
            <textarea id="prompt" name="prompt" rows="4" required><?= e($version['prompt'] ?? '') ?></textarea>
        </div>

        <div class="field-grid">
            <div class="field">
                <label for="engine">Engine</label>
                <select id="engine" name="engine">
                    <option value="p5" <?= ($version['engine'] ?? 'p5') === 'p5' ? 'selected' : '' ?>>P5.js</option>
                    <option value="c2" <?= ($version['engine'] ?? '') === 'c2' ? 'selected' : '' ?>>C2.js</option>
                    <option value="three" <?= ($version['engine'] ?? '') === 'three' ? 'selected' : '' ?>>Three.js</option>
                    <option value="svg" <?= ($version['engine'] ?? '') === 'svg' ? 'selected' : '' ?>>SVG</option>
                    <option value="aframe" <?= ($version['engine'] ?? '') === 'aframe' ? 'selected' : '' ?>>A-Frame</option>
                </select>
            </div>
            <div class="field">
                <label for="generation_mode">Generation Mode</label>
                <select id="generation_mode" name="generation_mode">
                    <?php $generationModeVal = art_piece_version_generation_mode($version, $piece); ?>
                    <option value="p5" <?= $generationModeVal === 'p5' ? 'selected' : '' ?>>P5.js</option>
                    <option value="c2" <?= $generationModeVal === 'c2' ? 'selected' : '' ?>>C2.js</option>
                    <option value="c2_interactive" <?= $generationModeVal === 'c2_interactive' ? 'selected' : '' ?>>C2.js Interactive</option>
                    <option value="three" <?= $generationModeVal === 'three' ? 'selected' : '' ?>>Three.js</option>
                    <option value="svg" <?= $generationModeVal === 'svg' ? 'selected' : '' ?>>SVG</option>
                    <option value="aframe" <?= $generationModeVal === 'aframe' ? 'selected' : '' ?>>A-Frame</option>
                </select>
            </div>
            <div class="field">
                <label for="validation_status">Validation Status</label>
                <select id="validation_status" name="validation_status">
                    <option value="validated" <?= ($version['validation_status'] ?? 'validated') === 'validated' ? 'selected' : '' ?>>Validated</option>
                    <option value="needs_review" <?= ($version['validation_status'] ?? '') === 'needs_review' ? 'selected' : '' ?>>Needs Review</option>
                    <option value="rejected" <?= ($version['validation_status'] ?? '') === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>
        </div>

        <div class="field-grid">
            <div class="field">
                <label for="generation_vendor">Generation Vendor</label>
                <input id="generation_vendor" name="generation_vendor" type="text" maxlength="64"
                       value="<?= e($version['generation_vendor'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="generation_model">Generation Model</label>
                <input id="generation_model" name="generation_model" type="text" maxlength="191"
                       value="<?= e($version['generation_model'] ?? '') ?>">
            </div>
        </div>

        <div class="field">
            <label for="generation_attempt_count">Attempt Count</label>
            <input id="generation_attempt_count" name="generation_attempt_count" type="number" min="1"
                   value="<?= (int) ($version['generation_attempt_count'] ?? 1) ?>">
        </div>

        <div class="field-grid">
            <div class="field">
                <label for="ai_profile_id">AI Profile Used</label>
                <select id="ai_profile_id" name="ai_profile_id">
                    <option value="">(Blank)</option>
                    <?php foreach (($profiles ?? []) as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= ((int) ($version['ai_profile_id'] ?? 0) === (int) $p['id']) ? 'selected' : '' ?>>
                            <?= e($p['profile_name']) ?> (<?= e($p['vendor']) ?> - <?= e($p['model']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="ai_persona_id">AI Persona Used</label>
                <select id="ai_persona_id" name="ai_persona_id">
                    <option value="">(Blank)</option>
                    <?php foreach (($personas ?? []) as $persona): ?>
                        <option value="<?= (int) $persona['id'] ?>" <?= ((int) ($version['ai_persona_id'] ?? 0) === (int) $persona['id']) ? 'selected' : '' ?>>
                            <?= e((string) $persona['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="field">
            <label for="structured_spec">Structured Spec</label>
            <textarea id="structured_spec" name="structured_spec" rows="6"><?= e($version['structured_spec'] ?? '') ?></textarea>
        </div>

<?php
$htmlCodeVal = $version['html_code'] ?? '';
$engineVal = $version['engine'] ?? 'p5';
if ($engineVal === 'p5') {
    $htmlCodeVal = '<div id="canvas-container"></div>';
} elseif ($engineVal === 'c2') {
    $htmlCodeVal = '<canvas id="piece-canvas"></canvas>';
} elseif ($engineVal === 'three') {
    $htmlCodeVal = '<div id="container"></div>';
}
?>
        <div class="field" id="field-html-code">
            <label for="html_code">HTML Code</label>
            <textarea id="html_code" name="html_code" rows="6"><?= e($htmlCodeVal) ?></textarea>
        </div>

        <div class="field">
            <label for="css_code">CSS Code</label>
            <textarea id="css_code" name="css_code" rows="6"><?= e($version['css_code'] ?? '') ?></textarea>
        </div>

        <div class="field">
            <label for="generated_code">Generated Code (JS)</label>
            <textarea id="generated_code" name="generated_code" rows="8"><?= e($version['generated_code'] ?? '') ?></textarea>
        </div>

        <div class="field">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" rows="3"><?= e($version['notes'] ?? '') ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="admin-btn"><?= $isEdit ? 'Update' : 'Add' ?> Version</button>
            <a href="/admin/pieces/<?= (int) $piece['id'] ?>/versions" class="admin-btn admin-btn-ghost">Cancel</a>
        </div>

        <script>
        (function () {
            var engineField = document.getElementById('engine');
            var modeField = document.getElementById('generation_mode');
            var htmlField = document.getElementById('html_code');
            var fieldHtmlCode = document.getElementById('field-html-code');
            var modeToEngine = {
                p5: 'p5',
                c2: 'c2',
                c2_interactive: 'c2',
                three: 'three',
                svg: 'svg',
                aframe: 'aframe'
            };

            function updateEngineHtmlVisibility(engine) {
                if (!fieldHtmlCode) return;
                if (engine === 'svg' || engine === 'aframe') {
                    fieldHtmlCode.style.display = '';
                } else {
                    fieldHtmlCode.style.display = 'none';
                    if (engine === 'p5') {
                        htmlField.value = '<div id="canvas-container"></div>';
                    } else if (engine === 'c2') {
                        htmlField.value = '<canvas id="piece-canvas"></canvas>';
                    } else if (engine === 'three') {
                        htmlField.value = '<div id="container"></div>';
                    }
                }
            }

            function syncModeToEngine() {
                if (!modeField || !engineField) return;
                engineField.value = modeToEngine[modeField.value] || 'p5';
                updateEngineHtmlVisibility(engineField.value);
            }

            function syncEngineToMode() {
                if (!modeField || !engineField) return;
                if (engineField.value === 'c2') {
                    if (modeField.value !== 'c2_interactive') {
                        modeField.value = 'c2';
                    }
                } else {
                    modeField.value = engineField.value;
                }
                updateEngineHtmlVisibility(engineField.value);
            }

            if (engineField) {
                engineField.addEventListener('change', function () {
                    syncEngineToMode();
                });
                updateEngineHtmlVisibility(engineField.value);
            }
            if (modeField) {
                modeField.addEventListener('change', syncModeToEngine);
            }
        })();
        </script>
    </form>
</div>
<?php
$content = ob_get_clean();

require dirname(__DIR__) . '/layout.php';
