<?php
$pageTitle = 'Bio — Fornesus Admin';
ob_start();
?>
<div class="admin-section">
    <span id="reorder-status" class="reorder-status"></span>
    <div class="admin-section-head">
        <h1 class="admin-heading">Bio Sections</h1>
        <a href="/admin/bio/create" class="admin-btn">+ New Section</a>
    </div>

    <?php if (empty($sections)): ?>
        <p class="admin-empty">No sections yet.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th></th>
                    <th>Heading</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="bio-sortable" data-reorder-url="/admin/bio/reorder">
                <?php foreach ($sections as $s): ?>
                    <tr data-id="<?= $s['id'] ?>">
                        <td class="drag-handle" title="Drag to reorder">&#8597;</td>
                        <td><?= $s['heading'] ? htmlspecialchars($s['heading']) : '<em style="opacity:.4">Opening paragraph</em>' ?></td>
                        <td class="admin-actions">
                            <a href="/admin/bio/<?= $s['id'] ?>/edit">Edit</a>
                            <form method="POST" action="/admin/bio/<?= $s['id'] ?>/delete"
                                  onsubmit="return confirm('Delete this section?')">
                                <button type="submit" class="admin-del-btn">Delete</button>
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
