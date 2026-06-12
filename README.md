# Augment Humankind

A small no-framework PHP site for `augmenthumankind.com`.

## Direction

Augment Humankind is a mission-first AI consulting practice for nontechnical
teams. The v1 site uses a Fieldguide position: practical, candid, and visibly
friendly, with `Friendly Guide.png` serving as the primary mascot signal.

The business promise is disciplined augmentation: help teams use AI to extend
their capabilities without overclaiming what a one-person practice can deliver.

## Services

- **AI Strategy Fieldguide** — clarify useful AI opportunities before adopting tools.
- **Workflow Prototype Build** — build one focused, maintainable AI-assisted workflow.
- **Team Capability Transfer** — help teams keep using AI well through playbooks, examples, and guided practice.

## Routes

- `/` — mission-led homepage
- `/services` — three focused service offers
- `/notes` — lightweight field notes landing page
- `/contact` — reCAPTCHA-protected inquiry form
- `/portfolio` — public work gallery
- `/portfolio/categories` — public category index
- `/portfolio/category/[slug]` — public category detail
- `/portfolio/exhibit/[slug]` — public exhibit detail
- `/portfolio/work/[slug]` — public work detail
- `/media/[id]` and `/image/[id]` — public blob-serving routes for stored media

Admin routes are flat and protected by OAuth login:

- `/admin/pages`
- `/admin/artworks`
- `/admin/categories`
- `/admin/exhibits`
- `/admin/media`
- `/admin/trash`
- `/admin/navigation`

## Deployed File Layout

The production document root should include:

- `index.php`
- `.htaccess`
- `assets/`
- `vendor/` Composer dependencies, including PHPMailer

The FTP deploy action may also create `.ftp-deploy-sync-state-public.json` in
the document root. That file is deploy metadata, not part of the app.

If an old Hostinger Website Builder site still appears at `/`, confirm the
domain is assigned to the PHP hosting document root (`public_html`) rather than
Website Builder, then purge any Hostinger/cache/CDN layer. The app's
`.htaccess` sets `DirectoryIndex index.php`, so the PHP mission homepage should
serve from the root once the domain points at `public_html`.

## Contact Form Configuration

The `/contact` form uses Google reCAPTCHA v3 and Hostinger SMTP. Create a
protected `.env` file in the deployed document root using `env.example` as the
template.

Required values:

- `RECAPTCHA_SITE_KEY`
- `RECAPTCHA_SECRET_KEY`
- `RECAPTCHA_MIN_SCORE`
- `SMTP_HOST` — use `smtp.hostinger.com`
- `SMTP_PORT` — use `465` with `smtps`, or `587` with `starttls`
- `SMTP_ENCRYPTION` — use `smtps` or `starttls`
- `SMTP_USERNAME` — use the Hostinger mailbox address
- `SMTP_PASSWORD`
- `SMTP_FROM_EMAIL` — use the same verified Hostinger mailbox address as
  `SMTP_USERNAME`
- `SMTP_FROM_NAME`
- `CONTACT_TO_EMAIL` — the inbox that receives form submissions; it can be the
  same address as `SMTP_FROM_EMAIL`

Do not commit real secret values.

For reCAPTCHA, create a Google reCAPTCHA v3 property for the public domain.
Use the generated site key for `RECAPTCHA_SITE_KEY` and the generated secret
key for `RECAPTCHA_SECRET_KEY`. If you want to test the complete browser flow
locally, include your local test host in the reCAPTCHA domain settings.

The contact form only sends outbound email through SMTP. IMAP settings such as
`imap.hostinger.com` are for email apps that need to read the mailbox and are
not used by this site.

## Database Configuration

Portfolio, media library, managed pages, OAuth identities, and navigation are
stored in MySQL. `schema.sql` is the source of truth for the tables.

Required values:

- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

The public mission and contact routes still have safe static behavior if the
database is unavailable. Managed pages, `/portfolio/*`, and `/admin/*` require
the database.

## Media Uploads

The admin media picker accepts images and videos and stores them in
`media_files` as blobs. `public/.htaccess` sets these shared-hosting upload
limits:

- `upload_max_filesize=64M`
- `post_max_size=72M`
- `max_execution_time=120`
- `max_input_time=120`

If Hostinger returns a 500 after deployment, remove those `php_value` lines from
`.htaccess` and set the same limits in Hostinger's PHP settings instead.

### Production Verification

After deployment and `.env` setup:

1. Confirm `https://augmenthumankind.com/contact` loads the form with an
   enabled submit button.
2. Submit an incomplete form and confirm inline validation errors appear.
3. Submit a complete test inquiry and confirm the page stays on `/contact`
   with the inline success panel.
4. Confirm the message arrives at `CONTACT_TO_EMAIL`.
5. Confirm the delivered message uses the configured `SMTP_FROM_EMAIL` as
   `From` and the submitter email as `Reply-To`.
6. Confirm direct requests to `https://augmenthumankind.com/.env` and
   `https://augmenthumankind.com/vendor/autoload.php` are denied.

If a complete inquiry fails, check the reCAPTCHA admin console for the domain,
action name `contact_submit`, and score; then check Hostinger SMTP host, port,
encryption, username, password, and verified sender settings.

You can check the local configuration shape without sending email or printing
secrets:

```sh
php scripts/verify-contact-config.php
```

## Run Locally

```sh
php -S 127.0.0.1:8080 -t public public/index.php
```

Then open:

- `http://127.0.0.1:8080/`
- `http://127.0.0.1:8080/services`
- `http://127.0.0.1:8080/notes`
- `http://127.0.0.1:8080/contact`
- `http://127.0.0.1:8080/portfolio`
- `http://127.0.0.1:8080/admin`

## Notes

- MySQL is required for admin CMS, navigation registry, and portfolio content.
- Form submissions are emailed only; they are not stored by the app.
- Public routes are treated as durable. If they move later, add permanent redirects.
- Deployments should upload the contents of `public/` into the hosting document root, not the repository root.
