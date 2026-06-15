<?php

declare(strict_types=1);

class PlatformArtPiece
{
    public static function all(): array
    {
        if (!self::tableExists()) {
            return [];
        }

        $rows = db()->query(
            "SELECT ap.*, u.name AS owner_name
             FROM art_pieces ap
             LEFT JOIN users u ON u.id = ap.owner_user_id
             WHERE ap.deleted_at IS NULL
               AND ap.status = 'active'
             ORDER BY ap.created_at DESC, ap.id DESC"
        )->fetchAll();

        return self::attachCurrentVersion($rows);
    }

    public static function findBySlug(string $id): array|false
    {
        if (!self::tableExists()) {
            return false;
        }

        // art_pieces has no slug column — use numeric id
        $stmt = db()->prepare(
            "SELECT ap.*, u.name AS owner_name
             FROM art_pieces ap
             LEFT JOIN users u ON u.id = ap.owner_user_id
             WHERE ap.id = ? AND ap.deleted_at IS NULL AND ap.status = 'active'
             LIMIT 1"
        );
        $stmt->execute([(int) $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }

        $rows = self::attachCurrentVersion([$row]);
        return $rows[0] ?? false;
    }

    public static function allForAdmin(): array
    {
        if (!self::tableExists()) {
            return [];
        }

        $rows = db()->query(
            "SELECT ap.*, u.name AS owner_name
             FROM art_pieces ap
             LEFT JOIN users u ON u.id = ap.owner_user_id
             WHERE ap.deleted_at IS NULL
             ORDER BY ap.created_at DESC, ap.id DESC"
        )->fetchAll();

        return self::attachCurrentVersion($rows);
    }

    public static function find(int $id): array|false
    {
        if (!self::tableExists()) {
            return false;
        }

        $stmt = db()->prepare(
            "SELECT ap.*, u.name AS owner_name
             FROM art_pieces ap
             LEFT JOIN users u ON u.id = ap.owner_user_id
             WHERE ap.id = ? AND ap.deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }

        $rows = self::attachCurrentVersion([$row]);
        return $rows[0] ?? false;
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO art_pieces
                (owner_user_id, title, prompt, engine, status,
                 thumbnail_url, description, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $data['owner_user_id'] ?? null,
            $data['title'],
            $data['prompt'] ?? null,
            $data['engine'] ?? 'p5',
            $data['status'] ?? 'active',
            $data['thumbnail_url'] ?? null,
            $data['description'] ?? null,
        ]);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE art_pieces SET
                title = ?, prompt = ?, engine = ?, status = ?,
                thumbnail_url = ?, description = ?, updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([
            $data['title'],
            $data['prompt'] ?? null,
            $data['engine'] ?? 'p5',
            $data['status'] ?? 'active',
            $data['thumbnail_url'] ?? null,
            $data['description'] ?? null,
            $id,
        ]);
    }

    public static function softDelete(int $id): void
    {
        $stmt = db()->prepare('UPDATE art_pieces SET deleted_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function restore(int $id): void
    {
        $stmt = db()->prepare('UPDATE art_pieces SET deleted_at = NULL WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function hardDelete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM art_pieces WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function trashed(): array
    {
        if (!self::tableExists()) {
            return [];
        }

        $rows = db()->query(
            "SELECT ap.*, u.name AS owner_name
             FROM art_pieces ap
             LEFT JOIN users u ON u.id = ap.owner_user_id
             WHERE ap.deleted_at IS NOT NULL
             ORDER BY ap.deleted_at DESC"
        )->fetchAll();

        return self::attachCurrentVersion($rows);
    }

    public static function trashedCount(): int
    {
        if (!self::tableExists()) {
            return 0;
        }

        return (int) db()->query(
            'SELECT COUNT(*) FROM art_pieces WHERE deleted_at IS NOT NULL'
        )->fetchColumn();
    }

    public static function updateCurrentVersion(int $pieceId, int $versionId): void
    {
        $stmt = db()->prepare(
            'UPDATE art_pieces SET current_version_id = ? WHERE id = ?'
        );
        $stmt->execute([$versionId, $pieceId]);
    }

    private static function attachCurrentVersion(array $rows): array
    {
        if ($rows === [] || !self::versionTableExists()) {
            return $rows;
        }

        $ids = array_map(static fn (array $r): int => (int) $r['id'], $rows);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $stmt = db()->prepare(
            "SELECT id, art_piece_id, version_number, prompt, structured_spec,
                    html_code, css_code, generated_code, engine,
                    generation_vendor, generation_model, validation_status,
                    generation_attempt_count, notes
             FROM art_piece_versions
             WHERE art_piece_id IN ($placeholders)"
        );
        $stmt->execute($ids);

        $byPiece = [];
        foreach ($stmt->fetchAll() as $version) {
            $pieceId = (int) $version['art_piece_id'];
            if (!isset($byPiece[$pieceId])) {
                $byPiece[$pieceId] = [];
            }
            $byPiece[$pieceId][] = $version;
        }

        foreach ($rows as &$row) {
            $pieceId = (int) $row['id'];
            $versions = $byPiece[$pieceId] ?? [];
            $row['versions'] = $versions;
            $row['version_count'] = count($versions);

            $currentVersionId = (int) ($row['current_version_id'] ?? 0);
            $currentVersion = null;
            foreach ($versions as $v) {
                if ((int) $v['id'] === $currentVersionId) {
                    $currentVersion = $v;
                    break;
                }
            }
            if (!$currentVersion && $versions !== []) {
                $currentVersion = $versions[0];
            }
            $row['current_version'] = $currentVersion;
        }
        unset($row);

        return $rows;
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
            $stmt->execute(['art_pieces']);
            return $exists = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return $exists = false;
        }
    }

    private static function versionTableExists(): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }
        try {
            $stmt = db()->prepare(
                'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $stmt->execute(['art_piece_versions']);
            return $exists = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return $exists = false;
        }
    }
}
