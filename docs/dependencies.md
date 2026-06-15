# Dependencies

## Google reCAPTCHA v3

- **Purpose:** Protect the `/contact` form from automated spam.
- **Data sent off-domain:** Browser interaction metadata and the generated
  reCAPTCHA token are sent to Google. The backend sends the token and shared
  secret to Google for verification.
- **External endpoint:** `https://www.google.com/recaptcha/api/siteverify`
- **What breaks if unavailable or changed:** Contact form spam protection can
  fail, and submissions may be rejected until configuration or code is updated.
- **Self-hosting alternative:** Honeypot fields, rate limiting, and email
  verification. This avoids Google but provides weaker bot detection.
- **Required config:** `RECAPTCHA_SITE_KEY`, `RECAPTCHA_SECRET_KEY`,
  `RECAPTCHA_MIN_SCORE`

## PHPMailer

- **Purpose:** Send contact form submissions through authenticated SMTP.
- **Package:** `phpmailer/phpmailer`
- **Installed version:** `v7.1.1`
- **Data sent off-domain:** None by PHPMailer itself; it transports submitted
  contact form data through the configured SMTP provider.
- **What breaks if unavailable or changed:** Contact form email delivery can
  fail until the package or sending code is updated.
- **Self-hosting alternative:** Hand-written SMTP over PHP streams. This is
  riskier to maintain because TLS, authentication, headers, encoding, and
  injection protections are easy to get wrong.

## Hostinger SMTP

- **Purpose:** Deliver contact form submissions to the configured destination
  email address.
- **Data sent off-domain:** Contact form fields are sent through Hostinger's
  SMTP service.
- **What breaks if unavailable or changed:** Successful form submissions may
  stop sending email if credentials, limits, pricing, or service availability
  change.
- **Self-hosting alternative:** Run and maintain a private mail server. This is
  operationally heavier and may have worse deliverability.
- **Required config:** `SMTP_HOST`, `SMTP_PORT`, `SMTP_ENCRYPTION`,
  `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_FROM_EMAIL`, `SMTP_FROM_NAME`,
  `CONTACT_TO_EMAIL`
- **Expected Hostinger SMTP config:** `SMTP_HOST=smtp.hostinger.com`.
  `SMTP_USERNAME` and `SMTP_FROM_EMAIL` should be the same Hostinger mailbox
  address, such as `contact@augmenthumankind.com`. Use `SMTP_PORT=465` with
  `SMTP_ENCRYPTION=smtps` or `SMTP_PORT=587` with `SMTP_ENCRYPTION=starttls`.
- **Not used by the contact form:** IMAP settings such as
  `imap.hostinger.com` are only for reading mail in an email client. The
  contact form only sends outbound messages through SMTP.

## MySQL Database (Portfolio + Admin CMS)

- **Purpose:** Stores the Portfolio gallery (artworks, categories, exhibits,
  media library) and the admin-managed Pages/Navigation CMS.
- **Data sent off-domain:** None — the database is queried directly via PDO.
- **External dependency:** A MySQL-compatible database instance. Development
  is configured to point at the same instance/provider used in production
  (via `DB_HOST`/`DB_NAME`/`DB_USER`/`DB_PASS`) so schema and data stay aligned.
- **What breaks if unavailable or changed:** `/admin/*` and `/portfolio/*`
  become unavailable. `/services` and `/notes` fall back to their built-in
  static content (see `public/index.php`), and `/`, `/contact` are unaffected.
- **Self-hosting alternative:** N/A — this is already self-hosted (Hostinger
  MySQL or equivalent). No third-party SaaS is introduced.
- **Required config:** `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
- **Schema:** `schema.sql` at the repository root is the source of truth.
- **Media storage:** Uploaded/imported admin media is stored in `media_files`
  blobs and served publicly through `/media/[id]` and `/image/[id]`.
- **Upload limits:** `.htaccess` requests `upload_max_filesize=64M`,
  `post_max_size=72M`, `max_execution_time=120`, and `max_input_time=120`.
  If Hostinger rejects `php_value` directives, configure those values in the
  Hostinger PHP settings panel instead.

## Platform Source MySQL Database (Read-Only Migration Source)

- **Purpose:** Source database for one-way assimilation of the existing
  platform data into the current PHP site's MySQL database.
- **Data sent off-domain:** The migration script connects to the configured
  platform database and reads rows for export/import into the PHP target
  database.
- **Write policy:** The platform database is live. Migration tooling must not
  issue DDL or DML against it. Only `SELECT` reads are permitted.
- **What breaks if unavailable or changed:** New platform data cannot be
  migrated until the source connection is restored. The already-migrated PHP
  site remains functional.
- **Self-hosting alternative:** A SQL dump exported manually from the platform
  database, imported through the same target-only migration path.
- **Required source config:** `PLATFORM_DB_HOST`, `PLATFORM_DB_NAME`,
  `PLATFORM_DB_USER`, `PLATFORM_DB_PASS`
- **Optional source config:** `PLATFORM_DB_PORT`, `PLATFORM_DB_SSL`
- **Target config:** The existing PHP database remains configured with
  `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS`.

## Platform Assimilation Runtime Configuration

- **Purpose:** Carries platform-derived runtime settings into the PHP app
  without colliding with current PHP-site settings.
- **Required where used:** `PLATFORM_AUTH_SECRET`,
  `PLATFORM_AI_SETTINGS_ENCRYPTION_KEY`, `PLATFORM_ALLOWED_ORIGINS`,
  `PLATFORM_PUBLIC_SITE_URL`, `PLATFORM_CRON_SECRET`,
  `PLATFORM_GITHUB_ID`, `PLATFORM_GITHUB_SECRET`,
  `PLATFORM_GOOGLE_CLIENT_ID`, and `PLATFORM_GOOGLE_CLIENT_SECRET`.
- **What breaks if unavailable or changed:** The related assimilated feature
  is disabled or cannot complete: owner/member auth, cron-protected feed
  refresh, AI key decryption, or platform OAuth callback display.
- **Self-hosting alternative:** These are app-owned environment variables; no
  hosted service is introduced by the prefix change itself.

## GitHub OAuth (Admin Login)

- **Purpose:** Authenticates admin users for `/admin/*` via "Continue with
  GitHub".
- **Data sent off-domain:** The browser is redirected to GitHub for sign-in;
  the server exchanges the returned code with GitHub for an access token and
  fetches the user's GitHub id/login/email via the GitHub API.
- **External endpoints:** `https://github.com/login/oauth/authorize`,
  `https://github.com/login/oauth/access_token`,
  `https://api.github.com/user`, `https://api.github.com/user/emails`
- **What breaks if unavailable or changed:** Admins cannot sign in via GitHub
  (Google OAuth remains available as the other configured provider). No
  public-facing feature depends on this.
- **Self-hosting alternative:** A self-hosted password/credential login form.
  Avoids depending on GitHub but requires storing and protecting admin
  credentials directly.
- **Required config:** `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET`,
  `ADMIN_GITHUB_USERNAMES` (comma-separated allowlist of GitHub usernames
  permitted to bootstrap an admin identity)
- **Callback URL to register:** `https://augmenthumankind.com/admin/auth/github/callback`

## Google OAuth (Admin Login)

- **Purpose:** Authenticates admin users for `/admin/*` via "Continue with
  Google".
- **Data sent off-domain:** The browser is redirected to Google for sign-in;
  the server exchanges the returned code with Google for an access token and
  fetches the user's Google subject id/email/name via the OpenID Connect
  userinfo endpoint.
- **External endpoints:** `https://accounts.google.com/o/oauth2/v2/auth`,
  `https://oauth2.googleapis.com/token`,
  `https://openidconnect.googleapis.com/v1/userinfo`
- **What breaks if unavailable or changed:** Admins cannot sign in via Google
  (GitHub OAuth remains available as the other configured provider). No
  public-facing feature depends on this.
- **Self-hosting alternative:** A self-hosted password/credential login form.
  Avoids depending on Google but requires storing and protecting admin
  credentials directly.
- **Required config:** `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`,
  `ADMIN_GOOGLE_EMAILS` (comma-separated allowlist of Google account emails
  permitted to bootstrap an admin identity)
- **Callback URL to register:** `https://augmenthumankind.com/admin/auth/google/callback`

## Tiptap (Rich Text Editor, via esm.sh CDN)

- **Purpose:** Provides the rich-text editing toolbar (headings, formatting,
  colors, links, images, iframe embeds, HTML source view) used by the page
  section editor at `/admin/pages/*/sections/*`.
- **Data sent off-domain:** None at runtime for editor content — page content
  is saved to the local database via the admin form. The editor's JavaScript
  modules themselves (`@tiptap/core`, `@tiptap/starter-kit`, and the
  `@tiptap/extension-*` packages) are fetched from `esm.sh` by the admin's
  browser when an admin page loads.
- **External endpoint:** `https://esm.sh/@tiptap/*@2` (import-mapped in
  `app/views/admin/layout.php`)
- **What breaks if unavailable or changed:** If esm.sh is unreachable or
  changes these package exports, the section-content textarea on
  `/admin/pages/*/sections/*` falls back to a plain `<textarea>` (raw HTML)
  instead of the rich-text toolbar — content can still be edited and saved as
  HTML. No public-facing page is affected.
- **Self-hosting alternative:** Bundle the same `@tiptap/*` packages locally
  (e.g. via npm + a build step) and serve them from `/assets/js/` instead of
  the CDN. Avoids the esm.sh dependency at the cost of adding a JS build
  pipeline to this otherwise no-build PHP project.
- **Required config:** None — the importmap in `app/views/admin/layout.php`
  is static.

## GuzzleHTTP 7

- **Purpose:** Shared HTTP client for PHP syndication adapters.
- **Package:** `guzzlehttp/guzzle`
- **Data sent off-domain:** None by itself. Adapter calls send post content,
  media URLs, access tokens, and metadata to the selected syndication service.
- **What breaks if unavailable or changed:** Syndication publishing and token
  refresh actions fail until the package or adapter code is updated.
- **Self-hosting alternative:** PHP stream/cURL wrappers per adapter. This is
  possible but duplicates timeout, header, body, and error handling.
- **Testing rule:** Adapter tests must use mocked HTTP clients or dry-run
  payload checks. Verification must not perform real outbound publish calls
  unless explicitly requested.

## Syndication Services

- **Purpose:** Optional owner-triggered distribution of PHP blog posts to
  external services from `/admin/platform-connections`.
- **Services:** Bluesky, WordPress.com, self-hosted WordPress, Blogger,
  Substack, LinkedIn, Facebook, and Instagram.
- **Data sent off-domain:** Post title, HTML/text content, canonical URL,
  featured image URL, categories/hashtags, platform credentials or tokens,
  and service-specific metadata.
- **What breaks if unavailable or changed:** Publishing to the affected
  service fails. Local posts, feeds, admin editing, and already-synced records
  remain available.
- **Self-hosting alternative:** Do not syndicate automatically; use exported
  feeds or manual copy/paste into each service.
- **Required config:** Service credentials are stored per connection in the
  current PHP database and encrypted with
  `PLATFORM_AI_SETTINGS_ENCRYPTION_KEY` semantics.
- **Testing rule:** Use mocked service responses. Do not use live credentials
  or perform real posts during route, migration, or deletion-readiness checks.

## Platform OAuth Providers

- **Purpose:** Interactive OAuth authentication for WordPress.com, Blogger, LinkedIn, Facebook, and Instagram connections.
- **Data sent off-domain:** The browser is redirected to the provider's OAuth consent screen; the server exchanges the returned code for an access token and refresh token with the provider's token endpoint.
- **What breaks if unavailable or changed:** OAuth-based credential acquisition fails. Manually entered credentials in the connection form remain available as a fallback.
- **Self-hosting alternative:** Continue using the manual credential entry form in `/admin/platform-connections/create` and `/admin/platform-connections/[id]/edit`.
- **Required config:**
  - WordPress.com: `WORDPRESS_COM_CLIENT_ID`, `WORDPRESS_COM_CLIENT_SECRET`
  - Blogger: `BLOGGER_GOOGLE_CLIENT_ID`, `BLOGGER_GOOGLE_CLIENT_SECRET`
  - LinkedIn: `LINKEDIN_CLIENT_ID`, `LINKEDIN_CLIENT_SECRET`
  - Facebook: `FACEBOOK_CLIENT_ID`, `FACEBOOK_CLIENT_SECRET`
  - Instagram: `INSTAGRAM_CLIENT_ID`, `INSTAGRAM_CLIENT_SECRET` (falls back to Facebook credentials if not set)
- **Callback URLs to register:** `https://augmenthumankind.com/admin/platform-connections/auth/{platform}/callback` where `{platform}` is one of `wordpress-com`, `blogger`, `linkedin`, `facebook`, `instagram`.

## Piece Renderer CDN Runtimes

- **Purpose:** PHP piece pages, standard embed routes, and the new immersive gallery/exhibit viewer views render generative sketches (P5, C2, Three.js, SVG) using client-side dynamic libraries loaded from CDNs.
- **External endpoints:**
  - P5.js: `https://cdnjs.cloudflare.com/ajax/libs/p5.js/1.9.0/p5.min.js`
  - C2.js: `https://cdn.jsdelivr.net/npm/c2.js@1.0.9/dist/c2.min.js`
  - Three.js Core: `https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js`
  - Three.js OrbitControls: `https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/controls/OrbitControls.js`
- **Data sent off-domain:** The browser requests the runtime script from the CDN. Piece content and database data are not sent by the PHP server.
- **What breaks if unavailable or changed:** P5, C2, and Three.js-based piece pages, embeds, and immersive views fail to render until local runtime copies are served.
- **Self-hosting alternative:** Store p5.js, c2.min.js, three.module.js, and OrbitControls.js under `public/assets/vendor/` and load them from the PHP site.

## AI Piece Generation (Multi-Vendor)

- **Purpose:** Allow generating and repairing art pieces using external LLM models (p5, c2, three, svg).
- **External endpoints:**
  - OpenRouter: `https://openrouter.ai/api/v1/chat/completions`
  - DeepSeek: `https://api.deepseek.com/chat/completions`
  - Mistral / Mistral Vibe: `https://api.mistral.ai/v1/chat/completions`
  - Google Gemini: `https://generativelanguage.googleapis.com/v1beta/models`
  - OpenCode Zen: `https://opencode.ai/zen/v1/*` (gateway for various provider endpoints)
  - OpenCode Go: `https://opencode.ai/zen/go/v1/*` (gateway for various provider endpoints)
- **Data sent off-domain:** User-provided creative prompts and system instructions (for the target engine) are sent to the selected vendor API along with the decrypted API key for authorization.
- **What breaks if unavailable or changed:** AI piece generation and version repair via affected profiles will fail (already-saved pieces/versions are stored locally and are unaffected).
- **Self-hosting alternative:** Configure a local proxy endpoint (e.g. Ollama) running self-hosted models as a custom vendor profile in the database.
- **Required config:** API keys are stored in `user_ai_vendor_keys.encrypted_api_key`, encrypted/decrypted via AES-256-GCM using `PLATFORM_AI_SETTINGS_ENCRYPTION_KEY`. Vendor settings are configured in `user_ai_vendor_settings`.

