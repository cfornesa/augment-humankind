<?php

declare(strict_types=1);

$isEdit = $collection !== null;
$collection = $collection ?? [];
$pageTitle = ($isEdit ? 'Edit Platform Collection' : 'New Platform Collection') . ' — ' . app_site_name() . ' Admin';

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
        <input type="hidden" name="thumbnail_url" id="collection-thumbnail-url" value="<?= e($collection['thumbnail_url'] ?? '') ?>">
        <div class="form-row">
            <label>Name *</label>
            <input type="text" name="name" id="collection-name" value="<?= htmlspecialchars($collection['name'] ?? '') ?>" required>
        </div>

        <div class="form-row">
            <label>Slug <span class="form-hint">(auto-generated if left blank)</span></label>
            <input type="text" name="slug" id="collection-slug" value="<?= htmlspecialchars($collection['slug'] ?? '') ?>">
        </div>

        <?php if ($isEdit): ?>
            <div class="form-row">
                <label>Thumbnail</label>
                <div style="display: flex; gap: 1rem; align-items: flex-start; flex-wrap: wrap;">
                    <?php if (!empty($collection['thumbnail_url'])): ?>
                        <img id="capture-preview-img" src="<?= e($collection['thumbnail_url']) ?>" alt="Collection Thumbnail" style="max-width: 240px; height: auto; border: 3px solid var(--line); box-shadow: 4px 4px 0 var(--line);">
                    <?php else: ?>
                        <div id="capture-preview-placeholder" style="width: 240px; height: 135px; border: 3px dashed var(--line); background: var(--paper); display: flex; align-items: center; justify-content: center; font-weight: 800; color: var(--ink-soft);">No Thumbnail</div>
                    <?php endif; ?>
                    <button type="button" id="btn-capture-thumbnail" data-collection-id="<?= (int) $collection['id'] ?>" data-collection-slug="<?= e($collection['slug'] ?? '') ?>" class="admin-btn admin-btn-sm" style="box-shadow: 3px 3px 0 var(--line);">Capture Thumbnail</button>
                </div>
            </div>
        <?php endif; ?>

        <div class="field-grid" style="display: flex; gap: 1rem;">
            <div class="form-row" style="flex: 1;">
                <label>Grid Rows</label>
                <input type="number" name="rows" min="1" max="20" value="<?= (int) ($collection['rows'] ?? 1) ?>">
            </div>
            <div class="form-row" style="flex: 1;">
                <label>Grid Columns</label>
                <input type="number" name="cols" min="1" max="20" value="<?= (int) ($collection['cols'] ?? 1) ?>">
            </div>
            <div class="form-row" style="flex: 1;">
                <label>Sort Order</label>
                <input type="number" name="sort_order" min="1" value="<?= (int) ($collection['sort_order'] ?? 0) + 1 ?>">
            </div>
        </div>

        <div class="form-row">
            <label>Status</label>
            <select name="status">
                <option value="active" <?= ($collection['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="draft" <?= ($collection['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="archived" <?= ($collection['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Archived</option>
            </select>
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

        <div class="form-row">
            <label class="checkbox-label">
                <input type="checkbox" name="comments_enabled" value="1"
                       <?= !empty($collection['comments_enabled']) ? 'checked' : '' ?>>
                Enable comments on this platform collection
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="admin-btn"><?= $isEdit ? 'Save Changes' : 'Create Collection' ?></button>
            <a href="/admin/platform-collections" class="admin-btn admin-btn-ghost">Cancel</a>
        </div>
    </form>
</div>

<script>
(function () {
    // 1. Manual capture button click
    var captureBtn = document.getElementById('btn-capture-thumbnail');
    if (captureBtn) {
        captureBtn.addEventListener('click', async function () {
            var colId = captureBtn.dataset.collectionId;
            var colSlug = captureBtn.dataset.collectionSlug;
            captureBtn.textContent = 'Capturing…';
            captureBtn.disabled = true;

            try {
                // Mount off-screen iframe
                var captureFrame = document.createElement('iframe');
                captureFrame.style.cssText = 'position:fixed;left:-9999px;top:-9999px;width:960px;height:540px;border:none;';
                captureFrame.sandbox = 'allow-scripts allow-same-origin';
                document.body.appendChild(captureFrame);

                // Load collection in immersive mode with closeup parameter enabled
                captureFrame.src = '/immersive/collections/' + colSlug + '?embed=1&closeup=1';

                await new Promise(function (resolve) {
                    captureFrame.onload = resolve;
                    setTimeout(resolve, 1500);
                });

                // Wait 4s for Three.js wall setup and progressive assets load
                await new Promise(function (resolve) { setTimeout(resolve, 4000); });

                var iframeDoc = captureFrame.contentDocument || captureFrame.contentWindow.document;
                var canvas = iframeDoc && iframeDoc.querySelector('canvas');

                if (!canvas) {
                    throw new Error('No canvas found inside iframe');
                }

                var imageData = canvas.toDataURL('image/png');
                document.body.removeChild(captureFrame);

                // Upload base64 capture
                var formData = new FormData();
                formData.append('image_data', imageData);

                var uploadResp = await fetch('/admin/platform-collections/' + colId + '/capture-thumbnail', {
                    method: 'POST',
                    body: formData
                });

                if (!uploadResp.ok) {
                    var err = await uploadResp.json();
                    throw new Error(err.error || 'Server error');
                }

                var res = await uploadResp.json();

                // Update hidden input
                var hiddenInput = document.getElementById('collection-thumbnail-url');
                if (hiddenInput) {
                    hiddenInput.value = res.url;
                }

                // Update preview image
                var previewImg = document.getElementById('capture-preview-img');
                if (!previewImg) {
                    var placeholder = document.getElementById('capture-preview-placeholder');
                    if (placeholder) {
                        previewImg = document.createElement('img');
                        previewImg.id = 'capture-preview-img';
                        previewImg.style.cssText = 'max-width:240px;height:auto;border:3px solid var(--line);box-shadow:4px 4px 0 var(--line);';
                        placeholder.parentNode.replaceChild(previewImg, placeholder);
                    }
                }
                if (previewImg) {
                    previewImg.src = res.url;
                }

                captureBtn.textContent = 'Captured!';
                setTimeout(function () { captureBtn.textContent = 'Capture Thumbnail'; }, 3000);
            } catch (err) {
                console.error('Thumbnail capture failed:', err);
                alert('Thumbnail capture failed: ' + err.message);
                captureBtn.textContent = 'Capture Thumbnail';
                if (captureFrame && captureFrame.parentNode) {
                    document.body.removeChild(captureFrame);
                }
            } finally {
                captureBtn.disabled = false;
            }
        });
    }

    // 2. Auto-capture on form submit if configuration changed
    var form = document.querySelector('form.admin-form');
    if (form && captureBtn) {
        var initialCheckboxes = Array.from(form.querySelectorAll('input[name="items[]"]')).map(function (cb) {
            return cb.checked + cb.value;
        }).join(',');

        var initialRows = form.querySelector('input[name="rows"]')?.value || '1';
        var initialCols = form.querySelector('input[name="cols"]')?.value || '1';

        form.addEventListener('submit', async function (event) {
            var currentCheckboxes = Array.from(form.querySelectorAll('input[name="items[]"]')).map(function (cb) {
                return cb.checked + cb.value;
            }).join(',');

            var currentRows = form.querySelector('input[name="rows"]')?.value || '1';
            var currentCols = form.querySelector('input[name="cols"]')?.value || '1';

            var isDirty = (initialCheckboxes !== currentCheckboxes) || (initialRows !== currentRows) || (initialCols !== currentCols);

            if (isDirty) {
                event.preventDefault();
                console.log('Collection configuration changed; auto-capturing thumbnail...');

                var submitBtn = form.querySelector('button[type="submit"]');
                var originalText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.textContent = 'Saving…';

                var captureFrame = null;

                try {
                    var action = form.getAttribute('action');
                    var formData = new FormData(form);

                    // Perform the save first via AJAX so database changes are committed
                    var saveResp = await fetch(action + (action.indexOf('?') !== -1 ? '&' : '?') + 'ajax=1', {
                        method: 'POST',
                        body: formData
                    });

                    if (!saveResp.ok) {
                        throw new Error('Save request failed with status ' + saveResp.status);
                    }

                    var saveResult = await saveResp.json();
                    var newSlug = saveResult.slug || colSlug;

                    // Database updated. Now execute thumbnail capture.
                    submitBtn.textContent = 'Updating thumbnail…';
                    
                    var colId = captureBtn.dataset.collectionId;
                    
                    captureFrame = document.createElement('iframe');
                    captureFrame.style.cssText = 'position:fixed;left:-9999px;top:-9999px;width:960px;height:540px;border:none;';
                    captureFrame.sandbox = 'allow-scripts allow-same-origin';
                    document.body.appendChild(captureFrame);

                    captureFrame.src = '/immersive/collections/' + newSlug + '?embed=1&closeup=1';

                    await new Promise(function (resolve) {
                        captureFrame.onload = resolve;
                        setTimeout(resolve, 1500);
                    });

                    await new Promise(function (resolve) { setTimeout(resolve, 4000); });

                    var iframeDoc = captureFrame.contentDocument || captureFrame.contentWindow.document;
                    var canvas = iframeDoc && iframeDoc.querySelector('canvas');

                    if (canvas) {
                        var imageData = canvas.toDataURL('image/png');
                        document.body.removeChild(captureFrame);
                        captureFrame = null;

                        var thumbFormData = new FormData();
                        thumbFormData.append('image_data', imageData);

                        var uploadResp = await fetch('/admin/platform-collections/' + colId + '/capture-thumbnail', {
                            method: 'POST',
                            body: thumbFormData
                        });

                        if (uploadResp.ok) {
                            var uploadResult = await uploadResp.json();
                            var hiddenInput = document.getElementById('collection-thumbnail-url');
                            if (hiddenInput) {
                                hiddenInput.value = uploadResult.url;
                            }
                        }
                    } else {
                        if (captureFrame) {
                            document.body.removeChild(captureFrame);
                            captureFrame = null;
                        }
                        console.warn('Auto capture failed: no canvas found inside iframe');
                    }
                } catch (err) {
                    console.error('Auto-capture save error:', err);
                    if (captureFrame && captureFrame.parentNode) {
                        document.body.removeChild(captureFrame);
                    }
                    if (!confirm('Auto thumbnail capture failed: ' + err.message + '\n\nDo you want to save the changes anyway without updating the thumbnail?')) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalText;
                        return;
                    }
                }

                // Redirect to platform collections index
                window.location.href = '/admin/platform-collections';
            }
        });
    }
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
