<?php
/**
 * Tests that "newest piece" ordering isn't corrupted by an unrelated
 * UPDATE's side effect.
 * Run with: php tests/art-piece-ordering.php
 */

declare(strict_types=1);

$passed = 0;
$failed = 0;

function test(string $label, callable $fn): void {
    global $passed, $failed;
    try {
        $fn();
        echo "  ✓ {$label}\n";
        $passed++;
    } catch (Throwable $e) {
        echo "  ✗ {$label}: {$e->getMessage()}\n";
        $failed++;
    }
}

function assert_contains(string $haystack, string $needle, string $msg = ''): void {
    if (str_contains($haystack, $needle) === false) {
        throw new RuntimeException($msg . " Expected to contain: {$needle}");
    }
}

function assert_not_contains(string $haystack, string $needle, string $msg = ''): void {
    if (str_contains($haystack, $needle) === true) {
        throw new RuntimeException($msg . " Expected NOT to contain: {$needle}");
    }
}

echo "=== PlatformArtPiece ordering ===\n";

$model = file_get_contents(__DIR__ . '/../public/app/models/PlatformArtPiece.php');

test('create() suppresses ON UPDATE CURRENT_TIMESTAMP on the sort_order shift', function () use ($model) {
    // art_pieces.updated_at is DATETIME(3) ... ON UPDATE CURRENT_TIMESTAMP(3)
    // — any UPDATE touching a row bumps it, even one that never mentions
    // updated_at. create()'s sort_order-shift statement touches every other
    // active piece's row, so without this self-assignment it silently
    // marked every existing piece as "just updated" on every new creation —
    // confirmed live: the actual newest piece dropped out of
    // latestActive()'s results entirely, displaced by older pieces whose
    // updated_at got bumped as a side effect of someone else's creation.
    assert_contains($model, 'sort_order = sort_order + 1, updated_at = updated_at');
});

test('latestActive() orders by created_at, not GREATEST(created_at, updated_at)', function () use ($model) {
    assert_not_contains($model, "GREATEST(ap.created_at, ap.updated_at) DESC, ap.id DESC\n             LIMIT ?\"");
});

test('paginateLatest() orders by created_at, not GREATEST(created_at, updated_at)', function () use ($model) {
    assert_not_contains($model, "GREATEST(ap.created_at, ap.updated_at) DESC, ap.id DESC\n             LIMIT ?, ?\"");
});

test('buildSortClause()\'s "newest"/default case orders by created_at, not GREATEST', function () use ($model) {
    assert_not_contains($model, "'newest'     => 'GREATEST(ap.created_at, ap.updated_at)'");
    assert_not_contains($model, "default      => 'GREATEST(ap.created_at, ap.updated_at)'");
    assert_contains($model, "'newest'     => 'ap.created_at'");
});

echo "\n=== Results ===\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}
echo "All tests passed!\n";
