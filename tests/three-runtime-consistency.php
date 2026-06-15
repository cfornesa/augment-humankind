<?php
/**
 * Tests that the Three.js runtime is consistent across all PHP rendering files.
 * Run with: php tests/three-runtime-consistency.php
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

echo "=== piece-render.php ===\n";

$pieceRender = file_get_contents(__DIR__ . '/../public/app/helpers/piece-render.php');

test('Has importmap for three', function () use ($pieceRender) {
    assert_contains($pieceRender, '"three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js"');
});

test('Has importmap for three/addons', function () use ($pieceRender) {
    assert_contains($pieceRender, '"three/addons/": "https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"');
});

test('Has instrumentedThree', function () use ($pieceRender) {
    assert_contains($pieceRender, 'instrumentedThree');
});

test('Has autoFit', function () use ($pieceRender) {
    assert_contains($pieceRender, 'autoFit');
});

test('Has ensureFallbackLighting', function () use ($pieceRender) {
    assert_contains($pieceRender, 'ensureFallbackLighting');
});

test('Has OrbitControls', function () use ($pieceRender) {
    assert_contains($pieceRender, 'OrbitControls');
});

test('startFrame passes count', function () use ($pieceRender) {
    assert_contains($pieceRender, 'callback(count)');
    assert_not_contains($pieceRender, 'callback(time)', 'startFrame should pass count, not time');
});

test('Canvas CSS is 100% width/height', function () use ($pieceRender) {
    assert_contains($pieceRender, 'width:100%;height:100%;');
});

test('WebGLRenderer uses managed canvas', function () use ($pieceRender) {
    assert_contains($pieceRender, 'super({ ...(params || {}), canvas })');
});

echo "\n=== form.php ===\n";

$formPhp = file_get_contents(__DIR__ . '/../public/app/views/admin/pieces/form.php');

test('Has importmap for three', function () use ($formPhp) {
    assert_contains($formPhp, '"three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js"');
});

test('Has importmap for three/addons', function () use ($formPhp) {
    assert_contains($formPhp, '"three/addons/": "https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"');
});

test('Has instrumentedThree', function () use ($formPhp) {
    assert_contains($formPhp, 'instrumentedThree');
});

test('Has autoFit', function () use ($formPhp) {
    assert_contains($formPhp, 'autoFit');
});

test('Has ensureFallbackLighting', function () use ($formPhp) {
    assert_contains($formPhp, 'ensureFallbackLighting');
});

test('Has OrbitControls', function () use ($formPhp) {
    assert_contains($formPhp, 'OrbitControls');
});

test('startFrame passes count', function () use ($formPhp) {
    assert_contains($formPhp, 'callback(count)');
    assert_not_contains($formPhp, 'callback(time)', 'startFrame should pass count, not time');
});

test('Canvas CSS is 100% width/height', function () use ($formPhp) {
    assert_contains($formPhp, 'width:100%;height:100%;');
});

test('WebGLRenderer uses managed canvas', function () use ($formPhp) {
    assert_contains($formPhp, 'super({ ...(params || {}), canvas })');
});

echo "\n=== generate-preview.php ===\n";

$previewPhp = file_get_contents(__DIR__ . '/../public/app/views/admin/pieces/generate-preview.php');

test('Has importmap for three', function () use ($previewPhp) {
    assert_contains($previewPhp, '"three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js"');
});

test('Has importmap for three/addons', function () use ($previewPhp) {
    assert_contains($previewPhp, '"three/addons/": "https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"');
});

test('Has instrumentedThree', function () use ($previewPhp) {
    assert_contains($previewPhp, 'instrumentedThree');
});

test('Has autoFit', function () use ($previewPhp) {
    assert_contains($previewPhp, 'autoFit');
});

test('Has ensureFallbackLighting', function () use ($previewPhp) {
    assert_contains($previewPhp, 'ensureFallbackLighting');
});

test('Has OrbitControls', function () use ($previewPhp) {
    assert_contains($previewPhp, 'OrbitControls');
});

test('startFrame passes count', function () use ($previewPhp) {
    assert_contains($previewPhp, 'callback(count)');
    assert_not_contains($previewPhp, 'callback(time)', 'startFrame should pass count, not time');
});

test('Canvas CSS is 100% width/height', function () use ($previewPhp) {
    assert_contains($previewPhp, 'width:100%;height:100%;');
});

test('WebGLRenderer uses managed canvas', function () use ($previewPhp) {
    assert_contains($previewPhp, 'super({ ...(params || {}), canvas })');
});

echo "\n=== embed.js ===\n";

$embedJs = file_get_contents(__DIR__ . '/../public/embed.js');

test('Has instrumentedThree', function () use ($embedJs) {
    assert_contains($embedJs, 'instrumentedThree');
});

test('Has autoFit', function () use ($embedJs) {
    assert_contains($embedJs, 'autoFit');
});

test('Has ensureFallbackLighting', function () use ($embedJs) {
    assert_contains($embedJs, 'ensureFallbackLighting');
});

test('Has OrbitControls', function () use ($embedJs) {
    assert_contains($embedJs, 'OrbitControls');
});

test('startFrame passes count', function () use ($embedJs) {
    assert_contains($embedJs, 'handler(count)');
    assert_not_contains($embedJs, 'handler(time)', 'startFrame should pass count, not time');
});

test('Canvas CSS is 100% width/height', function () use ($embedJs) {
    assert_contains($embedJs, 'width:100%;height:100%;');
});

test('WebGLRenderer uses managed canvas', function () use ($embedJs) {
    assert_contains($embedJs, 'super({ ...(params || {}), canvas })');
});

echo "\n=== Render loop wiring (bootThree) ===\n";

test('piece-render.php bootThree creates OrbitControls bound to state.camera/canvas', function () use ($pieceRender) {
    assert_contains($pieceRender, 'new OrbitControls(state.camera, canvas)');
});

test('piece-render.php bootThree has an animateControls render loop that calls renderer.render', function () use ($pieceRender) {
    assert_contains($pieceRender, 'const animateControls = () => {');
    assert_contains($pieceRender, 'state.renderer.render(state.scene, state.camera)');
});

test('form.php bootThree creates OrbitControls bound to state.camera/canvas', function () use ($formPhp) {
    assert_contains($formPhp, 'new OrbitControls(state.camera, canvas)');
});

test('form.php bootThree has an animateControls render loop that calls renderer.render', function () use ($formPhp) {
    assert_contains($formPhp, 'const animateControls = () => {');
    assert_contains($formPhp, 'state.renderer.render(state.scene, state.camera)');
});

test('generate-preview.php bootThree creates OrbitControls bound to state.camera/canvas', function () use ($previewPhp) {
    assert_contains($previewPhp, 'new OrbitControls(state.camera, canvas)');
});

test('generate-preview.php bootThree has an animateControls render loop that calls renderer.render', function () use ($previewPhp) {
    assert_contains($previewPhp, 'const animateControls = () => {');
    assert_contains($previewPhp, 'state.renderer.render(state.scene, state.camera)');
});

echo "\n=== Consistency check ===\n";

test('All three runtimes have consistent fallback lighting names', function () use ($pieceRender, $formPhp, $previewPhp, $embedJs) {
    foreach ([$pieceRender, $formPhp, $previewPhp, $embedJs] as $src) {
        assert_contains($src, '__viewer_fallback_ambient__');
        assert_contains($src, '__viewer_fallback_dir__');
    }
});

test('All three runtimes have Box3 for autoFit', function () use ($pieceRender, $formPhp, $previewPhp, $embedJs) {
    // piece-render.php / form.php / generate-preview.php use `mod.Box3` (imported as `mod`)
    // embed.js uses `THREE.Box3` directly
    foreach ([$pieceRender, $formPhp, $previewPhp] as $src) {
        assert_contains($src, 'new mod.Box3()');
    }
    assert_contains($embedJs, 'new THREE.Box3()');
});

echo "\n=== Results ===\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}
echo "All runtime consistency checks passed!\n";
