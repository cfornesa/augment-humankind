<?php

declare(strict_types=1);

$pageTitle = 'Platform Connections';

ob_start();
$tab = $_GET['tab'] ?? 'connections';
if (!in_array($tab, ['connections', 'syndications'], true)) {
    $tab = 'connections';
}
$error = $_GET['error'] ?? null;
$success = $_GET['success'] ?? null;
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
    <?php if ($success === 'oauth'): ?>
        <div class="form-status" role="status">
            <p>Connection updated successfully.</p>
        </div>
    <?php endif; ?>

    <nav class="admin-tabs" aria-label="Platform connection tabs">
        <a href="/admin/platform-connections?tab=connections" class="admin-tab <?= $tab === 'connections' ? 'active' : '' ?>">Connections</a>
        <a href="/admin/platform-connections?tab=syndications" class="admin-tab <?= $tab === 'syndications' ? 'active' : '' ?>">Syndications</a>
    </nav>

    <?php if ($tab === 'connections'): ?>
        <p class="admin-copy">Each platform below uses a guided setup flow with its own instructions, required fields, and connection summary. Raw metadata stays internal.</p>
        <div class="dashboard-stats">
            <?php foreach ($platforms as $platformKey => $platform): ?>
                <?php $connection = $connectionsByPlatform[$platformKey] ?? null; ?>
                <?php $latest = $latestSyndications[$platformKey] ?? null; ?>
                <div class="stat-card" style="gap:0.85rem;">
                    <div>
                        <strong><?= e($platform['label']) ?></strong>
                        <p class="admin-hint" style="margin:0.35rem 0 0;"><?= e($platform['setup_instruction']) ?></p>
                    </div>
                    <div>
                        <span class="status-badge <?= $connection && (int) ($connection['enabled'] ?? 1) ? 'status-active' : 'status-inactive' ?>">
                            <?= $connection ? ((int) ($connection['enabled'] ?? 1) ? 'Connected' : 'Disabled') : 'Not connected' ?>
                        </span>
                    </div>
                    <?php if ($latest): ?>
                        <p class="admin-hint" style="margin:0;">Latest syndication: <?= e($latest['status'] ?? 'pending') ?><?= !empty($latest['external_url']) ? ' • ' : '' ?><?php if (!empty($latest['external_url'])): ?><a href="<?= e($latest['external_url']) ?>" target="_blank" rel="noopener">open</a><?php endif; ?></p>
                    <?php endif; ?>
                    <div class="dashboard-links">
                        <?php if (($platform['kind'] ?? '') === 'oauth'): ?>
                            <a href="/admin/platform-connections/auth/<?= e(str_replace('_', '-', $platformKey)) ?>/start" class="admin-btn"><?= $connection ? 'Reconnect' : 'Connect' ?></a>
                        <?php else: ?>
                            <a href="<?= $connection ? '/admin/platform-connections/' . (int) $connection['id'] . '/edit' : '/admin/platform-connections/create?platform=' . urlencode($platformKey) ?>" class="admin-btn"><?= $connection ? 'Edit' : 'Set up' ?></a>
                        <?php endif; ?>
                        <?php if ($connection): ?>
                            <form method="post" action="/admin/platform-connections/<?= (int) $connection['id'] ?>/delete" class="inline-form" onsubmit="return confirm('Delete this connection?')">
                                <button type="submit" class="admin-btn admin-btn-ghost">Disconnect</button>
                            </form>
                        <?php endif; ?>
                        <a href="<?= e($platform['setup_href']) ?>" target="_blank" rel="noopener" class="admin-btn admin-btn-ghost">Instructions</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <a href="/admin/platform-connections/syndications/create" class="admin-btn" style="margin-bottom:1rem;display:inline-block;">Add Syndication</a>
        <?php if (empty($syndications)): ?>
            <p>No syndications yet. A syndication links a blog post to a platform connection for publishing.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr><th>Post</th><th>Platform</th><th>Status</th><th>Error / External ID</th><th>Created</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($syndications as $s): ?>
                        <tr>
                            <td><?= e($s['post_title'] ?? '') ?></td>
                            <td><?= e($s['platform'] ?? '') ?></td>
                            <td>
                                <span class="status-badge status-<?= e($s['status'] ?? 'pending') ?>">
                                    <?= e($s['status'] ?? 'pending') ?>
                                </span>
                            </td>
                            <td style="font-size:0.8rem;max-width:28rem;word-break:break-word">
                                <?php if (!empty($s['error_message'])): ?>
                                    <span style="color:#c0392b"><?= e($s['error_message']) ?></span>
                                <?php elseif (!empty($s['external_url'])): ?>
                                    <a href="<?= e($s['external_url']) ?>" target="_blank" rel="noopener"><?= e($s['external_id'] ?? $s['external_url']) ?></a>
                                <?php else: ?>
                                    <?= e($s['external_id'] ?? '—') ?>
                                <?php endif ?>
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
