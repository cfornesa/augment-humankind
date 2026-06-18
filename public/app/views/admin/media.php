<?php

declare(strict_types=1);

$pageTitle = 'Media Library — Augment Humankind Admin';
$needsEditor = true;
ob_start();
?>
<div class="admin-section media-library-page">
    <div class="admin-section-head">
        <div>
            <h1 class="admin-heading">Media Library</h1>
            <p class="admin-hint media-library-intro">Select an asset to open a larger editor with preview, title, description, and AI-assisted alt text.</p>
        </div>
        <div style="display:flex;gap:0.8rem;align-items:center">
            <span class="admin-hint"><?= count($files) ?> file<?= count($files) !== 1 ? 's' : '' ?></span>
            <button type="button" class="admin-btn" id="media-new-image-btn">+ New Asset</button>
        </div>
    </div>

    <?php if (!$nativeAltTextSupported): ?>
        <div class="form-status" role="status" style="margin-bottom: 1rem;">
            <p>Native uploaded files are usable, but their description and alt text cannot be stored until `docs/migrations/2026-06-18-ai-personas.sql` adds `media_files.alt_text`.</p>
        </div>
    <?php elseif ($warning === 'native-alt-text-unavailable' || $warning === 'native-media-metadata-unavailable'): ?>
        <div class="form-status" role="status" style="margin-bottom: 1rem;">
            <p>Native media metadata could not be saved because the required `media_files` metadata columns are still missing.</p>
        </div>
    <?php endif; ?>

    <section class="media-grid-panel">
        <div class="media-panel-head">
            <h2 class="admin-subheading">Library Grid</h2>
            <span class="admin-hint">Tap or click any asset to open the full editor.</span>
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
                        data-source="<?= htmlspecialchars((string) $f['source']) ?>"
                        data-preview="<?= htmlspecialchars((string) $f['preview']) ?>"
                        data-direct-url="<?= htmlspecialchars((string) $f['direct_url']) ?>"
                        data-mime="<?= htmlspecialchars((string) ($f['mime_type'] ?? '')) ?>"
                        data-date="<?= !empty($f['created_at']) ? date('Y-m-d', strtotime((string) $f['created_at'])) : '' ?>"
                        data-size="<?= (int) ($f['byte_size'] ?? 0) ?>"
                        data-asset-id="<?= isset($f['asset_id']) ? (int) $f['asset_id'] : '' ?>"
                        data-title="<?= htmlspecialchars((string) ($f['title'] ?? '')) ?>"
                        data-original-name="<?= htmlspecialchars((string) ($f['original_name'] ?? '')) ?>"
                        data-alt-text="<?= htmlspecialchars((string) ($f['alt_text'] ?? '')) ?>"
                        data-alt-supported="<?= !empty($f['alt_text_supported']) ? '1' : '0' ?>"
                        data-title-supported="<?= !empty($f['title_supported']) ? '1' : '0' ?>"
                        aria-label="Open <?= htmlspecialchars((string) $f['label']) ?> details">
                        <span class="media-card-thumb">
                            <?php if (str_starts_with((string) ($f['mime_type'] ?? ''), 'video/')): ?>
                                <video src="<?= htmlspecialchars((string) $f['preview']) ?>" muted preload="metadata"></video>
                            <?php elseif (($f['mime_type'] ?? '') === 'text/html' || str_starts_with((string) ($f['mime_type'] ?? ''), 'iframe')): ?>
                                <span class="media-thumb-iframe">&lt;/&gt; Embed</span>
                            <?php else: ?>
                                <img
                                    src="<?= htmlspecialchars((string) $f['preview']) ?>"
                                    alt=""
                                    loading="lazy"
                                    onerror="this.parentElement.classList.add('media-thumb-missing')"
                                >
                            <?php endif ?>
                        </span>
                        <span class="media-card-meta">
                            <span class="media-card-id"><?= htmlspecialchars((string) $f['label']) ?></span>
                            <span class="media-card-type"><?= htmlspecialchars((string) ($f['mime_type'] ?? 'Unknown type')) ?></span>
                            <span class="media-card-date"><?= !empty($f['created_at']) ? date('Y-m-d', strtotime((string) $f['created_at'])) : '—' ?></span>
                        </span>
                    </button>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </section>

    <dialog id="media-asset-modal" aria-labelledby="media-asset-modal-title">
        <form id="media-asset-form" method="post" class="media-asset-modal-shell">
            <div class="media-picker-header media-asset-modal-header">
                <div>
                    <h2 id="media-asset-modal-title">Selected Asset</h2>
                    <p class="admin-hint" id="media-asset-modal-subtitle" style="margin:0.35rem 0 0;">Preview and edit this asset.</p>
                </div>
                <button type="button" class="media-picker-close" id="media-asset-close-btn" aria-label="Close">&times;</button>
            </div>

            <div class="media-asset-modal-body">
                <div class="media-asset-preview-panel">
                    <div class="media-asset-preview-frame" id="media-asset-preview-host">
                        <img id="media-asset-preview-img" class="is-hidden" src="" alt="">
                    </div>

                    <dl class="media-asset-facts">
                        <div>
                            <dt>ID</dt>
                            <dd id="media-asset-meta-id">—</dd>
                        </div>
                        <div>
                            <dt>Type</dt>
                            <dd id="media-asset-meta-mime">—</dd>
                        </div>
                        <div>
                            <dt>Uploaded</dt>
                            <dd id="media-asset-meta-date">—</dd>
                        </div>
                        <div>
                            <dt>Size</dt>
                            <dd id="media-asset-meta-size">—</dd>
                        </div>
                    </dl>

                    <div class="field">
                        <label for="media-asset-url">Direct URL</label>
                        <div class="media-code-input-wrap">
                            <input type="text" id="media-asset-url" readonly>
                            <button type="button" class="admin-btn media-copy-btn" id="media-asset-copy-btn">Copy</button>
                        </div>
                    </div>
                </div>

                <div class="media-asset-edit-panel">
                    <div class="field">
                        <label for="media-asset-title-input">Title</label>
                        <input type="text" id="media-asset-title-input" name="title" maxlength="255" class="admin-input">
                    </div>

                    <div class="field">
                        <label for="media-asset-description-input">Description / Alt Text</label>
                        <p class="admin-hint" style="margin:0 0 0.5rem;">This description is stored as the default alt text when the image is inserted elsewhere.</p>
                        <div class="media-asset-description-row">
                            <textarea
                                id="media-asset-description-input"
                                name="alt_text"
                                rows="7"
                                maxlength="500"
                                class="media-picker-textarea"
                                placeholder="Describe this image for screen readers and future reuse."
                            ></textarea>
                            <button
                                type="button"
                                id="media-asset-ai-btn"
                                class="admin-btn admin-btn-ghost admin-btn-sm media-asset-ai-btn"
                                title="Generate description with AI"
                                aria-label="Generate description with AI"
                            >✨</button>
                        </div>
                    </div>

                    <p class="admin-hint is-hidden" id="media-asset-schema-note">This native upload needs the latest media metadata migration before title or description can be saved.</p>
                    <p class="admin-hint" id="media-asset-ai-status" aria-live="polite"></p>
                </div>
            </div>

            <div class="media-picker-footer media-asset-modal-footer">
                <div class="media-asset-footer-actions">
                    <button type="button" class="admin-btn admin-btn-ghost" id="media-asset-trash-btn">Move to Trash</button>
                    <button type="button" class="admin-btn-danger" id="media-asset-delete-btn">Delete Now</button>
                </div>
                <div class="media-asset-footer-actions">
                    <button type="button" class="admin-btn admin-btn-ghost" id="media-asset-cancel-btn">Cancel</button>
                    <button type="submit" class="admin-btn" id="media-asset-save-btn">Save</button>
                </div>
            </div>
        </form>
    </dialog>

    <form method="POST" id="media-asset-trash-form" class="is-hidden"></form>
    <form method="POST" id="media-asset-delete-form" class="is-hidden"></form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const cards = Array.from(document.querySelectorAll('.media-card'));
    const modal = document.getElementById('media-asset-modal');
    const modalForm = document.getElementById('media-asset-form');
    const closeBtn = document.getElementById('media-asset-close-btn');
    const cancelBtn = document.getElementById('media-asset-cancel-btn');
    const saveBtn = document.getElementById('media-asset-save-btn');
    const trashBtn = document.getElementById('media-asset-trash-btn');
    const deleteBtn = document.getElementById('media-asset-delete-btn');
    const trashForm = document.getElementById('media-asset-trash-form');
    const deleteForm = document.getElementById('media-asset-delete-form');
    const copyBtn = document.getElementById('media-asset-copy-btn');
    const previewHost = document.getElementById('media-asset-preview-host');
    const previewImg = document.getElementById('media-asset-preview-img');
    const titleInput = document.getElementById('media-asset-title-input');
    const descriptionInput = document.getElementById('media-asset-description-input');
    const schemaNote = document.getElementById('media-asset-schema-note');
    const aiStatus = document.getElementById('media-asset-ai-status');
    const aiBtn = document.getElementById('media-asset-ai-btn');
    const subtitle = document.getElementById('media-asset-modal-subtitle');
    const directUrlInput = document.getElementById('media-asset-url');
    const metaId = document.getElementById('media-asset-meta-id');
    const metaMime = document.getElementById('media-asset-meta-mime');
    const metaDate = document.getElementById('media-asset-meta-date');
    const metaSize = document.getElementById('media-asset-meta-size');
    let activeCard = null;

    function formatBytes(bytes) {
        const value = Number(bytes || 0);
        if (!value) return '—';
        if (value < 1024) return `${value} B`;
        if (value < 1048576) return `${(value / 1024).toFixed(1)} KB`;
        return `${(value / 1048576).toFixed(2)} MB`;
    }

    function clearPreview() {
        previewHost.querySelectorAll('video.dynamic-media-preview, iframe.dynamic-media-preview').forEach(node => node.remove());
        previewImg.classList.add('is-hidden');
        previewImg.removeAttribute('src');
        previewImg.alt = '';
    }

    function setPreview(card) {
        clearPreview();
        const mime = card.dataset.mime || '';
        const assetUrl = card.dataset.directUrl || '';

        if (mime.startsWith('video/')) {
            const video = document.createElement('video');
            video.className = 'dynamic-media-preview';
            video.src = assetUrl;
            video.controls = true;
            video.preload = 'metadata';
            previewHost.appendChild(video);
            return;
        }

        if (mime === 'text/html' || mime.startsWith('iframe')) {
            const iframe = document.createElement('iframe');
            iframe.className = 'dynamic-media-preview';
            iframe.src = assetUrl;
            iframe.style.width = '100%';
            iframe.style.height = '100%';
            iframe.style.border = '0';
            previewHost.appendChild(iframe);
            return;
        }

        previewImg.src = card.dataset.preview || assetUrl;
        previewImg.alt = descriptionInput.value.trim() || titleInput.value.trim() || 'Media preview';
        previewImg.classList.remove('is-hidden');
    }

    function populateModal(card) {
        activeCard = card;
        cards.forEach(item => item.classList.toggle('active', item === card));

        const isAsset = card.dataset.source === 'asset';
        const rawId = card.dataset.id || '';
        const assetId = card.dataset.assetId || '';
        const titleSupported = isAsset || card.dataset.titleSupported === '1';
        const altSupported = isAsset || card.dataset.altSupported === '1';
        const canSaveMetadata = titleSupported || altSupported;
        const label = (card.dataset.title || '').trim() || (card.dataset.originalName || '').trim() || rawId;
        const assetUrl = card.dataset.directUrl || '';

        modalForm.action = isAsset ? `/admin/media/asset/${assetId}/update` : `/admin/media/${rawId}/update`;
        trashForm.action = isAsset ? `/admin/media/asset/${assetId}/trash` : `/admin/media/${rawId}/trash`;
        deleteForm.action = isAsset ? `/admin/media/asset/${assetId}/destroy` : `/admin/media/${rawId}/destroy`;

        metaId.textContent = rawId;
        metaMime.textContent = card.dataset.mime || '—';
        metaDate.textContent = card.dataset.date || '—';
        metaSize.textContent = formatBytes(card.dataset.size || 0);
        directUrlInput.value = assetUrl ? window.location.origin + assetUrl : '';

        titleInput.value = card.dataset.title || card.dataset.originalName || '';
        descriptionInput.value = card.dataset.altText || '';
        titleInput.disabled = !titleSupported;
        descriptionInput.disabled = !altSupported;
        saveBtn.disabled = !canSaveMetadata;
        aiBtn.disabled = !(card.dataset.mime || '').startsWith('image/');
        schemaNote.classList.toggle('is-hidden', canSaveMetadata);
        aiStatus.textContent = '';

        document.getElementById('media-asset-modal-title').textContent = label || 'Selected Asset';
        subtitle.textContent = isAsset
            ? 'Preview, copy, and edit this library asset.'
            : 'Preview, copy, and edit this native upload.';

        setPreview(card);
    }

    cards.forEach(card => {
        card.addEventListener('click', () => {
            populateModal(card);
            modal.showModal();
        });
    });

    copyBtn.addEventListener('click', async () => {
        if (!directUrlInput.value) return;
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(directUrlInput.value);
            } else {
                directUrlInput.select();
                document.execCommand('copy');
            }
            aiStatus.textContent = 'Direct URL copied.';
        } catch (error) {
            aiStatus.textContent = 'Could not copy the URL automatically.';
        }
    });

    if (aiBtn) {
        aiBtn.addEventListener('click', async () => {
            if (!activeCard || !(activeCard.dataset.mime || '').startsWith('image/')) {
                aiStatus.textContent = 'Select an image asset first.';
                return;
            }

            window.openAiProfilePicker(async selection => {
                if (!selection?.profileId) return;
                aiBtn.disabled = true;
                aiStatus.textContent = 'Generating description...';
                try {
                    const fd = new FormData();
                    fd.append('profile_id', selection.profileId);
                    if (selection.personaId) fd.append('persona_id', selection.personaId);
                    fd.append('image_url', activeCard.dataset.directUrl || '');
                    if (descriptionInput.value.trim()) fd.append('existing_alt_text', descriptionInput.value.trim());
                    const res = await fetch('/admin/ai/describe-image', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (data.result) {
                        descriptionInput.value = data.result;
                        aiStatus.textContent = 'Description generated. Save to store it.';
                        if (!previewImg.classList.contains('is-hidden')) {
                            previewImg.alt = data.result;
                        }
                    } else {
                        aiStatus.textContent = data.error || 'AI description failed.';
                    }
                } catch (error) {
                    aiStatus.textContent = 'AI description failed.';
                } finally {
                    aiBtn.disabled = false;
                }
            }, {
                capability: 'vision',
                title: 'Generate Image Description',
                taskKey: 'alt-text',
                hint: 'Choose a vision-capable AI profile and an optional persona to shape the description while keeping it accessible.'
            });
        });
    }

    descriptionInput.addEventListener('input', () => {
        if (!previewImg.classList.contains('is-hidden')) {
            previewImg.alt = descriptionInput.value.trim() || titleInput.value.trim() || 'Media preview';
        }
    });

    titleInput.addEventListener('input', () => {
        if (!previewImg.classList.contains('is-hidden') && !descriptionInput.value.trim()) {
            previewImg.alt = titleInput.value.trim() || 'Media preview';
        }
    });

    trashBtn.addEventListener('click', () => {
        if (!activeCard) return;
        if (confirm('Move this asset to the recycle bin?')) {
            trashForm.submit();
        }
    });

    deleteBtn.addEventListener('click', () => {
        if (!activeCard) return;
        const isAsset = activeCard.dataset.source === 'asset';
        const extra = isAsset ? ' This asset may be referenced elsewhere and broken links will not be auto-fixed.' : '';
        if (confirm('Permanently delete this asset? This cannot be undone.' + extra)) {
            deleteForm.submit();
        }
    });

    function closeModal() {
        modal.close();
    }

    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', event => {
        if (event.target === modal) {
            closeModal();
        }
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
