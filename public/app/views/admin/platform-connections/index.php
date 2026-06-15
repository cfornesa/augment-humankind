<?php

declare(strict_types=1);

$pageTitle = 'Platform Connections';

ob_start();
$tab = $_GET['tab'] ?? 'connections';
if (!in_array($tab, ['connections', 'syndications'], true)) {
    $tab = 'connections';
}
$error = $_GET['error'] ?? null;
?>
<div class="admin-container">
    <div class="admin-header-row">
        <h1>Platform Connections</h1>
    </div>

    <?php if ($error): ?>
        <div class="form-status form-status-error" role="alert">
            <p><?= e($error) ?></p>
        </div>
    <?php endif; ?>

    <nav class="admin-tabs" aria-label="Platform connection tabs">
        <a href="/admin/platform-connections?tab=connections" class="admin-tab <?= $tab === 'connections' ? 'active' : '' ?>">Connections</a>
        <a href="/admin/platform-connections?tab=syndications" class="admin-tab <?= $tab === 'syndications' ? 'active' : '' ?>">Syndications</a>
    </nav>

    <?php if ($tab === 'connections'): ?>
        <a href="/admin/platform-connections/create" class="admin-btn" style="margin-bottom:1rem;display:inline-block;">Add Connection</a>
        <?php if (empty($connections)): ?>
            <p>No platform connections configured. These are the credentials that enable syndication to external platforms.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr><th>User</th><th>Platform</th><th>Enabled</th><th>Created</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($connections as $c): ?>
                        <tr>
                            <td><?= e($c['user_name'] ?? '') ?></td>
                            <td><?= e($c['platform']) ?></td>
                            <td><?= (int) ($c['enabled'] ?? 1) ? 'Yes' : 'No' ?></td>
                            <td><?= e($c['created_at'] ?? '') ?></td>
                            <td>
                                <a href="/admin/platform-connections/<?= (int) $c['id'] ?>/edit" class="admin-link">Edit</a>
                                <form method="post" action="/admin/platform-connections/<?= (int) $c['id'] ?>/delete" class="inline-form" onsubmit="return confirm('Delete this connection?')">
                                    <button type="submit" class="admin-link danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php else: ?>
        <a href="/admin/platform-connections/syndications/create" class="admin-btn" style="margin-bottom:1rem;display:inline-block;">Add Syndication</a>
        <?php if (empty($syndications)): ?>
            <p>No syndications yet. A syndication links a blog post to a platform connection for publishing.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr><th>Post</th><th>Platform</th><th>External ID</th><th>Status</th><th>Created</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($syndications as $s): ?>
                        <tr>
                            <td><?= e($s['post_title'] ?? '') ?></td>
                            <td><?= e($s['platform'] ?? '') ?></td>
                            <td><?= e($s['external_id'] ?? '') ?></td>
                            <td>
                                <span class="status-badge status-<?= e($s['status'] ?? 'pending') ?>">
                                    <?= e($s['status'] ?? 'pending') ?>
                                </span>
                            </td>
                            <td><?= e($s['created_at'] ?? '') ?></td>
                            <td>
                                <form method="post" action="/admin/platform-connections/syndications/<?= (int) $s['id'] ?>/delete" class="inline-form" onsubmit="return confirm('Delete this syndication?')">
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
