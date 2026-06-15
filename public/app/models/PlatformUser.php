<?php

declare(strict_types=1);

class PlatformUser
{
    public static function upsertOwnerFromAdminProfile(string $provider, array $profile): ?string
    {
        if (!self::tableExists()) {
            return null;
        }

        $email = trim((string) ($profile['email'] ?? ''));
        $providerSubject = trim((string) ($profile['provider_subject'] ?? ''));
        if ($email === '' && $providerSubject === '') {
            return null;
        }

        $existing = self::findByEmail($email);
        $id = $existing['id'] ?? self::uuid();
        $username = $existing['username'] ?? self::usernameFromProfile($provider, $profile);

        if ($existing) {
            $stmt = db()->prepare(
                'UPDATE users
                 SET name = ?, image = ?, role = ?, status = ?, username = COALESCE(username, ?), last_login_at = NOW(), updated_at = NOW()
                 WHERE id = ?'
            );
            $stmt->execute([
                $profile['display_name'] ?? $existing['name'] ?? 'Owner',
                $profile['avatar_url'] ?? $existing['image'] ?? null,
                'owner',
                'active',
                $username,
                $id,
            ]);
        } else {
            $stmt = db()->prepare(
                'INSERT INTO users
                    (id, name, username, email, image, role, status, last_login_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $id,
                $profile['display_name'] ?? 'Owner',
                $username,
                $email !== '' ? $email : null,
                $profile['avatar_url'] ?? null,
                'owner',
                'active',
            ]);
        }

        self::upsertAccount($id, $provider, $providerSubject);
        return $id;
    }

    public static function owner(): array|false
    {
        if (!self::tableExists()) {
            return false;
        }
        return db()->query("SELECT * FROM users WHERE role = 'owner' ORDER BY created_at ASC LIMIT 1")->fetch();
    }

    private static function findByEmail(string $email): array|false
    {
        if ($email === '') {
            return false;
        }
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    private static function upsertAccount(string $userId, string $provider, string $providerSubject): void
    {
        if ($providerSubject === '' || !self::accountsTableExists()) {
            return;
        }
        $stmt = db()->prepare(
            'INSERT IGNORE INTO accounts (user_id, type, provider, provider_account_id)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, 'oauth', $provider, $providerSubject]);
    }

    private static function usernameFromProfile(string $provider, array $profile): ?string
    {
        $base = $provider === 'github'
            ? (string) ($profile['login'] ?? $profile['display_name'] ?? '')
            : (string) ($profile['display_name'] ?? '');
        $base = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9_]+/', '-', $base), '-'));
        return $base !== '' ? substr($base, 0, 100) : null;
    }

    private static function tableExists(): bool
    {
        try {
            return (bool) db()->query("SHOW TABLES LIKE 'users'")->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    private static function accountsTableExists(): bool
    {
        try {
            return (bool) db()->query("SHOW TABLES LIKE 'accounts'")->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
