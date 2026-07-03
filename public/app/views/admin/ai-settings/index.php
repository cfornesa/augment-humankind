<?php

declare(strict_types=1);

$pageTitle = 'AI Settings';

ob_start();
$tab = $_GET['tab'] ?? 'profiles';
if (!in_array($tab, ['profiles', 'keys', 'vendor', 'personas'], true)) {
    $tab = 'profiles';
}
$error = $_GET['error'] ?? null;
$success = $_GET['success'] ?? null;
?>
<div class="admin-container">
    <div class="admin-header-row">
        <h1>AI Settings</h1>
    </div>

    <?php if ($error): ?>
        <div class="form-status form-status-error" role="alert"><p><?= e($error) ?></p></div>
    <?php endif; ?>
    <?php if ($success === 'vendor'): ?>
        <div class="form-status" role="status"><p>Preferred AI vendors updated.</p></div>
    <?php endif; ?>

    <nav class="admin-tabs" aria-label="AI settings tabs">
        <a href="/admin/ai-settings?tab=profiles" class="admin-tab <?= $tab === 'profiles' ? 'active' : '' ?>">AI Profiles</a>
        <a href="/admin/ai-settings?tab=keys" class="admin-tab <?= $tab === 'keys' ? 'active' : '' ?>">API Keys</a>
        <a href="/admin/ai-settings?tab=vendor" class="admin-tab <?= $tab === 'vendor' ? 'active' : '' ?>">AI Vendor</a>
        <a href="/admin/ai-settings?tab=personas" class="admin-tab <?= $tab === 'personas' ? 'active' : '' ?>">AI Personas</a>
    </nav>

    <?php if ($tab === 'profiles'): ?>
        <?php if (!$capabilitiesSchemaSupported): ?>
            <div class="form-status" role="status"><p>AI profile capability flags are not stored in this database yet. The runtime is inferring capabilities from vendor/model metadata until `docs/migrations/2026-06-18-ai-personas.sql` is applied.</p></div>
        <?php endif; ?>
        <p class="admin-copy">Profiles define which vendor, transport, and model a workflow should use.</p>
        <a href="/admin/user-profiles/settings/create" class="admin-btn" style="margin-bottom:1rem;display:inline-block;">Add AI Profile</a>
        <?php if (empty($settings)): ?>
            <p>No AI vendor settings configured.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr><th>User</th><th>Vendor</th><th>Profile</th><th>Model</th><th>Enabled</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($settings as $s): ?>
                        <tr>
                            <td><?= e($s['user_name'] ?? '') ?></td>
                            <td><?= e($s['vendor']) ?></td>
                            <td><?= e($s['profile_name'] ?? 'Default') ?></td>
                            <td><?= e($s['model'] ?? '') ?></td>
                            <td><?= (int) ($s['enabled'] ?? 0) ? 'Yes' : 'No' ?></td>
                            <td>
                                <a href="/admin/user-profiles/settings/<?= (int) $s['id'] ?>/edit" class="admin-link">Edit</a>
                                <form method="post" action="/admin/user-profiles/settings/<?= (int) $s['id'] ?>/delete" class="inline-form" onsubmit="return confirm('Delete this profile?')">
                                    <button type="submit" class="admin-link danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php elseif ($tab === 'keys'): ?>
        <p class="admin-copy">API keys are encrypted at rest and attached to vendors, not individual profiles.</p>
        <a href="/admin/user-profiles/keys/create" class="admin-btn" style="margin-bottom:1rem;display:inline-block;">Add API Key</a>
        <?php if (empty($keys)): ?>
            <p>No API keys stored.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr><th>User</th><th>Vendor</th><th>Key</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($keys as $k): ?>
                        <tr>
                            <td><?= e($k['user_name'] ?? '') ?></td>
                            <td><?= e($k['vendor']) ?></td>
                            <td><code>encrypted</code></td>
                            <td>
                                <a href="/admin/user-profiles/keys/<?= (int) $k['id'] ?>/edit" class="admin-link">Edit</a>
                                <form method="post" action="/admin/user-profiles/keys/<?= (int) $k['id'] ?>/delete" class="inline-form" onsubmit="return confirm('Delete this key?')">
                                    <button type="submit" class="admin-link danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php elseif ($tab === 'personas'): ?>
        <?php if (!$personasSchemaSupported): ?>
            <div class="form-status" role="status"><p>The `ai_personas` table is not present yet. Apply `docs/migrations/2026-06-18-ai-personas.sql` to create and store personas.</p></div>
        <?php endif; ?>
        <p class="admin-copy">Personas are named system prompts that shape how the AI interprets piece generation prompts. Select one in the generation form to prepend it automatically.</p>
        <a href="/admin/ai-settings/personas/create" class="admin-btn" style="margin-bottom:1rem;display:inline-block;">Create Persona</a>
        <?php if (empty($personas)): ?>
            <p>No personas created yet.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr><th>Name</th><th>System Prompt</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($personas as $p): ?>
                        <tr>
                            <td style="white-space: nowrap;"><?= e($p['name']) ?></td>
                            <td style="max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--ink-soft); font-size: 0.875rem;">
                                <?= e(mb_substr($p['system_prompt'], 0, 120)) ?><?= mb_strlen($p['system_prompt']) > 120 ? '…' : '' ?>
                            </td>
                            <td style="white-space: nowrap;">
                                <a href="/admin/ai-settings/personas/<?= (int) $p['id'] ?>/edit" class="admin-link">Edit</a>
                                <form method="post" action="/admin/ai-settings/personas/<?= (int) $p['id'] ?>/delete" class="inline-form" onsubmit="return confirm('Delete this persona?')">
                                    <button type="submit" class="admin-link danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php else: ?>
        <p class="admin-copy">Preferred AI vendors are set by choosing the profile each workflow should use by default.</p>
        <form method="post" action="/admin/ai-settings/vendor" class="admin-form">
            <div class="field">
                <label for="preferred_art_piece_profile_id">Art Piece Generation</label>
                <select id="preferred_art_piece_profile_id" name="preferred_art_piece_profile_id">
                    <option value="">— None —</option>
                    <?php foreach ($profiles as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= ((int) ($owner['preferred_art_piece_profile_id'] ?? 0) === (int) $p['id']) ? 'selected' : '' ?>>
                            <?= e($p['profile_name'] ?? 'Default') ?> (<?= e($p['vendor']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="ai_theme_default_profile_id">Theme Generation</label>
                <select id="ai_theme_default_profile_id" name="ai_theme_default_profile_id">
                    <option value="">— None —</option>
                    <?php foreach ($profiles as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= ((int) ($themeDefaultProfileId ?? 0) === (int) $p['id']) ? 'selected' : '' ?>>
                            <?= e($p['profile_name'] ?? 'Default') ?> (<?= e($p['vendor']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="admin-hint">Preselected in Site Identity → Design → AI Assist.</p>
            </div>
            <div class="field">
                <label for="preferred_text_improve_profile_id">Text Improvement</label>
                <select id="preferred_text_improve_profile_id" name="preferred_text_improve_profile_id">
                    <option value="">— None —</option>
                    <?php foreach ($profiles as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= ((int) ($owner['preferred_text_improve_profile_id'] ?? 0) === (int) $p['id']) ? 'selected' : '' ?>>
                            <?= e($p['profile_name'] ?? 'Default') ?> (<?= e($p['vendor']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="preferred_alt_text_profile_id">Alt Text Generation</label>
                <select id="preferred_alt_text_profile_id" name="preferred_alt_text_profile_id">
                    <option value="">— None —</option>
                    <?php foreach ($profiles as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= ((int) ($owner['preferred_alt_text_profile_id'] ?? 0) === (int) $p['id']) ? 'selected' : '' ?>>
                            <?= e($p['profile_name'] ?? 'Default') ?> (<?= e($p['vendor']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="admin-btn">Save Preferred Vendors</button>
            </div>
        </form>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();

require dirname(__DIR__) . '/layout.php';
