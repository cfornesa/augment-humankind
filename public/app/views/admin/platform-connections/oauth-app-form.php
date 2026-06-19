<?php

declare(strict_types=1);

$pageTitle = 'Configure OAuth App';
$platformKey = (string) ($app['platform'] ?? '');
$platformSlug = str_replace('_', '-', $platformKey);
$blogUrlValue = (string) ($app['blog_url'] ?? '');
$requiresBlogUrl = in_array($platformKey, ['wordpress_com', 'blogger'], true);

ob_start();
?>
<div class="admin-container">
    <div class="admin-header-row">
        <h1>Configure <?= e((string) ($definition['label'] ?? 'OAuth App')) ?></h1>
        <a href="/admin/platform-connections" class="admin-btn admin-btn-ghost">Back</a>
    </div>

    <?php if ($error): ?>
        <div class="form-status form-status-error" role="alert">
            <p><?= e($error) ?></p>
        </div>
    <?php endif; ?>

    <p class="admin-copy">
        Save the provider app credentials in the PHP site's own database. These
        values are encrypted at rest and are no longer read from environment
        variables for publishing/social platform connections.
    </p>

    <form method="post" action="/admin/platform-connections/oauth-apps/<?= e($platformSlug) ?>/edit" class="admin-form">
        <div class="field">
            <label for="client_id">Client ID <span class="required">*</span></label>
            <input id="client_id" name="client_id" type="text" maxlength="512" placeholder="<?= !empty($app['encrypted_client_id']) ? 'Leave blank to keep current value' : '' ?>">
        </div>

        <div class="field">
            <label for="client_secret">Client Secret <span class="required">*</span></label>
            <input id="client_secret" name="client_secret" type="password" maxlength="512" placeholder="<?= !empty($app['encrypted_client_secret']) ? 'Leave blank to keep current value' : '' ?>">
        </div>

        <?php if ($requiresBlogUrl): ?>
            <div class="field">
                <label for="blog_url">Blog URL</label>
                <input id="blog_url" name="blog_url" type="url" maxlength="500" value="<?= e($blogUrlValue) ?>" placeholder="https://your-blog.example.com">
                <small><?= $platformKey === 'wordpress_com' ? 'Optional, but helps scope the WordPress.com OAuth flow to the correct site.' : 'Recommended for Blogger so the callback can determine the correct blog ID.' ?></small>
            </div>
        <?php endif; ?>

        <?php if ($platformKey === 'instagram' || $platformKey === 'facebook'): ?>
            <p class="admin-hint">Instagram and Facebook can reuse the same Meta developer app if that is how your account is configured.</p>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit" class="admin-btn">Save OAuth App</button>
            <a href="/admin/platform-connections" class="admin-btn admin-btn-ghost">Cancel</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();

require dirname(__DIR__) . '/layout.php';
