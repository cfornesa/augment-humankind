<?php
$isEdit    = $section !== null;
$section   = $section ?? [];
$pageTitle = ($isEdit ? 'Edit Section' : 'New Section') . ' — Fornesus Art Admin';
ob_start();
?>
<div class="admin-section">
    <h1 class="admin-heading"><?= $isEdit ? 'Edit Section' : 'New Section' ?></h1>

    <?php if ($error ?? null): ?>
        <p class="admin-error"><?= htmlspecialchars($error) ?></p>
    <?php endif ?>

    <form
        method="POST"
        action="<?= $isEdit ? '/admin/bio/' . $section['id'] . '/edit' : '/admin/bio/create' ?>"
        class="admin-form"
    >
        <div class="form-row">
            <label>Heading <span class="form-hint">(leave blank for an opening paragraph with no heading)</span></label>
            <input type="text" name="heading" value="<?= htmlspecialchars($section['heading'] ?? '') ?>">
        </div>
        <div class="form-row">
            <label>Content *</label>
            <textarea name="content" rows="10" required data-tiptap><?= htmlspecialchars($section['content'] ?? '') ?></textarea>
        </div>
        <div class="form-actions">
            <button type="submit" class="admin-btn"><?= $isEdit ? 'Save Changes' : 'Add Section' ?></button>
            <a href="/admin/bio" class="admin-btn admin-btn-ghost">Cancel</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
