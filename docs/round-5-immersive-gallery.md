# Round 5 — Immersive/VR Gallery Overhaul

> **Status**: Done. Implemented in the PHP app; retained here as the
> implementation plan and audit trail. This was the **largest and most
> novel** of the three rounds (new 3D rendering subsystem). Source line
> numbers below are historical anchors and may have drifted; search by
> function name if a line number is off by a few lines.
>
> Independent of Round 3 and Round 4 — can be implemented in any order
> relative to those.

## Context

The PHP `/immersive/pieces/{id}` and `/immersive/exhibits/{slug}` routes
currently exist but are minimal:

- `/immersive/pieces/{id}`: full-page wrapper around
  `piece_render_iframe($piece, $version, 900)` — i.e. just the piece's own
  sandboxed iframe at 900px height, with a "Back" link overlay. No 3D
  navigation, no metadata overlay, regardless of engine.
- `/immersive/exhibits/{slug}`: a flat CSS grid
  (`repeat(auto-fit, minmax(260px,1fr))`) of 320px-tall iframes (art pieces)
  and `<img>` tags (media assets). `exhibits.rows`/`cols` are loaded
  (`PlatformExhibit::find()`/`findBySlug()` do `SELECT *`, so `rows`/`cols`
  are already present in the returned array) but **ignored**. No 3D, no
  frame labels, no progressive loading.

The platform's equivalent (`platform/artifacts/microblog/src/pages/`) is a
full Three.js-based 3D gallery with two distinct presentation modes plus a
multi-frame exhibit wall with budget-aware progressive loading. This round
ports that to PHP.

### User's behavior spec (2026-06-14, captured verbatim — this is the
### acceptance criteria for this entire round)

> "No, I want the viewer to be FULLY IMMERSED in Three.js pieces in
> immersive VR mode. Gallery mode is reserved for P5.js, C2.js, SVG pieces
> and images. However, only if Three.js pieces are included in an exhibit
> should it be in the same frame as other pieces, meaning that the viewer
> will not be immersed in the Three.js piece in such a situation given that
> they should be able to view other animations. Remember that there are also
> rules for how pieces should be loaded in exhibits: 1 animation at a time in
> mobile, 2 animations for tablet, 3 animations for desktop, with the
> remaining pieces showing only their thumbnail until the user's positioning
> changes. All loading (posts and exhibits) should mimic what's shown in the
> platform app."

And the mid-stream addition:

> "Also, make sure that exhibits can also be viewed as gallery pieces (which
> I believe is already the case but I wanted to make sure)."

Decomposed into concrete behaviors:

1. `/immersive/pieces/{id}` where `engine === 'three'` → **full immersion**:
   the viewer is inside the piece's own 3D scene, free to orbit/move
   (OrbitControls + keyboard navigation), same as the platform's current
   `ImmersiveThreePieceStage`.
2. `/immersive/pieces/{id}` where `engine in [p5, c2, svg]` → **gallery
   piece**: the piece is mounted as a framed artwork inside a small 3D
   gallery room (floor, back wall, frame, lighting), viewer can orbit/move
   *around the room* but the piece itself runs in its normal 2D
   canvas/iframe form, projected onto the frame. Metadata overlay (title,
   engine, prompt/alt text, source link, interaction hints) — same
   information density as the platform's `ImmersiveGalleryPieceStage`.
3. `/immersive/exhibits/{slug}` → **multi-frame wall**: every item
   (art pieces of *any* engine, including `three`, AND media assets/images)
   renders as a framed gallery item on a grid wall sized by
   `exhibits.rows` × `exhibits.cols`. Three.js pieces do **not** get full
   immersion here — they're just another frame on the wall.
4. Exhibit wall progressive loading: at most N items are "live"
   (actually running their animation/scene) at once —
   N = 1 (viewport < 640px, mobile), 2 (640–1179px, tablet), 3 (≥1180px,
   desktop). The live N are chosen by proximity to the camera target;
   others show a static thumbnail until the viewer's position/viewport
   changes.
5. Post embeds (`/blog` posts containing embedded art pieces) should use the
   **same progressive/lazy-loading philosophy** — don't eagerly boot a
   piece's JS runtime until it's likely to be seen.
6. Exhibits themselves must be reachable/viewable as "gallery pieces" —
   i.e. confirm `/pieces` (or wherever exhibits are linked from) links to
   `/immersive/exhibits/{slug}` and that the exhibit wall (#3 above) *is* the
   "gallery piece" view for an exhibit. This is largely a verification item
   once #3 is built.

---

## Current PHP state (full current files, for diffing against)

### `public/app/controllers/ImmersiveController.php` (current, full)

```php
<?php

declare(strict_types=1);

class ImmersiveController
{
    public static function piece(string $id): void
    {
        $data = EmbedController::loadPieceVersion((int) $id, isset($_GET['version']) ? (int) $_GET['version'] : null);
        if ($data === null) {
            self::notFound();
        }

        $piece = $data['piece'];
        $version = $data['version'];
        $pageTitle = (($piece['title'] ?? '') ?: 'Art Piece') . ' | Immersive';
        header('Content-Type: text/html; charset=utf-8');
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<style>
html,body{margin:0;width:100%;height:100%;background:#050507;color:#fff;overflow:hidden;}
.immersive-shell{position:fixed;inset:0;}
.immersive-shell iframe{width:100%;height:100%;border:0;display:block;}
.immersive-bar{position:fixed;left:1rem;top:1rem;z-index:20;display:flex;gap:.75rem;align-items:center;background:rgba(0,0,0,.72);padding:.6rem .8rem;border:1px solid rgba(255,255,255,.18);}
.immersive-bar a{color:#fff;}
</style>
</head>
<body>
<div class="immersive-shell"><?= piece_render_iframe($piece, $version, 900) ?></div>
<div class="immersive-bar"><a href="/pieces/<?= (int) $piece['id'] ?>">Back</a><strong><?= e($piece['title'] ?? 'Art piece') ?></strong></div>
</body>
</html>
<?php
        exit;
    }

    public static function exhibit(string $slug): void
    {
        $exhibit = PlatformExhibit::findBySlug($slug);
        if (!$exhibit) {
            self::notFound();
        }

        $items = self::hydrateItems($exhibit['items'] ?? []);
        $pageTitle = (($exhibit['name'] ?? '') ?: 'Exhibit') . ' | Immersive';
        header('Content-Type: text/html; charset=utf-8');
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<style>
body{margin:0;background:#111;color:#f7f2e8;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}
.exhibit-wrap{max-width:1200px;margin:0 auto;padding:2rem;}
.exhibit-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem;}
.exhibit-item{min-height:260px;background:#050507;border:1px solid rgba(255,255,255,.14);}
.exhibit-item iframe{width:100%;height:320px;border:0;display:block;}
.exhibit-item img{width:100%;height:320px;object-fit:contain;display:block;background:#050507;}
a{color:inherit;}
</style>
</head>
<body>
<main class="exhibit-wrap">
<p><a href="/pieces">Pieces</a></p>
<h1><?= e($exhibit['name'] ?? 'Exhibit') ?></h1>
<?php if (!empty($exhibit['description'])): ?><p><?= e($exhibit['description']) ?></p><?php endif; ?>
<section class="exhibit-grid" aria-label="Exhibit items">
<?php foreach ($items as $item): ?>
  <article class="exhibit-item">
    <?php if ($item['type'] === 'art_piece' && !empty($item['piece']) && !empty($item['version'])): ?>
      <?= piece_render_iframe($item['piece'], $item['version'], 320) ?>
    <?php elseif ($item['type'] === 'media_asset' && !empty($item['media'])): ?>
      <?php $src = $item['media']['url'] ?: '/api/media-assets/' . (int) $item['media']['id']; ?>
      <img src="<?= e($src) ?>" alt="<?= e($item['media']['alt_text'] ?? $item['media']['title'] ?? '') ?>">
    <?php endif; ?>
  </article>
<?php endforeach; ?>
</section>
</main>
</body>
</html>
<?php
        exit;
    }

    private static function hydrateItems(array $items): array
    {
        $hydrated = [];
        foreach ($items as $item) {
            $type = (string) ($item['item_type'] ?? '');
            $id = (int) ($item['item_id'] ?? 0);
            if ($type === 'art_piece') {
                $piece = PlatformArtPiece::find($id);
                $hydrated[] = ['type' => 'art_piece', 'piece' => $piece, 'version' => $piece['current_version'] ?? null];
            } elseif ($type === 'media_asset') {
                $hydrated[] = ['type' => 'media_asset', 'media' => MediaAsset::find($id)];
            }
        }
        return $hydrated;
    }

    private static function notFound(): never
    {
        http_response_code(404);
        require dirname(__DIR__) . '/views/404.php';
        exit;
    }
}
```

### `public/app/helpers/piece-render.php` (current, full — DO NOT modify;
### Round 5 builds new renderers alongside this, it remains the renderer for
### `/pieces/{id}`, `/embed/pieces/{id}`, and 320px exhibit thumbnails)

```php
<?php

declare(strict_types=1);

function piece_render_document(array $piece, array $version): string
{
    $title = htmlspecialchars((string) ($piece['title'] ?? 'Art piece'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $engine = strtolower((string) ($version['engine'] ?? $piece['engine'] ?? 'p5'));
    $html = (string) ($version['html_code'] ?? '');
    $css = (string) ($version['css_code'] ?? '');
    $code = (string) ($version['generated_code'] ?? '');
    $jsonCode = json_encode($code, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $jsonEngine = json_encode($engine);

    return <<<HTML
<!DOCTYPE html>
... (boilerplate head/style) ...
<body>
<div id="runtime-root">{$html}</div>
<div id="piece-error" role="alert"></div>
<script>
const PIECE_ENGINE = {$jsonEngine};
const PIECE_CODE = {$jsonCode};
... (error handling, findCanvas, sizeCanvas, startFrame) ...
function bootCanvasRuntime(extra) {
  runPieceCode(); // new Function(PIECE_CODE)()
  if (typeof window.sketch !== 'function') return;
  const canvas = findCanvas(PIECE_ENGINE === 'c2' ? 'c2-canvas' : 'scene');
  sizeCanvas(canvas);
  window.addEventListener('resize', () => sizeCanvas(canvas));
  try { window.sketch({ canvas, startFrame, ...(extra || {}) }); } catch (error) { showPieceError(error); }
}
function bootP5() { /* loads p5.js@1.9.0 from cdnjs, new p5(window.sketch, runtime-root) */ }
async function bootThree() {
  try {
    const mod = await import('https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js');
    bootCanvasRuntime({ THREE: mod }); // window.sketch({canvas, startFrame, THREE})
  } catch (error) { showPieceError(error); }
}
if (PIECE_ENGINE === 'p5') { bootP5(); }
else if (PIECE_ENGINE === 'three') { bootThree(); }
else if (PIECE_ENGINE === 'c2') { bootCanvasRuntime({ c2: {} }); }
else { runPieceCode(); if (typeof window.sketch === 'function') window.sketch(); }
</script>
</body>
</html>
HTML;
}

function piece_render_iframe(array $piece, array $version, int $height = 520): string
{
    $srcdoc = htmlspecialchars(piece_render_document($piece, $version), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $title = htmlspecialchars((string) ($piece['title'] ?? 'Art piece'), ENT_QUOTES, 'UTF-8');
    return '<iframe srcdoc="' . $srcdoc . '" style="width:100%;height:' . $height . 'px;border:0;display:block;" sandbox="allow-scripts allow-same-origin" title="' . $title . '"></iframe>';
}
```

**Key fact this round depends on**: for ANY engine, a piece's `generated_code`
defines `window.sketch`, which the harness calls with an object containing at
minimum `{ canvas, startFrame }`, plus `{ THREE }` for `three` and `{ c2 }`
for `c2`. For `p5`, `window.sketch` is the p5 sketch function passed to
`new p5(...)`. **A "three" piece's `sketch` function constructs its own
`THREE.Scene`/`PerspectiveCamera`/`WebGLRenderer` using the `canvas` and
`THREE` module it's handed.** Full immersion (Phase 1 below) means the
*viewer* gets OrbitControls + keyboard navigation layered onto **that
piece-constructed camera/scene** — this is the mechanism to find and port
from `ImmersiveThreePieceStage`.

---

## Source of truth map (platform files)

| Concern | File | Lines / functions |
|---|---|---|
| Single Three.js piece — full immersion | `platform/artifacts/microblog/src/pages/immersive-piece.tsx` | `ImmersiveThreePieceStage`, ~496-1093 |
| Single p5/c2/svg piece — gallery framing | same file | `ImmersiveGalleryPieceStage`, ~80-494 |
| Metadata overlay (title/engine/hints/alt/source) | same file | ~1205-1252 |
| Exhibit wall | `platform/artifacts/microblog/src/pages/immersive-exhibit-wall.tsx` | `ExhibitWallStage`, ~108-250+; progressive rendering ~73-106 |
| Engine label helper | same file | `engineLabel()`, near line 60 — quoted below |
| Progressive budget + slot selection | same file | `getProgressiveExhibitLiveBudget`, `selectProgressiveExhibitSlots` — **quoted in full below** |
| Gallery shell geometry/lighting | `platform/artifacts/microblog/src/lib/immersive-gallery.ts` | `createMountedGalleryShell` 82-195, `updateMountedGalleryLayout`/`fitMountedGalleryCamera` 196-259 |
| Presentation surface (p5/c2/svg → texture) | same file | `createPresentationSurface`/`drawContainedIntoPresentationSurface` 260-308 |
| Floor-click navigation | same file | `createFloorClickNavigation` — **quoted in full below** |
| Keyboard navigation | same file | `createKeyboardNavigation` 476-553; `computeOrbitKeyboardMotion` — **quoted in full below** |
| Exhibit wall geometry/grid | same file | `createMultiFrameExhibitWall`, `fitMultiFrameExhibitCamera`, `computeExhibitGridCenterY`, `computeExhibitBottomVisibleY`, `createFrameLabel`, `EXHIBIT_FRAME_ASPECT` — **quoted in full below** |
| Auto-fit camera math | same file | `computeThreeAutoFitView` — **quoted in full below** |
| Renderer background sync | same file | `syncThreeRendererBackground` 448-475 |
| Immersive runtime hosting helpers | `platform/artifacts/microblog/src/lib/immersive-piece-runtime.ts` | `resolveSketchFactory`, `createImmersiveHost`, `normalizeManagedCanvasStyles`, `observeManagedCanvasContainment` — full file is 187 lines, read in full |
| URL/embed helpers (image refs, embed HTML builders) | `platform/artifacts/microblog/src/lib/immersive-view.ts` | full file is 234 lines — `buildImmersivePieceHref`, `buildPieceGalleryEmbedHtml`, `buildImageGalleryEmbedHtml`, `buildExhibitGalleryEmbedHtml`, `INTERACTIVE_IMMERSIVE_EMBED_SANDBOX` |
| Exhibit schema (already migrated) | `platform/lib/db/src/schema/exhibits.ts` | `exhibitsTable` (id, slug, name, description, artist_statement, biography, rows, cols), `pieceExhibitsTable`, `mediaAssetExhibitsTable` |
| PHP exhibit model (already supports rows/cols via `SELECT *`) | `public/app/models/PlatformExhibit.php` | `find()`, `findBySlug()`, `itemsFor()` |

---

## Quoted reference implementations (verbatim from `immersive-gallery.ts`
## and `immersive-exhibit-wall.tsx`) — port these to JS in
## `public/assets/js/immersive-gallery.js`

These are pure functions/geometry — no DOM dependencies beyond
`document.createElement('canvas')` for label textures — and can be ported
essentially 1:1 into the new shared client asset.

### Constants and gallery profile

```ts
const WALL_CENTER = new THREE.Vector3(0, 1.35, -1.08);
const TARGET_OFFSET = new THREE.Vector3(0, -0.16, 0);
const MAX_ART_WIDTH = 6.4;
const MAX_ART_HEIGHT = 4.6;
const PRESENTATION_MAX_ART_WIDTH = 5.2;
const PRESENTATION_MAX_ART_HEIGHT = 3.9;

export const NORMALIZED_PRESENTATION_GALLERY_PROFILE = {
  maxArtWidth: PRESENTATION_MAX_ART_WIDTH,
  maxArtHeight: PRESENTATION_MAX_ART_HEIGHT,
  framingMultiplier: 1.58,
  targetOffset: { x: 0, y: 0, z: 0 },
  cameraYOffset: 0.02,
};

export function computeMountedArtworkLayout(aspect, profile = {}) {
  const safeAspect = Math.max(aspect, 0.35);
  const maxArtWidth = profile.maxArtWidth ?? MAX_ART_WIDTH;
  const maxArtHeight = profile.maxArtHeight ?? MAX_ART_HEIGHT;
  let width = maxArtWidth;
  let height = width / safeAspect;
  if (height > maxArtHeight) {
    height = maxArtHeight;
    width = height * safeAspect;
  }
  return { width, height, aspect: safeAspect };
}

export function isCompactImmersiveViewport(width) {
  return width < 1024;
}
```

### Floor-click navigation (full)

```ts
export function createFloorClickNavigation(camera, controls, floorMesh, domElement, options = {}) {
  const { minZ = 0.5, maxZ = 8, maxX = 8, duration = 350 } = options;
  const raycaster = new THREE.Raycaster();
  let animFromTarget = null, animToTarget = null, animFromCam = null, animToCam = null, animStart = 0;
  let downX = 0, downY = 0;

  function onPointerDown(e) { downX = e.clientX; downY = e.clientY; }

  function onPointerUp(e) {
    if (Math.hypot(e.clientX - downX, e.clientY - downY) >= 6) return;
    const rect = domElement.getBoundingClientRect();
    raycaster.setFromCamera(
      new THREE.Vector2(((e.clientX - rect.left) / rect.width) * 2 - 1, -((e.clientY - rect.top) / rect.height) * 2 + 1),
      camera,
    );
    const hits = raycaster.intersectObject(floorMesh, false);
    if (!hits.length) return;
    const hit = hits[0].point;
    const shift = new THREE.Vector3(
      Math.max(-maxX, Math.min(maxX, hit.x)) - camera.position.x,
      0,
      Math.max(minZ, Math.min(maxZ, hit.z)) - camera.position.z,
    );
    if (shift.lengthSq() < 0.003) return;
    animFromTarget = controls.target.clone();
    animToTarget = animFromTarget.clone().add(shift);
    animFromCam = camera.position.clone();
    animToCam = animFromCam.clone().add(shift);
    animStart = performance.now();
    controls.enabled = false;
  }

  function update() {
    if (!animFromTarget || !animToTarget) return;
    const t = Math.min((performance.now() - animStart) / duration, 1);
    const eased = 1 - (1 - t) ** 3;
    controls.target.lerpVectors(animFromTarget, animToTarget, eased);
    camera.position.lerpVectors(animFromCam, animToCam, eased);
    controls.update();
    if (t >= 1) {
      controls.enabled = true;
      animFromTarget = animToTarget = animFromCam = animToCam = null;
    }
  }

  function dispose() {
    domElement.removeEventListener("pointerdown", onPointerDown);
    domElement.removeEventListener("pointerup", onPointerUp);
    controls.enabled = true;
  }

  domElement.addEventListener("pointerdown", onPointerDown);
  domElement.addEventListener("pointerup", onPointerUp);
  return { update, dispose };
}
```

### Keyboard navigation motion (full)

```ts
export function computeOrbitKeyboardMotion(forward, keys, speed) {
  const activeKeys = keys instanceof Set ? keys : new Set(keys);
  let fwdScale = 0, rightScale = 0;
  if (activeKeys.has("ArrowUp")) fwdScale += speed;
  if (activeKeys.has("ArrowDown")) fwdScale -= speed;
  if (activeKeys.has("ArrowLeft")) rightScale -= speed;
  if (activeKeys.has("ArrowRight")) rightScale += speed;
  if (fwdScale === 0 && rightScale === 0) return { dx: 0, dy: 0, dz: 0 };

  const horizontalLength = Math.sqrt(forward.x ** 2 + forward.z ** 2);
  const right = horizontalLength > 1e-6
    ? { x: -forward.z / horizontalLength, y: 0, z: forward.x / horizontalLength }
    : { x: 1, y: 0, z: 0 };

  return {
    dx: (forward.x * fwdScale) + (right.x * rightScale),
    dy: forward.y * fwdScale,
    dz: (forward.z * fwdScale) + (right.z * rightScale),
  };
}
```

> Read `createKeyboardNavigation` (immersive-gallery.ts:476-553) for how
> `computeOrbitKeyboardMotion` is wired to `keydown`/`keyup` listeners and
> applied to `camera.position` + `controls.target` each frame (with WASD as
> aliases for arrow keys per the user's spec — confirm WASD mapping in this
> function; if absent, add `KeyW/KeyA/KeyS/KeyD` as aliases for
> `ArrowUp/ArrowLeft/ArrowDown/ArrowRight` to match the user's "fully move and
> navigate ... with the same degree of information" expectation, which
> explicitly mentions WASD).

### Exhibit wall geometry (full)

```ts
const WALL_FRAME_ART_WIDTH = /* read from immersive-gallery.ts, near EXHIBIT_FRAME_ASPECT def */;
const WALL_FRAME_ART_HEIGHT = /* same */;
const WALL_FRAME_SLOT_WIDTH = /* same */;
const WALL_FRAME_SLOT_HEIGHT = /* same */;
const WALL_LABEL_HEIGHT = /* same */;
const WALL_LABEL_GAP = /* same */;
const EXHIBIT_FLOOR_CLEARANCE = /* same */;
export const EXHIBIT_FRAME_ASPECT = WALL_FRAME_ART_WIDTH / WALL_FRAME_ART_HEIGHT;

export function computeExhibitGridCenterY(rows) {
  const gridRows = Math.max(1, rows);
  const minBottomSlotCenterY = (WALL_FRAME_ART_HEIGHT / 2) + (WALL_LABEL_HEIGHT / 2) + WALL_LABEL_GAP + EXHIBIT_FLOOR_CLEARANCE;
  return Math.max(WALL_CENTER.y, ((gridRows - 1) / 2) * WALL_FRAME_SLOT_HEIGHT + minBottomSlotCenterY);
}

export function computeExhibitBottomVisibleY(rows) {
  const gridRows = Math.max(1, rows);
  const gridCenterY = computeExhibitGridCenterY(gridRows);
  const bottomSlotCenterY = gridCenterY - (((gridRows - 1) / 2) * WALL_FRAME_SLOT_HEIGHT);
  return bottomSlotCenterY - (WALL_FRAME_ART_HEIGHT / 2) - (WALL_LABEL_HEIGHT / 2) - WALL_LABEL_GAP;
}

function createFrameLabel(title, subtitle) {
  const cw = 512, ch = 80;
  const canvas = document.createElement("canvas");
  canvas.width = cw; canvas.height = ch;
  const ctx = canvas.getContext("2d");
  ctx.clearRect(0, 0, cw, ch);
  ctx.fillStyle = "rgba(255,255,255,0.82)";
  ctx.fillRect(0, 0, cw, ch);
  ctx.fillStyle = "rgba(0,0,0,0.82)";
  ctx.font = "bold 22px sans-serif";
  ctx.textBaseline = "middle";
  ctx.fillText(title.slice(0, 38), 16, ch * 0.38);
  ctx.fillStyle = "rgba(0,0,0,0.52)";
  ctx.font = "16px sans-serif";
  ctx.fillText(subtitle.slice(0, 42), 16, ch * 0.72);

  const texture = new THREE.CanvasTexture(canvas);
  const material = new THREE.MeshBasicMaterial({ map: texture, transparent: true, depthWrite: false });
  const mesh = new THREE.Mesh(new THREE.PlaneGeometry(WALL_FRAME_ART_WIDTH, WALL_LABEL_HEIGHT), material);
  return { mesh, material };
}

export function createMultiFrameExhibitWall(stage, frameCount, rows = 1, cols = frameCount, labels) {
  const n = Math.max(1, frameCount);
  const gridRows = Math.max(1, rows);
  const gridCols = Math.max(1, cols);
  const wallWidth = Math.max(22, gridCols * WALL_FRAME_SLOT_WIDTH + 2);
  const wallMeshHeight = Math.max(11, gridRows * WALL_FRAME_SLOT_HEIGHT + 5);
  const gridCenterY = computeExhibitGridCenterY(gridRows);

  const canvas = document.createElement("canvas");
  canvas.style.width = "100%"; canvas.style.height = "100%"; canvas.style.display = "block"; canvas.style.touchAction = "none";
  const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  stage.innerHTML = "";
  stage.appendChild(canvas);

  const scene = new THREE.Scene();
  scene.background = new THREE.Color("#f1ece2");
  scene.fog = new THREE.Fog("#f1ece2", Math.max(20, wallWidth + 4), Math.max(40, wallWidth * 3));

  const camera = new THREE.PerspectiveCamera(40, 1, 0.1, 200);
  const controls = new OrbitControls(camera, canvas);
  controls.enableDamping = true;
  controls.enablePan = true;
  controls.minPolarAngle = 0.01;
  controls.maxPolarAngle = Math.PI - 0.01;

  scene.add(new THREE.AmbientLight(0xffffff, 1.38));
  const keyLight = new THREE.DirectionalLight(0xfffcf6, 0.9);
  keyLight.position.set(0.8, 4.8, 5.2);
  scene.add(keyLight);
  const fillLight = new THREE.DirectionalLight(0xf3ede2, 0.35);
  fillLight.position.set(-3.1, 2.4, 1.8);
  scene.add(fillLight);

  const floor = new THREE.Mesh(
    new THREE.PlaneGeometry(wallWidth + 8, 18),
    new THREE.MeshStandardMaterial({ color: "#d7d0c4", roughness: 0.98, metalness: 0 }),
  );
  floor.rotation.x = -Math.PI / 2;
  floor.position.set(0, 0, 1.9);
  scene.add(floor);

  const backWall = new THREE.Mesh(
    new THREE.PlaneGeometry(wallWidth + 4, wallMeshHeight),
    new THREE.MeshStandardMaterial({ color: "#f8f5ee", roughness: 1, metalness: 0 }),
  );
  backWall.position.set(0, gridCenterY, -1.35);
  scene.add(backWall);

  const wallCenterZ = WALL_CENTER.z;
  const slots = [];
  for (let i = 0; i < n; i++) {
    const row = Math.floor(i / gridCols);
    const col = i % gridCols;
    const slotX = (col - (gridCols - 1) / 2) * WALL_FRAME_SLOT_WIDTH;
    const slotY = gridCenterY + ((gridRows - 1) / 2 - row) * WALL_FRAME_SLOT_HEIGHT;

    const framePanel = new THREE.Mesh(
      new THREE.BoxGeometry(WALL_FRAME_ART_WIDTH + 0.3, WALL_FRAME_ART_HEIGHT + 0.3, 0.05),
      new THREE.MeshStandardMaterial({ color: "#fcfaf6", roughness: 0.96, metalness: 0 }),
    );
    framePanel.position.set(slotX, slotY, -1.16);
    scene.add(framePanel);

    const artMaterial = new THREE.MeshBasicMaterial({ color: "#e8e4de" });
    const artMesh = new THREE.Mesh(new THREE.PlaneGeometry(WALL_FRAME_ART_WIDTH, WALL_FRAME_ART_HEIGHT), artMaterial);
    artMesh.position.set(slotX, slotY, wallCenterZ);
    scene.add(artMesh);

    const frameMesh = new THREE.Mesh(
      new THREE.BoxGeometry(WALL_FRAME_ART_WIDTH + 0.12, WALL_FRAME_ART_HEIGHT + 0.12, 0.03),
      new THREE.MeshStandardMaterial({ color: "#d8d1c7", roughness: 0.92, metalness: 0 }),
    );
    frameMesh.position.set(slotX, slotY, -1.12);
    scene.add(frameMesh);

    const slot = { artMesh, artMaterial, frameMesh, framePanel, center: { x: slotX, y: slotY, z: wallCenterZ } };

    const label = labels?.[i];
    if (label) {
      const { mesh: labelMesh, material: labelMaterial } = createFrameLabel(label.title, label.subtitle);
      labelMesh.position.set(slotX, slotY - WALL_FRAME_ART_HEIGHT / 2 - WALL_LABEL_HEIGHT / 2 - WALL_LABEL_GAP, wallCenterZ + 0.01);
      scene.add(labelMesh);
      slot.labelMesh = labelMesh;
      slot.labelMaterial = labelMaterial;
    }
    slots.push(slot);
  }

  const shell = { canvas, renderer, scene, camera, controls, floor, backWall, slots, gridRows, gridCols, gridCenterY };
  fitMultiFrameExhibitCamera(shell, stage);
  return shell;
}

export function fitMultiFrameExhibitCamera(shell, stage, resetCamera = true) {
  const width = stage.clientWidth >= 50 ? stage.clientWidth : window.innerWidth;
  const height = stage.clientHeight >= 50 ? stage.clientHeight : window.innerHeight;
  shell.camera.aspect = width / Math.max(height, 1);
  shell.camera.updateProjectionMatrix();
  shell.renderer.setSize(width, height, false);

  const { gridRows, gridCols, gridCenterY } = shell;
  const totalWidth = Math.max(WALL_FRAME_ART_WIDTH, (gridCols - 1) * WALL_FRAME_SLOT_WIDTH + WALL_FRAME_ART_WIDTH);
  const totalHeight = Math.max(WALL_FRAME_ART_HEIGHT, (gridRows - 1) * WALL_FRAME_SLOT_HEIGHT + WALL_FRAME_ART_HEIGHT);

  const target = new THREE.Vector3(0, gridCenterY - 0.16, WALL_CENTER.z);
  const verticalFov = THREE.MathUtils.degToRad(shell.camera.fov);
  const horizontalFov = 2 * Math.atan(Math.tan(verticalFov / 2) * shell.camera.aspect);
  const distanceForHeight = (totalHeight / 2) / Math.tan(verticalFov / 2);
  const distanceForWidth = (totalWidth / 2) / Math.tan(horizontalFov / 2);
  const distance = Math.max(distanceForHeight, distanceForWidth) * 1.45;

  shell.controls.minDistance = Math.max(1.2, distance * 0.25);
  shell.controls.maxDistance = Math.max(20, distance * 6);
  shell.controls.minAzimuthAngle = -Math.PI;
  shell.controls.maxAzimuthAngle = Math.PI;

  if (resetCamera) {
    shell.camera.position.set(0, gridCenterY + 0.2, WALL_CENTER.z + distance);
    shell.camera.lookAt(target);
    shell.controls.target.copy(target);
  }
  shell.controls.update();
}

export function computeThreeAutoFitView(center, size, aspect, fovDegrees, compactViewport) {
  const verticalFov = (fovDegrees * Math.PI) / 180;
  const horizontalFov = 2 * Math.atan(Math.tan(verticalFov / 2) * Math.max(aspect, 0.1));
  const fitWidth = Math.max(size.x, size.z, 1);
  const fitHeight = Math.max(size.y, size.z * 1.08, 1);
  const distanceForHeight = (fitHeight / 2) / Math.tan(verticalFov / 2);
  const distanceForWidth = (fitWidth / 2) / Math.tan(horizontalFov / 2);
  const cameraZ = Math.max(distanceForHeight, distanceForWidth) * (compactViewport ? 1.46 : 1.34);
  const targetY = center.y + (fitHeight * (compactViewport ? 0.08 : 0.12));
  const cameraY = targetY + (fitHeight * (compactViewport ? 0.02 : 0.04));
  return { camera: { x: center.x, y: cameraY, z: center.z + cameraZ }, target: { x: center.x, y: targetY, z: center.z } };
}
```

> `WALL_FRAME_ART_WIDTH`, `WALL_FRAME_ART_HEIGHT`, `WALL_FRAME_SLOT_WIDTH`,
> `WALL_FRAME_SLOT_HEIGHT`, `WALL_LABEL_HEIGHT`, `WALL_LABEL_GAP`,
> `EXHIBIT_FLOOR_CLEARANCE` are module-level constants defined near
> `EXHIBIT_FRAME_ASPECT` (immersive-gallery.ts, just above line 554) — read
> their literal values and copy them verbatim; they were not captured in
> this pass.

### Engine label + progressive budget (full, from `immersive-exhibit-wall.tsx`)

```ts
function engineLabel(engine) {
  if (engine === "p5") return "P5.js";
  if (engine === "c2") return "C2.js";
  if (engine === "three") return "Three.js";
  if (engine === "svg") return "SVG";
  return engine;
}

export function getProgressiveExhibitLiveBudget(viewportWidth, staticMode = false) {
  if (staticMode) return 1;
  if (viewportWidth < 640) return 1;   // mobile
  if (viewportWidth < 1180) return 2;  // tablet
  return 3;                            // desktop
}

export function selectProgressiveExhibitSlots(items, centers, target, liveBudget) {
  if (liveBudget <= 0) return new Set();
  return new Set(
    items
      .map((item, index) => {
        const center = centers[index];
        if (item.kind !== "piece" || !center) return null;
        const dx = center.x - target.x;
        const dy = center.y - target.y;
        const dz = center.z - target.z;
        return { index, distance: (dx * dx) + (dy * dy) + (dz * dz * 0.35) };
      })
      .filter((entry) => Boolean(entry))
      .sort((a, b) => a.distance - b.distance)
      .slice(0, liveBudget)
      .map((entry) => entry.index),
  );
}
```

**This is the exact 1/2/3 mobile/tablet/desktop rule from the user's spec.**
Note `selectProgressiveExhibitSlots` only ever marks `item.kind === "piece"`
entries as "live" — media-asset/image items are presumably always rendered
(cheap `THREE.TextureLoader`, no running JS) regardless of budget. Preserve
this distinction in the PHP/JS port: images are not subject to the
live-animation budget, only `art_pieces` (any engine) are.

---

## Target architecture

### New shared client asset — `public/assets/js/immersive-gallery.js`

A single ES module, loaded with `<script type="module" src="/assets/js/immersive-gallery.js">`
on the immersive pages. Imports Three.js + OrbitControls from the **same
CDN/version** already used in `piece-render.php` for consistency:

```js
import * as THREE from 'https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js';
import { OrbitControls } from 'https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/controls/OrbitControls.js';
```

Export every function quoted/referenced above
(`computeMountedArtworkLayout`, `NORMALIZED_PRESENTATION_GALLERY_PROFILE`,
`isCompactImmersiveViewport`, `createFloorClickNavigation`,
`computeOrbitKeyboardMotion`, `createKeyboardNavigation`,
`createMountedGalleryShell`, `updateMountedGalleryLayout`,
`fitMountedGalleryCamera`, `createPresentationSurface`,
`drawContainedIntoPresentationSurface`, `syncThreeRendererBackground`,
`computeExhibitGridCenterY`, `computeExhibitBottomVisibleY`,
`createMultiFrameExhibitWall`, `fitMultiFrameExhibitCamera`,
`computeThreeAutoFitView`, `getProgressiveExhibitLiveBudget`,
`selectProgressiveExhibitSlots`, `engineLabel`), plus three new top-level
"page entry" functions written for this port (no direct platform
equivalent, since the platform splits these across React components):

- `mountThreeImmersivePiece(stage, pieceCode, metadata)` — Phase 1.
- `mountGalleryPiece(stage, pieceOrImage, metadata)` — Phase 2.
- `mountExhibitWall(stage, items, rows, cols, viewportBudgetFn)` — Phase 3.

---

## Phase 1 — Single Three.js piece: full immersion

Route: `/immersive/pieces/{id}` where the version's `engine === 'three'`.

### Target PHP (`ImmersiveController::piece()`)

When `engine === 'three'`, render a **new** full-page document (not the
`piece_render_iframe()` sandboxed srcdoc — full immersion needs the piece's
`generated_code` to run in a context where the harness can attach
`OrbitControls` to the camera/scene the piece itself creates, which requires
either (a) running the piece code directly on the main page inside a
`<script type="module">`, or (b) `postMessage`-based control bridging into
the existing sandboxed iframe). **Read `ImmersiveThreePieceStage`
(immersive-piece.tsx ~496-1093) to determine which of (a)/(b) the platform
uses** — this determines the PHP implementation shape:

- If (a) (direct execution): the new immersive document inlines
  `$version['generated_code']` directly (NOT inside an iframe), imports
  `THREE`/`OrbitControls` from the CDN, calls
  `window.sketch({ canvas, startFrame, THREE })` exactly as
  `piece_render_document()`'s `bootThree()` does, but the harness also
  captures the `THREE.Scene`/`PerspectiveCamera`/`WebGLRenderer` instances
  the piece constructs (e.g. by monkey-patching `THREE.PerspectiveCamera`'s
  constructor to record the last-created instance before handing `THREE` to
  `window.sketch`, OR by having the harness's own render loop call
  `renderer.render(scene, camera)` using captured references instead of
  letting the piece run its own `requestAnimationFrame` loop — **read the
  source to confirm which**), then attaches `new OrbitControls(camera,
  canvas)` + `createKeyboardNavigation(...)` + `createFloorClickNavigation(...)`
  to the captured camera, and renders the metadata overlay (1205-1252) as an
  HTML overlay on top of the canvas.
- If (b) (postMessage bridge): the existing `srcdoc` iframe stays, but a
  small protocol is added to `piece_render_document()`'s `bootThree()` (or a
  variant used only here) to `postMessage` the constructed
  scene/camera/renderer back to the parent immersive page, which then injects
  `OrbitControls` via a second message, or — more likely — the iframe itself
  is given the OrbitControls module and attaches it internally, with the
  parent page only providing the metadata overlay + fullscreen chrome.

Given the ambiguity, **(a) is more likely** based on the "fully immersed...
fully move and navigate" framing (in-iframe pointer/keyboard capture across
an `allow-scripts allow-same-origin` sandbox boundary is awkward for
OrbitControls' `pointermove`/`wheel` listeners). Default to (a) unless
reading `ImmersiveThreePieceStage` shows otherwise.

### Metadata overlay content (from immersive-piece.tsx ~1205-1252)

Read this range for exact markup/copy, but it should include at minimum:
title, engine label (`Three.js`), interaction hints (drag to orbit,
scroll/pinch to zoom, arrow/WASD + click-floor to move), alt text /
description if present, and a link back to `/pieces/{id}` (source URL). Port
this as an HTML overlay (`position:fixed`, semi-transparent panel, matching
the existing `.immersive-bar` styling conventions in `ImmersiveController`'s
current `<style>` block — reuse `.immersive-bar`'s visual language, extend
with a metadata panel).

### Accessibility

Per AGENTS.md Core Constraints ("Accessibility is required: semantic HTML,
ARIA labels, keyboard navigation, sufficient contrast") — the metadata
overlay must be real DOM (not canvas-drawn text), with `role="region"` +
`aria-label="Piece information"`, and the "Back" link must remain keyboard-
reachable (`tabindex` not removed). Keyboard navigation of the 3D scene
(arrows/WASD) is an *enhancement* on top of, not a replacement for, normal
page keyboard navigation — don't trap focus inside the canvas.

---

## Phase 2 — Single p5/c2/svg piece: gallery framing

Route: `/immersive/pieces/{id}` where the version's `engine in
['p5','c2','svg']` (and, per the user's "image media" requirement, also any
standalone media-asset immersive route such as `/immersive/images/{ref}`).

### Mechanism

1. PHP renders a page containing:
   - A `<div id="stage">` (full-viewport) for the Three.js gallery room.
   - A **hidden** (or off-screen, `position:absolute; left:-9999px`)
     `<iframe>` using the existing `piece_render_iframe($piece, $version,
     <presentation height>)` — this is the piece's normal 2D runtime,
     unchanged.
2. `immersive-gallery.js`'s `mountGalleryPiece()`:
   - Computes `aspect` from the piece's natural presentation size (read
     `createPresentationSurface`/`drawContainedIntoPresentationSurface`,
     immersive-gallery.ts:260-308, for the exact normalized size — summary
     mentions a "normalized 1200×900 presentation surface" for p5).
   - Calls `createMountedGalleryShell(stageEl, aspect,
     NORMALIZED_PRESENTATION_GALLERY_PROFILE)` to build the room (floor, back
     wall, frame, lighting, camera, OrbitControls) — quoted constants above;
     full body at immersive-gallery.ts:82-195, read for exact mesh setup.
   - Creates a `THREE.CanvasTexture` from a `<canvas>` that
     `drawContainedIntoPresentationSurface()` continuously draws the hidden
     iframe's content into (since cross-origin/sandboxed iframes can't be
     drawn via `drawImage` directly if `srcdoc` content is treated as
     opaque — **check**: `srcdoc` iframes are same-origin to the parent, so
     `iframe.contentWindow` access + `html2canvas`-style capture, OR — more
     likely, given the platform is a SPA that renders the piece's React
     component directly rather than via iframe — the platform may run the
     piece's `sketch` function in the SAME document and capture its
     `<canvas>` element directly via `canvas.getContext('2d').drawImage`
     each frame. **Read `immersive-piece-runtime.ts` in full (187 lines) —
     `createImmersiveHost`/`resolveSketchFactory` likely show exactly this:
     running the piece's sketch in-page (not in an iframe) and handing the
     resulting `<canvas>` to `drawContainedIntoPresentationSurface`.** If so,
     Phase 2's PHP page should run the piece's `generated_code` in-page (same
     pattern as Phase 1's option (a)), not via a hidden iframe — apply
     `piece_render_document()`'s `bootP5`/`bootCanvasRuntime` logic inline,
     targeting an off-screen `<canvas>`, then texture-map that canvas.
   - Wires up `OrbitControls` + `createFloorClickNavigation` +
     `createKeyboardNavigation` for room navigation (NOT navigation of the
     piece itself — the piece keeps running its own animation on the framed
     surface).
   - Renders the same metadata overlay pattern as Phase 1 (title, engine
     label via `engineLabel()`, prompt/alt text, source link, interaction
     hints) — per the user's "should have the same degree of information
     available in the original platform app."

### Image media

For a media asset (`media_assets`/`media_files` image), there's no
"sketch" — `mountGalleryPiece()` (or a sibling `mountGalleryImage()`)
should instead `new THREE.TextureLoader().load(imageUrl)` directly onto the
frame's `artMesh`, using `computeMountedArtworkLayout(imageAspect,
NORMALIZED_PRESENTATION_GALLERY_PROFILE)` for sizing. This is the simple
case — no canvas-capture loop needed.

### Resolved checkpoint — standalone image immersive route

The platform has `/immersive/images/{encodedRef}` (helpers in
`immersive-view.ts`: `normalizeImmersiveImageRef`, `encodeImmersiveImageRef`,
`buildImmersiveImageHref`, `readImmersiveImageMetadata`). The PHP app now
implements `/immersive/images/{ref}` as a compatibility route because the
platform source and memory files confirmed it was an existing public surface.
It is non-canonical, documented in `docs/api.md`, and renders through the
same gallery wall renderer used for exhibit images.

---

## Phase 3 — Exhibit wall: all pieces as gallery frames + progressive budget

Route: `/immersive/exhibits/{slug}`.

### PHP changes — `ImmersiveController::exhibit()`

1. Read `$exhibit['rows']` / `$exhibit['cols']` (already present via
   `PlatformExhibit::findBySlug()`'s `SELECT *` — confirm non-null, default
   to `1`/`count($items)` if absent/zero, matching
   `createMultiFrameExhibitWall(stage, frameCount, rows = 1, cols =
   frameCount, ...)`'s defaults).
2. Replace the flat CSS grid with: a full-viewport `<div id="stage">` for the
   Three.js wall, plus per-item **off-screen runtime hosts** — for each
   `art_piece` item, an off-screen container that will run that piece's
   `generated_code` in-page (same in-page execution model as Phase 2, NOT
   `piece_render_iframe`) so its `<canvas>` can be captured as a texture when
   "live"; for each `media_asset`/image item, just its URL (no runtime host
   needed — `THREE.TextureLoader` handles it directly).
3. Pass to `immersive-gallery.js`'s `mountExhibitWall(stage, items, rows,
   cols)`:
   - `items`: ordered array (matching `PlatformExhibit::itemsFor()`'s
     `sort_order ASC, item_id ASC`), each
     `{ kind: 'piece'|'image', engine?, title, subtitle, runtimeHostId?,
       imageUrl?, thumbnailUrl? }`.
   - `mountExhibitWall` calls `createMultiFrameExhibitWall(stage,
     items.length, rows, cols, labels)` where `labels[i] = { title:
     items[i].title, subtitle: items[i].kind === 'piece' ?
     engineLabel(items[i].engine) : 'Image' }` (or similar — confirm exact
     label content against `ExhibitWallStage`'s actual label construction,
     immersive-exhibit-wall.tsx, near where `createFrameLabel` is called).
4. Progressive loading loop (runs on `requestAnimationFrame`, on resize, and
   on `controls.target`/camera change):
   - `liveBudget = getProgressiveExhibitLiveBudget(window.innerWidth)`.
   - `liveSlots = selectProgressiveExhibitSlots(items, slots.map(s =>
     s.center), controls.target, liveBudget)` — only `items[i].kind ===
     'piece'` entries are eligible.
   - For each piece item:
     - If `i ∈ liveSlots` and not yet booted: boot its off-screen runtime
       host (run `generated_code` against an off-screen canvas, same as
       Phase 2), then start texture-capturing that canvas into
       `slots[i].artMaterial.map` each frame.
     - If `i ∉ liveSlots`: ensure the slot shows a **static thumbnail**
       instead — use `art_pieces.thumbnail_url` if set
       (`isValidArtPieceThumbnailUrl` — platform helper
       `art-piece-thumbnail-url.ts` — PHP equivalent: validate it's either a
       local `/api/media-assets/...`/`/media/...` URL or an absolute URL
       before using it), else a neutral placeholder texture/color (match
       `artMaterial`'s default `#e8e4de` from `createMultiFrameExhibitWall`).
     - If a previously-live slot becomes non-live, stop its runtime
       (cancel its animation frame / dispose its canvas context) and swap
       back to the thumbnail — don't leave orphaned runtimes accumulating.
   - For each image item: always load via `THREE.TextureLoader` (not subject
     to the budget, per `selectProgressiveExhibitSlots`'s `item.kind !==
     "piece"` exclusion).
5. Three.js pieces in an exhibit: per the user's spec, they are **just
   another frame** — when "live", their `generated_code` runs against an
   off-screen canvas and that canvas is texture-mapped onto the frame, **no**
   OrbitControls/keyboard takeover of the viewer (the wall's own
   OrbitControls remain in control). This is the same runtime-hosting
   mechanism as p5/c2/svg pieces in this phase — engine-specific handling is
   confined to `piece_render_document()`'s existing `bootThree`/`bootP5`/
   `bootCanvasRuntime` branches (reuse that logic, targeting the off-screen
   canvas).
6. Metadata: frame labels (title + engine/Image subtitle) via
   `createFrameLabel`. A click/tap on a frame could optionally navigate to
   that item's own `/immersive/pieces/{id}` (Phase 1/2) or
   `/pieces/{id}` — **check `ExhibitWallStage` for whether frame-click
   navigation exists**; if the platform doesn't do this, don't add it (avoid
   inventing new interactions not in the reference).

### Confirming "exhibits viewable as gallery pieces" (user's mid-stream ask)

Once Phase 3 is built, `/immersive/exhibits/{slug}` **is** the
"gallery piece" view for an exhibit (a multi-frame gallery wall). Verify:

- `/pieces` (or wherever exhibits are linked from in the public site —
  search for existing links to `/immersive/exhibits/`) links correctly.
- `PlatformExhibit::all()` exhibits with `item_count > 0` each produce a
  working `/immersive/exhibits/{slug}` wall.
- If no public listing of exhibits currently exists, that's a **pre-existing
  gap** outside this round's scope — note it in `DECISIONS.md` as a
  follow-up rather than silently adding new public routes (Rule 3/5).

---

## Phase 4 — Post-embed progressive loading

User: *"All loading (posts and exhibits) should mimic what's shown in the
platform app."*

### Find the current PHP post-embed mechanism first

Search `public/app/models/BlogPost.php` and the post-rendering view(s) (under
`public/app/views/blog/`) for how an art piece gets embedded inside post
content (likely either a shortcode like `[piece:123]` expanded server-side
into `piece_render_iframe(...)`, or raw `<iframe src="/embed/pieces/123">`
HTML stored in the post body). Then find the platform equivalent — search
`platform/artifacts/microblog/src` for the post-content renderer that handles
embedded art pieces (the component that turns an embed marker into
`<ArtPieceRenderer>`/similar — exact name not confirmed in this pass, search
for "embed" + "art" + "piece" together, or for usages of
`buildPieceGalleryEmbedHtml`/`buildImageGalleryEmbedHtml`/
`INTERACTIVE_IMMERSIVE_EMBED_SANDBOX` from `immersive-view.ts`, which are
strong candidates for the embed-HTML builder used in post content).

### Target behavior

Whatever the current PHP embed mechanism is, change it so that:

1. Initial render shows a **static placeholder** — the piece's
   `thumbnail_url` if set, else a neutral placeholder (matching the exhibit
   wall's non-live-slot treatment in Phase 3 for visual consistency), at the
   embed's normal size — **not** a booted iframe.
2. An `IntersectionObserver` (vanilla JS, add to a shared
   `public/assets/js/embed-lazy-boot.js` or fold into
   `immersive-gallery.js` if it's already loaded on post pages — check
   whether post pages currently load any piece-related JS at all) watches
   each embed placeholder; when it enters the viewport (with some margin,
   e.g. `rootMargin: '200px'`), replace the placeholder with the live
   `piece_render_iframe()`/`<iframe srcdoc>` (booting the real runtime).
3. Once booted, an embed stays booted (no un-booting on scroll-away) — this
   matches "don't eagerly boot" without adding the complexity of the
   exhibit wall's live/frozen swap logic, which is reserved for the 3D wall
   where many pieces share a budget. Confirm this simpler one-way lazy-boot
   matches what the platform actually does for **post** embeds specifically
   (as opposed to the exhibit wall's bidirectional swap) by reading the
   platform's post-content embed component — if the platform *does* do
   bidirectional swapping for posts too, port that instead.

---

## New/changed routes summary

The PHP app now serves the enhanced `/immersive/pieces/{id}` and
`/immersive/exhibits/{slug}` routes plus the platform compatibility route
`/immersive/images/{ref}`.

No `docs/api.md` changes expected (no new JSON API endpoints). No
`docs/dependencies.md` changes (Three.js + OrbitControls are loaded from the
same `cdn.jsdelivr.net/npm/three@0.160.0` CDN already used by
`piece-render.php` — not a *new* dependency, just a new import
(`examples/jsm/controls/OrbitControls.js`) from an already-approved CDN. If
`docs/dependencies.md` doesn't yet mention the jsdelivr/three.js CDN at all,
add an entry for it now, since this round meaningfully increases reliance on
it — apply the Rule 6 template even though the vendor was already in use:
"This dependency [loading Three.js from jsdelivr CDN] ... if jsdelivr changes
its API/shuts down, all piece rendering AND the new immersive views break.
The self-hosting alternative is vendoring `three.module.js` +
`OrbitControls.js` into `public/assets/vendor/three/`." — note this as a
pre-existing risk being amplified, get a quick sign-off before vendoring vs.
continuing to rely on the CDN.)

---

## Verification

1. `php -l` on every changed/new `.php` file; lint the new
   `public/assets/js/immersive-gallery.js` (and any companion JS files) with
   whatever JS tooling the project already uses for `public/assets/js/*` (if
   none, at minimum confirm it parses via `node --check` if Node is
   available, or load it in a browser console and check for syntax errors).

2. `php -S 127.0.0.1:8080 -t public public/index.php`.

3. **Phase 1**: find a piece with `engine = 'three'` (query
   `art_piece_versions` or use `/admin/pieces` filtered by engine — Round 3's
   engine-whitelist fix is a prerequisite for this to be reliable). Visit
   `/immersive/pieces/{id}`. Confirm: full-page 3D scene (not a flat iframe),
   mouse-drag orbits, scroll/pinch zooms, arrow keys/WASD move the viewpoint,
   metadata overlay shows title/engine/hints/source link, "Back" link works
   and is keyboard-reachable.

4. **Phase 2**: find a piece with `engine` in `p5`/`c2`/`svg`. Visit
   `/immersive/pieces/{id}`. Confirm: a 3D gallery room renders with the
   piece mounted on a framed surface, the piece's own animation/content is
   visible and running on that surface, OrbitControls + floor-click + arrow
   nav move the viewer around the room (not the piece), metadata overlay
   present.

5. **Phase 3**: find (or create, via `/admin/exhibits` if that exists, or
   directly via the existing `PlatformExhibit`/`platform_exhibit_items`
   tables — confirm a non-destructive way to add a test item) an exhibit with
   ≥4 items including at least one `engine='three'` piece, one
   `p5`/`c2`/`svg` piece, and one media-asset image, with `rows`×`cols` ≥ the
   item count. Visit `/immersive/exhibits/{slug}` at three viewport widths
   (resize browser or use device-toolbar presets): <640px → exactly 1 piece
   "live" (others showing thumbnails), 640-1179px → exactly 2 live, ≥1180px →
   3 live. Confirm the `three` piece, when live, renders into its frame
   (not full-immersed) and does not hijack OrbitControls. Confirm images
   always render regardless of budget. Pan/orbit the wall so different items
   are nearest the target — confirm the live set changes accordingly and
   previously-live runtimes are properly torn down (no console errors from
   orphaned `requestAnimationFrame` loops — check browser devtools).

6. **Phase 4**: find a published post with an embedded art piece. Confirm
   initial page load shows a placeholder/thumbnail for the embed (check
   network tab — the piece's runtime assets, e.g. p5.js from cdnjs, should
   NOT load until scrolled into view), and scrolling it into view boots the
   live piece.

7. Confirm no regression to `/pieces/{id}` and `/embed/pieces/{id}` (Phase
   1/2/3 add new rendering paths but must not modify `piece-render.php`'s
   existing exports in a way that breaks these routes — if any shared helper
   is refactored, re-test these routes explicitly).

8. Re-run `php scripts/check-platform-deletion-readiness.php
   --base-url=http://127.0.0.1:8080`.

9. `docs/platform-route-matrix.md`: update the `/immersive/pieces/{id}` and
   `/immersive/exhibits/{slug}` rows to describe the new behavior (full
   immersion for three.js, gallery framing for p5/c2/svg/images, progressive
   multi-frame wall for exhibits).

10. `DECISIONS.md` + `MEMORY.md`: log Round 5 completion, any deviations from
    the platform reference discovered while reading the source (e.g. exact
    in-page-execution vs. iframe-bridge mechanism chosen for Phase 1/2, exact
    `WALL_FRAME_*` constant values), and the CDN-dependency note from "New/
    changed routes summary" above (including whether vendoring was decided).

---

## Suggested implementation order (within this round)

Given the dependency chain (Phase 2/3 reuse Phase 1's "run piece code
in-page against an off-screen canvas" primitive), implement in this order:

1. Port the pure geometry/math functions (all "quoted in full" sections
   above) into `immersive-gallery.js` — no behavior change yet, just
   building blocks.
2. Read `immersive-piece-runtime.ts` (full, 187 lines) and
   `ImmersiveThreePieceStage` (496-1093) to nail down the in-page execution +
   camera-capture mechanism. Implement Phase 1.
3. Implement Phase 2 (reuses Phase 1's execution primitive +
   `createMountedGalleryShell`).
4. Implement Phase 3 (reuses Phase 1/2's primitive per-slot, plus the
   progressive budget functions already ported in step 1).
5. Implement Phase 4 (independent, smaller — can be done in parallel with
   3 if multiple implementers are available).
