<?php
$pageTitle = 'Media Library — Fornesus Art Admin';
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
                             data-id="<?= (int) $f['id'] ?>"
                             data-mime="<?= htmlspecialchars($f['mime_type'] ?? '') ?>"
                             data-date="<?= date('Y-m-d', strtotime($f['created_at'])) ?>"
                             data-size="<?= (int) ($f['byte_size'] ?? 0) ?>"
                             aria-label="Select asset <?= (int) $f['id'] ?>, <?= htmlspecialchars($f['mime_type'] ?? 'unknown type') ?>">
                            <span class="media-card-thumb">
                                <?php if (str_starts_with((string) ($f['mime_type'] ?? ''), 'video/')): ?>
                                    <video src="/media/<?= (int) $f['id'] ?>" muted preload="metadata"></video>
                                <?php else: ?>
                                    <img src="/image/<?= (int) $f['id'] ?>"
                                         alt=""
                                         loading="lazy"
                                         onerror="this.parentElement.classList.add('media-thumb-missing')">
                                <?php endif ?>
                            </span>
                            <span class="media-card-meta">
                                <span class="media-card-id">Asset #<?= (int) $f['id'] ?></span>
                                <span class="media-card-type"><?= htmlspecialchars($f['mime_type'] ?? 'Unknown type') ?></span>
                                <span class="media-card-date"><?= date('Y-m-d', strtotime($f['created_at'])) ?></span>
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

                <div class="media-details-actions">
                    <form method="POST" id="action-trash-form">
                        <button type="submit" class="admin-btn">Move to Trash</button>
                    </form>
                    <form method="POST" id="action-destroy-form">
                        <button type="submit" class="admin-btn-danger">Delete Now</button>
                    </form>
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
        previewHost.querySelectorAll('video.dynamic-media-preview').forEach(node => node.remove());
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

        previewImg.src = `/image/${card.dataset.id}`;
        previewImg.alt = `Preview of asset ${card.dataset.id}`;
        previewImg.classList.remove('is-hidden');
    }

    function selectCard(card) {
        cards.forEach(item => item.classList.remove('active'));
        card.classList.add('active');

        const id = card.dataset.id;
        const mime = card.dataset.mime;
        const date = card.dataset.date;
        const assetUrl = `/media/${id}`;
        setPreview(card, assetUrl);

        metaId.textContent = id;
        metaMime.textContent = mime;
        metaDate.textContent = date;
        metaSize.textContent = formatBytes(card.dataset.size);

        inputUrl.value = window.location.origin + assetUrl;
        inputHtml.value = mime.startsWith('video/')
            ? `<video src="${assetUrl}" controls preload="metadata"></video>`
            : `<img src="/image/${id}" alt="">`;

        trashForm.action = `/admin/media/${id}/trash`;
        destroyForm.action = `/admin/media/${id}/destroy`;

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
        if (!confirm('Permanently delete this asset? This cannot be undone.')) {
            e.preventDefault();
        }
    });

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
