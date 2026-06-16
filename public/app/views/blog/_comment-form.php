<?php declare(strict_types=1); ?>
<form class="post-comment-form" data-post-id="<?= $postId ?>">
    <input type="text" name="author_name" placeholder="Your name (optional)" maxlength="80" autocomplete="name">
    <textarea name="content" placeholder="Write a comment…" maxlength="500" required></textarea>
    <input type="text" name="hp_field" class="field-honeypot" tabindex="-1" autocomplete="off" aria-hidden="true">
    <button type="submit" class="post-action-btn">Post comment</button>
</form>
