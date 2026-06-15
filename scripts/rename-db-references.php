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

$envPath = dirname(__DIR__) . '/.env';
if (!is_readable($envPath)) {
    fwrite(STDERR, "Could not read .env file at {$envPath}\n");
    exit(1);
}

loadEnvFile($envPath);

$host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
$name = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: '';
$user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: '';
$pass = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '';

if ($name === '' || $user === '') {
    fwrite(STDERR, "Missing DB_NAME or DB_USER in .env\n");
    exit(1);
}

$execute = in_array('--execute', $argv, true);

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "Database error: " . $e->getMessage() . "\n");
    exit(1);
}

// We need to replace:
// 1. /immersive/exhibits/ -> /immersive/collections/
// 2. /api/exhibits/ -> /api/collections/
// 3. /exhibits/ -> /collections/
// 4. /portfolio/exhibit/ -> /portfolio/collection/
// 5. /portfolio/work/ -> /portfolio/exhibit/

$replacements = [
    '/immersive/exhibits/' => '/immersive/collections/',
    '/api/exhibits/' => '/api/collections/',
    '/exhibits/' => '/collections/',
    '/portfolio/exhibit/' => '/portfolio/collection/',
    '/portfolio/work/' => '/portfolio/exhibit/'
];

$tables = ['posts', 'pages', 'page_sections'];

foreach ($tables as $t) {
    $tableExists = $pdo->query("SHOW TABLES LIKE '$t'")->rowCount() > 0;
    if (!$tableExists) {
        continue;
    }

    $cols = $pdo->query("DESCRIBE `$t`")->fetchAll(PDO::FETCH_ASSOC);
    $textCols = [];
    foreach ($cols as $c) {
        if (preg_match('/(char|text|varchar|json)/i', $c['Type'])) {
            $textCols[] = $c['Field'];
        }
    }

    if (empty($textCols)) {
        continue;
    }

    echo "Scanning table `$t`...\n";
    $rows = $pdo->query("SELECT * FROM `$t`")->fetchAll();

    foreach ($rows as $row) {
        $id = $row['id'] ?? $row['uuid'] ?? null;
        $updatedFields = [];
        $params = [];

        foreach ($textCols as $col) {
            $originalVal = $row[$col];
            if ($originalVal === null || $originalVal === '') {
                continue;
            }

            $newVal = $originalVal;
            foreach ($replacements as $old => $new) {
                if (str_contains($newVal, $old)) {
                    $newVal = str_replace($old, $new, $newVal);
                }
            }

            if ($newVal !== $originalVal) {
                $updatedFields[] = "`$col` = ?";
                $params[] = $newVal;
            }
        }

        if (!empty($updatedFields)) {
            $idCol = isset($row['id']) ? 'id' : 'uuid';
            $sql = "UPDATE `$t` SET " . implode(', ', $updatedFields) . " WHERE `$idCol` = ?";
            $params[] = $id;

            echo "  Row ID $id in `$t` has changes:\n";
            foreach ($textCols as $col) {
                $originalVal = $row[$col];
                $newVal = $originalVal;
                foreach ($replacements as $old => $new) {
                    if ($originalVal !== null && str_contains($newVal, $old)) {
                        $newVal = str_replace($old, $new, $newVal);
                    }
                }
                if ($newVal !== $originalVal) {
                    echo "    Column `$col`:\n";
                    echo "      OLD: " . substr(strip_tags((string)$originalVal), 0, 120) . "...\n";
                    echo "      NEW: " . substr(strip_tags((string)$newVal), 0, 120) . "...\n";
                }
            }

            if ($execute) {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                echo "    -> Executed UPDATE.\n";
            } else {
                echo "    -> [Dry-Run] Would update.\n";
            }
        }
    }
}

echo "\nDone.\n";
if (!$execute) {
    echo "Re-run with --execute to commit the changes to the database.\n";
}
