<?php

declare(strict_types=1);

$isEdit = !empty($connection['id']);
$pageTitle = $isEdit ? 'Edit Connection' : 'Add Connection';

ob_start();
$connection = $connection ?? [];
?>
<div class="admin-container">
    <div class="admin-header-row">
        <h1><?= $isEdit ? 'Edit Connection' : 'Add Connection' ?></h1>
        <a href="/admin/platform-connections" class="admin-btn admin-btn-ghost">Back</a>
    </div>

    <?php if ($error): ?>
        <div class="form-status form-status-error" role="alert">
            <p><?= e($error) ?></p>
        </div>
    <?php endif; ?>

    <form method="post" class="admin-form">
        <div class="field">
            <label for="platform">Platform <span class="required">*</span></label>
            <input id="platform" name="platform" type="text" required maxlength="64"
                   value="<?= e($connection['platform'] ?? '') ?>">
            <small>e.g. wordpress, blogger, bluesky, linkedin</small>
        </div>
        <div class="field">
            <label for="user_id">User</label>
            <select id="user_id" name="user_id">
                <option value="">—</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= e($user['id']) ?>" <?= ($connection['user_id'] ?? '') === $user['id'] ? 'selected' : '' ?>>
                        <?= e($user['name'] ?? '') ?> (<?= e($user['email'] ?? '') ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="access_token">Access Token</label>
            <input id="access_token" name="access_token" type="password" maxlength="2048"
                   placeholder="<?= $isEdit ? 'Leave blank to keep unchanged' : '' ?>">
        </div>
        <div class="field">
            <label for="refresh_token">Refresh Token</label>
            <input id="refresh_token" name="refresh_token" type="password" maxlength="2048">
        </div>
        <div class="field">
            <label for="expires_at">Expires At</label>
            <input id="expires_at" name="expires_at" type="datetime-local"
                   value="<?= e($connection['expires_at'] ?? '') ?>">
        </div>
        <div class="field">
            <label for="metadata">Metadata (JSON)</label>
            <textarea id="metadata" name="metadata" rows="3"><?= e($connection['metadata'] ?? '') ?></textarea>
        </div>
        <div class="field">
            <label class="checkbox-label">
                <input type="checkbox" name="enabled" value="1" <?= (int) ($connection['enabled'] ?? 1) ? 'checked' : '' ?>>
                Enabled
            </label>
        </div>
        <div class="form-actions">
            <button type="submit" class="admin-btn"><?= $isEdit ? 'Update' : 'Add' ?> Connection</button>
            <a href="/admin/platform-connections" class="admin-btn admin-btn-ghost">Cancel</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();

require dirname(__DIR__) . '/layout.php';
