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

function itemSortWeight(string $type, int $id, array $assigned): int
{
    foreach ($assigned as $index => $item) {
        if (($item['item_type'] ?? '') === $type && (int) ($item['item_id'] ?? 0) === $id) {
            return $index;
        }
    }

    return 1000000 + $id;
}

$sortableItems = [];
foreach ($allPieces as $piece) {
    $sortableItems[] = [
        'item_type' => 'art_piece',
        'item_id' => (int) $piece['id'],
        'label' => ($piece['title'] ?? 'Untitled') . ' (' . ($piece['engine'] ?? 'p5') . ')',
        'assigned' => isItemAssigned('art_piece', (int) $piece['id'], $assignedItems),
    ];
}
foreach ($allAssets as $asset) {
    $sortableItems[] = [
        'item_type' => 'media_asset',
        'item_id' => (int) $asset['id'],
        'label' => ($asset['title'] ?: ($asset['filename'] ?: ('Media Asset #' . $asset['id']))) . ' (' . ($asset['mime_type'] ?? '') . ')',
        'assigned' => isItemAssigned('media_asset', (int) $asset['id'], $assignedItems),
    ];
}

usort($sortableItems, static function (array $left, array $right) use ($assignedItems): int {
    $leftAssigned = $left['assigned'] ? 0 : 1;
    $rightAssigned = $right['assigned'] ? 0 : 1;
    if ($leftAssigned !== $rightAssigned) {
        return $leftAssigned <=> $rightAssigned;
    }

    $leftWeight = itemSortWeight($left['item_type'], (int) $left['item_id'], $assignedItems);
    $rightWeight = itemSortWeight($right['item_type'], (int) $right['item_id'], $assignedItems);
    if ($leftWeight !== $rightWeight) {
        return $leftWeight <=> $rightWeight;
    }

    return strcasecmp((string) $left['label'], (string) $right['label']);
});

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
            <p class="admin-hint" style="margin-bottom: 1rem;">Drag items into your preferred display order. Checked items are saved in the order shown here across both pieces and media assets.</p>

            <?php if (empty($sortableItems)): ?>
                <p class="admin-hint">No platform art pieces or media assets found yet.</p>
            <?php else: ?>
                <div class="exhibit-artwork-list" data-checkbox-sortable style="max-height: 420px; overflow-y: auto; border: 1px solid var(--line); padding: 0.5rem; background: var(--paper);">
                    <?php foreach ($sortableItems as $item): ?>
                        <label class="exhibit-artwork-check" style="display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.25rem; cursor: pointer;" draggable="true">
                            <span class="artwork-slide-handle" aria-hidden="true" title="Drag to reorder">&#8597;</span>
                            <input
                                type="checkbox"
                                name="items[]"
                                value="<?= e($item['item_type']) ?>:<?= (int) $item['item_id'] ?>"
                                <?= $item['assigned'] ? 'checked' : '' ?>
                            >
                            <span><?= htmlspecialchars($item['label']) ?></span>
                        </label>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
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
