<?php
$isEdit = $section !== null;
$section = $section ?? [];
$pageTitle = ($isEdit ? 'Edit Section' : 'New Section') . ' — ' . app_site_name() . ' Admin';
$needsEditor = true;
ob_start();
?>
<div class="admin-section">
    <div class="admin-section-head">
        <h1 class="admin-heading"><?= $isEdit ? 'Edit Section' : 'New Section' ?></h1>
        <a href="/admin/pages/<?= (int) $page['id'] ?>/edit" class="admin-btn admin-btn-ghost">Back to Page</a>
    </div>

    <?php if ($sectionError ?? null): ?>
        <p class="admin-error"><?= htmlspecialchars($sectionError, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif ?>

    <form
        method="POST"
        action="<?= $isEdit ? '/admin/pages/sections/' . (int) $section['id'] . '/edit' : '/admin/pages/' . (int) $page['id'] . '/sections/create' ?>"
        class="admin-form"
    >
        <div class="form-row">
            <label for="section-heading">Heading <span class="form-hint">(leave blank for an opening section with no heading)</span></label>
            <input id="section-heading" type="text" name="heading" value="<?= htmlspecialchars($section['heading'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-row">
            <label for="section-wrapper-class">Section style <span class="form-hint">(wraps this section in a styled container)</span></label>
            <select id="section-wrapper-class" name="wrapper_class">
                <?php
                $currentWrapper = $section['wrapper_class'] ?? '';
                $wrapperOptions = [
                    ''                => 'None — raw output',
                    'mission-band'    => 'Mission band — styled accent band',
                    'callout'         => 'Callout — same accent, alternate name',
                    'content-cards'   => 'Content cards — 3-column card grid (wrap each item in <div class="content-card">)',
                    'managed-section' => 'Standard section box (border + shadow)',
                ];
                foreach ($wrapperOptions as $val => $label):
                ?>
                    <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>"<?= $currentWrapper === $val ? ' selected' : ''?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label for="section-content">Content *</label>
            <textarea id="section-content" name="content" rows="10" required data-tiptap><?= htmlspecialchars($section['content'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
        <div class="form-actions">
            <button type="submit" class="admin-btn"><?= $isEdit ? 'Save Section' : 'Add Section' ?></button>
            <a href="/admin/pages/<?= (int) $page['id'] ?>/edit" class="admin-btn admin-btn-ghost">Cancel</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
