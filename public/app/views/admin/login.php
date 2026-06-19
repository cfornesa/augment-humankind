<?php
$pageTitle = 'Admin Login — ' . app_site_name();
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
        <h1 class="login-title">Admin Access</h1>
        <p class="login-copy">Sign in with an approved GitHub or Google account.</p>
        <?php if ($error): ?>
            <p class="login-error" role="alert">
                <?php
                echo match ($error) {
                    'state' => 'The login session expired or the callback state was invalid.',
                    'denied' => 'That account is not approved for admin access.',
                    'provider' => 'This sign-in provider is not configured yet.',
                    'rate_limit' => 'Too many admin sign-in attempts. Please wait and try again.',
                    default => 'Sign-in could not be completed.',
                };
                ?>
            </p>
            <?php if (!empty($detail)): ?>
                <p class="login-error" role="alert"><?= htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif ?>
        <?php endif ?>
        <div class="login-provider-list">
            <a class="login-provider-btn" href="/admin/auth/github/start">Continue with GitHub</a>
            <a class="login-provider-btn login-provider-btn-alt" href="/admin/auth/google/start">Continue with Google</a>
        </div>
    </div>
</body>
</html>
