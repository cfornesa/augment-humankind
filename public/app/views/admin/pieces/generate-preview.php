<?php

declare(strict_types=1);

$pageTitle = 'AI Generation Preview';
ob_start();

$previewPiece = ['title' => 'AI Generated ' . strtoupper($engine)];
$previewVersion = [
    'html_code' => $htmlCode,
    'css_code' => $cssCode,
    'generated_code' => $generatedCode,
    'engine' => $engine
];

$defaultTitle = 'AI ' . strtoupper($engine) . ' Piece - ' . date('M d, Y H:i');
?>
<div class="admin-container">
    <div class="admin-header-row">
        <h1>AI Generation Preview</h1>
        <div style="display: flex; gap: 0.5rem;">
            <a href="/admin/pieces/generate" class="admin-btn admin-btn-ghost">Discard &amp; Back</a>
        </div>
    </div>

    <div style="background: var(--ink); border: 1px solid var(--line); border-radius: 4px; padding: 0.5rem; margin-bottom: 2rem; box-shadow: var(--shadow);">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem; border-bottom: 1px solid var(--line); margin-bottom: 0.5rem; font-size: 0.85rem; color: #a1a1aa;">
            <span>Live Sandbox Preview (Engine: <strong><?= e(strtoupper($engine)) ?></strong>)</span>
            <button type="button" class="admin-btn admin-btn-ghost" style="padding: 2px 8px; font-size: 0.75rem;" onclick="reloadPreviewIframe()">Reload Sandbox</button>
        </div>
        <div id="preview-iframe-wrapper">
            <?= piece_render_iframe($previewPiece, $previewVersion, 450) ?>
        </div>
    </div>

    <form class="admin-form" data-save-url="/admin/pieces/generate/save">
        <!-- Hidden inputs for AI generation details -->
        <input type="hidden" name="engine" value="<?= e($engine) ?>">
        <input type="hidden" name="generation_vendor" value="<?= e($profile['vendor'] ?? '') ?>">
        <input type="hidden" name="generation_model" value="<?= e($profile['model'] ?? '') ?>">
        <input type="hidden" name="generation_attempt_count" value="<?= (int) $attemptCount ?>">
        <input type="hidden" name="profile_id" value="<?= (int) ($profileId ?? 0) ?>">
        <input type="hidden" name="persona_id" value="<?= (int) ($personaId ?? 0) ?>">

        <div class="admin-tabs piece-preview-tabs" role="tablist" style="margin-bottom: 1.5rem;">
            <button type="button" class="admin-tab active" data-tab="meta">Metadata</button>
            <button type="button" class="admin-tab" data-tab="html">HTML Code</button>
            <button type="button" class="admin-tab" data-tab="css">CSS Code</button>
            <button type="button" class="admin-tab" data-tab="js">JavaScript Code</button>
        </div>

        <!-- Metadata Tab -->
        <div id="tab-meta" class="piece-tab-panel">
            <div class="field">
                <label for="title">Title</label>
                <input id="title" name="title" type="text" required maxlength="255" value="<?= e($defaultTitle) ?>">
            </div>

            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="draft" selected>Draft (Recommended)</option>
                    <option value="active">Active (Visible in public list)</option>
                    <option value="archived">Archived</option>
                </select>
            </div>

            <div class="field">
                <label for="description">Description (Optional)</label>
                <textarea id="description" name="description" rows="3" placeholder="Brief details about what this piece shows or how it behaves..."></textarea>
            </div>

            <div class="field">
                <label for="prompt">Generation Prompt</label>
                <textarea id="prompt" name="prompt" rows="3" readonly style="background: rgba(255,255,255,0.05); color: var(--ink-soft); cursor: not-allowed;"><?= e($prompt) ?></textarea>
            </div>
        </div>

        <!-- HTML Code Tab -->
        <div id="tab-html" class="piece-tab-panel is-hidden">
            <div class="field">
                <label for="html_code">HTML Mounting Node</label>
                <textarea id="html_code" name="html_code" rows="16" class="code-field" style="font-family: monospace; font-size: 0.9rem;" oninput="updatePreview()"><?= e($htmlCode) ?></textarea>
                <small>The element(s) loaded into the page container.</small>
            </div>
        </div>

        <!-- CSS Code Tab -->
        <div id="tab-css" class="piece-tab-panel is-hidden">
            <div class="field">
                <label for="css_code">CSS Styles</label>
                <textarea id="css_code" name="css_code" rows="16" class="code-field" style="font-family: monospace; font-size: 0.9rem;" oninput="updatePreview()"><?= e($cssCode) ?></textarea>
                <small>Scoped CSS used to style the canvas or mounting nodes.</small>
            </div>
        </div>

        <!-- JS Code Tab -->
        <div id="tab-js" class="piece-tab-panel is-hidden">
            <div class="field">
                <label for="generated_code">JavaScript (window.sketch)</label>
                <textarea id="generated_code" name="generated_code" rows="16" class="code-field" style="font-family: monospace; font-size: 0.9rem;" oninput="updatePreview()"><?= e($generatedCode) ?></textarea>
                <small>The primary drawing/setup loops and handlers.</small>
            </div>
        </div>

        <input type="hidden" id="thumbnail_data" name="thumbnail_data" value="">

        <div class="form-actions" style="margin-top: 2rem; display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem;">
            <span id="save-status" role="status" aria-live="polite" style="width:100%; font-size: 0.8rem; color: var(--ink-soft);"></span>
            <button type="button" class="admin-btn" id="save-insert-btn">Save and Insert (Create Version 1)</button>
            <button type="button" class="admin-btn admin-btn-ghost" id="save-without-thumbnail-btn" hidden>Save Without Thumbnail</button>
            <a href="/admin/pieces/generate" class="admin-btn admin-btn-ghost">Discard &amp; Restart</a>
            <span id="thumbnail-status" style="font-size: 0.8rem; color: var(--ink-soft);">Waiting for piece to render…</span>
        </div>
    </form>

    <script src="/assets/js/admin-piece-capture.js?v=<?= (int) @filemtime(dirname(__DIR__, 4) . '/assets/js/admin-piece-capture.js') ?>"></script>
    <script>
    // Computed server-side from the actual request (not window.location.origin):
    // the preview iframes below use srcdoc, which gets an opaque origin —
    // window.location.origin would literally be the string "null" inside
    // them, even with sandbox="allow-same-origin".
    var RUNTIME_ORIGIN = <?= json_encode(
        ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
    ) ?>;
    (function () {
        var engine = <?= json_encode($engine) ?>;
        var htmlTabButton = document.querySelector('.piece-preview-tabs button[data-tab="html"]');
        if (htmlTabButton && engine !== 'svg') {
            htmlTabButton.style.display = 'none';
        }

        var tabs = document.querySelectorAll('.piece-preview-tabs .admin-tab');
        var panels = {
            meta: document.getElementById('tab-meta'),
            html: document.getElementById('tab-html'),
            css: document.getElementById('tab-css'),
            js: document.getElementById('tab-js')
        };
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                tabs.forEach(function (t) { t.classList.remove('active'); });
                tab.classList.add('active');
                Object.keys(panels).forEach(function (key) {
                    if (key === tab.dataset.tab) {
                        panels[key].classList.remove('is-hidden');
                    } else {
                        panels[key].classList.add('is-hidden');
                    }
                });
            });
        });
    })();

    function reloadPreviewIframe() {
        var wrapper = document.getElementById('preview-iframe-wrapper');
        if (!wrapper) return;
        var iframe = wrapper.querySelector('iframe');
        if (iframe) {
            iframe.srcdoc = iframe.srcdoc; // Triggers reload
        }
    }

    var updateTimeout = null;
    var thumbnailRevision = 0;
    function updatePreview() {
        clearTimeout(updateTimeout);
        updateTimeout = setTimeout(function () {
            thumbnailRevision++;
            var thumbnailInput = document.getElementById('thumbnail_data');
            var thumbnailStatus = document.getElementById('thumbnail-status');
            if (thumbnailInput) thumbnailInput.value = '';
            if (thumbnailStatus) {
                thumbnailStatus.textContent = 'Waiting for updated piece to render…';
                thumbnailStatus.style.color = 'var(--ink-soft)';
            }

            var html = document.getElementById('html_code').value;
            var css = document.getElementById('css_code').value;
            var js = document.getElementById('generated_code').value;
            var engine = document.querySelector('input[name="engine"]').value;

            var docTemplate = window.CreatrPieceCapture.renderDocument({
                title: 'AI Generation Preview',
                engine: engine,
                html: html,
                css: css,
                js: js,
                runtimeOrigin: RUNTIME_ORIGIN,
                preserveDrawingBuffer: true
            });

            var wrapper = document.getElementById('preview-iframe-wrapper');
            if (wrapper) {
                var iframe = wrapper.querySelector('iframe');
                if (iframe) {
                    iframe.srcdoc = docTemplate;
                }
            }
        }, 600);
    }

    // Auto-capture thumbnail using the same browser-side capture path as the
    // edit form and Pieces list.
    var ensurePreviewThumbnail = (function () {
        var thumbnailInput = document.getElementById('thumbnail_data');
        var statusEl = document.getElementById('thumbnail-status');
        var inFlight = null;

        function setStatus(msg, ok) {
            if (!statusEl) return;
            statusEl.textContent = msg;
            statusEl.style.color = ok ? '#10b981' : (ok === false ? '#ef4444' : 'var(--ink-soft)');
        }

        function source() {
            return {
                title: document.getElementById('title').value || 'AI Generation Preview',
                engine: document.querySelector('input[name="engine"]').value || 'p5',
                html: document.getElementById('html_code').value || '',
                css: document.getElementById('css_code').value || '',
                js: document.getElementById('generated_code').value || '',
                runtimeOrigin: RUNTIME_ORIGIN,
                preserveDrawingBuffer: true,
                seed: 8383,
                width: 960,
                height: 540
            };
        }

        return function (reason) {
            if (thumbnailInput && thumbnailInput.value) {
                return Promise.resolve({ ok: true, dataUrl: thumbnailInput.value, kind: 'cached', error: null });
            }
            if (inFlight) return inFlight;

            setStatus(reason || 'Capturing thumbnail…');
            var revisionAtStart = thumbnailRevision;
            var wrapper = document.getElementById('preview-iframe-wrapper');
            var liveIframe = wrapper ? wrapper.querySelector('iframe') : null;
            var sourceObj = source();
            sourceObj.liveIframe = liveIframe;

            inFlight = window.CreatrPieceCapture.capture(sourceObj).then(function (result) {
                if (revisionAtStart !== thumbnailRevision) {
                    return { ok: false, dataUrl: '', kind: null, error: 'Capture was superseded by a newer preview.' };
                }
                if (result.ok && thumbnailInput) {
                    thumbnailInput.value = result.dataUrl;
                    setStatus('Thumbnail captured', true);
                } else {
                    setStatus('Thumbnail capture failed: ' + (result.error || 'Unknown error'), false);
                }
                return result;
            }).finally(function () {
                inFlight = null;
            });
            return inFlight;
        };
    })();

    window.addEventListener('load', function () {
        setTimeout(function () { ensurePreviewThumbnail('Capturing thumbnail…'); }, 250);
    });

    // Save as fetch() with a one-time retry on a network-level failure only
    // (the connection itself dying, not a server-returned error) — a stale
    // keep-alive connection reused after sitting idle since the Generate
    // request (e.g. a gap spent reviewing the preview, or the tab getting
    // backgrounded on mobile) fails a traditional POST with no way to
    // recover; a fresh fetch() attempt opens a new connection instead.
    (function () {
        var form = document.querySelector('form.admin-form');
        if (!form) return;
        var saveBtn = document.getElementById('save-insert-btn');
        var saveWithoutThumbBtn = document.getElementById('save-without-thumbnail-btn');
        var saveStatusEl = document.getElementById('save-status');
        var timerInterval = null;
        var startedAt = null;
        var allowSaveWithoutThumbnail = false;

        function formatElapsed(ms) {
            var totalSeconds = Math.floor(ms / 1000);
            var minutes = Math.floor(totalSeconds / 60);
            var seconds = totalSeconds % 60;
            return minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
        }

        function startTimer(label) {
            startedAt = Date.now();
            if (timerInterval) clearInterval(timerInterval);
            if (saveStatusEl) saveStatusEl.textContent = label + ' (' + formatElapsed(0) + ' elapsed)';
            timerInterval = setInterval(function () {
                if (saveStatusEl) saveStatusEl.textContent = label + ' (' + formatElapsed(Date.now() - startedAt) + ' elapsed)';
            }, 1000);
        }

        function stopTimer() {
            if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
        }

        function setSaveStatus(msg, isError) {
            if (!saveStatusEl) return;
            saveStatusEl.textContent = msg;
            saveStatusEl.style.color = isError ? '#ef4444' : 'var(--ink-soft)';
        }

        function submitSave(formData, isRetry) {
            fetch(form.dataset.saveUrl || '/admin/pieces/generate/save', { method: 'POST', body: formData }).then(function (resp) {
                return resp.json();
            }).then(function (data) {
                stopTimer();
                if (data.success) {
                    setSaveStatus('Saved ✓ Redirecting…');
                    window.location.href = data.redirect || '/admin/pieces';
                } else {
                    setSaveStatus('Save failed: ' + (data.error || 'Unknown error'), true);
                    if (saveBtn) saveBtn.disabled = false;
                }
            }).catch(function () {
                if (!isRetry) {
                    setSaveStatus('Connection issue — retrying…');
                    setTimeout(function () { submitSave(formData, true); }, 1000);
                    return;
                }
                stopTimer();
                setSaveStatus('Save failed: the connection was lost. Please try again.', true);
                if (saveBtn) saveBtn.disabled = false;
            });
        }

        if (!saveBtn) return;
        saveBtn.addEventListener('click', function () {
            if (!form.reportValidity()) return;
            if (saveBtn) saveBtn.disabled = true;
            startTimer('Capturing thumbnail…');
            ensurePreviewThumbnail('Capturing thumbnail before save…').then(function (result) {
                if (!result.ok && !allowSaveWithoutThumbnail) {
                    stopTimer();
                    setSaveStatus('Thumbnail capture failed: ' + (result.error || 'Unknown error') + '. Use Save Without Thumbnail only if this is intentional.', true);
                    if (saveWithoutThumbBtn) saveWithoutThumbBtn.hidden = false;
                    if (saveBtn) saveBtn.disabled = false;
                    return;
                }
                startTimer('Saving…');
                submitSave(new FormData(form), false);
            });
        });

        if (saveWithoutThumbBtn) {
            saveWithoutThumbBtn.addEventListener('click', function () {
                if (!form.reportValidity()) return;
                allowSaveWithoutThumbnail = true;
                saveWithoutThumbBtn.disabled = true;
                if (saveBtn) saveBtn.disabled = true;
                startTimer('Saving without thumbnail…');
                submitSave(new FormData(form), false);
            });
        }
    })();
    </script>
</div>
<?php
$content = ob_get_clean();

require dirname(__DIR__) . '/layout.php';
