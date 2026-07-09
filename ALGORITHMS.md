# ALGORITHMS.md

> **Analytical framework source:** This document's analysis is guided by
> "Characteristics of an Algorithm," Naukri Code360 Library,
> <https://www.naukri.com/code360/library/characteristics-of-an-algorithm>.
> Each algorithm below is assessed against the characteristics that article
> defines: **well-defined inputs**, **well-defined outputs**, **unambiguity**,
> **finiteness**, **language independence**, and **effectiveness/feasibility**,
> and classified using its taxonomy (brute-force, searching, sorting,
> recursive, greedy, etc.).

This file catalogs the algorithms that support each functional area of the CMS. An algorithm here means the underlying logic — expressible as
pseudo-code or a flowchart — not the PHP/JS code that happens to implement it.
Complexity is given in big-O where meaningful (n = size of the relevant input).

Each functional section ends with one or more **Recipe** subsections — a
labeled pseudocode trace of the data pipeline — each followed by sentence instruction versions of the algorithmic recipe, an AI-generated diagram ([Diagram-Creation Thread](https://chatgpt.com/share/6a4c93be-fa98-83ea-876e-42b57eb256a1)), and an
**Analysis** subsection covering edge cases, failure points, efficiency, and
potential improvements. Conventions used in every recipe:

```
RECIPE <name>
INPUT   what enters the pipeline (and from where)
OUTPUT  what leaves it

STEP <n> — <LABEL>
  IN:    data consumed (← the step it came from)
  <body written as conventional pseudocode>:
    ←                          assignment / production
    IF … THEN / ELSE / END IF  conditionals (ELSE IF, CASE … OF for
                               multi-way)
    FOR EACH … IN … DO/END FOR bounded iteration (CONTINUE to skip)
    WHILE … DO / REPEAT … UNTIL … loops
    PARALLEL DO … END PARALLEL  independent legs; JOIN implied at END
  every path ends with exactly one of:
    → STEP <m> WITH <data>     produce output AND proceed — one combined
                               line ("→ STEP <m>" alone when nothing is
                               passed; a backward "→ STEP <m>" is a loop
                               back-edge)
    RETURN <value>             terminal success
    ABORT "<reason>"           terminal stop (fail-closed)
```

Analysis subsections use four fixed headings — **Edge cases** (where
unusual-but-valid inputs land), **Failure points** (where the pipeline can
break; *fail-open* = degrades and continues, *fail-closed* = stops with an
error), **Efficiency** (cost profile as configured), and **Potential
improvements** (upgrades, each honest about its trade-off).

---

## 1. Site Search

### 1.1 Query parsing — `search_parse_query()` ([search.php](public/app/helpers/search.php))
- **Type:** Text-parsing / tokenization (two-pass scan).
- **Logic:** Pass 1 extracts balanced `"..."` segments as exact phrases and
  scrubs MySQL boolean operators inside them; pass 2 strips operators from the
  remaining words. Tokens are lowercased and deduplicated via a seen-set.
  Tokens ≥ 3 chars become required prefix terms (`+word*`) in a MySQL
  FULLTEXT boolean-mode expression; tokens ≤ 3 chars also emit a `LIKE`
  fallback list so short queries survive servers with a raised
  `innodb_ft_min_token_size`.
- **Inputs:** Raw query string (truncated at 200 chars). **Outputs:** boolean
  expression + normalized terms + LIKE terms, or `null`.
- **Characteristics:** Deterministic and unambiguous (same query always
  produces the same expression); finite (bounded by the 200-char cap);
  language-independent (the phrase/word/operator-stripping logic ports to any
  language). O(n) in query length.

### 1.2 Hybrid FULLTEXT + LIKE retrieval — `search_like_recall_clause()`
- **Type:** Searching algorithm (index search with linear-scan fallback).
- **Logic:** OR-composes an indexed FULLTEXT branch with per-term
  `LIKE '%term%'` predicates (with `\`, `%`, `_` escaped) so recall never
  silently drops short tokens. A greedy trade-off: LIKE branches are O(n) table
  scans, accepted only for the rare short-token case.

### 1.3 Snippet extraction and highlighting — `search_snippet()`
- **Type:** String searching (first-occurrence scan) + windowing.
- **Logic:** Linear scan finds the earliest case-insensitive occurrence of any
  term; a window of ±80 chars (max 220) is centered on it, clamped to the text
  bounds, ellipsized at cut edges. The window is HTML-escaped **first**, then
  each escaped term is regex-wrapped in `<mark>` — an escape-then-wrap ordering
  that makes the output XSS-safe by construction.
- **Characteristics:** Well-defined output (always valid, safe HTML);
  finite (bounded window); O(t·n) for t terms over text length n.

### Recipe Overview — Site Search pipeline

This recipe empowers any site visitor to find posts by keyword or phrase
instead of browsing the whole site by hand. It exists so that content stays
discoverable as the site grows: it turns whatever someone types into a safe
database query, returns the matching posts, and hands back a short, highlighted
preview of each so the reader can judge relevance at a glance.

### Recipe Pseudocode — Site Search pipeline

```
RECIPE Search.Query
INPUT   raw_query : untrusted string from the request
OUTPUT  results   : rows, each with an HTML-safe highlighted snippet

STEP 1 — CLAMP
  IN:    raw_query (request)
  bounded_query ← truncate(raw_query, 200 chars)
  → STEP 2 WITH bounded_query

STEP 2 — EXTRACT-PHRASES
  IN:    bounded_query (← STEP 1)
  phrases[] ← balanced "..." segments (operators + - > < ( ) ~ @ *
              scrubbed inside each)
  remainder ← the unquoted text
  → STEP 3 WITH phrases[], remainder

STEP 3 — CLEAN-WORDS
  IN:    remainder (← STEP 2), phrases[] (← STEP 2, for the seen-set)
  strip operators + stray quotes; lowercase; dedupe
    → terms[] (all tokens), unquoted[] (non-phrase words)
  IF terms[] is empty THEN
     RETURN null                    // caller runs no search at all
  ELSE
     → STEP 4 WITH terms[], unquoted[]
  END IF

STEP 4 — SPLIT-BRANCHES
  IN:    phrases[] (← STEP 2), unquoted[] (← STEP 3)
  FOR EACH p IN phrases[] DO  boolean_expr += +"p"            (required)
  FOR EACH w IN unquoted[] DO
     IF length(w) ≥ 3 THEN boolean_expr += +w*               (prefix)
     IF length(w) ≤ 3 THEN like_terms[] += w        // len 3 → BOTH
  END FOR
  IF boolean_expr = '' AND like_terms[] is empty THEN
     RETURN null
  ELSE
     → STEP 5 WITH boolean_expr, like_terms[]
  END IF

STEP 5 — QUERY
  IN:    boolean_expr, like_terms[] (← STEP 4)
  rows[] ← ONE SQL statement OR-composing two predicate groups
           (the DB evaluates them together, not in sequence):
             group 1: MATCH(...) AGAINST(boolean_expr IN BOOLEAN MODE)
             group 2: LOWER(col) LIKE LOWER('%term%') per like_term
                      (% _ \ escaped; user text only as bound params)
  → STEP 6 WITH rows[]

STEP 6 — SNIPPET
  IN:    rows[] (← STEP 5), terms[] (← STEP 3)
  FOR EACH row IN rows[] DO          // rows independent — order is free
     i ← earliest case-insensitive occurrence of any term IN row.text
     IF i found THEN
        window ← clamp(±80 chars around i, max 220), ellipsized
        row.snippet ← wrap_each_term_in_<mark>( escape(window) )
                      // escape FIRST, then wrap → XSS-safe by construction
     ELSE
        row.snippet ← escape( leading 220-char slice )
     END IF
  END FOR
  RETURN rows[]
```

### Recipe Instructions — Site Search pipeline

1. A visitor types something into the search box and submits it.
2. If what they typed is very long, the system keeps only the first 200
   characters.
3. It separates anything inside quotation marks (treated as an exact phrase)
   from the ordinary words, and removes punctuation that has special meaning
   to the database.
4. If nothing usable is left (the box was empty, or held only symbols), the
   search stops here and no results are looked up.
5. Longer words are used for fast, index-based matching; very short words
   (three letters or fewer) also get a slower, thorough backup match so they
   aren't missed.
6. The database is asked, in a single request, for all posts matching either
   kind of term.
7. For each result, the system finds the first place a search word appears,
   cuts out a short passage around it, makes that passage safe to display,
   and highlights the matching words inside it.
8. The list of results — each with its highlighted preview — is shown to the
   visitor.

### Recipe Diagram — Site Search pipeline

![Site Search Diagram](diagrams/site_search_pipeline.png)

### Analysis — Site Search pipeline

**Edge cases**
- Empty, whitespace-only, or operator-only query → `null` at STEP 3 or 4;
  the pipeline ends before any SQL runs.
- All tokens short → `boolean_expr` empty; the LIKE group alone matches.
- A term straddling the snippet window edge → excerpt correct, highlight lost.

**Failure points**
- *Fail-closed:* FULLTEXT index missing on a drifted deployment → STEP 5
  raises a database error; the remedy is the schema-convergence script (§9.6).

**Efficiency**
- Parse: O(n) in query length, two regex passes, no DB access.
- Query: the FULLTEXT branch is index-backed; each LIKE term forces a scan —
  bounded in practice because `like_terms` only exist for tokens ≤ 3 chars.
- Snippet: O(t·n) per row; rows are independent.

**Potential improvements**
- Cache parsed queries for repeated searches (memoize on the raw string);
  cheap win, though parsing is already fast enough that the DB query dominates.
- Replace the LIKE recall branch with an n-gram or prefix index if short-token
  searches become common; costs index storage and write amplification for a
  currently-rare case.
- Multi-fragment snippets (highlight *all* windows containing terms, à la
  search engines) instead of only the first; more useful excerpts at ~2–3×
  snippet cost per row.

---

## 2. Blog

### 2.1 Excerpt generation — `seo_excerpt()` ([seo.php](public/app/helpers/seo.php)), `buildPostExcerpt()` ([ContentHelpers.php](public/app/lib/syndication/ContentHelpers.php))
- **Type:** Brute-force text truncation.
- **Logic:** Strip HTML, collapse whitespace, truncate at a character limit,
  append ellipsis. Deterministic, O(n), trivially finite.

### 2.2 Feed ingestion (RSS/Atom import) — [feed-ingest.php](public/app/helpers/feed-ingest.php)
- **Type:** Polling/scheduling loop + deduplication by key lookup.
- **Logic:**
  1. `refresh_due_feeds()` — a scheduler: iterate enabled sources, skip any not
     yet due (per-source interval check), ingest the rest; failures are
     isolated per source so one bad feed can't halt the loop.
  2. `parse_feed_xml()` — format detection (RSS vs Atom) followed by
     field-mapping extraction into a normalized item shape.
  3. `ingest_feed()` — for each parsed item, `feed_item_seen()` performs an
     exact-match GUID lookup (indexed search, effectively O(1) per item) and
     only unseen items are recorded — an idempotent set-insertion algorithm.
- **Characteristics:** Finiteness is guaranteed (bounded by source count ×
  items per feed); repeat runs with the same input converge to the same state
  (idempotence is the well-defined-output property applied to side effects).

### 2.3 Category slug uniqueness — `unique_category_slug()` ([slugify.php](public/app/helpers/slugify.php))
See §7.1 — shared with all sluggable content types.

### Recipe Overview — Feed import pipeline

This recipe empowers an admin to connect outside sources — another blog's RSS
or Atom feed, for instance — and have their entries appear on this site
automatically. It exists to spare the admin from copying content over by hand:
it checks each connected feed on a schedule, pulls in only entries it hasn't
seen before, and keeps running smoothly even when one source is broken.

### Recipe Pseudocode — Feed import pipeline

```
RECIPE Blog.FeedImport            (cron-triggered)
INPUT   enabled feed sources (feed_url, fetch interval, last status)
OUTPUT  new rows in feed_import_items; updated per-source status

STEP 1 — SCHEDULE      refresh_due_feeds()
  IN:    enabled sources
  FOR EACH source IN enabled_sources DO   // independent, try/catch-isolated
     IF NOT is_due(source) THEN CONTINUE          // skip: not yet due
     run STEP 2–4 for source
  END FOR
  RETURN count of feeds ingested

STEP 2 — FETCH         fetch_feed()          (body of STEP 1's loop)
  IN:    source.feed_url (← STEP 1)
  IF source.feed_url = '' THEN
     source.status ← 'error: No feed URL configured';  CONTINUE  // next source
  END IF
  xml ← http_get(source.feed_url)
  IF xml is null THEN
     source.status ← 'error: Failed to fetch feed';  CONTINUE
  END IF
  → STEP 3 WITH xml

STEP 3 — PARSE         parse_feed_xml()
  IN:    xml (← STEP 2)
  items[] ← map( detect(RSS | Atom), xml ) → {guid, title, content, dates}
            // feed_datetime(): unparseable date → null, never fabricated
  IF items[] is empty THEN
     source.status ← 'success';  CONTINUE          // empty feed is NOT error
  ELSE
     → STEP 4 WITH items[]
  END IF

STEP 4 — DEDUP + RECORD
  IN:    source.id, items[] (← STEP 3)
  FOR EACH item IN items[] DO
     IF feed_item_seen(source.id, item.guid) THEN
        CONTINUE                                   // re-runs are no-ops
     ELSE
        record_feed_item(item)   // insert carrying source_feed_id
     END IF
  END FOR
  source.status ← 'success'
  → STEP 1 (next source)                           // loop back-edge
```

### Recipe Instructions — Feed import pipeline

1. On a schedule, the system reviews every external feed (for example, another
   blog's RSS) that has been connected.
2. For each feed, it checks whether enough time has passed since the last
   check. If not, it skips that feed for now.
3. It downloads the feed. If the address is blank or the download fails, it
   records an error for that one feed and moves on to the next.
4. It reads the downloaded content, works out its format, and pulls out each
   entry (title, content, date, and a unique identifier). Missing or
   unreadable dates are left blank rather than guessed.
5. An empty feed is treated as a successful check, not an error.
6. For each entry, it checks whether that entry's unique identifier has been
   seen before. Already-seen entries are ignored, so re-running never creates
   duplicates.
7. New entries are saved, each tagged with which feed it came from.
8. Throughout, one broken feed never stops the others from importing.

### Recipe Diagram — Feed import pipeline

![Feed Import Pipeline Diagram](diagrams/feed_import_pipeline.png)

### Analysis — Feed import pipeline

**Edge cases**
- A feed that changes its GUIDs → items re-import as duplicates; GUID
  stability is an external dependency this pipeline cannot enforce.
- Zero-item feeds record `success`, not an error.
- Unparseable dates become `null`, never a fabricated timestamp.

**Failure points**
- *Fail-open:* any throw inside one source's STEP 2–4 is caught; the loop
  continues with the next source.
- *Fail-open:* `feed_import_items` table missing → the existence probe
  reports false → ingestion degrades to inactive, no crash.

**Efficiency**
- O(sources) scheduling + O(items) per feed with O(1) indexed GUID lookups.
- The dominant cost is network fetch, currently serial — total wall time is
  the sum of all feed response times.

**Potential improvements**
- Fetch due feeds concurrently (multi-curl); the per-source isolation already
  in place makes this safe. The biggest wall-time win available.
- Honor HTTP caching (ETag / If-Modified-Since) to skip STEP 3–4 entirely on
  unchanged feeds; requires storing validators per source.
- Batch STEP 4 inserts into one multi-row INSERT per feed; marginal unless
  feeds are large.

### Recipe Overview — Authored post pipeline

This recipe empowers an author to publish a post once and have it reach every
channel automatically. It exists so that a single act of writing does the work
of many: after the post is saved, the same content is given a clean web
address, made searchable, summarized for search-engine previews, and offered to
the social-media syndication process — without the author repeating themselves.

### Recipe Pseudocode — Authored post pipeline

```
RECIPE Blog.AuthoredPost
INPUT   post content + category from the admin editor
OUTPUT  published post consumed by search (§1), syndication (§8),
        and SEO meta

STEP 1 — SLUG
  IN:    post/category names (INPUT)
  slug ← RECIPE ContentMgmt.Slug (§7)
  → STEP 2 WITH slug

STEP 2 — STORE
  IN:    post content, category, slug (← STEP 1)
  row ← save(post)
  → STEP 3 WITH row

STEP 3 — FAN-OUT       (independent downstream consumers of the same row)
  IN:    row (← STEP 2)
  PARALLEL DO
     LEG A (render):    meta ← seo_excerpt(row.content)
     LEG B (search):    index row.content FOR RECIPE Search.Query
     LEG C (syndicate): row → RECIPE Syndication.Publish (§8)
  END PARALLEL
  RETURN            // no JOIN result — each leg serves a different reader

NOTE   imported posts (Blog.FeedImport) carry source_feed_id, which
       Syndication.Publish STEP 2 reads to suppress self-attribution.
```

### Recipe Instructions — Authored post pipeline

1. An author writes a post and assigns it a category in the admin editor.
2. The post's name is turned into a clean web address (see the Slug recipe).
3. The post is saved.
4. From there, the same saved post feeds three independent readers at once: a
   short summary is generated for search-engine previews, the content is added
   to the site's search index, and the content is handed to the syndication
   process for posting to social platforms.
5. Note: posts imported from an external feed are marked as such, which later
   tells the syndication process not to add an "original source on this site"
   footer.

### Recipe Diagram — Authored post pipeline

![Authored Post Diagram](diagrams/authored_post_pipeline.png)

### Analysis — Authored post pipeline

**Edge cases**
- Feed-imported posts flow through the same downstream legs but carry
  `source_feed_id`, which changes syndication's footer decision (§8).

**Failure points**
- Inherits the slug race from ContentMgmt.Slug (§7 Analysis); no failure
  modes of its own beyond ordinary save errors.

**Efficiency**
- Single-row writes; the downstream legs each read the same stored content
  without transforming it twice.

**Potential improvements**
- Pre-compute and store the excerpt at save time instead of per render —
  trades a column for repeated strip/truncate work; only worth it if render
  volume is high.

---

## 3. Art Pieces (AI art pipelines)

### 3.1 Bounded generate-validate-repair loop — [PiecesAdminController.php](public/app/controllers/Admin/PiecesAdminController.php), [art-piece-generation.php](public/app/helpers/art-piece-generation.php)
- **Type:** Randomized algorithm (LLM sampling) wrapped in a bounded
  retry/feedback loop.
- **Logic:** Prompt an AI provider for engine-specific code (p5.js, Three.js,
  A-Frame, …); extract fenced code blocks; run static preflight validation
  (`art_piece_preflight_code/_document`) checking for forbidden constructs,
  media-reference policy, and engine-specific limits (e.g. counting Three.js
  object-construction calls). On failure, build a repair prompt embedding the
  failure message and previous response, and retry — up to
  `ART_PIECE_MAX_ATTEMPTS = 5` attempts with a 120 s per-attempt timeout.
- **Characteristics:** The LLM step is *not* deterministic (same input can
  yield different outputs — the article's randomized-algorithm class), so the
  system restores the other characteristics around it: **finiteness** via the
  hard attempt cap and timeout, and **well-defined outputs** via the validator,
  which acts as an acceptance predicate — only code passing every check is
  ever stored.

### 3.2 Media-reference validation — `validate_art_piece_prompted_media_refs()` and friends
- **Type:** Searching (pattern matching) + set-membership allowlisting.
- **Logic:** Regex-extract every media URL/`cms-media:` reference from
  generated HTML/CSS/JS, normalize each, and require membership in the allowed
  set derived from the prompt. Reject on any reference outside the allowlist.
- **Characteristics:** Unambiguous accept/reject predicate; O(n) scan of the
  generated code.

### 3.3 Refinement patch application — `art_piece_apply_refine_patches()` / `art_piece_find_patch_match()`
- **Type:** String searching with a whitespace-tolerant fallback matcher.
- **Logic:** Each LLM-produced patch is a SEARCH/REPLACE pair. Matching runs
  in two phases: (1) exact `substr_count` match — accept only if exactly one
  occurrence; (2) fallback: tokenize the SEARCH text into word-runs and
  individual symbols (whitespace discarded entirely), re-join with `\s*` into
  a regex, and match that. More than one match (either phase) → "ambiguous"
  and the patch is rejected; zero → rejected. Accepted patches are spliced in
  by offset.
- **Characteristics:** This is a deliberate unambiguity-enforcement algorithm:
  rather than guessing among multiple candidate locations, it refuses and
  triggers the repair loop (§3.1) with a corrective message. Only
  whitespace runs are interchangeable — every real token must match — so the
  well-defined-output guarantee (the right text is replaced or nothing is)
  holds. O(n·m) worst case for the regex phase.

### 3.4 Piece rendering and export — [piece-render.php](public/app/helpers/piece-render.php)
- **Type:** Template assembly / document generation (recursive descent over a
  parts tree: document → imports → runtime → bootstrap → user code).
- **Logic:** Builds a self-contained sandboxed HTML document per piece.
  Engine-specific bootstrap selection; for offline export, runtime sources are
  embedded and cross-module references rewired via `Blob` object-URLs (the
  OrbitControls source is patched to import Three.js from a generated blob URL
  — a small link-resolution/relocation algorithm, like a linker fixup pass).
  `piece_export_rewrite_media_refs()` rewrites `cms-media:` references through
  a resolver callback so exports carry their assets.
- **Characteristics:** Fully deterministic given piece + version + options;
  language-independent in structure (an assembly recipe).

### 3.5 Canvas export with paint-readiness polling — export scripts in piece-render.php
- **Type:** Bounded polling loop.
- **Logic:** Before capturing a PNG, `hasVisiblePixels()` samples the canvas
  and the loop waits/retries until non-blank or a retry cap is hit —
  finiteness imposed on an inherently asynchronous render.

### Recipe Overview — AI Art Piece Generation pipeline

This recipe empowers an admin to create interactive artwork simply by
describing it in words, letting the AI write the underlying code. It exists so
that someone who isn't a programmer can still produce generative art safely:
the system spends AI budget only within limits, checks everything the AI
returns against safety rules, and retries with feedback until the result is
valid — saving nothing that fails.

### Recipe Pseudocode — AI Art Piece Generation pipeline

```
RECIPE ArtPiece.Generate
INPUT   admin prompt, engine/generation mode, optional cms-media: refs
OUTPUT  validated, versioned art piece — or accumulated failure after
        the attempt cap, with NOTHING stored

STEP 1 — RATE-GATE     rate_limit_consume('ai_generate_piece')
  IN:    scope + hashed admin subject
  verdict ← RECIPE Security.RateGate    (bucket: 4 req / 15 min)
  IF verdict = denied THEN
     ABORT "rate limited"   // reject with retry_after, before any AI spend
  ELSE
     → STEP 2
  END IF

STEP 2 — PROMPT-BUILD
  IN:    engine, prompt, media refs (INPUT)
  system_prompt ← engine_specific_prompt(engine)
  IF media refs present THEN
     system_prompt += media-policy paragraph (restrict to exactly those refs)
  END IF
  attempt ← 1
  → STEP 3 WITH system_prompt, user_prompt

STEP 3 — AI-CALL       AiProviderClient::generate()        [NON-DETERMINISTIC]
  IN:    prompts (← STEP 2, or repair_prompt ← STEP 6);
         vendor key ← RECIPE Security.Secrets
  raw_response ← call_provider(prompts, timeout = 120 s)
  IF timeout OR provider error THEN
     → STEP 6 WITH failure_message
  ELSE
     → STEP 4 WITH raw_response
  END IF

STEP 4 — EXTRACT       art_piece_extract_code_blocks()
  IN:    raw_response (← STEP 3)
  code ← extract fenced blocks (html / css / js)
  IF no code block found THEN
     → STEP 6 WITH failure_message
  ELSE
     → STEP 5 WITH code
  END IF

STEP 5 — VALIDATE      (acceptance predicate — BOTH checks must pass)
  IN:    code (← STEP 4), allowed media refs (INPUT)
  check_a ← static_preflight(code)   // forbidden constructs, engine limits
  check_b ← every media URL IN code ∈ allowed set
                                     // a /media/{id} 3D-model ref is allowed
                                     // exactly like an image ref; the runtime
                                     // provides THREE.GLTFLoader/OBJLoader so the
                                     // code needs no forbidden import/fetch
  IF check_a fails OR check_b fails THEN
     → STEP 6 WITH exact failure_message    // first failure reported
  ELSE
     → STEP 7 WITH code
  END IF

  // SIDE CHANNEL (optional, piece-sound feature): "Describe the feel" /
  // "Tone Feel" guidance may produce a 4th ```sonic``` JSON block. When
  // present, it is soft-validated SEPARATELY — coerced to nearest supported
  // {tempo, scale, instrument} or dropped to null. It NEVER participates in
  // the check_a/check_b accept/reject above, so a bad sonic block can't fail
  // the code; it just means "no sound". Stored on the version as sonic_params.

STEP 6 — REPAIR-OR-STOP
  IN:    failure_message (← STEP 3/4/5), raw_response, attempt
  IF attempt < 5 THEN
     repair_prompt ← build(failure_message + previous raw_response)
     attempt ← attempt + 1
     → STEP 3 WITH repair_prompt      // loop back-edge; each is its own
                                      // request, tracks attemptNumber/can_retry
  ELSE
     ABORT "5 attempts exhausted; NOTHING stored"
  END IF

STEP 7 — STORE
  IN:    accepted code (← STEP 5)
  version ← save_new_piece_version(code)
  RETURN version.id
```

### Recipe Instructions — AI Art Piece Generation pipeline

1. An admin describes the artwork they want and picks an engine (the drawing
   technology to use).
2. The system first checks a usage limit. If the admin has made too many
   requests recently, it stops immediately — before spending any money on the
   AI.
3. It assembles the instructions for the AI, adding rules about which images
   may be used if the admin referenced any.
4. It asks the AI to produce the code, allowing up to two minutes.
5. It pulls the code out of the AI's reply. If there is no code, that counts
   as a failure.
6. It checks the code against safety rules and confirms every referenced image
   is on the allowed list. If any check fails, that counts as a failure.
7. On a failure, if fewer than five attempts have been made, it tells the AI
   exactly what went wrong and tries again; after five attempts it gives up
   and saves nothing.
8. On success, the code is saved as a new version of the artwork.

### Recipe Diagram — AI Art Piece Generation pipeline

![AI Art Piece Generation Pipeline Diagram](diagrams/ai_art_piece_generation_pipeline.png)

### Analysis — AI Art Piece Generation pipeline

**Edge cases**
- A response with no code block and a response failing validation both land
  in the same place — STEP 6 — never silently accepted.
- Media refs in the prompt but absent from the output are caught by the
  allowlist validator's required-refs mode.

**Failure points**
- *Fail-closed:* the validator is the acceptance predicate; after 5 attempts
  the admin gets the accumulated failure message and nothing is saved.
- *Contained:* validation is static, so code can pass preflight yet fail at
  runtime in the browser; the sandbox iframe and the `showPieceError`
  placeholder contain that case at display time.

**Efficiency**
- Cost is dominated by STEP 3 (paid AI call, up to 5 × 120 s worst case).
- Everything around it is O(code length) static analysis — deliberately cheap
  so a bad attempt fails fast and locally.
- The rate gate caps worst-case spend per admin per window.

**Potential improvements**
- Run STEP 5's two checks and report *all* failures at once (today the first
  failure wins) → richer repair prompts → likely fewer retries, at zero extra
  runtime cost.
- Stream the AI response and abort early when the output visibly violates
  preflight (e.g. a forbidden import appears) — saves tokens on doomed
  attempts; adds streaming-parse complexity.
- Persist failed attempts' messages per piece for offline prompt tuning;
  storage cost only.

### Recipe Overview — AI Art Piece Refinement pipeline

This recipe empowers an admin to adjust an existing artwork with a plain-
language request rather than by editing code themselves. It exists to make
iteration safe: the AI proposes precise find-and-replace edits, and the system
applies them only if every one matches the real code exactly — so a change is
either made cleanly and in full, or not at all, never leaving the artwork in a
broken half-edited state.

### Recipe Pseudocode — AI Art Piece Refinement pipeline

```
RECIPE ArtPiece.Refine
INPUT   refinement prompt + the piece's CURRENT html/css/js
OUTPUT  patched code stored as a new version — never a half-patched
        hybrid

STEP 1 — RATE-GATE     rate_limit_consume('ai_refine_piece')   (6 / 15 min)
  verdict ← RECIPE Security.RateGate
  IF verdict = denied THEN
     ABORT "rate limited"
  ELSE
     attempt ← 1;  → STEP 2
  END IF

STEP 2 — AI-CALL                                          [NON-DETERMINISTIC]
  IN:    refinement prompt + current code (INPUT, re-sent every attempt)
  response ← call_provider()   → plan + SEARCH/REPLACE patches[]
  → STEP 3 WITH patches[]

STEP 3 — MATCH         art_piece_find_patch_match()
  IN:    current code (INPUT), patches[] (← STEP 2)
  FOR EACH patch IN patches[] DO
     n ← exact substring count of patch.search IN code
     IF n = 1 THEN
        patch.offset ← position               // matched
     ELSE IF n > 1 THEN
        → STEP 5 WITH "ambiguous"              // whole set rejected
     ELSE  // n = 0 → whitespace-tolerant fallback
        pattern ← tokenize(patch.search) joined WITH \s*  // whitespace dropped
        m ← regex match count of pattern IN code
        IF m = 1 THEN patch.offset ← position
        ELSE → STEP 5 WITH "unmatched / ambiguous"
        END IF
     END IF
  END FOR
  → STEP 4 WITH patches[] (all matched)

STEP 4 — APPLY + STORE     (all-or-nothing)
  IN:    patches[] with offsets (← STEP 3)
  FOR EACH patch IN patches[] DO code ← splice(code, patch.offset, patch.replace)
  version ← save_new_piece_version(code)
  RETURN version.id

STEP 5 — REPAIR-OR-STOP
  IN:    rejection reason (← STEP 3), attempt
  IF attempt < cap THEN
     repair_prompt ← build(reason + RE-INCLUDED current source)
        // retry re-derives SEARCH from real code, not its own wrong output
     attempt ← attempt + 1
     → STEP 2 WITH repair_prompt            // loop back-edge
  ELSE
     ABORT "cap reached; stored code unchanged"
  END IF
```

### Recipe Instructions — AI Art Piece Refinement pipeline

1. An admin asks for a change to an existing artwork.
2. The usage limit is checked first, as with generation.
3. The AI is sent the request together with the artwork's current code, and it
   replies with a set of find-and-replace edits.
4. For each edit, the system locates exactly where it applies. If an edit's
   target cannot be found, or could match more than one place, the whole set
   is rejected — nothing is applied halfway.
5. If every edit matches cleanly, they are all applied together and saved as a
   new version.
6. If the set is rejected and attempts remain, the AI is asked to try again —
   and is re-shown the real current code so it can correct itself; otherwise
   the artwork is left unchanged.

### Recipe Diagram — AI Art Piece Refinement pipeline

![AI Art Piece Refinement Diagram](diagrams/ai_art_piece_refinement_pipeline.png)

### Analysis — AI Art Piece Refinement pipeline

**Edge cases**
- Whitespace-only differences between the model's SEARCH text and the real
  code are tolerated; any real-token difference is not — the matcher cannot
  match the wrong content, only incidental reformatting of the right content.

**Failure points**
- *Fail-closed:* an ambiguous or unmatched patch aborts the whole set at
  STEP 5; stored code is never partially patched.

**Efficiency**
- Exact match is the fast path (`substr_count`, O(n)); the regex fallback is
  O(n·m) worst case but only runs on exact-match misses.
- Re-sending the full source each attempt costs tokens but is what makes
  retries converge.

**Potential improvements**
- Report *all* failed patches in one repair prompt instead of the first —
  same rationale as the generation pipeline.
- Anchor patches with line hints from the model to cheaply disambiguate
  multi-match cases before rejecting; adds prompt-format complexity.

### Recipe Overview — AI Art Piece Rendering / export pipeline

This recipe empowers visitors to view an artwork on the site and to download a
self-contained copy they can open offline. It exists so that generative work is
both live on the web and portable: it assembles each piece into a standalone
page, bundles in the libraries and images a download needs to run without an
internet connection, and captures a still image only once the artwork has
actually painted on screen.

### Recipe Pseudocode — AI Art Piece Rendering / export pipeline

```
RECIPE ArtPiece.RenderExport
INPUT   stored piece + version; target = public iframe | offline export
OUTPUT  sandboxed embed document, or standalone offline document

STEP 1 — ASSEMBLE
  IN:    piece, version (INPUT)
  document ← imports + runtime + engine_bootstrap(engine) + user code
  IF target = iframe THEN
     RETURN document                        // served; capture path not taken
  ELSE  // target = export
     → STEP 2 WITH document
  END IF

STEP 2 — EMBED-RUNTIME
  IN:    document (← STEP 1)
  PARALLEL DO                               // independent rewrites
     LEG A (runtime): inline runtime sources; wire cross-module imports via
                      Blob object-URLs (OrbitControls patched to import
                      Three.js from the generated blob URL)
     LEG B (media):   rewrite cms-media: refs → bundle-relative asset paths
  END PARALLEL
  RETURN standalone document                // download

STEP 3 — PNG-CAPTURE   (separate on-demand entry point, browser side)
  IN:    rendered canvas
  REPEAT
     visible ← hasVisiblePixels(canvas)
  UNTIL visible OR retry cap reached
  IF visible THEN
     blob ← letterbox(canvas INTO presentation surface);  download(blob)
     RETURN png
  ELSE
     ABORT "blank canvas — no image downloaded"
  END IF
```

### Recipe Instructions — AI Art Piece Rendering / export pipeline

1. The system takes a saved artwork and builds a self-contained web page for
   it.
2. If the page is for viewing on the site, it is returned as-is.
3. If the page is for download, the drawing libraries are bundled into it and
   image links are rewritten to point inside the download, so it works with no
   internet connection.
4. Separately, when someone saves a picture of the artwork, the system waits
   until the drawing has actually appeared on screen, then captures and
   downloads it. If nothing ever appears, it reports a failure instead of
   saving a blank image.

### Recipe Diagram — AI Art Piece Rendering / export pipeline

![AI Art Piece Rendering / Export Diagram](diagrams/ai_art_piece_rendering_and_export_pipeline.png)

### Analysis — AI Art Piece Rendering / export pipeline

**Edge cases**
- The iframe and export paths share STEP 1's assembly, so embed and download
  render identically apart from where the runtime comes from.

**Failure points**
- *Contained:* validation upstream is static — exported code can still throw
  at runtime; the sandbox and `showPieceError` placeholder handle it.
- *Fail-closed:* a canvas that never paints exhausts the STEP 3 poll and
  reports failure rather than downloading a blank image.

**Efficiency**
- Assembly is string concatenation, O(document size); deterministic, and the
  export mode uses no network by design.

**Potential improvements**
- Cache assembled export documents per (piece, version) — they're immutable
  once a version exists, so invalidation is trivial (never), at the cost of
  storage.

---

## 4. Immersive Gallery / Exhibits ([immersive-gallery.js](public/assets/js/immersive-gallery.js))

### 4.1 Mounted-artwork layout — `computeMountedArtworkLayout()`, `computeExhibitGridCenterY()`
- **Type:** Geometric computation (closed-form, no iteration).
- **Logic:** Given an artwork aspect ratio and a layout profile, compute frame
  dimensions, wall placement, and camera-relevant centers with pure
  arithmetic; grid variants derive row/column world coordinates from counts.
- **Characteristics:** The textbook case of every characteristic at once:
  exact numeric inputs, exact numeric outputs, O(1), trivially portable.

### 4.2 Camera auto-fit — `fitMountedGalleryCamera()`, `computeThreeAutoFitView()`
- **Type:** Geometric optimization (closed-form).
- **Logic:** From the bounding size of the subject and the camera's vertical
  FOV, solve the distance at which the subject fills the frame
  (`d = size / (2·tan(fov/2))` scaled by a framing multiplier), with a
  compact-viewport branch for narrow screens.

### 4.3 Keyboard/orbit navigation — `computeOrbitKeyboardMotion()`, `createKeyboardNavigation()`
- **Type:** Vector math inside a fixed-timestep simulation loop.
- **Logic:** Project the camera's forward vector onto the ground plane,
  derive a strafe vector by cross product, sum contributions of currently-held
  keys, scale by speed, and translate camera + orbit target together each
  frame. Key-state is a set updated by keydown/keyup with blur-clearing to
  avoid stuck keys.

### 4.4 Floor click-to-walk — `createFloorClickNavigation()`
- **Type:** Ray casting (searching in 3D) + interpolation (easing loop).
- **Logic:** Distinguish click from drag by pointer-travel threshold; raycast
  from the click through the camera into the floor mesh; the hit point becomes
  a movement target approached by per-frame interpolation until within an
  epsilon — a finite convergence loop.

### 4.5 Progressive live-render budgeting — `getProgressiveExhibitLiveBudget()`
- **Type:** Greedy resource allocation.
- **Logic:** Only the k nearest/most-relevant exhibit items get live
  interactive renders (k chosen from viewport width and static-mode flag);
  the rest stay as static textures. A greedy heuristic in the article's sense:
  locally optimal (spend GPU on what's visible), fast, not globally optimal —
  chosen for feasibility (the "effectiveness and feasibility" characteristic)
  over exhaustive rendering, which would be infeasible on real hardware.

### 4.6 Presentation-surface letterboxing — `drawContainedIntoPresentationSurface()`
- **Type:** Geometric scaling ("contain" fit).
- **Logic:** `scale = min(destW/srcW, destH/srcH)`, center the scaled content,
  fill the margins with a background color. O(1) math, O(pixels) draw.

### Recipe Overview — Immersive viewing pipeline

This recipe empowers visitors to walk through the artwork as a 3D gallery
rather than scroll past flat thumbnails. It exists to present the work as an
exhibition experience while staying responsive on ordinary devices: it arranges
pieces on virtual walls, frames them with the camera, keeps only the nearest
few running live to protect performance, and lets people move around with
keyboard, pointer, or device tilt.

### Recipe Pseudocode — Immersive viewing pipeline

```
RECIPE Immersive.View
INPUT   piece/exhibit data (code, media, rows × cols), viewport size,
        input devices, optional encoded view-state from a shared URL
OUTPUT  interactive 3D gallery; on request: PNG snapshot or a
        shareable encoded view-state

STEP 1 — STAGE-SETUP
  IN:    environment (live site | offline export)
  runtime ← load(environment = live ? CDN : embedded blob-URL modules)
  stage   ← create Three.js stage + toolbar chrome
  → STEP 2 WITH stage

STEP 2 — LAYOUT        (closed-form, O(1) per item)
  IN:    aspect ratios, rows × cols, layout profile, viewport (INPUT)
  IF viewport is compact THEN profile ← compact ELSE profile ← standard
  positions ← { frame sizes  ← aspect;
                wall coords   ← row-major grid;
                camera centers ← row counts }
  → STEP 3 WITH positions

STEP 3 — CAMERA-FIT
  IN:    subject bounds (← STEP 2), fov, encoded view-state (INPUT)
  pose ← auto_fit:  d = size / (2·tan(fov/2)) · framing multiplier
  IF encoded view-state present THEN
     pose ← sanitize(view-state)    // clamp every value — a crafted URL
                                    // cannot place camera at garbage coords
  END IF
  → STEP 4 WITH pose

STEP 4 — RENDER-BUDGET (greedy)
  IN:    viewport width, static-mode flag (INPUT), items (← STEP 2)
  k ← live_budget(viewport width, static-mode)
  nearest k items → live interactive canvases;  remainder → static textures
  → STEP 5 WITH per-item render mode

STEP 5 — INTERACTION-LOOP
  IN:    input devices, render modes (← STEP 4)
  WHILE page open DO                        // one iteration per frame
     PARALLEL DO                            // independent event sources
        LEG A (keyboard):
           IF event target is a form field THEN IGNORE   // typing ≠ moving
           IF window blur THEN clear held-key set          // no stuck keys
           motion ← Σ held-key vectors (forward on floor plane,
                                        strafe by cross product)
        LEG B (pointer):
           IF pointer travel ≥ threshold THEN orbit only   // drag
           ELSE walk_target ← raycast(click → floor mesh)  // click
        LEG C (gyro):
           IF permission granted THEN camera ← orientation
           // else this leg never activates
        LEG D (audio — optional sonification):
           IF current version has sonic params AND user enabled sound THEN
              motion_delta ← camera.position − previous_frame_position
              Tone.js ← map(motion_delta) per {tempo, scale, instrument}
           // Tone.js self-hosted + lazy-loaded on the enable gesture; reads the
           // SAME per-frame motion as LEG A/B/C — a listener, not new tracking.
           // Autoplay-gated ("Tap to enable sound"); else this leg never sounds.
           // The regular /pieces/{id} view runs an identical LEG D inside its
           // own iframe (piece-runtime.js, three/aframe only there — other
           // engines have no camera motion on that surface) but the enable
           // gesture is a click on a PARENT-page button, relayed in via
           // postMessage rather than an in-page click; the leg itself is the
           // same motion→Tone.js mapping either way.
     END PARALLEL
     apply motion;  ease camera → walk_target UNTIL within epsilon;  render
     IF user requests capture THEN → STEP 6
  END WHILE

STEP 6 — CAPTURE       (on demand; returns to STEP 5)
  IN:    live canvas
  REPEAT visible ← hasVisiblePixels(canvas) UNTIL visible OR retry cap
  IF visible THEN
     download( letterbox(canvas) );  → STEP 5
  ELSE
     report capture failure (never a blank image);  → STEP 5
  END IF
```

### Recipe Instructions — Immersive viewing pipeline

1. The 3D gallery loads its viewing software and sets up the stage.
2. It calculates where each artwork hangs on the wall based on the artwork's
   shape and the grid size, adjusting the arrangement for small screens.
3. It positions the camera to frame the art. If the visitor arrived through a
   shared link that carries a saved viewpoint, that viewpoint is used — after
   being checked for sensible values.
4. To keep things running smoothly, only the nearest few artworks are shown
   "live"; the rest are shown as still images.
5. While the visitor is in the gallery, the system continuously responds to
   their keyboard, mouse or touch, and (if permission is granted) device tilt
   to move the camera around — ignoring keystrokes typed into text boxes and
   preventing keys from getting stuck.
6. If the visitor asks to save a picture, the system waits for the scene to be
   visible, then downloads it (or reports a failure if the scene is blank),
   and returns them to the gallery.

### Recipe Diagram — Immersive viewing pipeline

![Immersive Viewing Pipeline Diagram](diagrams/immersive_viewing_pipeline.png)

### Analysis — Immersive viewing pipeline

**Edge cases**
- ES-module sketches: `resolveSketchFactory` cannot parse them → those run
  via the srcdoc-iframe + proxy-canvas path instead (the wall-animation
  pattern).
- Devices without gyroscope permission → that input leg simply never
  activates.
- Audio autoplay policy → the sonification leg (LEG D) stays silent until the
  user taps "enable sound"; Tone.js is lazy-loaded then. The current version's
  stored `sonic_params.feel` is documented as Sound Feel in both the regular
  and immersive piece documentation blocks. If its (self-hosted) source fails
  to load, model-free rendering is unaffected — same fail-open shape as the
  gyro permission gate. Only the focused/active piece sonifies (in a
  collection/exhibit wall, the audio controller is torn down and rebuilt
  whenever camera focus moves to a different item).
- Offline export parity → both standalone (`piece_export_document`) and
  immersive/collection exports bundle Tone.js as an inlined Blob URL (same
  technique already used for OrbitControls), so a sound-bearing exported
  piece plays with zero network requests when opened via `file://` or a
  static host.
- Typing in form fields and window blur are filtered/cleared so the camera
  never moves unintentionally and keys never stick.

**Failure points**
- *Contained, per item:* WebGL context loss or an artwork whose code throws →
  that item's error placeholder shows; the rest of the wall keeps rendering.
- *Fail-closed:* a blank canvas at capture time exhausts the readiness poll
  and reports failure rather than downloading an empty image.

**Efficiency**
- Layout and camera math are O(1) closed-form — no iteration, no layout
  thrash.
- Frame cost is dominated by the k live canvases, which is exactly what the
  greedy budget bounds. Event handlers are O(1).

**Potential improvements**
- Make the live budget dynamic: promote/demote items as the camera moves
  (distance-sorted priority queue) rather than fixing k at load; smoother
  close-up experience, adds texture-swap churn.
- Use measured frame time (not just viewport width) to size k — adapts to
  weak GPUs on large screens; needs a warm-up sampling window.
- OffscreenCanvas for live items where supported, moving piece rendering off
  the main thread; browser-support-gated.

---

## 5. Collections (downloads / bundling)

### 5.1 Bundle assembly — `collection_export_bundle()`, `collection_export_build_manifest()` ([piece-render.php](public/app/helpers/piece-render.php))
- **Type:** Divide-and-combine document generation.
- **Logic:** Decompose the export into independent sub-products — manifest,
  README, per-item payloads (`collection_export_items_payload()`), runtime
  files, media assets — build each, then combine into a single archive.
  Media URLs are rewritten to bundle-relative paths via the same resolver
  pattern as §3.4.
- **Characteristics:** Deterministic and finite; well-defined output is the
  point (the bundle must run standalone with no network).

### 5.2 Exhibit-wall grid in exports — `collection_export_document()`
- **Type:** Grid layout (row-major placement).
- **Logic:** Items are assigned `(row, col)` positions in row-major order over
  a `rows × cols` wall and mounted via the shared immersive runtime (§4).

### Recipe Overview — Collection download pipeline

This recipe empowers an admin or visitor to download a whole curated collection
as a single offline archive. It exists so that a grouping of works can be
shared or preserved as one unit: it packages every item and its media, rewrites
links to point inside the archive, bundles in the viewing software plus a
plain-language README, and produces one file that reproduces the gallery wall
with no internet connection.

### Recipe Pseudocode — Collection download pipeline

```
RECIPE Collection.Export
INPUT   collection record, ordered items (pieces w/ current versions
        and/or media assets), options (rows × cols, initial view-state)
OUTPUT  standalone archive that reproduces the gallery wall offline

STEP 1 — ITEM-PAYLOADS
  IN:    items[] (INPUT)
  FOR EACH item IN items[] DO           // items independent
     IF item is a piece THEN
        payload ← {engine, code, generation mode}
     ELSE  // item is media
        payload ← {resolved asset URL}
     END IF
     payloads[] += payload
  END FOR
  → STEP 2 WITH payloads[]

STEP 2 — MEDIA-REWRITE
  IN:    payloads[] (← STEP 1)
  FOR EACH payload IN payloads[] DO
     rewrite every cms-media: ref and /media/ URL → bundle-relative path
     IF a ref cannot be resolved THEN
        keep placeholder path           // one bad ref never breaks the bundle
     END IF
     queue referenced files
  END FOR
  → STEP 3 WITH payloads[], media_queue

STEP 3 — BUILD-PARTS
  IN:    payloads[], media_queue (← STEP 2)
  PARALLEL DO                           // legs consume STEP 2 independently
     LEG A (runtime):  copy immersive runtime source files INTO bundle
     LEG B (document): doc ← wall document, row-major (row, col), + scripts
                       IF items < grid cells THEN leave remaining cells empty
     LEG C (manifest): list every file the bundle will contain
     LEG D (readme):   generate README (notes the snapshot caveat)
  END PARALLEL
  → STEP 4 WITH {runtime, doc, manifest, readme}

STEP 4 — ARCHIVE
  IN:    all parts (← STEP 3), media_queue files (← STEP 2)
  archive ← combine(parts + media), named from collection slug
  RETURN archive
```

### Recipe Instructions — Collection download pipeline

1. The system gathers the collection's items in order (artworks and/or media).
2. Each item is turned into a self-describing package: artworks keep their
   code, and media keep their file links.
3. Every image link is rewritten to point inside the download, and the
   referenced files are gathered. If a file cannot be found, a placeholder
   link is kept, so one missing file does not break the whole download.
4. In parallel, the system copies the viewing software, builds a single
   gallery-wall page (leaving blanks if there are fewer items than wall slots),
   lists all included files, and writes a plain-language README.
5. Everything is combined into one downloadable archive named after the
   collection.

### Recipe Diagram — Collection download pipeline

![Collection Download Pipeline Diagram](diagrams/collection_download_pipeline.png)

### Analysis — Collection download pipeline

**Edge cases**
- Mixed collections (pieces + plain media) are handled by the STEP 1 type
  branches.
- Fewer items than grid cells → remaining wall positions stay empty.
- An unresolvable media ref keeps a placeholder path — one bad ref never
  breaks the whole bundle.

**Failure points**
- *Inherent to snapshots:* the bundle reflects piece versions at export time;
  later edits don't propagate (stated in the README).
- *Unbounded input:* the archive grows linearly with media size; there is no
  cap in the assembly step, so the practical limit is server response limits.

**Efficiency**
- O(items + total media bytes). The PARALLEL structure of STEP 3 is logical
  today (execution is sequential) but shows nothing blocks true concurrency.
- The memory high-water mark is the full archive.

**Potential improvements**
- Stream the archive to the client (chunked zip) instead of building it fully
  in memory — removes the size ceiling; needs a streaming zip writer.
- Deduplicate media: a file referenced by several pieces stored once and
  referenced by path — a pure size win.
- Include a "re-export latest" link in the README pointing back to the
  collection URL, softening the snapshot-staleness caveat.

---

## 6. Comments & Reactions

### 6.1 Comment retrieval and shaping — [Comment.php](public/app/models/Comment.php)
- **Type:** Indexed lookup + linear mapping.
- **Logic:** `commentsFor(itemType, itemId)` is an indexed selection sorted by
  time (the sorting is delegated to the database's B-tree — O(log n) seek +
  ordered scan); `toApiPayloadList()` is a linear map to a fixed public shape,
  which is what makes the API output well-defined.

### 6.2 Ownership check — `comment_belongs_to_current_actor()` ([auth.php](public/app/helpers/auth.php))
- **Type:** Predicate (decision procedure).
- **Logic:** Resolve the current actor from whichever session exists (admin or
  user — the unified-auth model), then compare actor identity against the
  comment's stored author fields. Unambiguous boolean output.

### 6.3 Soft-delete / restore lifecycle — `softDelete()` / `restore()` / trash queries
- **Type:** State-machine transition (two states: active, trashed) implemented
  as a `deleted_at` timestamp toggle; all read algorithms filter on it, which
  keeps deletion reversible (supporting Rule 5/7 project constraints).

### Recipe Overview — Comment lifecycle pipeline

This recipe empowers logged-in users to discuss the site's items and empowers
admins to moderate that discussion. It exists to enable conversation while
keeping control and safety: anyone may read, only signed-in people may post,
authors and admins may edit or remove, and "deleting" only hides a comment so
it can be restored — with permanent removal reserved for the admin trash.

### Recipe Pseudocode — Comment lifecycle pipeline

```
RECIPE Comments.Lifecycle
INPUT   actor session (admin OR site-user — unified auth), comment
        text, target (itemType, itemId)
OUTPUT  ordered, shaped comment list per item; reversible trash state

STEP 1 — RESOLVE-ACTOR    current_comment_actor()
  IN:    whichever session exists
  actor ← resolve(session)
  IF actor is null THEN
     ABORT "write path refused"    // read path (STEP 3) stays open to all
  ELSE
     // caller's intent selects the path:
     → STEP 2 (create)  OR  → STEP 4 (edit / delete)   WITH actor
  END IF

STEP 2 — INSERT           insertComment()
  IN:    actor (← STEP 1), text + target (INPUT)
  row ← store(content, attribution = actor, target, timestamp)
  → STEP 3 (row now visible to reads)

STEP 3 — READ             (public path — needs no actor)
  IN:    itemType, itemId
  rows[] ← SELECT WHERE deleted_at IS NULL, time-ordered (DB B-tree)
  RETURN map(rows[] → FIXED public payload shape)  // only intended fields leave

STEP 4 — AUTHORIZE-MUTATION   comment_belongs_to_current_actor()
  IN:    actor (← STEP 1), comment.author fields
  IF actor is the author OR actor is admin THEN
     → STEP 5 WITH requested mutation
  ELSE
     ABORT "refused"
  END IF

STEP 5 — MUTATE           (state machine: active ⇄ trashed)
  IN:    mutation (← STEP 4)
  CASE mutation OF
     edit:       updateContent();  RETURN
     softDelete: set deleted_at;   RETURN   // hidden from all STEP 3 reads
     restore:    clear deleted_at; RETURN   // comes back EXACTLY as it was
     hardDelete: permanently delete (admin trash only);
                 RETURN   // IRREVERSIBLE — the only such transition here
  END CASE
```

### Recipe Instructions — Comment lifecycle pipeline

1. Someone tries to comment. The system identifies who they are from their
   login (admin or regular user). If they are not logged in, they cannot post
   — but anyone can still read.
2. To post, their comment is saved with their name, the item it belongs to,
   and the time.
3. To read, the system fetches all non-deleted comments for that item in time
   order and returns only the intended public fields.
4. To edit or delete, the system checks that the person is either the
   comment's author or an admin; otherwise it refuses.
5. Editing updates the text; "delete" simply hides the comment (and can be
   undone by restoring it); only a permanent delete from the admin trash is
   irreversible.

### Recipe Diagram — Comment lifecycle pipeline

![Comment Lifecycle Pipeline Diagram](diagrams/comment_lifecycle_pipeline.png)

### Analysis — Comment lifecycle pipeline

**Edge cases**
- An author account deleted later → the comment still renders from its
  stored attribution.
- Restoring a trashed comment returns it exactly as it was — the toggle
  touches only `deleted_at`.

**Failure points**
- *Fail-open:* comments table missing on a fresh deployment → the memoized
  `tableExists()` probe makes the feature degrade to "no comments"
  (empty-database convention), no crash.
- Hard delete is the one irreversible transition and is reachable only
  through the admin trash.

**Efficiency**
- Reads are one indexed query + O(n) mapping. Writes are single-row.
- The memoized existence probe costs at most one query per request.

**Potential improvements**
- Paginate STEP 3 for heavily-commented items (currently bounded only by
  item popularity); straightforward LIMIT/OFFSET or keyset pagination.
- Cache the shaped payload list per item with invalidation on STEP 2/5 —
  worth it only if comment reads dominate.
- Aggregate reactions in SQL (GROUP BY) rather than shaping in PHP if counts
  become hot.

---

## 7. Content Management (shared by Blog, Pieces, Pages, Exhibits, Collections)

### 7.1 Slug generation with collision probing — `slugify()`, `unique_*_slug()` ([slugify.php](public/app/helpers/slugify.php))
- **Type:** Normalization + brute-force linear probing.
- **Logic:** Normalize (lowercase, strip non-letter/number/space/hyphen via
  Unicode classes, collapse separators to `-`), then probe `base`, `base-2`,
  `base-3`, … against the table until a free slug is found, excluding the
  record's own id on edit.
- **Characteristics:** Finite in practice (terminates at the first gap; k+1
  queries for k collisions). Deterministic given database state. The
  brute-force approach is chosen deliberately — collision counts are tiny, so
  anything cleverer would fail the feasibility-vs-complexity trade the source
  article describes.

### 7.2 Manual reordering — `reorder_shift_position()` ([reorder.php](public/app/helpers/reorder.php))
- **Type:** Array manipulation (remove-and-splice), i.e. an insertion step of
  insertion sort applied once.
- **Logic:** Load all active ids in current order; clamp the requested
  position to `[0, n−1]`; remove the moved id and splice it at the new index;
  rewrite `sort_order` as sequential integers 0..n−1 (which also
  self-normalizes any drifted ordering).
- **Characteristics:** Well-defined output — the invariant "sort orders are a
  contiguous permutation" holds after every call. O(n) with n writes.

### 7.3 Upload validation — `upload_media()` ([upload.php](public/app/helpers/upload.php))
- **Type:** Sequential decision procedure (validation chain).
- **Logic:** Resolve the *actual* MIME type from file content (not the
  client-supplied one), check it against an allowlist map, enforce a byte
  cap, then move to storage with a generated name. Fail-fast: the first
  failing check terminates with a specific error.
- **3D model branch (`upload_model_media()`):** OBJ/GLTF/GLB are the one case
  where content-sniffing is *unreliable* (`.glb`→`application/octet-stream`,
  `.obj`→`text/plain`, `.gltf`→JSON/text), so they are routed by **file
  extension** against `ALLOWED_MODEL_EXT` and then stored under a **canonical
  `model/*` MIME** — an extension-keyed variant of the same allowlist decision,
  chosen because the sniffed type cannot distinguish these formats. 64 MB cap;
  gated on the `media_models` feature.

### 7.4 Navigation feature-gating — `ah_navigation_apply_feature_gating()` ([navigation.php](public/app/helpers/navigation.php))
- **Type:** Filtering (linear scan with predicate).
- **Logic:** Walk navigation items and drop any whose route maps to a disabled
  feature flag ([features.php](public/app/helpers/features.php)); falls back to
  a hardcoded generic set on an empty database (the "renders sensibly on an
  empty database" project convention).

### Recipe Overview — Path Slug pipeline

This recipe gives every page, post, or item a clean, readable, and permanent
web address derived from its name. It exists so that URLs are human-friendly and
never collide: it strips a name down to a safe form and, if that address is
already taken, appends a number until it finds a free one — while leaving an
item's existing address untouched when it is re-saved.

### Recipe Pseudocode — Path Slug pipeline

```
RECIPE ContentMgmt.Slug
INPUT   human-entered name (any Unicode); record's own id when editing
OUTPUT  unique URL-safe slug — a PUBLIC URL, therefore permanent
        under Rule 5 (never regenerated retroactively)

STEP 1 — NORMALIZE      slugify()
  IN:    name (INPUT)
  base ← name
         |> lowercase
         |> strip all but letters/numbers/spaces/hyphens (Unicode classes)
         |> collapse spaces/underscores + hyphen runs → single '-'
         |> trim edge hyphens
  → STEP 2 WITH base

STEP 2 — PROBE          unique_*_slug()
  IN:    base (← STEP 1), own id (INPUT)
  candidate ← base;  i ← 2
  WHILE candidate exists for a DIFFERENT id DO
     candidate ← base + '-' + i
     i ← i + 1
  END WHILE                         // k+1 queries for k collisions
  RETURN candidate                  // own id excluded → re-save keeps slug
```

### Recipe Instructions — Path Slug pipeline

1. Someone enters a name for a page, post, or other item.
2. The system converts it to a clean web address: made lowercase, reduced to
   letters, numbers, and hyphens, with spaces turned into hyphens and stray
   hyphens trimmed off the ends.
3. It checks whether that address is already taken by a different item. If it
   is, it tries the same address with "-2", then "-3", and so on until it
   finds a free one.
4. The free address is used. (When editing an existing item, that item's own
   address does not count as "taken," so saving again keeps it.)

### Recipe Diagram — Path Slug pipeline

![Path Slug Pipeline Diagram](diagrams/path_slug_pipeline.png)

### Analysis — Path Slug pipeline

**Edge cases**
- A name made entirely of stripped characters (only punctuation) normalizes
  to an empty base — the probe still runs but yields a degenerate slug; the
  admin form's required-name validation is the practical guard.

**Failure points**
- *Race:* probe-then-insert is not atomic → two simultaneous saves of the
  same name can race; the database unique index is the backstop, turning the
  race into a visible save error rather than duplicate URLs.

**Efficiency**
- One query per probe; collisions are rare in practice, so the expected cost
  is ~1 query.

**Potential improvements**
- Close the race properly: attempt the INSERT and on duplicate-key error
  increment and retry (probe-by-insert) — atomicity without locks; slightly
  more complex error handling.
- Single-query probe: SELECT all slugs `LIKE 'base%'` once and pick the
  first gap in memory — k collisions in 1 query instead of k+1.

### Recipe Overview — Content Upload pipeline

This recipe empowers users to add images and videos to the site safely. It
exists to accept media while keeping the site secure: it reports precisely what
went wrong when an upload fails, identifies a file's true type by inspecting its
contents rather than trusting its name, rejects anything not on the allowed
list or over the size limit, and returns a stable web address for what it
stores.

### Recipe Pseudocode — Content Upload pipeline

```
RECIPE ContentMgmt.Upload
INPUT   browser file upload ($_FILES entry) + optional attributes
OUTPUT  media record at a stable /media/{id} URL

STEP 1 — ERROR-TRIAGE
  IN:    PHP upload error code (INPUT)
  IF code ≠ UPLOAD_ERR_OK THEN
     ABORT specific mapped message (size / partial / no file / no tmp dir …)
           + server's actual upload_max_filesize + post_max_size
           // so the admin sees WHICH limit bit them
  ELSE
     → STEP 2
  END IF

STEP 2 — DETECT-MIME    upload_resolve_mime()
  IN:    tmp file (← STEP 1)
  mime ← finfo(file bytes)          // client-declared type NEVER trusted
  IF mime undetectable THEN
     ABORT "type could not be detected"
  ELSE
     → STEP 3 WITH mime
  END IF

STEP 3 — ROUTE          upload_media_auto()
  IN:    mime (← STEP 2), file extension, media_models feature flag
  IF media_models enabled AND ext ∈ {glb, gltf, obj} THEN
     // 3D models: sniffed MIME is unreliable, so route by EXTENSION and
     // store a canonical model/* MIME.  cap ← 64 MB   (upload_model_media)
     rules ← model ext-allowlist;  cap ← 64 MB
  ELSE IF mime ∈ {mp4, webm, mov} THEN
     rules ← video allowlist;  cap ← 64 MB
  ELSE
     rules ← image allowlist (jpg/png/gif/webp/avif);  cap ← 8 MB
  END IF
  → STEP 4 WITH rules, cap

STEP 4 — ALLOWLIST + SIZE
  IN:    mime (← STEP 2), rules + cap (← STEP 3), file bytes
  IF mime ∉ rules THEN     ABORT "type not permitted"
  ELSE IF bytes > cap THEN  ABORT "exceeds the upload limit"
  ELSE                      → STEP 5
  END IF

STEP 5 — STORE
  IN:    blob, mime, original basename, attributes
  TRY raise session max_allowed_packet   // best-effort; failure swallowed
  id ← MediaFile::create(blob, mime, basename, attributes)
  RETURN {id, mime_type: mime, url: '/media/' + id}
```

### Recipe Instructions — Content Upload pipeline

1. Someone uploads a file.
2. The system first checks for upload errors and, if there is a problem,
   explains exactly which limit or issue caused it.
3. It determines the file's real type by inspecting its contents — not by
   trusting its name or extension. (3D models are the exception: because their
   real type cannot be told apart by inspection, they are recognized by their
   `.glb`/`.gltf`/`.obj` extension and then stored under a standard 3D-model
   type.)
4. Videos and 3D models are allowed up to 64 MB; images up to 8 MB.
5. If the type is not on the allowed list, or the file is too big, it is
   rejected with a clear message.
6. Otherwise the file is stored, and the uploader gets back a permanent web
   address for it.

### Recipe Diagram — Content Upload pipeline

![Content Upload Diagram](diagrams/content_upload_pipeline.png)

### Analysis — Content Upload pipeline

**Edge cases**
- A spoofed extension (renamed `.exe`) is classified by its real bytes at
  STEP 2 and rejected at STEP 4 — never by filename.
- A failed upload still routes through the image path purely to produce its
  specific STEP 1 error message.

**Failure points**
- *Fail-closed:* every check aborts the request with a message; the only
  fail-open step is the packet-size raise inside STEP 5, whose failure is
  swallowed because the insert may still succeed for smaller files.

**Efficiency**
- One `finfo` read + one full blob read; the blob is held in memory once
  (the STEP 4 size check reuses it for STEP 5).

**Potential improvements**
- Store media on disk/object storage with a DB pointer instead of a DB blob —
  removes the `max_allowed_packet` coupling and shrinks backups; a schema +
  serving-path migration (Rule 3 territory).
- Stream-hash the upload to detect duplicate media at STEP 5 and reuse the
  existing id; saves storage on repeat uploads.

### Recipe Overview — Content Reordering pipeline

This recipe empowers editors to arrange items — artworks, collections, menu
entries, and the like — into whatever order they choose. It exists so that
presentation order is under human control and stays consistent: it moves an
item to its new place and then renumbers the whole list cleanly, which also
quietly repairs any gaps or inconsistencies that had crept into the ordering.

### Recipe Pseudocode — Content Reordering pipeline

```
RECIPE ContentMgmt.Reorder
INPUT   item id, requested 0-indexed position, target table
        (fixed set of orderable tables)
OUTPUT  contiguous, gap-free sort_order (0..n−1) — repairs
        pre-existing drift as a side effect

STEP 1 — LOAD
  ids[] ← all non-deleted ids IN current sort_order
  → STEP 2 WITH ids[]

STEP 2 — LOCATE
  IN:    item id (INPUT), ids[] (← STEP 1)
  oldIdx ← index of id IN ids[]
  IF oldIdx not found THEN
     RETURN                          // silent no-op (e.g. trashed mid-edit)
  ELSE
     → STEP 3 WITH oldIdx
  END IF

STEP 3 — CLAMP
  IN:    requested position (INPUT), n = |ids[]|
  newPos ← max(0, min(n−1, requested))   // out-of-range can't scatter order
  IF newPos = oldIdx THEN
     → STEP 5                         // normalize only — no move needed
  ELSE
     → STEP 4 WITH newPos
  END IF

STEP 4 — SPLICE
  IN:    ids[], oldIdx, newPos (← STEP 2–3)
  remove ids[oldIdx];  insert id AT newPos
  → STEP 5 WITH reordered ids[]

STEP 5 — REWRITE
  IN:    ids[] (← STEP 4, or ← STEP 1 if normalizing)
  FOR EACH (index, id) IN ids[] DO
     UPDATE sort_order ← index WHERE row = id
  END FOR
  RETURN            // invariant restored: sort orders are 0..n−1 contiguous
```

### Recipe Instructions — Content Reordering pipeline

1. An editor moves an item to a new position in a list.
2. The system loads the current order of all items.
3. If the moved item is not found (for example, it was just deleted), it
   quietly does nothing.
4. It keeps the requested position within valid bounds.
5. It removes the item from its old spot and inserts it at the new spot.
6. It renumbers every item's position from the top, so the ordering stays
   clean and gap-free — which also tidies up any earlier inconsistencies.

### Recipe Diagram — Content Reordering pipeline

![Content Reordering Diagram](diagrams/content_reordering_pipeline.png)

### Analysis — Content Reordering pipeline

**Edge cases**
- The moved item was trashed mid-edit → silent no-op at STEP 2.
- A same-position move still runs STEP 5, normalizing any drifted ordering.

**Failure points**
- *Transient:* STEP 5 is n individual UPDATEs, not one transaction → a
  mid-loop crash leaves a partially rewritten order; the next successful
  reorder renormalizes everything — the damage is temporary display order,
  never data loss.

**Efficiency**
- O(n) reads + O(n) writes per move — n UPDATE round trips is the dominant
  cost.

**Potential improvements**
- Wrap STEP 5 in a transaction: removes the partial-rewrite window at
  near-zero cost. The cheapest correctness upgrade in this file.
- Only rewrite rows whose sort_order actually changed (the slice between
  oldIdx and newPos) — cuts writes from n to |newPos − oldIdx|.
- Fractional ranks (midpoint keys) would make a move O(1), at the cost of
  periodic renormalization — overkill at current n.

---

## 8. Syndication (outbound to social/blog platforms) — [lib/syndication](public/app/lib/syndication)

### 8.1 Platform-specific post composition — `buildSocialPostText()` ([ContentHelpers.php](public/app/lib/syndication/ContentHelpers.php))
- **Type:** Constrained assembly (greedy truncation under a budget).
- **Logic:** Compose title + excerpt + hashtags + canonical URL under each
  platform's character limit, truncating the flexible parts (excerpt) first so
  the mandatory parts (URL, attribution) always survive — a greedy
  priority-ordered allocation. `ensureCanonicalUrl()` post-checks that the
  canonical link is present and appends it if truncation removed it.

### 8.2 Adapter dispatch — `AdapterFactory` + per-platform adapters
- **Type:** Strategy selection (table lookup).
- **Logic:** Map connection provider → adapter class; each adapter transforms
  the shared `SyndicationPayload` into that platform's API calls. The shared
  payload is the well-defined input contract that keeps every adapter's
  behavior comparable.

### Recipe Overview — Content Syndication pipeline

This recipe empowers an admin to reach audiences on outside platforms
automatically whenever a post is published. It exists to broaden a single
post's reach without extra manual effort: it tailors the wording to each
platform's length rules, always keeps the link back to the original, posts to
every connected account independently, and records each result so one failed
platform never blocks the rest.

### Recipe Pseudocode — Content Syndication pipeline

```
RECIPE Syndication.Publish
INPUT   published post (title, HTML content, category slugs, featured
        image), canonical URL, connected accounts (each with an
        encrypted OAuth token)
OUTPUT  platform posts always carrying the canonical back-link;
        per-connection delivery record in the admin

STEP 1 — PAYLOAD-BUILD
  IN:    post + canonical URL (INPUT)
  payload ← normalize(post) INTO shared SyndicationPayload
  payload.hashtags ← category slugs WITH hyphens removed
  payload.footer   ← buildSourceFooter()   // HTML + plain text
  → STEP 2 WITH payload

STEP 2 — FOOTER-DECISION   shouldAppendSourceFooter()
  IN:    post.source_feed_id (INPUT)
  IF post.source_feed_id is empty THEN
     keep payload.footer            // authored here
  ELSE
     drop payload.footer            // feed-imported → don't claim as origin
  END IF
  → STEP 3 WITH payload

STEP 3 — PER-CONNECTION DELIVERY
  IN:    payload (← STEP 2), connected accounts (INPUT)
  FOR EACH connection IN accounts DO   // independent; logically parallel,
                                       // sequential today; one failure
                                       // never touches another
     // ---- 3a COMPOSE   buildSocialPostText() ----
     limit ← platform limit (bluesky 300 | instagram 2200 |
             linkedin 3000 | facebook ~63k | unknown → 300)
     text  ← allocate budget IN PRIORITY ORDER:
             reserve URL + hashtags first; truncate excerpt to the rest

     // ---- 3b URL-GUARANTEE   ensureCanonicalUrl() ----
     IF canonical URL ∈ text THEN
        keep text
     ELSE IF text + URL fits limit THEN
        text ← text + URL          // re-truncate body as needed
     ELSE
        text ← truncate(URL)       // only imperfect case — pathological input
     END IF

     // ---- 3c DISPATCH   AdapterFactory ----
     adapter ← provider → adapter class
     token   ← decrypt(connection.token)   // RECIPE Security.Secrets
     IF platform = bluesky THEN
        text.card ← link-card {source: URL, title (fallback host), description}
     END IF
     result ← adapter.call_platform_api(text, token)
     // failure (expired token / API change / rate limit) is isolated here

     // ---- 3d RECORD ----
     write result (success | failure) TO post's syndication state
  END FOR
  RETURN delivery summary
```

### Recipe Instructions — Content Syndication pipeline

1. When a post is published, the system prepares a shared package of it,
   turning its categories into hashtags and preparing a source-credit footer.
2. If the post was imported from another site's feed, the footer is removed so
   this site does not claim to be the original source.
3. For each connected platform account, the system: writes a version of the
   post that fits that platform's length limit (always keeping the link and
   hashtags), makes sure the original link is present, and then posts it using
   that platform's connection — unlocking the stored access token first.
4. Each platform is handled independently, so a failure on one (for example,
   an expired login) is recorded but never blocks the others.

### Recipe Diagram — Content Syndication pipeline

![Content Syndication Pipeline Diagram](diagrams/content_syndication_pipeline.png)

### Analysis — Content Syndication pipeline

**Edge cases**
- Composed text ends up empty → degrades to just the canonical URL.
- Budgets use byte-length `strlen` → multi-byte content truncates *more*
  conservatively than required — never over-limit, sometimes shorter than
  necessary.
- A canonical URL longer than the entire platform limit is itself truncated —
  the only case the back-link can be imperfect, caused by pathological input.

**Failure points**
- *Fail-open, per leg:* any platform failure (expired token, API change,
  platform rate limit) is recorded for that connection only and never blocks
  delivery to the others; token expiry routes the admin back through
  `Security.OAuth`.
- *Vendor dependency:* a platform changing its API breaks only its own
  adapter — the isolation the adapter pattern buys.

**Efficiency**
- Composition is O(text) per platform. Delivery legs are logically parallel
  but execute sequentially today — wall time is the sum of all platform API
  latencies.

**Potential improvements**
- Execute STEP 3 legs concurrently (or queue them as background jobs with
  per-leg retry) — publish latency drops to the slowest platform instead of
  the sum; needs a job runner.
- Count budgets in `mb_strlen` (or platform-specific grapheme rules) to stop
  over-truncating multi-byte content; a small correctness win.
- Automatic token refresh in STEP 3c where the provider supports it, before
  surfacing a delivery failure.

---

## 9. Security & Infrastructure (supports every function above)

### 9.1 Authenticated encryption — `encrypt_string()` / `decrypt_string()` ([encryption.php](public/app/helpers/encryption.php))
- **Type:** Cryptographic algorithm (AES-256-GCM via OpenSSL).
- **Logic:** Random 12-byte IV per encryption + authenticated cipher with a
  16-byte tag; output is `base64(iv).base64(tag).base64(ciphertext)`.
  Decrypt branches on format: two dots → current format; otherwise the
  legacy colon-separated format, with a second-chance key derivation
  (SHA-256 of the raw env value) for secrets written under the old scheme.
- **Characteristics:** Per the source article, cryptographic algorithms trade
  compute for security; the randomness (IV) means ciphertexts differ per run
  while decryption remains deterministic — well-defined round-trip output.
  GCM's tag verification makes tampering a detected failure, not silent
  corruption.

### 9.2 Fixed-window rate limiting — `rate_limit_consume()` ([rate-limit.php](public/app/helpers/rate-limit.php))
- **Type:** Counting algorithm over fixed time windows.
- **Logic:** Window start = `floor(now / windowSeconds) · windowSeconds`
  (aligning all requests in a window to one bucket key); an atomic
  `INSERT … ON DUPLICATE KEY UPDATE` increments the bucket counter; allow iff
  `count ≤ max` for the scope's rule; `retry_after` = time to window end.
  Expired buckets are garbage-collected opportunistically. Fails **open**
  (allows) if the table is missing or errors — availability over strictness.
- **Characteristics:** O(1) per request; the atomic upsert removes the
  read-modify-write race, which is what makes the output well-defined under
  concurrency.

### 9.3 Privacy-preserving subject hashing — `operational_subject_hash()` ([audit-log.php](public/app/helpers/audit-log.php))
- **Type:** Keyed cryptographic hashing (HMAC-style).
- **Logic:** Hash `scope + subject` with a server-side secret seed so rate
  limits and audit trails can correlate a subject across requests without
  storing raw IPs/identities. One-way; deterministic per seed.

### 9.4 Audit-log redaction — `audit_log_redact_value()` / `audit_log_redact_array()`
- **Type:** Recursive traversal with a key-pattern denylist.
- **Logic:** Walk metadata arrays recursively; values under sensitive keys
  (tokens, secrets, passwords) are replaced with placeholders before storage.
  Finite because the input structure is finite and acyclic.

### 9.5 OAuth authorization-code flow — [oauth.php](public/app/helpers/oauth.php), [SharedAuthController.php](public/app/controllers/SharedAuthController.php)
- **Type:** Multi-step protocol (distributed state machine).
- **Logic:** Generate anti-CSRF `state` → redirect to provider → verify
  returned `state` (exact-match comparison) → exchange code for token →
  fetch profile → `oauth_allowed_identity()` allowlist check → establish
  session. Each transition validates its inputs before proceeding — the
  unambiguity characteristic applied to a protocol rather than a computation.

### 9.6 Schema convergence probing — [setup-database.php](scripts/setup-database.php), `*_table_exists()` helpers
- **Type:** Idempotent migration (probe-then-apply per manifest step).
- **Logic:** For each manifest step, probe current schema state
  (`INFORMATION_SCHEMA` lookups) and apply the step only if absent, so
  `git pull && php scripts/setup-database.php --yes` converges any deployment
  to the same schema regardless of starting state. Runtime helpers use the
  same probe pattern (memoized per request) to degrade gracefully when a
  table hasn't been created yet.
- **Characteristics:** Idempotence = determinism of the *final state* rather
  than of the actions taken; finite (manifest is a fixed list).

### Recipe Overview — Secret storage pipeline (encryption)

This recipe protects the sensitive credentials the site depends on — AI keys
and platform login tokens — so they are never exposed even if the database is
read. It exists to keep those secrets usable but unreadable at rest: it
scrambles each one with strong encryption and a fresh random value, verifies a
secret hasn't been tampered with before ever handing it back, and refuses
rather than returning anything corrupted.

### Recipe Pseudocode — Secret storage pipeline (encryption)

```
RECIPE Security.Secrets
INPUT   plaintext secret (AI vendor key, OAuth token) +
        AI_SETTINGS_ENCRYPTION_KEY env value
OUTPUT  ciphertext at rest that round-trips deterministically;
        plaintext exists only in memory at the consumer

STEP 1 — DERIVE-KEY     ai_encryption_key()
  IN:    env value (falls back to the older PLATFORM_-prefixed name)
  // try encodings in order; first that yields 32 bytes wins
  IF env is 64-char hex THEN            key ← hex2bin(env)
  ELSE IF base64(env) is 32 bytes THEN  key ← base64_decode(env)
  ELSE IF length(env) = 32 THEN         key ← env
  ELSE
     ABORT "must decode to exactly 32 bytes"   // fail-CLOSED before any write
  END IF
  → STEP 2 (write path)  OR  STEP 3 (read path)   WITH key

STEP 2 — ENCRYPT        encrypt_string()        (write path)
  IN:    plaintext (caller), key (← STEP 1)
  iv ← random_bytes(12)                          [NON-DETERMINISTIC]
  (ciphertext, tag) ← AES-256-GCM(plaintext, key, iv)   // tag = 16 bytes
  RETURN base64(iv) + '.' + base64(tag) + '.' + base64(ciphertext)

STEP 3 — DECRYPT        decrypt_string()         (read path)
  IN:    stored ciphertext, key (← STEP 1)
  IF dot count = 2 THEN                           // current format
     (iv, tag, ct) ← base64_decode(parts)
     plaintext ← AES-256-GCM-decrypt(ct, key, iv, tag)   // tag verified first
     IF tag valid THEN RETURN plaintext ELSE ABORT "tampered ciphertext"
  ELSE                                            // legacy colon format
     plaintext ← try_decrypt(current key)
     IF tag valid THEN
        RETURN plaintext
     ELSE
        plaintext ← try_decrypt( sha256(raw env value) )   // legacy key
        IF tag valid THEN RETURN plaintext ELSE ABORT "decryption failed"
     END IF
  END IF
```

### Recipe Instructions — Secret storage pipeline (encryption)

1. When a secret (such as an AI key or a platform login token) needs to be
   stored, the system first prepares the encryption key from a setting,
   accepting a few common formats. If the setting is missing or malformed, it
   stops before storing anything.
2. To store a secret, it scrambles it with strong encryption, using a fresh
   random value each time, so the stored form is unreadable and looks
   different every time.
3. To read a secret back, it unscrambles the stored value, verifying it has
   not been tampered with before handing it over; older-format secrets are
   handled by a fallback. If verification fails, it refuses rather than
   returning corrupted data.
4. Note: if the encryption key is ever changed without re-scrambling the
   existing secrets, those secrets can no longer be recovered.

### Recipe Diagram — Secret storage pipeline (encryption)

![Secret Storage Pipeline Diagram](diagrams/secret_storage_pipeline.png)

### Analysis — Secret storage pipeline (encryption)

**Edge cases**
- Legacy-format secrets decrypt via the fallback chain — no migration step
  was ever needed.
- The key is accepted in three encodings (hex, base64, raw 32 bytes), tried
  in a fixed order, so deployments can configure it whichever way their
  tooling prefers.

**Failure points**
- *Fail-closed:* a missing or malformed env key throws before any secret is
  written; a tampered or truncated ciphertext fails GCM tag verification and
  throws — garbage is never returned.
- *Undetectable:* rotating the env key without re-encrypting stored secrets
  makes them unrecoverable — the one operational hazard this design cannot
  detect.

**Efficiency**
- O(secret length); GCM is hardware-accelerated on modern CPUs. Key
  derivation is memoizable per request.

**Potential improvements**
- Key versioning: prefix ciphertexts with a key id so rotation becomes
  decrypt-with-old / re-encrypt-with-new instead of unrecoverable loss — the
  highest-value upgrade here.
- A one-time migration re-encrypting legacy-format secrets would let the
  legacy branch (and its extra failed-decrypt attempt on every legacy read)
  be deleted.

### Recipe Overview — Rate-limit gate pipeline

This recipe protects costly or abusable actions — AI requests, login attempts,
contact-form submissions — from being overused. It exists to cap spending and
deter abuse while respecting privacy: it counts how often each subject performs
an action within a time window (using a one-way identifier rather than raw
identities) and turns them away with a "try again later" once they exceed the
limit for that action.

### Recipe Pseudocode — Rate-limit gate pipeline

```
RECIPE Security.RateGate
INPUT   scope name (e.g. 'ai_generate_piece') + subject: admin
        identity id if logged in, else request fingerprint — both
        reduced to a KEYED HASH (no raw IP/identity stored)
OUTPUT  allow/deny + retry_after; consumed by AI endpoints, OAuth
        starts, contact form

STEP 1 — RULE           rate_limit_rule(scope)
  IF scope is known THEN rule ← its config (e.g. 4 / 900 s)
  ELSE                   rule ← default 10 / 300 s
  END IF
  → STEP 2 WITH rule

STEP 2 — BUCKET
  IN:    rule.window (← STEP 1), now
  window_start ← floor(now / window) · window    // one bucket key per window
  → STEP 3 WITH window_start

STEP 3 — GC + COUNT
  IN:    scope, subject_hash, window_start (← STEP 2)
  TRY
     DELETE buckets older than 2 days                // opportunistic GC
     atomic INSERT … ON DUPLICATE KEY UPDATE count+1  // removes RMW race
     count ← SELECT count back
  CATCH table missing OR any DB error
     RETURN allowed = true          // deliberate fail-OPEN
  END TRY
  → STEP 4 WITH count

STEP 4 — DECIDE
  IN:    count (← STEP 3), rule.max (← STEP 1)
  IF count ≤ max THEN
     RETURN allowed
  ELSE
     RETURN denied, retry_after = window_end − now
  END IF
```

### Recipe Instructions — Rate-limit gate pipeline

1. Before allowing a sensitive action, the system looks up how many times it
   is allowed within a set time window (with a default for anything unlisted).
2. It groups the current moment into a fixed time window.
3. It counts how many times this person has done this action within that
   window, clearing out old records as it goes.
4. If the count is within the limit, the action is allowed; otherwise it is
   denied with a "try again later" time.
5. Note: if the counting system is unavailable, the gate deliberately allows
   the action rather than blocking people — availability is chosen over strict
   enforcement.

### Recipe Diagram — Rate-limit gate pipeline

![Rate-Limit Gate Pipeline Diagram](diagrams/rate-limit_gate_pipeline.png)

### Analysis — Rate-limit gate pipeline

**Edge cases**
- Fixed-window boundary → up to 2× the nominal rate across the seam (burst
  at the end of one window plus the start of the next); accepted as the
  standard trade-off for O(1) bookkeeping.

**Failure points**
- *Fail-open, deliberate:* STEP 3's catch-all returns *allowed* on a missing
  table or any DB error — a half-migrated deployment loses throttling, not
  functionality; the schema-convergence script (§9.6) closes the gap.

**Efficiency**
- O(1) per request: one upsert + one select (+ the opportunistic delete).
  No per-request table scans.

**Potential improvements**
- Sliding window (store the previous window's count and interpolate) to kill
  the 2× boundary burst — one extra column, same O(1).
- Move the GC delete to a cron instead of every request — removes a write
  from the hot path.
- Return the count from the upsert itself (`LAST_INSERT_ID` trick) to drop
  the follow-up SELECT — one round trip instead of two.

### Recipe Overview — OAuth login pipeline

This recipe empowers admins and users to sign in through an outside provider
(such as Google) instead of managing another password. It exists to make
sign-in both convenient and safe: it guards each step against forged logins,
confirms the returned identity is on the allowed list before creating a
session, and — because admin and public access are unified — grants access to
both sides of the site with one login.

### Recipe Pseudocode — OAuth login pipeline

```
RECIPE Security.OAuth
INPUT   visitor's provider-login click; provider env config (client
        id/secret, redirect URI); later, the provider's callback
        parameters (code, state)
OUTPUT  authenticated session — or a specific refusal at whichever
        step failed (every transition is fail-CLOSED)

STEP 1 — START
  IN:    provider choice (INPUT)
  IF RECIPE Security.RateGate('admin_oauth_start', 8 / 5 min) = denied THEN
     ABORT "rate limited"
  END IF
  state ← anti-CSRF token;  session.state ← state
  redirect → provider authorize URL
  → STEP 2   // when the provider calls back; control leaves this system
             // in between, and the session carries the state

STEP 2 — CALLBACK
  IN:    code + returned state (provider), session.state (session)
  IF RECIPE Security.RateGate('admin_oauth_callback', 12 / 5 min) = denied THEN
     ABORT
  END IF
  IF returned state ≠ session.state THEN
     ABORT "no session"             // CSRF or a stale tab
  END IF
  token ← exchange(code)
  IF exchange fails THEN ABORT "no session" END IF
  profile ← fetch_identity(token)
  → STEP 3 WITH profile

STEP 3 — ALLOWLIST      oauth_allowed_identity()
  IN:    profile (← STEP 2)
  IF profile ∉ configured identities THEN
     ABORT "no session"             // a valid provider login is NOT sufficient
  ELSE
     → STEP 4 WITH profile
  END IF

STEP 4 — SESSION
  IN:    verified identity (← STEP 3)
  admin_login_identity() / user_login()   // admin session = site-wide login
                                          // (unified auth: covers both surfaces)
  RETURN authenticated session
```

### Recipe Instructions — OAuth login pipeline

1. A visitor clicks to log in with an outside provider (for example, Google).
2. After a quick abuse check, the system creates a one-time security token and
   sends the visitor to the provider.
3. When the provider sends them back, the system verifies that the security
   token matches (guarding against forged logins), then exchanges the
   provider's code for an access token and fetches the person's profile.
4. It checks that the profile is on the list of allowed identities — a valid
   outside login on its own is not enough.
5. If allowed, it logs them in; a single login covers both the admin and
   public sides of the site.
6. Any failed step simply stops with no login created.

### Recipe Diagram — OAuth login pipeline

![OAuth Login Pipeline Diagram](diagrams/oauth_login_pipeline.png)

### Analysis — OAuth login pipeline

**Edge cases**
- Local development: `oauth_is_local_request()` permits localhost redirect
  URIs; `oauth_debug_detail()` surfaces provider error detail *only* in that
  local context.

**Failure points**
- *Fail-closed, every transition:* mismatched state (CSRF or a stale tab), a
  failed token exchange, or an off-allowlist identity each stop the flow
  with no session created.
- *External:* provider-side config changes surface as token-exchange
  failures at STEP 2.

**Efficiency**
- Two provider round trips (token exchange + profile fetch) dominate; local
  work is O(1).

**Potential improvements**
- PKCE (code_verifier/challenge) alongside `state` — hardens the code
  exchange at negligible cost; providers widely support it.
- Combine token + profile into one round trip where the provider returns an
  id_token (OIDC) — halves external latency.

---

## 10. Site Theme AI Generation ([site-theme-generation.php](public/app/helpers/site-theme-generation.php))

- **Type:** Same algorithm family as §3: randomized LLM generation inside a
  bounded validate-repair loop, plus the SEARCH/REPLACE patch matcher
  (`site_theme_find_patch_match()` mirrors §3.3) for refinements.
- **Difference:** The preflight (`site_theme_preflight()`) validates a
  CSS/JS/HTML triple destined for site-wide chrome rather than a sandboxed
  iframe, so its acceptance predicate is stricter about global side effects.

### Recipe Overview — AI Site theme generation pipeline

This recipe empowers an admin to restyle the entire site simply by describing
the look they want, letting the AI produce the styling and code. It exists to
let a non-coder redesign safely and reversibly: because a theme affects the
whole site rather than an isolated preview, every result is checked for safety
before it is applied, and each accepted theme is saved as a new version so the
previous one can always be restored.

### Recipe Pseudocode — AI Site theme generation pipeline

```
RECIPE SiteTheme.Generate
INPUT   admin's theme description prompt; for refinement, also the
        currently active theme's CSS/JS/HTML triple
OUTPUT  validated, versioned site theme; the prior theme is never
        destroyed by activation

STEP 1 — AI-CALL                                          [NON-DETERMINISTIC]
  IN:    theme prompt (or repair_prompt ← STEP 4)
  attempt ← attempt or 1
  raw_response ← call_provider()
  → STEP 2 WITH raw_response

STEP 2 — EXTRACT        site_theme_extract_code_blocks()
  IN:    raw_response (← STEP 1)
  triple ← extract CSS / JS / HTML
  IF triple incomplete THEN
     → STEP 4 WITH failure
  ELSE
     → STEP 3 WITH triple
  END IF

STEP 3 — PREFLIGHT      site_theme_preflight()
  IN:    triple (← STEP 2, or ← STEP 5 patched triple)
  // runs IN the site's OWN chrome, not a sandboxed iframe → load-bearing
  IF preflight fails THEN
     → STEP 4 WITH error
  ELSE
     → STEP 6 WITH triple
  END IF

STEP 4 — REPAIR-OR-STOP
  IN:    error (← STEP 2/3), previous response, attempt
  IF attempt < cap THEN
     repair_prompt ← build(error + previous response);  attempt ← attempt + 1
     → STEP 1 WITH repair_prompt            // loop back-edge
  ELSE
     ABORT "cap reached; active theme untouched"
  END IF

STEP 5 — REFINE         (alternate entry: patching an existing theme)
  IN:    current triple (INPUT), patches from the AI response
  apply matcher as RECIPE ArtPiece.Refine STEP 3 (all-or-nothing)
  IF all patches matched THEN
     → STEP 3 WITH patched triple           // re-preflight
  ELSE
     → STEP 4 WITH rejection
  END IF

STEP 6 — SNAPSHOT
  IN:    accepted triple (← STEP 3)
  version ← store(SiteThemeSnapshot)   // previous theme remains restorable
  RETURN version
```

### Recipe Instructions — AI Site theme generation pipeline

1. An admin describes a look for the whole site.
2. The AI is asked to produce the theme's styling and code.
3. The result is pulled out and checked for safety — and because a theme
   affects the entire site (not an isolated preview), this check is especially
   important.
4. If it fails and attempts remain, the AI is told what went wrong and tries
   again; otherwise the current theme is left untouched.
5. When refining an existing theme, the edits are matched against the real
   current theme (all-or-nothing) and the result is re-checked before saving.
6. An accepted theme is saved as a new version, and the previous theme is kept
   so it can be restored.

### Recipe Diagram — AI Site theme generation pipeline

![Site Theme Generation Pipeline Diagram](diagrams/ai_site_theme_generation_pipeline.png)

### Analysis — AI Site theme generation pipeline

**Edge cases**
- Refinement re-enters the main flow at STEP 3: a patched triple is
  re-preflighted before it can ever be stored.

**Failure points**
- Same shape as §3: non-determinism is contained by validator + retry cap;
  ambiguous patches abort rather than guess, and a failed run leaves the
  active theme untouched.
- *Residual:* preflight is static → a theme can validate yet misbehave
  visually at runtime; snapshot history is the recovery path, keeping even
  that failure reversible.

**Efficiency**
- Same profile as ArtPiece.Generate — the AI call dominates; everything
  local is O(code length).

**Potential improvements**
- Same as ArtPiece.Generate (report all preflight failures at once; persist
  attempt history), plus:
- A staged "preview" activation rendering the candidate theme in an isolated
  preview session before it touches live chrome — converts the residual
  static-validation risk from restore-after to catch-before.

---

## Summary by algorithm type (per the source article's taxonomy)

| Type | Where used |
|---|---|
| Brute-force | Slug collision probing (§7.1), excerpt truncation (§2.1) |
| Searching | FULLTEXT/LIKE retrieval (§1.2), snippet term scan (§1.3), patch matching (§3.3, §10), GUID dedup (§2.2), raycasting (§4.4) |
| Sorting | Delegated to DB indexes (§6.1); single insertion step in reorder (§7.2) |
| Recursive | Redaction traversal (§9.4), document assembly (§3.4) |
| Greedy | Live-render budgeting (§4.5), post-text truncation under budget (§8.1), LIKE-branch trade-off (§1.2) |
| Randomized | LLM generation (§3.1, §10), crypto IVs (§9.1) |
| Divide and combine | Collection bundle assembly (§5.1) |
| Counting / windowing | Rate limiting (§9.2) |
| Geometric (closed-form) | Gallery layout, camera fit, letterboxing (§4.1–4.6) |
| Protocol / state machine | OAuth flow (§9.5), soft-delete lifecycle (§6.3), schema convergence (§9.6) |
