<?php

declare(strict_types=1);

function loadEnvFile(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if ($name === '') {
            continue;
        }

        $existingValue = $_ENV[$name] ?? getenv($name);
        if (is_string($existingValue) && $existingValue !== '') {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $_ENV[$name] = $value;
        putenv($name . '=' . $value);
    }
}

loadEnvFile(__DIR__ . '/../public/.env');
loadEnvFile(__DIR__ . '/../.env');

require __DIR__ . '/../public/app/config/database.php';
require __DIR__ . '/../public/app/helpers/schema.php';

$checks = [
    ['table', 'ai_personas', null, 'AI personas table (June 18 migration)'],
    ['column', 'user_ai_vendor_settings', 'capabilities', 'AI profile capabilities column'],
    ['column', 'art_pieces', 'thumbnail_alt_text', 'Piece thumbnail alt-text column'],
    ['column', 'media_files', 'title', 'Native media title column'],
    ['column', 'media_files', 'alt_text', 'Native media alt-text column'],
    ['column', 'users', 'theme', 'User profile theme column'],
    ['column', 'users', 'palette', 'User profile palette column'],
    ['column', 'users', 'color_background', 'User profile light background color'],
    ['column', 'users', 'color_background_dark', 'User profile dark background color'],
    ['column', 'users', 'preferred_art_piece_profile_id', 'Preferred art-piece AI profile column'],
    ['column', 'users', 'preferred_text_improve_profile_id', 'Preferred text-improve AI profile column'],
    ['column', 'users', 'preferred_alt_text_profile_id', 'Preferred alt-text AI profile column'],
];

$exitCode = 0;
echo "AI / Media / Profile Schema Verification\n";
echo "======================================\n\n";

foreach ($checks as [$kind, $table, $column, $label]) {
    $ok = $kind === 'table'
        ? ah_table_exists($table)
        : ah_column_exists($table, (string) $column);

    if (!$ok) {
        $exitCode = 1;
    }

    $subject = $kind === 'table' ? $table : $table . '.' . $column;
    printf(
        "[%s] %-48s %s\n",
        $ok ? 'OK' : 'MISSING',
        $subject,
        $label
    );
}

echo "\n";
if ($exitCode === 0) {
    echo "All checked schema pieces are present.\n";
} else {
    echo "One or more schema pieces are missing. Apply docs/migrations/2026-06-18-ai-personas.sql and any earlier user-style/profile migrations as needed.\n";
}

exit($exitCode);
