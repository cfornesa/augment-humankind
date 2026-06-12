<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — Fornesus Art</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="admin-body admin-login-body">
    <div class="login-wrap login-wrap-oauth">
        <p class="login-kicker">Administration</p>
        <h1 class="login-title">Archive Access</h1>
        <p class="login-copy">Sign in with an approved GitHub or Google identity.</p>
        <?php if ($error): ?>
            <p class="login-error" role="alert">
                <?php
                echo match ($error) {
                    'state' => 'The login session expired or the callback state was invalid.',
                    'denied' => 'That identity is not approved for admin access.',
                    'provider' => 'This OAuth provider is not configured yet.',
                    default => 'Sign-in could not be completed.',
                };
                ?>
            </p>
            <?php if (!empty($detail)): ?>
                <p class="login-error" role="alert"><?= htmlspecialchars($detail) ?></p>
            <?php endif ?>
        <?php endif ?>
        <div class="login-provider-list">
            <a class="login-provider-btn" href="/admin/auth/github/start">Continue With GitHub</a>
            <a class="login-provider-btn login-provider-btn-alt" href="/admin/auth/google/start">Continue With Google</a>
        </div>
    </div>
</body>
</html>
