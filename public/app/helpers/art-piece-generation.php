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
    }

    // Static check for window.sketch definition
    if ($engine !== 'svg' || $validatedCode !== 'window.sketch = () => {};') {
        if (!preg_match('/window\s*\.\s*sketch/i', $validatedCode)) {
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
 */
function art_piece_refine_system_prompt(string $engine): string
{
    $baseRules = art_piece_generation_system_prompt($engine);
    return implode(' ', [
        "You are an AI assistant specialized in iterating on and refining interactive generative art pieces.",
        "You will receive the current HTML, CSS, and JS code blocks of the art piece and a refinement instruction.",
        "Your task is to modify the existing code according to the instruction, while keeping the rest of the code intact.",
        "Ensure all constraints of the {$engine} engine are strictly maintained.",
        "You MUST return the modified code as exactly three Markdown code blocks (```html, ```css, and ```javascript) in that order.",
        "Return ONLY those three fenced code blocks. Do NOT include any intro/outro prose, explanations, titles, bullets, or notes.",
        "Here are the engine-specific rules for {$engine} that you MUST follow: ",
        $baseRules
    ]);
}

/**
 * Builds the user prompt representing the refinement task.
 */
function art_piece_refine_user_prompt(string $engine, string $refinementPrompt, ?string $html, ?string $css, ?string $js): string
{
    return implode("\n\n", [
        "### REFINEMENT INSTRUCTION",
        $refinementPrompt,
        "### CURRENT HTML CODE",
        "```html\n" . ($html ?? '') . "\n```",
        "### CURRENT CSS CODE",
        "```css\n" . ($css ?? '') . "\n```",
        "### CURRENT JAVASCRIPT CODE",
        "```javascript\n" . ($js ?? '') . "\n```",
    ]);
}

