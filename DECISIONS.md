# DECISIONS.md

<!-- Read at every session start. Older sessions are archived in
     docs/decisions-archive.md — the archive is the full record;
     this file holds the Project Profile, OPEN items, and recent
     sessions (current month). When archiving, always carry OPEN /
     REVIEW REQUIRED items forward into this file. -->

## OPEN ITEMS (carried forward from archived sessions)

From "2026-06-14 — Platform Rectification Pass" (full session in docs/decisions-archive.md):

### Remaining REVIEW REQUIRED Before Platform Deletion
- Adapter dry-run/mock tests still need to be run for all outbound syndication
  services before any real publish verification.
- Feed approval should be exercised with a mocked pending item and verified to
  create a draft post containing real title/content/source metadata.
- A final search and route matrix check must show no required route, data, or
  runtime asset still depends on `platform/`.

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
single-file HTML export for the current or selected version. Exports include
engine CDN imports and rewrite CMS media paths to absolute site URLs. They are
portable to another browser context with internet access, not offline bundles.
Exports intentionally omit immersive/admin/embed controls. Three.js exports
mirror the CMS viewer's interaction layer by instrumenting scene/camera/renderer
creation and attaching OrbitControls; A-Frame and C2 interactive exports pass
the live scene/canvas through so authored events remain interactive.

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
- `platform/`'s own legacy memory markdown left untouched by design:
  reference-only, slated for deletion behind the OPEN ITEMS block.
