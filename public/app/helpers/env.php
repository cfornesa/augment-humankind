<?php

declare(strict_types=1);

/**
 * Shared .env loading — the single implementation used by the web entrypoint
 * (public/index.php), scripts/setup-database.php, and
 * scripts/check-portable-launch-readiness.php.
 *
 * Semantics: process environment always wins over .env values, missing files
 * are silently skipped, comments and blank lines are ignored, and single or
 * double outer quotes are stripped from values.
 */

if (!function_exists('ah_load_env_file')) {
    function ah_load_env_file(string $path): void
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
                // Normalize real process env into $_ENV: variables_order often
                // excludes E, and db()/ah_env read $_ENV first.
                $_ENV[$name] = $existingValue;
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
}

if (!function_exists('ah_env')) {
    function ah_env(string $key, string $default = ''): string
    {
        $value = $_ENV[$key] ?? getenv($key);
        return is_string($value) && $value !== '' ? $value : $default;
    }
}
