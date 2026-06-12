<?php

declare(strict_types=1);

function slugify(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $text);
    $text = preg_replace('/[\s_]+/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-');
}

function unique_slug(string $title, int $excludeId = 0): string
{
    $base = slugify($title);
    $slug = $base;
    $i = 2;
    while (true) {
        $stmt = db()->prepare(
            'SELECT id FROM artworks WHERE slug = ? AND id != ?'
        );
        $stmt->execute([$slug, $excludeId]);
        if (!$stmt->fetch()) {
            break;
        }
        $slug = $base . '-' . $i++;
    }
    return $slug;
}

function unique_category_slug(string $name, int $excludeId = 0): string
{
    $base = slugify($name);
    $slug = $base;
    $i = 2;
    while (true) {
        $stmt = db()->prepare(
            'SELECT id FROM categories WHERE slug = ? AND id != ?'
        );
        $stmt->execute([$slug, $excludeId]);
        if (!$stmt->fetch()) {
            break;
        }
        $slug = $base . '-' . $i++;
    }
    return $slug;
}
