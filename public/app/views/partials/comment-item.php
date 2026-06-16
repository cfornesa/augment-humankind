<?php declare(strict_types=1); ?>
<?php
$commentId = (int) ($comment['id'] ?? 0);
$canManageComment = comment_belongs_to_current_actor($comment);
$commentDate = !empty($comment['created_at'])
    ? date('M j, Y', strtotime((string) $comment['created_at']) ?: time())
    : date('M j, Y');
?>
<div class="post-comment-item" data-comment-id="<?= $commentId ?>">
    <div class="post-comment-header">
        <strong><?= e((string) ($comment['author_name'] ?? 'Anonymous')) ?> · <span class="post-comment-date"><?= e($commentDate) ?></span></strong>
        <?php if ($canManageComment): ?>
        <div class="post-comment-actions">
            <button type="button"
                    class="post-comment-icon-btn"
                    data-comment-edit-toggle
                    aria-expanded="false"
                    aria-controls="comment-edit-<?= $commentId ?>"
                    aria-label="Edit your comment">
                <?= icon('pencil') ?>
            </button>
            <button type="button"
                    class="post-comment-icon-btn post-comment-icon-btn-danger"
                    data-comment-delete
                    aria-label="Delete your comment">
                <?= icon('trash-2') ?>
            </button>
        </div>
        <?php endif; ?>
    </div>
    <p class="post-comment-content"><?= nl2br(e((string) ($comment['content'] ?? ''))) ?></p>
    <?php if ($canManageComment): ?>
    <form class="post-comment-edit-form"
          id="comment-edit-<?= $commentId ?>"
          data-comment-id="<?= $commentId ?>"
          hidden>
        <textarea name="content" maxlength="500" required><?= e((string) ($comment['content'] ?? '')) ?></textarea>
        <div class="post-comment-edit-actions">
            <button type="submit" class="post-action-btn">Save</button>
            <button type="button" class="post-action-btn" data-comment-edit-cancel>Cancel</button>
        </div>
    </form>
    <?php endif; ?>
</div>
