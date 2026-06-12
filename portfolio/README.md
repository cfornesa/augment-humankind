# Fornesus Art

Personal art archive — PHP + MySQL, no framework.

## Development

```bash
php -c php.dev.ini -S localhost:8000 -t public public/index.php
```

This starts PHP's built-in server with the router argument and a repo-local PHP config that raises upload limits for the app's short-video workflow. The site runs at `http://localhost:8000`.

## GitHub Actions

Two GitHub Actions workflows are intentionally tracked separately:

- `.github/workflows/deploy.yml` handles deployment to Hostinger. It runs on pushes to `main` and also exposes `workflow_dispatch` so deployment can be triggered manually from the Actions tab.
- `.github/workflows/feed-refresh.yml` handles the scheduled feed refresh job and its manual refresh trigger.

If deployment ever appears to "stop working" because `deploy.yml` was overwritten or replaced, restoring these two workflow files and pushing that change to `main` re-registers the workflows for future runs. This restores GitHub's ability to discover and trigger the deploy workflow again, but it does not guarantee FTP connectivity or a successful deploy run; FTP success still depends on the external host, secrets, and network path at runtime.

## Requirements

- PHP 8.1+
- MySQL 8+ or MariaDB 10.6+
- Apache with `mod_rewrite` (or Nginx with equivalent `try_files` config)

## Setup

### 1. Clone and configure

```bash
cp env.example .env
```

Fill in `.env`:

```
DB_HOST=your_host
DB_NAME=your_db
DB_USER=your_user
DB_PASS=your_password
GITHUB_CLIENT_ID=...
GITHUB_CLIENT_SECRET=...
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
ADMIN_GITHUB_USERNAMES=yourgithubuser
ADMIN_GOOGLE_EMAILS=you@example.com
RECAPTCHA_SITE_KEY=...
RECAPTCHA_SECRET_KEY=...
RECAPTCHA_SCORE_THRESHOLD=0.5
```

`ADMIN_GITHUB_USERNAMES` and `ADMIN_GOOGLE_EMAILS` are bootstrap allowlists. The first approved login from each provider creates an `admin_identities` record, and later `/admin` access is tied to that stored identity instead of a shared password.

`RECAPTCHA_SITE_KEY` / `RECAPTCHA_SECRET_KEY` are optional. Register both your local and production domains under one reCAPTCHA v3 site key at https://www.google.com/recaptcha/admin. If left blank (or invalid), `/contact` and `/about` keep working — submissions are just saved with `is_flagged = 1` for review in `/admin/messages` instead of being score-checked. The honeypot and time-trap checks run regardless and need no configuration.

### 2. Create the database

**Fresh install** — import `schema.sql`:

```bash
mysql -h HOST -u USER -p DB_NAME < schema.sql
```

**Existing database** — run migrations in order:

| File | When to run |
|---|---|
| `migrate_phase2.sql` | Adds categories / exhibits schema |
| `migrate_phase4_blob.sql` | Adds `data` + `mime_type` columns to `media_files` |
| `migrate_phase4_cleanup.sql` | Drops legacy `path` / `subfolder` columns (run after verifying blob migration) |
| `migrate_phase6_pages.sql` | Creates `pages` + `page_sections`; seeds Bio and Contact pages |
| `migrate_phase7_oauth_carousel.sql` | Adds admin OAuth identities, artwork media items, and media metadata |
| `migrate_pages_softdelete.sql` | Adds `deleted_at` to `pages` for soft delete / trash |
| `migrate_phase8_placard.sql` | Adds museum placard fields (`artist_name`, `medium`, `dimensions`, `placard_notes`) to `artworks` and `caption` to `artwork_media_items` |
| `migrate_phase9_slide_title.sql` | Adds per-slide `title` to `artwork_media_items` |

Run each via your MySQL client or PHP PDO:

```bash
mysql -h HOST -u USER -p DB_NAME < migrate_pages_softdelete.sql
```

After `migrate_phase7_oauth_carousel.sql`, run the legacy artwork backfill once so existing single-piece works get an initial carousel slide:

```bash
php migrate_legacy_artwork_media.php
```

### 3. Point the web server

Set `DocumentRoot` to the `public/` directory. If your server root is the project root, the root `.htaccess` redirects to `public/` automatically.

### 4. Log in

Visit `/admin/login`.

To confirm OAuth configuration before testing login:

```bash
php scripts/check_oauth_setup.php
```

Register these callback URLs in your OAuth apps:

- GitHub: `https://your-domain/admin/auth/github/callback`
- Google: `https://your-domain/admin/auth/google/callback`

GitHub should request `read:user user:email`. Google should request `openid email profile`.

---

---

## Architecture

Plain PHP with a front-controller router. No ORM, no framework. Request flow:

```
public/index.php  →  regex router  →  Controller::method()
                                            ↓
                                       Model (static PDO)
                                            ↓
                                       require view.php
```

Admin authentication now uses approved GitHub and Google OAuth identities, with access gated by `.env` allowlists and persisted in `admin_identities`. The old password-only `.env` flow is no longer the source of truth for `/admin`.

The public layout centralizes SEO and accessibility concerns in one place:

- canonical URLs, meta descriptions, Open Graph, Twitter, and optional `robots` tags
- `og:image:alt` / `twitter:image:alt` support for richer page previews
- skip link, visible focus states, and `aria-current` navigation states
- progressive enhancement hooks so gallery/work toggles stay readable without JavaScript
- low-power / reduced-motion celestial fallbacks that quiet decorative motion automatically

Navigation is now centralized through a `navigation_items` registry model. The public header reads from that registry, and `/admin/navigation` manages mixed system, page, and external items in one place. If the table is missing, the app auto-bootstraps it once by creating and seeding nav records only; it does not alter the underlying pages themselves.

---

## File structure

```
fornesusart/
├── public/                         Web root — point DocumentRoot here
│   ├── index.php                   Front controller and regex router
│   ├── .htaccess                   Rewrites all requests to index.php
│   └── assets/
│       ├── css/
│       │   ├── style.css           Public site styles (Celestial Archive)
│       │   ├── admin.css           Admin panel styles
│       │   └── tiptap.css          Rich text editor + media picker modal styles
│       ├── js/
│       │   ├── main.js             Drag-and-drop reorder, slug auto-fill, gallery toggle
│       │   ├── cosmos.js           Ambient celestial background animation
│       │   └── tiptap-editor.js    Tiptap editor init, media picker, link/image popovers
│       └── fonts/                  Self-hosted Lora, Pinyon Script, Courier Prime (.woff2)
│
├── app/
│   ├── bootstrap.php               Env load, session start, class autoloader
│   ├── config/
│   │   └── database.php            PDO singleton — db() function
│   ├── controllers/
│   │   ├── AdminController.php     All /admin/* routes
│   │   ├── GalleryController.php   / — gallery with exhibit strip
│   │   ├── WorkController.php      /work/[slug] mixed-media carousel
│   │   ├── PageController.php      Managed pages, /contact form, /about redirect
│   │   ├── AboutController.php     Legacy /about fallback + contact handling when managed pages are unavailable
│   │   ├── CategoriesController.php  /categories and /category/[slug]
│   │   ├── ExhibitController.php   /exhibit/[slug]
│   │   ├── ImageController.php     /image/[id] — image compatibility route
│   │   └── MediaController.php     /media/[id] — image/video blobs with range support
│   ├── models/
│   │   ├── Artwork.php             Soft delete, category join, slug
│   │   ├── ArtworkMediaItem.php    Ordered image/video/iframe slides per artwork
│   │   ├── AdminIdentity.php       Provider-backed admin identity records
│   │   ├── Category.php            Soft delete, thumbnail, slug
│   │   ├── Exhibit.php             Soft delete, thumbnail, artwork sync
│   │   ├── MediaFile.php           Blob storage + MIME / byte_size / original_name metadata
│   │   ├── BioSection.php          Legacy About/Bio fallback sections
│   │   ├── Page.php                Soft delete, nav toggle, slug validation
│   │   ├── NavigationItem.php      Unified public/admin navigation registry + bootstrap
│   │   └── PageSection.php         Ordered sections within a page
│   ├── views/
│   │   ├── layout.php              Public shared header/footer + SEO metadata + celestial background
│   │   ├── gallery.php             /
│   │   ├── work.php                /work/[slug]
│   │   ├── about.php               Legacy About/contact fallback template
│   │   ├── page.php                /bio, /contact, /[slug] — renders page + sections
│   │   ├── categories.php          /categories
│   │   ├── category.php            /category/[slug]
│   │   ├── exhibit.php             /exhibit/[slug]
│   │   ├── 404.php
│   │   └── admin/
│   │       ├── layout.php          Admin shell — nav, importmap, Tiptap module, media picker modal
│   │       ├── login.php           Provider-only admin sign-in screen
│   │       ├── dashboard.php
│   │       ├── messages.php
│   │       ├── media.php           Media library — images + videos, asset details, + New Asset button
│   │       ├── navigation.php      Navigation manager — visible/hidden nav items + external links
│   │       ├── trash.php           Recycle bin (artworks / categories / exhibits / media)
│   │       ├── artworks/
│   │       │   ├── index.php       Drag-reorder list
│   │       │   └── form.php        Create + edit — thumbnail picker, ordered image/video/iframe slides
│   │       │                       (one active/expanded at a time, via Edit), placard + per-slide
│   │       │                       title/caption fields
│   │       ├── categories/
│   │       │   ├── index.php
│   │       │   └── form.php        Tiptap description, media picker thumbnail
│   │       ├── exhibits/
│   │       │   ├── index.php
│   │       │   └── form.php        Tiptap description, media picker thumbnail, artwork checklist
│   │       └── pages/
│   │           ├── index.php       Eye-icon nav toggle, trash count, drag-reorder
│   │           ├── form.php        Page metadata + SEO + sections list
│   │           ├── section-form.php  Tiptap content editor
│   │           └── trash.php       Pages-specific recycle bin (separate from /admin/trash)
│   └── helpers/
│       ├── auth.php                admin_check(), admin_login_identity(), admin_logout()
│       ├── oauth.php               provider config + token/profile exchange helpers
│       ├── recaptcha.php           reCAPTCHA v3 verification + honeypot/time-trap spam checks
│       ├── seo.php                 seo_excerpt(), seo_absolute_url()
│       ├── upload.php              MIME-validated image/video blob upload helpers
│       └── slugify.php             slugify(), unique_slug()
│
├── docs/
│   └── dependencies.md             External dependency register
│
├── schema.sql                      Full schema for fresh installs
├── migrate_phase2.sql              Adds categories / exhibits tables
├── migrate_phase4_blob.sql         Adds blob columns to media_files
├── migrate_phase4_cleanup.sql      Drops legacy path/subfolder columns
├── migrate_phase7_oauth_carousel.sql  Adds admin_identities + artwork_media_items + media metadata
├── migrate_phase6_pages.sql        Creates pages + page_sections; seeds Bio + Contact
├── migrate_pages_softdelete.sql    Adds deleted_at to pages
├── migrate_phase8_placard.sql      Adds museum placard fields to artworks + caption to artwork_media_items
├── migrate_phase9_slide_title.sql  Adds per-slide title to artwork_media_items
├── migrate_images_to_blob.php      One-time CLI script — filesystem images → DB blobs
├── migrate_legacy_artwork_media.php  Backfills one slide per legacy artwork
├── scripts/check_oauth_setup.php   Verifies OAuth env vars and expected callback URLs
├── scripts/mock_oauth_provider.php Local-only mock GitHub/Google OAuth provider for testing
├── env.example
└── .env                            Local config — never committed
```

---

## URLs

### Public

| URL | Description |
|---|---|
| `/` | Gallery — exhibit strip + works grid |
| `/categories` | All categories |
| `/category/[slug]` | Category with artworks |
| `/exhibit/[slug]` | Exhibit with artworks |
| `/work/[slug]` | Individual artwork |
| `/about` | Legacy route: redirects to `/bio` when managed pages are available; otherwise renders the fallback About/contact page |
| `/bio` | Managed biography page |
| `/contact` | Managed contact page with editable intro + contact form; falls back through the legacy About flow if the Pages migration has not been applied yet |
| `/[slug]` | Any other managed page |
| `/image/[id]` | Serves an image blob from the database |
| `/media/[id]` | Serves image or video blobs; videos support HTTP range requests |

### Admin

| URL | Description |
|---|---|
| `/admin` | Dashboard — stat cards for works, categories, exhibits, messages, trash |
| `/admin/login` | Provider login screen |
| `/admin/auth/github/start` | Begin GitHub admin OAuth |
| `/admin/auth/github/callback` | GitHub admin OAuth callback |
| `/admin/auth/google/start` | Begin Google admin OAuth |
| `/admin/auth/google/callback` | Google admin OAuth callback |
| `/admin/artworks` | Manage artworks — drag-reorder, soft delete |
| `/admin/categories` | Manage categories |
| `/admin/exhibits` | Manage exhibits + artwork assignment |
| `/admin/pages` | Manage pages — drag-reorder, eye-icon nav toggle, soft delete |
| `/admin/navigation` | Manage system/page/external nav items — reorder, hide/restore, edit external link labels, toggle external `New Tab`, hard-delete external links |
| `/admin/pages/trash` | Pages-specific recycle bin (restore / delete forever) |
| `/admin/messages` | Contact form submissions |
| `/admin/media` | Media library — image/video grid, asset details, upload/import flows |
| `/admin/trash` | Recycle bin for artworks, categories, exhibits, media |

---

## Navigation management

The dedicated `/admin/navigation` screen manages three nav item sources together:

- **System items**: permanent built-in routes like `Gallery` and `Categories`; they can be reordered and hidden, but not deleted.
- **Page items**: page-backed links; they can be reordered and hidden, but remain tied to their source pages.
- **External links**: custom URLs; they can be added, reordered, hidden/restored, renamed inline, switched between same-tab and new-tab behavior, and permanently deleted.

The admin screen separates nav items into `Visible` and `Hidden` sections. Restoring a hidden item appends it back to the visible list. The public header progressively enhances into an overflow hamburger only when items no longer fit inline.

---

## Media storage

Images and short videos are stored as blobs in the `media_files` table. Each asset now keeps MIME type, `byte_size`, and `original_name` metadata. `/image/[id]` remains the image-only compatibility route, while `/media/[id]` serves both images and videos and supports HTTP range requests for seeking.

The **Media Library modal** (available everywhere in the admin) has three tabs:

- **Select** — choose from existing library assets; artwork-carousel flows can see videos as well as images
- **Upload** — drag-and-drop or file picker; images stay limited to 8 MB, videos to 25 MB (`video/mp4`, `video/webm`, `video/quicktime`)
- **Import** — paste a URL; server fetches, validates (8 MB, JPEG/PNG/GIF/WebP/AVIF), and stores the image

Artwork detail pages now prefer ordered `artwork_media_items`, allowing mixed image, video, and iframe slides with lazy activation. Legacy `artworks.piece_type` / `piece_value` remain in place as a migration fallback for one release.

On public work pages:

- the first slide is fully rendered on initial load
- later slides begin as placeholders only
- images receive `src` the first time a viewer activates them
- videos receive `src` on first activation, pause when deactivated, and stay cached
- iframes are injected on activation and torn down on deactivate so embeds do not keep running in the background

---

## Artwork details: placard, slide titles, and captions

Each work page shows a **museum placard** (`.work-placard`) below the carousel — a single block shared across all slides:

- **Name** and **Year** — reuse the artwork's existing `title` / `year` (no separate entry)
- **Artist**, **Medium**, **Dimensions** — optional one-line fields
- **Notes** — optional rich text (Tiptap)

The placard renders only the rows that have a value; Name is always present since `title` is required. Both labels and values use the `Lora` body serif (matching the description text below); labels stay uppercase and letter-spaced for the label/value distinction.

Independently, each carousel slide can have:

- a **Title**, shown above the carousel stage
- a **Caption**, shown below the carousel stage

Both are optional and update automatically as the visitor moves between slides (`data-title` / `data-caption`, synced in `main.js`). They're separate from `alt_text`, which remains screen-reader-only.

The artwork's own title, year, and category sit above the carousel (`.work-header`), unchanged in styling from before.

In `/admin/artworks`, only one slide is expanded for editing at a time. The **Edit** button on a collapsed slide makes it active and collapses the others; switching the active slide never discards edits made to other slides.

---

## Rich text editor

Content fields (page section content, artwork / category / exhibit descriptions) use the [Tiptap](https://tiptap.dev/) rich text editor loaded via ES module imports from `esm.sh`. No build step required.

**Toolbar**: headings, font family, font size, bold, italic, underline, strikethrough, text colour, highlight, horizontal rule, link (with title), insert image from library, insert iframe embed, HTML source toggle.

**Iframe embeds**: the rich-text iframe button accepts either a bare iframe URL or full `<iframe ...></iframe>` markup. Saved TipTap embeds are emitted with a site-owned `rich-embed-frame` class so public templates can stage them as immersive media instead of prose-width blocks. If pasted embed code is malformed, the editor keeps it in a recoverable inline draft notice and offers to open HTML source mode with that draft instead of silently discarding it.

**Image editing**: a pencil icon appears at the bottom-right corner of each image on hover. Clicking it opens an inline popover for alt text editing.

**Link editing**: a small pencil icon appears to the right of any link the cursor is inside. Clicking it opens a floating popover with URL and title fields.

**HTML source mode**: a `HTML` toolbar button reveals the raw HTML in an editable textarea. All other controls are disabled while in source mode.

Content is stored as HTML in `TEXT` columns and rendered as raw HTML on the public site.

See `docs/dependencies.md` for CDN details and self-hosting instructions.

---

## Public-site behavior

- Typography: public reading text uses `Lora`; display headings use `Pinyon Script`; mono/admin metadata uses `Courier Prime`.
- Atmosphere: the celestial background combines three low-opacity nebula washes, a slow astrolabe grid, and lightweight stars/comets. On reduced-motion or low-power devices, decorative effects scale back automatically.
- Progressive enhancement: the homepage "See More" control and long work-description expand/collapse behavior only activate when JavaScript is available; content stays fully readable without JS.
- Accessibility: public templates use skip links, labelled sections, descriptive card links, decorative `aria-hidden` markers, responsive touch targets, and contact-form status/error messaging.
- Metadata: page-level SEO/social tags are emitted centrally from the public layout, with dynamic canonical URLs and optional social preview image alt text.

---

## License

MIT
