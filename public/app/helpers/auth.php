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

function user_logged_in(): bool
{
    return !empty($_SESSION['user_id']) || !empty($_SESSION['admin_identity_id']);
}

function current_user(): ?array
{
    $id = trim((string) ($_SESSION['user_id'] ?? ''));
    if ($id !== '') {
        try {
            $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    // Admin fallback: look up the users row by the admin identity's email
    $adminId = (int) ($_SESSION['admin_identity_id'] ?? 0);
    if ($adminId <= 0 || !class_exists('AdminIdentity')) {
        return null;
    }
    try {
        $identity = AdminIdentity::find($adminId);
        if (!$identity || empty($identity['email'])) {
            return null;
        }
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$identity['email']]);
        return ($stmt->fetch()) ?: null;
    } catch (Throwable) {
        return null;
    }
}

function current_comment_actor(): ?array
{
    $user = current_user();
    if ($user) {
        return [
            'id' => (string) $user['id'],
            'user_id' => (string) $user['id'],
            'name' => (string) ($user['name'] ?? $user['username'] ?? 'Member'),
            'username' => (string) ($user['username'] ?? ''),
            'image' => (string) ($user['image'] ?? ''),
        ];
    }

    $identity = admin_identity();
    if (!$identity) {
        return null;
    }

    return [
        'id' => 'admin:' . (string) $identity['id'],
        'user_id' => null,
        'name' => (string) ($identity['display_name'] ?? 'Admin'),
        'username' => '',
        'image' => (string) ($identity['avatar_url'] ?? ''),
    ];
}

function comment_belongs_to_current_actor(array $comment): bool
{
    $actor = current_comment_actor();
    if (!$actor) {
        return false;
    }

    $authorUserId = trim((string) ($comment['author_user_id'] ?? ''));
    $actorUserId = trim((string) ($actor['user_id'] ?? ''));
    if ($authorUserId !== '' && $actorUserId !== '' && hash_equals($authorUserId, $actorUserId)) {
        return true;
    }

    $authorId = trim((string) ($comment['author_id'] ?? ''));
    $actorId = trim((string) ($actor['id'] ?? ''));
    return $authorId !== '' && $actorId !== '' && hash_equals($authorId, $actorId);
}

function user_login(array $user): void
{
    $_SESSION['user_id'] = (string) $user['id'];
    $_SESSION['user_username'] = (string) ($user['username'] ?? '');
    $_SESSION['user_display_name'] = (string) ($user['name'] ?? '');
}

function user_logout(): void
{
    unset(
        $_SESSION['user_id'],
        $_SESSION['user_username'],
        $_SESSION['user_display_name'],
        $_SESSION['user_oauth_state']
    );
}
