<?php

declare(strict_types=1);

function admin_check(): void
{
    if (empty($_SESSION['admin_identity_id'])) {
        header('Location: /admin/login');
        exit;
    }
}

function admin_login_identity(array $identity): void
{
    $_SESSION['admin_identity_id'] = (int) $identity['id'];
    $_SESSION['admin_provider'] = (string) $identity['provider'];
    $_SESSION['admin_display_name'] = (string) $identity['display_name'];
}

function admin_logout(): void
{
    unset(
        $_SESSION['admin_identity_id'],
        $_SESSION['admin_provider'],
        $_SESSION['admin_display_name'],
        $_SESSION['oauth_state']
    );
    session_destroy();
}

function admin_identity(): ?array
{
    $id = (int) ($_SESSION['admin_identity_id'] ?? 0);
    if ($id <= 0 || !class_exists('AdminIdentity')) {
        return null;
    }

    try {
        $identity = AdminIdentity::find($id);
        return $identity ?: null;
    } catch (Throwable) {
        return null;
    }
}
