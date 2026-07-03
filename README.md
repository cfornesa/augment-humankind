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
placeholder content (see "Setting up a fresh database" below) rather than
this instance's content.

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
- `/pieces/[id]/download` — downloadable single-file HTML export for the piece's current version, using CDN engine imports and absolute CMS media URLs
- `/collections` and `/collections/[slug]` — public archive/detail for migrated platform art collections
- `/embed/pieces/[id]` — public embeddable HTML of a generative art piece
- `/embed/pieces/[id]/data` — public JSON feed of art piece parameters and source code
- `/immersive/pieces/[id]` — public 3D full-immersion stage or gallery room framing with viewer zoom, movement, keyboard, pointer, and touch controls where supported
- `/immersive/collections/[slug]` — public progressive rendering collection wall (`/immersive/exhibits/[slug]` 301-redirects here for legacy links)
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
- `/admin/media` — media library uploads and migrated media assets (with AI alt-text generation for images)
- `/admin/feed-sources` — RSS/Atom feed ingestion sources and approval queue
- `/admin/site-identity` — site settings and assets management
- `/admin/user-profiles` — public user account and profile photo management
- `/admin/ai-settings` — AI profiles, API keys, personas, and preferred workflow profiles; configuration stays accessible even when AI runtime features are disabled
- `/admin/features` — content-safe module and AI runtime toggles; disabled content modules stay manageable while records exist, and AI buttons are hidden per use-case flag
- `/admin/platform-connections` — syndication platforms (Bluesky, WordPress, Blogger, Substack, LinkedIn, Meta) with OAuth credential acquisition and a diagnostics page
- `/admin/forms` — database-owned Contact Form and Newsletter Signup records, fields, signups, and form settings
- `/admin/pieces` — platform generative art pieces, version history, and starter templates (with AI-driven generation at `/admin/pieces/generate`, including C2.js Interactive and A-Frame Experimental modes, and AI refinement at `/admin/pieces/refine-ai`)
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

## Art Piece Downloads And Templates

Art pieces store CMS-runtime-compatible HTML/CSS/JS, but public piece pages
also expose `Download HTML`. The downloaded file is a complete standalone HTML
document with the current version's code, engine imports, a small bootstrap,
and no admin, embed, or immersive presentation controls.

Downloaded files reference CDN runtimes for p5.js, C2.js, Three.js, and
A-Frame, and rewrite CMS media references such as `/image/2`, `/media/...`, and
`/api/media-assets/...` to absolute site URLs. The files therefore work in
another browser context with internet access to the CDN and live CMS media
routes, but they are not offline bundles.

The export bootstrap preserves interaction semantics for the engines that need
runtime help:

- Three.js downloads attach OrbitControls to the exported scene, camera, and
  renderer so drag/touch orbiting works even when the authored piece only
  animates.
- A-Frame downloads receive the live `<a-scene>` and can use authored scene
  events.
- C2.js interactive downloads receive the real canvas, `startFrame`, and safe
  image helpers, so authored pointer/click/touch/drag handlers continue to
  work.

Starter templates are database-owned and seeded by `scripts/setup-database.php`.
In `/admin/pieces`, the `Art Pieces` subtab holds the current piece list and
actions; the `Templates` subtab edits starter template metadata and
HTML/CSS/JS. The seeded templates demonstrate optional CMS media usage with
`/image/2` as a resizable foreground example and `/image/3` as a full-frame
background example, using engine-appropriate sizing rather than asset-tag
dimensions.

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

The installer seeds both Contact Form and Newsletter Signup. Contact and other
ordinary forms email the configured recipient and do not store payloads.
Newsletter Signup stores email/consent rows in `newsletter_subscribers`,
defaults consent to true, does not require a recipient email, and does not send
email by default.

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

### Setting up a fresh database

> **Full walkthrough:** [SETUP.md](SETUP.md) is the step-by-step setup
> procedure (prerequisites → env → installer → readiness check → first
> admin login → configuration), written to be followed by a human or an
> agent. The section below is the installer reference.

One command handles everything — a completely empty database or an existing
one that needs to catch up:

```sh
php scripts/setup-database.php
```

The installer is **idempotent and probe-based**: every table, column, and
index is checked against `INFORMATION_SCHEMA` before being created, so the
same command works on an empty database, resumes after a partial failure,
and safely no-ops on a database that is already up to date. It never
destroys data. Flags:

- `--dry-run` — report which schema changes are applied/missing without
  writing anything. Safe to run against production.
- `--with-example-content` — additionally seed example `/`, `/services`, and
  `/notes` pages and the Celestial theme code. Each seed is skipped when the
  target content already exists, so it can never overwrite a customized
  site. Without this flag those routes fall back to generic placeholder
  copy until you create real pages from the admin Pages screen.
- `--yes` — skip the existing-data confirmation prompt. Before applying
  anything, the installer scans the target database and, if it finds
  existing entries (admins, pages, posts, media, …), prints a summary and —
  when run interactively — asks for confirmation. Non-interactive runs
  (CI/cron) proceed automatically after printing the summary, so
  `git pull && php scripts/setup-database.php` stays unattended-safe.

Process environment variables always win over `.env`, so a different
database can be targeted without editing config:

```sh
DB_HOST=127.0.0.1 DB_NAME=my_new_site DB_USER=root DB_PASS=... php scripts/setup-database.php
```

After setup, run the read-only readiness checker:

```sh
php scripts/check-portable-launch-readiness.php
```

It reports blocking database/schema issues as failures and feature-gated
configuration gaps as warnings.

The installer supersedes the old manual `mysql < file.sql` sequence, which
(a) fails on MySQL 9.x local auth, (b) breaks on a fresh database because
`schema.sql` was rolled forward and now overlaps two later migrations, and
(c) omitted `docs/migrations/2026-06-21-art-piece-version-draft-attempts.sql`
and `docs/migrations/2026-07-02-system-page-identity.sql`. The dated files in
`migrations/` and `docs/migrations/` remain the documentation of record for
each change; the installer is the mechanism that applies them.

### Multi-site deployments

This codebase is designed to be copied as-is into any number of independent
site deployments. Each deployment gets its own database, `.env`, and OAuth
apps — no code changes. To stand up a new site: copy the code, fill out
`.env`, run `php scripts/setup-database.php`, then sign in at `/admin` to
complete first-run setup. To keep an existing deployment aligned after
pulling code updates: run `php scripts/setup-database.php` again.

### Adding a schema change

Every future schema change must ship as **both**:

1. A new dated file in `docs/migrations/YYYY-MM-DD-name.sql` — the
   documentation of record.
2. One probe-guarded step appended to the manifest in
   `scripts/setup-database.php` — the mechanism that applies it everywhere.

`schema.sql` is **frozen** — do not roll new changes into it. It remains the
bootstrap for the twelve core tables only; everything after it is a manifest
step. This keeps `git pull && php scripts/setup-database.php` sufficient to
align every deployment.

The remaining files in `scripts/*.sql` (`add-wrapper-class-column.sql`,
`add-exhibit-content-slide.sql`) and `scripts/migrate-home-nav-label.sql` are
**not** part of fresh-install setup — their columns already live in
`schema.sql` for new installs, and the nav-label script is a one-off content
fixup for this instance's pre-existing data.

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

Before manually deleting the legacy `platform/` application, keep this server
running and run:

```sh
php scripts/check-platform-deletion-readiness.php --base-url=http://127.0.0.1:8080
```

The readiness command must pass, and manual route/admin testing should still be
completed before deleting `platform/`.

## Scheduled Tasks (GitHub Actions)

The repository includes `.github/workflows/scheduled-tasks.yml`, which runs every 30 minutes and handles two background tasks:

- **Feed refresh** — calls `platform/scripts/scheduled-feed-refresh.sh`, which posts to `$PUBLIC_SITE_URL/api/feed-sources/refresh` on the Node.js platform server.
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

Only relevant if you migrated data from the legacy platform per
`docs/platform-assimilation-plan.md` — a brand-new site with no migrated
art pieces/collections has nothing for this script to do. After the first
deploy following a migration, SSH into your host and run:

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
