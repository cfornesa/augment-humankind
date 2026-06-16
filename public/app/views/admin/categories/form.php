<?php
$isEdit    = $category !== null;
$category  = $category ?? [];
$taxonomyLabel = $taxonomyLabel ?? 'Category';
$taxonomyPlural = $taxonomyPlural ?? 'Categories';
$taxonomyIndexPath = $taxonomyIndexPath ?? '/admin/categories';
$taxonomyCreatePath = $taxonomyCreatePath ?? '/admin/categories/create';
$taxonomyEditBasePath = $taxonomyEditBasePath ?? '/admin/categories';
$showTaxonomyThumbnail = $showTaxonomyThumbnail ?? true;
$pageTitle = ($isEdit ? 'Edit ' . $taxonomyLabel : 'New ' . $taxonomyLabel) . ' — Augment Humankind Admin';
$needsEditor = true;
ob_start();
?>
<div class="admin-section">
    <h1 class="admin-heading"><?= htmlspecialchars($isEdit ? 'Edit ' . $taxonomyLabel : 'New ' . $taxonomyLabel) ?></h1>

    <?php if ($error ?? null): ?>
        <p class="admin-error"><?= htmlspecialchars($error) ?></p>
    <?php endif ?>

    <form
        method="POST"
        enctype="multipart/form-data"
        action="<?= $isEdit ? $taxonomyEditBasePath . '/' . $category['id'] . '/edit' : $taxonomyCreatePath ?>"
        class="admin-form"
    >
        <div class="form-row">
            <label>Name *</label>
            <input type="text" name="name" id="cat-name" value="<?= htmlspecialchars($category['name'] ?? '') ?>" required>
        </div>

        <div class="form-row">
            <label>Slug <span class="form-hint">(auto-generated; do not change after publishing)</span></label>
            <input type="text" name="slug" id="cat-slug" value="<?= htmlspecialchars($category['slug'] ?? '') ?>">
        </div>

        <div class="form-row">
            <label>Description</label>
            <textarea name="description" rows="5" data-tiptap><?= htmlspecialchars($category['description'] ?? '') ?></textarea>
        </div>

        <?php if ($showTaxonomyThumbnail): ?>
            <fieldset class="form-fieldset">
                <legend>Thumbnail <span class="form-hint">(optional)</span></legend>
                <input type="hidden" name="thumbnail_type" value="link">
                <div class="media-field-preview" id="cat-thumb-preview">
                    <?php if ($isEdit && $category['thumbnail_value']): ?>
                        <img src="<?= htmlspecialchars($category['thumbnail_value']) ?>" alt="">
                    <?php endif ?>
                </div>
                <input id="cat-thumb-url" type="url" name="thumbnail_link"
                       value="<?= htmlspecialchars($category['thumbnail_value'] ?? '') ?>"
                       placeholder="No image selected" readonly>
                <div class="media-field-actions">
                    <button type="button" class="picker-trigger"
                            data-picker-target="cat-thumb-url"
                            data-picker-preview="cat-thumb-preview">Choose Image</button>
                    <button type="button" class="admin-btn admin-btn-ghost admin-btn-sm"
                            data-clear-input="cat-thumb-url"
                            data-clear-preview="cat-thumb-preview">Clear</button>
                </div>
            </fieldset>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit" class="admin-btn"><?= htmlspecialchars($isEdit ? 'Save Changes' : 'Create ' . $taxonomyLabel) ?></button>
            <a href="<?= htmlspecialchars($taxonomyIndexPath) ?>" class="admin-btn admin-btn-ghost">Cancel</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
