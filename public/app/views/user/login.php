<?php

declare(strict_types=1);

$pageTitle = 'Sign In — ' . app_site_name();
$redirectParam = $redirect !== '' ? '?redirect=' . urlencode($redirect) : '';
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
            <h1 style="margin: 0 0 0.5rem; font-size: 1.6rem;">Sign In</h1>
            <p style="margin: 0 0 2rem; color: var(--ink-soft);">Sign in with GitHub or Google to leave comments and manage your profile.</p>

            <?php if ($error): ?>
                <p role="alert" style="margin: 0 0 1.5rem; padding: 0.75rem 1rem; border: 2px solid var(--line); background: #fce4e4; color: #7a1010;">
                    <?= match ($error) {
                        'state'    => 'The login session expired. Please try again.',
                        'provider' => 'This sign-in provider is not configured.',
                        default    => 'Sign-in could not be completed. Please try again.',
                    } ?>
                </p>
            <?php endif ?>

            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <a href="/user/auth/github/start<?= $redirectParam ?>"
                   style="display: block; padding: 0.85rem 1.25rem; border: 3px solid var(--line); box-shadow: 4px 4px 0 var(--line); background: var(--paper); font-weight: 700; text-decoration: none; color: var(--ink); text-align: center; transition: transform 0.1s, box-shadow 0.1s;"
                   onmouseover="this.style.transform='translate(2px,2px)';this.style.boxShadow='2px 2px 0 var(--line)'"
                   onmouseout="this.style.transform='';this.style.boxShadow='4px 4px 0 var(--line)'">
                    Continue with GitHub
                </a>
                <a href="/user/auth/google/start<?= $redirectParam ?>"
                   style="display: block; padding: 0.85rem 1.25rem; border: 3px solid var(--line); box-shadow: 4px 4px 0 var(--line); background: var(--paper); font-weight: 700; text-decoration: none; color: var(--ink); text-align: center; transition: transform 0.1s, box-shadow 0.1s;"
                   onmouseover="this.style.transform='translate(2px,2px)';this.style.boxShadow='2px 2px 0 var(--line)'"
                   onmouseout="this.style.transform='';this.style.boxShadow='4px 4px 0 var(--line)'">
                    Continue with Google
                </a>
            </div>

            <p style="margin: 2rem 0 0; font-size: 0.85rem; color: var(--ink-soft);">
                <a href="/" style="color: var(--ink-soft);">← Back to site</a>
            </p>
        </div>
    </main>
</body>
</html>
