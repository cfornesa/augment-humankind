<?php

declare(strict_types=1);

class ArtPieceStarterTemplate
{
    public static function tableReady(): bool
    {
        return function_exists('ah_table_exists') ? ah_table_exists('art_piece_starter_templates') : true;
    }

    public static function all(): array
    {
        if (!self::tableReady()) {
            return [];
        }
        return db()->query('SELECT * FROM art_piece_starter_templates ORDER BY generation_mode ASC, id ASC')->fetchAll();
    }

    public static function find(int $id): array|false
    {
        if (!self::tableReady()) {
            return false;
        }
        $stmt = db()->prepare('SELECT * FROM art_piece_starter_templates WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function defaultForMode(string $mode): array|false
    {
        if (!self::tableReady()) {
            return false;
        }
        $stmt = db()->prepare(
            'SELECT * FROM art_piece_starter_templates
              WHERE generation_mode = ? AND is_default = 1 AND is_active = 1
              ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute([$mode]);
        return $stmt->fetch();
    }

    public static function defaultMap(): array
    {
        $map = [];
        foreach (self::all() as $template) {
            if (!empty($template['is_default']) && !empty($template['is_active'])) {
                $map[(string) $template['generation_mode']] = $template;
            }
        }
        return $map;
    }

    public static function update(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE art_piece_starter_templates
                SET label = ?, description = ?, html_code = ?, css_code = ?, js_code = ?,
                    is_default = ?, is_active = ?
              WHERE id = ?'
        );
        $stmt->execute([
            trim((string) $data['label']),
            self::nullIfBlank($data['description'] ?? null),
            (string) ($data['html_code'] ?? ''),
            (string) ($data['css_code'] ?? ''),
            (string) ($data['js_code'] ?? ''),
            !empty($data['is_default']) ? 1 : 0,
            !empty($data['is_active']) ? 1 : 0,
            $id,
        ]);

        if (!empty($data['is_default'])) {
            $template = self::find($id);
            if ($template) {
                $clear = db()->prepare(
                    'UPDATE art_piece_starter_templates SET is_default = 0 WHERE generation_mode = ? AND id != ?'
                );
                $clear->execute([(string) $template['generation_mode'], $id]);
            }
        }
    }

    private static function nullIfBlank(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }
}
