<?php
$isEdit    = $collection !== null;
$collection = $collection ?? [];
$pageTitle = ($isEdit ? 'Edit Collection' : 'New Collection') . ' — Augment Humankind Admin';
ob_start();
?>
<div class="admin-section">
    <h1 class="admin-heading"><?= $isEdit ? 'Edit Collection' : 'New Collection' ?></h1>

    <?php if ($error ?? null): ?>
        <p class="admin-error"><?= htmlspecialchars($error) ?></p>
    <?php endif ?>

    <form
        method="POST"
        enctype="multipart/form-data"
        action="<?= $isEdit ? '/admin/collections/' . $collection['id'] . '/edit' : '/admin/collections/create' ?>"
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
                <div class="exhibit-artwork-list">
                    <?php foreach ($allExhibits as $ex): ?>
                        <label class="exhibit-artwork-check">
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
            <button type="submit" class="admin-btn"><?= $isEdit ? 'Save Changes' : 'Create Collection' ?></button>
            <a href="/admin/collections" class="admin-btn admin-btn-ghost">Cancel</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
