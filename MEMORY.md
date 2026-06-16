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
