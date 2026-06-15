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

echo "\n=== art_piece_refine_user_prompt ===\n";

// 14. User prompt format
test('User prompt includes all sections', function () {
    $prompt = art_piece_refine_user_prompt('p5', 'Make it blue', '<div></div>', 'body{}', 'window.sketch = () => {};');
    assert_contains($prompt, 'REFINEMENT INSTRUCTION');
    assert_contains($prompt, 'Make it blue');
    assert_contains($prompt, 'CURRENT HTML CODE');
    assert_contains($prompt, 'CURRENT CSS CODE');
    assert_contains($prompt, 'CURRENT JAVASCRIPT CODE');
});

test('User prompt handles null inputs', function () {
    $prompt = art_piece_refine_user_prompt('p5', 'test', null, null, null);
    assert_contains($prompt, '```html');
    assert_contains($prompt, '```css');
    assert_contains($prompt, '```javascript');
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

echo "\n=== Results ===\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}
echo "All tests passed!\n";
