# PHP CMS Shell

A no-framework PHP application — blog, portfolio/gallery, generative art
pieces, immersive/VR piece and collection rendering, AI-assisted content
tools, and multi-platform syndication — designed to be deployable to any
host by filling out `.env` (see `env.example`) and running the database
setup below. No code changes should be required to point an existing
deployment's database at a new host, or to stand up a brand-new site.

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
- `/collections` and `/collections/[slug]` — public archive/detail for migrated platform art collections
- `/embed/pieces/[id]` — public embeddable HTML of a generative art piece
- `/embed/pieces/[id]/data` — public JSON feed of art piece parameters and source code
- `/immersive/pieces/[id]` — public 3D full-immersion stage or gallery room framing
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
- `/admin/user-profiles` — admin users, AI vendor configurations, API keys, and profile photo uploads
- `/admin/platform-connections` — syndication platforms (Bluesky, WordPress, Blogger, Substack, LinkedIn, Meta) with OAuth credential acquisition and a diagnostics page
- `/admin/pieces` — platform generative art pieces and version history (with AI-driven generation at `/admin/pieces/generate` and AI refinement at `/admin/pieces/refine-ai`)
- `/admin/ai/process` — AI text improvement endpoint (used by the Tiptap editor)
- `/admin/ai/describe-image` — AI alt-text generation endpoint (used by the media library)
- `/admin/trash` — trash bins for soft-deleted content
- `/admin/navigation` — custom menu headers registry

Cron endpoints:

- `POST /api/cron/publish-posts` — publishes due scheduled posts
- `POST /api/cron/refresh-feeds` — refreshes due external feed sources

## Portfolio Notes

- `/portfolio` is a sampler page, not the full archive. Each visible section now loads a small initial batch and keeps fetching more cards as the visitor scrolls.
- `Exhibit Collections` is the public/admin name for the native `collections` model that groups exhibits.
- `Art Media` is the public/admin name for the portfolio taxonomy stored in `categories` with `category_scope='portfolio'`, and it now applies to pieces rather than exhibits.
- `Categories` in the admin now refers only to blog/post categories stored in `categories` with `category_scope='blog'`.

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

## Contact Form Configuration

The `/contact` form uses Google reCAPTCHA v3 and standard SMTP — any
provider works (Gmail, AWS SES, Mailgun, Hostinger, etc.), since
`SMTP_USERNAME` does not need to match `SMTP_FROM_EMAIL`. Create a protected
`.env` file in the deployed document root using `env.example` as the
template.

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

For reCAPTCHA, create a Google reCAPTCHA v3 property for the public domain.
Use the generated site key for `RECAPTCHA_SITE_KEY` and the generated secret
key for `RECAPTCHA_SECRET_KEY`. If you want to test the complete browser flow
locally, include your local test host in the reCAPTCHA domain settings.

The contact form only sends outbound email through SMTP — IMAP settings are
never used by this app.

## Database Configuration

Everything except the static mission/contact pages — blog, portfolio,
managed pages, admin, auth, AI features — is stored in MySQL.

Required values:

- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

The public mission and contact routes still have safe static behavior if the
database is unavailable. Managed pages, `/portfolio/*`, and `/admin/*` require
the database.

### Setting up a fresh database

`schema.sql` alone is **not** sufficient — it only covers the native
portfolio/CMS tables and has a forward dependency (`post_sections` → `posts`)
on a table created later in the sequence. Apply these files, against an
empty database, in exactly this order (verified end-to-end against a fresh
MySQL 9 database with zero errors):

```sh
mysql -h "$DB_HOST" -u "$DB_USER" -p "$DB_NAME" < schema.sql
mysql -h "$DB_HOST" -u "$DB_USER" -p "$DB_NAME" < migrations/2026-06-14-platform-assimilation.sql
mysql -h "$DB_HOST" -u "$DB_USER" -p "$DB_NAME" < migrations/2026-06-15-comments-polymorphic.sql
mysql -h "$DB_HOST" -u "$DB_USER" -p "$DB_NAME" < docs/migrations/2026-06-17-admin-ia-and-canonical-origin.sql
mysql -h "$DB_HOST" -u "$DB_USER" -p "$DB_NAME" < docs/migrations/2026-06-18-ai-personas.sql
mysql -h "$DB_HOST" -u "$DB_USER" -p "$DB_NAME" < docs/migrations/2026-06-18-operational-hardening.sql
mysql -h "$DB_HOST" -u "$DB_USER" -p "$DB_NAME" < docs/migrations/2026-06-19-media-draft-confirm.sql
mysql -h "$DB_HOST" -u "$DB_USER" -p "$DB_NAME" < scripts/add-post-sections-table.sql
php scripts/apply-portfolio-taxonomy-schema.php
php scripts/apply-portfolio-ordering-schema.php
```

None of the `.sql` files in this list are safe to re-run against a database
that already has their columns (plain MySQL doesn't support
`ADD COLUMN IF NOT EXISTS`) — run each exactly once on a fresh database. The
two `apply-*.php` scripts at the end *are* idempotent and safe to re-run.

The remaining files in `scripts/*.sql` (`add-wrapper-class-column.sql`,
`add-exhibit-content-slide.sql`) and `scripts/migrate-home-nav-label.sql` are
**not** part of fresh-install setup — their columns already live in
`schema.sql` for new installs, and the nav-label script is a one-off content
fixup for this instance's pre-existing data. They only matter when patching
an older, already-deployed database that predates those columns. Likewise,
`seed_homepage.sql` and `seed_phase2_pages.sql` are optional example content
for `/`, `/services`, and `/notes`, not required schema — without them, those
routes fall back to generic placeholder copy until you create real pages
from the admin Pages screen.

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
