<?php
$collectionLabel = $collectionLabel ?? 'Collection';
$collectionPlural = $collectionPlural ?? 'Collections';
$collectionCreatePath = $collectionCreatePath ?? '/admin/collections/create';
$collectionReorderPath = $collectionReorderPath ?? '/admin/collections/reorder';
$collectionIndexPath = $collectionIndexPath ?? '/admin/collections';
$collectionDeleteMessage = $collectionDeleteMessage ?? ('Move this ' . strtolower($collectionLabel) . ' to the recycle bin?');
$pageTitle = $collectionPlural . ' — Augment Humankind Admin';
ob_start();
?>
<div class="admin-section">
    <span id="reorder-status" class="reorder-status" aria-live="polite"></span>
    <div class="admin-section-head">
        <h1 class="admin-heading"><?= htmlspecialchars($collectionPlural) ?></h1>
        <a href="<?= htmlspecialchars($collectionCreatePath) ?>" class="admin-btn">+ New <?= htmlspecialchars($collectionLabel) ?></a>
    </div>

    <?php if (empty($collections)): ?>
        <p class="admin-empty">No <?= htmlspecialchars(strtolower($collectionPlural)) ?> yet.</p>
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
            <tbody id="exhibits-sortable" data-reorder-url="<?= htmlspecialchars($collectionReorderPath) ?>">
                <?php foreach ($collections as $col): ?>
                    <tr data-id="<?= $col['id'] ?>">
                        <td class="drag-handle" title="Drag to reorder">&#8597;</td>
                        <td><?= htmlspecialchars($col['name']) ?></td>
                        <td><?= (int) ($col['exhibit_count'] ?? 0) ?></td>
                        <td class="admin-actions">
                            <a href="<?= htmlspecialchars($collectionIndexPath) ?>/<?= $col['id'] ?>/edit">Edit</a>
                            <form method="POST" action="<?= htmlspecialchars($collectionIndexPath) ?>/<?= $col['id'] ?>/delete"
                                  onsubmit="return confirm('<?= htmlspecialchars($collectionDeleteMessage, ENT_QUOTES, 'UTF-8') ?>')">
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
