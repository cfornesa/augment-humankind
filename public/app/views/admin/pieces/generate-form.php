<?php

declare(strict_types=1);

$pageTitle = 'Generate Piece with AI';
// Loads tiptap-editor.js + the shared #media-picker-modal markup (see
// layout.php) — required for window.openMediaPicker, used by the "Add
// media reference" picker below.
$needsEditor = true;
ob_start();
?>
<div class="admin-container">
    <div class="admin-header-row">
        <h1>Generate Piece with AI</h1>
        <a href="/admin/pieces" class="admin-btn admin-btn-ghost">Back</a>
    </div>

    <?php if ($error): ?>
        <div class="form-status form-status-error" role="alert" style="margin-bottom: 1.5rem;">
            <p><strong>Generation Failed:</strong> <?= e($error) ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($attemptLogs)): ?>
        <div class="admin-card" style="border: 1px solid var(--line); border-radius: 4px; padding: 1.25rem; margin-bottom: 2rem; background: rgba(239, 68, 68, 0.05);">
            <h2 style="margin-top: 0; font-size: 1.1rem; color: #ef4444; display: flex; align-items: center; gap: 0.5rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                Detailed Generation Log (<?= count($attemptLogs) ?> Attempts)
            </h2>
            <div style="display: flex; flex-direction: column; gap: 1rem; margin-top: 1rem;">
                <?php foreach ($attemptLogs as $log): ?>
                    <div style="border-left: 3px solid <?= $log['success'] ? '#10b981' : '#ef4444' ?>; padding-left: 1rem; font-size: 0.9rem;">
                        <div style="font-weight: 600; margin-bottom: 0.25rem;">
                            Attempt #<?= (int) $log['attempt'] ?> &mdash; 
                            <span style="color: <?= $log['success'] ? '#10b981' : '#ef4444' ?>;">
                                <?= $log['success'] ? 'SUCCESS' : 'FAILED' ?>
                            </span>
                        </div>
                        <div style="color: var(--ink-soft); margin-bottom: 0.25rem;">
                            <strong>Vendor/Model:</strong> <code><?= e($log['vendor']) ?></code> / <code><?= e($log['model']) ?></code><br>
                            <strong>API Endpoint:</strong> <code style="font-size: 0.8rem; word-break: break-all;"><?= e($log['url']) ?></code>
                            <?php if ($log['status'] !== null): ?>
                                (Status: <code><?= (int) $log['status'] ?></code>)
                            <?php endif; ?>
                        </div>
                        <?php if ($log['api_error']): ?>
                            <div style="color: #ef4444; background: rgba(239, 68, 68, 0.05); padding: 0.5rem; border-radius: 4px; font-family: monospace; font-size: 0.85rem; margin-top: 0.25rem; white-space: pre-wrap;">
                                <strong>API Error:</strong> <?= e($log['api_error']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($log['validation_error']): ?>
                            <div style="color: #ef4444; background: rgba(239, 68, 68, 0.05); padding: 0.5rem; border-radius: 4px; font-family: monospace; font-size: 0.85rem; margin-top: 0.25rem; white-space: pre-wrap;">
                                <strong>Preflight Validation Fail:</strong> <?= e($log['validation_error']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($log['raw_response'])): ?>
                            <details style="margin-top: 0.5rem;">
                                <summary style="cursor: pointer; font-size: 0.8rem; color: var(--ink-soft);">Raw API Response</summary>
                                <pre style="margin-top: 0.35rem; font-size: 0.75rem; white-space: pre-wrap; word-break: break-all; max-height: 280px; overflow-y: auto; background: var(--surface-2, #1a1a2e); padding: 0.5rem; border-radius: 4px;"><?= e(mb_substr($log['raw_response'], 0, 4000)) ?><?= mb_strlen($log['raw_response']) > 4000 ? "\n…[truncated]" : '' ?></pre>
                            </details>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($availableGenerationModes ?? [])): ?>
        <div class="form-status form-status-error" role="alert">
            <p>All piece AI generation modes are disabled. Enable at least one mode under Admin → Features → AI before generating a piece.</p>
            <p style="margin-top: 0.5rem;"><a href="/admin/features?tab=ai" class="admin-btn">Open AI Features</a></p>
        </div>
    <?php elseif (empty($profiles)): ?>
        <div class="form-status form-status-error" role="alert">
            <p>No active AI Settings profiles found. You must configure and enable at least one AI Profile in the settings page to generate pieces.</p>
            <p style="margin-top: 0.5rem;"><a href="/admin/user-profiles" class="admin-btn">Configure AI Profiles &amp; Keys</a></p>
        </div>
    <?php else: ?>
        <form class="admin-form" id="generate-form" data-generate-url="/admin/pieces/generate">
            <div class="field">
                <label for="profile_id">AI Profile / Vendor &amp; Model</label>
                <select id="profile_id" name="profile_id" required>
                    <option value="">-- Select Active AI Profile --</option>
                    <?php foreach ($profiles as $prof): ?>
                        <option value="<?= (int) $prof['id'] ?>"
                                data-capabilities="<?= e($prof['capabilities'] ?? 'text,code') ?>"
                                <?= (int) ($selectedProfileId ?? 0) === (int) $prof['id'] ? 'selected' : '' ?>>
                            <?= e($prof['profile_name']) ?> (<?= e($prof['vendor']) ?>: <?= e($prof['model']) ?>) &mdash; By <?= e($prof['user_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="ai-capability-warning" id="code-cap-warning" hidden
                   style="margin-top:0.5rem;padding:0.5rem 0.75rem;background:rgba(234,179,8,0.12);border:1px solid rgba(234,179,8,0.4);border-radius:4px;font-size:0.875rem;color:#92400e;">
                    ⚠ This profile may not support code generation. Piece generation may fail.
                    Consider switching to a profile with <strong>Code generation</strong> capability enabled
                    under <a href="/admin/ai-settings?tab=profiles" style="color:inherit;">AI Settings → AI Profiles</a>.
                </p>
            </div>

            <div class="field">
                <label for="persona_id">AI Persona <span style="font-weight:400;color:var(--ink-soft);">(optional)</span></label>
                <select id="persona_id" name="persona_id">
                    <option value="">None — use raw prompt</option>
                    <?php foreach ($personas as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= (int) ($selectedPersonaId ?? 0) === (int) $p['id'] ? 'selected' : '' ?>>
                            <?= e($p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="__new__">+ Create new persona…</option>
                </select>
                <small>A persona prepends its system prompt to your creative prompt before sending to the AI.</small>
            </div>

            <div class="field">
                <label for="generation_mode">Art Engine / Runtime Environment</label>
                <select id="generation_mode" name="generation_mode" required>
                    <?php
                    $modesByGroup = [];
                    foreach (($availableGenerationModes ?? []) as $mode) {
                        $modesByGroup[$mode['group']][] = $mode;
                    }
                    ?>
                    <?php foreach ($modesByGroup as $groupLabel => $modes): ?>
                        <optgroup label="<?= e($groupLabel) ?>">
                            <?php foreach ($modes as $mode): ?>
                                <option value="<?= e($mode['value']) ?>" <?= ($generationMode ?? $engine ?? 'p5') === $mode['value'] ? 'selected' : '' ?>>
                                    <?= e($mode['label']) ?>
                                </option>
                            <?php endforeach ?>
                        </optgroup>
                    <?php endforeach ?>
                </select>
                <small>Only modes enabled under Admin → Features → AI appear here.</small>
            </div>

            <div class="field">
                <label for="prompt">Creative Prompt</label>
                <textarea id="prompt" name="prompt" rows="6" placeholder="Describe the visual effects, interaction, behavior, colors, and layout of the piece. E.g. 'A cascading waterfall of particles that bounce off obstacles when the mouse is dragged.'" required><?= e($prompt ?? '') ?></textarea>
                <small>If an attempt fails syntax, namespace, or forbidden-API validation, you'll be asked whether to spend another attempt (up to <?= (int) ART_PIECE_MAX_ATTEMPTS ?> total) with the AI's own previous response as repair context.</small>
            </div>

            <div class="field" id="media-refs-field">
                <label>Media references <span style="font-weight:400;color:var(--ink-soft);">(optional)</span></label>
                <small>Pick specific uploaded media instead of naming it in the prompt, and say how each one should be used. A 3D model (GLB/GLTF) requires the Three.js or A-Frame engine above — incompatible selections are rejected before generation runs.</small>
                <div id="media-refs-list" style="margin-top:0.5rem;display:flex;flex-direction:column;gap:0.5rem;"></div>
                <button type="button" class="admin-btn admin-btn-ghost" id="media-refs-add-btn" style="margin-top:0.5rem;">+ Add media reference</button>
                <input type="hidden" id="media_refs_json" name="media_refs_json" value="[]">
            </div>
            <script>
            (function () {
                var listEl = document.getElementById('media-refs-list');
                var addBtn = document.getElementById('media-refs-add-btn');
                var jsonField = document.getElementById('media_refs_json');
                var refs = [];

                function sync() {
                    jsonField.value = JSON.stringify(refs.map(function (r) {
                        return { media_id: r.media_id, intent_text: r.intent_text };
                    }));
                }

                function fileTypeLabel(mimeType, originalName) {
                    if (originalName && originalName.lastIndexOf('.') > -1) {
                        return originalName.slice(originalName.lastIndexOf('.') + 1).toUpperCase();
                    }
                    if (mimeType === 'model/gltf-binary') return 'GLB';
                    if (mimeType === 'model/gltf+json') return 'GLTF';
                    if (!mimeType) return 'File';
                    var parts = mimeType.split('/');
                    return (parts[1] || parts[0] || 'file').toUpperCase();
                }

                function refLabel(ref) {
                    var title = (ref.name || '').trim() || 'Untitled';
                    var type = fileTypeLabel(ref.mimeType, ref.originalName);
                    return 'Media ID: ' + ref.media_id + ' - ' + title + ' (' + type + ')';
                }

                function render() {
                    listEl.innerHTML = '';
                    refs.forEach(function (ref, index) {
                        var row = document.createElement('div');
                        row.style.cssText = 'display:flex;flex-direction:column;gap:0.4rem;padding:0.5rem;border:1px solid var(--border-soft, #ddd);border-radius:6px;';

                        var label = document.createElement('div');
                        // CSS-truncated, not JS-truncated: the title stays as
                        // long as the row's width allows (which varies by
                        // viewport) and only gets an ellipsis if it actually
                        // overflows, rather than a fixed character count that
                        // wastes space on wide screens or still overflows on
                        // narrow ones.
                        label.style.cssText = 'font-size:0.85rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%;';
                        label.textContent = refLabel(ref);
                        label.title = refLabel(ref);
                        row.appendChild(label);

                        var intentInput = document.createElement('input');
                        intentInput.type = 'text';
                        intentInput.placeholder = 'How should this be used? e.g. "center the composition on this 3D model"';
                        intentInput.value = ref.intent_text || '';
                        intentInput.style.cssText = 'width:100%;';
                        intentInput.addEventListener('input', function () {
                            refs[index].intent_text = intentInput.value;
                            sync();
                        });
                        row.appendChild(intentInput);

                        var removeRow = document.createElement('div');
                        removeRow.style.cssText = 'display:flex;justify-content:flex-end;';
                        var removeBtn = document.createElement('button');
                        removeBtn.type = 'button';
                        removeBtn.className = 'admin-btn admin-btn-ghost';
                        removeBtn.textContent = 'Remove';
                        removeBtn.addEventListener('click', function () {
                            refs.splice(index, 1);
                            render();
                            sync();
                        });
                        removeRow.appendChild(removeBtn);
                        row.appendChild(removeRow);

                        listEl.appendChild(row);
                    });
                }

                addBtn.addEventListener('click', function () {
                    if (!window.openMediaPicker) return;
                    window.openMediaPicker(function (result) {
                        if (!result || !result.id) return;
                        refs.push({
                            media_id: result.id,
                            intent_text: '',
                            name: result.alt || '',
                            mimeType: result.mime_type || '',
                            originalName: '',
                        });
                        render();
                        sync();
                    }, 'select', { mode: 'art_media' });
                });

                sync();
            })();
            </script>

            <?php if (function_exists('art_piece_sonic_params_supported') && art_piece_sonic_params_supported()): ?>
            <div class="field" id="sound-field">
                <label class="sound-toggle-row">
                    <span class="sound-toggle">
                        <input type="checkbox" id="sound_enabled" name="sound_enabled" value="1">
                        <span class="sound-toggle-track"><span class="sound-toggle-thumb"></span></span>
                    </span>
                    <span class="sound-toggle-text">Add instrumentation</span>
                </label>
                <small>Turns camera movement in the immersive view into Tone.js sound. Works with any piece type; heard only in the immersive view.</small>
                <div id="sound-feel-wrap" style="margin-top:0.75rem;display:none;">
                    <label for="sound_feel">Describe the feel (optional)</label>
                    <textarea id="sound_feel" name="sound_feel" rows="2" maxlength="400" placeholder="E.g. 'ethereal, slow, minor pentatonic on a soft synth around 70 BPM'. Name a scale, instrument, or tempo if you like; the AI approximates anything unavailable."></textarea>
                </div>
            </div>
            <style>
                .sound-toggle-row { display:flex; align-items:center; gap:0.6rem; cursor:pointer; font-weight:600; user-select:none; }
                .sound-toggle { position:relative; display:inline-flex; flex:0 0 auto; width:42px; height:24px; }
                /* Absolutely positioned + sized to the 42px switch so the admin
                   form's `input { width:100% }` rule can't blow the box up. */
                .sound-toggle input { position:absolute; inset:0; width:100%; height:100%; margin:0; opacity:0; cursor:pointer; z-index:1; }
                .sound-toggle-track { position:absolute; inset:0; border-radius:999px; background:var(--line, rgba(120,120,120,0.55)); transition:background .15s ease; }
                .sound-toggle-thumb { position:absolute; top:2px; left:2px; width:20px; height:20px; border-radius:50%; background:#fff; box-shadow:0 1px 2px rgba(0,0,0,0.35); transition:transform .15s ease; }
                .sound-toggle input:checked + .sound-toggle-track { background:var(--accent, #3b82f6); }
                .sound-toggle input:checked + .sound-toggle-track .sound-toggle-thumb { transform:translateX(18px); }
                .sound-toggle input:focus-visible + .sound-toggle-track { outline:2px solid var(--accent, #3b82f6); outline-offset:2px; }
            </style>
            <script>
                (function () {
                    var toggle = document.getElementById('sound_enabled');
                    var wrap = document.getElementById('sound-feel-wrap');
                    if (toggle && wrap) {
                        toggle.addEventListener('change', function () {
                            wrap.style.display = this.checked ? 'block' : 'none';
                        });
                    }
                })();
            </script>
            <?php endif ?>

            <div class="form-actions" style="margin-top: 2rem;">
                <div id="generate-status" class="form-status" role="status" aria-live="polite" style="display:none; width:100%; margin-bottom:0.75rem;"></div>
                <button type="button" class="admin-btn" id="generate-submit-btn">Start AI Generation</button>
                <button type="button" class="admin-btn admin-btn-ghost" id="generate-cancel-btn">Cancel</button>
            </div>
        </form>

        <!-- Inline persona creation dialog -->
        <dialog id="persona-dialog" style="padding:1.5rem;border:2px solid var(--line);background:var(--paper);color:var(--ink);max-width:560px;width:100%;">
            <h2 style="margin-top:0;font-size:1.1rem;">Create AI Persona</h2>
            <div id="persona-dialog-error" hidden style="padding:0.5rem 0.75rem;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.4);border-radius:4px;margin-bottom:1rem;font-size:0.875rem;color:#dc2626;"></div>
            <div class="field">
                <label for="dlg-persona-name">Persona Name</label>
                <input id="dlg-persona-name" type="text" maxlength="128" placeholder="e.g. Abstract Expressionist">
            </div>
            <div class="field">
                <label for="dlg-persona-prompt">System Prompt</label>
                <textarea id="dlg-persona-prompt" rows="6" maxlength="4000" style="font-family:monospace;font-size:0.875rem;" placeholder="Write the system-level instruction…"></textarea>
            </div>
            <div style="display:flex;gap:0.75rem;margin-top:1rem;">
                <button type="button" id="dlg-persona-save" class="admin-btn">Create &amp; Select</button>
                <button type="button" id="dlg-persona-cancel" class="admin-btn admin-btn-ghost">Cancel</button>
            </div>
        </dialog>

        <!-- Attempt-failed dialog: mirrors form.php's AI Refine retry dialog
             (#refine-attempt-failed-dialog) — one AI attempt per request,
             user decides whether to spend another. -->
        <dialog id="generate-attempt-failed-dialog" class="inline-create-dialog">
            <div class="dialog-header">
                <h2 id="generate-attempt-failed-title">Attempt 1 of <?= (int) ART_PIECE_MAX_ATTEMPTS ?> failed</h2>
            </div>
            <div class="dialog-body">
                <p id="generate-attempt-failed-message"></p>
            </div>
            <div class="dialog-footer">
                <button type="button" class="admin-btn admin-btn-ghost" id="generate-attempt-give-up-btn">Give Up</button>
                <button type="button" class="admin-btn" id="generate-attempt-try-again-btn">Try Again</button>
            </div>
        </dialog>

        <script>
        (function () {
            var profileSel = document.getElementById('profile_id');
            var personaSel = document.getElementById('persona_id');
            var codeWarn   = document.getElementById('code-cap-warning');
            var form       = document.getElementById('generate-form');
            var btn        = document.getElementById('generate-submit-btn');
            var cancelBtn  = document.getElementById('generate-cancel-btn');
            var statusEl   = document.getElementById('generate-status');
            var dialog     = document.getElementById('persona-dialog');

            function checkCodeCap() {
                var opt = profileSel.options[profileSel.selectedIndex];
                var caps = (opt ? opt.dataset.capabilities || '' : '').split(',');
                codeWarn.hidden = caps.includes('code');
            }
            profileSel.addEventListener('change', checkCodeCap);
            checkCodeCap();

            // Persona dialog trigger
            personaSel.addEventListener('change', function () {
                if (this.value === '__new__') {
                    this.value = '';
                    dialog.showModal();
                }
            });
            document.getElementById('dlg-persona-cancel').addEventListener('click', function () {
                dialog.close();
            });
            document.getElementById('dlg-persona-save').addEventListener('click', function () {
                var name   = document.getElementById('dlg-persona-name').value.trim();
                var prompt = document.getElementById('dlg-persona-prompt').value.trim();
                var errEl  = document.getElementById('persona-dialog-error');
                if (!name || !prompt) {
                    errEl.textContent = 'Both name and system prompt are required.';
                    errEl.hidden = false;
                    return;
                }
                errEl.hidden = true;
                var fd = new FormData();
                fd.append('name', name);
                fd.append('system_prompt', prompt);
                fd.append('_format', 'json');
                fetch('/admin/ai-settings/personas/create', {method:'POST', body:fd,
                    headers:{'Accept':'application/json'}})
                    .then(function(r){return r.json();})
                    .then(function(data){
                        if (data.error) { errEl.textContent = data.error; errEl.hidden = false; return; }
                        var opt = document.createElement('option');
                        opt.value = data.persona.id;
                        opt.textContent = data.persona.name;
                        // Insert before "+ Create new persona…"
                        var newOpt = personaSel.querySelector('[value="__new__"]');
                        personaSel.insertBefore(opt, newOpt);
                        personaSel.value = data.persona.id;
                        dialog.close();
                    })
                    .catch(function(){ errEl.textContent = 'Request failed. Please try again.'; errEl.hidden = false; });
            });

            var ART_PIECE_MAX_ATTEMPTS = <?= (int) ART_PIECE_MAX_ATTEMPTS ?>;
            var GENERATE_RETRY_COOLDOWN_SECONDS = 30;
            var isGenerateRequestInFlight = false;
            var activeAbortController = null;

            function formatElapsed(ms) {
                var totalSeconds = Math.floor(ms / 1000);
                var minutes = Math.floor(totalSeconds / 60);
                var seconds = totalSeconds % 60;
                return minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
            }

            function setGenerateStatus(message, isError) {
                if (!statusEl) return;
                statusEl.style.display = 'block';
                statusEl.className = isError ? 'form-status form-status-error' : 'form-status';
                statusEl.textContent = message;
            }

            // One AI attempt per request, mirroring form.php's
            // performRefineAttempt()/handleRefineAttemptFailure() — the
            // client carries attempt number/previous response/last error
            // and decides whether to spend another attempt, instead of the
            // server looping through all attempts inside one long request.
            function performGenerateAttempt(ctx) {
                isGenerateRequestInFlight = true;
                var startedAt = Date.now();

                function renderRunningStatus() {
                    var elapsed = formatElapsed(Date.now() - startedAt);
                    setGenerateStatus('Attempt ' + ctx.attemptNumber + ' of ' + ART_PIECE_MAX_ATTEMPTS + ' - ' + elapsed + ' elapsed');
                }
                renderRunningStatus();
                var timerInterval = setInterval(renderRunningStatus, 1000);

                function stopAndReenable() {
                    clearInterval(timerInterval);
                    isGenerateRequestInFlight = false;
                    activeAbortController = null;
                    btn.disabled = false;
                }

                var fd = new FormData(form);
                fd.set('attempt_number', String(ctx.attemptNumber));
                fd.set('previous_raw_response', ctx.previousRawResponse || '');
                fd.set('last_error', ctx.lastError || '');
                fd.set('sequence_token', ctx.sequenceToken);

                var abortController = new AbortController();
                activeAbortController = abortController;

                fetch(form.dataset.generateUrl || '/admin/pieces/generate', {
                    method: 'POST',
                    body: fd,
                    headers: {'Accept': 'application/json'},
                    signal: abortController.signal
                }).then(function (resp) {
                    return resp.json();
                }).then(function (data) {
                    if (!data.success) {
                        stopAndReenable();
                        handleGenerateAttemptFailure(ctx, data);
                        return;
                    }
                    stopAndReenable();
                    setGenerateStatus('Generation complete. Opening preview...');
                    window.location.href = '/admin/pieces/generate/preview';
                }).catch(function (err) {
                    stopAndReenable();
                    if (err && err.name === 'AbortError') {
                        setGenerateStatus('Generation cancelled.', true);
                    } else {
                        setGenerateStatus(err.message || 'Generation failed.', true);
                    }
                });
            }

            function handleGenerateAttemptFailure(ctx, data) {
                var dialog = document.getElementById('generate-attempt-failed-dialog');
                if (!dialog) {
                    setGenerateStatus(data.error || 'Generation failed.', true);
                    return;
                }

                var titleEl = document.getElementById('generate-attempt-failed-title');
                var messageEl = document.getElementById('generate-attempt-failed-message');
                var tryAgainBtn = document.getElementById('generate-attempt-try-again-btn');
                var giveUpBtn = document.getElementById('generate-attempt-give-up-btn');

                var attemptNumber = data.attempt_number || ctx.attemptNumber;
                var canRetry = data.can_retry !== false && attemptNumber < ART_PIECE_MAX_ATTEMPTS;

                titleEl.textContent = 'Attempt ' + attemptNumber + ' of ' + ART_PIECE_MAX_ATTEMPTS + ' failed';
                messageEl.textContent = data.error || 'Unknown error';
                tryAgainBtn.hidden = !canRetry;

                // Clone-and-replace to drop any listener from a previous
                // failed attempt in this same sequence.
                var newTryAgainBtn = tryAgainBtn.cloneNode(true);
                tryAgainBtn.parentNode.replaceChild(newTryAgainBtn, tryAgainBtn);
                var newGiveUpBtn = giveUpBtn.cloneNode(true);
                giveUpBtn.parentNode.replaceChild(newGiveUpBtn, giveUpBtn);

                var cooldownInterval = null;

                newGiveUpBtn.addEventListener('click', function () {
                    dialog.close();
                    if (cooldownInterval) clearInterval(cooldownInterval);
                    setGenerateStatus('Generation stopped after attempt ' + attemptNumber + '.', true);
                });

                if (canRetry) {
                    newTryAgainBtn.addEventListener('click', function () {
                        if (isGenerateRequestInFlight) return;
                        dialog.close();
                        if (cooldownInterval) clearInterval(cooldownInterval);
                        btn.disabled = true;
                        performGenerateAttempt({
                            sequenceToken: ctx.sequenceToken,
                            attemptNumber: attemptNumber + 1,
                            previousRawResponse: data.raw_response || null,
                            lastError: data.error || null
                        });
                    });

                    var cooldownRemaining = GENERATE_RETRY_COOLDOWN_SECONDS;
                    newTryAgainBtn.disabled = true;
                    newTryAgainBtn.textContent = 'Try Again (' + cooldownRemaining + 's)';
                    cooldownInterval = setInterval(function () {
                        cooldownRemaining--;
                        if (cooldownRemaining <= 0) {
                            clearInterval(cooldownInterval);
                            newTryAgainBtn.disabled = false;
                            newTryAgainBtn.textContent = 'Try Again';
                        } else {
                            newTryAgainBtn.textContent = 'Try Again (' + cooldownRemaining + 's)';
                        }
                    }, 1000);
                }

                dialog.showModal();
            }

            btn.addEventListener('click', function () {
                if (!form || !btn) return;
                if (!form.reportValidity()) return;
                if (isGenerateRequestInFlight) return;

                btn.disabled = true;
                var sequenceToken = (window.crypto && window.crypto.randomUUID)
                    ? window.crypto.randomUUID()
                    : ('seq-' + Date.now() + '-' + Math.random().toString(36).slice(2));

                performGenerateAttempt({
                    sequenceToken: sequenceToken,
                    attemptNumber: 1,
                    previousRawResponse: null,
                    lastError: null
                });
            });

            cancelBtn.addEventListener('click', function () {
                // No server round-trip needed: a single attempt is now at
                // most one AI call, and there's no multi-attempt loop on
                // the server left to interrupt. Aborting the in-flight
                // fetch just stops the UI from waiting on it.
                if (activeAbortController) {
                    activeAbortController.abort();
                }
                window.location.href = '/admin/pieces';
            });
        })();
        </script>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();

require dirname(__DIR__) . '/layout.php';
