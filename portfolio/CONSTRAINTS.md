# Constraints

<!-- One entry per constraint. Format:
     CONSTRAINT: [plain-language description]
     SCOPE: [what it applies to]
     SET: [date or "this session"]

     Constraints are permanent until explicitly lifted.
     See AGENTS.md → User Constraints for full rules. -->


<!-- An empty file is still valid and still required.
     Absence of entries means no constraints have been stated yet —
     not that this file is optional. The agent must create this
     file at project root the first time any constraint is stated,
     even if AGENTS.md is read-only. -->

CONSTRAINT: Artworks must support mixed-media ordered slides using `artwork_media_items`, while legacy `artworks.piece_type` / `piece_value` remain in place through the migration release for fallback compatibility.
SCOPE: artworks schema, admin artwork form, gallery and work detail rendering
SET: 2026-06-09

CONSTRAINT: Admin authentication uses provider-backed admin identities via GitHub and Google OAuth only. No public user accounts table is introduced by this change.
SCOPE: all /admin/* routes
SET: 2026-06-09

CONSTRAINT: Uploaded files stored in public/uploads/ with .htaccess blocking PHP execution in that directory. MIME validated by magic bytes, not filename extension or Content-Type header.
SCOPE: file upload handling (app/helpers/upload.php)
SET: 2026-06-04

CONSTRAINT: Artwork slugs must not change after first publication. Once a /work/[slug] URL is live, it is permanent. Redirects required if a slug ever must change.
SCOPE: artwork admin form, Artwork model
SET: 2026-06-04

CONSTRAINT: Category and exhibit slugs must not change after first publication. Once /category/[slug] or /exhibit/[slug] is live, it is permanent.
SCOPE: category admin form, exhibit admin form, Category model, Exhibit model
SET: 2026-06-04

CONSTRAINT: Exhibits and artworks are many-to-many. An artwork may belong to multiple exhibits simultaneously. This is implemented via the exhibit_artworks junction table and must never be collapsed to a single foreign key.
SCOPE: exhibits schema, Exhibit::syncArtworks(), exhibit admin form
SET: 2026-06-04

CONSTRAINT: Artworks and categories are many-to-many. An artwork may belong
to multiple categories simultaneously. This is implemented via the
artwork_categories junction table and must never be collapsed to a single
foreign key. The legacy artworks.category_id column remains in the schema
(nullable, unused by application code) for this release as a rollback
safety net, per the pattern used for piece_type/piece_value.
SCOPE: artworks schema, categories schema, Artwork::syncCategories(),
Category::artworks(), artwork admin form
SET: 2026-06-09

CONSTRAINT: Every /admin/* page uses the shared .admin-main layout class at
max-width: 100% (full-bleed). Do not reintroduce per-page width modifiers
(e.g. an "admin-main-wide" variant) — all admin pages must share the same
relative screen real estate.
SCOPE: public/assets/css/admin.css, app/views/admin/layout.php, all
app/views/admin/* pages
SET: 2026-06-09

CONSTRAINT: Contact messages support soft-delete via deleted_at, restored or
purged through the unified /admin/trash "Messages" tab, matching the
Artwork/Category/Exhibit/Media pattern. Pinned messages (is_pinned) always
sort above unpinned ones; sort_order only affects ordering among pinned
rows — unpinned rows always order by created_at DESC regardless of their
stored sort_order, so the inbox stays newest-first by default.
SCOPE: contact_messages schema, app/models/ContactMessage.php,
app/views/admin/messages.php, app/views/admin/trash.php
SET: 2026-06-09

CONSTRAINT: On the public gallery homepage (app/views/gallery.php), the
Exhibits section deliberately uses the irregular .artwork-grid (size-large/
wide/medium/small variants, .artwork-card styling — Roman-numeral counters,
hover glow) and the Works section deliberately uses the compact uniform
.exhibits-grid (.exhibit-card styling, no counters/hover glow). This is an
intentional inversion of the classes' original associations (Exhibits is
now the visually prominent section, Works is the quieter catalog). Do not
"fix" this swap by reverting class names — it is the requested design.
/category/[slug] and /exhibit/[slug] detail pages have their own separate
.artwork-grid blocks and are unaffected by this constraint.
SCOPE: app/views/gallery.php
SET: 2026-06-09

CONSTRAINT: Contact form submissions (/contact and /about) pass through
honeypot + time-trap checks before any other processing. If either trips,
the request is silently discarded — redirected to ?sent=1 exactly as a
successful submission, with no database row written and no reCAPTCHA call
made. The honeypot is a hidden checkbox (not a text field) specifically to
avoid browser-autofill false positives. reCAPTCHA v3 verification (when
configured) determines is_flagged at insert time: score >=
RECAPTCHA_SCORE_THRESHOLD (default 0.5) saves normally (is_flagged = 0);
score below threshold — including when g-recaptcha-response is empty (JS
disabled) or RECAPTCHA_SECRET_KEY is unset — still saves the message but
with is_flagged = 1 for review in /admin/messages. reCAPTCHA verification
transport failures (network error or malformed siteverify response) fail
OPEN: the message is saved as if score were above threshold, with no flag
set. Both /contact and /about are protected identically via
spam_honeypot_tripped() / spam_timetrap_tripped() / recaptcha_verify() in
app/helpers/recaptcha.php.
SCOPE: app/controllers/PageController.php (contact, show),
app/controllers/AboutController.php (contact, index),
app/helpers/recaptcha.php, app/views/page.php, app/views/about.php
SET: 2026-06-09
