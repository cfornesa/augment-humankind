<?php

declare(strict_types=1);

class AdminIdentity
{
    public static function find(int $id): array|false
    {
        $stmt = db()->prepare('SELECT * FROM admin_identities WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function findActiveByProviderSubject(string $provider, string $subject): array|false
    {
        $stmt = db()->prepare(
            'SELECT * FROM admin_identities
             WHERE provider = ? AND provider_subject = ? AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute([$provider, $subject]);
        return $stmt->fetch();
    }

    public static function upsertFromProfile(array $profile): int
    {
        $existing = self::findActiveByProviderSubject($profile['provider'], $profile['provider_subject']);

        if ($existing) {
            $stmt = db()->prepare(
                'UPDATE admin_identities
                 SET email = ?, display_name = ?, avatar_url = ?, last_login_at = NOW(), updated_at = NOW()
                 WHERE id = ?'
            );
            $stmt->execute([
                $profile['email'] ?: null,
                $profile['display_name'],
                $profile['avatar_url'] ?: null,
                $existing['id'],
            ]);
            return (int) $existing['id'];
        }

        $stmt = db()->prepare(
            'INSERT INTO admin_identities
                (provider, provider_subject, email, display_name, avatar_url, is_active, last_login_at)
             VALUES (?, ?, ?, ?, ?, 1, NOW())'
        );
        $stmt->execute([
            $profile['provider'],
            $profile['provider_subject'],
            $profile['email'] ?: null,
            $profile['display_name'],
            $profile['avatar_url'] ?: null,
        ]);

        return (int) db()->lastInsertId();
    }
}
