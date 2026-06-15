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

        <div class="field">
            <label for="structured_spec">Structured Spec</label>
            <textarea id="structured_spec" name="structured_spec" rows="6"><?= e($version['structured_spec'] ?? '') ?></textarea>
        </div>

        <div class="field">
            <label for="html_code">HTML Code</label>
            <textarea id="html_code" name="html_code" rows="6"><?= e($version['html_code'] ?? '') ?></textarea>
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
    </form>
</div>
<?php
$content = ob_get_clean();

require dirname(__DIR__) . '/layout.php';
