# API Contract

## `GET /contact`

Renders the managed Contact system page and its database-owned Contact Form for collaboration, hiring, project help,
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

Processes the database-owned Contact Form and returns the `/contact` page with either an
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

## Forms CMS

Forms are database-owned records managed under `/admin/forms`. Setup seeds two
default forms:

- Contact Form — required on the Contact system page; emails submissions to the
  configured recipient and does not store payloads.
- Newsletter Signup — stores email addresses in `newsletter_subscribers` with
  consent defaulting to true; it does not require a recipient email and does
  not send email by default.

Form settings can store recipient email, reCAPTCHA site key, encrypted
reCAPTCHA secret, and minimum score in the database. Existing `.env` values are
runtime fallback/backfill values when database settings are empty.

Page sections can include form sections. Required system form sections, such as
the Contact Form on the Contact page, cannot be deleted from that page.

## Public User Routes

- `GET /user/login` — login form (OAuth buttons: GitHub, Google)
- `GET /user/logout` — ends the current user session and redirects to `/`
- `GET /user/auth/github/start` — begins GitHub OAuth flow
- `GET /user/auth/google/start` — begins Google OAuth flow
- `GET /auth/github/callback` — shared GitHub OAuth callback for admin and public user login
- `GET /auth/google/callback` — shared Google OAuth callback for admin and public user login
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
- Renamed system CMS page slugs redirect permanently to the page's current
  slug. For example, if the identified About page is renamed from `/about` to
  `/bio`, existing `/about` links return `301 Location: /bio`.

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

## Local Development

The canonical development server command for full local testing is:

```sh
php -S 127.0.0.1:8080 -t public public/index.php
```

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

The gallery intro, section labels/copy, archive intro/meta copy, and portfolio
detail-page chrome are admin-editable. Those visitor-facing strings are read
from `site_settings.settings_json` under `portfolio_copy`, with the previous
hard-coded text retained as runtime fallback defaults for fresh installs.

`/portfolio/collection/[slug]` renders a native collection detail page.
`/portfolio/exhibit/[slug]` renders a native exhibit detail page. Missing,
deleted, or unknown portfolio slugs return the shared 404 view.

Compatibility redirects are permanent:

- `GET /portfolio/collections` -> `/portfolio/exhibit-collections`
- `GET /portfolio/categories` -> `/portfolio/art-media`
- `GET /portfolio/category/[slug]` -> `/portfolio/art-media/[slug]`

`/media/[id]` streams any active stored media blob. `/image/[id]` is an
image-only public route for image assets. Missing, deleted, or mismatched media
returns the shared 404 view. Public media blob responses include permissive
CORS headers so CMS-hosted piece previews, embeds, and immersive WebGL/A-Frame
surfaces can load site media outside the page's own origin when needed.

## Public Platform Art Routes

Migrated platform generative art pieces live under their own namespace:

- `GET /pieces`
- `GET /pieces/[id]`
- `GET /pieces/[id]/download`
- `GET /collections/[slug]/download`
- `GET /exhibits`
- `GET /exhibits/[slug]`

`/pieces` lists all active art pieces. `/pieces/[id]` renders an individual
piece with its current version's generated code. Numeric IDs are canonical
because the migrated platform `art_pieces` records do not include slugs.
`/pieces/[id]/download` returns the current version as a downloadable ZIP
bundle. It accepts `?version=[version-id]` to export a specific version. It
also accepts `?surface=immersive` from immersive piece and collection gallery
views; immersive exports keep `index.html` as the single manual entry point but
open into the local immersive viewer surface, with fullscreen and PNG capture
available from that downloaded view. Optional `viewState` may carry a
base64url-encoded JSON object with viewer state such as camera and target
coordinates and, for collection walls, `activeIndex`; malformed state is
ignored. Immersive-origin exports include a local immersive renderer, the same
shared top stage toolbar as the live immersive surfaces (engine-gated
view/slideshow button, a download menu containing only `Download PNG`, and
fullscreen), interactive C2 support through the shared full-view overlay,
patched local Three.js/OrbitControls imports, and an embedded Blob-module fallback so
`index.html` can still mount if direct local opening blocks sibling module
imports. Regular exports remain a portable web bundle with `index.html` as the
single entry point: opening that file should load the piece without requiring
the recipient to manually open any helper file. The bundle still includes local
`runtime/`, `media/`, and editable source files so the piece can be uploaded to
another static host without the live site or CDN access. Regular exports
intentionally omit immersive/admin/embed controls and rewrite supported CMS
image/media references such as `/image/2`, `image/2`, `/media/...`,
`media/...`, `/api/media-assets/2`, and `api/media-assets/2` inside the bundle.
Bundled exports preserve engine-native interactivity: A-Frame receives its
live `<a-scene>`, C2 receives the real canvas plus safe media helpers, and
Three.js exports attach OrbitControls to the exported scene/camera/renderer so
drag/touch orbiting works even when the piece code itself only animates.
Those regular standalone exports also mirror the live regular `/pieces/[id]`
movement contract for supported 3D engines: Three.js exports keep elapsed-
time-scaled WASD/arrow movement plus click/tap-to-move teleport without
showing the immersive viewer HUD, and A-Frame exports keep the same regular-
view keyboard/tap movement behavior offline.
Interactive standalone exports for `c2_interactive`, `three`, and `aframe`
also include the lower-left screenshot icon overlay inside `index.html`.
Supported CMS media used by the piece are embedded in a file-open-safe way so
canvas screenshots can work when the recipient opens `index.html` directly.
For A-Frame, the export/runtime path also normalizes supported same-origin CMS
texture references whether they were authored through `<a-assets><img ...>`,
direct `src="/image/{id}"`, or `material="src: /api/media-assets/{id}"`.
Public `/pieces/[id]` pages keep a separate `Download PNG` action that
captures the current live frame from the rendered piece. A-Frame capture is
hardened in both the public page and exported `index.html` path with a
document-local pre-runtime WebGL context patch plus a forced-render/nonblank
validation retry, rather than relying on scene `renderer` attributes alone.
`/collections/[slug]/download` returns a platform collection gallery ZIP. It
accepts optional `viewState` with the same base64url JSON shape used by
immersive piece downloads, including camera/target and active selection state.
The collection export is not a selected-piece export: `index.html` opens into
the full local collection wall with all supported piece and media items,
fullscreen, PNG capture, slideshow/full-view behavior, and local runtime/media
packaging for direct local opening.

Sound is per-piece and muted by default everywhere it appears — no master
switch, no autoplay. On the regular `/pieces/[id]` page, a Three.js/A-Frame
piece with `sonic_params` gets a mute/unmute button next to the fullscreen
toggle (`piece-fullscreen.js`); the iframe (`piece-runtime.js`) owns Tone.js
and playback, and the parent page only posts `{type: 'creatr-sound-toggle',
enabled}` to it and listens for its `{type: 'creatr-sound-state', enabled,
reason}` reply to keep the button's state honest (a `reason: 'unavailable'`
means Tone.js failed to load, so the button reverts to muted rather than
sticking on). Other engines never render the button — they have no camera
motion on this surface to sonify (the immersive view sonifies every engine;
see the ALGORITHMS.md interaction-loop notes). Regular and immersive
standalone exports bundle the same capability entirely offline: Tone.js is
self-hosted at `assets/vendor/tone/Tone.js` live, and in bundle-mode exports
(`/pieces/[id]/download`, its immersive-surface variant, and
`/collections/[slug]/download`) it's inlined as a Blob URL — the same
inline-then-Blob-URL technique already used for the exported OrbitControls
module — so a sound-bearing exported piece needs no network at all to play.
The non-immersive export renders its own self-contained toggle button and
audio controller directly in `index.html` (there's no parent page to post a
message to); the immersive single-piece and collection exports reuse the same
shared toolbar sound button and `createAudioController` as the live immersive
surfaces. In a collection/exhibit wall (live or exported), only the item
nearest the current camera focus sonifies — the controller is torn down and
rebuilt as focus moves between pieces.

Immersive `/pieces/[id]` pages expose `Download Piece` and `Download PNG` in
a download menu inside the shared top stage toolbar (see the immersive route
notes below). PNG capture reflects the visible immersive view from the
current camera; for gallery-room engines this is the rendered Three.js
gallery canvas, not the off-screen source canvas, unless the full-view
overlay (including interactive C2) is open — then capture uses the overlay
iframe.

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
  Full immersive pages expose viewer-level controls for native Three.js and
  A-Frame pieces: a low-opacity edge HUD, right-edge vertical zoom slider
  identified by a magnifying-glass icon and aligned with the site expand
  control's x-axis, left-edge forward/back/left/right movement controls, float
  up/down controls, click/tap and press-and-hold button operation, keyboard
  WASD/arrow movement where keyboard input exists, and pointer/touch gestures
  where supported.
  Embedded/static immersive iframes keep the viewer HUD hidden. Desktop and
  supporting tablet browsers use native fullscreen when available; iPhone
  Safari uses Immersive Focus, a fixed `visualViewport`-sized mode with page
  scrolling locked and "Expand immersive view" / "Return to page" button
  language.
  All immersive surfaces (pieces, collections, images, and downloaded
  standalone exports) render one shared stage toolbar anchored to the top of
  the stage — built by `public/app/helpers/immersive-chrome.php` and wired by
  `setupImmersiveStageChrome()` in `immersive-gallery.js`. Top placement
  keeps it clear of the bottom-center iOS "Enable Motion Controls" button;
  after permission is granted the gyro toggle mounts into a reserved toolbar
  slot. The left group holds an engine-gated view button and a
  downward-opening download menu; the right side holds fullscreen. View
  button gating: collections get a slideshow button; P5/SVG/non-interactive
  C2 pieces get a full-size button opening the slideshow-style overlay
  without Prev/Next or overlay download controls; interactive C2 pieces open
  the same overlay with a fully interactive iframe; Three.js and A-Frame
  pieces render no view button.
- `GET /immersive/exhibits/[slug]` — full-page presentation for one platform
  exhibit and its migrated art/media items.
- `GET /immersive/collections/[slug]` — full-page progressive collection wall
  for migrated art/media items. The wall exposes `Download Piece` as a
  collection-gallery export via `/collections/[slug]/download`; that ZIP
  contains the full gallery wall, not only the selected item. The wall
  `Download PNG` captures the currently rendered collection wall. The slideshow
  overlay keeps active-slide PNG behavior for capturable iframe/image slides.
  Downloaded immersive exports also ship a local `window.CreatrPieceDownload`
  bridge before `runtime/immersive-gallery.js` boots, and downloaded slideshow
  iframes expose an export-only `window.__creatrExportCapture` hook instead of
  the live runtime readiness markers. Ordinary canvas slides capture the
  currently visible exported slide directly, SVG slides export from the
  visible SVG surface, and A-Frame slides still reserve the stricter nonblank
  validation path. `Download Piece` still routes to the same full collection
  export.
- `GET /immersive/images/[encoded-ref]` — full-page presentation for a
  base64url-encoded image reference, retained for legacy platform image
  gallery embeds. Query parameters `title`, `alt`, and `caption` are optional
  display metadata.

## Admin Piece Generation Routes

AI piece generation is an admin-only workflow. `POST /admin/pieces/generate`
performs exactly **one AI attempt per request** — the same stateless,
client-driven retry design `POST /admin/pieces/refine-ai` already used. The
server never loops through multiple attempts inside one request; the browser
decides whether to spend another attempt and sends a fresh request for it.
This keeps any single request's worst-case duration to one AI call (rather
than up to `ART_PIECE_MAX_ATTEMPTS` chained calls in one request), which
matters because the documented local/production server model handles a
limited number of requests at a time — a long multi-attempt request
previously blocked unrelated navigation (e.g. clicking "Back") until it
finished.

- `GET /admin/pieces/generate` — renders the generation form.
- `POST /admin/pieces/generate` — accepts `prompt`, `generation_mode`,
  `profile_id`, optional `persona_id`, and the per-attempt fields described
  below; performs a single AI generation attempt; returns JSON. Supported
  generation modes are `p5`, `c2`, `c2_interactive`, `three`, `svg`, and
  experimental `aframe`. The `c2_interactive` mode persists generated pieces
  as `engine = 'c2'`; it is a prompt/validation mode for click, touch,
  pointer, and drag behavior, not a durable engine value. The experimental
  `aframe` mode persists as `engine = 'aframe'` only after the generated
  draft passes static preflight and the preview/capture save gate.
- `GET /admin/pieces/generate/preview` — renders the one-time pending
  generation preview saved in the admin session; redirects back to
  `/admin/pieces/generate` if no pending preview exists.
- `POST /admin/pieces/generate/save` — saves the previewed piece and returns
  JSON with a redirect URL.

The generation form's engine selector groups stable engines separately from
`C2.js Interactive` and `A-Frame Experimental`. `C2.js Interactive` uses the
same C2 runtime contract as normal C2 pieces (`runtime.c2`, `canvas`,
`startFrame`, plus the safe `loadImage()` / `drawImage()` CMS-media helpers)
while explicitly allowing native `canvas.addEventListener()`
pointer/click/touch/drag state. `A-Frame Experimental` requires exactly one
`<a-scene id="scene" embedded>` root, no external assets or networked media,
and optional setup code assigned as
`window.sketch = ({ AFRAME, scene, startFrame }) => { ... }`.

Generated and edited art pieces may reference existing CMS-owned media only
through same-origin paths:

- `/image/[id]`
- `/media/[id-or-path]`
- `/api/media-assets/[id]`

Prompt parsing and validation treat the two CMS image families explicitly:

- `image ID [n]`, `photo ID [n]`, and `picture ID [n]` authorize only
  `/image/[n]`
- `media asset ID [n]` authorizes only `/api/media-assets/[n]`
- A prompt that explicitly names both forms authorizes both path families
- The validator does not infer that `/image/[n]` and `/api/media-assets/[n]`
  are interchangeable, even if they may correspond to the same underlying
  media visually

Examples:

- `use image ID 3 as the background` permits `/image/3`
- `apply photo ID 4 as the texture` permits `/image/4`
- `use media asset ID 5 as the background` permits `/api/media-assets/5`
- `use image ID 3 and media asset ID 5` permits both `/image/3` and
  `/api/media-assets/5`

Engine-specific safe media examples:

- p5.js: `p.preload = () => { img = p.loadImage('/image/2'); };`
  then `p.image(...)`, or a local `drawImageCover(...)` helper for full-frame
  cover-cropped backgrounds
- Three.js: `new THREE.TextureLoader().load('/image/2')`, then map the
  texture onto explicit geometry dimensions; full-frame background textures
  should configure repeat/offset cover behavior
- A-Frame: `<a-assets><img id="asset" src="/image/2"></a-assets>` and
  `src="#asset"` or `material="src: #asset"` on a rendered entity
- SVG: `<image href="/image/2" x="0" y="0" width="800" height="600" />`
- C2.js: `runtime.loadImage('/image/2')`, `runtime.drawImage(...)`, and
  `runtime.drawImageCover(...)`

Every engine's system prompt now documents both families where relevant, for
example `/image/{id}` for image/photo prompts and `/api/media-assets/{id}` for
media-asset prompts. This keeps generation, regeneration, and refine guidance
aligned with the shared validator rather than treating media-asset IDs as an
undocumented special case.

Media asset declarations only define the source image. Rendered size belongs
to the engine's drawing surface: p5/C2 draw calls use their width/height
arguments, SVG `<image>` uses `x/y/width/height`, Three.js uses geometry size,
and A-Frame uses the dimensions of the entity that references the asset. Full
background examples use the current canvas/frame dimensions or, for 3D engines,
camera-aware plane dimensions computed from FOV, aspect ratio, and distance.
Stored piece code remains CMS-runtime compatible and must keep its
`window.sketch` contract. Portable standalone files are produced by the
download/export wrapper rather than by adding document, script, import, or
presentation-control code to the stored HTML/CSS/JS blocks. Export-only URL
rewriting converts CMS media paths into absolute live site URLs; stored code
continues to use same-origin CMS paths.

Remote URLs, `fetch`, XHR, imports, scripts, iframes, arbitrary asset-loading
tags, storage access, page navigation, and parent/top-window access remain
blocked in generated piece code. This keeps existing CMS media usable as a
creative input without turning art pieces into unrestricted web documents.

A-Frame pieces use the self-hosted `/assets/js/aframe.min.js` runtime. Public
piece pages, embeds, admin previews, thumbnail capture, and
`/immersive/pieces/[id]` all recognize `engine = 'aframe'`. The immersive
piece route mounts A-Frame pieces as a live `<a-scene>` directly inside the
immersive stage, rather than projecting them as a framed gallery texture. In
full immersive mode, A-Frame pieces use the same site-level viewer controls as
native Three.js pieces: the edge HUD, magnifying-glass-labeled vertical zoom
slider aligned with the site expand control, directional camera buttons, float
up/down buttons, tap/click movement, and mobile/tablet/desktop pointer support.
These controls translate the viewer camera and do not call into piece-authored
event handlers. A-Frame's built-in enter-VR/fullscreen control is disabled in
this surface so the site-level expand control remains the only expansion
control, with iPhone Safari using Immersive Focus rather than native browser
fullscreen.
C2.js pieces in the immersive gallery/exhibit wall are texture-projected
(no native pointer events reach the off-screen canvas); clicking the framed
piece or the toolbar view button opens the shared full-view overlay — the
slideshow-style shell hosting the same on-screen render document
`/pieces/[id]` uses in a fully interactive iframe — for full
click/touch/drag, closable via the × button or Escape. The off-screen C2 runtime used for the projected gallery
frame still follows the same safe CMS-media helper contract as normal C2
renders: `runtime.loadImage()`, `runtime.drawImage()`, and
`runtime.drawImageCover()` are available for same-origin CMS media paths.

`POST /admin/pieces/generate` request fields (in addition to `prompt`,
`generation_mode`, `profile_id`, `persona_id`):

- `attempt_number` — 1 for a fresh generation; the client increments this on
  each retry. Defensively capped server-side at `ART_PIECE_MAX_ATTEMPTS`
  regardless of what the client sends.
- `previous_raw_response` — the prior attempt's raw AI response, echoed back
  by the server on failure; omitted/empty on attempt 1. Used to build the
  repair prompt for attempt 2+.
- `last_error` — the prior attempt's error message, same role as
  `previous_raw_response`.
- `sequence_token` — a client-generated UUID (or timestamp-based fallback)
  identifying one logical generation sequence across its retries, for audit
  log correlation. Not validated server-side.

`POST /admin/pieces/generate` success response:

```json
{"success": true}
```

Failure responses:

```json
{
  "success": false,
  "error": "Human-readable error",
  "raw_response": "...or null",
  "attempt_number": 1,
  "can_retry": true
}
```

`can_retry` is `false` once `attempt_number` has reached
`ART_PIECE_MAX_ATTEMPTS`. The generation form shows an "Attempt N of 5
failed" dialog on any failure response, offering "Try Again" (after a 30s
cooldown, sending `attempt_number + 1` with the echoed `raw_response`/`error`
as repair context) or "Give Up" — mirroring `POST /admin/pieces/refine-ai`'s
existing retry dialog. There is no separate cancel endpoint: aborting the
in-flight attempt is purely client-side (`AbortController`), since there's
no multi-attempt server-side loop left to interrupt.

## Platform Compatibility API Routes

The following JSON/API routes provide public read access for portable site
consumption and legacy platform compatibility. They are not a complete
API-first CMS contract: there is no machine-auth write API, no full remote
admin API, and no cross-origin browser CORS guarantee unless that is added
later.

- `GET /api/site`
- `GET /api/navigation`
- `GET /api/pages`
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
  app ships no platform-era runtime assets of its own; this route redirects old
  clients to the documented public CDN runtimes.

These routes are read-only in the PHP app. Owner/admin mutations use the
existing `/admin/*` surfaces.

### Portable Read API

`GET /api/site` returns safe public site identity and display metadata:
site title, description, canonical origin, theme/palette names, default theme
mode, logo URLs, CTA label/href, public color tokens, and feed links. It does
not expose custom JavaScript, custom body HTML, encrypted values, OAuth
credentials, API keys, SMTP settings, or admin-only configuration.

`GET /api/navigation` returns the same public navigation items used by the
rendered site header. Each item includes label, URL, target, source type, and
active key when available.

`GET /api/pages` returns a published page index: id, system key, title, slug,
URL, navigation label, description fields, public SEO metadata, sort order,
and timestamps. Page body sections remain on `GET /api/p/{slug}`.

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

### Feature Flags (content-safe module toggles)

- `GET /admin/features` — Features panel with Art Pieces / Exhibits / Blog / AI subtabs.
- `POST /admin/features/save` — saves one subtab's toggles; redirects `303` back to the panel.

Flags live in `site_settings.settings_json` under `features_json` and default to
enabled (a missing or unreadable store fails open). Toggling a feature off is
content-safe: **no public route changes behavior** — existing published content
keeps its URLs, feeds, and listings. Only admin creation routes (`*/create`,
feed ingest/approve) and AI endpoints are gated; a gated form route redirects
`303` to its section index with `?error=`, and a gated JSON endpoint (piece
generate/refine, theme generate/refine, `/admin/ai/process`,
`/admin/ai/describe-image`) returns `403` with `{"ok": false, "error": ...}`.
Primary admin modules stay visible as manage-only while non-deleted content
exists, and related management surfaces also consider their own records:
categories, comments/reactions, external feed sources/pending imports, and art
media terms remain reachable for management even when their parent feature is
off. New related creation/import actions remain hidden/blocked while the parent
feature is off. AI Settings is configuration and remains accessible regardless
of the AI runtime master switch.
The AI subtab is grouped into Master Switch, Piece Code Generation, Theme
Generation, Image Description Generation, and Editor AI. The Editor AI section
contains both the parent editor switch and the per-editor function toggles.
Piece generation/refine requires `ai_pieces_code` plus the matching engine flag
(`p5`, `c2`, `c2_interactive`, `three`, `svg`, or `aframe`); `c2_interactive`
applies to new generation only, while saved C2 pieces use the C2 flag for AI
Refine. Runtime AI buttons are hidden independently by use-case flag, with the
server endpoint still enforcing each flag. `POST /admin/ai/process`
additionally requires `ai_editor` and a `context` field
(`pages|blog|exhibits|platform_collections|media`) matching an enabled per-area
editor-AI flag. There is no `pieces` editor-AI context.
Theme-generation default profile selection is managed from
`/admin/ai-settings?tab=vendor`, not from the Features panel.
`POST /api/cron/refresh-feeds` responds `200 {"ok": true, "skipped": "blog disabled"}`
instead of ingesting while the blog feature is off.

### Public Copy

- `GET /admin/public-copy`
- `POST /admin/public-copy/save`

`/admin/public-copy` edits visitor-facing system copy for the portfolio and
adjacent public art surfaces. Portfolio-owned strings are stored under
`site_settings.settings_json.portfolio_copy`; shared public-art/UI strings are
stored under `site_settings.settings_json.public_art_copy`.

The surface covers `/portfolio`, the portfolio archive pages, portfolio detail
page chrome, `/pieces`, `/collections`, the shared 404 hero, selected
immersive/download labels, and the comments empty-state copy used by those
surfaces. Record-owned descriptions and statements remain on their original
content models.

The footer credit remains `site_settings.footer_credit`, but it now renders as
sanitized inline HTML. Allowed tags: `<a>`, `<strong>`, `<em>`, `<b>`, `<i>`,
and `<br>`. Link URLs may be relative, `https`, `mailto`, `tel`, or `#`
anchors; unsafe tags and attributes are stripped.

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
- `POST /admin/pieces/[id]/refine-save`
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

`art_pieces` now carries a `thumbnail_alt_text VARCHAR(500) NULL` column. When a piece is saved via `POST /admin/pieces/generate/save` or a thumbnail is captured via `POST /admin/pieces/[id]/capture-thumbnail`, the first 500 characters of the piece's creative prompt are automatically written to `thumbnail_alt_text` — no AI tokens are used. All piece thumbnail `<img>` elements use this value as their `alt` attribute, falling back to the piece title when the column is NULL.

### Media Library

- `GET /admin/media`
- `GET /admin/media/library`
- `POST /admin/media/upload`
- `POST /admin/media/import`
- `POST /admin/media/[id]/confirm`
- `POST /admin/media/[id]/discard`
- `POST /admin/media/poster-upload`
- `POST /admin/media/[id]/trash`
- `POST /admin/media/[id]/destroy`
- `POST /admin/media/[id]/update` — updates title, `alt_text`, and optional `poster_media_file_id` for a native `media_files` record. Redirects to `/admin/media` for normal form posts and returns JSON for AJAX callers.
- `POST /admin/media/asset/[id]/update`
- `POST /admin/media/asset/[id]/trash`
- `POST /admin/media/asset/[id]/destroy`

`/admin/media/library` returns a JSON array for the existing Tiptap/media
picker. It includes native uploads (`media_files`) plus migrated platform
media (`media_assets`). Picker responses default to `ready` assets only.
Native video rows may also include `poster_media_file_id` and `poster_url`.
Both entry types include `alt_text`:

```json
[
  {
    "id": 123,
    "mime_type": "image/png",
    "url": "/media/123",
    "legacy_url": "/image/123",
    "kind": "image",
    "status": "ready"
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

Native uploads/imports now create `draft` `media_files` rows first. The upload
response returns the created draft payload, and callers are expected to
complete confirmation via `POST /admin/media/[id]/confirm` before the asset is
insertable from pickers. `POST /admin/media/[id]/discard` permanently deletes a
draft asset. `POST /admin/media/poster-upload` is an admin-only helper that
creates a ready image asset for use as a linked video poster. Media is stored
in the database and is not written to repo files.

`POST /admin/media/[id]/confirm` validates and persists metadata for a draft
native upload, including:

- `title` (optional, max 255 chars)
- `alt_text` (required for confirmation, max 500 chars)
- `poster_media_file_id` (optional; image assets only, meaningful for videos)

On success, the native asset flips from `status='draft'` to `status='ready'`
and receives `confirmed_at`.

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
`admin_nav_order_json` (owner-configured admin navigation order, managed from
`/admin/navigation?tab=admin`; the existing post endpoint remains for
compatibility).

### User Profiles

- `GET /admin/user-profiles`
- `GET /admin/user-profiles/[id]/edit`
- `POST /admin/user-profiles/[id]/edit`
- `POST /admin/user-profiles/[id]/photo` — upload a profile photo (owner uses `media_files`, member uses `profile_photo_assets`)

The user profiles admin is user-only: profile editing, membership state, and
profile photos. AI profiles, keys, personas, and preferred workflow defaults
live under AI Settings.

### AI Settings

- `GET /admin/ai-settings`
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
- `POST /admin/ai-settings/vendor`
- `GET /admin/ai-settings/personas/create`
- `POST /admin/ai-settings/personas/create`
- `GET /admin/ai-settings/personas/[id]/edit`
- `POST /admin/ai-settings/personas/[id]/edit`
- `POST /admin/ai-settings/personas/[id]/delete`

The AI settings admin has four tabs: **AI Profiles**, **API Keys**, **AI Vendor**, and **AI Personas**. The vendor tab controls owner-facing preferred AI profiles for art generation, theme generation, text improvement, and alt-text generation. AI Settings is treated as configuration and remains accessible even when the AI runtime master switch is off.

Each **AI Profile** row now carries a `capabilities` field (comma-separated tokens: `text`, `code`, `vision`; default `text,code`). Only profiles with the `code` capability should be used for piece generation; only profiles with the `vision` capability can call `POST /admin/ai/describe-image`. The generate form shows an inline warning when a profile without `code` capability is selected, and the describe-image endpoint returns HTTP 400 if the selected profile lacks `vision` capability.

**AI Personas** are reusable named system prompts stored in `ai_personas`. When a persona is selected in the generate form, the AI receives: `{persona system_prompt}\n\nApply this to the following prompt:\n\n{user prompt}` as the effective prompt. Persona records belong to `user_id` rows; they are hard-deleted (no soft-delete). The create endpoint accepts `Accept: application/json` or `_format=json` for inline AJAX creation from the generate form and returns `{ok: true, persona: {id, name}}`.

The user edit form includes three preferred AI profile selects:
- **Art Piece Generation** — `preferred_art_piece_profile_id`
- **Text Improvement** — `preferred_text_improve_profile_id`
- **Alt Text Generation** — `preferred_alt_text_profile_id`

These preferences are automatically pre-selected in the AI generation form when available.

### AI Content Helpers

- `POST /admin/ai/process` — improves the provided text using the selected AI profile. Accepts `profile_id`, `content` (HTML markup or plain text), and `mode` (`html` or `text`). Returns JSON `{result: string}`. When text is selected in TipTap, the selected fragment is serialized as HTML. When no text is selected, the full editor HTML is sent and the entire content is replaced on success. The HTML-mode system prompt explicitly preserves all iframes, images, videos, figures, and HTML attributes — only visible text words are changed. This same endpoint is also used for refine-only video description polishing in the media library and TipTap picker: AI never watches the video, it only improves user-written text in `mode=text`.
- `POST /admin/ai/describe-image` — generates alt text for an image using the selected AI profile. Accepts `profile_id` and `image_url`, plus optional `existing_alt_text` to refine an existing draft instead of starting from scratch. Resolves the image binary from `/api/media/{filename}`, `/media/{id}`, or `/image/{id}`. Returns JSON `{result: string}` on success. On configuration failures it now returns a stable `code` plus `error` message and `diagnostics` payload. Current codes include `vision_not_enabled`, `vision_transport_unsupported`, `vision_model_unsupported`, `missing_api_key`, `image_load_failed`, `provider_request_failed`, and `unexpected_error`.
- `GET /admin/ai/profiles` — returns a JSON array of enabled AI vendor profiles for the "Improve with AI" and "Generate Alt Text with AI" picker modals. Each entry includes `id`, `profile_name`, `vendor`, `model`, `user_name`, resolved `capabilities`, and diagnostics fields such as `capability_source`, `explicit_capabilities`, `inferred_capabilities`, `transport_kind`, and `vision_inferred`. The picker filters by the resolved capability set client-side, so schema-missing or stale capability flags can fall back to vendor/model inference instead of producing false negatives.

All three endpoints require an authenticated admin session. They use `AiProviderClient::chat()` and `AiProviderClient::describeImage()` respectively, supporting `chat-completions`, `google-generate-content`, `anthropic-messages`, and `openai-responses` transports.

The AI write/helper endpoints are subject to fixed-window rate limiting. When
the limit is exceeded they return HTTP `429` with a JSON error payload instead
of calling the provider.

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
- `GET /admin/platform-connections/diagnostics` — shows OAuth credential status, redirect URIs, and endpoint reachability for the 5 OAuth publishing providers, plus a second non-secret diagnostics table for the 3 credentials-based providers (`wordpress_self`, `substack`, `bluesky`)

The platform connections admin is a platform-guided setup surface. Operators do
not edit raw metadata JSON; typed platform forms map to `platform_connections`
internally. The admin manages outbound credentials plus `post_syndications`
(links between posts and platform connections). The `publish` endpoint
syndicates a post to a platform via the adapter layer.

OAuth providers supported: `wordpress-com`, `blogger`, `linkedin`, `facebook`, `instagram`.
OAuth callbacks encrypt tokens with `encrypt_string()` and save them into
`platform_connections`. The provider app credentials for those five publishing
providers are stored in the PHP site's own `platform_oauth_apps` table, not in
runtime environment variables. The diagnostics page reads configured status from
that table and tests each provider's token endpoint with a dummy request to
report reachability without exposing secrets.

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

`POST /admin/pieces/[id]/capture-thumbnail` accepts `image_data`
(base64-encoded PNG string, captured client-side) in the request body,
decodes it, stores it via `MediaFile::create()` into `media_files`, and
points `art_pieces.thumbnail_url` at the resulting `/image/{mediaId}`.

The client side renders the piece's current version code in a sandboxed
iframe (kept within the viewport via `opacity:0`, not positioned off-screen
— see below) with `preserveDrawingBuffer: true` for Three.js, then captures
the canvas as a PNG once it's confirmed ready, via one of three
hand-duplicated capture implementations: `generate-preview.php`'s
auto-capture-on-generate, `form.php`'s `performCapture()` (manual "Generate
Thumbnail" on the Edit page, and — since both fire it automatically rather
than requiring an extra click — also called right after a successful AI
Refine "Accept Changes" and after a dirty "Save Changes" submit), and
`index.php`'s `runCaptureForId()` (manual, from the Pieces list — this one
carries its own full inline copy of `piece-runtime.js`'s boot logic rather
than loading the shared file, and must be kept mirrored by hand). All three
previously captured based on a *proxy* for readiness (canvas element
existing, or a fixed timeout) rather than a confirmed real rendered frame —
`piece-runtime.js` now sets `canvas.dataset.creatrReady = '1'` (and posts
the existing `sketch-status` message) only once a real frame has actually
drawn: P5 polls `instance.frameCount >= 1`; C2/generic wraps the
`startFrame` handed to the sketch; Three.js fires from whichever of its two
possible render paths actually runs. The manual capture paths require this
marker for p5/c2/three before calling `toDataURL()`.

For Three.js specifically, `bootThree()` used to create the `<canvas>`
element only after two sequential `await import(...)` calls to the
three.module.js/OrbitControls CDN bundles — so on a slow or WebKit-throttled
connection, the capture polling loop would see no canvas at all for its
entire wait window and fail with a generic "No canvas or svg element found"
message that misreported a CDN stall as the piece having no canvas. The
canvas is now created and inserted before those imports start (only the
`creatrReady` marker still waits on them), the two imports run in parallel
via `Promise.all` instead of sequentially, and a 20s stall timer posts a
specific "Three.js failed to load from the CDN" error via `sketch-status` if
the imports haven't resolved by then — so a genuine network stall is now
distinguishable from an actually-broken piece.

`GET /admin/pieces/generate` renders the interface for AI piece generation, letting the admin select the generation mode, prompt, vendor settings, and model. `POST /admin/pieces/generate` performs exactly one AI generation attempt (checking `window.sketch` constraints and output structure via the same preflight generation/refine share) and, on success, stores a preview sandbox; on failure it returns the attempt's error/raw response so the client can decide whether to spend another attempt — see "Admin Piece Generation Routes" above for the full per-attempt request/response contract, which mirrors `POST /admin/pieces/refine-ai`'s. `generation_mode='c2_interactive'` selects a stricter C2 prompt for click/touch/drag interaction but still stores the resulting piece as `engine='c2'`; `generation_mode='aframe'` stores `engine='aframe'` only after the experimental A-Frame draft passes static preflight and reaches the preview save step. When the deployment has the optional `art_piece_versions.sonic_params` column (`art_piece_sonic_params_supported()`) and the generate form's **Add instrumentation** control is used, the optional **Describe the feel** text is sent to the model as sonification guidance and saved as `sonic_params.feel` when the returned `sonic` JSON validates. `POST /admin/pieces/generate/save` saves the generated metadata and code as a new piece with its initial version, returning JSON — `{"success":true,"redirect":"/admin/pieces"}` on success, or `{"success":false,"error":"..."}` with HTTP 400 on failure (e.g. a missing title) — rather than a server-side redirect. A-Frame saves additionally require the preview page's browser-side thumbnail capture to complete, using that nonblank capture as the sandbox render check before the durable `aframe` engine value is persisted. The preview page's Save form submits via `fetch()` with a one-time automatic retry on a network-level failure specifically (the connection itself dying before the server ever received the request — e.g. a stale keep-alive connection reused after a gap spent reviewing the preview — not a resolved server error, which is shown directly without retrying), then navigates via the returned `redirect` on success. This endpoint has exactly one caller (`generate-preview.php`'s form).

Sound is per-piece, never a global master switch, and is created or updated
through exactly two paths: generation's **Add instrumentation** control
(above) and AI Refine's own instrumentation checkbox (below) — there is no
manual Metadata-tab or per-version-edit-page toggle; both were removed since
generation and refine already cover every case sound needs to change, and a
third manual path only invited the two to drift out of sync. Whichever path
is used, the input is normalized into a canonical JSON object with `tempo`,
`scale`, `instrument`, and `feel`; terms such as "minor" and "theremin" map to
the closest supported runtime values, and saving a changed Tone feel creates
a new current version even when HTML/CSS/JS are unchanged. The main "Save
Changes" form and the per-version edit page never submit sound fields, so
saving through either always preserves the version's existing `sonic_params`
untouched.

`POST /admin/pieces/refine-ai` accepts a JSON body with `prompt` (the refinement instruction), `engine`, `profile_id`, `persona_id` (optional), `html_code`, `css_code`, `generated_code`, `original_prompt` (optional — the piece's own creative prompt), and optionally `sound_enabled`/`sound_feel` when the admin asks AI Refine to add or update instrumentation. Unlike generation, refine never asks the AI for a complete rewritten file — a full rewrite gives a model no structural reason to leave anything untouched, and in practice none reliably do. Instead the AI must respond with a `PLAN:` section naming the specific existing elements it intends to touch, followed by one or more `PATCH <html|css|js>:` blocks, each an exact `<<<<<<< SEARCH` / `=======` / `>>>>>>> REPLACE` find-and-replace against the *current* code (see `art_piece_refine_system_prompt()`, which includes worked examples for both an HTML and a JS patch). When `sound_enabled` is true, the same response may also include a fenced `sonic` JSON block; a sound-only refinement is valid with a `PLAN:` plus `sonic` block and no code patches. `art_piece_extract_refine_patches()` parses code patches — it also accepts `PATCH javascript:` as a synonym for `PATCH js:` and normalizes it, since models reliably write the former despite being asked for the latter (this silently discarded every otherwise-valid patch on Three.js pieces specifically, since their refinements are almost always JS-only and so hit the mismatch on every attempt — found by reproducing a real failing piece's refine request directly against the same code path outside the browser); `art_piece_apply_refine_patches()` applies each one against the original `html_code`/`css_code`/`generated_code` sent in the request via `art_piece_find_patch_match()` — an exact substring match first, falling back to a whitespace-tolerant match (tokenizes into word-runs and individual punctuation characters, discarding whitespace, then re-joins with `\s*` between every token) if the exact match finds nothing. This tolerates the kind of incidental reformatting LLMs are known to introduce when transcribing code verbatim (adding/dropping a space around `:`/`{`/`,`, re-indenting) without weakening the guarantee: every actual token in `SEARCH` must still match exactly, only whitespace *between* tokens is flexible, so this can't match different content, only differently-formatted identical content. A file with no `PATCH` block for it is carried forward completely unchanged either way. This is a structural guarantee, not a prompt request: content outside a matched `SEARCH` region is never regenerated, so it cannot drift. A patch whose `SEARCH` text doesn't match the current code exactly once (not found, or ambiguous) by either method fails validation and feeds into the same retry loop generation uses, via a refine-specific repair prompt (`art_piece_refine_repair_prompt()`) that reminds the AI of the exact format — and, critically, re-includes the current HTML/CSS/JS on every retry attempt (not just the first), so a failed attempt can actually re-read the real source and correct itself instead of guessing blind from memory of its own previous wrong response — rather than reusing generation's "animations must be infinite" framing. When the model ignores the PLAN+PATCH protocol entirely and responds with full-file fenced ` ```css `/` ```javascript ` blocks and no `PATCH` marker at all (confirmed in production audit logs), the zero-patches branch now throws a distinct "AI ignored the required PATCH format and returned full rewritten files in fenced code blocks instead of a diff" message rather than the generic "no valid PATCH blocks" one — that specific wording flows straight into the next repair prompt's "your previous attempt failed" line, naming the actual mistake instead of repeating the same generic instruction that already failed to prevent it once. The resulting code still runs through the same `art_piece_preflight_code()` engine/security checks as generation. On success it returns `{success: true, html_code, css_code, generated_code, plan, profile_id, persona_id, sonic_params}` — `plan` is the AI's stated PLAN text, shown to the admin alongside the diff for the same before-acting visibility a plan gives; `profile_id`/`persona_id` are echoed back so the client can carry them into the version created when the accepted code is saved. On failure it returns `{success: false, error: "..."}` with HTTP 500. The endpoint is used by the "AI Refine" tab in the piece editor, which also renders a line diff of what actually changed, and the AI's PLAN text, before the admin decides to accept, edit, or reject. Accepting calls `POST /admin/pieces/[id]/refine-save` immediately (see below) rather than requiring a separate "Save Changes" submit, then — on a successful save that actually changed something — automatically re-captures and uploads a new thumbnail via the same client-side `performCapture()` path the Edit page's manual "Generate Thumbnail" button uses, so an accepted refinement's thumbnail no longer goes stale until someone clicks that button by hand.

`POST /admin/pieces/refine-ai` partitions scope by **purpose domain**, derived from which instruction field(s) the admin filled:

- prompt set, **Add or update instrumentation** unchecked → `purpose_domain = 'visual'` (visuals in scope; sound carried forward unchanged — no `### PURPOSE` directive asks for a sonic block, and `sonic_params` falls back to the current version's stored value).
- prompt empty + **Add or update instrumentation** checked + **Tone Feel** non-empty → `purpose_domain = 'audio'` (sound in scope; visuals OUT OF SCOPE — no `PATCH` block is generated at all and any hallucinated html/css/js patch is force-cleared by the backstop after the response returns).
- prompt set + sound feel non-empty → `purpose_domain = 'audio_visual'` (both domains in scope; the model may emit `PATCH` blocks AND a `sonic` block together — the only mode in which both may change in a single refine).

Each refine request now opens with an explicit `### PURPOSE OF THIS REFINEMENT` header in the user prompt declaring which domain is IN SCOPE and which is OUT OF SCOPE and must not change, regardless of what prior context is also included — so the model and any tool-using proxy layer see the scope unambiguously. The piece's original creative prompt (`original_prompt`) is included as **context for reference only** (labeled `### CONTEXT: ORIGINAL CREATIVE PROMPT`), never as the goal of the refine — the directive is the PURPOSE header. In audio-only mode the original prompt is run through `art_piece_elide_out_of_scope_refs()`, which replaces bare file-name references whose extension names a visual/media asset (`image.png`, `/image/N`, `.jpg`/`.svg`/`.glb`/etc.) with the descriptive placeholder `[(visual asset reference elided; out of scope for this audio-only refine)]` so an agentic provider proxy cannot auto-resolve those refs into image input for a text-only model (the root cause of the `"Cannot read 'image.png' (this model does not support image input)"` failure observed on sound-only refine). Visual-only and audio+visual refine pass `original_prompt` through unchanged — visual assets are in scope there.

`POST /admin/pieces/generate/regenerate` (used by the Regenerate button on `/admin/pieces/generate/preview`) derives its `purpose_domain` **purely from its lineage** (`sound_enabled_lineage` and `sound_feel_lineage` carried as read-only hidden inputs from the originating generation; the JS `buildRegeneratePayload` passes them straight through). Regeneration can only *amplify* existing scope, never change it: generation lineage always yields `'audio_visual'` (the admin enabled instrumentation at generate time) or `'visual'` (no instrumentation requested at generate time) — never `'audio'` alone, since every piece generation requires a visual prompt. Sound-only lineage is unavailable from generation and is reserved for a future refine-lineage regenerate flow (the architecture accommodates it without further change). When the lineage placed audio in scope, the regenerate instruction asks the model to revise both visuals and sound; sonic capability instructions are appended to the system prompt (parity with refine — previously regenerate silently dropped the audio lineage and produced visual-only output). The regenerated `sonic_params` (or `null` when visual-only lineage) persist into the pending preview's current block, return in the success JSON as `sonic_params`, and `applyRegeneratedPreview` writes them back to the `sonic_params` hidden input before `renderPreviewDocument()` so the preview iframe surfaces the new instrumentation audibly. The empty-patches check is sonic-aware: visual-only lineage requires at least one visual `PATCH`; audio+visual lineage accepts a regenerated `sonic` block alone as a valid regenerate (sound improved, visuals preserved).

`POST /admin/pieces/generate` and `POST /admin/pieces/refine-ai` are both
rate-limited, consumed on every attempt request (not once per logical
sequence) — same precedent `refine-ai` already established. A throttled
request returns HTTP `429`.

`refine-ai`'s call into `AiProviderClient::generate()` passes
`suppressPlanningPreamble: false` and `maxTokensOverride: 24576` — both
optional parameters added so refine's request isn't subject to behavior
written for fresh generation. By default `generate()` appends a "skip
reasoning/planning notes, output only fenced code blocks" instruction for
opencode and Anthropic-transport vendors, which is correct for generation's
plain-fenced-code-block format but directly contradicts refine's PLAN+PATCH
format (which *requires* a `PLAN:` section); refine opts out of it. The
higher token ceiling accounts for refine's structurally larger output cost —
every patch reproduces a verbatim `SEARCH` anchor on top of its `REPLACE`
content, on top of the transport's already-raised default of `16384` for
opencode/deepseek models. `generate()` also now returns `finishReason`
(the provider's stop/finish reason, transport-dependent field name), and
`AiProviderClient::finishReasonMeansTruncated()` recognizes
"length"/"max_tokens"/"MAX_TOKENS"/"incomplete" as a truncated response —
`refine-ai` surfaces a distinct "cut off before finishing" error for these
instead of the generic "no valid PATCH blocks" message, since the two
failure modes need different fixes (a smaller instruction vs. a genuine
SEARCH-mismatch retry). Neither change affects `generate()`'s other caller
(fresh piece generation) or `chat()` (admin AI text/alt-text tools), which
both pass no arguments and so keep the prior defaults exactly.

`POST /admin/pieces/[id]/refine-save` accepts a JSON body with `html_code`, `css_code`, `generated_code`, `refinement_prompt`, `profile_id`, `persona_id`, and optionally `sonic_params`, and is called automatically when "Accept Changes" is clicked in the AI Refine tab — no separate save step is needed. If the submitted code or submitted `sonic_params` differs from the piece's current version, it creates a new `art_piece_versions` row with `prompt` set to `refinement_prompt` (the instruction that was actually given for *this* refinement — not the piece's original creative prompt; conflating the two was a bug that made every version display the same original prompt regardless of what each refinement actually changed) and `ai_profile_id`/`ai_persona_id` set from the request, then repoints `current_version_id` to it. If code and sound metadata are both unchanged, no version is created. Returns `{success: true, changed: bool, version_number: int}` on success or `{success: false, error: "..."}` with HTTP 400/404 on failure (piece not found, no current version to refine, or missing `refinement_prompt`). Requires an existing piece — a not-yet-saved new piece has no row to attach a version to, so the client only calls this when editing an existing piece.

`POST /admin/pieces/[id]` (the main piece edit form's "Save Changes") creates
a new `art_piece_versions` row whenever the submitted HTML/CSS/JS differs
from the current version's stored code — covering manual code edits made
directly in the HTML/CSS/JS tabs — instead of updating the current version's
row in place. Its version's `prompt` falls back to the Metadata tab's
original creative-prompt field, since a manual edit has no "refinement
instruction" of its own. A save that only changes metadata (title,
description, status, etc.) with no code change does not create a new
version. Each `art_piece_versions` row carries its own
`ai_profile_id`/`ai_persona_id` (nullable, no FK — displayed as "(Blank)"
when unset or when the referenced profile/persona no longer exists), and its
own `prompt`, both editable per-version on
`/admin/pieces/[id]/versions/[vid]/edit`; the main piece edit form's Metadata
tab edits the *current* version's AI attribution only (not its prompt — use
the per-version edit page for that).

Both `/pieces/[id]` and `/immersive/pieces/[id]` show each version's `prompt`,
`ai_profile_id`-derived name, and `ai_persona_id`-derived name in a "Versions"
list/section — not just the current version, the full history. If a version
has Tone.js instrumentation, both surfaces also show the stored
`sonic_params.feel` value as "Sound Feel" in the current-version documentation
and in that version's history entry.

`POST /admin/pieces/[id]/versions/[vid]/set-current` repoints the piece's
`current_version_id` to the given version — this is what `/admin/pieces/[id]/versions`
labels "Revert" for any non-current version (with a confirmation prompt), and
is what makes `/pieces/[id]` and every other surface that renders the piece's
"current" code switch to that version's code, including its recorded AI
attribution. Old versions are never deleted as a side effect of this or of
creating a new version — only the explicit "Delete" action on a version row
removes it. The same list's "Preview" link opens
`/immersive/pieces/[id]?version=[vid]` for any version (current or not)
without changing what's live.

`GET /admin/pieces/library` returns a JSON array of active art pieces
(`id`, `title`, `engine`, `thumbnail_url`, `status`) for the Tiptap "Insert art
piece or exhibit" picker.

### Open Graph Images

- `GET /og/posts/[id]`

Returns a generated PNG social card for a published blog post. Missing or
unpublished posts return `404`. Blog post pages point `og:image` and
`twitter:image` to this route when a custom post image is not provided.

### Cron Endpoints

- `POST /api/cron/publish-posts`
- `POST /api/cron/refresh-feeds`

Both cron endpoints require an `X-Cron-Secret` header matching `CRON_SECRET`
and return JSON summaries. `publish-posts` publishes due scheduled posts and
processes pending syndications. `refresh-feeds` refreshes due external feed
sources and reports how many sources/items were processed.

### Rate Limiting

These endpoints now return HTTP `429` when their fixed-window limits are
exceeded:

- `POST /contact`
- `POST /admin/ai/process`
- `POST /admin/ai/describe-image`
- `POST /admin/pieces/generate`
- `POST /admin/pieces/refine-ai`
- admin OAuth login start/callback flow at `/admin/auth/[provider]/*`

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

- `GET /admin/navigation` — defaults to the Site subtab.
- `GET /admin/navigation?tab=site`
- `GET /admin/navigation?tab=admin`
- `POST /admin/navigation/external`
- `POST /admin/navigation/reorder`
- `POST /admin/navigation/[id]/toggle`
- `POST /admin/navigation/[id]/delete`
- `POST /admin/navigation/[id]/label`
- `POST /admin/navigation/[id]/target`

The Navigation admin is split into Site and Admin subtabs. The Site subtab
manages public header navigation through `navigation_items` when available and
falls back to system navigation if the database or table is unavailable. The
Admin subtab manages the owner-configured admin navigation order stored in
`site_settings.admin_nav_order_json`, using the compatibility endpoint
`POST /admin/site-identity/navigation-order`. Signed-in account actions now
live behind a person-menu in the public header; admin users also see the
ordered admin navigation inside that account surface.
