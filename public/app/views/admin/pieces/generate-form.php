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
        <form method="post" action="/admin/pieces/generate" class="admin-form">
            <div class="field">
                <label for="profile_id">AI Profile / Vendor &amp; Model</label>
                <select id="profile_id" name="profile_id" required>
                    <option value="">-- Select Active AI Profile --</option>
                    <?php foreach ($profiles as $prof): ?>
                        <option value="<?= (int) $prof['id'] ?>" <?= (int) ($selectedProfileId ?? 0) === (int) $prof['id'] ? 'selected' : '' ?>>
                            <?= e($prof['profile_name']) ?> (<?= e($prof['vendor']) ?>: <?= e($prof['model']) ?>) &mdash; By <?= e($prof['user_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="engine">Art Engine / Runtime Environment</label>
                <select id="engine" name="engine" required>
                    <option value="p5" <?= ($engine ?? 'p5') === 'p5' ? 'selected' : '' ?>>P5.js (Interactive canvas drawing)</option>
                    <option value="c2" <?= ($engine ?? '') === 'c2' ? 'selected' : '' ?>>C2.js (Physics-based drawing &amp; geometry)</option>
                    <option value="three" <?= ($engine ?? '') === 'three' ? 'selected' : '' ?>>Three.js (3D WebGL scenes &amp; lights)</option>
                    <option value="svg" <?= ($engine ?? '') === 'svg' ? 'selected' : '' ?>>SVG (Vector paths &amp; CSS animation)</option>
                </select>
            </div>

            <div class="field">
                <label for="prompt">Creative Prompt</label>
                <textarea id="prompt" name="prompt" rows="6" placeholder="Describe the visual effects, interaction, behavior, colors, and layout of the piece. E.g. 'A cascading waterfall of particles that bounce off obstacles when the mouse is dragged.'" required><?= e($prompt ?? '') ?></textarea>
                <small>The generation engine will trigger a validation/retry repair loop up to 3 times to correct syntax, namespace conflicts, or forbidden API behaviors.</small>
            </div>

            <div class="form-actions" style="margin-top: 2rem;">
                <button type="submit" class="admin-btn" id="generate-submit-btn">Start AI Generation Loop</button>
                <a href="/admin/pieces" class="admin-btn admin-btn-ghost">Cancel</a>
            </div>
        </form>

        <script>
        document.querySelector('.admin-form').addEventListener('submit', function () {
            var btn = document.getElementById('generate-submit-btn');
            if (btn) {
                btn.disabled = true;
                btn.innerText = 'Generating & Validating (this may take up to 60s)...';
            }
        });
        </script>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();

require dirname(__DIR__) . '/layout.php';
