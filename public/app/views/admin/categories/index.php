<?php
$taxonomyLabel = $taxonomyLabel ?? 'Category';
$taxonomyPlural = $taxonomyPlural ?? 'Categories';
$taxonomyCreatePath = $taxonomyCreatePath ?? '/admin/categories/create';
$taxonomyReorderPath = $taxonomyReorderPath ?? '/admin/categories/reorder';
$taxonomyIndexPath = $taxonomyIndexPath ?? '/admin/categories';
$taxonomyDeleteMessage = $taxonomyDeleteMessage ?? ('Move this ' . strtolower($taxonomyLabel) . ' to the recycle bin?');
$pageTitle = $taxonomyPlural . ' — ' . app_site_name() . ' Admin';
ob_start();
?>
<div class="admin-section">
    <span id="reorder-status" class="reorder-status" aria-live="polite"></span>
    <div class="admin-section-head">
        <h1 class="admin-heading"><?= htmlspecialchars($taxonomyPlural) ?></h1>
        <a href="<?= htmlspecialchars($taxonomyCreatePath) ?>" class="admin-btn">+ New <?= htmlspecialchars($taxonomyLabel) ?></a>
    </div>

    <?php if (empty($categories)): ?>
        <p class="admin-empty">No <?= htmlspecialchars(strtolower($taxonomyPlural)) ?> yet.</p>
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
            <tbody id="categories-sortable" data-reorder-url="<?= htmlspecialchars($taxonomyReorderPath) ?>">
                <?php foreach ($categories as $cat): ?>
                    <tr data-id="<?= $cat['id'] ?>">
                        <td class="drag-handle" title="Drag to reorder">&#8597;</td>
                        <td><?= htmlspecialchars($cat['name']) ?></td>
                        <td><?= htmlspecialchars($cat['slug']) ?></td>
                        <td class="admin-actions">
                            <a href="<?= htmlspecialchars($taxonomyIndexPath) ?>/<?= $cat['id'] ?>/edit">Edit</a>
                            <form method="POST" action="<?= htmlspecialchars($taxonomyIndexPath) ?>/<?= $cat['id'] ?>/delete"
                                  onsubmit="return confirm('<?= htmlspecialchars($taxonomyDeleteMessage, ENT_QUOTES, 'UTF-8') ?>')">
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
