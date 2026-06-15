# Round 3 — Piece Edit Reconciliation (Metadata/HTML/CSS/JS Tabs) + Media Asset CRUD Parity

> **Status**: Done. Implemented in the PHP app; retained here as the
> implementation plan and audit trail. This document is self-contained
> and intended to be executed independently (no access to prior chat
> context required). Read every "Current state" excerpt against the live
> file before editing — line numbers are best-effort anchors, not guarantees.

## Context

The platform's piece editor (`admin-pieces.tsx`) shows four tabs when editing
an art piece: **Metadata**, **HTML**, **CSS**, **JS**. The PHP port
(`/admin/pieces/{id}/edit`) currently shows only Metadata — the HTML/CSS/JS
of the piece's current version are invisible and unreachable except via
"Manage Versions" → edit a specific version (a different, lower-level form).
This round reconciles `/admin/pieces/{id}/edit` (and `/admin/pieces/create`)
to expose all four tabs, editing the **current version** in place.

Separately, Round 2 unified `/admin/media` to show both native `media_files`
uploads and the 102 migrated `media_assets`, but left the migrated assets
**read-only**. `MediaAsset` already has full `softDelete`/`restore`/
`hardDelete`/`trashed()` support — this round wires that into `/admin/media`
and `/admin/trash` so migrated assets get full CRUD parity with native
uploads.

### Standing constraints (apply to every edit in this doc)

- **Never** write to the platform's source MySQL DB (`PLATFORM_*` env vars) —
  only the app's own DB (the one `db()` in `public/app/config/database.php`
  connects to).
- Public URLs must never break (no route in this doc is renamed or removed).
- Run `php -l <file>` after every edit to a `.php` file.
- After implementing, update `docs/platform-route-matrix.md` and
  `docs/platform-assimilation-plan.md` with a short "Round 3" entry (see
  Verification step 4).

### Root-cause bug found during scoping

`PiecesAdminController::resolvePieceData()` (around line 193) and
`resolveVersionData()` (around line 235) whitelist the `engine` column to
`['p5', 'css']`:

```php
if (!in_array($engine, ['p5', 'css'], true)) {
    $engine = 'p5';
}
```

But the actual schema (`platform/lib/db/src/schema/art-pieces.ts`,
`artPieceEngineSchema`) and the data already in `art_pieces.engine` /
`art_piece_versions.engine` use **`p5 | c2 | three | svg`**. `'css'` is not a
valid engine value at all. This bug silently coerces any c2/three/svg piece
back to `p5` whenever it's saved via either admin form. **Fix this first** —
both phases below depend on the engine selector being correct.

---

## Phase A — Piece edit: Metadata / HTML / CSS / JS tabs

### A0. Fix the engine whitelist (two locations)

File: `public/app/controllers/Admin/PiecesAdminController.php`

1. In `resolvePieceData()` (~line 193), change:
   ```php
   if (!in_array($engine, ['p5', 'css'], true)) {
       $engine = 'p5';
   }
   ```
   to:
   ```php
   if (!in_array($engine, ['p5', 'c2', 'three', 'svg'], true)) {
       $engine = 'p5';
   }
   ```

2. In `resolveVersionData()` (~line 235), apply the identical change.

### A1. Fix the engine `<option>` lists in both forms

File: `public/app/views/admin/pieces/form.php` (~lines 40-43) — the engine
`<select name="engine">` currently has only two `<option>`s (`p5`, `css`).
Replace its options with:

```php
<select id="engine" name="engine">
  <option value="p5" <?= ($piece['engine'] ?? 'p5') === 'p5' ? 'selected' : '' ?>>P5.js</option>
  <option value="c2" <?= ($piece['engine'] ?? '') === 'c2' ? 'selected' : '' ?>>C2.js</option>
  <option value="three" <?= ($piece['engine'] ?? '') === 'three' ? 'selected' : '' ?>>Three.js</option>
  <option value="svg" <?= ($piece['engine'] ?? '') === 'svg' ? 'selected' : '' ?>>SVG</option>
</select>
```

(Match the existing attribute style/indentation in the file — the snippet
above is the logical content, not a literal diff.)

File: `public/app/views/admin/pieces/version-form.php` (~lines 32-35) — same
fix, same four options, keyed off `$version['engine'] ?? 'p5'`.

### A2. Restructure `public/app/views/admin/pieces/form.php` into tabs

**Current structure** (88 lines total):

```
$isEdit = !empty($piece['id']);
$pageTitle = ...;
ob_start();
$piece = $piece ?? [];
... .admin-container
  .admin-header-row (h1 + Back link to /admin/pieces)
  error block (.form-status.form-status-error if $error)
  if ($isEdit && !empty($piece['current_version'])):
    .piece-preview-pane > h2 "Current Version Preview" + piece_render_iframe($piece, $piece['current_version'], 360)
  endif
  <form method="post" class="admin-form">
    title input (required, maxlength 255)
    .field-grid: engine <select> + status <select> (active/draft/archived)
    thumbnail_url input (type=url, maxlength 2048)
    description textarea (rows=4)
    prompt textarea (rows=4) + <small>The creative prompt that generated this piece.</small>
    .form-actions: Update/Create Piece button + Cancel link
  </form>
  if ($isEdit):
    second .form-actions: "Manage Versions" link (-> /admin/pieces/{id}/versions)
                         + "View Public" link (-> /pieces/{id}, target=_blank)
  endif
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
```

**Target structure** — keep everything outside the `<form>` exactly as-is
(preview pane, Manage Versions / View Public links, error block, header).
Inside the `<form method="post" class="admin-form">`:

1. Immediately after the `<form ...>` opening tag, add a tab bar:

   ```php
   <div class="admin-tabs piece-edit-tabs" role="tablist">
     <button type="button" class="admin-tab active" data-tab="meta">Metadata</button>
     <button type="button" class="admin-tab" data-tab="html">HTML</button>
     <button type="button" class="admin-tab" data-tab="css">CSS</button>
     <button type="button" class="admin-tab" data-tab="js">JS</button>
   </div>
   ```

2. Wrap the **existing** fields (title, `.field-grid` with engine+status,
   thumbnail_url, description, prompt textarea + its `<small>` hint) in:

   ```php
   <div id="tab-meta" class="piece-tab-panel">
     <!-- ...existing fields, unchanged... -->
   </div>
   ```

3. Add three new panels, each containing one large `<textarea>`. Use the
   current version's code as the initial value — `$piece['current_version']`
   is already attached by `PlatformArtPiece::find()`/`attachCurrentVersion()`
   when editing an existing piece, and is absent (`null`/missing) when
   creating a new one:

   ```php
   <?php
     $cv = $piece['current_version'] ?? [];
     $versionNum = $cv['version_number'] ?? null;
   ?>
   <div id="tab-html" class="piece-tab-panel is-hidden">
     <div class="field">
       <label for="html_code">HTML</label>
       <textarea id="html_code" name="html_code" rows="16" class="code-field"><?= e($cv['html_code'] ?? '') ?></textarea>
       <?php if ($versionNum): ?>
         <small>Edits the current version (v<?= (int) $versionNum ?>) in place. Use Manage Versions for history.</small>
       <?php else: ?>
         <small>Saving will create version 1 of this piece if any of HTML/CSS/JS is filled in.</small>
       <?php endif; ?>
     </div>
   </div>
   <div id="tab-css" class="piece-tab-panel is-hidden">
     <div class="field">
       <label for="css_code">CSS</label>
       <textarea id="css_code" name="css_code" rows="16" class="code-field"><?= e($cv['css_code'] ?? '') ?></textarea>
       <?php if ($versionNum): ?>
         <small>Edits the current version (v<?= (int) $versionNum ?>) in place. Use Manage Versions for history.</small>
       <?php endif; ?>
     </div>
   </div>
   <div id="tab-js" class="piece-tab-panel is-hidden">
     <div class="field">
       <label for="generated_code">JS (Generated Code)</label>
       <textarea id="generated_code" name="generated_code" rows="16" class="code-field"><?= e($cv['generated_code'] ?? '') ?></textarea>
       <?php if ($versionNum): ?>
         <small>Edits the current version (v<?= (int) $versionNum ?>) in place. Use Manage Versions for history.</small>
       <?php endif; ?>
     </div>
   </div>
   ```

   Use whatever the file's existing helper is for HTML-escaping (`e(...)` is
   used elsewhere in this file for the error block / title attributes —
   confirm by grepping the file; if a different helper name is used,
   match it).

4. After the closing `</form>` (or just before it — either is fine as long
   as it's valid HTML and doesn't end up inside a `<label>`/`<textarea>`),
   add an inline `<script>` that toggles tabs. Follow the exact vanilla-JS
   pattern already used in `public/app/views/admin/media.php` for its own
   tab/toggle logic (`classList.add('is-hidden')` /
   `classList.remove('is-hidden')`, `classList.toggle('active')`):

   ```html
   <script>
   (function () {
     var tabs = document.querySelectorAll('.piece-edit-tabs .admin-tab');
     var panels = {
       meta: document.getElementById('tab-meta'),
       html: document.getElementById('tab-html'),
       css: document.getElementById('tab-css'),
       js: document.getElementById('tab-js')
     };
     tabs.forEach(function (tab) {
       tab.addEventListener('click', function () {
         tabs.forEach(function (t) { t.classList.remove('active'); });
         tab.classList.add('active');
         Object.keys(panels).forEach(function (key) {
           if (key === tab.dataset.tab) {
             panels[key].classList.remove('is-hidden');
           } else {
             panels[key].classList.add('is-hidden');
           }
         });
       });
     });
   })();
   </script>
   ```

### A3. CSS: allow `.admin-tab` on `<button>` elements

File: `public/assets/admin.css`. The existing rule (around line 729) is:

```css
.trash-tab,.admin-tab{display:inline-flex;align-items:center;gap:0.45rem;padding:0.55rem 0.9rem;border:3px solid var(--line);background:var(--white);box-shadow:3px 3px 0 var(--line);font-weight:950;text-decoration:none}
```

Add `cursor:pointer` so it also reads correctly as a `<button>` (buttons
don't inherit pointer cursor by default in some browsers/resets):

```css
.trash-tab,.admin-tab{display:inline-flex;align-items:center;gap:0.45rem;padding:0.55rem 0.9rem;border:3px solid var(--line);background:var(--white);box-shadow:3px 3px 0 var(--line);font-weight:950;text-decoration:none;cursor:pointer}
```

Optionally add a small block for `.piece-tab-panel` / `.code-field` if the
plain `<textarea>` doesn't already pick up reasonable styling from existing
`.admin-form textarea` rules — check first; only add new CSS if needed
(e.g. `.code-field{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:0.85rem}`).

### A4. Controller changes — `PiecesAdminController.php`

Add a new **private** helper (place near `resolveVersionData()`):

```php
private static function resolveVersionCodeFromPost(): array
{
    $html = trim((string) ($_POST['html_code'] ?? ''));
    $css = trim((string) ($_POST['css_code'] ?? ''));
    $js = trim((string) ($_POST['generated_code'] ?? ''));

    return [
        'html_code' => $html !== '' ? $html : null,
        'css_code' => $css !== '' ? $css : null,
        'generated_code' => $js !== '' ? $js : null,
    ];
}

private static function hasAnyVersionCode(array $code): bool
{
    return $code['html_code'] !== null
        || $code['css_code'] !== null
        || $code['generated_code'] !== null;
}
```

#### `store()` (~lines 22-36)

Current behavior: validates via `resolvePieceData()`/`draftPieceFromPost()`,
calls `PlatformArtPiece::create($data)`, redirects to `/admin/pieces`.

**After** `$pieceId = PlatformArtPiece::create($data);` succeeds, add:

```php
$code = self::resolveVersionCodeFromPost();
if (self::hasAnyVersionCode($code)) {
    $versionId = PlatformArtPieceVersion::create([
        'art_piece_id' => $pieceId,
        'version_number' => 1,
        'prompt' => $data['prompt'] !== null && $data['prompt'] !== ''
            ? $data['prompt']
            : $data['title'],
        'structured_spec' => null,
        'html_code' => $code['html_code'],
        'css_code' => $code['css_code'],
        'generated_code' => $code['generated_code'] ?? '',
        'engine' => $data['engine'],
        'generation_vendor' => null,
        'generation_model' => null,
        'validation_status' => null,
        'generation_attempt_count' => 0,
        'notes' => null,
    ]);
    PlatformArtPiece::updateCurrentVersion($pieceId, $versionId);
}
```

> `art_piece_versions.generated_code` is `NOT NULL` in the platform schema
> (per `PlatformArtPieceVersion::create()`'s INSERT) — if `generated_code`
> is null here, pass `''` instead, as shown above (`$code['generated_code'] ?? ''`).
> Confirm this against `PlatformArtPieceVersion::create()`'s current SQL
> (~lines 45-71) before finalizing — if it already defaults nulls to `''`
> internally, the `?? ''` here is redundant but harmless.

Keep the existing redirect (to `/admin/pieces` or wherever `store()`
currently redirects on success) unchanged.

#### `update()` (~lines 50-69)

Current behavior: loads `$existing = PlatformArtPiece::find((int) $id)`,
validates, calls `PlatformArtPiece::update((int) $id, $data)`, redirects.

**After** `PlatformArtPiece::update((int) $id, $data);` succeeds, add:

```php
$code = self::resolveVersionCodeFromPost();
if (self::hasAnyVersionCode($code)) {
    $currentVersion = $existing['current_version'] ?? null;
    if ($currentVersion) {
        $merged = $currentVersion; // start from full existing row
        $merged['html_code'] = $code['html_code'];
        $merged['css_code'] = $code['css_code'];
        $merged['generated_code'] = $code['generated_code'] ?? ($currentVersion['generated_code'] ?? '');
        $merged['engine'] = $data['engine']; // keep version engine in sync with piece engine
        PlatformArtPieceVersion::update((int) $currentVersion['id'], $merged);
    } else {
        $versionId = PlatformArtPieceVersion::create([
            'art_piece_id' => (int) $id,
            'version_number' => 1,
            'prompt' => $data['prompt'] !== null && $data['prompt'] !== ''
                ? $data['prompt']
                : $data['title'],
            'structured_spec' => null,
            'html_code' => $code['html_code'],
            'css_code' => $code['css_code'],
            'generated_code' => $code['generated_code'] ?? '',
            'engine' => $data['engine'],
            'generation_vendor' => null,
            'generation_model' => null,
            'validation_status' => null,
            'generation_attempt_count' => 0,
            'notes' => null,
        ]);
        PlatformArtPiece::updateCurrentVersion((int) $id, $versionId);
    }
}
```

**Critical invariant**: `PlatformArtPieceVersion::update()` (~lines 73-97) is
a **full-row update** — every field in `$merged` is written back. The
`$merged = $currentVersion;` starting point preserves `prompt`,
`structured_spec`, `generation_vendor`, `generation_model`,
`validation_status`, `generation_attempt_count`, `notes`, and
`version_number` exactly as they were; only `html_code`, `css_code`,
`generated_code`, and `engine` change. Do **not** rebuild `$merged` from
scratch from `$_POST` — that would null out fields the HTML/CSS/JS tabs
don't submit.

#### `draftPieceFromPost()` (~lines 212-225)

This function rebuilds `$piece` for re-rendering the form after a validation
error. Add the three code fields so a failed submission doesn't lose the
user's HTML/CSS/JS edits:

```php
$draft['current_version'] = array_merge(
    $existing['current_version'] ?? [],
    self::resolveVersionCodeFromPost()
);
```

(Adjust variable names to match the function's actual local variable for the
piece array being built — read the function body first; it currently returns
an array of `art_pieces` columns only. Add `current_version` as an extra key
so `form.php`'s `$piece['current_version']['html_code']` etc. still works on
re-render. `$existing` may not be in scope inside `draftPieceFromPost()` for
the `create` path — in that case use `[]` as the base instead of
`$existing['current_version'] ?? []`.)

No model changes are required for Phase A — `PlatformArtPieceVersion::create()`
and `::update()` already accept every field referenced above.

---

## Phase B — Media asset CRUD parity (`/admin/media`, `/admin/trash`)

`MediaAsset` (public/app/models/MediaAsset.php) already implements
`all()`, `find()`, `findByFilename()`, `create()`, `update()` (full-row),
`softDelete()`, `restore()`, `hardDelete()`, `trashed()`, `trashedCount()`.
Round 2 surfaced the 102 migrated assets in `/admin/media` but left them
read-only. This phase wires the existing model methods into the UI.

> Do not touch `SiteIdentityAdminController::mediaAssetDelete()`
> (route `/admin/site-identity/media/([0-9]+)/delete`, ~lines 52-58) — it is
> an existing, working soft-delete pathway used from `/admin/site-identity`
> and must keep working unchanged (Rule 5). This phase adds **new** routes
> under `/admin/media/asset/*` instead.

### B1. New model method — `MediaAsset::updateMetadata()`

File: `public/app/models/MediaAsset.php`. Add (near `update()`, ~line 62):

```php
public static function updateMetadata(int $id, ?string $title, ?string $altText): void
{
    $stmt = db()->prepare('UPDATE media_assets SET title = ?, alt_text = ? WHERE id = ?');
    $stmt->execute([$title, $altText, $id]);
}
```

Rationale: the existing `update()` (~lines 62-80) is a full-row UPDATE that
would null out `file_data`/`url`/`filename`/`mime_type`/`byte_size` if called
with only `title`/`alt_text` — this targeted method avoids that.

### B2. New routes — `public/app/router.php`

Add near the existing `/admin/media/*` routes (after
`['POST', '/admin/media/([0-9]+)/destroy', [MediaAdminController::class, 'destroy']],`):

```php
['POST', '/admin/media/asset/([0-9]+)/update',  [MediaAdminController::class, 'assetUpdate']],
['POST', '/admin/media/asset/([0-9]+)/trash',   [MediaAdminController::class, 'assetTrash']],
['POST', '/admin/media/asset/([0-9]+)/destroy', [MediaAdminController::class, 'assetDestroy']],
```

No restore route is needed here — restoring a trashed asset happens from
`/admin/trash` (Phase B's TrashController changes, below).

Match the router's existing array literal style (verify by viewing
neighboring lines — earlier routes confirmed use
`['METHOD', 'pattern', [Controller::class, 'method']]`).

### B3. Controller methods — `MediaAdminController`

File: `public/app/controllers/Admin/MediaController.php` (class name is
`MediaAdminController`). Add three new public static methods, following the
existing style of `trash()`/`destroy()` (~lines 139-153: `admin_check()`,
call model method, `header('Location: /admin/media'); exit;`):

```php
public static function assetUpdate(string $id): void
{
    admin_check();
    $title = trim((string) ($_POST['title'] ?? ''));
    $altText = trim((string) ($_POST['alt_text'] ?? ''));
    MediaAsset::updateMetadata(
        (int) $id,
        $title !== '' ? $title : null,
        $altText !== '' ? $altText : null
    );
    header('Location: /admin/media');
    exit;
}

public static function assetTrash(string $id): void
{
    admin_check();
    MediaAsset::softDelete((int) $id);
    header('Location: /admin/media');
    exit;
}

public static function assetDestroy(string $id): void
{
    admin_check();
    MediaAsset::hardDelete((int) $id);
    header('Location: /admin/media');
    exit;
}
```

### B4. `MediaAdminController::index()` — pass through asset id/title/alt_text

File: `public/app/controllers/Admin/MediaController.php`, `index()`
(~lines 7-41). The asset-normalization closure currently maps each
`MediaAsset::all()` row to something like:

```php
[
    'id' => 'asset-' . $id,
    'source' => 'asset',
    'mime_type' => $asset['mime_type'],
    'byte_size' => $asset['byte_size'],
    'created_at' => $asset['uploaded_at'],
    'preview' => '/api/media-assets/' . $id,
    'direct_url' => '/api/media-assets/' . $id,
    'label' => !empty($asset['title']) ? $asset['title'] : 'Media Asset #' . $id,
]
```

Add three keys so the view can render an editable form and target the
correct numeric id:

```php
'asset_id' => $id,                          // numeric id, for /admin/media/asset/{id}/...
'title' => $asset['title'] ?? '',
'alt_text' => $asset['alt_text'] ?? '',
```

(`$id` here is the existing `(int) $asset['id']` already in scope for the
closure — reuse it, do not recompute.)

### B5. `public/app/views/admin/media.php` — card markup + details panel

#### B5a. Card data attributes

In the card-rendering loop (renders `<button class="media-card" data-id="..."
data-source="..." data-preview="..." data-direct-url="..." data-mime="..."
data-date="..." data-size="...">`), for items where `$item['source'] ===
'asset'`, add:

```php
data-asset-id="<?= isset($item['asset_id']) ? (int) $item['asset_id'] : '' ?>"
data-title="<?= e($item['title'] ?? '') ?>"
data-alt-text="<?= e($item['alt_text'] ?? '') ?>"
```

(For `source === 'file'` cards, these three attributes can be omitted or left
empty — the JS branch for non-asset cards doesn't read them.)

#### B5b. Details panel HTML — add an asset-metadata mini-form

Inside `.media-details-panel`, near the existing `#action-trash-form` /
`#action-destroy-form` and the `#media-readonly-note` paragraph (~around the
`.media-details-actions` block), add a new form **before** the trash/destroy
forms:

```html
<form id="action-asset-update-form" method="post" class="media-asset-meta-form is-hidden">
  <div class="field">
    <label for="asset-title-input">Title</label>
    <input type="text" id="asset-title-input" name="title" maxlength="255">
  </div>
  <div class="field">
    <label for="asset-alt-input">Alt text</label>
    <input type="text" id="asset-alt-input" name="alt_text" maxlength="500">
  </div>
  <button type="submit" class="btn btn-secondary">Save metadata</button>
</form>
```

Remove (or simply leave dead/unused — but prefer removing) the old
`<p class="admin-hint is-hidden" id="media-readonly-note">Migrated asset —
managed from Site Identity, read-only here.</p>` paragraph, OR repurpose it:
since assets are no longer read-only, this note is no longer accurate. If you
keep the element for layout reasons, ensure the JS below never un-hides it.

#### B5c. JS — `selectCard(card)` rewrite

The existing `selectCard(card)` function (~lines 172-207) sets `id`, `mime`,
`date`, `source = card.dataset.source`, `assetUrl = card.dataset.directUrl`,
fills `inputUrl.value` / `inputHtml.value`, then branches on `source`. The
branch to replace (currently ~lines 193-203) looks like:

```js
if (source === 'asset') {
  trashForm.classList.add('is-hidden');
  destroyForm.classList.add('is-hidden');
  readonlyNote.classList.remove('is-hidden');
} else {
  readonlyNote.classList.add('is-hidden');
  trashForm.classList.remove('is-hidden');
  destroyForm.classList.remove('is-hidden');
  trashForm.action = `/admin/media/${id}/trash`;
  destroyForm.action = `/admin/media/${id}/destroy`;
}
```

(Exact current syntax may differ slightly — locate it via the
`source === 'asset'` string and `readonlyNote` identifier.)

Replace with (assumes `assetMetaForm`, `assetTitleInput`, `assetAltInput`
are looked up once at top of the script, the same way `trashForm` /
`destroyForm` already are — add those three `getElementById` lookups
alongside the existing ones):

```js
if (source === 'asset') {
  var assetId = card.dataset.assetId;
  assetMetaForm.classList.remove('is-hidden');
  assetMetaForm.action = `/admin/media/asset/${assetId}/update`;
  assetTitleInput.value = card.dataset.title || '';
  assetAltInput.value = card.dataset.altText || '';
  if (readonlyNote) readonlyNote.classList.add('is-hidden');
  trashForm.classList.remove('is-hidden');
  destroyForm.classList.remove('is-hidden');
  trashForm.action = `/admin/media/asset/${assetId}/trash`;
  destroyForm.action = `/admin/media/asset/${assetId}/destroy`;
  destroyForm.dataset.confirmExtra = ' This asset may be referenced by site settings, posts, or art pieces — broken links won\'t be auto-fixed.';
} else {
  assetMetaForm.classList.add('is-hidden');
  if (readonlyNote) readonlyNote.classList.add('is-hidden');
  trashForm.classList.remove('is-hidden');
  destroyForm.classList.remove('is-hidden');
  trashForm.action = `/admin/media/${id}/trash`;
  destroyForm.action = `/admin/media/${id}/destroy`;
  destroyForm.dataset.confirmExtra = '';
}
```

#### B5d. JS — destroy confirm dialog

Find the existing destroy-confirm handler (a `confirm(...)` call before
submitting `#action-destroy-form`, likely named something like "Delete Now"
per the `MediaFile` pattern). Append `destroyForm.dataset.confirmExtra`
(set above, empty string for native files) to the confirm message text, e.g.:

```js
var msg = 'Permanently delete this item? This cannot be undone.' + (destroyForm.dataset.confirmExtra || '');
if (!confirm(msg)) return false;
```

If the current handler hardcodes the message string, restructure minimally
to append the suffix rather than duplicating the whole dialog.

---

### B6. `TrashController` + `trash.php` — merge trashed media_assets into the "Media" tab

File: `public/app/controllers/Admin/TrashController.php`.

#### B6a. `index()` (~lines 7-18)

Current: `$mediaFiles = MediaFile::trashed();` (one of several arrays built
for the `$tabs` structure). Change to merge in trashed `media_assets`, each
tagged with `_type` and given a display `label`:

```php
$mediaFiles = array_merge(
    array_map(static function (array $row): array {
        $row['_type'] = 'media';
        $row['label'] = 'ID ' . (int) $row['id'] . ' · ' . (string) ($row['mime_type'] ?? '');
        return $row;
    }, MediaFile::trashed()),
    array_map(static function (array $row): array {
        $row['_type'] = 'media_asset';
        $row['label'] = !empty($row['title'])
            ? $row['title']
            : (!empty($row['filename']) ? $row['filename'] : ('Media Asset #' . (int) $row['id']));
        return $row;
    }, MediaAsset::trashed())
);
```

Keep the rest of `index()` (the `$tabs` array assembly, counts, etc.)
unchanged — `$mediaFiles` is already the variable plugged into
`'media' => ['label' => 'Media', 'items' => $mediaFiles]`.

> Note: `MediaFile::trashed()` rows may not currently have a `label` key —
> check whether `trash.php`'s media-tab column (~lines 64-83, "ID N + mime
> hint") already derives this inline. If so, either (a) keep that inline
> derivation working for `_type === 'media'` rows by leaving its existing
> column-rendering code path intact and only using the new `label` for
> `_type === 'media_asset'` rows, or (b) replace the inline derivation with
> `$item['label']` everywhere — pick whichever is the smaller diff once you
> see the actual markup. Functionally both must render the same text for
> `media_files` rows as before.

#### B6b. `restore()` (~lines 20-38) and `purge()` (~lines 40-58)

Both `match($type)` over a `type` POST field. Add a new arm to each:

```php
'media_asset' => MediaAsset::restore($id),   // in restore()
'media_asset' => MediaAsset::hardDelete($id), // in purge()
```

(`$id` is already cast to `int` earlier in each method — reuse it.)

#### B6c. `empty()` (~lines 60-100)

The `'media'` case currently does something like:

```php
case 'media':
    foreach (MediaFile::trashed() as $row) {
        MediaFile::hardDelete((int) $row['id']);
    }
    break;
```

Extend it to also purge trashed `media_assets`:

```php
case 'media':
    foreach (MediaFile::trashed() as $row) {
        MediaFile::hardDelete((int) $row['id']);
    }
    foreach (MediaAsset::trashed() as $row) {
        MediaAsset::hardDelete((int) $row['id']);
    }
    break;
```

#### B6d. `public/app/views/admin/trash.php`

The Restore form (~lines 89-93, `action="/admin/trash/restore"`) and "Delete
permanently" form (~lines 94-99, `action="/admin/trash/purge"`) currently use
a **tab-level** `$type` (derived via `match($tab)` ~lines 34-42, e.g.
`'media' => 'media'`) as their hidden `type` input for every row in that tab.

For the `'media'` tab specifically, change the hidden `type` input in **both**
forms to use the **per-item** `$item['_type']` (set in B6a: `'media'` or
`'media_asset'`) instead of the tab-level `$type`:

```php
<input type="hidden" name="type" value="<?= e($item['_type'] ?? $type) ?>">
```

(`?? $type` keeps the other 5 tabs — artworks/categories/exhibits/posts/
comments — working unchanged, since only `media`-tab items will have `_type`
set.)

Also update the label cell (~lines 64-83) for the `media` tab to render
`$item['label']` (already computed in B6a) instead of (or in addition to) the
current inline `ID <?= (int) $item['id'] ?>` + mime-hint markup — see the note
in B6a about picking the smaller diff.

---

## Verification

1. `php -l` on every changed `.php` file:
   - `public/app/controllers/Admin/PiecesAdminController.php`
   - `public/app/views/admin/pieces/form.php`
   - `public/app/views/admin/pieces/version-form.php`
   - `public/app/models/MediaAsset.php`
   - `public/app/controllers/Admin/MediaController.php`
   - `public/app/controllers/Admin/TrashController.php`
   - `public/app/views/admin/media.php`
   - `public/app/views/admin/trash.php`
   - `public/app/router.php`

2. Start the canonical local server:
   ```sh
   php -S 127.0.0.1:8080 -t public public/index.php
   ```

3. Authenticate as admin (use the existing session-cookie technique already
   established in this project — log in via `/admin/login` with the admin
   credentials and reuse the cookie jar for subsequent `curl` calls, or use a
   real browser).

4. Piece editing:
   - `GET /admin/pieces` — pick an existing p5 piece and, if any exist, a
     c2/three/svg piece. `GET /admin/pieces/{id}/edit` for each.
   - Confirm all 4 tabs render (Metadata default-active; HTML/CSS/JS hidden
     until clicked) and that HTML/CSS/JS textareas are pre-filled from
     `current_version`.
   - Confirm the Engine `<select>` shows the **correct** selected option
     (especially for c2/three/svg pieces — this is the bug-fix check).
   - Edit the CSS tab's textarea content, submit. Re-`GET` the edit page:
     confirm the CSS change persisted AND that `prompt`,
     `generation_vendor`, `generation_model`, `validation_status`,
     `generation_attempt_count`, `notes` on the current version are
     **unchanged** (merge-not-clobber check — compare
     `/api/art-pieces/{id}/versions` JSON before/after if easier than
     reading the DB directly).
   - `GET /admin/pieces/create`, fill in Title + HTML/CSS/JS tabs, submit.
     Confirm: a new piece row exists, a version-1 row was created for it,
     `current_version_id` points at that version, and the new piece's edit
     page preview pane renders the submitted HTML/CSS/JS.

5. Media asset CRUD:
   - `GET /admin/media`. Click a card with `data-source="asset"`.
   - Confirm the metadata mini-form shows (with existing title/alt_text
     pre-filled if present), edit and Save — confirm
     `POST /admin/media/asset/{id}/update` succeeds and the new
     title/alt_text persist (re-select the card after redirect).
   - Trash it (`POST /admin/media/asset/{id}/trash`) — confirm it disappears
     from `/admin/media` and appears under `/admin/trash` (Media tab) with
     `_type = media_asset` and a sensible `label`.
   - Restore it from `/admin/trash` — confirm it reappears in `/admin/media`.
   - Trash + Destroy a **different** asset (`POST
     /admin/media/asset/{id}/destroy`) — confirm `MediaAsset::find($id)` now
     returns `false` and the row is gone from both `/admin/media` and
     `/admin/trash`.
   - `/admin/trash?tab=media`, "Empty this tab" — confirm it purges both
     file-sourced and asset-sourced rows (create a throwaway trashed asset
     first if none remain, to avoid purging anything load-bearing).

6. Regression: confirm `/admin/site-identity` still works and its own
   "delete media asset" action (`/admin/site-identity/media/{id}/delete`,
   `SiteIdentityAdminController::mediaAssetDelete`) is untouched and still
   functions (soft-delete only).

7. Re-run the deletion-readiness check:
   ```sh
   php scripts/check-platform-deletion-readiness.php --base-url=http://127.0.0.1:8080
   ```
   Confirm it still passes.

8. Documentation updates (Pre-Write Check / AGENTS.md compliance):
   - `docs/platform-route-matrix.md`: note that piece editing now supports
     all 4 engines (`p5|c2|three|svg`) via the Metadata/HTML/CSS/JS tabs, and
     that `/admin/media` + `/admin/trash` now have full CRUD parity for
     migrated `media_assets` (new routes
     `/admin/media/asset/{id}/update|trash|destroy`).
   - `docs/platform-assimilation-plan.md`: add a short "Round 3 — complete"
     entry summarizing Phases A and B.
   - This round adds **no** new `/api/*` endpoints and **no** new external
     vendor dependencies, so `docs/api.md` and `docs/dependencies.md` do not
     need changes.
   - `DECISIONS.md`: append a dated entry recording Round 3's completion
     (engine-whitelist bug fixed, piece-edit tabs added, media-asset CRUD
     wired into `/admin/media` and `/admin/trash`).
   - `MEMORY.md`: per AGENTS.md's end-of-session protocol, propose 1-3 new
     entries summarizing what changed (do not write without the usual
     confirmation step if a human is present for that session; if running
     unattended per SESSION CONSTRAINTS, log as a NOTE/DECISION instead).

---

## Forward pointers (Rounds 4 and 5)

- **Round 4** (`docs/round-4-ai-piece-generation.md`): AI-driven piece
  generation with vendor-profile selection, reusing the same HTML/CSS/JS tab
  UI built in Phase A above as the **preview-before-save** surface for
  generated drafts.
- **Round 5** (`docs/round-5-immersive-gallery.md`): immersive/VR gallery
  overhaul. Independent of this round's changes; both can land in either
  order, but Round 5 reuses `current_version` data shapes confirmed here.
