# DECISIONS.md

<!-- Read at every session start. Older sessions are archived in
     docs/decisions-archive.md — the archive is the full record;
     this file holds the Project Profile, OPEN items, and recent
     sessions (current month). When archiving, always carry OPEN /
     REVIEW REQUIRED items forward into this file. -->

## OPEN ITEMS (carried forward from archived sessions)

None.

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

- **Stack:** No-framework PHP site with shared route handling in `public/index.php`.
- **Deployment:** PHP-capable static/shared hosting or PHP built-in server for local preview.
- **Database:** None.
- **Version pins:** None.
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
