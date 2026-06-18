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
            Review Queue
            <?php if (!empty($pending)): ?>
                <span class="badge"><?= count($pending) ?></span>
            <?php endif; ?>
        </a>
    </nav>

    <?php if ($tab === 'sources'): ?>
        <p class="admin-copy">Feeds follow the same guided pattern as platform connections: connect the source, choose refresh cadence, ingest new items, then approve or reject them in the review queue.</p>
        <?php if (empty($sources)): ?>
            <p>No feed sources yet. <a href="/admin/feed-sources/create">Add the first one</a>.</p>
        <?php else: ?>
            <div class="dashboard-stats">
                <?php foreach ($sources as $source): ?>
                    <div class="stat-card" style="gap:0.85rem;">
                        <div>
                            <strong><?= e($source['name'] ?? '') ?></strong>
                            <p class="admin-hint" style="margin:0.35rem 0 0;"><?= e($source['feed_url'] ?? '') ?></p>
                        </div>
                        <div class="dashboard-links">
                            <span class="status-badge <?= (int) ($source['enabled'] ?? 1) ? 'status-active' : 'status-inactive' ?>">
                                <?= (int) ($source['enabled'] ?? 1) ? 'Enabled' : 'Disabled' ?>
                            </span>
                            <span class="admin-hint">Cadence: <?= e($source['cadence'] ?? 'daily') ?></span>
                        </div>
                        <p class="admin-hint" style="margin:0;">Last fetched: <?= e($source['last_fetched_at'] ?? 'Never') ?> • Imported items: <?= (int) ($source['items_imported'] ?? 0) ?></p>
                        <div class="dashboard-links">
                            <form method="post" action="/admin/feed-sources/<?= (int) $source['id'] ?>/ingest" class="inline-form">
                                <button type="submit" class="admin-btn">Run Ingest</button>
                            </form>
                            <a href="/admin/feed-sources/<?= (int) $source['id'] ?>/edit" class="admin-btn admin-btn-ghost">Edit</a>
                            <a href="/admin/feed-sources?tab=pending" class="admin-btn admin-btn-ghost">Review Queue</a>
                            <form method="post" action="/admin/feed-sources/<?= (int) $source['id'] ?>/delete" class="inline-form" onsubmit="return confirm('Delete this feed source permanently?')">
                                <button type="submit" class="admin-btn admin-btn-ghost">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <?php if (empty($pending)): ?>
            <p>No pending imports. Run <strong>Ingest</strong> on a feed source to populate this review queue.</p>
        <?php else: ?>
            <p class="admin-copy">Each imported item lands here before it becomes a draft post. Approve what belongs on your site and reject the rest.</p>
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
