<?php
$pageTitle = 'Exhibits — Augment Humankind Admin';
ob_start();
?>
<div class="admin-section">
    <span id="reorder-status" class="reorder-status" aria-live="polite"></span>
    <div class="admin-section-head">
        <h1 class="admin-heading">Exhibits</h1>
        <a href="/admin/exhibits/create" class="admin-btn">+ Add Exhibit</a>
    </div>

    <?php if (empty($exhibits)): ?>
        <p class="admin-empty">No exhibits yet.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th></th>
                    <th>Title</th>
                    <th>Year</th>
                    <th>Category</th>
                    <th>Collection</th>
                    <th>Slides</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="artworks-sortable" data-reorder-url="/admin/exhibits/reorder">
                <?php foreach ($exhibits as $ex): ?>
                    <tr data-id="<?= $ex['id'] ?>">
                        <td class="drag-handle" title="Drag to reorder">&#8597;</td>
                        <td>
                            <a href="/portfolio/exhibit/<?= htmlspecialchars($ex['slug']) ?>" target="_blank">
                                <?= htmlspecialchars($ex['title']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($ex['year'] ?? '') ?></td>
                        <td><?= htmlspecialchars(implode(', ', array_column($ex['categories'] ?? [], 'name')) ?: '—') ?></td>
                        <td><?= htmlspecialchars(implode(', ', array_column($ex['collections'] ?? [], 'name')) ?: '—') ?></td>
                        <td><?= count($ex['media_items'] ?? Exhibit::resolvedMediaItems($ex)) ?></td>
                        <td class="admin-actions">
                            <a href="/admin/exhibits/<?= $ex['id'] ?>/edit">Edit</a>
                            <form method="POST" action="/admin/exhibits/<?= $ex['id'] ?>/delete"
                                  onsubmit="return confirm('Move this exhibit to the recycle bin?')">
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
