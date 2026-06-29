<?php

declare(strict_types=1);

$pageTitle = 'Generate Piece with AI';
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

    <?php if (empty($profiles)): ?>
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
                    <optgroup label="Stable engines">
                        <option value="p5" <?= ($generationMode ?? $engine ?? 'p5') === 'p5' ? 'selected' : '' ?>>P5.js (Interactive canvas drawing)</option>
                        <option value="c2" <?= ($generationMode ?? $engine ?? '') === 'c2' ? 'selected' : '' ?>>C2.js (Animated drawing &amp; geometry)</option>
                        <option value="c2_interactive" <?= ($generationMode ?? '') === 'c2_interactive' ? 'selected' : '' ?>>C2.js Interactive (Click, touch &amp; drag)</option>
                        <option value="three" <?= ($generationMode ?? $engine ?? '') === 'three' ? 'selected' : '' ?>>Three.js (3D WebGL scenes &amp; lights)</option>
                        <option value="svg" <?= ($generationMode ?? $engine ?? '') === 'svg' ? 'selected' : '' ?>>SVG (Vector paths &amp; CSS animation)</option>
                    </optgroup>
                    <optgroup label="Experimental engines">
                        <option value="aframe" <?= ($generationMode ?? $engine ?? '') === 'aframe' ? 'selected' : '' ?>>A-Frame Experimental (Self-contained 3D scene)</option>
                    </optgroup>
                </select>
            </div>

            <div class="field">
                <label for="prompt">Creative Prompt</label>
                <textarea id="prompt" name="prompt" rows="6" placeholder="Describe the visual effects, interaction, behavior, colors, and layout of the piece. E.g. 'A cascading waterfall of particles that bounce off obstacles when the mouse is dragged.'" required><?= e($prompt ?? '') ?></textarea>
                <small>The generation engine will trigger a validation/retry repair loop up to <?= (int) ART_PIECE_MAX_ATTEMPTS ?> times to correct syntax, namespace conflicts, or forbidden API behaviors.</small>
            </div>

            <div class="form-actions" style="margin-top: 2rem;">
                <div id="generate-status" class="form-status" role="status" aria-live="polite" style="display:none; width:100%; margin-bottom:0.75rem;"></div>
                <button type="button" class="admin-btn" id="generate-submit-btn">Start AI Generation Loop</button>
                <a href="/admin/pieces" class="admin-btn admin-btn-ghost">Cancel</a>
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

        <script>
        (function () {
            var profileSel = document.getElementById('profile_id');
            var personaSel = document.getElementById('persona_id');
            var codeWarn   = document.getElementById('code-cap-warning');
            var form       = document.getElementById('generate-form');
            var btn        = document.getElementById('generate-submit-btn');
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

            function parseJsonResponse(resp) {
                return resp.json().then(function (data) {
                    if (!resp.ok || !data.success) {
                        throw new Error(data.error || 'Generation failed.');
                    }
                    return data;
                });
            }

            btn.addEventListener('click', function () {
                if (!form || !btn) return;
                if (!form.reportValidity()) return;

                btn.disabled = true;
                var startedAt = Date.now();
                var currentAttempt = null;
                var maxAttempts = <?= (int) ART_PIECE_MAX_ATTEMPTS ?>;
                var timerInterval = null;
                var progressInterval = null;

                function renderRunningStatus() {
                    var elapsed = formatElapsed(Date.now() - startedAt);
                    if (currentAttempt) {
                        setGenerateStatus('Attempt ' + currentAttempt + ' of ' + maxAttempts + ' - ' + elapsed + ' elapsed');
                    } else {
                        setGenerateStatus('Starting generation - ' + elapsed + ' elapsed');
                    }
                }

                function stopIntervals() {
                    if (timerInterval) {
                        clearInterval(timerInterval);
                        timerInterval = null;
                    }
                    if (progressInterval) {
                        clearInterval(progressInterval);
                        progressInterval = null;
                    }
                }

                function pollProgress() {
                    fetch('/admin/pieces/generate/progress', {
                        headers: {'Accept': 'application/json'}
                    }).then(function (resp) {
                        if (!resp.ok) return null;
                        return resp.json();
                    }).then(function (data) {
                        if (!data) return;
                        if (data.attempt) currentAttempt = data.attempt;
                        if (data.max_attempts) maxAttempts = data.max_attempts;
                        renderRunningStatus();
                    }).catch(function () {
                        // The generation request itself is authoritative.
                    });
                }

                renderRunningStatus();
                timerInterval = setInterval(renderRunningStatus, 1000);
                progressInterval = setInterval(pollProgress, 1500);
                pollProgress();

                fetch(form.dataset.generateUrl || '/admin/pieces/generate', {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {'Accept': 'application/json'}
                }).then(parseJsonResponse).then(function () {
                    stopIntervals();
                    setGenerateStatus('Generation complete. Opening preview...');
                    window.location.href = '/admin/pieces/generate/preview';
                }).catch(function (err) {
                    stopIntervals();
                    setGenerateStatus(err.message || 'Generation failed.', true);
                    btn.disabled = false;
                });
            });
        })();
        </script>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();

require dirname(__DIR__) . '/layout.php';
