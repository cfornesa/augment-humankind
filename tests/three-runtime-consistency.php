<?php
/**
 * Tests that the piece rendering runtime is consistent and correctly wired
 * across the PHP views that load it.
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

echo "=== shared piece-runtime.js ===\n";

$runtime = file_get_contents(__DIR__ . '/../public/assets/js/piece-runtime.js');

test('Has instrumentedThree', function () use ($runtime) {
    assert_contains($runtime, 'instrumentedThree');
});

test('findCanvas mounts inside a piece-authored #container (matches the documented contract)', function () use ($runtime) {
    assert_contains($runtime, "document.getElementById('container')");
});

test('Has autoFit', function () use ($runtime) {
    assert_contains($runtime, 'autoFit');
});

test('Has ensureFallbackLighting', function () use ($runtime) {
    assert_contains($runtime, 'ensureFallbackLighting');
});

test('Has OrbitControls', function () use ($runtime) {
    assert_contains($runtime, 'OrbitControls');
});

test('startFrame passes count, not elapsed/delta time', function () use ($runtime) {
    assert_contains($runtime, 'handler(count)');
    assert_not_contains($runtime, 'handler(time)');
    assert_not_contains($runtime, 'handler(count, ');
});

test('WebGLRenderer uses managed canvas', function () use ($runtime) {
    assert_contains($runtime, 'super({ ...(params || {}), canvas,');
});

test('WebGLRenderer respects PIECE_PRESERVE_DRAWING_BUFFER for thumbnail capture', function () use ($runtime) {
    assert_contains($runtime, 'window.PIECE_PRESERVE_DRAWING_BUFFER');
});

test('OrbitControls loop creates OrbitControls bound to state.camera/canvas', function () use ($runtime) {
    assert_contains($runtime, 'new OrbitControls(state.camera, canvas)');
});

test('animateControls does not double-render when the piece drives its own loop', function () use ($runtime) {
    assert_contains($runtime, 'pieceDrivesOwnRender = true');
    assert_contains($runtime, 'if (!pieceDrivesOwnRender) {');
});

test('a dead piece render loop hands rendering back to the bootstrap (no permanent freeze)', function () use ($runtime) {
    assert_contains($runtime, 'pieceDrivesOwnRender = false');
});

echo "\n=== PHP views load the shared runtime (not an inline copy) ===\n";

foreach ([
    'piece-render.php' => __DIR__ . '/../public/app/helpers/piece-render.php',
    'form.php' => __DIR__ . '/../public/app/views/admin/pieces/form.php',
    'generate-preview.php' => __DIR__ . '/../public/app/views/admin/pieces/generate-preview.php',
] as $label => $path) {
    $src = file_get_contents($path);
    test("{$label} references /assets/js/piece-runtime.js", function () use ($src) {
        assert_contains($src, '/assets/js/piece-runtime.js');
    });
    test("{$label} does not inline its own bootThree copy", function () use ($src) {
        assert_not_contains($src, 'async function bootThree');
        assert_not_contains($src, 'async bootThree');
    });
    test("{$label} loads the runtime from the actual request host, not window.location.origin or seo_origin()", function () use ($src) {
        // Regression guard #1: a root-absolute <script src="/assets/..."> tag
        // gets silently redirected by the document's <base href> to the
        // site's configured canonical/production domain on any host that
        // isn't that exact domain — breaking every engine everywhere this
        // runtime is loaded.
        assert_not_contains($src, 'src="/assets/js/piece-runtime.js"');
        // Regression guard #2: this document is frequently embedded via
        // <iframe srcdoc> (piece_render_iframe()), and srcdoc documents get
        // an opaque origin — window.location.origin literally evaluates to
        // the string "null" in that context, even with
        // sandbox="allow-same-origin". Must use a value computed server-side
        // from the actual request ($_SERVER['HTTP_HOST']), not seo_origin()
        // (the configured canonical URL, which can differ from the actual
        // host) and not window.location.origin.
        assert_not_contains($src, '= window.location.origin');
        assert_not_contains($src, 's.src=window.location.origin');
        assert_contains($src, "HTTP_HOST");
    });
}

echo "\n=== immersive-gallery.js (separate implementation, same contract) ===\n";

$immersive = file_get_contents(__DIR__ . '/../public/assets/js/immersive-gallery.js');

test('mountThreeImmersivePiece startFrame passes frameCount, not elapsed/delta time', function () use ($immersive) {
    assert_contains($immersive, 'handler(frameCount)');
    assert_not_contains($immersive, 'handler(frameCount, ');
});

test('mountThreeImmersivePiece does not double-render when the piece drives its own loop', function () use ($immersive) {
    assert_contains($immersive, 'pieceDrivesOwnRender = true');
    assert_contains($immersive, 'if (!pieceDrivesOwnRender || userHasInteracted) {');
});

test('mountThreeImmersivePiece hands rendering back to the bootstrap if the piece loop dies', function () use ($immersive) {
    assert_contains($immersive, 'pieceDrivesOwnRender = false');
});

test('mountThreeImmersivePiece has consistent fallback lighting names', function () use ($immersive) {
    assert_contains($immersive, '__viewer_fallback_ambient__');
    assert_contains($immersive, '__viewer_fallback_dir__');
});

test('user-driven camera control overrides a piece\'s own scripted camera once the user has interacted', function () use ($immersive) {
    // Regression guard: pieces that script their own camera every frame
    // (a normal thing for ambient generative-art motion) always win over
    // drag/keyboard input, because only the piece's own render call paints
    // pixels — without this latch, the camera state updates correctly but
    // the user never sees it, which looks exactly like "interaction is
    // broken" (confirmed by direct framebuffer readback during investigation).
    assert_contains($immersive, 'userHasInteracted = true');
});

test('wheel-zoom saves orbit state so the next frame does not snap back to the pre-zoom camera', function () use ($immersive) {
    // Regression guard: OrbitControls' own wheel/dolly handling moves the
    // camera without dispatching start/end, so saveOrbitState() never runs
    // for zoom unless we intercept the wheel event ourselves. Without this,
    // animateControls()'s "snap back to last saved state when not
    // orbit-active" logic reverts every zoom on the very next frame. This
    // exact handler was added once (commit 151cb9a), then silently deleted
    // by an unrelated refactor (commit 6a838d0) — guard against that
    // happening again.
    assert_contains($immersive, 'function onThreeWheel');
    assert_contains($immersive, 'addEventListener("wheel", onThreeWheel');
});

test('createKeyboardNavigation scales movement by elapsed time, not a fixed per-tick step', function () use ($immersive) {
    // Regression guard: a fixed step per animateControls() tick makes
    // navigation speed vary with actual frame rate (device/fullscreen/tab
    // visibility), which is what made navigation feel "unpredictable".
    assert_contains($immersive, 'performance.now()');
    assert_contains($immersive, 'frameScale');
});

echo "\n=== iOS Safari fullscreen protocol (ported from platform/) ===\n";

$embedJs = file_get_contents(__DIR__ . '/../public/embed.js');

test('embed.js wrapper listens for creatr-toggle-fullscreen and promotes itself to document.body', function () use ($embedJs) {
    assert_contains($embedJs, 'creatr-toggle-fullscreen');
    assert_contains($embedJs, 'document.body.appendChild(el)');
});

test('embed.js wrapper fullscreen overlay uses dvh/dvw (Safari address-bar-aware units)', function () use ($embedJs) {
    assert_contains($embedJs, '100dvw');
    assert_contains($embedJs, '100dvh');
});

foreach ([
    'piece.php' => __DIR__ . '/../public/app/views/immersive/piece.php',
    'collection.php' => __DIR__ . '/../public/app/views/immersive/collection.php',
] as $label => $path) {
    $src = file_get_contents($path);
    test("{$label} fullscreen CSS uses synced viewport vars, not bare 100vh/100vw", function () use ($src) {
        assert_contains($src, '--immersive-viewport-height, 100dvh');
        assert_contains($src, '--immersive-viewport-width, 100dvw');
    });
    test("{$label} syncs --immersive-viewport-* from window.visualViewport", function () use ($src) {
        assert_contains($src, 'syncImmersiveViewportVars');
        assert_contains($src, 'visualViewport');
    });
    test("{$label} detects iPhone Safari and asks the wrapper to promote on fullscreen fallback", function () use ($src) {
        assert_contains($src, 'isIPhoneWebKitBrowser');
        assert_contains($src, 'creatr-toggle-fullscreen');
    });
    test("{$label} fullscreen button clears the iPhone notch/home-indicator via safe-area insets", function () use ($src) {
        assert_contains($src, 'env(safe-area-inset-bottom)');
        assert_contains($src, 'env(safe-area-inset-right)');
    });
}

echo "\n=== Results ===\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}
echo "All runtime consistency checks passed!\n";
