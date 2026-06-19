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

2026-06-14 DECISION Platform assimilation uses the current PHP MySQL database as the only writable target; the live platform database is read/export-only via `PLATFORM_*`; canonical feed route is `/blog`.

2026-06-14 DECISION Phase 3 (auth/admin/CMS reconciliation) is complete. The nav_links migration mapping bug (platform `kind='system'` rows silently deleted by `removeDefunctSystemItems()`) is fixed; the dropped "Feeds" and "Categories" links are repaired as visible externals at `/blog/feeds` and `/blog/categories`. The public header now has 11 items: Home, Services, Field Notes, Blog, Contact, Portfolio, About, Exhibit, Coded Art, Feeds, Categories.

2026-06-14 DECISION Phase 4A (Admin Content Core) is complete, no schema changes: `/admin/posts` (CRUD for draft/published/scheduled blog posts, with categories and featured image) and `/admin/comments` (comment/reaction moderation, `?tab=comments|reactions`) are now live, with recycle-bin coverage for posts and comments at `/admin/trash`. Phase 4 is now sequenced 4B-4H in `docs/platform-assimilation-plan.md`.

2026-06-14 DECISION Phase 4B (Scheduled publishing + feed export parity) is complete. `publishDuePosts()` flips `scheduled` → `published` and overwrites `created_at`. New routes: `/feeds/mf2`, `/blog/category/{slug}/feed.*`, `/{slug}/feed.*`, plus legacy 301 redirects. Existing 6 feed routes enhanced with `subtitle`, `author`, `summary`, `category`, `description`, `authors`, `content_text`, `tags`. `/export.json` remains JSON Feed 1.1 per Rule 5; `/feeds/mf2` is the mf2 equivalent.

2026-06-14 DECISION Phase 4C (Art pieces & platform exhibits) is complete. Platform art pieces live at `/pieces/*` with admin at `/admin/pieces/*`. New models: `PlatformArtPiece`, `PlatformArtPieceVersion`, `PlatformExhibit`. Public render uses iframe for p5.js or CSS output. Existing portfolio at `/portfolio/*` preserved per Rule 5.

2026-06-14 DECISION Phase 4D (Feed ingestion & moderation) is complete. `FeedSource` model with CRUD, `feed-ingest.php` helper for RSS/Atom parsing, `FeedSourcesAdminController` with sources table and pending-import moderation queue. Admin nav now includes "Feeds".

2026-06-14 DECISION Phase 4E (Site identity & media) is complete. `SiteAsset` and `MediaAsset` models, `SiteIdentityAdminController` with three-tab admin (Settings, Assets, Media Library). Admin nav now includes "Identity".

2026-06-14 DECISION Phase 4F (User profiles & AI settings) is complete. `UserAiVendorSettings` and `UserAiVendorKeys` models, AES-256-GCM encryption helper (`encryption.php`), `UserProfilesAdminController` with three-tab admin (Users, AI Settings, API Keys). Admin nav now includes "Users".

2026-06-14 DECISION Phase 4G (Platform connections & syndication foundation) is complete. `PlatformConnection` and `PostSyndication` models, `PlatformConnectionsAdminController` with two-tab admin (Connections, Syndications). Scaffold only — no live adapters. Admin nav now includes "Connections".

2026-06-14 DECISION Phase 4H (Syndication adapters) is complete. All 8 platform adapters implemented: Bluesky, WordPress.com, WordPress self-hosted, Blogger, Substack, LinkedIn, Facebook, Instagram. Uses `guzzlehttp/guzzle` as shared HTTP client. `AdapterFactory` maps platforms to adapter instances. `SyndicationPayload` normalizes post data. Content helpers ported from platform's `content.ts`. `PlatformConnectionsAdminController::publish()` orchestrates syndication.

2026-06-14 DECISION Platform Rectification Pass Round 2 is complete: 9 more admin views fixed (Closure-vs-`ob_start()` bug), `/admin/posts`'s second deprecation (`null =>` array key) fixed, shared admin CSS vocabulary (`.admin-container`, `.admin-tabs`, `.admin-link`, `.status-badge`, `.field`, `.form-status`, etc.) added to `admin.css`, piece-prompt overflow fixed, Immersive/VR links surfaced, `/admin/pieces/{id}/edit` fixed with a live preview pane, and `/admin/media` now unifies `media_files` + the 102 migrated `media_assets` (read-only).

2026-06-14 NOTE The platform's "VR mode" is the Three.js immersive presentation at `/immersive/pieces/{id}` (`ImmersiveController::piece`) — not WebXR/A-Frame, which was already rolled back. Now linked from `/pieces/{id}` and `/admin/pieces`.

2026-06-14 NOTE Recurring bug pattern in admin views: `$content = function () use (...): void { ... };` produces a Closure that `<?= $content ?>` in `layout.php` can't stringify (fatal). Correct pattern is `ob_start(); ... $content = ob_get_clean();`. Watch for this in any new/copied admin view.

2026-06-15 DECISION Fixed the art piece engine validation whitelist to support `p5`, `c2`, `three`, and `svg` (dropped invalid `css`). Restructured `/admin/pieces/{id}/edit` and `/admin/pieces/create` into Metadata/HTML/CSS/JS tabs that edit the `current_version` in place.

2026-06-15 DECISION Wired migrated `media_assets` CRUD parity under `/admin/media` and `/admin/trash` via `/admin/media/asset/{id}/update|trash|destroy` routes, supporting metadata updates, trashing, restoring, and permanent purging.

2026-06-15 DECISION Implement AI Piece Generation (Round 4): created Guzzle-based multi-vendor client (AiProviderClient.php), GET/POST generation routes under /admin/pieces/generate, and preview/save templates. Uses a 3-attempt validation and repair loop checking static constraints and window.sketch definitions.

2026-06-15 DECISION Implement Immersive/VR Gallery Overhaul (Round 5): completed full-immersion Three.js pieces (/immersive/pieces/{id} where engine === 'three'), gallery room framing for P5.js, C2.js, SVG, and image pieces, and the multi-frame progressive exhibit wall (/immersive/exhibits/{slug}). Built dynamic client-side iframe lazy-loading (public/embed.js) that intercepts and upgrades embeds inside blog posts and CMS pages using IntersectionObserver. Fully verified that check-platform-deletion-readiness.php passes with HTTP check validation.

2026-06-15 DECISION Platform-assimilation gap closure (items 1-7) is complete: `scripts/repair-platform-embed-links.php` normalizes embed iframe URLs; new public `/exhibits` + `/exhibits/{slug}`; `/admin/platform-exhibits` (+`/library`) read-only admin listing distinct from native `/admin/exhibits`; TipTap "Insert art piece or exhibit" picker backed by `/admin/pieces/library`; iframe-embed and AI-profile `<dialog>` pickers replace both `window.prompt()` calls; `readiness_check_post_embeds()` added to `check-platform-deletion-readiness.php`; VR/immersive pages (piece/image/exhibit) now show full metadata parity with the legacy platform (About this piece, fixed image description, Artist Statement/Biography/Works cards).

2026-06-15 NOTE The `<dialog>`-based `.media-picker-*` pattern (header/tabs/panel/grid/item/footer, plus `.media-picker-field-label/-textarea/-select`) is now the standard for all TipTap insertion UIs (media, pieces/exhibits, iframe, AI profile) — reuse this for any future insertion dialog instead of `window.prompt()`.

2026-06-15 DECISION Resolved abstract-studies exhibit embed: Added iframe_code column to platform_exhibits schema. Inserted u276695328_augmentart record for abstract-studies (ID 6) containing the external iframe source. Normalized Post #9 (posts.id=9) URL to relative /immersive/exhibits/abstract-studies?embed=1. Exhibits Controller and embed.js updated to render iframe_code directly. All link repair and readiness bypass exceptions removed. Platform deletion is now fully unblocked.

2026-06-15 DECISION Renamed native portfolio elements ('Works' -> 'Exhibit', 'Exhibits' -> 'Collections') and platform exhibits ('Platform Exhibits' -> 'Platform Collections') in the target PHP database and routes, using the Pure approach. Replaced database text field route references (in posts, page sections) in accordance with the new scheme. Modified models (Collection, Exhibit, ExhibitMediaItem, PlatformCollection), controllers, trash flow, and templates to align with the new scheme. Modified media library views and TipTap editor selection grids to support iframe/embed kinds with HTML copy templates and preview rendering. Verified all check-platform-deletion-readiness.php verifications pass (100% PASS with HTTP checks).

2026-06-15 DECISION Implemented the dual-mode VR/immersive interface on PHP views (piece.php, collection.php, image.php) using the CSS-driven Overlay approach, supporting standard split layout and fullscreen canvas-only immersive layout.
2026-06-15 DECISION Implemented iframe embed code copy actions (plain, custom interactive, and CMS interactive) on immersive views via navigator.clipboard and custom toast feedback.
2026-06-15 DECISION Fixed Three.js and C2.js runtime initializers in piece-render.php by exposing window.THREE globally and loading the c2.js library on-demand.

2026-06-15 DECISION Integrated split-pane editor (desktop), full-canvas (tablet/mobile), and revertible AI prompt-centric refinement (Reframe) into a unified workspace under `/admin/pieces/create` and `/admin/pieces/{id}/edit`.

2026-06-15 DECISION Three.js runtime is now consistent across all four rendering surfaces: `public/app/helpers/piece-render.php`, `public/app/views/admin/pieces/form.php`, `public/app/views/admin/pieces/generate-preview.php`, and `public/embed.js`. Each uses `instrumentedThree`, `autoFit`, `ensureFallbackLighting`, `OrbitControls`, `startFrame(count)`, and `WebGLRenderer({ canvas })`. Any future Three.js fix must be mirrored in all four files.

2026-06-15 DECISION AI Refine endpoint (`POST /admin/pieces/refine-ai`) hardened with SVG edge-case handling (default `window.sketch = () => {};` when JS block is omitted) and default HTML/CSS fallbacks when the AI omits blocks. The same 3-attempt retry/repair loop and preflight validation as generation are used.

2026-06-15 NOTE Two test suites created in `tests/`: `art-piece-generation.php` (25 tests for code extraction, validation, prompts) and `three-runtime-consistency.php` (36 tests for runtime parity across all rendering files). Run with `php tests/art-piece-generation.php` and `php tests/three-runtime-consistency.php`.

2026-06-15 DECISION Resolved browser timeouts/freezes in immersive/default VR modes by correcting DOM traversal sanitization to remove traversed child nodes. Restored Three.js and Full Canvas rendering by binding the global THREE instance and overriding WebGLRenderer render/setSize methods to capture scene and camera references across all four rendering paths.

2026-06-15 DECISION Fixed collection/image embed rendering in blog posts by changing template.appendChild to template.content.appendChild inside public/embed.js web components. Corrected Tiptap insert picker dialog and script in layout.php and tiptap-editor.js to reference Collections instead of Exhibits, using the correct grid selector and load state variables.

2026-06-15 DECISION Hardened Three.js camera auto-fit bounding box calculations to ignore skyboxes (BackSide materials), particles (Points), large objects (dimensions >= 30), and large planes (dimensions >= 15). If no valid artwork bounding box is found, the camera defaults to the sketch's pre-configured position instead of zooming to infinity.

2026-06-15 DECISION Hardened Three.js camera auto-fitting to preserve custom camera positions (lengthSq > 0.01) and zoomed in default auto-fit views 3.5x.
2026-06-15 DECISION Appended returnTo query parameters to all VR gallery buttons in embed.js and public views, enabling the Back button to route to referring blog posts.
2026-06-15 DECISION Enabled route dispatching for /collections paths in public/index.php gate, restoring platform collection detail rendering.
2026-06-15 DECISION Implemented batched 3-item progressive see-more gallery disclosure on portfolio.php.
2026-06-15 DECISION Portfolio taxonomy is now split three ways: `/admin/categories` manages blog post categories, `/admin/art-media` manages piece taxonomy (`category_scope='portfolio'`), and `/admin/exhibit-collections` is the renamed native collection surface. Public redirects keep `/portfolio/collections`, `/portfolio/categories`, and `/portfolio/category/{slug}` durable while canonical routes are `/portfolio/exhibit-collections` and `/portfolio/art-media`.
2026-06-15 DECISION Aligned blog action buttons via flexbox, keeping Edit/Expand at the top-right and Embed/Share/Comments at the bottom.

2026-06-16 DECISION Fixed mobile viewport overflows by restricting embed elements and web components to max-width: 100% and reducing min-height on small viewports.

2026-06-16 DECISION Upgraded the art piece thumbnail capture flow with allow-same-origin sandboxing, dirty-check auto-capture-on-save, and a sequential bulk-regeneration utility in the admin index.

2026-06-16 DECISION Added close-up Three.js gallery room thumbnail generation for Platform Collections, including a database-backed schema option, conditional WebGL preserveDrawingBuffer properties, and auto-capture-on-save form interception.
2026-06-16 DECISION Resolved blank SVG thumbnails by copying computed DOM styles inline to vector nodes before canvas serialization, disabling transient CSS animations during capture.
2026-06-16 DECISION Synced platform collection edit form states with hidden input thumbnail fields and AJAX JSON slug responses, preventing 404s and data loss on metadata saves.
2026-06-16 DECISION Added a Thumbnail column to Pieces and manual "Generate Thumbnail" buttons on rows in both pieces and platform collections list views.
2026-06-16 DECISION Dark-mode contrast regressions caused by bright accent surfaces inheriting scheme-flipped text were corrected site-wide across all style presets, and row-level actions for pieces/platform collections were unified to one lightweight button pattern.
2026-06-16 DECISION Public member profiles now live at `/user/{username}` with `/user/settings` for profile and style preferences. Comments require sign-in, and owners can now edit or soft-delete their own comments on posts, pieces, exhibits, and exhibit collections.
2026-06-16 DECISION Owner-edit comment forms stay hidden until explicitly opened, and blog feed cards now expand inline by replacing the preview slot while keeping an always-visible Expand/Collapse toggle.

2026-06-17 DECISION Search/filter/sort + infinite scroll are now fully implemented on all 6 public archive pages (/pieces, /collections, /portfolio/pieces, /portfolio/platform-collections, /portfolio/exhibits, /portfolio/exhibit-collections) and the blog. Shared UI pattern: content-filter-bar + filter-bar-primary (search + button inline) + filter-bar-secondary <details> with chip fieldsets (SORT everywhere; TYPE/engine chips only on pieces pages; Category chips on blog). All search labels are sr-only — no visible text prefix before any input. data-listing-status is visually hidden site-wide. Infinite scroll uses the +1 trick (fetch PAGE_SIZE+1, derive hasMore, no COUNT query) with filter-aware $fetchUrl so batch requests preserve active filters. /portfolio gallery page (See More buttons per section) is intentionally untouched.
2026-06-17 DECISION Public canonical/syndication origin now resolves from `site_settings.canonical_public_url` first, then `PUBLIC_SITE_URL`, then the request host. Social-card metadata and emitted public links should follow that resolver so local publishing still points to the public site.
2026-06-17 DECISION Admin navigation is now registry-driven and owner-orderable from `Identity -> Design`; the same order should drive the desktop sidebar, mobile hamburger menu, dashboard cards, and admin account-menu destinations.
2026-06-17 DECISION Admin IA now separates concerns as `Users` (user management only), top-level `AI Settings` (`AI Profiles`, `API Keys`, `AI Vendor`), guided `Platform Connections`, and guided `Feeds` surfaces. Raw JSON/schema editing should stay internal rather than operator-facing.

2026-06-17 DECISION Blog post admin has section-based editing (`post_sections`), inline category creation, "Publish to" platform fieldset with per-platform draft text for Bluesky/LinkedIn, post calendar at `/admin/posts/calendar`, and automatic scheduled-post publishing on every index visit. `posts.content` is always `''`; content lives in `post_sections`.

2026-06-17 NOTE CSS specificity trap: `.form-row { display: grid; }` in `admin.css` beats UA-stylesheet `[hidden]`. Fix: always add `.form-row[hidden] { display: none; }` alongside, and use `element.style.display` in JS (inline style beats everything).

2026-06-17 NOTE `SyndicationPayload::fromPost()` reads `$post['content']` which is always `''` in the section system. Callers must populate `$payload->contentHtml` from `PostSection::allForPost()`. Relative image paths (`/image/123`) must be prefixed with `seo_origin()` before passing to external APIs.

2026-06-17 DECISION Syndication failures are now surfaced: `handleSyndication()` returns a failures array; `postStore()`/`postUpdate()` show a `?syndication_error=` banner on `/admin/posts`. The Syndications tab in Platform Connections shows `error_message` in red.

2026-06-18 DECISION Platform publishing OAuth app credentials for the PHP shell are DB-only, and malformed migrated provider app rows should be normalized to empty placeholders rather than preserved as undecryptable ciphertext.

2026-06-18 CORRECTION Exact session-row parity between the legacy platform DB and the live PHP DB is not a stable deletion-readiness invariant after cutover; session drift should be treated as operational state, not migration failure.

2026-06-19 DECISION Native media uploads/imports are draft-first: `media_files.status` governs readiness, editor/slide pickers only insert `ready` assets, and video posters are linked image assets via `poster_media_file_id`.
Picker and Media Library confirmation must persist `media_files.alt_text` before any image/video asset is considered reusable.

2026-06-19 DECISION The shared Three.js runtime must reconcile OrbitControls after keyboard/click translation so drag/pan preserves the current zoom distance; only wheel/pinch is allowed to change zoom.

2026-06-19 DECISION Managed pages in `draft` are public-404/private-preview: guests should not see them at their public URLs, while signed-in admins may preview them with an explicit draft notice.

2026-06-19 DECISION `site_settings.settings_json` is the compatibility fallback for editable settings like `canonical_public_url` when a dedicated column is missing, so the admin UI never silently drops saved values.

2026-06-19 CORRECTION Any save handler shared by multiple tabs/forms on one DB row must only update fields present in that request's `$_POST` — never rebuild the full row with defaults — or saving one tab silently wipes another's data.
Bit twice in Site Identity: first the logo (Settings vs. Design tabs), then all Settings-tab text (site title, hero/about copy, CTA, copyright). Fixed at the single `updateSettings()` chokepoint in `SiteIdentityAdminController`.

2026-06-19 CORRECTION `public/index.php`'s static/managed-page route and `app/router.php`'s MVC route load different helper sets — always check both load `helpers/auth.php` and `helpers/admin-navigation.php` when adding anything that depends on `current_user()`/admin nav.
Missing requires on the static route made `function_exists()` guards silently behave as logged-out, which is how the nav avatar bug happened — same session showed it working on `/blog` but not `/`.

2026-06-19 DECISION Home and About are the only two protected "system pages" (`Page::PROTECTED_SLUGS`) — undeletable, each auto-seeded/self-healing, each rendering a mandatory top section (Home: Hero+CTA, About: heading+body) from existing `site_settings` fields before normal Pages-CMS sections.

2026-06-19 DECISION URL fields (for pages, categories, collections, exhibits, and posts) were changed from type="url" to type="text" to prevent HTML5 validation failures when picker-selected relative paths (e.g. /image/83) are saved. Constrained Design tab logo preview heights to 60px to prevent layout distortion.

2026-06-19 DECISION Upgraded TipTap link popover and schema to support title, alt, and target attributes vertically in rows, using a DOM-node activeLinkEl fallback to retrieve attributes reliably when the cursor is focused on a link.

2026-06-19 DECISION Added min-width: 0 to the parent .admin-container grid element and max-width: 100% to .admin-table-wrap to resolve CSS Grid horizontal table overflow leaks on desktop.

2026-06-19 DECISION Implemented table-to-card layout conversion below 1024px for Pieces, Platform Collections, Exhibits, and Collections list views, shifting the sidebar collapse breakpoint to 1024px to match, and conditionally disabling drag-reordering under 1024px.

2026-06-19 DECISION Added Sort Order input fields (1-based index) to edit forms and implemented a sequential reordering shift helper (reorder.php) in the database on save/update to shift adjacent items contiguous orders automatically.

2026-06-19 DECISION Removed table min-width: 950px on desktop and added a min-width: 280px constraint for the Actions cell, letting columns size fluids and actions wrap cleanly. Overrode table and actions cell min-widths to 0 inside the mobile media query, preventing card cutoff and wrapping buttons inline on mobile/tablet.

2026-06-19 DECISION Integrated editor blur, window scroll, and immediate link-unset listener resets in tiptap-editor.js to ensure the floating link trigger button hides immediately and doesn't remain fixed in mid-air.

