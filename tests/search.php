<?php
/**
 * Tests the search helper: boolean-mode query parsing and HTML-safe
 * snippet highlighting (port of the retired platform app's post-search.ts).
 * Run with: php tests/search.php
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

function assert_true(bool $cond, string $msg): void {
    if (!$cond) {
        throw new RuntimeException($msg);
    }
}

function assert_same(mixed $expected, mixed $actual, string $msg): void {
    if ($expected !== $actual) {
        throw new RuntimeException($msg . ' — expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

require __DIR__ . '/../public/app/helpers/search.php';

echo "=== search_parse_query() ===\n";

test('blank input returns null', function () {
    assert_same(null, search_parse_query(''), 'empty string');
    assert_same(null, search_parse_query('   '), 'whitespace only');
});

test('simple words become required prefix clauses', function () {
    $p = search_parse_query('celestial painting');
    assert_same('+celestial* +painting*', $p['boolean'], 'boolean expression');
    assert_same(['celestial', 'painting'], $p['terms'], 'terms');
    assert_same([], $p['like_terms'], 'no short tokens');
});

test('boolean operators are scrubbed, not interpreted', function () {
    $p = search_parse_query('+foo -bar (baz) ~qux @quux');
    assert_same('+foo* +bar* +baz* +qux* +quux*', $p['boolean'], 'operators stripped');
});

test('quoted phrase becomes required exact-phrase clause', function () {
    $p = search_parse_query('"hello world" extra');
    assert_same('+"hello world" +extra*', $p['boolean'], 'phrase clause first');
    assert_same(['hello', 'world', 'extra'], $p['terms'], 'phrase words seed terms');
});

test('operators inside a phrase are scrubbed within the phrase', function () {
    $p = search_parse_query('"foo+bar*"');
    assert_same('+"foo bar"', $p['boolean'], 'inner operators become spaces');
});

test('short tokens get LIKE fallback; length-3 gets both branches', function () {
    $p = search_parse_query('ai vue painting');
    assert_same('+vue* +painting*', $p['boolean'], 'ai too short for FULLTEXT');
    assert_same(['ai', 'vue'], $p['like_terms'], 'ai and vue (len 3) in like_terms');
});

test('all-short-token query yields empty boolean but usable like_terms', function () {
    $p = search_parse_query('ai io');
    assert_same('', $p['boolean'], 'no FULLTEXT branch');
    assert_same(['ai', 'io'], $p['like_terms'], 'both in like fallback');
});

test('duplicate words are deduped, phrase entry wins', function () {
    $p = search_parse_query('"hello world" hello');
    assert_same('+"hello world"', $p['boolean'], 'no redundant +hello*');
    assert_same(['hello', 'world'], $p['terms'], 'deduped terms');
});

test('input is clamped to 200 chars', function () {
    $long = str_repeat('a', 300);
    $p = search_parse_query($long);
    assert_same('+' . str_repeat('a', 200) . '*', $p['boolean'], 'clamped token');
});

test('all-operator garbage returns null', function () {
    assert_same(null, search_parse_query('+-><()~@*"'), 'nothing usable');
});

test('mixed case is lowercased', function () {
    $p = search_parse_query('Celestial THEME');
    assert_same('+celestial* +theme*', $p['boolean'], 'lowercased');
});

echo "\n=== search_snippet() ===\n";

test('empty text returns empty string', function () {
    assert_same('', search_snippet('', ['x']), 'empty');
    assert_same('', search_snippet(null, ['x']), 'null');
});

test('no terms returns escaped leading slice with ellipsis', function () {
    $text = str_repeat('word ', 60);
    $out = search_snippet($text, []);
    assert_true(mb_strlen($out) <= 221, 'bounded');
    assert_true(str_ends_with($out, '…'), 'ellipsis suffix');
    assert_true(!str_contains($out, '<mark>'), 'no marks without terms');
});

test('match mid-document centers window with ellipses both sides', function () {
    $text = str_repeat('lorem ipsum ', 40) . 'NEEDLE in a haystack ' . str_repeat('dolor sit ', 40);
    $out = search_snippet($text, ['needle']);
    assert_true(str_starts_with($out, '…'), 'prefix ellipsis');
    assert_true(str_ends_with($out, '…'), 'suffix ellipsis');
    assert_true(str_contains($out, '<mark>NEEDLE</mark>'), 'case-insensitive mark preserves original case');
});

test('all occurrences within window are marked', function () {
    $out = search_snippet('alpha beta alpha gamma alpha', ['alpha']);
    assert_same(3, substr_count($out, '<mark>alpha</mark>'), 'three marks');
});

test('HTML in source is escaped and inert', function () {
    $out = search_snippet('safe <script>alert(1)</script> attack vector', ['attack']);
    assert_true(!str_contains($out, '<script>'), 'no live script tag');
    assert_true(str_contains($out, '&lt;script&gt;'), 'escaped script');
    assert_true(str_contains($out, '<mark>attack</mark>'), 'mark still applied');
});

test('term containing regex metacharacters is safe', function () {
    $out = search_snippet('price is $5.99 (sale)', ['$5.99', '(sale)']);
    assert_true(str_contains($out, '<mark>$5.99</mark>'), 'metachar term marked');
});

test('term matching escaped entities marks correctly', function () {
    // "code" appears in text next to a & that becomes &amp; — the term
    // itself must be escaped before matching so offsets line up.
    $out = search_snippet('AT&T code review', ['code']);
    assert_true(str_contains($out, 'AT&amp;T'), 'ampersand escaped');
    assert_true(str_contains($out, '<mark>code</mark>'), 'mark applied');
});

test('multibyte text is not corrupted', function () {
    $out = search_snippet('étoile céleste — ünïcode täst', ['céleste']);
    assert_true(str_contains($out, '<mark>céleste</mark>'), 'multibyte mark');
    assert_true(str_contains($out, 'étoile'), 'accents intact');
});

test('short text without match has no ellipses', function () {
    $out = search_snippet('just a short line', ['missing']);
    assert_true(!str_contains($out, '…'), 'no ellipsis');
    assert_same('just a short line', $out, 'text unchanged');
});

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
