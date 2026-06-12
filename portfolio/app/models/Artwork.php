<?php

declare(strict_types=1);

class Artwork
{
    public static function allSorted(): array
    {
        $rows = db()->query(
            'SELECT a.*
             FROM artworks a
             WHERE a.deleted_at IS NULL
             ORDER BY a.sort_order ASC, a.id ASC'
        )->fetchAll();

        return self::attachExhibits(self::attachCategories($rows));
    }

    public static function allGroupedByCategory(): array
    {
        $categories = db()->query(
            'SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY sort_order ASC, id ASC'
        )->fetchAll();

        $uncategorized = db()->query(
            'SELECT * FROM artworks WHERE category_id IS NULL AND deleted_at IS NULL ORDER BY sort_order ASC, id ASC'
        )->fetchAll();

        $result = [];

        foreach ($categories as $cat) {
            $stmt = db()->prepare(
                'SELECT * FROM artworks WHERE category_id = ? AND deleted_at IS NULL ORDER BY sort_order ASC, id ASC'
            );
            $stmt->execute([$cat['id']]);
            $works = $stmt->fetchAll();
            if ($works) {
                $result[] = ['category' => $cat, 'artworks' => $works];
            }
        }

        if ($uncategorized) {
            $result[] = ['category' => null, 'artworks' => $uncategorized];
        }

        return $result;
    }

    public static function all(): array
    {
        $rows = db()->query(
            'SELECT a.*
             FROM artworks a
             WHERE a.deleted_at IS NULL
             ORDER BY a.sort_order ASC, a.id ASC'
        )->fetchAll();

        return self::attachExhibits(self::attachCategories($rows));
    }

    public static function find(int $id): array|false
    {
        $stmt = db()->prepare(
            'SELECT a.*
             FROM artworks a
             WHERE a.id = ? AND a.deleted_at IS NULL'
        );
        $stmt->execute([$id]);
        $artwork = $stmt->fetch();
        return $artwork ? self::decorate($artwork) : false;
    }

    public static function findBySlug(string $slug): array|false
    {
        $stmt = db()->prepare(
            'SELECT a.*
             FROM artworks a
             WHERE a.slug = ? AND a.deleted_at IS NULL'
        );
        $stmt->execute([$slug]);
        $artwork = $stmt->fetch();
        return $artwork ? self::decorate($artwork) : false;
    }

    public static function trashed(): array
    {
        $rows = db()->query(
            'SELECT a.*
             FROM artworks a
             WHERE a.deleted_at IS NOT NULL
             ORDER BY a.deleted_at DESC'
        )->fetchAll();

        return self::attachExhibits(self::attachCategories($rows));
    }

    public static function trashedCount(): int
    {
        return (int) db()->query(
            'SELECT COUNT(*) FROM artworks WHERE deleted_at IS NOT NULL'
        )->fetchColumn();
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO artworks
                (title, artist_name, slug, year, medium, dimensions, description, placard_notes,
                 thumbnail_type, thumbnail_value,
                 piece_type, piece_value, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['title'],
            $data['artist_name'] ?: null,
            $data['slug'],
            $data['year'] ?: null,
            $data['medium'] ?: null,
            $data['dimensions'] ?: null,
            $data['description'] ?: null,
            $data['placard_notes'] ?: null,
            $data['thumbnail_type'] ?: null,
            $data['thumbnail_value'] ?: null,
            $data['piece_type'],
            $data['piece_value'],
            $data['sort_order'] ?? 0,
        ]);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE artworks SET
                title = ?, artist_name = ?, slug = ?, year = ?, medium = ?, dimensions = ?,
                description = ?, placard_notes = ?,
                thumbnail_type = ?, thumbnail_value = ?,
                piece_type = ?, piece_value = ?, sort_order = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['title'],
            $data['artist_name'] ?: null,
            $data['slug'],
            $data['year'] ?: null,
            $data['medium'] ?: null,
            $data['dimensions'] ?: null,
            $data['description'] ?: null,
            $data['placard_notes'] ?: null,
            $data['thumbnail_type'] ?: null,
            $data['thumbnail_value'] ?: null,
            $data['piece_type'],
            $data['piece_value'],
            $data['sort_order'] ?? 0,
            $id,
        ]);
    }

    public static function legacyPieceFromMediaItems(array $items): array
    {
        if ($items === []) {
            return ['piece_type' => 'image_link', 'piece_value' => ''];
        }

        $first = $items[0];

        if (($first['media_kind'] ?? '') === 'iframe') {
            return [
                'piece_type' => 'embed',
                'piece_value' => trim((string) ($first['iframe_html'] ?? '')),
            ];
        }

        $sourceUrl = null;
        $mediaFileId = (int) ($first['media_file_id'] ?? 0);
        if ($mediaFileId > 0) {
            $sourceUrl = '/media/' . $mediaFileId;
            if (($first['media_kind'] ?? '') === 'image') {
                $sourceUrl = '/image/' . $mediaFileId;
            }
        }

        return [
            'piece_type' => ($first['media_kind'] ?? '') === 'video' ? 'image_link' : 'image_link',
            'piece_value' => $sourceUrl ?: '',
        ];
    }

    public static function resolvedMediaItems(array $artwork): array
    {
        $artworkId = (int) ($artwork['id'] ?? 0);
        $items = $artworkId > 0 ? ArtworkMediaItem::allForArtwork($artworkId) : [];

        if ($items === []) {
            $legacy = ArtworkMediaItem::buildLegacyItem($artwork);
            return $legacy ? [ArtworkMediaItem::normalizeForDisplay($legacy)] : [];
        }

        return array_map(
            static fn (array $item): array => ArtworkMediaItem::normalizeForDisplay($item),
            $items
        );
    }

    public static function previewImage(array $artwork): ?string
    {
        if (!empty($artwork['thumbnail_value'])) {
            return (string) $artwork['thumbnail_value'];
        }

        foreach (self::resolvedMediaItems($artwork) as $item) {
            if (($item['display_kind'] ?? '') === 'image' && !empty($item['source_url'])) {
                return (string) $item['source_url'];
            }
            if (($item['display_kind'] ?? '') === 'video' && !empty($item['poster_url'])) {
                return (string) $item['poster_url'];
            }
        }

        return null;
    }

    private static function decorate(array $artwork): array
    {
        $artwork['media_items'] = self::resolvedMediaItems($artwork);
        $artwork['categories']  = self::categoriesFor((int) $artwork['id']);
        $artwork['exhibits']    = self::exhibitsFor((int) $artwork['id']);
        return $artwork;
    }

    public static function categoriesFor(int $artworkId): array
    {
        $stmt = db()->prepare(
            'SELECT c.id, c.name, c.slug
             FROM artwork_categories ac
             JOIN categories c ON c.id = ac.category_id AND c.deleted_at IS NULL
             WHERE ac.artwork_id = ?
             ORDER BY c.sort_order ASC, c.id ASC'
        );
        $stmt->execute([$artworkId]);
        return $stmt->fetchAll();
    }

    public static function categoryIds(int $id): array
    {
        $stmt = db()->prepare('SELECT category_id FROM artwork_categories WHERE artwork_id = ?');
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function syncCategories(int $id, array $categoryIds): void
    {
        $pdo = db();
        $del = $pdo->prepare('DELETE FROM artwork_categories WHERE artwork_id = ?');
        $del->execute([$id]);

        if (empty($categoryIds)) {
            return;
        }

        $ins = $pdo->prepare('INSERT INTO artwork_categories (artwork_id, category_id) VALUES (?, ?)');
        foreach (array_unique(array_map('intval', $categoryIds)) as $categoryId) {
            $ins->execute([$id, $categoryId]);
        }
    }

    private static function attachCategories(array $artworks): array
    {
        if ($artworks === []) {
            return $artworks;
        }

        $ids = array_map(static fn (array $a): int => (int) $a['id'], $artworks);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare(
            "SELECT ac.artwork_id, c.id, c.name, c.slug
             FROM artwork_categories ac
             JOIN categories c ON c.id = ac.category_id AND c.deleted_at IS NULL
             WHERE ac.artwork_id IN ($placeholders)
             ORDER BY c.sort_order ASC, c.id ASC"
        );
        $stmt->execute($ids);

        $byArtwork = [];
        foreach ($stmt->fetchAll() as $row) {
            $byArtwork[$row['artwork_id']][] = ['id' => $row['id'], 'name' => $row['name'], 'slug' => $row['slug']];
        }

        foreach ($artworks as &$artwork) {
            $artwork['categories'] = $byArtwork[$artwork['id']] ?? [];
        }

        return $artworks;
    }

    public static function exhibitsFor(int $artworkId): array
    {
        $stmt = db()->prepare(
            'SELECT e.id, e.name, e.slug
             FROM exhibit_artworks ea
             JOIN exhibits e ON e.id = ea.exhibit_id AND e.deleted_at IS NULL
             WHERE ea.artwork_id = ?
             ORDER BY e.sort_order ASC, e.id ASC'
        );
        $stmt->execute([$artworkId]);
        return $stmt->fetchAll();
    }

    private static function attachExhibits(array $artworks): array
    {
        if ($artworks === []) {
            return $artworks;
        }

        $ids = array_map(static fn (array $a): int => (int) $a['id'], $artworks);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare(
            "SELECT ea.artwork_id, e.id, e.name, e.slug
             FROM exhibit_artworks ea
             JOIN exhibits e ON e.id = ea.exhibit_id AND e.deleted_at IS NULL
             WHERE ea.artwork_id IN ($placeholders)
             ORDER BY e.sort_order ASC, e.id ASC"
        );
        $stmt->execute($ids);

        $byArtwork = [];
        foreach ($stmt->fetchAll() as $row) {
            $byArtwork[$row['artwork_id']][] = ['id' => $row['id'], 'name' => $row['name'], 'slug' => $row['slug']];
        }

        foreach ($artworks as &$artwork) {
            $artwork['exhibits'] = $byArtwork[$artwork['id']] ?? [];
        }

        return $artworks;
    }

    public static function softDelete(int $id): void
    {
        $stmt = db()->prepare('UPDATE artworks SET deleted_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function hardDelete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM artworks WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function restore(int $id): void
    {
        $stmt = db()->prepare('UPDATE artworks SET deleted_at = NULL WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function inspectPiece(array $artwork): array
    {
        $pieceType = (string) ($artwork['piece_type'] ?? '');
        $pieceValue = trim((string) ($artwork['piece_value'] ?? ''));

        if ($pieceType === 'embed') {
            if ($pieceValue === '') {
                return [
                    'type' => 'embed',
                    'valid' => false,
                    'reason' => 'missing',
                    'message' => 'This artwork does not currently have embed code saved.',
                    'source' => '',
                ];
            }

            if (stripos($pieceValue, '<iframe') === false) {
                return [
                    'type' => 'embed',
                    'valid' => false,
                    'reason' => 'malformed',
                    'message' => 'The saved embed content is not valid iframe markup.',
                    'source' => $pieceValue,
                ];
            }

            $src = self::extractIframeSrc($pieceValue);
            if ($src === null) {
                return [
                    'type' => 'embed',
                    'valid' => false,
                    'reason' => 'malformed',
                    'message' => 'The saved embed code is missing an iframe source URL.',
                    'source' => $pieceValue,
                ];
            }

            return [
                'type' => 'embed',
                'valid' => true,
                'reason' => null,
                'message' => 'Rendering stored iframe embed code.',
                'source' => $src,
            ];
        }

        if ($pieceValue === '') {
            return [
                'type' => 'image',
                'valid' => false,
                'reason' => 'missing',
                'message' => 'This artwork does not currently have an image source saved.',
                'source' => '',
            ];
        }

        if (stripos($pieceValue, '<iframe') !== false || stripos($pieceValue, '<img') !== false) {
            return [
                'type' => 'image',
                'valid' => false,
                'reason' => 'html-in-image-field',
                'message' => 'The image source contains HTML instead of a direct image URL.',
                'source' => $pieceValue,
            ];
        }

        if (!self::isDirectMediaUrl($pieceValue)) {
            return [
                'type' => 'image',
                'valid' => false,
                'reason' => 'invalid-url',
                'message' => 'The saved image source is not a supported direct image URL.',
                'source' => $pieceValue,
            ];
        }

        return [
            'type' => 'image',
            'valid' => true,
            'reason' => null,
            'message' => 'Rendering a direct image URL.',
            'source' => $pieceValue,
        ];
    }

    public static function isDirectMediaUrl(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        if (str_starts_with($value, '/')) {
            return true;
        }

        $validated = filter_var($value, FILTER_VALIDATE_URL);
        if ($validated === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true);
    }

    public static function extractIframeSourcePublic(string $html): ?string
    {
        return self::extractIframeSrc($html);
    }

    private static function extractIframeSrc(string $html): ?string
    {
        if (!preg_match('/<iframe\b[^>]*\bsrc=(["\'])(.*?)\1/i', $html, $matches)) {
            return null;
        }

        $src = trim(html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        return $src !== '' ? $src : null;
    }
}
