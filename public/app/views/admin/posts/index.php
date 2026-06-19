<?php
$pageTitle = 'Posts — ' . app_site_name() . ' Admin';
$status = $status ?? null;

$statusTabs = [
    ''          => 'All',
    'published' => 'Published',
    'draft'     => 'Drafts',
    'scheduled' => 'Scheduled',
];

ob_start();
?>
<div class="admin-section">
    <div class="admin-section-head">
        <h1 class="admin-heading">Posts</h1>
        <div style="display:flex;gap:0.5rem">
            <a href="/admin/posts/calendar" class="admin-btn admin-btn-ghost">Calendar</a>
            <a href="/admin/posts/create" class="admin-btn">+ New Post</a>
        </div>
    </div>

    <?php if (!empty($_GET['syndication_error'])): ?>
        <p class="admin-error" style="margin-bottom:1rem">
            <strong>Syndication failed</strong> — post was saved but could not be published to one or more platforms:<br>
            <?= htmlspecialchars($_GET['syndication_error']) ?>
        </p>
    <?php endif ?>

    <nav class="trash-tabs">
        <?php foreach ($statusTabs as $key => $label): ?>
            <a href="/admin/posts<?= $key === '' ? '' : '?status=' . $key ?>"
               class="trash-tab <?= ($status ?? '') === $key ? 'active' : '' ?>">
                <?= htmlspecialchars($label) ?>
            </a>
        <?php endforeach ?>
    </nav>

    <?php if (empty($posts)): ?>
        <p class="admin-empty">No posts yet.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Categories</th>
                    <th>Comments</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $post): ?>
                    <?php $categoryNames = implode(', ', array_map(static fn ($c) => $c['name'], $post['categories'] ?? [])); ?>
                    <tr>
                        <td><?= htmlspecialchars($post['title'] !== null && $post['title'] !== '' ? $post['title'] : '(untitled)') ?></td>
                        <td>
                            <?= htmlspecialchars(ucfirst($post['status'])) ?>
                            <?php if ($post['status'] === 'scheduled' && $post['scheduled_at']): ?>
                                <span class="admin-hint"> — <?= date('Y-m-d H:i', strtotime($post['scheduled_at'])) ?></span>
                            <?php endif ?>
                        </td>
                        <td><?= $categoryNames !== '' ? htmlspecialchars($categoryNames) : '—' ?></td>
                        <td><?= (int) $post['comment_count'] ?></td>
                        <td class="admin-hint"><?= date('Y-m-d', strtotime($post['created_at'])) ?></td>
                        <td class="admin-actions">
                            <?php if ($post['status'] === 'published'): ?>
                                <a href="/blog/posts/<?= (int) $post['id'] ?>" target="_blank" rel="noopener">View</a>
                            <?php endif ?>
                            <a href="/admin/posts/<?= (int) $post['id'] ?>/edit">Edit</a>
                            <form method="POST" action="/admin/posts/<?= (int) $post['id'] ?>/delete"
                                  onsubmit="return confirm('Move this post to the recycle bin?')">
                                <button type="submit" class="admin-del-btn">Move to trash</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
