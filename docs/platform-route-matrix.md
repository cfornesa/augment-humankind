# Platform Route And Functionality Matrix

Status: reconciled 2026-06-18. This doc was last edited in commit `0ff7093`
(2026-06-15), the same day `platform_exhibits`/`/exhibits` were renamed to
`platform_collections`/`/collections`. All "Needs Repair" rows below
described routes/names that either no longer exist or were already fixed
under the new name — re-verified directly against `router.php`, controllers,
and JS on 2026-06-18 (see `docs/platform-gap-analysis.md`'s "Verified Fixed
2026-06-18" section for the per-item evidence). Every row is now Done.

Status values:

- `implemented in PHP` — PHP serves the route or feature directly.
- `implemented with redirect` — PHP returns a permanent redirect to the new canonical route.
- `intentionally replaced by PHP admin route` — original platform owner/admin surface is represented by PHP admin workflows.
- `implemented but needs repair` — PHP has a route or surface, but manual
  browser testing found a parity bug that blocks deletion readiness.
- `missing and required before deletion` — must be implemented before `platform/` can be removed.
- `not needed after deletion` — intentionally excluded with rationale.

## Public Browser Routes

| Platform surface | PHP status | PHP route / note |
|---|---|---|
| `/` platform feed | implemented in PHP | Reconciled as `/blog`; current PHP homepage remains `/`. |
| `/posts/{id}` | implemented with redirect | `/blog/posts/{mapped-id}`. |
| `/categories` | implemented in PHP | `/blog/categories`. |
| `/categories/{slug}` | implemented with redirect | `/blog/category/{slug}` for imported blog categories. |
| `/feeds` | implemented with redirect | `/blog/feeds`. |
| `/p/{slug}` | implemented with redirect | Top-level PHP page when migrated page exists. Source currently had 0 pages. |
| `/pieces` | implemented in PHP | Canonical PHP route remains `/pieces`. |
| `/pieces/{id}` | implemented in PHP | Numeric IDs are canonical because platform `art_pieces` has no slug column. |
| `/embed/posts/{id}` | implemented in PHP | Returns embeddable HTML for a published post. Canonical browser route remains `/blog/posts/{id}`. |
| `/embed/pieces/{id}` | implemented in PHP | Returns embeddable HTML for the current or requested version. `public/embed.js`'s `CreatrArtPiece.loadAndRender()` fetches `/embed/pieces/{id}/data` and shows a visible error state on failure — no stuck-placeholder defect found. |
| `/embed/pieces/{id}/data` | implemented in PHP | Returns current/requested version JSON. |
| `/immersive/pieces/{id}` | implemented in PHP | Renders a full-page immersive piece view. Native Three.js and A-Frame pieces include site-level viewer controls for zoom, directional camera movement, keyboard movement, pointer/touch gestures, and tap/click-to-move where supported. A-Frame's built-in enter-VR/fullscreen button is suppressed so the site fullscreen control is the only fullscreen control. VR hrefs are built from the same `id` the data endpoint returns; 404s only when the piece genuinely doesn't exist. |
| `/immersive/images/{encodedRef}` | implemented in PHP | Decodes the platform base64url image reference and renders the image in the gallery wall scene. |
| `/immersive/collections/{slug}` | implemented in PHP | Renders a multi-frame collection wall (`ImmersiveController::collection()`). Migrated platform collections are surfaced via `/collections`, `/admin/platform-collections`, and the TipTap "Pieces/Collections" picker. `/immersive/exhibits/{slug}` 301-redirects here for legacy links (`ImmersiveController::redirectCollection()`); there is no separate public `/exhibits` route. |

## Feed And API Routes

| Platform surface | PHP status | PHP route / note |
|---|---|---|
| `/feed.xml`, `/atom` | implemented in PHP | Atom 1.0. |
| `/feed.json`, `/jsonfeed` | implemented in PHP | JSON Feed 1.1. |
| `/export.json`, `/export/json` | implemented in PHP | JSON Feed 1.1 retained for PHP clients; mf2 is `/feeds/mf2`. |
| `/feeds/mf2` | implemented in PHP | mf2 JSON export. |
| `/api/feeds` | implemented in PHP | Feed catalog JSON. |
| `/api/feeds/atom` | implemented in PHP | Atom payload. |
| `/api/feeds/json` | implemented in PHP | JSON Feed payload. |
| `/api/feeds/mf2` | implemented in PHP | mf2 payload. |
| `/api/categories` | implemented in PHP | Blog taxonomy JSON. |
| `/api/categories/{slug}` | implemented in PHP | Single category JSON. |
| `/api/categories/{slug}/posts` | implemented in PHP | Posts in category JSON. |
| `/api/posts` | implemented in PHP | Published post archive JSON. |
| `/api/posts/{id}` | implemented in PHP | Single post JSON. |
| `/api/p/{slug}` | implemented in PHP | Published page JSON when a migrated/public page exists. |
| `/api/p/{slug}/feeds/atom` | implemented in PHP | Page Atom feed. |
| `/api/p/{slug}/feeds/json` | implemented in PHP | Page JSON Feed. |
| `/api/art-pieces` | implemented in PHP | Art piece JSON. |
| `/api/art-pieces/{id}` | implemented in PHP | Single art piece JSON. |
| `/api/art-pieces/{id}/versions` | implemented in PHP | Version list JSON. |
| `/api/collections` | implemented in PHP | Migrated platform collection JSON (`ApiController::collections`). |
| `/api/collections/{slug}` | implemented in PHP | Single collection JSON (`ApiController::collection`). |
| `/api/collections/{slug}/items` | implemented in PHP | Collection items JSON (`ApiController::collectionItems`). |
| `/api/exhibits/{slug}` | implemented with redirect | Redirects to `/api/collections/{slug}` (`ApiController::redirectCollection`) for legacy clients. Note: `/api/exhibits/{slug}/comments` is a *different*, unrelated route — the native portfolio-exhibits comment API, not migrated platform data. |
| `/api/media-assets/{id}` | implemented in PHP | Streams stored migrated media blobs used by immersive collection items. |
| `/api/media/{filename}` (platform `/media/:fileName`) | implemented in PHP | Public, unauthenticated. Streams `media_assets.file_data` by `filename` with `Cache-Control: public, max-age=31536000, immutable`. Restores migrated `/api/media/{uuid}.ext` links embedded in `site_settings.logo_url`/`logo_dark_url` and post content/featured images. |
| `/api/media/{filename}/collections` | implemented in PHP | Returns public metadata for collections containing the migrated media asset (`ApiController::mediaAssetCollections`). |
| `/api/runtimes/{path}` | implemented in PHP | Legacy platform runtime URLs redirect to documented CDN runtime assets for p5, c2, and Three.js/OrbitControls so old embeds do not depend on `platform/`. |

## Owner/Admin Functionality

| Platform function | PHP status | PHP route / note |
|---|---|---|
| Posts, drafts, scheduled posts | implemented in PHP | `/admin/posts`. |
| Comments/reactions moderation | implemented in PHP | `/admin/comments`. |
| Feed sources and pending imports | implemented in PHP | Pending imports are stored in `feed_import_items`; approval creates draft posts from full item data. |
| Site settings/assets/media | implemented in PHP | `/admin/site-identity`, `/admin/media` (unified library — merges native `media_files` uploads with the 102 migrated `media_assets`, with full metadata editing and soft/hard delete CRUD parity supported via `/admin/media/asset/{id}/update|trash|destroy` and `/admin/trash`). |
| User profiles and AI settings | implemented in PHP | `/admin/user-profiles`. |
| Platform connections and syndication | implemented in PHP | Tokens are encrypted on save; publish results upsert by post/connection. Adapter dry-run tests remain required before real publishing. |
| Art pieces and versions | implemented in PHP | `/pieces`, `/embed/pieces`, and `/immersive/pieces` use the PHP piece renderer, and `/admin/pieces/{id}/edit` supports code tabs. TipTap has a dedicated "Pieces/Collections" `<dialog>` picker/inserter with thumbnails (`initPiecePicker()` in `tiptap-editor.js`, backed by `GET /admin/pieces/library`). |
| Platform collections (migrated platform exhibits) | implemented in PHP | Public immersive collection route renders migrated collection items; collections are surfaced consistently at `/collections`, `/admin/platform-collections`, and the TipTap picker's Collections tab. |
| Admin dashboard | implemented in PHP | Dashboard aggregate counts are schema-aware and tolerate tables without `deleted_at` columns. |

## Deletion Readiness Report

Canonical local server command:

```sh
php -S 127.0.0.1:8080 -t public public/index.php
```

Required readiness command:

```sh
php scripts/check-platform-deletion-readiness.php --base-url=http://127.0.0.1:8080
```

Current status (reconciled 2026-06-18 — see `docs/platform-gap-analysis.md`
for full per-item verification):

- Done — All five previously-claimed "Needs Repair" parity gaps (stuck embed
  placeholders, VR 404s, missing TipTap picker, `window.prompt()` flows,
  collection surfacing) were re-verified directly against current code and
  are confirmed fixed. They were never actually outstanding as of this
  reconciliation — the prior "Needs Repair" status was stale documentation,
  not a real defect (see root-cause note at the top of this file).
- Done — The readiness command passed against the canonical local PHP server, including all HTTP checks (Piece data routes, post embeds, runtime compatibility redirects, immersive image routes, media exhibit lookup, index, blog, pages, feed exports, and collections all returned expected status codes).
- Done — Rollback-only feed approval check proved imported feed items
  become real draft posts with title/content/source metadata.
- Done — Rollback-only syndication check proved token encryption,
  refresh persistence path, and post/connection upsert recording.
- **Not actually Done** — The readiness script's static check proves the PHP
  *application code* doesn't depend on `platform/`, but
  `.github/workflows/scheduled-tasks.yml`'s feed-refresh job still shells out
  to `platform/scripts/scheduled-feed-refresh.sh`, which calls the Node
  server. This is a real, current blocker on deletion, tracked separately in
  the CMS-shell remediation plan (Phase F) — not fixed by this doc
  reconciliation pass.
- Done — Retention check proved nonzero source platform data has mapped
  PHP target rows or explicit report entries.
- Done — AI vendor keys decrypt with the configured PHP/platform encryption
  key, so they do not need reinsertion.
- Done — Migrated platform connection rows are retained and usable. Existing
  migrated tokens use the adapters' preserved legacy-token fallback; newly
  saved/refreshed tokens use platform-compatible AES-256-GCM encryption.
- Done — Manual admin testing pass (rectification round): all 15 admin nav
  pages (`/admin`, pages, posts, comments, feed-sources, site-identity,
  user-profiles, platform-connections, artworks, pieces, categories,
  exhibits, media, trash, navigation) return 200 with no fatal errors or
  warnings, and migrated data renders (102 media assets, 60 pieces/123
  versions, 2 platform connections, 7 AI settings/keys, 0 feed
  sources/pending matching empty source). `php
  scripts/check-platform-deletion-readiness.php` passes against the
  canonical local server.
- Unconfirmed — Whether the 2026-06-18 AI Personas SQL migration and a
  re-run of the thumbnail-migration script were ever applied to the live
  Hostinger production database. Operational items, not verifiable from this
  repo; needs the site owner to confirm.
- Remaining before `platform/` can actually be deleted: port the GitHub
  Actions feed-refresh cron off the Node server (Phase F), confirm the two
  unconfirmed production-migration items above, then get explicit
  owner sign-off before manual deletion.
