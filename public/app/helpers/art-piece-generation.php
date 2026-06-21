<?php

declare(strict_types=1);

const ART_PIECE_MAX_ATTEMPTS = 5;
const ART_PIECE_ATTEMPT_TIMEOUT = 120; // seconds

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
            "The HTML block must contain ONLY this exact mount element: `<div id=\"canvas-container\"></div>`. Do NOT use custom ids such as 'book-container', 'scene-container', 'app', or 'root'. Do NOT include <style>, <script>, <link>, <base>, <html>, <head>, or <body> tags in the HTML block.",
            "The CSS block may style only mount IDs/classes that you define in the HTML block. Do NOT target `html`, `body`, or global `canvas`, and do NOT use `position: fixed`, `display: none`, `visibility: hidden`, or `opacity: 0`.",
            "Do NOT use import statements for p5; the runtime provides it globally.",
            "Use p5 instance mode. The JS must assign its sketch function to `window.sketch = (p) => { ... }` and follow this shape: `window.sketch = (p) => { p.setup = () => {}; p.draw = () => {}; };`.",
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
            'The HTML block must contain ONLY a mount canvas such as `<canvas id="piece-canvas"></canvas>`. Do NOT include <style>, <script>, <link>, <base>, <html>, <head>, or <body> tags in the HTML block.',
            "The CSS block may style only mount IDs/classes that you define in the HTML block. Do NOT target `html`, `body`, or global `canvas`, and do NOT use `position: fixed`, `display: none`, `visibility: hidden`, or `opacity: 0`.",
            "Do NOT use import statements for c2; the runtime provides it globally.",
            "The JS must assign its setup function to `window.sketch` like this: `window.sketch = (runtime) => { const { c2, canvas, startFrame } = runtime; const renderer = new c2.Renderer(canvas); startFrame((frameCount) => { renderer.clear(); /* draw */ }); };`. CALL `startFrame(handler)` inside the sketch to register the animation loop — do NOT return it or return an object containing it.",
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
        'three' => implode(' ', [
            "You generate reusable interactive 3D scenes for a self-hosted Three.js runtime.",
            "You MUST return your response as three separate Markdown code blocks (```html, ```css, and ```javascript).",
            "Return ONLY those three fenced code blocks. Do NOT include prose, explanations, titles, bullets, or notes before, between, or after the code blocks.",
            "Include a container <div> or <canvas> and relevant CSS for centering or sizing.",
            "The runtime provides THREE globally. Do NOT use import statements.",
            "The JS must assign its setup function to `window.sketch` like this:",
            "`window.sketch = (runtime) => { const { THREE, canvas, startFrame, width, height } = runtime; /* setup scene, return cleanup function */ return () => {}; };`.",
            "CRITICAL: Always create the WebGLRenderer with the provided canvas: `new THREE.WebGLRenderer({ canvas, antialias: true })`. If you omit `{ canvas }`, Three.js creates a second canvas element that is not positioned in the DOM — the scene will be invisible. NEVER call `document.body.appendChild(renderer.domElement)` — the canvas is already in the correct position.",
            'CRITICAL: The HTML container div MUST use id="container" — do NOT use custom ids such as \'book-container\', \'scene-container\', \'app\', or \'root\'. The runtime only mounts the WebGL canvas inside elements with known ids (container, canvas-container, sketch-container). Any other id causes the canvas to be placed outside the styled container, making the scene invisible in the normal preview.',
            "CRITICAL: Use `width` and `height` from the runtime for ALL sizing — never use `window.innerWidth` or `window.innerHeight`. Pass `false` as the third argument to `renderer.setSize(width, height, false)` to prevent CSS override. Do NOT add `window.addEventListener('resize', ...)` — the runtime handles resize. Incorrect sizing makes the scene invisible in the default post view.",
            "CRITICAL: If you use MeshPhongMaterial, MeshLambertMaterial, or MeshStandardMaterial, you MUST add at least one light (e.g. AmbientLight + DirectionalLight). These materials are invisible without lights. MeshBasicMaterial does not need lights and is suitable for simple solid-colored objects.",
            "Use `startFrame(handler)` for animation and call `renderer.render(scene, camera)` yourself inside that handler. CRITICAL: `handler` is called with exactly ONE argument — an integer frame counter (`startFrame((frameCount) => { ... })`) — never elapsed time or delta time. If you need real elapsed time for `Math.sin`/`Math.cos` motion, create your own `const clock = new THREE.Clock();` inside the sketch and read `clock.getElapsedTime()` at the top of the handler; do NOT destructure a second parameter from the handler — it will always be `undefined` and corrupt every value computed from it.",
            "CRITICAL: Animations MUST be infinite. Use Math.sin/cos with elapsed time from a local `THREE.Clock` or the handler's frame counter to create periodic motion or pulsating effects. Ensure elements don't just disappear; the scene must remain visually active indefinitely.",
            "Keep the scene self-contained."
        ]),
        'svg' => implode(' ', [
            "You generate animated SVG art pieces for display as a self-contained iframe.",
            "Return ONLY three Markdown code blocks: ```html, ```css, ```javascript. No prose, titles, or notes.",
            'HTML block: one `<svg viewBox="0 0 800 600" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%">` as root. All shapes, paths, groups, and `<defs>` go inside it. No <style>, <script>, <html>, <head>, or <body> tags.',
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
function art_piece_preflight_code(string $engine, string $code): string
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

        // A loop creating one `new THREE.Mesh(...)` per iteration ("denser
        // hair/beard/particles") for hundreds of items produces thousands
        // of individual geometry buffers and draw calls — this has been
        // observed to exhaust WebGL resources (a silent context loss, with
        // no error and no canvas ever marked ready) well within capture's
        // timeout, on real saved pieces. Cap the total count rather than
        // waiting longer for a renderer that will never come up.
        $meshCount = art_piece_count_three_object_calls($validatedCode);
        if ($meshCount > 150) {
            throw new RuntimeException("Generated Three.js code creates {$meshCount} individual mesh/points/line objects, which will exhaust WebGL resources on real devices. For repeated elements (hair strands, particles, etc.), use a single THREE.InstancedMesh or a merged BufferGeometry instead of one object per item.");
        }
    }

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

/**
 * Builds the repair prompt to guide the AI to correct validation failures.
 */
function art_piece_repair_prompt(string $engine, string $originalPrompt, ?string $previousRawResponse, string $failureMessage): string
{
    $segments = [
        "Target engine: {$engine}",
        "Original prompt: {$originalPrompt}",
        "The previous art-piece attempt failed validation: {$failureMessage}",
        "Return a corrected response that fixes the error while staying visually faithful to the original prompt. Provide the HTML, CSS, and JS in Markdown code blocks.",
        "CRITICAL: Animations MUST be infinite. They must loop, reset their state, or pulsate continuously. Never allow the piece to end on a blank screen or permanently destroy all elements.",
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
    $htmlContextDesc = $isSvg 
        ? "the current HTML, CSS, and JS code blocks" 
        : "the current CSS and JS code blocks (excluding the static HTML block which is managed automatically and must not be edited)";
    
    $engineConstraint = $isSvg
        ? "CRITICAL: For svg engine pieces, the HTML code MUST retain the <svg> element. The CSS must never hide the SVG or container (display: none and visibility: hidden on svg or container elements are strictly forbidden)."
        : "CRITICAL: For {$engine} engine pieces, HTML changes are STRICTLY FORBIDDEN. The HTML container is managed automatically. Do not write a 'PATCH html:' block. Focus your edits solely on CSS or JS. The CSS must never hide the canvas or container (display: none and visibility: hidden on canvas or container elements are strictly forbidden).";

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
function art_piece_refine_user_prompt(string $engine, string $refinementPrompt, ?string $html, ?string $css, ?string $js, ?string $originalPrompt = null): string
{
    $sections = [];
    if ($originalPrompt !== null && trim($originalPrompt) !== '') {
        $sections[] = "### ORIGINAL CREATIVE PROMPT (the intent this piece was built to fulfill — stay true to it)";
        $sections[] = $originalPrompt;
    }
    $sections[] = "### REFINEMENT INSTRUCTION";
    $sections[] = $refinementPrompt;
    $sections[] = "### REMINDER";
    if ($engine === 'svg') {
        $sections[] = "Apply ONLY the change named above, as PATCH blocks against the exact current code below — never as a rewritten file. Every color, shape, decoration, and detail not mentioned in the instruction must not appear in any SEARCH/REPLACE pair at all.";
    } else {
        $sections[] = "Apply ONLY the change named above, as PATCH blocks against the exact current code below — never as a rewritten file. Every color, shape, decoration, and detail not mentioned in the instruction must not appear in any SEARCH/REPLACE pair at all. You are STRICTLY FORBIDDEN from editing or adding HTML patches. Focus your edits solely on JS or CSS.";
    }
    if ($engine === 'svg') {
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
function art_piece_refine_repair_prompt(string $engine, string $refinementPrompt, ?string $previousRawResponse, string $failureMessage, ?string $html = null, ?string $css = null, ?string $js = null): string
{
    $segments = [
        "Target engine: {$engine}",
        "Refinement instruction: {$refinementPrompt}",
        "Your previous response could not be applied: {$failureMessage}",
    ];
    if ($engine === 'svg') {
        $segments[] = "Respond again in the exact PLAN: / PATCH <file>: / <<<<<<< SEARCH / ======= / >>>>>>> REPLACE format. Every SEARCH block must be copied character-for-character from the CURRENT code below, including whitespace and indentation — do not paraphrase, reformat, or reproduce it from memory of your previous attempt. Re-read the current code below and copy directly from it.";
    } else {
        $segments[] = "Respond again in the exact PLAN: / PATCH <file>: / <<<<<<< SEARCH / ======= / >>>>>>> REPLACE format. Every SEARCH block must be copied character-for-character from the CURRENT code below, including whitespace and indentation — do not paraphrase, reformat, or reproduce it from memory of your previous attempt. Re-read the current code below and copy directly from it. Remember: You are STRICTLY FORBIDDEN from editing HTML. Only edit JS or CSS.";
    }
    if ($engine === 'svg') {
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

