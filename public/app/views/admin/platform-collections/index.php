<?php

declare(strict_types=1);

$pageTitle = 'Platform Collections';
ob_start();
?>
<div class="admin-container">
    <div class="admin-header-row">
        <h1>Platform Collections</h1>
        <a href="/admin/platform-collections/create" class="admin-btn">+ New Collection</a>
    </div>

    <p>Curated collections migrated from the platform app. Manage their metadata, items, layouts, and external embeds here.</p>

    <?php if (empty($collections)): ?>
        <p>No platform collections yet.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Thumbnail</th>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Items</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($collections as $collection): ?>
                    <tr>
                        <td>
                            <?php if (!empty($collection['thumbnail_url'])): ?>
                                <img src="<?= e($collection['thumbnail_url']) ?>" alt="" style="width: 60px; height: 45px; object-fit: cover;">
                            <?php endif; ?>
                        </td>
                        <td><?= e($collection['name'] ?? 'Untitled Collection') ?></td>
                        <td><code><?= e($collection['slug'] ?? '') ?></code></td>
                        <td><?= (int) ($collection['item_count'] ?? 0) ?></td>
                        <td><?= e($collection['created_at'] ?? '') ?></td>
                        <td>
                            <a href="/collections/<?= e($collection['slug'] ?? '') ?>" target="_blank" class="admin-link">View</a>
                            <a href="/immersive/collections/<?= e($collection['slug'] ?? '') ?>" target="_blank" class="admin-link">Immersive</a>
                            <a href="/admin/platform-collections/<?= (int) $collection['id'] ?>/edit" class="admin-link">Edit</a>
                            <form method="POST" action="/admin/platform-collections/<?= (int) $collection['id'] ?>/delete" style="display:inline;" onsubmit="return confirm('Move this collection to the recycle bin?')">
                                <button type="submit" class="admin-link-danger" style="background:none;border:none;padding:0;font:inherit;cursor:pointer;">Delete</button>
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
