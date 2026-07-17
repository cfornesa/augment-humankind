<?php

declare(strict_types=1);

const ART_PIECE_MAX_ATTEMPTS = 5;
const ART_PIECE_ATTEMPT_TIMEOUT = 120; // seconds

function art_piece_supported_engines(): array
{
    return ['p5', 'c2', 'three', 'svg', 'aframe'];
}

function art_piece_supported_generation_modes(): array
{
    return ['p5', 'c2', 'c2_interactive', 'three', 'svg', 'aframe'];
}

function art_piece_canvas_managed_engines(): array
{
    return ['p5', 'c2', 'three'];
}

function art_piece_generation_mode_to_engine(string $mode): string
{
    return $mode === 'c2_interactive' ? 'c2' : $mode;
}

function art_piece_generation_mode_label(string $mode): string
{
    return match ($mode) {
        'p5' => 'P5.js',
        'c2' => 'C2.js',
        'c2_interactive' => 'C2.js Interactive',
        'three' => 'Three.js',
        'svg' => 'SVG',
        'aframe' => 'A-Frame',
        default => strtoupper(trim($mode) !== '' ? $mode : 'p5'),
    };
}

function art_piece_normalize_generation_mode(?string $mode, ?string $engineFallback = 'p5'): string
{
    $mode = trim((string) $mode);
    if (in_array($mode, art_piece_supported_generation_modes(), true)) {
        return $mode;
    }

    $fallback = art_piece_generation_mode_to_engine((string) $engineFallback);
    return in_array($fallback, art_piece_supported_engines(), true) ? $fallback : 'p5';
}

function art_piece_c2_interactive_pattern(): string
{
    return '/(?:addEventListener\s*\(\s*[\'"](?:pointerdown|pointerup|pointermove|mousedown|mouseup|mousemove|touchstart|touchmove|touchend|click)|on(?:click|mousedown|mouseup|mousemove|touchstart|touchmove|touchend|pointerdown|pointermove|pointerup)\s*=)/i';
}

function art_piece_c2_interactive_sql_pattern(): string
{
    return "(addEventListener[[:space:]]*[(][[:space:]]*['\"](pointerdown|pointerup|pointermove|mousedown|mouseup|mousemove|touchstart|touchmove|touchend|click)|on(click|mousedown|mouseup|mousemove|touchstart|touchmove|touchend|pointerdown|pointermove|pointerup)[[:space:]]*=)";
}

function art_piece_c2_interactive_backfill_sql(): string
{
    $pattern = str_replace("'", "\\'", art_piece_c2_interactive_sql_pattern());

    return "UPDATE art_piece_versions
SET generation_mode = 'c2_interactive'
WHERE engine = 'c2'
  AND (generation_mode IS NULL OR generation_mode = '' OR generation_mode = 'c2')
  AND LOWER(CONCAT(COALESCE(generated_code, ''), '\n', COALESCE(html_code, ''))) REGEXP '{$pattern}'";
}

function art_piece_version_generation_mode(array $version, array $piece = []): string
{
    $stored = trim((string) ($version['generation_mode'] ?? ''));
    if ($stored !== '' && in_array($stored, art_piece_supported_generation_modes(), true)) {
        return $stored;
    }

    $engine = strtolower((string) ($version['engine'] ?? $piece['engine'] ?? 'p5'));
    if ($engine === 'c2' && preg_match(art_piece_c2_interactive_pattern(), (string) ($version['generated_code'] ?? '') . "\n" . (string) ($version['html_code'] ?? '')) === 1) {
        return 'c2_interactive';
    }

    return art_piece_normalize_generation_mode($engine, 'p5');
}

function art_piece_effective_generation_mode_label(array $piece, ?array $version = null): string
{
    return art_piece_generation_mode_label(
        art_piece_version_generation_mode($version ?? (array) ($piece['current_version'] ?? []), $piece)
    );
}

function art_piece_is_c2_interactive_code(string $code, ?string $html = null): bool
{
    return preg_match(
        art_piece_c2_interactive_pattern(),
        $code . "\n" . (string) $html
    ) === 1;
}

function art_piece_version_base_columns(bool $includeGenerationMode = true, bool $includeSonic = false, bool $includeCameraOverlay = false): array
{
    $columns = [
        'v.id',
        'v.art_piece_id',
        'v.version_number',
        'v.prompt',
        'v.structured_spec',
        'v.html_code',
        'v.css_code',
        'v.generated_code',
        'v.engine',
        'v.generation_vendor',
        'v.generation_model',
    ];

    if ($includeGenerationMode) {
        $columns[] = 'v.generation_mode';
    }

    $columns[] = 'v.validation_status';
    $columns[] = 'v.generation_attempt_count';
    $columns[] = 'v.notes';

    if ($includeSonic) {
        $columns[] = 'v.sonic_params';
    }

    if ($includeCameraOverlay) {
        $columns[] = 'v.camera_overlay';
        // camera_placement rides the camera_overlay flag: both ship in the
        // same feature and the extra probe keeps older deployments (pulled
        // code, setup script not yet run) selectable.
        if (function_exists('ah_column_exists') && ah_column_exists('art_piece_versions', 'camera_placement')) {
            $columns[] = 'v.camera_placement';
        }
        foreach (['immersive_camera_overlay', 'immersive_camera_placement', 'regular_hand_motion'] as $surfaceColumn) {
            if (function_exists('ah_column_exists') && ah_column_exists('art_piece_versions', $surfaceColumn)) {
                $columns[] = 'v.' . $surfaceColumn;
            }
        }
    }

    return $columns;
}

function art_piece_version_select_columns(bool $includeGenerationMode = true, bool $includeCreatedAt = false, bool $includeDraftMeta = false, bool $includeSonic = false, bool $includeCameraOverlay = false): string
{
    $columns = art_piece_version_base_columns($includeGenerationMode, $includeSonic, $includeCameraOverlay);

    if ($includeCreatedAt) {
        $columns[] = 'v.created_at';
    }

    $columns[] = 'v.ai_profile_id';
    $columns[] = 'v.ai_persona_id';

    if ($includeDraftMeta) {
        $columns[] = 'v.is_draft_attempt';
        $columns[] = 'v.attempt_sequence_token';
    }

    $columns[] = 'uavs.profile_name AS ai_profile_name';
    $columns[] = 'ap.name AS ai_persona_name';

    return implode(",\n                    ", $columns);
}

function art_piece_version_storage_columns(bool $includeGenerationMode = true, bool $includeSonic = false, bool $includeCameraOverlay = false): array
{
    $columns = [
        'art_piece_id',
        'version_number',
        'prompt',
        'structured_spec',
        'html_code',
        'css_code',
        'generated_code',
        'engine',
        'generation_vendor',
        'generation_model',
    ];

    if ($includeGenerationMode) {
        $columns[] = 'generation_mode';
    }

    $columns = array_merge($columns, [
        'validation_status',
        'generation_attempt_count',
        'notes',
        'ai_profile_id',
        'ai_persona_id',
        'is_draft_attempt',
        'attempt_sequence_token',
    ]);

    // sonic_params is appended last so storage values stay aligned; only
    // included when the (optional) column exists on this deployment.
    if ($includeSonic) {
        $columns[] = 'sonic_params';
    }

    if ($includeCameraOverlay) {
        $columns[] = 'camera_overlay';
        if (function_exists('ah_column_exists') && ah_column_exists('art_piece_versions', 'camera_placement')) {
            $columns[] = 'camera_placement';
        }
        foreach (['immersive_camera_overlay', 'immersive_camera_placement', 'regular_hand_motion'] as $surfaceColumn) {
            if (function_exists('ah_column_exists') && ah_column_exists('art_piece_versions', $surfaceColumn)) {
                $columns[] = $surfaceColumn;
            }
        }
    }

    return $columns;
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

/**
 * Coarse media kind derived from a media_files.mime_type prefix, used to
 * decide whether a selected media reference is compatible with the piece's
 * engine. 'model' covers GLTF/GLB (glTF's JSON variant is application/json
 * or model/gltf+json depending on how it was uploaded, so both are matched
 * explicitly rather than relying on a mime prefix).
 */
function art_piece_media_kind_from_mime(?string $mimeType): string
{
    $mimeType = strtolower(trim((string) $mimeType));
    if ($mimeType === 'model/gltf-binary' || $mimeType === 'model/gltf+json') {
        return 'model';
    }
    if (str_starts_with($mimeType, 'video/')) {
        return 'video';
    }
    if (str_starts_with($mimeType, 'image/')) {
        return 'image';
    }
    if (str_starts_with($mimeType, 'audio/')) {
        return 'audio';
    }
    return 'other';
}

/**
 * Engines that can actually load a given media kind as part of the generated
 * piece. 3D models require Three.js/A-Frame's GLTFLoader capability (see
 * art_piece_model_capability_prompt()); every engine's base system prompt
 * only knows how to load images (loadImage/TextureLoader/<img>/SVG <image>).
 * Video/audio have no generation-time loading path on any engine today.
 */
function art_piece_engine_supports_media_kind(string $engine, string $kind): bool
{
    return match ($kind) {
        'model' => in_array($engine, ['three', 'aframe'], true),
        'image' => true,
        default => false, // video, audio, other: no engine can load these into generated code today
    };
}

/**
 * Parses the structured "Add media reference" picker payload (a JSON array
 * of {media_id, intent_text} submitted as media_refs_json) into a resolved
 * list carrying each media asset's mime type and canonical /media/{id} ref.
 * Silently skips rows with a missing/deleted media_id — the picker only ever
 * offers existing assets, so a missing row here means the asset was deleted
 * out from under an in-progress form, not something to hard-fail on.
 */
function art_piece_resolve_structured_media_refs(mixed $rawRefsJson): array
{
    if ($rawRefsJson === null || $rawRefsJson === '') {
        return [];
    }
    $decoded = is_array($rawRefsJson) ? $rawRefsJson : json_decode((string) $rawRefsJson, true);
    if (!is_array($decoded)) {
        return [];
    }

    $resolved = [];
    foreach ($decoded as $row) {
        if (!is_array($row)) {
            continue;
        }
        $mediaId = (int) ($row['media_id'] ?? 0);
        if ($mediaId <= 0 || !class_exists('MediaFile')) {
            continue;
        }
        $media = MediaFile::find($mediaId);
        if (!$media || ($media['deleted_at'] ?? null) !== null) {
            continue;
        }
        $resolved[] = [
            'media_id' => $mediaId,
            'intent_text' => trim((string) ($row['intent_text'] ?? '')),
            'mime_type' => (string) ($media['mime_type'] ?? ''),
            'original_name' => (string) ($media['original_name'] ?? ''),
            'ref' => '/media/' . $mediaId,
        ];
    }
    return $resolved;
}

/**
 * Rule 6 gate: rejects generation up front, with a specific per-asset
 * message, rather than letting an incompatible media kind + engine
 * combination silently fail downstream (the exact failure mode behind
 * pieces #114-116 — a GLB referenced under conditions where nothing in the
 * generated code could actually load it as geometry).
 */
function art_piece_assert_media_refs_engine_compatible(string $engine, array $resolvedRefs): void
{
    foreach ($resolvedRefs as $ref) {
        $kind = art_piece_media_kind_from_mime($ref['mime_type'] ?? '');
        if ($kind === 'image') {
            continue; // every engine can load images
        }
        if (art_piece_engine_supports_media_kind($engine, $kind)) {
            continue;
        }
        $label = trim((string) ($ref['original_name'] ?? '')) !== ''
            ? $ref['original_name'] . ' (' . $ref['ref'] . ')'
            : $ref['ref'];
        $kindLabel = match ($kind) {
            'model' => '3D model',
            'video' => 'video',
            'audio' => 'audio file',
            default => 'unsupported media type',
        };
        $suggestion = $kind === 'model'
            ? ' Select the Three.js or A-Frame engine to use it.'
            : '';
        throw new RuntimeException("Media {$label} is a {$kindLabel} — this cannot be used with the {$engine} engine.{$suggestion}");
    }
}

/**
 * Builds the appendable prompt text describing each structured media
 * reference and the admin's stated intent for it, so the AI receives
 * unambiguous per-asset guidance instead of having to infer usage from
 * free-text prose. Returns '' when there are no structured refs (legacy
 * free-text prompts still flow through art_piece_media_policy_prompt() only).
 */
function art_piece_structured_media_refs_prompt(array $resolvedRefs): string
{
    if ($resolvedRefs === []) {
        return '';
    }
    $lines = [];
    foreach ($resolvedRefs as $ref) {
        $intent = trim((string) ($ref['intent_text'] ?? ''));
        $lines[] = $intent !== ''
            ? "- {$ref['ref']}: {$intent}"
            : "- {$ref['ref']}: (no specific usage instruction given — use your judgment)";
    }
    return "SELECTED MEDIA REFERENCES: The user explicitly selected the following CMS media for this piece via the media picker (not by naming them in prose). Use exactly these paths and follow each one's stated usage:\n" . implode("\n", $lines);
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
            "Use CMS images ONLY when the user prompt explicitly names a specific image/photo ID, media asset ID, or path. When explicitly requested, use p5's preload pattern with that exact path, such as `img = p.loadImage('/image/{id}')` for image/photo prompts or `img = p.loadImage('/api/media-assets/{id}')` for media asset prompts, and draw it with `p.image(...)` or a local `drawImageCover(...)` helper. Allowed media paths are `/image/{id}`, `/media/...`, and `/api/media-assets/{id}` only. Image assets define the source; `p.image(..., width, height, sx, sy, sw, sh)` defines rendered size and cover cropping.",
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
            "Use CMS images ONLY when the user prompt explicitly names a specific image/photo ID, media asset ID, or path. When explicitly requested, load that exact path through the runtime helpers only, such as `const img = runtime.loadImage('/image/{id}');` for image/photo prompts or `const img = runtime.loadImage('/api/media-assets/{id}');` for media asset prompts, then draw it with `runtime.drawImage(...)` or `runtime.drawImageCover(...)`. `runtime.loadImage()` returns a Promise that resolves to the image once loaded — you may `await` it, chain `.then()`, or pass its return value directly to the draw helpers, which safely skip drawing until the image is loaded. Allowed media paths are `/image/{id}`, `/media/...`, and `/api/media-assets/{id}` only. Image assets define the source; `runtime.drawImage(...)` and `runtime.drawImageCover(...)` define rendered size. Do NOT call canvas.getContext(), drawImage(), new Image(), fetch(), or external URLs yourself.",
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
            "Use CMS images ONLY when the user prompt explicitly names a specific image/photo ID, media asset ID, or path. When explicitly requested, load that exact path through the runtime helpers only, such as `const img = runtime.loadImage('/image/{id}');` for image/photo prompts or `const img = runtime.loadImage('/api/media-assets/{id}');` for media asset prompts, then draw it with `runtime.drawImage(...)` or `runtime.drawImageCover(...)`. `runtime.loadImage()` returns a Promise that resolves to the image once loaded — you may `await` it, chain `.then()`, or pass its return value directly to the draw helpers, which safely skip drawing until the image is loaded. Allowed media paths are `/image/{id}`, `/media/...`, and `/api/media-assets/{id}` only. Image assets define the source; `runtime.drawImage(...)` and `runtime.drawImageCover(...)` define rendered size. Do NOT call canvas.getContext(), drawImage(), new Image(), fetch(), or external URLs yourself.",
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
            "Use CMS images ONLY when the user prompt explicitly names a specific image/photo ID, media asset ID, or path. When explicitly requested, load that exact path as a texture, such as `const texture = new THREE.TextureLoader().load('/image/{id}');` for image/photo prompts or `const texture = new THREE.TextureLoader().load('/api/media-assets/{id}');` for media asset prompts, then size the geometry to control how it appears. Allowed media paths are `/image/{id}`, `/media/...`, and `/api/media-assets/{id}` only. For full-frame requested image backgrounds, compute `backgroundHeight` and `backgroundWidth` from camera FOV, aspect, and distance, apply the texture to `new THREE.PlaneGeometry(backgroundWidth, backgroundHeight)`, and configure texture repeat/offset cover behavior.",
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
            "Use CMS images ONLY when the user prompt explicitly names a specific image/photo ID, media asset ID, or path. When explicitly requested, place that exact path in an `<img id=\"asset-id\" src=\"/image/{id}\">` for image/photo prompts or `<img id=\"asset-id\" src=\"/api/media-assets/{id}\">` for media asset prompts inside one `<a-assets>` block, then reference it with `src=\"#asset-id\"` or `material=\"src: #asset-id\"`. Allowed image paths are `/image/{id}`, `/media/...`, and `/api/media-assets/{id}` only. Image assets define the source; rendered A-Frame entities define size. Do NOT put width/height on the `<img>` asset expecting it to resize the scene. Set width/height on the `<a-plane>` or entity that references it; for full-frame requested image backgrounds, compute and set the plane's `backgroundWidth` and `backgroundHeight` from camera FOV, aspect, and distance in `window.sketch`.",
            "CRITICAL: Any `<a-plane>` or `<a-image>` that displays an image asset MUST preserve the image's natural aspect ratio — once the asset image has loaded, set the entity's `height` to `width / (img.naturalWidth / img.naturalHeight)` in `window.sketch` instead of hardcoding an arbitrary width/height pair that stretches the image.",
            "CRITICAL: Never rotate a flat backdrop plane around the Y (or X) axis — a spinning flat plane periodically turns edge-on to the camera and disappears. Animate backdrops only with position drift, scale breathing, opacity, or Z-axis (roll) rotation.",
            "Every element inside `<a-assets>` MUST be written with an explicit closing tag (e.g. `<img ...>` is fine, but any custom asset element must be `<tag ...></tag>`) — unclosed asset elements corrupt the assets block.",
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
            'Use CMS images ONLY when the user prompt explicitly names a specific image/photo ID, media asset ID, or path. When explicitly requested, use that exact path in an SVG `<image href="/image/{id}" ... preserveAspectRatio="xMidYMid slice" />` element for image/photo prompts or `<image href="/api/media-assets/{id}" ... preserveAspectRatio="xMidYMid slice" />` for media asset prompts, and set x/y/width/height for the requested placement. Allowed media paths are `/image/{id}`, `/media/...`, and `/api/media-assets/{id}` only. Image assets define the source; SVG `<image>` x/y/width/height attributes define rendered size.',
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
    // Optional Tone.js sonification parameters (piece-sound feature). Extracted
    // before the JS fallback below so a ```sonic``` block is never mistaken for
    // the JS code block.
    $sonicParams   = $extract(['sonic']);

    // Fallback: any fenced block whose language tag is not html, css, or sonic
    if ($generatedCode === null) {
        preg_match_all('/```([a-zA-Z]*)\s*\n([\s\S]*?)```/', $raw, $allBlocks, PREG_SET_ORDER);
        foreach ($allBlocks as $block) {
            $lang    = strtolower($block[1]);
            $content = trim($block[2]);
            if (!in_array($lang, ['html', 'css', 'sonic'], true) && $content !== '') {
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
        'sonicParams'   => $sonicParams,
    ];
}

/**
 * Validates and normalizes the optional Tone.js sonic-parameter JSON emitted by
 * generation/refine. Deliberately SOFT-FAILING and decoupled from code
 * validation: a malformed, empty, or missing block returns null ("no sound")
 * and must NEVER block otherwise-valid piece code from saving. Unknown
 * instrument/scale values are coerced to the nearest supported option and tempo
 * is clamped, honoring the "approximate rather than fail" intent.
 *
 * Returns a canonical JSON string ({tempo, scale, instrument, feel}) to store,
 * or null when there is nothing usable.
 */
function validate_art_piece_sonic_params(?string $sonicJson): ?string
{
    if ($sonicJson === null || trim($sonicJson) === '') {
        return null;
    }

    $decoded = json_decode(trim($sonicJson), true);
    if (!is_array($decoded)) {
        return null; // not usable → no sound, never an error
    }

    // Supported Tone.js instrument families and musical scales the runtime maps.
    $instruments = ['synth', 'amsynth', 'fmsynth', 'membranesynth', 'metalsynth', 'plucksynth', 'duosynth'];
    $scales = ['major', 'minor', 'pentatonic', 'chromatic', 'dorian', 'phrygian', 'lydian', 'mixolydian', 'wholetone'];

    $nearest = static function (string $value, array $allowed, string $default): string {
        $value = strtolower(preg_replace('/[^a-z0-9]/i', '', $value) ?? '');
        if ($value === '') {
            return $default;
        }
        foreach ($allowed as $option) {
            if ($option === $value) {
                return $option;
            }
        }
        foreach ($allowed as $option) {
            if (str_contains($option, $value) || str_contains($value, $option)) {
                return $option;
            }
        }
        return $default;
    };

    $instrument = $nearest((string) ($decoded['instrument'] ?? ''), $instruments, 'synth');
    $scale = $nearest((string) ($decoded['scale'] ?? ''), $scales, 'major');

    $tempo = $decoded['tempo'] ?? 90;
    $tempo = is_numeric($tempo) ? (int) round((float) $tempo) : 90;
    $tempo = max(40, min(220, $tempo)); // clamp to a sane BPM range

    $feel = trim((string) ($decoded['feel'] ?? ''));
    if (mb_strlen($feel) > 400) {
        $feel = mb_substr($feel, 0, 400);
    }

    $enabled = isset($decoded['enabled']) ? (bool) $decoded['enabled'] : true;

    return json_encode([
        'tempo' => $tempo,
        'scale' => $scale,
        'instrument' => $instrument,
        'feel' => $feel,
        'enabled' => $enabled,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/**
 * Validates/normalizes the mechanical, non-AI-authored "extras" sub-object
 * nested inside sonic_params: per-piece public-visibility toggles for each
 * sonification voice, a default volume, and admin-only synth tuning
 * (octave range / filter cutoff / resonance). Deliberately SOFT-FAILING like
 * validate_art_piece_sonic_params() — always returns a fully-defaulted array,
 * never null, so a malformed/missing extras block never blocks a save.
 *
 * This is intentionally a sibling function, not a merge into
 * validate_art_piece_sonic_params() itself: keeping extras out of that
 * function's own canonicalization means art_piece_sonic_params_equal()
 * (which re-validates through that function before comparing) naturally
 * ignores extras when deciding whether AI-authored sonic content changed —
 * admin-only Audio-tab tweaks must never look like a "sonic changed" event
 * for version-forking purposes.
 */
function validate_art_piece_sonic_extras(mixed $decoded): array
{
    $extras = is_array($decoded) ? $decoded : [];
    $voicesIn = is_array($extras['voices'] ?? null) ? $extras['voices'] : [];

    $volume = $extras['default_volume'] ?? 50;
    $volume = is_numeric($volume) ? (int) round((float) $volume) : 50;
    $volume = max(0, min(100, $volume));

    $synthIn = is_array($extras['synth'] ?? null) ? $extras['synth'] : [];
    $filterTypes = ['lowpass', 'highpass', 'bandpass'];
    $filterType = in_array($synthIn['filter_type'] ?? null, $filterTypes, true) ? $synthIn['filter_type'] : 'lowpass';

    $octaveMin = $synthIn['octave_min'] ?? 1;
    $octaveMin = is_numeric($octaveMin) ? (int) round((float) $octaveMin) : 1;
    $octaveMin = max(-1, min(7, $octaveMin));

    $octaveMax = $synthIn['octave_max'] ?? 5;
    $octaveMax = is_numeric($octaveMax) ? (int) round((float) $octaveMax) : 5;
    $octaveMax = max(-1, min(7, $octaveMax));
    if ($octaveMax < $octaveMin) {
        [$octaveMin, $octaveMax] = [$octaveMax, $octaveMin];
    }

    $cutoff = $synthIn['filter_cutoff'] ?? 8000;
    $cutoff = is_numeric($cutoff) ? (float) $cutoff : 8000.0;
    $cutoff = max(20.0, min(20000.0, $cutoff));

    $resonance = $synthIn['filter_resonance'] ?? 1;
    $resonance = is_numeric($resonance) ? (float) $resonance : 1.0;
    $resonance = max(0.1, min(20.0, $resonance));

    // Admin-only effects chain, inserted between the shared filter and the
    // master volume bus in sonic-controller.js's ensureSynth(). All default
    // 'enabled' => false so existing pieces are unaffected until an admin
    // opts in. See docs/dependencies.md's Tone.js section for the shape.
    $effectsIn = is_array($synthIn['effects'] ?? null) ? $synthIn['effects'] : [];

    $distortionIn = is_array($effectsIn['distortion'] ?? null) ? $effectsIn['distortion'] : [];
    $distortionAmount = $distortionIn['amount'] ?? 0.4;
    $distortionAmount = is_numeric($distortionAmount) ? (float) $distortionAmount : 0.4;
    $distortionAmount = max(0.0, min(1.0, $distortionAmount));

    $chorusIn = is_array($effectsIn['chorus'] ?? null) ? $effectsIn['chorus'] : [];
    $chorusDepth = $chorusIn['depth'] ?? 0.5;
    $chorusDepth = is_numeric($chorusDepth) ? (float) $chorusDepth : 0.5;
    $chorusDepth = max(0.0, min(1.0, $chorusDepth));
    $chorusRate = $chorusIn['rate'] ?? 1.5;
    $chorusRate = is_numeric($chorusRate) ? (float) $chorusRate : 1.5;
    $chorusRate = max(0.1, min(20.0, $chorusRate));

    $tremoloIn = is_array($effectsIn['tremolo'] ?? null) ? $effectsIn['tremolo'] : [];
    $tremoloDepth = $tremoloIn['depth'] ?? 0.5;
    $tremoloDepth = is_numeric($tremoloDepth) ? (float) $tremoloDepth : 0.5;
    $tremoloDepth = max(0.0, min(1.0, $tremoloDepth));
    $tremoloRate = $tremoloIn['rate'] ?? 5.0;
    $tremoloRate = is_numeric($tremoloRate) ? (float) $tremoloRate : 5.0;
    $tremoloRate = max(0.1, min(20.0, $tremoloRate));

    $pitchShiftIn = is_array($effectsIn['pitch_shift'] ?? null) ? $effectsIn['pitch_shift'] : [];
    $pitchSemitones = $pitchShiftIn['semitones'] ?? 0;
    $pitchSemitones = is_numeric($pitchSemitones) ? (int) round((float) $pitchSemitones) : 0;
    $pitchSemitones = max(-24, min(24, $pitchSemitones));

    $bitcrusherIn = is_array($effectsIn['bitcrusher'] ?? null) ? $effectsIn['bitcrusher'] : [];
    $bits = $bitcrusherIn['bits'] ?? 4;
    $bits = is_numeric($bits) ? (int) round((float) $bits) : 4;
    $bits = max(1, min(16, $bits));

    $flangerIn = is_array($effectsIn['flanger'] ?? null) ? $effectsIn['flanger'] : [];
    $flangerDepth = $flangerIn['depth'] ?? 0.006;
    $flangerDepth = is_numeric($flangerDepth) ? (float) $flangerDepth : 0.006;
    $flangerDepth = max(0.0, min(0.02, $flangerDepth));
    $flangerRate = $flangerIn['rate'] ?? 0.25;
    $flangerRate = is_numeric($flangerRate) ? (float) $flangerRate : 0.25;
    $flangerRate = max(0.05, min(5.0, $flangerRate));
    $flangerFeedback = $flangerIn['feedback'] ?? 0.5;
    $flangerFeedback = is_numeric($flangerFeedback) ? (float) $flangerFeedback : 0.5;
    $flangerFeedback = max(0.0, min(0.95, $flangerFeedback));

    $ringModIn = is_array($effectsIn['ring_mod'] ?? null) ? $effectsIn['ring_mod'] : [];
    $ringModFreq = $ringModIn['frequency'] ?? 440.0;
    $ringModFreq = is_numeric($ringModFreq) ? (float) $ringModFreq : 440.0;
    $ringModFreq = max(1.0, min(5000.0, $ringModFreq));

    // Admin-selected uploaded audio file to loop as the ambient voice,
    // instead of a synthesized instrument (sonic-controller.js builds a
    // Tone.Player for it when enabled). A separate field from `instrument`
    // (not an overload of it) so every SONIC_INSTRUMENTS-keyed code path —
    // the visitor-facing per-voice instrument picker, buildVoice(),
    // localStorage overrides — stays untouched for movement/melodic and for
    // any piece that doesn't opt in. media_id is only kept if it actually
    // refers to an existing, active, audio-kind media file; otherwise
    // silently dropped back to "off" rather than erroring, matching this
    // function's general fail-safe-to-default posture.
    $ambientSampleIn = is_array($synthIn['ambient_sample'] ?? null) ? $synthIn['ambient_sample'] : [];
    $ambientSampleMediaId = $ambientSampleIn['media_id'] ?? null;
    $ambientSampleMediaId = is_numeric($ambientSampleMediaId) ? (int) $ambientSampleMediaId : null;
    if ($ambientSampleMediaId !== null && $ambientSampleMediaId <= 0) {
        $ambientSampleMediaId = null;
    }
    if ($ambientSampleMediaId !== null && class_exists('MediaFile') && !MediaFile::isActiveOfKind($ambientSampleMediaId, 'audio')) {
        $ambientSampleMediaId = null;
    }
    $ambientSampleEnabled = (bool) ($ambientSampleIn['enabled'] ?? false) && $ambientSampleMediaId !== null;

    return [
        'default_volume' => $volume,
        'voices' => [
            'ambient' => !isset($voicesIn['ambient']) || (bool) $voicesIn['ambient'],
            'movement' => !isset($voicesIn['movement']) || (bool) $voicesIn['movement'],
            'melodic' => !isset($voicesIn['melodic']) || (bool) $voicesIn['melodic'],
            'hand_tracking' => (bool) ($voicesIn['hand_tracking'] ?? false),
            // Default true, matching the admin checkbox and the capability
            // contract: hand control is offered whenever the piece's camera
            // permission (or the hand-tracking voice) unlocks it, unless the
            // admin explicitly turns it off.
        ],
        'synth' => [
            'octave_min' => $octaveMin,
            'octave_max' => $octaveMax,
            'filter_cutoff' => $cutoff,
            'filter_resonance' => $resonance,
            'filter_type' => $filterType,
            'effects' => [
                'distortion' => ['enabled' => (bool) ($distortionIn['enabled'] ?? false), 'amount' => $distortionAmount],
                'chorus' => ['enabled' => (bool) ($chorusIn['enabled'] ?? false), 'depth' => $chorusDepth, 'rate' => $chorusRate],
                'tremolo' => ['enabled' => (bool) ($tremoloIn['enabled'] ?? false), 'depth' => $tremoloDepth, 'rate' => $tremoloRate],
                'pitch_shift' => ['enabled' => (bool) ($pitchShiftIn['enabled'] ?? false), 'semitones' => $pitchSemitones],
                'bitcrusher' => ['enabled' => (bool) ($bitcrusherIn['enabled'] ?? false), 'bits' => $bits],
                'flanger' => ['enabled' => (bool) ($flangerIn['enabled'] ?? false), 'depth' => $flangerDepth, 'rate' => $flangerRate, 'feedback' => $flangerFeedback],
                'ring_mod' => ['enabled' => (bool) ($ringModIn['enabled'] ?? false), 'frequency' => $ringModFreq],
            ],
            'ambient_sample' => [
                'enabled' => $ambientSampleEnabled,
                'media_id' => $ambientSampleMediaId,
            ],
        ],
    ];
}

/**
 * Merges a validated extras array into an AI-validated sonic_params JSON
 * string as a sibling `extras` key — the actual value written to the DB
 * column. Returns null when there's no AI-validated sonic content at all
 * (extras alone, with no sonic_params, means "no sound" — nothing to attach
 * extras to).
 */
function art_piece_sonic_json_merge_extras(?string $aiValidatedJson, array $extras): ?string
{
    if ($aiValidatedJson === null || trim($aiValidatedJson) === '') {
        return null;
    }
    $decoded = json_decode($aiValidatedJson, true);
    if (!is_array($decoded)) {
        return null;
    }
    $decoded['extras'] = $extras;
    return json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function art_piece_sonic_params_from_feel(?string $feel): ?string
{
    $feel = trim((string) $feel);
    if ($feel === '') {
        return null;
    }
    if (mb_strlen($feel) > 400) {
        $feel = mb_substr($feel, 0, 400);
    }

    $lower = mb_strtolower($feel);
    $scale = 'major';
    foreach (['wholetone', 'mixolydian', 'lydian', 'phrygian', 'dorian', 'chromatic', 'pentatonic', 'minor', 'major'] as $candidate) {
        if (str_contains($lower, $candidate)) {
            $scale = $candidate;
            break;
        }
    }

    $instrument = 'synth';
    if (str_contains($lower, 'theremin') || str_contains($lower, 'fm synth') || str_contains($lower, 'fmsynth')) {
        $instrument = 'fmsynth';
    } elseif (str_contains($lower, 'pluck')) {
        $instrument = 'plucksynth';
    } elseif (str_contains($lower, 'metal') || str_contains($lower, 'bell')) {
        $instrument = 'metalsynth';
    } elseif (str_contains($lower, 'membrane') || str_contains($lower, 'drum')) {
        $instrument = 'membranesynth';
    } elseif (str_contains($lower, 'duo')) {
        $instrument = 'duosynth';
    } elseif (str_contains($lower, 'am synth') || str_contains($lower, 'amsynth')) {
        $instrument = 'amsynth';
    }

    $tempo = 90;
    if (preg_match('/\b([4-9][0-9]|1[0-9]{2}|2[01][0-9]|220)\s*(?:bpm|beats?\s+per\s+minute)?\b/i', $feel, $m)) {
        $tempo = (int) $m[1];
    } elseif (preg_match('/\b(slow|ambient|drone|hushed)\b/i', $feel)) {
        $tempo = 72;
    } elseif (preg_match('/\b(fast|quick|rapid|urgent|bright|energetic)\b/i', $feel)) {
        $tempo = 128;
    }

    return validate_art_piece_sonic_params(json_encode([
        'tempo' => $tempo,
        'scale' => $scale,
        'instrument' => $instrument,
        'feel' => $feel,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function art_piece_sonic_feel(?string $sonicJson): string
{
    if ($sonicJson === null || trim($sonicJson) === '') {
        return '';
    }

    $decoded = json_decode($sonicJson, true);
    if (!is_array($decoded)) {
        return '';
    }

    return trim((string) ($decoded['feel'] ?? ''));
}

function art_piece_sonic_params_equal(?string $a, ?string $b): bool
{
    return (validate_art_piece_sonic_params($a) ?? '') === (validate_art_piece_sonic_params($b) ?? '');
}

function art_piece_sonic_params_supported(): bool
{
    if (!function_exists('ah_column_exists')) {
        return true;
    }
    try {
        return ah_column_exists('art_piece_versions', 'sonic_params');
    } catch (Throwable) {
        return false;
    }
}

function art_piece_camera_overlay_supported(): bool
{
    if (!function_exists('ah_column_exists')) {
        return true;
    }
    try {
        return ah_column_exists('art_piece_versions', 'camera_overlay');
    } catch (Throwable) {
        return false;
    }
}

/**
 * Default authoring value for camera overlays when no explicit Metadata-tab
 * choice has been stored yet. The camera OPTION (a visitor-activated toggle;
 * nothing auto-starts) is available by default on every engine — 2D engines
 * render it as a pointer-transparent video overlay, Three.js/A-Frame as an
 * in-scene background quad (2026-07-12; the p5/c2/svg-only default shipped
 * 2026-07-11).
 */
function art_piece_camera_overlay_default(string $generationMode): ?int
{
    return 1;
}

/**
 * Default camera placement per engine when camera_placement is NULL:
 * Three.js/A-Frame render the feed as an in-scene, camera-attached blended
 * quad (a background), the 2D engines as a DOM <video> overlay.
 */
function art_piece_camera_placement_default(string $generationMode): string
{
    $generationMode = art_piece_normalize_generation_mode($generationMode, $generationMode);
    return in_array($generationMode, ['three', 'aframe'], true) ? 'background' : 'overlay';
}

/**
 * Appendable guidance telling the model how to load an uploaded 3D model that
 * the user referenced by an allowed /media/{id} path. Returned only for the
 * Three.js and A-Frame engines (empty string otherwise) and only meant to be
 * appended when the media_models feature is enabled. For A-Frame this grants a
 * narrow, explicit exception to the base prompt's <a-asset-item> ban.
 */
function art_piece_model_capability_prompt(string $engine): string
{
    return match ($engine) {
        'three' => implode(' ', [
            "3D MODEL CAPABILITY: If the user's prompt references an uploaded 3D model at an allowed /media/{id} path (GLTF/GLB), load it with the runtime-provided loader — `new THREE.GLTFLoader().load('/media/{id}', gltf => { ... })`.",
            "Do NOT use fetch, XMLHttpRequest, or import statements; THREE.GLTFLoader is already provided on the THREE object. Preserve the loaded model's embedded materials, textures, UVs, transparency, vertex colors, and color data; do NOT replace loaded meshes with new MeshBasicMaterial, MeshStandardMaterial, MeshPhongMaterial, or similar materials unless the user explicitly asks to restyle the model.",
            "CRITICAL — AUTO-FIT THE MODEL, do not assume it already matches the scene's scale: an uploaded GLB/GLTF can carry ANY real-world scale and any pivot/origin from whatever tool exported it. Loading it and adding it to the scene with no adjustment routinely makes it render invisibly tiny, absurdly oversized (camera ends up inside the mesh), or offset far outside the camera's view — which looks exactly like nothing loaded at all, even though the load succeeded. Inside the load callback, ALWAYS compute `const box = new THREE.Box3().setFromObject(gltf.scene); const size = box.getSize(new THREE.Vector3()); const center = box.getCenter(new THREE.Vector3());`, then recenter the model with `gltf.scene.position.sub(center);` and uniformly rescale it with `const maxDim = Math.max(size.x, size.y, size.z); const targetSize = 3; gltf.scene.scale.multiplyScalar(targetSize / maxDim);` (adjust targetSize to whatever scale matches the rest of the composition), BEFORE adding it to the group/scene or letting the camera frame it. Skipping this auto-fit step is the single most common reason a correctly-loaded model appears not to render at all.",
            "You may set castShadow/receiveShadow, additional transform/rotate/animate on top of the auto-fit, add lights, and frame the camera around the loaded model.",
        ]),
        'aframe' => implode(' ', [
            "3D MODEL CAPABILITY: If the user's prompt references an uploaded 3D model at an allowed /media/{id} path (GLTF/GLB), you MAY — as a specific exception to the no-<a-asset-item> rule — include a single `<a-assets>` block containing one `<a-asset-item id=\"model\" src=\"/media/{id}\">`, then place `<a-entity gltf-model=\"#model\">` in the scene.",
            "This exception applies ONLY to an allowed /media/{id} 3D-model reference — do NOT load any other external URL or remote asset, and keep all other A-Frame safety rules.",
            "CRITICAL — AUTO-FIT THE MODEL: an uploaded GLB/GLTF can carry any real-world scale/origin from whatever tool exported it, so a fixed guessed `scale`/`position` attribute routinely renders it invisibly tiny, absurdly oversized, or outside the camera's view — indistinguishable from nothing loading at all. In `window.sketch`, listen for the entity's `model-loaded` event, then inside the handler get the loaded mesh with `const mesh = event.detail.model || entity.getObject3D('mesh'); const box = new THREE.Box3().setFromObject(mesh); const size = box.getSize(new THREE.Vector3()); const center = box.getCenter(new THREE.Vector3());`, recenter with `mesh.position.sub(center);`, and uniformly rescale the ENTITY (not the mesh) via `entity.setAttribute('scale', ...)` so its largest dimension matches a reasonable on-screen size (e.g. 2-4 meters) relative to the rest of the scene, before relying on any static scale/position values.",
        ]),
        default => '',
    };
}

/**
 * Appendable guidance asking the model to emit a fourth ```sonic``` JSON block
 * describing Tone.js sonification parameters. Applies to EVERY engine: the
 * immersive view sonifies camera movement (in the gallery room for p5/c2/svg
 * pieces, and directly for three/aframe), so sound is a property of the
 * immersive view, not the engine. $feel carries the user's optional free-text
 * "describe the feel" input.
 */
function art_piece_sonic_capability_prompt(string $engine, string $feel = ''): string
{
    $feel = trim($feel);
    $feelLine = $feel !== ''
        ? "The user describes the desired feel as: \"" . str_replace('"', "'", $feel) . "\". Honor any scale/instrument/tempo they name; if something they ask for is not available, approximate it as closely as possible rather than omitting sound."
        : "Infer the mood from the piece prompt.";

    return implode(' ', [
        "SONIFICATION: In addition to the three code blocks, emit a FOURTH Markdown block ```sonic``` containing a single JSON object describing how movement in the immersive view should sound:",
        '`{"tempo": <BPM 40-220>, "scale": "major|minor|pentatonic|chromatic|dorian|phrygian|lydian|mixolydian|wholetone", "instrument": "synth|amsynth|fmsynth|membranesynth|metalsynth|plucksynth|duosynth", "feel": "<short mood phrase>"}`.',
        $feelLine,
        "The sonic block is DATA only — do NOT add any audio code, Tone.js, or Web Audio to the JS block; the runtime owns audio. If you cannot produce meaningful values, omit the sonic block entirely.",
    ]);
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
function art_piece_preflight_code(string $engine, string $code, ?string $html = null, ?string $css = null, ?string $generationMode = null): string
{
    $effectiveMode = art_piece_normalize_generation_mode($generationMode ?? $engine, $engine);
    $runtimeEngine = art_piece_generation_mode_to_engine($effectiveMode);
    $validatedCode = validate_art_piece_code($code);

    if ($runtimeEngine === 'c2') {
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

    if ($effectiveMode === 'c2_interactive' && !art_piece_is_c2_interactive_code($validatedCode, $html)) {
        throw new RuntimeException('Selected C2.js Interactive mode requires explicit pointer, mouse, touch, or click interaction hooks in the generated code.');
    }

    if ($runtimeEngine === 'three') {
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

    if ($runtimeEngine === 'aframe') {
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

    validate_art_piece_media_references($runtimeEngine, null, null, $validatedCode);

    // Static check for window.sketch definition. Requires an actual
    // assignment (a single `=` not followed by another `=`) rather than
    // just the identifier appearing anywhere — a stray
    // `typeof window.sketch === 'function'` guard with no real assignment
    // elsewhere must still fail, since the runtime silently no-ops (no
    // canvas, no error) when window.sketch isn't actually a function.
    if ($runtimeEngine !== 'svg' || $validatedCode !== 'window.sketch = () => {};') {
        if (!preg_match('/window\s*\.\s*sketch\s*=(?!=)/i', $validatedCode)) {
            throw new RuntimeException('Generated code did not define window.sketch');
        }
    }

    return $validatedCode;
}

function art_piece_preflight_document(string $engine, ?string $html, ?string $css, ?string $js, ?string $generationMode = null): array
{
    $effectiveMode = art_piece_normalize_generation_mode($generationMode ?? $engine, $engine);
    $runtimeEngine = art_piece_generation_mode_to_engine($effectiveMode);
    $html = trim((string) $html);
    $css = trim((string) $css);
    $js = trim((string) $js);

    if ($html === '' && $runtimeEngine !== 'svg') {
        throw new RuntimeException('HTML block is empty');
    }
    if ($js !== '') {
        art_piece_preflight_code($runtimeEngine, $js, $html, $css, $effectiveMode);
    } elseif ($runtimeEngine !== 'svg') {
        throw new RuntimeException('JavaScript block is empty');
    }

    if (in_array($runtimeEngine, art_piece_canvas_managed_engines(), true)) {
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

    if ($runtimeEngine === 'aframe') {
        if (!preg_match('/<a-scene\b[^>]*\bid\s*=\s*["\']scene["\']/i', $html)) {
            throw new RuntimeException('A-Frame HTML must contain one <a-scene id="scene" embedded> root.');
        }
        if (preg_match_all('/<a-scene\b/i', $html) !== 1) {
            throw new RuntimeException('A-Frame HTML must contain exactly one <a-scene> root.');
        }
        $aframeHtmlRules = [
            ['pattern' => '/<\/?(?:script|link|base|html|head|body|iframe|audio|video)\b/i', 'message' => 'A-Frame HTML cannot contain document, script, iframe, audio, video, or arbitrary asset-loading tags.'],
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

    if ($runtimeEngine === 'svg') {
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

    validate_art_piece_media_references($runtimeEngine, $html, $css, $js);

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

        if (preg_match_all('/url\s*\(\s*([\'"]?)\s*\1\s*\)/i', $content, $emptyUrlMatches, PREG_SET_ORDER)) {
            throw new RuntimeException("{$label} contains an empty url() reference. Empty resource URLs resolve to the document itself and break downloaded pieces under file://.");
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

    if ($html !== null && preg_match_all('/\b(?:src|href|xlink:href)\s*=\s*(["\'])([^"\']*)\1/i', $html, $attrMatches, PREG_SET_ORDER)) {
        foreach ($attrMatches as $match) {
            $src = trim((string) $match[2]);
            if ($src === '') {
                throw new RuntimeException('HTML contains an empty src/href/xlink:href attribute. Empty resource URLs resolve to the document itself and break downloaded pieces under file://.');
            }
            if (str_starts_with($src, '#') || str_starts_with($src, 'data:')) {
                continue;
            }
            if (!is_allowed_art_piece_media_src($src)) {
                throw new RuntimeException('HTML media attributes may only reference same-origin CMS media paths or local #asset ids.');
            }
        }
    }

    if ($engine === 'p5' && $js !== null) {
        validate_empty_literal_media_call($js, '/\bp\s*\.\s*loadImage\s*\(\s*(["\'])\1\s*\)/i', 'p5 loadImage()');
        validate_literal_media_call_urls($js, '/\bp\s*\.\s*loadImage\s*\(\s*(["\'])([^"\']+)\1/i', 'p5 loadImage()');
    }

    if ($engine === 'three' && $js !== null) {
        validate_empty_literal_media_call($js, '/\bTextureLoader\s*\(\s*\)\s*\.\s*load\s*\(\s*(["\'])\1\s*\)/i', 'Three.js TextureLoader.load()');
        validate_empty_literal_media_call($js, '/\.\s*load\s*\(\s*(["\'])\1\s*\)/i', 'Three.js asset loader calls');
        validate_literal_media_call_urls($js, '/\bTextureLoader\s*\(\s*\)\s*\.\s*load\s*\(\s*(["\'])([^"\']+)\1/i', 'Three.js TextureLoader.load()');
        validate_literal_media_call_urls($js, '/\.\s*load\s*\(\s*(["\'])([^"\']+)\1/i', 'Three.js asset loader calls');
        validate_three_gltf_material_preservation($js);
    }

    if ($engine === 'c2' && $js !== null) {
        validate_empty_literal_media_call($js, '/\bruntime\s*\.\s*loadImage\s*\(\s*(["\'])\1\s*\)/i', 'C2 runtime.loadImage()');
        validate_literal_media_call_urls($js, '/\bruntime\s*\.\s*loadImage\s*\(\s*(["\'])([^"\']+)\1/i', 'C2 runtime.loadImage()');
    }
}

function validate_empty_literal_media_call(string $code, string $pattern, string $label): void
{
    if (preg_match($pattern, $code)) {
        throw new RuntimeException("{$label} was called with an empty URL. Empty resource URLs resolve to the document itself and break downloaded pieces under file://.");
    }
}

function validate_three_gltf_material_preservation(string $js): void
{
    if (!preg_match('/GLTFLoader\s*\([^)]*\)\s*\.\s*load\s*\(\s*["\']\/media\/[0-9]+["\']/i', $js)
        && !preg_match('/\b[a-zA-Z_$][A-Za-z0-9_$]*\s*\.\s*load\s*\(\s*["\']\/media\/[0-9]+["\']/i', $js)
    ) {
        return;
    }

    if (!preg_match('/\.traverse\s*\(/i', $js)) {
        return;
    }

    if (preg_match('/\.\s*material\s*=\s*new\s+THREE\.(?:MeshBasicMaterial|MeshStandardMaterial|MeshPhongMaterial|MeshLambertMaterial|MeshPhysicalMaterial|ShaderMaterial|RawShaderMaterial)\s*\(/i', $js)) {
        throw new RuntimeException('Uploaded GLB/GLTF model materials must be preserved. Do not replace loaded model mesh materials; keep embedded textures, UVs, transparency, vertex colors, and material data unless the user explicitly asks to restyle the model.');
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
    $allowedModelAssetIds = [];
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

    if (preg_match_all('/<a-asset-item\b([^>]*)>/i', $html, $assetMatches, PREG_SET_ORDER)) {
        foreach ($assetMatches as $match) {
            $attrs = $match[1] ?? '';
            if (!preg_match('/\bid\s*=\s*(["\'])([^"\']+)\1/i', $attrs, $idMatch)) {
                throw new RuntimeException('A-Frame model assets must have an id so gltf-model can reference them.');
            }
            if (!preg_match('/\bsrc\s*=\s*(["\'])([^"\']+)\1/i', $attrs, $srcMatch)) {
                throw new RuntimeException('A-Frame model assets must include a same-origin CMS model src.');
            }
            $src = art_piece_normalize_cms_media_ref((string) $srcMatch[2]);
            if ($src === null || !preg_match('#^/media/[0-9]+$#', $src)) {
                throw new RuntimeException('A-Frame model assets must use same-origin uploaded GLTF/GLB paths such as /media/2.');
            }
            $allowedModelAssetIds[] = preg_quote((string) $idMatch[2], '/');
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

    if (preg_match_all('/\bgltf-model\s*=\s*(["\'])([^"\']+)\1/i', $html, $modelMatches, PREG_SET_ORDER)) {
        foreach ($modelMatches as $match) {
            $src = trim((string) $match[2]);
            if (str_starts_with($src, '#')) {
                if ($allowedModelAssetIds === [] || !preg_match('/^#(?:' . implode('|', $allowedModelAssetIds) . ')$/', $src)) {
                    throw new RuntimeException('A-Frame gltf-model references must point to an <a-asset-item> id defined in <a-assets>.');
                }
                continue;
            }
            $normalized = art_piece_normalize_cms_media_ref($src);
            if ($normalized === null || !preg_match('#^/media/[0-9]+$#', $normalized)) {
                throw new RuntimeException('A-Frame gltf-model may only reference same-origin uploaded GLTF/GLB paths such as /media/2 or #model ids.');
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
    if (!in_array($engine, art_piece_supported_generation_modes(), true)) {
        throw new RuntimeException('Unknown engine');
    }
    $effectiveMode = art_piece_normalize_generation_mode($engine, $engine);
    $runtimeEngine = art_piece_generation_mode_to_engine($effectiveMode);
    $baseRules = art_piece_generation_system_prompt($effectiveMode);
    $isSvg = ($runtimeEngine === 'svg');
    $isAframe = ($runtimeEngine === 'aframe');
    $htmlContextDesc = $isSvg 
        ? "the current HTML, CSS, and JS code blocks" 
        : ($isAframe
            ? "the current HTML, CSS, and JS code blocks"
            : "the current CSS and JS code blocks (excluding the static HTML block which is managed automatically and must not be edited)");
    
    $engineConstraint = $isSvg
        ? "CRITICAL: For svg engine pieces, the HTML code MUST retain the <svg> element. The CSS must never hide the SVG or container (display: none and visibility: hidden on svg or container elements are strictly forbidden)."
        : ($isAframe
            ? "CRITICAL: For aframe engine pieces, the HTML code MUST retain exactly one <a-scene id=\"scene\" embedded> root. Do not add external assets, scripts, iframes, or remote URLs. Same-origin CMS image assets are allowed only through `<a-assets><img src=\"/image/2\"></a-assets>` and `#asset` references. The CSS must never hide the scene or canvas."
            : "CRITICAL: For {$effectiveMode} engine pieces, HTML changes are STRICTLY FORBIDDEN. The HTML container is managed automatically. Do not write a 'PATCH html:' block. Focus your edits solely on CSS or JS. The CSS must never hide the canvas or container (display: none and visibility: hidden on canvas or container elements are strictly forbidden).");

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
        "Ensure all constraints of the {$effectiveMode} engine are strictly maintained in anything you write inside a REPLACE section.",
        $engineConstraint,
        "Here are the engine-specific rules for {$effectiveMode} that you MUST follow: ",
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
 * The set of file-name extensions whose references name visual/media assets.
 * Used by art_piece_elide_out_of_scope_refs() when a refine has explicitly
 * placed the visual domain OUT OF SCOPE (an audio-only refine): referencing
 * `image.png` in such a request is unwanted context that some agentic
 * provider proxies will attempt to auto-resolve as file input, crashing a
 * text-only model. Same-origin CMS asset paths like `/image/2` are also
 * visual asset references and are elided in the same pass.
 */
function art_piece_out_of_scope_media_extensions(): array
{
    return ['png', 'jpg', 'jpeg', 'webp', 'gif', 'mp4', 'webm', 'glb', 'gltf', 'obj', 'fbx', 'svg'];
}

/**
 * Replaces bare file-name references whose extension is in $extensions with
 * a descriptive placeholder that explicitly disclaims the reference as out
 * of scope for this request. Conservative: matches `image.png`, `wave.jpg`,
 * `/path/to/model.glb`, and same-origin CMS asset paths `/image/2`,
 * `/media/5`; never matches a bare word like `image`, `images`, or an
 * extension-less filename.
 *
 * The placeholder form, rather than silently dropping or quoting the token,
 * keeps the surrounding prose intelligible to the model while making the
 * out-of-scope status explicit — an agentic proxy layer resolving files by
 * literal name cannot treat the placeholder as a file to read, and a
 * reasoning model cannot mistake the reference for an actionable asset.
 */
function art_piece_elide_out_of_scope_refs(string $text, array $extensions): string
{
    if ($text === '' || $extensions === []) {
        return $text;
    }
    $extAlt = implode('|', array_map(static fn (string $e): string => preg_quote($e, '/'), $extensions));
    // Same-origin CMS asset paths first: `/image/N`, `/media/N` standalone.
    $text = preg_replace('#(?<![\w/])/(?:image|media)/\d+(?![\w/])#i', '[(visual asset reference elided; out of scope for this audio-only refine)]', $text);
    // Bare filenames with an out-of-scope extension: word/path chars + `.ext`,
    // not preceded by a URL scheme or a CMS path already handled above.
    $text = preg_replace('/\b[\w.\-/]+\.(?:' . $extAlt . ')\b(?!\w)/i', '[(visual asset reference elided; out of scope for this audio-only refine)]', $text);
    return $text;
}

/**
 * The three purpose domains a refine or regenerate request can target. Each
 * explicitly declares what is IN SCOPE this request and what is OUT OF SCOPE
 * and must not change, so the model — and any tool-using proxy in front of
 * it — understands the request's scope unambiguously regardless of which
 * prior context is also included.
 *
 *  - 'visual'        : visuals in scope; sound OUT OF SCOPE, carried forward
 *                      unchanged (no sonic block requested; sonic_params
 *                      preserved as-is server-side).
 *  - 'audio'          : sound in scope; visuals OUT OF SCOPE, must not change
 *                      (no visual code shown; no visual patches accepted).
 *  - 'audio_visual'   : both domains in scope; both may change in one request
 *                      — the only mode where a model may emit visual patches
 *                      AND a sonic block together.
 *
 * The original creative prompt is always labeled as CONTEXT, never as the
 * goal of the refine — the directive is the PURPOSE header. This reframing
 * stops the model from treating an older prompt (which may reference assets
 * the proxy then tries to resolve) as an actionable instruction.
 */
function art_piece_purpose_domain_header(string $purposeDomain): string
{
    switch ($purposeDomain) {
        case 'audio':
            return "### PURPOSE OF THIS REFINEMENT\nPURPOSE: AUDIO ONLY. Only the sound design / instrumentation may change. The visual presentation (HTML, CSS, JS) is OUT OF SCOPE and must not change in any way. Do not emit any PATCH block for html, css, or js. Emit only the ```sonic``` JSON block described in the system prompt.";
        case 'audio_visual':
            return "### PURPOSE OF THIS REFINEMENT\nPURPOSE: AUDIO + VISUAL. Both the visual presentation and the sound design may change in this single request. You may emit PATCH blocks for html, css, and/or js AND a ```sonic``` JSON block together. Anything not named by this refinement instruction must still be preserved exactly as-is.";
        case 'visual':
        default:
            return "### PURPOSE OF THIS REFINEMENT\nPURPOSE: VISUAL ONLY. Only the visual presentation (HTML, CSS, JS) may change. The sound design is OUT OF SCOPE and must not change — do not emit a ```sonic``` block; the existing sound design is carried forward unchanged.";
    }
}

/**
 * Coarse validation helper for the 3-state purpose domain. Anything not
 * exactly one of the three allowed values normalizes to 'visual' (the
 * historical default prior to the audio/visual split), so callers can pass
 * untrusted input safely without ever producing an empty/unknown header.
 */
function art_piece_normalize_purpose_domain(?string $purposeDomain): string
{
    return in_array($purposeDomain, ['audio', 'visual', 'audio_visual'], true)
        ? $purposeDomain
        : 'visual';
}

/**
 * Builds the user prompt representing the refinement task.
 *
 * The original creative prompt ($originalPrompt) is included as CONTEXT
 * for reference only — never as the goal of the refine (the directive is
 * the ### PURPOSE OF THIS REFINEMENT header, derived from $purposeDomain).
 * This reframing matters most in audio-only mode, where an older prompt
 * that mentioned e.g. `image.png` would otherwise leak an out-of-scope
 * asset reference into a request whose purpose explicitly excludes the
 * visual domain — and a tool-using provider proxy may then try to read
 * that file, crashing a text-only model. art_piece_elide_out_of_scope_refs
 * neutralizes such references only in audio-only mode (visual context is
 * in scope in the other two modes, so asset references there are legitimate).
 *
 * $purposeDomain ('visual' | 'audio' | 'audio_visual') replaces the prior
 * boolean $soundOnly. The visual code is omitted in 'audio' mode (there is
 * nothing to patch that would be allowed anyway); it is included in
 * 'visual' and 'audio_visual'.
 */
function art_piece_refine_user_prompt(string $engine, string $refinementPrompt, ?string $html, ?string $css, ?string $js, ?string $originalPrompt = null, array $allowedMediaRefs = [], string $purposeDomain = 'visual'): string
{
    $purposeDomain = art_piece_normalize_purpose_domain($purposeDomain);
    $soundOnly = ($purposeDomain === 'audio');

    $sections = [];
    $sections[] = art_piece_purpose_domain_header($purposeDomain);
    if ($originalPrompt !== null && trim($originalPrompt) !== '') {
        $contextualizedOriginalPrompt = $soundOnly
            ? art_piece_elide_out_of_scope_refs($originalPrompt, art_piece_out_of_scope_media_extensions())
            : $originalPrompt;
        $sections[] = "### CONTEXT: ORIGINAL CREATIVE PROMPT (history of the piece, for reference only — the directive is the PURPOSE above; do not treat this prompt as the goal of this refine)";
        $sections[] = $contextualizedOriginalPrompt;
    }
    $sections[] = "### REFINEMENT INSTRUCTION";
    $sections[] = $refinementPrompt;
    $sections[] = "### REMINDER";
    if ($soundOnly) {
        // No visual code is included below at all — there is nothing to
        // patch, and nothing should be. Only the ```sonic``` JSON block
        // (per the sonic capability instructions already in the system
        // prompt) is expected in the response.
        $sections[] = "This is a SOUND-ONLY refinement. Do NOT emit any PATCH block for html, css, or js under any circumstance — the current visual code is deliberately not included below because it must not change. Only emit the ```sonic``` JSON block described in the system prompt.";
    } elseif ($engine === 'svg' || $engine === 'aframe') {
        $sections[] = "Apply ONLY the change named above, as PATCH blocks against the exact current code below — never as a rewritten file. Every color, shape, decoration, and detail not mentioned in the instruction must not appear in any SEARCH/REPLACE pair at all.";
    } else {
        $sections[] = "Apply ONLY the change named above, as PATCH blocks against the exact current code below — never as a rewritten file. Every color, shape, decoration, and detail not mentioned in the instruction must not appear in any SEARCH/REPLACE pair at all. You are STRICTLY FORBIDDEN from editing or adding HTML patches. Focus your edits solely on JS or CSS.";
    }
    $sections[] = art_piece_media_policy_prompt($allowedMediaRefs, true);
    if (!$soundOnly) {
        if ($engine === 'svg' || $engine === 'aframe') {
            $sections[] = "### CURRENT HTML CODE";
            $sections[] = "```html\n" . ($html ?? '') . "\n```";
        }
        $sections[] = "### CURRENT CSS CODE";
        $sections[] = "```css\n" . ($css ?? '') . "\n```";
        $sections[] = "### CURRENT JAVASCRIPT CODE";
        $sections[] = "```javascript\n" . ($js ?? '') . "\n```";
    }

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
 * Returns whether an A-Frame refine changed something that can affect the
 * rendered piece. Removing the neutral default scale from an entity, or
 * reformatting/repeating the same model reference, is valid HTML but has no
 * visual effect and must not be presented as a successful refinement.
 */
function art_piece_refine_has_meaningful_change(
    string $engine,
    ?string $beforeHtml,
    ?string $afterHtml,
    ?string $beforeCss,
    ?string $afterCss,
    ?string $beforeJs,
    ?string $afterJs
): bool {
    if ($engine !== 'aframe') {
        return true;
    }

    $normalizeHtml = static function (?string $html): string {
        $html = trim((string) $html);
        // scale="1 1 1" is the identity transform in A-Frame. Treat its
        // addition/removal as equivalent so the AI cannot turn a no-op into
        // an apparently meaningful model-placement refinement.
        $html = preg_replace('/\s+scale\s*=\s*(["\'])\s*1(?:\s+1){2}\s*\1/i', '', $html) ?? $html;
        return preg_replace('/\s+/u', ' ', $html) ?? $html;
    };
    $normalizeCode = static function (?string $code): string {
        return preg_replace('/\s+/u', ' ', trim((string) $code)) ?? trim((string) $code);
    };

    return $normalizeHtml($beforeHtml) !== $normalizeHtml($afterHtml)
        || $normalizeCode($beforeCss) !== $normalizeCode($afterCss)
        || $normalizeCode($beforeJs) !== $normalizeCode($afterJs);
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
function art_piece_refine_repair_prompt(string $engine, string $refinementPrompt, ?string $previousRawResponse, string $failureMessage, ?string $html = null, ?string $css = null, ?string $js = null, array $allowedMediaRefs = [], string $purposeDomain = 'visual'): string
{
    $purposeDomain = art_piece_normalize_purpose_domain($purposeDomain);
    $soundOnly = ($purposeDomain === 'audio');
    $segments = [
        art_piece_purpose_domain_header($purposeDomain),
        "Target engine: {$engine}",
        "Refinement instruction: {$refinementPrompt}",
        "CRITICAL — your previous attempt failed: \"{$failureMessage}\" You MUST directly address this specific problem in your PLAN section before writing any PATCH — state the new approach you will use to avoid it, not just adjusted numbers or a smaller version of the same approach.",
        art_piece_media_policy_prompt($allowedMediaRefs, true),
    ];
    if ($soundOnly) {
        // Same no-visual-code contract as art_piece_refine_user_prompt()'s
        // sound-only path — a retry here means the previous attempt didn't
        // return a usable ```sonic``` block, not a PATCH-formatting problem.
        $segments[] = "This is a SOUND-ONLY refinement. Do NOT emit any PATCH block for html, css, or js under any circumstance — visual code is deliberately not included below because it must not change. Only emit a valid ```sonic``` JSON block per the sonic capability instructions in the system prompt.";
    } elseif ($engine === 'svg' || $engine === 'aframe') {
        $segments[] = "Respond again in the exact PLAN: / PATCH <file>: / <<<<<<< SEARCH / ======= / >>>>>>> REPLACE format. Every SEARCH block must be copied character-for-character from the CURRENT code below, including whitespace and indentation — do not paraphrase, reformat, or reproduce it from memory of your previous attempt. Re-read the current code below and copy directly from it.";
    } else {
        $segments[] = "Respond again in the exact PLAN: / PATCH <file>: / <<<<<<< SEARCH / ======= / >>>>>>> REPLACE format. Every SEARCH block must be copied character-for-character from the CURRENT code below, including whitespace and indentation — do not paraphrase, reformat, or reproduce it from memory of your previous attempt. Re-read the current code below and copy directly from it. Remember: You are STRICTLY FORBIDDEN from editing HTML. Only edit JS or CSS.";
    }
    if (!$soundOnly) {
        if ($engine === 'svg' || $engine === 'aframe') {
            $segments[] = "### CURRENT HTML CODE";
            $segments[] = "```html\n" . ($html ?? '') . "\n```";
        }
        $segments[] = "### CURRENT CSS CODE";
        $segments[] = "```css\n" . ($css ?? '') . "\n```";
        $segments[] = "### CURRENT JAVASCRIPT CODE";
        $segments[] = "```javascript\n" . ($js ?? '') . "\n```";
    }
    if ($previousRawResponse !== null && $previousRawResponse !== '') {
        $segments[] = "Your previous response: {$previousRawResponse}";
    }
    return implode("\n\n", $segments);
}
