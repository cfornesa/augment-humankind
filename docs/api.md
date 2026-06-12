# API Contract

## `GET /contact`

Renders the public contact form for collaboration, hiring, project help,
strategy help, and other inquiries.

The page includes:

- name field
- email field
- optional organization field
- inquiry type field
- message field
- hidden honeypot field
- CSRF token field
- reCAPTCHA v3 token field

## `POST /contact`

Processes the contact form and returns the `/contact` page with either an
inline success message or validation errors.

### Accepted Fields

- `name` — required, 2-120 characters
- `email` — required, valid email address, up to 254 characters
- `organization` — optional, up to 160 characters
- `inquiry_type` — required, one of:
  - `collaboration`
  - `hiring`
  - `project_help`
  - `strategy_help`
  - `other`
- `message` — required, 20-3000 characters
- `csrf_token` — required
- `g-recaptcha-response` — required
- `website` — hidden honeypot, must be empty

### Validation Errors

The handler redisplays the form with safe, user-facing validation messages
when:

- a required field is missing
- a field exceeds its allowed length
- `email` is invalid
- `inquiry_type` is not recognized
- the honeypot field is filled
- the CSRF token is missing or invalid
- reCAPTCHA verification fails, has the wrong action, wrong hostname, or a
  score below the configured threshold
- required email/reCAPTCHA configuration is missing
- SMTP delivery fails

### Success Response

On success, the handler:

- sends a plain-text email through the configured SMTP service
- sets `Reply-To` to the submitter email
- does not store the submission in a database or file
- redisplays `/contact` with an inline success panel

No separate success URL is added.
