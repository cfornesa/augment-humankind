<?php

declare(strict_types=1);

$isEdit = $collection !== null;
$collection = $collection ?? [];
$pageTitle = ($isEdit ? 'Edit Platform Collection' : 'New Platform Collection') . ' — Augment Humankind Admin';

function isItemAssigned(string $type, int $id, array $assigned): bool {
    foreach ($assigned as $item) {
        if (($item['item_type'] ?? '') === $type && (int) ($item['item_id'] ?? 0) === $id) {
            return true;
        }
    }
    return false;
}

ob_start();
?>
<div class="admin-section">
    <h1 class="admin-heading"><?= $isEdit ? 'Edit Platform Collection' : 'New Platform Collection' ?></h1>

    <?php if ($error ?? null): ?>
        <p class="admin-error"><?= htmlspecialchars($error) ?></p>
    <?php endif ?>

    <form
        method="POST"
        action="<?= $isEdit ? '/admin/platform-collections/' . $collection['id'] . '/edit' : '/admin/platform-collections/create' ?>"
        class="admin-form"
    >
        <div class="form-row">
            <label>Name *</label>
            <input type="text" name="name" id="collection-name" value="<?= htmlspecialchars($collection['name'] ?? '') ?>" required>
        </div>

        <div class="form-row">
            <label>Slug <span class="form-hint">(auto-generated if left blank)</span></label>
            <input type="text" name="slug" id="collection-slug" value="<?= htmlspecialchars($collection['slug'] ?? '') ?>">
        </div>

        <div class="field-grid" style="display: flex; gap: 1rem;">
            <div class="form-row" style="flex: 1;">
                <label>Grid Rows</label>
                <input type="number" name="rows" min="1" max="20" value="<?= (int) ($collection['rows'] ?? 1) ?>">
            </div>
            <div class="form-row" style="flex: 1;">
                <label>Grid Columns</label>
                <input type="number" name="cols" min="1" max="20" value="<?= (int) ($collection['cols'] ?? 1) ?>">
            </div>
        </div>

        <div class="form-row">
            <label>Description</label>
            <textarea name="description" rows="4"><?= htmlspecialchars($collection['description'] ?? '') ?></textarea>
        </div>

        <div class="form-row">
            <label>Artist Statement</label>
            <textarea name="artist_statement" rows="4"><?= htmlspecialchars($collection['artist_statement'] ?? '') ?></textarea>
        </div>

        <div class="form-row">
            <label>Biography</label>
            <textarea name="biography" rows="4"><?= htmlspecialchars($collection['biography'] ?? '') ?></textarea>
        </div>

        <div class="form-row">
            <label>External Iframe HTML <span class="form-hint">(optional; if provided, embeds this code directly instead of the interactive grid room)</span></label>
            <textarea name="iframe_code" rows="4" placeholder='<iframe src="https://example.com" ...></iframe>'><?= htmlspecialchars($collection['iframe_code'] ?? '') ?></textarea>
        </div>

        <fieldset class="form-fieldset">
            <legend>Items in this Collection</legend>
            <p class="admin-hint" style="margin-bottom: 1rem;">Select the platform art pieces and media assets to display on the collection's grid wall. They will be ordered in the sequence selected below.</p>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <div>
                    <h3 class="admin-subheading" style="margin-bottom: 0.5rem;">Platform Art Pieces</h3>
                    <?php if (empty($allPieces)): ?>
                        <p class="admin-hint">No platform art pieces found.</p>
                    <?php else: ?>
                        <div class="exhibit-artwork-list" style="max-height: 300px; overflow-y: auto; border: 1px solid var(--line); padding: 0.5rem; background: var(--paper);">
                            <?php foreach ($allPieces as $piece): ?>
                                <label class="exhibit-artwork-check" style="display: block; margin-bottom: 0.25rem; cursor: pointer;">
                                    <input
                                        type="checkbox"
                                        name="items[]"
                                        value="art_piece:<?= (int) $piece['id'] ?>"
                                        <?= isItemAssigned('art_piece', (int) $piece['id'], $assignedItems) ? 'checked' : '' ?>
                                    >
                                    <span><?= htmlspecialchars($piece['title'] ?? 'Untitled') ?> <small style="color:var(--ink-soft)">(<?= htmlspecialchars($piece['engine'] ?? 'p5') ?>)</small></span>
                                </label>
                            <?php endforeach ?>
                        </div>
                    <?php endif ?>
                </div>

                <div>
                    <h3 class="admin-subheading" style="margin-bottom: 0.5rem;">Media Assets</h3>
                    <?php if (empty($allAssets)): ?>
                        <p class="admin-hint">No media assets found.</p>
                    <?php else: ?>
                        <div class="exhibit-artwork-list" style="max-height: 300px; overflow-y: auto; border: 1px solid var(--line); padding: 0.5rem; background: var(--paper);">
                            <?php foreach ($allAssets as $asset): ?>
                                <label class="exhibit-artwork-check" style="display: block; margin-bottom: 0.25rem; cursor: pointer;">
                                    <input
                                        type="checkbox"
                                        name="items[]"
                                        value="media_asset:<?= (int) $asset['id'] ?>"
                                        <?= isItemAssigned('media_asset', (int) $asset['id'], $assignedItems) ? 'checked' : '' ?>
                                    >
                                    <span><?= htmlspecialchars($asset['title'] ?: ($asset['filename'] ?: ('Media Asset #' . $asset['id']))) ?> <small style="color:var(--ink-soft)">(<?= htmlspecialchars($asset['mime_type'] ?? '') ?>)</small></span>
                                </label>
                            <?php endforeach ?>
                        </div>
                    <?php endif ?>
                </div>
            </div>
        </fieldset>

        <div class="form-actions">
            <button type="submit" class="admin-btn"><?= $isEdit ? 'Save Changes' : 'Create Collection' ?></button>
            <a href="/admin/platform-collections" class="admin-btn admin-btn-ghost">Cancel</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
