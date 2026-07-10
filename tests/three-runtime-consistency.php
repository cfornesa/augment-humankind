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

test('A-Frame bootstrap relies on a pre-runtime WebGL context shim instead of renderer attributes', function () use ($runtime) {
    assert_contains($runtime, "function bootAFrame()");
    assert_not_contains($runtime, "existingScene.setAttribute('renderer'");
});

test('OrbitControls loop creates OrbitControls bound to state.camera/canvas', function () use ($runtime) {
    assert_contains($runtime, 'new OrbitControls(state.camera, canvas)');
});

test('regular piece runtime uses elapsed-time-scaled keyboard navigation instead of OrbitControls key panning', function () use ($runtime) {
    assert_contains($runtime, 'function createKeyboardNavigation');
    assert_contains($runtime, 'const TARGET_FRAME_MS = 1000 / 60;');
    assert_contains($runtime, 'if (keyNav?.update())');
    assert_not_contains($runtime, 'controls.listenToKeyEvents(window);');
});

test('regular piece runtime restores click-to-move teleport for Three.js without immersive chrome', function () use ($runtime) {
    assert_contains($runtime, 'function moveThreeOrbitTo(hitPoint)');
    assert_contains($runtime, 'threeRaycaster.setFromCamera(');
    assert_contains($runtime, 'animToTarget = animFromTarget.clone().add(shift)');
    assert_contains($runtime, "canvas.addEventListener('pointerup', onThreePointerUp)");
    assert_not_contains($runtime, 'immersive-zoom-slider');
});

test('regular piece runtime restores click-to-move teleport for A-Frame pieces', function () use ($runtime) {
    assert_contains($runtime, 'function moveAFrameViewTo(hitPoint)');
    assert_contains($runtime, 'raycaster.intersectObjects(scene.object3D?.children || [], true)');
    assert_contains($runtime, "pointerTarget.addEventListener('pointerup', onAFramePointerUp)");
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

test('regular piece runtime keyboard navigation uses only Arrow keys, never WASD', function () use ($runtime) {
    // WASD is reserved for the piano-key sound input; camera movement must
    // not respond to those keys.
    assert_contains($runtime, "function mapMovementKey(event)");
    $mapFn = substr($runtime, strpos($runtime, "function mapMovementKey(event)"));
    $mapFn = substr($mapFn, 0, strpos($mapFn, "function shouldIgnoreKeyEventTarget"));
    assert_contains($mapFn, "if (event.key === 'ArrowLeft' || event.key === 'ArrowRight' || event.key === 'ArrowUp' || event.key === 'ArrowDown') return event.key;");
    assert_not_contains($mapFn, "KeyW");
    assert_not_contains($mapFn, "KeyA");
    assert_not_contains($mapFn, "KeyS");
    assert_not_contains($mapFn, "KeyD");
});

test('regular piece runtime disables A-Frame WASD after the A-Frame script loads', function () use ($runtime) {
    assert_contains($runtime, 'function disableAFrameWASD()');
    assert_contains($runtime, "window.AFRAME.components['wasd-controls']");
    assert_contains($runtime, "if (e.code === 'KeyW' || e.code === 'KeyA' || e.code === 'KeyS' || e.code === 'KeyD') return;");
    // The safeguard must run after A-Frame is actually available, not just
    // at module parse time, because bootAFrame loads aframe.min.js dynamically.
    $bootAFrame = substr($runtime, strpos($runtime, 'function bootAFrame()'));
    $bootAFrame = substr($bootAFrame, 0, strpos($bootAFrame, 'let pointerTarget = null'));
    assert_contains($bootAFrame, 'script.onload = () => {');
    assert_contains($bootAFrame, 'disableAFrameWASD();');
});

test('Three.js/OrbitControls load from absolute https:// CDN URLs, not relative/bare-specifier imports (Safari sandboxed-srcdoc compatibility)', function () use ($runtime) {
    // Regression guard: Safari blocks relative dynamic module imports
    // inside sandboxed <iframe srcdoc> documents — this is why Three.js
    // pieces rendered blank and non-interactive on mobile Safari until this
    // was fixed. The fix is specifically importing from an ABSOLUTE
    // https:// URL; reverting to a relative path or a bare specifier like
    // import('three') would silently reintroduce the blank-on-iPhone bug.
    assert_contains($runtime, "import('https://cdn.jsdelivr.net/npm/three@");
    assert_contains($runtime, "import('https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/controls/OrbitControls.js')");
});

test('regular export bootstrap mirrors the live regular-view Three.js movement contract', function () {
    $render = file_get_contents(__DIR__ . '/../public/app/helpers/piece-render.php');
    assert_contains($render, 'function updateKeyboardNavigation()');
    assert_contains($render, 'const TARGET_FRAME_MS = 1000 / 60;');
    assert_contains($render, 'function moveThreeOrbitTo(hitPoint)');
    assert_contains($render, "canvas.addEventListener('pointerup', onThreePointerUp)");
    assert_not_contains($render, "controls.listenToKeyEvents(window);");
});

test('regular export bootstrap mirrors the live regular-view A-Frame teleport contract', function () {
    $render = file_get_contents(__DIR__ . '/../public/app/helpers/piece-render.php');
    assert_contains($render, "scene.addEventListener('renderstart', bindAFramePointerControls, { once: true });");
    assert_contains($render, 'function moveAFrameViewTo(hitPoint)');
    assert_contains($render, 'raycaster.intersectObjects(scene.object3D?.children || [], true)');
});

test('regular export bootstrap disables A-Frame WASD in both CDN and bundle modes', function () {
    $render = file_get_contents(__DIR__ . '/../public/app/helpers/piece-render.php');
    // CDN imports: inline shim immediately after the aframe.min.js script tag.
    assert_contains($render, 'if (window.AFRAME && window.AFRAME.components["wasd-controls"]) {');
    // Bundle inline runtime: shim appended to the vendored A-Frame source.
    assert_contains($render, "if (window.AFRAME && window.AFRAME.components['wasd-controls']) {");
    assert_contains($render, 'if (e.code === "KeyW" || e.code === "KeyA" || e.code === "KeyS" || e.code === "KeyD") return;');
    assert_contains($render, "if (e.code === 'KeyW' || e.code === 'KeyA' || e.code === 'KeyS' || e.code === 'KeyD') return;");
});

echo "\n=== regular-view sound (Three.js/A-Frame only, muted by default) ===\n";

test('mountExhibitWall sonifies only the camera-focused item, rebinding as focus changes', function () {
    $immersiveSrc = file_get_contents(__DIR__ . '/../public/assets/js/immersive-gallery.js');
    assert_contains($immersiveSrc, 'function computeFocusedSlotIndex');
    assert_contains($immersiveSrc, 'if (focusedIndex !== audioControllerIndex');
    assert_contains($immersiveSrc, 'audioController = createAudioController(focusedItem?.sonicParams, stageEl, { attachListener: false, allowHandTracking: false, pieceId: focusedItem?.piece_id })');
    assert_contains($immersiveSrc, 'wallSoundToggleBtn.addEventListener("click", onWallSoundToggleClick)');
    // Old controller must be disposed on every rebind, and again on wall
    // teardown, or a stale synth instance leaks.
    assert_contains($immersiveSrc, 'audioController?.dispose();');
});

test('ambient voice ticks continuously and is never gated behind a stillness timer', function () {
    // Regression guard: ambientStep() previously suppressed all ambient
    // notes for 2s of continued motion plus a 2s IDLE_GAP_MS cooldown after
    // motion stopped. Both lastMotionAt and IDLE_GAP_MS were removed
    // entirely so ambient always ticks on its own minInterval cadence,
    // layered with (never suppressed by) the movement voice.
    $sonicSrc = file_get_contents(__DIR__ . '/../public/assets/js/sonic-controller.js');
    assert_not_contains($sonicSrc, 'lastMotionAt');
    assert_not_contains($sonicSrc, 'IDLE_GAP_MS');
    assert_contains($sonicSrc, 'if (now - lastIdleNoteAt >= minInterval) {');
});

test('collection views/exports pass each item\'s own sonicParams and gate the sound toggle on any item having one', function () {
    $collectionView = file_get_contents(__DIR__ . '/../public/app/views/immersive/collection.php');
    assert_contains($collectionView, '$sonicParamsDecoded = !empty($version[\'sonic_params\'])');
    assert_contains($collectionView, "'sound_action' => \$hasAnySonic ? ['enabled' => true] : null");

    $render = file_get_contents(__DIR__ . '/../public/app/helpers/piece-render.php');
    assert_contains($render, "collection_export_piece_item_payload");
    assert_contains($render, "sonicParamsDecoded");
});

test('piece-runtime.js wires the audio controller into both Three.js and A-Frame boot paths', function () use ($runtime) {
    assert_contains($runtime, 'function createPieceRuntimeAudioController(sonicParams, getMover)');
    assert_contains($runtime, 'pieceAudioController = createPieceRuntimeAudioController(PIECE_SONIC, () => state.camera)');
    assert_contains($runtime, 'pieceAudioController = createPieceRuntimeAudioController(PIECE_SONIC, () => getAFrameCameraMover())');
});

test('piece-runtime.js audio controller does not assume a self-created toggle button exists', function () use ($runtime) {
    // Regression guard: when a host page (e.g. pieces/show.php) owns the
    // sound button and posts the toggle message, toggleBtn is null inside
    // this iframe — an unguarded toggleBtn.disabled write throws and aborts
    // the Tone.js load before it starts.
    assert_not_contains($runtime, '  toggleBtn.disabled = true;');
    assert_contains($runtime, 'if (toggleBtn) toggleBtn.disabled = true;');
});

test('piece_render_document injects sonic_params into the iframe context', function () {
    $render = file_get_contents(__DIR__ . '/../public/app/helpers/piece-render.php');
    assert_contains($render, "'sonic' => !empty(\$version['sonic_params'])");
    assert_contains($render, "(\$sonicDecoded['enabled'] ?? true) !== false");
});

test('pieces/show.php renders a gated sound toggle and posts creatr-sound-toggle to the iframe', function () {
    $show = file_get_contents(__DIR__ . '/../public/app/views/pieces/show.php');
    assert_contains($show, 'data-piece-sound-toggle');
    assert_contains($show, '$soundToggleAvailable = is_array($version)');

    $fullscreenScript = file_get_contents(__DIR__ . '/../public/assets/js/piece-fullscreen.js');
    assert_contains($fullscreenScript, "type: 'creatr-sound-toggle'");
    assert_contains($fullscreenScript, "creatr-sound-state");
});

test('piece_export_document (non-immersive export) inlines a self-contained sound controller', function () {
    $render = file_get_contents(__DIR__ . '/../public/app/helpers/piece-render.php');
    assert_contains($render, 'function piece_export_sonic_script(string $engine, string $sonicParamsJson, string $runtimeMode, int $pieceId = 0): string');
    assert_contains($render, "trim(\$sonicParamsJson) === ''");
    // Bundle mode must load Tone.js from the ZIP, not from a blob:null script URL.
    assert_contains($render, "'runtime/tone/Tone.js'");
    assert_not_contains($render, '__creatrToneInlineSource');
    assert_contains($render, '__creatrSonicSetMover');
});

test('immersive export bundles Tone.js and wires sonicParams through to the mount* calls', function () {
    $render = file_get_contents(__DIR__ . '/../public/app/helpers/piece-render.php');
    assert_contains($render, "'source_path' => \$publicRoot . '/assets/vendor/tone/Tone.js',");
    assert_contains($render, "'zip_path' => 'runtime/tone/Tone.js',");
    assert_contains($render, "'s.src = \"/assets/vendor/tone/Tone.js\";' => 's.src = \"runtime/tone/Tone.js\";'");
    assert_contains($render, 'sonicParams: piece.sonicParams');
    assert_contains($render, "'sound_action' => \$hasSonic ? ['enabled' => true] : null");
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

test('createAudioController finds the sound toggle from the document, not scoped to stageEl', function () use ($immersive) {
    // Regression guard: immersive_stage_toolbar_markup() renders the toggle
    // as a SIBLING of #immersive-stage (both children of .stage-wrapper), not
    // a descendant — stageEl.querySelector(...) can never find it, silently
    // leaving the button unwired (no click listener attached at all, no
    // error either) in the live view, single-piece export, and collection
    // export alike.
    assert_not_contains($immersive, 'stageEl?.querySelector?.("[data-immersive-sound-toggle]")');
    assert_contains($immersive, 'document.querySelector("[data-immersive-sound-toggle]")');
});

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

test('Three.js viewer zoom slider uses the same saved orbit state path as wheel zoom', function () use ($immersive) {
    assert_contains($immersive, 'function applyThreeZoom');
    assert_contains($immersive, 'function applyThreeZoomValue');
    assert_contains($immersive, 'onZoomSliderInput: (value) => applyThreeZoomValue(value)');
    assert_contains($immersive, 'className = "immersive-zoom-slider"');
    assert_contains($immersive, 'className = "immersive-zoom-icon"');
    assert_contains($immersive, 'className = "immersive-zoom-slider-slot"');
    assert_contains($immersive, 'right:calc(1rem + env(safe-area-inset-right));top:50%;width:2.75rem');
    assert_contains($immersive, 'saveOrbitState();');
});

test('immersive viewer controls are optional and gated by the piece view', function () use ($immersive) {
    $pieceView = file_get_contents(__DIR__ . '/../public/app/views/immersive/piece.php');
    assert_contains($immersive, 'function createImmersiveViewerControls');
    assert_contains($immersive, 'options.showViewerControls');
    assert_contains($immersive, 'immersive-edge-hud-left');
    assert_contains($immersive, 'immersive-edge-hud-right');
    assert_contains($immersive, 'setInterval(onClick, 90)');
    assert_contains($immersive, 'touch-action:none');
    assert_contains($pieceView, 'showViewerControls: <?= (!$isEmbedMode && !$isStaticEmbed)');
});

test('immersive downloads serialize viewer state and expose collection slideshow downloads', function () use ($immersive) {
    $pieceView = file_get_contents(__DIR__ . '/../public/app/views/immersive/piece.php');
    $collectionView = file_get_contents(__DIR__ . '/../public/app/views/immersive/collection.php');
    $collectionController = file_get_contents(__DIR__ . '/../public/app/controllers/CollectionsController.php');
    $router = file_get_contents(__DIR__ . '/../public/app/router.php');
    assert_contains($immersive, 'function encodeViewState');
    assert_contains($immersive, 'getViewState: () => shellViewState');
    assert_contains($immersive, 'onActiveItemChange');
    assert_contains($immersive, 'download_url');
    assert_contains($pieceView, 'data-immersive-download-piece');
    assert_contains($pieceView, "url.searchParams.set('surface', 'immersive')");
    assert_contains($collectionView, 'data-collection-download-piece');
    assert_contains($collectionView, 'data-collection-download-png');
    assert_contains($collectionView, "'download_url' => \$pieceDownloadUrl");
    assert_contains($collectionView, "'download_url' => \$collectionDownloadUrl");
    assert_contains($collectionView, 'collectionGalleryDownloadUrl');
    assert_contains($collectionController, 'collection_export_bundle');
    assert_contains($router, "/collections/([a-z0-9-]+)/download");
});

test('immersive bundle export patches renderer runtime URLs to local bundle paths', function () {
    $render = file_get_contents(__DIR__ . '/../public/app/helpers/piece-render.php');
    assert_contains($render, 'function piece_export_immersive_runtime_files');
    assert_contains($render, 'function piece_export_patched_orbitcontrols_source');
    assert_contains($render, 'runtime/immersive-gallery.js');
    assert_contains($render, "import * as THREE from './three/three.module.js';");
    assert_contains($render, "from '../../three.module.js';");
    assert_contains($render, 'runtime/aframe/aframe.min.js');
    assert_contains($render, 'runtime/p5/p5.min.js');
    assert_contains($render, 'runtime/c2/c2.min.js');
});

test('immersive gallery-room piece PNG capture uses the rendered gallery canvas', function () use ($immersive) {
    $mountGalleryPiece = substr($immersive, strpos($immersive, 'export function mountGalleryPiece'));
    $mountGalleryPiece = substr($mountGalleryPiece, 0, strpos($mountGalleryPiece, '// Standalone image gallery mounting helper'));
    assert_contains($mountGalleryPiece, 'canvas: shell.renderer.domElement');
    assert_contains($mountGalleryPiece, 'shell.renderer.render(shell.scene, shell.camera)');
});

test('tap/click movement preserves Three.js camera distance by translating camera and target together', function () use ($immersive) {
    assert_contains($immersive, 'threeAnimToTarget = threeAnimFromTarget.clone().add(shift)');
    assert_contains($immersive, 'threeAnimToCam = threeAnimFromCam.clone().add(shift)');
});

test('Three.js viewer direction buttons translate camera and target together', function () use ($immersive) {
    assert_contains($immersive, 'function applyThreeDirectionalMove');
    assert_contains($immersive, 'function applyThreeFloatMove');
    assert_contains($immersive, 'onMoveForward: () => applyThreeDirectionalMove(1, 0)');
    assert_contains($immersive, 'onMoveBackward: () => applyThreeDirectionalMove(-1, 0)');
    assert_contains($immersive, 'onMoveLeft: () => applyThreeDirectionalMove(0, -1)');
    assert_contains($immersive, 'onMoveRight: () => applyThreeDirectionalMove(0, 1)');
    assert_contains($immersive, 'onFloatUp: () => applyThreeFloatMove(1)');
    assert_contains($immersive, 'onFloatDown: () => applyThreeFloatMove(-1)');
    assert_contains($immersive, 'controls.target.x += dx');
    assert_contains($immersive, 'controls.target.z += dz');
    assert_contains($immersive, 'controls.target.y += dy');
});

test('A-Frame immersive pieces expose viewer zoom and tap-to-move controls without new dependencies', function () use ($immersive) {
    assert_contains($immersive, 'export function mountAFrameImmersivePiece(stageEl, code, htmlCode, cssCode, onError = console.error, options = {})');
    assert_contains($immersive, 'function applyAFrameZoom');
    assert_contains($immersive, 'function applyAFrameZoomSliderValue');
    assert_contains($immersive, 'function applyAFrameDirectionalMove');
    assert_contains($immersive, 'function applyAFrameFloatMove');
    assert_contains($immersive, 'function moveAFrameViewTo');
    assert_contains($immersive, 'onZoomSliderInput: (value) => applyAFrameZoomSliderValue(value)');
    assert_contains($immersive, 'onMoveForward: () => applyAFrameDirectionalMove(1, 0)');
    assert_contains($immersive, 'onMoveBackward: () => applyAFrameDirectionalMove(-1, 0)');
    assert_contains($immersive, 'onMoveLeft: () => applyAFrameDirectionalMove(0, -1)');
    assert_contains($immersive, 'onMoveRight: () => applyAFrameDirectionalMove(0, 1)');
    assert_contains($immersive, 'onFloatUp: () => applyAFrameFloatMove(1)');
    assert_contains($immersive, 'onFloatDown: () => applyAFrameFloatMove(-1)');
    assert_not_contains($immersive, 'import("/assets/js/aframe');
});

test('immersive gallery keyboard navigation uses only Arrow keys, never WASD', function () use ($immersive) {
    assert_contains($immersive, 'function mapMovementKey(e)');
    $mapFn = substr($immersive, strpos($immersive, 'function mapMovementKey(e)'));
    $mapFn = substr($mapFn, 0, strpos($mapFn, 'function shouldIgnoreKeyEventTarget'));
    assert_contains($mapFn, 'if (e.key === "ArrowLeft" || e.key === "ArrowRight" || e.key === "ArrowUp" || e.key === "ArrowDown") return e.key;');
    assert_not_contains($mapFn, 'KeyW');
    assert_not_contains($mapFn, 'KeyA');
    assert_not_contains($mapFn, 'KeyS');
    assert_not_contains($mapFn, 'KeyD');
});

test('immersive gallery disables A-Frame WASD after the A-Frame runtime loads', function () use ($immersive) {
    assert_contains($immersive, 'function disableAFrameWASD()');
    assert_contains($immersive, 'window.AFRAME.components["wasd-controls"]');
    assert_contains($immersive, 'if (e.code === "KeyW" || e.code === "KeyA" || e.code === "KeyS" || e.code === "KeyD") return;');
    $loadAFrame = substr($immersive, strpos($immersive, 'function loadAFrameRuntime()'));
    $loadAFrame = substr($loadAFrame, 0, strpos($loadAFrame, 'let p5RuntimePromise'));
    assert_contains($loadAFrame, 'script.onload = () => {');
    assert_contains($loadAFrame, 'disableAFrameWASD();');
    assert_contains($loadAFrame, 'if (window.AFRAME) {');
    assert_contains($loadAFrame, 'disableAFrameWASD();');
});

test('full-view overlay PNG capture uses strict validation only for A-Frame iframe slides, not every exported canvas slide', function () use ($immersive) {
    assert_contains($immersive, 'const iframeDoc = iframe.contentDocument;');
    assert_contains($immersive, 'const exportCapture = iframe.contentWindow?.__creatrExportCapture;');
    assert_contains($immersive, 'exportCapture && typeof exportCapture.getSurface === "function"');
    assert_contains($immersive, 'await exportCapture.getSurface()');
    assert_contains($immersive, 'iframeDoc.querySelector("a-scene#scene, a-scene")');
    assert_contains($immersive, 'exportCapture.requiresCanvasValidation === true');
    assert_contains($immersive, 'dl.exportCanvasWithValidation(iframeDoc, surface.node)');
    assert_contains($immersive, 'dl.exportCanvas(surface.node)');
});

test('exported slideshow slides expose a narrow capture hook without depending on live creatrReady markers', function () {
    $render = file_get_contents(__DIR__ . '/../public/app/helpers/piece-render.php');
    assert_contains($render, 'window.__creatrExportCapture = {');
    assert_contains($render, 'getSurface: getCaptureSurface');
    assert_contains($render, 'requiresCanvasValidation: generationMode === \'aframe\'');
    assert_contains($render, 'captureCanvas: async function () {');
    assert_contains($render, 'if (supportsScreenshot && button) {');
    assert_contains($render, "return { type: 'svg', node: svgs[0] };");
    assert_not_contains($render, 'if (!supportsScreenshot || !button) return;');
    assert_not_contains($render, "dataset.creatrReady === '1'");
    assert_not_contains($render, "dataset.creatrSettled === '1'");
});

test('downloaded immersive exports ship the local CreatrPieceDownload bridge before overlay runtime bootstrap', function () {
    $render = file_get_contents(__DIR__ . '/../public/app/helpers/piece-render.php');
    assert_contains($render, 'function piece_export_download_bridge_script(): string');
    assert_contains($render, "piece_export_runtime_source_file('assets/js/public-piece-download.js')");
    assert_contains($render, '$downloadBridgeScript = piece_export_download_bridge_script();');
    assert_contains($render, '{$downloadBridgeScript}');
    if (substr_count($render, '{$downloadBridgeScript}') < 2) {
        throw new RuntimeException('Expected both downloaded immersive export entry points to inline the bridge script.');
    }
    if (strpos($render, '{$downloadBridgeScript}') >= strpos($render, '<script src="runtime/immersive-gallery.global.js"></script>')) {
        throw new RuntimeException('Expected the bridge script to load before the immersive runtime bootstrap.');
    }
});

test('A-Frame immersive view suppresses the duplicate built-in VR fullscreen control', function () use ($immersive) {
    assert_contains($immersive, 'scene.setAttribute("vr-mode-ui", "enabled: false")');
    assert_contains($immersive, '.a-enter-vr');
    assert_contains($immersive, 'display: none !important;');
});

test('iPhone Safari enters immersive focus mode without attempting native fullscreen first', function () {
    $pieceView = file_get_contents(__DIR__ . '/../public/app/views/immersive/piece.php');
    assert_contains($pieceView, "if (isIPhoneWebKitBrowser()) {\n            syncFullscreenState(true, { mode: 'focus' });");
    assert_contains($pieceView, "shell.dataset.immersiveMode = options.mode || 'fullscreen'");
    assert_contains($pieceView, "btn.setAttribute('aria-label', 'Return to page')");
    assert_contains($pieceView, 'function lockImmersiveScroll');
    assert_contains($pieceView, 'window.visualViewport?.addEventListener');
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
    test("{$label} renders the shared top stage toolbar (immersive-chrome.php)", function () use ($src) {
        assert_contains($src, 'immersive_stage_toolbar_css()');
        assert_contains($src, 'immersive_stage_toolbar_markup(');
    });
}

test('shared stage toolbar clears the iPhone notch via safe-area insets and anchors to the top', function () {
    $chrome = file_get_contents(__DIR__ . '/../public/app/helpers/immersive-chrome.php');
    assert_contains($chrome, 'env(safe-area-inset-top)');
    assert_contains($chrome, 'env(safe-area-inset-left)');
    assert_contains($chrome, 'env(safe-area-inset-right)');
    assert_contains($chrome, 'top: calc(0.75rem + env(safe-area-inset-top))');
});

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
    assert_contains($runtime, "ready.markRendered('canvas-startFrame-' + count);");
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
    assert_contains($runtime, 'function signalThreeReadyOnce(source)');
    $count = preg_match_all('/signalThreeReadyOnce\(/', $runtime);
    if ($count < 4) {
        throw new RuntimeException("Expected signalThreeReadyOnce(...) called from all 3 sites (startFrame tick, animateControls, end-of-function fallback) plus its own definition, found {$count}");
    }
});

test('shared admin capture module waits for the runtime ready marker before using canvas pixels', function () use ($captureModule) {
    assert_contains($captureModule, "dataset.creatrReady === '1'");
    assert_contains($captureModule, "dataset.creatrManagedMedia === '1'");
    assert_contains($captureModule, "dataset.creatrSettled === '1'");
    assert_contains($captureModule, "engine === 'p5'");
    assert_contains($captureModule, "engine === 'c2'");
    assert_contains($captureModule, "engine === 'three'");
    assert_contains($captureModule, "engine === 'aframe'");
});

test('shared admin capture module uses the Three.js 6000ms head-start and 40 x 500ms poll window', function () use ($captureModule) {
    assert_contains($captureModule, "engine === 'three' || engine === 'aframe'");
    assert_contains($captureModule, '? 6000 : 500');
    assert_contains($captureModule, 'attempt < maxAttempts');
    assert_contains($captureModule, 'await wait(500)');
});

test('shared runtime boots A-Frame from the self-hosted asset and signals readiness after canvas creation', function () use ($runtime) {
    assert_contains($runtime, 'function bootAFrame()');
    assert_contains($runtime, '/assets/js/aframe.min.js');
    assert_contains($runtime, "PIECE_ENGINE === 'aframe'");
    assert_contains($runtime, 'signalAFrameReadyOnce');
    assert_contains($runtime, "ready.markRendered('aframe-renderstart');");
});

test('A-Frame immersive pieces mount as live scenes, not gallery textures', function () use ($immersive) {
    $pieceView = file_get_contents(__DIR__ . '/../public/app/views/immersive/piece.php');
    assert_contains($immersive, 'export function mountAFrameImmersivePiece');
    assert_contains($immersive, '/assets/js/aframe.min.js');
    assert_contains($immersive, '<a-scene id="scene" embedded>');
    assert_contains($pieceView, "engine === 'aframe'");
    assert_contains($pieceView, 'mountAFrameImmersivePiece(stage, code, htmlCode, cssCode, handleRuntimeError, viewerControlsOptions)');
});

test('A-Frame sizing CSS is emitted only on A-Frame render documents', function () use ($captureModule) {
    $renderHelper = file_get_contents(__DIR__ . '/../public/app/helpers/piece-render.php');
    assert_contains($renderHelper, "\$engine === 'aframe'");
    assert_contains($captureModule, "engine === 'aframe'");
    assert_contains($captureModule, "var aframeCss = engine === 'aframe'");
});

test('shared admin capture module keeps capture iframes in the viewport', function () use ($captureModule) {
    assert_not_contains($captureModule, 'left:-9999px');
    assert_contains($captureModule, 'position:fixed;left:0;top:0');
    assert_contains($captureModule, 'opacity:1');
    assert_contains($captureModule, 'z-index:999999');
    assert_contains($captureModule, 'overflow:hidden');
});

test('shared admin capture module supports SVG conversion and deterministic capture seeds', function () use ($captureModule) {
    assert_contains($captureModule, 'function convertSvgToCanvas');
    assert_contains($captureModule, 'Math.random = function()');
    assert_contains($captureModule, 'diffImages');
});

test('shared runtime tracks managed CMS media before signaling settled readiness', function () use ($runtime) {
    assert_contains($runtime, 'const managedMediaState = {');
    assert_contains($runtime, "dataset.creatrManagedMedia = managedMediaState.used ? '1' : '0'");
    assert_contains($runtime, "dataset.creatrManagedMediaState = managedMediaState.used");
    assert_contains($runtime, 'trackedRequests: new Set()');
    assert_contains($runtime, 'function extractCmsMediaUrls(value)');
    assert_contains($runtime, 'function trackBackgroundManagedMedia(root)');
    assert_contains($runtime, "[document.querySelector('canvas'), document.querySelector('svg')].forEach");
    assert_contains($runtime, 'node = node.parentElement;');
    assert_contains($runtime, "window.getComputedStyle(node).backgroundImage");
    assert_contains($runtime, 'function createReadyController(target)');
    assert_contains($runtime, "document.documentElement.dataset.creatrSettled = '1'");
    assert_contains($runtime, "window.Image = class CreatrTrackedImage extends nativeImageCtor");
    assert_contains($runtime, "const previousSrc = typeof element.getAttribute === 'function' ? (element.getAttribute('src') || '') : '';");
    assert_contains($runtime, "const completeNow = tag === 'img' && previousSrc && element.complete === true;");
    assert_contains($runtime, "element.complete === true");
    assert_contains($runtime, "element.naturalWidth || 0");
    assert_contains($runtime, "if (options.surfaceErrors !== false) {");
    assert_contains($runtime, 'const observer = new MutationObserver');
});

test('shared runtime marks p5 and SVG media-backed pieces ready only through the settled-state controller', function () use ($runtime) {
    assert_contains($runtime, "ready.markRendered('p5-frame-' + instance.frameCount);");
    assert_contains($runtime, "ready.markRendered('svg-document');");
    assert_contains($runtime, 'ready.noteInlineMedia(document);');
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

test('Pieces index uses the same shared overlay capture path as save-time thumbnail generation', function () {
    $indexView = file_get_contents(__DIR__ . '/../public/app/views/admin/pieces/index.php');
    assert_contains($indexView, 'CreatrPieceCapture.captureWithOverlay');
    assert_not_contains($indexView, 'function createCaptureOverlay()');
});

test('shared capture module combines stricter readiness with stable-frame acceptance', function () use ($captureModule) {
    assert_contains($captureModule, "function resolveWaitProfile(engine, profile)");
    assert_contains($captureModule, "var attemptProfiles = [opts.waitProfile || 'default']");
    assert_contains($captureModule, "attemptProfiles.push('manual')");
    assert_contains($captureModule, 'Retrying thumbnail capture');
    assert_contains($captureModule, 'function getComputedBackgroundLayers(doc, width, height)');
    assert_contains($captureModule, "[doc.querySelector('canvas'), doc.querySelector('svg')].forEach");
    assert_contains($captureModule, 'async function compositeVisibleSurface(doc, foregroundCanvas, width, height)');
    assert_contains($captureModule, 'await inlineSvgManagedImages(svgClone, svgElement);');
    assert_contains($captureModule, 'function captureStableFrame');
    assert_contains($captureModule, 'async function analyzeFrame(dataUrl)');
    assert_contains($captureModule, 'analysis.blankLike');
    assert_contains($captureModule, 'var totalLumaSquared = 0;');
    assert_contains($captureModule, 'var lumaStdDev = Math.sqrt(lumaVariance);');
    assert_contains($captureModule, 'var flatLowInfo = averageAlpha >= 0.98');
    assert_contains($captureModule, '&& lumaRange <= 6');
    assert_contains($captureModule, '&& lumaStdDev <= 1.5');
    assert_contains($captureModule, '&& nonDarkPixelRatio <= 0.01;');
    assert_contains($captureModule, '|| flatLowInfo;');
    assert_contains($captureModule, 'flatLowInfo: flatLowInfo,');
    assert_contains($captureModule, 'Piece kept producing blank or near-blank frames instead of a usable thumbnail.');
    assert_contains($captureModule, "acceptedBy: 'stable-rendered-frame'");
    assert_contains($captureModule, 'Piece kept rendering, but its thumbnail never converged to a stable frame.');
});

$agents = file_get_contents(__DIR__ . '/../AGENTS.md');
test('AGENTS planning section requires questions before proposing plans', function () use ($agents) {
    assert_contains($agents, 'ask all necessary questions before proposing the');
    assert_contains($agents, 'before requesting, expecting, or relying');
    assert_contains($agents, 'Do not present a proposed plan until those questions');
    assert_contains($agents, 'do not reopen');
});

$generatePreview = file_get_contents(__DIR__ . '/../public/app/views/admin/pieces/generate-preview.php');
test('generate-preview no longer uses old single-delay direct canvas capture', function () use ($generatePreview) {
    assert_not_contains($generatePreview, "engine === 'three' ? 3500 : 2000");
    assert_not_contains($generatePreview, 'setTimeout(function () { if (!captured) capture(); }, 10000)');
    assert_contains($generatePreview, 'Save Without Thumbnail');
    assert_contains($generatePreview, 'Discard &amp; Restart');
    assert_contains($generatePreview, '/admin/pieces/generate?restart=1');
    assert_contains($generatePreview, 'id="profile_id"');
    assert_contains($generatePreview, 'id="persona_id"');
    assert_contains($generatePreview, 'id="regenerate-preview-btn"');
    assert_contains($generatePreview, "/admin/pieces/generate/regenerate");
});

$formView = file_get_contents(__DIR__ . '/../public/app/views/admin/pieces/form.php');
test('AI Refine render gate shows visual toggle and option to request stronger changes', function () use ($formView) {
    assert_contains($formView, 'ai-visual-container');
    assert_contains($formView, 'Show Refined (Live Preview)');
    assert_contains($formView, 'Show Original (Live Preview)');
    assert_contains($formView, 'Currently showing:');
    assert_contains($formView, 'Request Stronger Change');
});

test('Accepting AI Refine resets dirty baselines so a later Update is not a duplicate version', function () use ($formView) {
    assert_contains($formView, 'function resetDirtyBaselines()');
    assert_contains($formView, 'resetDirtyBaselines();');
});

$controller = file_get_contents(__DIR__ . '/../public/app/controllers/Admin/PiecesAdminController.php');
test('refineSave JSON includes version_id for changed and unchanged saves', function () use ($controller) {
    assert_contains($controller, "'version_id' => (int) \$currentVersion['id']");
    assert_contains($controller, "'version_id' => \$versionId");
});

test('preview generation controller preserves restart context and supports preview regenerate', function () use ($controller) {
    assert_contains($controller, "isset(\$_GET['restart']) && \$_GET['restart'] === '1'");
    assert_contains($controller, "public static function generateRegenerate(): void");
    assert_contains($controller, 'Rebuild this piece so it better fulfills the original creative prompt.');
    assert_contains($controller, "private static function updatePendingGenerationCurrent(array \$current): void");
    assert_contains($controller, "private static function clearPendingGeneration(): void");
    assert_contains($controller, "private static function requestedGenerationModeFromPost(string \$engineFallback = 'p5'): string");
    assert_contains($controller, "'generation_mode' => art_piece_normalize_generation_mode(\$generationMode, \$engine)");
});

test('saved versions and immersive surfaces treat persisted generation mode as primary with legacy fallback', function () {
    $versionModel = file_get_contents(__DIR__ . '/../public/app/models/PlatformArtPieceVersion.php');
    $pieceModel = file_get_contents(__DIR__ . '/../public/app/models/PlatformArtPiece.php');
    $immersivePiece = file_get_contents(__DIR__ . '/../public/app/views/immersive/piece.php');
    $immersiveCollection = file_get_contents(__DIR__ . '/../public/app/views/immersive/collection.php');
    assert_contains($versionModel, 'art_piece_version_select_columns(self::hasGenerationModeColumn(), true, true, self::hasSonicParamsColumn())');
    assert_contains($versionModel, 'art_piece_version_storage_columns(self::hasGenerationModeColumn(), self::hasSonicParamsColumn())');
    assert_contains($versionModel, 'if (self::hasGenerationModeColumn())');
    assert_contains($versionModel, "return ah_column_exists('art_piece_versions', 'generation_mode');");
    assert_contains($pieceModel, 'art_piece_version_select_columns(self::versionHasGenerationMode(), false, false, self::versionHasSonicParamsColumn())');
    assert_contains($pieceModel, "return ah_column_exists('art_piece_versions', 'generation_mode');");
    assert_contains($pieceModel, 'public static function searchFilteredByGenerationMode(');
    assert_contains($immersivePiece, '$generationMode = art_piece_version_generation_mode($version, $piece);');
    assert_contains($immersivePiece, '$engineLabel = art_piece_generation_mode_label($generationMode);');
    assert_contains($immersivePiece, '$c2Interactive = $generationMode === \'c2_interactive\';');
    assert_contains($immersiveCollection, '$generationMode = art_piece_version_generation_mode($version, $piece);');
    assert_contains($immersiveCollection, '$itemEngineLabel = art_piece_generation_mode_label($generationMode);');
    assert_contains($immersiveCollection, "in_array(\$generationMode, ['three', 'aframe', 'c2_interactive'], true)");
});

test('admin Pieces UI exposes C2.js Interactive as a first-class label and filter', function () {
    $piecesIndex = file_get_contents(__DIR__ . '/../public/app/views/admin/pieces/index.php');
    $piecesController = file_get_contents(__DIR__ . '/../public/app/controllers/Admin/PiecesAdminController.php');
    assert_contains($piecesIndex, '<option value="c2_interactive"');
    assert_contains($piecesIndex, 'C2.js Interactive');
    assert_contains($piecesIndex, 'art_piece_generation_mode_label($effectiveGenerationMode)');
    assert_contains($piecesController, "array_merge(art_piece_supported_engines(), ['c2_interactive'])");
    assert_contains($piecesController, "if (\$engine === 'c2_interactive')");
});

test('public piece surfaces use effective generation mode labels and filters', function () {
    $piecesController = file_get_contents(__DIR__ . '/../public/app/controllers/PiecesController.php');
    $portfolioController = file_get_contents(__DIR__ . '/../public/app/controllers/PortfolioController.php');
    $piecesIndex = file_get_contents(__DIR__ . '/../public/app/views/pieces/index.php');
    $pieceCard = file_get_contents(__DIR__ . '/../public/app/views/pieces/_piece-card.php');
    $pieceShow = file_get_contents(__DIR__ . '/../public/app/views/pieces/show.php');
    $archiveCards = file_get_contents(__DIR__ . '/../public/app/views/portfolio/archive-cards.php');
    $portfolioCategory = file_get_contents(__DIR__ . '/../public/app/views/portfolio/category.php');
    assert_contains($piecesController, "array_merge(art_piece_supported_engines(), ['c2_interactive'])");
    assert_contains($piecesController, 'PlatformArtPiece::searchFilteredByGenerationMode(');
    assert_contains($portfolioController, "array_merge(art_piece_supported_engines(), ['c2_interactive'])");
    assert_contains($portfolioController, 'PlatformArtPiece::searchFilteredByGenerationMode(');
    assert_contains($piecesIndex, "'c2_interactive' => 'C2.js Interactive'");
    assert_contains($pieceCard, 'art_piece_effective_generation_mode_label($piece)');
    assert_contains($pieceShow, 'art_piece_effective_generation_mode_label($piece, is_array($version) ? $version : null)');
    assert_contains($archiveCards, 'art_piece_effective_generation_mode_label($item)');
    assert_contains($portfolioCategory, 'art_piece_effective_generation_mode_label($piece)');
});

test('AI generation and regenerate enforce selected generation mode during validation', function () {
    $controller = file_get_contents(__DIR__ . '/../public/app/controllers/Admin/PiecesAdminController.php');
    $helper = file_get_contents(__DIR__ . '/../public/app/helpers/art-piece-generation.php');
    $preview = file_get_contents(__DIR__ . '/../public/app/views/admin/pieces/generate-preview.php');
    assert_contains($helper, 'function art_piece_is_c2_interactive_code(string $code, ?string $html = null): bool');
    assert_contains($helper, "Selected C2.js Interactive mode requires explicit pointer, mouse, touch, or click interaction hooks in the generated code.");
    assert_contains($controller, 'art_piece_preflight_document($engine, $html, $css, $js, $generationMode);');
    assert_contains($controller, 'art_piece_preflight_document($engine, $extractedHtml, $extractedCss, $extractedJs, $generationMode);');
    assert_contains($controller, 'art_piece_preflight_document($engine, $extractedHtml, $extractedCss, $extractedJs, $persistedGenerationMode);');
    assert_contains($controller, '$systemPrompt = art_piece_refine_system_prompt($generationMode);');
    assert_contains($controller, '$systemPrompt = art_piece_refine_system_prompt($persistedGenerationMode);');
    assert_contains($preview, 'generation_mode: generationModeField ? generationModeField.value');
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

test('sizeCanvas gives c2 a fixed canonical intrinsic resolution, not container-derived', function () use ($runtime) {
    // Regression guard: c2 pieces draw with literal screen-pixel
    // coordinates (canvas.width/canvas.height), and AI-generated code
    // reliably mixes a few fixed-pixel touches into otherwise-proportional
    // code (confirmed in real pieces). Those only produce a visibly
    // different composition across surfaces when canvas.width/height
    // itself varies by container, as it did before this fix (320 on the
    // thumbnail vs 1280 in Immersive). Matches Immersive's own hardcoded
    // runtimeSize so every surface converges on the same value.
    $sizeCanvasBody = substr($runtime, strpos($runtime, 'function sizeCanvas('));
    $sizeCanvasBody = substr($sizeCanvasBody, 0, strpos($sizeCanvasBody, "\n}\n"));
    assert_contains($sizeCanvasBody, "PIECE_ENGINE === 'c2'");
    assert_contains($sizeCanvasBody, 'canvas.width = 1280');
    assert_contains($sizeCanvasBody, 'canvas.height = 720');
});

test('c2 canvas element box is aspect-locked to the bitmap (fitCanvasBox), not object-fit letterboxed', function () use ($runtime) {
    // Regression guard: a fixed intrinsic resolution alone isn't enough —
    // plain width:100%;height:100% non-uniformly stretches the canvas's
    // 16:9 bitmap to fill whatever shape box each surface's container
    // happens to be (a phone's narrow/tall public-view iframe vs. an
    // already-16:9 thumbnail vs. a squarer admin preview pane). Confirmed
    // live: the same piece looked square, oval, and severely vertically
    // elongated across three surfaces from this exact cause.
    // object-fit:contain fixed the distortion but broke c2_interactive
    // pointer hit-testing: generated sketches map pointer coordinates with
    // (clientX - rect.left) * (canvas.width / rect.width) — the formula the
    // generation prompt prescribes — which assumes the element box IS the
    // bitmap box, while object-fit letterboxes the bitmap inside it
    // (confirmed on piece 103: ±36 canvas px of hit-test skew in a 896×560
    // box, larger than its drag targets, i.e. "no interactivity").
    // fitCanvasBox() sizes the element itself to the contained rectangle so
    // both properties hold at once.
    $bootCanvasRuntimeBody = substr($runtime, strpos($runtime, 'function bootCanvasRuntime('));
    $bootCanvasRuntimeBody = substr($bootCanvasRuntimeBody, 0, strpos($bootCanvasRuntimeBody, "\n}\n"));
    assert_not_contains($bootCanvasRuntimeBody, 'object-fit');
    assert_not_contains($bootCanvasRuntimeBody, "'c2'\n    ? 'display:block;width:100%;height:100%");
    assert_contains($bootCanvasRuntimeBody, 'fitCanvasBox(canvas)');
    $fitBody = substr($runtime, strpos($runtime, 'function fitCanvasBox('));
    $fitBody = substr($fitBody, 0, strpos($fitBody, "\n}\n"));
    assert_contains($fitBody, 'Math.min(hw / canvas.width, hh / canvas.height)');
    assert_contains($fitBody, "canvas.style.width");
    assert_contains($fitBody, "canvas.style.height");
});

test('c2 canvas allows pointer drags on touchscreens (touch-action:none) in runtime and export', function () use ($runtime) {
    // Without touch-action:none the browser claims pointermove sequences on
    // the canvas for scroll/zoom gestures, so c2_interactive drag
    // interactions silently do nothing on touch devices.
    $bootCanvasRuntimeBody = substr($runtime, strpos($runtime, 'function bootCanvasRuntime('));
    $bootCanvasRuntimeBody = substr($bootCanvasRuntimeBody, 0, strpos($bootCanvasRuntimeBody, "\n}\n"));
    assert_contains($bootCanvasRuntimeBody, 'touch-action:none');
    $render = file_get_contents(__DIR__ . '/../public/app/helpers/piece-render.php');
    assert_contains($render, "canvas.style.touchAction = 'none'");
});

test('c2 loadImage returns an awaitable/thenable Promise carrying the image element in all three runtimes', function () use ($runtime, $immersive) {
    // Regression guard: generated sketches call loadImage every way models
    // guess at — `await runtime.loadImage(...)`, `runtime.loadImage(...)
    // .then(...)`, and plain `const img = runtime.loadImage(...)` handed
    // straight to the draw helpers. The old contract returned a bare
    // HTMLImageElement, so `.then(...)` crashed the sketch at boot
    // (TypeError: runtime.loadImage(...).then is not a function — hit by a
    // real Mistral Vibe generation). loadImage must return a Promise that
    // resolves to the image on load, expose the element as __creatrImage,
    // and the draw helpers must unwrap it via resolveImageRef() so the
    // synchronous pass-through style keeps working.
    $render = file_get_contents(__DIR__ . '/../public/app/helpers/piece-render.php');
    foreach (['piece-runtime.js' => $runtime, 'immersive-gallery.js' => $immersive, 'piece-render.php export bootstrap' => $render] as $label => $src) {
        assert_contains($src, 'loaded.__creatrImage = image', "{$label}:");
        assert_contains($src, 'imageCache.set(src, loaded)', "{$label}:");
        assert_contains($src, 'return loaded', "{$label}:");
        assert_contains($src, 'function resolveImageRef(image)', "{$label}:");
        assert_contains($src, 'image = resolveImageRef(image)', "{$label}:");
    }
});

test('c2 generation prompts document the loadImage Promise contract', function () {
    $generation = file_get_contents(__DIR__ . '/../public/app/helpers/art-piece-generation.php');
    assert_contains($generation, 'returns a Promise that resolves to the image once loaded');
});

test('immersive-gallery.js has no static top-level import beyond the two unconditionally-required Three.js dependencies', function () use ($immersive) {
    // Regression guard: a static top-level `import` of DeviceOrientationControls
    // from a URL that 404'd (three.js removed it from examples/jsm in the
    // version this app pins) aborted loading this ENTIRE module — breaking
    // Three.js, A-Frame, and p5/c2/svg immersive mounting alike, since
    // they're all exported from this one file. THREE core and OrbitControls
    // are the only dependencies every immersive piece actually needs
    // unconditionally; anything else optional/experimental must be loaded
    // via a dynamic import() inside a try/catch instead, so a missing or
    // broken source can only disable that one feature, never the module.
    preg_match_all('/^import\s.+$/m', $immersive, $matches);
    $staticImports = $matches[0];
    foreach ($staticImports as $line) {
        $isCoreThree = str_contains($line, "from 'https://cdn.jsdelivr.net/npm/three@") && str_contains($line, '/build/three.module.js');
        $isOrbitControls = str_contains($line, 'OrbitControls.js');
        if (!$isCoreThree && !$isOrbitControls) {
            throw new Exception("Unexpected static top-level import found (should be a dynamic import() instead, contained in try/catch): {$line}");
        }
    }
    assert_contains($immersive, "import * as THREE from");
    assert_contains($immersive, "import { OrbitControls }");
});

test('the gyroscope feature loads DeviceOrientationControls via a contained dynamic import, not a static one', function () use ($immersive) {
    assert_contains($immersive, 'await import("/assets/js/three-device-orientation-controls.js")');
    $setupBody = substr($immersive, strpos($immersive, 'function createSharedGyroController('));
    $setupBody = substr($setupBody, 0, strpos($setupBody, '// Standalone image gallery mounting helper') ?: 5000);
    assert_contains($setupBody, 'try {');
    assert_contains($setupBody, 'catch');
});

test('Three.js gyro controls wait for real angles and calibrate first yaw to the visible view', function () use ($immersive) {
    assert_contains($immersive, 'function requestCalibration()');
    assert_contains($immersive, 'function hasDeviceOrientationAngles');
    assert_contains($immersive, 'function calibrateGyroToCurrentView()');
    assert_contains($immersive, 'baselineQuat.copy(camera.quaternion)');
    assert_contains($immersive, 'if (hasDeviceOrientationAngles(deviceControls))');
    assert_contains($immersive, 'deviceControls.alphaOffset = bestOffset');
});

echo "\n=== live human-voice input (mic) — consistency across UI surfaces ===\n";

test('sonic-controller.js exposes the mic engine API', function () {
    $sonicSrc = file_get_contents(__DIR__ . '/../public/assets/js/sonic-controller.js');
    assert_contains($sonicSrc, 'isMicSupported: function ()');
    assert_contains($sonicSrc, 'enableMic: enableMic');
    assert_contains($sonicSrc, 'disableMic: disableMic');
    assert_contains($sonicSrc, 'setMicEffect: function (key, enabled, params)');
    // Mic must default off and never be gated by inputMode/enabled state
    // persistence — dispose() must always tear it down.
    assert_contains($sonicSrc, 'disableMic();');
});

test('mic toggle + effects markup is present in both live UI surfaces (immersive-chrome.php shared markup and the ZIP export mountUi())', function () {
    $chrome = file_get_contents(__DIR__ . '/../public/app/helpers/immersive-chrome.php');
    assert_contains($chrome, 'function immersive_stage_mic_panel_markup');
    assert_contains($chrome, "'-toggle aria-pressed=\"false\">Live mic</button>'");
    assert_contains($chrome, 'immersive_stage_mic_panel_markup()'); // mounted in the immersive toolbar

    $render = file_get_contents(__DIR__ . '/../public/app/helpers/piece-render.php');
    assert_contains($render, "micToggle.textContent = 'Live mic';");
    assert_contains($render, 'engine.setMicEffect(key, checkbox.checked)');
    assert_contains($render, 'micOk = ok && engine ? await engine.enableMic() : false;');

    $show = file_get_contents(__DIR__ . '/../public/app/views/pieces/show.php');
    assert_contains($show, "immersive_stage_mic_panel_markup('piece-mic', 'data-piece-mic'");
});

test('piece-runtime.js relays mic toggle/effect postMessages and reports mic support to the parent', function () {
    $runtime = file_get_contents(__DIR__ . '/../public/assets/js/piece-runtime.js');
    assert_contains($runtime, "data.type === 'creatr-sound-mic-toggle'");
    assert_contains($runtime, "data.type === 'creatr-sound-mic-fx'");
    assert_contains($runtime, 'micSupported:');
    // The dead standalone-panel branch must stay dead — not silently grown
    // a second mic implementation that could drift from the real one.
    assert_contains($runtime, 'NOT extended with mic UI');
});

echo "\n=== Results ===\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}
echo "All runtime consistency checks passed!\n";
