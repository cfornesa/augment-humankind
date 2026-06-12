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

## Public Portfolio Routes

These routes are durable public URLs:

- `GET /portfolio`
- `GET /portfolio/categories`
- `GET /portfolio/category/[slug]`
- `GET /portfolio/exhibit/[slug]`
- `GET /portfolio/work/[slug]`
- `GET /media/[id]`
- `GET /image/[id]`

`/media/[id]` streams any active stored media blob. `/image/[id]` is an
image-only public route for image assets. Missing, deleted, or mismatched media
returns the shared 404 view.

## Admin CMS Routes

All `/admin/*` routes require an authenticated admin session. Unauthenticated
requests redirect to `/admin/login`.

### Artworks

- `GET /admin/artworks`
- `GET /admin/artworks/create`
- `POST /admin/artworks/create`
- `GET /admin/artworks/[id]/edit`
- `POST /admin/artworks/[id]/edit`
- `POST /admin/artworks/[id]/delete`
- `POST /admin/artworks/reorder`

Artwork create/update accepts title, slug, year, description, placard fields,
thumbnail URL, category ids, exhibit ids, and ordered media slide fields. Legacy
reference fields `piece_type`, `piece_value`, `category_id`, and
`legacyPieceFromMediaItems` are intentionally not part of this app contract.

### Categories and Exhibits

- `GET /admin/categories`
- `GET /admin/categories/create`
- `POST /admin/categories/create`
- `POST /admin/categories/create-inline`
- `GET /admin/categories/[id]/edit`
- `POST /admin/categories/[id]/edit`
- `POST /admin/categories/[id]/delete`
- `POST /admin/categories/reorder`
- `GET /admin/exhibits`
- `GET /admin/exhibits/create`
- `POST /admin/exhibits/create`
- `POST /admin/exhibits/create-inline`
- `GET /admin/exhibits/[id]/edit`
- `POST /admin/exhibits/[id]/edit`
- `POST /admin/exhibits/[id]/delete`
- `POST /admin/exhibits/reorder`

Inline create endpoints return JSON:

```json
{"success":true,"id":123,"name":"Example","slug":"example"}
```

### Media Library

- `GET /admin/media`
- `GET /admin/media/library`
- `POST /admin/media/upload`
- `POST /admin/media/import`
- `POST /admin/media/[id]/trash`
- `POST /admin/media/[id]/destroy`

`/admin/media/library` returns a JSON array for the existing Tiptap/media
picker:

```json
[
  {
    "id": 123,
    "mime_type": "image/png",
    "url": "/media/123",
    "legacy_url": "/image/123",
    "kind": "image"
  }
]
```

Upload/import success returns the same item fields at the top level. Media is
stored in the database and is not written to repo files.

### Trash

- `GET /admin/trash`
- `POST /admin/trash/restore`
- `POST /admin/trash/purge`
- `POST /admin/trash/empty`

Supported trash types are artworks, categories, exhibits, and media.

### Navigation

- `GET /admin/navigation`
- `POST /admin/navigation/external`
- `POST /admin/navigation/reorder`
- `POST /admin/navigation/[id]/toggle`
- `POST /admin/navigation/[id]/delete`
- `POST /admin/navigation/[id]/label`
- `POST /admin/navigation/[id]/target`

The public header uses `navigation_items` when available. It falls back to the
system navigation if the database or table is unavailable.
