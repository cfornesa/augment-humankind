<?php

declare(strict_types=1);

class SiteThemeSnapshot
{
    public static function create(array $data): int
    {
        $nextNumber = self::nextSnapshotNumber();
        $stmt = db()->prepare(
            'INSERT INTO site_theme_snapshots
             (snapshot_number, label, custom_css, custom_js, custom_html_body,
              is_draft_attempt, attempt_sequence_token, generation_prompt,
              generation_vendor, generation_model, ai_profile_id, ai_persona_id, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $nextNumber,
            $data['label'] ?? null,
            $data['custom_css'] ?? null,
            $data['custom_js'] ?? null,
            $data['custom_html_body'] ?? null,
            (int) ($data['is_draft_attempt'] ?? 0),
            $data['attempt_sequence_token'] ?? null,
            $data['generation_prompt'] ?? null,
            $data['generation_vendor'] ?? null,
            $data['generation_model'] ?? null,
            isset($data['ai_profile_id']) ? ((int) $data['ai_profile_id'] ?: null) : null,
            isset($data['ai_persona_id']) ? ((int) $data['ai_persona_id'] ?: null) : null,
            $data['notes'] ?? null,
        ]);
        return (int) db()->lastInsertId();
    }

    /** Flip a draft attempt to permanent and update its label/notes. */
    public static function promoteDraft(int $id, ?string $label = null): void
    {
        $stmt = db()->prepare(
            'UPDATE site_theme_snapshots SET is_draft_attempt = 0, label = ? WHERE id = ?'
        );
        $stmt->execute([$label, $id]);
    }

    /** Delete all draft attempts in a sequence except the one being accepted. */
    public static function deleteBySequenceToken(string $token, int $exceptId): void
    {
        $stmt = db()->prepare(
            'DELETE FROM site_theme_snapshots
             WHERE attempt_sequence_token = ? AND is_draft_attempt = 1 AND id != ?'
        );
        $stmt->execute([$token, $exceptId]);
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM site_theme_snapshots WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Most recent N non-draft snapshots for the history table. */
    public static function getLast(int $n = 10): array
    {
        $stmt = db()->prepare(
            'SELECT * FROM site_theme_snapshots
             WHERE is_draft_attempt = 0
             ORDER BY created_at DESC, id DESC LIMIT ?'
        );
        $stmt->execute([$n]);
        return $stmt->fetchAll();
    }

    private static function nextSnapshotNumber(): int
    {
        $row = db()->query('SELECT COALESCE(MAX(snapshot_number), 0) FROM site_theme_snapshots')->fetchColumn();
        return ((int) $row) + 1;
    }
}
