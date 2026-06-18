<?php

declare(strict_types=1);

// Inject user-scoped color overrides scoped per theme mode to prevent dark-mode bleed
$_ahUserLightMap = [
    'color_background'       => '--paper',
    'color_foreground'       => '--ink',
    'color_muted'            => '--paper-deep',
    'color_muted_foreground' => '--ink-soft',
    'color_primary'          => '--green',
    'color_secondary'        => '--cyan',
    'color_accent'           => '--orange',
];
$_ahUserDarkMap = [
    'color_background_dark'       => '--paper',
    'color_foreground_dark'       => '--ink',
    'color_muted_dark'            => '--paper-deep',
    'color_muted_foreground_dark' => '--ink-soft',
    'color_primary_dark'          => '--green',
    'color_secondary_dark'        => '--cyan',
    'color_accent_dark'           => '--orange',
];
$_ahUserLightVars = [];
foreach ($_ahUserLightMap as $_col => $_var) {
    if (!empty($profileUser[$_col])) {
        $_ahUserLightVars[] = $_var . ':hsl(' . htmlspecialchars((string) $profileUser[$_col], ENT_QUOTES, 'UTF-8') . ')';
    }
}
$_ahUserDarkVars = [];
foreach ($_ahUserDarkMap as $_col => $_var) {
    if (!empty($profileUser[$_col])) {
        $_ahUserDarkVars[] = $_var . ':hsl(' . htmlspecialchars((string) $profileUser[$_col], ENT_QUOTES, 'UTF-8') . ')';
    }
}
$extraHeadHtml = '';
if ($_ahUserLightVars !== []) {
    $extraHeadHtml .= '<style>:root:not([data-theme="dark"]) .page-user-profile{' . implode(';', $_ahUserLightVars) . '}</style>';
    $extraHeadHtml .= '<style>@media(prefers-color-scheme:light){:root:not([data-theme="dark"]) .page-user-profile{' . implode(';', $_ahUserLightVars) . '}}</style>';
}
if ($_ahUserDarkVars !== []) {
    $extraHeadHtml .= '<style>[data-theme="dark"] .page-user-profile{' . implode(';', $_ahUserDarkVars) . '}</style>';
    $extraHeadHtml .= '<style>@media(prefers-color-scheme:dark){:root:not([data-theme="light"]) .page-user-profile{' . implode(';', $_ahUserDarkVars) . '}}</style>';
}
unset($_ahUserLightMap, $_ahUserDarkMap, $_ahUserLightVars, $_ahUserDarkVars, $_col, $_var);

require dirname(__DIR__) . '/partials/header.php';
?>
<div class="managed-section" style="max-width: 860px; margin: 0 auto; padding: 2rem 1.5rem;">

    <header class="user-profile-header" style="display: flex; gap: 1.5rem; align-items: flex-start; margin-bottom: 2.5rem; padding-bottom: 2rem; border-bottom: 3px solid var(--line);">
        <?php if (!empty($profileUser['image'])): ?>
            <img src="<?= e($profileUser['image']) ?>" alt="" class="user-avatar"
                 style="width: 72px; height: 72px; border-radius: 50%; border: 3px solid var(--line); object-fit: cover; flex-shrink: 0;">
        <?php else: ?>
            <div aria-hidden="true"
                 style="width: 72px; height: 72px; border-radius: 50%; border: 3px solid var(--line); background: var(--paper-deep); flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; color: var(--ink-soft);">
                <?= e(mb_strtoupper(mb_substr((string) ($profileUser['name'] ?? $profileUser['username'] ?? '?'), 0, 1))) ?>
            </div>
        <?php endif ?>
        <div>
            <h1 style="margin: 0 0 0.25rem; font-size: 1.75rem;"><?= e((string) ($profileUser['name'] ?? $profileUser['username'])) ?></h1>
            <?php if (!empty($profileUser['username'])): ?>
                <p style="margin: 0 0 0.5rem; color: var(--ink-soft); font-size: 0.9rem;">@<?= e($profileUser['username']) ?></p>
            <?php endif ?>
            <?php if (!empty($profileUser['bio'])): ?>
                <p style="margin: 0.5rem 0 0;"><?= e($profileUser['bio']) ?></p>
            <?php endif ?>
            <?php if (!empty($profileUser['website'])): ?>
                <p style="margin: 0.5rem 0 0; font-size: 0.9rem;">
                    <a href="<?= e($profileUser['website']) ?>" rel="noopener noreferrer" style="color: var(--ink-soft);"><?= e($profileUser['website']) ?></a>
                </p>
            <?php endif ?>
        </div>
        <?php if ($isOwnProfile): ?>
            <a href="/user/settings" style="margin-left: auto; align-self: flex-start; padding: 0.5rem 1rem; border: 2px solid var(--line); box-shadow: 3px 3px 0 var(--line); font-weight: 700; text-decoration: none; color: var(--ink); background: var(--paper); white-space: nowrap; font-size: 0.9rem;">
                Edit profile
            </a>
        <?php endif ?>
    </header>

    <?php if (!empty($posts)): ?>
    <section aria-labelledby="posts-heading" style="margin-bottom: 2.5rem;">
        <h2 id="posts-heading" style="margin: 0 0 1rem; font-size: 1.2rem; border-bottom: 2px solid var(--line); padding-bottom: 0.5rem;">Blog Posts</h2>
        <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.75rem;">
            <?php foreach ($posts as $post): ?>
            <li>
                <a href="/blog/posts/<?= (int) $post['id'] ?>" style="font-weight: 700; color: var(--ink);"><?= e((string) $post['title']) ?></a>
                <?php if (!empty($post['created_at'])): ?>
                    <span style="color: var(--ink-soft); font-size: 0.85rem; margin-left: 0.5rem;"><?= e(date('j M Y', strtotime($post['created_at']))) ?></span>
                <?php endif ?>
            </li>
            <?php endforeach ?>
        </ul>
    </section>
    <?php endif ?>

    <?php if (!empty($pieces)): ?>
    <section aria-labelledby="pieces-heading" style="margin-bottom: 2.5rem;">
        <h2 id="pieces-heading" style="margin: 0 0 1rem; font-size: 1.2rem; border-bottom: 2px solid var(--line); padding-bottom: 0.5rem;">Art Pieces</h2>
        <ul style="list-style: none; padding: 0; margin: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 1rem;">
            <?php foreach ($pieces as $piece): ?>
            <li>
                <a href="/pieces/<?= (int) $piece['id'] ?>" style="display: block; text-decoration: none; color: var(--ink);">
                    <?php if (!empty($piece['thumbnail_url'])): ?>
                        <img src="<?= e($piece['thumbnail_url']) ?>" alt="" loading="lazy" decoding="async"
                             style="width: 100%; aspect-ratio: 1; object-fit: cover; border: 2px solid var(--line); display: block; margin-bottom: 0.4rem;">
                    <?php else: ?>
                        <div aria-hidden="true" style="width: 100%; aspect-ratio: 1; background: var(--paper-deep); border: 2px solid var(--line); margin-bottom: 0.4rem;"></div>
                    <?php endif ?>
                    <span style="font-size: 0.85rem; font-weight: 700;"><?= e((string) $piece['title']) ?></span>
                </a>
            </li>
            <?php endforeach ?>
        </ul>
        <?php if (!empty($piecesHasMore)): ?>
        <p style="margin: 1rem 0 0;">
            <a href="?show_pieces=all" style="font-weight: 700; color: var(--ink); border-bottom: 2px solid var(--line);">Show all pieces →</a>
        </p>
        <?php endif ?>
    </section>
    <?php endif ?>

    <?php if (!empty($comments)): ?>
    <section aria-labelledby="comments-heading" style="margin-bottom: 2rem;">
        <h2 id="comments-heading" style="margin: 0 0 1rem; font-size: 1.2rem; border-bottom: 2px solid var(--line); padding-bottom: 0.5rem;">Recent Comments</h2>
        <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.75rem;">
            <?php foreach ($comments as $comment): ?>
            <li style="padding: 0.75rem 1rem; border: 2px solid var(--line);">
                <p style="margin: 0 0 0.35rem; font-size: 0.9rem; color: var(--ink-soft);"><?= e(ucfirst((string) ($comment['item_type'] ?? ''))) ?> #<?= (int) $comment['item_id'] ?></p>
                <p style="margin: 0; font-size: 0.95rem;"><?= e(mb_substr((string) $comment['content'], 0, 200)) ?><?= mb_strlen((string) $comment['content']) > 200 ? '…' : '' ?></p>
                <?php if (!empty($comment['created_at'])): ?>
                    <p style="margin: 0.35rem 0 0; font-size: 0.8rem; color: var(--ink-soft);"><?= e(date('j M Y', strtotime($comment['created_at']))) ?></p>
                <?php endif ?>
            </li>
            <?php endforeach ?>
        </ul>
    </section>
    <?php endif ?>

    <?php if (empty($posts) && empty($pieces) && empty($comments)): ?>
        <p style="color: var(--ink-soft);">Nothing to show yet.</p>
    <?php endif ?>

</div>
<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
