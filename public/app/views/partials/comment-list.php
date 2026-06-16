<?php declare(strict_types=1); ?>
<?php
$comments = $comments ?? [];
$commentsUrl = $commentsUrl ?? '';
$emptyCommentMessage = $emptyCommentMessage ?? 'No comments yet. Be the first.';
?>
<div class="post-comments-list"
     data-comments-url="<?= e((string) $commentsUrl) ?>"
     data-empty-message="<?= e((string) $emptyCommentMessage) ?>">
    <?php if (empty($comments)): ?>
        <p class="admin-empty"><?= e((string) $emptyCommentMessage) ?></p>
    <?php else: ?>
        <?php foreach ($comments as $comment): ?>
            <?php require __DIR__ . '/comment-item.php'; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
