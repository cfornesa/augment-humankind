<?php

declare(strict_types=1);

$siteName = function_exists('app_site_name') ? app_site_name() : 'This site';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?> — Site setup in progress</title>
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body class="page-managed">
    <main id="main">
        <div class="managed-section" style="max-width: 480px; margin: 4rem auto; padding: 2.5rem; border: 3px solid var(--line); box-shadow: 6px 6px 0 var(--line); text-align: center;">
            <h1 style="margin: 0 0 0.75rem; font-size: 1.6rem;">Site setup in progress</h1>
            <p style="margin: 0 0 2rem; color: var(--ink-soft);">
                This site hasn't finished its first-run setup yet. If you're
                the owner, sign in to complete configuration.
            </p>
            <a class="button button-primary" href="/admin/login">Sign in to finish setup</a>
        </div>
    </main>
</body>
</html>
