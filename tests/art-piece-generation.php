<?php
/**
 * Simple CLI test for art piece generation helpers.
 * Run with: php tests/art-piece-generation.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../public/app/helpers/art-piece-generation.php';
require_once __DIR__ . '/../public/app/helpers/database-errors.php';
require_once __DIR__ . '/../public/app/helpers/slugify.php';
require_once __DIR__ . '/../public/app/helpers/piece-render.php';

if (!function_exists('seo_origin')) {
    function seo_origin(): string
    {
        $envPublic = trim((string) ($_ENV['PUBLIC_SITE_URL'] ?? getenv('PUBLIC_SITE_URL') ?: ''));
        if ($envPublic !== '') {
            return rtrim($envPublic, '/');
        }

        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
        return $scheme . '://' . $host;
    }
}

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

function assert_eq($actual, $expected, string $msg = ''): void {
    if ($actual !== $expected) {
        throw new RuntimeException($msg . " Expected: " . var_export($expected, true) . " Got: " . var_export($actual, true));
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

function assert_true(bool $actual, string $msg = ''): void {
    if ($actual !== true) {
        throw new RuntimeException($msg !== '' ? $msg : 'Expected condition to be true.');
    }
}

function assert_throws(callable $fn, string $expectedMsg = ''): void {
    try {
        $fn();
        throw new RuntimeException("Expected exception was not thrown");
    } catch (Throwable $e) {
        if ($expectedMsg !== '' && !str_contains($e->getMessage(), $expectedMsg)) {
            throw new RuntimeException("Expected exception containing '{$expectedMsg}', got: {$e->getMessage()}");
        }
    }
}

echo "=== art_piece_extract_code_blocks ===\n";

// 1. Full extraction
test('Extracts all three blocks', function () {
    $raw = "```html\n<div id='canvas-container'></div>\n```\n\n```css\nbody { margin: 0; }\n```\n\n```javascript\nwindow.sketch = () => {};\n```";
    $blocks = art_piece_extract_code_blocks($raw);
    assert_eq($blocks['htmlCode'], "<div id='canvas-container'></div>");
    assert_eq($blocks['cssCode'], "body { margin: 0; }");
    assert_eq($blocks['generatedCode'], "window.sketch = () => {};");
});

// 2. JS alias
test('Extracts js alias block', function () {
    $raw = "```js\nwindow.sketch = () => {};\n```";
    $blocks = art_piece_extract_code_blocks($raw);
    assert_eq($blocks['generatedCode'], "window.sketch = () => {};");
    assert_eq($blocks['htmlCode'], null);
});

// 3. Missing blocks
test('Returns null for missing blocks', function () {
    $raw = "```html\n<div></div>\n```";
    $blocks = art_piece_extract_code_blocks($raw);
    assert_eq($blocks['htmlCode'], "<div></div>");
    assert_eq($blocks['cssCode'], null);
    assert_eq($blocks['generatedCode'], null);
});

// 4. Empty input
test('Handles empty input', function () {
    $blocks = art_piece_extract_code_blocks('');
    assert_eq($blocks['htmlCode'], null);
    assert_eq($blocks['cssCode'], null);
    assert_eq($blocks['generatedCode'], null);
});

echo "\n=== validate_art_piece_code ===\n";

// 5. Basic validation
test('Validates window.sketch', function () {
    $result = validate_art_piece_code("window.sketch = () => {};");
    assert_eq($result, "window.sketch = () => {};");
});

// 6. Disallowed patterns
test('Rejects fetch', function () {
    assert_throws(fn() => validate_art_piece_code("fetch('/api')"), 'fetch');
});

test('Rejects import', function () {
    assert_throws(fn() => validate_art_piece_code("import THREE from 'three'"), 'import');
});

test('Rejects dynamic import', function () {
    assert_throws(fn() => validate_art_piece_code("import('/module.js')"), 'dynamic imports');
});

test('Rejects empty code', function () {
    assert_throws(fn() => validate_art_piece_code(''), 'empty');
});

test('Rejects oversized code', function () {
    assert_throws(fn() => validate_art_piece_code(str_repeat('a', 120001)), 'too large');
});

echo "\n=== art_piece_preflight_code ===\n";

// 7. SVG no-op stub
test('SVG allows window.sketch = () => {};', function () {
    $result = art_piece_preflight_code('svg', 'window.sketch = () => {};');
    assert_eq($result, 'window.sketch = () => {};');
});

// 8. SVG requires window.sketch for non-no-op
test('SVG requires window.sketch for non-trivial code', function () {
    assert_throws(fn() => art_piece_preflight_code('svg', 'console.log("hello")'), 'window.sketch');
});

// 9. C2 rejects disallowed patterns
test('C2 rejects Ease.linear', function () {
    assert_throws(fn() => art_piece_preflight_code('c2', 'c2.Ease.linear'), 'c2.Ease');
});

// 10. p5 requires window.sketch
test('p5 requires window.sketch', function () {
    assert_throws(fn() => art_piece_preflight_code('p5', 'function draw() {}'), 'window.sketch');
});

// 11. three requires window.sketch
test('three requires window.sketch', function () {
    assert_throws(fn() => art_piece_preflight_code('three', 'const scene = new THREE.Scene()'), 'window.sketch');
});

// 12. three with window.sketch passes
test('three with window.sketch passes', function () {
    $result = art_piece_preflight_code('three', 'window.sketch = (runtime) => { const { THREE } = runtime; };');
    assert_contains($result, 'window.sketch');
});

// 13. window.sketch referenced but never assigned still fails (regression
// guard: the runtime requires typeof window.sketch === 'function', and
// silently no-ops with no error and no canvas if it's only read, not set)
test('three rejects window.sketch that is referenced but never assigned', function () {
    assert_throws(fn() => art_piece_preflight_code('three', "if (typeof window.sketch === 'function') { console.log('noop'); }"), 'window.sketch');
});

// 14. three has NO mesh/object-count rejection of any kind (explicit,
// deliberate decision — tried at two thresholds, 150 then 1000, both
// directly falsified by live evidence: a 728-object piece under the 1000
// cap still failed to render, while a 1400+ object piece rendered fine.
// Object count does not predict renderability, so no fixed number can
// work; the person reviewing each piece decides, not a static check. This
// regression-guards the removal itself: even a very large count (well
// above either retired threshold) must pass cleanly, not just "still under
// some new higher number")
test('three has no mesh-count rejection — even a very large object count passes', function () {
    $code = "window.sketch = (runtime) => {\n  for (let i = 0; i < 2500; i++) {\n    const strand = new THREE.Mesh(geo, mat);\n    root.add(strand);\n  }\n};";
    $result = art_piece_preflight_code('three', $code);
    assert_contains($result, 'window.sketch');
});

// 16. art_piece_count_three_object_calls multiplies loop-bound mesh calls
// rather than just counting source call sites
test('art_piece_count_three_object_calls multiplies by the loop bound', function () {
    $code = "for (let i = 0; i < 900; i++) { const s = new THREE.Mesh(geo, mat); }\nconst single = new THREE.Mesh(geo2, mat2);";
    assert_eq(art_piece_count_three_object_calls($code), 901);
});

echo "\n=== art_piece_refine_system_prompt ===\n";

// 13. System prompts exist for all engines
test('p5 system prompt exists', function () {
    $prompt = art_piece_refine_system_prompt('p5');
    assert_contains($prompt, 'p5');
    assert_contains($prompt, 'window.sketch');
});

test('three system prompt exists', function () {
    $prompt = art_piece_refine_system_prompt('three');
    assert_contains($prompt, 'THREE');
    assert_contains($prompt, 'WebGLRenderer');
});

test('svg system prompt exists', function () {
    $prompt = art_piece_refine_system_prompt('svg');
    assert_contains($prompt, 'svg');
    assert_contains($prompt, '@keyframes');
});

test('c2 system prompt exists', function () {
    $prompt = art_piece_refine_system_prompt('c2');
    assert_contains($prompt, 'c2');
    assert_contains($prompt, 'c2.Renderer');
});

test('Unknown engine throws', function () {
    assert_throws(fn() => art_piece_refine_system_prompt('unknown'), 'Unknown engine');
});

test('Refine system prompt requires a plan-then-patch response, not a full-file rewrite', function () {
    // Regression guard: a minimal-edit *instruction* alone wasn't enough —
    // the AI still rewrote nearly an entire piece from scratch even with
    // that wording in place. The actual fix is structural: the AI must
    // name what it intends to touch (PLAN) and express every change as an
    // exact find-and-replace (PATCH ... SEARCH/REPLACE) against the current
    // code, never a regenerated file — see art_piece_apply_refine_patches().
    $prompt = art_piece_refine_system_prompt('p5');
    assert_contains($prompt, 'STEP 1');
    assert_contains($prompt, 'STEP 2');
    assert_contains($prompt, 'PLAN');
    assert_contains($prompt, 'PATCH');
    assert_contains($prompt, 'SEARCH');
    assert_contains($prompt, 'REPLACE');
    assert_contains($prompt, 'NEVER rewrite or regenerate a file in full');
});

test('Refine system prompt includes a worked PLAN/PATCH example', function () {
    // Few-shot examples are far more reliable than an abstract format
    // description at getting a model to produce parseable, exactly-matching
    // SEARCH/REPLACE blocks.
    $prompt = art_piece_refine_system_prompt('p5');
    assert_contains($prompt, 'EXAMPLE');
    assert_contains($prompt, '<<<<<<< SEARCH');
    assert_contains($prompt, '=======');
    assert_contains($prompt, '>>>>>>> REPLACE');
});

echo "\n=== generation_mode compatibility ===\n";

test('Version select columns include generation_mode when supported', function () {
    $sql = art_piece_version_select_columns(true, true, true);
    assert_contains($sql, 'v.generation_mode');
    assert_contains($sql, 'v.created_at');
    assert_contains($sql, 'v.is_draft_attempt');
});

test('Version select columns omit generation_mode when unsupported', function () {
    $sql = art_piece_version_select_columns(false, true, true);
    assert_not_contains($sql, "v.generation_mode,\n");
    assert_contains($sql, 'v.generation_model');
    assert_contains($sql, 'v.validation_status');
});

test('Version storage columns include generation_mode when supported', function () {
    $columns = art_piece_version_storage_columns(true);
    if (!in_array('generation_mode', $columns, true)) {
        throw new RuntimeException('Expected generation_mode column to be present');
    }
});

test('Version storage columns omit generation_mode when unsupported', function () {
    $columns = art_piece_version_storage_columns(false);
    if (in_array('generation_mode', $columns, true)) {
        throw new RuntimeException('Did not expect generation_mode column to be present');
    }
    assert_eq(in_array('generation_model', $columns, true), true, 'generation_model should still be present.');
});

test('Legacy C2 interactive backfill SQL upgrades only heuristic matches', function () {
    $sql = art_piece_c2_interactive_backfill_sql();
    assert_contains($sql, "SET generation_mode = 'c2_interactive'");
    assert_contains($sql, "WHERE engine = 'c2'");
    assert_contains($sql, "generation_mode IS NULL OR generation_mode = '' OR generation_mode = 'c2'");
    assert_contains($sql, "LOWER(CONCAT(COALESCE(generated_code, '')");
    assert_contains($sql, "COALESCE(html_code, '')))");
    assert_contains($sql, 'REGEXP');
    assert_contains($sql, 'pointerdown');
});

echo "\n=== pdo error classification ===\n";

test('PDO connection classifier recognizes DNS lookup failure', function () {
    $e = new PDOException('SQLSTATE[HY000] [2002] php_network_getaddresses: getaddrinfo for host failed', 2002);
    assert_eq(ah_is_pdo_connection_failure($e), true);
});

test('PDO connection classifier rejects unknown column query failure', function () {
    $e = new PDOException("SQLSTATE[42S22]: Column not found: 1054 Unknown column 'generation_mode' in 'field list'");
    assert_eq(ah_is_pdo_connection_failure($e), false);
});

echo "\n=== art_piece_refine_user_prompt ===\n";

// 14. User prompt format
test('User prompt includes all sections', function () {
    // For SVG pieces, all sections including HTML are present
    $svgPrompt = art_piece_refine_user_prompt('svg', 'Make it blue', '<div></div>', 'body{}', 'window.sketch = () => {};');
    assert_contains($svgPrompt, 'CURRENT HTML CODE');
    assert_contains($svgPrompt, 'CURRENT CSS CODE');
    assert_contains($svgPrompt, 'CURRENT JAVASCRIPT CODE');

    // For non-SVG pieces (like p5), HTML is excluded and forbidden reminder is added
    $p5Prompt = art_piece_refine_user_prompt('p5', 'Make it blue', '<div></div>', 'body{}', 'window.sketch = () => {};');
    assert_not_contains($p5Prompt, 'CURRENT HTML CODE');
    assert_contains($p5Prompt, 'CURRENT CSS CODE');
    assert_contains($p5Prompt, 'CURRENT JAVASCRIPT CODE');
    assert_contains($p5Prompt, 'STRICTLY FORBIDDEN from editing or adding HTML patches');
});

test('User prompt repeats the minimal-edit reminder next to the instruction', function () {
    $prompt = art_piece_refine_user_prompt('p5', 'Make it blue', '<div></div>', 'body{}', 'window.sketch = () => {};');
    assert_contains($prompt, 'REMINDER');
    assert_contains($prompt, 'PATCH blocks');
    assert_contains($prompt, 'never as a rewritten file');
});

test('User prompt includes the original creative prompt as context when given', function () {
    $prompt = art_piece_refine_user_prompt('p5', 'Make it blue', '<div></div>', 'body{}', 'window.sketch = () => {};', 'A dream-like landscape');
    assert_contains($prompt, 'ORIGINAL CREATIVE PROMPT');
    assert_contains($prompt, 'A dream-like landscape');
});

test('User prompt handles null inputs', function () {
    $svgPrompt = art_piece_refine_user_prompt('svg', 'test', null, null, null);
    assert_contains($svgPrompt, '```html');
    assert_contains($svgPrompt, '```css');
    assert_contains($svgPrompt, '```javascript');

    $p5Prompt = art_piece_refine_user_prompt('p5', 'test', null, null, null);
    assert_not_contains($p5Prompt, '```html');
    assert_contains($p5Prompt, '```css');
    assert_contains($p5Prompt, '```javascript');
});

echo "\n=== art_piece_repair_prompt ===\n";

// 15. Repair prompt format
test('Repair prompt includes failure context', function () {
    $prompt = art_piece_repair_prompt('three', 'Create a sphere', 'bad response', 'Missing window.sketch');
    assert_contains($prompt, 'Target engine: three');
    assert_contains($prompt, 'Create a sphere');
    assert_contains($prompt, 'Missing window.sketch');
    assert_contains($prompt, 'bad response');
});

test('Repair prompt handles null previous response', function () {
    $prompt = art_piece_repair_prompt('p5', 'test', null, 'error');
    assert_contains($prompt, 'Target engine: p5');
    assert_contains($prompt, 'test');
    assert_contains($prompt, 'error');
});

echo "\n=== art_piece_refine_repair_prompt ===\n";

test('Refine repair prompt includes the failure and the format reminder, not generation-specific framing', function () {
    // Distinct from art_piece_repair_prompt(): a rejected patch has nothing
    // to do with "animations must be infinite" or visual fidelity to a
    // creative prompt, so this must not reuse that wording.
    $prompt = art_piece_refine_repair_prompt('svg', 'make it blue', 'bad response', "A patch's SEARCH text did not match");
    assert_contains($prompt, 'Target engine: svg');
    assert_contains($prompt, 'make it blue');
    assert_contains($prompt, "A patch's SEARCH text did not match");
    assert_contains($prompt, 'PLAN:');
    assert_contains($prompt, 'SEARCH');
    assert_contains($prompt, 'copied character-for-character');
    assert_not_contains($prompt, 'infinite');
});

test('Refine repair prompt handles null previous response', function () {
    $prompt = art_piece_refine_repair_prompt('p5', 'test', null, 'error');
    assert_contains($prompt, 'Target engine: p5');
    assert_contains($prompt, 'error');
});

test('Refine repair prompt re-includes the current code on retry', function () {
    // For SVG pieces, HTML is included
    $svgPrompt = art_piece_refine_repair_prompt('svg', 'darken the skin', 'bad response', 'mismatch', '<div id="container"></div>', 'body{}', 'const x = 1;');
    assert_contains($svgPrompt, 'CURRENT HTML CODE');
    assert_contains($svgPrompt, '<div id="container"></div>');
    assert_contains($svgPrompt, 'CURRENT CSS CODE');
    assert_contains($svgPrompt, 'body{}');
    assert_contains($svgPrompt, 'CURRENT JAVASCRIPT CODE');
    assert_contains($svgPrompt, 'const x = 1;');

    // For non-SVG pieces (like three), HTML is excluded
    $threePrompt = art_piece_refine_repair_prompt('three', 'darken the skin', 'bad response', 'mismatch', '<div id="container"></div>', 'body{}', 'const x = 1;');
    assert_not_contains($threePrompt, 'CURRENT HTML CODE');
    assert_not_contains($threePrompt, '<div id="container"></div>');
    assert_contains($threePrompt, 'CURRENT CSS CODE');
    assert_contains($threePrompt, 'body{}');
    assert_contains($threePrompt, 'CURRENT JAVASCRIPT CODE');
    assert_contains($threePrompt, 'const x = 1;');
});

echo "\n=== art_piece_extract_refine_plan ===\n";

test('Extracts the PLAN section', function () {
    $raw = "PLAN:\n- change the circle color\n\nPATCH html:\n<<<<<<< SEARCH\nfoo\n=======\nbar\n>>>>>>> REPLACE";
    assert_eq(art_piece_extract_refine_plan($raw), '- change the circle color');
});

test('Returns empty string when no PLAN section is present', function () {
    assert_eq(art_piece_extract_refine_plan('PATCH html:\n<<<<<<< SEARCH\nfoo\n=======\nbar\n>>>>>>> REPLACE'), '');
});

echo "\n=== art_piece_extract_refine_patches ===\n";

test('Extracts a single patch for one file', function () {
    $raw = "PLAN:\n- x\n\nPATCH html:\n<<<<<<< SEARCH\n<circle/>\n=======\n<circle fill=\"blue\"/>\n>>>>>>> REPLACE";
    $patches = art_piece_extract_refine_patches($raw);
    assert_eq(count($patches['html']), 1);
    assert_eq($patches['html'][0]['search'], '<circle/>');
    assert_eq($patches['html'][0]['replace'], '<circle fill="blue"/>');
    assert_eq(count($patches['css']), 0);
    assert_eq(count($patches['js']), 0);
});

test('Extracts multiple patches across multiple files', function () {
    $raw = "PLAN:\n- x\n\n"
        . "PATCH html:\n<<<<<<< SEARCH\na\n=======\nb\n>>>>>>> REPLACE\n\n"
        . "PATCH html:\n<<<<<<< SEARCH\nc\n=======\nd\n>>>>>>> REPLACE\n\n"
        . "PATCH css:\n<<<<<<< SEARCH\ne\n=======\nf\n>>>>>>> REPLACE";
    $patches = art_piece_extract_refine_patches($raw);
    assert_eq(count($patches['html']), 2);
    assert_eq(count($patches['css']), 1);
    assert_eq(count($patches['js']), 0);
});

test('A file with no PATCH block has no patches', function () {
    $raw = "PLAN:\n- x\n\nPATCH css:\n<<<<<<< SEARCH\na\n=======\nb\n>>>>>>> REPLACE";
    $patches = art_piece_extract_refine_patches($raw);
    assert_eq(count($patches['html']), 0);
    assert_eq(count($patches['js']), 0);
});

test('Lowercase search/replace markers still parse', function () {
    // Regression guard: a real model response that used lowercase markers
    // silently produced zero patches (treated as "succeeded, no changes"
    // instead of being parsed or rejected) until this was made case-insensitive.
    $raw = "PLAN:\n- x\n\nPATCH html:\n<<<<<<< search\n<circle/>\n=======\n<circle fill=\"blue\"/>\n>>>>>>> replace";
    $patches = art_piece_extract_refine_patches($raw);
    assert_eq(count($patches['html']), 1);
    assert_eq($patches['html'][0]['replace'], '<circle fill="blue"/>');
});

test('"PATCH javascript:" is treated as a synonym for "PATCH js:"', function () {
    // The real bug behind every Three.js refinement failing: the prompt asks
    // for the label "js", but models reliably write "javascript" instead
    // (observed consistently — every Three.js refinement is JS-only, and the
    // worked example in the system prompt only demonstrated an HTML patch).
    // Rejecting this label discards an otherwise perfectly valid,
    // correctly-targeted patch over a spelling mismatch, not a real problem.
    $raw = "PLAN:\n- x\n\nPATCH javascript:\n<<<<<<< SEARCH\nconst speed = 0.5;\n=======\nconst speed = 1.2;\n>>>>>>> REPLACE";
    $patches = art_piece_extract_refine_patches($raw);
    assert_eq(count($patches['js']), 1);
    assert_eq($patches['js'][0]['search'], 'const speed = 0.5;');
    assert_eq($patches['js'][0]['replace'], 'const speed = 1.2;');
    assert_eq(count($patches['html']), 0);
    assert_eq(count($patches['css']), 0);
});

test('Multiple "PATCH javascript:" blocks all parse, alongside an "html" block', function () {
    $raw = "PLAN:\n- x\n\n"
        . "PATCH javascript:\n<<<<<<< SEARCH\na\n=======\nb\n>>>>>>> REPLACE\n\n"
        . "PATCH javascript:\n<<<<<<< SEARCH\nc\n=======\nd\n>>>>>>> REPLACE\n\n"
        . "PATCH html:\n<<<<<<< SEARCH\ne\n=======\nf\n>>>>>>> REPLACE";
    $patches = art_piece_extract_refine_patches($raw);
    assert_eq(count($patches['js']), 2);
    assert_eq(count($patches['html']), 1);
});

test('A response with a PLAN but no PATCH blocks at all yields zero patches in every file', function () {
    // This is the exact shape of the real failure: refineAi() must treat
    // "all three arrays empty" as a failed attempt, not a success with no
    // changes — see the guard added around art_piece_extract_refine_patches()
    // in PiecesAdminController::refineAi().
    $raw = "PLAN:\n- I will add glasses and shorten the beard.\n\nNo patches follow.";
    $patches = art_piece_extract_refine_patches($raw);
    assert_eq(count($patches['html']), 0);
    assert_eq(count($patches['css']), 0);
    assert_eq(count($patches['js']), 0);
    $allEmpty = !$patches['html'] && !$patches['css'] && !$patches['js'];
    assert_eq($allEmpty, true, 'The all-empty condition used as the refineAi() guard must detect this case');
});

echo "\n=== art_piece_apply_refine_patches ===\n";

test('Applies a patch, leaving everything else byte-for-byte unchanged', function () {
    $original = "before <circle fill=\"red\"/> after";
    $patches = [['search' => '<circle fill="red"/>', 'replace' => '<circle fill="blue"/>']];
    assert_eq(art_piece_apply_refine_patches($original, $patches), 'before <circle fill="blue"/> after');
});

test('Applies multiple non-overlapping patches in one pass', function () {
    $original = "AAA BBB CCC";
    $patches = [
        ['search' => 'AAA', 'replace' => 'XXX'],
        ['search' => 'CCC', 'replace' => 'ZZZ'],
    ];
    assert_eq(art_piece_apply_refine_patches($original, $patches), 'XXX BBB ZZZ');
});

test('No patches returns the original code unchanged', function () {
    assert_eq(art_piece_apply_refine_patches('untouched code', []), 'untouched code');
});

test('Null original code with no patches returns an empty string', function () {
    assert_eq(art_piece_apply_refine_patches(null, []), '');
});

test('Throws when a SEARCH block is empty', function () {
    assert_throws(fn() => art_piece_apply_refine_patches('abc', [['search' => '  ', 'replace' => 'x']]), 'empty SEARCH');
});

test('Throws when SEARCH text is not found in the current code', function () {
    assert_throws(fn() => art_piece_apply_refine_patches('abc', [['search' => 'NOPE', 'replace' => 'x']]), 'did not match');
});

test('Throws when SEARCH text matches more than one location (ambiguous)', function () {
    assert_throws(fn() => art_piece_apply_refine_patches('abc abc', [['search' => 'abc', 'replace' => 'x']]), 'ambiguous');
});

test('Reconstructed regression case: a 3-item instruction only touches the named elements', function () {
    // Mirrors the actual failure this protocol was built to fix: a refine
    // instruction asking only to add glasses, add a mouth, and shorten the
    // hair/beard came back having rewritten the gradients, deleted the
    // abstract background shapes, deleted the body/clothing, and rewritten
    // the particle system — none of which were named. With patches, content
    // outside a matched SEARCH block cannot change, by construction.
    $original = implode("\n", [
        '<radialGradient id="bgGrad"><stop stop-color="#3a1a5a"/></radialGradient>',
        '<g id="bgShapes"><circle cx="120" cy="120" r="90" fill="#ff3366"/></g>',
        '<ellipse cx="400" cy="540" rx="200" ry="140" fill="#8b5a3c"/>', // body/clothing
        '<circle cx="370" cy="295" r="9" fill="#fff"/>', // eye
        '<path d="M 330 350 Q 315 410 320 470" fill="url(#beardGrad)"/>', // beard
    ]);
    $patches = [
        // Add glasses near the eye (additive, anchored to existing text).
        ['search' => '<circle cx="370" cy="295" r="9" fill="#fff"/>', 'replace' => '<circle cx="370" cy="295" r="9" fill="#fff"/><rect x="350" y="290" width="100" height="20" class="glasses"/>'],
        // Shorten the beard path.
        ['search' => 'M 330 350 Q 315 410 320 470', 'replace' => 'M 330 350 Q 320 380 322 410'],
    ];
    $result = art_piece_apply_refine_patches($original, $patches);

    // Untouched, unrequested elements survive byte-for-byte.
    assert_contains($result, '<radialGradient id="bgGrad"><stop stop-color="#3a1a5a"/></radialGradient>');
    assert_contains($result, '<g id="bgShapes"><circle cx="120" cy="120" r="90" fill="#ff3366"/></g>');
    assert_contains($result, '<ellipse cx="400" cy="540" rx="200" ry="140" fill="#8b5a3c"/>');
    // The named changes did apply.
    assert_contains($result, 'class="glasses"');
    assert_contains($result, 'M 330 350 Q 320 380 322 410');
    assert_not_contains($result, 'Q 315 410 320 470');
});

echo "\n=== art_piece_find_patch_match (whitespace-tolerant fallback) ===\n";

test('Tolerates the AI adding whitespace the original code does not have', function () {
    // The actual reported failure: the AI's SEARCH had spaces around the
    // braces that the real code did not.
    $original = 'const skinMaterial = new THREE.MeshStandardMaterial({color: 0xffdbac});';
    $patches = [['search' => 'const skinMaterial = new THREE.MeshStandardMaterial({ color: 0xffdbac });', 'replace' => 'const skinMaterial = new THREE.MeshStandardMaterial({color: 0xfff0d0});']];
    $result = art_piece_apply_refine_patches($original, $patches);
    assert_contains($result, '0xfff0d0');
});

test('Tolerates the AI removing whitespace the original code has', function () {
    // The reverse direction — must not require "more" whitespace either.
    $original = 'const skinMaterial = new THREE.MeshStandardMaterial({ color: 0xffdbac });';
    $patches = [['search' => 'const skinMaterial = new THREE.MeshStandardMaterial({color: 0xffdbac});', 'replace' => 'const skinMaterial = new THREE.MeshStandardMaterial({color: 0xfff0d0});']];
    $result = art_piece_apply_refine_patches($original, $patches);
    assert_contains($result, '0xfff0d0');
});

test('Tolerates indentation/newline differences in a multi-line SEARCH block', function () {
    $original = "line1\n    line2WithIndent\nline3";
    $patches = [['search' => "line1\nline2WithIndent\nline3", 'replace' => 'REPLACED']];
    assert_eq(art_piece_apply_refine_patches($original, $patches), 'REPLACED');
});

test('Whitespace tolerance still preserves everything outside the match exactly', function () {
    $original = 'BEFORE_UNTOUCHED const m = new THREE.MeshStandardMaterial({color: 0xffdbac}); AFTER_UNTOUCHED';
    $patches = [['search' => '{ color: 0xffdbac }', 'replace' => '{color: 0xfff0d0}']];
    $result = art_piece_apply_refine_patches($original, $patches);
    assert_contains($result, 'BEFORE_UNTOUCHED');
    assert_contains($result, 'AFTER_UNTOUCHED');
    assert_contains($result, '0xfff0d0');
});

test('A genuine content difference (not just whitespace) is still rejected', function () {
    // The fallback must not become content-fuzzy — every actual token must
    // still match, only whitespace between tokens is flexible.
    assert_throws(fn() => art_piece_apply_refine_patches('color: 0xffdbac', [['search' => 'color: 0xAAAAAA', 'replace' => 'x']]), 'did not match');
});

test('Ambiguous matches are still rejected even via the whitespace-tolerant path', function () {
    // Neither occurrence is an *exact* match for the single-spaced search
    // (both have two spaces), so this only reaches the fallback path — which
    // must still correctly detect that it now matches two locations.
    assert_throws(fn() => art_piece_apply_refine_patches("a  b\na  b", [['search' => 'a b', 'replace' => 'x']]), 'ambiguous');
});

test('c2 generation prompt still mandates canvas.width/height over hardcoded pixels', function () {
    // Regression guard: future c2 pieces stay consistent across rendering
    // surfaces (public view, admin preview, thumbnail, Immersive) because
    // piece-runtime.js's sizeCanvas() now gives c2 a fixed canonical
    // intrinsic resolution everywhere — but that guarantee is only useful
    // if the AI keeps being told to read canvas.width/height dynamically
    // rather than hardcoding numbers. If this instruction is ever removed
    // from the prompt, future pieces could still vary in *other* ways this
    // fix doesn't cover (e.g. literally different canvas APIs).
    $prompt = art_piece_generation_system_prompt('c2');
    assert_contains($prompt, 'new c2.Renderer(canvas)');
    assert_contains($prompt, 'canvas.width');
    assert_contains($prompt, 'canvas.height');
});

test('c2 refine prompt still forbids hardcoded pixel values', function () {
    // Same guarantee, for the AI Refine path — a refinement could otherwise
    // silently reintroduce hardcoded-pixel positioning into an
    // already-working piece.
    $prompt = art_piece_refine_system_prompt('c2');
    assert_contains($prompt, 'canvas.width');
});

test('c2 interactive prompt requires native pointer interaction while preserving c2 runtime constraints', function () {
    $prompt = art_piece_generation_system_prompt('c2_interactive');
    assert_contains($prompt, 'canvas.addEventListener()');
    assert_contains($prompt, 'pointerdown');
    assert_contains($prompt, 'touchstart');
    assert_contains($prompt, 'const { c2, canvas, startFrame } = runtime');
    assert_contains($prompt, 'NEVER use');
    assert_contains($prompt, 'c2.Mouse');
});

test('c2 interactive mode persists as c2 engine', function () {
    assert_eq(art_piece_generation_mode_to_engine('c2_interactive'), 'c2');
    assert_eq(art_piece_generation_mode_to_engine('aframe'), 'aframe');
});

test('generation mode helpers preserve explicit c2 interactive mode and legacy fallback', function () {
    assert_eq(art_piece_normalize_generation_mode('c2_interactive', 'c2'), 'c2_interactive');
    assert_eq(art_piece_version_generation_mode(['generation_mode' => 'c2_interactive', 'engine' => 'c2']), 'c2_interactive');
    assert_true(art_piece_is_c2_interactive_code("canvas.addEventListener('pointerdown', () => {});"), 'Expected direct pointer listener to count as interactive C2 code.');
    assert_eq(
        art_piece_version_generation_mode([
            'engine' => 'c2',
            'html_code' => '<canvas id="piece-canvas"></canvas>',
            'generated_code' => "canvas.addEventListener('pointerdown', () => {});",
        ]),
        'c2_interactive'
    );
});

test('generation mode labels expose C2.js Interactive distinctly', function () {
    assert_eq(art_piece_generation_mode_label('c2_interactive'), 'C2.js Interactive');
    assert_eq(art_piece_generation_mode_label('c2'), 'C2.js');
});

test('A-Frame system prompt exists', function () {
    $prompt = art_piece_generation_system_prompt('aframe');
    assert_contains($prompt, 'A-Frame');
    assert_contains($prompt, '<a-scene id="scene" embedded>');
    assert_contains($prompt, 'window.sketch');
    assert_contains($prompt, 'Do NOT include <script>');
    assert_contains($prompt, 'ONLY when the user prompt explicitly names');
});

test('generation prompts document same-origin CMS media for each engine', function () {
    assert_contains(art_piece_generation_system_prompt('p5'), "p.loadImage('/image/{id}')");
    assert_contains(art_piece_generation_system_prompt('p5'), "p.loadImage('/api/media-assets/{id}')");
    assert_contains(art_piece_generation_system_prompt('p5'), 'drawImageCover');
    assert_contains(art_piece_generation_system_prompt('three'), "new THREE.TextureLoader().load('/image/{id}')");
    assert_contains(art_piece_generation_system_prompt('three'), "new THREE.TextureLoader().load('/api/media-assets/{id}')");
    assert_contains(art_piece_generation_system_prompt('three'), 'coverTexture');
    assert_contains(art_piece_generation_system_prompt('c2'), "runtime.loadImage('/image/{id}')");
    assert_contains(art_piece_generation_system_prompt('c2'), "runtime.loadImage('/api/media-assets/{id}')");
    assert_contains(art_piece_generation_system_prompt('c2'), 'runtime.drawImageCover');
    assert_contains(art_piece_generation_system_prompt('c2_interactive'), "runtime.loadImage('/image/{id}')");
    assert_contains(art_piece_generation_system_prompt('c2_interactive'), "runtime.loadImage('/api/media-assets/{id}')");
    assert_contains(art_piece_generation_system_prompt('c2_interactive'), 'runtime.drawImageCover');
    assert_contains(art_piece_generation_system_prompt('svg'), '<image href="/image/{id}"');
    assert_contains(art_piece_generation_system_prompt('svg'), '<image href="/api/media-assets/{id}"');
    assert_contains(art_piece_generation_system_prompt('aframe'), '<img id="asset-id" src="/image/{id}">');
    assert_contains(art_piece_generation_system_prompt('aframe'), '<img id="asset-id" src="/api/media-assets/{id}">');
});

test('prompt media intent parser accepts loose image and photo ID phrasing', function () {
    assert_eq(art_piece_extract_prompt_media_refs('integrate image ID 94 as the background'), ['/image/94']);
    assert_eq(art_piece_extract_prompt_media_refs('integrate image with an ID of 94 as the background'), ['/image/94']);
    assert_eq(art_piece_extract_prompt_media_refs('integrate image 94 as the background'), ['/image/94']);
    assert_eq(art_piece_extract_prompt_media_refs('integrate photo ID 94 as the background'), ['/image/94']);
    assert_eq(art_piece_extract_prompt_media_refs('apply picture ID 94 as the texture'), ['/image/94']);
    assert_eq(art_piece_extract_prompt_media_refs('use /image/94 and /media/12'), ['/image/94', '/media/12']);
});

test('prompt media intent parser accepts media asset ID phrasing', function () {
    assert_eq(art_piece_extract_prompt_media_refs('use media asset ID 77'), ['/api/media-assets/77']);
    assert_eq(art_piece_extract_prompt_media_refs('use media asset with an ID of 77 as the background'), ['/api/media-assets/77']);
    assert_eq(art_piece_extract_prompt_media_refs('apply media asset 77 as the texture'), ['/api/media-assets/77']);
    assert_eq(art_piece_extract_prompt_media_refs('use image ID 3 and media asset ID 4'), ['/image/3', '/api/media-assets/4']);
});

test('prompt media policy rejects unprompted CMS media', function () {
    assert_throws(
        fn() => validate_art_piece_prompted_media_refs([], '<div id="canvas-container"></div>', '', "window.sketch = (p) => { p.preload = () => { p.loadImage('/image/3'); }; };"),
        'explicitly names'
    );
});

test('prompt media policy accepts explicitly requested CMS media', function () {
    $allowed = art_piece_extract_prompt_media_refs('make image ID 94 the background');
    validate_art_piece_prompted_media_refs($allowed, '<div id="canvas-container"></div>', '', "window.sketch = (p) => { p.preload = () => { p.loadImage('/image/94'); }; };", [], true);
});

test('prompt media policy accepts image with an ID phrasing', function () {
    $allowed = art_piece_extract_prompt_media_refs('make the image with an ID of 94 the background');
    validate_art_piece_prompted_media_refs($allowed, '<div id="canvas-container"></div>', '', "window.sketch = (p) => { p.preload = () => { p.loadImage('/image/94'); }; };", [], true);
});

test('prompt media policy accepts media asset with an ID phrasing', function () {
    $allowed = art_piece_extract_prompt_media_refs('apply media asset ID 94 as the texture');
    validate_art_piece_prompted_media_refs($allowed, '<div id="canvas-container"></div>', '', "window.sketch = (p) => { p.preload = () => { p.loadImage('/api/media-assets/94'); }; };", [], true);
});

test('prompt media policy rejects a different image than the one requested', function () {
    $allowed = art_piece_extract_prompt_media_refs('make image ID 94 the background');
    assert_throws(
        fn() => validate_art_piece_prompted_media_refs($allowed, '<div id="canvas-container"></div>', '', "window.sketch = (p) => { p.preload = () => { p.loadImage('/image/3'); }; };", [], true),
        'Unexpected reference'
    );
});

test('prompt media policy rejects media asset path when only image/photo prompt was given', function () {
    $allowed = art_piece_extract_prompt_media_refs('make photo ID 94 the background');
    assert_throws(
        fn() => validate_art_piece_prompted_media_refs($allowed, '<div id="canvas-container"></div>', '', "window.sketch = (p) => { p.preload = () => { p.loadImage('/api/media-assets/94'); }; };", [], true),
        'Unexpected reference'
    );
});

test('prompt media policy rejects image path when only media asset prompt was given', function () {
    $allowed = art_piece_extract_prompt_media_refs('apply media asset ID 94 as the texture');
    assert_throws(
        fn() => validate_art_piece_prompted_media_refs($allowed, '<div id="canvas-container"></div>', '', "window.sketch = (p) => { p.preload = () => { p.loadImage('/image/94'); }; };", [], true),
        'Unexpected reference'
    );
});

test('prompt media policy accepts both route families when both are explicitly requested', function () {
    $allowed = art_piece_extract_prompt_media_refs('use image ID 94 and media asset ID 77 in the piece');
    validate_art_piece_prompted_media_refs(
        $allowed,
        '<div id="canvas-container"></div>',
        '',
        "window.sketch = (p) => { p.preload = () => { p.loadImage('/image/94'); p.loadImage('/api/media-assets/77'); }; };",
        [],
        true
    );
});

test('prompt media policy allows existing refine media but rejects newly introduced unprompted media', function () {
    validate_art_piece_prompted_media_refs([], '<div id="canvas-container"></div>', '', "window.sketch = (p) => { p.preload = () => { p.loadImage('/image/2'); }; };", ['/image/2'], false);
    assert_throws(
        fn() => validate_art_piece_prompted_media_refs([], '<div id="canvas-container"></div>', '', "window.sketch = (p) => { p.preload = () => { p.loadImage('/image/2'); p.loadImage('/image/3'); }; };", ['/image/2'], false),
        'Unexpected reference'
    );
});

test('p5 preflight accepts same-origin CMS image loading', function () {
    $html = '<div id="canvas-container"></div>';
    $js = "let img; window.sketch = (p) => { p.preload = () => { img = p.loadImage('/image/2'); }; p.setup = () => { p.createCanvas(400, 300); }; p.draw = () => { if (img) p.image(img, 0, 0, p.width, p.height); }; };";
    $result = art_piece_preflight_document('p5', $html, '#canvas-container{width:100%;height:100%;}', $js);
    assert_eq($result['js'], $js);
});

test('p5 preflight accepts local cover-image helper using CMS media', function () {
    $html = '<div id="canvas-container"></div>';
    $js = "let img; window.sketch = (p) => { p.preload = () => { img = p.loadImage('/image/3'); }; p.setup = () => { p.createCanvas(400, 300); }; function drawImageCover(image, x, y, width, height) { p.image(image, x, y, width, height, 0, 0, image.width, image.height); } p.draw = () => { if (img) drawImageCover(img, 0, 0, p.width, p.height); }; };";
    $result = art_piece_preflight_document('p5', $html, '#canvas-container{width:100%;height:100%;}', $js);
    assert_contains($result['js'], 'drawImageCover');
});

test('p5 preflight rejects remote image loading', function () {
    assert_throws(
        fn() => art_piece_preflight_document('p5', '<div id="canvas-container"></div>', '', "window.sketch = (p) => { p.preload = () => { p.loadImage('https://example.com/cat.png'); }; };"),
        'same-origin CMS media'
    );
});

test('Three.js preflight accepts same-origin CMS texture loading', function () {
    $html = '<div id="container"></div>';
    $js = "window.sketch = (runtime) => { const { THREE, canvas } = runtime; const texture = new THREE.TextureLoader().load('/image/2'); texture.colorSpace = THREE.SRGBColorSpace; const material = new THREE.MeshBasicMaterial({ map: texture }); };";
    $result = art_piece_preflight_document('three', $html, '#container{width:100%;height:100%;}', $js);
    assert_contains($result['js'], "TextureLoader");
});

test('Three.js preflight accepts camera-frame cover texture helper', function () {
    $html = '<div id="container"></div>';
    $js = "window.sketch = (runtime) => { const { THREE } = runtime; function coverTexture(texture, backgroundWidth, backgroundHeight) { texture.repeat.set(1, 1); texture.offset.set(0, 0); } const texture = new THREE.TextureLoader().load('/image/3', () => coverTexture(texture, 16, 9)); const plane = new THREE.Mesh(new THREE.PlaneGeometry(16, 9), new THREE.MeshBasicMaterial({ map: texture })); };";
    $result = art_piece_preflight_document('three', $html, '#container{width:100%;height:100%;}', $js);
    assert_contains($result['js'], 'coverTexture');
});

test('Three.js preflight rejects remote texture loading', function () {
    assert_throws(
        fn() => art_piece_preflight_document('three', '<div id="container"></div>', '', "window.sketch = (runtime) => { const texture = new THREE.TextureLoader().load('https://example.com/texture.png'); };"),
        'same-origin CMS media'
    );
});

test('C2 preflight accepts runtime media helpers', function () {
    $html = '<canvas id="piece-canvas"></canvas>';
    $js = "window.sketch = (runtime) => { const { c2, canvas, startFrame } = runtime; const renderer = new c2.Renderer(canvas); const img = runtime.loadImage('/image/2'); startFrame(() => { renderer.clear('#000'); runtime.drawImage(img, 0, 0, canvas.width, canvas.height); }); };";
    $result = art_piece_preflight_document('c2', $html, '#piece-canvas{width:100%;height:100%;}', $js);
    assert_contains($result['js'], 'runtime.drawImage');
});

test('C2 preflight accepts runtime cover media helper', function () {
    $html = '<canvas id="piece-canvas"></canvas>';
    $js = "window.sketch = (runtime) => { const { c2, canvas, startFrame } = runtime; const renderer = new c2.Renderer(canvas); const img = runtime.loadImage('/image/3'); startFrame(() => { renderer.clear('#000'); runtime.drawImageCover(img, 0, 0, canvas.width, canvas.height); }); };";
    $result = art_piece_preflight_document('c2', $html, '#piece-canvas{width:100%;height:100%;}', $js);
    assert_contains($result['js'], 'runtime.drawImageCover');
});

test('C2 preflight rejects raw canvas image APIs', function () {
    assert_throws(
        fn() => art_piece_preflight_document('c2', '<canvas id="piece-canvas"></canvas>', '', "window.sketch = (runtime) => { const ctx = runtime.canvas.getContext('2d'); const img = new Image(); ctx.drawImage(img, 0, 0); };"),
        'raw canvas image APIs'
    );
});

test('C2 preflight rejects remote runtime media loading', function () {
    assert_throws(
        fn() => art_piece_preflight_document('c2', '<canvas id="piece-canvas"></canvas>', '', "window.sketch = (runtime) => { runtime.loadImage('https://example.com/x.png'); };"),
        'same-origin CMS media'
    );
});

test('C2 interactive preflight rejects code with no direct interaction hooks', function () {
    assert_throws(
        fn() => art_piece_preflight_document(
            'c2',
            '<canvas id="piece-canvas"></canvas>',
            '',
            "window.sketch = (runtime) => { const { c2, canvas, startFrame } = runtime; const renderer = new c2.Renderer(canvas); startFrame(() => { renderer.clear('#000'); renderer.circle(canvas.width / 2, canvas.height / 2, 40); }); };",
            'c2_interactive'
        ),
        'requires explicit pointer, mouse, touch, or click interaction hooks'
    );
});

test('C2 interactive preflight accepts code with direct interaction hooks', function () {
    $js = "window.sketch = (runtime) => { const { c2, canvas, startFrame } = runtime; const renderer = new c2.Renderer(canvas); let burst = 0; canvas.addEventListener('pointerdown', () => { burst = 1; }); startFrame(() => { renderer.clear('#000'); renderer.circle(canvas.width / 2, canvas.height / 2, 40 + burst * 20); burst *= 0.96; }); };";
    $result = art_piece_preflight_document(
        'c2',
        '<canvas id="piece-canvas"></canvas>',
        '',
        $js,
        'c2_interactive'
    );
    assert_contains($result['js'], "canvas.addEventListener('pointerdown'");
});

test('SVG preflight accepts same-origin CMS image elements', function () {
    $html = '<svg viewBox="0 0 800 600" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%"><image href="/image/2" x="0" y="0" width="800" height="600"/></svg>';
    $result = art_piece_preflight_document('svg', $html, 'svg { display:block; width:100%; height:100%; }', 'window.sketch = () => {};');
    assert_eq($result['html'], $html);
});

test('SVG preflight rejects remote image elements', function () {
    assert_throws(
        fn() => art_piece_preflight_document('svg', '<svg viewBox="0 0 800 600" xmlns="http://www.w3.org/2000/svg"><image href="https://example.com/x.png"/></svg>', '', 'window.sketch = () => {};'),
        'same-origin CMS media'
    );
});

test('A-Frame preflight accepts a minimal safe scene', function () {
    $html = '<a-scene id="scene" embedded><a-sky color="#101014"></a-sky><a-box position="0 1 -3" color="#ffcc00" animation="property: rotation; to: 0 360 0; loop: true; dur: 4000"></a-box></a-scene>';
    $css = '#scene { width: 100%; height: 100%; }';
    $js = 'window.sketch = ({ AFRAME, scene, startFrame }) => { startFrame(() => {}); };';
    $result = art_piece_preflight_document('aframe', $html, $css, $js);
    assert_eq($result['html'], $html);
});

test('A-Frame preflight rejects missing scene root', function () {
    assert_throws(
        fn() => art_piece_preflight_document('aframe', '<a-box></a-box>', '', 'window.sketch = ({ scene }) => {};'),
        '<a-scene'
    );
});

test('A-Frame preflight rejects external assets', function () {
    assert_throws(
        fn() => art_piece_preflight_document('aframe', '<a-scene id="scene" embedded><a-image src="https://example.com/x.png"></a-image></a-scene>', '', 'window.sketch = ({ scene }) => {};'),
        'same-origin CMS media'
    );
});

test('A-Frame preflight accepts same-origin CMS image assets', function () {
    $html = '<a-scene id="scene" embedded><a-assets><img id="my-logo" src="/image/2"></a-assets><a-plane src="#my-logo" rotation="-90 0 0" width="9" height="9"></a-plane></a-scene>';
    $result = art_piece_preflight_document('aframe', $html, '', 'window.sketch = ({ scene }) => {};');
    assert_eq($result['html'], $html);
});

test('A-Frame preflight rejects undefined asset references', function () {
    assert_throws(
        fn() => art_piece_preflight_document('aframe', '<a-scene id="scene" embedded><a-plane src="#missing"></a-plane></a-scene>', '', 'window.sketch = ({ scene }) => {};'),
        'defined in <a-assets>'
    );
});

test('A-Frame preflight rejects webcam or media capture', function () {
    assert_throws(
        fn() => art_piece_preflight_document('aframe', '<a-scene id="scene" embedded></a-scene>', '', 'window.sketch = () => { navigator.mediaDevices.getUserMedia({ video: true }); };'),
        'webcam'
    );
});

test('A-Frame preflight rejects hidden scene CSS', function () {
    assert_throws(
        fn() => art_piece_preflight_document('aframe', '<a-scene id="scene" embedded></a-scene>', '#scene { display: none; }', 'window.sketch = ({ scene }) => {};'),
        'display: none'
    );
});

test('default art starter templates pass preflight', function () {
    $templates = require __DIR__ . '/../public/app/config/art-starter-templates.php';
    assert_eq(count($templates), 6);
    foreach ($templates as $template) {
        $engine = (string) $template['engine'];
        art_piece_preflight_document(
            $engine,
            (string) $template['html_code'],
            (string) $template['css_code'],
            (string) $template['js_code']
        );
    }
});

test('starter templates demonstrate CMS foreground photo ID 2', function () {
    $templates = require __DIR__ . '/../public/app/config/art-starter-templates.php';
    foreach ($templates as $template) {
        $combined = (string) $template['html_code'] . "\n" . (string) $template['css_code'] . "\n" . (string) $template['js_code'];
        assert_contains($combined, '/image/2', (string) ($template['template_key'] ?? 'template'));
    }
});

test('starter templates demonstrate full-frame CMS background photo ID 3', function () {
    $templates = require __DIR__ . '/../public/app/config/art-starter-templates.php';
    foreach ($templates as $template) {
        $combined = (string) $template['html_code'] . "\n" . (string) $template['css_code'] . "\n" . (string) $template['js_code'];
        assert_contains($combined, '/image/3', (string) ($template['template_key'] ?? 'template'));
    }
});

test('starter templates expose editable foreground and full-frame background sizing', function () {
    $templates = require __DIR__ . '/../public/app/config/art-starter-templates.php';
    $byKey = [];
    foreach ($templates as $template) {
        $byKey[(string) $template['template_key']] = (string) $template['html_code'] . "\n" . (string) $template['css_code'] . "\n" . (string) $template['js_code'];
    }

    foreach (['p5_default', 'c2_default', 'c2_interactive_default'] as $key) {
        assert_contains($byKey[$key], 'backgroundWidth');
        assert_contains($byKey[$key], 'backgroundHeight');
        assert_contains($byKey[$key], 'portraitWidth');
        assert_contains($byKey[$key], 'portraitHeight');
    }

    assert_contains($byKey['three_default'], 'backgroundDepth');
    assert_contains($byKey['three_default'], 'backgroundWidth');
    assert_contains($byKey['three_default'], 'backgroundHeight');
    assert_contains($byKey['three_default'], 'PlaneGeometry(backgroundWidth, backgroundHeight)');

    assert_contains($byKey['aframe_default'], 'id="background-plane"');
    assert_contains($byKey['aframe_default'], 'backgroundDistance');
    assert_contains($byKey['aframe_default'], 'backgroundWidth');
    assert_contains($byKey['aframe_default'], 'backgroundHeight');
    assert_contains($byKey['aframe_default'], "backgroundPlane.setAttribute('width', backgroundWidth)");
    assert_contains($byKey['aframe_default'], 'portraitWidth');
    assert_contains($byKey['aframe_default'], 'portraitHeight');

    assert_contains($byKey['svg_default'], '<image href="/image/3" x="0" y="0" width="800" height="600"');
    assert_contains($byKey['svg_default'], '<image href="/image/2" x="312" y="212" width="176" height="176"');
});

test('starter templates use cover helpers for full-frame raster backgrounds', function () {
    $templates = require __DIR__ . '/../public/app/config/art-starter-templates.php';
    $byKey = [];
    foreach ($templates as $template) {
        $byKey[(string) $template['template_key']] = (string) $template['html_code'] . "\n" . (string) $template['css_code'] . "\n" . (string) $template['js_code'];
    }

    assert_contains($byKey['p5_default'], 'function drawImageCover');
    assert_contains($byKey['p5_default'], 'drawImageCover(backdrop, 0, 0, backgroundWidth, backgroundHeight)');
    assert_contains($byKey['c2_default'], 'runtime.drawImageCover(backdrop, 0, 0, backgroundWidth, backgroundHeight)');
    assert_contains($byKey['three_default'], 'function coverTexture');
    assert_contains($byKey['three_default'], "textureLoader.load('/image/3'");
    assert_contains($byKey['three_default'], 'camera.add(backgroundPlane)');
});

test('piece export document creates standalone download HTML without presentation controls', function () {
    $_ENV['PUBLIC_SITE_URL'] = '';
    putenv('PUBLIC_SITE_URL');
    $_SERVER['HTTP_HOST'] = 'example.test';
    $_SERVER['HTTPS'] = 'on';
    $piece = ['id' => 42, 'title' => 'Portable Test Piece', 'engine' => 'p5'];
    $version = [
        'engine' => 'p5',
        'html_code' => '<div id="canvas-container"></div>',
        'css_code' => '#canvas-container{width:100%;height:100%;background:url("media/example.png");}',
        'generated_code' => "let img; window.sketch = (p) => { p.preload = () => { img = p.loadImage('image/83'); }; p.setup = () => { p.createCanvas(100, 100); }; p.draw = () => { p.background(0); }; }; const asset = 'api/media-assets/83';",
    ];

    $document = piece_export_document($piece, $version);
    assert_contains($document, '<!DOCTYPE html>');
    assert_contains($document, '<body>');
    assert_contains($document, '<div id="runtime-root"><div id="canvas-container"></div></div>');
    assert_contains($document, 'https://cdnjs.cloudflare.com/ajax/libs/p5.js/1.9.0/p5.min.js');
    assert_contains($document, 'https://example.test/image/83');
    assert_contains($document, 'https://example.test/media/example.png');
    assert_contains($document, 'https://example.test/api/media-assets/83');
    assert_contains($document, 'window.sketch = (p)');
    assert_not_contains($document, 'Immersive View');
    assert_not_contains($document, 'copyEmbed');
    assert_eq(piece_export_filename($piece), 'portable-test-piece.html');
});

test('piece export document uses CDN imports for every engine', function () {
    $_ENV['PUBLIC_SITE_URL'] = '';
    putenv('PUBLIC_SITE_URL');
    $cases = [
        'p5' => 'https://cdnjs.cloudflare.com/ajax/libs/p5.js/1.9.0/p5.min.js',
        'c2' => 'https://cdn.jsdelivr.net/npm/c2.js@1.0.9/dist/c2.min.js',
        'three' => 'https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js',
        'aframe' => 'https://aframe.io/releases/1.6.0/aframe.min.js',
    ];

    foreach ($cases as $engine => $cdn) {
        $document = piece_export_document(
            ['id' => 7, 'title' => strtoupper($engine), 'engine' => $engine],
            [
                'engine' => $engine,
                'html_code' => $engine === 'aframe'
                    ? '<a-scene id="scene" embedded><a-box position="0 1 -3"></a-box></a-scene>'
                    : ($engine === 'c2' ? '<canvas id="piece-canvas"></canvas>' : '<div id="' . ($engine === 'three' ? 'container' : 'canvas-container') . '"></div>'),
                'css_code' => '',
                'generated_code' => $engine === 'aframe'
                    ? 'window.sketch = ({ AFRAME, scene, startFrame }) => { startFrame(() => {}); };'
                    : 'window.sketch = () => {};',
            ]
        );
        assert_contains($document, $cdn, $engine);
    }

    $aframeDocument = piece_export_document(
        ['id' => 90, 'title' => 'A-Frame Piece', 'engine' => 'aframe'],
        [
            'engine' => 'aframe',
            'html_code' => '<a-scene id="scene" embedded><a-sky color="#000"></a-sky></a-scene>',
            'css_code' => '#scene{width:100%;height:100%;}',
            'generated_code' => 'window.sketch = ({ AFRAME, scene, startFrame }) => { startFrame(() => {}); };',
        ]
    );
    assert_not_contains($aframeDocument, '/assets/js/aframe.min.js');
    assert_contains($aframeDocument, '<a-scene id="scene" embedded>');
    assert_contains($aframeDocument, 'window.sketch = ({ AFRAME, scene, startFrame })');
    assert_contains($aframeDocument, 'window.sketch({ AFRAME: window.AFRAME, scene, startFrame })');
});

test('piece render and export documents base media on request host, not public URL', function () {
    $_ENV['PUBLIC_SITE_URL'] = 'https://example.test';
    putenv('PUBLIC_SITE_URL=https://example.test');
    $_SERVER['HTTP_HOST'] = '127.0.0.1:8080';
    $_SERVER['HTTPS'] = 'off';

    $document = piece_render_document(
        ['id' => 94, 'title' => 'Local Preview Piece', 'engine' => 'aframe'],
        [
            'engine' => 'aframe',
            'html_code' => '<a-scene id="scene" embedded><a-assets><img id="texture" src="/api/media-assets/1"></a-assets></a-scene>',
            'css_code' => '',
            'generated_code' => 'window.sketch = ({ scene }) => {};',
        ]
    );

    assert_contains($document, '<base href="http://127.0.0.1:8080/">');
    assert_not_contains($document, '<base href="https://example.test/">');

    $exported = piece_export_document(
        ['id' => 94, 'title' => 'Local Download Piece', 'engine' => 'aframe'],
        [
            'engine' => 'aframe',
            'html_code' => '<a-scene id="scene" embedded><a-assets><img id="texture" src="/api/media-assets/1"></a-assets></a-scene>',
            'css_code' => '.frame{background:url("media/example.png");}',
            'generated_code' => "window.sketch = ({ scene }) => {}; const asset = 'image/83';",
        ]
    );

    assert_contains($exported, 'http://127.0.0.1:8080/api/media-assets/1');
    assert_contains($exported, 'http://127.0.0.1:8080/media/example.png');
    assert_contains($exported, 'http://127.0.0.1:8080/image/83');
    assert_not_contains($exported, 'https://example.test/api/media-assets/1');
});

test('A-Frame render and export documents add crossorigin to asset images', function () {
    $_SERVER['HTTP_HOST'] = 'example.test';
    $_SERVER['HTTPS'] = 'on';
    $piece = ['id' => 94, 'title' => 'A-Frame Texture Piece', 'engine' => 'aframe'];
    $version = [
        'engine' => 'aframe',
        'html_code' => '<a-scene id="scene" embedded><a-assets><img id="texture" src="/api/media-assets/1"></a-assets><a-plane material="src: #texture"></a-plane></a-scene>',
        'css_code' => '#scene{width:100%;height:100%;}',
        'generated_code' => 'window.sketch = ({ scene }) => {};',
    ];

    $rendered = piece_render_document($piece, $version);
    assert_contains($rendered, '<img id="texture" src="/api/media-assets/1" crossorigin="anonymous">');

    $exported = piece_export_document($piece, $version);
    assert_contains($exported, '<img id="texture" src="https://example.test/api/media-assets/1" crossorigin="anonymous">');
});

test('piece export document keeps Three, A-Frame, and C2 interactive in downloaded HTML', function () {
    $threeDocument = piece_export_document(
        ['id' => 83, 'title' => 'Interactive Three', 'engine' => 'three'],
        [
            'engine' => 'three',
            'html_code' => '<div id="container"></div>',
            'css_code' => '#container{width:100%;height:100%;}',
            'generated_code' => "window.sketch = ({ THREE, canvas, startFrame }) => { const scene = new THREE.Scene(); const camera = new THREE.PerspectiveCamera(60, canvas.width / canvas.height, 0.1, 100); camera.position.z = 5; const renderer = new THREE.WebGLRenderer({ canvas }); renderer.setSize(canvas.width, canvas.height); startFrame(() => renderer.render(scene, camera)); };",
        ]
    );
    assert_contains($threeDocument, 'instrumentedThree.WebGLRenderer');
    assert_contains($threeDocument, 'new OrbitControls(state.camera, canvas)');
    assert_contains($threeDocument, 'userHasInteracted');
    assert_contains($threeDocument, "canvas.style.touchAction = 'none'");

    $aframeDocument = piece_export_document(
        ['id' => 90, 'title' => 'Interactive A-Frame', 'engine' => 'aframe'],
        [
            'engine' => 'aframe',
            'html_code' => '<a-scene id="scene" embedded><a-box id="target" position="0 1 -3"></a-box></a-scene>',
            'css_code' => '#scene{width:100%;height:100%;}',
            'generated_code' => "window.sketch = ({ scene }) => { scene.querySelector('#target')?.addEventListener('click', () => {}); };",
        ]
    );
    assert_contains($aframeDocument, 'https://aframe.io/releases/1.6.0/aframe.min.js');
    assert_contains($aframeDocument, "addEventListener('click'");
    assert_contains($aframeDocument, 'window.sketch({ AFRAME: window.AFRAME, scene, startFrame })');

    $c2Document = piece_export_document(
        ['id' => 91, 'title' => 'Interactive C2', 'engine' => 'c2'],
        [
            'engine' => 'c2',
            'html_code' => '<canvas id="piece-canvas"></canvas>',
            'css_code' => '#piece-canvas{width:100%;height:100%;}',
            'generated_code' => "window.sketch = ({ canvas, startFrame }) => { canvas.addEventListener('pointerdown', () => {}); startFrame(() => {}); };",
        ]
    );
    assert_contains($c2Document, 'https://cdn.jsdelivr.net/npm/c2.js@1.0.9/dist/c2.min.js');
    assert_contains($c2Document, "canvas.addEventListener('pointerdown'");
    assert_contains($c2Document, 'window.sketch({ c2: window.c2, canvas, startFrame, loadImage, drawImage, drawImageCover })');
});

echo "\n=== Results ===\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}
echo "All tests passed!\n";
