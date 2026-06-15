# Round 4 — AI-Driven Piece Generation (Vendor Profiles + Draft Preview)

> **Status**: Done. Implemented in the PHP app; retained here as the
> implementation plan and audit trail. Self-contained for independent
> execution. **Depends on Round 3 Phase A** (`docs/round-3-piece-edit-and-media-crud.md`)
> being implemented first — this round's "Save and Insert" reuses the
> Metadata/HTML/CSS/JS tab UI and the `resolveVersionCodeFromPost()` /
> merge-on-update logic added there. If Round 3 hasn't landed yet, implement
> it first.

## Context

The platform lets a user generate a new art piece (or a new version of an
existing one) by picking a saved AI **vendor profile** (`user_ai_vendor_settings`
row — vendor + model + endpoint kind), entering a prompt and an engine
(`p5|c2|three|svg`), and getting back a validated HTML/CSS/JS draft shown in
a Preview/HTML/CSS/JS tabbed dialog (`ArtPieceDraftDialog.tsx`) before
"Save and Insert" persists it. The PHP port currently has **no** generation
UI at all — pieces can only be created/edited by hand-typing code (even
after Round 3).

**User's explicit decision (2026-06-14, captured verbatim)**: *"Yes, proceed
and document dependencies. However, note that this functionality is already
built out in the platform app. Use that as the template, translate it into
PHP, and implement accordingly to the best of your abilities."*

This activates a **live external AI-vendor dependency** — Rule 6's mandatory
question has already been answered ("proceed") by the user, but
`docs/dependencies.md` must still be updated (Pre-Write Check item 3, "calls
an external service → update docs/dependencies.md first").

### Standing constraints

- Never write to `PLATFORM_*` (source) DB.
- `php -l` every changed/new `.php` file.
- Update `docs/dependencies.md` **before** the new vendor-calling code is
  exercised.
- No silent fallbacks: if a vendor/transport isn't ported, fail with a clear
  error (Rule 6).

---

## Source of truth (platform files — read these directly; do not rely on
## transcriptions below for anything not explicitly quoted)

| What | File | Lines |
|---|---|---|
| Request schema (`prompt`, `engine`, `profileId`) | `platform/artifacts/api-server/src/routes/art-pieces.ts` | ~60-64 (`GenerateArtPieceBody`) |
| Vendor enum | same file | ~53 (`aiVendorSchema`) |
| Generation/validation/retry loop | same file | ~263-378 (`generateValidatedDraft`) — **read this function in full**, it is the master reference for the PHP `generate()` controller method below |
| Attempt limit / timeout constants | `platform/artifacts/api-server/src/lib/art-pieces.ts` | 25-26 (`MAX_ART_PIECE_ATTEMPTS = 5`, `ART_PIECE_TIMEOUT_MS = 1_200_000`) |
| `getArtPieceGenerationLimits()` | same | 416-421 |
| Engine system prompts + preflight | same | 423-514 (`ENGINE_ADAPTERS`: `p5` 426-443, `c2` 446-468, `three` 471-490, `svg` 493-514) |
| `extractCodeBlocks()` | same | 519-537 — **quoted in full below** |
| `preflightCompiledArtPieceCode()` | same | 626-631 (delegates to `getEngineAdapter(engine).preflight`) |
| `buildArtPieceRepairPrompt()` | same | 633-650 — **quoted in full below** |
| HTTP transport dispatch | `platform/artifacts/api-server/src/lib/ai-providers.ts` | `processTextWithProvider` 102-155, `getTransportAttempts` 332-372, vendor helpers 374-500, request-building switch ~228-330, response extraction 900-1011 |
| Vendor profile schema (already migrated) | `platform/lib/db/src/schema/user-ai-settings.ts` | full file, 1-58 |
| Draft preview UI reference | `platform/artifacts/microblog/src/components/post/ArtPieceDraftDialog.tsx` | ~54-108 |

### `extractCodeBlocks()` (port this almost verbatim)

```ts
export function extractCodeBlocks(raw: string): { htmlCode: string | null; cssCode: string | null; generatedCode: string } {
  const extract = (langs: string[]) => {
    for (const lang of langs) {
      const match = raw.match(new RegExp("```" + lang + "\\s*([\\s\\S]*?)```", "i"));
      if (match) return match[1]!.trim();
    }
    return null;
  };

  const htmlCode = extract(["html"]);
  const cssCode = extract(["css"]);
  const generatedCode = extract(["javascript", "js", "javascript"]);

  if (!generatedCode) {
    throw new Error("AI response did not contain a ```javascript code block");
  }

  return { htmlCode, cssCode, generatedCode };
}
```

PHP equivalent (`art_piece_extract_code_blocks(string $raw): array`):

```php
function art_piece_extract_code_blocks(string $raw): array
{
    $extract = static function (array $langs) use ($raw): ?string {
        foreach ($langs as $lang) {
            if (preg_match('/```' . preg_quote($lang, '/') . '\s*([\s\S]*?)```/i', $raw, $m)) {
                return trim($m[1]);
            }
        }
        return null;
    };

    $htmlCode = $extract(['html']);
    $cssCode = $extract(['css']);
    $generatedCode = $extract(['javascript', 'js']);

    if ($generatedCode === null) {
        throw new RuntimeException('AI response did not contain a ```javascript code block');
    }

    return ['htmlCode' => $htmlCode, 'cssCode' => $cssCode, 'generatedCode' => $generatedCode];
}
```

### `buildArtPieceRepairPrompt()` (port verbatim)

```ts
export function buildArtPieceRepairPrompt(input: {
  engine: ArtPieceEngine;
  originalPrompt: string;
  previousRawResponse?: string | null;
  failureMessage: string;
}): string {
  const segments = [
    `Target engine: ${input.engine}`,
    `Original prompt: ${input.originalPrompt}`,
    `The previous art-piece attempt failed validation: ${input.failureMessage}`,
    "Return a corrected response that fixes the error while staying visually faithful to the original prompt. Provide the HTML, CSS, and JS in Markdown code blocks.",
    "CRITICAL: Animations MUST be infinite. They must loop, reset their state, or pulsate continuously. Never allow the piece to end on a blank screen or permanently destroy all elements.",
  ];
  if (input.previousRawResponse) {
    segments.push(`Previous invalid response: ${input.previousRawResponse}`);
  }
  return segments.join("\n\n");
}
```

PHP equivalent:

```php
function art_piece_repair_prompt(string $engine, string $originalPrompt, ?string $previousRawResponse, string $failureMessage): string
{
    $segments = [
        "Target engine: {$engine}",
        "Original prompt: {$originalPrompt}",
        "The previous art-piece attempt failed validation: {$failureMessage}",
        "Return a corrected response that fixes the error while staying visually faithful to the original prompt. Provide the HTML, CSS, and JS in Markdown code blocks.",
        "CRITICAL: Animations MUST be infinite. They must loop, reset their state, or pulsate continuously. Never allow the piece to end on a blank screen or permanently destroy all elements.",
    ];
    if ($previousRawResponse !== null && $previousRawResponse !== '') {
        $segments[] = "Previous invalid response: {$previousRawResponse}";
    }
    return implode("\n\n", $segments);
}
```

### `generateValidatedDraft()` defaults block (port verbatim)

After `extractCodeBlocks` succeeds (or the SVG CSS-only fallback applies —
see the route file's catch block around the `extractCodeBlocks` call, which
special-cases `engine === "svg"` responses that have no JS block), the route
applies these defaults if `htmlCode`/`cssCode` came back null:

```ts
if (!htmlCode) {
  if (input.engine === "svg") {
    htmlCode = '<svg viewBox="0 0 800 600" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%"></svg>';
  } else {
    htmlCode = input.engine === "p5" ? '<div id="canvas-container"></div>' : '<div id="container"></div>';
  }
}
if (!cssCode) {
  cssCode = "body, html { margin: 0; padding: 0; width: 100%; height: 100%; overflow: hidden; }";
}
```

Port this exactly into the PHP `generate()` loop, including the SVG-no-JS
fallback (read the route file's full catch block around `extractCodeBlocks`
for the exact regex/condition used — it checks
`extractError.message.includes("javascript code block")` and `engine ===
"svg"`, then extracts `html`/`css` blocks and sets
`rawJsCode = "window.sketch = () => {};"`).

---

## Current PHP state (what already exists, reuse as-is)

- `UserAiVendorSettings` / `UserAiVendorKeys` models —
  `public/app/models/UserAiSettings.php`. `UserAiVendorSettings::all()`
  returns every profile across users (joined with `users.name AS
  user_name`), already used by `/admin/user-profiles`' "AI Settings" tab.
  `UserAiVendorKeys::all()` similarly. Both tables are already migrated
  (7 rows confirmed in the deletion-readiness report) and confirmed
  decryptable with the existing PHP encryption key.
- `public/app/helpers/encryption.php` — AES-256-GCM helper, same key used
  for `user_ai_vendor_keys.encrypted_api_key` and syndication tokens. Find
  and reuse its existing decrypt function (grep for how
  `PlatformConnectionsAdminController` or `UserProfilesAdminController`
  currently decrypts a stored key — match that exact call signature).
- `guzzlehttp/guzzle` — already a composer dependency (used by all 8
  syndication adapters in `public/app/lib/syndication/`). Reuse for AI HTTP
  calls; do not add a new HTTP client dependency.
- Round 3 Phase A — `pieces/form.php`'s 4-tab UI, and
  `PiecesAdminController::resolveVersionCodeFromPost()` /
  `hasAnyVersionCode()` / the `update()`/`store()` merge logic. This round's
  "Save and Insert" should funnel into the **same create/version-1 code path**
  Phase A built (so a generated-then-saved piece is indistinguishable from a
  hand-typed one with code filled in).

---

## New model method

File: `public/app/models/UserAiSettings.php`, class `UserAiVendorKeys`. Add:

```php
public static function findForUserVendor(string $userId, string $vendor): array|false
{
    if (!self::tableExists()) {
        return false;
    }

    $stmt = db()->prepare(
        'SELECT * FROM user_ai_vendor_keys WHERE user_id = ? AND vendor = ? LIMIT 1'
    );
    $stmt->execute([$userId, $vendor]);
    return $stmt->fetch() ?: false;
}
```

(`tableExists()` is a private static method on the same class — `self::` works
since this is added inside the class body.)

---

## New helper file — `public/app/helpers/art-piece-generation.php`

Register it the same way other helpers are loaded (check
`public/index.php` or `public/app/router.php` for the existing
`require`/autoload pattern used for `piece-render.php` / `feed-ingest.php`
and follow it).

Contents:

```php
<?php

declare(strict_types=1);

const ART_PIECE_MAX_ATTEMPTS = 3; // platform uses 5; see "Attempts/timeouts" note below
const ART_PIECE_ATTEMPT_TIMEOUT = 60; // seconds, Guzzle per-request timeout

function art_piece_generation_system_prompt(string $engine): string
{
    // Port ENGINE_ADAPTERS[$engine].systemPrompt from
    // platform/artifacts/api-server/src/lib/art-pieces.ts (lines 423-514).
    // Copy each engine's systemPrompt array verbatim and join exactly as
    // the TS does at its use site (check whether array entries are joined
    // with "\n\n", "\n", or string-concatenated — match it).
    return match ($engine) {
        'p5' => /* ... ported from ENGINE_ADAPTERS.p5.systemPrompt (lines 426-443) ... */ '',
        'c2' => /* ... ported from ENGINE_ADAPTERS.c2.systemPrompt (lines 446-468) ... */ '',
        'three' => /* ... ported from ENGINE_ADAPTERS.three.systemPrompt (lines 471-490) ... */ '',
        'svg' => /* ... ported from ENGINE_ADAPTERS.svg.systemPrompt (lines 493-514) ... */ '',
        default => throw new InvalidArgumentException("Unknown engine: {$engine}"),
    };
}

function art_piece_extract_code_blocks(string $raw): array
{
    // See "extractCodeBlocks()" section above — implement exactly as shown there.
}

function art_piece_preflight_code(string $engine, string $code): string
{
    // Port getEngineAdapter(engine).preflight(code) for each engine
    // (search art-pieces.ts for "preflight:" inside each ENGINE_ADAPTERS
    // entry). These are static checks (regex/string inspection) — PHP
    // cannot execute the JS to truly validate it. Port whatever static
    // checks the TS performs. If a given adapter's preflight does something
    // that requires a JS runtime (e.g. actually executing the sketch),
    // document that gap explicitly in this function's docblock and in
    // DECISIONS.md rather than silently skipping validation.
    return $code; // replace with ported logic; return value mirrors TS (possibly normalized code)
}

function art_piece_repair_prompt(string $engine, string $originalPrompt, ?string $previousRawResponse, string $failureMessage): string
{
    // See "buildArtPieceRepairPrompt()" section above.
}
```

---

## New AI provider client — `public/app/lib/ai/AiProviderClient.php`

```php
<?php

declare(strict_types=1);

namespace App\Lib\Ai;

use GuzzleHttp\Client;
use RuntimeException;

class AiProviderClient
{
    public function __construct(private Client $http = new Client())
    {
    }

    /**
     * @param array $profile  row from user_ai_vendor_settings (vendor, model, endpoint_kind, ...)
     * @param string $apiKey  decrypted API key
     */
    public function generate(array $profile, string $apiKey, string $systemPrompt, string $userPrompt): string
    {
        $vendor = $profile['vendor'];
        $model = $profile['model'];
        $endpointKind = $profile['endpoint_kind'] ?? null;

        return match (true) {
            in_array($vendor, ['openrouter', 'deepseek', 'mistral', 'mistral-vibe', 'opencode-zen', 'opencode-go'], true)
                => $this->chatCompletions($vendor, $model, $endpointKind, $apiKey, $systemPrompt, $userPrompt),
            $vendor === 'google'
                => $this->googleGenerateContent($model, $apiKey, $systemPrompt, $userPrompt),
            default => throw new RuntimeException("AI vendor '{$vendor}' is not yet supported by this PHP port."),
        };
    }

    private function chatCompletions(string $vendor, string $model, ?string $endpointKind, string $apiKey, string $systemPrompt, string $userPrompt): string
    {
        // Port getTransportAttempts() (ai-providers.ts:332-372) +
        // getOpencodeZenTransportAttempt() (374-411) +
        // getOpencodeGoTransportAttempt() (434-459) +
        // normalizeModelForProvider() (463-471)
        // to determine $baseUrl and $normalizedModel for (vendor, model, endpointKind).
        [$baseUrl, $normalizedModel] = $this->resolveChatCompletionsEndpoint($vendor, $model, $endpointKind);

        $response = $this->http->post($baseUrl . '/chat/completions', [
            'timeout' => ART_PIECE_ATTEMPT_TIMEOUT,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $normalizedModel,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'max_tokens' => 12000,
            ],
        ]);

        $payload = json_decode((string) $response->getBody(), true);
        // Port extractChatCompletionText() (ai-providers.ts:928-958).
        $text = $payload['choices'][0]['message']['content'] ?? null;
        if (!is_string($text) || $text === '') {
            throw new RuntimeException('AI provider returned an empty response.');
        }
        return $text;
    }

    private function googleGenerateContent(string $model, string $apiKey, string $systemPrompt, string $userPrompt): string
    {
        $response = $this->http->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent",
            [
                'timeout' => ART_PIECE_ATTEMPT_TIMEOUT,
                'query' => ['key' => $apiKey],
                'json' => [
                    'contents' => [
                        ['role' => 'user', 'parts' => [['text' => $userPrompt]]],
                    ],
                    'systemInstruction' => [
                        'parts' => [['text' => $systemPrompt]],
                    ],
                ],
            ]
        );

        $payload = json_decode((string) $response->getBody(), true);
        // Port extractGoogleText() (ai-providers.ts:979-1011).
        $parts = $payload['candidates'][0]['content']['parts'] ?? [];
        $text = implode('', array_column($parts, 'text'));
        if ($text === '') {
            throw new RuntimeException('AI provider returned an empty response.');
        }
        return $text;
    }

    private function resolveChatCompletionsEndpoint(string $vendor, string $model, ?string $endpointKind): array
    {
        // Port from ai-providers.ts lines 332-471. Each vendor has a fixed
        // base URL (e.g. OpenRouter: https://openrouter.ai/api/v1;
        // DeepSeek: https://api.deepseek.com; Mistral: https://api.mistral.ai/v1;
        // opencode-zen/opencode-go: vendor-hosted gateways — read the exact
        // URLs from ai-providers.ts, do not guess). $model may need
        // normalization (e.g. stripping a vendor prefix) per
        // normalizeModelForProvider() (463-471).
        throw new RuntimeException('resolveChatCompletionsEndpoint: implement per ai-providers.ts:332-471');
    }
}
```

Notes:
- The `match (true)` dispatch + `resolveChatCompletionsEndpoint()` stub is
  the **one piece of this round that requires careful, line-by-line porting**
  from `ai-providers.ts`. Everything else in this doc is either a small,
  fully-specified PHP function or new UI/controller code original to this
  port.
- If, after reading `ai-providers.ts:332-471`, any of the 7 vendors actually
  requires `anthropic-messages` or `openai-responses` transport (not
  `chat-completions`), add a corresponding private method
  (`anthropicMessages()` / `openAiResponses()`) following the same pattern,
  porting the relevant request-building branch (~228-330) and response
  extractor (`extractAnthropicText` 959-978 / `extractOpenAiResponsesText`
  900-927).
- Throwing `RuntimeException` for unsupported vendors (rather than falling
  back to a different vendor or returning empty) satisfies Rule 6 ("no
  silent workarounds").

---

## New routes — `public/app/router.php`

Add near the existing `/admin/pieces/*` routes:

```php
['GET',  '/admin/pieces/generate',         [PiecesAdminController::class, 'generateForm']],
['POST', '/admin/pieces/generate',         [PiecesAdminController::class, 'generate']],
['POST', '/admin/pieces/generate/save',    [PiecesAdminController::class, 'generateSave']],
```

All `admin_check()`-gated inside the controller methods (matching the
existing convention for `/admin/pieces/*`).

---

## New controller methods — `PiecesAdminController`

### `generateForm(): void`

```php
public static function generateForm(): void
{
    admin_check();

    $profiles = array_filter(
        UserAiVendorSettings::all(),
        static fn (array $row): bool => (int) $row['enabled'] === 1
    );

    $pageTitle = 'Generate Piece — Augment Humankind Admin';
    ob_start();
    // .admin-container > .admin-header-row (h1 "Generate Piece" + Back to /admin/pieces)
    // error block if $error is set (passed via redirect-with-flash or re-render)
    // <form method="post" class="admin-form" action="/admin/pieces/generate">
    //   prompt textarea (name="prompt", maxlength 4000, required)
    //   engine <select name="engine"> options: p5/c2/three/svg (P5.js/C2.js/Three.js/SVG)
    //   profile <select name="profile_id"> options: for each $profiles row,
    //     value=row.id, label="{profile_name} — {vendor} ({model}) [{user_name}]"
    //     (empty-state message + disabled submit if $profiles is empty:
    //      "No enabled AI vendor profiles. Add one in /admin/user-profiles.")
    //   .form-actions: "Generate" submit button + Cancel link to /admin/pieces
    // </form>
    $content = ob_get_clean();
    require dirname(__DIR__, 2) . '/views/admin/layout.php'; // match existing path depth used by pieces views
}
```

Use the same `.admin-container` / `.admin-form` / `.field` / `.form-status`
CSS vocabulary as `pieces/form.php` (Round 2 added these classes to
`admin.css` — no new CSS should be needed for this form).

### `generate(): void`

```php
public static function generate(): void
{
    admin_check();
    set_time_limit(180);

    $prompt = trim((string) ($_POST['prompt'] ?? ''));
    $engine = $_POST['engine'] ?? 'p5';
    $profileId = (int) ($_POST['profile_id'] ?? 0);

    // Validate: prompt 1-4000 chars, engine in [p5,c2,three,svg], profile exists & enabled.
    // On validation failure: re-render generateForm()'s view with $error set
    // and the submitted values preserved (mirror draftPieceFromPost()'s
    // re-render pattern from Round 3).

    $profile = UserAiVendorSettings::find($profileId);
    // ... validate $profile exists, enabled == 1 ...

    $key = UserAiVendorKeys::findForUserVendor($profile['user_id'], $profile['vendor']);
    if ($key === false) {
        // re-render form with $error = "No API key configured for vendor
        // '{$profile['vendor']}'. Add one in /admin/user-profiles."
    }

    $apiKey = /* decrypt $key['encrypted_api_key'] using the existing encryption.php helper */;

    $client = new \App\Lib\Ai\AiProviderClient();
    $previousRawResponse = null;
    $failureMessage = 'The previous attempt did not pass validation.';
    $draft = null;

    for ($attempt = 1; $attempt <= ART_PIECE_MAX_ATTEMPTS; $attempt++) {
        $userPrompt = $attempt === 1
            ? $prompt
            : art_piece_repair_prompt($engine, $prompt, $previousRawResponse, $failureMessage);

        try {
            $responseText = $client->generate($profile, $apiKey, art_piece_generation_system_prompt($engine), $userPrompt);
            $previousRawResponse = $responseText;

            try {
                $blocks = art_piece_extract_code_blocks($responseText);
            } catch (\RuntimeException $e) {
                if ($engine === 'svg' && str_contains($e->getMessage(), 'javascript code block')) {
                    // Port the SVG CSS-only fallback from the route file's
                    // catch block: extract html/css blocks only, set
                    // generatedCode = "window.sketch = () => {};"
                    $blocks = [
                        'htmlCode' => /* extract html block or null */,
                        'cssCode' => /* extract css block or null */,
                        'generatedCode' => 'window.sketch = () => {};',
                    ];
                } else {
                    throw $e;
                }
            }

            $htmlCode = $blocks['htmlCode'];
            $cssCode = $blocks['cssCode'];
            // Apply the defaults block from generateValidatedDraft (quoted above).
            if ($htmlCode === null) {
                $htmlCode = $engine === 'svg'
                    ? '<svg viewBox="0 0 800 600" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%"></svg>'
                    : ($engine === 'p5' ? '<div id="canvas-container"></div>' : '<div id="container"></div>');
            }
            if ($cssCode === null) {
                $cssCode = 'body, html { margin: 0; padding: 0; width: 100%; height: 100%; overflow: hidden; }';
            }

            $generatedCode = art_piece_preflight_code($engine, $blocks['generatedCode']);

            $draft = [
                'title' => mb_substr($prompt, 0, 80),
                'prompt' => $prompt,
                'engine' => $engine,
                'html_code' => $htmlCode,
                'css_code' => $cssCode,
                'generated_code' => $generatedCode,
                'generation_vendor' => $profile['vendor'],
                'generation_model' => $profile['model'],
                'generation_attempt_count' => $attempt,
                'validation_status' => 'validated',
            ];
            break;
        } catch (\Throwable $e) {
            $failureMessage = $e->getMessage();
            // loop continues; on final attempt, fall through with $draft === null
        }
    }

    if ($draft === null) {
        // re-render generateForm()'s view with
        // $error = "Generation failed after " . ART_PIECE_MAX_ATTEMPTS . " attempts: {$failureMessage}"
        // and submitted prompt/engine/profile_id preserved.
        return;
    }

    // Render generate-preview.php with $draft (see below).
}
```

### `generateSave(): void`

```php
public static function generateSave(): void
{
    admin_check();

    $title = trim((string) ($_POST['title'] ?? ''));
    $prompt = trim((string) ($_POST['prompt'] ?? ''));
    $engine = $_POST['engine'] ?? 'p5';
    $code = self::resolveVersionCodeFromPost(); // from Round 3 Phase A

    // Validate title non-empty (fallback to mb_substr($prompt, 0, 80) if blank), engine valid.

    $pieceId = PlatformArtPiece::create([
        'title' => $title !== '' ? $title : mb_substr($prompt, 0, 80),
        'prompt' => $prompt,
        'engine' => $engine,
        'status' => 'active',
        'thumbnail_url' => null,
        'description' => null,
    ]);

    $versionId = PlatformArtPieceVersion::create([
        'art_piece_id' => $pieceId,
        'version_number' => 1,
        'prompt' => $prompt,
        'structured_spec' => null,
        'html_code' => $code['html_code'],
        'css_code' => $code['css_code'],
        'generated_code' => $code['generated_code'] ?? '',
        'engine' => $engine,
        'generation_vendor' => $_POST['generation_vendor'] ?? null,
        'generation_model' => $_POST['generation_model'] ?? null,
        'validation_status' => 'validated',
        'generation_attempt_count' => (int) ($_POST['generation_attempt_count'] ?? 1),
        'notes' => null,
    ]);

    PlatformArtPiece::updateCurrentVersion($pieceId, $versionId);

    header('Location: /admin/pieces/' . $pieceId . '/edit');
    exit;
}
```

This reuses Round 3's `resolveVersionCodeFromPost()` and the exact
"create version 1 + set current_version_id" sequence from Round 3's
`store()` — keeping the two flows (hand-typed vs. AI-generated) consistent.

---

## New view — `public/app/views/admin/pieces/generate-preview.php`

Structure: same `.admin-container` shell as `pieces/form.php`, with the
**same 4-tab layout** built in Round 3 Phase A (`.admin-tabs` with
Metadata/HTML/CSS/JS, `.piece-tab-panel` divs, same tab-toggle `<script>`).
Differences from the edit form:

- Metadata tab: `title` input pre-filled with `mb_substr($draft['prompt'], 0, 80)`
  (editable), a read-only summary line "Generated with
  {generation_vendor} / {generation_model}, attempt
  {generation_attempt_count}", `engine` shown as a **read-only** label (not a
  `<select>` — the engine was fixed at generation time) plus a hidden
  `<input type="hidden" name="engine" value="...">`.
- HTML/CSS/JS tabs: same `<textarea name="html_code|css_code|generated_code">`
  as Round 3, pre-filled from `$draft`, editable.
- Hidden fields carried through to `/admin/pieces/generate/save`: `prompt`,
  `generation_vendor`, `generation_model`, `generation_attempt_count`.
- `.form-actions`: "Save and Insert" submit button (form `action="/admin/pieces/generate/save"`,
  `method="post"`), and a "Discard / Try Again" link back to
  `/admin/pieces/generate`.
- Optionally add a live preview pane (reuse `piece_render_iframe()` with a
  synthetic `$piece`/`$version` array built from `$draft` — `$version` needs
  `engine`, `html_code`, `css_code`, `generated_code` keys; `$piece` needs at
  least `id` — use `0` or omit if `piece_render_iframe` tolerates it, check
  its signature in `public/app/helpers/piece-render.php` first).

---

## Why no "draft token"

The platform issues a server-signed `draftToken` (`issueValidatedDraftToken`/
`consumeValidatedDraftToken`) because its API is stateless (separate
generate-request and save-request, potentially different processes) and the
SPA holds the draft client-side in between. In this PHP app, `generate()` can
render `generate-preview.php` directly in the same response, with the full
draft (including code strings) carried as form fields to
`/admin/pieces/generate/save` in the **next** request — no signing/session
storage needed. This is a deliberate simplification; note it in the
DECISIONS.md entry for this round as intentional, not a missing feature.

---

## Attempts/timeouts note

Platform: `MAX_ART_PIECE_ATTEMPTS = 5`, `ART_PIECE_TIMEOUT_MS = 1_200_000`
(20 minutes total) — designed for a long-lived Node process with streaming
progress to the SPA. A synchronous PHP request cannot reasonably hold open
for 20 minutes. This doc specifies `ART_PIECE_MAX_ATTEMPTS = 3` and a 60s
per-attempt Guzzle timeout (`ART_PIECE_ATTEMPT_TIMEOUT`), with
`set_time_limit(180)` in `generate()`. Document this as a deliberate
PHP-appropriate adjustment in both `docs/dependencies.md` and the
DECISIONS.md entry for this round — it changes *how many times* a bad
generation is retried, not *whether* validation happens.

---

## `docs/dependencies.md` entry (write before first live call)

Add an entry following the existing format in that file, covering:

> **AI piece generation (multi-vendor)** — `/admin/pieces/generate` sends the
> admin's prompt text to the AI vendor configured in the selected
> `user_ai_vendor_settings` profile (OpenRouter, OpenCode Zen, OpenCode Go,
> Google Gemini, Mistral, Mistral Vibe, or DeepSeek, per `vendor` column).
> Vendor profiles and API keys were migrated from the platform database and
> are already present/decryptable. If a vendor changes its API, pricing, or
> shuts down: generation via that profile fails (other profiles unaffected;
> already-saved pieces/versions are stored locally and unaffected). The
> self-hosting alternative: none provided by this round — a future
> `AiProviderClient` branch could target a self-hosted model (e.g. Ollama) as
> an additional vendor. User sign-off: 2026-06-14 ("Yes, proceed and document
> dependencies... implement accordingly to the best of your abilities").

---

## Verification

1. `php -l` on every new/changed file:
   - `public/app/helpers/art-piece-generation.php`
   - `public/app/lib/ai/AiProviderClient.php`
   - `public/app/models/UserAiSettings.php`
   - `public/app/controllers/Admin/PiecesAdminController.php`
   - `public/app/views/admin/pieces/generate-form.php`
   - `public/app/views/admin/pieces/generate-preview.php`
   - `public/app/router.php`

2. `php -S 127.0.0.1:8080 -t public public/index.php`, authenticate as admin.

3. `GET /admin/pieces` → "Generate" link/button → `/admin/pieces/generate`.
   Confirm the form lists at least one enabled vendor profile (7 were
   migrated per the deletion-readiness report — confirm at least one has
   `enabled = 1`; if none do, enable one via `/admin/user-profiles` first,
   noting this is a local DB write, not a `PLATFORM_*` write, so it's fine).

4. Submit a short prompt with `engine = p5`. Confirm:
   - A live HTTP call goes out to the configured vendor (check for network
     errors / auth errors first — these surface real account/API-key issues,
     not bugs in this port).
   - On success, `generate-preview.php` renders with non-empty `generated_code`
     and the same 4-tab layout as the edit form.
   - On failure after `ART_PIECE_MAX_ATTEMPTS` attempts, `generate-form.php`
     re-renders with a clear error message (no fatal error / stack trace).

5. From the preview, optionally edit the CSS tab, then "Save and Insert".
   Confirm redirect to `/admin/pieces/{id}/edit`, all 4 tabs show the
   generated (and any edited) code, and the new `art_piece_versions` row has
   `generation_vendor`, `generation_model`, `validation_status = 'validated'`,
   `generation_attempt_count` populated correctly.

6. Re-run `php scripts/check-platform-deletion-readiness.php
   --base-url=http://127.0.0.1:8080` — confirm still passes.

7. `DECISIONS.md` + `MEMORY.md`: log Round 4 completion, the draft-token
   simplification, and the attempt/timeout deviation from the platform's
   defaults.

---

## Forward pointer

Round 5 (`docs/round-5-immersive-gallery.md`) is independent of this round
and can be implemented before or after it.
