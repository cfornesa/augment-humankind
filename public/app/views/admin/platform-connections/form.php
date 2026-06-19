<?php

declare(strict_types=1);

$isEdit = !empty($connection['id']);
$selectedPlatform = $selectedPlatform ?: ($connection['platform'] ?? '');
$definition = $selectedPlatform !== '' ? ($platforms[$selectedPlatform] ?? null) : null;
$pageTitle = $isEdit ? 'Edit Connection' : 'Connect Platform';
$meta = parse_connection_meta($connection['metadata'] ?? null);

ob_start();
$connection = $connection ?? [];
?>
<div class="admin-container">
    <div class="admin-header-row">
        <h1><?= $isEdit ? 'Edit Connection' : 'Connect Platform' ?></h1>
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
            <select id="platform" name="platform" required <?= $isEdit ? 'disabled' : '' ?>>
                <option value="">Choose a platform</option>
                <?php foreach ($platforms as $platformKey => $platformDef): ?>
                    <option value="<?= e($platformKey) ?>" <?= $selectedPlatform === $platformKey ? 'selected' : '' ?>><?= e($platformDef['label']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($isEdit): ?>
                <input type="hidden" name="platform" value="<?= e($selectedPlatform) ?>">
            <?php endif; ?>
        </div>
        <?php if ($definition): ?>
            <div class="form-status" role="note">
                <p><strong><?= e($definition['label']) ?></strong> — <?= e($definition['setup_instruction']) ?> <a href="<?= e($definition['setup_href']) ?>" target="_blank" rel="noopener">Instructions</a></p>
            </div>
        <?php endif; ?>
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
        <?php if ($definition && ($definition['kind'] ?? '') === 'credentials'): ?>
            <?php if ($selectedPlatform === 'bluesky'): ?>
                <div class="field">
                    <label for="handle">Handle</label>
                    <input id="handle" name="handle" type="text" maxlength="191" value="<?= e($meta['handle'] ?? '') ?>" placeholder="you.bsky.social">
                </div>
                <div class="field">
                    <label for="app_password">App Password</label>
                    <input id="app_password" name="app_password" type="password" maxlength="2048" placeholder="<?= $isEdit ? 'Leave blank to keep unchanged' : '' ?>">
                </div>
            <?php elseif ($selectedPlatform === 'wordpress_self'): ?>
                <div class="field-grid">
                    <div class="field">
                        <label for="site_url">Site URL</label>
                        <input id="site_url" name="site_url" type="url" maxlength="2048" value="<?= e($meta['siteUrl'] ?? '') ?>" placeholder="https://yourblog.example.com">
                    </div>
                    <div class="field">
                        <label for="username">Username</label>
                        <input id="username" name="username" type="text" maxlength="191" value="<?= e($meta['username'] ?? '') ?>">
                    </div>
                </div>
                <div class="field">
                    <label for="app_password">Application Password</label>
                    <input id="app_password" name="app_password" type="password" maxlength="2048" placeholder="<?= $isEdit ? 'Leave blank to keep unchanged' : '' ?>">
                </div>
            <?php elseif ($selectedPlatform === 'substack'): ?>
                <div class="field-grid">
                    <div class="field">
                        <label for="publication_id">Publication ID</label>
                        <input id="publication_id" name="publication_id" type="text" maxlength="191" value="<?= e($meta['publicationId'] ?? '') ?>">
                    </div>
                    <div class="field">
                        <label for="publication_host">Publication Host</label>
                        <input id="publication_host" name="publication_host" type="text" maxlength="191" value="<?= e($meta['publicationHost'] ?? '') ?>" placeholder="yourpublication.substack.com">
                    </div>
                </div>
                <div class="field">
                    <label for="session_cookie">Session Cookie</label>
                    <input id="session_cookie" name="session_cookie" type="password" maxlength="2048" placeholder="<?= $isEdit ? 'Leave blank to keep unchanged' : '' ?>">
                </div>
            <?php endif; ?>
        <?php elseif ($definition && ($definition['kind'] ?? '') === 'oauth'): ?>
            <div class="form-status" role="note">
                <p>OAuth-based platforms are connected from the overview card so the callback flow can manage tokens safely. Configure the provider app credentials first, then start or refresh the connection.</p>
            </div>
            <div class="form-actions">
                <a href="/admin/platform-connections/auth/<?= e(str_replace('_', '-', $selectedPlatform)) ?>/start" class="admin-btn"><?= $isEdit ? 'Reconnect' : 'Start OAuth' ?></a>
                <a href="/admin/platform-connections/oauth-apps/<?= e(str_replace('_', '-', $selectedPlatform)) ?>/edit" class="admin-btn admin-btn-ghost">Configure App</a>
                <a href="/admin/platform-connections/diagnostics" class="admin-btn admin-btn-ghost">Diagnostics</a>
            </div>
        <?php endif; ?>
        <div class="field">
            <label class="checkbox-label">
                <input type="checkbox" name="enabled" value="1" <?= (int) ($connection['enabled'] ?? 1) ? 'checked' : '' ?>>
                Enabled
            </label>
        </div>
        <?php if (!$definition || ($definition['kind'] ?? '') === 'credentials'): ?>
            <div class="form-actions">
                <button type="submit" class="admin-btn"><?= $isEdit ? 'Update' : 'Save' ?> Connection</button>
                <a href="/admin/platform-connections" class="admin-btn admin-btn-ghost">Cancel</a>
            </div>
        <?php endif; ?>
    </form>
</div>
<?php
$content = ob_get_clean();

require dirname(__DIR__) . '/layout.php';
