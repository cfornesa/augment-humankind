<?php

declare(strict_types=1);

/**
 * Normalizes <iframe src="..."> embeds in posts.content that point at an
 * absolute external origin (e.g. atelier.fornesusart.com,
 * studio.augmenthumankind.com, fornesusart.com, platform.creatrweb.com) for
 * /embed/pieces/*, /immersive/exhibits/*, and /immersive/images/* paths,
 * rewriting them to relative paths so these embeds always resolve against
 * this app's own routes/data (window.location.origin) instead of an
 * external site.
 *
 * Also reports (without rewriting) /embed/pieces/{id} and
 * /immersive/exhibits/{slug} references that don't resolve against this
 * app's art_pieces / platform_exhibits tables, so orphaned references are
 * visible before the legacy platform app is removed.
 *
 * Default mode is dry-run. Use --execute to write to DB_*.
 * Usage: php scripts/repair-platform-embed-links.php [--execute]
 */

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

/**
 * Mirrors ImmersiveController::decodeImageRef() for reporting purposes only.
 */
function decodeImageRef(string $encodedRef): ?string
{
    $normalized = strtr($encodedRef, '-_', '+/');
    $padding = strlen($normalized) % 4;
    if ($padding > 0) {
        $normalized .= str_repeat('=', 4 - $padding);
    }

    $decoded = base64_decode($normalized, true);
    if (!is_string($decoded) || trim($decoded) === '') {
        return null;
    }
    $decoded = trim($decoded);

    if (preg_match('#^(javascript|data|vbscript):#i', $decoded)) {
        return null;
    }
    if (str_starts_with($decoded, '//')) {
        return null;
    }
    if (preg_match('#^https?://#i', $decoded)) {
        return $decoded;
    }
    if ($decoded[0] !== '/') {
        return '/' . ltrim($decoded, '/');
    }
    return $decoded;
}

/**
 * @return array{new: string, changed: bool, path: ?string}
 */
function normalizeSrc(string $src): array
{
    $parts = parse_url($src);
    if ($parts === false) {
        return ['new' => $src, 'changed' => false, 'path' => null];
    }

    $path = $parts['path'] ?? '';
    $isTarget = str_starts_with($path, '/embed/pieces/')
        || str_starts_with($path, '/immersive/collections/')
        || str_starts_with($path, '/immersive/images/');

    if (!$isTarget) {
        return ['new' => $src, 'changed' => false, 'path' => null];
    }

    $hasHost = isset($parts['scheme']) || isset($parts['host']);
    if (!$hasHost) {
        return ['new' => $src, 'changed' => false, 'path' => $path];
    }


    $relative = $path;
    if (isset($parts['query'])) {
        $relative .= '?' . $parts['query'];
    }
    if (isset($parts['fragment'])) {
        $relative .= '#' . $parts['fragment'];
    }

    return ['new' => $relative, 'changed' => true, 'path' => $path];
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

$pieceIdSet = array_flip(array_map(
    'intval',
    $pdo->query("SELECT id FROM art_pieces WHERE deleted_at IS NULL")->fetchAll(PDO::FETCH_COLUMN)
));

$exhibitSlugSet = array_flip(
    $pdo->query("SELECT slug FROM platform_collections WHERE deleted_at IS NULL")->fetchAll(PDO::FETCH_COLUMN)
);

$posts = $pdo->query(
    "SELECT id, title, content FROM posts
     WHERE content LIKE '%<iframe%'
       AND (content LIKE '%/embed/pieces/%'
            OR content LIKE '%/immersive/collections/%'
            OR content LIKE '%/immersive/images/%')"
);

$update = $pdo->prepare('UPDATE posts SET content = ? WHERE id = ?');

$totalRewrites = 0;
$totalWarnings = 0;

foreach ($posts as $row) {
    $postId = (int) $row['id'];
    $title = (string) $row['title'];
    $content = (string) $row['content'];
    $newContent = $content;
    $reported = false;

    preg_match_all('/<iframe\b[^>]*\bsrc=(["\'])(.*?)\1[^>]*>/i', $content, $matches);

    foreach ($matches[2] as $i => $src) {
        $quote = $matches[1][$i];
        $result = normalizeSrc($src);
        $path = $result['path'];
        $notes = [];

        if ($path !== null) {
            if (str_starts_with($path, '/embed/pieces/') && preg_match('#^/embed/pieces/(\d+)#', $path, $m)) {
                $id = (int) $m[1];
                if (!isset($pieceIdSet[$id])) {
                    $notes[] = "WARNING: art_pieces id={$id} does not exist or is soft-deleted";
                }
            } elseif (str_starts_with($path, '/immersive/collections/') && preg_match('~^/immersive/collections/([^/?#]+)~', $path, $m)) {
                $slug = $m[1];
                if (!isset($exhibitSlugSet[$slug])) {
                    $notes[] = "WARNING: platform_collections slug '{$slug}' does not exist (orphaned reference)";
                }
            } elseif (str_starts_with($path, '/immersive/images/') && preg_match('~^/immersive/images/([^/?#]+)~', $path, $m)) {
                $decoded = decodeImageRef($m[1]);
                $notes[] = $decoded !== null
                    ? "info: image ref decodes to '{$decoded}'"
                    : "WARNING: image ref does not decode to a valid path";
            }
        }

        if (!$result['changed'] && empty($notes)) {
            continue;
        }

        if (!$reported) {
            echo "=== Post #{$postId}: \"{$title}\" ===\n";
            $reported = true;
        }

        if ($result['changed']) {
            echo "  src: {$src}\n";
            echo "   -> {$result['new']}\n";
            $totalRewrites++;
            $old = "src={$quote}{$src}{$quote}";
            $new = "src={$quote}{$result['new']}{$quote}";
            $newContent = str_replace($old, $new, $newContent);
        }

        foreach ($notes as $note) {
            echo "  {$note}\n";
            if (str_starts_with($note, 'WARNING')) {
                $totalWarnings++;
            }
        }
    }

    if ($reported) {
        echo "\n";
    }

    if ($execute && $newContent !== $content) {
        $update->execute([$newContent, $postId]);
        echo "  -> updated posts.id={$postId}\n\n";
    }
}

if ($execute) {
    echo "Done. {$totalRewrites} src rewrite(s) applied. {$totalWarnings} warning(s) remain (not auto-fixed; see above).\n";
} else {
    echo "Dry run complete. {$totalRewrites} src rewrite(s) would be applied. {$totalWarnings} warning(s) found.\n";
    echo "Re-run with --execute to apply the rewrites.\n";
}
