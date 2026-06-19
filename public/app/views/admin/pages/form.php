<?php
$isEdit = $page !== null;
$page = $page ?? [];
$pageTitle = ($isEdit ? 'Edit Page' : 'New Page') . ' — ' . app_site_name() . ' Admin';
$needsEditor = true;
ob_start();
?>
<div class="admin-section">
    <div class="admin-section-head">
        <h1 class="admin-heading"><?= $isEdit ? 'Edit Page' : 'New Page' ?></h1>
        <a href="/admin/pages" class="admin-btn admin-btn-ghost">Back to Pages</a>
    </div>

    <?php if (!empty($_GET['saved'])): ?>
        <p class="admin-notice">Page details saved.</p>
    <?php endif ?>
    <?php if ($pageError ?? null): ?>
        <p class="admin-error"><?= htmlspecialchars($pageError, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif ?>

    <form
        method="POST"
        action="<?= $isEdit ? '/admin/pages/' . (int) $page['id'] . '/edit' : '/admin/pages/create' ?>"
        class="admin-form"
    >
        <div class="form-row">
            <label for="page-name">Title *</label>
            <input id="page-name" type="text" name="title" value="<?= htmlspecialchars($page['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <div class="form-row">
            <label for="page-slug">Slug *</label>
            <input id="page-slug" type="text" name="slug" value="<?= htmlspecialchars($page['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <div class="form-row">
            <label for="page-nav-label">Navigation Label</label>
            <input id="page-nav-label" type="text" name="nav_label" value="<?= htmlspecialchars($page['nav_label'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="toggle-group">
            <label class="toggle-opt"><input type="checkbox" name="show_in_nav" value="1" <?= !empty($page['show_in_nav']) ? 'checked' : '' ?>> Show in primary navigation</label>
        </div>
        <div class="form-row">
            <label for="page-status">Status</label>
            <select id="page-status" name="status">
                <option value="published" <?= ($page['status'] ?? 'published') === 'published' ? 'selected' : '' ?>>Published</option>
                <option value="draft" <?= ($page['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
            </select>
        </div>

        <fieldset class="form-fieldset">
            <legend>Metadata</legend>
            <div class="form-row">
                <label for="page-meta-title">Meta Title</label>
                <input id="page-meta-title" type="text" name="meta_title" value="<?= htmlspecialchars($page['meta_title'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-row">
                <label for="page-meta-description">Meta Description</label>
                <textarea id="page-meta-description" name="meta_description" rows="3"><?= htmlspecialchars($page['meta_description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div class="form-row">
                <label for="page-og-title">Open Graph Title</label>
                <input id="page-og-title" type="text" name="og_title" value="<?= htmlspecialchars($page['og_title'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-row">
                <label for="page-og-description">Open Graph Description</label>
                <textarea id="page-og-description" name="og_description" rows="3"><?= htmlspecialchars($page['og_description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div class="form-row">
                <label for="page-og-image">Open Graph Image URL</label>
                <input id="page-og-image" type="url" name="og_image" value="<?= htmlspecialchars($page['og_image'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <button type="button" class="picker-trigger" data-picker-target="page-og-image">Choose from Library</button>
            </div>
        </fieldset>

        <div class="form-actions">
            <button type="submit" class="admin-btn"><?= $isEdit ? 'Save Page' : 'Create Page' ?></button>
            <a href="/admin/pages" class="admin-btn admin-btn-ghost">Cancel</a>
        </div>
    </form>

    <?php if ($isEdit): ?>
        <div class="admin-section">
            <div class="admin-section-head">
                <h2 class="admin-subheading">Sections</h2>
                <a href="/admin/pages/<?= (int) $page['id'] ?>/sections/create" class="admin-btn">+ New Section</a>
            </div>

            <?php $sections = $sections ?? []; ?>
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
                    <tbody data-reorder-url="/admin/pages/<?= (int) $page['id'] ?>/sections/reorder">
                        <?php foreach ($sections as $section): ?>
                            <tr data-id="<?= (int) $section['id'] ?>">
                                <td class="drag-handle" title="Drag to reorder">&#8597;</td>
                                <td><?= $section['heading'] ? htmlspecialchars($section['heading'], ENT_QUOTES, 'UTF-8') : '<span class="admin-hint">Opening section</span>' ?></td>
                                <td class="admin-actions">
                                    <a href="/admin/pages/sections/<?= (int) $section['id'] ?>/edit">Edit</a>
                                    <form method="POST" action="/admin/pages/sections/<?= (int) $section['id'] ?>/delete"
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
    <?php endif ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
