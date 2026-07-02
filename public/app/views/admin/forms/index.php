<?php
$pageTitle = 'Forms';
$formsTableReady = $formsTableReady ?? true;
ob_start();
?>
<div class="admin-section">
    <div class="admin-section-head">
        <div>
            <p class="eyebrow">Forms</p>
            <h1>Forms</h1>
        </div>
        <?php if ($formsTableReady): ?>
            <a href="/admin/forms/create" class="admin-btn">New Form</a>
        <?php endif; ?>
    </div>
    <?php if (!$formsTableReady): ?>
        <p class="admin-empty">Forms are not installed in this database yet. Run the database setup script to create and seed the default Contact Form and Newsletter Signup.</p>
    <?php elseif (empty($forms)): ?>
        <p class="admin-empty">No forms yet.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead><tr><th>Form</th><th>Type</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($forms as $form): ?>
                <tr>
                    <td><strong><?= e($form['title']) ?></strong><br><code><?= e($form['form_key']) ?></code></td>
                    <td><?= e($form['form_type']) ?></td>
                    <td><?= e($form['status']) ?></td>
                    <td><a href="/admin/forms/<?= (int) $form['id'] ?>/edit" class="admin-link">Edit</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../layout.php'; ?>
