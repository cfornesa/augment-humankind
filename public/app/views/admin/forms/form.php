<?php
$isEdit = !empty($form['id']);
$pageTitle = $isEdit ? 'Edit Form' : 'New Form';
$configSources = $configSources ?? Form::configurationSources($form);
ob_start();
?>
<div class="admin-section">
    <div class="admin-section-head">
        <div>
            <p class="eyebrow">Forms</p>
            <h1><?= $isEdit ? e($form['title']) : 'New Form' ?></h1>
        </div>
        <a href="/admin/forms" class="admin-btn admin-btn-ghost">Back</a>
    </div>
    <?php if ($formError ?? null): ?><p class="admin-error"><?= e($formError) ?></p><?php endif; ?>
    <form class="admin-form" method="post" action="<?= $isEdit ? '/admin/forms/' . (int) $form['id'] . '/edit' : '/admin/forms/create' ?>">
        <div class="admin-tabs" role="tablist" aria-label="Form editor">
            <button type="button" class="admin-tab active" data-tab="settings">Settings</button>
            <button type="button" class="admin-tab" data-tab="fields">Fields</button>
            <?php if (($form['form_type'] ?? '') === Form::TYPE_NEWSLETTER): ?>
                <button type="button" class="admin-tab" data-tab="signups">Signups</button>
            <?php endif; ?>
        </div>
        <div class="form-tab-panel" data-panel="settings">
            <div class="field-grid">
                <div class="field">
                    <label for="title">Title</label>
                    <input id="title" name="title" required value="<?= e($form['title'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="form_key">Key</label>
                    <input id="form_key" name="form_key" <?= $isEdit ? 'readonly' : '' ?> required value="<?= e($form['form_key'] ?? '') ?>">
                </div>
            </div>
            <div class="field-grid">
                <div class="field">
                    <label for="form_type">Type</label>
                    <select id="form_type" name="form_type">
                        <option value="email" <?= ($form['form_type'] ?? '') === 'email' ? 'selected' : '' ?>>Email form</option>
                        <option value="newsletter" <?= ($form['form_type'] ?? '') === 'newsletter' ? 'selected' : '' ?>>Newsletter signup</option>
                    </select>
                </div>
                <div class="field">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="active" <?= ($form['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($form['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>
            <div class="field">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3"><?= e($form['description'] ?? '') ?></textarea>
            </div>
            <div class="field-grid">
                <div class="field">
                    <label for="recipient_email">Recipient email</label>
                    <input id="recipient_email" name="recipient_email" type="email" value="<?= e($form['recipient_email'] ?? '') ?>">
                    <small>
                        <?= ($form['form_type'] ?? '') === Form::TYPE_NEWSLETTER ? 'Not required for newsletter signup forms.' : e($configSources['recipient_email'] ?? 'Missing.') ?>
                    </small>
                </div>
                <div class="field">
                    <label for="submit_label">Submit label</label>
                    <input id="submit_label" name="submit_label" value="<?= e($form['submit_label'] ?? 'Submit') ?>">
                </div>
            </div>
            <div class="field-grid">
                <div class="field">
                    <label for="recaptcha_site_key">reCAPTCHA v3 site key</label>
                    <input id="recaptcha_site_key" name="recaptcha_site_key" value="<?= e($form['recaptcha_site_key'] ?? '') ?>">
                    <small><?= e($configSources['recaptcha_site_key'] ?? 'Missing.') ?></small>
                </div>
                <div class="field">
                    <label for="recaptcha_minimum_score">Minimum score</label>
                    <input id="recaptcha_minimum_score" name="recaptcha_minimum_score" type="number" step="0.01" min="0" max="1" value="<?= e((string) ($form['recaptcha_minimum_score'] ?? '0.50')) ?>">
                </div>
            </div>
            <div class="field">
                <label for="recaptcha_secret">reCAPTCHA v3 secret</label>
                <input id="recaptcha_secret" name="recaptcha_secret" type="password" placeholder="<?= !empty($form['encrypted_recaptcha_secret']) ? 'Leave blank to keep current secret' : '' ?>">
                <small><?= e($configSources['recaptcha_secret'] ?? 'Missing.') ?></small>
                <?php if (!empty($form['encrypted_recaptcha_secret'])): ?>
                    <label class="toggle-opt"><input type="checkbox" name="clear_recaptcha_secret" value="1"> Clear saved secret</label>
                <?php endif; ?>
            </div>
            <div class="field">
                <label for="success_message">Success message</label>
                <textarea id="success_message" name="success_message" rows="3"><?= e($form['success_message'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="form-tab-panel is-hidden" data-panel="fields">
            <?php if ($isEdit): ?>
                <p><a href="/admin/forms/<?= (int) $form['id'] ?>/fields/create" class="admin-btn">Add Field</a></p>
            <?php endif; ?>
            <?php if (empty($fields)): ?>
                <p class="admin-empty">No fields yet.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead><tr><th>Label</th><th>Key</th><th>Type</th><th>Required</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($fields as $field): ?>
                        <tr>
                            <td><?= e($field['label']) ?></td>
                            <td><code><?= e($field['field_key']) ?></code></td>
                            <td><?= e($field['field_type']) ?></td>
                            <td><?= !empty($field['is_required']) ? 'Yes' : 'No' ?></td>
                            <td class="admin-actions">
                                <a href="/admin/forms/fields/<?= (int) $field['id'] ?>/edit" class="admin-link">Edit</a>
                                <form method="post" action="/admin/forms/fields/<?= (int) $field['id'] ?>/delete" class="inline-form" onsubmit="return confirm('Delete this field?')">
                                    <button class="admin-link danger" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php if (($form['form_type'] ?? '') === Form::TYPE_NEWSLETTER): ?>
            <div class="form-tab-panel is-hidden" data-panel="signups">
                <?php if (empty($signups)): ?>
                    <p class="admin-empty">No newsletter signups yet.</p>
                <?php else: ?>
                    <table class="admin-table">
                        <thead><tr><th>Email</th><th>Consent</th><th>Source</th><th>Created</th></tr></thead>
                        <tbody>
                        <?php foreach ($signups as $signup): ?>
                            <tr>
                                <td><?= e($signup['email']) ?></td>
                                <td><?= !empty($signup['consent']) ? 'TRUE' : 'FALSE' ?></td>
                                <td><?= e($signup['source_path'] ?? '') ?></td>
                                <td><?= e($signup['created_at'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="form-actions">
            <button class="admin-btn" type="submit">Save Form</button>
        </div>
    </form>
</div>
<script>
document.querySelectorAll('.admin-tab[data-tab]').forEach(function (tab) {
    tab.addEventListener('click', function () {
        document.querySelectorAll('.admin-tab[data-tab]').forEach(function (item) { item.classList.remove('active'); });
        document.querySelectorAll('.form-tab-panel').forEach(function (panel) { panel.classList.add('is-hidden'); });
        tab.classList.add('active');
        var panel = document.querySelector('.form-tab-panel[data-panel="' + tab.dataset.tab + '"]');
        if (panel) panel.classList.remove('is-hidden');
    });
});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/../layout.php'; ?>
