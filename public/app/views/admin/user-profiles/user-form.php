<?php

declare(strict_types=1);

$pageTitle = 'Edit User';

ob_start();
?>
<div class="admin-container">
    <div class="admin-header-row">
        <h1>Edit User</h1>
        <a href="/admin/user-profiles" class="admin-btn admin-btn-ghost">Back</a>
    </div>

    <?php if ($error): ?>
        <div class="form-status form-status-error" role="alert">
            <p><?= e($error) ?></p>
        </div>
    <?php endif; ?>

    <?php if (($_GET['success'] ?? '') === 'photo'): ?>
        <div class="form-status" role="alert">
            <p>Photo updated successfully.</p>
        </div>
    <?php endif; ?>

    <?php if (!empty($user['image'])): ?>
        <div class="field" style="margin-bottom: 1rem;">
            <label>Current Photo</label>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <img src="<?= e($user['image']) ?>" alt="" style="width: 80px; height: 80px; object-fit: cover; border-radius: 50%; border: 1px solid var(--line);">
                <span class="admin-hint"><?= e($user['image']) ?></span>
            </div>
        </div>
    <?php endif; ?>

    <form method="post" action="/admin/user-profiles/<?= e($user['id']) ?>/photo" enctype="multipart/form-data" class="admin-form" style="margin-bottom: 2rem;">
        <div class="field">
            <label for="profile_photo">Upload New Photo</label>
            <input id="profile_photo" name="profile_photo" type="file" accept="image/jpeg,image/png,image/gif,image/webp,image/avif">
            <span class="admin-hint">JPEG, PNG, GIF, WebP, AVIF — max 8 MB. Owner photos are stored as media files; member photos are stored in profile_photo_assets.</span>
        </div>
        <div class="form-actions">
            <button type="submit" class="admin-btn">Update Photo</button>
        </div>
    </form>

    <form method="post" action="/admin/user-profiles/<?= e($user['id']) ?>/edit" class="admin-form">
        <div class="field">
            <label for="name">Name</label>
            <input id="name" name="name" type="text" required maxlength="255"
                   value="<?= e($user['name'] ?? '') ?>">
        </div>
        <div class="field">
            <label for="username">Username</label>
            <input id="username" name="username" type="text" maxlength="255"
                   value="<?= e($user['username'] ?? '') ?>">
        </div>
        <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" maxlength="191"
                   value="<?= e($user['email'] ?? '') ?>">
        </div>
        <div class="field">
            <label for="bio">Bio</label>
            <textarea id="bio" name="bio" rows="3"><?= e($user['bio'] ?? '') ?></textarea>
        </div>
        <div class="field">
            <label for="website">Website</label>
            <input id="website" name="website" type="url" maxlength="2048"
                   value="<?= e($user['website'] ?? '') ?>">
        </div>
        <div class="field">
            <label for="social_links">Social Links (JSON)</label>
            <textarea id="social_links" name="social_links" rows="3"><?= e($user['social_links'] ?? '') ?></textarea>
        </div>
        <div class="field-grid">
            <div class="field">
                <label for="theme">Theme</label>
                <input id="theme" name="theme" type="text" maxlength="32"
                       value="<?= e($user['theme'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="palette">Palette</label>
                <input id="palette" name="palette" type="text" maxlength="32"
                       value="<?= e($user['palette'] ?? '') ?>">
            </div>
        </div>

        <p class="admin-hint">Preferred AI vendors are managed from <a href="/admin/ai-settings?tab=vendor">AI Settings → AI Vendor</a>.</p>

        <div class="form-actions">
            <button type="submit" class="admin-btn">Update User</button>
            <a href="/admin/user-profiles" class="admin-btn admin-btn-ghost">Cancel</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();

require dirname(__DIR__) . '/layout.php';
