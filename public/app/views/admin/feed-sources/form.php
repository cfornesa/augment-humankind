<?php

declare(strict_types=1);

$isEdit = !empty($source['id']);
$pageTitle = $isEdit ? 'Edit Feed Source' : 'Add Feed Source';

ob_start();
$source = $source ?? [];
?>
<div class="admin-container">
    <div class="admin-header-row">
        <h1><?= $isEdit ? 'Edit Feed Source' : 'Add Feed Source' ?></h1>
        <a href="/admin/feed-sources" class="admin-btn admin-btn-ghost">Back</a>
    </div>

    <?php if ($error): ?>
        <div class="form-status form-status-error" role="alert">
            <p><?= e($error) ?></p>
        </div>
    <?php endif; ?>

    <form method="post" class="admin-form">
        <div class="field">
            <label for="name">Name <span class="required">*</span></label>
            <input id="name" name="name" type="text" required maxlength="255"
                   value="<?= e($source['name'] ?? '') ?>">
        </div>

        <div class="field">
            <label for="feed_url">Feed URL <span class="required">*</span></label>
            <input id="feed_url" name="feed_url" type="url" required maxlength="2048"
                   value="<?= e($source['feed_url'] ?? '') ?>">
            <small>RSS or Atom feed URL.</small>
        </div>

        <div class="field-grid">
            <div class="field">
                <label for="site_url">Site URL</label>
                <input id="site_url" name="site_url" type="url" maxlength="2048"
                       value="<?= e($source['site_url'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="cadence">Cadence</label>
                <select id="cadence" name="cadence">
                    <option value="hourly" <?= ($source['cadence'] ?? '') === 'hourly' ? 'selected' : '' ?>>Hourly</option>
                    <option value="daily" <?= ($source['cadence'] ?? 'daily') === 'daily' ? 'selected' : '' ?>>Daily</option>
                    <option value="weekly" <?= ($source['cadence'] ?? '') === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                    <option value="monthly" <?= ($source['cadence'] ?? '') === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                </select>
            </div>
        </div>

        <div class="field-grid">
            <div class="field">
                <label for="author_name">Author Name</label>
                <input id="author_name" name="author_name" type="text" maxlength="255"
                       value="<?= e($source['author_name'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="username">Username</label>
                <input id="username" name="username" type="text" maxlength="100"
                       value="<?= e($source['username'] ?? '') ?>">
            </div>
        </div>

        <div class="field">
            <label for="bio">Bio</label>
            <textarea id="bio" name="bio" rows="3"><?= e($source['bio'] ?? '') ?></textarea>
        </div>

        <div class="field-grid">
            <div class="field">
                <label for="image_url">Image URL</label>
                <input id="image_url" name="image_url" type="url" maxlength="2048"
                       value="<?= e($source['image_url'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="profile_photo_url">Profile Photo URL</label>
                <input id="profile_photo_url" name="profile_photo_url" type="url" maxlength="2048"
                       value="<?= e($source['profile_photo_url'] ?? '') ?>">
            </div>
        </div>

        <div class="field">
            <label class="checkbox-label">
                <input type="checkbox" name="enabled" value="1" <?= (int) ($source['enabled'] ?? 1) ? 'checked' : '' ?>>
                Enabled
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="admin-btn"><?= $isEdit ? 'Update' : 'Add' ?> Source</button>
            <a href="/admin/feed-sources" class="admin-btn admin-btn-ghost">Cancel</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();

require dirname(__DIR__) . '/layout.php';
