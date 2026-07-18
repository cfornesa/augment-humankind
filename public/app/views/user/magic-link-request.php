<?php

declare(strict_types=1);

$pageTitle = 'Sign In by Email — ' . app_site_name();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body class="page-managed">
    <a class="skip-link" href="#main">Skip to content</a>
    <header class="site-header" aria-label="Site header">
        <a class="brand" href="/" aria-label="<?= e(app_site_name()) ?> home">
            <span class="brand-text"><?= e(app_site_name()) ?></span>
        </a>
    </header>
    <main id="main">
        <div class="managed-section" style="max-width: 480px; margin: 4rem auto; padding: 2.5rem; border: 3px solid var(--line); box-shadow: 6px 6px 0 var(--line);">
            <h1 style="margin: 0 0 0.5rem; font-size: 1.6rem;">Sign In by Email</h1>
            <p style="margin: 0 0 2rem; color: var(--ink-soft);">Enter your email address and we'll send you a one-time sign-in link.</p>

            <?php if ($error): ?>
                <p role="alert" style="margin: 0 0 1.5rem; padding: 0.75rem 1rem; border: 2px solid var(--line); background: #fce4e4; color: #7a1010;">
                    <?= match ($error) {
                        'email' => 'Please enter a valid email address.',
                        'link'  => 'That sign-in link is invalid or has expired. Request a new one below.',
                        default => 'Sign-in could not be completed. Please try again.',
                    } ?>
                </p>
            <?php endif ?>

            <div aria-live="polite">
                <?php if ($sent): ?>
                    <p style="margin: 0 0 1.5rem; padding: 0.75rem 1rem; border: 2px solid var(--line); background: var(--paper);">
                        If that address is valid, a sign-in link is on its way. It expires in 15 minutes — check your inbox and spam folder.
                    </p>
                <?php endif ?>
            </div>

            <form method="post" action="/user/auth/email" style="display: flex; flex-direction: column; gap: 0.75rem;">
                <label for="magic-link-email" style="font-weight: 700;">Email address</label>
                <input id="magic-link-email" name="email" type="email" required autocomplete="email"
                       style="padding: 0.85rem 1rem; border: 3px solid var(--line); background: var(--paper); color: var(--ink); font: inherit;">
                <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
                <button type="submit"
                        style="padding: 0.85rem 1.25rem; border: 3px solid var(--line); box-shadow: 4px 4px 0 var(--line); background: var(--paper); font-weight: 700; color: var(--ink); cursor: pointer; font: inherit;">
                    Send sign-in link
                </button>
            </form>

            <p style="margin: 2rem 0 0; font-size: 0.85rem; color: var(--ink-soft);">
                <a href="/user/login" style="color: var(--ink-soft);">← Other sign-in options</a>
            </p>
        </div>
    </main>
</body>
</html>
