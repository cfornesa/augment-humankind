<?php
$pageTitle = 'Collections — Augment Humankind Admin';
ob_start();
?>
<div class="admin-section">
    <span id="reorder-status" class="reorder-status" aria-live="polite"></span>
    <div class="admin-section-head">
        <h1 class="admin-heading">Collections</h1>
        <a href="/admin/collections/create" class="admin-btn">+ New Collection</a>
    </div>

    <?php if (empty($collections)): ?>
        <p class="admin-empty">No collections yet.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th></th>
                    <th>Name</th>
                    <th>Exhibits</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="exhibits-sortable" data-reorder-url="/admin/collections/reorder">
                <?php foreach ($collections as $col): ?>
                    <tr data-id="<?= $col['id'] ?>">
                        <td class="drag-handle" title="Drag to reorder">&#8597;</td>
                        <td><?= htmlspecialchars($col['name']) ?></td>
                        <td><?= (int) ($col['exhibit_count'] ?? 0) ?></td>
                        <td class="admin-actions">
                            <a href="/admin/collections/<?= $col['id'] ?>/edit">Edit</a>
                            <form method="POST" action="/admin/collections/<?= $col['id'] ?>/delete"
                                  onsubmit="return confirm('Move this collection to the recycle bin?')">
                                <button type="submit" class="admin-del-btn">Move to trash</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
