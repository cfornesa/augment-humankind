<!-- Agent reads this file at every session start. Surface any entry marked PENDING CONFIRMATION
to the human before proceeding. Do not act on a pending entry — wait for explicit confirmation
or rejection. -->

2026-06-11 STACK Augment Humankind v1 is a no-framework PHP site with durable routes at `/`, `/services`, `/notes`, and `/contact`.

2026-06-11 DECISION The v1 brand direction is Fieldguide for nontechnical teams, with the friendly robot used as a primary mascot signal.

2026-06-12 DECISION The `/contact` page uses a reCAPTCHA v3-protected PHP form with PHPMailer over Hostinger SMTP and no database or submission storage.

2026-06-12 DECISION `/services` and `/notes` are now DB-backed via the Pages CMS (with static fallback); new pages are created in `/admin/pages` and reach the public site via a catch-all `/{slug}` route.

2026-06-12 DECISION Page section editing in `/admin` uses the Tiptap rich-text editor loaded from the esm.sh CDN; it falls back to a plain textarea if the CDN is unreachable. The media picker is now wired to flat `/admin/media/*` endpoints for library, upload, and import.

2026-06-12 NOTE The contact-config verification harness (`scripts/verify-contact-config.php`) was switched from probing `/notes` to `/contact`, because DB-backed managed pages now `exit` early — relevant if routing changes again.

2026-06-12 DECISION Phase 3/4 admin CMS uses flat protected routes: `/admin/artworks`, `/admin/categories`, `/admin/exhibits`, `/admin/media`, `/admin/trash`, and `/admin/navigation`. Public navigation is backed by `navigation_items` with fallback to Mission, Services, Field Notes, Contact, and Portfolio.
