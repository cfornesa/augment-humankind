<?php
$pageTitle = 'Admin Email Sign-In — ' . app_site_name();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/assets/styles.css">
    <link rel="stylesheet" href="/assets/admin.css">
</head>
<body class="admin-body admin-login-body">
    <div class="login-wrap">
        <p class="login-kicker">Administration</p>
        <h1 class="login-title">Email Sign-In</h1>
        <p class="login-copy">Enter an approved admin email address to receive a one-time sign-in link.</p>
        <?php if ($error): ?>
            <p class="login-error" role="alert">
                <?php
                echo match ($error) {
                    'email' => 'Please enter a valid email address.',
                    default => 'Sign-in could not be completed.',
                };
                ?>
            </p>
        <?php endif ?>
        <div aria-live="polite">
            <?php if ($sent): ?>
                <p class="login-copy">If that address is approved, a sign-in link is on its way. It expires in 15 minutes.</p>
            <?php endif ?>
        </div>
        <form method="post" action="/admin/auth/email" class="login-provider-list">
            <label for="admin-magic-link-email" class="login-copy" style="font-weight: 700;">Email address</label>
            <input id="admin-magic-link-email" name="email" type="email" required autocomplete="email"
                   class="login-provider-btn" style="text-align: left; cursor: text;">
            <button type="submit" class="login-provider-btn login-provider-btn-alt" style="cursor: pointer; font: inherit;">
                Send sign-in link
            </button>
        </form>
        <p class="login-copy"><a href="/admin/login">← Other sign-in options</a></p>
    </div>
</body>
</html>
