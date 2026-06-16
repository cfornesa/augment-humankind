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
             ORDER BY ap.sort_order ASC, ap.id ASC"
        )->fetchAll();

        return self::attachCategories(self::attachCurrentVersion($rows));
    }

    public static function paginate(int $offset, int $limit): array
    {
        if (!self::tableExists()) {
            return [];
        }

        $stmt = db()->prepare(
            "SELECT ap.*, u.name AS owner_name
             FROM art_pieces ap
             LEFT JOIN users u ON u.id = ap.owner_user_id
             WHERE ap.deleted_at IS NULL
               AND ap.status = 'active'
             ORDER BY ap.sort_order ASC, ap.id ASC
             LIMIT ?, ?"
        );
        $stmt->bindValue(1, max(0, $offset), PDO::PARAM_INT);
        $stmt->bindValue(2, max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return self::attachCategories(self::attachCurrentVersion($stmt->fetchAll()));
    }

    public static function countActive(): int
    {
        if (!self::tableExists()) {
            return 0;
        }

        return (int) db()->query(
            "SELECT COUNT(*) FROM art_pieces WHERE deleted_at IS NULL AND status = 'active'"
        )->fetchColumn();
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

        $rows = self::attachCategories(self::attachCurrentVersion([$row]));
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
             ORDER BY ap.sort_order ASC, ap.id ASC"
        )->fetchAll();

        return self::attachCategories(self::attachCurrentVersion($rows));
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

        $rows = self::attachCategories(self::attachCurrentVersion([$row]));
        return $rows[0] ?? false;
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO art_pieces
                (owner_user_id, title, prompt, engine, status,
                 thumbnail_url, description, sort_order, comments_enabled, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $data['owner_user_id'] ?? null,
            $data['title'],
            $data['prompt'] ?? null,
            $data['engine'] ?? 'p5',
            $data['status'] ?? 'active',
            $data['thumbnail_url'] ?? null,
            $data['description'] ?? null,
            $data['sort_order'] ?? self::nextSortOrder(),
            isset($data['comments_enabled']) ? (int)(bool) $data['comments_enabled'] : 0,
        ]);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE art_pieces SET
                title = ?, prompt = ?, engine = ?, status = ?,
                thumbnail_url = ?, description = ?, sort_order = ?, comments_enabled = ?, updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([
            $data['title'],
            $data['prompt'] ?? null,
            $data['engine'] ?? 'p5',
            $data['status'] ?? 'active',
            $data['thumbnail_url'] ?? null,
            $data['description'] ?? null,
            $data['sort_order'] ?? 0,
            isset($data['comments_enabled']) ? (int)(bool) $data['comments_enabled'] : 0,
            $id,
        ]);
    }

    public static function reorder(array $ids): void
    {
        $stmt = db()->prepare('UPDATE art_pieces SET sort_order = ? WHERE id = ? AND deleted_at IS NULL');
        foreach (array_values($ids) as $index => $id) {
            $stmt->execute([$index, $id]);
        }
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

        return self::attachCategories(self::attachCurrentVersion($rows));
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

    public static function setStatus(int $id, string $status): void
    {
        $stmt = db()->prepare('UPDATE art_pieces SET status = ?, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$status, $id]);
    }

    public static function updateCurrentVersion(int $pieceId, int $versionId): void
    {
        $stmt = db()->prepare(
            'UPDATE art_pieces SET current_version_id = ? WHERE id = ?'
        );
        $stmt->execute([$versionId, $pieceId]);
    }

    public static function categoryIds(int $pieceId): array
    {
        if (!self::relationTableExists('art_piece_categories')) {
            return [];
        }

        $stmt = db()->prepare('SELECT category_id FROM art_piece_categories WHERE art_piece_id = ?');
        $stmt->execute([$pieceId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public static function syncCategories(int $pieceId, array $categoryIds): void
    {
        if (!self::relationTableExists('art_piece_categories')) {
            return;
        }

        $categoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds))));
        $pdo = db();
        $del = $pdo->prepare('DELETE FROM art_piece_categories WHERE art_piece_id = ?');
        $del->execute([$pieceId]);

        if ($categoryIds === []) {
            return;
        }

        $ins = $pdo->prepare('INSERT INTO art_piece_categories (art_piece_id, category_id) VALUES (?, ?)');
        foreach ($categoryIds as $categoryId) {
            $ins->execute([$pieceId, $categoryId]);
        }
    }

    public static function attachCurrentVersionPublic(array $rows): array
    {
        return self::attachCategories(self::attachCurrentVersion($rows));
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

    private static function attachCategories(array $rows): array
    {
        if ($rows === [] || !self::relationTableExists('art_piece_categories')) {
            return $rows;
        }

        $ids = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        try {
            $stmt = db()->prepare(
                "SELECT apc.art_piece_id, c.id, c.name, c.slug
                 FROM art_piece_categories apc
                 JOIN categories c
                   ON c.id = apc.category_id
                  AND c.deleted_at IS NULL
                  AND c.category_scope = 'portfolio'
                 WHERE apc.art_piece_id IN ($placeholders)
                 ORDER BY c.sort_order ASC, c.id ASC"
            );
            $stmt->execute($ids);
        } catch (Throwable) {
            return $rows;
        }

        $byPiece = [];
        foreach ($stmt->fetchAll() as $category) {
            $byPiece[(int) $category['art_piece_id']][] = [
                'id' => (int) $category['id'],
                'name' => $category['name'],
                'slug' => $category['slug'],
            ];
        }

        foreach ($rows as &$row) {
            $row['categories'] = $byPiece[(int) $row['id']] ?? [];
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

    private static function relationTableExists(string $tableName): bool
    {
        static $cache = [];
        if (array_key_exists($tableName, $cache)) {
            return $cache[$tableName];
        }

        try {
            $stmt = db()->prepare(
                'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $stmt->execute([$tableName]);
            $cache[$tableName] = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            $cache[$tableName] = false;
        }

        return $cache[$tableName];
    }

    private static function nextSortOrder(): int
    {
        return (int) db()->query(
            'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM art_pieces'
        )->fetchColumn();
    }
}
