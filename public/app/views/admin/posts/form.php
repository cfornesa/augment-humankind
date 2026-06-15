<?php
$isEdit    = ($post['id'] ?? null) !== null;
$pageTitle = ($isEdit ? 'Edit Post' : 'New Post') . ' — Augment Humankind Admin';

$scheduledValue = '';
if (!empty($post['scheduled_at'])) {
    $ts = strtotime($post['scheduled_at']);
    if ($ts !== false) {
        $scheduledValue = date('Y-m-d\TH:i', $ts);
    }
}

$status = $post['status'] ?? 'draft';
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
            <label>Content *</label>
            <textarea name="content" rows="14" data-tiptap><?= htmlspecialchars($post['content'] ?? '') ?></textarea>
        </div>

        <div class="form-row">
            <label>Status *</label>
            <select name="status" id="post-status">
                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
                <option value="scheduled" <?= $status === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
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
            <?php if (empty($categories)): ?>
                <p class="admin-hint">No blog categories yet.</p>
            <?php else: ?>
                <div class="exhibit-artwork-list">
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
                </div>
            <?php endif ?>
        </fieldset>

        <div class="form-actions">
            <button type="submit" class="admin-btn"><?= $isEdit ? 'Save Changes' : 'Create Post' ?></button>
            <a href="/admin/posts" class="admin-btn admin-btn-ghost">Cancel</a>
        </div>
    </form>
</div>

<script>
document.getElementById('post-status').addEventListener('change', function () {
    document.getElementById('post-scheduled-row').hidden = this.value !== 'scheduled';
});
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
