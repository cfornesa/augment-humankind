<?php
$pageTitle = 'Deleted Pages — Fornesus Admin';
ob_start();
?>
<div class="admin-section">
    <div class="admin-section-head">
        <h1 class="admin-heading">Deleted Pages</h1>
        <a href="/admin/pages" class="admin-btn admin-btn-ghost">← Back to Pages</a>
    </div>

    <?php if (empty($pages)): ?>
        <p class="admin-empty">No deleted pages.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Slug</th>
                    <th>Deleted</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pages as $page): ?>
                    <tr>
                        <td><?= htmlspecialchars($page['title']) ?></td>
                        <td>/<?= htmlspecialchars($page['slug']) ?></td>
                        <td class="admin-hint">
                            <?= htmlspecialchars(date('Y-m-d H:i', strtotime($page['deleted_at']))) ?>
                        </td>
                        <td class="admin-actions">
                            <form method="POST" action="/admin/pages/<?= (int) $page['id'] ?>/restore"
                                  style="display:inline">
                                <button type="submit" class="admin-btn admin-btn-sm">Restore</button>
                            </form>
                            <form method="POST" action="/admin/pages/<?= (int) $page['id'] ?>/hard-delete"
                                  style="display:inline"
                                  onsubmit="return confirm('Permanently delete “<?= htmlspecialchars(addslashes($page['title'])) ?>” and all of its sections? This cannot be undone.')">
                                <button type="submit" class="admin-del-btn">Delete forever</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>

        <form method="POST" action="/admin/pages/trash/empty" style="margin-top:1.8rem"
              onsubmit="return confirm('Permanently delete all pages in the trash? This cannot be undone.')">
            <button type="submit" class="admin-btn admin-btn-ghost">Empty trash</button>
        </form>
    <?php endif ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
