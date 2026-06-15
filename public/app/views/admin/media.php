<?php
$pageTitle = 'Media Library — Augment Humankind Admin';
$needsEditor = true;
ob_start();
?>
<div class="admin-section media-library-page">
    <div class="admin-section-head">
        <div>
            <h1 class="admin-heading">Media Library</h1>
            <p class="admin-hint media-library-intro">Select an asset to copy its URL or move it out of circulation.</p>
        </div>
        <div style="display:flex;gap:0.8rem;align-items:center">
            <span class="admin-hint"><?= count($files) ?> file<?= count($files) !== 1 ? 's' : '' ?></span>
            <button type="button" class="admin-btn" id="media-new-image-btn">+ New Asset</button>
        </div>
    </div>

    <div class="media-workspace">
        <section class="media-grid-panel">
            <div class="media-panel-head">
                <h2 class="admin-subheading">Library Grid</h2>
                <span class="admin-hint">Newest uploads appear first.</span>
            </div>

            <?php if (empty($files)): ?>
                <p class="admin-empty">No uploaded files yet.</p>
            <?php else: ?>
                <div class="media-grid">
                    <?php foreach ($files as $f): ?>
                        <button
                             type="button"
                             class="media-card"
                             data-id="<?= htmlspecialchars((string) $f['id']) ?>"
                             data-source="<?= htmlspecialchars($f['source']) ?>"
                             data-preview="<?= htmlspecialchars($f['preview']) ?>"
                             data-direct-url="<?= htmlspecialchars($f['direct_url']) ?>"
                             data-mime="<?= htmlspecialchars($f['mime_type'] ?? '') ?>"
                             data-date="<?= !empty($f['created_at']) ? date('Y-m-d', strtotime($f['created_at'])) : '' ?>"
                             data-size="<?= (int) ($f['byte_size'] ?? 0) ?>"
                             data-asset-id="<?= isset($f['asset_id']) ? (int) $f['asset_id'] : '' ?>"
                             data-title="<?= htmlspecialchars($f['title'] ?? '') ?>"
                             data-alt-text="<?= htmlspecialchars($f['alt_text'] ?? '') ?>"
                             aria-label="Select <?= htmlspecialchars($f['label']) ?>, <?= htmlspecialchars($f['mime_type'] ?? 'unknown type') ?>">
                            <span class="media-card-thumb">
                                <?php if (str_starts_with((string) ($f['mime_type'] ?? ''), 'video/')): ?>
                                    <video src="<?= htmlspecialchars($f['preview']) ?>" muted preload="metadata"></video>
                                <?php elseif (($f['mime_type'] ?? '') === 'text/html' || str_starts_with((string) ($f['mime_type'] ?? ''), 'iframe')): ?>
                                    <div class="media-thumb-iframe" style="display:flex;align-items:center;justify-content:center;height:100%;background:var(--paper);color:var(--orange);font-weight:bold;font-size:1.2rem;">&lt;/&gt; Embed</div>
                                <?php else: ?>
                                    <img src="<?= htmlspecialchars($f['preview']) ?>"
                                         alt=""
                                         loading="lazy"
                                         onerror="this.parentElement.classList.add('media-thumb-missing')">
                                <?php endif ?>
                            </span>
                            <span class="media-card-meta">
                                <span class="media-card-id"><?= htmlspecialchars($f['label']) ?></span>
                                <span class="media-card-type"><?= htmlspecialchars($f['mime_type'] ?? 'Unknown type') ?></span>
                                <span class="media-card-date"><?= !empty($f['created_at']) ? date('Y-m-d', strtotime($f['created_at'])) : '—' ?></span>
                            </span>
                        </button>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </section>

        <aside class="media-details-panel">
            <div class="media-panel-head">
                <h2 class="admin-subheading">Selected Asset</h2>
                <span class="admin-hint">Preview, copy, or remove.</span>
            </div>
            <div class="media-details-preview" id="details-preview-host">
                <img id="details-preview-img" class="is-hidden" src="" alt="">
            </div>

            <div class="media-details-placeholder" id="details-placeholder" aria-live="polite">
                Select an asset to view details.
            </div>

            <div class="media-details-content is-hidden" id="details-content-area">
                <div class="media-details-meta">
                    <div class="media-meta-row">
                        <span class="media-meta-label">ID</span>
                        <span class="media-meta-value" id="meta-id">—</span>
                    </div>
                    <div class="media-meta-row">
                        <span class="media-meta-label">Type</span>
                        <span class="media-meta-value" id="meta-mime">—</span>
                    </div>
                    <div class="media-meta-row">
                        <span class="media-meta-label">Uploaded</span>
                        <span class="media-meta-value" id="meta-date">—</span>
                    </div>
                    <div class="media-meta-row">
                        <span class="media-meta-label">Size</span>
                        <span class="media-meta-value" id="meta-size">—</span>
                    </div>
                </div>

                <div class="form-row media-details-code">
                    <label for="input-url">Direct URL</label>
                    <div class="media-code-input-wrap">
                        <input type="text" id="input-url" readonly>
                        <button type="button" class="admin-btn media-copy-btn" data-copy-target="input-url">Copy</button>
                    </div>
                </div>

                <div class="form-row media-details-code">
                    <label for="input-html">HTML Embed Code</label>
                    <div class="media-code-input-wrap">
                        <input type="text" id="input-html" readonly>
                        <button type="button" class="admin-btn media-copy-btn" data-copy-target="input-html">Copy</button>
                    </div>
                </div>

                <div class="form-row media-details-code" id="ai-alt-row" style="display: none; flex-direction: column; gap: 0.5rem;">
                    <label>AI Alt Text</label>
                    <div class="media-code-input-wrap">
                        <input type="number" id="ai-alt-profile" class="admin-input" placeholder="AI profile ID" style="flex: 1; min-width: 120px;">
                        <button type="button" class="admin-btn" id="ai-alt-btn">Generate</button>
                    </div>
                    <p class="admin-hint" id="ai-alt-status" aria-live="polite"></p>
                </div>

                <div class="media-details-actions" style="display: flex; flex-direction: column; gap: 1rem; margin-top: 1rem;">
                    <form id="action-asset-update-form" method="post" class="media-asset-meta-form is-hidden" style="display: flex; flex-direction: column; gap: 0.5rem; border: 1px solid var(--line); padding: 0.75rem; background: var(--paper);">
                        <div class="field" style="display: flex; flex-direction: column; gap: 0.25rem;">
                            <label for="asset-title-input" style="font-weight: bold; font-size: 0.85rem;">Title</label>
                            <input type="text" id="asset-title-input" name="title" maxlength="255" class="admin-input" style="width: 100%; box-sizing: border-box; padding: 0.25rem;">
                        </div>
                        <div class="field" style="display: flex; flex-direction: column; gap: 0.25rem;">
                            <label for="asset-alt-input" style="font-weight: bold; font-size: 0.85rem;">Alt Text</label>
                            <input type="text" id="asset-alt-input" name="alt_text" maxlength="500" class="admin-input" style="width: 100%; box-sizing: border-box; padding: 0.25rem;">
                        </div>
                        <button type="submit" class="admin-btn admin-btn-sm" style="align-self: flex-start;">Save metadata</button>
                    </form>

                    <div style="display: flex; gap: 0.5rem;">
                        <form method="POST" id="action-trash-form" style="display: inline;">
                            <button type="submit" class="admin-btn">Move to Trash</button>
                        </form>
                        <form method="POST" id="action-destroy-form" style="display: inline;">
                            <button type="submit" class="admin-btn-danger">Delete Now</button>
                        </form>
                    </div>
                    <p class="admin-hint is-hidden" id="media-readonly-note">Migrated asset — managed from Site Identity, read-only here.</p>
                </div>
            </div>

            <p class="admin-hint" id="media-copy-status" aria-live="polite"></p>
        </aside>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const cards = document.querySelectorAll('.media-card');
    const previewImg = document.getElementById('details-preview-img');
    const metaId = document.getElementById('meta-id');
    const metaMime = document.getElementById('meta-mime');
    const metaDate = document.getElementById('meta-date');
    const metaSize = document.getElementById('meta-size');
    const inputUrl = document.getElementById('input-url');
    const inputHtml = document.getElementById('input-html');
    const trashForm = document.getElementById('action-trash-form');
    const destroyForm = document.getElementById('action-destroy-form');
    const readonlyNote = document.getElementById('media-readonly-note');
    const assetMetaForm = document.getElementById('action-asset-update-form');
    const assetTitleInput = document.getElementById('asset-title-input');
    const assetAltInput = document.getElementById('asset-alt-input');
    const placeholderText = document.getElementById('details-placeholder');
    const contentArea = document.getElementById('details-content-area');
    const copyStatus = document.getElementById('media-copy-status');
    const previewHost = document.getElementById('details-preview-host');

    function formatBytes(bytes) {
        const value = Number(bytes || 0);
        if (!value) return '—';
        if (value < 1024) return `${value} B`;
        if (value < 1048576) return `${(value / 1024).toFixed(1)} KB`;
        return `${(value / 1048576).toFixed(2)} MB`;
    }

    function setPreview(card, assetUrl) {
        previewHost.querySelectorAll('video.dynamic-media-preview, iframe.dynamic-media-preview').forEach(node => node.remove());
        previewImg.classList.add('is-hidden');
        previewImg.removeAttribute('src');

        if ((card.dataset.mime || '').startsWith('video/')) {
            const video = document.createElement('video');
            video.className = 'dynamic-media-preview';
            video.src = assetUrl;
            video.controls = true;
            video.preload = 'metadata';
            previewHost.appendChild(video);
            return;
        }

        if ((card.dataset.mime || '') === 'text/html' || (card.dataset.mime || '').startsWith('iframe')) {
            const iframe = document.createElement('iframe');
            iframe.className = 'dynamic-media-preview';
            iframe.src = assetUrl;
            iframe.style.width = '100%';
            iframe.style.height = '200px';
            iframe.style.border = '0';
            previewHost.appendChild(iframe);
            return;
        }

        previewImg.src = card.dataset.preview;
        previewImg.alt = `Preview of ${card.dataset.id}`;
        previewImg.classList.remove('is-hidden');
    }

    function selectCard(card) {
        cards.forEach(item => item.classList.remove('active'));
        card.classList.add('active');

        const id = card.dataset.id;
        const mime = card.dataset.mime;
        const date = card.dataset.date;
        const source = card.dataset.source;
        const assetUrl = card.dataset.directUrl;
        setPreview(card, assetUrl);

        metaId.textContent = id;
        metaMime.textContent = mime;
        metaDate.textContent = date;
        metaSize.textContent = formatBytes(card.dataset.size);

        inputUrl.value = window.location.origin + assetUrl;
        if (mime.startsWith('video/')) {
            inputHtml.value = `<video src="${assetUrl}" controls preload="metadata"></video>`;
        } else if (mime === 'text/html' || mime.startsWith('iframe')) {
            inputHtml.value = `<iframe src="${assetUrl}" width="100%" height="480" frameborder="0" allowfullscreen></iframe>`;
        } else {
            inputHtml.value = `<img src="${card.dataset.preview}" alt="">`;
        }

        if (source === 'asset') {
            var assetId = card.dataset.assetId;
            assetMetaForm.classList.remove('is-hidden');
            assetMetaForm.action = `/admin/media/asset/${assetId}/update`;
            assetTitleInput.value = card.dataset.title || '';
            assetAltInput.value = card.dataset.altText || '';
            if (readonlyNote) readonlyNote.classList.add('is-hidden');
            trashForm.classList.remove('is-hidden');
            destroyForm.classList.remove('is-hidden');
            trashForm.action = `/admin/media/asset/${assetId}/trash`;
            destroyForm.action = `/admin/media/asset/${assetId}/destroy`;
            destroyForm.dataset.confirmExtra = ' This asset may be referenced by site settings, posts, or art pieces — broken links won\'t be auto-fixed.';
        } else {
            assetMetaForm.classList.add('is-hidden');
            if (readonlyNote) readonlyNote.classList.add('is-hidden');
            trashForm.classList.remove('is-hidden');
            destroyForm.classList.remove('is-hidden');
            trashForm.action = `/admin/media/${id}/trash`;
            destroyForm.action = `/admin/media/${id}/destroy`;
            destroyForm.dataset.confirmExtra = '';
        }

        // Show AI alt text row for images
        if (aiAltRow) {
            if (mime.startsWith('image/')) {
                aiAltRow.style.display = 'flex';
            } else {
                aiAltRow.style.display = 'none';
            }
        }

        placeholderText.classList.add('is-hidden');
        contentArea.classList.remove('is-hidden');
    }

    cards.forEach(card => {
        card.addEventListener('click', () => {
            selectCard(card);
        });
    });

    if (cards.length > 0) {
        selectCard(cards[0]);
    } else {
        placeholderText.textContent = 'No assets in library.';
    }

    trashForm.addEventListener('submit', (e) => {
        if (!confirm('Move this asset to the recycle bin?')) {
            e.preventDefault();
        }
    });

    destroyForm.addEventListener('submit', (e) => {
        var msg = 'Permanently delete this asset? This cannot be undone.' + (destroyForm.dataset.confirmExtra || '');
        if (!confirm(msg)) {
            e.preventDefault();
        }
    });

    // AI Alt Text generation
    const aiAltRow = document.getElementById('ai-alt-row');
    const aiAltProfile = document.getElementById('ai-alt-profile');
    const aiAltBtn = document.getElementById('ai-alt-btn');
    const aiAltStatus = document.getElementById('ai-alt-status');

    if (aiAltBtn) {
        aiAltBtn.addEventListener('click', async () => {
            const profileId = aiAltProfile.value;
            if (!profileId) {
                aiAltStatus.textContent = 'Please select an AI profile.';
                return;
            }
            const activeCard = document.querySelector('.media-card.active');
            if (!activeCard) {
                aiAltStatus.textContent = 'Select an image first.';
                return;
            }
            const assetUrl = activeCard.dataset.directUrl;
            aiAltBtn.disabled = true;
            aiAltStatus.textContent = 'Generating alt text...';
            try {
                const fd = new FormData();
                fd.append('profile_id', profileId);
                fd.append('image_url', assetUrl);
                const res = await fetch('/admin/ai/describe-image', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.result) {
                    assetAltInput.value = data.result;
                    aiAltStatus.textContent = 'Alt text generated. Click "Save metadata" to store it.';
                } else {
                    aiAltStatus.textContent = 'Error: ' + (data.error || 'Unknown error');
                }
            } catch (e) {
                aiAltStatus.textContent = 'Error: ' + e.message;
            } finally {
                aiAltBtn.disabled = false;
            }
        });
    }

    // AI alt text row is now toggled inside selectCard


    document.querySelectorAll('.media-copy-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.dataset.copyTarget;
            const input = document.getElementById(targetId);
            const originalText = btn.textContent;

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(input.value).catch(() => {
                    input.select();
                    document.execCommand('copy');
                });
            } else {
                input.select();
                document.execCommand('copy');
            }

            btn.textContent = 'Copied!';
            copyStatus.textContent = `${targetId === 'input-url' ? 'Direct URL' : 'HTML embed code'} copied.`;
            setTimeout(() => {
                btn.textContent = originalText;
                copyStatus.textContent = '';
            }, 1200);
        });
    });

    const newImageBtn = document.getElementById('media-new-image-btn');
    if (newImageBtn) {
        newImageBtn.addEventListener('click', () => {
            if (window.openMediaPicker) window.openMediaPicker(null, 'upload', { mode: 'media' });
        });
    }
});
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
