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
        <div class="form-actions">
            <button type="submit" class="admin-btn"><?= $isEdit ? 'Update' : 'Add' ?> Setting</button>
            <a href="/admin/ai-settings?tab=profiles" class="admin-btn admin-btn-ghost">Cancel</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();

require dirname(__DIR__) . '/layout.php';
