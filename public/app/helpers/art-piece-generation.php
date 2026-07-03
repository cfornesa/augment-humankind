<?php

declare(strict_types=1);

const ART_PIECE_MAX_ATTEMPTS = 5;
const ART_PIECE_ATTEMPT_TIMEOUT = 120; // seconds

function art_piece_supported_engines(): array
{
    return ['p5', 'c2', 'three', 'svg', 'aframe'];
}

function art_piece_canvas_managed_engines(): array
{
    return ['p5', 'c2', 'three'];
}

function art_piece_generation_mode_to_engine(string $mode): string
{
    return $mode === 'c2_interactive' ? 'c2' : $mode;
}

function art_piece_normalize_cms_media_ref(string $src): ?string
{
    $src = trim($src);
    if ($src === '') {
        return null;
    }
    $path = preg_split('/[?#]/', $src, 2)[0] ?? $src;
    $path = '/' . ltrim($path, '/');
    return preg_match('#^/(?:image/[0-9]+|api/media-assets/[0-9]+|media/[A-Za-z0-9._~/%+-]+)$#', $path)
        ? $path
        : null;
}

function art_piece_extract_prompt_media_refs(string $prompt): array
{
    $refs = [];
    $add = static function (string $ref) use (&$refs): void {
        $normalized = art_piece_normalize_cms_media_ref($ref);
        if ($normalized !== null) {
            $refs[$normalized] = true;
        }
    };

    if (preg_match_all('#(?<![A-Za-z0-9._~/-])/?(?:image/[0-9]+|api/media-assets/[0-9]+|media/[A-Za-z0-9._~/%+-]+)(?:\?[A-Za-z0-9._~%=&+-]*)?#i', $prompt, $matches)) {
        foreach ($matches[0] as $match) {
            $add($match);
        }
    }

    if (preg_match_all('/\b(?:image|img|photo|picture)\s+(?:(?:with\s+an?\s+)?id\s*(?:of\s*)?[:#]?\s*|#)?([0-9]+)\b/i', $prompt, $matches)) {
        foreach ($matches[1] as $id) {
            $add('/image/' . $id);
        }
    }

    if (preg_match_all('/\bmedia(?!\s+asset\b)\s+(?:(?:with\s+an?\s+)?id\s*(?:of\s*)?[:#]?\s*|#)?([0-9]+)\b/i', $prompt, $matches)) {
        foreach ($matches[1] as $id) {
            $add('/media/' . $id);
        }
    }

    if (preg_match_all('/\bmedia\s+asset\s+(?:(?:with\s+an?\s+)?id\s*(?:of\s*)?[:#]?\s*|#)?([0-9]+)\b/i', $prompt, $matches)) {
        foreach ($matches[1] as $id) {
            $add('/api/media-assets/' . $id);
        }
    }

    return array_keys($refs);
}

function art_piece_media_policy_prompt(array $allowedMediaRefs, bool $allowExisting = false): string
{
    $allowedMediaRefs = array_values(array_unique(array_filter($allowedMediaRefs, 'is_string')));
    if ($allowedMediaRefs === []) {
        return $allowExisting
            ? 'CMS MEDIA POLICY: Preserve existing CMS media only if it is already present in the current code. Do NOT add any new /image/{id}, /media/..., or /api/media-assets/{id} references unless the refinement instruction explicitly names that exact image or media ID/path.'
            : 'CMS MEDIA POLICY: The user did not explicitly request a CMS image or media asset. Do NOT include any /image/{id}, /media/..., or /api/media-assets/{id} references. Use procedural drawing, colors, gradients, geometry, particles, or generated shapes instead.';
    }
    return 'CMS MEDIA POLICY: The only CMS media references explicitly requested by the user are: ' . implode(', ', $allowedMediaRefs) . '. Do not use any other /image/{id}, /media/..., or /api/media-assets/{id} reference.';
}

function art_piece_collect_cms_media_refs(?string ...$contents): array
{
    $refs = [];
    foreach ($contents as $content) {
        $content = (string) $content;
        if ($content === '') {
            continue;
        }
        if (!preg_match_all('#(?<![A-Za-z0-9._~/-])/?(?:image/[0-9]+|api/media-assets/[0-9]+|media/[A-Za-z0-9._~/%+-]+)(?:\?[A-Za-z0-9._~%=&+-]*)?#i', $content, $matches)) {
            continue;
        }
        foreach ($matches[0] as $match) {
            $normalized = art_piece_normalize_cms_media_ref($match);
            if ($normalized !== null) {
                $refs[$normalized] = true;
            }
        }
    }
    return array_keys($refs);
}

function validate_art_piece_prompted_media_refs(array $allowedMediaRefs, ?string $html, ?string $css, ?string $js, array $existingMediaRefs = [], bool $requirePromptedRefs = false): void
{
    $allowed = [];
    foreach (array_merge($allowedMediaRefs, $existingMediaRefs) as $ref) {
        $normalized = art_piece_normalize_cms_media_ref((string) $ref);
        if ($normalized !== null) {
            $allowed[$normalized] = true;
        }
    }

    $found = art_piece_collect_cms_media_refs($html, $css, $js);
    $unexpected = array_values(array_filter($found, static fn(string $ref): bool => !isset($allowed[$ref])));
    if ($unexpected !== []) {
        throw new RuntimeException('CMS media references are only allowed when the prompt explicitly names the exact image or media ID/path. Unexpected reference(s): ' . implode(', ', $unexpected) . '.');
    }

    if ($requirePromptedRefs) {
        $foundSet = array_fill_keys($found, true);
        $missing = [];
        foreach ($allowedMediaRefs as $ref) {
            $normalized = art_piece_normalize_cms_media_ref((string) $ref);
            if ($normalized !== null && !isset($foundSet[$normalized])) {
                $missing[] = $normalized;
            }
        }
        if ($missing !== []) {
            throw new RuntimeException('The prompt explicitly requested CMS media reference(s) that were not integrated into the piece: ' . implode(', ', array_values(array_unique($missing))) . '.');
        }
    }
}

/**
 * Returns the engine-specific system prompt for generation.
 * Joined with a single space to match the Node.js .join(" ") behavior.
 */
function art_piece_generation_system_prompt(string $engine): string
{
    return match ($engine) {
        'p5' => implode(' ', [
            "You generate reusable interactive art sketches for a self-hosted p5 runtime.",
            "You MUST return your response as three separate Markdown code blocks (```html, ```css, and ```javascript).",
            "Return ONLY those three fenced code blocks. Do NOT include prose, explanations, titles, bullets, or notes before, between, or after the code blocks.",
            "The HTML block must contain ONLY this exact body-ready mount element: `<div id=\"canvas-container\"></div>`. Do NOT use custom ids such as 'book-container', 'scene-container', 'app', or 'root'. Do NOT include <style>, <script>, <link>, <base>, <html>, <head>, or <body> tags in the HTML block; standalone exports add imports and document wrappers later.",
            "The CSS block must contain all styling needed by the piece's body HTML and canvas. It may style only mount IDs/classes that you define in the HTML block. Do NOT target `html`, `body`, or global `canvas`, and do NOT use `position: fixed`, `display: none`, `visibility: hidden`, or `opacity: 0`.",
            "Do NOT use import statements for p5; the runtime provides it globally.",
            "Use p5 instance mode. The JS must assign its sketch function to `window.sketch = (p) => { ... }` and follow this shape: `window.sketch = (p) => { p.setup = () => {}; p.draw = () => {}; };`.",
            "The JS block must contain all functions needed by the sketch, including local helpers like `drawImageCover(img, x, y, width, height)` when it needs a full-frame image. Standalone exports wrap this same JS; do not rely on presentation-only controls.",
            "Use CMS images ONLY when the user prompt explicitly names a specific image/media ID or path. When explicitly requested, use p5's preload pattern with that exact path, such as `img = p.loadImage('/image/{id}')`, and draw it with `p.image(...)` or a local `drawImageCover(...)` helper. Allowed media paths are `/image/{id}`, `/media/...`, and `/api/media-assets/{id}` only. Image assets define the source; `p.image(..., width, height, sx, sy, sw, sh)` defines rendered size and cover cropping.",
            "Always call `p.createCanvas(p.windowWidth, p.windowHeight)` inside `setup()` so the sketch fills the iframe. Do NOT hardcode small fixed dimensions like `createCanvas(400, 400)`.",
            "CRITICAL: Animations MUST be infinite and engaging. Use periodic functions like Math.sin() or Math.cos() combined with p.frameCount to ensure movement loops or pulsates indefinitely.",
            "Avoid logic that permanently removes all elements from the screen. If elements are destroyed, they must be periodically respawned.",
            "Keep the composition self-contained and visually intentional.",
            "NEVER use `p` as a variable name for anything other than the p5 instance. In forEach/map/filter callbacks and class instances, use names like `particle`, `item`, `shape`, `obj` — using `p` shadows the outer p5 instance and causes all p5 calls inside the callback to fail.",
            "Always initialize every state/config object inside `setup()`, not at the top of the sketch. In `draw()` and event handlers, guard any access to objects that might not yet exist: `if (!myObj) return;`. Do NOT call methods on variables that are declared but not yet initialized."
        ]),
        'c2' => implode(' ', [
            "You generate reusable interactive art sketches for a self-hosted c2.js runtime.",
            "You MUST return your response as three separate Markdown code blocks (```html, ```css, and ```javascript).",
            "Return ONLY those three fenced code blocks. Do NOT include prose, explanations, titles, bullets, or notes before, between, or after the code blocks.",
            'The HTML block must contain ONLY body-ready canvas HTML such as `<canvas id="piece-canvas"></canvas>`. Do NOT include <style>, <script>, <link>, <base>, <html>, <head>, or <body> tags in the HTML block; standalone exports add imports and document wrappers later.',
            "The CSS block must contain all styling needed by the piece's body HTML and canvas. It may style only mount IDs/classes that you define in the HTML block. Do NOT target `html`, `body`, or global `canvas`, and do NOT use `position: fixed`, `display: none`, `visibility: hidden`, or `opacity: 0`.",
            "Do NOT use import statements for c2; the runtime provides it globally.",
            "The JS must assign its setup function to `window.sketch` like this: `window.sketch = (runtime) => { const { c2, canvas, startFrame } = runtime; const renderer = new c2.Renderer(canvas); startFrame((frameCount) => { renderer.clear(); /* draw */ }); };`. CALL `startFrame(handler)` inside the sketch to register the animation loop — do NOT return it or return an object containing it.",
            "The JS block must contain all functions needed by the sketch and must use only the supplied runtime helpers for image drawing.",
            "Use CMS images ONLY when the user prompt explicitly names a specific image/media ID or path. When explicitly requested, load that exact path through the runtime helpers only, such as `const img = runtime.loadImage('/image/{id}');`, then draw it with `runtime.drawImage(...)` or `runtime.drawImageCover(...)`. Allowed media paths are `/image/{id}`, `/media/...`, and `/api/media-assets/{id}` only. Image assets define the source; `runtime.drawImage(...)` and `runtime.drawImageCover(...)` define rendered size. Do NOT call canvas.getContext(), drawImage(), new Image(), fetch(), or external URLs yourself.",
            "Do NOT include any <script src> tags — the runtime is already loaded, and any <script src> referencing an external file will cause a fatal error.",
            "Use `new c2.Renderer(canvas)` to create the renderer. Do NOT call any canvas-sizing or canvas-context methods directly.",
            "RENDERER API (call on the renderer object): renderer.clear(), renderer.clear(cssColor) [fills canvas with that color — use this for background fills], renderer.fill(cssColor), renderer.stroke(cssColor), renderer.fill(false), renderer.stroke(false), renderer.lineWidth(n), renderer.alpha(a), renderer.fontSize(n), renderer.fontFamily(f), renderer.textAlign(a), renderer.text(str,x,y). IMPORTANT: renderer.background(c) only sets a CSS style and does NOT paint the canvas — NEVER use renderer.background() for background fills; use renderer.clear('#color') instead.",
            "DRAWING METHODS — use these exact signatures: renderer.circle(x,y,r) OR renderer.circle(new c2.Circle(x,y,r)); renderer.rect(x,y,w,h) OR renderer.rect(new c2.Rect(x,y,w,h)); renderer.line(x1,y1,x2,y2) OR renderer.line(new c2.Line(p1,p2)); renderer.ellipse(x,y,rx,ry) [no c2.Ellipse constructor exists — always call directly]; renderer.triangle(x1,y1,x2,y2,x3,y3); renderer.polygon([{x,y},...]); renderer.arc(new c2.Arc(p,r,start,end)); renderer.sector(new c2.Sector(p,r1,r2,start,end)).",
            "NEVER use: c2.Ellipse, c2.Text, c2.Path, c2.Shape, beginShape, endShape, vertex, bezierVertex, curveVertex, noFill, noStroke, push, pop, strokeWeight, beginFill, endFill — these do not exist in c2.js. renderer.fill() and renderer.stroke() each take exactly ONE argument (a CSS color string, e.g. '#ff0000'). Do not call renderer.draw(), renderer.animation(), or renderer.loop() — the runtime drives the animation loop.",
            "The `c2` library object comes from `runtime.c2` — always destructure it as `const { c2, canvas, startFrame } = runtime`. Do NOT assume `c2` is a global variable.",
            "Do NOT use `c2.Ease`, `c2.Mouse`, `c2.Keyboard`, `c2.Touch`, `.linear`, `.pressed`, or any c2 input/easing helper. Those helpers are not provided by this runtime. For easing, write local math functions such as `const ease = (t) => t * t * (3 - 2 * t);`. For motion, use `frameCount`, `Math.sin`, and `Math.cos`.",
            "ALWAYS use `canvas.width` and `canvas.height` (not hardcoded pixel values) for ALL coordinate and size calculations — the canvas dimensions vary by context. To center a shape: `canvas.width/2, canvas.height/2`. To fill the background: draw from `0,0` to `canvas.width, canvas.height`.",
            "NEVER call `document.body.appendChild(canvas)` or any DOM method that moves the canvas element. The runtime manages canvas placement.",
            "CRITICAL: Animations MUST be infinite. Use the frameCount passed to startFrame() with periodic functions like Math.sin() to ensure the piece loops or pulsates indefinitely. Respawn elements if they move off-screen or are destroyed.",
            "Keep the work visually intentional."
        ]),
        'c2_interactive' => implode(' ', [
            "You generate reusable INTERACTIVE art sketches for a self-hosted c2.js runtime.",
            "You MUST return your response as three separate Markdown code blocks (```html, ```css, and ```javascript).",
            "Return ONLY those three fenced code blocks. Do NOT include prose, explanations, titles, bullets, or notes before, between, or after the code blocks.",
            'The HTML block must contain ONLY body-ready canvas HTML such as `<canvas id="piece-canvas"></canvas>`. Do NOT include <style>, <script>, <link>, <base>, <html>, <head>, or <body> tags in the HTML block; standalone exports add imports and document wrappers later.',
            "The CSS block must contain all styling needed by the piece's body HTML and canvas. It may style only mount IDs/classes that you define in the HTML block. Do NOT target `html`, `body`, or global `canvas`, and do NOT use `position: fixed`, `display: none`, `visibility: hidden`, or `opacity: 0`.",
            "Do NOT use import statements for c2; the runtime provides it through the runtime object.",
            "The JS must assign its setup function to `window.sketch` like this: `window.sketch = (runtime) => { const { c2, canvas, startFrame } = runtime; const renderer = new c2.Renderer(canvas); startFrame((frameCount) => { renderer.clear('#101014'); /* draw */ }); };`. CALL `startFrame(handler)` inside the sketch to register the animation loop.",
            "The JS block must contain all functions needed by the sketch and must use only the supplied runtime helpers for image drawing.",
            "Use CMS images ONLY when the user prompt explicitly names a specific image/media ID or path. When explicitly requested, load that exact path through the runtime helpers only, such as `const img = runtime.loadImage('/image/{id}');`, then draw it with `runtime.drawImage(...)` or `runtime.drawImageCover(...)`. Allowed media paths are `/image/{id}`, `/media/...`, and `/api/media-assets/{id}` only. Image assets define the source; `runtime.drawImage(...)` and `runtime.drawImageCover(...)` define rendered size. Do NOT call canvas.getContext(), drawImage(), new Image(), fetch(), or external URLs yourself.",
            "This mode MUST include direct user interaction. Use native `canvas.addEventListener()` handlers for `pointerdown`, `pointermove`, `pointerup`, `click`, or `touchstart` to update local state, hit-test shapes, spawn elements, drag anchors, toggle colors, or otherwise visibly change the artwork.",
            "For pointer coordinates, use `const rect = canvas.getBoundingClientRect(); const x = (event.clientX - rect.left) * (canvas.width / rect.width); const y = (event.clientY - rect.top) * (canvas.height / rect.height);` so interaction works after responsive scaling.",
            "Keep every interaction state variable inside the `window.sketch` closure. Do NOT use localStorage, cookies, fetch, parent/top window access, or navigation.",
            "Use `new c2.Renderer(canvas)` to create the renderer. Do NOT call any canvas-sizing or canvas-context methods directly.",
            "RENDERER API (call on the renderer object): renderer.clear(), renderer.clear(cssColor), renderer.fill(cssColor), renderer.stroke(cssColor), renderer.fill(false), renderer.stroke(false), renderer.lineWidth(n), renderer.alpha(a), renderer.fontSize(n), renderer.fontFamily(f), renderer.textAlign(a), renderer.text(str,x,y). Use renderer.clear('#color') for background fills; NEVER use renderer.background() for drawing backgrounds.",
            "DRAWING METHODS — use these exact signatures: renderer.circle(x,y,r) OR renderer.circle(new c2.Circle(x,y,r)); renderer.rect(x,y,w,h) OR renderer.rect(new c2.Rect(x,y,w,h)); renderer.line(x1,y1,x2,y2) OR renderer.line(new c2.Line(p1,p2)); renderer.ellipse(x,y,rx,ry); renderer.triangle(x1,y1,x2,y2,x3,y3); renderer.polygon([{x,y},...]); renderer.arc(new c2.Arc(p,r,start,end)); renderer.sector(new c2.Sector(p,r1,r2,start,end)).",
            "NEVER use: c2.Ellipse, c2.Text, c2.Path, c2.Shape, beginShape, endShape, vertex, bezierVertex, curveVertex, noFill, noStroke, push, pop, strokeWeight, beginFill, endFill, c2.Ease, c2.Mouse, c2.Keyboard, c2.Touch, .linear, .pressed, .released, .dragged, renderer.draw(), renderer.animation(), or renderer.loop().",
            "The `c2` library object comes from `runtime.c2` — always destructure it as `const { c2, canvas, startFrame } = runtime`. Do NOT assume `c2` is a global variable.",
            "ALWAYS use `canvas.width` and `canvas.height` for drawing coordinates and sizes. Animations MUST be infinite and continue even when no pointer is active.",
            "Keep the work visually intentional and make the interactive affordance discoverable from motion or composition."
        ]),
        'three' => implode(' ', [
            "You generate reusable interactive 3D scenes for a self-hosted Three.js runtime.",
            "You MUST return your response as three separate Markdown code blocks (```html, ```css, and ```javascript).",
            "Return ONLY those three fenced code blocks. Do NOT include prose, explanations, titles, bullets, or notes before, between, or after the code blocks.",
            "The HTML block must contain body-ready mount HTML such as `<div id=\"container\"></div>` or a canvas. Do NOT include imports, <script>, <style>, <html>, <head>, or <body>; standalone exports add imports and document wrappers later.",
            "The CSS block must contain all styling needed by the piece's body HTML and canvas.",
            "The runtime provides THREE globally. Do NOT use import statements.",
            "The JS must assign its setup function to `window.sketch` like this:",
            "`window.sketch = (runtime) => { const { THREE, canvas, startFrame, width, height } = runtime; /* setup scene, return cleanup function */ return () => {}; };`.",
            "The JS block must contain all functions needed by the scene, including local helpers like `coverTexture(texture, planeWidth, planeHeight)` when it needs full-frame image cover behavior.",
            "Use CMS images ONLY when the user prompt explicitly names a specific image/media ID or path. When explicitly requested, load that exact path as a texture, such as `const texture = new THREE.TextureLoader().load('/image/{id}');`, then size the geometry to control how it appears. Allowed media paths are `/image/{id}`, `/media/...`, and `/api/media-assets/{id}` only. For full-frame requested image backgrounds, compute `backgroundHeight` and `backgroundWidth` from camera FOV, aspect, and distance, apply the texture to `new THREE.PlaneGeometry(backgroundWidth, backgroundHeight)`, and configure texture repeat/offset cover behavior.",
            "CRITICAL: Always create the WebGLRenderer with the provided canvas: `new THREE.WebGLRenderer({ canvas, antialias: true })`. If you omit `{ canvas }`, Three.js creates a second canvas element that is not positioned in the DOM — the scene will be invisible. NEVER call `document.body.appendChild(renderer.domElement)` — the canvas is already in the correct position.",
            'CRITICAL: The HTML container div MUST use id="container" — do NOT use custom ids such as \'book-container\', \'scene-container\', \'app\', or \'root\'. The runtime only mounts the WebGL canvas inside elements with known ids (container, canvas-container, sketch-container). Any other id causes the canvas to be placed outside the styled container, making the scene invisible in the normal preview.',
            "CRITICAL: Use `width` and `height` from the runtime for ALL sizing — never use `window.innerWidth` or `window.innerHeight`. Pass `false` as the third argument to `renderer.setSize(width, height, false)` to prevent CSS override. Do NOT add `window.addEventListener('resize', ...)` — the runtime handles resize. Incorrect sizing makes the scene invisible in the default post view.",
            "CRITICAL: If you use MeshPhongMaterial, MeshLambertMaterial, or MeshStandardMaterial, you MUST add at least one light (e.g. AmbientLight + DirectionalLight). These materials are invisible without lights. MeshBasicMaterial does not need lights and is suitable for simple solid-colored objects.",
            "Use `startFrame(handler)` for animation and call `renderer.render(scene, camera)` yourself inside that handler. CRITICAL: `handler` is called with exactly ONE argument — an integer frame counter (`startFrame((frameCount) => { ... })`) — never elapsed time or delta time. If you need real elapsed time for `Math.sin`/`Math.cos` motion, create your own `const clock = new THREE.Clock();` inside the sketch and read `clock.getElapsedTime()` at the top of the handler; do NOT destructure a second parameter from the handler — it will always be `undefined` and corrupt every value computed from it.",
            "CRITICAL: Animations MUST be infinite. Use Math.sin/cos with elapsed time from a local `THREE.Clock` or the handler's frame counter to create periodic motion or pulsating effects. Ensure elements don't just disappear; the scene must remain visually active indefinitely.",
            "CRITICAL: For any repeated small element — hair strands, particles, leaves, a swarm, grass, fur, etc. — numbering more than about 30-40 instances, you MUST use a single `THREE.InstancedMesh` with one shared `BufferGeometry`/material, never `new THREE.Mesh(...)` once per item; hundreds or thousands of individual mesh objects will exhaust WebGL resources and the piece will fail to render at all. Static (non-animated) instances: `const geo = new THREE.CylinderGeometry(0.02, 0.01, 0.3, 4); const mat = new THREE.MeshStandardMaterial({ color: 0x1a0d0a }); const count = 600; const inst = new THREE.InstancedMesh(geo, mat, count); const dummy = new THREE.Object3D(); for (let i = 0; i < count; i++) { dummy.position.set(Math.random()-0.5, Math.random()-0.5, Math.random()-0.5); dummy.rotation.set(Math.random()*Math.PI, Math.random()*Math.PI, 0); dummy.scale.setScalar(0.8 + Math.random()*0.4); dummy.updateMatrix(); inst.setMatrixAt(i, dummy.matrix); } scene.add(inst);` — per-instance variation (position, rotation, scale, even per-instance color via `inst.setColorAt(i, color)` with `inst.instanceColor` if material has `vertexColors: true`) all happens through `setMatrixAt`/`setColorAt`, never by creating a new geometry or mesh per item.",
            "CRITICAL: A single shared geometry does NOT mean every instance must look identical — simulate per-instance SIZE/shape variation with non-uniform scale on the SAME base geometry instead of a different geometry per item: `dummy.scale.set(radiusFactor, heightFactor, radiusFactor);` (e.g. a base unit cylinder stretched/thinned per instance) covers the common 'each strand is a slightly different size' need without ever calling `new THREE.CylinderGeometry(...)` (or any geometry constructor) inside the per-instance loop.",
            "CRITICAL: If repeated instances need their OWN per-instance animation (e.g. each hair strand swaying independently), do the per-instance setup ONCE outside any frame loop — storing each instance's base transform and a phase offset in a plain array, e.g. `const instanceData = []; for (let i = 0; i < count; i++) { instanceData.push({ basePos: new THREE.Vector3(...), phase: Math.random() * Math.PI * 2 }); }` — then inside `startFrame((frameCount) => { ... })`, loop over that array once per frame, compute each instance's updated matrix from its stored base data plus the current time/frameCount, call `inst.setMatrixAt(i, dummy.matrix)` for each one, and set `inst.instanceMatrix.needsUpdate = true;` once after the loop (not per instance) before `renderer.render(scene, camera)`. This is the instanced equivalent of updating each individual mesh's `.position`/`.rotation` per frame — never abandon instancing for repeated elements just because they need independent motion.",
            "Keep the scene self-contained."
        ]),
        'aframe' => implode(' ', [
            "You generate reusable experimental A-Frame scenes for a self-hosted A-Frame runtime.",
            "You MUST return your response as three separate Markdown code blocks (```html, ```css, and ```javascript).",
            "Return ONLY those three fenced code blocks. Do NOT include prose, explanations, titles, bullets, or notes before, between, or after the code blocks.",
            "The HTML block MUST contain exactly one `<a-scene id=\"scene\" embedded>` as the scene root. Include all generated A-Frame entities inside that scene.",
            "Use CMS images ONLY when the user prompt explicitly names a specific image/media ID or path. When explicitly requested, place that exact path in an `<img id=\"asset-id\" src=\"/image/{id}\">` inside one `<a-assets>` block, then reference it with `src=\"#asset-id\"` or `material=\"src: #asset-id\"`. Allowed image paths are `/image/{id}`, `/media/...`, and `/api/media-assets/{id}` only. Image assets define the source; rendered A-Frame entities define size. Do NOT put width/height on the `<img>` asset expecting it to resize the scene. Set width/height on the `<a-plane>` or entity that references it; for full-frame requested image backgrounds, compute and set the plane's `backgroundWidth` and `backgroundHeight` from camera FOV, aspect, and distance in `window.sketch`.",
            "Do NOT include <script>, <link>, <base>, <html>, <head>, <body>, <iframe>, <audio>, <video>, or <a-asset-item>. Do NOT use external URLs or remote textures.",
            "The CSS block may style only `#scene`, `.a-canvas`, or classes/ids used by generated entities. Do NOT target global page chrome, and do NOT use `display: none`, `visibility: hidden`, or `opacity: 0` on the scene or canvas.",
            "The runtime provides AFRAME globally. Do NOT use import statements.",
            "The JS must assign its setup function to `window.sketch` like this: `window.sketch = ({ AFRAME, scene, startFrame }) => { /* optional event handlers or generated entities */ };`.",
            "Use declarative infinite A-Frame animations with `animation` / `animation__name` attributes whenever possible. Animations MUST be infinite, looping, alternating, or continuously updated by startFrame().",
            "For interaction, use A-Frame event listeners on generated entities, such as `entity.addEventListener('click', ...)`, `mouseenter`, `mouseleave`, or cursor/raycaster events. Include an `<a-camera>` with an `<a-cursor>` when click/hover interaction matters.",
            "Do NOT request camera, microphone, webcam, device sensors, XR sessions, remote assets, networking, storage, parent/top window access, or page navigation.",
            "Keep the scene self-contained, lightweight, and visually intentional."
        ]),
        'svg' => implode(' ', [
            "You generate animated SVG art pieces for display as a self-contained iframe.",
            "Return ONLY three Markdown code blocks: ```html, ```css, ```javascript. No prose, titles, or notes.",
            'HTML block: one `<svg viewBox="0 0 800 600" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%">` as root. All shapes, paths, groups, and `<defs>` go inside it. No <style>, <script>, <html>, <head>, or <body> tags.',
            'Use CMS images ONLY when the user prompt explicitly names a specific image/media ID or path. When explicitly requested, use that exact path in an SVG `<image href="/image/{id}" ... preserveAspectRatio="xMidYMid slice" />` element and set x/y/width/height for the requested placement. Allowed media paths are `/image/{id}`, `/media/...`, and `/api/media-assets/{id}` only. Image assets define the source; SVG `<image>` x/y/width/height attributes define rendered size.',
            'CRITICAL: Never leave SVG groups empty. If you create a group to hold dynamic content (e.g. `<g id="particles"></g>`), you MUST populate it — either with inline children in the HTML block (for static elements) or by appending children in `window.sketch` (for dynamic/spawning elements). An empty placeholder group is a generation failure.',
            "CSS block: MUST start with `svg { display: block; width: 100%; height: 100%; } body, html { margin: 0; padding: 0; width: 100%; height: 100%; overflow: hidden; background: transparent; }`. MUST add `@keyframes` animations on actual SVG elements (shapes, paths, groups by ID or class) using `animation: name duration easing infinite`. Targeting only `body` and `html` in CSS with no SVG element animations is not acceptable.",
            "CRITICAL: CSS `@keyframes` must be `animation-iteration-count: infinite`. Use staggered `animation-delay` across elements for organic motion.",
            "JavaScript block: REQUIRED for all prompts that involve particles, storms, explosions, swarms, or dynamic spawning. Implement using this exact pattern: `window.sketch = () => { const svg = window.svgRoot || document.querySelector('svg'); const group = svg.querySelector('#targetGroupId') || svg; const items = []; for (let i = 0; i < 60; i++) { const el = document.createElementNS('http://www.w3.org/2000/svg', 'circle'); el.setAttribute('r', '3'); el.setAttribute('fill', '#ff8844'); group.appendChild(el); items.push({ el, x: Math.random()*800, y: Math.random()*600, vx: (Math.random()-.5)*4, vy: (Math.random()-.5)*4 }); } (function tick() { items.forEach(p => { p.x += p.vx; p.y += p.vy; if (p.x < 0 || p.x > 800 || p.y < 0 || p.y > 600) { p.x = 400; p.y = 300; p.vx = (Math.random()-.5)*4; p.vy = (Math.random()-.5)*4; } p.el.setAttribute('cx', p.x); p.el.setAttribute('cy', p.y); }); requestAnimationFrame(tick); })(); };`",
            "Only output `window.sketch = () => {};` when the piece uses ONLY CSS @keyframes and truly needs no JavaScript at all.",
            "CRITICAL: The piece MUST have visible animation running from the first frame. A piece with no CSS @keyframes on SVG elements AND an empty window.sketch is a generation failure.",
            "To create SVG elements in JavaScript, ALWAYS use `document.createElementNS('http://www.w3.org/2000/svg', tagName)`. Use `setAttribute` to set SVG attributes. The runtime provides `window.svgRoot` pointing to the SVG root. Do NOT use `document.createElement()`, `document.body.appendChild()`, `fetch`, `import`, or canvas APIs.",
            "Keep the SVG art self-contained and visually intentional."
        ]),
        default => throw new InvalidArgumentException("Unknown engine: {$engine}"),
    };
}

/**
 * Extracts HTML, CSS, and JS code blocks from AI markdown response.
 * Tries a broad set of language aliases for JS, then falls back to any
 * unrecognised fenced block, then to <script> content embedded in the HTML block.
 */
function art_piece_extract_code_blocks(string $raw): array
{
    $extract = static function (array $langs) use ($raw): ?string {
        foreach ($langs as $lang) {
            if (preg_match('/```' . preg_quote($lang, '/') . '\s*([\s\S]*?)```/i', $raw, $m)) {
                return trim($m[1]);
            }
        }
        return null;
    };

    $htmlCode      = $extract(['html']);
    $cssCode       = $extract(['css']);
    $generatedCode = $extract(['javascript', 'js', 'typescript', 'ts', 'jsx', 'tsx', 'ecmascript']);

    // Fallback: any fenced block whose language tag is not html or css
    if ($generatedCode === null) {
        preg_match_all('/```([a-zA-Z]*)\s*\n([\s\S]*?)```/', $raw, $allBlocks, PREG_SET_ORDER);
        foreach ($allBlocks as $block) {
            $lang    = strtolower($block[1]);
            $content = trim($block[2]);
            if (!in_array($lang, ['html', 'css'], true) && $content !== '') {
                $generatedCode = $content;
                break;
            }
        }
    }

    // Last resort: pull <script> content out of the HTML block
    if ($generatedCode === null && $htmlCode !== null && $htmlCode !== '') {
        if (preg_match('/<script(?:\s[^>]*)?>[\r\n]*([\s\S]*?)<\/script>/i', $htmlCode, $m)) {
            $extracted = trim($m[1]);
            if ($extracted !== '') {
                $generatedCode = $extracted;
                $htmlCode      = trim(preg_replace('/<script(?:\s[^>]*)?>[\s\S]*?<\/script>/i', '', $htmlCode));
            }
        }
    }

    return [
        'htmlCode'      => $htmlCode,
        'cssCode'       => $cssCode,
        'generatedCode' => $generatedCode,
    ];
}

/**
 * Static validation matching platform's validateArtPieceCode and preflight checks.
 * Note: PHP cannot run a JS runtime to execute the sketch dynamically. We verify static 
 * code invariants and look for window.sketch definition.
 */
function validate_art_piece_code(string $code): string
{
    $code = trim($code);
    if ($code === '') {
        throw new RuntimeException('Generated code cannot be empty');
    }
    if (strlen($code) > 120000) {
        throw new RuntimeException('Generated code is too large');
    }

    $disallowedPatterns = [
        ['pattern' => '/\bimport\s*\(/i', 'message' => 'Generated code cannot use dynamic imports'],
        ['pattern' => '/\bimport\s+[^("\'`]/i', 'message' => 'Generated code cannot use import statements'],
        ['pattern' => '/\bexport\s+/i', 'message' => 'Generated code cannot use export statements'],
        ['pattern' => '/<\/?script\b/i', 'message' => 'Generated code cannot contain script tags'],
        ['pattern' => '/\bfetch\s*\(/i', 'message' => 'Generated code cannot fetch remote resources'],
        ['pattern' => '/\bXMLHttpRequest\b/i', 'message' => 'Generated code cannot make XHR requests'],
        ['pattern' => '/\bWebSocket\b/i', 'message' => 'Generated code cannot open WebSockets'],
        ['pattern' => '/\bEventSource\b/i', 'message' => 'Generated code cannot open EventSource streams'],
        ['pattern' => '/\blocalStorage\b/i', 'message' => 'Generated code cannot access localStorage'],
        ['pattern' => '/\bsessionStorage\b/i', 'message' => 'Generated code cannot access sessionStorage'],
        ['pattern' => '/\bdocument\.cookie\b/i', 'message' => 'Generated code cannot access cookies'],
        ['pattern' => '/\bwindow\.location\b/i', 'message' => 'Generated code cannot navigate the page'],
        ['pattern' => '/\bdocument\.location\b/i', 'message' => 'Generated code cannot navigate the page'],
        ['pattern' => '/\btop\./i', 'message' => 'Generated code cannot access the top window'],
        ['pattern' => '/\bparent\./i', 'message' => 'Generated code cannot access the parent window'],
    ];

    foreach ($disallowedPatterns as $rule) {
        if (preg_match($rule['pattern'], $code)) {
            throw new RuntimeException($rule['message']);
        }
    }

    return $code;
}

/**
 * Estimates how many THREE.Mesh/Points/Line objects the code will actually
 * create at runtime — not just how many `new THREE.Mesh(...)` call sites
 * appear in the source. A single call site inside `for (let i = 0; i < 900;
 * i++) { ... new THREE.Mesh(...) ... }` creates 900 objects at runtime, and
 * a flat source-text count would miss that entirely (it would see "1").
 * Detects simple bounded for-loops with a literal numeric comparison bound,
 * multiplies any mesh-creation calls found in that loop's body by the
 * bound, and adds any remaining calls found outside a detected loop body at
 * face value. Not a full JS parser — covers the common
 * `for (...; i < N; ...) { ... }` / `for (...; i <= N; ...) { ... }`
 * generative-art pattern this check exists to catch, not every possible
 * loop construct.
 */
function art_piece_count_three_object_calls(string $code): int
{
    $callPattern = '/\bnew\s+THREE\s*\.\s*(?:Mesh|Points|Line)\s*\(/i';
    $total = 0;
    $consumedRanges = [];

    if (preg_match_all('/for\s*\([^;{}]*;\s*\w+\s*(?:<=?|>=?)\s*(\d+)\s*;[^)]*\)\s*\{/i', $code, $loopMatches, PREG_OFFSET_CAPTURE)) {
        foreach ($loopMatches[0] as $idx => $fullMatch) {
            $bound = (int) $loopMatches[1][$idx][0];
            $bodyStart = $fullMatch[1] + strlen($fullMatch[0]);
            $depth = 1;
            $pos = $bodyStart;
            $len = strlen($code);
            while ($pos < $len && $depth > 0) {
                if ($code[$pos] === '{') {
                    $depth++;
                } elseif ($code[$pos] === '}') {
                    $depth--;
                }
                $pos++;
            }
            $bodyEnd = $pos - 1;
            $body = substr($code, $bodyStart, $bodyEnd - $bodyStart);
            $callsInBody = preg_match_all($callPattern, $body);
            if ($callsInBody > 0) {
                $total += $bound * $callsInBody;
                $consumedRanges[] = [$bodyStart, $bodyEnd];
            }
        }
    }

    if (preg_match_all($callPattern, $code, $allMatches, PREG_OFFSET_CAPTURE)) {
        foreach ($allMatches[0] as $match) {
            $pos = $match[1];
            $insideConsumed = false;
            foreach ($consumedRanges as [$rangeStart, $rangeEnd]) {
                if ($pos >= $rangeStart && $pos < $rangeEnd) {
                    $insideConsumed = true;
                    break;
                }
            }
            if (!$insideConsumed) {
                $total++;
            }
        }
    }

    return $total;
}

/**
 * Validates art piece code based on its engine type.
 */
function art_piece_preflight_code(string $engine, string $code, ?string $html = null, ?string $css = null): string
{
    $validatedCode = validate_art_piece_code($code);

    if ($engine === 'c2') {
        $c2Rules = [
            [
                'pattern' => '/\bc2\s*\.\s*Ease\b|\bEase\s*\.\s*linear\b|\.linear\s*\(/',
                'message' => 'Generated C2.js code cannot use c2.Ease, Ease.linear, or .linear() easing helpers; define a local easing function instead.'
            ],
            [
                'pattern' => '/\bc2\s*\.\s*(Mouse|Keyboard|Touch)\b|\b(Mouse|Keyboard|Touch)\s*\(|\.(pressed|released|dragged)\b/',
                'message' => 'Generated C2.js code cannot use c2 input helpers or .pressed/.released/.dragged state; this runtime only provides c2, canvas, and startFrame.'
            ],
            [
                'pattern' => '/\.getContext\s*\(|\bnew\s+Image\s*\(|(?<!\.)\bdrawImage\s*\(/i',
                'message' => 'Generated C2.js code cannot use raw canvas image APIs; use runtime.loadImage() and runtime.drawImage() with same-origin CMS media instead.'
            ]
        ];
        foreach ($c2Rules as $rule) {
            if (preg_match($rule['pattern'], $validatedCode)) {
                throw new RuntimeException($rule['message']);
            }
        }
    }

    if ($engine === 'three') {
        $threeRules = [
            [
                'pattern' => '/startFrame\s*\(\s*(?:\([^)]*,[^)]*\)|function\s*\([^)]*,[^)]*\))/',
                'message' => 'Generated Three.js code cannot pass a multi-parameter handler to startFrame(); the runtime only ever calls it with a single frame-count integer, never elapsed/delta time. Use startFrame((frameCount) => {...}) and a local THREE.Clock for real elapsed time.'
            ],
        ];
        foreach ($threeRules as $rule) {
            if (preg_match($rule['pattern'], $validatedCode)) {
                throw new RuntimeException($rule['message']);
            }
        }

        // No automatic rejection on individual mesh/points/line object
        // count, by deliberate, explicit decision — tried at two different
        // thresholds (150, then 1000) and both were directly falsified by
        // live evidence: a 728-object piece under the 1000 cap still failed
        // to render, while a 1400+ object piece rendered fine. Object count
        // doesn't predict renderability (whether objects are instanced
        // does), so no fixed number can work here. The person reviewing
        // each piece decides whether it's workable, not a static check.
        // art_piece_count_three_object_calls() still exists for optional
        // non-blocking diagnostics (e.g. audit-log metadata) but must not
        // gate or warn here.
    }

    if ($engine === 'aframe') {
        $aframeJsRules = [
            [
                'pattern' => '/\bnavigator\s*\.\s*mediaDevices\b|\bgetUserMedia\s*\(/i',
                'message' => 'Generated A-Frame code cannot request webcam, microphone, or media capture.'
            ],
            [
                'pattern' => '/\bnavigator\s*\.\s*xr\b|\brequestSession\s*\(/i',
                'message' => 'Generated A-Frame code cannot request XR sessions directly; the runtime owns fullscreen/immersive behavior.'
            ],
        ];
        foreach ($aframeJsRules as $rule) {
            if (preg_match($rule['pattern'], $validatedCode)) {
                throw new RuntimeException($rule['message']);
            }
        }
    }

    validate_art_piece_media_references($engine, null, null, $validatedCode);

    // Static check for window.sketch definition. Requires an actual
    // assignment (a single `=` not followed by another `=`) rather than
    // just the identifier appearing anywhere — a stray
    // `typeof window.sketch === 'function'` guard with no real assignment
    // elsewhere must still fail, since the runtime silently no-ops (no
    // canvas, no error) when window.sketch isn't actually a function.
    if ($engine !== 'svg' || $validatedCode !== 'window.sketch = () => {};') {
        if (!preg_match('/window\s*\.\s*sketch\s*=(?!=)/i', $validatedCode)) {
            throw new RuntimeException('Generated code did not define window.sketch');
        }
    }

    return $validatedCode;
}

function art_piece_preflight_document(string $engine, ?string $html, ?string $css, ?string $js): array
{
    $html = trim((string) $html);
    $css = trim((string) $css);
    $js = trim((string) $js);

    if ($html === '' && $engine !== 'svg') {
        throw new RuntimeException('HTML block is empty');
    }
    if ($js !== '') {
        art_piece_preflight_code($engine, $js, $html, $css);
    } elseif ($engine !== 'svg') {
        throw new RuntimeException('JavaScript block is empty');
    }

    if (in_array($engine, art_piece_canvas_managed_engines(), true)) {
        if (!preg_match('/id\s*=\s*["\'](?:container|canvas-container|sketch-container|runtime-root)["\']/i', $html) && !preg_match('/<canvas/i', $html)) {
            throw new RuntimeException('HTML block must contain a container element (e.g. <div id="container"></div> or a <canvas> element) to mount the canvas.');
        }
        if (preg_match('/(?:canvas|#container|#scene|#c2-canvas)\s*\{[^}]*\bdisplay\s*:\s*none\b/i', $css)) {
            throw new RuntimeException('CSS cannot hide the canvas or container element (display: none is forbidden).');
        }
        if (preg_match('/(?:canvas|#container|#scene|#c2-canvas)\s*\{[^}]*\bvisibility\s*:\s*hidden\b/i', $css)) {
            throw new RuntimeException('CSS cannot hide the canvas or container element (visibility: hidden is forbidden).');
        }
    }

    if ($engine === 'aframe') {
        if (!preg_match('/<a-scene\b[^>]*\bid\s*=\s*["\']scene["\']/i', $html)) {
            throw new RuntimeException('A-Frame HTML must contain one <a-scene id="scene" embedded> root.');
        }
        if (preg_match_all('/<a-scene\b/i', $html) !== 1) {
            throw new RuntimeException('A-Frame HTML must contain exactly one <a-scene> root.');
        }
        $aframeHtmlRules = [
            ['pattern' => '/<\/?(?:script|link|base|html|head|body|iframe|audio|video|a-asset-item)\b/i', 'message' => 'A-Frame HTML cannot contain document, script, iframe, audio, video, or arbitrary asset-loading tags.'],
            ['pattern' => '/\burl\s*\(/i', 'message' => 'A-Frame CSS/HTML cannot reference external URL assets.'],
        ];
        foreach ($aframeHtmlRules as $rule) {
            if (preg_match($rule['pattern'], $html) || preg_match($rule['pattern'], $css)) {
                throw new RuntimeException($rule['message']);
            }
        }
        validate_aframe_media_references($html);
        if (preg_match('/(?:#scene|a-scene|canvas|\.a-canvas)\s*\{[^}]*\bdisplay\s*:\s*none\b/i', $css)) {
            throw new RuntimeException('CSS cannot hide the A-Frame scene or canvas (display: none is forbidden).');
        }
        if (preg_match('/(?:#scene|a-scene|canvas|\.a-canvas)\s*\{[^}]*\bvisibility\s*:\s*hidden\b/i', $css)) {
            throw new RuntimeException('CSS cannot hide the A-Frame scene or canvas (visibility: hidden is forbidden).');
        }
    }

    if ($engine === 'svg') {
        if (!preg_match('/<svg/i', $html)) {
            throw new RuntimeException('HTML code must contain an <svg> element for SVG pieces.');
        }
        if (preg_match('/(?:svg|#container)\s*\{[^}]*\bdisplay\s*:\s*none\b/i', $css)) {
            throw new RuntimeException('CSS cannot hide the SVG or container element (display: none is forbidden).');
        }
        if (preg_match('/(?:svg|#container)\s*\{[^}]*\bvisibility\s*:\s*hidden\b/i', $css)) {
            throw new RuntimeException('CSS cannot hide the SVG or container element (visibility: hidden is forbidden).');
        }
    }

    validate_art_piece_media_references($engine, $html, $css, $js);

    return ['html' => $html, 'css' => $css, 'js' => $js];
}

function validate_art_piece_media_references(string $engine, ?string $html, ?string $css, ?string $js): void
{
    $parts = [
        'HTML' => (string) $html,
        'CSS' => (string) $css,
        'JavaScript' => (string) $js,
    ];

    foreach ($parts as $label => $content) {
        if ($content === '') {
            continue;
        }
        if (preg_match_all('/url\s*\(\s*([\'"]?)([^)\'"]+)\1\s*\)/i', $content, $urlMatches, PREG_SET_ORDER)) {
            foreach ($urlMatches as $match) {
                $src = trim((string) $match[2]);
                if (str_starts_with($src, '#') || str_starts_with($src, 'data:')) {
                    continue;
                }
                if (!is_allowed_art_piece_media_src($src)) {
                    throw new RuntimeException("CSS url() media references may only use same-origin CMS media paths such as /image/2, /media/..., or /api/media-assets/2.");
                }
            }
        }
    }

    if ($html !== null && preg_match_all('/\b(?:src|href|xlink:href)\s*=\s*(["\'])([^"\']+)\1/i', $html, $attrMatches, PREG_SET_ORDER)) {
        foreach ($attrMatches as $match) {
            $src = trim((string) $match[2]);
            if ($src === '' || str_starts_with($src, '#') || str_starts_with($src, 'data:')) {
                continue;
            }
            if (!is_allowed_art_piece_media_src($src)) {
                throw new RuntimeException('HTML media attributes may only reference same-origin CMS media paths or local #asset ids.');
            }
        }
    }

    if ($engine === 'p5' && $js !== null) {
        validate_literal_media_call_urls($js, '/\bp\s*\.\s*loadImage\s*\(\s*(["\'])([^"\']+)\1/i', 'p5 loadImage()');
    }

    if ($engine === 'three' && $js !== null) {
        validate_literal_media_call_urls($js, '/\bTextureLoader\s*\(\s*\)\s*\.\s*load\s*\(\s*(["\'])([^"\']+)\1/i', 'Three.js TextureLoader.load()');
        validate_literal_media_call_urls($js, '/\.\s*load\s*\(\s*(["\'])([^"\']+)\1/i', 'Three.js asset loader calls');
    }

    if ($engine === 'c2' && $js !== null) {
        validate_literal_media_call_urls($js, '/\bruntime\s*\.\s*loadImage\s*\(\s*(["\'])([^"\']+)\1/i', 'C2 runtime.loadImage()');
    }
}

function validate_literal_media_call_urls(string $code, string $pattern, string $label): void
{
    if (!preg_match_all($pattern, $code, $matches, PREG_SET_ORDER)) {
        return;
    }
    foreach ($matches as $match) {
        $src = trim((string) $match[2]);
        if (!is_allowed_art_piece_media_src($src)) {
            throw new RuntimeException("{$label} may only load same-origin CMS media paths such as /image/2, /media/..., or /api/media-assets/2.");
        }
    }
}

function validate_aframe_media_references(string $html): void
{
    $allowedAssetIds = [];
    if (preg_match_all('/<img\b([^>]*)>/i', $html, $imgMatches, PREG_SET_ORDER)) {
        foreach ($imgMatches as $match) {
            $attrs = $match[1] ?? '';
            if (!preg_match('/\bid\s*=\s*(["\'])([^"\']+)\1/i', $attrs, $idMatch)) {
                throw new RuntimeException('A-Frame image assets must have an id so scene materials can reference them.');
            }
            if (!preg_match('/\bsrc\s*=\s*(["\'])([^"\']+)\1/i', $attrs, $srcMatch)) {
                throw new RuntimeException('A-Frame image assets must include a same-origin CMS image src.');
            }
            $src = trim((string) $srcMatch[2]);
            if (!is_allowed_art_piece_media_src($src)) {
                throw new RuntimeException('A-Frame image assets must use same-origin CMS media paths such as /image/2, /media/..., or /api/media-assets/2.');
            }
            $allowedAssetIds[] = preg_quote((string) $idMatch[2], '/');
        }
    }

    if (preg_match_all('/\bsrc\s*=\s*(["\'])([^"\']+)\1/i', $html, $srcMatches, PREG_SET_ORDER)) {
        foreach ($srcMatches as $match) {
            $src = trim((string) $match[2]);
            if (str_starts_with($src, '#')) {
                if ($allowedAssetIds === [] || !preg_match('/^#(?:' . implode('|', $allowedAssetIds) . ')$/', $src)) {
                    throw new RuntimeException('A-Frame texture references must point to an <img> id defined in <a-assets>.');
                }
                continue;
            }
            if (!is_allowed_art_piece_media_src($src)) {
                throw new RuntimeException('A-Frame src attributes may only reference same-origin CMS media or #asset ids.');
            }
        }
    }

    if (preg_match_all('/\bmaterial\s*=\s*(["\'])([^"\']*\bsrc\s*:\s*([^;"\']+)[^"\']*)\1/i', $html, $materialMatches, PREG_SET_ORDER)) {
        foreach ($materialMatches as $match) {
            $src = trim((string) $match[3]);
            if (str_starts_with($src, '#')) {
                if ($allowedAssetIds === [] || !preg_match('/^#(?:' . implode('|', $allowedAssetIds) . ')$/', $src)) {
                    throw new RuntimeException('A-Frame material texture references must point to an <img> id defined in <a-assets>.');
                }
                continue;
            }
            if (!is_allowed_art_piece_media_src($src)) {
                throw new RuntimeException('A-Frame material texture src may only reference same-origin CMS media or #asset ids.');
            }
        }
    }
}

function is_allowed_art_piece_media_src(string $src): bool
{
    return (bool) preg_match('#^/(?:image/[0-9]+|api/media-assets/[0-9]+|media/[A-Za-z0-9._~/%+-]+)(?:\?[A-Za-z0-9._~%=&+-]*)?$#', $src);
}

/**
 * Builds the repair prompt to guide the AI to correct validation failures.
 */
function art_piece_repair_prompt(string $engine, string $originalPrompt, ?string $previousRawResponse, string $failureMessage, array $allowedMediaRefs = []): string
{
    $segments = [
        "Target engine: {$engine}",
        "Original prompt: {$originalPrompt}",
        "The previous art-piece attempt failed validation: {$failureMessage}",
        "Return a corrected response that fixes the error while staying visually faithful to the original prompt. Provide the HTML, CSS, and JS in Markdown code blocks.",
        "CRITICAL: Animations MUST be infinite. They must loop, reset their state, or pulsate continuously. Never allow the piece to end on a blank screen or permanently destroy all elements.",
        art_piece_media_policy_prompt($allowedMediaRefs),
        "If the failure involves media, use only the CMS media path(s) explicitly allowed above; never use remote URLs, fetch, imports, script tags, iframe tags, storage, or parent/top window access.",
        "If the failure involves C2 images, use `runtime.loadImage()`, `runtime.drawImage()`, or `runtime.drawImageCover()` only; never call `canvas.getContext()`, `new Image()`, or raw `drawImage()`.",
        "If the failure involves portability, keep the CMS `window.sketch` contract. Standalone HTML export is generated by the system wrapper, not by adding document/import tags to the generated blocks.",
    ];
    if ($previousRawResponse !== null && $previousRawResponse !== '') {
        $segments[] = "Previous invalid response: {$previousRawResponse}";
    }
    return implode("\n\n", $segments);
}

/**
 * Returns the system prompt for refining/iterating on an existing art piece.
 *
 * Unlike generation, refine never asks for a full rewritten file — a full
 * rewrite gives an LLM no structural reason to leave anything untouched, and
 * in practice it doesn't. Instead this enforces a plan-then-patch protocol:
 * the AI must first name the specific existing elements it intends to touch
 * (the same "state assumptions before acting" discipline plan mode uses),
 * then express every change as an exact find-and-replace PATCH against the
 * current code. Anything not captured in a patch is carried forward
 * unchanged by art_piece_apply_refine_patches() — a structural guarantee,
 * not a request the model can quietly ignore.
 */
function art_piece_refine_system_prompt(string $engine): string
{
    $baseRules = art_piece_generation_system_prompt($engine);
    $isSvg = ($engine === 'svg');
    $isAframe = ($engine === 'aframe');
    $htmlContextDesc = $isSvg 
        ? "the current HTML, CSS, and JS code blocks" 
        : ($isAframe
            ? "the current HTML, CSS, and JS code blocks"
            : "the current CSS and JS code blocks (excluding the static HTML block which is managed automatically and must not be edited)");
    
    $engineConstraint = $isSvg
        ? "CRITICAL: For svg engine pieces, the HTML code MUST retain the <svg> element. The CSS must never hide the SVG or container (display: none and visibility: hidden on svg or container elements are strictly forbidden)."
        : ($isAframe
            ? "CRITICAL: For aframe engine pieces, the HTML code MUST retain exactly one <a-scene id=\"scene\" embedded> root. Do not add external assets, scripts, iframes, or remote URLs. Same-origin CMS image assets are allowed only through `<a-assets><img src=\"/image/2\"></a-assets>` and `#asset` references. The CSS must never hide the scene or canvas."
            : "CRITICAL: For {$engine} engine pieces, HTML changes are STRICTLY FORBIDDEN. The HTML container is managed automatically. Do not write a 'PATCH html:' block. Focus your edits solely on CSS or JS. The CSS must never hide the canvas or container (display: none and visibility: hidden on canvas or container elements are strictly forbidden).");

    return implode(' ', [
        "You are an AI assistant making a SINGLE, NARROWLY SCOPED edit to an existing interactive generative art piece, following a strict two-step process.",
        "You will receive the original creative prompt (if available), {$htmlContextDesc} of the art piece, and a refinement instruction.",
        "STEP 1 — PLAN: before writing any code, identify exactly which specific existing elements (a named SVG path/element, a CSS rule, a function or variable) are relevant to the refinement instruction. For each one, quote a short identifying fragment of the CURRENT code and state in one line what you will change about it and why. Name only elements the instruction is actually about — do not plan to touch anything it did not name.",
        "STEP 2 — PATCH: express every change ONLY as an exact find-and-replace edit against the current code. NEVER rewrite or regenerate a file in full, even partially. For each element from your plan, write a PATCH block naming the file (html, css, or js), then a SEARCH section containing the EXISTING text copied VERBATIM — character-for-character, including whitespace and indentation, exactly as it appears in the current code below — and a REPLACE section with the new text to put in its place. A SEARCH block that does not match the current code (even allowing minor whitespace differences) will be rejected.",
        "Prefer a SHORT, single-purpose SEARCH block — ideally one line, or the smallest span of text that uniquely identifies the one location you mean — over a large multi-line block. A shorter exact-match target is far less likely to contain a transcription mistake.",
        "If a file (html, css, or js) needs no change at all, write NO PATCH block for that file — omit it entirely. Its current code is kept exactly as-is.",
        "You MUST respond in exactly this format and nothing else — no prose, explanations, or notes before, between, or after these sections:",
        art_piece_refine_patch_format_example(),
        "Everything outside a matched SEARCH region is preserved exactly as it is today — you are never regenerating the whole file, only patching the specific elements named in your own plan.",
        "Ensure all constraints of the {$engine} engine are strictly maintained in anything you write inside a REPLACE section.",
        $engineConstraint,
        "Here are the engine-specific rules for {$engine} that you MUST follow: ",
        $baseRules
    ]);
}

/**
 * A worked example of the PLAN/PATCH response format, included verbatim in
 * the refine system prompt. Few-shot examples are far more reliable than an
 * abstract format description at getting a model to actually produce
 * parseable, exactly-matching SEARCH/REPLACE blocks.
 */
function art_piece_refine_patch_format_example(): string
{
    return implode("\n", [
        "EXAMPLE (illustrative only — your plan/patches must be about the actual instruction and actual current code given to you):",
        "PLAN:",
        "- `<circle cx=\"50\" cy=\"50\" r=\"10\" fill=\"#ff0000\"/>` — the instruction asks to make this circle blue, so I will change only its fill color.",
        "- `const speed = 0.5;` — the instruction asks to make the animation faster, so I will increase this value.",
        "",
        "PATCH html:",
        "<<<<<<< SEARCH",
        "<circle cx=\"50\" cy=\"50\" r=\"10\" fill=\"#ff0000\"/>",
        "=======",
        "<circle cx=\"50\" cy=\"50\" r=\"10\" fill=\"#0000ff\"/>",
        ">>>>>>> REPLACE",
        "",
        "PATCH js:",
        "<<<<<<< SEARCH",
        "const speed = 0.5;",
        "=======",
        "const speed = 1.2;",
        ">>>>>>> REPLACE",
        "",
        "(No PATCH css block here, because nothing in CSS needed to change for this example. The label for the JavaScript file is always exactly \"js\" — never \"javascript\" — even when every change in a piece is JS-only, as is typical for Three.js pieces.)",
    ]);
}

/**
 * Builds the user prompt representing the refinement task.
 *
 * $originalPrompt is the piece's own creative prompt (why the code looks the
 * way it does), distinct from $refinementPrompt (what to change about it
 * now) — without it, the AI only sees the code and the new instruction, with
 * no sense of the original intent it's supposed to stay true to.
 */
function art_piece_refine_user_prompt(string $engine, string $refinementPrompt, ?string $html, ?string $css, ?string $js, ?string $originalPrompt = null, array $allowedMediaRefs = []): string
{
    $sections = [];
    if ($originalPrompt !== null && trim($originalPrompt) !== '') {
        $sections[] = "### ORIGINAL CREATIVE PROMPT (the intent this piece was built to fulfill — stay true to it)";
        $sections[] = $originalPrompt;
    }
    $sections[] = "### REFINEMENT INSTRUCTION";
    $sections[] = $refinementPrompt;
    $sections[] = "### REMINDER";
    if ($engine === 'svg' || $engine === 'aframe') {
        $sections[] = "Apply ONLY the change named above, as PATCH blocks against the exact current code below — never as a rewritten file. Every color, shape, decoration, and detail not mentioned in the instruction must not appear in any SEARCH/REPLACE pair at all.";
    } else {
        $sections[] = "Apply ONLY the change named above, as PATCH blocks against the exact current code below — never as a rewritten file. Every color, shape, decoration, and detail not mentioned in the instruction must not appear in any SEARCH/REPLACE pair at all. You are STRICTLY FORBIDDEN from editing or adding HTML patches. Focus your edits solely on JS or CSS.";
    }
    $sections[] = art_piece_media_policy_prompt($allowedMediaRefs, true);
    if ($engine === 'svg' || $engine === 'aframe') {
        $sections[] = "### CURRENT HTML CODE";
        $sections[] = "```html\n" . ($html ?? '') . "\n```";
    }
    $sections[] = "### CURRENT CSS CODE";
    $sections[] = "```css\n" . ($css ?? '') . "\n```";
    $sections[] = "### CURRENT JAVASCRIPT CODE";
    $sections[] = "```javascript\n" . ($js ?? '') . "\n```";

    return implode("\n\n", $sections);
}

/**
 * Pulls the PLAN: section out of a refine response, for display only (not
 * used for validation) — lets the admin see what the AI intended to touch,
 * the same visibility a plan gives before a change is made.
 */
function art_piece_extract_refine_plan(string $raw): string
{
    if (preg_match('/PLAN\s*:\s*\n([\s\S]*?)(?=\n\s*PATCH\s+(?:html|css|js)\s*:|\z)/i', $raw, $m)) {
        return trim($m[1]);
    }
    return '';
}

/**
 * Parses every "PATCH <file>:" section and its <<<<<<< SEARCH / =======
 * / >>>>>>> REPLACE pairs out of a refine response.
 *
 * Returns ['html' => [['search' => ..., 'replace' => ...], ...], 'css' => [...], 'js' => [...]].
 * A file with no PATCH block in the response simply has an empty array here.
 *
 * Accepts "javascript" as a synonym for "js" — despite the prompt asking
 * for exactly "js", models (observed consistently on Three.js pieces, whose
 * refinements are almost always JS-only) reliably write "PATCH javascript:"
 * instead. Rejecting it would silently discard an otherwise perfectly valid,
 * correctly-targeted patch over a label spelling, not a real problem with
 * the patch itself.
 */
function art_piece_extract_refine_patches(string $raw): array
{
    $patches = ['html' => [], 'css' => [], 'js' => []];

    if (!preg_match_all(
        '/PATCH\s+(html|css|js|javascript)\s*:\s*\n([\s\S]*?)(?=\n\s*PATCH\s+(?:html|css|js|javascript)\s*:|\z)/i',
        $raw,
        $segments,
        PREG_SET_ORDER
    )) {
        return $patches;
    }

    foreach ($segments as $segment) {
        $file = strtolower($segment[1]);
        if ($file === 'javascript') {
            $file = 'js';
        }
        $body = $segment[2];
        if (preg_match_all(
            '/<<<<<<<\s*SEARCH\s*\n([\s\S]*?)\n=======\s*\n([\s\S]*?)\n>>>>>>>\s*REPLACE/i',
            $body,
            $pairs,
            PREG_SET_ORDER
        )) {
            foreach ($pairs as $pair) {
                $patches[$file][] = ['search' => $pair[1], 'replace' => $pair[2]];
            }
        }
    }

    return $patches;
}

/**
 * Applies a list of search/replace patches to a single file's current code.
 *
 * This is the actual guarantee behind the plan-then-patch protocol: a patch
 * can only change the exact text it names. Anything not named in a SEARCH
 * block is never touched, because it's never passed back through the AI's
 * generation path at all — there's nothing for it to drift on.
 *
 * Throws if a patch's SEARCH text doesn't match the current code exactly
 * once (not found, or found in more than one place — ambiguous). Both
 * failure messages are written to read naturally inside the existing
 * repair-prompt retry loop.
 */
function art_piece_apply_refine_patches(?string $originalCode, array $patches): string
{
    $code = $originalCode ?? '';
    foreach ($patches as $patch) {
        $search = $patch['search'];
        if (trim($search) === '') {
            throw new RuntimeException('A patch had an empty SEARCH block — every patch must target specific existing text.');
        }
        $match = art_piece_find_patch_match($code, $search);
        if ($match === null) {
            throw new RuntimeException('A patch\'s SEARCH text did not match the current code, even allowing for whitespace differences — copy it verbatim from the current code shown to you: ' . mb_substr($search, 0, 300));
        }
        if ($match === 'ambiguous') {
            throw new RuntimeException("A patch's SEARCH text matched more than one place in the current code (ambiguous, even allowing for whitespace differences) — include more surrounding context so it uniquely identifies one location: " . mb_substr($search, 0, 300));
        }
        $code = substr($code, 0, $match['start']) . $patch['replace'] . substr($code, $match['start'] + $match['length']);
    }
    return $code;
}

/**
 * Finds where a patch's SEARCH text occurs in the current code, tolerating
 * whitespace-only differences between the two.
 *
 * LLMs are well known to be inconsistent at reproducing *exact* spacing or
 * indentation even when directly copying visible text — adding/dropping a
 * space around `:`/`{`/`,`, normalizing tabs, etc., without changing
 * anything semantically meaningful. This is not a content-fuzziness
 * allowance: every actual character/token in the SEARCH text must still
 * match exactly — only runs of whitespace between tokens are
 * interchangeable, so this can't match the wrong content, only tolerate
 * incidental reformatting of the right content.
 *
 * Returns ['start' => int, 'length' => int] for a single unambiguous match,
 * the string 'ambiguous' if more than one location matches (by either exact
 * or whitespace-tolerant matching), or null if no location matches either way.
 */
function art_piece_find_patch_match(string $code, string $search): array|string|null
{
    // Exact match first — the common, fast path for already-correct output.
    $exactCount = substr_count($code, $search);
    if ($exactCount === 1) {
        return ['start' => strpos($code, $search), 'length' => strlen($search)];
    }
    if ($exactCount > 1) {
        return 'ambiguous';
    }

    // Whitespace-tolerant fallback. Tokenizes into word-runs (identifiers,
    // numbers — kept as a unit) and individual punctuation/symbol
    // characters, discarding whitespace entirely, then re-joins with \s*
    // between every pair. This (not just splitting on the search text's own
    // whitespace) is what makes both directions of mismatch tolerated —
    // "{ color:" vs "{color:" tokenize identically either way, since
    // whitespace is never part of a token to begin with.
    preg_match_all('/[A-Za-z0-9_$]+|[^\sA-Za-z0-9_$]/u', $search, $tokenMatches);
    $tokens = $tokenMatches[0];
    if ($tokens === []) {
        return null;
    }
    $pattern = '/' . implode('\s*', array_map(static fn (string $t): string => preg_quote($t, '/'), $tokens)) . '/s';
    if (!preg_match_all($pattern, $code, $matches, PREG_OFFSET_CAPTURE)) {
        return null;
    }
    if (count($matches[0]) > 1) {
        return 'ambiguous';
    }
    [$matchedText, $offset] = $matches[0][0];
    return ['start' => $offset, 'length' => strlen($matchedText)];
}

/**
 * Builds the repair prompt used when a refine attempt's patches fail to
 * parse or apply. Distinct from art_piece_repair_prompt() (generation's
 * repair prompt talks about infinite animations and visual fidelity to a
 * creative prompt, neither of which applies to a rejected patch).
 *
 * Re-includes the current HTML/CSS/JS, the same as the first attempt's
 * art_piece_refine_user_prompt() — without this, every retry was working
 * blind from memory of its own previous (wrong) response, with no way to
 * actually re-derive a correct verbatim SEARCH block from the real source.
 */
function art_piece_refine_repair_prompt(string $engine, string $refinementPrompt, ?string $previousRawResponse, string $failureMessage, ?string $html = null, ?string $css = null, ?string $js = null, array $allowedMediaRefs = []): string
{
    $segments = [
        "Target engine: {$engine}",
        "Refinement instruction: {$refinementPrompt}",
        "CRITICAL — your previous attempt failed: \"{$failureMessage}\" You MUST directly address this specific problem in your PLAN section before writing any PATCH — state the new approach you will use to avoid it, not just adjusted numbers or a smaller version of the same approach.",
        art_piece_media_policy_prompt($allowedMediaRefs, true),
    ];
    if ($engine === 'svg' || $engine === 'aframe') {
        $segments[] = "Respond again in the exact PLAN: / PATCH <file>: / <<<<<<< SEARCH / ======= / >>>>>>> REPLACE format. Every SEARCH block must be copied character-for-character from the CURRENT code below, including whitespace and indentation — do not paraphrase, reformat, or reproduce it from memory of your previous attempt. Re-read the current code below and copy directly from it.";
    } else {
        $segments[] = "Respond again in the exact PLAN: / PATCH <file>: / <<<<<<< SEARCH / ======= / >>>>>>> REPLACE format. Every SEARCH block must be copied character-for-character from the CURRENT code below, including whitespace and indentation — do not paraphrase, reformat, or reproduce it from memory of your previous attempt. Re-read the current code below and copy directly from it. Remember: You are STRICTLY FORBIDDEN from editing HTML. Only edit JS or CSS.";
    }
    if ($engine === 'svg' || $engine === 'aframe') {
        $segments[] = "### CURRENT HTML CODE";
        $segments[] = "```html\n" . ($html ?? '') . "\n```";
    }
    $segments[] = "### CURRENT CSS CODE";
    $segments[] = "```css\n" . ($css ?? '') . "\n```";
    $segments[] = "### CURRENT JAVASCRIPT CODE";
    $segments[] = "```javascript\n" . ($js ?? '') . "\n```";
    if ($previousRawResponse !== null && $previousRawResponse !== '') {
        $segments[] = "Your previous response: {$previousRawResponse}";
    }
    return implode("\n\n", $segments);
}
