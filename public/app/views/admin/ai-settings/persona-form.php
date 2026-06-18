<?php

declare(strict_types=1);

$isEdit = !empty($persona['id']);
$pageTitle = $isEdit ? 'Edit AI Persona' : 'Create AI Persona';

ob_start();
$persona = $persona ?? [];
?>
<div class="admin-container">
    <div class="admin-header-row">
        <h1><?= $isEdit ? 'Edit AI Persona' : 'Create AI Persona' ?></h1>
        <a href="/admin/ai-settings?tab=personas" class="admin-btn admin-btn-ghost">Back</a>
    </div>

    <p class="admin-copy">
        A persona is a named system prompt that prepends to the user's piece generation prompt.
        When selected, the AI receives: <em>{persona system prompt}</em> followed by
        <em>"Apply this to the following prompt: {user prompt}"</em>.
    </p>

    <?php if ($error ?? null): ?>
        <div class="form-status form-status-error" role="alert">
            <p><?= e($error) ?></p>
        </div>
    <?php endif; ?>

    <form method="post" class="admin-form">
        <div class="field">
            <label for="persona-name">Persona Name</label>
            <input id="persona-name" name="name" type="text" required maxlength="128"
                   value="<?= e($persona['name'] ?? '') ?>"
                   placeholder="e.g. Abstract Expressionist, Minimal Brutalist">
        </div>
        <div class="field">
            <label for="persona-prompt">System Prompt</label>
            <textarea id="persona-prompt" name="system_prompt" rows="10" required maxlength="4000"
                      style="font-family: monospace; font-size: 0.875rem;"
                      placeholder="Write the system-level instruction that shapes how the AI interprets generation prompts. This text will be sent to the model before the user's prompt."><?= e($persona['system_prompt'] ?? '') ?></textarea>
            <small>Maximum 4,000 characters. This is sent as the user message prefix — not as an API system role.</small>
        </div>
        <div class="form-actions">
            <button type="submit" class="admin-btn"><?= $isEdit ? 'Update' : 'Create' ?> Persona</button>
            <a href="/admin/ai-settings?tab=personas" class="admin-btn admin-btn-ghost">Cancel</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();

require dirname(__DIR__) . '/layout.php';
