<?php

declare(strict_types=1);

$pageTitle = 'Versions — ' . ($piece['title'] ?? 'Piece');

ob_start();
?>
<div class="admin-container">
    <div class="admin-header-row">
        <h1>Versions: <?= e($piece['title'] ?? 'Untitled') ?></h1>
        <div>
            <a href="/admin/pieces/<?= (int) $piece['id'] ?>/versions/create" class="admin-btn">Add Version</a>
            <a href="/admin/pieces" class="admin-btn admin-btn-ghost">Back</a>
        </div>
    </div>

    <?php if (empty($versions)): ?>
        <p>No versions yet. <a href="/admin/pieces/<?= (int) $piece['id'] ?>/versions/create">Add the first version</a>.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Version</th>
                    <th>Engine</th>
                    <th>Vendor</th>
                    <th>Model</th>
                    <th>AI Profile</th>
                    <th>AI Persona</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Current</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($versions as $version): ?>
                    <?php
                        $isCurrent = (int) ($piece['current_version_id'] ?? 0) === (int) $version['id'];
                        $isDraftAttempt = (int) ($version['is_draft_attempt'] ?? 0) === 1;
                    ?>
                    <tr<?= $isDraftAttempt ? ' class="version-row-draft-attempt"' : '' ?>>
                        <td><?= (int) $version['version_number'] ?></td>
                        <td><?= e(art_piece_effective_generation_mode_label($piece, $version)) ?></td>
                        <td><?= e($version['generation_vendor'] ?? '—') ?></td>
                        <td><?= e($version['generation_model'] ?? '—') ?></td>
                        <td><?= e($version['ai_profile_name'] ?? '(Blank)') ?></td>
                        <td><?= e($version['ai_persona_name'] ?? '(Blank)') ?></td>
                        <td>
                            <?php if ($isDraftAttempt): ?>
                                <span class="status-badge status-failed_attempt" title="An AI Refine attempt — viewable and editable, but can never become the current version.">Draft attempt — not revertible</span>
                            <?php endif; ?>
                            <span class="status-badge status-<?= e($version['validation_status'] ?? 'validated') ?>">
                                <?= e($version['validation_status'] ?? 'validated') ?>
                            </span>
                        </td>
                        <td><?= e($version['created_at'] ?? '') ?></td>
                        <td>
                            <?php if ($isCurrent): ?>
                                <strong>Yes</strong>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="/admin/pieces/<?= (int) $piece['id'] ?>/versions/<?= (int) $version['id'] ?>/edit" class="admin-link">Edit</a>
                            <a href="/immersive/pieces/<?= (int) $piece['id'] ?>?version=<?= (int) $version['id'] ?>" target="_blank" rel="noopener" class="admin-link">Preview</a>
                            <?php if (!$isCurrent && !$isDraftAttempt): ?>
                                <form method="post" action="/admin/pieces/<?= (int) $piece['id'] ?>/versions/<?= (int) $version['id'] ?>/set-current" class="inline-form" onsubmit="return confirm('Revert to version <?= (int) $version['version_number'] ?>? The current code will be replaced by this version\'s.')">
                                    <button type="submit" class="admin-link">Revert</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="/admin/pieces/<?= (int) $piece['id'] ?>/versions/<?= (int) $version['id'] ?>/fork" class="inline-form" onsubmit="return confirm('Create a brand new, independent piece starting from this version\'s code?')">
                                <button type="submit" class="admin-link">Fork as New Piece</button>
                            </form>
                            <form method="post" action="/admin/pieces/<?= (int) $piece['id'] ?>/versions/<?= (int) $version['id'] ?>/delete" class="inline-form" onsubmit="return confirm('Delete this version permanently?')">
                                <button type="submit" class="admin-link danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();

require dirname(__DIR__) . '/layout.php';
