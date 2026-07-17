<!-- Agent reads this file at every session start. Surface any entry marked PENDING CONFIRMATION
to the human before proceeding. Do not act on a pending entry — wait for explicit confirmation
or rejection. -->

<!-- Restructured 2026-07-02 (user-approved): entries are grouped by topic instead of pure
chronology. All dates are original. Superseded intermediate steps of closed investigations
were folded into their final entries — see "Closed Investigations". -->

# Stack & Deployment

2026-07-12 CORRECTION Disabling hand steering must preserve the current pose
and immediately restore pre-steering controls across live and downloaded
immersive surfaces; Reset View alone restores the initial pose.

2026-07-12 DECISION Spatial hand navigation uses one stabilized, single-hand clutched-gestural router that emits look, orbit, travel, and zoom commands through existing surface controllers without replacing other input or rendering systems.

2026-07-12 DECISION Camera/video presentation and visual hand motion are never
stored in or enabled by audio metadata. Regular and immersive camera settings
are independent; immersive hand motion is universal across engines. Piece
downloads offer Full ZIP and a Non-Camera ZIP that retains non-camera sound
while omitting camera UI, camera theremin, visual steering, and MediaPipe.

2026-06-11 STACK Augment Humankind v1 is a no-framework PHP site with durable routes at `/`, `/services`, `/notes`, and `/contact`.

2026-06-20 CONSTRAINT augmenthumankind.com production runs on Hostinger/PHP. The legacy platform/Replit app has since been removed from the repo (folder deleted 2026-07-03); only its exported database survives — read/export-only via `PLATFORM_*`, never written.

2026-06-14 DECISION Platform assimilation uses the current PHP MySQL database as the only writable target; the live platform database is read/export-only via `PLATFORM_*`; canonical feed route is `/blog`.

2026-07-02 DECISION Portable-CMS setup readiness is complete: SETUP.md is the canonical human/agent setup procedure, setup-database.php pre-flights existing data (TTY confirm, `--yes`/non-TTY proceeds after summary), env loading is unified in public/app/helpers/env.php, and empty-DB placeholders are runtime-rendered in index.php, never installer-seeded.
Source: DECISIONS.md 2026-07-02 Portable-CMS Setup Readiness Remediation (commit edf36a5).

2026-07-02 DECISION AGENTS.md was reconfigured (user-approved diff): tool-agnostic Mode table (any agentic tool maps its state onto behavioral modes), Seven Rules consistency fix, plan-mode gallery-suppression note absorbed from CLAUDE.md/GEMINI.md (now thin shims), and coupled-CMS Project Specific Rules added (schema dual-ship, no hardcoded site content, feature-flag registration). DECISIONS.md sessions before 2026-07 are archived in docs/decisions-archive.md with open items carried forward.

2026-07-03 DECISION Updated DESIGN.md to document the CMS codebase's dynamic theme-switching and color customization architecture (10 built-in presets, custom CSS/JS injection, HSL-mapped custom properties in the admin panel).

# Standing Decisions — Auth, Admin & CMS

2026-06-12 DECISION The `/contact` page uses a reCAPTCHA v3-protected PHP form with PHPMailer over Hostinger SMTP; submissions are not stored.

2026-06-12 DECISION `/services` and `/notes` are DB-backed via the Pages CMS (with static fallback); new pages are created in `/admin/pages` and reach the public site via a catch-all `/{slug}` route.

2026-06-12 DECISION Phase 3/4 admin CMS uses flat protected routes: `/admin/artworks`, `/admin/categories`, `/admin/exhibits`, `/admin/media`, `/admin/trash`, and `/admin/navigation`. Public navigation is backed by `navigation_items` with a generic fallback set.

2026-06-16 DECISION Public member profiles live at `/user/{username}` with `/user/settings`. Comments require sign-in; owners can edit or soft-delete their own comments on posts, pieces, exhibits, and exhibit collections.

2026-06-19 DECISION OAuth login for admin and member shares one callback URL per provider (/auth/google/callback, /auth/github/callback), dispatched internally by pending session state rather than URL path. Splitting per-flow callbacks would need 4 registered OAuth apps instead of the 2 actually held.

2026-06-19 DECISION Home and About are the only two protected "system pages" (`Page::PROTECTED_SLUGS`) — undeletable, auto-seeded/self-healing, each rendering a mandatory top section from existing `site_settings` fields before normal Pages-CMS sections.

2026-07-06 CORRECTION About/Bio is a system page but must still respect `pages.show_in_nav` through a real system navigation item, using the current page nav label and slug.

2026-06-19 DECISION Managed pages in `draft` are public-404/private-preview: guests never see them at public URLs; signed-in admins preview with an explicit draft notice.

2026-06-19 DECISION `site_settings.settings_json` is the compatibility fallback for editable settings when a dedicated column is missing, so the admin UI never silently drops saved values.

2026-06-17 DECISION Public canonical/syndication origin resolves from `site_settings.canonical_public_url` first, then `PUBLIC_SITE_URL`, then the request host. Social-card metadata and emitted public links follow that resolver.

2026-06-17 DECISION Admin navigation is registry-driven and owner-orderable from `Identity -> Design`; the same order drives the desktop sidebar, mobile hamburger, dashboard cards, and account-menu destinations.

2026-06-17 DECISION Admin IA separates `Users` (user management only), top-level `AI Settings` (`AI Profiles`, `API Keys`, `AI Vendor`), guided `Platform Connections`, and guided `Feeds`. Raw JSON/schema editing stays internal, never operator-facing.

2026-07-02 DECISION Forms are the reusable special page element abstraction. Installer/setup seeds Contact Form and Newsletter Signup; newsletter signups persist to `newsletter_subscribers` with consent defaulting true and do not require recipient email or send email by default. Ordinary forms email the configured recipient and do not store payloads.

2026-07-06 DECISION The Public Copy admin UI uses a 5-tab sub-interface with grouped archives page sections; footer credit copy and copyright line are consolidated inside Site Identity Settings as textareas.
Source: DECISIONS.md 2026-07-06 Public Copy Subtabs, Footer Consolidation, Widen Text Columns, and CSS Layout Alignment.

2026-07-06 DECISION Footer credit and copyright columns are text/HTML-enabled via safe sanitization; layout wraps in site-footer-text div with row-gap and align-self flex-start controls preventing vertical stretch.
Source: DECISIONS.md 2026-07-06 Public Copy Subtabs, Footer Consolidation, Widen Text Columns, and CSS Layout Alignment.

# Standing Decisions — Blog, Feeds & Syndication

2026-06-14 DECISION Phase 4B: `publishDuePosts()` flips `scheduled` → `published`. Feed routes `/feeds/mf2`, `/blog/category/{slug}/feed.*`, `/{slug}/feed.*` plus legacy 301 redirects; `/export.json` remains JSON Feed 1.1 per Rule 5.

2026-06-17 DECISION Blog post admin is section-based (`post_sections`), with inline category creation, per-platform "Publish to" draft text (Bluesky/LinkedIn), a post calendar at `/admin/posts/calendar`, and automatic scheduled publishing on index visits. `posts.content` is always `''`; content lives in `post_sections`.

2026-07-03 DECISION Site-wide search now uses MySQL FULLTEXT relevance ranking with short-token LIKE recall and highlighted snippets across posts, pieces, collections, exhibits, and pages.

2026-07-03 DECISION Portable launch readiness now gates FULLTEXT search index presence and MATCH smoke queries; platform deletion readiness accepts an untracked --env-file for legacy source DB credentials.

2026-06-17 NOTE `SyndicationPayload::fromPost()` reads `$post['content']` which is always `''` in the section system — callers must populate `$payload->contentHtml` from `PostSection::allForPost()`, and relative image paths (`/image/123`) must be prefixed with `seo_origin()` before passing to external APIs.

2026-06-17 DECISION Syndication failures are surfaced: `handleSyndication()` returns a failures array; `/admin/posts` shows a `?syndication_error=` banner; the Syndications tab shows `error_message` in red.

2026-06-18 DECISION Platform publishing OAuth app credentials are DB-only; malformed migrated provider rows are normalized to empty placeholders rather than preserved as undecryptable ciphertext.

2026-06-18 CORRECTION Exact session-row parity between the legacy platform DB and the live PHP DB is not a stable deletion-readiness invariant after cutover; session drift is operational state, not migration failure.

# Standing Decisions — Art Pieces, AI Generation & Immersive

2026-07-13 · DIRECTION · Preferred temporary explicit-focus overrides that restore the prior ambient state on exit, preserving exploratory behavior while making the foreground artwork authoritative.

2026-07-13 DECISION Immersive collection sound follows wall proximity during room navigation, temporarily follows the displayed slideshow work—including Three.js and A-Frame—and restores the prior proximity owner on close. The visitor’s mute intent persists through silent works.

2026-07-13 CORRECTION Collection slideshow overlap refers to title/control geometry; preserve existing slideshow functionality and place the title vertically above its controls.

2026-07-13 DECISION Collection slides suspend progressive wall runtimes and restore them after closing; collection camera, active-work sound, and room-hand navigation remain independent capabilities.

2026-07-11 DECISION Live and downloaded piece views derive sensible controls and capture behavior from one engine-aware capability matrix; screenshots must include all active visible composition layers.
2026-07-11 CORRECTION Fixed a corrupted multi-line comment close tag in public/app/helpers/piece-render.php (which had been accidentally modified to "nfunction" in a prior session, commenting out 1,800 lines of helper code and throwing "Call to undefined function" errors on live views).
2026-07-11 DECISION Split the Audio tab in the piece edit panel (/admin/pieces/[id]/edit) into two separate fieldsets: "Public sound controls" and "Public camera controls", making camera steering (hand control) independent of the hand-tracking audio voice.
2026-07-11 DECISION Renamed the Audio tab to "Interact" in form.php (still always accessible when the schema supports sound), and wrapped all audio-specific UI controls and settings (sound controls, default volume, ambient sample, synth settings, and effects) inside checks for `$currentSonicEnabled` to hide them when a piece has no associated audio.

2026-07-11 CORRECTION resolveSonicParamsFromPost() must NEVER fabricate a sonic_params block for a piece without one (a brief regression constructed ['enabled' => false] payloads, which made silent pieces render the sound panel and play idle notes; the 2026-07-11 null-fabricated-sonic-params migration cleans the affected rows). Only generation/AI Refine create sonic_params; admin saves only mutate existing blocks, guarded by sound_playback_present/sonic_extras_present form markers so unrendered fields never read as "off".

2026-07-11 DECISION Hand control (camera steering + device-tilt fallback) is granted by piece_sound_capability_contract() as: steerable engine (three/aframe/c2_interactive) AND (hand-tracking voice OR camera_view permission) AND voices.hand_control (default true everywhere). Sound-less pieces get it via a silent engine (CreatrSonicController.create({}, {allowHandControl: true}) — ensureEnabled() refuses audio on them); exports bundle MediaPipe whenever hand_tracking OR hand_control is offered, and collection exports force both off to bound ZIP size.
2026-07-11 DECISION Hand-tracking theremin (and hand controls) and live microphone features in sonic-controller.js are stabilized for cross-browser runtime safety: video width/height guards protect against warmup zero-dimension frame exceptions; MediaPipe detectForVideo calls use strictly monotonic timestamps to avoid WebGL/WASM and MediaPipe crashes; and Tone.connect connects the native mic stream node recursively to Tone.js nodes to resolve browser TypeErrors.

2026-07-11 DECISION Registered camera video overlay layers and composed capture routines inside P5.js and SVG (default) offline bootstrap scripts to provide webcam overlay features and correct PNG capture composition offline under the `file://` protocol.

2026-07-11 DECISION P5.js, plain C2.js, and SVG camera overlays default On but remain visitor-activated and author-disableable; camera approval and opacity share the regular-view Piece controls menu.

2026-07-12 DECISION The regular piece stage is authoritative for `/pieces/{id}`, the regular embed, and TipTap/blog piece embeds; wrappers may handle lazy sizing/fullscreen promotion but must not duplicate stage controls. Embed controls are surface-local: regular pages say Embed, immersive pages say Embed (Custom)/(CMS), Screenshot/ZIP/literal-VR share the stage rail, and the full immersive link below Prompt is standalone-page-only.

2026-07-12 DECISION Regular piece embeds fill their viewport, and platform collection pages/embeds reuse the immersive collection renderer rather than maintaining a second gallery. Collection entry chrome may adapt, but slideshow, navigation, selection, downloads, sound, fullscreen, and renderer lifecycle behavior must remain intact.

2026-07-12 DECISION Downloads and embeds preserve all technically relevant functionality of their source surface; deliberate exclusions such as bare `static=1` embeds and collection per-tile inference must be recorded in `docs/piece-surface-parity.md`.

2026-07-12 DECISION Every engine offers a visitor-activated camera option by default without auto-starting capture; per-version placement is background or overlay, with Three.js/A-Frame defaulting to background and p5/C2/SVG to overlay.

2026-07-11 DECISION Ported the `isIPhoneWebKitBrowser()` check, CSS pseudo-fullscreen layout fallbacks, and the Escape key exit handlers directly to exported/downloaded piece documents so fullscreen transitions operate cleanly on mobile Safari without browser window failures.

2026-07-10 DECISION iOS hand features use retryable MediaPipe initialization, direct-video then throttled-canvas inference, and explicitly labeled device tilt as the final steering fallback; live mic uses one granted stream through createMediaStreamSource() and never raw-monitors on failure.

2026-07-10 DECISION Hand steering and camera backgrounds have Three.js/A-Frame parity across regular views, mounted immersive views, and standalone exports; mounted steering exclusively owns the camera and restores prior controls at the resulting pose.
Source: DECISIONS.md 2026-07-10 Completed Hand/Camera Parity for Immersive Mounts and A-Frame Exports.

2026-07-10 DECISION Immersive toolbar screenshot buttons are icon-only and lack a nested `span` element. The click event handlers in piece-render.php, collection.php, and piece.php are refactored to conditionally update text content only when a nested `span` is present. This avoids replacing the button's entire inner HTML and prevents the SVG camera icon from disappearing after a capture.

2026-07-10 DECISION Native Tone.js BitCrusher (which uses an AudioWorkletNode compiled from a local Blob URL) is replaced with a custom WaveShaper-based BitCrusher (amplitude quantization via a step-like curve) in sonic-controller.js. This avoids browser sandboxing blocks against loading Workers/AudioWorklets from Blob URLs under the file:// protocol, allowing the bitcrusher effect to work seamlessly on downloaded pages.

2026-07-10 DECISION Offline single-piece exports pre-load Tone.js and sonic-controller.js via static script tags in the generated markup, rather than dynamically on first interaction, to ensure that the browser user gesture context is preserved when accessing hardware (microphone and camera stream). Toggled buttons (Play notes, Camera theremin, Live mic) are styled with `.offline-sound-btn` and receive active highlights for user-friendly feedback.

2026-07-10 DECISION Offline single-piece exports support a local-first MediaPipe (hand-tracking) and Tone.js loading strategy, falling back dynamically to public CORS-enabled CDNs (jsDelivr/CDNjs) if local files fail due to file:// CORS restrictions. If access is blocked or fails (such as Safari's secure context restrictions on file://), a floating troubleshooting banner guides the user to serve files locally via python/npx.

2026-07-10 DECISION Syntax error in offline bundle styles is fixed by swapping JavaScript concatenated single-quoted strings to multi-line template literals (using backticks) to correctly support literal newline bytes generated by PHP heredocs.

2026-07-10 DECISION Sound controls in web/runtime views are refactored to align with the offline ZIP layout, replacing separate absolute positioning with a single flex container row. The settings chevron icon is enlarged to 16x16 for mobile responsiveness, and the settings panel flows naturally below.

2026-07-10 DECISION Keyboard notes play state in the web runtime popover toggles the `.is-pressed` state class on visual keyboard buttons when physical keys are pressed, matching the host page's visual feedback.

2026-07-09 CORRECTION The WASD-decoupling safeguard is now complete for A-Frame in immersive VR views. The 2026-07-10 entry below claimed coverage for immersive galleries, but A-Frame pieces mounted directly in `/immersive/pieces/{id}` (and collection exports) still used A-Frame's default `wasd-controls`. `public/assets/js/immersive-gallery.js` now disables `wasd-controls` prototype handlers for W/A/S/D after the A-Frame runtime loads, and `public/assets/js/piece-runtime.js` applies the same shim inside `bootAFrame()`'s `script.onload` so it runs after A-Frame is available. Regular `/pieces/{id}` Three.js/A-Frame and downloaded ZIP files were already arrow-key-only; the immersive VR surface is now consistent with them.

2026-07-10 DECISION WASD keys are decoupled from viewer camera movement controls across all piece types (Three.js and A-Frame), including runtime modules, immersive galleries, and downloaded ZIP files, to prevent movement conflicts when physical keyboard notes are played. Camera movement is mapped exclusively to the arrow keys. A-Frame's built-in `wasd-controls` component is overridden via dynamic prototype interception at boot time.


2026-07-09 DECISION GLB/GLTF uploads loaded from `/media/{id}` are authored visual assets: generated Three.js code must preserve embedded materials, textures, UVs, transparency, vertex colors, and material color data by default.
Generated code may transform, light, animate, and set shadows on loaded models, but material replacement requires an explicit future restyling workflow.

2026-07-09 DECISION Direct-open Three.js exports bundle local classic globals for Three.js, OrbitControls, and GLTFLoader; immersive/collection exports also bundle GLTFLoader module/global files so GLB/GLTF pieces do not rely on Blob module URLs or CDN loader imports offline.

2026-07-09 CONSTRAINT Refine and regenerate partition scope by "purpose domain": when ONE refinement direction is filled, ONLY that direction is in scope — the other domain's content is context that must not change; BOTH directions may change only when both are filled.
The original creative prompt is always labeled ### CONTEXT (never the goal — the directive is the ### PURPOSE OF THIS REFINEMENT header); out-of-scope asset refs (`image.png`, `/image/N`) are elided to `[(visual asset reference elided; out of scope for this audio-only refine)]` in audio-only mode only.

2026-07-09 CONSTRAINT Regenerate inherits `purpose_domain` PURELY from lineage (`sound_enabled_lineage` + `sound_feel_lineage` captured at generation time, passed through as read-only hidden inputs) — never recomputed from new inputs at regenerate time; regeneration amplifies existing scope, never changes it.
Generation lineage yields `'audio_visual'` or `'visual'` (never `'audio'` — pieces MUST have a visual prompt); sound-only lineage is reserved for a future refine-lineage regenerate flow the same arch already accommodates.

2026-07-09 CORRECTION Sound is gated by the schema-column probe `art_piece_sonic_params_supported()` (true when `art_piece_versions.sonic_params` exists) — there is NO `ai_pieces_sound` feature flag in PHP, despite two prior DECISIONS.md mentions claiming so (both corrected 2026-07-09).
The agentic opencode-zen provider proxy auto-resolves file refs it finds in outbound prompt text, crashing text-only models with `"Cannot read 'image.png' (this model does not support image input)"`; quarantine out-of-scope asset refs when sending context that may contain them.

2026-07-09 DECISION Scoping logic in refineAi() ensures visual-only prompt refinement preserves existing sonic_params, and sound-only prompt refinement keeps visuals unchanged and bypasses visual preflight validation.

2026-07-09 DECISION Sound-only AI Refine saves bypass validation of refinement prompt when empty, falling back to "Update sound design: [feel]". A manual "Enable sound playback" metadata checkbox is rendered only if sound is associated with the piece, and stored inside `sonic_params.enabled`. Public/immersive pages and export bundlers respect this flag to disable audio playback. The client-side payload explicitly supplies `purpose_domain` to ensure scoping guarantees hold through all retry iterations (including "Request stronger changes" where visual prompts are empty but feedback is present). The "Setup Checklist" header link is moved to the sidebar navigation panel to prevent accidental clicks.

2026-07-09 DECISION sonic-controller.js is the shared 3-voice Tone.js engine (ambient/movement/melodic) for all sound surfaces; per-piece extras (voice visibility, volume, synth tuning) live nested inside sonic_params JSON, deliberately excluded from AI-authored canonicalization so Audio-tab edits never fork a new version.

2026-07-09 DECISION Hand-tracking (self-hosted MediaPipe, ~19.4MB) works in live pages and solo single-piece ZIP exports (admin-gated per piece, downloader-narrowable via a dl_voices picker) but is always excluded from collection/exhibit-wall ZIP exports regardless of a piece's own config, to keep collection download size bounded.

2026-07-09 DECISION Investigating downloaded-piece `file://` security warnings should use defensive runtime/preflight guards rather than connecting to the remote production database for ad-hoc inspection. The WebKit "Unsafe attempt to load URL ...index.html from frame with URL ...index.html" warning means a subresource resolved to the document itself; the fix is to reject empty `src`/`href`/`url()` at validation time and empty `loadImage()` arguments at runtime.

2026-07-09 CONSTRAINT Generated art-piece HTML/CSS/JS must not contain empty resource URLs. Empty `src`/`href`/`xlink:href` attributes, empty CSS `url()`, and empty literal arguments to `p5.loadImage()`, Three.js `.load()` / `TextureLoader.load()`, and C2 `runtime.loadImage()` now fail preflight validation with a repairable error message.

2026-07-09 DECISION Vendored runtime files copied into ZIP exports have their `sourceMappingURL` comments stripped (via `piece_export_strip_source_maps()` in `piece-render.php`) so browsers opening `file://` exports do not attempt to load missing `.map` files and produce unrelated console warnings.


2026-06-14 NOTE The platform's "VR mode" is the Three.js immersive presentation at `/immersive/pieces/{id}` — not WebXR/A-Frame (rolled back). Linked from `/pieces/{id}` and `/admin/pieces`.

2026-06-15 DECISION Art piece engine whitelist: `p5`, `c2`, `three`, `svg` (plus A-Frame in immersive contexts). `/admin/pieces/{id}/edit` and `/create` use Metadata/HTML/CSS/JS tabs editing `current_version` in place; split-pane editor on desktop, full-canvas on tablet/mobile.

2026-06-15 DECISION Three.js runtime is consistent across all four rendering surfaces: `piece-render.php`, `admin/pieces/form.php`, `generate-preview.php`, and `public/embed.js` — each uses `instrumentedThree`, `autoFit`, `ensureFallbackLighting`, `OrbitControls`, `startFrame(count)`, `WebGLRenderer({ canvas })`. Any future Three.js fix must be mirrored in all four files.

2026-06-15 DECISION Three.js camera auto-fit ignores skyboxes (BackSide), particles (Points), and oversized objects/planes; preserves custom camera positions; defaults to the sketch's configured position rather than zooming to infinity.

2026-06-20 DECISION Every code-changing save creates a new `art_piece_versions` row (metadata-only saves don't). Versions carry nullable `ai_profile_id`/`ai_persona_id`, editable per-version, shown on public/immersive piece pages in one combined Engine/Profile/Persona/Prompt block.

2026-06-20 DECISION AI Refine uses a plan-then-patch protocol: the model returns `PLAN:` plus `PATCH <html|css|js>:` blocks as exact SEARCH/REPLACE edits against current code — never a rewritten file; unmatched or ambiguous patches fail and retry. `javascript` is accepted as a synonym for `js` (models write it constantly); retries always re-send the current HTML/CSS/JS; matching is exact-first with whitespace-tolerant fallback. Initial generation stays full-file.

2026-06-21 DECISION AI Refine is client-driven: each call makes exactly one AI attempt; a styled dialog offers retry (up to 5) or give-up, so tokens are only spent deliberately. Every attempt persists as a draft version (`is_draft_attempt = 1`, grouped by `attempt_sequence_token`) — never current, but viewable/forkable via "Fork as New Piece" (available on every version). Accept promotes the draft and deletes failed siblings. `PlatformArtPiece::attachCurrentVersion()` filters drafts — audit any new `art_piece_versions` reader for draft leakage.

2026-06-20 CORRECTION `refineSave()` (POST /admin/pieces/[id]/refine-save, called on Accept) creates the version with `prompt` set to the actual refinement instruction; manual saves fall back to the Metadata prompt. Accept saves immediately — no separate Save step.

2026-06-21 DECISION Non-SVG engines (p5, c2, three) have HTML containers locked to standard boilerplate backend and frontend, hiding the HTML tab: `<div id="canvas-container">` (p5), `<canvas id="piece-canvas">` (c2), `<div id="container">` (three). HTML is excluded from refinement for non-SVG pieces; SVG refinements are checked so the `<svg>` element and canvas visibility survive.

2026-06-30 DECISION Immersive platform collections are gateway surfaces: pieces hand off to canonical `/immersive/pieces/{id}` with `returnTo`; images may use the in-collection slideshow overlay. The shared side-edge HUD pattern covers all gallery-mounted engines.

2026-07-01 DECISION The collection slideshow overlay renders ALL engine types as live srcdoc iframes; Three.js pieces animate on the 3D exhibit wall via a srcdoc-iframe + proxy-canvas path (`resolveSketchFactory`'s `new Function()` cannot parse ES-module `import` syntax — the iframe path bypasses it).

2026-07-01 CORRECTION `window.PIECE_PRESERVE_DRAWING_BUFFER = true` must be set in srcdoc HTML before `piece-runtime.js` loads so `preserveDrawingBuffer:true` reaches `THREE.WebGLRenderer` — otherwise `drawImage` reads blank pixels.

2026-07-01 CONSTRAINT A-Frame wall animation remains a thumbnail placeholder: A-Frame bundles its own Three.js, so `PIECE_PRESERVE_DRAWING_BUFFER` never reaches its renderer. Defer full fix to a future session.

2026-06-30 CORRECTION Embedding a full HTML srcdoc as JSON inside a PHP `<script>` block requires `JSON_HEX_TAG` — `JSON_UNESCAPED_UNICODE` alone does not escape `</script>` (fixed in `immersive/piece.php`).

2026-07-02 DECISION Art piece starter templates are DB-owned install data edited under `/admin/pieces?tab=templates` (`/admin/pieces/templates` is a permanent redirect). Defaults demonstrate optional CMS media, sized where rendering happens.

2026-07-04 DECISION Public piece downloads (`/pieces/{id}/download`) are ZIP bundles with `index.html` as the single manual entry point. The bundle remains editable/rehostable (`styles/piece.css`, `scripts/piece.js`, `runtime/`, `media/`), but the direct-open `index.html` path embeds vendored runtime/media-safe equivalents so supported interactive pieces can still render and take screenshots when opened locally. Stored code remains CMS-runtime-compatible via `window.sketch`; standalone Three.js exports still attach OrbitControls in the bootstrap.

2026-07-05 DECISION Preserved the OrbitControls export statement in `piece_export_three_orbitcontrols_inline_source()` so that offline Three.js exports (both standalone piece downloads and slideshow mode iframe srcdocs in collection downloads) can successfully dynamically import OrbitControls as an ES module without throwing constructor errors.

2026-07-05 DECISION Enabled keyboard navigation (WASD/arrows) for Three.js OrbitControls in focused live regular views and downloaded index files by calling `controls.listenToKeyEvents(window)`. Modified collection ZIP downloads to package all individual collection pieces in their entirety under `pieces/{slug}/` (with their own standalone `index.html`, runtimes, assets, and styles) so they can be run and edited fully offline.

2026-07-03 DECISION AI piece generation may only introduce CMS media when the prompt explicitly names the exact image/media ID or path; downloaded HTML exports rewrite root-relative and relative CMS media refs to absolute URLs on the host serving the download.

2026-07-04 DECISION AI piece prompting treats `image/photo/picture ID` and `media asset ID` as parallel but distinct media-authoring language: image/photo/picture wording authorizes `/image/{id}`, media-asset wording authorizes `/api/media-assets/{id}`, and prompts must name both explicitly to authorize both families. No hidden ID aliasing layer exists between those route families.

2026-07-03 DECISION Persisted `generation_mode` is the primary art-piece runtime contract whenever the column exists; shared art-piece/version SQL must stay schema-compatible during rollout and fall back to engine-based behavior when `generation_mode` is absent.

2026-07-03 DECISION Legacy interactive C2 versions are systematically upgraded by setup-database: every saved `art_piece_versions` row with `engine='c2'`, empty/plain `generation_mode`, and code matching the existing `art_piece_c2_interactive_pattern()` is backfilled to `generation_mode='c2_interactive'`. Ambiguous non-matches stay on compatibility fallback.

2026-07-03 CORRECTION The “This site isn’t configured yet / database connection failed” screen in `public/index.php` must be reserved for real connection-class PDO failures only; piece-route schema/query regressions can otherwise masquerade as whole-site DB outages and mislead debugging.

2026-07-03 DECISION C2 runtime pointer contract: the c2 canvas element box must stay aspect-locked to its bitmap (`fitCanvasBox()` in piece-runtime.js, guarded by tests/three-runtime-consistency.php) — reintroducing `object-fit`/stretch styling silently breaks `c2_interactive` hit-testing, because generated sketches map pointer coordinates with `(clientX - rect.left) * (canvas.width / rect.width)` per the generation prompt, which assumes the element rect IS the visible bitmap. Perceived interactivity differences between models (e.g. Minimax M3 piece 95 vs Mistral Vibe piece 103) are interaction-style choices — global pointer tracking with a cursor affordance vs precision hit-test dragging — not generation failures; the `c2_interactive` preflight already guarantees interaction hooks exist.
Source: DECISIONS.md 2026-07-03 C2.js Interactive pointer-coordinate fix.

2026-06-15 NOTE The `<dialog>`-based `.media-picker-*` pattern is the standard for all TipTap insertion UIs (media, pieces/exhibits, iframe, AI profile) — never `window.prompt()`.

2026-06-19 DECISION Native media uploads/imports are draft-first: `media_files.status` governs readiness; pickers only insert `ready` assets; `alt_text` must persist before an asset is reusable; video posters are linked image assets via `poster_media_file_id`.

2026-06-19 DECISION URL fields use type="text", not type="url", so picker-selected relative paths (`/image/83`) pass validation.

2026-06-17 DECISION Archive pages share the content-filter-bar pattern (search + sort chips, sr-only labels) with +1-trick infinite scroll (fetch PAGE_SIZE+1, no COUNT query) and filter-aware batch URLs.

2026-06-19 DECISION Below 1024px, admin list tables convert to cards, the sidebar collapses, and drag-reordering disables; Sort Order inputs with a sequential reorder helper (reorder.php) cover ordering instead.

2026-06-19 DECISION The shared Three.js runtime reconciles OrbitControls after keyboard/click translation so drag/pan preserves zoom; only wheel/pinch changes zoom. (Regressed once when an unrelated refactor deleted `onThreeWheel` — the incident that motivated Rule 7.)

2026-07-05 DECISION Immersive collections slideshow allows traversing all art pieces in a single unified traversal experience. While the slideshow overlay is open, the main exhibit wall's rendering loop is suspended and active slot WebGL runtimes are destroyed to free up GPU resources and prevent WebGL context conflict failures (especially on Safari). They are re-hydrated when the overlay is closed. The slideshow opens at the currently focused/closest piece using `getActiveIndex()` on the viewer.

2026-07-05 DECISION Immersive collections slideshow traversal is hardened against touch/pointer ghost-click closures on mobile Safari/touchscreens by delaying the overlay opening (50ms) and increasing the backdrop click guard to 500ms. getActiveIndex() supports both piece and image kinds.

2026-07-05 DECISION The immersive stage toolbar is shared chrome: markup/CSS live in `public/app/helpers/immersive-chrome.php` (`immersive_stage_toolbar_markup()`/`_css()`), wiring in `setupImmersiveStageChrome()` (immersive-gallery.js). Any new immersive surface or export must use it rather than hand-rolling controls, and controls must stay TOP-anchored so they never overlap the bottom-center iOS "Enable Motion Controls" permission button; the gyro ⟲ toggle mounts into the toolbar's `data-immersive-gyro-slot`. View-button gating: collections=slideshow, p5/svg/non-interactive-c2=single-item overlay (slideshow shell, no Prev/Next), interactive c2=same overlay with interactive iframe, three/aframe=no view button. Export download menus are PNG-only.
Source: DECISIONS.md 2026-07-05 Shared Top Stage Toolbar session.

# Standing Decisions — Documentation

2026-07-10 DECISION ALGORITHMS.md is the maintained algorithm catalog: new algorithmic code must get a matching section/recipe entry, and function moves/removals must be reflected there.
The OBJ removal and the sonic-controller extraction had left it stale; source: DECISIONS.md 2026-07-10 audit session.

2026-07-10 DECISION Sonification's audio engine lives in sonic-controller.js as four independent continuous voices; the old stillness-gated idle pattern is intentionally gone and a test locks that in.

2026-07-10 CONSTRAINT ALGORITHMS.md diagrams are AI-generated PNGs from the user's diagram thread; agents leave placeholder paths + pending comments, never generate them.

# Regression Watchlist

2026-06-14 NOTE Admin-view bug pattern: `$content = function () ... ;` produces a Closure that `<?= $content ?>` can't stringify (fatal). Correct pattern: `ob_start(); ... $content = ob_get_clean();`.

2026-06-17 NOTE CSS specificity trap: `.form-row { display: grid; }` beats UA `[hidden]`. Always pair with `.form-row[hidden] { display: none; }`, and use `element.style.display` in JS.

2026-06-19 CORRECTION Any save handler shared by multiple tabs/forms on one DB row must only update fields present in that request's `$_POST` — never rebuild the full row with defaults. Bit twice in Site Identity; fixed at the `updateSettings()` chokepoint.

2026-06-19 CORRECTION `public/index.php`'s static/managed-page route and `app/router.php` load different helper sets — check both load `helpers/auth.php` and `helpers/admin-navigation.php` when adding anything depending on `current_user()`/admin nav. Missing requires make `function_exists()` guards silently behave as logged-out.

2026-06-19 CORRECTION Site-wide sign-in (one login/logout covers both admin and member sessions) is a standing constraint that has silently regressed before. Watch for it any time auth.php, AuthController, or UserAuthController are touched in isolation.

2026-06-21 DECISION `bootThree()` must create the canvas before its CDN imports, not after — otherwise capture can't tell a slow CDN apart from a piece with no canvas.

2026-06-21 DECISION `performCapture()` must run on every path that saves new piece code (Save Changes AND AI Refine Accept).

2026-06-21 CORRECTION "No canvas or svg element found" from admin-piece-capture.js is a fallback timeout message, not a real claim about the piece. Check piece-runtime.js's CDN import path and any runtimeError before trusting it.

2026-06-20 NOTE When an AI failure can't be explained from the audit log (`audit_log_redact_value()` truncates metadata to 500 chars), reproduce it with a standalone CLI script calling the same generate/extract/apply functions against the real piece/profile/instruction — that method found the `javascript`-vs-`js` bug after log-snippet guessing failed three times.

2026-06-12 NOTE `scripts/verify-contact-config.php` probes `/contact` (not `/notes`) because DB-backed managed pages `exit` early — relevant if routing changes again.

2026-07-09 DECISION A per-item controller must never own a DOM listener when its own lifecycle is shorter than the DOM element's — `mountExhibitWall`'s per-focus-change `createAudioController()` instances each attached/detached the shared sound button's click listener, so any focused item with no sonicParams left the button silently unwired. Fixed by having the longer-lived owner (the wall) hold one persistent listener and drive whichever controller is currently bound via exposed methods.

2026-07-09 CORRECTION Other AI tools (Opencode Go, Antigravity) have been editing this same codebase concurrently with Claude Code sessions — always re-check current file state via fresh reads/Explore before trusting session memory, and scan DECISIONS.md for entries not yet seen this session before assuming a reported bug is unaddressed.

2026-07-09 CORRECTION Don't trust static code-reading alone to rule out a runtime dependency (e.g. "no instrument uses AudioWorklet") — a real browser console stack trace decoded against the minified vendor bundle found PluckSynth's hidden FeedbackCombFilter/AudioWorklet dependency that two prior static-analysis passes missed entirely.

2026-07-09 DECISION Client-side upload gating for the Media Library has two independent layers that must both be updated together — the PHP-rendered accept/hint in layout.php, AND tiptap-editor.js's `pickerModeConfig()`, which unconditionally overwrites the DOM's accept/hint/type-check on every picker open. Check both whenever a new upload type is added.

2026-07-17 CORRECTION When media-in-pieces (or any DB-backed) features fail silently, run `php scripts/setup-database.php --dry-run` before blaming the generating tool — the deployment had never applied the 2026-07-13 media-refs migration, so `ArtPieceVersionMediaRef`'s table-missing no-op dropped every picked media ref while looking like an Opencode Go generation bug.
Source: DECISIONS.md 2026-07-17 Mobile Media Picker + A-Frame Media Reliability.

# Closed Investigations — do not re-litigate

2026-06-21 DECISION Three.js mesh/object-count validation is permanently removed: object count never predicted renderability at any threshold tried (150, then 1000 — a 728-object piece failed while a 1400+ one rendered fine); what matters is instancing, not count. `art_piece_count_three_object_calls()` survives only as a silent audit-log diagnostic. Do not propose a third threshold number. InstancedMesh guidance (including non-uniform per-instance scale and per-frame `setMatrixAt` updates) stays in the shared Three.js system prompt as voluntary advice.

2026-06-21 DECISION The "retried too soon" theory for AI Refine timeouts is disproven (user tested 60+s spacing, identical 120s timeout; the 30s "Try Again" cooldown remains as a debounce only). Real factors, all addressed: Guzzle timeout raised to 180s for refine only; Hostinger has an external proxy timeout shorter than PHP's own limit, so refine stops starting new attempts past ~240s elapsed and clients parse response text defensively (`JSON.parse` of non-JSON was surfacing as WebKit's cryptic "string did not match the expected pattern").

2026-06-20 CORRECTION "ERR_CONNECTION_CLOSED"/"Load failed" on production saves and refines is a stale keep-alive connection reused after a review gap, not resource exhaustion or payload size. All piece save/refine paths use fetch() with one automatic network-level retry, returning JSON (`X-Requested-With` branch in `update()`), with a live elapsed-time tick during waits.

2026-06-21 CORRECTION The thumbnail/comparison capture pipeline's final form: `signalCanvasReady()` fires only after a real drawn frame (P5 polls frameCount, C2 wraps startFrame, Three.js fires from both render paths); the capture iframe extracts from the live preview iframe first, falling back to a background iframe wrapped in a 1px overflow-hidden parent overlaid on top (WebKit occlusion culling defeats `opacity:0` and `z-index:-1` iframes — it skips WebGL backing-store allocation and throttles rAF); `requestAnimationFrame` is shimmed with setTimeout inside capture iframes; captures run sequentially, never `Promise.all`; a `webglcontextlost` listener surfaces context loss as a real error. Ghost clicks within 500ms of dialog close are discarded; disabled buttons get `pointer-events: none !important`.

2026-06-20 CORRECTION `art_piece_preflight_code()`'s `window.sketch` check requires a real `=` assignment (excluding `==`/`===`) — substring presence alone let non-assigning references pass and fail silently at capture time.

2026-07-17 DECISION Piece-error overlays showing only bare `@…piece-runtime.js:NNNN` frames were sketch-thrown errors surfaced at the `window.sketch()` call site with the message hidden by Safari/Firefox stack formatting — `formatPieceError()` now always prints `Name: message` plus `[sketch]`/`[runtime]`-labeled frames, and thumbnail-capture failure is the same error re-surfaced, never a second bug. Do not re-investigate piece-runtime.js line ~2111.
Source: DECISIONS.md 2026-07-17 Mobile Media Picker + A-Frame Media Reliability.
