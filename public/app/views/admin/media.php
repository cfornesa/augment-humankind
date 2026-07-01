<?php

declare(strict_types=1);

$pageTitle = 'Media Library — ' . app_site_name() . ' Admin';
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
            <p>Native uploaded files are usable, but their description and alt text cannot be stored until the latest media metadata migration adds `media_files.alt_text`.</p>
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

        <?php if (!empty($files)): ?>
            <div class="admin-tabs media-type-filter" role="group" aria-label="Filter media by type">
                <button type="button" class="admin-tab active" data-media-filter="all">All</button>
                <button type="button" class="admin-tab" data-media-filter="image">Images</button>
                <button type="button" class="admin-tab" data-media-filter="video">Videos</button>
                <button type="button" class="admin-tab" data-media-filter="embed">Embeds</button>
            </div>
        <?php endif ?>

        <?php if (empty($files)): ?>
            <p class="admin-empty">No uploaded files yet.</p>
        <?php else: ?>
            <div class="media-grid">
                <?php foreach ($files as $f): ?>
                    <?php
                        $cardMime = (string) ($f['mime_type'] ?? '');
                        $cardKind = str_starts_with($cardMime, 'video/')
                            ? 'video'
                            : ((($cardMime === 'text/html') || str_starts_with($cardMime, 'iframe')) ? 'embed' : 'image');
                    ?>
                    <button
                        type="button"
                        class="media-card"
                        data-kind="<?= htmlspecialchars($cardKind) ?>"
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
                        data-status="<?= htmlspecialchars((string) ($f['status'] ?? 'ready')) ?>"
                        data-poster-media-id="<?= htmlspecialchars((string) ($f['poster_media_file_id'] ?? '')) ?>"
                        data-poster-url="<?= htmlspecialchars((string) ($f['poster_url'] ?? '')) ?>"
                        aria-label="Open <?= htmlspecialchars((string) $f['label']) ?> details">
                        <span class="media-card-thumb">
                            <?php if (($f['status'] ?? 'ready') === 'draft'): ?>
                                <span class="media-card-badge">Draft</span>
                            <?php endif ?>
                            <?php if (str_starts_with((string) ($f['mime_type'] ?? ''), 'video/')): ?>
                                <?php if (!empty($f['poster_url'])): ?>
                                    <img
                                        src="<?= htmlspecialchars((string) $f['poster_url']) ?>"
                                        alt=""
                                        loading="lazy"
                                        onerror="this.parentElement.classList.add('media-thumb-missing')"
                                    >
                                <?php else: ?>
                                    <span class="media-thumb-video-empty">No poster</span>
                                <?php endif ?>
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
                            <dt>Status</dt>
                            <dd id="media-asset-meta-status">—</dd>
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
                        <label for="media-asset-description-input" id="media-asset-description-label">Description / Alt Text</label>
                        <p class="admin-hint" style="margin:0 0 0.5rem;" id="media-asset-description-hint">This description is stored as the default alt text when the image is inserted elsewhere.</p>
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

                    <div class="field is-hidden" id="media-asset-poster-field">
                        <label>Video Poster Image <span class="form-hint">(optional)</span></label>
                        <div class="media-code-input-wrap">
                            <input type="text" id="media-asset-poster-url" readonly placeholder="No poster selected">
                            <button type="button" class="admin-btn admin-btn-ghost" id="media-asset-poster-choose-btn">Choose Poster</button>
                        </div>
                        <div class="media-picker-panel-actions">
                            <button type="button" class="admin-btn admin-btn-ghost" id="media-asset-poster-clear-btn">Clear Poster</button>
                        </div>
                        <p class="media-picker-status" id="media-asset-poster-status" aria-live="polite"></p>
                    </div>

                    <p class="admin-hint is-hidden" id="media-asset-schema-note">This native upload needs the latest media metadata migration before title or description can be saved.</p>
                    <p class="admin-hint is-hidden" id="media-asset-draft-note">This asset is still a draft. Save the metadata successfully before it becomes reusable elsewhere.</p>
                    <p class="admin-hint" id="media-asset-ai-status" aria-live="polite"></p>
                </div>
            </div>

            <div class="media-picker-footer media-asset-modal-footer">
                <div class="media-asset-footer-actions">
                    <button type="button" class="admin-btn admin-btn-ghost is-hidden" id="media-asset-discard-btn">Discard Draft</button>
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
    const filterBtns = Array.from(document.querySelectorAll('[data-media-filter]'));
    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const filter = btn.dataset.mediaFilter;
            filterBtns.forEach(b => b.classList.toggle('active', b === btn));
            cards.forEach(card => {
                card.classList.toggle('is-hidden', filter !== 'all' && card.dataset.kind !== filter);
            });
        });
    });

    const modal = document.getElementById('media-asset-modal');
    const modalForm = document.getElementById('media-asset-form');
    const closeBtn = document.getElementById('media-asset-close-btn');
    const cancelBtn = document.getElementById('media-asset-cancel-btn');
    const saveBtn = document.getElementById('media-asset-save-btn');
    const trashBtn = document.getElementById('media-asset-trash-btn');
    const deleteBtn = document.getElementById('media-asset-delete-btn');
    const discardBtn = document.getElementById('media-asset-discard-btn');
    const trashForm = document.getElementById('media-asset-trash-form');
    const deleteForm = document.getElementById('media-asset-delete-form');
    const copyBtn = document.getElementById('media-asset-copy-btn');
    const previewHost = document.getElementById('media-asset-preview-host');
    const previewImg = document.getElementById('media-asset-preview-img');
    const titleInput = document.getElementById('media-asset-title-input');
    const descriptionInput = document.getElementById('media-asset-description-input');
    const descriptionLabel = document.getElementById('media-asset-description-label');
    const descriptionHint = document.getElementById('media-asset-description-hint');
    const schemaNote = document.getElementById('media-asset-schema-note');
    const draftNote = document.getElementById('media-asset-draft-note');
    const aiStatus = document.getElementById('media-asset-ai-status');
    const aiBtn = document.getElementById('media-asset-ai-btn');
    const subtitle = document.getElementById('media-asset-modal-subtitle');
    const directUrlInput = document.getElementById('media-asset-url');
    const metaId = document.getElementById('media-asset-meta-id');
    const metaMime = document.getElementById('media-asset-meta-mime');
    const metaStatus = document.getElementById('media-asset-meta-status');
    const metaDate = document.getElementById('media-asset-meta-date');
    const metaSize = document.getElementById('media-asset-meta-size');
    const posterField = document.getElementById('media-asset-poster-field');
    const posterUrlInput = document.getElementById('media-asset-poster-url');
    const posterChooseBtn = document.getElementById('media-asset-poster-choose-btn');
    const posterClearBtn = document.getElementById('media-asset-poster-clear-btn');
    const posterStatus = document.getElementById('media-asset-poster-status');
    let activeCard = null;

    function formatBytes(bytes) {
        const value = Number(bytes || 0);
        if (!value) return '—';
        if (value < 1024) return `${value} B`;
        if (value < 1048576) return `${(value / 1024).toFixed(1)} KB`;
        return `${(value / 1048576).toFixed(2)} MB`;
    }

    function clearPreview() {
        previewHost.querySelectorAll('video.dynamic-media-preview, iframe.dynamic-media-preview, .media-thumb-video-empty').forEach(node => node.remove());
        previewImg.classList.add('is-hidden');
        previewImg.removeAttribute('src');
        previewImg.alt = '';
    }

    function createBlankVideoThumb() {
        const empty = document.createElement('span');
        empty.className = 'media-thumb-video-empty';
        empty.textContent = 'No poster';
        return empty;
    }

    function updatePosterField(card) {
        const isNativeVideo = card?.dataset.source !== 'asset' && (card?.dataset.mime || '').startsWith('video/');
        posterField.classList.toggle('is-hidden', !isNativeVideo);
        if (!isNativeVideo) {
            posterUrlInput.value = '';
            posterStatus.textContent = '';
            return;
        }
        posterUrlInput.value = card.dataset.posterUrl || '';
        posterStatus.textContent = '';
    }

    function updateCardThumb(card) {
        if (!card) return;
        const thumb = card.querySelector('.media-card-thumb');
        if (!thumb) return;
        thumb.querySelectorAll('img, video, .media-thumb-iframe, .media-thumb-video-empty').forEach(node => node.remove());
        thumb.classList.remove('media-thumb-missing');

        const mime = card.dataset.mime || '';
        if ((card.dataset.status || 'ready') === 'draft' && !thumb.querySelector('.media-card-badge')) {
            const badge = document.createElement('span');
            badge.className = 'media-card-badge';
            badge.textContent = 'Draft';
            thumb.appendChild(badge);
        }
        if ((card.dataset.status || 'ready') !== 'draft') {
            thumb.querySelector('.media-card-badge')?.remove();
        }

        if (mime.startsWith('video/')) {
            if (card.dataset.posterUrl) {
                const img = document.createElement('img');
                img.src = card.dataset.posterUrl;
                img.alt = '';
                img.loading = 'lazy';
                img.onerror = () => thumb.classList.add('media-thumb-missing');
                thumb.appendChild(img);
            } else {
                thumb.appendChild(createBlankVideoThumb());
            }
            return;
        }

        if (mime === 'text/html' || mime.startsWith('iframe')) {
            const label = document.createElement('span');
            label.className = 'media-thumb-iframe';
            label.textContent = '</> Embed';
            thumb.appendChild(label);
            return;
        }

        const img = document.createElement('img');
        img.src = card.dataset.preview || card.dataset.directUrl || '';
        img.alt = '';
        img.loading = 'lazy';
        img.onerror = () => thumb.classList.add('media-thumb-missing');
        thumb.appendChild(img);
    }

    function updateCardFromAsset(card, asset) {
        if (!card || !asset) return;
        const label = (asset.title || asset.original_name || '').trim() || `Asset #${asset.id}`;
        const url = asset.legacy_url || asset.url || card.dataset.directUrl || '';
        card.dataset.id = String(asset.id ?? card.dataset.id ?? '');
        card.dataset.preview = asset.kind === 'image' ? (asset.legacy_url || asset.url || '') : (asset.poster_url || '');
        card.dataset.directUrl = asset.url || card.dataset.directUrl || '';
        card.dataset.mime = asset.mime_type || card.dataset.mime || '';
        card.dataset.title = asset.title || '';
        card.dataset.altText = asset.alt_text || '';
        card.dataset.status = asset.status || 'ready';
        card.dataset.posterMediaId = asset.poster_media_file_id ? String(asset.poster_media_file_id) : '';
        card.dataset.posterUrl = asset.poster_url || '';
        card.dataset.kind = asset.kind || card.dataset.kind || '';
        card.setAttribute('aria-label', `Open ${label} details`);

        const idNode = card.querySelector('.media-card-id');
        const typeNode = card.querySelector('.media-card-type');
        if (idNode) idNode.textContent = label;
        if (typeNode) typeNode.textContent = asset.mime_type || 'Unknown type';
        updateCardThumb(card);
    }

    async function persistActiveAsset(message = 'Metadata saved.') {
        if (!activeCard) return false;
        const isAsset = activeCard.dataset.source === 'asset';
        const isDraft = (activeCard.dataset.status || 'ready') === 'draft';
        const fd = new FormData();
        fd.append('ajax', '1');
        fd.append('title', titleInput.value.trim());
        fd.append('alt_text', descriptionInput.value.trim());
        if (activeCard.dataset.source !== 'asset' && (activeCard.dataset.mime || '').startsWith('video/')) {
            fd.append('poster_media_file_id', activeCard.dataset.posterMediaId || '');
        }

        const endpoint = isAsset
            ? `/admin/media/asset/${activeCard.dataset.assetId}/update`
            : (isDraft ? `/admin/media/${activeCard.dataset.id}/confirm` : `/admin/media/${activeCard.dataset.id}/update`);

        saveBtn.disabled = true;
        try {
            const res = await fetch(endpoint, {
                method: 'POST',
                body: fd,
                headers: { Accept: 'application/json' },
            });
            const data = await res.json();
            if (!data.ok || !data.asset) {
                aiStatus.textContent = data.error || 'Could not save this asset.';
                return false;
            }
            updateCardFromAsset(activeCard, data.asset);
            populateModal(activeCard);
            aiStatus.textContent = message;
            return true;
        } catch (error) {
            aiStatus.textContent = 'Could not save this asset.';
            return false;
        } finally {
            saveBtn.disabled = false;
        }
    }

    function updateAiBtnState() {
        if (!activeCard) { aiBtn.disabled = true; return; }
        const mime = activeCard.dataset.mime || '';
        if (mime.startsWith('image/')) {
            aiBtn.disabled = false;
        } else if (mime.startsWith('video/')) {
            // Refine-only: AI cannot watch the video, so it needs existing text to improve.
            aiBtn.disabled = descriptionInput.value.trim() === '';
        } else {
            aiBtn.disabled = true;
        }
    }

    function setPreview(card) {
        clearPreview();
        const mime = card.dataset.mime || '';
        const assetUrl = card.dataset.directUrl || '';
        const posterUrl = card.dataset.posterUrl || '';

        if (mime.startsWith('video/')) {
            const video = document.createElement('video');
            video.className = 'dynamic-media-preview';
            video.src = assetUrl;
            video.controls = true;
            video.preload = 'metadata';
            if (posterUrl) video.poster = posterUrl;
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
        const status = card.dataset.status || 'ready';
        const isDraft = status === 'draft';

        modalForm.action = isAsset
            ? `/admin/media/asset/${assetId}/update`
            : (isDraft ? `/admin/media/${rawId}/confirm` : `/admin/media/${rawId}/update`);
        trashForm.action = isAsset ? `/admin/media/asset/${assetId}/trash` : `/admin/media/${rawId}/trash`;
        deleteForm.action = isAsset ? `/admin/media/asset/${assetId}/destroy` : `/admin/media/${rawId}/destroy`;

        metaId.textContent = rawId;
        metaMime.textContent = card.dataset.mime || '—';
        metaStatus.textContent = status.toUpperCase();
        metaDate.textContent = card.dataset.date || '—';
        metaSize.textContent = formatBytes(card.dataset.size || 0);
        directUrlInput.value = assetUrl ? window.location.origin + assetUrl : '';

        titleInput.value = card.dataset.title || card.dataset.originalName || '';
        descriptionInput.value = card.dataset.altText || '';
        titleInput.disabled = !titleSupported;
        descriptionInput.disabled = !altSupported;
        saveBtn.disabled = !canSaveMetadata;

        const isVideo = (card.dataset.mime || '').startsWith('video/');
        const isImage = (card.dataset.mime || '').startsWith('image/');
        if (isVideo) {
            descriptionLabel.textContent = 'Description';
            descriptionHint.textContent = 'Describe this video for accessibility (e.g. as a caption/transcript summary). AI cannot watch the video — it can only refine text you write here.';
            descriptionInput.placeholder = 'Describe this video for screen readers and future reuse.';
            aiBtn.title = 'Refine description with AI';
            aiBtn.setAttribute('aria-label', 'Refine description with AI');
        } else {
            descriptionLabel.textContent = 'Description / Alt Text';
            descriptionHint.textContent = 'This description is stored as the default alt text when the image is inserted elsewhere.';
            descriptionInput.placeholder = 'Describe this image for screen readers and future reuse.';
            aiBtn.title = 'Generate description with AI';
            aiBtn.setAttribute('aria-label', 'Generate description with AI');
        }
        updateAiBtnState();
        schemaNote.classList.toggle('is-hidden', canSaveMetadata);
        draftNote.classList.toggle('is-hidden', !isDraft);
        aiStatus.textContent = '';
        saveBtn.textContent = isDraft ? 'Confirm Asset' : 'Save';
        discardBtn.classList.toggle('is-hidden', !isDraft);
        trashBtn.classList.toggle('is-hidden', isDraft);
        deleteBtn.classList.toggle('is-hidden', isDraft);
        updatePosterField(card);

        document.getElementById('media-asset-modal-title').textContent = label || 'Selected Asset';
        if (isAsset) {
            subtitle.textContent = 'Preview, copy, and edit this library asset.';
        } else if (isDraft) {
            subtitle.textContent = 'Review this draft upload and confirm its metadata before it becomes reusable.';
        } else {
            subtitle.textContent = 'Preview, copy, and edit this native upload.';
        }

        setPreview(card);
    }

    function openCard(card, clearOpenParam = false) {
        if (!card) return;
        populateModal(card);
        modal.showModal();
        if (clearOpenParam) {
            const url = new URL(window.location.href);
            url.searchParams.delete('open');
            window.history.replaceState({}, '', url.toString());
        }
    }

    cards.forEach(card => {
        card.addEventListener('click', () => {
            openCard(card);
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
            if (!activeCard) return;
            const mime = activeCard.dataset.mime || '';
            const isVideo = mime.startsWith('video/');
            const isImage = mime.startsWith('image/');
            if (!isImage && !isVideo) {
                aiStatus.textContent = 'Select an image or video asset first.';
                return;
            }
            if (isVideo && !descriptionInput.value.trim()) {
                aiStatus.textContent = 'Write a description first — AI can only refine existing text for video, not invent one.';
                return;
            }

            window.openAiProfilePicker(async selection => {
                if (!selection?.profileId) return;
                aiBtn.disabled = true;
                aiStatus.textContent = isVideo ? 'Refining description...' : 'Generating description...';
                try {
                    const fd = new FormData();
                    fd.append('profile_id', selection.profileId);
                    if (selection.personaId) fd.append('persona_id', selection.personaId);

                    let res;
                    if (isVideo) {
                        fd.append('content', descriptionInput.value.trim());
                        fd.append('mode', 'text');
                        res = await fetch('/admin/ai/process', { method: 'POST', body: fd });
                    } else {
                        fd.append('image_url', activeCard.dataset.directUrl || '');
                        if (descriptionInput.value.trim()) fd.append('existing_alt_text', descriptionInput.value.trim());
                        res = await fetch('/admin/ai/describe-image', { method: 'POST', body: fd });
                    }

                    const data = await res.json();
                    if (data.result) {
                        descriptionInput.value = data.result;
                        aiStatus.textContent = (isVideo ? 'Description refined.' : 'Description generated.') + ' Save to store it.';
                        if (!previewImg.classList.contains('is-hidden')) {
                            previewImg.alt = data.result;
                        }
                    } else {
                        aiStatus.textContent = data.error || 'AI request failed.';
                    }
                } catch (error) {
                    aiStatus.textContent = 'AI request failed.';
                } finally {
                    updateAiBtnState();
                }
            }, isVideo ? {
                title: 'Refine Video Description',
                taskKey: 'video-description',
                hint: 'Choose an AI profile and an optional persona to polish the wording of the description you already wrote. AI cannot watch the video itself.'
            } : {
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
        updateAiBtnState();
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

    async function discardActiveDraft(skipConfirm = false) {
        if (!activeCard || (activeCard.dataset.status || 'ready') !== 'draft') return;
        if (!skipConfirm && !confirm('Discard this draft asset now? This will permanently remove the upload.')) return;
        aiStatus.textContent = 'Discarding draft...';
        try {
            const res = await fetch(`/admin/media/${activeCard.dataset.id}/discard`, {
                method: 'POST',
                headers: { Accept: 'application/json' },
            });
            const data = await res.json();
            if (!data.ok) {
                aiStatus.textContent = data.error || 'Could not discard the draft.';
                return;
            }
            const cardToRemove = activeCard;
            closeModal();
            cardToRemove.remove();
        } catch (error) {
            aiStatus.textContent = 'Could not discard the draft.';
        }
    }

    discardBtn?.addEventListener('click', () => {
        void discardActiveDraft(false);
    });

    deleteBtn.addEventListener('click', () => {
        if (!activeCard) return;
        const isAsset = activeCard.dataset.source === 'asset';
        const extra = isAsset ? ' This asset may be referenced elsewhere and broken links will not be auto-fixed.' : '';
        if (confirm('Permanently delete this asset? This cannot be undone.' + extra)) {
            deleteForm.submit();
        }
    });

    posterChooseBtn?.addEventListener('click', () => {
        if (!activeCard || (activeCard.dataset.mime || '').startsWith('video/') === false) return;
        if (window.openMediaPicker) {
            window.openMediaPicker(result => {
                if (!result?.id || result.kind !== 'image') return;
                activeCard.dataset.posterMediaId = String(result.id).replace(/^asset-/, '');
                activeCard.dataset.posterUrl = result.legacy_url || result.url || '';
                updatePosterField(activeCard);
                updateCardThumb(activeCard);
                setPreview(activeCard);
                if ((activeCard.dataset.status || 'ready') === 'draft') {
                    aiStatus.textContent = 'Poster selected. Confirm the draft to persist it.';
                } else {
                    void persistActiveAsset('Poster linked and metadata saved.');
                }
            }, 'select', { mode: 'image' });
        }
    });

    posterClearBtn?.addEventListener('click', () => {
        if (!activeCard) return;
        activeCard.dataset.posterMediaId = '';
        activeCard.dataset.posterUrl = '';
        updatePosterField(activeCard);
        updateCardThumb(activeCard);
        setPreview(activeCard);
        aiStatus.textContent = 'Poster cleared. Save to persist the change.';
    });

    async function requestModalClose() {
        if (activeCard && (activeCard.dataset.status || 'ready') === 'draft') {
            const keepDraft = window.confirm('Press OK to keep this draft for later, or Cancel to delete it now.');
            if (keepDraft) {
                closeModal();
                return;
            }
            void discardActiveDraft(true);
            return;
        }
        closeModal();
    }

    function closeModal() {
        modal.close();
    }

    closeBtn.addEventListener('click', () => { void requestModalClose(); });
    cancelBtn.addEventListener('click', () => { void requestModalClose(); });
    modal.addEventListener('click', event => {
        if (event.target === modal) {
            void requestModalClose();
        }
    });

    modalForm.addEventListener('submit', async event => {
        event.preventDefault();
        if (!activeCard) return;
        const isDraft = (activeCard.dataset.status || 'ready') === 'draft';
        aiStatus.textContent = isDraft ? 'Confirming asset...' : 'Saving metadata...';
        void persistActiveAsset(isDraft ? 'Asset confirmed and ready to reuse.' : 'Metadata saved.');
    });

    const newImageBtn = document.getElementById('media-new-image-btn');
    if (newImageBtn) {
        newImageBtn.addEventListener('click', () => {
            if (window.openMediaPicker) window.openMediaPicker(null, 'upload', { mode: 'media' });
        });
    }

    const openToken = new URLSearchParams(window.location.search).get('open') || '';
    if (openToken !== '') {
        const targetCard = cards.find(card => {
            if (openToken.startsWith('asset-')) {
                return card.dataset.source === 'asset' && ('asset-' + (card.dataset.assetId || '')) === openToken;
            }
            if (openToken.startsWith('file-')) {
                return card.dataset.source !== 'asset' && ('file-' + (card.dataset.id || '')) === openToken;
            }
            return false;
        });
        if (targetCard) {
            openCard(targetCard, true);
            targetCard.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
    }
});
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
