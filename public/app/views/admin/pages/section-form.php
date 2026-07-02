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
        <?php $sectionKind = $section['section_kind'] ?? 'content'; ?>
        <div class="form-row">
            <label for="section-kind">Section kind</label>
            <select id="section-kind" name="section_kind" <?= !empty($section['is_required']) ? 'disabled' : '' ?>>
                <option value="content" <?= $sectionKind === 'content' ? 'selected' : '' ?>>Content</option>
                <option value="form" <?= $sectionKind === 'form' ? 'selected' : '' ?>>Form</option>
            </select>
            <?php if (!empty($section['is_required'])): ?>
                <input type="hidden" name="section_kind" value="<?= htmlspecialchars($sectionKind, ENT_QUOTES, 'UTF-8') ?>">
                <p class="admin-hint">This required section is protected and cannot be converted or deleted.</p>
            <?php endif; ?>
        </div>
        <div class="form-row">
            <label for="section-heading">Heading <span class="form-hint">(leave blank for an opening section with no heading)</span></label>
            <input id="section-heading" type="text" name="heading" value="<?= htmlspecialchars($section['heading'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-row section-form-field" <?= $sectionKind === 'form' ? '' : 'hidden' ?>>
            <label for="section-form-id">Form</label>
            <select id="section-form-id" name="form_id">
                <option value="">Choose a form</option>
                <?php foreach (($forms ?? []) as $availableForm): ?>
                    <option value="<?= (int) $availableForm['id'] ?>" <?= (int) ($section['form_id'] ?? 0) === (int) $availableForm['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($availableForm['title'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
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
        <div class="form-row section-content-field" <?= $sectionKind === 'form' ? 'hidden' : '' ?>>
            <label for="section-content">Content *</label>
            <textarea id="section-content" name="content" rows="10" required data-tiptap><?= htmlspecialchars($section['content'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
        <div class="form-actions">
            <button type="submit" class="admin-btn"><?= $isEdit ? 'Save Section' : 'Add Section' ?></button>
            <a href="/admin/pages/<?= (int) $page['id'] ?>/edit" class="admin-btn admin-btn-ghost">Cancel</a>
        </div>
    </form>
</div>
<script>
document.getElementById('section-kind')?.addEventListener('change', function () {
    var isForm = this.value === 'form';
    document.querySelector('.section-form-field')?.toggleAttribute('hidden', !isForm);
    document.querySelector('.section-content-field')?.toggleAttribute('hidden', isForm);
    var content = document.getElementById('section-content');
    if (content) content.required = !isForm;
});
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
