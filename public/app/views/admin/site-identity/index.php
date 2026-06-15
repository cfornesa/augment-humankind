<?php

declare(strict_types=1);

$pageTitle = 'Site Identity';

ob_start();
$error = $_GET['error'] ?? null;
$tab = $_GET['tab'] ?? 'settings';
if (!in_array($tab, ['settings', 'assets', 'media'])) {
    $tab = 'settings';
}
?>
<div class="admin-container">
    <div class="admin-header-row">
        <h1>Site Identity</h1>
    </div>

    <?php if ($error): ?>
        <div class="form-status form-status-error" role="alert">
            <p><?= e($error) ?></p>
        </div>
    <?php endif; ?>

    <nav class="admin-tabs" aria-label="Site identity tabs">
        <a href="/admin/site-identity?tab=settings" class="admin-tab <?= $tab === 'settings' ? 'active' : '' ?>">Settings</a>
        <a href="/admin/site-identity?tab=assets" class="admin-tab <?= $tab === 'assets' ? 'active' : '' ?>">Assets</a>
        <a href="/admin/site-identity?tab=media" class="admin-tab <?= $tab === 'media' ? 'active' : '' ?>">Media Library</a>
    </nav>

    <?php if ($tab === 'settings'): ?>
        <form method="post" action="/admin/site-identity/settings" class="admin-form">
            <div class="field">
                <label for="site_title">Site Title</label>
                <input id="site_title" name="site_title" type="text" maxlength="255"
                       value="<?= e($settings['site_title'] ?? 'Augment Humankind') ?>">
            </div>
            <div class="field">
                <label for="hero_heading">Hero Heading</label>
                <input id="hero_heading" name="hero_heading" type="text" maxlength="255"
                       value="<?= e($settings['hero_heading'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="hero_subheading">Hero Subheading</label>
                <textarea id="hero_subheading" name="hero_subheading" rows="3"><?= e($settings['hero_subheading'] ?? '') ?></textarea>
            </div>
            <div class="field">
                <label for="about_heading">About Heading</label>
                <input id="about_heading" name="about_heading" type="text" maxlength="255"
                       value="<?= e($settings['about_heading'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="about_body">About Body</label>
                <textarea id="about_body" name="about_body" rows="4"><?= e($settings['about_body'] ?? '') ?></textarea>
            </div>
            <div class="field">
                <label for="copyright_line">Copyright Line</label>
                <input id="copyright_line" name="copyright_line" type="text" maxlength="255"
                       value="<?= e($settings['copyright_line'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="footer_credit">Footer Credit</label>
                <input id="footer_credit" name="footer_credit" type="text" maxlength="255"
                       value="<?= e($settings['footer_credit'] ?? '') ?>">
            </div>
            <div class="field-grid">
                <div class="field">
                    <label for="cta_label">CTA Label</label>
                    <input id="cta_label" name="cta_label" type="text" maxlength="255"
                           value="<?= e($settings['cta_label'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="cta_href">CTA URL</label>
                    <input id="cta_href" name="cta_href" type="text" maxlength="2048"
                           value="<?= e($settings['cta_href'] ?? '/') ?>">
                </div>
            </div>
            <div class="field-grid">
                <div class="field">
                    <label for="logo_url">Logo URL</label>
                    <input id="logo_url" name="logo_url" type="url" maxlength="2048"
                           value="<?= e($settings['logo_url'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="logo_dark_url">Logo Dark URL</label>
                    <input id="logo_dark_url" name="logo_dark_url" type="url" maxlength="2048"
                           value="<?= e($settings['logo_dark_url'] ?? '') ?>">
                </div>
            </div>
            <div class="field-grid">
                <div class="field">
                    <label for="logo_layout">Logo Layout</label>
                    <select id="logo_layout" name="logo_layout">
                        <option value="text_only" <?= ($settings['logo_layout'] ?? '') === 'text_only' ? 'selected' : '' ?>>Text Only</option>
                        <option value="image" <?= ($settings['logo_layout'] ?? '') === 'image' ? 'selected' : '' ?>>Image</option>
                        <option value="mixed" <?= ($settings['logo_layout'] ?? '') === 'mixed' ? 'selected' : '' ?>>Mixed</option>
                    </select>
                </div>
                <div class="field">
                    <label for="default_theme_mode">Default Theme</label>
                    <select id="default_theme_mode" name="default_theme_mode">
                        <option value="system" <?= ($settings['default_theme_mode'] ?? '') === 'system' ? 'selected' : '' ?>>System</option>
                        <option value="light" <?= ($settings['default_theme_mode'] ?? '') === 'light' ? 'selected' : '' ?>>Light</option>
                        <option value="dark" <?= ($settings['default_theme_mode'] ?? '') === 'dark' ? 'selected' : '' ?>>Dark</option>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="admin-btn">Save Settings</button>
            </div>
        </form>
    <?php elseif ($tab === 'assets'): ?>
        <h2>Site Assets</h2>
        <form method="post" action="/admin/site-identity/assets" enctype="multipart/form-data" class="admin-form">
            <div class="field-grid">
                <div class="field">
                    <label for="asset_key">Asset Key</label>
                    <input id="asset_key" name="asset_key" type="text" required maxlength="191">
                </div>
                <div class="field">
                    <label for="asset_file">File</label>
                    <input id="asset_file" name="asset_file" type="file" required>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="admin-btn">Upload Asset</button>
            </div>
        </form>

        <?php if (empty($assets)): ?>
            <p>No site assets uploaded.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr><th>Key</th><th>Filename</th><th>Type</th><th>Size</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($assets as $asset): ?>
                        <tr>
                            <td><code><?= e($asset['asset_key']) ?></code></td>
                            <td><?= e($asset['filename'] ?? '') ?></td>
                            <td><?= e($asset['mime_type'] ?? '') ?></td>
                            <td><?= (int) ($asset['byte_size'] ?? 0) ?></td>
                            <td>
                                <form method="post" action="/admin/site-identity/assets/<?= (int) $asset['id'] ?>/delete" class="inline-form" onsubmit="return confirm('Delete this asset?')">
                                    <button type="submit" class="admin-link danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php else: ?>
        <h2>Media Library</h2>
        <?php if (empty($mediaAssets)): ?>
            <p>No media assets in the library.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr><th>Filename</th><th>Type</th><th>Alt</th><th>Title</th><th>Uploaded</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($mediaAssets as $ma): ?>
                        <tr>
                            <td><?= e($ma['filename'] ?? '') ?></td>
                            <td><?= e($ma['mime_type'] ?? '') ?></td>
                            <td><?= e($ma['alt_text'] ?? '') ?></td>
                            <td><?= e($ma['title'] ?? '') ?></td>
                            <td><?= e($ma['uploaded_at'] ?? '') ?></td>
                            <td>
                                <form method="post" action="/admin/site-identity/media/<?= (int) $ma['id'] ?>/delete" class="inline-form" onsubmit="return confirm('Move to trash?')">
                                    <button type="submit" class="admin-link danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();

require dirname(__DIR__) . '/layout.php';
