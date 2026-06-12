# Decisions
<!-- IMPORTANT: Load CONSTRAINTS.md and DESIGN.md alongside this
file at every session start. Constraints listed in CONSTRAINTS.md are binding regardless of what is recorded here. Design identity in DESIGN.md informs all gallery
options regardless of session context. -->

## Project Profile

<!-- Operational details for this project. Kept here, not in AGENTS.md,
     to keep the root instruction file framework-agnostic and safe to
     publish. Do not put credentials, hostnames, file paths, or API
     keys here — those belong in .env.

     An agent fills this section during Phase 1 by asking the person
     plain-language questions. If this section is empty, ask before
     writing any code. See AGENTS.md → Detect the Framework. -->

- **Stack:** Bare PHP (no framework), MySQL, PDO
- **Deployment:** Apache/Nginx, `public/` as DocumentRoot. URL rewriting via `.htaccess`.
- **Database:** MySQL via PDO. No ORM.
- **Version pins:** PHP 8.1+, MySQL 8+
- **Framework AGENTS.md:** No framework sub-file — root AGENTS.md only.
- **Profile switch rule:** Stop before touching existing files. Record
  current state and reason here. Confirm new profile explicitly. Flag
  every file needing migration before starting.

---

## REVIEW REQUIRED — Read before starting next session
- [x] 2026-06-04 Claude Code. URL structure confirmed via plan approval: `/`, `/about`, `/work/[slug]`, `/admin/*`
- [x] 2026-06-04 Claude Code. Google Fonts CDN dependency disclosed and accepted; documented in `docs/dependencies.md`
- [x] 2026-06-05 Codex. Public header overflow still needs a live browser verification pass at intermediate and narrow widths to confirm the hamburger never opens empty and `Fornesus Art` stays on one line until all inline nav links have collapsed.

## Phase 7 — Codex (2026-06-09)

### Auth and Artwork Constraint Changes
- Admin auth constraint intentionally lifted from single password-in-`.env` to provider-backed admin identities using GitHub and Google OAuth.
- Artwork piece storage constraint intentionally lifted from single-table `artworks.piece_type`/`piece_value` only to a dedicated ordered `artwork_media_items` table, while keeping legacy fields during the migration release for fallback compatibility.

### Admin OAuth Decisions
- `/admin/login` is now a provider-only sign-in screen. Approved access is bootstrapped from `.env` allowlists and persisted in `admin_identities`.
- Admin session state is identity-based (`admin_identity_id` plus provider metadata), replacing the prior boolean password-authenticated session flag.
- GitHub and Google are accepted off-domain auth dependencies for admin access and are documented in `docs/dependencies.md`.
- OAuth configuration was simplified so normal environments require only provider client credentials plus the admin allowlists. Provider endpoint override env vars were removed from the supported setup after proving too error-prone in local use.
- Localhost OAuth failures now surface a concrete callback/detail message on the login screen in addition to the generic sign-in error, so token/profile failures can be debugged without code edits.

### Mixed-Media Artwork Decisions
- Public artwork pages now render from ordered `artwork_media_items` first, with legacy `piece_type` / `piece_value` retained temporarily as fallback during the migration release.
- `artworks.thumbnail_*` remains unchanged and continues to drive cards, previews, and SEO/social images.
- Existing legacy artworks are backfilled to one initial carousel slide so public `/work/[slug]` URLs continue to work without changing route structure.

### Media Storage Decisions
- Short videos are intentionally supported in database-backed blob storage, but uploads are capped to 25 MB and restricted to `video/mp4`, `video/webm`, and `video/quicktime`.
- `/media/[id]` is the canonical binary route for image/video assets; `/image/[id]` remains in place as the image-only compatibility route.
- Video responses on `/media/[id]` support HTTP range requests so browser seeking works against DB-backed media.

## Phase 6 — Codex (2026-06-05)

### Investigation Notes
- Agent loop initiated to trace why an embed-backed artwork was being marked unavailable in both admin and public views.
- Verified live that `https://atelier.fornesusart.com/immersive/exhibits/asian-representation?embed=1` returns `HTTP 200`; the local site was producing a false negative before the browser could attempt the iframe render.

### Embed Validation Decision
- Embed validation now accepts any saved iframe source URL as long as the iframe markup is present and the `src` can be extracted.
- Route-shaped heuristics for `/immersive/exhibits/` were removed; malformed iframe HTML remains invalid, but legacy-looking or off-site iframe URLs are no longer auto-blocked.
- Operational rule: if an embed needs review in future, verify it from the browser behavior or the iframe markup itself, not from URL shape alone.

---

## Phase 1 — Claude Code (2026-06-04)

### Stack Confirmed
- Bare PHP, no framework
- Front controller in `public/index.php` with regex router
- MVC-lite pattern: controllers → views via `require`; models via static PDO methods
- Admin auth: single bcrypt-hashed password in `.env`, session-based
- URL rewriting via `.htaccess` (Apache) — Nginx equivalent: `try_files $uri $uri/ /index.php`

### Schema and Data Decisions
- `artworks` table: `piece_type` ENUM discriminator (`image_upload`, `image_link`, `embed`); `thumbnail_type` ENUM (`upload`, `link`)
- `thumbnail_value` / `piece_value`: stores relative file path (uploads) or URL or raw iframe HTML
- `categories` → `artworks` foreign key with `ON DELETE SET NULL` (uncategorised works remain visible)
- `bio_sections`: `heading` nullable (NULL = opening paragraph, rendered without a heading element)
- `contact_messages`: stored in DB; viewable in admin at `/admin/messages`
- Auto-slug derived from title at creation; slug not changed on subsequent edits to protect URLs

### Files Created
- `schema.sql` — database schema
- `public/index.php` — front controller + regex router
- `public/.htaccess` — rewrite all to index.php
- `public/uploads/.htaccess` — block PHP execution in uploads directory
- `.htaccess` (root) — redirect to `public/` when DocumentRoot is project root
- `.gitignore` — excludes `.env`, uploaded files
- `app/bootstrap.php` — env loader, session start, autoloader
- `app/config/database.php` — PDO singleton via `db()` function
- `app/helpers/auth.php` — `admin_check()`, `admin_login()`, `admin_logout()`
- `app/helpers/upload.php` — MIME-validated file upload via magic bytes
- `app/helpers/slugify.php` — `slugify()`, `unique_slug()`, `unique_category_slug()`
- `app/models/Artwork.php`, `Category.php`, `BioSection.php` — static PDO models
- `app/controllers/GalleryController.php`, `WorkController.php`, `AboutController.php`, `AdminController.php`
- `app/views/layout.php`, `gallery.php`, `work.php`, `about.php`, `404.php`
- `app/views/admin/layout.php`, `login.php`, `dashboard.php`
- `app/views/admin/artworks/index.php`, `form.php`
- `app/views/admin/categories/index.php`
- `app/views/admin/bio/index.php`
- `app/views/admin/messages.php`
- `public/assets/css/style.css` — Celestial Archive design
- `public/assets/css/admin.css` — admin panel styles
- `public/assets/js/main.js` — admin toggle panels
- `docs/dependencies.md` — dependency register
- `env.example` — updated with DB and admin vars

### Vendor Dependencies Added
- Google Fonts CDN (Cinzel Decorative, IM Fell English, Courier Prime) — off-domain (logs visitor IPs); self-hosting alternative: download `.woff2` files to `public/assets/fonts/`. Documented in `docs/dependencies.md`.

### Environment Variables Required
- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `ADMIN_PASSWORD_HASH` — bcrypt hash; generate with: `php -r "echo password_hash('yourpassword', PASSWORD_BCRYPT);"`

### Gaps and Deferred Items
- Email delivery for contact form not implemented (messages stored in DB only)
- No rate limiting on contact form or admin login (deferred)
- Nginx rewrite config not written (Apache `.htaccess` only)

### Unresolved Checkpoints Entering Phase 2
- [ ] Decide whether to add email notifications for contact form submissions

---

## Phase 2 — Claude Code (2026-06-04)

### New URL Structure Confirmed
- `/categories` — public categories listing
- `/category/[slug]` — individual category page
- `/exhibit/[slug]` — individual exhibit page
- `/admin/categories/create`, `/admin/categories/[id]/edit` — category full CRUD (replaces inline table editing)
- `/admin/exhibits`, `/admin/exhibits/create`, `/admin/exhibits/[id]/edit` — exhibits CRUD

### Schema and Data Decisions
- `categories` table: added `thumbnail_type ENUM('upload','link')`, `thumbnail_value VARCHAR(500)`, `description TEXT` columns (nullable — categories without thumbnails/descriptions are valid)
- `exhibits` table (new): same thumbnail pattern as categories; `sort_order` for drag-to-reorder
- `exhibit_artworks` junction table (new): many-to-many between exhibits and artworks; composite primary key `(exhibit_id, artwork_id)`; `ON DELETE CASCADE` on both foreign keys
- Artworks can belong to multiple exhibits simultaneously
- Exhibit artwork assignment stored in `exhibit_artworks`; synced on each edit (delete-then-reinsert)
- Gallery restructured: flat `allSorted()` query replaces grouped-by-category query; first 3 works shown, rest toggled via JS

### Files Created (Phase 2)
- `migrate_phase2.sql` — migration for existing databases
- `app/models/Exhibit.php` — full exhibit model including `syncArtworks()`
- `app/controllers/CategoriesController.php` — public categories listing and detail
- `app/controllers/ExhibitController.php` — public exhibit detail
- `app/views/categories.php` — /categories
- `app/views/category.php` — /category/[slug]
- `app/views/exhibit.php` — /exhibit/[slug]
- `app/views/admin/exhibits/index.php`, `form.php`
- `app/views/admin/categories/form.php` — new dedicated create/edit form replacing inline table

### Files Updated (Phase 2)
- `schema.sql` — includes exhibits and exhibit_artworks tables; categories has new columns
- `app/models/Category.php` — thumbnail/description fields; `findBySlug()`, `artworks()` methods
- `app/models/Artwork.php` — `allSorted()` method; `category_slug` added to all JOIN queries
- `app/controllers/GalleryController.php` — passes `$exhibits` and `$artworks` (flat)
- `app/controllers/AdminController.php` — category full CRUD with thumbnail; exhibits full CRUD; shared `resolveThumbnail()` helper
- `app/views/layout.php` — nav: Gallery · Categories · About
- `app/views/gallery.php` — exhibits strip + flat Works section with See More
- `app/views/work.php` — category rendered as link to `/category/[slug]`
- `app/views/admin/layout.php` — Exhibits added to nav
- `app/views/admin/categories/index.php` — read-only list with Edit links (inline editing removed)
- `public/index.php` — all new routes added
- `public/assets/css/style.css` — exhibits strip, collection pages, See More button
- `public/assets/css/admin.css` — exhibit artwork checklist
- `public/assets/js/main.js` — See More toggle; generic slug auto-fill for categories and exhibits

### Vendor Dependencies Added
- None

### Environment Variables Required (cumulative)
- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
- `ADMIN_PASSWORD_HASH`

### Gaps and Deferred Items
- Email delivery for contact form still not implemented
- Exhibit artwork checklist has no search/filter (may be unwieldy with many artworks)

### Unresolved Checkpoints Entering Phase 3
- [ ] Decide whether to add email notifications for contact form submissions

---

---

## Phase 4 — Claude Code (2026-06-04)

### Blob Storage Migration

Images are now stored directly in the database as LONGBLOBs rather than on the filesystem. The `media_files` table previously stored file paths (`path`, `subfolder`); those columns have been dropped. All image serving goes through `/image/[id]`.

**Motivation:** Simplify the stack — no filesystem dependency for media, no uploads directory to manage or back up separately, no path-reference synchronisation needed.

### Schema Changes
- `media_files`: added `data LONGBLOB NULL`, `mime_type VARCHAR(50) NULL`; dropped `path` and `subfolder`
- Migration files: `migrate_phase4_blob.sql` (adds columns), `migrate_phase4_cleanup.sql` (drops legacy columns)
- Data migration: `migrate_images_to_blob.php` — reads files from `public/uploads/`, writes BLOBs to DB, rewrites path references in `artworks`, `categories`, `exhibits`

### New Route
- `GET /image/[id]` — served by `ImageController::serve()`; returns binary blob with correct `Content-Type`, `ETag`, and `Cache-Control: immutable` headers. Returns 404 if blob is NULL, deleted, or id invalid.

### Files Created (Phase 4)
- `app/controllers/ImageController.php` — blob image serving
- `migrate_phase4_blob.sql` — schema migration (adds columns)
- `migrate_phase4_cleanup.sql` — cleanup migration (drops `path`, `subfolder`)
- `migrate_images_to_blob.php` — one-time data migration CLI script

### Files Updated (Phase 4)
- `app/models/MediaFile.php` — `create()` now takes `(string $data, string $mimeType)`; `getData()` method added; all listing queries exclude blob column; `hardDelete()` no longer touches filesystem
- `app/helpers/upload.php` — stores blob via `MediaFile::create()`; returns `/image/{id}`; `SET SESSION max_allowed_packet` wrapped in try/catch (MariaDB 11+ made it read-only at session level)
- `app/views/admin/media.php` — thumbnails via `/image/{id}`; filename display replaced with ID + mime type (path column gone)
- `app/views/admin/trash.php` — removed references to `path` and `subfolder` columns
- `public/index.php` — added `cli-server` static file handler (required for PHP built-in server to serve CSS/JS); added `/image/([0-9]+)` route
- `public/assets/css/admin.css` — responsive media grid (single column mobile, two columns ≥768px); button `appearance: none` override for macOS/Safari native rendering
- `app/controllers/AdminController.php` — dashboard now includes `$exhibitCount`
- `app/views/admin/dashboard.php` — Exhibits stat card added

### Operational Notes
- Remote server is **MariaDB 11.8.6**, not MySQL. Use `--column-statistics=0` with `mysqldump` from MySQL client 8.x.
- `max_allowed_packet` on the server is 1 GB; the session-level SET is unnecessary and errors on MariaDB 11+.
- `public/uploads/categorys/` and `public/uploads/thumbnails/` deleted from disk after migration.
- PHP built-in dev server requires the router argument: `php -S localhost:8000 -t public public/index.php`

### Unresolved Items Entering Phase 5
- [x] Media Library UI: button styling not applying in Safari (appearance: none served correctly but not rendering; under active investigation)
- [x] Media Library UI: fuller redesign deferred — type labels, sort, context ("used by"), original filename storage

---

## Phase 5 — Antigravity (2026-06-04)

### New URL Structure Confirmed
- `POST /admin/media/upload` — Direct media file upload endpoint

### Layout & Formatting Decisions
- Redesigned the Media Library into a dual-pane "Darkroom Gallery" split-view workspace:
  - Left pane: Spacious grid of aspect-ratio 1:1 square image tiles (140px minimum width) with subtle grayscale filters, spotlight vignette overlays, and zoom animations on hover.
  - Right pane: Sticky details panel with a radial gradient preview canvas displaying ID, MIME type, upload timestamp, and action buttons.
  - Added a drag-and-drop / file upload zone at the top of the grid with display headings.
  - Responsive alignment: Dual-pane split on screens ≥ 900px, stacking vertically on smaller screens.
  - Expanded layout: Overrode `.admin-main` container max-width to `1200px` on this page to provide optimal grid space.
- CSS Cache-buster: Added dynamic `filemtime` query cache busters to style sheets in `app/views/admin/layout.php` to force instant client updates.

### Button Style & Compatibility Decisions
- Standardized copyable inputs: Enclosed URLs and HTML tags in a `.media-code-input-wrap` that joins the input field and a left-bordered Copy button with no gap, sharing a border and aligning to the exact pixel.
- Unified action buttons: Standardized full-height `.admin-btn` (gold) and `.admin-btn-danger` (crimson) outline buttons to stretch to the exact dimensions of the details forms.
- Applied `-webkit-appearance: none` and `border-radius: 0` to all button styles, satisfying the "no rounded corners" design constraint and resolving native Safari rendering inconsistencies.
- Cleaned up Recycle Bin count badge styles to use `border-radius: 0`.

### Files Created (Phase 5)
- None

### Files Updated (Phase 5)
- `public/index.php` — Registered the POST route for `/admin/media/upload`
- `app/controllers/AdminController.php` — Implemented `mediaUpload()` method
- `app/views/admin/layout.php` — Added `filemtime`-based cache busters to CSS stylesheets
- `app/views/admin/media.php` — Entirely rewritten to implement the split view layout and clipboard copying / drop zone logic
- `public/assets/css/admin.css` — Standardized danger button classes and designed split-workspace, tiles, and upload-zone styles

### Vendor Dependencies Added
- None

### Environment Variables Required
- None

### Unresolved Items Entering Phase 6
- [ ] Decide whether to add email notifications for contact form submissions (carried over)

---

## Phase 6 — Codex (2026-06-04)

### Admin UI Decisions
- Reframed the Media Library from the earlier "Darkroom Gallery" split-view treatment into an admin-native asset manager so it inherits the same typography, controls, and hierarchy as the rest of `/admin/*`.
- Standardized the shared admin header/navigation to read as administrative chrome rather than public-site navigation:
  - Added explicit active-link states with `aria-current="page"`
  - Shifted branding/navigation emphasis toward the existing metadata-style mono system
  - Preserved all existing admin routes and labels
- Added a page-level wide-content mode for the Media Library via `.admin-main-wide` instead of inline per-view CSS overrides.

### Media Library Layout & Interaction Decisions
- Rebuilt the Media Library into a grid-first workspace:
  - Asset cards now show thumbnail, asset ID, MIME type, and upload date
  - Selected asset details remain available in a secondary panel
  - Upload zone remains drag-and-drop and click-to-browse
  - Trash and permanent delete remain confirmation-gated
- Replaced one-off inline success styling with a reusable `.admin-notice` pattern.

---

## Phase 7 — Codex (2026-06-05)

### Navigation Registry Decisions
- Added a unified `navigation_items` registry to manage mixed system, page, and external navigation items in one ordered list.
- Chose runtime auto-bootstrap for navigation storage so `/admin/navigation` can become usable without a separate manual migration step:
  - table creation and seeding happen once per request
  - initialization is explicitly non-recursive
  - bootstrap is limited to navigation records only and must not alter or delete the underlying pages
- System items remain permanent nav records: reorderable and hideable, never deletable.
- Page-linked items remain tied to their source pages: reorderable and hideable from Navigation, never hard-deleted there.
- External links are the only nav items that may be hard-deleted or have their labels and tab-target behavior edited directly in Navigation.

### Admin Navigation UX Decisions
- Added `/admin/navigation` as the dedicated navigation management screen with separate `Visible` and `Hidden` sections.
- Hidden items restore directly back into the visible navigation instead of using Trash.
- Replaced ambiguous hidden-state controls with explicit `Hide` / `Restore` actions after a class-name collision (`.is-hidden`) caused hidden restore controls not to render.
- Added a dedicated `New Tab` column with an accessible switch control for external links only; page/system rows render `Not applicable`.
- Added inline external-link label editing inside the `Label` column so link names can be updated without recreating the link.
- Mobile and tablet behavior for the external-link creation form was simplified into a constrained single-column flow.

### Public Navigation Behavior Decisions
- Public navigation rendering now comes from `NavigationItem::publicItems()` rather than a hard-coded header link list.
- The header overflow logic now measures against the full navigation shell instead of the already-constrained inline nav box.
- The hamburger control should only remain visible when actual overflow items exist; the script now explicitly hides the control again if no items moved into overflow.
- `Fornesus Art` should remain on one line unless the viewport is so narrow that all navigation links have already collapsed into the hamburger and the wordmark still has no remaining horizontal room.

### Regression Notes
- A recursion bug in navigation auto-bootstrap caused site-wide timeouts when initialization called page-sync logic that re-entered initialization. This was fixed by adding one-time initialization guards and using a direct missing-page-row insert path during bootstrap.
- The hidden restore control bug was caused by reusing the global admin utility class `.is-hidden`, which applies `display: none !important;`.

### Files Introduced or Functionally Added to Navigation Work
- `app/models/NavigationItem.php`
- `app/views/admin/navigation.php`
- `/admin/navigation` routes in `public/index.php`

### Unresolved / Needs Verification
- [x] Confirm in a live browser that the latest header measurement changes fully eliminate the empty hamburger state at the widths previously reported by the user. (Resolved by Option 3 Navigation Drawer)
- [x] Confirm in a live browser that the `Fornesus Art` wordmark no longer breaks into two lines before the navigation fully collapses. (Resolved by Option 3 Navigation Drawer)
- Updated copy controls to use the shared admin button/form language while preserving direct URL and HTML embed copying.
- Added keyboard activation for the upload zone (`Enter` / `Space`) to improve accessibility.

### Admin Layout Bugfix
- Corrected multiple admin view layout includes so admin pages consistently render through `app/views/admin/layout.php` rather than accidentally resolving toward the public layout path.
- Verified the corrected admin chrome rendering on representative routes:
  - `/admin`
  - `/admin/artworks`
  - `/admin/media`
  - `/admin/trash`

### Files Updated (Phase 6)
- `app/views/admin/layout.php` — active nav state, admin branding block, configurable body/main classes
- `app/views/admin/media.php` — grid-first Media Library markup, reusable notice usage, wide main mode, keyboard-friendly upload zone
- `public/assets/css/admin.css` — admin chrome refresh, wide main variant, media-card grid system, upload/details panel styling
- `app/views/admin/dashboard.php` — corrected admin layout include path
- `app/views/admin/trash.php` — corrected admin layout include path
- `app/views/admin/artworks/index.php`, `form.php` — corrected admin layout include paths
- `app/views/admin/categories/index.php`, `form.php` — corrected admin layout include paths
- `app/views/admin/exhibits/index.php`, `form.php` — corrected admin layout include paths
- `app/views/admin/bio/index.php`, `form.php` — corrected admin layout include paths

---

## Phase 11 — Antigravity (2026-06-05)

### Navigation System Decisions
- Reframed the mobile/tablet navigation from the complex item-by-item JS-based collision detection loop to a standard, clean slide-out mobile drawer menu on viewports < 900px.
- Shifted the visibility logic to CSS media queries using the `.js-enhanced` class as a hook to hide inline links and show the hamburger toggle on viewports < 900px, while keeping the inline navigation as a fallback when JavaScript is disabled (perfect progressive enhancement).
- Pre-populated the mobile overflow drawer menu once upon script execution with cloned links from the main navigation, completely eliminating dynamic resizing races and ensuring the drawer is never empty.
- Configured `.site-title` to permit wrapping (`white-space: normal`) on viewports under 480px so that it wraps only when all navigation links have already collapsed into the drawer and the viewport is extremely narrow.

### Files Updated (Phase 11)
- `public/assets/js/main.js` — Removed the dynamic resize/collision code and replaced it with a simple drawer initializer and toggle event handler.
- `public/assets/css/style.css` — Standardized display of `.site-nav-toggle` to be hidden on desktop, shown inside `.js-enhanced` under 900px, and updated `.site-title` to allow wrapping under 480px.

### Verification Notes
- PHP syntax checks passed for the updated admin layout and representative admin views.
- Direct-render verification confirmed:
  - admin body/nav markup is present
  - active nav states render on current admin pages
  - Media Library renders with `admin-main-wide`
  - Media asset cards render in the expected grid markup

---

## Phase 7 — Codex (2026-06-04)

### Public Design Decisions
- Kept the public "Celestial Archive" identity, but shifted the primary reading/content font to `Lora` for readability across the site.
- Strengthened public accessibility basics in the shared layout and stylesheet:
  - central metadata rendering in the public layout
  - canonical URLs, descriptions, Open Graph, and Twitter metadata support
  - skip link and stronger focus-visible treatment
  - reduced-motion handling for decorative atmosphere
- Expanded the ambient background with an additional low-opacity nebula layer in CSS while keeping the existing stars/cosmos treatment lightweight.

### Pages System Decisions
- Replaced the one-off `bio_sections` concept with a reusable Pages system:
  - `pages` for page identity, template, nav behavior, and SEO/social metadata
  - `page_sections` for ordered sections within each page
- Split the old `/about` concept into:
  - `/bio` — managed standard page
  - `/contact` — managed page with editable intro sections plus the fixed contact form
- Added legacy compatibility by redirecting `GET /about` to `/bio` and accepting `POST /about` through the contact submission handler.
- Reserved `/contact` for the contact-template page so the built-in form remains coherent with its public route.

### Admin Content Decisions
- Replaced the admin “Bio” entry point with “Pages” in the shared admin navigation.
- Added a Pages portal in admin for:
  - page creation and editing
  - SEO/social metadata management
  - ordered section management per page
  - page deletion and page ordering
- Kept admin visuals broadly intact while extending the current CRUD pattern rather than redesigning the admin area.

### Files Updated (Phase 7)
- `public/index.php` — page routes, `/about` redirect, `/contact` POST handler, new model/helper/controller requires
- `app/controllers/PageController.php` — managed page rendering, contact form handling, `/about` redirect
- `app/controllers/AdminController.php` — Pages CRUD and page-section CRUD/reorder
- `app/models/Page.php`, `PageSection.php` — page identity/navigation and section ordering
- `app/views/layout.php` — centralized metadata tags, skip link, managed-page nav
- `app/views/page.php` — shared managed page template with optional contact section
- `app/views/gallery.php`, `work.php`, `categories.php`, `category.php`, `exhibit.php`, `404.php` — explicit metadata variables and public layout cleanup
- `app/views/admin/pages/index.php`, `form.php`, `section-form.php` — admin Pages interface
- `public/assets/css/style.css` — `Lora` reading system, nebula layer, accessibility/responsive improvements
- `public/assets/js/cosmos.js` — reduced-motion-aware lighter atmosphere behavior
- `public/assets/js/main.js` — page slug auto-fill support
- `schema.sql`, `migrate_phase6_pages.sql` — page system schema and migration
- `docs/dependencies.md`, `README.md` — updated font dependency and architecture docs


## Phase 8 — Codex (2026-06-04)

### Typography & Self-Hosting Decisions
- Migrated all web fonts to local hosting to completely remove Google Fonts CDN off-domain requests, protecting visitor IP privacy and preventing outage dependencies.
- Placed `.woff2` files for `Lora`, `Pinyon Script`, and `Courier Prime` under `public/assets/fonts/` and registered them using `@font-face` blocks at the top of `style.css` with `font-display: block`.
- Added high-priority HTML `<link rel="preload">` tags to layout files to completely resolve FOUT (Flash of Unstyled Text).
- Changed regular text font-family to `'Lora', Georgia, serif` site-wide.
- Changed heading text font-family to `'Pinyon Script', 'Lora', cursive` site-wide.
- Adjusted letter-spacing, font-weight, text-transform, and font-variant properties across all headings (`.site-title`, `.category-name`, `.work-title`, `.page-title`, etc. in both public and admin stylesheets) to align with Pinyon Script's calligraphic script aesthetics.
- Updated administrative buttons (e.g. login form button, see-more button) to use the monospace `Courier Prime` font for consistency and legibility.

### Colorful & High-Performance Background Decisions
- Replaced the simple static background nebulas with a composite celestial atmosphere.
- Created `#celestial-background` with three slow-drifting, low-opacity `.nebula-wash` elements in colors `cyan`, `magenta`, and `gold`. These blend dynamically via `mix-blend-mode: screen` to form a rich, shifting watercolor nebula color scheme.
- Added a thin, vector-drawn SVG astrolabe/coordinate grid (`.astrolabe-grid`) rotating extremely slowly (300s duration) behind the static star field.
- Animated the `#cosmos-stars` container to rotate clockwise slowly (60s loop duration) around the center, creating a realistic celestial rotation.
- Rescaled `#cosmos-stars` to a square of `150vmax` to ensure complete coverage of the screen diagonals at all angles of rotation.
- Refactored canvas shooting stars into highly noticeable, clockwise, 10-second orbital comets that share the stars' center of rotation (the viewport center), doing exactly one full 360-degree loop before fading out.
- Scaled comet linear speeds proportionally to their orbit radius ($v = R \cdot \omega$) to maintain a constant 10-second orbital cycle (making outer comets sweep faster and inner comets sweep slower).
- Implemented smooth dynamic HSL color-shifting along the comets' curved coordinate history paths for a vibrant celestial aesthetic.
- Integrated all visuals using CSS `transform: translate3d()` and `opacity` animations only, ensuring full hardware acceleration and negligible device resource utilization.
- Modified `cosmos.js` to assign colors based on astrophysical spectral distributions (O, B, A, F, G, K, M types: blue-white, white, golden, orange, and red-orange) for twinkling star nodes.

### Files Updated (Phase 8)
- `docs/dependencies.md` — removed Google Fonts CDN, documented self-hosted typefaces
- `app/views/layout.php` — removed CDN fonts, added `#celestial-background` HTML elements
- `app/views/admin/layout.php` — removed CDN fonts
- `public/assets/css/style.css` — added `@font-face` rules, updated font variables, added watercolor nebula & astrolabe styles, updated heading overrides
- `public/assets/css/admin.css` — updated admin headings, set login button font to mono
- `public/assets/js/cosmos.js` — implemented color-temperature based star spawning

## Phase 9 — Claude Code (2026-06-04 → 2026-06-05)

### Pages System — Database + Soft Delete + Nav Toggle

- Ran `migrate_phase6_pages.sql` against the live DB to create `pages` and `page_sections` (had been written but never executed).
- Created `migrate_pages_softdelete.sql`: `ALTER TABLE pages ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL`.
- `Page` model updated: `all()`, `navItems()`, `findBySlug()`, `findPublishedBySlug()`, `validateSlug()` and `reorder()` all filter `WHERE deleted_at IS NULL`. Added `softDelete`, `hardDelete`, `restore`, `trashed`, `trashedCount`, `toggleNav` methods.
- `pageDelete` controller now soft-deletes (sends to trash) instead of hard-deleting.
- Pages trash is a standalone admin section at `/admin/pages/trash` — entirely separate from the existing `/admin/trash` (artworks/categories/exhibits/media). Reason: pages are a distinct content type with their own editorial workflow.
- Eye-icon nav toggle added to the pages index: `POST /admin/pages/{id}/toggle-nav` returns JSON and flips `show_in_nav` in place without page reload. SVG eye-open / eye-crossed icons in the Nav column.

### Rich Text Editor (Tiptap)

- Replaced all `<textarea>` content fields with [Tiptap](https://tiptap.dev/) — a headless ProseMirror-based rich text editor.
- **Loading strategy**: No build step. Tiptap loaded via ES module imports (`<script type="module">`) from `https://esm.sh/`. An `importmap` in the admin layout deduplicate ProseMirror instances across all extensions. Dependency on `esm.sh` CDN — admin panel requires internet access when the editor is in use. Self-hosting alternative: run a one-time `esbuild` bundle and serve from `public/assets/js/`.
- **Extensions in use**: StarterKit, Underline, TextStyle, FontSize (custom inline extension), Color, Highlight (multicolor), FontFamily, LinkWithTitle (Link extended with `title` attribute), Image (extended with custom NodeView), IframeNode (custom block extension).
- **Toolbar**: Paragraph/H1-H4 dropdown, font family select, font size input (debounced), B/I/U/S, text colour, highlight colour, horizontal rule, link, insert image from library, insert iframe (prompt), HTML source toggle.
- **HTML source toggle**: Shows a `<textarea>` with raw HTML. All other toolbar controls disabled while in source mode. Form submit reads from source textarea when active.
- **Storage**: Tiptap HTML is stored directly in `TEXT` columns. No schema changes. Public rendering changed from `nl2br(htmlspecialchars($content))` to raw HTML output for: `page_sections.content`, `artworks.description`, `categories.description`, `exhibits.description`.
- **Existing content**: Plain text content written before Tiptap normalises to `<p>` tags on first save through the editor. No one-time migration script needed.
- Fields with Tiptap: `page_sections.content` (section-form.php), `artworks.description`, `categories.description`, `exhibits.description`.

### Media Library Modal Picker

- **Architecture**: A `<dialog id="media-picker-modal">` in `admin/layout.php` (present on every admin page) with three tabs: **Select** (grid of existing images), **Upload** (drag-and-drop, max 8 MB, client-side validation before POST), **Import** (URL-to-DB fetch, server-side MIME validation + 8 MB limit).
- **New endpoints**: `GET /admin/media/library` → JSON array of non-trashed files. `POST /admin/media/upload` now returns JSON (was a redirect). `POST /admin/media/import` fetches remote URL via `file_get_contents`, validates MIME/size, stores as LONGBLOB.
- **CSS bug pattern**: `display: flex` on a `<dialog>` or element using the `hidden` attribute overrides `display: none` and makes the element permanently visible. Fixed across the media picker dialog and image NodeView popover by splitting into base rule (`display: none`) and `:not([hidden])` rule (`display: flex`). Dialog centering additionally fixed by `position: fixed; inset: 0; margin: auto`.
- **Trigger points**: Tiptap image toolbar button opens picker on Select tab; standalone `[data-picker-target]` buttons on thumbnail/OG image fields open on Select tab; media library "+ New Image" button opens on Upload tab in library-only mode (page reloads on close).
- **After upload/import**: picker automatically switches to Select tab with the new image pre-selected; alt text input is revealed.
- **Library-only mode** (no callback): "Select Image" button is hidden; on dialog close, page reloads to refresh the media grid.
- **Media library page**: removed the standalone upload zone; added `+ New Image` button that opens the picker.

### Thumbnail and Piece Fields Simplified

- Artwork thumbnail, category thumbnail, exhibit thumbnail, artwork piece (image type): removed "Upload image" / "Image URL" radio toggles entirely. Replaced with a hidden `<input type="hidden" name="thumbnail_type" value="link">`, a media preview area, a read-only URL input, a "Choose Image" picker button, and a "Clear" button.
- All uploads and external URLs now go through the media library first (blob stored in DB, served via `/image/{id}`). This eliminates direct-to-form file uploads for thumbnails.
- `resolveArtworkData()` and `resolveThumbnail()` in AdminController updated: submitting an empty `thumbnail_link` now clears the thumbnail (sets type and value to null) rather than preserving the existing value. This enables the "Clear" button to work.
- Artwork piece field: `image_upload` radio removed; only "Image" (maps to `image_link`) and "Iframe embed" remain. Existing artworks stored as `image_upload` are migrated to `image_link` on next save (same `/image/{id}` URL, different type tag).

### Alt Text for Images + Link Title

- **Media picker**: alt text input (`#mp-alt-input`) in the modal footer, hidden until an image is selected on the Select tab. `confirmSelection()` now calls `callback({ url, alt })` instead of `callback(url)`. Standalone pickers (thumbnails, OG image) extract `.url` and ignore `.alt` since server templates derive alt from item title/name.
- **Image NodeView**: pencil icon button (`opacity: 0`, revealed on hover via CSS) at bottom-right corner of each image in Tiptap. Clicking opens an inline popover with alt text input, Save, ✕ (close), and "Delete image from editor" (red text link, confirm-guarded). Save dispatches `tr.setNodeMarkup(pos, null, newAttrs)` directly — does not rely on `updateAttributes` command (which requires the node to be the active selection). `hidden` attribute toggle was the visibility mechanism; `display: flex` in CSS caused the popover to be permanently visible until fixed with the `display: none` + `:not([hidden])` pattern.
- **Link popover**: floating `position: fixed` trigger (pencil icon) appears next to the active link using `editor.view.coordsAtPos()` + `getBoundingClientRect()`. Clicking opens a popover with URL + title fields. Toolbar link button also shows this popover. `LinkWithTitle` extends `@tiptap/extension-link` with a `title` attribute that round-trips through HTML.
- **NodeView `stopEvent`**: returns `true` only for events on the edit button and popover, passing all other events through to ProseMirror so text selection drag works normally. `img.draggable = false` + `dragstart → preventDefault` prevents native browser image drag from intercepting text-selection drags. Outside-close uses `document.addEventListener('mousedown', ...)` rather than `click` because drag-to-select produces no `click` event.

### Files Created (Phase 9)
- `migrate_pages_softdelete.sql`
- `public/assets/css/tiptap.css`
- `public/assets/js/tiptap-editor.js`
- `app/views/admin/pages/trash.php`

---

## Phase 10 — Documentation + Public Accessibility Audit (2026-06-05)

### Current-State Clarifications
- Preserved older implementation notes as history, but clarified the current source of truth in project markdown after the site-wide Pages/public-accessibility work moved beyond the earlier admin-only Media Library effort.
- Corrected the dependency story: the public site and authenticated admin shell use self-hosted `Lora`, `Pinyon Script`, and `Courier Prime`, but the standalone admin login still loads Google Fonts for its legacy title/body stack.

### Public Layout and Metadata Decisions Captured
- Documented that the shared public layout now centralizes:
  - canonical URLs
  - meta descriptions
  - Open Graph and Twitter metadata
  - optional `robots` tags
  - `og:image:alt` and `twitter:image:alt`
- Documented the current legacy-nav fallback behavior:
  - when no managed nav pages exist, the public nav still exposes `/about`
  - `/about` can still render the legacy About/contact experience when the Pages migration or seeded managed pages are unavailable

### Accessibility, Performance, and Atmosphere Decisions Captured
- Recorded the current public accessibility posture:
  - skip link
  - `aria-current` nav states
  - labelled sections on managed pages and legacy About
  - decorative `aria-hidden` markers on non-content visuals
  - accessible contact-form success/error semantics
- Recorded the progressive-enhancement behavior:
  - homepage "See More" and work-description expansion only collapse content when JavaScript is available
  - the public site remains readable without JS
- Recorded the low-power / reduced-motion atmosphere behavior:
  - `cosmos.js` adds a `low-power` class when reduced-motion, save-data, low-memory, or low-CPU conditions are detected
  - CSS quiets nebula, grid, glow, and other decorative motion accordingly

### Markdown Files Updated (Phase 10)
- `README.md` — current routes, fallback behavior, public metadata/accessibility summary, legacy About artifacts added to the file map
- `docs/dependencies.md` — corrected self-hosted-vs-CDN font story; documented Google Fonts as admin-login-only
- `MEMORY.md` — added current public accessibility/metadata/low-power notes and corrected the font-hosting summary

### Files Updated (Phase 9)
- `app/models/Page.php` — soft delete, toggleNav, query filters
- `app/controllers/AdminController.php` — pageDelete → softDelete; pagesTrash, pageRestore, pageHardDelete, pageToggleNav, pagesTrashEmpty; mediaLibrary, mediaUpload (→ JSON), mediaImport; thumbnail clear logic in resolveArtworkData + resolveThumbnail
- `public/index.php` — pages trash routes, media library/import routes, toggle-nav route
- `app/views/admin/layout.php` — importmap for Tiptap ESM; tiptap.css; tiptap-editor.js module; media picker dialog HTML
- `app/views/admin/pages/index.php` — eye icon nav toggle, trash count link, soft-delete label
- `app/views/admin/pages/section-form.php` — `data-tiptap` on content textarea
- `app/views/admin/pages/form.php` — picker button for og_image
- `app/views/admin/artworks/form.php` — Tiptap on description; picker-only thumbnail + piece
- `app/views/admin/categories/form.php` — Tiptap on description; picker-only thumbnail
- `app/views/admin/exhibits/form.php` — Tiptap on description; picker-only thumbnail
- `app/views/admin/media.php` — removed upload zone; `+ New Image` button
- `app/views/page.php`, `work.php`, `exhibit.php`, `category.php` — raw HTML output for rich text fields
- `docs/dependencies.md` — Tiptap CDN dependency documented

## Phase 12 — Claude Code (2026-06-08)

### 16:9 Art Piece Containers

All art piece display contexts now use a consistent 16:9 aspect-ratio box. Content scales to **fit** (no crop, no overflow). Dark background `#0a0a0a` provides letterboxing for non-widescreen art.

**Thumbnail containers** — gallery grid, exhibit cards, category/collection grid:
- `.artwork-thumb-wrap`: `aspect-ratio` changed from `4/3` → `16/9`; `object-fit` changed from `cover` → `contain`
- `.exhibit-card .artwork-thumb-wrap`: override changed from `1/1` → `16/9`
- `.collection-thumb-wrap`: same `4/3` → `16/9` and `cover` → `contain` changes

**Work detail — image** (`.work-image`): `max-height: 80vh` replaced with `aspect-ratio: 16/9`; `object-fit: contain` unchanged.

**Work detail — embed** (`.work-embed`): The earlier `height: var(--embed-stage-h)` variable system was replaced with the **padding-bottom ratio technique** (`height: 0; padding-bottom: 56.25%; overflow: hidden`). Reason: `aspect-ratio` on a container can be expanded by children with fixed intrinsic dimensions; `height: 0 + padding-bottom: %` cannot be overridden by any child — the container's height is purely CSS-driven. This resolved per-piece horizontal overflow that appeared when embed codes contained wrappers or iframes with large hardcoded `width` attributes.

**Embed children** — new `.work-embed > *` rule and revised `.work-embed iframe` rule: both use `position: absolute !important; top: 0; left: 0; width: 100% !important; height: 100% !important`. The `!important` flags defeat both HTML attributes (`width="640"`) and inline styles (`style="width: 640px"`). Taking children out of flow prevents them from driving the container's size.

**Responsive media query cleanup**: `.work-embed` and `.work-embed iframe` removed from the `@media (max-width: 900px)` and `@media (max-width: 640px)` height-override selector lists. Those overrides were using `!important` height values that fought the new ratio containment. The remaining selectors in those blocks (`.bio-text iframe`, `.rich-embed-frame`, etc.) are unchanged.

### Hamburger Menu — iOS Safari Fix

The hamburger toggle button was non-functional on Safari on iPhone while working on Android, desktop, and other browsers.

**Root cause**: `.site-header` has `position: sticky` combined with `-webkit-backdrop-filter: blur(12px)`. This creates a GPU compositing layer in WebKit. A documented WebKit bug causes synthesized `click` events to be dropped or mis-routed for child elements inside such a composited sticky container. Android Chrome implements `backdrop-filter` via Blink and is unaffected.

**CSS fix** (`public/assets/css/style.css`): Added `touch-action: manipulation` to `.site-nav-toggle`. This tells iOS to treat the element as a direct manipulation target — no 300ms tap delay, no double-tap-to-zoom intercept.

**JS fix** (`public/assets/js/main.js`): Added `touchend` listener on the toggle (immediately after the `click` listener). `e.preventDefault()` blocks the browser from synthesising a ghost click after touch; `toggle.click()` fires a programmatic click that the existing handler catches. On Android and desktop `touchend` also fires but `e.preventDefault()` prevents the duplicate click, so toggle behaviour is identical on all platforms. `{ passive: false }` is required to allow `preventDefault` on the touchend event.

### Work Embed — Minimum Height on Narrow Viewports

The "Asian Representation" embed (OpenProcessing exhibit with three sketches and a fullscreen expand button) was clipping its expand button on mobile. Root cause: `padding-bottom: 56.25%` (16:9) derives height from content width — at 390px iPhone the container was only 192px tall, and the embed platform's fullscreen button near the bottom of its gallery layout was clipped by `overflow: hidden`.

Added `min-height: 400px` to `.work-embed`. When the 16:9-derived height falls below 400px (content width < ~711px), the container expands to 400px. The absolutely-positioned iframe at `height: 100%` fills the 400px minimum correctly via the containing-block height resolution. At wider viewports (>~800px), 16:9 exceeds 400px and the ratio governs unchanged. 400px is ~47% of an iPhone 13 screen height — a proportionate stage for an artwork that is the primary content.

### Files Updated (Phase 12)
- `public/assets/css/style.css` — 16:9 thumbnail/image/embed sizing; `touch-action: manipulation` on nav toggle; `min-height: 400px` on `.work-embed`
- `public/assets/js/main.js` — `touchend` iOS workaround for nav toggle

---

## Phase 13 — Claude Code (2026-06-09)

### Multi-select Categories & Exhibits on the Edit Work Form

Previously an artwork could only belong to a single category (`artworks.category_id`
FK) and could not be assigned to exhibits from its own edit form at all (exhibit
membership was only editable from the exhibit side via `exhibit_artworks`).

User requested both gaps closed and confirmed the **full many-to-many** approach
for categories — mirroring the existing `exhibit_artworks` junction-table pattern
rather than keeping categories single-select or adding a secondary-category field.

**Schema**: new `artwork_categories` junction table (`artwork_id`, `category_id`,
composite PK, both FKs `ON DELETE CASCADE`), added to `schema.sql` next to
`exhibit_artworks`. `migrate_artwork_categories.sql` creates the table and
backfills it from existing `artworks.category_id` values. Per the
"keep legacy field during the migration release" pattern (Phase 7/9,
`piece_type`/`piece_value`), `artworks.category_id` is **retained** (nullable,
unused by application code) as a rollback safety net — to be dropped in a future
cleanup migration.

**Models**:
- `Artwork.php`: `allSorted()`, `all()`, `find()`, `findBySlug()`, `trashed()` no
  longer JOIN `categories` directly; a new private `attachCategories()` batches
  category lookups for list queries, and `decorate()` attaches `categories` via
  new `categoriesFor()` for single-record fetches. New `categoryIds()` and
  `syncCategories()` (delete-then-reinsert, mirrors `Exhibit::syncArtworks`).
  `create()`/`update()` no longer read or write `category_id`.
  `allGroupedByCategory()` is confirmed dead code (no callers) and was left
  untouched.
- `Category.php`: `artworks()` now joins through `artwork_categories` instead of
  `WHERE category_id = ?`.
- `Exhibit.php`: `artworks()` dropped its now-pointless `LEFT JOIN categories`
  (category data was unused by `exhibit.php`). New `exhibitIdsForArtwork()` and
  `syncForArtwork()` (delete-then-reinsert from the artwork side, appending new
  rows to the end of each exhibit's existing `sort_order`).

**Controller** (`AdminController.php`): `artworkCreate`/`artworkEdit` now also
load `$allExhibits`, `$assignedCategoryIds`, `$assignedExhibitIds` for the form.
`resolveArtworkData()` drops `category_id` and adds `category_ids`/`exhibit_ids`
arrays from `$_POST`. `artworkStore`/`artworkUpdate` call
`Artwork::syncCategories()` and `Exhibit::syncForArtwork()` after the existing
`ArtworkMediaItem::syncForArtwork()` call. `draftArtworkFromPost()` now derives
`category_ids`/`exhibit_ids` from POST or, for an existing artwork, from
`Artwork::categoryIds()` / `Exhibit::exhibitIdsForArtwork()`, so validation-error
re-renders preserve checked boxes.

**Views**:
- `admin/artworks/form.php`: the single Category `<select>` was replaced with two
  checkbox-list fieldsets (Categories, Exhibits), reusing the existing
  `.exhibit-artwork-list` / `.exhibit-artwork-check` classes from the exhibit
  edit form verbatim — no new CSS.
- `work.php`: the single category link is now a loop over `$artwork['categories']`
  (comma-separated, same `.work-cat-sep`/`.work-category` classes); the
  meta-description fallback uses `categories[0]['name']` with the same generic
  fallback string.
- `admin/artworks/index.php`: the Category column now joins
  `array_column($w['categories'], 'name')` with `, `, falling back to `—`.

### Files Updated (Phase 13)
- `schema.sql`, `migrate_artwork_categories.sql` (new)
- `app/models/Artwork.php`, `app/models/Category.php`, `app/models/Exhibit.php`
- `app/controllers/AdminController.php`
- `app/views/admin/artworks/form.php`, `app/views/admin/artworks/index.php`,
  `app/views/work.php`
- `CONSTRAINTS.md` — new constraint for `artwork_categories` many-to-many

---

## Phase 14 — Antigravity (2026-06-09)

### Database Integration, Custom Interactive Multiselect Widget & Inline Creation

Applied the database migration for `artwork_categories` which was previously left unexecuted, successfully establishing the many-to-many relationship in the live database. Reframed the category and exhibit selection user interface into a custom, responsive, tag-based multiselect widget built on vanilla HTML/CSS/JS.

**UI/UX Refactoring**:
- Replaced the simple checklists for Categories and Exhibits with a custom `.multiselect-control` search and selection interface.
- Selected items are displayed as styled dismissible tag/chip elements.
- Typing in the input filters the dropdown options. Selecting an option marks it selected and hides/disables it from the dropdown. Removing a tag restores the option.
- Preserved standard browser form submission compatibility by dynamically sync-managing hidden input fields (`category_ids[]` and `exhibit_ids[]`).
- Rendered multiselect controls unconditionally, even when no categories or exhibits exist in the database, allowing users to create entries dynamically from a blank state.

**Inline Creation Overlay Modal**:
- Added support for inline creation of categories or exhibits that do not yet exist: if the user's search query does not match any current options, a `+ Create [Type] "[Name]"` option is shown at the bottom of the dropdown.
- Selecting it or pressing Enter triggers a custom in-site modal dialog (`dialog.inline-create-dialog` with native `<dialog>` overlay support) that displays confirmation options ("Create" and "Cancel") and allows renaming the proposed item before committing.
- On confirm, sends a POST request to new backend API endpoints (`/admin/categories/create-inline` and `/admin/exhibits/create-inline` in `AdminController.php`), returning the newly created ID and name in JSON format.
- The Javascript dynamically creates a new dropdown option element, adds it to the list, and selects it as a tag immediately without a page reload.

**Files Updated (Phase 14)**:
- `app/views/admin/artworks/form.php` (Custom multiselect HTML, hidden inputs, and inline-create dialog markup)
- `public/assets/css/admin.css` (Styles for multiselect UI and inline modal dialog styling)
- `public/assets/js/main.js` (Multiselect control logic, dialog overlay event handling, inline creation AJAX requests, and dynamic option injection)
- `app/controllers/AdminController.php` (New `categoryCreateInline` and `exhibitCreateInline` API methods)
- `public/index.php` (New routes mapping inline category and exhibit creation endpoints)
- `app/models/Artwork.php` (Implemented batch exhibit loading and single-item exhibit decoration)
- `app/views/admin/artworks/index.php` (Added the Exhibit column displaying all assigned exhibits after the Category column)

---

## Phase 15 — Claude Code (2026-06-09)

### Admin Full-Bleed Layout & Messages Management (read/flag/pin/trash)

Two changes, resolved via AskUserQuestion before implementation: (1) every
`/admin/*` page now uses the same near-100%-width layout instead of a
1000px-capped column with a separate 1280px "wide" exception for
Media/Navigation; (2) the Messages admin page gained read/unread, flag, and
pin-to-favorite toggles plus soft-delete via the existing Trash flow.

**Layout**:
- `public/assets/css/admin.css`: `.admin-main` changed from
  `max-width: 1000px` to `max-width: 100%` (full-bleed, edges aligned with
  `.admin-header`'s existing `2rem` padding; global `box-sizing:
  border-box` prevents overflow). The `.admin-main-wide` (1280px) rule was
  removed — with `.admin-main` at 100% it would have made Media/Navigation
  *narrower* than every other page.
- `app/views/admin/media.php` and `app/views/admin/navigation.php`: removed
  their `$mainClass = 'admin-main...'` overrides; `layout.php` already
  defaults `$mainClass` to `admin-main`, so all admin pages converge on one
  width.

**Messages**:
- `contact_messages` gained `is_read`, `is_flagged`, `is_pinned`,
  `sort_order`, `deleted_at` columns
  (`migrate_phase15_messages.sql`, `schema.sql`).
- New `app/models/ContactMessage.php`: `all()` (active, ordered),
  `trashed()`, `trashedCount()`, `softDelete()`/`hardDelete()`/`restore()`
  (mirrors `Category.php`), `toggleRead()`, `toggleFlagged()`,
  `togglePinned()`, `reorder()`.
- Ordering: `ORDER BY is_pinned DESC, (CASE WHEN is_pinned = 1 THEN
  sort_order ELSE 0 END) ASC, created_at DESC`. Pinning a message
  ("favorite") sets `is_pinned = 1` and `sort_order` to one less than the
  current minimum among pinned rows, jumping it to the top of the pinned
  group; pinned rows can then be drag-reordered via the existing
  `tbody[data-reorder-url]` JS (`/admin/messages/reorder`, mirrors
  `artworkReorder`). The `CASE` means unpinned rows always stay
  `created_at DESC`-ordered regardless of any stored `sort_order`, so
  reordering the whole table (the generic handler's behavior) doesn't
  freeze the live inbox order.
- `AdminController.php`: `messagesIndex()` now uses `ContactMessage::all()`;
  added `messageToggleRead`, `messageToggleFlag`, `messageTogglePin`,
  `messageReorder`, `messageDelete` (soft-delete); `trashIndex`/
  `trashRestore`/`trashPurge`/`trashEmpty` extended with a `'message'`/
  `'messages'` case alongside artwork/category/exhibit/media.
- `public/index.php`: new POST routes for the five message actions above.
- `app/views/admin/messages.php`: rewritten with a drag-handle column,
  `is-unread` row styling (bold), and per-row toggle buttons (`.msg-toggle-btn`
  / `.msg-toggle-btn.active`) for read/unread (●/○), flag (⚑), and pin (★/☆),
  plus a "Move to trash" form matching `artworks/index.php`'s pattern.
- `app/views/admin/trash.php`: added a "Messages" tab to `$tabs`, a
  `'messages' => 'message'` arm to the `$type` match, and a name+preview
  branch for the messages tab.

### Files Updated (Phase 15)
- `public/assets/css/admin.css`
- `app/views/admin/media.php`, `app/views/admin/navigation.php`
- `migrate_phase15_messages.sql` (new), `schema.sql`
- `app/models/ContactMessage.php` (new)
- `app/controllers/AdminController.php`
- `public/index.php`
- `app/views/admin/messages.php`, `app/views/admin/trash.php`
- `CONSTRAINTS.md` — new constraints for admin layout width and
  contact_messages soft-delete/ordering

### Resolved checkpoints
- [x] `migrate_phase15_messages.sql` applied to `srv1819.hstgr.io`
  (`u276695328_art.contact_messages`) after explicit user confirmation —
  confirmed via `SHOW COLUMNS` that `is_read`, `is_flagged`, `is_pinned`,
  `sort_order`, `deleted_at` are present.

---

## Phase 16 — Claude Code (2026-06-09)

### Public Gallery Homepage — Exhibits/Works Prominence Swap

User observed that on the gallery homepage, Works (large irregular grid)
read as far more prominent than Exhibits (small uniform thumbnail row),
even though Exhibits sit above Works — and asked for the inverse.

Gallery presented per AGENTS.md Rule 2: (1) swap grid treatments between
the two sections [chosen], (2) give Exhibits a full-width featured-hero
band with Works left unchanged (Reframe — "prominent" need not mean "same
grid, bigger spans"), (3) horizontal scrolling exhibits filmstrip with
Works left unchanged (unexpected — picks up the existing-but-unused
`.exhibits-strip` class name as a hint of an earlier filmstrip intent).

**Chosen**: swap grid treatments. In `app/views/gallery.php`:
- Exhibits now render in the irregular 12-column `.artwork-grid` with
  `size-large/wide/medium/small` variants (same `match ($i % 7)` formula
  Works used) and `.artwork-card` styling — Roman-numeral catalog counters,
  hover glow/scale.
- Works now render in the compact uniform `.exhibits-grid`
  (`auto-fill, minmax(180px,1fr)`) with `.exhibit-card` styling — no
  counters, no hover glow.
- No new CSS rules were added; only the grid container class and per-item
  card class (incl. size-class assignment) were swapped between the two
  sections. `/category/[slug]` and `/exhibit/[slug]` detail pages have
  their own independent `.artwork-grid` blocks (`category.php`,
  `exhibit.php`) and are unaffected.
- The "See More" overflow toggle (`gallery-work-overflow` /
  `#works-see-more`, `main.js`) is class/grid-agnostic and unaffected.

**Named caveat (accepted by user)**: with only one exhibit in the database
today, it renders as a single `size-large` (span-6) card with empty grid
space beside it on wide viewports. If this looks sparse in the browser,
revisit the per-exhibit size-class formula (e.g. always `size-wide`/full
span when `count($exhibits) === 1`) — not done preemptively per AGENTS.md
Rule 4 (don't substitute judgment the user hasn't asked for).

### Files Updated (Phase 16)
- `app/views/gallery.php`
- `CONSTRAINTS.md` — new constraint documenting this swap as intentional

---

## Phase 17 — Claude Code (2026-06-09)

### Contact Form Spam Mitigation — reCAPTCHA v3 + Honeypot/Time-Trap

User reported a lot of contact-form spam and asked for reCAPTCHA. Both
`/contact` (`PageController`) and `/about` (`AboutController`) post to the
same `contact_messages` table with zero prior anti-spam measures.

Per AGENTS.md's mandatory "New Vendor Dependency" question (any reCAPTCHA
choice sends visitor data to Google — off-domain, requires disclosure), a
gallery was presented per Rule 2: (1) reCAPTCHA v2 checkbox as literally
requested, (2) Reframe — honeypot + time-trap only, no vendor at all, (3)
unexpected — Cloudflare Turnstile (smaller privacy footprint than Google).
User countered with a fourth option not in the gallery: **Google reCAPTCHA
v3** (invisible, score-based, more accessible — no checkbox/challenge UI).
Adopted v3, with honeypot + time-trap added regardless as a free first layer
(agreed "either way" of vendor choice). Scope question: both `/contact` and
`/about` protected (confirmed — they share a backend, so protecting only one
just redirects spam to the other).

**Honeypot**: hidden **checkbox** (not text field) named `website`,
`aria-hidden`, `tabindex="-1"`, off-screen via new `.field-row-honeypot`
CSS rule. Checkbox chosen specifically because browser/password-manager
autofill can populate text fields named "website"/"url" with the user's own
site, which would silently swallow real submissions — checkboxes aren't
subject to that autofill heuristic. `spam_honeypot_tripped()` is true if the
checkbox was submitted as checked at all.

**Time-trap**: hidden `form_rendered_at` field set to `time()` at render;
`spam_timetrap_tripped()` is true if missing or submitted within
`SPAM_TIMETRAP_MIN_SECONDS` (3s) of render — a real human can't fill a
3-field form that fast.

Either trip → silent discard: redirect to `?sent=1` exactly as success, no
DB row, no reCAPTCHA call.

**reCAPTCHA v3**: new `app/helpers/recaptcha.php` (mirrors `oauth.php`
conventions — global functions, `oauth_env()`, `oauth_http_request()`).
`recaptcha_verify()` returns a score; `is_flagged = score <
RECAPTCHA_SCORE_THRESHOLD (default 0.5) ? 1 : 0` at insert time — **low
score saves the message but flags it** (visible via the ⚑ toggle shipped in
Phase 15's `/admin/messages`) rather than rejecting outright, to avoid
losing real messages to false positives. Empty token (no JS) or unset
`RECAPTCHA_SECRET_KEY` also resolve to flagged-not-rejected, so the form
works (degraded) before keys are configured. **Transport failures (network
error, malformed siteverify response) fail OPEN** — saved unflagged, as if
score were above threshold. Named, accepted tradeoff: a Google outage
silently degrades protection to honeypot/time-trap only.

`contact_messages.is_flagged` already existed (Phase 15) — no migration.

`grecaptcha.execute(siteKey, {action: 'contact'})` runs on form submit
(token is short-lived/single-use, can't be fetched at page load); inline
submit-handler script in `page.php`/`about.php`, gated on
`$recaptchaSiteKey` so it's omitted entirely until keys are set.

### Files Updated (Phase 17)
- `.env` — `RECAPTCHA_SITE_KEY`, `RECAPTCHA_SECRET_KEY`,
  `RECAPTCHA_SCORE_THRESHOLD` (all blank/default; see Resolved checkpoints)
- `app/helpers/recaptcha.php` (new)
- `public/index.php` — require `recaptcha.php`
- `public/assets/css/style.css` — `.field-row-honeypot`
- `app/views/page.php`, `app/views/about.php` — honeypot checkbox,
  time-trap field, recaptcha token field + submit-handler script
- `app/controllers/PageController.php` — `show()`, `contact()`
- `app/controllers/AboutController.php` — `index()`, `contact()`
- `docs/dependencies.md` — new Google reCAPTCHA v3 entry
- `CONSTRAINTS.md` — new constraint for the spam-mitigation pipeline

### Resolved checkpoints
- [ ] User needs to register `localhost`/`fornesusart.com` under one
  reCAPTCHA v3 site key at https://www.google.com/recaptcha/admin and fill
  `RECAPTCHA_SITE_KEY`/`RECAPTCHA_SECRET_KEY` into `.env`. Until then the
  reCAPTCHA script is omitted and every submission is saved with
  `is_flagged = 1` (honeypot/time-trap protection is still active).

---

<!-- Add a new dated section at the start of each phase following
     the same pattern. Resolved checkpoints from the prior phase
     should be marked [x] and left in place — do not delete them.
     They are the audit trail. If empty, begin with Phase 1. -->

