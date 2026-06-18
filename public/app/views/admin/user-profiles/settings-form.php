<?php

declare(strict_types=1);

$isEdit = !empty($setting['id']);
$pageTitle = $isEdit ? 'Edit AI Setting' : 'Add AI Setting';

ob_start();
$setting = $setting ?? [];
?>
<div class="admin-container">
    <div class="admin-header-row">
        <h1><?= $isEdit ? 'Edit AI Setting' : 'Add AI Setting' ?></h1>
        <a href="/admin/ai-settings?tab=profiles" class="admin-btn admin-btn-ghost">Back</a>
    </div>

    <?php if ($error): ?>
        <div class="form-status form-status-error" role="alert">
            <p><?= e($error) ?></p>
        </div>
    <?php endif; ?>

    <form method="post" class="admin-form">
        <?php if (!UserAiVendorSettings::supportsCapabilitiesColumn()): ?>
            <div class="form-status" role="status">
                <p>This database does not yet store explicit AI profile capabilities. Saved profiles will still work, and the runtime will infer capabilities from vendor/model metadata until the June 18 migration is applied.</p>
            </div>
        <?php endif; ?>
        <div class="field">
            <label for="user_id">User</label>
            <select id="user_id" name="user_id" required>
                <option value="">Choose user</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= e($user['id']) ?>" <?= ($setting['user_id'] ?? '') === $user['id'] ? 'selected' : '' ?>>
                        <?= e($user['name'] ?? '') ?> (<?= e($user['email'] ?? '') ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="vendor">Vendor</label>
            <input id="vendor" name="vendor" type="text" required maxlength="64"
                   value="<?= e($setting['vendor'] ?? '') ?>">
        </div>
        <div class="field">
            <label for="profile_name">Profile Name</label>
            <input id="profile_name" name="profile_name" type="text" maxlength="128"
                   value="<?= e($setting['profile_name'] ?? 'Default') ?>">
        </div>
        <div class="field">
            <label for="endpoint_kind">Endpoint Kind</label>
            <input id="endpoint_kind" name="endpoint_kind" type="text" maxlength="32"
                   value="<?= e($setting['endpoint_kind'] ?? '') ?>">
        </div>
        <div class="field">
            <label for="model">Model</label>
            <input id="model" name="model" type="text" maxlength="191"
                   value="<?= e($setting['model'] ?? '') ?>">
        </div>
        <div class="field">
            <label class="checkbox-label">
                <input type="checkbox" name="enabled" value="1" <?= (int) ($setting['enabled'] ?? 0) ? 'checked' : '' ?>>
                Enabled
            </label>
        </div>
        <?php
        $caps = array_map('trim', explode(',', (string) ($setting['capabilities'] ?? 'text,code')));
        ?>
        <fieldset class="field" style="border: 1px solid var(--line); padding: 0.75rem 1rem; margin: 0;">
            <legend style="font-weight: 700; font-size: 0.875rem; padding: 0 0.25rem;">Capabilities</legend>
            <div style="display: flex; flex-wrap: wrap; gap: 0.75rem 1.5rem; margin-top: 0.25rem;">
                <label class="checkbox-label">
                    <input type="checkbox" name="cap_text" value="1" <?= in_array('text', $caps, true) ? 'checked' : '' ?>>
                    Text generation
                </label>
                <label class="checkbox-label">
                    <input type="checkbox" name="cap_code" value="1" <?= in_array('code', $caps, true) ? 'checked' : '' ?>>
                    Code generation (art pieces)
                </label>
                <label class="checkbox-label">
                    <input type="checkbox" name="cap_vision" value="1" <?= in_array('vision', $caps, true) ? 'checked' : '' ?>>
                    Vision / image description
                </label>
            </div>
        </fieldset>
        <div class="form-actions">
            <button type="submit" class="admin-btn"><?= $isEdit ? 'Update' : 'Add' ?> Setting</button>
            <a href="/admin/ai-settings?tab=profiles" class="admin-btn admin-btn-ghost">Cancel</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();

require dirname(__DIR__) . '/layout.php';
