<?php

declare(strict_types=1);

function encrypt_string(string $plaintext, string $key): string
{
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    if ($ciphertext === false) {
        throw new RuntimeException('Encryption failed.');
    }
    return implode('.', [
        base64_encode($iv),
        base64_encode($tag),
        base64_encode($ciphertext),
    ]);
}

function decrypt_string(string $ciphertext, string $key): string
{
    if (substr_count($ciphertext, '.') === 2) {
        return decrypt_platform_secret($ciphertext, $key);
    }

    return decrypt_legacy_php_secret($ciphertext, $key);
}

function decrypt_platform_secret(string $ciphertext, string $key): string
{
    [$ivRaw, $tagRaw, $encryptedRaw] = explode('.', $ciphertext, 3);
    $iv = base64_decode($ivRaw, true);
    $tag = base64_decode($tagRaw, true);
    $encrypted = base64_decode($encryptedRaw, true);
    if ($iv === false || $tag === false || $encrypted === false) {
        throw new RuntimeException('Invalid ciphertext encoding.');
    }

    $plaintext = openssl_decrypt($encrypted, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($plaintext === false) {
        throw new RuntimeException('Decryption failed.');
    }
    return $plaintext;
}

function decrypt_legacy_php_secret(string $ciphertext, string $key): string
{
    $decoded = base64_decode($ciphertext, true);
    if ($decoded === false) {
        throw new RuntimeException('Invalid ciphertext encoding.');
    }

    $parts = explode(':', $decoded);
    if (count($parts) !== 3) {
        throw new RuntimeException('Invalid ciphertext format.');
    }

    [$iv, $cipher, $tag] = $parts;
    $plaintext = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($plaintext === false) {
        $legacyKey = hash('sha256', ai_encryption_key_raw_env(), true);
        $plaintext = openssl_decrypt($cipher, 'aes-256-gcm', $legacyKey, OPENSSL_RAW_DATA, $iv, $tag);
    }
    if ($plaintext === false) {
        throw new RuntimeException('Decryption failed.');
    }
    return $plaintext;
}

/**
 * Reads the AI settings encryption key from the environment. Checks
 * AI_SETTINGS_ENCRYPTION_KEY first; falls back to the older
 * PLATFORM_AI_SETTINGS_ENCRYPTION_KEY name so deployments that set it before
 * the rename keep working without an immediate .env edit. New deployments
 * should only ever need AI_SETTINGS_ENCRYPTION_KEY — the PLATFORM_ prefix
 * was misleading since this key is used by this app's own AI vendor key
 * storage, not just legacy platform migration.
 */
function ai_encryption_key_raw_env(): string
{
    // getenv() returns false (not null) when unset, so a plain ?? chain
    // won't fall through correctly — check each source explicitly.
    foreach (['AI_SETTINGS_ENCRYPTION_KEY', 'PLATFORM_AI_SETTINGS_ENCRYPTION_KEY'] as $name) {
        $value = $_ENV[$name] ?? null;
        if (!is_string($value) || $value === '') {
            $fromGetenv = getenv($name);
            $value = $fromGetenv !== false ? $fromGetenv : '';
        }
        if (is_string($value) && $value !== '') {
            return $value;
        }
    }
    return '';
}

/**
 * Get the configured encryption key from AI_SETTINGS_ENCRYPTION_KEY.
 * Returns a 32-byte key derived from the env value.
 */
function ai_encryption_key(): string
{
    $envKey = trim(ai_encryption_key_raw_env());
    if ($envKey === '') {
        throw new RuntimeException('AI_SETTINGS_ENCRYPTION_KEY is not configured.');
    }

    if (preg_match('/^[0-9a-f]+$/i', $envKey) === 1 && strlen($envKey) % 2 === 0) {
        $candidate = hex2bin($envKey);
        if ($candidate !== false && strlen($candidate) === 32) {
            return $candidate;
        }
    }

    $candidate = base64_decode($envKey, true);
    if ($candidate !== false && strlen($candidate) === 32) {
        return $candidate;
    }

    if (strlen($envKey) === 32) {
        return $envKey;
    }

    throw new RuntimeException('AI_SETTINGS_ENCRYPTION_KEY must decode to exactly 32 bytes.');
}
