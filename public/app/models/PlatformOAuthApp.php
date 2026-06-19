<?php

declare(strict_types=1);

class PlatformOAuthApp
{
    public static function findByPlatform(string $platform): array|false
    {
        if (!self::tableExists()) {
            return false;
        }

        $stmt = db()->prepare('SELECT * FROM platform_oauth_apps WHERE platform = ? LIMIT 1');
        $stmt->execute([$platform]);
        return $stmt->fetch() ?: false;
    }

    public static function allByPlatform(): array
    {
        if (!self::tableExists()) {
            return [];
        }

        $rows = db()->query('SELECT * FROM platform_oauth_apps ORDER BY platform ASC')->fetchAll();
        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['platform']] = $row;
        }
        return $map;
    }

    public static function decryptedCredentialsForPlatform(string $platform): ?array
    {
        $row = self::findByPlatform($platform);
        if (!$row) {
            return null;
        }

        $encryptedClientId = (string) ($row['encrypted_client_id'] ?? '');
        $encryptedClientSecret = (string) ($row['encrypted_client_secret'] ?? '');
        if ($encryptedClientId === '' || $encryptedClientSecret === '') {
            return null;
        }

        try {
            return [
                'client_id' => decrypt_string($encryptedClientId, ai_encryption_key()),
                'client_secret' => decrypt_string($encryptedClientSecret, ai_encryption_key()),
                'blog_url' => trim((string) ($row['blog_url'] ?? '')) ?: null,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    public static function upsert(string $platform, ?string $clientId, ?string $clientSecret, ?string $blogUrl, bool $preserveSecrets = true): void
    {
        $existing = self::findByPlatform($platform) ?: [];
        $clientId = trim((string) $clientId);
        $clientSecret = trim((string) $clientSecret);

        $key = ai_encryption_key();

        $encryptedClientId = $clientId !== ''
            ? encrypt_string($clientId, $key)
            : (($preserveSecrets && !empty($existing['encrypted_client_id'])) ? $existing['encrypted_client_id'] : null);

        $encryptedClientSecret = $clientSecret !== ''
            ? encrypt_string($clientSecret, $key)
            : (($preserveSecrets && !empty($existing['encrypted_client_secret'])) ? $existing['encrypted_client_secret'] : null);

        if ($encryptedClientId === null || $encryptedClientSecret === null) {
            throw new InvalidArgumentException('Client ID and Client Secret are required.');
        }

        if ($clientId !== '' && decrypt_string($encryptedClientId, $key) !== $clientId) {
            throw new RuntimeException('Saved Client ID could not be verified; not storing it to avoid corrupted ciphertext.');
        }
        if ($clientSecret !== '' && decrypt_string($encryptedClientSecret, $key) !== $clientSecret) {
            throw new RuntimeException('Saved Client Secret could not be verified; not storing it to avoid corrupted ciphertext.');
        }

        if ($existing) {
            $stmt = db()->prepare(
                'UPDATE platform_oauth_apps
                    SET encrypted_client_id = ?, encrypted_client_secret = ?, blog_url = ?, updated_at = CURRENT_TIMESTAMP(3)
                 WHERE platform = ?'
            );
            $stmt->execute([$encryptedClientId, $encryptedClientSecret, $blogUrl, $platform]);
            return;
        }

        $stmt = db()->prepare(
            'INSERT INTO platform_oauth_apps
                (platform, encrypted_client_id, encrypted_client_secret, blog_url)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$platform, $encryptedClientId, $encryptedClientSecret, $blogUrl,]);
    }

    public static function hasConfiguredSecrets(string $platform): bool
    {
        $credentials = self::decryptedCredentialsForPlatform($platform);
        return $credentials !== null
            && trim((string) ($credentials['client_id'] ?? '')) !== ''
            && trim((string) ($credentials['client_secret'] ?? '')) !== '';
    }

    private static function tableExists(): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }

        try {
            $stmt = db()->prepare(
                'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $stmt->execute(['platform_oauth_apps']);
            $exists = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            $exists = false;
        }

        return $exists;
    }
}
