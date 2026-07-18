<?php

declare(strict_types=1);

// Passwordless email sign-in ("magic link"). Tokens live in the
// verification_tokens table (identifier, token, expires): identifier is
// "email|context" so one table serves member and admin flows, and the token
// column stores a sha256 hash — a database leak exposes nothing usable.

function magic_link_enabled(): bool
{
    return app_smtp_configured();
}

function magic_link_normalize_email(string $email): string
{
    $email = strtolower(trim($email));

    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}

/**
 * Create a single active token for the address and email the sign-in link.
 * Returns false only on internal failure — callers must render the same
 * neutral confirmation either way to avoid account enumeration.
 */
function magic_link_issue(string $email, string $context): bool
{
    $email = magic_link_normalize_email($email);
    if ($email === '' || !in_array($context, ['member', 'admin'], true)) {
        return false;
    }

    $token = bin2hex(random_bytes(32));
    $identifier = $email . '|' . $context;

    db()->prepare('DELETE FROM verification_tokens WHERE identifier = ?')->execute([$identifier]);
    // NOW() on both insert and sweep keeps expiry comparisons consistent
    // regardless of the connection's time zone.
    db()->prepare('INSERT INTO verification_tokens (identifier, token, expires) VALUES (?, ?, DATE_ADD(NOW(3), INTERVAL 900 SECOND))')
        ->execute([$identifier, hash('sha256', $token)]);

    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $link = ($https ? 'https' : 'http') . '://' . $host . '/auth/email/verify?' . http_build_query([
        'token' => $token,
        'context' => $context,
    ]);

    $siteName = function_exists('app_site_name') ? app_site_name() : 'this site';
    $body = implode("\n", [
        'Hello,',
        '',
        'Use the link below to sign in to ' . $siteName . '. It works once and expires in 15 minutes.',
        '',
        $link,
        '',
        'If you did not request this, you can ignore this email — no account change has been made.',
    ]);

    return app_send_mail($email, $siteName . ' sign-in link', $body);
}

/**
 * Validate and consume a token. Returns the email address on success, '' on
 * any failure. Single-use: the row is deleted the moment it matches.
 */
function magic_link_consume(string $token, string $context): string
{
    $token = trim($token);
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token) || !in_array($context, ['member', 'admin'], true)) {
        return '';
    }

    // Expired rows are removed before lookup, so a surviving match is live.
    // Doubling as the cleanup sweep keeps abandoned tokens from accumulating.
    db()->prepare('DELETE FROM verification_tokens WHERE expires < NOW(3)')->execute();

    $tokenHash = hash('sha256', $token);
    $stmt = db()->prepare('SELECT identifier, token FROM verification_tokens WHERE token = ? LIMIT 1');
    $stmt->execute([$tokenHash]);
    $row = $stmt->fetch();
    if (!$row || !hash_equals((string) $row['token'], $tokenHash)) {
        return '';
    }

    $identifier = (string) $row['identifier'];
    $separator = strrpos($identifier, '|');
    $email = $separator !== false ? substr($identifier, 0, $separator) : '';
    $rowContext = $separator !== false ? substr($identifier, $separator + 1) : '';
    if ($email === '' || $rowContext !== $context) {
        return '';
    }

    db()->prepare('DELETE FROM verification_tokens WHERE identifier = ? AND token = ?')
        ->execute([$identifier, $tokenHash]);

    return $email;
}

/** Per-address and per-IP throttle for link requests. */
function magic_link_rate_limited(string $email): bool
{
    if (!function_exists('rate_limit_consume')) {
        return false;
    }

    $ipLimit = rate_limit_consume('magic_link_request', rate_limit_subject_for_scope('magic_link_request'));
    if (!$ipLimit['allowed']) {
        return true;
    }

    if ($email !== '') {
        $emailLimit = rate_limit_consume(
            'magic_link_email',
            operational_subject_hash('magic_link_email', 'email:' . hash('sha256', $email))
        );
        if (!$emailLimit['allowed']) {
            return true;
        }
    }

    return false;
}
