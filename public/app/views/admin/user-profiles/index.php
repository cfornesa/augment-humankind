<?php

declare(strict_types=1);

$pageTitle = 'User Profiles';

ob_start();
$tab = $_GET['tab'] ?? 'users';
if (!in_array($tab, ['users', 'settings', 'keys'], true)) {
    $tab = 'users';
}
$error = $_GET['error'] ?? null;
?>
<div class="admin-container">
    <div class="admin-header-row">
        <h1>User Profiles</h1>
    </div>

    <?php if ($error): ?>
        <div class="form-status form-status-error" role="alert">
            <p><?= e($error) ?></p>
        </div>
    <?php endif; ?>

    <nav class="admin-tabs" aria-label="User profile tabs">
        <a href="/admin/user-profiles?tab=users" class="admin-tab <?= $tab === 'users' ? 'active' : '' ?>">Users</a>
        <a href="/admin/user-profiles?tab=settings" class="admin-tab <?= $tab === 'settings' ? 'active' : '' ?>">AI Settings</a>
        <a href="/admin/user-profiles?tab=keys" class="admin-tab <?= $tab === 'keys' ? 'active' : '' ?>">API Keys</a>
    </nav>

    <?php if ($tab === 'users'): ?>
        <?php if (empty($users)): ?>
            <p>No users found.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= e($user['name'] ?? '') ?></td>
                            <td><?= e($user['email'] ?? '') ?></td>
                            <td><?= e($user['role'] ?? '') ?></td>
                            <td>
                                <span class="status-badge status-<?= e($user['status'] ?? 'active') ?>">
                                    <?= e($user['status'] ?? 'active') ?>
                                </span>
                            </td>
                            <td>
                                <a href="/admin/user-profiles/<?= e($user['id']) ?>/edit" class="admin-link">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php elseif ($tab === 'settings'): ?>
        <a href="/admin/user-profiles/settings/create" class="admin-btn" style="margin-bottom:1rem;display:inline-block;">Add AI Setting</a>
        <?php if (empty($settings)): ?>
            <p>No AI vendor settings configured.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr><th>User</th><th>Vendor</th><th>Profile</th><th>Model</th><th>Enabled</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($settings as $s): ?>
                        <tr>
                            <td><?= e($s['user_name'] ?? '') ?></td>
                            <td><?= e($s['vendor']) ?></td>
                            <td><?= e($s['profile_name'] ?? 'Default') ?></td>
                            <td><?= e($s['model'] ?? '') ?></td>
                            <td><?= (int) ($s['enabled'] ?? 0) ? 'Yes' : 'No' ?></td>
                            <td>
                                <a href="/admin/user-profiles/settings/<?= (int) $s['id'] ?>/edit" class="admin-link">Edit</a>
                                <form method="post" action="/admin/user-profiles/settings/<?= (int) $s['id'] ?>/delete" class="inline-form" onsubmit="return confirm('Delete this setting?')">
                                    <button type="submit" class="admin-link danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php else: ?>
        <a href="/admin/user-profiles/keys/create" class="admin-btn" style="margin-bottom:1rem;display:inline-block;">Add API Key</a>
        <?php if (empty($keys)): ?>
            <p>No API keys stored.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr><th>User</th><th>Vendor</th><th>Key</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($keys as $k): ?>
                        <tr>
                            <td><?= e($k['user_name'] ?? '') ?></td>
                            <td><?= e($k['vendor']) ?></td>
                            <td><code>encrypted</code></td>
                            <td>
                                <a href="/admin/user-profiles/keys/<?= (int) $k['id'] ?>/edit" class="admin-link">Edit</a>
                                <form method="post" action="/admin/user-profiles/keys/<?= (int) $k['id'] ?>/delete" class="inline-form" onsubmit="return confirm('Delete this key?')">
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
