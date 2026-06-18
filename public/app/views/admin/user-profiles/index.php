<?php

declare(strict_types=1);

$pageTitle = 'User Profiles';

ob_start();
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

    <p class="admin-copy">Profile editing and membership state live here. AI profiles, keys, and preferred vendor defaults now live under <a href="/admin/ai-settings">AI Settings</a>.</p>
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
</div>
<?php
$content = ob_get_clean();

require dirname(__DIR__) . '/layout.php';
