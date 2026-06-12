# External Dependencies

## Self-Hosted (no network requests)

The public site and the main authenticated admin shell serve their primary fonts locally.

### Self-Hosted Fonts (`public/assets/fonts/`)

Registered via `@font-face` in `public/assets/css/style.css`. Preloaded in both `app/views/layout.php` and `app/views/admin/layout.php`.

| Typeface | Usage |
|---|---|
| **Lora** | Body text — `'Lora', Georgia, serif` |
| **Pinyon Script** | Display headings — `'Pinyon Script', 'Lora', cursive` |
| **Courier Prime** | Monospace / admin metadata — `'Courier Prime', 'Courier New', monospace` |

---

## Third-Party Service Dependencies

The **public-facing site has no font/CDN dependency at all**. Remaining off-domain dependencies are limited to accepted admin and editor integrations.

### GitHub OAuth — admin login

**Provider**: `https://github.com/` and `https://api.github.com/`  
**Used by**: `/admin/auth/github/*`

**Risk**: If GitHub changes its OAuth API, rate limits, terms, or availability, GitHub-backed admin login stops working for GitHub identities and those admins cannot sign in through GitHub until the integration is updated.

**Self-hosting alternative**: First-party password-only admin login.

---

### Google OAuth — admin login

**Provider**: `https://accounts.google.com/`, `https://oauth2.googleapis.com/`, and `https://openidconnect.googleapis.com/`  
**Used by**: `/admin/auth/google/*`

**Risk**: If Google changes its OAuth API, rate limits, terms, or availability, Google-backed admin login stops working for Google identities and those admins cannot sign in through Google until the integration is updated.

**Self-hosting alternative**: First-party password-only admin login.

---

### Google reCAPTCHA v3 — contact form spam mitigation

**Provider**: `https://www.google.com/recaptcha/`
**Used by**: `/contact` and `/about` POST handlers (`PageController::contact()`, `AboutController::contact()`), via `app/helpers/recaptcha.php`

**Risk**: Loads `https://www.google.com/recaptcha/api.js` (and associated `gstatic.com`/`google.com` resources) on `/contact` and `/about` — the first off-domain script on public pages. If Google's reCAPTCHA service is unavailable or changes its API, server-side verification fails open (messages are still saved, score check is skipped), so the contact form keeps working but spam protection temporarily degrades to the honeypot/time-trap layer only.

**Self-hosting alternative**: Honeypot + time-trap fields (already implemented as a first layer, zero off-domain calls) provide baseline protection without reCAPTCHA. If reCAPTCHA is removed entirely, `recaptcha_verify()` can be left configured-but-unused (returns `not-configured` → flagged-not-rejected) or the score-check branch removed from both controllers.

---

### Tiptap Rich Text Editor — `esm.sh`

**CDN**: `https://esm.sh/`  
**Loaded via**: `<script type="module" src="/assets/js/tiptap-editor.js">` in `app/views/admin/layout.php`  
**Import map**: also in `admin/layout.php` — deduplicates shared ProseMirror instances across all extensions

**Risk**: If `esm.sh` is unavailable, the Tiptap editor fails to load. Existing saved content is unaffected; admin users can still submit forms (the `<textarea>` is hidden but present). The rest of the admin panel functions normally.

**Packages loaded**:

| Package | Version | Purpose |
|---|---|---|
| `@tiptap/core` | `^2` | Editor core |
| `@tiptap/starter-kit` | `^2` | Bold, italic, headings, lists, blockquote, code, HR, history |
| `@tiptap/extension-underline` | `^2` | Underline mark |
| `@tiptap/extension-text-style` | `^2` | Base for font size, color, font family |
| `@tiptap/extension-color` | `^2` | Text color |
| `@tiptap/extension-highlight` | `^2` | Highlight color (multicolor) |
| `@tiptap/extension-font-family` | `^2` | Font family |
| `@tiptap/extension-link` | `^2` | Hyperlinks (extended with `title` attribute) |
| `@tiptap/extension-image` | `^2` | Image nodes (extended with hover alt-text editor NodeView) |

**Self-hosting alternative**: Run `npm install @tiptap/core @tiptap/starter-kit ...` and `npx esbuild public/assets/js/tiptap-editor.js --bundle --format=esm --outfile=public/assets/js/tiptap.bundle.js`, then update the script src. No changes to PHP required.
