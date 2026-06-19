<?php
$pageTitle = 'Pages — ' . app_site_name() . ' Admin';
$trashedCount = Page::trashedCount();
$error = $_GET['error'] ?? null;
ob_start();
?>
<div class="admin-section">
    <span id="reorder-status" class="reorder-status" aria-live="polite"></span>
    <?php if ($error): ?>
        <div class="form-status form-status-error" role="alert">
            <p><?= e($error) ?></p>
        </div>
    <?php endif; ?>
    <div class="admin-section-head">
        <h1 class="admin-heading">Pages</h1>
        <div class="admin-actions">
            <?php if ($trashedCount > 0): ?>
                <a href="/admin/pages/trash" class="admin-btn admin-btn-ghost admin-btn-sm">Trash <?= $trashedCount ?></a>
            <?php else: ?>
                <a href="/admin/pages/trash" class="admin-btn admin-btn-ghost admin-btn-sm">Trash</a>
            <?php endif ?>
            <a href="/admin/pages/create" class="admin-btn">+ New Page</a>
        </div>
    </div>

    <?php if (empty($pages)): ?>
        <p class="admin-empty">No pages yet.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th></th>
                    <th>Title</th>
                    <th>Slug</th>
                    <th>Status</th>
                    <th>Nav</th>
                    <th></th>
                </tr>
            </thead>
            <tbody data-reorder-url="/admin/pages/reorder">
                <?php foreach ($pages as $page): ?>
                    <tr data-id="<?= (int) $page['id'] ?>">
                        <td class="drag-handle" title="Drag to reorder">&#8597;</td>
                        <td><?= htmlspecialchars($page['title'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>/<?= htmlspecialchars($page['slug'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($page['status'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= !empty($page['show_in_nav']) ? 'Visible' : 'Hidden' ?></td>
                        <td class="admin-actions">
                            <a href="/admin/pages/<?= (int) $page['id'] ?>/edit">Edit</a>
                            <?php if (Page::isProtectedSlug($page['slug'])): ?>
                                <span class="admin-hint">System page</span>
                            <?php else: ?>
                                <form method="POST" action="/admin/pages/<?= (int) $page['id'] ?>/delete"
                                      onsubmit="return confirm('Move this page and all of its sections to the trash?')">
                                    <button type="submit" class="admin-del-btn">Move to Trash</button>
                                </form>
                            <?php endif; ?>
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
