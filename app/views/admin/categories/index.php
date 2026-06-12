<?php
$pageTitle = 'Categories — Augment Humankind Admin';
ob_start();
?>
<div class="admin-section">
    <span id="reorder-status" class="reorder-status" aria-live="polite"></span>
    <div class="admin-section-head">
        <h1 class="admin-heading">Categories</h1>
        <a href="/admin/categories/create" class="admin-btn">+ New Category</a>
    </div>

    <?php if (empty($categories)): ?>
        <p class="admin-empty">No categories yet.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th></th>
                    <th>Name</th>
                    <th>Slug</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="categories-sortable" data-reorder-url="/admin/categories/reorder">
                <?php foreach ($categories as $cat): ?>
                    <tr data-id="<?= $cat['id'] ?>">
                        <td class="drag-handle" title="Drag to reorder">&#8597;</td>
                        <td><?= htmlspecialchars($cat['name']) ?></td>
                        <td><?= htmlspecialchars($cat['slug']) ?></td>
                        <td class="admin-actions">
                            <a href="/admin/categories/<?= $cat['id'] ?>/edit">Edit</a>
                            <form method="POST" action="/admin/categories/<?= $cat['id'] ?>/delete"
                                  onsubmit="return confirm('Move this category to the recycle bin? Works will become uncategorised.')">
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
