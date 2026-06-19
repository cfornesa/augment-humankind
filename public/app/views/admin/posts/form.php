<?php
$isEdit    = ($post['id'] ?? null) !== null;
$pageTitle = ($isEdit ? 'Edit Post' : 'New Post') . ' — ' . app_site_name() . ' Admin';
$needsEditor = true;

$scheduledValue = '';
if (!empty($post['scheduled_at'])) {
    $ts = strtotime($post['scheduled_at']);
    if ($ts !== false) {
        $scheduledValue = date('Y-m-d\TH:i', $ts);
    }
}

$status = $post['status'] ?? 'draft';
$sections = $sections ?? [];

$wrapperOptions = [
    ''                => 'None',
    'mission-band'    => 'Mission band',
    'callout'         => 'Callout',
    'content-cards'   => 'Content cards',
    'managed-section' => 'Standard section box',
];

ob_start();
?>
<div class="admin-section">
    <h1 class="admin-heading"><?= $isEdit ? 'Edit Post' : 'New Post' ?></h1>

    <?php if ($error ?? null): ?>
        <p class="admin-error"><?= htmlspecialchars($error) ?></p>
    <?php endif ?>

    <form
        method="POST"
        enctype="multipart/form-data"
        action="<?= $isEdit ? '/admin/posts/' . $post['id'] . '/edit' : '/admin/posts/create' ?>"
        class="admin-form"
    >
        <div class="form-row">
            <label>Title <span class="form-hint">(optional)</span></label>
            <input type="text" name="title" value="<?= htmlspecialchars($post['title'] ?? '') ?>">
        </div>

        <div class="form-row">
            <label>Sections *</label>
            <div class="sections-manager" id="post-sections-manager">

                <?php foreach ($sections as $i => $section): ?>
                    <details class="section-panel" open>
                        <summary class="section-panel-summary">
                            <span class="section-panel-title"><?= htmlspecialchars($section['heading'] ?: '(no heading)', ENT_QUOTES, 'UTF-8') ?></span>
                            <button type="button" class="section-remove-btn admin-btn admin-btn-ghost admin-btn-sm">Remove</button>
                        </summary>
                        <div class="section-panel-body">
                            <input type="hidden" name="sections[<?= $i ?>][id]" value="<?= htmlspecialchars((string) ($section['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="sections[<?= $i ?>][sort_order]" value="<?= $i ?>">
                            <div class="form-row">
                                <label>Heading <span class="form-hint">(optional)</span></label>
                                <input type="text" name="sections[<?= $i ?>][heading]" value="<?= htmlspecialchars($section['heading'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="section-heading-input">
                            </div>
                            <div class="form-row">
                                <label>Section style</label>
                                <select name="sections[<?= $i ?>][wrapper_class]">
                                    <?php foreach ($wrapperOptions as $val => $label): ?>
                                        <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>"<?= ($section['wrapper_class'] ?? '') === $val ? ' selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                            <div class="form-row">
                                <label>Content *</label>
                                <textarea name="sections[<?= $i ?>][content]" rows="10" data-tiptap><?= htmlspecialchars($section['content'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>
                        </div>
                    </details>
                <?php endforeach ?>

                <button type="button" id="add-section-btn" class="admin-btn admin-btn-ghost">+ Add Section</button>
            </div>
        </div>

        <div class="form-row">
            <label>Status *</label>
            <select name="status" id="post-status">
                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Save as Draft</option>
                <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Publish Now</option>
                <option value="scheduled" <?= $status === 'scheduled' ? 'selected' : '' ?>>Schedule</option>
            </select>
        </div>

        <div class="form-row" id="post-scheduled-row" <?= $status === 'scheduled' ? '' : 'hidden' ?>>
            <label>Scheduled for *</label>
            <input type="datetime-local" name="scheduled_at" value="<?= htmlspecialchars($scheduledValue) ?>">
        </div>

        <!-- Featured image -->
        <fieldset class="form-fieldset">
            <legend>Featured image <span class="form-hint">(optional)</span></legend>
            <div class="media-field-preview" id="post-featured-preview">
                <?php if (!empty($post['featured_image_url'])): ?>
                    <img src="<?= htmlspecialchars($post['featured_image_url']) ?>" alt="">
                <?php endif ?>
            </div>
            <input id="post-featured-url" type="url" name="featured_image_url"
                   value="<?= htmlspecialchars($post['featured_image_url'] ?? '') ?>"
                   placeholder="No image selected" readonly>
            <div class="media-field-actions">
                <button type="button" class="picker-trigger"
                        data-picker-target="post-featured-url"
                        data-picker-preview="post-featured-preview">Choose Image</button>
                <button type="button" class="admin-btn admin-btn-ghost admin-btn-sm"
                        data-clear-input="post-featured-url"
                        data-clear-preview="post-featured-preview">Clear</button>
            </div>
        </fieldset>

        <!-- Categories -->
        <fieldset class="form-fieldset">
            <legend>Categories</legend>
            <div class="exhibit-artwork-list" id="post-categories-list">
                <?php foreach ($categories as $cat): ?>
                    <label class="exhibit-artwork-check">
                        <input
                            type="checkbox"
                            name="category_ids[]"
                            value="<?= (int) $cat['id'] ?>"
                            <?= in_array((int) $cat['id'], $assignedCategoryIds, true) ? 'checked' : '' ?>
                        >
                        <span><?= htmlspecialchars($cat['name']) ?></span>
                    </label>
                <?php endforeach ?>
                <?php if (empty($categories)): ?>
                    <p class="admin-hint" id="post-no-cats-hint">No categories yet.</p>
                <?php endif ?>
            </div>
            <button type="button" id="new-cat-toggle-btn" class="admin-btn admin-btn-ghost admin-btn-sm" style="margin-top:0.5rem">+ New category</button>
            <div id="new-cat-form" style="display:none;margin-top:0.45rem">
                <div style="display:flex;gap:0.5rem;align-items:center">
                    <input type="text" id="new-cat-name" placeholder="Category name" style="flex:1">
                    <button type="button" id="new-cat-save" class="admin-btn admin-btn-sm">Create</button>
                </div>
                <p id="new-cat-error" style="color:#c0392b;font-size:0.78rem;margin:0.3rem 0 0;display:none"></p>
            </div>
        </fieldset>

        <!-- Publish to platforms -->
        <fieldset class="form-fieldset" id="post-platforms-fieldset">
            <legend>Publish to <span class="form-hint">(optional)</span></legend>
            <?php if (empty($platformConnections)): ?>
                <p class="admin-hint">No platform connections enabled.
                    <a href="/admin/platform-connections">Manage connections</a></p>
            <?php else: ?>
                <div class="exhibit-artwork-list">
                    <?php
                    $platformLabels = AdapterFactory::allPlatforms();
                    foreach ($platformConnections as $conn):
                        $alreadySynced = in_array((int) $conn['id'], $publishedConnectionIds, true);
                        $connId = (int) $conn['id'];
                        $platformName = htmlspecialchars($conn['platform'], ENT_QUOTES, 'UTF-8');
                        $platformLabel = htmlspecialchars($platformLabels[$conn['platform']] ?? ucfirst($conn['platform']));
                    ?>
                        <label class="exhibit-artwork-check">
                            <input
                                type="checkbox"
                                name="platform_connection_ids[]"
                                value="<?= $connId ?>"
                                id="pc-<?= $connId ?>"
                                class="platform-conn-checkbox"
                                data-platform="<?= $platformName ?>"
                                <?= $alreadySynced ? 'checked' : '' ?>
                            >
                            <span><?= $platformLabel ?></span>
                            <?php if ($alreadySynced): ?>
                                <span class="form-hint"> — previously published (will republish if checked)</span>
                            <?php endif ?>
                        </label>
                        <div class="platform-draft-row" id="pdr-<?= $connId ?>"
                             style="display:<?= $alreadySynced ? 'none' : 'none' ?>;margin:0.3rem 0 0.5rem 1.9rem">
                            <textarea
                                name="platform_texts[<?= $platformName ?>]"
                                placeholder="Custom text before the link (leave blank to auto-generate)…"
                                rows="3"
                                style="width:100%"
                            ></textarea>
                        </div>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </fieldset>

        <div class="form-actions">
            <button type="submit" class="admin-btn"><?= $isEdit ? 'Save Changes' : 'Create Post' ?></button>
            <a href="/admin/posts" class="admin-btn admin-btn-ghost">Cancel</a>
        </div>
    </form>
</div>

<template id="section-panel-tpl">
    <details class="section-panel" open>
        <summary class="section-panel-summary">
            <span class="section-panel-title">(no heading)</span>
            <button type="button" class="section-remove-btn admin-btn admin-btn-ghost admin-btn-sm">Remove</button>
        </summary>
        <div class="section-panel-body">
            <input type="hidden" name="sections[__IDX__][id]" value="">
            <input type="hidden" name="sections[__IDX__][sort_order]" value="__IDX__">
            <div class="form-row">
                <label>Heading <span class="form-hint">(optional)</span></label>
                <input type="text" name="sections[__IDX__][heading]" value="" class="section-heading-input">
            </div>
            <div class="form-row">
                <label>Section style</label>
                <select name="sections[__IDX__][wrapper_class]">
                    <option value="">None</option>
                    <option value="mission-band">Mission band</option>
                    <option value="callout">Callout</option>
                    <option value="content-cards">Content cards</option>
                    <option value="managed-section">Standard section box</option>
                </select>
            </div>
            <div class="form-row">
                <label>Content *</label>
                <textarea name="sections[__IDX__][content]" rows="10" data-tiptap-new></textarea>
            </div>
        </div>
    </details>
</template>

<script>
function syncScheduledRow() {
    var show = document.getElementById('post-status').value === 'scheduled';
    document.getElementById('post-scheduled-row').style.display = show ? '' : 'none';
}
document.getElementById('post-status').addEventListener('change', syncScheduledRow);
syncScheduledRow();

// Platform draft text rows
document.querySelectorAll('.platform-conn-checkbox').forEach(function (cb) {
    cb.addEventListener('change', function () {
        var row = document.getElementById('pdr-' + this.value);
        if (row) row.style.display = this.checked ? '' : 'none';
    });
});

// Inline category creation toggle
document.getElementById('new-cat-toggle-btn').addEventListener('click', function () {
    var form = document.getElementById('new-cat-form');
    form.style.display = form.style.display === 'none' ? '' : 'none';
    if (form.style.display !== 'none') document.getElementById('new-cat-name').focus();
});

document.getElementById('new-cat-save').addEventListener('click', function () {
    var name = document.getElementById('new-cat-name').value.trim();
    var errEl = document.getElementById('new-cat-error');
    errEl.style.display = 'none';
    if (!name) { errEl.textContent = 'Name required'; errEl.style.display = 'block'; return; }
    fetch('/admin/blog/categories/create-inline', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'name=' + encodeURIComponent(name)
    }).then(function (r) { return r.json(); }).then(function (data) {
        if (data.error) { errEl.textContent = data.error; errEl.style.display = 'block'; return; }
        var list = document.getElementById('post-categories-list');
        var hint = document.getElementById('post-no-cats-hint');
        if (hint) hint.remove();
        var label = document.createElement('label');
        label.className = 'exhibit-artwork-check';
        label.innerHTML = '<input type="checkbox" name="category_ids[]" value="' + data.id + '" checked><span>' + data.name + '</span>';
        list.appendChild(label);
        document.getElementById('new-cat-name').value = '';
        document.getElementById('new-cat-form').style.display = 'none';
    }).catch(function () { errEl.textContent = 'Request failed'; errEl.style.display = 'block'; });
});

(function () {
    let sectionCount = <?= count($sections) ?>;
    const manager = document.getElementById('post-sections-manager');
    const addBtn = document.getElementById('add-section-btn');

    function wirePanel(panel) {
        const removeBtn = panel.querySelector('.section-remove-btn');
        const headingInput = panel.querySelector('.section-heading-input');
        const titleSpan = panel.querySelector('.section-panel-title');

        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                const idInput = panel.querySelector('input[name$="[id]"]');
                if (idInput && idInput.value) {
                    const del = document.createElement('input');
                    del.type = 'hidden';
                    del.name = idInput.name.replace('[id]', '[_delete]');
                    del.value = '1';
                    panel.appendChild(del);
                }
                panel.hidden = true;
            });
        }

        if (headingInput && titleSpan) {
            headingInput.addEventListener('input', function () {
                titleSpan.textContent = headingInput.value.trim() || '(no heading)';
            });
        }
    }

    document.querySelectorAll('.section-panel').forEach(wirePanel);

    addBtn.addEventListener('click', function () {
        const tpl = document.getElementById('section-panel-tpl');
        const frag = tpl.content.cloneNode(true);
        const idx = sectionCount++;

        frag.querySelectorAll('[name]').forEach(function (el) {
            el.name = el.name.replace(/__IDX__/g, idx);
        });
        const sortInput = frag.querySelector('input[type="hidden"][name*="sort_order"]');
        if (sortInput) sortInput.value = idx;

        const ta = frag.querySelector('textarea[data-tiptap-new]');
        if (ta) {
            ta.removeAttribute('data-tiptap-new');
            ta.setAttribute('data-tiptap', '');
        }

        manager.insertBefore(frag, addBtn);

        const panels = manager.querySelectorAll('.section-panel');
        const panel = panels[panels.length - 1];
        wirePanel(panel);

        const textarea = panel.querySelector('textarea[data-tiptap]');
        if (textarea && window.initTiptap) {
            window.initTiptap(textarea);
        }
    });
}());
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
