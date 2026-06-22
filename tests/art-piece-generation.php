<?php
/**
 * Simple CLI test for art piece generation helpers.
 * Run with: php tests/art-piece-generation.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../public/app/helpers/art-piece-generation.php';

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

echo "\n=== Results ===\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}
echo "All tests passed!\n";
