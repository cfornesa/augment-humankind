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
