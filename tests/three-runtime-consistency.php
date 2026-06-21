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
    assert_contains($runtime, 'if (!pieceDrivesOwnRender || userHasInteracted) {');
});

test('a dead piece render loop hands rendering back to the bootstrap (no permanent freeze)', function () use ($runtime) {
    assert_contains($runtime, 'pieceDrivesOwnRender = false');
});

test('user-driven camera control overrides a piece\'s own scripted camera once the user has interacted (plain /pieces/{id} page, same fix as immersive-gallery.js)', function () use ($runtime) {
    // Regression guard: piece-runtime.js is a SEPARATE implementation from
    // immersive-gallery.js (used by the plain /pieces/{id} page, not just
    // VR mode). Without this latch, any piece that scripts its own camera
    // every frame (a normal ambient-motion pattern) permanently defeats
    // OrbitControls drag/zoom on the plain page even though it works fine
    // in VR mode — confirmed for piece 40 vs piece 75.
    assert_contains($runtime, "controls.addEventListener('start'");
    assert_contains($runtime, 'userHasInteracted = true');
});

test('Three.js/OrbitControls load from absolute https:// CDN URLs, not relative/bare-specifier imports (Safari sandboxed-srcdoc compatibility)', function () use ($runtime) {
    // Regression guard: Safari blocks relative dynamic module imports
    // inside sandboxed <iframe srcdoc> documents — this is why Three.js
    // pieces rendered blank and non-interactive on mobile Safari until this
    // was fixed. The fix is specifically importing from an ABSOLUTE
    // https:// URL; reverting to a relative path or a bare specifier like
    // import('three') would silently reintroduce the blank-on-iPhone bug.
    assert_contains($runtime, "await import('https://cdn.jsdelivr.net/npm/three@");
    assert_contains($runtime, "await import('https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/controls/OrbitControls.js')");
});

echo "\n=== PHP views load the shared runtime (not an inline copy) ===\n";

foreach ([
    'piece-render.php' => __DIR__ . '/../public/app/helpers/piece-render.php',
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

foreach ([
    'form.php' => __DIR__ . '/../public/app/views/admin/pieces/form.php',
    'generate-preview.php' => __DIR__ . '/../public/app/views/admin/pieces/generate-preview.php',
] as $label => $path) {
    $src = file_get_contents($path);
    test("{$label} delegates admin preview/capture rendering through admin-piece-capture.js", function () use ($src) {
        assert_contains($src, '/assets/js/admin-piece-capture.js');
        assert_contains($src, 'CreatrPieceCapture.renderDocument');
        assert_not_contains($src, 'async function bootThree');
    });
    test("{$label} still passes the actual request host to srcdoc rendering", function () use ($src) {
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

echo "\n=== thumbnail capture readiness signal (P5/C2/Three) ===\n";

$captureModule = file_get_contents(__DIR__ . '/../public/assets/js/admin-piece-capture.js');

test('bootP5 waits for instance.frameCount before signaling ready, not right after setup', function () use ($runtime) {
    // Regression guard: createCanvas() runs synchronously inside setup(),
    // before draw() ever paints anything — signaling readiness right after
    // setup (instead of after a real draw call) is what produced piece 80's
    // blank P5.js thumbnail despite the piece itself rendering fine live.
    assert_contains($runtime, 'instance.frameCount >= 1');
});

test('bootCanvasRuntime (C2/generic) signals ready only after its first real startFrame tick', function () use ($runtime) {
    assert_contains($runtime, 'function instrumentedStartFrame(callback)');
    assert_contains($runtime, 'readySignaled = true; signalCanvasReady(canvas);');
});

test('bootThree signals ready from an actual render call, not unconditionally right after setup', function () use ($runtime) {
    // Regression guard: the old unconditional postMessage right after
    // window.sketch() returns fired before any real frame was guaranteed to
    // exist — same class of bug as P5/C2, just usually masked on desktop by
    // generous fixed timeouts elsewhere. Capture callers now wait for this
    // same signal instead of guessing a big-enough delay. signalCanvasReady()
    // itself still posts the message (legitimately, once) — the guard here
    // is that bootThree() only triggers it via signalThreeReadyOnce(), from
    // an actual render path, not as an unconditional line at setup's end.
    assert_contains($runtime, 'function signalThreeReadyOnce()');
    $count = substr_count($runtime, 'signalThreeReadyOnce();');
    if ($count < 3) {
        throw new RuntimeException("Expected signalThreeReadyOnce() called from all 3 sites (startFrame tick, animateControls, end-of-function fallback), found {$count}");
    }
});

test('shared admin capture module waits for the runtime ready marker before using canvas pixels', function () use ($captureModule) {
    assert_contains($captureModule, "dataset.creatrReady === '1'");
    assert_contains($captureModule, "engine === 'p5'");
    assert_contains($captureModule, "engine === 'c2'");
    assert_contains($captureModule, "engine === 'three'");
});

test('shared admin capture module uses the Three.js 6000ms head-start and 40 x 500ms poll window', function () use ($captureModule) {
    assert_contains($captureModule, "engine === 'three' ? 6000 : 500");
    assert_contains($captureModule, 'attempt < maxAttempts');
    assert_contains($captureModule, 'await wait(500)');
});

test('shared admin capture module keeps capture iframes in the viewport', function () use ($captureModule) {
    assert_not_contains($captureModule, 'left:-9999px');
    assert_contains($captureModule, 'position:fixed;left:0;top:0');
    assert_contains($captureModule, 'opacity:0');
});

test('shared admin capture module supports SVG conversion and deterministic capture seeds', function () use ($captureModule) {
    assert_contains($captureModule, 'function convertSvgToCanvas');
    assert_contains($captureModule, 'Math.random = function()');
    assert_contains($captureModule, 'diffImages');
});

foreach ([
    'form.php' => __DIR__ . '/../public/app/views/admin/pieces/form.php',
    'index.php' => __DIR__ . '/../public/app/views/admin/pieces/index.php',
    'generate-preview.php' => __DIR__ . '/../public/app/views/admin/pieces/generate-preview.php',
] as $label => $path) {
    $src = file_get_contents($path);
    test("{$label} loads and calls the shared admin capture module", function () use ($src) {
        assert_contains($src, '/assets/js/admin-piece-capture.js');
        assert_contains($src, 'CreatrPieceCapture');
    });
    test("{$label} does not carry a duplicated capture runtime bootstrap", function () use ($src) {
        assert_not_contains($src, 'function convertSvgToCanvas');
        assert_not_contains($src, 'async function bootThree');
    });
}

$generatePreview = file_get_contents(__DIR__ . '/../public/app/views/admin/pieces/generate-preview.php');
test('generate-preview no longer uses old single-delay direct canvas capture', function () use ($generatePreview) {
    assert_not_contains($generatePreview, "engine === 'three' ? 3500 : 2000");
    assert_not_contains($generatePreview, 'setTimeout(function () { if (!captured) capture(); }, 10000)');
    assert_contains($generatePreview, 'Save Without Thumbnail');
});

$formView = file_get_contents(__DIR__ . '/../public/app/views/admin/pieces/form.php');
test('AI Refine render gate shows before/after snapshots and low-delta Accept Anyway path', function () use ($formView) {
    assert_contains($formView, 'ai-visual-container');
    assert_contains($formView, 'Before refine');
    assert_contains($formView, 'After refine');
    assert_contains($formView, 'Rendered result appears nearly unchanged');
    assert_contains($formView, 'Accept Anyway');
    assert_contains($formView, 'Request Stronger Change');
});

test('Accepting AI Refine resets dirty baselines so a later Update is not a duplicate version', function () use ($formView) {
    assert_contains($formView, 'function resetDirtyBaselines()');
    assert_contains($formView, 'resetDirtyBaselines();');
});

$controller = file_get_contents(__DIR__ . '/../public/app/Controllers/Admin/PiecesAdminController.php');
test('refineSave JSON includes version_id for changed and unchanged saves', function () use ($controller) {
    assert_contains($controller, "'version_id' => (int) \$currentVersion['id']");
    assert_contains($controller, "'version_id' => \$versionId");
});

echo "\n=== CreatrImmersiveImage embedded Expand button (iOS Safari) ===\n";

test('CreatrImmersiveImage installs the same fullscreen wrapper protocol as CreatrExhibitWall', function () use ($embedJs) {
    // Regression guard: CreatrImmersiveImage embeds the full
    // /immersive/images/{ref} page directly (same as CreatrExhibitWall),
    // so its Expand button posts the same creatr-toggle-fullscreen message
    // on iPhone Safari — but the wrapper-side listener was never installed,
    // so clicking Expand inside an embedded immersive image silently did
    // nothing useful.
    $classBody = substr($embedJs, strpos($embedJs, 'class CreatrImmersiveImage'));
    $classBody = substr($classBody, 0, strpos($classBody, 'class CreatrExhibitWall'));
    assert_contains($classBody, 'installFullscreenWrapperProtocol(this)');
    assert_contains($classBody, ':host(.creatr-fullscreen)');
});

echo "\n=== Results ===\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}
echo "All runtime consistency checks passed!\n";
