<?php

declare(strict_types=1);

$pageTitle = 'Feed Sources';

ob_start();
$tab = $_GET['tab'] ?? 'sources';
if (!in_array($tab, ['sources', 'pending'], true)) {
    $tab = 'sources';
}
$error = $_GET['error'] ?? null;
?>
<div class="admin-container">
    <div class="admin-header-row">
        <h1>Feed Sources</h1>
        <a href="/admin/feed-sources/create" class="admin-btn">Add Source</a>
    </div>

    <?php if ($error): ?>
        <div class="form-status form-status-error" role="alert">
            <p><?= e($error) ?></p>
        </div>
    <?php endif; ?>

    <nav class="admin-tabs" aria-label="Feed tabs">
        <a href="/admin/feed-sources?tab=sources" class="admin-tab <?= $tab === 'sources' ? 'active' : '' ?>">Sources</a>
        <a href="/admin/feed-sources?tab=pending" class="admin-tab <?= $tab === 'pending' ? 'active' : '' ?>">
            Pending
            <?php if (!empty($pending)): ?>
                <span class="badge"><?= count($pending) ?></span>
            <?php endif; ?>
        </a>
    </nav>

    <?php if ($tab === 'sources'): ?>
        <?php if (empty($sources)): ?>
            <p>No feed sources yet. <a href="/admin/feed-sources/create">Add the first one</a>.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Feed URL</th>
                        <th>Cadence</th>
                        <th>Status</th>
                        <th>Last Fetched</th>
                        <th>Items</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sources as $source): ?>
                        <tr>
                            <td><?= e($source['name'] ?? '') ?></td>
                            <td><a href="<?= e($source['feed_url'] ?? '') ?>" target="_blank" rel="noopener">Feed</a></td>
                            <td><?= e($source['cadence'] ?? 'daily') ?></td>
                            <td>
                                <span class="status-badge <?= (int) ($source['enabled'] ?? 1) ? 'status-active' : 'status-inactive' ?>">
                                    <?= (int) ($source['enabled'] ?? 1) ? 'Enabled' : 'Disabled' ?>
                                </span>
                            </td>
                            <td><?= e($source['last_fetched_at'] ?? 'Never') ?></td>
                            <td><?= (int) ($source['items_imported'] ?? 0) ?></td>
                            <td>
                                <form method="post" action="/admin/feed-sources/<?= (int) $source['id'] ?>/ingest" class="inline-form">
                                    <button type="submit" class="admin-link">Ingest</button>
                                </form>
                                <a href="/admin/feed-sources/<?= (int) $source['id'] ?>/edit" class="admin-link">Edit</a>
                                <form method="post" action="/admin/feed-sources/<?= (int) $source['id'] ?>/delete" class="inline-form" onsubmit="return confirm('Delete this feed source permanently?')">
                                    <button type="submit" class="admin-link danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php else: ?>
        <?php if (empty($pending)): ?>
            <p>No pending imports. Run <strong>Ingest</strong> on a feed source to populate this queue.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Source</th>
                        <th>Item</th>
                        <th>Seen</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $item): ?>
                        <tr>
                            <td><?= e($item['source_name'] ?? '') ?></td>
                            <td>
                                <strong><?= e($item['title'] ?? 'Untitled') ?></strong>
                                <?php if (!empty($item['source_url'])): ?>
                                    <br><a href="<?= e($item['source_url']) ?>" target="_blank" rel="noopener">Source</a>
                                <?php endif; ?>
                                <br><code><?= e($item['guid_hash'] ?? '') ?></code>
                            </td>
                            <td><?= e($item['created_at'] ?? $item['seen_at'] ?? '') ?></td>
                            <td>
                                <form method="post" action="/admin/feed-sources/approve" class="inline-form">
                                    <input type="hidden" name="seen_id" value="<?= (int) $item['id'] ?>">
                                    <input type="hidden" name="source_id" value="<?= (int) $item['source_id'] ?>">
                                    <button type="submit" class="admin-link">Approve</button>
                                </form>
                                <form method="post" action="/admin/feed-sources/reject" class="inline-form">
                                    <input type="hidden" name="seen_id" value="<?= (int) $item['id'] ?>">
                                    <button type="submit" class="admin-link danger">Reject</button>
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
