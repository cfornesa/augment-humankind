# DECISIONS.md

<!-- Read at every session start. Older sessions are archived in
     docs/decisions-archive.md — the archive is the full record;
     this file holds the Project Profile, OPEN items, and recent
     sessions (current month). When archiving, always carry OPEN /
     REVIEW REQUIRED items forward into this file. -->

## OPEN ITEMS (carried forward from archived sessions)

None.

## 2026-07-09 — Completed WASD decoupling for A-Frame in immersive views and exports

### Decision

The 2026-07-10 entry below asserted that WASD keys were decoupled from camera movement across all piece types, including immersive galleries and downloaded ZIP files. Review showed that A-Frame pieces in the immersive VR view (`/immersive/pieces/{id}` and collection exports) were still relying on A-Frame's default `wasd-controls`, which moves the camera on W/A/S/D — the same keys used for piano-note input when sound is enabled.

To close the gap:

- Added a `disableAFrameWASD()` helper to `public/assets/js/immersive-gallery.js` that intercepts the `wasd-controls` component prototype and ignores `KeyW`, `KeyA`, `KeyS`, and `KeyD` events.
- Wired the shim into `loadAFrameRuntime()` so it runs both when A-Frame is already present and immediately after the `aframe.min.js` script finishes loading.
- Strengthened `public/assets/js/piece-runtime.js` by calling `disableAFrameWASD()` inside `bootAFrame()`'s `script.onload` callback, ensuring the shim is applied after A-Frame is actually available rather than only at module parse time.
- This automatically covers immersive/collection ZIP exports, because `piece_export_patched_immersive_gallery_source()` in `public/app/helpers/piece-render.php` reads directly from `immersive-gallery.js`.
- Added regression tests in `tests/three-runtime-consistency.php` verifying that both runtimes' keyboard navigation maps only Arrow keys and that the A-Frame WASD disable runs after A-Frame loads.

Camera movement remains arrow-key-only across regular views, immersive VR views, and all download bundles.

### Verification

- `php tests/three-runtime-consistency.php` — **Passed: 110, Failed: 0**
- `php tests/art-piece-generation.php` — **Passed: 136, Failed: 0**
- `php tests/art-piece-ordering.php` — **Passed: 4, Failed: 0**

## 2026-07-09 — Defensive fixes for C2 interactive file:// security warning and empty resource URLs

### Decision

Investigated the Safari/WebKit warning in downloaded C2 interactive pieces:

`Unsafe attempt to load URL file:///.../index.html from frame with URL file:///.../index.html. 'file:' URLs are treated as unique security origins.`

Static inspection of the failing download (`/Users/Fornesus/Downloads/3d-unified-minimax-m3-c2js-interactive (2)/`) found no iframe, no empty `src`/`href`/`url()`, and no empty `runtime.loadImage()` calls in the bundled runtime or the piece's own `scripts/piece.js`. The warning's "from frame" phrasing is WebKit's generic message when a `file://` document tries to load itself as a subresource, which can happen from an empty/self-referential resource URL anywhere in the asset graph.

Because the actual production database credentials in `.env` point to a remote host, I did not connect to it to inspect the saved version row for piece 110. Instead, I added defensive guards that protect every engine and every export path from this class of bug:

- **`public/app/helpers/art-piece-generation.php`** `validate_art_piece_media_references()` now rejects:
  - Empty `src`, `href`, and `xlink:href` attributes in HTML.
  - Empty `url()` references in CSS.
  - Empty literal URLs passed to `p5.loadImage()`, Three.js `TextureLoader.load()` / `.load()`, and `runtime.loadImage()`.
  These checks run at generation/validation time so an AI-generated draft with an empty resource URL fails preflight instead of reaching a download.

- **`public/app/helpers/piece-render.php`** export C2 bootstrap `loadImage()` now guards against empty/non-string `src` at runtime, returning a rejected Promise and surfacing a clear error instead of letting the browser resolve an empty URL to `index.html`.

- **`public/app/helpers/piece-render.php`** now strips `sourceMappingURL` comments from every vendored runtime file copied into an export (`piece_export_strip_source_maps()` applied via `piece_export_runtime_source_file()`). This removes a separate class of `file://` warnings caused by browsers trying to load missing `.map` files such as `Tone.js.map`.

- Updated `piece_export_runtime_files()`, `piece_export_immersive_runtime_files()`, `piece_export_mediapipe_hands_runtime_files()`, and the non-immersive sonic runtime injection in `piece_export_build_manifest()` to route vendor sources through `piece_export_runtime_source_file()` so the source-map stripping is applied consistently.

### Impact

- All future AI-generated code with empty resource URLs will fail validation with a repairable error message.
- Existing downloads that happen to pass empty URLs will now surface a clear runtime error instead of a cryptic WebKit security warning.
- Exported bundles no longer reference missing source maps, removing unrelated `file://` console noise.

### Verification

- Static inspection of the reported failing download confirmed no iframe or obvious empty URL in the runtime or `scripts/piece.js`.
- Generated a fresh C2 interactive test bundle and confirmed:
  - `runtime/tone/Tone.js` contains zero `sourceMappingURL` references.
  - `runtime/c2/c2.min.js` contains zero `sourceMappingURL` references.
  - The export `loadImage()` guard string is present in `index.html`.
- `php tests/three-runtime-consistency.php` — **Passed: 110, Failed: 0**
- `php tests/art-piece-generation.php` — **Passed: 136, Failed: 0**
- `php tests/art-piece-ordering.php` — **Passed: 4, Failed: 0**

## 2026-07-10 — Fixed SyntaxError in offline bundle styles; aligned sound controls and visual piano keys state

### Decision

Implemented fixes to resolve the syntax error on offline bundle download pages, and improved the layout, alignment, and responsiveness of the sound control buttons:

- **Syntax Error in style block**: Fixed `Uncaught SyntaxError: Invalid or unexpected token` inside generated offline `index.html` files. The issue was that PHP's heredoc string compiled `\n` to a literal newline byte (`0x0A`), which JavaScript single-quoted strings cannot legally contain. Replaced the concatenated single-quoted style string with a JavaScript template literal (using backticks) which natively supports multi-line newlines.
- **Visual Piano Key Pressed State**: Integrated a callback in `piece-runtime.js`'s standalone piano key listener so that physical keyboard key presses correctly toggle the `.is-pressed` state class on visual white/black keys, matching the host page's behavior. Injected matching `.runtime-key-white`/`.runtime-key-black` hover and pressed styles to `document.head` in `piece-runtime.js`.
- **Sound Control Buttons Alignment**: Grouped the sound toggle and settings trigger buttons into a single flex container row in `pieces/show.php` and `piece-runtime.js`, matching the layout of the downloaded offline version. Enlarged the settings trigger chevron icon to `16x16` (up from `12x12`) for improved clickability/mobile responsiveness, and allowed the dropdown panel to flow naturally rather than using complex absolute calculations.
- **WASD Decoupled from Movement**: Decoupled W, A, S, D keys from keyboard/camera movement controls across all piece types (Three.js and A-Frame) for both online previews and downloaded ZIP files. Removed WASD mapping from Three.js bootloaders and decoupled A-Frame's built-in `wasd-controls` component by intercepting its prototype event handlers synchronously at boot time. Movement is now safely restricted to arrow keys, preventing camera drift when physical keyboard keys are played.

### Verification

- `php tests/art-piece-generation.php` — **Passed: 136, Failed: 0**
- `php tests/three-runtime-consistency.php` — **Passed: 105, Failed: 0**
- Verified that generated offline bundle HTML files contain valid template literal backticks in their style blocks and load without console SyntaxErrors.

## 2026-07-09 — Immersive screenshot button, single-item download simplification, ZIP label, fullscreen click-through fix

### Decision

Follow-up UX fixes to the immersive VR views and regular piece view, prompted
by a user review of the just-shipped GLTFLoader export fix on `/pieces/113`:

- **Immersive screenshot button (web + offline export)**: the "Download PNG"
  action was previously folded into the immersive toolbar's "Open download
  menu" dropdown, requiring an extra click and not matching the regular
  view's own always-visible screenshot button. Added a `screenshot_action`
  option to the shared `immersive_stage_toolbar_markup()` helper
  (`public/app/helpers/immersive-chrome.php`) that renders a standalone
  camera-icon button in the toolbar's left group, next to (not inside) the
  download control. Wired into all 4 call sites: live single-piece
  (`public/app/views/immersive/piece.php`), live collection
  (`public/app/views/immersive/collection.php`), and both offline export
  documents (`piece_export_immersive_document()` and
  `collection_export_document()` in `public/app/helpers/piece-render.php`).
- **Single-item download menu → direct button**: once PNG moved out, the
  remaining download dropdown in the live views only ever holds one item
  ("Download ZIP"). A one-item menu is an unnecessary extra click, so
  `immersive_stage_toolbar_markup()` now renders a single `download_items`
  entry as a plain icon button/link directly, only falling back to the
  dropdown+menu pattern when there are 2+ items. (Offline exports never had
  a ZIP item in the first place — a standalone export can't re-download
  itself — so their download control is just the screenshot button now, no
  dropdown at all.)
- **"Download Piece" → "Download ZIP"**: renamed for clarity across
  `public/app/helpers/public-copy.php` (`download_piece_label` default +
  admin field label) and the JS-built gallery full-view overlay
  (`public/assets/js/immersive-gallery.js`).
- **Fullscreen click-through bug (regular `/pieces/{id}` view)**: entering
  the CSS-fallback fullscreen overlay (`piece-fullscreen.js`, used when the
  native Fullscreen API is unavailable/blocked) left the site header nav and
  footer both visibly and functionally on top of/reachable through the
  piece. Root cause, found via direct DOM hit-testing
  (`document.elementFromPoint`) rather than trusting screenshots (a
  screenshot-tool artifact initially pointed at the wrong fix): `<header>`
  (z-index:30), `<main>` (z-index:1), and `<footer>` (z-index:1) are sibling
  stacking contexts under `<body>`; `<footer>` wins the DOM-order tiebreak
  against `<main>`, and `<header>` beats it outright — no z-index raise
  *inside* `<main>` (where `.piece-stage-fullscreen` lives) could ever
  escape that. Fixed in `public/assets/styles.css` by raising `<main>`'s own
  z-index to 9600 while `body.piece-fullscreen-locked`, so its entire
  subtree (including the fullscreen overlay) paints above both siblings;
  also hides the floating `.theme-toggle` button (z-index:9000, a
  `<body>`-level sibling that isn't inside any stacking-context-forming
  ancestor) outright during fullscreen as a belt-and-suspenders measure.
  Verified live: footer/header link coordinates now hit the fullscreen
  overlay instead of the underlying page elements.
- **Reverted**: a same-session attempt to also add a standalone screenshot
  button to the regular view's *live* canvas (top-left, mirroring the
  top-right sound/fullscreen icons) was reverted after user review — the
  regular view already has a working "Download PNG" button
  (`.piece-fullscreen-bar` / `.piece-action-row` in
  `public/app/views/pieces/show.php`), making the new button redundant. The
  regular view's *downloaded/exported* bundle keeps its existing screenshot
  button (`piece_export_screenshot_overlay_*()`, unchanged) — only the live
  iframe addition (`piece_render_document()`) was removed, along with the
  now-unused `$position`/`$includeFullscreenButton` parameters those
  functions had gained to support it.

### Verification

- `php tests/art-piece-generation.php` — **Passed: 136, Failed: 0**.
- `php tests/three-runtime-consistency.php` — **Passed: 105, Failed: 0**.
- Verified live via browser automation on the local PHP dev server
  (`127.0.0.1:8080`): immersive standalone screenshot button renders and
  responds to clicks (web + offline export markup checked directly); the
  single-item download control is a plain button with no dropdown; the
  regular view's fullscreen overlay now blocks clicks to header/footer
  (confirmed via `elementFromPoint`, not just visually); the regular view's
  live canvas screenshot button was confirmed removed while the export
  bundle's own screenshot button was confirmed still present.

## 2026-07-09 — Fixed broken GLTFLoader in direct-open Three.js exports; added export syntax guard

### Decision

A user reported that downloading the Three.js piece at `/pieces/113` (both the
regular and immersive VR view downloads) and opening the exported `index.html`
via `file://` threw `Uncaught SyntaxError: Unexpected token 'export'` in
`GLTFLoader.global.js`, followed by `TypeError: THREE.GLTFLoader is not a
constructor`. This affected only the download/export path — the live CDN
rendering path (`piece_render_document()` / CDN-mode `piece_export_document()`)
was never affected.

Root cause: `piece_export_gltfloader_global_source()` in `piece-render.php`
(added in the "GLB/GLTF asset fidelity" decision above) converts the vendored
ES-module `GLTFLoader.js`/`BufferGeometryUtils.js` into a classic global
script, but only rewrote each file's *leading* `import` and *trailing*
`export { ... };` block. It missed three **mid-file** `export function`
declarations in `BufferGeometryUtils.js`, which leaked through verbatim and
crashed the whole classic script at parse time — so `window.THREE.GLTFLoader`
never got assigned.

Fixing that surfaced a second, previously-masked bug: once the SyntaxError was
gone, the same file threw `Identifier 'BufferAttribute' has already been
declared`, because `BufferGeometryUtils.js` and `GLTFLoader.js` both
destructure overlapping THREE symbols into the same shared script scope via
`const`.

Fix (in `public/app/helpers/piece-render.php`):

- Added `piece_export_strip_module_syntax()` — strips mid-file
  `export function`/`export const`/`export class` generically, reused across
  all three `*_global_source()` conversion functions (three.js, OrbitControls,
  GLTFLoader), not just the specific gap found today.
- Added `piece_export_assert_no_module_syntax()` — a fail-loud guard run after
  conversion in each of those three functions that throws `RuntimeException`
  if any bare `export`/`import` keyword survives, so a future upstream
  Three.js file-shape change fails at generation time (server-side) instead of
  shipping a silently-broken download.
- Changed the `const {...} = window.THREE;` leading-import rewrites in
  `piece_export_gltfloader_global_source()` to `var`, since
  `BufferGeometryUtils.js`'s and `GLTFLoader.js`'s converted sources share one
  function scope and both declare overlapping identifiers (e.g.
  `BufferAttribute`, `toTrianglesDrawMode`) — `var` tolerates the harmless
  redundant re-declaration where `const` does not.
- Added `<link rel="icon" href="data:,">` to all three export document
  templates (regular, immersive, collection) as a low-confidence mitigation
  attempt for a second, separately-reported console warning (`Unsafe attempt
  to load URL file:///.../index.html from frame with URL
  file:///.../index.html`) — no application code path was found to explain
  that warning after exhaustive static search; this is a cheap, safe,
  testable guess (implicit favicon-fetch under `file://`), not a confirmed
  fix. Needs live-browser confirmation.

### Verification

- `php tests/art-piece-generation.php` — **Passed: 136, Failed: 0** (added a
  new execution-level test: extracts the generated `*.global.js` files from a
  real export bundle and runs `node --check` on each — the existing tests only
  asserted on generated PHP text and never executed the JS, which is why this
  shipped undetected. Also added a direct unit test for the new guard
  function.)
- `php tests/three-runtime-consistency.php` — **Passed: 105, Failed: 0**
  (no regressions).
- Reproduced the original bug against the pre-fix code (confirmed it throws
  the exact reported `SyntaxError`), then confirmed the fix resolves it: ran
  the actual generated `three.global.js`/`GLTFLoader.global.js`/
  `OrbitControls.global.js` from a real export bundle in a Node `vm` sandbox —
  `THREE.GLTFLoader` is a function and `new THREE.GLTFLoader()` succeeds.
  Repeated for the immersive VR view bundle with the same result.

## 2026-07-09 — GLB/GLTF asset fidelity and offline GLTFLoader export support

### Decision

Model uploads are treated as authored visual assets, not just geometry. A
generated Three.js piece that loads a GLB/GLTF from `/media/{id}` must preserve
the loaded model's embedded materials, textures, UVs, transparency, vertex
colors, and material color data by default. Generated code may transform,
scale, rotate, animate, frame, light, and set shadow flags on the loaded model,
but it must not replace loaded mesh materials with new `THREE.Mesh*Material`
instances unless a future explicit restyling workflow is designed.

Implementation:

- Updated `art_piece_model_capability_prompt('three')` with the material
  preservation contract.
- Added a narrow Three.js preflight guard that rejects GLB/GLTF loader code
  which traverses a loaded `/media/{id}` model and assigns replacement
  `THREE.MeshBasicMaterial`, `MeshStandardMaterial`, `MeshPhongMaterial`,
  `MeshLambertMaterial`, `MeshPhysicalMaterial`, `ShaderMaterial`, or
  `RawShaderMaterial` instances to loaded mesh `.material`.
- Vendored Three `0.160.0` `GLTFLoader.js` plus its required
  `BufferGeometryUtils.js` helper under `public/assets/vendor/piece-runtime/three/`.
- Regular ZIP exports now load `runtime/three/three.global.js`,
  `runtime/three/GLTFLoader.global.js`, and
  `runtime/three/OrbitControls.global.js` as local classic scripts, with
  `window.THREE.GLTFLoader` attached before generated code runs.
- Immersive and collection ZIP exports include both module and classic-global
  GLTFLoader runtime files. The classic immersive runtime uses
  `window.GLTFLoader` instead of a dynamic `import()`/Blob fallback so
  direct-open `file://` exports avoid unique-origin failures.
- CDN-backed standalone Three exports import `GLTFLoader` from the existing
  `three/addons/` import map and expose it on the instrumented `THREE` object.

Existing data repair:

- Repaired art piece `113`, version `257`, only because the stored code still
  matched the known bad pattern: it loaded `/media/194`, traversed
  `gltf.scene`, and replaced each mesh material with a new gray/white
  `MeshStandardMaterial`.
- The repair removed only that destructive replacement block and kept the
  model's embedded material intact, while preserving scene setup, background
  objects, animation, camera, scale, and all unrelated generated code.

### Verification

- `php tests/art-piece-generation.php` — **Passed: 134, Failed: 0**
- `php tests/three-runtime-consistency.php` — **Passed: 105, Failed: 0**
- Temporary local PHP server on `127.0.0.1:8081` returned `HTTP/1.1 200 OK`
  for `/pieces/113`; server was stopped after verification.

## 2026-07-09 — Unmute preview button, metadata sound toggle, and refinement prompt requirement fix

### Decision

Implemented fixes and features to resolve the sound previewing, metadata sound toggles, empty refinement prompts during sound-only refinement, and cleaned up pre-existing consistency test failures:

- **Refinement Acceptance Error**: Allowed an empty visual refinement prompt if a sound-only refinement took place. Automatically falls back to generating a descriptive prompt: `Update sound design: [feel]`.
- **Sound Toggle Metadata Switch**: Added an `Enable sound playback on this piece` checkbox under the Metadata tab in `form.php`, visible only if sound is associated with the piece. Stored as an `enabled` boolean within the `sonic_params` JSON.
- **AI Refinement Preview Audio Parsing**: Correctly parsed JSON strings returned from the server into objects on the client-side so the sound controller initializes correctly. Hides the unmute button when disabled.
- **Public and Immersive Surfaces Sound Disablement**: Disabled sound action buttons, synth initialization, and export payloads if `enabled` is `false` inside the piece's `sonic_params`.
- **Runtime Consistency Test Suite Fixes**: Updated assertions in `tests/three-runtime-consistency.php` to align with the new engine-less sound architecture and matched the refactored gyroscope helper names (`createSharedGyroController`, `requestCalibration`, `calibrateGyroToCurrentView`).
- **Domain Scoping Preservation on Retry**: Integrated an explicit `purpose_domain` property inside the client's `basePayload` sent from `form.php`. This guarantees the server respects the sound-only partition across all retry attempts (such as "Request stronger changes" where visual prompts are empty but feedback text is appended to the request prompt). Sound-only instruction overrides now preserve the feedback instruction.
- **Layout Link Relocation**: Moved the "Setup Checklist" header link in `layout.php` into the main sidebar navigation panel to prevent accidental misclicks.

### Verification
- Checked syntax across all changed PHP and view files.
- Run `php tests/three-runtime-consistency.php` — **Passed: 105, Failed: 0** (all tests passed).
- Run `php tests/art-piece-generation.php` — **Passed: 126, Failed: 0** (all tests passed).

## 2026-07-09 — AI Refine purpose-domain partition: original prompt is context, not the goal; regen inherits lineage

### Decision

Sound-only AI Refine on `/admin/pieces/{id}/edit` was crashing with the
provider error `"Cannot read 'image.png' (this model does not support
image input)"` within ~10s. Investigation showed the application code
never attaches image input at all — `AiProviderClient::generate()` is
strictly text-only across every transport (the only image-attaching
method, `describeImage()`, is the alt-text path, separate from refine).
The proximate cause was the agentic provider proxy auto-resolving
`image.png` referenced in a piece's stored original creative prompt,
which was still being sent to a text-only model even in sound-only mode —
because the code only quarantined the visual *code* (HTML/CSS/JS blocks)
in sound-only refine, not the original creative *prompt* (which still
held the reference).

The real underlying principle — which the user named and which this fix
implements — is **per-domain purpose partition**, summarized as:

> When ONE refinement direction is filled, ONLY that direction is in
> scope — the other domain's content is context that must not change.
> When BOTH are filled, only then may both change.

Applied to refine:
- Replaced the binary `$soundOnly` flag with a 3-state `$purposeDomain`
  (`'visual' | 'audio' | 'audio_visual'`) on both
  `art_piece_refine_user_prompt()` and `art_piece_refine_repair_prompt()`
  in `public/app/helpers/art-piece-generation.php`.
- Every refine request now opens with an explicit
  `### PURPOSE OF THIS REFINEMENT` header declaring which domain is IN
  SCOPE and which is OUT OF SCOPE and must not change, so the model — and
  any tool-using proxy layer in front of it — understands scope
  unambiguously regardless of any other context included.
- The original creative prompt is now labeled **CONTEXT** ("for reference
  only — the directive is the PURPOSE above; do not treat this prompt as
  the goal of this refine"), not "stay true to it," in every mode. This
  applies to visual-only and audio+visual refine too, per the user's
  framing ("prior prompts need to be contextualized as context, rather
  than the goal").
- In audio-only mode, the original creative prompt is additionally run
  through a new `art_piece_elide_out_of_scope_refs()` helper that
  neutralizes bare file-name references (`image.png`, `/image/N`,
  model file extensions, etc.) with a descriptive placeholder
  `[(visual asset reference elided; out of scope for this audio-only refine)]`
  (chosen over the quoted-literal form `backticked:image.png` per the
  user's preference — the descriptive form explicitly disclaims the
  reference as out of scope, which both an agentic proxy and a reasoning
  model interpret more reliably than a quoted token). Visual-only and
  audio+visual refine keep the original prompt text verbatim (visual
  assets are in scope in those modes).
- The outbound patch force-clear backstop in `refineAi()` (lines ~1904+
  in `PiecesAdminController.php`) keeps discarding any hallucinated
  html/css/js patches in audio-only mode — the inbound quarantine (no
  visual code shown to the model) and the outbound backstop (no visual
  patches accepted) form a two-sided guarantee that an out-of-scope
  domain cannot change.

Applied to regenerate (`/admin/pieces/generate/regenerate`):
- **Regenerate inherits its purpose_domain PURELY from its lineage —
  lineage is the only determinant** (per the user's explicit caveat:
  regeneration's lineage, whether from piece generation or from a prior
  refine, is the SOLE determinant of whether regeneration does visual,
  audio, or both — never recomputed from new user inputs at regenerate
  time, because regenerate only amplifies existing scope and never
  changes it).
- Two audio-lineage constants are now captured at generation time into
  the pending-generation session (`storePendingGeneration`) and persisted
  in its `original` block: `sound_feel` (the prose the admin wrote at
  generate time, reused to re-emit sonic capability instructions on an
  audio-in-scope regenerate) and `sound_enabled_lineage` (a bool
  marking that this generation was an audio+visual generation, even if
  the model returned a null sonic block; null sonic_params would
  otherwise obscure the audio intent that the lineage must preserve).
  These render as read-only hidden inputs in `generate-preview.php` and
  pass through unchanged in `buildRegeneratePayload` to the regenerate
  endpoint, which derives `purposeDomain` purely from them.
- For generation lineage the visual domain is ALWAYS in scope (pieces
  MUST have a visual prompt; the sound prompt is the only optional
  domain). So generation lineage yields `'audio_visual'` or `'visual'`
  — never `'audio'` (sound-only lineage from generation is impossible;
  the same arch will accommodate a future refine-lineage regenerate flow
  where `'audio'` (sound-only refine) lineage would inherit untouched).
- The regenerate instruction is now lineage-aware: audio+visual lineage
  rebuilds visuals AND sound; visual-only lineage rebuilds visuals and
  forbids the model from producing sound. Sonic capability instructions
  are appended to the system prompt only when the lineage placed audio
  in scope (parity with `refineAi()`), ended a silent drop where
  regenerate previously produced visual-only output for a sound-enabled
  generation.
- The regenerated sonic_params (or `null` when visual-only lineage)
  persist into the pending preview's `current` block via
  `updatePendingGenerationCurrent()`, return in the JSON response, and
  `applyRegeneratedPreview` in `generate-preview.php` writes them back
  into the `sonic_params` hidden input BEFORE `renderPreviewDocument()`,
  so the preview iframe (which reads `currentSonicParams()` live from
  that input) actually surfaces the new instrumentation audibly.
  The save form already read this hidden input via
  `validate_art_piece_sonic_params($_POST['sonic_params'] ?? null)`, so
  the regenerated sound flows through unchanged to the persisted
  `art_piece_versions.sonic_params` column.
- Sonic-aware empty-patches check: visual-only lineage requires at least
  one visual patch (regenerate without change is not a valid outcome);
  audio+visual lineage accepts a regenerated sonic block alone as a valid
  regenerate (sound improved, visuals preserved).

### Behavior changes flagged per Rule 7 (both explicitly approved)

1. The original creative prompt relabeled as **context, not the goal**
   in EVERY refine mode (not just audio-only). Models may now treat the
   prior prompt with less deference in visual-only and audio+visual
   refine too.
2. Regenerate with audio+visual lineage now preserves or improves the
   sound design, where it previously silently dropped the audio lineage
   and produced visual-only output.

### Correction of a prior doc/code mismatch

Two prior DECISIONS.md entries (the 2026-07-08 sound continuation entry
and one above) claim sound is "Gated on `ai_pieces_sound`." That feature
flag does NOT exist anywhere in the PHP code (grep returned 0 matches).
Sound is actually gated only by `art_piece_sonic_params_supported()`, a
schema-column probe (true when `art_piece_versions.sonic_params`
exists) — there is no separate `ai_pieces_sound` feature flag
registered in `public/app/helpers/features.php` and no router feature
key for it. The two `ai_pieces_sound` mentions have been corrected
below; per AGENTS.md convention (sound stays gated by the schema probe
only, which is sufficient under the project's feature-flag rules), no
new flag is being introduced.

## 2026-07-08 — Sound continuation: regular-view + offline export parity, fixed toggle bug, exhibit-wall sonification

### Decision
Picked up a prior sound-feature plan that had run out of tokens mid-implementation.
Audited the working tree against the plan first rather than re-deriving it, then
finished the outstanding pieces:

- **A-Frame regular-view audio** (`piece-runtime.js`): wired
  `createPieceRuntimeAudioController` into `bootAFrame`'s scene `loaded` event,
  mirroring the existing Three.js wiring.
- **`/pieces/[id]` sound toggle**: added the missing parent-page button
  (`pieces/show.php`, styled to match the existing fullscreen toggle) and its
  postMessage wiring (`piece-fullscreen.js`), gated on `engine ∈ {three, aframe}`
  and `sonic_params` present. The iframe side (`piece-runtime.js`) already had a
  controller waiting for exactly this message but nothing was sending it.
- **Bug found and fixed**: `piece-runtime.js`'s audio controller unconditionally
  ran `toggleBtn.disabled = true` on first unmute even when `toggleBtn` is `null`
  (the normal case when a host page owns the button) — this threw and silently
  aborted the Tone.js load before it started. Now guarded like its `finally`
  counterpart already was.
- **Second, more consequential bug found and fixed**: `createAudioController`'s
  toggle-button lookup (`stageEl.querySelector('[data-immersive-sound-toggle]')`)
  could never succeed — `immersive_stage_toolbar_markup()` renders the toolbar as
  a *sibling* of `#immersive-stage`, not a descendant, so `querySelector` always
  returned nothing, no click listener was ever attached, and sound silently never
  worked in the immersive view *or* any export — despite the prior session's
  DECISIONS entries describing Phase 2 as "fully wired." Fixed by looking up the
  button from `document` instead (there is exactly one immersive stage per page).
  This one regression fix is what actually makes every surface below audible.
- **Offline export parity**: added a new `piece_export_sonic_script()` inlining a
  self-contained Tone.js controller + toggle button directly into
  `piece_export_document()` (bundle mode embeds Tone.js as a Blob URL — same
  technique already used for OrbitControls — so a sound-bearing standalone
  export needs no network). Immersive single-piece exports bundle
  `runtime/tone/Tone.js` and pass `sonicParams`/`sound_action` through to the
  mount calls and toolbar, reusing the live `createAudioController` path.
- **Exhibit-wall sonification (new — the inherited plan wrongly assumed this
  already existed)**: `mountExhibitWall` (the collection/exhibit thumbnail-grid
  view, live and exported) had zero audio wiring at all. Built it: only the item
  nearest the current camera focus sonifies; the controller is disposed and
  rebuilt via a new `computeFocusedSlotIndex()` helper whenever focus moves to a
  different item. Wired per-item `sonicParams` through
  `immersive/collection.php` (live) and `collection_export_piece_item_payload()`
  (export), with the sound toggle gated on any item in the collection having
  `sonic_params`.
- **Dead-code cleanup**: removed orphaned `sound_enabled`/`sound_feel` JS
  variable references in `form.php`/`version-form.php` left over from the
  already-completed removal of the manual per-version toggle, and simplified
  `PiecesAdminController::resolveSonicParamsFromPost()` to just preserve the
  current version's value, since no form can submit those fields anymore.
- **Volume boost (follow-up, after user testing reported "no audio at all")**:
  synth output was `-14dB` in all three implementations
  (`immersive-gallery.js`, `piece-runtime.js`, `piece_export_sonic_script()`).
  Verified with a real Web Audio `AnalyserNode` tapped onto `Tone.Destination`
  while driving synthetic keyboard movement (see Verification below) that this
  produced genuine but very quiet signal (~0.034 peak amplitude, ≈ -30 dBFS) —
  plausible under actual "no audio at all" over normal laptop speakers even
  though the trigger→signal pipeline was working correctly end to end. Raised
  to `-6dB` (≈2.5x amplitude) in all three call sites; re-measured under the
  same method at ~0.085 peak, matching the expected +8dB gain exactly.

### Scope corrections vs. the inherited plan
- Regular-view sound is Three.js/A-Frame only (other engines have no camera
  motion on that surface) — this was already correctly scoped in the working
  tree; the immersive view remains all-engines.
- The two DECISIONS entries below this one describing per-version manual sound
  toggles as current UI are superseded — that UI was already removed before
  this session started; generation and AI Refine are the only creation paths.

### Verification
- `php -l` on all touched PHP; `node --check` on `piece-runtime.js` and
  `immersive-gallery.js`.
- `php tests/art-piece-generation.php` — 126/126 passing (fixed the 2 stale
  assertions expecting the removed per-version toggle markup).
- `php tests/three-runtime-consistency.php` — 102/103 excluding 3 pre-existing
  gyro-function-rename failures unrelated to this work (confirmed failing on
  unmodified HEAD too); added assertions for A-Frame audio wiring, the
  `/pieces/[id]` toggle, the toggle-button scoping regression, export bundling,
  and exhibit-wall sonification.
- Browser (piece 112, a Three.js piece with real `sonic_params`): confirmed
  mute/unmute cycles correctly on `/pieces/112`, `/immersive/pieces/112`, the
  downloaded standalone bundle (`file://`-equivalent local server, zero network
  requests for Tone.js), and the downloaded immersive bundle. Exhibit-wall
  sonification verified via a standalone harness importing `mountExhibitWall`
  directly with fabricated multi-item data (no real collection with a
  sound-bearing piece was available to test against directly).
- Audibility (not just "did the code run"): monkey-patched the active
  synth's `triggerAttackRelease` to confirm real trigger calls fire while the
  camera moves (dispatched synthetic held-key events into the piece iframe),
  and tapped a Web Audio `AnalyserNode` onto `Tone.Destination.output` to
  confirm non-zero PCM samples actually reach the output graph — this is what
  caught the volume being too quiet after the toggle-wiring bugs above were
  already fixed and passing every state-flag check.

### Follow-ups (not fixed this session, out of scope)
- A brand-new (not-yet-saved) piece where AI Refine adds sound before the first
  "Save Changes" submit still loses that sound on save — the manual-toggle
  removal left no conduit for that specific edge case. Narrow enough that it
  wasn't fixed here; generation and refine-on-an-existing-piece are unaffected.

## 2026-07-08 — Tone.js Instrumentation Is Version-Level Piece Metadata

### Decision
Tone.js movement sonification for art pieces is stored on
`art_piece_versions.sonic_params`, not on the piece row. Generation,
manual editing, per-version editing, and AI Refine can all create or update
this metadata when the schema column `art_piece_versions.sonic_params`
exists (gated by `art_piece_sonic_params_supported()`, a probe of that
column — there is no `ai_pieces_sound` feature flag registered anywhere
in the PHP).
A sound-only change creates a new current version with unchanged visual code;
turning instrumentation off stores `NULL` for the new version.

The authored "Describe the feel" / "Tone Feel" text is preserved in
`sonic_params.feel` and is shown as Sound Feel in both regular and immersive
piece documentation. Piece 111 was aligned by creating version 231 from version
230 with identical visual code and `{"tempo":90,"scale":"minor","instrument":"fmsynth","feel":"Ethereal, minor scale theremin sound."}`.

### Scope
- `public/app/helpers/art-piece-generation.php`: added Tone Feel
  normalization helpers, support checks, and stable sonic comparison; fixed
  setup's C2 regex backfill pattern so schema alignment can complete.
- `public/app/controllers/Admin/PiecesAdminController.php`: saves
  `sonic_params` through generation, manual edits, version forms, draft
  attempts, AI Refine, and refine accept; sound-only edits create versions.
- `public/app/views/admin/pieces/form.php` and
  `public/app/views/admin/pieces/version-form.php`: expose gated Add
  instrumentation / Tone Feel controls.
- `docs/api.md`, `README.md`, `ALGORITHMS.md`, and `docs/dependencies.md`:
  document admin fields, refine payloads, Sound Feel display, and the
  self-hosted Tone.js dependency boundary.
- `tests/art-piece-generation.php`: covers Tone Feel normalization, form
  exposure, and source-level save/refine contracts.

### Verification
- `php scripts/setup-database.php --yes`
- `php -l` on touched PHP files
- `php tests/art-piece-generation.php` — 126 passed
- `git diff --check`
- `php tests/three-runtime-consistency.php` — Tone/runtime checks passed; the
  two existing gyro assertions still fail unchanged.

## 2026-07-06 — About/Bio System Page Navigation Respects Pages Visibility

### Decision
The About system page, currently used as Bio via slug rename support, now has a real `navigation_items` system row instead of being excluded as a special page-nav case. The system navigation item mirrors the current system page's `show_in_nav`, nav label/title fallback, slug, published status, and deletion state, so the Pages UI checkbox and public navigation agree.

### Scope
- `public/app/models/NavigationItem.php`: added the `about` system navigation registration, hydrates system navigation rows from matching `pages.system_key`, syncs system-page visibility from Pages saves, and keeps `/admin/navigation` toggles aligned back to `pages.show_in_nav`.
- `tests/system-page-navigation.php`: added focused coverage for a visible Bio page rendering as `Bio` -> `/bio` in public navigation.

### Verification
- `php -l public/app/models/NavigationItem.php`
- `php -l tests/system-page-navigation.php`
- `php tests/system-page-identity.php`
- `php tests/system-page-navigation.php`
- `git diff --check`

## 2026-07-06 — Public Copy Subtabs, Footer Consolidation, Widen Text Columns, and CSS Layout Alignment

### Decision
Refactored the `/admin/public-copy` interface into a 5-tab subtab layout ('Portfolio Gallery', 'Portfolio Archives', 'Portfolio Detail Chrome', 'Standalone Art Archives', and 'Shared Public UI') using the `admin-tabs` navigation patterns for improved readability. Visual organization of the 'Portfolio Archives' page was enhanced by grouping field clusters into rule-divided `<h2>` sections.

Moved the `footer_credit` field out of the Public Copy tab layout and consolidated all footer text configuration (Copyright Line and Footer Credit) under a shared **Footer** section in Site Identity → Settings. Upgraded both `site_settings.copyright_line` and `site_settings.footer_credit` to `TEXT` (supporting up to 64KB) in the schema to support HTML formatting and nested structures. Idempotent column modification steps were added to the `setup-database.php` manifest.

Modified both the main layout index and the partial footer views to parse these fields through the HTML sanitizer (adding `<p>` and `<span>` tags to the allowed elements whitelist). The markup container was changed from `<p>` to `<div class="site-footer-text">` to prevent layout breaking from nested paragraph elements. Updated the stylesheet (`styles.css`) to enforce `align-self: flex-start`, `align-content: flex-start`, and `row-gap: 0.5rem` on the footer navigation, preventing vertical stretching and matching the leading/margins of the text block.

### Scope
- `public/app/helpers/public-copy.php`: removed the footer tab from helper list, updated sanitizer allowed elements.
- `public/app/controllers/Admin/PublicCopyAdminController.php`: removed footer tab and special-case save logic.
- `public/app/views/admin/public-copy/index.php`: updated valid tabs list and rendered subtabs layout with sub-section headings.
- `public/app/views/admin/site-identity/index.php`: updated inputs to use textareas, added the Footer group heading and descriptions.
- `public/app/views/partials/footer.php` / `public/index.php`: switched from p to div wrappers, enabled HTML parsing on copyright.
- `public/assets/admin.css` / `styles.css`: added styles for group headings, corrected footer nav flex-stretch.
- `scripts/setup-database.php`: added manifest migration steps for both columns.
- `migrations/2026-07-06-footer-credit-text.sql` / `migrations/2026-07-06-copyright-line-text.sql`: new migrations.

### Verification
- Checked syntax across all changed PHP and view files.
- Verified live database changes (both columns widened to `text` successfully).
- Verified the correct visual layout structure of the footer and group headers.

## 2026-07-05 — Shared Top Stage Toolbar Across All Immersive Surfaces + Broken Piece View Fix

### Decision
All immersive surfaces — `/immersive/pieces/{id}`, `/immersive/collections/{slug}`,
`/immersive/images/{ref}`, and both immersive export documents — render one
shared stage toolbar anchored to the TOP of the stage, built by the new
`public/app/helpers/immersive-chrome.php` (`immersive_stage_toolbar_css()` /
`immersive_stage_toolbar_markup()`) and wired by the new
`setupImmersiveStageChrome()` export in `immersive-gallery.js`. Top placement
is mandatory: the bottom-anchored collection action bar overlapped the
bottom-center "Enable Motion Controls" iOS permission button. Once motion is
granted, the gyro ⟲ toggle mounts into a `data-immersive-gyro-slot` span
reserved inside the toolbar (absolute top-left fallback for old exports).

View-button gating (user-stated absolute requirements): collections get a
slideshow button opening the real slideshow at the active index; P5/SVG/
non-interactive-C2 pieces get a full-size button opening the slideshow-style
overlay with `showDownloadControls: false` (title + × only, no Prev/Next);
interactive C2 pieces open the same overlay with an `interactive: true`
iframe (raw `#c2-interactive-overlay` deleted from piece.php and the piece
export); Three.js/A-Frame pieces render no view button. Export download menus
contain only `Download PNG` (user-confirmed — offline exports cannot
re-download themselves); live surfaces keep `Download Piece` + `Download PNG`.

### Root Cause of the broken piece view
piece.php imported `setupImmersiveStageChrome` from immersive-gallery.js, but
the function was never written — the missing named export failed the whole ES
module link, so no mount function ran and every `/immersive/pieces/{id}`
rendered a black stage. Collections only imported `mountExhibitWall`, which
existed, so they kept working.

### Scope
- `public/assets/js/immersive-gallery.js`: new `setupImmersiveStageChrome()`
  (view trigger + download menu wiring: aria-expanded, focus-first-item,
  capture-phase outside-pointerdown close, Escape close with focus return,
  120 ms deferred close after item clicks); gyro-slot mounting in
  `createGyroToggleButton()`; `showDownloadControls` option and
  `fullView.overlayOptions` pass-through for `createReadOnlyFullViewOverlay`.
- `public/app/helpers/immersive-chrome.php` (new, required by
  piece-render.php, loaded transitively via router.php): toolbar CSS/markup.
- piece.php / collection.php / image.php: shared toolbar replaces the
  view-local CSS+markup (collection's `.iab-*` bottom bar and image.php's
  bottom-corner buttons deleted); Escape handlers now yield to open menus and
  the full-view overlay; PNG button label updates target the inner span so
  the icon survives.
- piece-render.php: both immersive export builders emit the shared toolbar
  (`position:fixed` override) and wire it via `setupImmersiveStageChrome`
  from the exported/embedded runtime; blob-fallback import rewrites verified
  intact.
- Tests updated: art-piece-generation export assertions now check
  `immersive-stage-toolbar`/`data-immersive-download-png`/
  `setupImmersiveStageChrome`; three-runtime-consistency safe-area test now
  checks the shared helper and both views' use of it.
- Docs: README.md and docs/api.md rewritten for the top toolbar, engine
  gating, PNG-only export menu, and slideshow-shell C2 overlay.

### Verification
- `php -l` all touched PHP; `node --check` on immersive-gallery.js.
- `php tests/art-piece-generation.php` — 121 passed.
- `php tests/three-runtime-consistency.php` — 86 passed, only the 2
  pre-existing gyro assertion failures (confirmed identical at git HEAD).
- Browser (Chrome, local php -S): piece 108 (SVG) mounts and its overlay
  shows title + × only; piece 50 (Three) has no view button; piece 49
  (non-interactive C2) and piece 88 (interactive C2 — iframe pointer-events
  auto, tabindex 0) open the shared overlay; collection wall toolbar at top,
  slideshow + arrows + Escape work; download menu open/outside-close/Escape/
  focus-return verified via scripted events; `Download Piece` appends
  `viewState` (and `surface=immersive` on pieces); immersive image view
  full-size + fullscreen at top; exported ZIPs (interactive C2, Three,
  collection) mount with the same toolbar, engine-gated buttons, PNG-only
  menus — including with `runtime/immersive-gallery.js` + `runtime/three`
  deleted to force the embedded Blob-module fallback.

### Known pre-existing issue (not introduced here; user-facing chip filed)
Collection export slideshow slides for Three.js pieces throw
"OrbitControls is not a constructor" inside the slide iframe
(`piece_export_document()` payload path, untouched by this session).

## 2026-07-05 — Immersive And Collection Piece Downloads Preserve Render Surface

### Decision
`/pieces/{id}/download` remains the single-piece export endpoint, but it now
accepts `surface=immersive` and an optional base64url `viewState` payload from
immersive piece surfaces. Regular `/pieces/{id}` exports continue to produce
the regular standalone piece view. Immersive-origin piece exports produce a ZIP
whose `index.html` opens directly into the local immersive renderer, restores
sanitized camera/target state where available, and includes icon controls for
fullscreen and PNG capture.

Platform collections use `/collections/{slug}/download` for downloads from the
collection wall and slideshow overlay. That export is the full collection
gallery implementation with all supported pieces and images, not a selected or
active-piece export. The collection `viewState` may include camera, target, and
active selection state.

### Root Cause
The earlier implementation reused the regular standalone export path from
immersive gallery controls, so downloads did not preserve the view the user was
actually looking at. The first collection implementation then exported only the
selected piece, which missed the point of downloading a platform collection
gallery. A follow-up failure showed that simply packaging local runtime files
was not enough: local direct-open behavior can still fail when a browser blocks
sibling ES-module imports from `file://`, producing a blank stage with only
static export controls visible.

### Scope
- `public/app/controllers/PiecesController.php` forwards `surface` and
  `viewState` into the export helper.
- `public/app/controllers/CollectionsController.php` streams full collection
  gallery ZIP exports from `/collections/{slug}/download`.
- `public/app/helpers/piece-render.php` builds immersive standalone documents,
  full collection gallery documents, patched local runtime imports, and a
  direct-open Blob-module fallback for the immersive runtime graph while
  keeping regular bundle exports unchanged.
- `public/app/views/immersive/piece.php` and
  `public/app/views/immersive/collection.php` expose download controls from
  immersive action rails and slideshow overlays without overlapping existing
  fullscreen/movement/zoom controls.
- `public/assets/js/immersive-gallery.js` serializes/restores viewer state,
  tracks active collection items for slideshow state, and captures PNGs from
  the rendered immersive gallery canvas unless an interactive overlay is open.
- Project markdown documents the route parameters, collection semantics,
  direct-open fallback, and full-gallery collection download rule.

### Verification
- `php tests/art-piece-generation.php` — 120 passed
- `php -l` on changed PHP files
- `node --check public/assets/js/immersive-gallery.js`
- `git diff --check`
- Browser verification of a regenerated piece 110 immersive ZIP rendered when
  served normally and when `runtime/immersive-gallery.js` was deliberately
  unavailable, forcing the embedded fallback path
- `php tests/three-runtime-consistency.php` still has 2 unrelated pre-existing
  gyroscope assertions failing, while the new immersive download/export checks
  pass

## 2026-07-05 — A-Frame PNG Capture Uses A Pre-Runtime WebGL Context Patch

### Decision
A-Frame PNG capture for both the public `/pieces/{id}` `Download PNG` action
and the exported standalone `index.html` screenshot control now relies on a
document-local WebGL context patch that forces `preserveDrawingBuffer` before
A-Frame boots. Capture then forces one last render and validates that the
copied canvas contains visible pixels before saving.

### Root Cause
The earlier hardening path assumed A-Frame would honor
`renderer="preserveDrawingBuffer: true"` on the scene. In practice A-Frame
1.6.0 rejected that property, so captures could read from a blank WebGL buffer
even when the scene was visibly rendering. That produced empty PNG downloads
for media-backed A-Frame pieces on both the live site and the direct-open
export path.

### Scope
- `public/app/helpers/piece-render.php` now injects the pre-runtime A-Frame
  capture shim into both public piece render documents and exported bundle
  `index.html`.
- `public/assets/js/public-piece-download.js` and the standalone export
  overlay script now force a fresh A-Frame render immediately before capture
  and retry once if the sampled pixel grid is still blank.
- `public/assets/js/piece-runtime.js` also hardens managed-media observation so
  console/runtime noise does not interfere with capture-state debugging.
- Project markdown now documents the WebGL-context-based A-Frame capture
  contract instead of the rejected renderer-attribute assumption.

### Verification
- `php tests/art-piece-generation.php`
- `git diff --check`
- Manual browser verification on `/pieces/109` produced a nonblank PNG after
  clicking `Download PNG`

## 2026-07-04 — Portable Piece ZIP Export With Single-Entry `index.html`

### Decision
Public piece downloads at `/pieces/{id}/download` now return ZIP bundles
instead of raw HTML files, and `Download HTML` is renamed to `Download Piece`.
The durable contract is that `index.html` is the only manual entry point a
recipient should need to open. Supporting files may still exist in the bundle
for editing and rehosting, but `index.html` must load the exported piece and
its screenshot affordance without requiring the recipient to manually open a
helper file first.

### Scope
- `public/app/controllers/PiecesController.php` now streams a ZIP export from
  the existing route.
- `public/app/helpers/piece-render.php` now assembles bundle exports with
  `index.html`, editable source files, vendored runtimes, packaged media, and
  direct-open-safe runtime/media embedding for the `index.html` path.
- `public/app/views/pieces/show.php` exposes `Download Piece` on public piece
  pages while keeping the public `Download PNG` action in place.
- Project markdown now documents the ZIP bundle, the single-entry-point
  contract, and the owner-maintained vendored runtime set.

### Root Cause
The earlier HTML-only export and then the first ZIP iteration both optimized
for portability before accounting for browser `file://` restrictions. That left
the direct-open path unable to guarantee screenshot/export behavior for some
interactive pieces, even though the piece itself was packaged locally. The fix
was to separate the editable/rehostable bundle shape from the runtime path used
when the recipient opens `index.html` directly: the bundle can still include
ordinary files, but the primary entry document must embed the specific runtime
and supported CMS-owned media forms needed for direct local execution.

### Verification
- `php -l public/app/helpers/piece-render.php`
- `php -l public/app/controllers/PiecesController.php`
- `php -l public/app/views/pieces/show.php`
- `git diff --check`
- Bundle smoke tests confirmed:
  - ZIP output contains `index.html`, editable source files, and vendored
    runtime files
  - generated `index.html` no longer references preview-helper files
  - supported CMS media refs are embedded as data URLs for the direct-open path
  - standalone Three.js exports bootstrap from vendored local sources without
    CDN imports

## Project Profile

<!-- Operational details for this project. Kept here, not in AGENTS.md,
     to keep the root instruction file framework-agnostic and safe to
     publish. Do not put credentials, hostnames, file paths, or API
     keys here — those belong in .env.

     An agent fills this section during Phase 1 by asking the person
     plain-language questions. If this section is empty, ask before
     writing any code. See AGENTS.md → Detect the Framework. -->

- **Stack:** No-framework PHP CMS with shared route handling in `public/index.php`; MVC layer under `public/app/`.
- **Deployment:** PHP ≥ 8.1 on shared/managed hosting (production runs on Hostinger); PHP built-in server for local preview. The codebase is copied as-is per deployment — only the MySQL database and `.env` differ.
- **Database:** MySQL 8+ (required). Schema is applied by `scripts/setup-database.php`; `schema.sql` bootstraps the core tables and dated migrations follow.
- **Version pins:** PHP ≥ 8.1, MySQL 8+. Dependency versions tracked in `composer.lock` and `docs/dependencies.md`.
- **Framework AGENTS.md:** No framework-specific AGENTS.md exists — sessions follow root AGENTS.md only.
- **Profile switch rule:** Stop before touching existing files. Record
  current state and reason here. Confirm new profile explicitly. Flag
  every file needing migration before starting.

---

## 2026-07-03 — Art Piece Generation Mode Compatibility, Legacy C2 Backfill, And Error Classification

### Decision
The `generation_mode` rollout remains the long-term contract, but shared
art-piece/version reads and writes must stay schema-compatible while older or
partially aligned databases catch up. `PlatformArtPiece` and
`PlatformArtPieceVersion` now treat `generation_mode` as optional at the SQL
layer, preferring it whenever the column exists and falling back to legacy
engine-based behavior otherwise.

Legacy interactive C2 versions are upgraded systematically rather than left on
heuristic-only runtime detection. The setup path now backfills every saved
`art_piece_versions` row where `engine = 'c2'`, `generation_mode` is null/empty
or plain `c2`, and the saved code matches the existing
`art_piece_c2_interactive_pattern()` detector. Matching rows are promoted to
explicit `generation_mode = 'c2_interactive'` across full version history, not
just current versions.

The public fatal screen in `public/index.php` was also narrowed: only genuine
connection-class PDO failures now render the “site isn’t configured yet /
database connection failed” page. Schema/query failures fall through to the
normal server-error path so piece-route regressions are no longer mislabeled as
total DB outages.

### Root Cause
The piece-only outage surfaced a deployment-alignment mismatch in the shared
art-piece hydration path. Routes such as `/admin/pieces`, `/pieces`,
`/portfolio/pieces`, and `/collections/{slug}` (when collections hydrate art
pieces) all traverse `PlatformArtPiece::attachCurrentVersion()` and/or
`PlatformArtPieceVersion`. Those readers had begun selecting `v.generation_mode`
unconditionally, so any environment where the column was missing or not yet
aligned could take down piece-facing routes specifically while the rest of the
site kept working.

### Scope
- `public/app/helpers/art-piece-generation.php` now owns the shared
  `generation_mode`-aware SELECT/INSERT/UPDATE column lists and the SQL record
  used to backfill legacy interactive C2 versions.
- `scripts/setup-database.php` gained the idempotent
  `art piece version c2 interactive backfill (2026-07-03)` step, and
  `docs/migrations/2026-07-03-art-piece-c2-interactive-backfill.sql` records
  the same data upgrade.
- `DECISIONS.md` / `MEMORY.md` carry the lasting runtime contract and the
  regression lesson: shared art-piece/version hydration must remain
  schema-compatible during staged rollouts.

### Verification
- `php -l public/index.php`
- `php -l public/app/helpers/database-errors.php`
- `php -l public/app/helpers/art-piece-generation.php`
- `php -l public/app/models/PlatformArtPiece.php`
- `php -l public/app/models/PlatformArtPieceVersion.php`
- `php tests/art-piece-generation.php` — 106 passed
- `php tests/three-runtime-consistency.php` — new generation-mode compatibility
  assertion passes; 2 pre-existing gyro-related failures remain
  (`DeviceOrientationControls` test parser failure and missing
  `requestGyroCalibration()` expectation)

## 2026-07-04 — Parallel Prompt Support For Image/Photo IDs And Media Asset IDs

### Decision
AI art-piece prompting now treats `image/photo/picture ID` and `media asset ID`
as parallel first-class prompt language across generation, regeneration, and
refine validation. The durable rule is explicit-route authorization, not hidden
identity inference: image-style wording authorizes `/image/{id}`, media-asset
wording authorizes `/api/media-assets/{id}`, and prompts that name both forms
authorize both path families.

### Scope
- `public/app/helpers/art-piece-generation.php` keeps the shared media-policy
  contract and now documents both route families directly in every engine's
  system prompt where CMS media examples are shown.
- `tests/art-piece-generation.php` locks in prompt parsing for `image ID`,
  `photo ID`, `picture ID`, and `media asset ID`, plus the rule that one path
  family does not automatically authorize the other unless both were named.
- Project markdown now mirrors the same rule in `README.md`,
  `docs/api.md`, and `docs/forms-and-templates.md`.

### Non-Decision
This is not a new aliasing layer between `media_files` and `media_assets`.
Even if one visual asset may be reachable through both record families, the
prompt must still name the exact family it wants to authorize. Any future
cross-family identity mapping would be a separate design decision.

### Verification
- `php tests/art-piece-generation.php` — 115 passed
- `git diff --check`

## 2026-07-03 — Immersive Gallery Runtime Contract Parity For C2.js

### Decision
The direct `/immersive/pieces/{id}` gallery-frame runtime and progressive
`/immersive/collections/{slug}` wall runtime now honor the same C2.js runtime
contract as `piece-runtime.js` and the fullscreen/slideshow srcdoc path. Valid
C2 code generated for the documented CMS contract may use `runtime.c2`,
`canvas`, `startFrame`, `runtime.loadImage()`, `runtime.drawImage()`, and
`runtime.drawImageCover()` on every C2 render surface.

### Root Cause
Piece 95 exposed two runtime-surface mismatches rather than a generation defect:
`immersive-gallery.js` could run C2 code before the `window.c2` CDN global was
available, then after that fix it still passed a smaller runtime object than
`piece-runtime.js` did. The fullscreen "Click to interact" view rendered
correctly because it uses the canonical `piece_render_document()` /
`piece-runtime.js` path, which already loads C2 and supplies the safe CMS media
helpers.

### Scope
The fix stays runtime-local: `immersive-gallery.js` now has cached async loaders
for p5 and C2, and C2-only media helpers for same-origin CMS paths
(`/image/{id}`, `/media/...`, `/api/media-assets/{id}`) in both direct piece
mounting and collection wall slots. Prompts, validation, URLs, schema, public
API endpoints, and vendor dependencies were not changed. Other engines were
reviewed for this class of mismatch: p5 uses native `p.loadImage`, Three.js
uses `THREE.TextureLoader`, A-Frame uses `<a-assets>`, and SVG uses
`<image>`/DOM APIs, so this helper parity issue is C2-specific even though the
larger watch item is runtime contract drift across surfaces.

### Verification
- `node --check public/assets/js/immersive-gallery.js`
- `git diff --check`
- `php tests/art-piece-generation.php` — 91 passed
- `php tests/feature-flags.php` — 20 passed
- Local route smoke: `GET /immersive/pieces/95` returned `200 OK` and emitted
  the updated cache-busted `immersive-gallery.js` import.

---

## 2026-07-03 — Legacy Platform Tooling Removed After Deletion

### Decision
After the platform deletion readiness gate passed and the untracked
`platform/` app folder was manually removed, the repository was slimmed by
removing legacy platform migration/checker scripts and plan-only markdown that
no longer participates in runtime behavior or duplicated-site setup.

### Scope
Kept the portable setup path intact: `scripts/setup-database.php`,
`scripts/check-portable-launch-readiness.php`, `schema.sql`, `migrations/`,
and `docs/migrations/` remain. Removed only the old platform-deletion gate,
one-way platform import/repair scripts, obsolete platform planning docs, and
the old AI media schema helper now superseded by the setup manifest.

## 2026-07-03 — Site-Wide Ranked Search Harvest Before Platform Deletion

### Decision
The retired `platform/` app's better search behavior was harvested into the
PHP CMS before `platform/` deletion: site-wide search now has a real
`sort=relevance` path using MySQL boolean FULLTEXT ranking, prefix clauses,
short-token LIKE recall, and HTML-safe highlighted snippets. Scope is all
searchable content types: posts plus art pieces, platform collections, exhibit
collections, exhibits, and pages.

### Schema (Rule 3 sign-off)
The owner approved adding FULLTEXT indexes on `art_pieces`,
`platform_collections`, `collections`, `exhibits`, and `pages`. Per the
schema dual-ship convention, the record is
`docs/migrations/2026-07-03-search-fulltext-indexes.sql` and the mechanism is
the probe-guarded `search fulltext indexes (2026-07-03)` manifest step in
`scripts/setup-database.php`. `posts` already had
`posts_content_text_fulltext`, so no posts index was added. Dry-run against the
configured DB reported the search indexes already applied and the schema fully
up to date.

### Related Decisions
- Stored/feed HTML sanitization was deliberately declined. External HTML must
  be able to run; admin-only authoring/approval is the accepted boundary. Risk
  recorded in `CONSTRAINTS.md`.
- The Medium syndication adapter is officially dropped because the Medium
  write API is moribund; this does not block `platform/` deletion.
- `/search` URL contract is unchanged: `q`, `type`, and
  `sort=newest|relevance`; `docs/api.md` did not need a contract update.

### Verification
- `php tests/search.php`
- `php tests/feature-flags.php`
- `php -l` on touched PHP files
- `git diff --check`
- `php scripts/setup-database.php --dry-run`

---

## 2026-07-02 — Feature Modularity: Content-Safe Toggles For Portfolio Types, Blog, And AI

### Decision
Site modules are now toggleable from a new `/admin/features` panel (subtabs:
Art Pieces, Exhibits, Blog, AI) with **content-safe** semantics chosen
explicitly by the human: toggling a feature OFF blocks creating new content
and hides empty sections from navigation, while existing published content
keeps its public URLs, stays in public nav/listings and feeds, and remains
editable/deletable in admin ("manage existing only" badge on gated admin nav
entries with content). All flags default ON and fail open when settings are
missing, so fresh installs work before database setup. Pages have no toggle.

Dependencies are enforced at read time and in the panel UI: exhibit
collections require exhibits; platform collections require art pieces. AI has
a master switch plus per-capability flags — `ai_pieces_code` (generate +
refine, also requires pieces), `ai_theme` (Site Identity AI Assist, with a new
site-wide default theme-generation profile setting), `ai_alt_text`, and
per-area editor text flags (`ai_text_pages|blog|pieces|exhibits|
platform_collections|media`). The shared `/admin/ai/process` endpoint now
requires a validated `context` field; the shared TipTap bundle reads flags and
context from body data attributes set by the admin layout.

### Storage (gallery: Embedded / Columns / Ledger, Reframe: install-time site profiles)
Embedded was selected: flags live as one `features_json` map inside the
existing `site_settings.settings_json` JSON column via a new
`SiteSettings::updateJsonSetting()`, matching the `admin_nav_order_json`
idiom. No schema change (Rule 3). Saves are audit-logged
(`admin_settings` / `feature_flags_save`). The Reframe — choosing a site's
module set at install time in `setup-database.php` — remains open.

### Implementation Notes
- `public/app/helpers/features.php`: registry, effective-value logic,
  content checks, blocked-route responses, `feature_flags_override()` test seam.
- Router dispatch honors an optional trailing feature key on route tuples;
  only creation/AI routes carry keys. **No public route is gated** (Rule 5).
- `POST /api/cron/refresh-feeds` skips ingest (200 + skipped) while blog is
  off; scheduled publishing and syndication of existing posts stay active
  (human chose to keep syndication available).
- New CLI suite `tests/feature-flags.php` (13 tests). Pre-existing
  `tests/three-runtime-consistency.php` failures (2) are unrelated.
- `docs/api.md` gained a Feature Flags section; public contract unchanged.

---

## 2026-07-02 — Art Piece Templates, CMS Media, And Portable HTML Exports

### Decision
Art piece starter templates are database-owned installation data, edited under
`/admin/pieces?tab=templates` rather than as an action button. The default
templates are meant to be usable immediately after setup and educational by
default: each engine can demonstrate optional CMS-owned media, using `/image/2`
as an explicitly resizable foreground example and `/image/3` as a full-frame
background example. Image source declarations do not control rendered size;
each engine sizes media where drawing/rendering happens.

Generated and hand-authored piece code remains CMS-runtime compatible through
the `window.sketch` contract. Existing media is allowed only through safe
same-origin CMS paths (`/image/{id}`, `/media/...`, `/api/media-assets/{id}`);
remote URLs, scripts, iframes, arbitrary fetch/storage/navigation, and raw C2
canvas context access remain blocked.

Public piece pages now expose `GET /pieces/{id}/download`, returning a
ZIP bundle for the current or selected version. `index.html` is the single
manual entry point, while supporting source/runtime/media files remain in the
bundle for editing and rehosting. Exports intentionally omit
immersive/admin/embed controls. Three.js exports mirror the CMS viewer's
interaction layer by instrumenting scene/camera/renderer creation and
attaching OrbitControls; A-Frame and C2 interactive exports pass the live
scene/canvas through so authored events remain interactive; supported CMS
media used by the direct-open path are embedded in a file-open-safe way so
interactive exports can still take screenshots locally.

### Verification
- `php -l public/app/helpers/piece-render.php`
- `php tests/art-piece-generation.php` — 91 passed
- `node --check public/assets/js/piece-runtime.js`
- `git diff --check`
- Live route check: `/pieces/83/download` returned `200 OK`, correct attachment
  filename, Three.js CDN import map, and the OrbitControls export bootstrap.

### Known Limit
Downloaded files depend on CDN libraries and live CMS media URLs. ZIP/offline
bundling is intentionally deferred.

## 2026-07-02 — pages.meta_description / og_description Widened to TEXT

### Decision
Both columns were `VARCHAR(320)`; MySQL (non-strict mode on Hostinger) silently truncated longer admin input mid-word on save. Widened both to `TEXT NULL` so the stored value is always exactly what the admin entered (search engines/social scrapers apply their own display truncation). `posts` has no equivalent columns — pages was the only affected table.

### Migration (per convention)
- `docs/migrations/2026-07-02-page-meta-descriptions-text.sql` — record
- New probe-guarded manifest step (probes `INFORMATION_SCHEMA DATA_TYPE = 'varchar'` via new `columnDataType()` helper) in `scripts/setup-database.php`; applied to live (step 21/22 ✓).

### Data repair
Bio's meta_description and og_description had been truncated to exactly 320 chars; both repaired to the full 521-char description text they were pasted from. Verified the rendered `<meta name="description">` now carries the complete text.

---

## 2026-07-02 — Per-Page Description Section Toggle

### Decision
Every page now has a `description` (TEXT) field and a `show_description_section` toggle (TINYINT, default 0/off), both edited in the page form's Metadata section. When the toggle is on and the description is non-empty, the public page renders a mission-band first section: the page title as H1 plus the description text. This generalizes what was previously an about-page-only special case.

The site-wide `site_settings.about_body` intro mechanism is retired: the view's about-system-page branch was replaced by the generic toggle block, and the "Page Intro" field was removed from Site Identity (the `about_body`/`about_heading` DB columns remain, unused; the legacy platform migration script still writes them). The `about` system-page defaults set `show_description_section = 1` so fresh installs keep the intro-capable about page behavior.

### Migration (per the frozen-schema.sql convention)
- `docs/migrations/2026-07-02-page-description-section.sql` — record
- New probe-guarded manifest step in `scripts/setup-database.php`, with a one-time backfill (runs only when the column is first added): copies `site_settings.about_body` onto the about-type system page's `description` and sets its toggle on.

### Files modified
- `public/app/models/Page.php` — guarded `description`/`show_description_section` in create/update (self-healing on pre-migration DBs via `hasDescriptionColumns()`); about system-page default toggle
- `public/app/controllers/Admin/PagesController.php` — resolveData fields
- `public/app/views/admin/pages/form.php` — Description textarea + toggle in Metadata
- `public/app/views/managed_page.php` — generic description-section block replaces the about branch
- `public/app/views/admin/site-identity/index.php` + `SiteIdentityAdminController.php` — Page Intro field retired

### Verification (all passed)
- Installer against live: applied exactly the two new columns + backfill; Bio has toggle=1 with its 547-char intro, all other pages toggle=0/NULL.
- `/bio` renders identical markup (mission-band, H1 "Bio", intro text); homepage contains no description section.
- Fresh scratch-DB install with the new 21-step manifest: clean run, columns present. `tests/system-page-identity.php` passes.

---

## 2026-07-02 — Portable-Codebase Installer + Bio Heading + Baseline Security Headers

### Decision: one-command idempotent DB installer
Added `scripts/setup-database.php` — a pure probe-based installer (no tracking table) that brings any database, empty or existing, to the full current schema in one command. Every table/column/index change is guarded by an `INFORMATION_SCHEMA` probe, matching the proven `apply-*-schema.php` house pattern. Flags: `--dry-run` (report only, zero writes) and `--with-example-content` (demo pages + Celestial theme, each seed probe-guarded so it can never overwrite a customized site). This is the portability core: copy the codebase, fill out `.env`, run one script — and re-run it after any code pull to keep every deployment aligned.

Findings that forced this design: `schema.sql` had been rolled forward and overlaps two later migrations (the README's manual sequence would fail on a fresh DB); the README sequence omitted two required migrations (2026-06-21 draft attempts — its column is queried by `PlatformArtPieceVersion` — and 2026-07-02 system page identity); nothing ever created the `site_settings` id=1 row on a fresh DB (the installer now does).

### Convention: schema.sql frozen
`schema.sql` is frozen as the twelve-core-table bootstrap. Every future schema change = new dated `docs/migrations/*.sql` (record) + one probe-guarded manifest step in the installer (mechanism). Documented in README "Adding a schema change".

### Supporting fixes
- Env loaders in `scripts/seed-celestial-theme-code.php`, `scripts/seed-theme-code-table.php`, and `public/index.php` now let process environment win over `.env` and normalize real process env into `$_ENV` (CLI `variables_order=GPCS` excludes E, so `db()`'s `$_ENV` reads previously ignored genuine environment variables). Enables scratch-DB targeting and host-panel env config.
- Baseline security headers in `public/index.php`: `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, and `X-Frame-Options: SAMEORIGIN` (skipped for `/embed/*`, which is designed to be iframed cross-origin). CSP/HSTS deferred.

### Bio heading
The about-type system page's intro H1 now renders the page's own title (e.g. "Bio") instead of `site_settings.about_heading`. The About Heading admin field was removed; About Body was relabeled "Page Intro (Bio/About page)". The `about_heading` DB column remains (unused, harmless).

### Verification (all passed)
- `--dry-run` vs live DB: read-only; truthfully surfaced that live was missing the 2026-06-17 columns (`site_settings.canonical_public_url`, `admin_nav_order_json`). **Resolved same day:** user approved; installer run against live applied exactly those two columns (step 4), all other steps "already applied"; follow-up dry-run reports schema fully up to date; site healthy.
- Fresh install on local MySQL 9.6 scratch DB: 20/20 steps ✓; second run: all "already applied"; app boots against scratch DB (serves designed first-run setup page, `/admin/login` 200).
- `--with-example-content` on recreated scratch: home/services/notes pages + Celestial theme seeded; second run skips all. Live DB confirmed untouched throughout. Scratch DB + test user dropped.
- Headers present on `/` and `/bio`; `X-Frame-Options` correctly absent on `/embed/*`. `tests/system-page-identity.php` passes.

---

## 2026-07-02 — Bio Page Claims About Identity; About Page Removed

### Decision
The Bio page (id 7, slug `bio`) now carries `system_key='about'`, giving it the About system-page capabilities — including the intro section (`site_settings.about_heading` + `about_body`) rendered as the first section of the page. `/about` 301-redirects permanently to `/bio` via `page_slug_redirects` (Rule 5 satisfied). The leftover quarantined draft "About" page (id 8, `system_key` NULL) was soft-deleted to Trash.

### Deletion method
`Page::softDelete(8)` was blocked by the system-page guard: `isSystemPage()` has a slug-based fallback (`Page.php:55`) that protects any page with slug `home`/`about` even when `system_key` is NULL (backward compatibility for pre-migration databases). Per user decision, the page was soft-deleted via direct SQL (`UPDATE pages SET deleted_at = NOW() WHERE id = 8 AND system_key IS NULL`) rather than modifying the guard. **Known quirk:** future quarantined duplicates with protected slugs will also require direct SQL to delete, unless the guard is refined later.

### Verification
- `tests/system-page-identity.php` passes
- `/about` → 301 → `/bio`; `/bio` renders the About intro band plus its own sections
- `Page::all()` no longer lists About; it appears in `Page::trashed()`

### Headless CMS Readiness Audit (for fornesusart)
**Verdict: partially ready.** JSON endpoints exist for posts, categories, single page by slug (`/api/p/[slug]`), art pieces + versions, platform collections, media (`/image/{id}`, `/media/{id}` with ETag/range/immutable caching), and Atom/JSON Feed/mf2 feeds.

Gaps before fornesusart can consume this as a headless CMS:
1. **Missing JSON endpoints**: portfolio exhibits, exhibit collections, art-media taxonomy, navigation menu, site settings/identity, page listing (only known-slug lookup exists), user profiles.
2. **No CORS headers** anywhere — browser `fetch()` from another origin will fail; server-side consumers unaffected.
3. **No machine auth** — public API is anonymous-only; admin is OAuth-session; cron is `X-Cron-Secret`. Non-public content or write access would need an API-token scheme.
4. **Single-DB per deployment** — but fully `.env`-driven with no hardcoded content, so a second deployment of this codebase pointed at the fornesusart DB works by config alone. One codebase serving two DBs simultaneously would be new work.

No fornesusart actions taken; this is the roadmap for a future session.

---

## 2026-07-01 — Celestial Theme z-index Fix (Public Site Stars/Nebulas Invisible)

### Root Cause
`styles.css:266` sets `html { background: var(--paper) }`. In WebKit/Safari and Chrome, `position: fixed` elements with negative z-index render *behind* the HTML element background and are completely invisible. The Celestial CSS used `#celestial-background { z-index: -3 }` and `#cosmos-stars { z-index: -1 }`, hiding the star field and nebula washes on the public site.

### Fix
In `scripts/seed-celestial-theme-code.php` (`$customCss` heredoc):
- `#celestial-background { z-index: -3 }` → `z-index: 0`
- `#cosmos-stars { z-index: -1 }` → `z-index: 0`
- Added new rule: `[data-layout-theme="celestial"] .site-header, main, .site-footer { position: relative; z-index: 1 }` so page content stacks above the star field

Both seed scripts re-run to push the updated CSS to `site_settings` and `site_theme_code` in the live DB.

### Note
`#cosmos-canvas` (comets, created by `cosmos.js`) was unaffected — it already uses inline `z-index: 9999` and was visible throughout.

### Files Modified
- `scripts/seed-celestial-theme-code.php` — three CSS changes in `$customCss` heredoc

---

## 2026-07-01 — Admin Preview Bugs Fixed (Light/Dark Toggle + Stars/Nebulas)

### Bug 1 — Light/Dark toggle did nothing
`syncPreview()` wrapped raw HSL channel values in `hsl()` before setting them as CSS custom properties: `setProperty('--sp-paper', 'hsl(40 49% 94%)')`. The CSS then evaluates `hsl(var(--sp-paper))` → `hsl(hsl(40 49% 94%))` — invalid, producing the same transparent result for both modes. Fixed by removing the `hsl()` wrapper so `--sp-paper` holds raw channel values (`40 49% 94%`).

### Bug 2 — Stars/nebulas invisible in admin preview
`.sp-header` and `.sp-body` had solid backgrounds covering `#celestial-background` (z-index 0). The preview frame also had no background for star dots to render against. Fixed by updating `injectPreviewCss()` to give `#design-preview-frame` a background of `hsl(var(--sp-paper, ...))` and override `.sp-header`/`.sp-body` to `background: transparent !important`.

### Files Modified
- `public/app/views/admin/site-identity/index.php` — `syncPreview()` lines 1110 & 1116 (removed `hsl()` wrapper); `injectPreviewCss()` scoping CSS (frame background + transparent overrides)

---

## 2026-07-01 — Per-Theme Code Storage + Preview Fix

### Decision
Added `site_theme_code` table as a per-theme code library. `site_settings.custom_*` remains the live injection path (unchanged); `site_theme_code` stores each theme's code independently. Dual-write keeps them in sync when the admin saves via form or AI accept.

### New features
- **Theme switch**: changing the Layout Theme dropdown fetches that theme's code into the CSS/JS/HTML editor tabs via AJAX, and applies `data-layout-theme` + injects the CSS into the preview frame.
- **Preview fix**: `#design-preview-frame` now receives `data-layout-theme` attribute; a `<style id="preview-theme-css">` tag is dynamically populated so Pinyon Script, nebula CSS, and other layout-theme selectors render in the preview.
- **Reset to defaults**: restores the seeded `default_*` columns for a theme without writing to DB until the user saves.
- **Save as new theme**: creates a new row in `site_theme_code`, adds the option to the dropdown, and activates it.
- Custom (non-builtin) themes appear in the Layout Theme dropdown automatically.

### Files added
- `docs/migrations/2026-07-01-site-theme-code.sql`
- `scripts/seed-theme-code-table.php`
- `public/app/models/SiteThemeCode.php`

### Files modified
- `public/app/controllers/Admin/SiteIdentityAdminController.php` — 3 new endpoints, dual-write, merged themeOptions
- `public/app/views/admin/site-identity/index.php` — preview fix, theme-switch JS, Reset/Save-as-new buttons
- `public/app/router.php` — 3 new routes + model require

---

## 2026-07-01 — Site Theme Code Editor + AI Generation

### Decision
Moved Celestial theme CSS (229 lines), JS (cosmos.js), and HTML (#celestial-background div) from static files into `site_settings` DB columns (`custom_css`, `custom_js`, `custom_html_body`). These are injected at runtime via `header.php` and `footer.php`.

Added a tabbed CSS/JS/HTML/AI Assist editor in the admin Design section, mirroring the art piece code editor. Added four new endpoints: `theme-generate`, `theme-refine`, `theme-save`, `theme-revert`.

Added `site_theme_snapshots` table for version history with draft/accept/reject flow identical to art piece generation.

### Star Field Bug Fix
Root cause: `body::before` radial-gradient star field was covered by `#celestial-background` (a DOM child). Fix: moved radial-gradient background-image onto `#celestial-background` directly; removed `body::before` Celestial block. Applied via seed script.

### MySQL 9.x Constraint
Both new migrations applied via `php scripts/run-migration.php` (PHP PDO), not `mysql` CLI — MySQL 9.x removed `mysql_native_password` auth plugin used by Hostinger.

### Files Changed
- `public/assets/styles.css` — Celestial block removed (CSS now in DB)
- `public/app/views/partials/header.php` — generic `custom_css`/`custom_html_body` injection
- `public/app/views/partials/footer.php` — generic `custom_js` injection
- `public/app/views/admin/site-identity/index.php` — CSS/JS/HTML/AI tabs
- `public/app/controllers/Admin/SiteIdentityAdminController.php` — 4 new AI endpoints + helpers
- `public/app/helpers/site-theme-generation.php` — new helper
- `public/app/models/SiteThemeSnapshot.php` — new model
- `docs/migrations/2026-07-01-theme-code-columns.sql` — new
- `docs/migrations/2026-07-01-site-theme-snapshots.sql` — new
- `scripts/seed-celestial-theme-code.php` — one-time seed

---

## 2026-07-01 — Platform Collection Slideshow Overlay + Wall Animation (All Engines)

### Context

The prior session's "Follow-up regression" fix (Three.js/A-Frame → `full_view = null` + navigation fallback in `openSlideshowAt`) was an intermediate step that fixed the black-canvas symptom but left Three.js/A-Frame outside the slideshow entirely. This session completes the work by making all engine types renderable in the overlay AND animating on the wall.

### What changed

**`public/app/views/immersive/collection.php`**

All art piece engines now receive a `full_view` with `srcdoc` (output of `piece_render_document()`). The `$pieceInteractive` flag is `true` for Three.js, A-Frame, and C2 pieces whose generated code registers pointer/mouse/touch events; `false` for P5, SVG, and non-interactive C2 (read-only previews). The `immersive_href` continues to exist alongside `full_view` for all pieces.

**`public/assets/js/immersive-gallery.js` — `createReadOnlyFullViewOverlay`**

- `contentWrap.style.cssText` reverted to `flex:1;min-height:0;display:flex;align-items:center;justify-content:center;padding:1rem 1rem 0.75rem;` — removed `overflow:hidden` (BFC regression that broke `height:100%` in nested iframes) and corrected flex values (`flex:1 1 auto;min-height:11rem` → `flex:1;min-height:0`).
- Overlay now shows `getProgressiveExhibitLiveBudget(window.innerWidth)` pieces per page: 1 on mobile (<640px), 2 on tablet (<1180px), 3 on desktop (≥1180px). Multi-column layout uses CSS grid `repeat(N,1fr)` with per-item title/subtitle labels; single-column mode shows the title/subtitle/description in the top bar.
- `showPrevious`/`showNext` advance by the current column count. A `resize` listener (rAF-debounced) re-renders when viewport width crosses a breakpoint.

**`public/assets/js/immersive-gallery.js` — `updateProgressiveLoading` (wall animation)**

Added an early-exit branch for `item.engine === 'three' || item.engine === 'aframe'` before `createImmersiveHost`. Instead of calling `resolveSketchFactory(item.generated_code)` (which fails on ES module `import` syntax), the new path:

1. Creates an off-screen `<iframe srcdoc="...">` (400×300 px, same as `runtimeSize`, `sandbox="allow-scripts allow-same-origin"`) using `item.full_view.srcdoc`.
2. Loads `item.thumbnail_url` as a placeholder texture while the iframe boots.
3. On iframe `load`, starts a `requestAnimationFrame` loop (`syncFrame`) that polls `iframe.contentDocument.querySelector('canvas')` and, once found, calls `ctx.drawImage(iframeCanvas, 0, 0, ...)` onto a proxy canvas each frame.
4. Creates `THREE.CanvasTexture(proxyCanvas)` on the first successful draw and sets it as the slot's artMaterial texture.
5. `stop()` cancels the rAF, removes the iframe, and disposes the live texture.

This runtime entry is compatible with the existing teardown path (`runtime.stop()`, `runtime.texture?.dispose()`, `runtime.host?.remove()`).

**`public/app/helpers/piece-render.php`**

Added `window.PIECE_PRESERVE_DRAWING_BUFFER = true;` to the inline `<script>` block in `piece_render_document()`. This activates an already-existing flag in `piece-runtime.js` line 271 (`...(window.PIECE_PRESERVE_DRAWING_BUFFER ? { preserveDrawingBuffer: true } : {})` inside the patched `THREE.WebGLRenderer` constructor), making the WebGL canvas pixel-readable via `drawImage`. Without this flag, WebGL pixels are cleared after compositing and `drawImage` returns blank.

**A-Frame caveat:** A-Frame's internal WebGLRenderer is created by A-Frame's own bundled Three.js, not through `piece-runtime.js`'s instrumented `instrumentedThree`, so `PIECE_PRESERVE_DRAWING_BUFFER` does not propagate to A-Frame's renderer. A-Frame slots display the thumbnail placeholder and remain on it after load. The same iframe boot path is used (better than the previous silent `resolveSketchFactory` crash), with full animation support left as a future improvement.

### Verification

- `node --check public/assets/js/immersive-gallery.js` — passes.
- Live browser test on `/immersive/collections/apocalyptic`:
  - Three.js pieces animate on the wall (confirmed via pixel read: non-zero RGBA from `drawImage` on the off-screen iframe canvas).
  - All 8 pieces animate in the wall simultaneously (P5, SVG, C2, Three.js all live).
  - "View slideshow" button opens the overlay with 2-column layout (tablet-width preview): Google P5 Apocalyptic + Google 3JS Apocalyptic side by side, both rendering live animated iframes.
  - "Next" paginates to DeepSeek C2 Apocalyptic + 3JS Apocalyptic — both rendering.
  - Zero console errors throughout.

---

## 2026-07-01 — Fix: Metadata Card Blank and Description in Wrong Location for Non-Three.js Immersive Pieces

### Context
Live testing on the deployed site after the previous session's changes revealed three issues with `/immersive/pieces/:id` for gallery-frame engines (P5, SVG, C2, A-Frame):

1. **Metadata card was blank for all non-Three.js pieces** — a prior change gated the entire card (icon, title, description) on `$isThree`, leaving only the hidden runtime-error row for P5/SVG/C2/A-Frame. The title was completely missing.

2. **Description and title appearing in the full-view overlay instead of the card** — the `fullView` items array passed to `mountGalleryPiece` included `title`, `subtitle` (engine label), and `description`, which `createReadOnlyFullViewOverlay` renders in its topBar and footer inside the expanded slide view. The user wants these in the metadata card below the stage, not cluttering the expanded overlay.

3. **"Untitled" fallback in overlay** — without the title field, `createReadOnlyFullViewOverlay`'s `item.title || "Untitled"` would have rendered "Untitled" in the topBar. Required a JS fix alongside the PHP change.

The artMesh click handler fix (wiring P5/SVG art-frame clicks to `readOnlyOverlay.openAt(0)`) was already correctly applied and needed no further changes.

### Implemented

**`public/app/views/immersive/piece.php`** — two sub-changes:
- **Metadata card**: Restored `card-icon` and `card-title` for all engines. Non-Three.js engines now render `card-icon` + `card-title` + `card-desc` (from `$description` or `$prompt` if present) + runtime-error-item only — no AI profile/persona grid, no embed source (those are redundant: already on the piece page the user came from). Three.js keeps its full card unchanged.
- **`fullView` items**: Stripped `title`, `subtitle`, `description` from the items array passed to `mountGalleryPiece`. The overlay now carries only `type` and `srcdoc`, so `createReadOnlyFullViewOverlay` renders just the piece iframe with no text chrome.

**`public/assets/js/immersive-gallery.js`** — `createReadOnlyFullViewOverlay`, `renderCurrentItems` (cols === 1 branch):
- Changed `item.title || "Untitled"` to `item.title || ""` with `titleEl.style.display` toggled on content presence.
- Added `metaWrap.style.display` hide when both `titleEl` and `subtitleEl` are empty, cleanly collapsing the meta area in the topBar.
- Collection/exhibit-wall callers always pass real titles and are unaffected.

### Verification
Pending live confirmation on deployed site: P5/SVG/C2/A-Frame pieces should show title + description in the metadata card; the full-view overlay should show only the piece with no title/description text; Three.js pieces unchanged.

### Follow-up correction — Full transparency grid restored for ALL piece engines (2026-07-01)

After live testing, the "title + card-desc only" card for non-Three.js pieces was found insufficient. DECISIONS.md entry 2026-06-20 ("AI Profile/Persona Attribution Per Version") establishes that every generated piece must show the full transparency grid: Engine, Version, Interaction, AI Profile, AI Persona, Creative Prompt, About, Embed Source. Only `image.php` (images not generated on this site) is exempt.

**Fix:** Removed the `$isThree` gate that was restricting non-Three.js pieces to a stripped card. The card-grid now renders for **all** engine types. Engine-specific text is confined to two fields:
- `card-desc` paragraph: Three.js → 3D canvas description; A-Frame → WebXR/scene description; all others → gallery-room description.
- Interaction row: Three.js → orbit/fly instructions; A-Frame → look-around/walk; all others → orbit/walk-floor.

`image.php` is a separate file and was not touched.

---

## 2026-07-01 — Collection Fullscreen and Slideshow Description Fix

### Context

Two issues reported with `/immersive/collections/{slug}`:

1. **Slideshow overlay did not cover the full browser viewport.** `createReadOnlyFullViewOverlay` used `position:absolute;inset:0`, positioning the overlay relative to `.stage-wrapper` (its nearest positioned ancestor, which is only 55 vh tall). This meant the overlay was visually constrained to the stage area even when the user expected a fullscreen experience.

   On iOS Safari, `shell.requestFullscreen()` is always rejected (iOS Safari has never supported the Fullscreen API for non-`<video>` elements). The existing `.catch()` handler already calls `syncFullscreenState(true)` unconditionally, adding the `.fullscreen` class and applying `.stage-wrapper { position: fixed; inset: 0; width: 100dvw; height: 100dvh }` via CSS — so the **fullscreen button for the 3D gallery room** was already functional on iOS Safari via this CSS fallback. The overlay was the missing piece.

2. **Description text appearing in collection slideshow overlays.** `collection.php` was passing `'description' => $pieceFullViewDescription` (piece description or fallback to prompt) and `'description' => $altText` (media alt text) in the `full_view` arrays for pieces and images respectively. `createReadOnlyFullViewOverlay` renders `item.description` in a footer paragraph, causing the text to appear inside the overlay. Piece.php had already stripped its fullView items of description in a prior session; collection.php had not been updated consistently.

### Implemented

**`public/assets/js/immersive-gallery.js` — `createReadOnlyFullViewOverlay` (line 854)**

Changed `position:absolute` to `position:fixed` in the root overlay element's inline style. The overlay is appended to `stageEl.parentElement` (`.stage-wrapper`), which has `position:relative` but no `transform`, `filter`, or `perspective` — so `position:fixed` escapes `overflow:hidden` and positions relative to the viewport. z-index:145 remains correct: above the fullscreen stage-wrapper (z-index:120) and below the toast container (z-index:200). This change affects both collection slideshows and individual piece full-view overlays — both now cover the full browser viewport when opened.

**`public/app/views/immersive/collection.php`**

- Piece `$fullView` (lines 57–64): removed `'description' => $pieceFullViewDescription`.
- Media asset `full_view` (lines 95–102): removed `'description' => $altText`.

Title and subtitle are retained in both cases (user specified "no description text," not "no title/subtitle"). The `descriptionEl` in `createReadOnlyFullViewOverlay` was already hidden when `item.description` is absent or empty (prior session fix), so no JS change is needed.

### Verification
Pending live confirmation: collection slideshow opens covering the full browser viewport; no description text appears in any slide; individual piece full-view overlays also cover the full viewport.

### Follow-up fix — Collection fullscreen button not working on Safari iOS (2026-07-01)

Live testing confirmed the fullscreen button on platform collections did not work on Safari iOS, while the same button on individual piece pages did. Root cause: `collection.php`'s fullscreen JS was an earlier, underdeveloped version of the code that piece.php had already evolved past.

**Two concrete gaps:**

1. **Missing `lockImmersiveScroll()` / `unlockImmersiveScroll()`** — piece.php locks page scrolling with `document.body.style.position = 'fixed'; top: -${scrollY}px`. This is the only reliable technique to prevent iOS Safari's momentum scrolling from operating behind a `position:fixed` overlay. collection.php only set `overflow: hidden` on body/html, which iOS Safari ignores for touch-scroll purposes. Without the body lock, iOS would continue scrolling the page behind the fullscreen stage, causing the fixed overlay to appear to shift or fail to cover the visual viewport.

2. **No iOS Safari pre-check in `toggleFullscreen()`** — piece.php detects iPhone WebKit before calling `shell.requestFullscreen()` and immediately calls `syncFullscreenState(true, { mode: 'focus' })` then returns. collection.php called `requestFullscreen()` unconditionally (which always rejects on iOS), then handled the rejection in `.catch()`. While the catch path should theoretically work, skipping the doomed API call is the safer and faster path on iOS.

**Fix:** Replaced collection.php's fullscreen JS block with a fully up-to-date implementation matching piece.php:
- Added `let lockedScrollY = 0; let immersiveScrollLocked = false;`
- Added `lockImmersiveScroll()` and `unlockImmersiveScroll()` functions (body position-lock technique)
- `toggleFullscreen()`: pre-checks `isIPhoneWebKitBrowser()` first; skips `requestFullscreen()` on iPhone
- `syncFullscreenState(isFull, options = {})`: accepts options, sets `shell.dataset.immersiveMode`, calls `lockImmersiveScroll()`/`unlockImmersiveScroll()` instead of inline overflow toggles
- Fullscreen init: passes `{ mode: isIPhoneWebKitBrowser() ? 'focus' : 'fullscreen' }`

The `window.addEventListener('message', ...)` handler and `creatr-iframe-ready` postMessage were already present in collection.php and needed no change.

---

## 2026-07-01 — Celestial Theme Import from fornesusart

### Context

The long-term goal is to retire `fornesusart` and reassign its database and domain to `augment-humankind`. As a prerequisite, the fornesusart visual identity needed to be importable as a selectable theme in augment-humankind's admin Design panel. Three phases of work were completed this session.

### Phase 1 — Color Palette and Layout Theme Options

Added "Celestial" as a new option in both the Layout Theme and Color Palette dropdowns in the admin Design tab (`/admin/site-identity?tab=design`).

**Files changed:**
- `public/app/controllers/Admin/SiteIdentityAdminController.php` — added `'celestial' => 'Celestial — Cosmic dark, parchment & amber glow'` to `themeOptions()`.
- `public/app/views/admin/site-identity/index.php` — added `<option value="celestial">` to the palette `<select>` and a full 24-field `celestial:{...}` entry to the JS `PALETTES` object.

**Color values** (HSL `"H S% L%"` format):
- Light mode: warm parchment bg (`44 40% 93%`), deep charcoal ink (`267 25% 15%`), darkened amber primary (`33 60% 38%`), deep navy secondary (`231 45% 30%`). All meet WCAG AA.
- Dark mode: pure black bg (`0 0% 0%`), parchment text (`44 47% 83%`), amber primary (`38 53% 51%`) — matches fornesusart exactly.

### Phase 2 — Fonts, Animations, Preview Fix, Custom CSS Field

**Preview Light/Dark button bug fixed:** `PREVIEW_MAP_DARK` in `index.php` only covered 2 of 10 dark color fields. Expanded to all 10 so clicking ☾/☀ in the admin correctly flips Primary/Secondary/Accent button colors as well as background/foreground.

**Fonts imported from fornesusart (self-hosted woff2):**
- Copied 4 files to `public/assets/fonts/`: `pinyon-script-latin.woff2`, `lora-normal-latin.woff2`, `lora-italic-latin.woff2`, `courier-prime-latin.woff2`.
- Added `@font-face` declarations at top of `public/assets/styles.css`.
- Set `data-layout-theme="celestial"` on `<html>` before first paint via inline script in `header.php` (reads `$_ahS['theme']`).
- Added `[data-layout-theme="celestial"]` CSS rules: Pinyon Script for h1/h2/h3/`.brand`, Lora for body, Courier Prime for code/pre/kbd.

**Cosmic background animations:**
- Copied `cosmos.js` from fornesusart to `public/assets/js/cosmos.js`. No modifications needed — it already skips `admin-body` pages, respects `prefers-reduced-motion`, and uses no fornesusart-specific variables.
- `header.php` injects `#celestial-background` div (3 nebula-wash divs + astrolabe SVG) immediately after `<body>` when `$_ahS['theme'] === 'celestial'`.
- `footer.php` conditionally loads `cosmos.js` when celestial theme is active (using `$_ahFooterSettings['theme']`, already read there).
- `styles.css` (end of file): added all CSS for the celestial system — `body::before` star field (45+ `radial-gradient` layers), nebula-wash + drift keyframes, astrolabe rotation, `#cosmos-stars` rotation, `.cosmos-star` twinkling, low-power overrides, `prefers-reduced-motion` and `prefers-contrast` media queries.
- `body { background: transparent }` and `html { background: hsl(var(--paper)) }` when celestial theme is active — required for the `body::before` star field to be visible through the transparent body.

**Custom CSS admin field:**
- Added `custom_css` field: stored in `settings_json` fallback (no DB migration — `SiteSettings::current()` and `updateSettings()` already support this fallback path).
- Added `custom_css` to `$allFields` and `resolveSettingsData()` in `SiteIdentityAdminController.php`.
- Added monospace textarea in admin Design tab (12 rows, before Save button).
- Injected as raw `<style><?= $_ahS['custom_css'] ?></style>` in `header.php` (admin-only input, no escaping needed).

**Files changed:**
- `public/app/views/admin/site-identity/index.php` — PREVIEW_MAP_DARK fix, Custom CSS textarea
- `public/app/controllers/Admin/SiteIdentityAdminController.php` — `custom_css` field support
- `public/assets/styles.css` — @font-face declarations + celestial layout theme CSS block
- `public/app/views/partials/header.php` — `data-layout-theme` inline script, celestial background HTML, custom CSS injection
- `public/app/views/partials/footer.php` — conditional `cosmos.js` load
- `public/assets/fonts/` (new dir) — 4 woff2 font files
- `public/assets/js/cosmos.js` (new file) — copied from fornesusart unchanged

### Verification
Live preview confirmed on PHP dev server (port 8080):
- Dark mode: black void, parchment text (`44 47% 83%`), amber accents, star field, nebula blobs, rotating astrolabe, twinkling DOM stars, canvas shooting comets.
- Light mode: warm parchment background, dark charcoal text, amber accents, subtle nebula wash visible as a soft cosmic blush on the parchment.
- Fonts: Pinyon Script on all headings and site brand, Lora on body copy.
- Admin preview Light/Dark toggle: verified via code review (OAuth blocks browser login in preview).

---

## 2026-07-01 — Headless CMS Compliance Audit and Gap Closure

### Context

User identified that storing `custom_css` in `settings_json` violates the headless CMS goal that all data lives in explicit MySQL columns. A full audit was run to assess compliance.

### Audit Findings

**Compliant (no action needed):**
- JSON API: 20+ routes in `ApiController.php` serve posts, pages, art pieces, collections, media, and feeds as `application/json`
- `site_settings`: 47+ fields are proper columns; only `custom_css` was missing
- Public navigation: `ah_public_navigation_items()` reads from `navigation_items` table (DB-driven at runtime)
- Admin nav ordering: `admin_nav_order_json` is a dedicated column (added 2026-06-17)
- Hardcoded page fallbacks: placeholder content for home/services/notes/contact is overridden by managed pages; API always serves managed content, never the fallbacks
- System nav items: defined in `NavigationItem::SYSTEM_ITEMS` in PHP but seeded to DB; runtime is DB-driven
- `users.social_links` JSON blob: intentional; flexible key-value for arbitrary social platform URLs

**Gaps closed:**

#### Gap 1 — `custom_css` in `settings_json` blob
`SiteIdentityAdminController::$allFields` included `custom_css` but the `site_settings` table had no column for it, causing values to be stored in the `settings_json` JSON blob — invisible to SQL, DB tooling, and API consumers.

**Fix:** New migration `docs/migrations/2026-07-01-custom-css-column.sql` adds `custom_css MEDIUMTEXT NULL AFTER palette` and includes a one-time data migration to move any existing value from `settings_json`. No PHP changes needed — `SiteSettings::availableColumns()` queries `INFORMATION_SCHEMA.COLUMNS` at runtime; once the column exists, `updateSettings()` writes directly to it.

**Migration must be applied manually:** `mysql … < docs/migrations/2026-07-01-custom-css-column.sql`

#### Gap 2 — Footer navigation hardcoded in PHP
`footer.php` hardcoded 4 navigation links (Home, Portfolio, Blog, Contact) as static HTML. Admin had no way to change footer links without editing PHP.

**Fix:** Replaced with a loop over `$navigationItems` (already in scope from `header.php`'s call to `ah_public_navigation_items()`). Footer now reflects the same DB-driven, admin-orderable navigation items as the header. Live verification confirmed 8 DB items now render in the footer.

### Files Changed
- `docs/migrations/2026-07-01-custom-css-column.sql` (new)
- `public/app/views/partials/footer.php` — hardcoded nav replaced with `$navigationItems` loop
- `README.md` — new migration added to setup sequence

---

## 2026-07-01 — Session Summary: Celestial Theme and Headless CMS Closure

All work from this session is live on the local dev server (`php -S 127.0.0.1:8080 -t public`) connected to the remote Hostinger MySQL database.

### Accessible features

**Public site (no login):**
- Celestial theme active: black void, parchment text (`44 47% 83%`), amber accents (`38 53% 51%`), Pinyon Script headings, Lora body text, Courier Prime code
- Cosmic animations: nebula wash (3 drifting blobs), rotating astrolabe SVG, twinkling DOM stars (14–28 spans), canvas shooting comets (3/min)
- Light mode: warm parchment background (`44 40% 93%`), dark charcoal text, amber accents, subtle nebula blush
- Footer navigation: DB-driven via `navigation_items` table (was 4 hardcoded links; now reflects all visible admin-managed items)

**Admin panel (`/admin`, login required):**
- Design tab: Celestial in both Layout Theme and Color Palette dropdowns; palette auto-fills all 24 color fields; Light/Dark preview buttons work correctly; Custom CSS textarea persists to `site_settings.custom_css` column and injects site-wide
- All other admin sections unchanged

### Migration applied
`docs/migrations/2026-07-01-custom-css-column.sql` was applied to the live database. `custom_css MEDIUMTEXT NULL` is now a proper column in `site_settings`.

---

## 2026-07-02 — Portable-CMS Setup Readiness Remediation

### Context
Audit of readiness for the coupled-CMS goal: clone the codebase, point it at
an empty MySQL database + `.env` + OAuth apps, and get a working site with
proper placeholders until configured. Audit verdict: installer, readiness
checker, feature flags, setup gate, and inline placeholder pages were already
in place (commit 870ad66); the gaps were installer failsafes, a canonical
setup document, duplicated env-loading code, and one site-specific fallback
label.

### Implemented
- **Installer existing-data failsafe** (`scripts/setup-database.php`):
  read-only `preflightExistingData()` scan runs before any step. If the
  target DB has entries (admins, users, pages, posts, art pieces, exhibits,
  media, comments), a boxed warning + counts summary prints; interactive
  (TTY) runs must confirm, non-TTY runs and `--yes` proceed after the
  summary, keeping `git pull && php scripts/setup-database.php` unattended-
  safe. Chosen via AskUserQuestion: TTY-confirm + `--yes` over warn-only.
- **Seed-secret warning**: `encryptedSeedSecret()` now emits one STDERR
  warning when `RECAPTCHA_SECRET_KEY` is set but cannot be encrypted
  (missing/invalid `AI_SETTINGS_ENCRYPTION_KEY`) instead of silently seeding
  NULL form secrets.
- **Shared env loader** (`public/app/helpers/env.php`, new):
  `ah_load_env_file()` / `ah_env()` extracted from `public/index.php`;
  `public/index.php` (`loadEnvFile`/`configValue`), `scripts/setup-database.php`
  (`loadEnvFile`/`envValue`), and `scripts/check-portable-launch-readiness.php`
  (`load_env_file`/`env_value`) are now thin wrappers. Identical semantics
  (process env wins, quote stripping, silent missing files); no behavior change.
- **SETUP.md** (new, repo root): numbered, verifiable setup procedure for a
  human or agent — prerequisites, env table, DB creation, installer flags,
  readiness check, OAuth app creation, first admin login, post-login
  configuration, and duplication steps. README links to it and documents `--yes`.
- **Nav fallback label**: `Field Notes` → `Notes` in
  `ah_fallback_navigation_items()` (renders only when `navigation_items` is
  empty/unreachable; live site unaffected).

### Decisions (via AskUserQuestion)
- Empty-DB homepage: runtime placeholder (already implemented in
  `public/index.php` — starter home/services/notes/contact views render when
  no page row exists; only unpublished/trashed rows 404). No installer
  seeding of a home page.
- Verification scope: readiness only — no scratch database, no test clone.
  The codebase must be *ready* to duplicate; the duplication itself happens
  later.

### Verified (readiness-only, existing local env)
- `php tests/feature-flags.php` — 20 passed, 0 failed.
- `php -l` clean on all changed files.
- Dry-run installer: pre-flight summary prints (incl. 88 art pieces after
  fixing the probe from `platform_art_pieces` to the real `art_pieces`
  table); no prompt in dry-run; all 23 steps already applied.
- Piped (non-TTY) real run proceeds without prompting; `--yes` run proceeds;
  both no-op idempotently.
- `DB_NAME=nonexistent…` override reaches MySQL as that DB (process-env-wins
  intact through the shared loader).
- Secret warning fires with an invalid encryption key, silent with the real one.
- Readiness checker exits 0 (1 warning). Local web smoke test after the env
  refactor: `/`, `/contact`, `/blog` all 200.
- cosmos.js confirmed reachable only via DB `custom_js` (seeded by
  `--with-example-content`); fresh sites get no star animations. Do not
  re-audit this.

### Not done / open
- The "REVIEW REQUIRED Before Platform Deletion" block (2026-06-14) remains
  open — unrelated to this pass.

## 2026-07-02 — Agentic Markdown Reconfiguration + Design System Reframe

### Context
Following the portable-CMS readiness confirmation, the user requested DESIGN.md
development and reconfiguration of AGENTS.md/CLAUDE.md/GEMINI.md and related
files for maintainability, staying true to multi-tool use (Claude Code,
Antigravity, Codex, Opencode Go, Gemini CLI, and others).

### Decisions (via AskUserQuestion, all explicitly approved)
- **DESIGN.md scope**: "Both are themes, not identity" — DESIGN.md now
  describes the multi-site CMS design *system*; the confirmed Pareto Derived
  Identity is preserved verbatim as a theme instance, Celestial documented as
  a second instance, with a system-constants paragraph (accessibility floor,
  authored-content-only, structure-carries-credibility, no attention-economy
  patterns). Two Observed Taste entries added (2026-07-01 Celestial adoption;
  2026-07-02 themes-not-identity choice).
- **AGENTS.md** (diff shown and approved per the AGENTS.md Safeguard):
  tool-agnostic Mode table; "Six Rules" → "Seven Rules" fix in Session
  Constraints; plan-mode gallery-suppression note absorbed from
  CLAUDE.md/GEMINI.md; Project Specific Rules populated with the coupled-CMS
  conventions (schema dual-ship, no hardcoded site content, feature-flag
  registration; platform/ is instance-only legacy).
- **CLAUDE.md / GEMINI.md**: reduced to thin shims pointing at AGENTS.md,
  with a Claude Code mode-mapping line. Their duplicated plan-mode note now
  lives once in AGENTS.md.
- **EVAL_PROMPT.md**: header fixed to Seven Rules; new check item 8 for
  Rule 7; Mandatory Checks renumbered 9–14.
- **DECISIONS.md**: 130 pre-2026-07 sessions (≈380KB) archived to
  docs/decisions-archive.md; Project Profile, all 2026-07 sessions, and the
  open "REVIEW REQUIRED Before Platform Deletion" block carried forward
  (now under OPEN ITEMS at the top of this file).
- **MEMORY.md**: restructured (user-approved) from 259 chronological lines
  into topical sections (Stack & Deployment; Standing Decisions ×3; UI &
  Editor Patterns; Regression Watchlist; Closed Investigations). All dates
  preserved; superseded intermediate steps of closed investigations folded
  into their final entries with do-not-relitigate guards intact.

### Duplication-readiness confirmation (same session)
Confirmed to the user: the codebase is ready to copy as-is to a new
deployment (empty DB + .env + OAuth apps), and future changes propagate
safely provided the three conventions now codified in AGENTS.md → Project
Specific Rules are honored.

## 2026-07-02 — Remaining Agent-Specific Markdown Alignment

### Context
Follow-up to the agentic markdown reconfiguration: user asked for the same
treatment on Gemini, Replit, and any other agent-specific files not
explicitly mentioned. Survey found `.github/copilot-instructions.md` (stale,
inherited from the IndieWeb/Next.js predecessor project), `.gemini/settings.json`
(already correct), synced `.agents/skills/` + `.claude/skills/` dirs, and no
Replit config (platform/ is the retired app's reference export).

### Implemented
- `.github/copilot-instructions.md` rewritten as a thin shim: Seven Rules
  priority (was "Six"), removed nonexistent skills (indieweb-specs,
  indieweb-principles, posse-syndication, security) and Next.js/microformats
  guidance (Server Components, `use client`) left over from the predecessor
  project; added Copilot mode mapping onto AGENTS.md → Mode and the
  coupled-CMS reminder. Durable behaviors kept: feed-route protection,
  AGENTS.md edit guard, no auto-syndication.
- `replit.md` (new, root): thin shim declaring Replit is NOT a runtime
  target (production is Hostinger/PHP), `platform/` is reference-only with a
  read-only legacy DB, plus the correct run command and SETUP.md pointer if
  the repo is ever opened in Replit.
- No shims created for Codex, Opencode Go, or Antigravity — they read
  AGENTS.md natively; speculative per-tool files would add maintenance
  burden without benefit.
- `platform/`'s own legacy memory markdown was reference-only and is now gone
  with the removed legacy app folder.

## 2026-07-02 — Platform Folder Redundancy Audit (findings only, no changes)

### Context
User asked whether `platform/`'s best features are implemented or improved in
the PHP app. Explore agent inventoried ~90 platform capabilities (agent loop
logged per Agent Use rule); uncertain parity items verified directly against
PHP code.

### Verdict
`platform/` is functionally redundant — every user-facing feature is
implemented in PHP, most improved. The former cron blocker is stale:
scheduled-tasks.yml now hits PHP endpoints
(`/api/cron/refresh-feeds`, `/api/cron/publish-posts`).

### Gaps where the platform version was better (reference value before deletion)
1. Search depth: platform had FULLTEXT boolean MATCH/AGAINST with relevance
   ranking, prefix matching, short-token LIKE fallback, and highlighted
   HTML-safe snippets (lib/post-search.ts). PHP /search is LIKE-only,
   newest-first (its "relevance" option doesn't rank), no snippets — though
   PHP search is broader (6 content types vs posts-only) and platform's
   filter-rich search UI (date range/sources/author/recent searches) has no
   PHP equivalent.
2. Medium adapter: platform had 9 adapters incl. medium.ts; PHP has 8 (no
   Medium). Possibly intentional (Medium's write API is moribund) — confirm.
3. Stored-HTML sanitization: platform sanitized HTML to an allowlist
   (lib/html.ts). PHP strips tags for content_text but appears to store/render
   feed-imported HTML unsanitized — potential third-party-feed XSS; verify.
4. Typed API contract chain (OpenAPI→Zod→React client via Orval): no PHP
   equivalent; docs/api.md is hand-maintained. Architecturally N/A for
   no-framework PHP; the drift-prevention idea is the loss.
5. /api/healthz: absent in PHP (trivial).

### Improved beyond platform (highlights)
Polymorphic comments (4 content types vs posts-only); AI refine plan+patch
protocol with draft attempts/forks; 10 themes + per-theme DB code + AI theme
generation; feature flags; portable installer + SETUP.md; forms/newsletter;
unified media library; piece downloads/templates; cron via GitHub Actions
(no resident worker — right for shared hosting).

### Deletion readiness after this audit
Remaining: confirm two operational items (2026-06-18 AI Personas SQL
migration + thumbnail-migration re-run on production), decide whether to
port gaps 1–3 first, then owner sign-off per OPEN ITEMS.

## 2026-07-03 — DESIGN.md Theme Customization Documentation

### Context
Following the user-approved implementation plan, updated the creative identity document (`DESIGN.md`) to reflect the CMS codebase's dynamic theme-switching and color customization architecture.

### Decision
Added details to `DESIGN.md` under the `Declared Preferences` section for `Color direction` and `Layout disposition`:
- **Color direction:** Documented that light and dark mode colors are customizable via the admin panel (Site Identity → Design) using HSL variables mapped via CSS custom properties (`--sp-*`), enabling per-deployment palette overrides.
- **Layout disposition:** Documented the availability of 10 built-in theme presets (e.g. Bauhaus/Pareto, Celestial, traditional, academic, minimalist, and comfort) which can be customized or extended with inject-ready custom CSS, JS, and HTML body wrappers stored in the database.

## 2026-07-03 — C2.js Interactive pointer-coordinate fix (piece 103 "no interactivity")

### Context
User reported Mistral Vibe C2.js Interactive pieces (piece 103) showed no
interactivity, suspecting weak-model generation. Investigation (one Explore
agent loop over the generation pipeline, plus DB inspection of version 222
and browser measurement) showed generation was CORRECT: the stored code has
full pointerdown/move/up drag handlers and passed the c2_interactive
preflight. The defect was a runtime/prompt contract mismatch: the generation
prompt mandates `(clientX - rect.left) * (canvas.width / rect.width)` for
pointer mapping, but piece-runtime.js letterboxed the fixed 1280×720 bitmap
inside the element with object-fit:contain, skewing every hit-test by up to
±36 canvas px in non-16:9 containers — larger than piece 103's drag targets.

### Decision (user-approved plan)
Aspect-lock the c2 canvas ELEMENT box to the bitmap instead of letterboxing
inside it: new fitCanvasBox() in public/assets/js/piece-runtime.js sizes the
element to the contained rectangle (host flex-centered), preserving the
distortion fix that object-fit provided while making the prompt's formula
exact on every surface. Also added touch-action:none (runtime + export
bootstrap in piece-render.php) so touch drags aren't eaten by scrolling.
Existing stored pieces become interactive with no regeneration and no prompt
changes. Regression tests updated/added in tests/three-runtime-consistency.php.
Verified: element rect 896×504 vs bitmap 1280×720 (exact 16:9 match), no
piece-error, suites pass (110/0 generation; 79 pass consistency with only the
2 pre-existing gyro failures also present on HEAD).

## 2026-07-04 — C2 loadImage Promise contract (new-generation "then is not a function" crash)

### Context
A fresh Mistral Vibe C2 interactive generation crashed at boot with
"TypeError: runtime.loadImage(...).then is not a function": the runtime's
loadImage returned a bare HTMLImageElement, but models guess all three call
styles (.then(), await, plain sync pass-through). Only the sync and await
styles happened to work; .then crashed the sketch after passing preflight.

### Decision
loadImage in all three C2 runtimes (public/assets/js/piece-runtime.js,
public/assets/js/immersive-gallery.js, piece_export_document bootstrap in
public/app/helpers/piece-render.php) now returns a Promise that resolves to
the image on load, carries the element as __creatrImage, and the draw
helpers unwrap it via resolveImageRef() — making await, .then(), and sync
pass-through all valid. DB survey of every stored C2 version confirmed only
await/sync styles exist, so no stored piece regresses. Both C2 generation
prompts now document the Promise contract. Regression tests added to
tests/three-runtime-consistency.php; all three patterns verified end-to-end
in-browser against the live runtime and /image/82 (marker + image pixels
drawn, no piece-error).

## 2026-07-05 — C2 media guard vs capture-safe data: URLs; downloads in immersive view; regular-view fullscreen overlay

### Context
C2/C2-interactive pieces showed "C2 media helpers may only load same-origin
CMS media paths…" on /pieces/{id} and produced blank PNGs, while other
engines were fine. Stored code was correct (runtime.loadImage('/image/82')):
piece_render_iframe() renders with capture_safe_media, which rewrites CMS
refs to data: URLs (keeps the canvas untainted for PNG capture), but the C2
loadImage guard in piece-runtime.js only accepted literal CMS paths — it
rejected the very data: URL the server substituted, so nothing drew and the
capture copied a blank canvas. Only C2 routes media through this guard.

### Decision (user-approved plan)
1. Guard fix: piece-runtime.js gains isInlineMediaSrc (data:image/, blob:)
   and resolveRuntimeMediaSrc (inline pass-through, else the existing
   normalizeCmsMediaPath — also fixing the latent rejection of absolute
   same-origin URLs). loadImage resolves-then-rejects; managed-media
   tracking now counts inline srcs so PNG capture waits for their decode.
   Same guard parity in immersive-gallery.js createC2MediaHelpers, marked
   KEEP IN SYNC (creatr-media-path-guard). Generation-time validation stays
   strict (CMS paths only); ZIP-export bootstrap was already guard-free.
2. Immersive downloads: all three mounts in immersive-gallery.js return
   { destroy, getCaptureSurface } (three/aframe get preserveDrawingBuffer);
   three/aframe capture the stage canvas (user's current perspective, per
   user choice), gallery-room engines capture the artwork's own canvas,
   c2-interactive snapshots the open overlay iframe. public-piece-download.js
   exposes window.CreatrPieceDownload primitives; immersive piece.php adds a
   Download Piece / Download PNG cluster in .stage-wrapper (visible in
   fullscreen, gated !$isStaticEmbed).
3. Regular-view fullscreen: expand toggle on .piece-canvas-container +
   fixed bottom toolbar (Download Piece / Download PNG / Close) via new
   piece-fullscreen.js (native requestFullscreen, iPhone-WebKit CSS
   fallback, Escape/fullscreenchange sync, focus restore).
Verified locally against the deployment DB: c2 (106) renders image-82 with
no guard error and exports a non-blank untainted PNG on regular, fullscreen,
and immersive surfaces; three (107), aframe (109), svg (108), p5 (105), and
c2-interactive overlay (104) all capture non-blank PNGs in immersive; ZIP
export embeds media as data: URLs and stays guard-free; suites pass
(118/0 generation; 82 pass consistency with only the 2 pre-existing gyro
failures also present on HEAD).

## 2026-07-05 — Immersive Collection Slideshow Traversal and Piece Interaction

### Context
User reported that the immersive collection slideshow (e.g. `/immersive/collections/apocalyptic`) only allowed viewing/animating the active piece, rather than enabling a complete slideshow traversal of all collection pieces. Touching a piece on the 3D VR gallery wall did not open the slideshow at that piece (or did not open it at all for Three.js/A-Frame pieces because their `full_view` was previously set to null to avoid WebGL context conflicts). Clicking the slideshow button always hardcoded the start index to 0.

### Decision (user-approved plan Option A)
1. **Unified Traversal & WebGL Suspension**: We restore `full_view` iframe renders for Three.js and A-Frame collection pieces in the PHP view. To prevent WebGL context limit conflicts and performance issues (especially in Safari) when multiple 3D scenes run simultaneously, we implement a resource-saving protocol. When the slideshow overlay is open (`onOpen`), the main gallery wall's Three.js rendering loop is suspended (`isWallSuspended = true`) and all active wall slot WebGL contexts are destroyed. When the overlay is closed (`onClose`), the wall rendering loop resumes and the visible slots are progressively re-hydrated.
2. **Active Slide Tracking**: Added `getActiveIndex()` on the exhibit wall viewer to determine the index of the piece closest to the camera center target. Clicking the slideshow button queries `getActiveIndex()` to open the slideshow starting with the currently focused piece on the wall.
3. **Interactive Touch Open**: Clicking/touching any piece in the immersive VR view maps to `readOnlyOverlay.openAt(slideshowIndex)`, correctly launching the slideshow overlay at that piece's index. Clicking P5.js, C2.js, and interactive C2.js pieces in gallery immersive VR modes successfully opens the slideshow/fullscreen view within the browser.

### 2026-07-05 Follow-Up — Ghost Click Mitigation & Image Support in getActiveIndex
1. **Ghost Click Prevention**: On mobile Safari and touchscreen browsers, the 300ms delayed synthetic click event after pointerup/touchend targeted the newly visible overlay background (`root`), triggering the backdrop-close listener immediately and exiting the slideshow. To resolve this:
   - Changed overlay `openAt` calls to execute inside a `setTimeout(..., 50)` delay, allowing pointer events to fully disperse before rendering the overlay.
   - Increased the overlay's backdrop click guard to 500ms (`elapsed < 500`).
   - Unified the click handler inside `onPointerUp` to go through a local `openSlideshowAt()` wrapper.
2. **Image Support in getActiveIndex**: Updated `getActiveIndex()` to match both `piece` and `image` kinds so that the wall correctly calculates the closest item when images are active.
3. **Debugging Logs**: Added stack trace printing (`new Error().stack`) inside `openAt()`, `close()`, and `suspendExhibitWall()` to trace execution call stacks in the browser console.

## 2026-07-05 — Preserving OrbitControls Export in Offline Three.js Bundles

### Context
When downloading a platform collection, clicking on a Three.js piece in the slideshow mode failed with a `TypeError: OrbitControls is not a constructor` at runtime inside the iframe. The same crash affected standalone downloaded Three.js pieces (`/pieces/{id}/download`).

### Decision (user-approved plan Option 1)
1. **Preserve OrbitControls Export**: We resolved the crash by removing the regex replace statement in `piece_export_three_orbitcontrols_inline_source()` inside `public/app/helpers/piece-render.php` that was stripping out `export { OrbitControls };`.
2. **Dynamic ES Module Resolution**: Preserving the export statement ensures that the dynamic module bootstrapper `await import(creatrOrbitUrl)` can successfully load and resolve the `OrbitControls` constructor offline without any internet connection.
3. **Verified**: Verified that existing Three.js runtime consistency test suites pass cleanly and OrbitControls is correctly exported in the bundled offline source.

### 2026-07-05 Follow-Up — OrbitControls Keyboard Controls & Collection ZIP Packaging
1. **OrbitControls Keyboard Controls**: Called `controls.listenToKeyEvents(window)` after instantiating `OrbitControls` in `piece-runtime.js` and in both the bundle-mode and CDN-mode `piece-render.php` bootstrap templates. This enables keyboard navigation (WASD/arrows) for focused regular views and downloaded index files.
2. **Collection ZIP Packaging**: Updated `collection_export_build_manifest()` to compile and write each item in the collection inside its own standalone directory under `pieces/{slug}/` (containing its own standalone `index.html`, assets, styles, scripts, and runtime files). Updated the packaging loop in `collection_export_bundle()` to support files using `source_path` as well as raw string `data`.

## 2026-07-07 — Automated ALGORITHMS.pdf Publishing via GitHub Actions

### Decision
`ALGORITHMS.pdf` is now built and published automatically by a new GitHub
Actions workflow (`.github/workflows/publish-algorithms-pdf.yml`). The
workflow triggers on pushes to `main` that touch `ALGORITHMS.md` or
`diagrams/**`, plus manual dispatch from the Actions tab.

### Publishing strategy
A **rolling GitHub Release** tagged `algorithms-latest` — the tag moves
forward on each rebuild, so there is always exactly one current PDF. The
PDF is a release asset, not committed to the tree (`.gitignore` keeps
`ALGORITHMS.pdf` ignored). `--latest=false` prevents the rolling release
from appearing as the repo's "latest release" in the sidebar.

### Dependency: md-to-pdf
`md-to-pdf` (npm) is installed ephemerally in the GitHub Actions runner.
It uses Puppeteer/headless Chromium — the same rendering engine as the
VS Code "Markdown PDF" extension the owner uses locally. No project
dependency is added. If `md-to-pdf` breaks or is abandoned, the fallback
is the VS Code extension + manual upload via the Releases UI.
Documented in `docs/dependencies.md`.

### Caveat
CI-generated PDF may differ slightly from the VS Code extension output
(Ubuntu runner fonts vs. macOS fonts, page break placement). Review the
first automated build output and add custom CSS to the workflow if
adjustments are needed.

### Files
- `.github/workflows/publish-algorithms-pdf.yml` — new workflow
- `docs/dependencies.md` — new `md-to-pdf` entry
- `docs/README.md` — updated to reference the automated workflow

## 2026-07-08 — 3D model uploads (OBJ/GLTF/GLB) + Tone.js movement sonification

### Decision
Two immersive-gallery features, built together (plan:
`~/.claude/plans/can-you-gauge-the-compressed-cocoa.md`).

**3D model uploads.** OBJ/GLTF/GLB accepted into the media library and
auto-wired into AI-generated Three.js/A-Frame pieces.
- Cap: **64 MB** (matches the video cap and the hard-coded
  `SET SESSION max_allowed_packet = 67108864` in `upload.php`; no infra change).
- Routed **by file extension**, not finfo MIME (`.glb`→octet-stream,
  `.obj`→text/plain, `.gltf`→json are unreliable), then stored under a
  **canonical `model/*` MIME** so the grid/picker/serve classify reliably.
  New `upload_model_media()` + `ALLOWED_MODEL_EXT`; gated on `media_models`.
- Consumption: no new ref syntax — an uploaded model's `/media/{id}` already
  matches the cms-media allowlist. `GLTFLoader`/`OBJLoader` are attached to each
  piece's instrumented `THREE` in **both live runtimes** (immersive-gallery.js
  via a contained dynamic `import()`, piece-runtime.js likewise) so generated
  code calls `new THREE.GLTFLoader().load('/media/{id}', …)` with no
  preflight-forbidden `import`/`fetch` token. A-Frame uses `<a-asset-item>` +
  `gltf-model`/`obj-model` via an explicit, model-only exception to the base
  prompt's `<a-asset-item>` ban. OBJ is geometry-only for v1.

**Movement sonification (Tone.js).** Optional, prompt-driven.
- Tone.js is **self-hosted** at `public/assets/vendor/tone/Tone.js` (Rule 6 /
  vendor rule; documented in `docs/dependencies.md`), lazy-loaded only on the
  "Tap to enable sound" gesture (browser autoplay policy).
- Params are **data, not code**: the AI emits a 4th ```sonic``` JSON block
  ({tempo, scale, instrument, feel}); generated code never touches audio. Stored
  in a new optional `art_piece_versions.sonic_params` column. The immersive
  runtime owns Tone.js and drives it from the same per-frame camera motion the
  navigation legs produce (a new "audio leg"). Only the focused/active piece
  sonifies. Gated on `art_piece_sonic_params_supported()` (a probe of the
  `art_piece_versions.sonic_params` column — there is no `ai_pieces_sound`
  feature flag).
- `validate_art_piece_sonic_params()` **soft-fails** (coerce to nearest
  supported instrument/scale, clamp tempo; malformed/missing → null "no sound")
  and is **decoupled** from code validation so it never blocks valid code.

### Schema (dual-shipped; schema.sql is frozen — NOT edited)
- `docs/migrations/2026-07-08-art-piece-version-sonic-params.sql` +
  `ensureColumn` step in `setup-database.php`.
- `docs/migrations/2026-07-08-exhibit-media-kind-model.sql` (adds `'model'` to
  `exhibit_media_items.media_kind`) + a new idempotent `ensureEnumValue()`
  helper + step. NOTE: the ENUM value is only needed for placing models into
  exhibits via the picker; the AI-auto-wire-into-pieces path does not use it.

### Corrections vs. the approved plan
- The plan mentioned editing `schema.sql` for both changes; that was wrong —
  `schema.sql` is frozen. Both ship as migration record + setup-database probe
  only, per the Project Rules.
- Optional-column plumbing uses the existing `hasGenerationModeColumn()` /
  `ah_column_exists` pattern (`hasSonicParamsColumn()`), which is safer than the
  plan's fixed-column-list approach on unmigrated deployments.

### Verification status / follow-ups
- All changed PHP/JS lint clean; `three-runtime-consistency.php` passes for the
  runtime changes (static-import guard green; select/storage-column assertions
  updated for the new `sonic_params` signature). **2 pre-existing failures** in
  that test (`setupGyroControls`/`requestGyroCalibration`) are unrelated —
  they reference gyro function names refactored to `createSharedGyroController`
  in a prior commit and fail on unmodified HEAD too. Worth a separate cleanup.
- End-to-end browser + DB verification (upload/render/sound, and running
  `setup-database.php --yes` twice for idempotence) still to be done in a
  configured environment.
- **Offline export** of a model-bearing piece is a tracked follow-up: the
  export bootstraps in `piece-render.php` don't yet embed the loaders, so a
  model piece won't render in a downloaded bundle. Non-model pieces and all
  existing exports are unaffected (no regression).

## 2026-07-08 — Sonification scope correction + toggle UI fix

Follow-up to the same-day 3D/sonification entry, per user correction:
- **Sonification now applies to ALL piece types, immersive-view only** (not the
  original three/aframe restriction). The immersive gallery room gives p5/c2/svg
  pieces camera movement too, so sound is a property of the immersive view, not
  the engine. Removed the engine guard in `art_piece_sonic_capability_prompt()`
  and wired the audio leg into `mountGalleryPiece` as well (it was already in the
  three/aframe mounts). It remains absent from `piece-runtime.js`, so sound is
  never heard in the regular (non-immersive) piece view.
- **3D-model inclusion stays Three.js/A-Frame ONLY** (unchanged —
  `art_piece_model_capability_prompt()` returns '' for other engines).
- **Generate-form toggle** rebuilt as a proper switch (`.sound-toggle`). The old
  bare checkbox rendered as an oversized square with detached label because the
  admin form's `input { width:100% }` rule stretched it; the switch absolutely-
  positions the (transparent) input inside a fixed 42px control so that rule
  can't distort it, with the label text adjacent.

## 2026-07-09 — Visual and Audio Refinement Scope Boundaries

### Decision
Ensured clean boundaries for visual-only and sound-only changes during refinement and generation, complying with the user's domain-scoping expectations.

- **Visual-Only Refine:** When the visual prompt is the only prompt provided, the audio domain is out of scope. The existing `sonic_params` are carried forward exactly as-is from the current version. This prevents the sound design from being deleted/nullified even when the "Add or update instrumentation" checkbox is toggled.
- **Sound-Only Refine:** When only a sound prompt is provided, the visuals must stay exactly the same. The current visual code (HTML, CSS, JS) remains completely quarantined (omitted from the prompt context). Furthermore, visual preflight validation (`art_piece_preflight_document`) and media reference validation (`validate_art_piece_prompted_media_refs`) are bypassed, ensuring that legacy visual warnings or errors do not block sound-only refinements.
- **Both Domains:** If both prompts are provided, both are sent to the AI and refined concurrently.

### Verification
- Added prompt-scoping tests to `tests/art-piece-generation.php` and verified that they pass.
- Verified that all 126 tests in the test suite pass successfully.

## 2026-07-09 — Idle-pattern sonification, exhibit-wall toggle desync, screenshot allowlist, and a metadata-save re-validation regression

Ran alongside the other 2026-07-09 sessions above (some of this overlaps with
or builds on their sound-only-refine/metadata-toggle work) — this entry
covers the parts not already logged: idle-pattern playback, the offline
export parity work, and three separately-discovered bugs found while
verifying the sound toggle end to end.

### Decision

**Idle-pattern sonification** (`immersive-gallery.js`'s `createAudioController()`,
`piece-runtime.js`'s `createPieceRuntimeAudioController()`, and the matching
inline script in `piece-render.php`'s `piece_export_sonic_script()`): toggled-on
sound previously stayed silent at rest, only firing on real camera/pointer
motion. Added an idle ticker (2s-after-last-motion threshold) that plays a
plain scale-walk pattern from the same scale/instrument at rest; motion still
modulates pitch/octave as before and resets the idle clock. Also extended
sonification on the regular (non-immersive) `/pieces/{id}` view to every
engine, not just three/aframe: p5/plain-c2/svg get idle-only playback (no
motion signal there), c2_interactive gets pointer-position-driven modulation
via a new canvas listener gated on a `c2Interactive` context flag derived
from `generation_mode`. All three surfaces (live regular view, live immersive
view, and every offline export — both single-piece and collection bundles,
which already reuse the live runtime files directly) inherit this from the
same shared functions.

**Exhibit-wall (collection) sound toggle was three separate bugs, not one:**
1. The read-only "slide view" overlay (`createReadOnlyFullViewOverlay()`)
   never hid the shared stage toolbar's sound button, so it stayed
   interactive — and confusingly overlaid — behind the modal. Fixed by
   hiding/restoring it in `openAt()`/`close()`.
2. `createAudioController()`'s `dispose()` never removed its own click
   listener, so `mountExhibitWall`'s per-focus-change rebind (recreating a
   controller every time the nearest wall item changes) leaked one stale
   listener per rebind and let a controller's `enabled` state get wiped out
   mid-unmute by the next rebind before a click could take effect. Added a
   distance-margin (0.85×) plus a 500ms cooldown to `computeFocusedSlotIndex()`
   /the rebind gate so OrbitControls damping jitter can't thrash the focused
   index every frame.
3. Root architectural bug, found after (1) and (2) still didn't fully fix it:
   the click listener lived *inside* each per-item controller object, so any
   wall item with no `sonicParams` (most of them, typically) produced a
   `null` controller with **no listener attached at all** — the button looked
   identical whether or not a click would do anything, and appeared randomly
   "broken" depending on which item the camera happened to be focused on.
   Refactored `createAudioController()` to take an `attachListener` option;
   `mountExhibitWall` now owns exactly one persistent listener for the
   wall's lifetime, driving whichever controller is currently bound via new
   `toggleEnabled()`/`syncButton()` methods, and disables the button with a
   "No sound for this piece" label when the focused item has none.

**Screenshot-overlay allowlist**: `piece_export_supports_screenshot_overlay()`
was hardcoded to `['c2_interactive', 'three', 'aframe']` — a copy-paste of an
unrelated "interactive viewer" allowlist, not a real technical constraint.
The capture code already finds any `<canvas>`/`<svg>` generically and
captures with plain `toDataURL()`/`toBlob()`; the admin's live thumbnail
capture already proves this works for p5. Changed to
`art_piece_supported_generation_modes()` (every engine).

**Metadata/sonic-only saves were re-validating unchanged code.**
`PiecesAdminController::update()`'s version-creation branch called
`art_piece_preflight_document()` unconditionally, even when `$codeChanged`
was `false` and only `$sonicChanged` was `true` — validating the piece's
already-saved, already-working JS against a preflight rule
(`art_piece_preflight_code()`'s three.js `startFrame` arity check, added
2026-06-19) it may never have been run through before. This could reject an
unrelated metadata or sound-only save outright. Now gated on `$codeChanged`
— only code that's actually changing gets (re-)validated.

**Also added:** an admin edit link on the regular `/pieces/{id}` view
(mirrors `blog/show.php`'s existing pattern, gated on `admin_identity()`),
numeric-ID matching in the admin pieces list search (`buildSearchWhere()`),
and a working sound preview toggle in both admin generation/refine preview
surfaces (`generate-preview.php`, `form.php`) — these reuse
`piece-runtime.js`'s existing controller via `window.CREATR_PIECE_CONTEXT`
(now settable by `admin-piece-capture.js`'s `renderDocument()`), no new
client audio code.

### Corrections
- A reported "duplicate Version 1 rows" bug on pieces 18/19 was investigated
  with an explicit-authorization, read-only production `SELECT` — all
  duplicates predate the 2026-06-20 version-diffing fix; today's saves
  correctly increment version numbers. No code change was needed there.
- A reported "no Tone.js in downloads" belief was disproven by unzipping
  real downloads: single immersive-piece exports bundle Tone.js
  unconditionally, and collection exports already reuse the live
  `mountExhibitWall` runtime with every engine library bundled. Only the
  *inline-vs-standalone-file* packaging differed between the immersive and
  plain export paths, not sound support itself.

### Follow-ups
- Offline exports of `c2_interactive` pieces still only get idle-pattern
  playback, not pointer-driven modulation — `piece_export_bootstrap()` has
  no c2 pointer-listener branch to mirror `piece-runtime.js`'s live one.
  Flagged as a separate background task, not yet done.
- The translucent movement D-pad HUD (`createImmersiveViewerControls()`,
  34% opacity at rest) some screenshots showed is confirmed by-design, not a
  bug — left as-is pending explicit direction to change it.

### Verification
- `php -l`/`node --check` clean on every changed file.
- Live-server served files byte-diffed against source (ruled out stale
  opcache after edits).
- Downloaded and unzipped a single p5 piece, a single svg piece, a single
  immersive piece, and the full "apocalyptic" collection; confirmed the
  screenshot button, idle-pattern code, and the exhibit-wall listener fix
  are all present and `node --check`-clean inside the bundled runtime files.
- User confirmed in the browser: the metadata sound-playback checkbox fix
  unblocked piece 18's sound; the exhibit-wall persistent-listener fix is
  pending the user's own browser re-verification.

## 2026-07-09 — PluckSynth file:// worklet failure (root-caused, not patched) + GLTF/GLB-only model uploads

### Decision

**PluckSynth's AudioWorklet dependency, precisely diagnosed via a real
browser console trace** (not speculation — two rounds of static analysis
first wrongly concluded no instrument touches AudioWorklet at all): a
downloaded c2_interactive piece opened via `file://` showed `"Unable to load
a worklet's module."` only on unmute. The actual console trace named the
minified call stack; decoding it in the vendor bundle confirmed
`Qr` = `PluckSynth`, which constructs `Gr` = `LowpassCombFilter` internally,
which builds `Br` = `FeedbackCombFilter extends zr` where `zr` =
`ToneAudioWorklet`. Karplus-Strong plucked-string synthesis needs a real
feedback delay line, which Tone.js implements as an AudioWorkletProcessor —
a deliberate upstream design choice, not a Tone.js bug. Checked all 6 other
selectable instruments' constructors (`Synth`, `AMSynth`, `FMSynth`,
`MembraneSynth`, `MetalSynth`, `DuoSynth`) for the same pattern — **zero
reference the comb-filter/worklet classes or `AudioWorklet`/`addModule` at
all.** `PluckSynth` is the sole exception among the 7.

Under `file://`'s opaque/null origin, Chrome refuses to load that worklet's
blob-URL module ("Not allowed to load local resource"), producing an
unhandled `AbortError` fired asynchronously from inside `PluckSynth`'s own
constructor — unrelated to and unreachable from this app's own unmute-click
code. Patching the vendored Tone.js bundle to reimplement
`FeedbackCombFilter` without a worklet was considered and rejected as
disproportionate (real vendor surgery, ongoing re-verification burden on
every future Tone.js update, and it would change how `PluckSynth` actually
sounds even outside this one narrow scenario). Fixed instead by recognizing
this specific, known-benign rejection shape (`AbortError` + message matching
`/worklet/i`) via `unhandledrejection` filters and suppressing it from the
red error banner in all three sonification runtimes —
`immersive-gallery.js`, `piece-runtime.js`, and both inline error-handler
scripts in `piece-render.php` (single-piece and collection exports) — since
it isn't a real piece-rendering failure. No vendor file touched. The piece
still plays; only the comb filter's pitched resonance is affected under this
one narrow condition (`plucksynth` + `file://`).

**3D model uploads (GLTF/GLB/OBJ) didn't actually work in the Media Library
UI**, despite the PHP upload layer being fully implemented since 2026-07-08.
Root cause: a second, independent client-side gate —
`pickerModeConfig()` in `public/assets/js/tiptap-editor.js` — was never
updated for the `media_models` feature. Its `'media'` mode branch hardcoded
an image/video-only `accept`/`types` allowlist that silently overwrote the
already-correct server-rendered `accept`/hint in `layout.php` every time the
picker opened, and separately blocked the Upload button via a MIME-type
check even if a model file was selected. Fixed by adding a
`data-media-models` attribute (set server-side from `feature_enabled()`) to
the file input, which `pickerModeConfig()` now reads to conditionally add
`.gltf`/`.glb` to `accept`/`types`/hint, plus an extension-based fallback in
`showFileInfo()`'s type check (browsers unreliably sniff GLTF/GLB MIME
types, often reporting an empty string).

Per explicit user confirmation, nothing has been uploaded in any of these
formats yet, so this was purely forward-looking: **narrowed to GLTF and GLB
only, OBJ dropped entirely** (self-contained single-file formats vs. OBJ's
typical dependency on companion `.mtl`/texture files this app's
single-file upload flow doesn't support). Removed `'obj'` from
`ALLOWED_MODEL_EXT` (`upload.php`), the upload error message, `layout.php`'s
accept/hint strings, both Three.js/A-Frame "3D MODEL CAPABILITY" AI
system-prompt sections (`art-piece-generation.php`), and the `OBJLoader`
dynamic imports/attachments in both `immersive-gallery.js` and
`piece-runtime.js`. Left `art_piece_out_of_scope_media_extensions()`'s
`'obj'` entry untouched — confirmed that's an unrelated defensive
prompt-sanitization list (elides stray file-name references during
sound-only refines), not a format-support declaration; removing it would
have been the wrong direction.

### Verification
- `php -l`/`node --check` clean on every changed file.
- Confirmed via a synthetic `piece_export_document()` call with
  `instrument: "plucksynth"` that the assembled offline-export document
  contains the worklet/AbortError filter.
- Live-server served `immersive-gallery.js` byte-diffed identical to source.
- Grepped all touched files post-edit for `OBJLoader`/`.obj`/`'obj'` —
  zero remaining references outside unrelated identifier usage (e.g.
  `obj-model`, `Object3D`).

## 2026-07-09 — Sound expansion: three concurrent voices, piano keyboard, per-piece Audio tab, hand-tracking, and downloader-chosen ZIP contents

### Decision

**Rebuilt the movement-sonification engine around three concurrent Tone.js
voices instead of one.** The prior single-synth design forced idle-pattern
and motion-triggered notes to share one monophonic voice (audibly cutting
each other off) and had no way to let a keyboard/hand-tracking melodic layer
play *over* the ambient soundscape rather than replacing it — the user's
stated goal was explicitly to avoid needing a second sound library (Wad.js/
XSound.js) for layering, since Tone.js's single shared `AudioContext` already
supports arbitrarily many concurrent instruments for free. `sonic-controller.js`
now builds three independent Tone instruments — `ambientSynth` (idle-timer
scale-walk), `movementSynth` (motion-triggered), `melodicSynth` (keyboard/
hand-tracking-triggered) — all `.connect()`ed through one shared `Tone.Filter`
(admin-tunable cutoff/resonance) into one shared `Tone.Volume` bus, so a
single slider controls the combined mix and `setInputMode()` is now purely a
UI-facing "which control source feeds the melodic voice" flag rather than an
audio mute switch. This module is the single shared engine for all four
sound-bearing surfaces (immersive views, the regular `/pieces/[id]` view via
`piece-runtime.js`, and both ZIP export bootstraps) — replacing what had been
three separately-duplicated synth implementations.

**Added a real piano keyboard** (C-to-B chromatic layout, octave display +
up/down, `triggerChromaticNote()`/`setOctave()` on the engine) replacing the
placeholder 7-button scale-degree grid, plus **physical-keyboard play**
using the standard "typing keyboard as piano" convention (home row `A S D F
G H J K L ;` = white keys, `W E T Y U O P` = black/sharp keys in the gaps).
The physical-key listener is attached **only while on-screen keyboard mode is
toggled on**, specifically to avoid colliding with existing WASD/arrow-key
Three.js camera-movement shortcuts — verified live that toggling keyboard
mode off fully restores normal camera movement with no lingering listener.

**Added a per-piece "Audio tab"** in the admin piece editor exposing
mechanical, non-AI-authored settings — per-voice public-visibility toggles
(ambient/movement/melodic/hand-tracking), a default volume, and admin-only
synth tuning (octave range, filter cutoff/resonance/type) — stored as a
nested `extras` key inside the *existing* `sonic_params` JSON column (no
schema migration). Kept deliberately separate from
`validate_art_piece_sonic_params()`'s own AI-authored canonicalization
specifically so `art_piece_sonic_params_equal()` (used to decide whether a
save forks a new version) naturally ignores `extras` with zero changes to
that function — confirmed via direct execution that toggling an Audio-tab
setting does **not** fork a new version (a small `PiecesAdminController`
branch updates the current version row in place instead), while an actual
code/AI-sonic change still does.

**Added camera hand-tracking** (MediaPipe Tasks-Vision `HandLandmarker`,
self-hosted at `public/assets/vendor/mediapipe-hands/`, ~19.4MB — WASM engine
+ float16 model, no UMD build available so loaded via dynamic `import()`
even from classic, non-module scripts). Wrist height drives continuous pitch
glide (not discrete triggers, for real theremin feel); wrist-to-fingertip
spread drives that voice's own volume, independent of the shared master
slider. Gated to single-piece full-view contexts only — confirmed the
exhibit-wall/gallery-room multiplex never receives `allow="camera"` or the
hand-tracking toggle at all, so no parallel inference or camera prompts fire
across unfocused wall thumbnails.

**Discovered mid-implementation that hand-tracking was initially wired into
only two of the four sound surfaces** — the immersive view and the live
regular-view (`piece-runtime.js`/`piece-fullscreen.js`), but not
`piece_export_sonic_script()` (the self-built popover used for every
non-immersive ZIP export, including every piece inside a collection). This
meant the ~19.4MB MediaPipe payload was being bundled into non-immersive
exports as pure dead weight with no UI to ever trigger it — and, worse, was
being duplicated **once per piece** inside collection ZIP exports with zero
cross-piece deduplication, a real bug that would blow a collection past
100MB for a handful of hand-tracking-enabled pieces. Fixed in two passes:
first by removing the dead-weight bundling entirely (temporarily leaving
hand-tracking export-only in the immersive path), then, once the user asked
for hand-tracking to work in *all* downloaded pieces, by properly wiring a
matching toggle into `piece_export_sonic_script()` and restoring conditional
bundling — with an explicit, user-confirmed decision to keep collection
exports excluding hand-tracking entirely (`piece_export_force_voice_off()`,
via `collection_export_build_manifest()`'s `'exclude_hand_tracking' => true`)
rather than build collection-root deduplication, since a solo download of
the same piece still gets it and the wall is a live-viewing surface first.

**Added a downloader-facing ZIP-contents picker** on the regular `/pieces/[id]`
view: before downloading, the person chooses which admin-*allowed* optional
panels (keyboard, hand-tracking) ride along in their specific export via a
`dl_voices` query param, resolved server-side by
`piece_export_apply_requested_voices()` — the admin's per-piece config is
always a ceiling, never expandable by the downloader. Collections
intentionally get no picker at all; every piece in a collection ZIP keeps
using its admin-configured defaults exactly as before this whole feature
existed, per explicit user instruction.

### Verification
- Live-verified (browser, no console errors) on both an immersive Three.js
  piece and the regular `/pieces/[id]` view: popover open/close, volume
  slider, mute/unmute, piano key clicks, physical key presses (`a`, `w`, `j`),
  and octave up/down all work identically across both surfaces.
- Confirmed WASD camera movement is fully undisturbed with keyboard mode off,
  and fully restored after toggling it off again.
- Confirmed a piece with no `sonic_params` at all renders zero sound UI
  (clean regression check) and a piece with sonic but no optional voices
  enabled shows a plain, un-popovered download link.
- Direct PHP execution (bypassing the DB, using synthetic `$piece`/`$version`
  arrays) against the real `piece_export_build_manifest()`/
  `collection_export_build_manifest()` code paths confirmed all four
  target scenarios: solo hand-tracking export bundles MediaPipe + renders
  the toggle; the same piece inside a collection context does neither;
  downloader-unchecked hand-tracking bundles neither; a piece with
  hand-tracking disabled by the admin is unaffected by any of this.
- Downloaded the real `apocalyptic` collection ZIP and confirmed via
  `unzip -l` zero MediaPipe files anywhere in it, and downloaded a real
  single piece's ZIP confirming `dl_voices=` (nothing checked) produces a
  clean, ~8.3MB export with no dead assets.
- `art_piece_sonic_params_equal()` confirmed (direct execution) to return
  `true` across two JSON blobs differing only in `extras`, verifying
  Audio-tab-only saves cannot fork a spurious new version.

## 2026-07-10 — Offline Art Piece Hand-tracking & Mic Fallbacks

### Decision

Implemented a local-first with runtime CDN fallback strategy for downloaded art pieces to resolve errors under the `file://` protocol (CORS blocks on local asset loads/fetches and browser sandbox blocks on Web Workers/WASM resolver):

- **MediaPipe (Camera Theremin) Fallback:** Modified `loadHandLandmarkerOnce` in `sonic-controller.js` to catch failures loading local files from `runtime/mediapipe-hands/...`. When a failure is caught, it falls back to loading `@mediapipe/tasks-vision@0.10.8` from jsDelivr and Google's static storage bucket.
- **Tone.js Fallback:** Modified `loadToneOnce` in `sonic-controller.js` to catch script errors when loading local `runtime/tone/Tone.js` and fall back to loading Tone.js from CDNjs.
- **Troubleshooting Notice Drawer:** Dispatched custom `creatr-hand-tracking-failed` and `creatr-mic-failed` events from `sonic-controller.js` on permission or script failures. Added event listeners inside `piece-render.php`'s offline sonic script to slide up a beautiful, glassmorphic banner under `file://` (or on blocks like Safari's secure context restriction). The banner explains the restriction and provides copy-pasteable terminal commands to run a local HTTP server (`npx http-server .` or `python -m http.server 8000`).
- **Worklet-Free Custom BitCrusher:** Replaced the native `Tone.BitCrusher` with a custom `Tone.WaveShaper` based implementation. This avoids browser sandbox blocks against Web Workers/AudioWorklets instantiated from dynamic Blob URLs under the `file://` protocol, keeping the microphone input fully active when the effect is toggled.
- **Static Script Loading to Preserve User Gestures:** Modified script injection in `piece-render.php` to output static script tags for Tone.js and `sonic-controller.js` in the exported HTML. Pre-loading the scripts ensures the audio controller initializes synchronously inside user click event handlers, preserving the browser's user gesture context so camera and mic prompts are not blocked by the browser.
- **Toggle Button Highlights:** Added `.offline-sound-btn` CSS classes and highlight rules for active `[aria-pressed="true"]` buttons in the exported page stylesheet to visually match the active toggles on the live site.
- **Screenshot Icon Disappearing Fix:** Corrected click handlers in `piece-render.php`, `collection.php`, and `piece.php` to conditionally check for a nested `span` inside screenshot buttons before attempting to set their text content. This prevents the browser from replacing the inner SVG template (the camera icon) on icon-only buttons.
- **dependencies.md Update:** Documented the CDN endpoints and fallback conditions per the pre-write check rules.

### Verification

- Added automated test `hand-tracking bundle export includes MediaPipe, fallback loading, and troubleshooting banner` to `tests/art-piece-generation.php`.
- Ran the test suites and verified all checks pass:
  - `php tests/three-runtime-consistency.php` — **Passed: 114, Failed: 0**
  - `php tests/art-piece-generation.php` — **Passed: 142, Failed: 0**

## 2026-07-10 — ALGORITHMS.md Audit, Full Gap Coverage, Restructure

### Context
User asked to verify ALGORITHMS.md accuracy, capture all algorithms in the
codebase, re-evaluate all Potential improvements, and fill new sections to
match the existing recipe format. Mid-session, the user redirected to adapt
to three post-audit commits (573d524, 0e148f1, f21887f); the revised plan
was approved via plan mode.

### Agent loops (per AGENTS.md → Agent Use)
- 2 Explore agents (parallel): PHP-side and JS-side algorithm inventory +
  accuracy spot-check of 35 documented functions/constants.
- 1 Explore agent: diff audit of the three post-baseline commits.

### Decisions (user-approved via AskUserQuestion + plan approvals)
- Scope: document everything found, restructured for readability (TOC;
  new §11 AI Provider Client, §12 Movement Sonification, §13 Frontend
  Presentation, §14 Platform Utilities; §3.6–3.8, §4.7–4.10 added).
- New recipes get full Overview/Pseudocode/Instructions/Analysis blocks with
  diagram placeholder paths (two pending PNGs: ai_provider_call_pipeline,
  sonification_pipeline — to be generated via the user's diagram thread).
- All existing Potential improvements reviewed; additions only, none stale.

### Corrections made (doc-only; no code touched)
- hasVisiblePixels attribution fixed (public-piece-download.js, not
  immersive-gallery.js).
- OBJ upload references removed (ALLOWED_MODEL_EXT is glb/gltf-only since
  7ac6064); doc now states the deliberate OBJ exclusion rationale.
- §12 rewritten for the sonic-controller.js voice architecture (ambient
  voice is continuous — the old IDLE_GAP_MS stillness gate is gone; added
  hand-tracking theremin §12.5, mic effects chain §12.6, extras schema
  §12.7); §4.3 notes arrow-keys-only navigation (disableAFrameWASD);
  §3.4 documents the export module-syntax guard; §7.3 adds the 32 MB
  media_audio branch; §9.2 lists all rate-limit scopes; §9.6 documents
  ensureEnumValue.

### Verification
- All function/file references in new text grep-verified at HEAD; zero
  stale names (IDLE_GAP_MS/pieceLoadToneOnce/motionTick absent from doc).
- All markdown links resolve except the two intentional diagram
  placeholders; TOC anchors follow GitHub slug rules.

## 2026-07-10 — iOS Regular-View Fixes + Camera Background & Hand Control

### Context
User reported iOS Safari breakage on /pieces/113 (unstyled fullscreen/sound
UI, clickable footer in fullscreen, dead camera theremin, mic silencing the
ambient voice) and requested two features: camera feed as piece background
and hand-tracking piece control. Plan approved in plan mode (scoping via
AskUserQuestion: inline critical CSS + cache-bust; defensive mic fix;
camera background three/aframe only; hand-as-orbit control).

### Agent loops
- 2 Explore agents (parallel): regular-view UI/CSS forensics; sonic-controller
  hand/mic tracing + feature feasibility.

### Root causes and fixes
- Unstyled UI: styles.css linked without cache-busting (header.php) while
  the recent .piece-* rules only lived in that external sheet; iOS served a
  stale copy. Fixed: `?v=filemtime` on the stylesheet link AND the .piece-*
  block (styles.css 1695–2198) MOVED verbatim into a new
  `piece_view_critical_css()` (immersive-chrome.php) inlined by
  views/pieces/show.php — single source, deleted from styles.css.
  `.piece-immersive-link` stayed in styles.css (used by collections/show).
- Camera theremin dead on iOS: postMessage relays carry no user activation,
  and getUserMedia sat after Tone+MediaPipe awaits. Fixed: same-origin
  gesture bridge (`window.__creatrSonicGesture`, piece-runtime.js) called
  synchronously from parent click handlers (piece-fullscreen.js
  `gestureCall()`, postMessage fallback); engine-level getUserMedia-FIRST
  ordering (ref-counted `acquireHandCamera()`, mic permission stream before
  Tone load); MediaPipe model warmed on sound-enable; hidden video appended
  to DOM; `allow="camera; microphone"` on the regular-view iframe and the
  immersive full-view lightbox iframe (previously had NO allow attribute).
- Mic silencing ambient: piece 113 is synth-ambient, so the confirmed
  sample-Player interruption theory is incomplete; shipped defensive
  `recoverFromAudioSessionChange()` (context resume + sample restart +
  statechange listener). If the synth case still reproduces on device,
  next step is the ?sonicdebug diagnostics from the plan.

### New features
- "Steer the piece" (hand-as-orbit) + "Show camera" (VideoTexture scene
  background): shared ref-counted camera pipeline in sonic-controller.js
  (`enableHandControl`/`onHandFrame`/`acquireCameraFeed`), engine hooks on
  `window.__pieceHandHooks` (three: eased-spherical orbit + VideoTexture;
  aframe: yaw/pitch + VideoTexture; c2 interactive: synthetic pointermove,
  no background). Rows capability-gated via the iframe handshake. Export
  parity: hooks added to BOTH three export bootstraps (bundle + cdn) and
  rows/wiring in piece_export_sonic_script (three exports only).

### Follow-up scope (closed 2026-07-10)
- The initially omitted immersive mounted-view toggles and A-Frame offline
  export rows were completed in the parity follow-up below.

### Verification
- All tests pass (three-runtime-consistency 120 incl. 6 new contract tests;
  art-piece-generation 142; full suite green).
- Local browser (php -S via preview): /pieces/113 renders styled popover,
  piano, download picker from the INLINE CSS (rules no longer exist in
  styles.css, so rendering proves the inline path); CSS-fallback fullscreen
  lifts main to z-index 9600 — footer unreachable; sound enables via the
  gesture bridge (Tone loads); hand-control/camera toggles fail closed with
  aria reverted when camera is blocked; zero console errors; mobile-width
  @media rules apply from the inline block; export sonic script emits the
  new rows for three and not for aframe.
- Remaining: on-device iPhone verification against production (theremin,
  ambient-vs-mic, camera background, hand-orbit) — awaiting user.

## 2026-07-10 — Completed Hand/Camera Parity for Immersive Mounts and A-Frame Exports

### Decision

“Steer the piece” and “Show camera” now have Three.js/A-Frame parity across
regular live views, mounted immersive views, and standalone ZIP exports.
Mounted hand steering receives exclusive camera ownership: Three.js pauses
OrbitControls, arrow/click/wheel/viewer navigation, and gyro; A-Frame pauses
look/WASD, pointer, and viewer controls. Disabling steering restores the exact
previous control modes at the hand-steered pose, with gyro recalibrated from
that pose rather than snapping back.

The shared ref-counted MediaPipe camera stream remains the only camera source.
Theremin, steering, and camera background may be toggled independently; denial,
disable, page teardown, and viewer destruction release their references,
restore the prior scene background, and dispose video textures. Collection
exports remain excluded from hand tracking, and no schema, route, feature flag,
public endpoint, or vendor dependency changed.

Browser permission testing caught and fixed a mounted-view initialization gap:
`createAudioController()` now provides the synchronous `ensureEngineSync()`
path used by camera-first gestures, and live/immersive-export pages preload
`sonic-controller.js` so `getUserMedia` remains inside the originating click.

### Verification

- `php tests/three-runtime-consistency.php` — **Passed: 122, Failed: 0**
- `php tests/art-piece-generation.php` — **Passed: 143, Failed: 0**
- JavaScript and modified PHP syntax checks passed.

## 2026-07-10 — iOS Hand Tracking and Live Mic Recovery

### Decision

iPhone testing proved camera capture/background worked while MediaPipe hand
features failed and mic capture silenced the existing mix. The shared sonic
engine now treats these as downstream runtime failures: MediaPipe initialization
is retryable, direct-video inference falls back to a throttled canvas source,
and failures expose capability states instead of being swallowed. Steering then
offers explicitly labeled device tilt; camera theremin has no fake substitute.

Live mic now uses one `getUserMedia({audio:true})` stream connected through
`AudioContext.createMediaStreamSource()` into the Tone effects/bus. It no
longer opens a second Tone.UserMedia stream after the gesture. Failure stops
the mic, resumes the existing synth graph, and never enables feedback-prone raw
monitoring. `?sonicdebug=1` shows local-only stage diagnostics; nothing is
persisted or transmitted. No schema, route, feature flag, or vendor changed.
