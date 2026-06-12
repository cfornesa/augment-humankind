<?php
$pageTitle   = 'Pages — Fornesus Admin';
$trashedCount = Page::trashedCount();
ob_start();
?>
<div class="admin-section">
    <span id="reorder-status" class="reorder-status" aria-live="polite"></span>
    <div class="admin-section-head">
        <h1 class="admin-heading">Pages</h1>
        <div style="display:flex;gap:0.8rem;align-items:center">
            <?php if ($trashedCount > 0): ?>
                <a href="/admin/pages/trash" class="admin-btn admin-btn-ghost admin-btn-sm">
                    Trash <?= $trashedCount ?>
                </a>
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
                    <th>Template</th>
                    <th>Status</th>
                    <th>Nav</th>
                    <th></th>
                </tr>
            </thead>
            <tbody data-reorder-url="/admin/pages/reorder">
                <?php foreach ($pages as $page): ?>
                    <tr data-id="<?= (int) $page['id'] ?>">
                        <td class="drag-handle" title="Drag to reorder">&#8597;</td>
                        <td><?= htmlspecialchars($page['title']) ?></td>
                        <td>/<?= htmlspecialchars($page['slug']) ?></td>
                        <td><?= htmlspecialchars($page['template']) ?></td>
                        <td><?= htmlspecialchars($page['status']) ?></td>
                        <td class="page-nav-cell">
                            <span class="nav-page-status <?= !empty($page['nav_is_visible']) ? 'is-visible' : 'is-hidden' ?>">
                                <?= !empty($page['nav_is_visible']) ? 'Visible in navigation' : 'Hidden from navigation' ?>
                            </span>
                            <a href="/admin/navigation" class="admin-hint">Manage</a>
                        </td>
                        <td class="admin-actions">
                            <a href="/admin/pages/<?= (int) $page['id'] ?>/edit">Edit</a>
                            <form method="POST" action="/admin/pages/<?= (int) $page['id'] ?>/delete"
                                  onsubmit="return confirm('Move this page and all of its sections to the trash?')">
                                <button type="submit" class="admin-del-btn">Move to Trash</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>

<style>
.page-nav-cell { display: flex; align-items: center; gap: 0.5rem; }
.nav-page-status.is-visible { color: var(--amber); }
.nav-page-status.is-hidden { opacity: 0.55; }
</style>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
