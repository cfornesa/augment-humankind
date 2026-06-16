<?php
$isEdit    = $collection !== null;
$collection = $collection ?? [];
$collectionLabel = $collectionLabel ?? 'Collection';
$collectionPlural = $collectionPlural ?? 'Collections';
$collectionIndexPath = $collectionIndexPath ?? '/admin/collections';
$collectionCreatePath = $collectionCreatePath ?? '/admin/collections/create';
$collectionEditBasePath = $collectionEditBasePath ?? '/admin/collections';
$pageTitle = ($isEdit ? 'Edit ' . $collectionLabel : 'New ' . $collectionLabel) . ' — Augment Humankind Admin';
$needsEditor = true;
ob_start();
?>
<div class="admin-section">
    <h1 class="admin-heading"><?= htmlspecialchars($isEdit ? 'Edit ' . $collectionLabel : 'New ' . $collectionLabel) ?></h1>

    <?php if ($error ?? null): ?>
        <p class="admin-error"><?= htmlspecialchars($error) ?></p>
    <?php endif ?>

    <form
        method="POST"
        enctype="multipart/form-data"
        action="<?= $isEdit ? $collectionEditBasePath . '/' . $collection['id'] . '/edit' : $collectionCreatePath ?>"
        class="admin-form"
    >
        <div class="form-row">
            <label>Name *</label>
            <input type="text" name="name" id="collection-name" value="<?= htmlspecialchars($collection['name'] ?? '') ?>" required>
        </div>

        <div class="form-row">
            <label>Slug <span class="form-hint">(auto-generated; do not change after publishing)</span></label>
            <input type="text" name="slug" id="collection-slug" value="<?= htmlspecialchars($collection['slug'] ?? '') ?>">
        </div>

        <div class="form-row">
            <label>Description</label>
            <textarea name="description" rows="5" data-tiptap><?= htmlspecialchars($collection['description'] ?? '') ?></textarea>
        </div>

        <!-- Thumbnail -->
        <fieldset class="form-fieldset">
            <legend>Thumbnail <span class="form-hint">(optional)</span></legend>
            <input type="hidden" name="thumbnail_type" value="link">
            <div class="media-field-preview" id="collection-thumb-preview">
                <?php if ($isEdit && $collection['thumbnail_value']): ?>
                    <img src="<?= htmlspecialchars($collection['thumbnail_value']) ?>" alt="">
                <?php endif ?>
            </div>
            <input id="collection-thumb-url" type="url" name="thumbnail_link"
                   value="<?= htmlspecialchars($collection['thumbnail_value'] ?? '') ?>"
                   placeholder="No image selected" readonly>
            <div class="media-field-actions">
                <button type="button" class="picker-trigger"
                        data-picker-target="collection-thumb-url"
                        data-picker-preview="collection-thumb-preview">Choose Image</button>
                <button type="button" class="admin-btn admin-btn-ghost admin-btn-sm"
                        data-clear-input="collection-thumb-url"
                        data-clear-preview="collection-thumb-preview">Clear</button>
            </div>
        </fieldset>

        <!-- Exhibit assignment -->
        <fieldset class="form-fieldset">
            <legend>Exhibits in this collection</legend>
            <?php if (empty($allExhibits)): ?>
                <p class="admin-hint">No exhibits exist yet. Add some first.</p>
            <?php else: ?>
                <p class="admin-hint" style="margin-bottom: 0.75rem;">Drag exhibits into your preferred order. Checked exhibits are saved in the order shown here.</p>
                <div class="exhibit-artwork-list" data-checkbox-sortable>
                    <?php foreach ($allExhibits as $ex): ?>
                        <label class="exhibit-artwork-check" draggable="true">
                            <span class="artwork-slide-handle" aria-hidden="true" title="Drag to reorder">&#8597;</span>
                            <input
                                type="checkbox"
                                name="exhibit_ids[]"
                                value="<?= $ex['id'] ?>"
                                <?= in_array((string) $ex['id'], array_map('strval', $assigned)) ? 'checked' : '' ?>
                            >
                            <span><?= htmlspecialchars($ex['title']) ?><?= $ex['year'] ? ' · ' . htmlspecialchars($ex['year']) : '' ?></span>
                        </label>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </fieldset>

        <div class="form-actions">
            <button type="submit" class="admin-btn"><?= htmlspecialchars($isEdit ? 'Save Changes' : 'Create ' . $collectionLabel) ?></button>
            <a href="<?= htmlspecialchars($collectionIndexPath) ?>" class="admin-btn admin-btn-ghost">Cancel</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
