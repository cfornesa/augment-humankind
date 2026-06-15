<?php

declare(strict_types=1);

$pageTitle = 'Versions — ' . ($piece['title'] ?? 'Piece');

ob_start();
?>
<div class="admin-container">
    <div class="admin-header-row">
        <h1>Versions: <?= e($piece['title'] ?? 'Untitled') ?></h1>
        <div>
            <a href="/admin/pieces/<?= (int) $piece['id'] ?>/versions/create" class="admin-btn">Add Version</a>
            <a href="/admin/pieces" class="admin-btn admin-btn-ghost">Back</a>
        </div>
    </div>

    <?php if (empty($versions)): ?>
        <p>No versions yet. <a href="/admin/pieces/<?= (int) $piece['id'] ?>/versions/create">Add the first version</a>.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Version</th>
                    <th>Engine</th>
                    <th>Vendor</th>
                    <th>Model</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Current</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($versions as $version): ?>
                    <tr>
                        <td><?= (int) $version['version_number'] ?></td>
                        <td><?= e(strtoupper($version['engine'] ?? 'p5')) ?></td>
                        <td><?= e($version['generation_vendor'] ?? '—') ?></td>
                        <td><?= e($version['generation_model'] ?? '—') ?></td>
                        <td>
                            <span class="status-badge status-<?= e($version['validation_status'] ?? 'validated') ?>">
                                <?= e($version['validation_status'] ?? 'validated') ?>
                            </span>
                        </td>
                        <td><?= e($version['created_at'] ?? '') ?></td>
                        <td>
                            <?php if ((int) ($piece['current_version_id'] ?? 0) === (int) $version['id']): ?>
                                <strong>Yes</strong>
                            <?php else: ?>
                                <form method="post" action="/admin/pieces/<?= (int) $piece['id'] ?>/versions/<?= (int) $version['id'] ?>/set-current" class="inline-form">
                                    <button type="submit" class="admin-link">Set current</button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="/admin/pieces/<?= (int) $piece['id'] ?>/versions/<?= (int) $version['id'] ?>/edit" class="admin-link">Edit</a>
                            <form method="post" action="/admin/pieces/<?= (int) $piece['id'] ?>/versions/<?= (int) $version['id'] ?>/delete" class="inline-form" onsubmit="return confirm('Delete this version permanently?')">
                                <button type="submit" class="admin-link danger">Delete</button>
                            </form>
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
