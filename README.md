# Portable PHP CMS Shell

A no-framework PHP application — browser-admin CMS, rendered public site,
blog, portfolio/gallery, generative art pieces, immersive/VR rendering,
AI-assisted content tools, public read APIs, and multi-platform syndication —
designed to be deployable to any host by filling out `.env` (see
`env.example`) and running the database setup below. No code changes should be
required to point an existing deployment's database at a new host, or to stand
up a brand-new site.

This is a portable CMS-backed PHP site with public read APIs. It is not yet a
complete API-first/headless CMS: there is no machine-auth write API, no full
remote admin contract, and no CORS guarantee for browser clients on other
origins unless that is explicitly added later.

## This Deployment

This particular checkout is currently configured for `augmenthumankind.com`,
a small AI-consulting practice. That business identity lives in the
database (`site_settings`, managed `pages`) and `.env` — not in application
code — so a fresh deployment of this same codebase starts from generic
placeholder content (see [SETUP.md](SETUP.md)) rather than
this instance's content.

## Documentation Map

Start here to find the right document. Each concept has one authoritative
home; other files link to it rather than repeat it.

| Document | What it covers | Read it when |
|---|---|---|
| **README.md** (this file) | What the system is, its architecture, the full route map, and how the docs fit together. | Getting oriented, or looking up a route or feature. |
| **[SETUP.md](SETUP.md)** | The canonical step-by-step procedure to install, verify, upgrade, and duplicate a deployment — including installer flags and the schema-change convention. | Standing up or updating a site. |
| **[docs/api.md](docs/api.md)** | The request/response contract for every public and admin route, plus feature-flag mechanics, forms, comments, syndication, and cron. | Integrating with, or changing, an endpoint. |
| **[docs/dependencies.md](docs/dependencies.md)** | Every external dependency: what data leaves the domain, failure modes, config keys, and self-hosting alternatives. | Adding a dependency or auditing privacy/risk. |
| **[ALGORITHMS.md](ALGORITHMS.md)** | The algorithms behind each feature, as pseudocode "recipes" with plain-English instructions and complexity analysis. | Understanding *how* a pipeline works internally. |

### Project memory & history

These files track the project's evolution and standing rules. They are
maintained alongside the code and are not needed to run the site:

- **CONSTRAINTS.md** — permanent, non-negotiable rules.
- **DECISIONS.md** — the full, dated session-by-session change log (older
  sessions archived in **docs/decisions-archive.md**).
- **MEMORY.md** — a distilled, topical index of standing decisions, each
  pointing back to its `DECISIONS.md` source.
- **DESIGN.md** — the site's aesthetic identity and design-system intent.

## Routes

- `/` — mission-led homepage
- `/services` — three focused service offers
- `/notes` — lightweight field notes landing page
- `/contact` — reCAPTCHA-protected inquiry form
- `/portfolio` — public work gallery
- `/portfolio/collections` — permanent redirect to `/portfolio/exhibit-collections`
- `/portfolio/categories` — permanent redirect to `/portfolio/art-media`
- `/portfolio/category/[slug]` — permanent redirect to `/portfolio/art-media/[slug]`
- `/portfolio/exhibit-collections` — public exhibit collections archive
- `/portfolio/art-media` — public art media index for pieces
- `/portfolio/art-media/[slug]` — public art media detail
- `/portfolio/exhibits` — public exhibits archive
- `/portfolio/platform-collections` — public migrated platform collections archive
- `/portfolio/pieces` — public migrated art pieces archive
- `/portfolio/exhibit/[slug]` — public exhibit detail
- `/portfolio/collection/[slug]` — public exhibit collection detail
- `/media/[id]` and `/image/[id]` — public blob-serving routes for stored media
- `/blog` — canonical public blog feed
- `/blog/posts/[id]` — public blog post detail page
- `/og/posts/[id]` — generated PNG social card for a published blog post
- `/blog/categories` and `/blog/category/[slug]` — public blog category index and detail pages
- `/blog/feeds` — catalog page of RSS, Atom, JSON, and mf2 feeds
- `/search` — public blog post search
- `/pieces` — public gallery listing of generative art pieces
- `/pieces/[id]` — public render page of a generative art piece
- `/pieces/[id]/download` — downloadable ZIP export for the piece's current or selected version, with `index.html` as the single manual entry point plus bundled runtime/media/source files; `surface=immersive` exports the same piece in its immersive gallery context
- `/collections` and `/collections/[slug]` — public archive/detail for migrated platform art collections
- `/collections/[slug]/download` — downloadable ZIP export for the full platform collection gallery wall, with all supported pieces/images in one local immersive `index.html`
- `/embed/pieces/[id]` — public embeddable HTML of a generative art piece
- `/embed/pieces/[id]/data` — public JSON feed of art piece parameters and source code
- `/immersive/pieces/[id]` — public 3D full-immersion stage or gallery room framing with viewer zoom, movement, keyboard, pointer, touch, fullscreen, `Download ZIP`, and `Download PNG` controls where supported
- `/immersive/collections/[slug]` — public progressive rendering collection wall with full-gallery `Download ZIP` export and wall/slideshow PNG capture (`/immersive/exhibits/[slug]` 301-redirects here for legacy links)
- `/feeds/mf2` — mf2 JSON format feed export

Admin routes are flat and protected by OAuth login:

- `/admin` — admin dashboard with expanded metrics (exhibits, art media, exhibit collections, posts, comments, media, syndications, trash, etc.)
- `/admin/pages` — CMS managed pages
- `/admin/posts` — blog posts CRUD (draft, published, scheduled)
- `/admin/comments` — comment and reaction moderation
- `/admin/categories` — blog post categories CRUD
- `/admin/art-media` — piece taxonomy CRUD
- `/admin/exhibits` — portfolio exhibits CRUD
- `/admin/exhibit-collections` — native exhibit collections CRUD
- `/admin/media` — media library uploads and migrated media assets (with AI alt-text generation for images; GLTF/GLB 3D models up to 64 MB when the `media_models` feature is enabled, loadable into AI-generated Three.js/A-Frame pieces by referencing their `/media/{id}` path in the prompt)
- `/admin/feed-sources` — RSS/Atom feed ingestion sources and approval queue
- `/admin/site-identity` — site settings and assets management
- `/admin/user-profiles` — public user account and profile photo management
- `/admin/ai-settings` — AI profiles, API keys, personas, and preferred workflow profiles; configuration stays accessible even when AI runtime features are disabled
- `/admin/features` — content-safe module and AI runtime toggles; disabled content modules stay manageable while records exist, and AI buttons are hidden per use-case flag
- `/admin/platform-connections` — syndication platforms (Bluesky, WordPress, Blogger, Substack, LinkedIn, Meta) with OAuth credential acquisition and a diagnostics page
- `/admin/forms` — database-owned Contact Form and Newsletter Signup records, fields, signups, and form settings
- `/admin/pieces` — platform generative art pieces, version history, and starter templates (with AI-driven generation at `/admin/pieces/generate`, including C2.js Interactive and A-Frame Experimental modes, and AI refinement at `/admin/pieces/refine-ai`; when the `ai_pieces_sound` feature is enabled, generation, manual editing, and AI Refine can add optional Tone.js sonification whose "Describe the feel" / "Tone Feel" value is stored per version, shown in regular and immersive piece documentation, and heard as three concurrent voices — ambient, movement, and a melodic voice playable via an on-screen piano keyboard, the physical keyboard (A S D F G H J K L ; / W E T Y U O P), or camera hand-tracking — in the regular and immersive views. The piece editor's Audio tab lets an admin toggle which voices are publicly visible and tune octave range/filter cutoff/resonance per piece. Single-piece ZIP downloads offer a picker letting the downloader narrow which admin-allowed optional panels (keyboard, hand-tracking) ride along in their specific export; hand-tracking is excluded from collection/exhibit-wall ZIP exports entirely, regardless of a piece's own config, to keep collection download size bounded.)
- `/admin/ai/process` — AI text improvement endpoint (used by the Tiptap editor)
- `/admin/ai/describe-image` — AI alt-text generation endpoint (used by the media library)
- `/admin/trash` — trash bins for soft-deleted content
- `/admin/navigation` — custom menu headers registry

Public read APIs:

- `/api/site` — public site identity, canonical origin, theme basics, logo/CTA metadata, feed links, and color tokens
- `/api/navigation` — public navigation items as rendered by the site header
- `/api/pages` — published page index with slugs and public metadata
- `/api/p/[slug]` — one published page plus sections
- `/api/posts`, `/api/posts/[id]`, `/api/categories`, `/api/art-pieces`, `/api/collections` — existing public content/compatibility feeds

Cron endpoints:

- `POST /api/cron/publish-posts` — publishes due scheduled posts
- `POST /api/cron/refresh-feeds` — refreshes due external feed sources

## Portfolio Notes

- `/portfolio` is a sampler page, not the full archive. Each visible section now loads a small initial batch and keeps fetching more cards as the visitor scrolls.
- `Exhibit Collections` is the public/admin name for the native `collections` model that groups exhibits.
- `Art Media` is the public/admin name for the portfolio taxonomy stored in `categories` with `category_scope='portfolio'`, shared by pieces and exhibits.
- `Categories` in the admin now refers only to blog/post categories stored in `categories` with `category_scope='blog'`.

## Immersive Piece Controls

Full `/immersive/pieces/[id]` pages expose viewer-level camera controls for
native Three.js and A-Frame pieces:

- A low-opacity edge HUD keeps controls available without covering the center
  of the piece, brightening on hover, touch, pointer movement, or keyboard
  focus.
- The right edge vertical slider zooms the viewer in and out, is identified by
  a magnifying-glass icon above the slider, and aligns on the same x-axis as
  the site-level expand control.
- The left edge movement controls move the camera forward, backward, left, and
  right, with separate float up/down controls for vertical camera movement.
- Buttons support click/tap and press-and-hold across desktop, tablet, and
  mobile pointer devices.
- Existing keyboard controls remain available on desktop: arrow keys and
  WASD move the viewer.
- Existing pointer gestures remain available where supported: drag/pan,
  wheel or pinch zoom, and tap/click-to-move.

These controls move the viewer camera, not the artwork's own authored
interaction state. Embedded and static immersive iframes stay visually quieter
and do not show the viewer HUD. A-Frame's built-in enter-VR/fullscreen button
is suppressed in immersive piece views so the site-level expand control remains
the single expansion control.

Desktop and supporting tablet browsers still use native fullscreen when
available. iPhone Safari uses Immersive Focus instead: the piece is promoted to
a fixed `visualViewport`-sized mode, page scrolling is locked, safe-area insets
are respected, and the button state uses "Expand immersive view" / "Return to
page" language instead of promising true browser fullscreen.

All immersive surfaces — pieces, collection gallery walls, standalone images,
and downloaded standalone exports — share one stage toolbar anchored to the
TOP of the stage (`immersive_stage_toolbar_markup()` /
`immersive_stage_toolbar_css()` in `public/app/helpers/immersive-chrome.php`,
wired by `setupImmersiveStageChrome()` in `immersive-gallery.js`). Top
placement keeps the toolbar clear of the bottom-center "Enable Motion
Controls" iOS permission button; once motion is granted, the gyroscope toggle
mounts into a reserved slot inside the toolbar instead of floating over it.
The left group holds the view/slideshow button, a standalone `Download PNG`
screenshot button, and the download control for `Download ZIP` (a standalone
button when it's the only download option, as it is on every immersive
surface today — the dropdown-menu form only appears once a surface offers
2+ download choices); the right side holds the sound toggle (where
applicable) and the fullscreen toggle. Whenever the download control does
render as a menu, it supports mouse, touch, and keyboard: Escape closes it
and outside clicks dismiss it.

The view button differs per surface, and is otherwise identical everywhere:

- Collection walls show a slideshow button that opens the full slideshow at
  the active item.
- P5, SVG, and non-interactive C2 pieces show a full-size button that opens
  the same overlay shell as the slideshow, minus Prev/Next and the overlay's
  own download controls (downloads stay in the stage toolbar).
- Interactive C2 pieces open that same overlay with a fully interactive
  iframe — authored click/touch/drag handlers work inside it.
- Three.js and A-Frame pieces render no view button at all: they have no
  gallery full view, and the stage itself is the interactive surface.
- Standalone immersive images show a full-size button that opens the image
  in the overlay.

`Download PNG` captures the rendered immersive surface from the user's
current perspective. For gallery-room pieces and collection walls, that means
the Three.js gallery renderer, not the hidden source canvas. If the full-view
overlay is open (including interactive C2), PNG capture uses that overlay
iframe instead.

`Download ZIP` from immersive piece pages calls the existing
`/pieces/[id]/download` route with `surface=immersive` and a serialized
viewer-state payload. The resulting ZIP still opens through `index.html`, but
that file mounts the local immersive renderer, restores camera/target state
when provided, renders the shared top stage toolbar (view/slideshow button
where applicable, a standalone screenshot button since a standalone export
can't re-download itself, fullscreen), and preserves C2
interactive full-size behavior through the shared full-view overlay. `Download ZIP` from immersive
collection walls and their slideshow overlays calls `/collections/[slug]/download`
instead; that ZIP exports the full local collection gallery wall with all
supported pieces and images, not only the selected or active slide.

## Art Piece Downloads And Templates

> Route contracts (download endpoints, the AI generation/refine request and
> response shapes, and the CMS-media prompt rules) are specified in
> [docs/api.md](docs/api.md#admin-piece-generation-routes); this section is the
> feature overview.

Art pieces store CMS-runtime-compatible HTML/CSS/JS, but public piece pages
also expose `Download ZIP`. The download is a ZIP bundle with `index.html`
as the single manual entry point: recipients should only need to open that
file to run the piece locally. Supporting files such as `styles/piece.css`,
`scripts/piece.js`, `runtime/`, and `media/` are still included so the bundle
remains editable and rehostable as ordinary static web files.

Downloaded bundles vendor the runtime files they need, and `index.html`
rewrites supported CMS media references such as `/image/2`, `image/2`,
`/media/...`, `media/...`, `/api/media-assets/...`, and `api/media-assets/...`
into export-safe local or embedded forms. For the direct `index.html` path,
supported CMS-owned images/media are embedded in a file-open-safe way so
interactive pieces can still export screenshots when opened from a local file.
Regular exports keep the regular standalone piece surface. Immersive-origin
exports use the same ZIP route with `surface=immersive`; those bundles open
directly into the immersive viewer, include fullscreen and PNG controls, and
load local classic-runtime equivalents of the immersive runtime graph so the
page can still mount when opened directly from `file://`.
Regular standalone piece exports now mirror the live regular `/pieces/[id]`
viewer for native movement behavior too: Three.js downloads keep elapsed-time-
scaled WASD/arrow movement plus click/tap-to-move teleport without adding the
immersive viewer HUD, and A-Frame downloads keep authored scene interaction
while restoring the same regular-view keyboard/tap movement contract offline.

The export bootstrap preserves interaction semantics for the engines that need
runtime help:

- Three.js downloads attach OrbitControls to the exported scene, camera, and
  renderer so drag/touch orbiting works even when the authored piece only
  animates. The standalone export bootstraps Three.js, OrbitControls, and
  GLTFLoader from vendored local sources in a way that can still run from
  `index.html`; GLTF/GLB model materials and embedded textures are preserved
  by default when loaded from `/media/{id}`.
- A-Frame downloads receive the live `<a-scene>` and can use authored scene
  events.
- C2.js interactive downloads receive the real canvas, `startFrame`, and safe
  image helpers, so authored pointer/click/touch/drag handlers continue to
  work.
- Regular interactive `c2_interactive`, `three`, and `aframe` downloads include
  fullscreen plus the local screenshot control directly inside `index.html`.
- Local collection-gallery slideshow PNG capture now uses a downloaded-bundle
  bridge plus a narrow export-only slide hook instead of assuming the live
  runtime helper contract is already present. Downloaded immersive piece and
  collection exports ship a local `CreatrPieceDownload` helper before the
  overlay runtime boots, then exported iframe slides expose
  `window.__creatrExportCapture` so the overlay can capture the visible
  surface directly. Ordinary exported canvas slides capture the currently
  visible slide, SVG slides export from their visible SVG surface, and
  A-Frame slides still keep the stricter nonblank validation path they need.
- Immersive piece and collection exports render the same shared top stage
  toolbar as the live surfaces: the engine-gated view/slideshow button, a
  standalone `Download PNG` screenshot button (a standalone export cannot
  re-download itself offline, so there's no download menu at all here),
  and fullscreen.

Starter templates are database-owned and seeded by `scripts/setup-database.php`.
In `/admin/pieces`, the `Art Pieces` subtab holds the current piece list and
actions; the `Templates` subtab edits starter template metadata and
HTML/CSS/JS. The seeded templates demonstrate optional CMS media usage with
`/image/2` as a resizable foreground example and `/image/3` as a full-frame
background example, using engine-appropriate sizing rather than asset-tag
dimensions.

AI piece prompts can also name existing CMS media explicitly. Prompt language
now treats image-style IDs and media-asset IDs as parallel inputs rather than
implicitly interchangeable aliases:

- `image ID 3`, `photo ID 3`, and `picture ID 3` authorize `/image/3`
- `media ID 4` or `/media/4` authorizes native uploaded media, including
  GLTF/GLB model files served through `/media/4`
- `media asset ID 4` authorizes `/api/media-assets/4`
- Mixed prompts such as `use image ID 3 and media asset ID 4` authorize both
  route families in the same generated piece

This is intentionally explicit. The system does not auto-convert an
`/image/[id]` reference into `/api/media-assets/[id]`, or vice versa, just
because the underlying image might correspond to both records. The prompt form
chosen by the author determines which same-origin CMS path family the model may
introduce.

## Deployed File Layout

The production document root should include:

- `index.php`
- `.htaccess`
- `assets/`
- `app/` — MVC layer (controllers, models, views, helpers)
- `vendor/` — Composer dependencies, including PHPMailer

There is no `uploads/` directory. Thumbnails, images, and all other binary assets
are stored as BLOBs in the `media_files` MySQL table and served via `/image/{id}`
or `/media/{id}`. The `public/uploads/` path is gitignored.

The FTP deploy action may also create `.ftp-deploy-sync-state-public.json` in
the document root. That file is deploy metadata, not part of the app.

**Hostinger note:** if an old Hostinger Website Builder site still appears at
`/`, confirm the domain is assigned to the PHP hosting document root
(`public_html`) rather than Website Builder, then purge any Hostinger/cache/
CDN layer. The app's `.htaccess` sets `DirectoryIndex index.php`, so the
homepage should serve from the root once the domain points at `public_html`.
This step is specific to Hostinger's product split between Website Builder
and PHP hosting — other hosts don't have this concern.

## Forms Configuration

The `/contact` page is a managed system page with a required Contact Form
section. Forms are configured in `/admin/forms`; `.env` values are used as
fallback/backfill when database settings are empty. The Contact Form uses Google
reCAPTCHA v3 and standard SMTP — any provider works (Gmail, AWS SES, Mailgun,
Hostinger, etc.), since `SMTP_USERNAME` does not need to match
`SMTP_FROM_EMAIL`. Create a protected `.env` file in the deployed document root
using `env.example` as the template.

Required values:

- `RECAPTCHA_SITE_KEY`
- `RECAPTCHA_SECRET_KEY`
- `RECAPTCHA_MIN_SCORE`
- `SMTP_HOST` — your SMTP provider's hostname
- `SMTP_PORT` — typically `465` with `smtps`, or `587` with `starttls`
- `SMTP_ENCRYPTION` — `smtps` or `starttls`
- `SMTP_USERNAME`
- `SMTP_PASSWORD`
- `SMTP_FROM_EMAIL` — must be a verified sender address for your SMTP provider
- `SMTP_FROM_NAME`
- `CONTACT_TO_EMAIL` — the inbox that receives form submissions; it can be the
  same address as `SMTP_FROM_EMAIL`

Do not commit real secret values.

The installer seeds both Contact Form and Newsletter Signup. Ordinary forms
email the configured recipient and don't store payloads; Newsletter Signup is
the storage exception (it persists email/consent to `newsletter_subscribers`
and doesn't send by default). Full form behavior and the database-vs-`.env`
settings model: [docs/api.md § Forms CMS](docs/api.md#forms-cms).

For reCAPTCHA, create a Google reCAPTCHA v3 property for the public domain.
Use the generated site key for `RECAPTCHA_SITE_KEY` and the generated secret
key for `RECAPTCHA_SECRET_KEY`. If you want to test the complete browser flow
locally, include your local test host in the reCAPTCHA domain settings.

The contact form only sends outbound email through SMTP — IMAP settings are
never used by this app.

## Database Configuration

Everything except generic starter/error states is stored in MySQL: managed
pages, navigation, blog, portfolio, media, forms, admin identities, auth
records, AI settings, and public read API content.

Required values:

- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

The public mission/contact-style starter routes have generic placeholder or
configuration-error behavior before setup is complete. Managed pages,
`/portfolio/*`, `/blog/*`, public APIs, and `/admin/*` require a configured
database.

Admin sign-in also requires at least one OAuth provider to be configured:

- GitHub: `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET`, and
  `ADMIN_GITHUB_USERNAMES`
- Google: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, and
  `ADMIN_GOOGLE_EMAILS`

Register exactly one callback URL per provider:

- GitHub: `https://yourdomain.com/auth/github/callback`
- Google: `https://yourdomain.com/auth/google/callback`

Feature-gated values are only required when you enable those features:
`AI_SETTINGS_ENCRYPTION_KEY` is required before storing AI keys, platform
OAuth app secrets, platform access tokens, or database-owned reCAPTCHA
secrets. `RECAPTCHA_*` and `SMTP_*` are required for live contact-form email
delivery. `CRON_SECRET` is required only for protected scheduled-task
endpoints.

Feature toggles are content-safe. Turning off Blog, Pieces, Exhibits, or
Collections hides new creation/import actions, but existing records and related
management surfaces remain reachable while there is content to manage. Turning
off AI runtime toggles hides matching AI buttons and blocks the endpoints, but
does not remove AI profiles, keys, personas, or preferred profile settings.
Full flag semantics and the admin toggle contract:
[docs/api.md § Feature Flags](docs/api.md#feature-flags-content-safe-module-toggles).

### Setting up a fresh database

Setup, verification, upgrade, and duplication are one procedure, documented
step by step in **[SETUP.md](SETUP.md)** (prerequisites → env → installer →
readiness check → first admin login → configuration). The short version: fill
out `.env`, create an empty MySQL database, then run the idempotent installer —
it probes `INFORMATION_SCHEMA` before every change, never destroys data, and is
safe to re-run:

```sh
php scripts/setup-database.php                     # apply (add --dry-run to preview)
php scripts/check-portable-launch-readiness.php    # verify
```

See [SETUP.md](SETUP.md) for the installer flags (`--dry-run`,
`--with-example-content`, `--yes`), targeting a different database with process
env vars, the first-login flow, and post-install configuration.

### Multi-site deployments

This codebase is copied as-is into any number of independent site deployments;
each gets its own MySQL database, `.env`, and OAuth apps, with **no code
changes**. The step-by-step duplication procedure is
[SETUP.md § 10](SETUP.md#10-duplicating-to-another-site).

### Adding a schema change

Every schema change ships as **both** a dated `docs/migrations/YYYY-MM-DD-*.sql`
file (the record) and a probe-guarded step in `scripts/setup-database.php` (the
mechanism); `schema.sql` stays frozen. The full convention — including the
`scripts/*.sql` files that are **not** part of fresh-install setup — lives in
**[SETUP.md](SETUP.md#adding-a-schema-change-maintainers)**.

## Media Uploads

The admin media picker accepts images and videos and stores them in
`media_files` as blobs. New native uploads now land as `draft` assets until
their metadata is confirmed; only `ready` assets are insertable from editor
pickers. Video assets can optionally link a poster image asset for thumbnail
views. `public/.htaccess` sets these shared-hosting upload limits:

- `upload_max_filesize=64M`
- `post_max_size=72M`
- `max_execution_time=120`
- `max_input_time=120`

If your host returns a 500 after deployment because it rejects `php_value`
directives in `.htaccess` (this happens on some shared hosts, Hostinger
included), remove those lines from `.htaccess` and set the same limits in
your host's PHP settings panel instead.

### Production Verification

After deployment and `.env` setup (substitute your real domain below):

1. Confirm `https://yourdomain.com/contact` loads the form with an
   enabled submit button.
2. Submit an incomplete form and confirm inline validation errors appear.
3. Submit a complete test inquiry and confirm the page stays on `/contact`
   with the inline success panel.
4. Confirm the message arrives at `CONTACT_TO_EMAIL`.
5. Confirm the delivered message uses the configured `SMTP_FROM_EMAIL` as
   `From` and the submitter email as `Reply-To`.
6. Confirm direct requests to `https://yourdomain.com/.env` and
   `https://yourdomain.com/vendor/autoload.php` are denied.

If a complete inquiry fails, check the reCAPTCHA admin console for the domain,
action name `contact_submit`, and score; then check your SMTP provider's host,
port, encryption, username, password, and verified sender settings.

You can check the local configuration shape without sending email or printing
secrets:

```sh
php scripts/verify-contact-config.php
```

## Run Locally

The canonical development server command is:

```sh
php -S 127.0.0.1:8080 -t public public/index.php
```

Then open:

- `http://127.0.0.1:8080/`
- `http://127.0.0.1:8080/services`
- `http://127.0.0.1:8080/notes`
- `http://127.0.0.1:8080/contact`
- `http://127.0.0.1:8080/portfolio`
- `http://127.0.0.1:8080/admin`

The legacy `platform/` application has been removed. New deployments should
configure only this PHP app's `.env`, then run `php scripts/setup-database.php`
and `php scripts/check-portable-launch-readiness.php`.

## Scheduled Tasks (GitHub Actions)

The repository includes `.github/workflows/scheduled-tasks.yml`, which runs every 30 minutes and handles two background tasks:

- **Feed refresh** — calls `POST $PUBLIC_SITE_URL/api/cron/refresh-feeds` on the PHP app to refresh due external feed sources.
- **Post publishing** — calls `POST $PUBLIC_SITE_URL/api/cron/publish-posts` on the PHP app to transition any scheduled posts whose `scheduled_at` time has passed.

Both jobs can also be triggered manually from the Actions tab via `workflow_dispatch`.

### Required GitHub repository secrets

Set these under **Settings → Secrets and variables → Actions**:

| Secret | Description |
|---|---|
| `CRON_SECRET` | A random secret used to authenticate cron requests. Generate one with `openssl rand -hex 32`. |
| `PUBLIC_SITE_URL` | The fully-qualified origin of the deployed site, e.g. `https://yourdomain.com`. No trailing slash. |

### Required `.env` value on the deployed site

The PHP app's `POST /api/cron/publish-posts` endpoint validates the same `X-Cron-Secret` header. Add the matching value to the deployed `.env`:

```
CRON_SECRET=<same value as the GitHub secret>
```

### Opportunistic feed refresh

In addition to the cron job, `refresh_due_feeds()` fires on two page loads so feeds stay fresh during normal site usage:

- Any visit to `/admin/feed-sources` (admin)
- Any visit to `/blog/feeds` (public)

Each enabled feed source is checked against its `next_fetch_at` cadence; only overdue sources make an outbound HTTP request.

## One-Time Migration (legacy platform data only)

Only relevant for deployments that already contain imported legacy art-piece
or collection thumbnail URLs. A brand-new site has nothing for this script to
do. After the first deploy following such an import, SSH into your host and
run:

```sh
php scripts/migrate-thumbnails-to-db.php --execute
```

This backfills all existing art-piece and platform-collection thumbnails into
`media_files` and rewrites their `thumbnail_url` to `/image/{id}`. The script
is idempotent: rows already pointing to `/image/*` are skipped, so it is safe
to re-run.

## Notes

- MySQL is required for admin CMS, navigation registry, and portfolio content.
- Form submissions are emailed only; they are not stored by the app.
- All binary assets (thumbnails, images, media) are stored as BLOBs in `media_files` and served via `/image/{id}` or `/media/{id}`. There is no `uploads/` directory on disk.
- Public routes are treated as durable. If they move later, add permanent redirects.
- Deployments should upload the contents of `public/` into the hosting document root, not the repository root.
