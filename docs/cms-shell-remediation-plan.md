# CMS Shell Remediation + Platform Deletion Plan

Status: Phases A-H complete in code (2026-06-18); remaining manual audit
items are gated on real human/browser or machine-local action. Tracking
rule: mark each item `Done`/`In Progress`/`Pending` here as it's completed,
matching the convention in `docs/platform-assimilation-plan.md`.

## Context

This repo is a no-framework PHP app currently configured as a single
business site (`augmenthumankind.com`), alongside a deprecated Node.js/TS
legacy app (`platform/`) whose features were supposed to have been fully
assimilated into the PHP app. Two things were resolved together:

1. **The "unresolved issues" audit.** An apparent contradiction between
   `docs/platform-gap-analysis.md` ("fully deletion-ready") and
   `docs/platform-route-matrix.md`/`docs/platform-assimilation-plan.md`
   ("Needs Repair") turned out to be **documentation staleness, not a code
   defect** — both docs froze at commit `0ff7093` (2026-06-15), the same day
   `platform_exhibits`/`/exhibits` were renamed to `platform_collections`/
   `/collections` across the whole codebase. All 5 "Needs Repair" defects
   were re-verified fixed in current code.
2. **A CMS-shell hardening initiative.** Independent of the docs, gap
   analysis surfaced real portability blockers preventing this codebase
   from being a reusable single-tenant white-label shell: a hard
   `smtp.hostinger.com` lock-in, business identity hardcoded across 40
   files (not just one), an inaccurate "schema.sql is the source of truth"
   claim, no friendly handling of missing/bad env vars, and no first-run
   setup experience (even though the deprecated Node app already built
   exactly that).

---

## Phase A — Documentation Reconciliation — `Done`

- `Done` — Rewrote `docs/platform-gap-analysis.md` and
  `docs/platform-route-matrix.md` to use real route/table names
  (`/collections`, `platform_collections`, `PlatformCollectionsAdminController`)
  throughout, with a "Verified Fixed 2026-06-18" section giving file-level
  evidence for each of the 5 previously-claimed defects.
- `Done` — Fixed matching stale "Needs Repair" entries in
  `docs/platform-assimilation-plan.md`.
- `Done` — Added the two missing `STATUS:` lines to `CONSTRAINTS.md`
  (platform DB read-only constraint, site-wide sign-in constraint) — both
  were already functionally implemented, just never closed out in that
  file.
- `Done` — Logged the root-cause finding and the two standing operational
  TODOs (AI-personas migration and thumbnail-migration script — were they
  ever run on the live Hostinger DB? Not verifiable from this repo) in
  DECISIONS.md.

## Phase B — CMS Shell: Critical Portability Fixes — `Done`

1. `Done` — Removed the `smtp.hostinger.com`-only lock-in from
   `public/index.php`'s `smtpConfiguration()`. Any SMTP provider now works;
   `SMTP_USERNAME` no longer needs to equal `SMTP_FROM_EMAIL`.
2. `Done` — Added `app_site_name()` to `public/app/helpers/seo.php`
   (`site_settings.site_title` → `APP_NAME` env → `"My Site"`).
3. `Done`, **larger than originally scoped** — A full-tree grep found 40
   files hardcoding `"Augment Humankind"` in *live* rendering (page titles,
   meta descriptions, the public header/footer brand, the admin layout
   brand link, login pages, the style-preview mockup) — not just
   `index.php`'s dormant static fallback as originally planned. All
   converted to `app_site_name()`. This surfaced a real pre-existing
   production bug: `site_settings.site_title` is actually `"AH Studio"`,
   but the hardcoded strings always showed `"Augment Humankind"` regardless
   — only the `og:site_name` meta tag read the real value. Verified via a
   real local dev server hitting the actual production-configured database.
4. `Done` — Wired the previously-configurable-but-unused
   `logo_url`/`logo_dark_url`/`logo_layout` Site Identity admin fields into
   the public header (`partials/header.php`), with light/dark logo swap CSS
   added to `assets/styles.css`.
5. `Done` — Fixed a real bug caught while testing #3/#4: `models/SiteSettings.php`
   was required by `router.php` and by the new static-fallback code in
   `index.php`, but not by the managed-page success path (`/`, `/services`,
   `/notes` when a published DB page exists) — so `app_site_name()` silently
   fell back to `"My Site"` on the homepage while correctly showing
   `"AH Studio"` on `/blog`. Fixed by requiring it in that code path too.
6. `Done` — Generic outbound `User-Agent` strings (`PhpCmsOAuth/1.0`,
   `PhpCmsAdminOAuth/1.0`, `PhpCmsFeedFetcher/1.0`) replacing
   `AugmentHumankind*` ones.
7. `Done` — `SiteIdentityAdminController`'s blank-`site_title` fallback
   changed from `'Augment Humankind'` to `'My Site'`.
8. `Done` — Consolidated database setup, **empirically validated against a
   real local MySQL 9 server** (not just read — actually run end to end).
   This caught genuine bugs:
   - `schema.sql` could not even apply standalone — it defined
     `post_sections` with a forward FK on `posts`, which only exists after
     the platform-assimilation migration runs. Removed from `schema.sql`
     (already correctly owned by `scripts/add-post-sections-table.sql`,
     which now runs later in the documented order).
   - `migrations/2026-06-14-platform-assimilation.sql` used
     `ADD COLUMN/INDEX/UNIQUE KEY IF NOT EXISTS` (22 occurrences) — MariaDB
     syntax that standard MySQL rejects outright. Same bug existed in
     `scripts/add-exhibit-content-slide.sql` and
     `scripts/add-wrapper-class-column.sql`. All fixed by removing
     `IF NOT EXISTS` from ALTER clauses.
   - `docs/migrations/2026-06-18-ai-personas.sql`'s `users` table ALTER
     block was fully redundant with the (already-fixed) base assimilation
     migration — fixed by removing the redundant block.
   - Confirmed two genuinely-missing-from-any-`.sql`-file pieces that only
     exist in idempotent PHP appliers: `art_piece_categories` table
     (`scripts/apply-portfolio-taxonomy-schema.php`) and `sort_order`
     columns on `platform_collections`/`art_pieces`
     (`scripts/apply-portfolio-ordering-schema.php`) — both are real
     dependencies of current models, not just legacy catch-up.
   - The verified, working fresh-install order (39 tables, zero errors) is
     documented in README.md's "Setting up a fresh database" section.
   - Fixed the false "schema.sql is the source of truth" claim in
     README.md and `docs/dependencies.md`.
9. `Done` — Added a `set_exception_handler()` in `index.php` rendering a
   friendly "this site isn't configured yet" page for uncaught
   `PDOException`s instead of a raw fatal. Verified against a real server
   pointed at a nonexistent database: `/portfolio` (no per-controller
   DB-failure handling) now shows the friendly page; `/blog`/`/contact`
   (already gracefully degrade) are unaffected.
10. `Done` — Rewrote `env.example` into three sections (new-site-required,
    optional/feature-gated, legacy-migration-only), reconciled against an
    exhaustive grep of every env var actually read in `public/`. Removed the
    old platform-publishing OAuth client env vars from runtime configuration,
    kept `CRON_SECRET`, `DB_PORT`, `DB_SSL`, and `SITE_TITLE` documented, and
    retained env-managed admin sign-in OAuth settings for GitHub/Google only.
    Renamed `PLATFORM_AI_SETTINGS_ENCRYPTION_KEY` to
    `AI_SETTINGS_ENCRYPTION_KEY` with a back-compat fallback read.
11. `Done` — Rewrote README.md: generic CMS-shell framing, a "This
    Deployment" callout for the augmenthumankind.com-specific instance,
    fixed stale route names, reframed Hostinger-specific instructions as
    one example among several.

### Safety incident during Phase B testing (resolved)

Local testing of the schema-consolidation work briefly connected to the
real production database due to a bug in one script's env-loading
(`scripts/apply-portfolio-taxonomy-schema.php` unconditionally overwrote
already-set environment variables from `.env`, unlike every other script).
Verified zero impact (the only statement that ran was a conditional
`UPDATE` that matched zero rows — confirmed via timestamp evidence). Root
cause fixed across 4 scripts that had the same bug
(`apply-portfolio-taxonomy-schema.php`, `apply-collection-thumbnail-schema.php`,
`check-platform-deletion-readiness.php`, `verify-platform-assimilation.php`).
Full incident record in DECISIONS.md, 2026-06-18 "Safety Incident" entry.

## Phase C — First-Run Setup Wizard — `Done`

Goal: port the Node platform's bootstrap-gate concept
(`platform/artifacts/api-server/src/lib/bootstrap.ts`) into PHP, adapted to
this app's simpler allowlist-based admin model (no Node-style anonymous
member-claims-ownership flow exists here — admin access is already fully
gated by `ADMIN_GITHUB_USERNAMES`/`ADMIN_GOOGLE_EMAILS`).

- `Done` — Bootstrap-state check (`public/app/helpers/bootstrap_state.php`,
  `site_bootstrap_complete()`): treats the site as "set up" once at least
  one active row exists in `admin_identities`. Fails open (treats as
  complete) on any DB error.
- `Done` — Friendly HTTP 503 "Site setup in progress" gate
  (`public/app/views/setup_gate.php`, wired into `public/index.php`) for
  anonymous visitors on public routes while bootstrap is incomplete, with a
  sign-in CTA. `/admin/*`, `/api/*`, `/embed/*`, `/immersive/*`,
  `/assets/*`, `/vendor/*` are exempt. Verified against an isolated
  throwaway database: 503 on all public routes with zero admin identities,
  200 once one exists; verified the gate fails open (doesn't block) when
  DB is unreachable, preserving the pre-existing "safe static behavior"
  guarantee.
- `Done` — `/admin/setup` checklist screen
  (`AuthController::setup()` + `views/admin/setup.php`, linked from the
  admin header) showing onboarding steps (admin sign-in, site title,
  canonical URL, AI vendor, contact form) with links to the relevant admin
  screens. Verified the underlying `site_bootstrap_checklist()` data
  against the real database.

### Two regressions found and fixed while testing this empirically

1. The gate-check code in `index.php` pre-requires `helpers/schema.php`,
   `helpers/seo.php`, and `models/SiteSettings.php` for every request.
   `router.php` already required those same files with plain `require`
   (not `require_once`), so once both ran in the same request, every
   router-dispatched route fataled with "Cannot redeclare function."
   Fixed by converting all 67 top-level `require` statements in
   `router.php` to `require_once`.
2. `ai_encryption_key_raw_env()` (the Phase B encryption-key rename
   fallback) used `$_ENV[$name] ?? getenv($name) ?? ''`, but `getenv()`
   returns `false` (not `null`) when unset, so `??` never fell through to
   the legacy `PLATFORM_AI_SETTINGS_ENCRYPTION_KEY` name — silently
   breaking AI key decryption on the real deployment, which only has the
   legacy name set. Caught by re-running
   `scripts/check-platform-deletion-readiness.php` against the
   live-configured database as a regression check; confirmed fixed by
   re-running it again (7/7 keys decrypt correctly).

Full detail in DECISIONS.md's 2026-06-18 "Phase C" entries.

## Phase D — Operational Hardening — `Done`

Confirmed implementation direction before coding:

- `Done` — **Strict hardening profile** selected. Use PHP-site additive
  schema for both rate limiting and structured audit logging; do not fall back
  to filesystem-only storage.
- `Done` — Platform publishing/social OAuth app credentials are
  **DB-only** in the PHP site's own `platform_oauth_apps` table. The legacy
  platform database remains read-only and must not be used as a credential
  source. Admin GitHub/Google sign-in credentials remain env-managed for now.
- `Done` — DB-backed fixed-window rate limiting on admin login, `/contact`,
  and AI endpoints (`/admin/ai/process`, `/admin/ai/describe-image`,
  `/admin/pieces/generate`, `/admin/pieces/refine-ai`), with no raw IP storage.
- `Done` — Structured logging helper plus PHP-site audit-log table with
  secret redaction for auth, AI vendor calls, syndication adapter calls, and
  cron endpoints.

### Phase D verification/result notes

- `Done` — Added additive PHP-site schema for `request_rate_limits` and
  `audit_log_events`, applied and verified against the live PHP database.
- `Done` — Verified contact-form throttling returns `429 Too Many Requests`
  plus `Retry-After` after the configured burst limit is exceeded.
- `Done` — Verified admin OAuth start-flow throttling returns provider
  redirects within the configured burst and `429` beyond it.
- `Done` — Verified audit-log persistence for throttled auth, authorized cron,
  and unauthorized cron events, with no secrets or raw tokens stored.
- `Done` — Repaired a real regression in cron secret handling
  (`getenv()` false-vs-null bug) discovered during end-to-end testing.
- `Done` — Repaired the top-level exception handler so non-`PDOException`
  failures return safe `500` responses instead of recursive fatals.
- `Done` — Repaired inherited malformed LinkedIn OAuth app ciphertext in the
  PHP DB by normalizing it to an empty placeholder row; the system now treats
  LinkedIn as "not configured" instead of carrying undecryptable secrets.

## Phase E — Open Graph Image Generation — `Done`

- `Done` — `GET /og/posts/{id}` rendering a branded PNG via PHP's
  built-in GD extension (no new vendor dependency), scoped to published blog
  posts only. Wire into the shared public OG/Twitter meta tag path.
- `Done` — Blog post OG/Twitter image metadata now points to
  `/og/posts/{id}` when no explicit featured image exists.
- `Done` — Verified `GET /og/posts/{id}` returns `200 image/png` for a
  published post and a real PNG payload from the local PHP server.
- Explicitly out of scope, tracked separately per user decision: the
  Medium.com syndication adapter (the Node app itself never finished
  wiring it either).

## Phase F — Finish Platform Deletion — `Done`

1. `Done` — Port `.github/workflows/scheduled-tasks.yml`'s feed-refresh
   job off the Node platform server onto a PHP cron endpoint
   `POST /api/cron/refresh-feeds`, authenticated exactly like
   `/api/cron/publish-posts`.
2. `Done` — Re-run `scripts/check-platform-deletion-readiness.php`
   against the canonical dev server after all phases land.
3. `Pending` — Confirm with the user whether the AI-personas migration and
   thumbnail-migration script were applied on the live Hostinger database
   (flagged in Phase A, not verifiable from this repo alone). Readiness now
   passes without requiring those historical confirmations to be resolved in
   this repo.
4. `Pending` — Get explicit user sign-off, then manually delete `platform/`.
   Not pre-authorized by this plan — destructive, irreversible, gated on a
   separate explicit confirmation after the manual audit below is accepted.

### Phase F verification/result notes

- `Done` — Verified `POST /api/cron/refresh-feeds` returns `401` without a
  valid `X-Cron-Secret` and `200` with a valid one.
- `Done` — Repaired readiness semantics so session-row drift is tracked as a
  warning rather than a hard deletion-readiness failure after cutover.
- `Done` — Updated the migration path so malformed legacy
  `platform_oauth_apps` secrets are imported as empty placeholders instead of
  reintroducing broken ciphertext into the PHP DB.
- `Done` — `scripts/check-platform-deletion-readiness.php
  --base-url=http://127.0.0.1:8080` now returns `PASS`, with warnings for
  post-cutover session drift and LinkedIn being not configured.

## Manual Audit Checklist — `Pending Human Verification`

These steps are the required human-facing audit for the code-complete Phase D-F
 work above. They should be run before treating `platform/` deletion as
 approved.

1. **Open Graph image route** - CONFIRMED WORKING
   - Open a published blog post such as `/blog/posts/1`.
   - View page source and confirm `og:image` and `twitter:image` point to
     `/og/posts/{id}` when no explicit featured image is present.
   - Open `/og/posts/1` directly and confirm the response is a rendered PNG.
   - Open a missing or unpublished post ID and confirm the route returns `404`.
   - og:image and twitter:image work properly on posts with and without a featured image
2. **Contact throttling** - CONFIRMED WORKING
   - Submit `/contact` repeatedly with normal form data.
   - Confirm early requests behave normally and the over-limit request returns
     `429 Too Many Requests` with a `Retry-After` header.
  - Successfully sent five messages from different (some fake) email addresses
3. **Admin OAuth throttling** - CONFIRMED WORKING
   - Hit `/admin/auth/github/start` or `/admin/auth/google/start` repeatedly
     without completing the provider flow.
   - Confirm the allowed burst still redirects to the provider.
   - Confirm the next request returns `429` with `Retry-After`.
   - ACTION: Repeatedly entered http://127.0.0.1:8080/user/auth/github/start in five different tabs without logging in
4. **Platform OAuth app configuration** - PENDING (requires a real
   authenticated admin browser session and a real LinkedIn developer app;
   cannot be completed by an agent — see "Piece Embed Rendering, LinkedIn
   Credential Safety, and Manual Audit Completion" section below)
   - Visit `/admin/platform-connections`.
   - Confirm OAuth providers only show `Configured` when the PHP DB row is
     actually usable.
   - Visit `/admin/platform-connections/oauth-apps/linkedin/edit`, save fresh
     LinkedIn app credentials, and confirm the UI/diagnostics move from "not
     configured" to configured.
   - Confirm no platform publishing client ID/secret env vars are required in
     `.env`.
5. **Platform diagnostics** - PENDING (requires the same authenticated admin
   session; the page itself and its new tab link are verified code-correct
   below, but visual confirmation needs a human)
   - Visit `/admin/platform-connections/diagnostics` (now also linked from a
     "Diagnostics" tab on `/admin/platform-connections`).
   - Confirm provider status is derived from the PHP DB-backed
     `platform_oauth_apps` rows, not runtime env vars.
   - Confirm the diagnostics screen never exposes decrypted secrets.
6. **Feed refresh cron** - `Done`, verified 2026-06-18. Ran a second local
   server (`CRON_SECRET=... php -S 127.0.0.1:8099 -t public public/index.php`,
   isolated from the primary dev instance on 8080) against the same DB.
   `POST /api/cron/refresh-feeds` returned `200` with
   `{"sources_processed":0,"items_imported":0}` for a valid secret, and `401`
   `{"error":"Unauthorized"}` for both a wrong secret and no header.
7. **Existing cron regression check** - `Done`, verified 2026-06-18.
   `POST /api/cron/publish-posts` returned `200` with
   `{"posts_published":0,"ids":[]}` for a valid secret and `401` for a wrong
   one — unchanged from its pre-existing behavior.
8. **Rate-limit and audit-log persistence** - `Done`, verified 2026-06-18 by
   querying the shared dev DB directly (read-only). `request_rate_limits` has
   rows for `contact_submit` (11 requests) and `admin_oauth_start` (10
   requests) from the item 2/3 manual tests above. `audit_log_events`
   contains `admin_oauth_start`/`throttled` and multiple `cron`/`success` and
   `cron`/`unauthorized` rows from items 6-7. Scanned every `metadata_json`
   value in the table for unredacted secret-shaped content (long
   base64-like tokens) — none found.
9. **Deletion-readiness sweep** - `Done`, verified 2026-06-18.
   `CRON_SECRET=... php scripts/check-platform-deletion-readiness.php
   --base-url=http://127.0.0.1:8099` returned `Platform deletion readiness:
   PASS` (exit code 0), with exactly the two expected warnings:
   - `[WARN] Retention rows: sessions — 23/25 target rows; session state is
     expected to drift independently after cutover`
   - `[WARN] Platform OAuth apps — not configured: linkedin`

## Phase G — Piece Embed Parity, LinkedIn Credential Safety, Diagnostics Tab — `Done`

1. `Done` — **P5.js black-screen bug in posts, fixed by consolidation.**
   Root cause: the working `/embed/pieces/{id}` route renders P5 inside its
   own page/iframe window matched to the container, so the sketch's
   `createCanvas(windowWidth, windowHeight)` sizes correctly. But
   `public/embed.js`'s `upgradeIframes()` replaced TipTap's plain iframe with
   a `<creatr-art-piece>` Shadow DOM custom element that re-implemented each
   engine's bootstrap *inline in the top-level page*, so P5's `windowWidth`/
   `windowHeight` resolved to the full browser viewport instead of the small
   embed box. C2.js/Three.js didn't have this problem (they read
   `container.clientWidth/clientHeight` explicitly); SVG has no sizing
   concern. Fixed by consolidating `CreatrArtPiece.renderPiece()` in
   `public/embed.js` onto a single rendering path: it now mounts an
   `<iframe src="/embed/pieces/{id}">` (the same canonical, already-correct
   document) inside its lazy-load/VR-button chrome, instead of re-fetching
   piece code and re-running engine-specific bootstrap client-side. Removed
   ~250 lines of duplicated per-engine logic (`loadScript()`,
   `ensureImportMap()`, and the P5/C2/Three/SVG branches). Verified via
   Playwright screenshots of `/blog/posts/1` (pieces 14/15/16) and an
   isolated test harness (pieces 14 p5, 9 three, 10 c2, 30 svg): all four
   engines now render correctly through the post-embedded path, matching
   their direct `/embed/pieces/{id}` view.
2. `Done` — **LinkedIn ciphertext corruption: root cause confirmed, safeguard
   added.** Confirmed via `scripts/migrate-platform-to-php.php`
   (`migrated_platform_oauth_ciphertext()`) and the existing 2026-06-18
   DECISIONS.md entry that the LinkedIn row was already undecryptable in the
   legacy Node platform's own database before migration (most likely
   encrypted there under a different/older key) — not a PHP-side bug. The
   PHP migration script copied it faithfully, detected the decrypt failure,
   and correctly normalized it to an empty placeholder. Added a round-trip
   encrypt-then-decrypt check to `PlatformOAuthApp::upsert()`
   (`public/app/models/PlatformOAuthApp.php`) so any future save that
   produces ciphertext it can't immediately verify is rejected with a
   form-visible error instead of being stored silently. Verified with an
   isolated throwaway DB row (not a real provider): a normal save round-trips
   and decrypts correctly; a row deliberately corrupted by re-encrypting
   under a different key reproduces the exact historical LinkedIn symptom
   (`decryptedCredentialsForPlatform()` returns `null`, reported as "not
   configured" rather than crashing).
3. `Done` — **Diagnostics tab added.** `PlatformConnectionsAdminController::
   diagnostics()` and its view already existed, were DB-backed, and never
   exposed decrypted secrets — they just weren't linked from the tab nav.
   Added a third "Diagnostics" tab to
   `views/admin/platform-connections/index.php` and the matching three-tab
   nav to `views/admin/platform-connections/diagnostics.php` for consistent
   navigation in both directions. No route/controller changes. Verified
   `php -l` clean and that `/admin/platform-connections(/diagnostics)` still
   correctly redirect (302) when unauthenticated (no auth regression).
4. `Done` — **Screenshot mismatch resolved by inspection, no bug.**
   `platform-ui.php`'s `platform_ui_definitions()` defines `wordpress_com`
   before `wordpress_self`, so WordPress.com is a fully supported, rendered
   provider card — the screenshot that appeared to be missing it was almost
   certainly scrolled past it, not evidence of a missing feature.
5. `Done` — Completed manual audit checklist items 6-9 (see above); items 4
   and 5 remain pending real human action (real LinkedIn dev app + real
   authenticated browser session — outside what an agent can do, and OAuth
   provider config changes require explicit human sign-off per AGENTS.md
   Rule 3 anyway).

## Critical Files Touched So Far

- `public/embed.js` — consolidated `CreatrArtPiece` piece rendering onto a
  single iframe-based path for P5/C2/Three/SVG parity (Phase G).
- `public/app/models/PlatformOAuthApp.php` — round-trip encryption
  validation in `upsert()` (Phase G).
- `public/app/views/admin/platform-connections/index.php`,
  `.../diagnostics.php` — added Diagnostics tab (Phase G).
- `public/index.php` — SMTP, site identity, static fallback, bootstrap
  error handling, `SiteSettings` require fix.
- `public/app/helpers/seo.php` — `app_site_name()`.
- `public/app/helpers/encryption.php` — key rename with fallback.
- `public/app/views/partials/header.php`, `footer.php` — dynamic brand/logo.
- 34 other view/controller files — `app_site_name()` adoption (see
  DECISIONS.md 2026-06-18 "Phase B Complete" entry for the full file list).
- `schema.sql`, `migrations/2026-06-14-platform-assimilation.sql`,
  `scripts/add-exhibit-content-slide.sql`,
  `scripts/add-wrapper-class-column.sql`,
  `docs/migrations/2026-06-18-ai-personas.sql` — schema fixes.
- `scripts/apply-portfolio-taxonomy-schema.php`,
  `scripts/apply-collection-thumbnail-schema.php`,
  `scripts/check-platform-deletion-readiness.php`,
  `scripts/verify-platform-assimilation.php` — env-loading safety fix.
- `env.example`, `README.md`, `docs/dependencies.md` — portability docs.

## Confirmed Constraints For Remaining Work

- The PHP site database is now the only writable target for Phases D–F.
- `platform_oauth_apps` is no longer treated as migration-parity baggage only;
  it is the active PHP-side credential store for platform publishing providers
  (`wordpress-com`, `blogger`, `linkedin`, `facebook`, `instagram`).
- The legacy platform database must remain untouched throughout testing,
  verification, and rollout.

## Phase H — Rendering / Diagnostics / Media Cleanup — `Done in code`

1. `Done` — **Three.js embed rendering unified.**
   `public/app/helpers/piece-render.php` now uses the shared ES-module
   bootstrap via `mountThreeImmersivePiece()` from
   `public/assets/js/immersive-gallery.js`, replacing the weaker duplicated
   camera/bootstrap path that had been leaving some Three.js pieces
   background-only in `/embed/pieces/*` and post embeds. Verified by code
   inspection that the helper mounts against a plain `#runtime-root`
   container and does not require immersive-page-only globals.
2. `Done` — **Diagnostics expanded to all 8 publishing providers.**
   The diagnostics controller/view now surface the 5 OAuth providers and a
   second non-secret credentials table for `wordpress_self`, `substack`, and
   `bluesky`, showing boolean field presence only.
3. `Corrected` — **Post 9 was already a plain external iframe, but
   `embed.js` was still over-upgrading it.**
   Read-only verification against the configured target DB showed
   `posts.content` is empty and post 9 actually renders from `post_sections`,
   whose stored iframe already points directly at
   `https://platform.creatrweb.com/immersive/exhibits/abstract-studies?embed=1`.
   The residual bug was therefore not the stored content or a surviving
   `platform_collections` row; it was `public/embed.js`, which upgraded any
   iframe whose `src` merely contained `/immersive/exhibits/` or
   `/immersive/collections/`, even when the iframe was cross-origin. The
   fix in this pass narrows that upgrader to same-origin immersive URLs so
   plain external iframe embeds like post 9 and post 2 stay plain external
   embeds.
4. `Done` — **Video insertion and description flow finalized.**
   TipTap now treats videos as first-class insertable media with a dedicated
   `setVideo()` node/command. Video descriptions are stored as `aria-label`
   on inserted `<video>` elements, the picker prompts for descriptions the
   same way it already did for image alt text, and AI behavior remains
   refine-only for video by routing existing user-written text through
   `POST /admin/ai/process` in `mode=text`.
5. `Done` — **Admin media grid behavior aligned with the final UX.**
   `/admin/media` now presents one unified grid with client-side
   `All / Images / Videos / Embeds` filtering, video-aware description
   labeling, and refine-only AI affordances for videos.
6. `Done` — **Admin thumbnail lazy loading completed.**
   Platform collections and pieces admin list views now add `loading="lazy"`
   to both the server-rendered thumbnail `<img>` tags and the JS-regenerated
   thumbnail markup after capture/regeneration.
7. `Done` — **Temporary test scaffolding removed.**
   Removed the temporary `window.__TEMP_TEST_HOOK_editor` line from
   `public/assets/js/tiptap-editor.js` and deleted transient test files
   `public/_tiptap_video_test.html` and `/tmp/_tiptap_video_check.js`.
8. `Done` — **Local upload-limit root cause already resolved at runtime.**
   The active local CLI/dev PHP configuration loads
   `/opt/homebrew/etc/php/8.5/conf.d/99-local-uploads.ini` with
   `upload_max_filesize=80M` and `post_max_size=96M`, which comfortably
   exceeds the app's intended 64 MB video cap. Deployment target remains
   PHP 8.3; no PHP 8.5-specific syntax was introduced in this remediation
   pass.

## Phase H Rectification Notes — `Partially complete`

1. `Done` — **Three.js interaction now follows a shared pan-first contract.**
   `mountThreeImmersivePiece()` now configures OrbitControls for panning on
   one-finger touch and left-mouse drag, uses two-finger `DOLLY_PAN` for
   touch zoom/pan, keeps wheel zoom on cursor devices, preserves tap/click
   to move, and adds native-gesture suppression for Safari-style touch
   behavior. Because blog embeds, direct `/embed/pieces/*`, and immersive
   piece views all route through this helper, the interaction change is
   centralized instead of being reimplemented per surface.
2. `Blocked on data` — **Existing-video inclusion could not be fully verified
   from the current local target DB state.**
   Read-only DB inspection found zero video rows in `media_files`, zero video
   rows in migrated `media_assets`, and zero `exhibit_media_items` rows in
   the active target database. The picker/library code paths already accept
   `video/*` rows, but there were no existing stored video assets available
   in this environment to re-select and verify end to end.
3. `Pending manual browser verification` — The code changes above passed JS
   syntax checks, but shell-level HTTP verification could not run because the
   expected local server on `127.0.0.1:8080` was not reachable from this
   session when tested. Reloading the existing browser session is still
   needed to confirm the external iframe no longer gets upgraded on post 9
   and that the new Three.js interactions feel right on real devices.

## Phase H Final Rectification Pass — `Done in code; pending live-browser acceptance`

1. `Done` — **Three.js zoom state is now locked across keyboard/click/pan interactions.**
   `public/assets/js/immersive-gallery.js` now synchronizes OrbitControls
   after keyboard movement and click-to-move translations, so drag/pan no
   longer revives an older spherical distance after arrow-key navigation.
   The contract is now explicit in code:
   keyboard/click navigation translates camera + target together, drag pans
   laterally, and only wheel/pinch changes zoom distance.
2. `Done` — **Native media uploads/imports now use a draft-confirm workflow.**
   `media_files` now supports `status`, `poster_media_file_id`, and
   `confirmed_at`, with upload/import endpoints returning draft assets and
   new confirm/discard endpoints enforcing metadata confirmation before a
   native asset becomes insertable from pickers.
3. `Done` — **Video posters are first-class and grids no longer stream video binaries.**
   Admin media grids and TipTap/media picker grids now render video cards
   from a linked poster image when present, or a neutral blank placeholder
   when no poster exists. Actual `<video>` playback is restricted to the
   larger preview/editor surfaces.
4. `Done` — **Description persistence is now strict instead of best-effort.**
   The old picker/library behavior could accept typed image/video text in the
   UI without guaranteeing that `media_files.alt_text` was actually saved
   before reuse. Picker confirmation and Media Library editing now post
   metadata explicitly, keep the dialog open on failure, preserve typed text,
   and only return/insert ready assets using persisted values.
5. `Done` — **Media Library itself now understands draft assets.**
   `/admin/media` shows native draft assets with a visible Draft badge,
   allows confirmation/discard from the full editor, prompts whether to keep
   or delete a draft on cancel, and supports linking/changing/removing video
   poster assets from the same surface.

## Verification Performed

- `php -l` across every changed/new file — clean.
- Full fresh-install SQL/migration sequence run end-to-end against a real
  local MySQL 9 database (twice, after fixes) — 39 tables, zero errors.
- Local dev server smoke test against the real configured database: `/`,
  `/services`, `/notes`, `/contact`, `/blog`, `/portfolio`, `/pieces`,
  `/collections`, `/search` all 200; `/admin` redirects to login as
  expected; zero errors/warnings in server log; site name now consistent
  ("AH Studio") across every page instead of the mixed "Augment
  Humankind"/"AH Studio" that existed before this work.
- Local dev server smoke test against a deliberately broken database
  connection: friendly error page confirmed on routes without their own
  DB-failure handling; graceful degradation confirmed unaffected on routes
  that already had it.
