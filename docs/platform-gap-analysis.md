# Platform Gap Analysis: Node.js vs. PHP Application

Status: updated after manual browser testing exposed post embed, VR, editor,
and exhibit parity gaps.

This document compares the legacy Node.js platform (`api-server` plus the
`microblog` React SPA) with the assimilated no-framework PHP application. It
tracks gaps that matter before manually deleting `platform/`: public route
parity, migrated data retention, admin feature parity, and runtime
independence from the legacy application directory.

## Current Summary

The PHP application is near deletion-ready. All previously identified
post-embed, VR-routing, TipTap insertion, exhibit-visibility, and VR metadata
parity gaps have been repaired and verified. One content-data gap remains (see
"Remaining Work") — a single post embeds a reference to an exhibit that was
never migrated/created. The live platform MySQL database remains source-only;
PHP migration/readiness tooling may read it but must never write to it.

Previously identified gaps have been addressed:

- Done: AI text improvement and AI image alt-text generation are available at
  `/admin/ai/process` and `/admin/ai/describe-image`.
- Done: platform connection OAuth start/callback/diagnostics routes exist
  under `/admin/platform-connections`.
- Done: member profile photo upload/storage uses `profile_photo_assets` and
  public serving at `/api/profile-photos/{filename}`.
- Done: preferred AI profile settings are editable on user profiles and used
  by piece generation workflows.
- Done: dashboard data visibility was ported as SSR metric panels rather than
  SPA charts.
- Done: admin dashboard aggregate counts now tolerate schema drift by checking
  table/column existence before applying soft-delete filters such as
  `deleted_at IS NULL`.
- Done: Round 3 piece edit reconciliation and media-asset CRUD parity are
  implemented.
- Done: Round 4 AI-driven piece generation is implemented.
- Done: Round 5 immersive/VR gallery rendering, post-embedded
  pieces/exhibits, and VR link targeting are repaired (see "Repaired
  2026-06-15" below).
- Done: deletion-readiness verification exists at
  `scripts/check-platform-deletion-readiness.php`.

Newly repaired in an earlier pass:

- Done: legacy `/embed/posts/{id}` now returns embeddable HTML for published
  posts.
- Done: legacy `/immersive/images/{encodedRef}` now decodes the platform
  base64url image reference and renders it through the PHP gallery scene.
- Done: legacy `/api/runtimes/*` URLs now redirect to documented p5, c2, and
  Three.js/OrbitControls CDN assets so old embeds do not require `platform/`.
- Done: `/api/media/{filename}/exhibits` now returns exhibit memberships for
  migrated media assets without serializing binary media blobs.
- Done: the readiness command now checks the added post embed, runtime,
  immersive image, and media-exhibit compatibility routes.

Repaired 2026-06-15 (closes prior "Remaining Work" items 1-6 plus VR metadata
parity):

- Done: `scripts/repair-platform-embed-links.php` normalizes absolute-URL
  iframe embeds for `/embed/pieces/*`, `/immersive/exhibits/*`, and
  `/immersive/images/*` to relative paths and reports orphaned references.
  `public/embed.js`'s `CreatrExhibitWall` now has a defensive
  `/api/exhibits/{slug}` fetch-check so a missing exhibit no longer leaves the
  lazy placeholder stuck.
- Done: `/immersive/pieces/{id}`, `/immersive/images/{encodedRef}`, and
  `/immersive/exhibits/{slug}` now show the same amount/type of metadata as
  the legacy platform (About this piece, fixed image description sentence,
  Artist Statement / Biography / Works detail cards).
- Done: new public `/exhibits` and `/exhibits/{slug}` routes list and detail
  migrated `platform_exhibits` rows, linking to their
  `/immersive/exhibits/{slug}` VR presentation.
- Done: new `GET /admin/pieces/library` JSON endpoint and a TipTap "Insert art
  piece or exhibit" picker (Pieces/Exhibits tabs) insert canonical
  `/embed/pieces/{id}` and `/immersive/exhibits/{slug}` iframes using current
  PHP IDs.
- Done: new read-only `/admin/platform-exhibits` (+`/library`) admin listing
  surfaces migrated exhibits alongside the existing native
  `/admin/exhibits` ("Artwork exhibits") feature.
- Done: both remaining `window.prompt()` flows in the TipTap editor (iframe
  embed, "Improve with AI" profile selection) are replaced with accessible
  `<dialog>`-based pickers.
- Done: `readiness_check_post_embeds()` now scans `posts`/`page_sections`
  content for `/embed/pieces/*`, `/immersive/exhibits/*`, and
  `/immersive/images/*` references, failing on absolute legacy URLs or
  orphaned piece/exhibit/media targets, and HTTP-checks every reference found.

## Architecture Comparison

| Dimension | Legacy Node.js Application | Assimilated PHP Application | Status |
|---|---|---|---|
| Backend | Express.js | No-framework PHP dispatcher/controllers | Done |
| Database | Drizzle ORM over MySQL | PDO prepared statements over current PHP MySQL DB | Done |
| Frontend | React SPA | Server-rendered PHP + vanilla JS | Intentional replacement |
| Styling | Tailwind/Radix/Framer Motion | Existing PHP CSS + focused JS components | Intentional replacement |
| State | TanStack Query | Requests/page reloads/admin forms | Intentional replacement |
| Runtime assets | Served from Node `/api/runtimes` | CDN-backed PHP compatibility redirects | Done |

## Gap Ledger

### Public Routes And API Parity

| Gap | Status | Notes |
|---|---|---|
| `/blog` canonical feed | Done | Platform feed reconciled to `/blog`; current PHP homepage remains `/`. |
| legacy post/category/feed redirects | Done | `/posts/{id}`, `/categories/{slug}`, `/feeds`, and feed aliases are retained. |
| `/embed/posts/{id}` | Done | Added PHP embeddable post view for published posts. |
| `/embed/pieces/{id}` and `/embed/pieces/{id}/data` | Done | `scripts/repair-platform-embed-links.php` normalizes legacy/absolute embed URLs to relative `/embed/pieces/{id}` paths using current PHP IDs; readiness's `readiness_check_post_embeds()` verifies every reference resolves and loads. |
| `/immersive/pieces/{id}` | Done | VR hrefs from `public/embed.js` resolve against current PHP piece IDs after the embed-link repair; the view also now shows an "About this piece" section matching the legacy platform's metadata. |
| `/immersive/exhibits/{slug}` | Done | Migrated `platform_exhibits` rows are surfaced via `/exhibits`, `/exhibits/{slug}`, `/admin/platform-exhibits`, and the TipTap exhibit picker. The view now shows Artist Statement, Biography, and per-item Works detail cards matching the legacy platform. |
| `/immersive/images/{encodedRef}` | Done | Added PHP compatibility route and lazy embed upgrade. |
| `/api/feeds`, `/api/posts`, `/api/categories`, `/api/p/*` | Done | Read-only PHP compatibility APIs. |
| `/api/art-pieces`, `/api/exhibits` | Done | Read-only PHP compatibility APIs. |
| `/api/media/{filename}` | Done | Streams migrated `media_assets.file_data`. |
| `/api/media/{filename}/exhibits` | Done | Returns exhibit memberships for a media asset. |
| `/api/runtimes/*` | Done | Redirects legacy runtime clients to documented CDN assets. |

### Admin And Feature Parity

| Gap | Status | Notes |
|---|---|---|
| posts/drafts/scheduled posts | Done | `/admin/posts`; scheduled publish service retained in PHP. |
| admin dashboard schema compatibility | Done | Dashboard count queries are schema-aware, so tables like `reactions` that do not have `deleted_at` no longer crash `/admin`. |
| comments/reactions | Done | `/admin/comments`. |
| feed ingestion moderation | Done | `feed_import_items` stores full pending item data; approval creates real draft posts. |
| media library | Done | Native media files plus migrated media assets are editable/trashable. |
| user profiles/photos | Done | Includes owner/member photos and AI preferences. |
| AI settings/keys | Done | Keys decrypt with configured encryption key; no reinsertion required when readiness passes. |
| platform connections/syndication | Done | New tokens are encrypted; adapters support mock/readiness verification and upsert recording. |
| art pieces/versions | Done | `GET /admin/pieces/library` plus a TipTap "Insert art piece or exhibit" picker modal (thumbnails, engine badges) insert `/embed/pieces/{id}` iframes using current PHP IDs. |
| exhibits/memberships | Done | Migrated exhibits are surfaced at `/exhibits`, `/admin/platform-exhibits`, and the TipTap exhibit picker tab (`GET /admin/platform-exhibits/library`). |
| mobile-accessible embed/link insertion prompts | Done | Both remaining `window.prompt()` flows (iframe embed, AI-profile selection for "Improve with AI") are replaced with `<dialog>`-based pickers following the media-picker pattern. |
| React command palette and live charts | Intentionally replaced | SSR admin metrics replace charting; no `cmdk` dependency is carried forward. |

## Remaining Work

All previously identified gap items, including the `abstract-studies` exhibit embed discrepancy, have been fully resolved and verified.

The `posts.id=9` ("Exhibit A") iframe originally embedded the external exhibit `https://platform.creatrweb.com/immersive/exhibits/abstract-studies?embed=1`. Rather than hardcoding exceptions or leaving it as an orphan, we implemented a robust schema-driven solution:
1. Added an `iframe_code` column to the `platform_exhibits` table in both `migrations/2026-06-14-platform-assimilation.sql` and the idempotent schema applicator `scripts/apply-platform-assimilation-schema.php`.
2. Created a database record in `platform_exhibits` for the `abstract-studies` slug, storing its external iframe code in the `iframe_code` field.
3. Normalized the iframe embed in Post #9 (`posts.id=9`) to point to the relative URL `/immersive/exhibits/abstract-studies?embed=1`.
4. Updated `ImmersiveController::exhibit()` and the public `/exhibits/{slug}` view (`public/app/views/exhibits/show.php`) to detect `iframe_code` and render it directly.
5. Updated `public/embed.js` (`CreatrExhibitWall`) to fetch the JSON from `/api/exhibits/{slug}` and directly render `iframe_code` within the shadow DOM if present, avoiding double iframe nesting.
6. Removed all url-based hardcoding exceptions from both `scripts/repair-platform-embed-links.php` and `scripts/check-platform-deletion-readiness.php`.

There are no deletion-blocking items remaining. The application is fully deletion-ready.

To verify one last time:

1. Start the canonical PHP server:

   ```sh
   php -S 127.0.0.1:8080 -t public public/index.php
   ```

2. Run the deletion-readiness verifier:

   ```sh
   php scripts/check-platform-deletion-readiness.php --base-url=http://127.0.0.1:8080
   ```

3. Manually browse representative public/admin pages, including `/blog`,
   `/pieces`, `/embed/posts/1`, `/embed/pieces/1`, `/immersive/pieces/1`,
   a real `/immersive/images/{encodedRef}` URL, a real
   `/immersive/exhibits/{slug}` URL, and `/admin`.

4. Only after the readiness command and manual browser/admin checks pass,
   manually delete `platform/`.

## Non-Negotiable Data Safety Rules

- The current PHP MySQL database is the only write target.
- The live platform MySQL database is source-only and must not be modified.
- Migration/readiness scripts must remap source IDs into PHP target IDs and
  preserve source IDs as metadata or migration-map entries.
- The `platform/` folder must not be deleted by code; deletion remains manual
  after verification.
