<?php
$pageTitle = 'Setup Checklist — ' . app_site_name() . ' Admin';
ob_start();
?>
<div class="admin-section">
    <h1 class="admin-heading">Setup Checklist</h1>
    <p class="admin-hint">
        Onboarding steps for a new deployment. Nothing here blocks the
        site &mdash; the public setup gate already lifted once you signed in.
    </p>
    <ul class="admin-list" style="list-style: none; padding: 0;">
        <?php foreach ($checklist as $item): ?>
            <li class="admin-list-item" style="display: flex; align-items: baseline; gap: 0.75rem; padding: 0.75rem 0; border-bottom: 1px solid var(--line);">
                <span aria-hidden="true" style="font-size: 1.1rem;"><?= $item['done'] ? '✅' : '⬜' ?></span>
                <span style="flex: 1;">
                    <strong><?= e($item['label']) ?></strong>
                    <br>
                    <span class="admin-hint"><?= e($item['detail']) ?></span>
                </span>
                <?php if (!$item['done'] && $item['href']): ?>
                    <a class="admin-link" href="<?= e($item['href']) ?>">Configure</a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
