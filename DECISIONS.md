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

- **Stack:** No-framework PHP site with shared route handling in `public/index.php`.
- **Deployment:** PHP-capable static/shared hosting or PHP built-in server for local preview.
- **Database:** None.
- **Version pins:** None.
- **Framework AGENTS.md:** No framework-specific AGENTS.md exists — sessions follow root AGENTS.md only.
- **Profile switch rule:** Stop before touching existing files. Record
  current state and reason here. Confirm new profile explicitly. Flag
  every file needing migration before starting.

---

## 2026-06-11 — Phase 1 — Augment Humankind PHP Site

### Stack Confirmed
- Built as a no-framework PHP site with clean public routes.
- Public URL structure confirmed before implementation: `/`, `/services`, `/notes`, `/contact`.
- Superseded on 2026-06-12: `/contact` initially used an email link in v1, then moved to a reCAPTCHA-protected backend intake form.

### Schema and Data Decisions
- No database, persistence layer, or schema was added.
- No public API endpoint was added.
- No form submission handling was added.

### Files Created
- `public/index.php` — shared PHP route handler and page rendering for all v1 routes.
- `public/assets/styles.css` — local responsive CSS with accessibility states and no external assets.
- `public/.htaccess` — clean URL rewrite support for Apache-style PHP hosting.
- `public/assets/friendly-guide.png` — URL-safe copy of the existing robot image.

### Vendor Dependencies Added
- None.

### Environment Variables Required
- None.

### Gaps and Deferred Items
- Superseded on 2026-06-12: backend contact form decisions were made, with no submission storage, reCAPTCHA v3 spam protection, brief privacy copy, and Hostinger SMTP delivery.
- `DESIGN.md` still has no confirmed references or Derived Identity; the v1 visual direction was based on the approved session plan.

### Unresolved Checkpoints Entering Phase 2
- [x] Decide whether to build the backend contact form.
- [ ] Decide whether to populate `DESIGN.md` with confirmed references before future visual expansion.

## 2026-06-11 — Documentation Maintenance

### Memory and Design Updates
- Added confirmed stack, brand direction, and contact-form boundary entries to `MEMORY.md`.
- Added confirmed mascot-forward Fieldguide observed taste entry to `DESIGN.md`.
- Left `DESIGN.md` References and Derived Identity unfilled because no formal reference workflow has been completed.

### Corrections Applied
- Replaced placeholder `REVIEW REQUIRED` rows with `None currently` to avoid treating template text as active session blockers.
- Expanded `README.md` with the v1 direction, services, routes, and durable-route note.
- Corrected `env.example` to reflect that v1 has no required environment variables.

## 2026-06-11 — Deployment Correction

### Corrections Applied
- Updated the Hostinger FTP workflow to deploy `public/` as the local directory into `/public_html/`.
- Kept production URLs at `/`, `/services`, `/notes`, and `/contact` rather than redirecting visitors into `/public`.
- Noted in `README.md` that deployments should upload `public/` contents to the hosting document root.
- Switched the FTP deploy action to `.ftp-deploy-sync-state-public.json` so it does not reuse the stale sync state from the earlier repository-root upload.
- Documented the intentionally small production file layout and denied direct web access to hidden dotfiles through `.htaccess`.

## 2026-06-11 — WCAG 2.1 AA Pareto Pass

### Audit Outcome
- Full accessibility audit of `public/index.php` and `public/assets/styles.css` found the site already compliant on semantic landmarks, heading order, skip link, alt text, link text, `lang` attribute, and `aria-*` usage.
- An initial automated pass flagged `--ink-soft` text and `--ink`-on-`--yellow` card backgrounds as contrast failures. Manual recalculation via the WCAG relative-luminance formula showed both pass comfortably (~6.2–6.6:1 and ~9.1:1 respectively, against the 4.5:1 requirement). **These were false positives — no palette/brand changes were made.**

### Fixes Applied (public/assets/styles.css)
- `:focus-visible` outline color changed from `--orange` (~1.9–2.0:1 against `--paper`/`--white`, failing WCAG 1.4.11's 3:1 non-text contrast requirement) to `--line` (~8.8–9.5:1). Sitewide effect on every focusable element.
- Added a `prefers-reduced-motion: reduce` media query disabling `.button` hover transform/transition and the `.guide-panel` rotation (WCAG 2.3.3).

### Unresolved Checkpoints
- [ ] Consider adding an automated accessibility check (axe-core or Lighthouse CI) to the deploy workflow as a regression guard — would require the New Vendor Dependency question before adding.
- [x] When the deferred backend contact form is built, ensure all inputs have `<label for>` associations and validation errors use `aria-live`/`aria-describedby`, per the `testing` skill pre-merge checklist.

## 2026-06-11 — reCAPTCHA Contact Form

### Components Built
- Replaced the `/contact` mailto CTA with a low-friction inquiry form that posts back to `/contact`.
- Added CSRF protection, a honeypot field, reCAPTCHA v3 verification, and inline success/error states.
- Added PHPMailer SMTP delivery through Composer, with dependencies installed into `public/vendor` for Hostinger FTP deployment.

### Schema and Data Decisions
- No database, persistence layer, or file-based submission storage was added.
- Contact submissions are emailed only through the configured SMTP provider.
- `/contact` remains the only public contact URL; no thank-you route was added.

### Vendor Dependencies Added
- Google reCAPTCHA v3 — protects contact form submissions; documented in `docs/dependencies.md`.
- PHPMailer `v7.1.1` — sends SMTP email; documented in `docs/dependencies.md`.
- Hostinger SMTP — transports inquiry emails; documented in `docs/dependencies.md`.

### Environment Variables Required
- `RECAPTCHA_SITE_KEY`
- `RECAPTCHA_SECRET_KEY`
- `RECAPTCHA_MIN_SCORE`
- `SMTP_HOST`
- `SMTP_PORT`
- `SMTP_ENCRYPTION`
- `SMTP_USERNAME`
- `SMTP_PASSWORD`
- `SMTP_FROM_EMAIL`
- `SMTP_FROM_NAME`
- `CONTACT_TO_EMAIL`

### Gaps and Deferred Items
- Resolved on 2026-06-12: reCAPTCHA keys and Hostinger SMTP credentials were configured locally, the config verifier passed, and an end-to-end browser submission returned the inline success panel.

## 2026-06-12 — Hostinger SMTP Configuration Guardrails

### Corrections Applied
- Clarified that IMAP settings are for reading mail in an email client and are not used by the contact form.
- Set `env.example` to the Hostinger outbound server default `SMTP_HOST=smtp.hostinger.com`.
- Added runtime validation that the contact form uses Hostinger SMTP settings: `smtp.hostinger.com`, an email-address SMTP username, a matching verified `SMTP_FROM_EMAIL`, a valid `CONTACT_TO_EMAIL`, and compatible port/encryption pairs.

### SMTP Defaults
- `SMTP_PORT=465` with `SMTP_ENCRYPTION=smtps`.
- Alternative supported pair: `SMTP_PORT=587` with `SMTP_ENCRYPTION=starttls`.

### Verification Utility
- Added `scripts/verify-contact-config.php` to validate required reCAPTCHA and Hostinger SMTP configuration shape without sending email or printing secret values.

## 2026-06-12 — Contact Form End-to-End Verification

### Verification Outcome
- `php scripts/verify-contact-config.php` passed with the configured local `.env` values.
- The configured `/contact` form rendered with the Google reCAPTCHA v3 script and enabled submit button.
- A real browser submission against `http://127.0.0.1:8083/contact` returned the inline success panel: `Message sent.`
- The test delivery produced an email from `Augment Humankind <contact@augmenthumankind.com>` to the configured receiving inbox.
- A separate human-submitted test inquiry was also received, confirming the form works for the intended collaboration/hiring inquiry flow.

### Current Status
- The contact form is no longer deferred.
- `/contact` remains the durable public URL.
- Submissions are emailed only; no database or file-based submission storage was added.

## 2026-06-12 — Phase 2 — Pages CMS + Tiptap

### Stack Confirmed
- Added a no-framework `app/` MVC layer (front-controller router → controller →
  PDO model → view), dispatched from `public/index.php` for `/admin/*`,
  `/portfolio/*`, and `/media|image/[id]` paths (Phase 1 groundwork), now
  exercised by the Pages CMS.

### Schema and Data Decisions
- `pages` and `page_sections` tables (from `schema.sql`, applied in Phase 1)
  now hold live content: `/services` and `/notes` were seeded from the prior
  static `public/index.php` markup via `seed_phase2_pages.sql`, each as a
  single heading-less section rendered raw by `app/views/managed_page.php`.
- `/services` and `/notes` are DB-first with a static-markup fallback
  (`Page::safeFindPublishedBySlug()` catches `Throwable`) — Rule 5 holds even
  if the DB is unreachable.
- New pages created via `/admin/pages` are reachable through a catch-all
  `/{slug}` route in `public/index.php` (falls through to 404 if no
  published page matches).

### Files Created
- `app/helpers/slugify.php`, `app/helpers/seo.php`
- `app/models/Page.php`, `app/models/PageSection.php`
- `app/controllers/PageController.php`, `app/controllers/Admin/PagesController.php`
- `app/views/partials/{header,footer}.php`, `app/views/managed_page.php`
- `app/views/admin/pages/{index,form,section-form,trash}.php`
- `public/assets/css/tiptap.css` — Tiptap toolbar/editor/media-picker CSS
  reskinned from the original "Celestial Archive" theme to AH's "Pareto"
  tokens (no new fonts; system font stack throughout).
- `public/assets/js/tiptap-editor.js` — full port (9 `@tiptap/*` extensions,
  custom `FontSize`/`IframeNode`/`LinkWithTitle`/image-edit NodeView, media
  picker). Media picker's Select/Upload/Import actions call
  `/admin/media/*` endpoints that don't exist yet — deferred to Phase 3, fails
  gracefully in the meantime.
- `public/assets/js/main.js` — minimal port: drag-reorder for
  `tbody[data-reorder-url]` and `page-name` → `page-slug` autofill.
- `app/views/admin/layout.php` — added the esm.sh importmap, `tiptap.css`
  link, media-picker `<dialog>` markup, and `main.js`/`tiptap-editor.js`
  script tags.

### Vendor Dependencies Added
- Tiptap via `esm.sh` CDN (`@tiptap/*@2`, 9 packages) — documented in
  `docs/dependencies.md`. Falls back to a plain `<textarea>` if esm.sh is
  unreachable; no public-facing page depends on it.

### Verification Outcome
- `/`, `/services`, `/notes`, `/contact` all return 200; `/services` and
  `/notes` render via the DB-backed managed-page path with content identical
  to the prior static version (plus added SEO meta tags).
- Logged into `/admin/pages` (test session), created a new page
  ("Phase 2 Test Page", slug `phase2-test`), confirmed the Tiptap-backed
  section editor renders (importmap/tiptap.css/media-picker all present),
  added a section, confirmed `/phase2-test` rendered it with `.managed-section`
  markup, then deleted the test page (404 confirmed afterward).
- `php -l` clean on all new/changed PHP files.

### Regression Found and Fixed
- `scripts/verify-contact-config.php` set `REQUEST_URI = '/notes'` to load
  `configValue()`/`smtpConfiguration()` (defined later in `public/index.php`)
  without rendering output, relying on `/notes` falling through to end-of-file.
  Phase 2's DB-backed `/notes` route now calls `exit` early (via
  `PageController::show()`), which flushed the `/notes` HTML to stdout and
  skipped the actual config checks (silently "passing" with no real check).
  **Fix:** changed the harness's `REQUEST_URI` to `/contact`, which is
  untouched by the managed-page routing and falls through normally.
  Re-ran: `php scripts/verify-contact-config.php` → "Contact form
  configuration shape is valid." (exit 0).

### Unresolved Checkpoints Entering Phase 3
- [ ] `/admin/media/{library,upload,import}` endpoints needed before the
  Tiptap media picker and `page-og-image` "Choose from Library" button are
  functional (currently inert with graceful errors).
- [ ] Decide fate of the `portfolio/` reference folder (deferred per the
  approved plan, after Phase 3 verification).

## REVIEW REQUIRED — Read before starting next session
<!-- Agent writes this block. Human must confirm or override each item before new code is written. -->
- None currently.
