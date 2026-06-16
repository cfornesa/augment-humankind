<?php

declare(strict_types=1);

/**
 * Copies theme/palette/color columns from the platform users table to the
 * PHP site's users table, matching rows by email address.
 *
 * Column names are identical between both tables — no transformation needed.
 *
 * Default mode: dry-run (prints what would change, writes nothing).
 * Pass --execute to apply updates.
 *
 * Usage:
 *   php scripts/migrate-user-styles.php
 *   php scripts/migrate-user-styles.php --execute
 *
 * Requires: DB_* (target) and PLATFORM_DB_* (source) configured in .env
 */

function loadEnv(string $path): void
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
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }
        if (($_ENV[$name] ?? getenv($name) ?: '') === '') {
            $_ENV[$name] = $value;
            putenv($name . '=' . $value);
        }
    }
}

function envGet(string $key, string $default = ''): string
{
    $v = $_ENV[$key] ?? getenv($key);
    return is_string($v) && $v !== '' ? $v : $default;
}

function pdoFor(string $prefix): PDO
{
    $host = envGet($prefix . 'DB_HOST', 'localhost');
    $port = envGet($prefix . 'DB_PORT', '3306');
    $name = envGet($prefix . 'DB_NAME');
    $user = envGet($prefix . 'DB_USER');
    $pass = envGet($prefix . 'DB_PASS');

    if ($name === '' || $user === '') {
        throw new RuntimeException("Missing {$prefix}DB_NAME or {$prefix}DB_USER in .env");
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    if (strtolower(envGet($prefix . 'DB_SSL')) === 'true') {
        if (class_exists('Pdo\Mysql') && defined('Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')) {
            $options[constant('Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')] = false;
        } elseif (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
            $options[constant('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')] = false;
        }
    }

    return new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $pass, $options);
}

// Columns to copy (identical names in both tables)
const STYLE_COLUMNS = [
    'theme', 'palette',
    'color_background', 'color_foreground',
    'color_background_dark', 'color_foreground_dark',
    'color_primary', 'color_primary_foreground',
    'color_secondary', 'color_secondary_foreground',
    'color_accent', 'color_accent_foreground',
    'color_muted', 'color_muted_foreground',
    'color_destructive', 'color_destructive_foreground',
];

loadEnv(dirname(__DIR__) . '/.env');

$execute = in_array('--execute', $argv ?? [], true);

$targetName   = envGet('DB_NAME');
$sourceName   = envGet('PLATFORM_DB_NAME');

if ($targetName === '' || $sourceName === '') {
    fwrite(STDERR, "Both DB_NAME and PLATFORM_DB_NAME must be configured in .env\n");
    exit(1);
}
if ($targetName === $sourceName) {
    fwrite(STDERR, "Refusing to run: DB_NAME and PLATFORM_DB_NAME are the same database.\n");
    exit(1);
}

echo $execute ? "Running in EXECUTE mode — changes will be written.\n" : "Running in DRY-RUN mode — no changes written. Pass --execute to apply.\n\n";

try {
    $source = pdoFor('PLATFORM_');
    $source->exec('SET SESSION TRANSACTION READ ONLY');
    $source->beginTransaction();

    $target = pdoFor('');

    // Fetch platform users that have any non-null style value
    $styleColList = implode(', ', array_map(fn ($c) => "`$c`", STYLE_COLUMNS));
    $platformUsers = $source->query(
        "SELECT email, {$styleColList} FROM users WHERE email IS NOT NULL AND email != ''"
    )->fetchAll();

    if ($platformUsers === []) {
        echo "No platform users with email addresses found.\n";
        $source->rollBack();
        exit(0);
    }

    echo 'Platform users with email: ' . count($platformUsers) . "\n\n";

    $matched = 0;
    $updated = 0;
    $skipped = 0;

    $findStmt   = $target->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $setSqls    = implode(', ', array_map(fn ($c) => "`$c` = ?", STYLE_COLUMNS));
    $updateStmt = $execute ? $target->prepare("UPDATE users SET {$setSqls}, updated_at = NOW() WHERE id = ?") : null;

    foreach ($platformUsers as $pu) {
        $findStmt->execute([$pu['email']]);
        $targetUser = $findStmt->fetch();
        if (!$targetUser) {
            continue;
        }
        $matched++;

        // Check if there's anything non-null to copy
        $hasData = false;
        foreach (STYLE_COLUMNS as $col) {
            if ($pu[$col] !== null && $pu[$col] !== '') {
                $hasData = true;
                break;
            }
        }
        if (!$hasData) {
            $skipped++;
            echo "  SKIP  {$pu['email']} — all style columns null\n";
            continue;
        }

        $params = array_map(fn ($c) => $pu[$c] !== '' ? $pu[$c] : null, STYLE_COLUMNS);

        if ($execute) {
            $params[] = $targetUser['id'];
            $updateStmt->execute($params);
            $updated++;
            echo "  UPDATE {$pu['email']}\n";
        } else {
            $preview = [];
            foreach (STYLE_COLUMNS as $i => $col) {
                if ($params[$i] !== null) {
                    $preview[] = $col . '=' . $params[$i];
                }
            }
            echo "  WOULD UPDATE {$pu['email']}: " . implode(', ', $preview) . "\n";
            $updated++;
        }
    }

    $source->rollBack();

    echo "\nDone. Matched: {$matched} | " . ($execute ? 'Updated' : 'Would update') . ": {$updated} | Skipped: {$skipped}\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}
