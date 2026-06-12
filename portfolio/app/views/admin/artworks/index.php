<?php
$pageTitle = 'Works — Fornesus Art Admin';
ob_start();
?>
<div class="admin-section">
    <span id="reorder-status" class="reorder-status" aria-live="polite"></span>
    <div class="admin-section-head">
        <h1 class="admin-heading">Works</h1>
        <a href="/admin/artworks/create" class="admin-btn">+ Add Work</a>
    </div>

    <?php if (empty($artworks)): ?>
        <p class="admin-empty">No works yet.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th></th>
                    <th>Title</th>
                    <th>Year</th>
                    <th>Category</th>
                    <th>Exhibit</th>
                    <th>Slides</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="artworks-sortable" data-reorder-url="/admin/artworks/reorder">
                <?php foreach ($artworks as $w): ?>
                    <tr data-id="<?= $w['id'] ?>">
                        <td class="drag-handle" title="Drag to reorder">&#8597;</td>
                        <td>
                            <a href="/work/<?= htmlspecialchars($w['slug']) ?>" target="_blank">
                                <?= htmlspecialchars($w['title']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($w['year'] ?? '') ?></td>
                        <td><?= htmlspecialchars(implode(', ', array_column($w['categories'] ?? [], 'name')) ?: '—') ?></td>
                        <td><?= htmlspecialchars(implode(', ', array_column($w['exhibits'] ?? [], 'name')) ?: '—') ?></td>
                        <td><?= count($w['media_items'] ?? Artwork::resolvedMediaItems($w)) ?></td>
                        <td class="admin-actions">
                            <a href="/admin/artworks/<?= $w['id'] ?>/edit">Edit</a>
                            <form method="POST" action="/admin/artworks/<?= $w['id'] ?>/delete"
                                  onsubmit="return confirm('Move this work to the recycle bin?')">
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
