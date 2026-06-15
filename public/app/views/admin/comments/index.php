<?php
$pageTitle = 'Comments — Augment Humankind Admin';
$tab = $tab ?? 'comments';

$tabs = [
    'comments'  => ['label' => 'Comments', 'items' => $comments],
    'reactions' => ['label' => 'Reactions', 'items' => $reactions],
];

ob_start();
?>
<div class="admin-section">
    <h1 class="admin-heading">Comments &amp; Reactions</h1>

    <nav class="trash-tabs">
        <?php foreach ($tabs as $key => $info): ?>
            <a href="/admin/comments?tab=<?= $key ?>"
               class="trash-tab <?= $tab === $key ? 'active' : '' ?>">
                <?= $info['label'] ?>
                <?php if ($count = count($info['items'])): ?>
                    <span class="trash-tab-count"><?= $count ?></span>
                <?php endif ?>
            </a>
        <?php endforeach ?>
    </nav>

    <?php $items = $tabs[$tab]['items'] ?? $comments; ?>

    <?php if (empty($items)): ?>
        <p class="admin-empty">Nothing here yet.</p>
    <?php elseif ($tab === 'reactions'): ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Post</th>
                    <th>User</th>
                    <th>Type</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $reaction): ?>
                    <tr>
                        <td>
                            <?php if ($reaction['post_id']): ?>
                                <a href="/blog/posts/<?= (int) $reaction['post_id'] ?>" target="_blank" rel="noopener"><?= htmlspecialchars($reaction['post_title'] ?? ('#' . $reaction['post_id'])) ?></a>
                            <?php else: ?>
                                —
                            <?php endif ?>
                        </td>
                        <td><?= htmlspecialchars($reaction['user_name'] ?? $reaction['user_id']) ?></td>
                        <td><?= htmlspecialchars($reaction['type']) ?></td>
                        <td class="admin-hint"><?= date('Y-m-d H:i', strtotime($reaction['created_at'])) ?></td>
                        <td class="admin-actions">
                            <form method="POST" action="/admin/reactions/<?= (int) $reaction['id'] ?>/delete"
                                  onsubmit="return confirm('Remove this reaction? This cannot be undone.')">
                                <button type="submit" class="admin-del-btn">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Post</th>
                    <th>Author</th>
                    <th>Comment</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $comment): ?>
                    <?php $excerpt = mb_strlen($comment['content']) > 140 ? mb_substr($comment['content'], 0, 140) . '…' : $comment['content']; ?>
                    <tr>
                        <td>
                            <?php if ($comment['post_id']): ?>
                                <a href="/blog/posts/<?= (int) $comment['post_id'] ?>" target="_blank" rel="noopener"><?= htmlspecialchars($comment['post_title'] ?? ('#' . $comment['post_id'])) ?></a>
                            <?php else: ?>
                                —
                            <?php endif ?>
                        </td>
                        <td><?= htmlspecialchars($comment['author_name']) ?></td>
                        <td><?= htmlspecialchars($excerpt) ?></td>
                        <td class="admin-hint"><?= date('Y-m-d H:i', strtotime($comment['created_at'])) ?></td>
                        <td class="admin-actions">
                            <form method="POST" action="/admin/comments/<?= (int) $comment['id'] ?>/delete"
                                  onsubmit="return confirm('Move this comment to the recycle bin?')">
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
