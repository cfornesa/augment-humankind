<?php

declare(strict_types=1);

$pageTitle = 'AI Generation Preview';
ob_start();

$generationMode = (string) ($generationMode ?? $engine);
$generationModeLabel = art_piece_generation_mode_label($generationMode);
$previewPiece = ['title' => 'AI Generated ' . $generationModeLabel];
$previewVersion = [
    'html_code' => $htmlCode,
    'css_code' => $cssCode,
    'generated_code' => $generatedCode,
    'engine' => $engine,
    'sonic_params' => $sonicParams ?? null,
];

$defaultTitle = 'AI ' . $generationModeLabel . ' Piece - ' . date('M d, Y H:i');
?>
<div class="admin-container">
    <div class="admin-header-row">
        <h1>AI Generation Preview</h1>
        <div style="display: flex; gap: 0.5rem;">
            <a href="/admin/pieces/generate?restart=1" class="admin-btn admin-btn-ghost">Discard &amp; Back</a>
        </div>
    </div>

    <div style="background: var(--ink); border: 1px solid var(--line); border-radius: 4px; padding: 0.5rem; margin-bottom: 2rem; box-shadow: var(--shadow);">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem; border-bottom: 1px solid var(--line); margin-bottom: 0.5rem; font-size: 0.85rem; color: #a1a1aa;">
            <span>Live Sandbox Preview (Engine: <strong><?= e($generationModeLabel) ?></strong>)</span>
            <div style="display: flex; gap: 0.5rem;">
                <button type="button" id="preview-sound-toggle" class="admin-btn admin-btn-ghost" style="padding: 2px 8px; font-size: 0.75rem;" data-piece-sound-toggle aria-pressed="false" aria-label="Unmute sound" hidden>Unmute</button>
                <button type="button" class="admin-btn admin-btn-ghost" style="padding: 2px 8px; font-size: 0.75rem;" onclick="reloadPreviewIframe()">Reload Sandbox</button>
            </div>
        </div>
        <div id="preview-iframe-wrapper">
            <?= piece_render_iframe($previewPiece, $previewVersion, 450) ?>
        </div>
    </div>

    <form class="admin-form" data-save-url="/admin/pieces/generate/save">
        <!-- Hidden inputs for AI generation details -->
        <input type="hidden" id="engine" name="engine" value="<?= e($engine) ?>">
        <input type="hidden" id="generation_mode" name="generation_mode" value="<?= e($generationMode) ?>">
        <input type="hidden" id="generation_vendor" name="generation_vendor" value="<?= e($profile['vendor'] ?? '') ?>">
        <input type="hidden" id="generation_model" name="generation_model" value="<?= e($profile['model'] ?? '') ?>">
        <input type="hidden" id="generation_attempt_count" name="generation_attempt_count" value="<?= (int) $attemptCount ?>">
        <input type="hidden" id="profile_id" name="profile_id" value="<?= (int) ($profileId ?? 0) ?>">
        <input type="hidden" id="persona_id" name="persona_id" value="<?= (int) ($personaId ?? 0) ?>">
        <input type="hidden" id="sonic_params" name="sonic_params" value="<?= e((string) ($sonicParams ?? '')) ?>">
        <!-- Audio-lineage hidden inputs: per the per-domain rule, regenerate
             derives its purpose_domain PURELY from these (the original
             generation's audio intention), NOT from any new user input on
             the regenerate request (regenerate only amplifies existing
             scope, never changes it). -->
        <input type="hidden" id="sound_feel_lineage" name="sound_feel_lineage" value="<?= e($soundFeelLineage ?? '') ?>">
        <input type="hidden" id="sound_enabled_lineage" name="sound_enabled_lineage" value="<?= $soundEnabledLineage ? '1' : '0' ?>">

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
            <button type="button" class="admin-btn admin-btn-ghost" id="regenerate-preview-btn">Regenerate</button>
            <button type="button" class="admin-btn admin-btn-ghost" id="save-without-thumbnail-btn" hidden>Save Without Thumbnail</button>
            <a href="/admin/pieces/generate?restart=1" class="admin-btn admin-btn-ghost">Discard &amp; Restart</a>
            <span id="thumbnail-status" style="font-size: 0.8rem; color: var(--ink-soft);">Waiting for piece to render…</span>
        </div>
    </form>

    <dialog id="preview-regenerate-failed-dialog" class="inline-create-dialog">
        <div class="dialog-header">
            <h2 id="preview-regenerate-failed-title">Regenerate attempt 1 of <?= (int) ART_PIECE_MAX_ATTEMPTS ?> failed</h2>
        </div>
        <div class="dialog-body">
            <p id="preview-regenerate-failed-message"></p>
        </div>
        <div class="dialog-footer">
            <button type="button" class="admin-btn admin-btn-ghost" id="preview-regenerate-give-up-btn">Give Up</button>
            <button type="button" class="admin-btn" id="preview-regenerate-try-again-btn">Try Again</button>
        </div>
    </dialog>

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
        if (htmlTabButton && engine !== 'svg' && engine !== 'aframe') {
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

    // Sound toggle — same pattern as piece-fullscreen.js/the edit form's
    // preview: the iframe's document reloads on every regenerate/reload
    // (srcdoc reassignment), so the button always re-queries the current
    // iframe rather than caching a reference.
    (function () {
        var soundToggle = document.getElementById('preview-sound-toggle');
        if (!soundToggle) return;
        var soundEnabled = false;

        function currentIframe() {
            var wrapper = document.getElementById('preview-iframe-wrapper');
            return wrapper ? wrapper.querySelector('iframe') : null;
        }

        window.setPreviewSoundToggleVisibility = function () {
            var params = currentSonicParams();
            soundToggle.hidden = !params || params.enabled === false;
            soundEnabled = false;
            soundToggle.setAttribute('aria-pressed', 'false');
            soundToggle.setAttribute('aria-label', 'Unmute sound');
            soundToggle.textContent = 'Unmute';
        };

        soundToggle.addEventListener('click', function () {
            var next = !soundEnabled;
            var iframe = currentIframe();
            if (iframe && iframe.contentWindow) {
                iframe.contentWindow.postMessage({ type: 'creatr-sound-toggle', enabled: next }, '*');
            }
            soundEnabled = next;
            soundToggle.setAttribute('aria-pressed', next ? 'true' : 'false');
            soundToggle.setAttribute('aria-label', next ? 'Mute sound' : 'Unmute sound');
            soundToggle.textContent = next ? 'Mute' : 'Unmute';
        });

        window.addEventListener('message', function (event) {
            var iframe = currentIframe();
            if (!iframe || event.source !== iframe.contentWindow || !event.data || event.data.type !== 'creatr-sound-state') {
                return;
            }
            soundEnabled = !!event.data.enabled;
            soundToggle.setAttribute('aria-pressed', soundEnabled ? 'true' : 'false');
            soundToggle.setAttribute('aria-label', soundEnabled ? 'Mute sound' : 'Unmute sound');
            soundToggle.textContent = soundEnabled ? 'Mute' : 'Unmute';
        });

        setPreviewSoundToggleVisibility();
    })();

    var updateTimeout = null;
    var thumbnailRevision = 0;
    function currentSonicParams() {
        var input = document.getElementById('sonic_params');
        if (!input || !input.value) return null;
        try {
            var parsed = JSON.parse(input.value);
            return (parsed && typeof parsed === 'object') ? parsed : null;
        } catch (e) {
            return null;
        }
    }

    function renderPreviewDocument() {
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
            preserveDrawingBuffer: true,
            sonicParams: currentSonicParams()
        });

        var wrapper = document.getElementById('preview-iframe-wrapper');
        if (wrapper) {
            var iframe = wrapper.querySelector('iframe');
            if (iframe) {
                iframe.srcdoc = docTemplate;
            }
        }
        setPreviewSoundToggleVisibility();
    }
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
            renderPreviewDocument();
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
            var sourceObj = Object.assign(source(), { width: 320, height: 180 });

            // Builds its own genuinely visible overlay rather than reusing
            // #preview-iframe-wrapper's iframe — applied for uniformity with
            // every other non-"Generate Thumbnail" capture call site, not
            // because this wrapper was found hidden (it isn't).
            inFlight = window.CreatrPieceCapture.captureWithOverlay(sourceObj).then(function (result) {
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

    (function () {
        var regenerateBtn = document.getElementById('regenerate-preview-btn');
        var statusEl = document.getElementById('save-status');
        var htmlField = document.getElementById('html_code');
        var cssField = document.getElementById('css_code');
        var jsField = document.getElementById('generated_code');
        var promptField = document.getElementById('prompt');
        var profileField = document.getElementById('profile_id');
        var personaField = document.getElementById('persona_id');
        var engineField = document.querySelector('input[name="engine"]');
        var generationModeField = document.getElementById('generation_mode');
        var thumbField = document.getElementById('thumbnail_data');
        // Lineage hidden inputs — per the per-domain rule regenerate derives
        // its purpose_domain PURELY from these (read-only lineage, never
        // recomputed from scratch on a regenerate request). sonicField is
        // the current (potentially already-regenerated) preview's sound
        // design; soundFeelLineageField / soundEnabledLineageField are the
        // audio-intent constants captured at generation time.
        var sonicField = document.getElementById('sonic_params');
        var soundFeelLineageField = document.getElementById('sound_feel_lineage');
        var soundEnabledLineageField = document.getElementById('sound_enabled_lineage');
        var failedDialog = document.getElementById('preview-regenerate-failed-dialog');
        var failedTitle = document.getElementById('preview-regenerate-failed-title');
        var failedMessage = document.getElementById('preview-regenerate-failed-message');
        var tryAgainBtn = document.getElementById('preview-regenerate-try-again-btn');
        var giveUpBtn = document.getElementById('preview-regenerate-give-up-btn');
        var regenerateInFlight = false;
        var regenerateSequenceToken = '';
        var ART_PIECE_MAX_ATTEMPTS = <?= (int) ART_PIECE_MAX_ATTEMPTS ?>;

        function setRegenerateStatus(message, isError) {
            if (!statusEl) return;
            statusEl.textContent = message;
            statusEl.style.color = isError ? '#ef4444' : 'var(--ink-soft)';
        }

        function formatElapsed(ms) {
            var totalSeconds = Math.floor(ms / 1000);
            var minutes = Math.floor(totalSeconds / 60);
            var seconds = totalSeconds % 60;
            return minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
        }

        function buildRegeneratePayload(ctx) {
            return {
                prompt: promptField ? promptField.value.trim() : '',
                engine: engineField ? engineField.value : 'p5',
                generation_mode: generationModeField ? generationModeField.value : (engineField ? engineField.value : 'p5'),
                profile_id: profileField ? profileField.value : '',
                persona_id: personaField ? personaField.value : '',
                html_code: htmlField ? htmlField.value : '',
                css_code: cssField ? cssField.value : '',
                generated_code: jsField ? jsField.value : '',
                // Audio lineage — passed straight through as read-only
                // constants. The server derives purpose_domain solely
                // from these (per the per-domain rule).
                sound_feel_lineage: soundFeelLineageField ? soundFeelLineageField.value : '',
                sound_enabled_lineage: soundEnabledLineageField ? (soundEnabledLineageField.value === '1') : false,
                sonic_params: sonicField ? sonicField.value : '',
                attempt_number: ctx.attemptNumber,
                previous_raw_response: ctx.previousRawResponse || '',
                last_error: ctx.lastError || '',
                sequence_token: regenerateSequenceToken
            };
        }

        function applyRegeneratedPreview(data) {
            if (htmlField && typeof data.html_code === 'string') htmlField.value = data.html_code;
            if (cssField && typeof data.css_code === 'string') cssField.value = data.css_code;
            if (jsField && typeof data.generated_code === 'string') jsField.value = data.generated_code;
            // Reflect the regenerated sound design back into the hidden
            // input BEFORE renderPreviewDocument() — render() reads
            // currentSonicParams() live from this input to assemble the
            // preview iframe, so an updated value here is what surfaces
            // the new instrumentation audibly in the preview. Previously
            // regenerate silently dropped the sonic_params back to the
            // client, leaving the preview with the original sound.
            if (sonicField && Object.prototype.hasOwnProperty.call(data, 'sonic_params')) {
                sonicField.value = (typeof data.sonic_params === 'string') ? data.sonic_params : '';
            }
            thumbnailRevision++;
            if (thumbField) thumbField.value = '';
            renderPreviewDocument();
            setRegenerateStatus('Regenerate succeeded. Rebuilding thumbnail…', false);
            ensurePreviewThumbnail('Recapturing thumbnail after regenerate…').then(function (thumbResult) {
                if (thumbResult.ok) {
                    setRegenerateStatus('Regenerate complete. Thumbnail updated.', false);
                } else {
                    setRegenerateStatus('Regenerate succeeded, but thumbnail capture failed: ' + (thumbResult.error || 'Unknown error'), true);
                }
            });
        }

        function handleRegenerateFailure(ctx, data) {
            if (!failedDialog) {
                setRegenerateStatus(data.error || 'Regenerate failed.', true);
                return;
            }
            var attemptNumber = data.attempt_number || ctx.attemptNumber;
            var canRetry = data.can_retry !== false && attemptNumber < ART_PIECE_MAX_ATTEMPTS;
            failedTitle.textContent = 'Regenerate attempt ' + attemptNumber + ' of ' + ART_PIECE_MAX_ATTEMPTS + ' failed';
            failedMessage.textContent = data.error || 'Unknown error';
            tryAgainBtn.hidden = !canRetry;

            var nextTryAgainBtn = tryAgainBtn.cloneNode(true);
            tryAgainBtn.parentNode.replaceChild(nextTryAgainBtn, tryAgainBtn);
            tryAgainBtn = nextTryAgainBtn;
            var nextGiveUpBtn = giveUpBtn.cloneNode(true);
            giveUpBtn.parentNode.replaceChild(nextGiveUpBtn, giveUpBtn);
            giveUpBtn = nextGiveUpBtn;

            giveUpBtn.addEventListener('click', function () {
                failedDialog.close();
                setRegenerateStatus('Regenerate stopped after attempt ' + attemptNumber + '.', true);
            });

            if (canRetry) {
                tryAgainBtn.addEventListener('click', function () {
                    failedDialog.close();
                    performRegenerateAttempt({
                        attemptNumber: attemptNumber + 1,
                        previousRawResponse: data.raw_response || null,
                        lastError: data.error || null
                    });
                });
            }

            failedDialog.showModal();
        }

        function performRegenerateAttempt(ctx) {
            if (regenerateInFlight) return;
            regenerateInFlight = true;
            if (!regenerateSequenceToken) {
                regenerateSequenceToken = 'preview-regen-' + Date.now() + '-' + Math.random().toString(36).slice(2);
            }
            regenerateBtn.disabled = true;
            var startedAt = Date.now();
            var timer = setInterval(function () {
                setRegenerateStatus('Regenerate attempt ' + ctx.attemptNumber + ' of ' + ART_PIECE_MAX_ATTEMPTS + ' - ' + formatElapsed(Date.now() - startedAt) + ' elapsed', false);
            }, 1000);
            setRegenerateStatus('Regenerate attempt ' + ctx.attemptNumber + ' of ' + ART_PIECE_MAX_ATTEMPTS + ' - ' + formatElapsed(0) + ' elapsed', false);

            fetch('/admin/pieces/generate/regenerate', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(buildRegeneratePayload(ctx))
            }).then(function (resp) {
                return resp.json();
            }).then(function (data) {
                clearInterval(timer);
                regenerateInFlight = false;
                regenerateBtn.disabled = false;
                if (!data.success) {
                    handleRegenerateFailure(ctx, data);
                    return;
                }
                applyRegeneratedPreview(data);
            }).catch(function (err) {
                clearInterval(timer);
                regenerateInFlight = false;
                regenerateBtn.disabled = false;
                setRegenerateStatus('Regenerate failed: ' + (err && err.message ? err.message : 'Unknown error'), true);
            });
        }

        if (regenerateBtn) {
            regenerateBtn.addEventListener('click', function () {
                performRegenerateAttempt({
                    attemptNumber: 1,
                    previousRawResponse: null,
                    lastError: null
                });
            });
        }
    })();

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
