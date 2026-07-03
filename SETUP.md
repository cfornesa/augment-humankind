# SETUP.md — Standing Up a Site From This Codebase

This is the exact, ordered procedure for taking a fresh copy of this codebase
to a working site. It is written to be executable by a human **or** an agent:
every step has a command and an expected outcome. The same procedure applies
whether this is the first deployment or a duplicate — each deployment differs
only in its MySQL database and its `.env` values.

For background on the architecture and schema-change conventions, see
[README.md](README.md).

---

## 1. Prerequisites

- **PHP ≥ 8.1** with the `pdo_mysql` and `openssl` extensions
  (`php -m | grep -E 'pdo_mysql|openssl'` should print both).
- **Composer** (https://getcomposer.org).
- **A MySQL 8+ server** you can create a database on (local or remote;
  Hostinger-style managed MySQL works — set `DB_SSL=true` if the host
  requires TLS).

Install PHP dependencies:

```sh
composer install
```

**Expected outcome:** `vendor/` exists at the repo root and the command exits 0.
(The repo also ships `public/vendor/` for the web runtime; `composer install`
keeps both consistent per `composer.json`.)

## 2. Create an empty database

On your MySQL server:

```sql
CREATE DATABASE my_site CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'my_site'@'%' IDENTIFIED BY '<strong password>';
GRANT ALL PRIVILEGES ON my_site.* TO 'my_site'@'%';
```

**Expected outcome:** an empty database the credentials below can reach.
Nothing else — the installer creates every table.

## 3. Configure the environment

```sh
cp env.example .env
```

Fill in `.env`. The file is annotated section by section; the short version:

| Variable | Required? | Purpose / failure mode if missing |
|---|---|---|
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` | **Required** | Database connection. Missing → installer aborts; site shows "This site isn't configured yet". |
| `DB_PORT` | Optional | Defaults to `3306`. |
| `DB_SSL` | Optional | `true` only if the host requires TLS to MySQL. |
| `GITHUB_CLIENT_ID` + `GITHUB_CLIENT_SECRET` + `ADMIN_GITHUB_USERNAMES` — **or** — `GOOGLE_CLIENT_ID` + `GOOGLE_CLIENT_SECRET` + `ADMIN_GOOGLE_EMAILS` | **At least one provider required** | Admin sign-in. Without a complete provider + allowlist, no one can ever reach `/admin` and the public site stays behind the setup gate. |
| `APP_NAME` | Recommended | Fallback site name until Site Identity is configured (defaults to "My Site"). |
| `PUBLIC_SITE_URL` | Recommended | Canonical origin for feeds/OG/canonical URLs (falls back to the request Host header). No trailing slash. |
| `AI_SETTINGS_ENCRYPTION_KEY` | Recommended | AES-256-GCM key for encrypting AI vendor keys, platform tokens, and DB-stored reCAPTCHA secrets. Generate: `openssl rand -hex 32`. If unset when `RECAPTCHA_SECRET_KEY` is present, the installer warns and seeds form secrets as NULL. |
| `RECAPTCHA_SITE_KEY`, `RECAPTCHA_SECRET_KEY`, `RECAPTCHA_MIN_SCORE` | Optional | Contact form protection. Missing → form renders with a "configuration needed" notice and a disabled submit button. |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_ENCRYPTION`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_FROM_EMAIL`, `SMTP_FROM_NAME`, `CONTACT_TO_EMAIL` | Optional | Outbound contact-form email. Missing → submission fails with "configuration incomplete". |
| `CRON_SECRET` | Optional | Auth for scheduled-publish / feed-refresh cron endpoints. Generate: `openssl rand -hex 32`. |
| `SITE_TITLE` | Optional | OpenRouter dashboard attribution only. |
| `PLATFORM_*` (Section 3 of env.example) | **Omit on new sites** | Legacy one-time migration tooling only; not read at runtime. |

Process environment variables always win over `.env`, so any value can be
overridden per-invocation without editing the file.

**Expected outcome:** `.env` exists at the repo root with at least the `DB_*`
values and one complete OAuth provider + allowlist.

## 4. Create the OAuth app(s)

- **GitHub:** https://github.com/settings/developers → New OAuth App →
  callback URL `https://yourdomain.com/auth/github/callback`
  (local dev: `http://127.0.0.1:8080/auth/github/callback`).
- **Google:** https://console.cloud.google.com/apis/credentials →
  OAuth client → callback URL `https://yourdomain.com/auth/google/callback`.

Put the client ID/secret in `.env`, and your GitHub username(s) in
`ADMIN_GITHUB_USERNAMES` (or Google email(s) in `ADMIN_GOOGLE_EMAILS`) —
only allowlisted identities can become the site's admin on first login.

**Expected outcome:** exactly one callback URL registered per provider, and
the allowlist names the person who will do the first login.

## 5. Run the database installer

Preview first (read-only):

```sh
php scripts/setup-database.php --dry-run
```

**Expected outcome:** "DRY RUN — no changes will be made. Target database:
…", then every manifest step listed as `✗ missing` on an empty database,
ending with "schema changes are pending".

Then apply:

```sh
php scripts/setup-database.php
```

**Expected outcome:** each step prints `✓ applied`, ending with
"✓ Done — schema is now up to date." The installer also seeds the baseline
`site_settings` row, the Contact/Newsletter forms, and the default art-piece
starter templates.

Notes:

- **Existing-data failsafe:** if the target database is not empty, the
  installer prints a summary of what it found ("3 admin identities, 42
  pages, …") and, when run interactively, asks for confirmation. Pass
  `--yes` to skip the prompt (non-interactive runs proceed automatically
  after printing the summary). The installer is additive and idempotent —
  it never deletes data.
- `--with-example-content` additionally seeds example `/`, `/services`, and
  `/notes` pages plus the Celestial theme code. Skip it for a blank new site:
  without it, the core routes render generic placeholder copy until you
  create real pages in the admin.
- Re-running the installer is the documented upgrade path after
  `git pull` — completed steps are skipped.

## 6. Verify readiness

```sh
php scripts/check-portable-launch-readiness.php
```

**Expected outcome:** exits 0 with "Portable launch readiness passed".
`FAIL` lines are blocking (missing env/DB/schema); `WARN` lines are
feature-gated gaps (e.g. no `AI_SETTINGS_ENCRYPTION_KEY`) that can be
resolved later. Fix any `FAIL`, re-run the installer if schema-related,
and re-check.

## 7. Run the site

Local development:

```sh
php -S 127.0.0.1:8080 -t public public/index.php
```

(Production: point the web server's docroot at `public/`.)

**Expected outcome:** before any admin exists, every public route returns
**503 "Site setup in progress"** (the setup gate) — this is correct. `/admin`
and `/auth/*` remain reachable.

## 8. First admin login

Visit `http://127.0.0.1:8080/admin` (or your domain) and sign in with the
allowlisted GitHub/Google identity.

**Expected outcome:** the OAuth callback creates the first `admin_identities`
row, the 503 setup gate lifts immediately, and the public site renders
placeholder content: a starter homepage, placeholder Services/Notes copy, and
the contact form. `/admin/setup` shows the first-run checklist.

## 9. Configure the site

In the admin panel:

1. **`/admin/site-identity`** — site title, canonical public URL, theme,
   palette, logos. This replaces the "My Site"/`APP_NAME` fallback everywhere.
2. **`/admin/features`** — toggle feature groups (Pieces, Exhibits, Blog) and
   AI use cases (master switch, per-engine piece generation, theme
   generation, alt text, per-area text editing). All flags default to ON;
   disabling is content-safe (existing content keeps its URLs).
3. **`/admin/pages`** — create the real `home`, `services`, `notes`, and
   `contact` pages; they take over from the placeholders automatically.
4. **`/admin/navigation`** — adjust public navigation (falls back to a
   generic Home/Services/Notes/Blog/Contact/Portfolio set until then).
5. Optional: `/admin/user-profiles` (AI vendors + keys — requires
   `AI_SETTINGS_ENCRYPTION_KEY`), `/admin/forms` (recipient/reCAPTCHA per
   form), `/admin/platform-connections` (syndication).

**Expected outcome:** the public site shows your content and identity; the
`/admin/setup` checklist items are green.

## 10. Duplicating to another site

Nothing in the codebase is site-specific. To stand up another site:

1. Copy/clone the codebase as-is.
2. Create a new empty MySQL database (step 2).
3. Write a new `.env` (step 3) and new OAuth apps with that domain's
   callback URLs (step 4).
4. Repeat steps 5–9.

To keep any deployment aligned after pulling code updates:

```sh
git pull && php scripts/setup-database.php --yes
```
