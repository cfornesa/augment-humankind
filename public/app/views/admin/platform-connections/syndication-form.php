<?php

declare(strict_types=1);

$pageTitle = 'Add Syndication';

ob_start();
$syndication = $syndication ?? [];
?>
<div class="admin-container">
    <div class="admin-header-row">
        <h1>Add Syndication</h1>
        <a href="/admin/platform-connections" class="admin-btn admin-btn-ghost">Back</a>
    </div>

    <?php if ($error): ?>
        <div class="form-status form-status-error" role="alert">
            <p><?= e($error) ?></p>
        </div>
    <?php endif; ?>

    <form method="post" class="admin-form">
        <div class="field">
            <label for="post_id">Post <span class="required">*</span></label>
            <select id="post_id" name="post_id" required>
                <option value="">Choose post</option>
                <?php foreach ($posts as $post): ?>
                    <option value="<?= (int) $post['id'] ?>" <?= (int) ($syndication['post_id'] ?? 0) === (int) $post['id'] ? 'selected' : '' ?>>
                        <?= e($post['title'] ?? 'Untitled') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="platform_connection_id">Platform Connection <span class="required">*</span></label>
            <select id="platform_connection_id" name="platform_connection_id" required>
                <option value="">Choose connection</option>
                <?php foreach ($connections as $conn): ?>
                    <option value="<?= (int) $conn['id'] ?>" <?= (int) ($syndication['platform_connection_id'] ?? 0) === (int) $conn['id'] ? 'selected' : '' ?>>
                        <?= e($conn['platform']) ?> (<?= e($conn['user_name'] ?? '') ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="external_id">External ID</label>
            <input id="external_id" name="external_id" type="text" maxlength="512"
                   value="<?= e($syndication['external_id'] ?? '') ?>">
        </div>
        <div class="field">
            <label for="external_url">External URL</label>
            <input id="external_url" name="external_url" type="url" maxlength="2048"
                   value="<?= e($syndication['external_url'] ?? '') ?>">
        </div>
        <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="pending" <?= ($syndication['status'] ?? 'pending') === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="synced" <?= ($syndication['status'] ?? '') === 'synced' ? 'selected' : '' ?>>Synced</option>
                <option value="failed" <?= ($syndication['status'] ?? '') === 'failed' ? 'selected' : '' ?>>Failed</option>
            </select>
        </div>
        <div class="form-actions">
            <button type="submit" class="admin-btn">Add Syndication</button>
            <a href="/admin/platform-connections" class="admin-btn admin-btn-ghost">Cancel</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();

require dirname(__DIR__) . '/layout.php';
