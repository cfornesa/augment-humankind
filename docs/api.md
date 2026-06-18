# API Contract

## `GET /contact`

Renders the public contact form for collaboration, hiring, project help,
strategy help, and other inquiries.

The page includes:

- name field
- email field
- optional organization field
- inquiry type field
- message field
- hidden honeypot field
- CSRF token field
- reCAPTCHA v3 token field

## `POST /contact`

Processes the contact form and returns the `/contact` page with either an
inline success message or validation errors.

### Accepted Fields

- `name` — required, 2-120 characters
- `email` — required, valid email address, up to 254 characters
- `organization` — optional, up to 160 characters
- `inquiry_type` — required, one of:
  - `collaboration`
  - `hiring`
  - `project_help`
  - `strategy_help`
  - `other`
- `message` — required, 20-3000 characters
- `csrf_token` — required
- `g-recaptcha-response` — required
- `website` — hidden honeypot, must be empty

### Validation Errors

The handler redisplays the form with safe, user-facing validation messages
when:

- a required field is missing
- a field exceeds its allowed length
- `email` is invalid
- `inquiry_type` is not recognized
- the honeypot field is filled
- the CSRF token is missing or invalid
- reCAPTCHA verification fails, has the wrong action, wrong hostname, or a
  score below the configured threshold
- required email/reCAPTCHA configuration is missing
- SMTP delivery fails

### Success Response

On success, the handler:

- sends a plain-text email through the configured SMTP service
- sets `Reply-To` to the submitter email
- does not store the submission in a database or file
- redisplays `/contact` with an inline success panel

No separate success URL is added.

## Public User Routes

- `GET /user/login` — login form (OAuth buttons: GitHub, Google)
- `GET /user/logout` — ends the current user session and redirects to `/`
- `GET /user/auth/github/start` — begins GitHub OAuth flow
- `GET /user/auth/github/callback` — GitHub OAuth callback
- `GET /user/auth/google/start` — begins Google OAuth flow
- `GET /user/auth/google/callback` — Google OAuth callback
- `GET /user/settings` — profile settings page (requires login; redirects to `/user/login?redirect=...` otherwise)
- `POST /user/settings/profile` — updates the signed-in user's display name, bio, and website
- `POST /user/settings/photo` — uploads a profile photo for the signed-in user. Accepts a `profile_photo` multipart file (JPEG, PNG, GIF, WebP, or AVIF). Stores the binary in `profile_photo_assets` and sets `users.image` to `/api/profile-photos/{filename}`. Redirects to `/user/settings?success=photo` on success or `?error=...` on failure.
- `POST /user/settings/style` — updates the signed-in user's palette, theme, and per-mode color overrides
- `GET /user/[username]` — public profile page for a user. Resolves by `users.username`; falls back to `users.id` lookup if the slug is all digits. Accepts `?show_pieces=all` to display all pieces instead of the default 12.

User photo URLs follow the `/api/profile-photos/[filename]` pattern (served by the platform compatibility API; see below). There is no `/user/register` route — account creation happens through OAuth only.

## Public Blog and Platform Compatibility Routes

The assimilated platform feed is canonical at:

- `GET /blog`
- `GET /blog/posts/[id]`
- `GET /blog/categories`
- `GET /blog/category/[slug]`
- `GET /blog/feeds`
- `GET /search`

`/blog` lists published posts from the PHP target database. Scheduled, draft,
pending, and deleted posts are not public. `/blog/posts/[id]` renders one
published post with its title, rich HTML/plain content, featured image, source
attribution, categories, comments, and reaction count when available.

The legacy platform URL surface is not canonical after assimilation. Public
compatibility URLs issue permanent redirects when a mapped target exists:

- `/posts/[id]` redirects to `/blog/posts/[target-id]`
- `/categories/[slug]` redirects to `/blog/category/[target-slug]` for blog
  categories
- `/feeds` redirects to `/blog/feeds`
- `/p/[slug]` redirects to the reconciled top-level CMS page

Feed aliases remain public and must continue to work for existing clients:

- `GET /feed.xml` — Atom 1.0, all published posts
- `GET /atom` — alias for `/feed.xml`
- `GET /feed.json` — JSON Feed 1.1, all published posts
- `GET /jsonfeed` — alias for `/feed.json`
- `GET /export.json` — JSON Feed 1.1 (Rule 5: format unchanged from pre-assimilation)
- `GET /export/json` — alias for `/export.json`

Enhanced fields added during assimilation:

- Atom feeds now include `<subtitle>` (site description), feed-level `<author>`, `<link rel="self">` / `rel="alternate">`, per-entry `<summary>`, and `<category>` per post category.
- JSON Feed 1.1 now includes `description`, feed-level `authors`, per-item `content_text`, and `tags` (category names).

Additional feed routes:

- `GET /feeds/mf2` — mf2-JSON export (`{"items": [...]}` h-entry array) for all published posts
- `GET /blog/category/{slug}/feed.xml` — Atom 1.0, posts in category
- `GET /blog/category/{slug}/feed.json` — JSON Feed 1.1, posts in category
- `GET /{slug}/feed.xml` — Atom 1.0, single entry for published page
- `GET /{slug}/feed.json` — JSON Feed 1.1, single entry for published page

Legacy category/page feed redirects (301):

- `/categories/{slug}/feed.xml`, `/categories/{slug}/atom`, `/categories/{slug}/feeds/atom` → `/blog/category/{slug}/feed.xml`
- `/categories/{slug}/feed.json`, `/categories/{slug}/jsonfeed`, `/categories/{slug}/feeds/json` → `/blog/category/{slug}/feed.json`
- `/p/{slug}/feed.xml`, `/p/{slug}/atom`, `/p/{slug}/feeds/atom` → `/{slug}/feed.xml`
- `/p/{slug}/feed.json`, `/p/{slug}/jsonfeed`, `/p/{slug}/feeds/json` → `/{slug}/feed.json`

The Atom and JSON Feed endpoints serialize published blog posts from the PHP
target database. `publishDuePosts()` flips `status='scheduled'` to `published` when `scheduled_at` has passed, and overwrites `created_at` to the publish moment.

## Platform Data Migration Contract

The current PHP database is the only write target. It is configured through
`DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS`.

The live platform database is source-only. It is configured through
`PLATFORM_DB_HOST`, `PLATFORM_DB_NAME`, `PLATFORM_DB_USER`,
`PLATFORM_DB_PASS`, optional `PLATFORM_DB_PORT`, and optional
`PLATFORM_DB_SSL`. Migration tooling may read/export platform rows but must
not add, edit, delete, migrate in place, or alter schema in the platform
database.

Migrated platform row identities are remapped in the PHP target database. The
original platform ids are retained in `platform_source_id` columns or migration
mapping metadata so relationships can be rebuilt without forcing target ids.

## Local Development And Deletion Readiness

The canonical development server command for full local testing is:

```sh
php -S 127.0.0.1:8080 -t public public/index.php
```

Before manually deleting the legacy `platform/` application, run the deletion
readiness verifier against the local server:

```sh
php scripts/check-platform-deletion-readiness.php --base-url=http://127.0.0.1:8080
```

The verifier may write only to the PHP target database inside transactions that
rollback. It must never write to the live `PLATFORM_*` source database.

## Public Portfolio Routes

These routes are durable public URLs:

- `GET /portfolio`
- `GET /portfolio/exhibit-collections`
- `GET /portfolio/exhibits`
- `GET /portfolio/platform-collections`
- `GET /portfolio/pieces`
- `GET /portfolio/art-media`
- `GET /portfolio/art-media/[slug]`
- `GET /portfolio/collection/[slug]`
- `GET /portfolio/exhibit/[slug]`
- `GET /media/[id]`
- `GET /image/[id]`

`/portfolio` is a curated sampler that shows a small preview of each portfolio
type and links into dedicated archive pages. Each sampler section and each
archive page lazy-load additional cards as the person scrolls, but the durable
public URLs are still the page routes above rather than separate API endpoints.

`/portfolio/exhibit-collections` lists native exhibit collections that contain
at least one exhibit. `/portfolio/exhibits` lists native exhibits.
`/portfolio/platform-collections` lists migrated platform collections, and
`/portfolio/pieces` lists migrated platform art pieces. `/portfolio/art-media`
lists piece taxonomy entries, and `/portfolio/art-media/[slug]` renders the
pieces assigned to one art-medium term.

`/portfolio/collection/[slug]` renders a native collection detail page.
`/portfolio/exhibit/[slug]` renders a native exhibit detail page. Missing,
deleted, or unknown portfolio slugs return the shared 404 view.

Compatibility redirects are permanent:

- `GET /portfolio/collections` -> `/portfolio/exhibit-collections`
- `GET /portfolio/categories` -> `/portfolio/art-media`
- `GET /portfolio/category/[slug]` -> `/portfolio/art-media/[slug]`

`/media/[id]` streams any active stored media blob. `/image/[id]` is an
image-only public route for image assets. Missing, deleted, or mismatched media
returns the shared 404 view.

## Public Platform Art Routes

Migrated platform generative art pieces live under their own namespace:

- `GET /pieces`
- `GET /pieces/[id]`
- `GET /exhibits`
- `GET /exhibits/[slug]`

`/pieces` lists all active art pieces. `/pieces/[id]` renders an individual
piece with its current version's generated code. Numeric IDs are canonical
because the migrated platform `art_pieces` records do not include slugs.

`/exhibits` lists migrated `platform_exhibits` rows (name, description, item
count, and a thumbnail drawn from the first item). `/exhibits/[slug]` renders
an individual exhibit's details and links to its `/immersive/exhibits/[slug]`
VR presentation. Returns 404 for an unknown or soft-deleted slug.

Compatibility embed and immersive routes return content rather than redirects:

- `GET /embed/posts/[id]` — embeddable HTML for a published post, retained for
  legacy platform embeds.
- `GET /embed/pieces/[id]` — embeddable HTML for the current version.
- `GET /embed/pieces/[id]?version=[version-id]` — embeddable HTML for a
  specific version of that piece.
- `GET /embed/pieces/[id]/data` — JSON for the current version.
- `GET /embed/pieces/[id]/data?version=[version-id]` — JSON for a specific
  version.
- `GET /immersive/pieces/[id]` — full-page presentation for one piece.
- `GET /immersive/exhibits/[slug]` — full-page presentation for one platform
  exhibit and its migrated art/media items.
- `GET /immersive/images/[encoded-ref]` — full-page presentation for a
  base64url-encoded image reference, retained for legacy platform image
  gallery embeds. Query parameters `title`, `alt`, and `caption` are optional
  display metadata.

## Platform Compatibility API Routes

The following JSON/API routes are provided so old platform clients can continue
to read migrated content without the Node platform app:

- `GET /api/feeds`
- `GET /api/feeds/atom`
- `GET /api/feeds/json`
- `GET /api/feeds/mf2`
- `GET /api/posts`
- `GET /api/posts/[id]`
- `GET /api/categories`
- `GET /api/categories/[slug]`
- `GET /api/categories/[slug]/posts`
- `GET /api/p/[slug]`
- `GET /api/p/[slug]/feeds/atom`
- `GET /api/p/[slug]/feeds/json`
- `GET /api/art-pieces`
- `GET /api/art-pieces/[id]`
- `GET /api/art-pieces/[id]/versions`
- `GET /api/exhibits`
- `GET /api/exhibits/[slug]`
- `GET /api/exhibits/[slug]/items`
- `GET /api/media-assets/[id]`
- `GET /api/media/[filename]`
- `GET /api/media/[filename]/exhibits`
- `GET /api/profile-photos/[filename]` — streams a `profile_photo_assets` binary blob by filename (public, unauthenticated)
- `GET /api/runtimes/[runtime-path]` — compatibility redirect for legacy
  platform embed/runtime URLs (`p5`, `c2`, and `three` runtime paths). The PHP
  app does not require `platform/` runtime assets; this route redirects old
  clients to the documented public CDN runtimes.

These routes are read-only in the PHP app. Owner/admin mutations use the
existing `/admin/*` surfaces.

## Comments API

Per-item comment list routes are public, but comment creation and owner
management require a signed-in public profile or an active admin session that
resolves to the same person. Comments are only accepted when
`comments_enabled = 1` on the target item; attempting to POST to a disabled
item returns `{"error":"not found"}` with HTTP 404.

### Blog posts

- `GET /api/posts/[id]/comments` — returns a JSON array of visible comments
  for a published post:
  `[{"id":1,"author_name":"...","content":"...","created_at":"...","can_manage":false}, ...]`.
- `POST /api/posts/[id]/comments` — submits a new signed-in comment on a
  published post. Accepts `content` (required, 1–500 chars) plus a
  `hp_field` honeypot. Returns `{"ok":true,"comment":{...}}` on success.

### Art pieces

- `GET /api/pieces/[id]/comments` — returns a JSON array of visible comments
  for an art piece:
  `[{"id":1,"author_name":"...","content":"...","created_at":"...","can_manage":false}, ...]`.
- `POST /api/pieces/[id]/comments` — submits a new signed-in comment. Accepts
  `content` (required, 1–500 chars) plus a `hp_field` honeypot. Returns
  `{"ok":true,"comment":{...}}` on success.

### Exhibits

- `GET /api/exhibits/[slug]/comments` — returns comments for an exhibit.
- `POST /api/exhibits/[slug]/comments` — submits a comment on an exhibit.

### Exhibit Collections

- `GET /api/exhibit-collections/[slug]/comments` — returns comments for a
  native exhibit collection.
- `POST /api/exhibit-collections/[slug]/comments` — submits a comment on an
  exhibit collection.

All eight item routes share the same submission shape and validation rules.
Admin controls for enabling/disabling comments per item are in the respective
admin edit forms (`/admin/pieces/[id]/edit`, `/admin/exhibits/[id]/edit`,
`/admin/exhibit-collections/[id]/edit`, `/admin/platform-collections/[id]/edit`,
`/admin/posts/[id]/edit`, `/admin/pages/[id]/edit`).

### Shared owner actions

- `POST /api/comments/[id]/edit` — updates an existing non-deleted comment
  owned by the current signed-in person. Accepts `content` (required, 1–500
  chars). Returns `{"ok":true,"comment":{...}}`.
- `POST /api/comments/[id]/delete` — soft-deletes an existing non-deleted
  comment owned by the current signed-in person. Returns `{"ok":true}` and
  leaves the record available to existing admin trash/moderation flows.

Ownership is checked against `comments.author_user_id` when available, with
`comments.author_id` as the fallback for older comments and admin-only
sessions that do not have a linked public user row yet. These public endpoints
allow people to manage only their own comments; broader moderation remains at
`/admin/comments`.

`/api/media/[filename]` looks up a migrated `media_assets` row by its
`filename` column and streams `file_data` with the stored `Content-Type` and
`Cache-Control: public, max-age=31536000, immutable`. It is a public,
unauthenticated port of the platform's `GET /media/:fileName` route, kept so
already-migrated content (post bodies, featured images, `site_settings`
logo URLs) that embeds `/api/media/{uuid}.ext` links keeps working. Returns
404 if no matching row exists or `file_data` is empty.

## Admin CMS Routes

All `/admin/*` routes require an authenticated admin session. Unauthenticated
requests redirect to `/admin/login`.

### Categories, Art Media, and Exhibits

- `GET /admin/categories`
- `GET /admin/categories/create`
- `POST /admin/categories/create`
- `GET /admin/categories/[id]/edit`
- `POST /admin/categories/[id]/edit`
- `POST /admin/categories/[id]/delete`
- `POST /admin/categories/reorder`
- `GET /admin/art-media`
- `GET /admin/art-media/create`
- `POST /admin/art-media/create`
- `POST /admin/art-media/create-inline`
- `GET /admin/art-media/[id]/edit`
- `POST /admin/art-media/[id]/edit`
- `POST /admin/art-media/[id]/delete`
- `POST /admin/art-media/reorder`
- `GET /admin/exhibit-collections`
- `GET /admin/exhibit-collections/create`
- `POST /admin/exhibit-collections/create`
- `POST /admin/exhibit-collections/create-inline`
- `GET /admin/exhibit-collections/[id]/edit`
- `POST /admin/exhibit-collections/[id]/edit`
- `POST /admin/exhibit-collections/[id]/delete`
- `POST /admin/exhibit-collections/reorder`
- `GET /admin/exhibits`
- `GET /admin/exhibits/create`
- `POST /admin/exhibits/create`
- `GET /admin/exhibits/[id]/edit`
- `POST /admin/exhibits/[id]/edit`
- `POST /admin/exhibits/[id]/delete`
- `POST /admin/exhibits/reorder`

`/admin/categories` manages blog/post categories (`category_scope='blog'`).
`/admin/art-media` manages portfolio piece taxonomy (`category_scope='portfolio'`)
and is assigned to art pieces rather than exhibits. `/admin/exhibit-collections`
is the renamed native collections surface for grouping exhibits.

Inline create endpoints return JSON:

```json
{"success":true,"id":123,"name":"Example","slug":"example"}
```

### Pieces

- `GET /admin/pieces`
- `POST /admin/pieces/reorder`
- `GET /admin/pieces/create`
- `POST /admin/pieces/create`
- `GET /admin/pieces/[id]/edit`
- `POST /admin/pieces/[id]/edit`
- `POST /admin/pieces/[id]/delete`
- `POST /admin/pieces/[id]/capture-thumbnail`
- `GET /admin/pieces/library`
- `GET /admin/pieces/generate`
- `POST /admin/pieces/generate`
- `POST /admin/pieces/generate/save`
- `POST /admin/pieces/refine-ai`
- `GET /admin/pieces/[id]/versions`
- `GET /admin/pieces/[id]/versions/create`
- `POST /admin/pieces/[id]/versions/create`
- `GET /admin/pieces/[id]/versions/[version-id]/edit`
- `POST /admin/pieces/[id]/versions/[version-id]/edit`
- `POST /admin/pieces/[id]/versions/[version-id]/delete`
- `POST /admin/pieces/[id]/versions/[version-id]/set-current`

Piece create/update accepts title, prompt, engine, status, description,
thumbnail URL, and `category_ids[]` for Art Media assignment. Current-version
HTML/CSS/JS can be edited inline on the piece form, and `/admin/pieces/library`
returns JSON for picker dialogs.

### Media Library

- `GET /admin/media`
- `GET /admin/media/library`
- `POST /admin/media/upload`
- `POST /admin/media/import`
- `POST /admin/media/[id]/trash`
- `POST /admin/media/[id]/destroy`
- `POST /admin/media/asset/[id]/update`
- `POST /admin/media/asset/[id]/trash`
- `POST /admin/media/asset/[id]/destroy`

`/admin/media/library` returns a JSON array for the existing Tiptap/media
picker. It includes native uploads (`media_files`) plus migrated platform
media (`media_assets`):

```json
[
  {
    "id": 123,
    "mime_type": "image/png",
    "url": "/media/123",
    "legacy_url": "/image/123",
    "kind": "image"
  },
  {
    "id": "asset-45",
    "mime_type": "image/png",
    "url": "/api/media-assets/45",
    "legacy_url": "/api/media-assets/45",
    "kind": "image"
  }
]
```

Migrated `media_assets` entries use a string id of the form `asset-{id}` to
avoid colliding with native `media_files` numeric ids, and both `url` and
`legacy_url` point at `/api/media-assets/{id}` (the existing migrated-media
streaming route) so the picker resolves them without using `id` to build a
`/media/{id}` or `/image/{id}` path.

Upload/import success returns the same item fields at the top level. Media is
stored in the database and is not written to repo files.

`POST /admin/media/asset/[id]/update` processes metadata edits (title and alt text) for migrated media assets in the `media_assets` table. `POST /admin/media/asset/[id]/trash` soft-deletes a migrated asset, and `POST /admin/media/asset/[id]/destroy` purges it permanently.

### Feed Sources

- `GET /admin/feed-sources`
- `GET /admin/feed-sources/create`
- `POST /admin/feed-sources/create`
- `GET /admin/feed-sources/[id]/edit`
- `POST /admin/feed-sources/[id]/edit`
- `POST /admin/feed-sources/[id]/delete`
- `POST /admin/feed-sources/[id]/ingest`
- `POST /admin/feed-sources/approve`
- `POST /admin/feed-sources/reject`

The feed sources admin is a guided two-surface workflow: **Feed Sources** for
setup/refresh and **Review Queue** for moderation. The `ingest` endpoint fetches
the feed, parses items, and records unseen items as pending imports. The
`approve` endpoint converts a pending item into a draft blog post. The `reject`
endpoint marks it as rejected.

### Trash

- `GET /admin/trash`
- `POST /admin/trash/restore`
- `POST /admin/trash/purge`
- `POST /admin/trash/empty`

Supported trash types are artworks, categories, exhibits, posts, comments, pieces, and media.

### Site Identity

- `GET /admin/site-identity`
- `POST /admin/site-identity/settings`
- `POST /admin/site-identity/navigation-order`
- `POST /admin/site-identity/assets`
- `POST /admin/site-identity/assets/[id]/delete`
- `POST /admin/site-identity/media/[id]/delete`

The site identity admin is a four-tab surface: Settings, Design, Assets, and
Media Library. `site_settings` now also carries `canonical_public_url` (used
for canonical links, social cards, and syndication when set) and
`admin_nav_order_json` (owner-configured admin navigation order).

### User Profiles

- `GET /admin/user-profiles`
- `GET /admin/user-profiles/[id]/edit`
- `POST /admin/user-profiles/[id]/edit`
- `POST /admin/user-profiles/[id]/photo` — upload a profile photo (owner uses `media_files`, member uses `profile_photo_assets`)
- `GET /admin/user-profiles/settings/create`
- `POST /admin/user-profiles/settings/create`
- `GET /admin/user-profiles/settings/[id]/edit`
- `POST /admin/user-profiles/settings/[id]/edit`
- `POST /admin/user-profiles/settings/[id]/delete`
- `GET /admin/user-profiles/keys/create`
- `POST /admin/user-profiles/keys/create`
- `GET /admin/user-profiles/keys/[id]/edit`
- `POST /admin/user-profiles/keys/[id]/edit`
- `POST /admin/user-profiles/keys/[id]/delete`

The user profiles admin is now user-only: profile editing, photos, and
per-user preferred AI profile selections remain here.

### AI Settings

- `GET /admin/ai-settings`
- `GET /admin/ai-settings/profiles/create`
- `POST /admin/ai-settings/profiles/create`
- `GET /admin/ai-settings/profiles/[id]/edit`
- `POST /admin/ai-settings/profiles/[id]/edit`
- `POST /admin/ai-settings/profiles/[id]/delete`
- `GET /admin/ai-settings/keys/create`
- `POST /admin/ai-settings/keys/create`
- `GET /admin/ai-settings/keys/[id]/edit`
- `POST /admin/ai-settings/keys/[id]/edit`
- `POST /admin/ai-settings/keys/[id]/delete`
- `POST /admin/ai-settings/vendor`

The AI settings admin has three tabs: **AI Profiles**, **API Keys**, and
**AI Vendor**. The vendor tab controls owner-facing preferred AI profiles for
art generation, text improvement, and alt-text generation.

The user edit form includes three preferred AI profile selects:
- **Art Piece Generation** — `preferred_art_piece_profile_id`
- **Text Improvement** — `preferred_text_improve_profile_id`
- **Alt Text Generation** — `preferred_alt_text_profile_id`

These preferences are automatically pre-selected in the AI generation form when available.

### AI Content Helpers

- `POST /admin/ai/process` — improves the provided text using the selected AI profile. Accepts `profile_id`, `content`, and `mode` (`html` or `text`). Returns JSON `{result: string}`.
- `POST /admin/ai/describe-image` — generates alt text for an image using the selected AI profile. Accepts `profile_id` and `image_url`. Resolves the image from `/api/media/{filename}`, `/media/{id}`, or `/image/{id}`. Returns JSON `{result: string}`.
- `GET /admin/ai/profiles` — returns a JSON array of enabled AI vendor profiles (`id`, `profile_name`, `vendor`, `model`, `user_name`) for the Tiptap "Improve with AI" profile picker.

Both endpoints require an authenticated admin session. They use `AiProviderClient::chat()` and `AiProviderClient::describeImage()` respectively, supporting `chat-completions`, `google-generate-content`, `anthropic-messages`, and `openai-responses` transports.

### Platform Connections

- `GET /admin/platform-connections`
- `GET /admin/platform-connections/create`
- `POST /admin/platform-connections/create`
- `GET /admin/platform-connections/[id]/edit`
- `POST /admin/platform-connections/[id]/edit`
- `POST /admin/platform-connections/[id]/delete`
- `GET /admin/platform-connections/syndications/create`
- `POST /admin/platform-connections/syndications/create`
- `POST /admin/platform-connections/syndications/[id]/delete`
- `POST /admin/platform-connections/publish`
- `GET /admin/platform-connections/auth/[platform]/start` — redirects to the provider's OAuth consent screen
- `GET /admin/platform-connections/auth/[platform]/callback` — exchanges the OAuth code for tokens and saves/upserts them into `platform_connections`
- `GET /admin/platform-connections/diagnostics` — shows OAuth credential status, redirect URIs, and endpoint reachability for all supported providers

The platform connections admin is a platform-guided setup surface. Operators do
not edit raw metadata JSON; typed platform forms map to `platform_connections`
internally. The admin manages outbound credentials plus `post_syndications`
(links between posts and platform connections). The `publish` endpoint
syndicates a post to a platform via the adapter layer.

OAuth providers supported: `wordpress-com`, `blogger`, `linkedin`, `facebook`, `instagram`.
OAuth callbacks encrypt tokens with `encrypt_string()` and save them into `platform_connections`.
The diagnostics page tests each provider's token endpoint with a dummy request and reports whether the endpoint is reachable.

### Syndication Adapters

All 8 platform adapters are implemented using GuzzleHTTP 7:

- `BlueskyAdapter` — AT Protocol with App Password
- `WordPressComAdapter` — WordPress.com REST API with OAuth
- `WordPressSelfAdapter` — Self-hosted WordPress REST API with App Password
- `BloggerAdapter` — Google Blogger API v3 with OAuth
- `SubstackAdapter` — Unofficial API with session cookie
- `LinkedInAdapter` — LinkedIn Posts API with OAuth
- `FacebookAdapter` — Meta Graph API with Page Access Token
- `InstagramAdapter` — Meta Graph API with Page Access Token

`AdapterFactory::get($platform)` returns the appropriate adapter instance.
`SyndicationPayload::fromPost($post, $canonicalUrl, $siteTitle)` normalizes post
data. Content helpers (buildSocialPostText, buildSyndicatedContent,
buildSourceFooter, etc.) are ported from the platform's `content.ts`.

### Platform Art Pieces

- `GET /admin/pieces`
- `GET /admin/pieces/library`
- `GET /admin/pieces/generate`
- `POST /admin/pieces/generate`
- `POST /admin/pieces/generate/save`
- `GET /admin/pieces/create`
- `POST /admin/pieces/create`
- `GET /admin/pieces/[id]/edit`
- `POST /admin/pieces/[id]/edit`
- `POST /admin/pieces/[id]/delete`
- `GET /admin/pieces/[id]/versions`
- `GET /admin/pieces/[id]/versions/create`
- `POST /admin/pieces/[id]/versions/create`
- `GET /admin/pieces/[id]/versions/[vid]/edit`
- `POST /admin/pieces/[id]/versions/[vid]/edit`
- `POST /admin/pieces/[id]/versions/[vid]/set-current`

Art piece create/update accepts title, prompt, engine, description,
thumbnail URL, and a `comments_enabled` toggle. Numeric IDs are canonical for
public piece routes. Versions are managed separately; each version has prompt,
structured spec, HTML/CSS/generated code, generation vendor, model, and
validation status. The public renderer supports migrated p5, c2, Three.js,
SVG, and generic HTML/code versions.

`POST /admin/pieces/[id]/capture-thumbnail` renders the piece's current version
code in an off-screen sandboxed iframe with `preserveDrawingBuffer: true`
(overriding the Three.js default), captures the canvas as a PNG after a
3-second animation stabilization delay, and saves it to
`public/uploads/thumbnails/piece-{id}-{time}.png`. Accepts `image_data`
(base64-encoded PNG string) in the request body. Returns
`{"ok":true,"url":"/uploads/thumbnails/piece-{id}-{time}.png"}` on success and
updates `art_pieces.thumbnail_url`.

`GET /admin/pieces/generate` renders the interface for AI piece generation, letting the admin select the engine, prompt, vendor settings, and model. `POST /admin/pieces/generate` triggers the generation process (running a 3-attempt validation and repair loop checking window.sketch constraints and output structure) and returns a preview sandbox. `POST /admin/pieces/generate/save` saves the generated metadata and code as a new piece with its initial version.

`POST /admin/pieces/refine-ai` accepts a JSON body with `prompt`, `engine`, `profile_id`, `html_code`, `css_code`, and `generated_code`. It sends the current code blocks and the refinement instruction to the configured AI vendor, then runs the same 3-attempt validation and repair loop as generation. On success it returns `{success: true, html_code, css_code, generated_code}`; on failure it returns `{success: false, error: "..."}` with HTTP 500. The endpoint is used by the "AI Refine" tab in the piece editor to suggest changes that the admin can inspect, edit, accept, or reject.

`POST /admin/pieces/[id]/versions/[vid]/set-current` sets the active version code for rendering.

`GET /admin/pieces/library` returns a JSON array of active art pieces
(`id`, `title`, `engine`, `thumbnail_url`, `status`) for the Tiptap "Insert art
piece or exhibit" picker.

### Platform Exhibits

- `GET /admin/platform-exhibits`
- `GET /admin/platform-exhibits/library`

`/admin/platform-exhibits` is a read-only listing of migrated
`platform_exhibits` rows (thumbnail, name, slug, item count, created date)
with links to each exhibit's public `/exhibits/[slug]` and
`/immersive/exhibits/[slug]` pages. These are distinct from the native
"Artwork exhibits" managed under `/admin/exhibits`. `GET
/admin/platform-exhibits/library` returns a JSON array (`slug`, `name`,
`item_count`, `thumbnail_url`) for the Tiptap exhibit picker tab.

### Navigation

- `GET /admin/navigation`
- `POST /admin/navigation/external`
- `POST /admin/navigation/reorder`
- `POST /admin/navigation/[id]/toggle`
- `POST /admin/navigation/[id]/delete`
- `POST /admin/navigation/[id]/label`
- `POST /admin/navigation/[id]/target`

The public header uses `navigation_items` when available. It falls back to the
system navigation if the database or table is unavailable. Signed-in account
actions now live behind a person-menu in the public header; admin users also
see the ordered admin navigation inside that account surface.
