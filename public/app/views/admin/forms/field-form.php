<?php $isEdit = !empty($field['id']); $pageTitle = $isEdit ? 'Edit Field' : 'New Field'; ob_start(); ?>
<div class="admin-section">
    <div class="admin-section-head">
        <div>
            <p class="eyebrow">Forms</p>
            <h1><?= $isEdit ? 'Edit Field' : 'New Field' ?></h1>
        </div>
        <a href="/admin/forms/<?= (int) $form['id'] ?>/edit" class="admin-btn admin-btn-ghost">Back</a>
    </div>
    <?php if ($fieldError ?? null): ?><p class="admin-error"><?= e($fieldError) ?></p><?php endif; ?>
    <form class="admin-form" method="post" action="<?= $isEdit ? '/admin/forms/fields/' . (int) $field['id'] . '/edit' : '/admin/forms/' . (int) $form['id'] . '/fields/create' ?>">
        <div class="field-grid">
            <div class="field">
                <label for="label">Label</label>
                <input id="label" name="label" required value="<?= e($field['label'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="field_key">Key</label>
                <input id="field_key" name="field_key" required value="<?= e($field['field_key'] ?? '') ?>">
            </div>
        </div>
        <div class="field-grid">
            <div class="field">
                <label for="field_type">Type</label>
                <select id="field_type" name="field_type">
                    <?php foreach (['text', 'email', 'textarea', 'select', 'checkbox'] as $type): ?>
                        <option value="<?= e($type) ?>" <?= ($field['field_type'] ?? '') === $type ? 'selected' : '' ?>><?= e($type) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="sort_order">Position</label>
                <input id="sort_order" name="sort_order" type="number" value="<?= (int) ($field['sort_order'] ?? 0) ?>">
            </div>
        </div>
        <label class="toggle-opt"><input type="checkbox" name="is_required" value="1" <?= !empty($field['is_required']) ? 'checked' : '' ?>> Required</label>
        <div class="field">
            <label for="placeholder">Placeholder</label>
            <input id="placeholder" name="placeholder" value="<?= e($field['placeholder'] ?? '') ?>">
        </div>
        <div class="field">
            <label for="help_text">Help text</label>
            <textarea id="help_text" name="help_text" rows="3"><?= e($field['help_text'] ?? '') ?></textarea>
        </div>
        <div class="field">
            <label for="options_text">Options</label>
            <textarea id="options_text" name="options_text" rows="5"><?= e(Form::optionsText($field ?? [])) ?></textarea>
            <small>For select fields only. Use one option per line as <code>value|Label</code>.</small>
        </div>
        <div class="form-actions">
            <button class="admin-btn" type="submit">Save Field</button>
        </div>
    </form>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../layout.php'; ?>
