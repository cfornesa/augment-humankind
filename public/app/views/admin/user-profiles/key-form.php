<?php

declare(strict_types=1);

$isEdit = !empty($key['id']);
$pageTitle = $isEdit ? 'Edit API Key' : 'Add API Key';

ob_start();
$key = $key ?? [];
?>
<div class="admin-container">
    <div class="admin-header-row">
        <h1><?= $isEdit ? 'Edit API Key' : 'Add API Key' ?></h1>
        <a href="/admin/ai-settings?tab=keys" class="admin-btn admin-btn-ghost">Back</a>
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
                    <option value="<?= e($user['id']) ?>" <?= ($key['user_id'] ?? '') === $user['id'] ? 'selected' : '' ?>>
                        <?= e($user['name'] ?? '') ?> (<?= e($user['email'] ?? '') ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="vendor">Vendor</label>
            <input id="vendor" name="vendor" type="text" required maxlength="64"
                   value="<?= e($key['vendor'] ?? '') ?>">
        </div>
        <div class="field">
            <label for="api_key">API Key</label>
            <input id="api_key" name="api_key" type="password" required maxlength="2048"
                   placeholder="<?= $isEdit ? 'Leave blank to keep unchanged' : '' ?>">
            <?php if ($isEdit): ?>
                <small>Leave blank to keep the existing key.</small>
            <?php endif; ?>
        </div>
        <div class="form-actions">
            <button type="submit" class="admin-btn"><?= $isEdit ? 'Update' : 'Add' ?> Key</button>
            <a href="/admin/ai-settings?tab=keys" class="admin-btn admin-btn-ghost">Cancel</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();

require dirname(__DIR__) . '/layout.php';
