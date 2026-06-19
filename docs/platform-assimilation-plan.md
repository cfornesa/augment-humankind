# Phased PHP Assimilation Plan

## Summary

Assimilate the Node platform into the current no-framework PHP app in phases,
with the current PHP MySQL database as the only write target. The live platform
database is source-only: migration tooling may read/export from `PLATFORM_*`
connection values but must never mutate that database. Public `/` remains the
current PHP homepage; the platform feed becomes canonical at `/blog`; old
platform public URLs get permanent redirects to reconciled PHP routes.

## Implementation Tracker

Tracking rule: after completing each task in each phase, mark the task as
`Done` here or in the relevant phase checklist in an interpretable way.

- Done — Persisted this phased plan in `docs/platform-assimilation-plan.md`.
- Done — Added confirmed memory entry for PHP-target-only/platform-read-only
  assimilation.
- Done — Documented `/blog`, platform redirects, `PLATFORM_*` source env vars,
  and the no-write platform DB rule in `docs/api.md` and
  `docs/dependencies.md`.
- Done — Sanitized `env.example` to placeholders only.
- Done — Added first additive target schema migration for platform
  assimilation.
- Done — Added dry-run-first migration tooling with separate source/target PDO
  connections and read-only source transaction.
- Done — Added public PHP blog shell, feed endpoints, search, and legacy
  redirects.
- Done — Added imported comment rendering on blog post detail pages.
- Done — Extend migration coverage for media, settings, AI,
  syndication, art pieces, and platform exhibits.
- Done — Added idempotent schema applicator for the target DB migration.
- Done — Applied target DB migration to `DB_*`.
- Done — Ran `--execute` platform data migration into `DB_*`.
- Done — Verified migrated public blog routes, feed routes, and legacy
  redirects.
- Done — Implemented Phase 3 auth/admin/CMS reconciliation: auth tables and
  OAuth owner bootstrap and user/account migration re-verified (read-only);
  page reconciliation confirmed as a no-op (0 platform pages); nav-link
  reconciliation fixed (corrected `kind='system'` mapping bug, repaired the 2
  dropped nav links, fixed `seedSystemItems()` sort-order shift so `Blog`
  lands in its intended position).
- Done — Phase 4A: admin CRUD for posts (draft/published/scheduled),
  comment/reaction moderation, and recycle-bin coverage for posts/comments at
  `/admin/posts`, `/admin/comments`, and `/admin/trash`.
- Done — Phase 4B: scheduled publishing + feed export route parity.
  `publishDuePosts()` flips `status='scheduled'` to `published` and overwrites
  `created_at` to the publish moment. `SiteSettings.php` and `seo.php` helpers
  added. `BlogController` generalized to `atomXml()`/`jsonFeedPayload()`/`mf2Payload()`
  with shared entry shape. New routes: `/feeds/mf2`, `/blog/category/{slug}/feed.*`,
  `/{slug}/feed.*`, plus legacy 301 redirects. `PageController::feed()` renders
  page content from `page_sections` concatenation. `BlogAdminController::postsIndex()`
  also calls `publishDuePosts()`. `views/blog/feeds.php` catalog enhanced with
  mf2 link, category feed list, and page feed note. `/export.json`/`/export/json`
  remain JSON Feed 1.1 per Rule 5; `/feeds/mf2` is the mf2 equivalent.
  4C-4H reordered — see the Phase 4 roadmap below.
- Done — Round 3: Piece Edit Reconciliation (Metadata/HTML/CSS/JS tabs + 4 engines) and Media Asset CRUD Parity (full metadata update and soft/hard delete wired to /admin/media and /admin/trash).
- Done — Round 4: AI-driven piece generation with multi-vendor LLM client support, Creative prompts sandboxed preview editor, and auto-repair retry loops.
- Done — Round 5: Immersive/VR Gallery Overhaul. Re-verified 2026-06-18: the
  post-embed, lazy-loader, VR-link, and collection-surfacing defects flagged
  during manual browser testing on 2026-06-15 were checked directly against
  current code and are fixed (this entry and the "Needs Repair" one below
  predate that day's `platform_exhibits`→`platform_collections` rename and
  were never updated afterward — see `docs/platform-gap-analysis.md`
  "Verified Fixed 2026-06-18" for the per-item evidence).
- Done — Platform Gap Analysis Rectification. Implemented all major and minor gaps identified in `docs/platform-gap-analysis.md`:
  - AI Content Helpers: `POST /admin/ai/process` (text improvement) and `POST /admin/ai/describe-image` (alt-text generation) with `AiProviderClient::chat()` and `describeImage()` support for all transport kinds. Tiptap toolbar integration with "Improve Text" button. Media library AI Alt Text generator for images.
  - Platform OAuth Callbacks: `GET /admin/platform-connections/auth/{platform}/start` and `GET /admin/platform-connections/auth/{platform}/callback` for WordPress.com, Blogger, LinkedIn, Facebook, and Instagram. OAuth callback exchanges codes, encrypts tokens, and saves/upserts them into `platform_connections`. Diagnostics page at `/admin/platform-connections/diagnostics` with endpoint reachability tests and setup checklist.
  - User Profile Photo Upload: `POST /admin/user-profiles/{id}/photo` with owner photos via `upload_media_auto()` and member photos via `profile_photo_assets`. Public serving at `/api/profile-photos/{filename}`. User edit form includes photo upload and preview.
  - Preferred AI Profile Settings: `preferred_art_piece_profile_id`, `preferred_text_improve_profile_id`, and `preferred_alt_text_profile_id` loaded/saved in `UserProfilesAdminController`. Three select dropdowns in user edit form. `PiecesAdminController::generateForm()` pre-selects owner's preferred art piece profile.
  - Admin Dashboard Metrics: Expanded from 4 to 17 stats covering posts (published/scheduled/draft), comments, reactions, connections, syndications, pieces, media, assets, feed sources, pending imports, and trash count.
- Done — Admin dashboard schema compatibility fix. Dashboard aggregate counts
  now check table/column existence before applying soft-delete filters, so
  `/admin` does not crash when a table such as `reactions` lacks `deleted_at`.
- Done — Manual browser parity pass. Re-verified 2026-06-18: post-embedded
  pieces/collections load reliably, embed VR links resolve correctly,
  TipTap's "Pieces/Collections" picker exists and is wired
  (`initPiecePicker()` in `tiptap-editor.js`), no `window.prompt()` calls
  remain anywhere in `public/`, and migrated platform collections surface at
  `/collections`, `/admin/platform-collections`, and the TipTap picker.

## Phase 1 — Foundation And Data Safety

- Done — Update `docs/api.md` and `docs/dependencies.md` before code changes to
  document `/blog`, redirects, platform-derived APIs, `PLATFORM_*` source env
  vars, and the no-write platform DB rule.
- Done — Sanitize `env.example` so it contains placeholders only; do not modify `.env`.
- Done — Add additive target-DB migration SQL only for the current PHP database:
  - Extend existing `categories` with `category_scope`, `platform_source_id`,
    `platform_original_slug`, platform timestamps, and soft-delete metadata.
  - Extend existing `pages` with `platform_source_id`,
    `platform_original_slug`, `content_format`, `content_text`,
    `author_user_id`, and timestamps.
  - Extend `navigation_items` with platform source metadata and
    `open_in_new_tab` compatibility.
  - Add missing platform-owned tables: users, accounts, sessions,
    verification tokens, posts, comments, reactions, post-category links, feed
    sources, feed seen items, site settings/assets, media assets, profile photo
    assets, AI settings, platform connections, syndications, art
    pieces/versions, and platform exhibit memberships.
- Done — Build migration tooling with two explicit PDO connections:
  - `DB_*` = writable target current PHP database.
  - `PLATFORM_*` = read/export-only source database.
  - Source code only issues `SELECT` reads and starts a read-only transaction.
- Done — Always remap migrated row identities:
  - Target rows get new PHP DB IDs.
  - Original platform IDs are stored in `platform_source_id` or mapping tables.
  - Foreign-key relationships are rebuilt through migration maps.

## Phase 2 — Publishing Core

- Done — Implement canonical public blog routes in PHP:
  - `GET /blog` for the feed.
  - `GET /blog/posts/[id-or-slug]` for post detail.
  - `GET /blog/categories` and `GET /blog/category/[slug]` for blog taxonomy.
  - `GET /search` for published post search.
- Done — Add permanent redirects:
  - `/posts/:id` to `/blog/posts/:mapped-id-or-slug`.
  - `/categories/:slug` to `/blog/category/:mapped-slug` for blog categories.
  - `/feeds` to `/blog/feeds`.
  - `/p/:slug` to top-level migrated CMS page URL.
  - Feed aliases such as `/feed.xml`, `/feed.json`, `/atom`, `/jsonfeed`,
    `/export.json` redirect or serve equivalent PHP feed output.
- Done — Render platform posts from the PHP target DB with title, rich HTML, featured
  image, source attribution, categories, comments, reactions, and
  deleted/draft/scheduled status rules preserved.
- Done — Keep existing portfolio category behavior intact by using
  `category_scope='portfolio'` for existing rows and `category_scope='blog'`
  for imported platform categories.

## Phase 3 — Auth, Admin, And CMS Reconciliation

- Done — Add platform-style `users/accounts/sessions` auth tables while
  preserving current `admin_identities`.
- Done — GitHub/Google admin allowlists remain the default owner bootstrap:
  - `ADMIN_GITHUB_USERNAMES` and `ADMIN_GOOGLE_EMAILS` can create or mark
    owner users on first OAuth login.
  - Existing admin OAuth login remains usable during transition.
- Done — Migrate platform users/accounts into remapped PHP users, preserving
  provider identities, roles, profile fields, social links, profile themes,
  and profile photos.
- Done — Reconcile platform pages into the existing PHP `pages/page_sections`
  CMS (verified no-op — platform source had 0 pages to migrate):
  - Existing PHP pages win on slug conflicts.
  - Imported platform pages become normal CMS pages with one section containing
    the original body.
  - If a slug conflicts, import as `platform-[slug]`, retain
    `platform_original_slug`, and redirect `/p/[original-slug]` to the imported
    page.
- Done — Reconcile platform nav links into `navigation_items` (fixed a mapping
  bug where platform `kind='system'` rows were mapped to PHP
  `source_type='system'`, causing `removeDefunctSystemItems()` to silently
  delete the imported Feeds and Categories rows; repaired both as visible
  external links to `/blog/feeds` and `/blog/categories`):
  - Existing PHP nav items keep priority.
  - Non-conflicting platform nav links are imported.
  - Conflicting platform nav links are imported hidden with source metadata.

## Phase 4 — Platform Feature Parity

Phase 4 is sequenced into sub-phases 4A-4H so each slice has its own
trackable `Done`/`Pending` status, per the tracking rule above. New vendor
dependencies (the syndication adapters in 4G) each require their own Rule 6
gallery + confirmation when that sub-phase is reached.

- Done — 4A: Admin Content Core. Added admin CRUD for blog posts
  (draft/published/scheduled) at `/admin/posts`, comment/reaction moderation
  at `/admin/comments`, and recycle-bin coverage for posts and comments via
  `/admin/trash`. No schema changes — reuses the existing admin CRUD pattern.
- Done — 4B: Scheduled publishing + feed export route parity.
  `publishDuePosts()` flips `scheduled` → `published` and overwrites
  `created_at`. `SiteSettings.php`, `seo.php` helpers, generalized feed
  serializers, new routes (`/feeds/mf2`, category/page feeds), and catalog
  enhancements all wired and verified.
- Done — 4C: Art pieces & platform exhibits admin — migrated platform
  `art_pieces`/`art_piece_versions` and `platform_exhibits` get their own
  public routes (`/pieces/*`) and admin surface (`/admin/pieces/*`),
  preserving existing `/portfolio/*` routes and `artworks`/`exhibits` data
  per Rule 5. The portfolio and platform art are kept separate because
  portfolio works are presentation/gallery focused while platform art pieces
  are generative/code focused.
- Done — 4D: Feed ingestion & moderation — `feed_sources` admin surface
  plus RSS/Atom ingestion and a pending-import moderation queue.
- Done — 4E: Site identity & media — `site_settings`/`site_assets` admin
  and a media library admin model beyond the existing `MediaFile` picker.
- Done — 4F: User profiles & AI settings — `PlatformUser` profile admin
  surface and AES-256-GCM vendor key encryption using
  `PLATFORM_AI_SETTINGS_ENCRYPTION_KEY` semantics mapped into PHP.
- Done — 4G: Platform connections & syndication foundation — scaffold the
  connections data model and admin surface only (no live adapters yet).
- Done — 4H: Syndication adapters for WordPress.com, self-hosted
  WordPress, Blogger, Substack, Bluesky, LinkedIn, Facebook, and Instagram.
  All 8 adapters implemented as PHP classes using GuzzleHTTP 7. Shared
  `AdapterFactory` maps platform names to adapter instances. `SyndicationPayload`
  and `SyndicationResult` classes handle content normalization. Content helpers
  (buildSocialPostText, buildSyndicatedContent, etc.) ported from platform's
  `content.ts`. `PlatformConnectionsAdminController::publish()` orchestrates
  post syndication via the adapter layer.
- Done — Rectification route/API parity pass. Added PHP compatibility routes
  for `/embed/pieces/*`, `/immersive/*`, and the required read-only `/api/*`
  surfaces; added a self-contained PHP piece renderer with error overlay;
  added `feed_import_items` persistence for full pending feed moderation;
  hardened platform connection token storage for newly saved credentials; and
  added `--verify-only` plus resume-skip behavior to the platform migration
  script.
- Done — Deletion readiness checks (app-code parity). Adapter mock tests,
  feed approval functional tests, retention checks, and post
  embed/VR/editor-insertion/collection-surfacing defects flagged by manual
  browser/admin testing are all re-verified fixed as of 2026-06-18. **Still
  open at the infrastructure level:** the deletion-readiness verifier's
  "no-`platform/` runtime dependency" check only covers PHP application
  code — it doesn't catch that `.github/workflows/scheduled-tasks.yml` still
  calls a `platform/` shell script for feed refresh. That's a real,
  separate blocker on deletion, tracked in the CMS-shell remediation plan's
  Phase F, not a parity gap in the PHP app itself.
- Done — Deletion-readiness verifier. Added
  `scripts/check-platform-deletion-readiness.php` with static, DB retention,
  rollback-only feed approval, rollback-only syndication, piece renderer, and
  optional HTTP route checks. Verified with the canonical dev server command:
  `php -S 127.0.0.1:8080 -t public public/index.php`.
- Done — Added `docs/platform-route-matrix.md` to track route/functionality
  parity against the platform before deleting `platform/`.
- Done — Rectification pass for broken admin pages and one missing media
  route. `--verify-only` confirmed the migration itself was already 100%
  complete (every source table count matched target/mapped counts); the 5
  fatal admin pages (`/admin/feed-sources`, `/admin/pieces`,
  `/admin/platform-connections`, `/admin/site-identity`,
  `/admin/user-profiles`) all shared one bug — their views built `$content`
  as a `Closure` instead of via `ob_start()`/`ob_get_clean()`, which
  `layout.php` cannot echo. Converted all 5 to the standard pattern (no
  controller changes needed). Fixed a null-array deprecation warning in
  `/admin/posts` (`$post['categories'] ?? []`). Ported the platform's public
  `GET /media/:fileName` route as `GET /api/media/{filename}`
  (`ApiController::mediaAssetByFilename`, `MediaAsset::findByFilename`),
  restoring previously-404ing `/api/media/{uuid}.ext` links in
  `site_settings.logo_url`/`logo_dark_url` and post content/featured images.
  Extended `MediaAdminController::library()` to merge the 102 migrated
  `media_assets` (as `asset-{id}` entries resolving via
  `/api/media-assets/{id}`) into the Tiptap media picker alongside native
  `media_files` uploads. Verified via `php -l`, a full admin-nav smoke test
  against the canonical dev server (all 15 admin pages 200, no fatal/warning
  log output, migrated counts render correctly), and a passing
  `php scripts/check-platform-deletion-readiness.php` run.
- Done — Rectification pass round 2: a second wave of issues found during
  manual browser testing of the round-1 fixes. (1) The same Closure-vs-
  `ob_start()` bug from round 1 also affected 9 more admin views —
  `pieces/versions.php`, `pieces/form.php`, `pieces/version-form.php`,
  `feed-sources/form.php`, `platform-connections/syndication-form.php`,
  `platform-connections/form.php`, `user-profiles/settings-form.php`,
  `user-profiles/key-form.php`, and `user-profiles/user-form.php` — all
  converted to `ob_start()`/`ob_get_clean()`. (2) Fixed a second, distinct
  `/admin/posts` deprecation: the `$statusTabs` array used a literal
  `null =>` key, which PHP 8.1 deprecates when coercing to an array offset;
  changed the key to `''` and updated the "All" tab's link/active-state
  comparisons. (3) Added the `.admin-container`, `.admin-header-row`,
  `.admin-tabs`/`.admin-tab`/`.admin-tab.active`, `.admin-link`/
  `.admin-link.danger`, `.inline-form`, `.status-badge` (+ `.status-active`/
  `.status-draft`/`.status-archived`), `.field`/`.field-grid`, and
  `.form-status`/`.form-status-error` rules to `public/assets/admin.css` —
  these classes were used by the views fixed in rounds 1-2 but were never
  defined, leaving tabs, action links, and headings unstyled (and the public
  `h1` rule leaking oversized headings into `/admin`). (4) Added
  `.piece-prompt pre { white-space: pre-wrap; ... }` to `public/assets/
  styles.css` so long prompts on `/pieces/{id}` wrap instead of overflowing.
  (5) Surfaced the existing (already-working) `/immersive/pieces/{id}` route
  with a "View in Immersive / VR Mode" link on `/pieces/{id}` (when the piece
  has renderable code) and an "Immersive" admin action link on
  `/admin/pieces` — no new routes or vendor dependencies, matching the
  platform's Three.js-based immersive presentation (the platform never had
  WebXR/headset VR). (6) Added a read-only live preview pane to
  `/admin/pieces/{id}/edit` using the existing `piece_render_iframe()`
  helper against `$piece['current_version']`. (7) Unified `/admin/media`:
  `MediaAdminController::index()` now merges `MediaAsset::all()` (102
  migrated rows, normalized `id` => `asset-{id}`, preview via
  `/api/media-assets/{id}`) alongside native `media_files`, rendered
  read-only (no Trash/Destroy) in `views/admin/media.php`. Verified via
  `php -l` on all changed files, an authenticated smoke test of every
  affected route (200s, no fatal/deprecation output), and a passing
  `php scripts/check-platform-deletion-readiness.php` run.

## Phase 4B — Completed

See the DECISIONS.md entry for 2026-06-14 — Phase 4B for the full decision
record. All implementation steps listed below were completed and verified.

### Context

4A (admin content core) is done. 4B was two pieces:

1. Scheduled publishing — flip `status='scheduled'` posts to `published`
   once `scheduled_at` has passed.
2. Feed export refinement — bring `BlogController`'s Atom/JSON Feed output,
   plus the platform's category feeds, page feeds, mf2 export, and feeds
   catalog, to parity.

### Standing Directives

- Stick to the platform's actual implementation scheme.
- `created_at` is overwritten to the publish timestamp when a scheduled post
  auto-publishes.
- Feed export scope = "Full route parity".

### Rule 5 Resolution — `/export.json` / `/export/json`

The platform serves mf2-JSON at `/export.json`/`/export/json`. Our PHP app
serves JSON Feed 1.1 there (must continue to work for existing clients).
The platform's mf2 export is available at the new `/feeds/mf2` route.

### URL Map (implemented)

**A. Existing 6 routes — same URL, same format, enhanced fields**

| Route | Format | New fields added |
|---|---|---|
| `/feed.xml`, `/atom` | Atom 1.0 | `<subtitle>` (site description), feed-level `<author>`, `<link rel="self">`/`rel="alternate"`, per-entry `<summary>`, `<category>` per post category |
| `/feed.json`, `/jsonfeed` | JSON Feed 1.1 | `description`, feed-level `authors`, per-item `content_text`, `tags` |
| `/export.json`, `/export/json` | JSON Feed 1.1 (unchanged format, Rule 5) | same enhancements as `/feed.json` |

**B. New site-wide route**

| Route | Format |
|---|---|
| `/feeds/mf2` | mf2-JSON (`{"items": [...]}` h-entry export, all published posts) |

**C. Category-scoped feeds (new)**

| Route | Format |
|---|---|
| `/blog/category/{slug}/feed.xml` | Atom 1.0, posts in category |
| `/blog/category/{slug}/feed.json` | JSON Feed 1.1, posts in category |

Plus 301 redirects from legacy alias paths to the canonical routes above.

**D. Page-scoped feeds (new, single-entry)**

| Route | Format |
|---|---|
| `/{slug}/feed.xml` | Atom 1.0, single entry = published page content |
| `/{slug}/feed.json` | JSON Feed 1.1, single entry = published page content |

Plus 301 redirects from `/p/{slug}/...` legacy paths.

**E. `/blog/feeds` catalog page (enhanced)**

Includes mf2 export link, "Category feeds" section, and page feed note.

## Test Plan

- Before migration: run schema inspection against target and source with no
  writes to platform DB.
- Migration dry run: report row counts, slug conflicts, redirect mappings,
  skipped/hidden nav conflicts, and category/page mappings without writing.
- Migration execution: write only to `DB_*`; verify platform row counts match
  imported/mapped target counts.
- PHP checks: `php -l` across `public/app`, `public/index.php`, and scripts.
- Route checks: `/`, `/blog`, `/blog/posts/...`, `/search`, `/contact`,
  `/portfolio`, `/admin`, old redirect URLs, and feed URLs.
- Data retention checks: every platform post, category, page, nav item, user,
  comment, reaction, media asset, feed source, AI setting, platform connection,
  and art/exhibit record has either a target row or an explicit migration
  report entry.
- Safety checks: migration tooling refuses to run if source and target database
  names are identical unless an explicit read-only export mode is used.

## Assumptions And Defaults

- Current PHP database is the only database that receives schema/data changes.
- Platform database is live and never receives writes.
- Old platform public URLs redirect to reconciled PHP routes, not canonical
  duplicates.
- Migrated IDs are always remapped; platform IDs are retained only as source
  metadata.
- Existing PHP homepage stays at `/`; platform feed moves to `/blog`.
- Existing PHP page, category, navigation, portfolio, media, and contact
  behavior takes precedence on conflicts, while platform data is retained
  through added columns, hidden imported rows, redirects, or source mapping
  metadata.

## Round 3 — Completed

### Phase A: Piece Edit Tab Reconciliation
- Fixed a bug in `PiecesAdminController` where the engine whitelist erroneously restricted values to `p5` and `css` (which isn't a valid engine). Expanded whitelist and dropdowns in `form.php` and `version-form.php` to include `p5`, `c2`, `three`, and `svg`.
- Restructured `/admin/pieces/{id}/edit` into four tabs: Metadata, HTML, CSS, and JS. Edits are applied directly to the piece's `current_version` (if it exists) or create a new version 1 (upon creation).

### Phase B: Media Asset CRUD Parity
- Implemented `MediaAsset::updateMetadata()` to allow editing title and alt text of migrated assets.
- Added `/admin/media/asset/{id}/update|trash|destroy` routes and wired them to the media library view details panel.
- Merged trashed `media_assets` into `/admin/trash` (Media tab), allowing restoring and purging of both native media files and migrated assets.

## Round 4 — Completed

- Created Guzzle-based `AiProviderClient.php` encapsulating multi-vendor API support (Gemini, DeepSeek, Mistral, OpenRouter, OpenCode).
- Added routes `GET/POST /admin/pieces/generate` and `POST /admin/pieces/generate/save` to generate art pieces using LLMs.
- Implemented the 3-attempt validation and repair loop checking window.sketch definitions and static structure.
- Created forms and preview views including sandbox preview and live repair logs.

## Round 5 — Completed

- Refactored `ImmersiveController` to delegate rendering of pieces and exhibits to distinct view files.
- Created `public/app/views/immersive/exhibit.php` rendering the multi-frame progressive exhibit wall with viewport-aware rendering budgets.
- Developed `public/embed.js` implementing client-side dynamic DOM scanner/interceptor to upgrade and lazy-load embeds inside blog posts and managed pages via `IntersectionObserver`.
- Fully verified all changes against the canonical PHP dev server using `check-platform-deletion-readiness.php --base-url=http://127.0.0.1:8089`.
