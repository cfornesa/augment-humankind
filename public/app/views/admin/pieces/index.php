<?php

declare(strict_types=1);

$pageTitle = 'Pieces';
ob_start();
?>
<div class="admin-container">
    <div class="admin-header-row">
        <h1>Art Pieces</h1>
        <div style="display: flex; gap: 0.5rem;">
            <a href="/admin/pieces/generate" class="admin-btn">Generate with AI</a>
            <a href="/admin/pieces/create" class="admin-btn admin-btn-ghost">Create Piece</a>
        </div>
    </div>

    <?php if (empty($pieces)): ?>
        <p>No art pieces yet. <a href="/admin/pieces/create">Create the first one</a>.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>ID</th>
                    <th>Engine</th>
                    <th>Status</th>
                    <th>Versions</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pieces as $piece): ?>
                    <tr>
                        <td><?= e($piece['title'] ?? 'Untitled') ?></td>
                        <td><code><?= (int) $piece['id'] ?></code></td>
                        <td><?= e(strtoupper($piece['engine'] ?? 'p5')) ?></td>
                        <td>
                            <span class="status-badge status-<?= e($piece['status'] ?? 'active') ?>">
                                <?= e($piece['status'] ?? 'active') ?>
                            </span>
                        </td>
                        <td><?= (int) ($piece['version_count'] ?? 0) ?></td>
                        <td><?= e($piece['created_at'] ?? '') ?></td>
                        <td>
                            <a href="/pieces/<?= (int) $piece['id'] ?>" target="_blank" class="admin-link">View</a>
                            <a href="/immersive/pieces/<?= (int) $piece['id'] ?>" target="_blank" class="admin-link">Immersive</a>
                            <a href="/admin/pieces/<?= (int) $piece['id'] ?>/versions" class="admin-link">Versions</a>
                            <a href="/admin/pieces/<?= (int) $piece['id'] ?>/edit" class="admin-link">Edit</a>
                            <form method="post" action="/admin/pieces/<?= (int) $piece['id'] ?>/delete" class="inline-form" onsubmit="return confirm('Move this piece to trash?')">
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
