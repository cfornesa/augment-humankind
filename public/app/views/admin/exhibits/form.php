<?php
$isEdit    = $exhibit !== null;
$exhibit   = $exhibit ?? [];
$pageTitle = ($isEdit ? 'Edit Exhibit' : 'New Exhibit') . ' — Augment Humankind Admin';
ob_start();
?>
<div class="admin-section">
    <h1 class="admin-heading"><?= $isEdit ? 'Edit Exhibit' : 'New Exhibit' ?></h1>

    <?php if ($error ?? null): ?>
        <p class="admin-error"><?= htmlspecialchars($error) ?></p>
    <?php endif ?>

    <form
        method="POST"
        enctype="multipart/form-data"
        action="<?= $isEdit ? '/admin/exhibits/' . $exhibit['id'] . '/edit' : '/admin/exhibits/create' ?>"
        class="admin-form"
    >
        <div class="form-row">
            <label>Name *</label>
            <input type="text" name="name" id="exhibit-name" value="<?= htmlspecialchars($exhibit['name'] ?? '') ?>" required>
        </div>

        <div class="form-row">
            <label>Slug <span class="form-hint">(auto-generated; do not change after publishing)</span></label>
            <input type="text" name="slug" id="exhibit-slug" value="<?= htmlspecialchars($exhibit['slug'] ?? '') ?>">
        </div>

        <div class="form-row">
            <label>Description</label>
            <textarea name="description" rows="5" data-tiptap><?= htmlspecialchars($exhibit['description'] ?? '') ?></textarea>
        </div>

        <!-- Thumbnail -->
        <fieldset class="form-fieldset">
            <legend>Thumbnail <span class="form-hint">(optional)</span></legend>
            <input type="hidden" name="thumbnail_type" value="link">
            <div class="media-field-preview" id="exhibit-thumb-preview">
                <?php if ($isEdit && $exhibit['thumbnail_value']): ?>
                    <img src="<?= htmlspecialchars($exhibit['thumbnail_value']) ?>" alt="">
                <?php endif ?>
            </div>
            <input id="exhibit-thumb-url" type="url" name="thumbnail_link"
                   value="<?= htmlspecialchars($exhibit['thumbnail_value'] ?? '') ?>"
                   placeholder="No image selected" readonly>
            <div class="media-field-actions">
                <button type="button" class="picker-trigger"
                        data-picker-target="exhibit-thumb-url"
                        data-picker-preview="exhibit-thumb-preview">Choose Image</button>
                <button type="button" class="admin-btn admin-btn-ghost admin-btn-sm"
                        data-clear-input="exhibit-thumb-url"
                        data-clear-preview="exhibit-thumb-preview">Clear</button>
            </div>
        </fieldset>

        <!-- Artwork assignment -->
        <fieldset class="form-fieldset">
            <legend>Artworks in this exhibit</legend>
            <?php if (empty($allArtworks)): ?>
                <p class="admin-hint">No artworks exist yet. Add some first.</p>
            <?php else: ?>
                <div class="exhibit-artwork-list">
                    <?php foreach ($allArtworks as $aw): ?>
                        <label class="exhibit-artwork-check">
                            <input
                                type="checkbox"
                                name="artwork_ids[]"
                                value="<?= $aw['id'] ?>"
                                <?= in_array((string) $aw['id'], array_map('strval', $assigned)) ? 'checked' : '' ?>
                            >
                            <span><?= htmlspecialchars($aw['title']) ?><?= $aw['year'] ? ' · ' . htmlspecialchars($aw['year']) : '' ?></span>
                        </label>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </fieldset>

        <div class="form-actions">
            <button type="submit" class="admin-btn"><?= $isEdit ? 'Save Changes' : 'Create Exhibit' ?></button>
            <a href="/admin/exhibits" class="admin-btn admin-btn-ghost">Cancel</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
