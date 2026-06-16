<?php declare(strict_types=1); ?>
<?php if (user_logged_in()): ?>
<form class="post-comment-form" data-comment-url="<?= e((string) $commentUrl) ?>">
    <textarea name="content" placeholder="Write a comment…" maxlength="500" required></textarea>
    <input type="text" name="hp_field" class="field-honeypot" tabindex="-1" autocomplete="off" aria-hidden="true">
    <button type="submit" class="post-action-btn">Post comment</button>
</form>
<?php else: ?>
<p class="post-comment-signin">
    <a href="/user/login?redirect=<?= e(urlencode((string) $signinRedirect)) ?>">Sign in to leave a comment.</a>
</p>
<?php endif ?>
