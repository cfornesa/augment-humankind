<?php

declare(strict_types=1);

class MediaFile
{
    public static function supportsTitle(): bool
    {
        return ah_column_exists('media_files', 'title');
    }

    public static function supportsAltText(): bool
    {
        return ah_column_exists('media_files', 'alt_text');
    }

    public static function supportsStatus(): bool
    {
        return ah_column_exists('media_files', 'status');
    }

    public static function supportsPosterMediaFileId(): bool
    {
        return ah_column_exists('media_files', 'poster_media_file_id');
    }

    public static function supportsConfirmedAt(): bool
    {
        return ah_column_exists('media_files', 'confirmed_at');
    }

    public static function create(string $data, string $mimeType, ?string $originalName = null, array $attributes = []): int
    {
        $pdo  = db();
        $title = null;
        if ($originalName !== null && $originalName !== '') {
            $title = pathinfo($originalName, PATHINFO_FILENAME) ?: $originalName;
        }

        $fields = ['data', 'mime_type', 'byte_size', 'original_name'];
        $values = ['?', '?', '?', '?'];
        $params = [$data, $mimeType, mb_strlen($data, '8bit'), $originalName ?: null];

        if (self::supportsTitle()) {
            $fields[] = 'title';
            $values[] = '?';
            $params[] = array_key_exists('title', $attributes) ? $attributes['title'] : $title;
        }
        if (self::supportsAltText() && array_key_exists('alt_text', $attributes)) {
            $fields[] = 'alt_text';
            $values[] = '?';
            $params[] = $attributes['alt_text'];
        }
        if (self::supportsStatus() && array_key_exists('status', $attributes)) {
            $fields[] = 'status';
            $values[] = '?';
            $params[] = $attributes['status'];
        }
        if (self::supportsPosterMediaFileId() && array_key_exists('poster_media_file_id', $attributes)) {
            $fields[] = 'poster_media_file_id';
            $values[] = '?';
            $params[] = $attributes['poster_media_file_id'];
        }
        if (self::supportsConfirmedAt() && array_key_exists('confirmed_at', $attributes)) {
            $fields[] = 'confirmed_at';
            $values[] = '?';
            $params[] = $attributes['confirmed_at'];
        }

        $stmt = $pdo->prepare(
            'INSERT INTO media_files (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $values) . ')'
        );
        $blobParam = $params[0];
        $stmt->bindParam(1, $blobParam, PDO::PARAM_LOB);
        foreach (array_slice($params, 1) as $index => $param) {
            $stmt->bindValue($index + 2, $param);
        }
        $stmt->execute();
        return (int) $pdo->lastInsertId();
    }

    private static function selectColumns(): string
    {
        $titleSelect = self::supportsTitle() ? 'title' : 'NULL AS title';
        $altTextSelect = self::supportsAltText() ? 'alt_text' : 'NULL AS alt_text';
        $statusSelect = self::supportsStatus() ? 'status' : "'ready' AS status";
        $posterSelect = self::supportsPosterMediaFileId() ? 'poster_media_file_id' : 'NULL AS poster_media_file_id';
        $confirmedAtSelect = self::supportsConfirmedAt() ? 'confirmed_at' : 'NULL AS confirmed_at';

        return 'id, mime_type, byte_size, original_name, ' . $titleSelect . ', ' . $altTextSelect . ', '
            . $statusSelect . ', ' . $posterSelect . ', ' . $confirmedAtSelect . ', deleted_at, created_at';
    }

    public static function all(bool $includeDrafts = true): array
    {
        $sql = 'SELECT ' . self::selectColumns() . '
             FROM media_files
             WHERE deleted_at IS NULL';
        if (!$includeDrafts && self::supportsStatus()) {
            $sql .= " AND status = 'ready'";
        }
        $sql .= ' ORDER BY created_at DESC';
        return db()->query($sql)->fetchAll();
    }

    public static function ready(): array
    {
        return self::all(false);
    }

    public static function updateMetadata(int $id, ?string $title, ?string $altText): bool
    {
        $sets = [];
        $params = [];

        if (self::supportsTitle()) {
            $sets[] = 'title = ?';
            $params[] = $title;
        }

        if (self::supportsAltText()) {
            $sets[] = 'alt_text = ?';
            $params[] = $altText;
        }

        if ($sets === []) {
            return false;
        }

        $params[] = $id;
        $stmt = db()->prepare('UPDATE media_files SET ' . implode(', ', $sets) . ' WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute($params);
        return true;
    }

    public static function updatePoster(int $id, ?int $posterMediaFileId): bool
    {
        if (!self::supportsPosterMediaFileId()) {
            return false;
        }
        $stmt = db()->prepare('UPDATE media_files SET poster_media_file_id = ? WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$posterMediaFileId, $id]);
        return true;
    }

    public static function confirmDraft(int $id, ?string $title, string $altText, ?int $posterMediaFileId = null): bool
    {
        $sets = [];
        $params = [];

        if (self::supportsTitle()) {
            $sets[] = 'title = ?';
            $params[] = $title;
        }
        if (self::supportsAltText()) {
            $sets[] = 'alt_text = ?';
            $params[] = $altText;
        }
        if (self::supportsPosterMediaFileId()) {
            $sets[] = 'poster_media_file_id = ?';
            $params[] = $posterMediaFileId;
        }
        if (self::supportsStatus()) {
            $sets[] = "status = 'ready'";
        }
        if (self::supportsConfirmedAt()) {
            $sets[] = 'confirmed_at = NOW()';
        }

        if ($sets === []) {
            return false;
        }

        $params[] = $id;
        $stmt = db()->prepare('UPDATE media_files SET ' . implode(', ', $sets) . ' WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute($params);
        return true;
    }

    public static function updateAltText(int $id, ?string $altText): bool
    {
        return self::updateMetadata($id, null, $altText);
    }

    public static function trashed(): array
    {
        return db()->query(
            'SELECT ' . self::selectColumns() . '
             FROM media_files
             WHERE deleted_at IS NOT NULL
             ORDER BY deleted_at DESC'
        )->fetchAll();
    }

    public static function trashedCount(): int
    {
        return (int) db()->query(
            'SELECT COUNT(*) FROM media_files WHERE deleted_at IS NOT NULL'
        )->fetchColumn();
    }

    public static function find(int $id): array|false
    {
        $stmt = db()->prepare(
            'SELECT ' . self::selectColumns() . '
             FROM media_files
             WHERE id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function getData(int $id): array|false
    {
        $titleSelect = self::supportsTitle() ? 'title' : 'NULL AS title';
        $altTextSelect = self::supportsAltText() ? 'alt_text' : 'NULL AS alt_text';
        $statusSelect = self::supportsStatus() ? 'status' : "'ready' AS status";
        $posterSelect = self::supportsPosterMediaFileId() ? 'poster_media_file_id' : 'NULL AS poster_media_file_id';
        $confirmedAtSelect = self::supportsConfirmedAt() ? 'confirmed_at' : 'NULL AS confirmed_at';
        $stmt = db()->prepare(
            'SELECT id, mime_type, byte_size, original_name, ' . $titleSelect . ', ' . $altTextSelect . ', '
            . $statusSelect . ', ' . $posterSelect . ', ' . $confirmedAtSelect . ', deleted_at, data
             FROM media_files
             WHERE id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function softDelete(int $id): void
    {
        $stmt = db()->prepare('UPDATE media_files SET deleted_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function hardDelete(int $id): void
    {
        if (self::find($id)) {
            $stmt = db()->prepare('DELETE FROM media_files WHERE id = ?');
            $stmt->execute([$id]);
        }
    }

    public static function discardDraft(int $id): void
    {
        $row = self::find($id);
        if (!$row || ($row['status'] ?? 'ready') !== 'draft') {
            return;
        }
        self::hardDelete($id);
    }

    public static function restore(int $id): void
    {
        $stmt = db()->prepare('UPDATE media_files SET deleted_at = NULL WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function isActiveOfKind(int $id, string $kind): bool
    {
        $row = self::find($id);
        if (!$row || $row['deleted_at'] !== null) {
            return false;
        }

        return match ($kind) {
            'image' => str_starts_with((string) ($row['mime_type'] ?? ''), 'image/'),
            'video' => str_starts_with((string) ($row['mime_type'] ?? ''), 'video/'),
            default => false,
        };
    }
}
