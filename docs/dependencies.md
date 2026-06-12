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
