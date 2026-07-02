<?php

declare(strict_types=1);

const SITE_THEME_MAX_ATTEMPTS = 5;
const SITE_THEME_CODE_MAX_BYTES = 204800; // 200 KB per file

// ─── System prompts ───────────────────────────────────────────────────────────

function site_theme_generation_system_prompt(): string
{
    return implode(' ', [
        'You generate site-wide CSS, JavaScript, and background HTML for a PHP CMS.',
        'Return exactly three Markdown code blocks: ```css, ```javascript, and ```html.',
        'Return ONLY those three fenced blocks — no prose, bullets, or explanations.',

        'CSS rules: all styles must be scoped to [data-layout-theme] selectors or named',
        '#celestial-background / .nebula-wash / .astrolabe-grid / #cosmos-stars / .cosmos-star',
        'identifiers so they do not bleed into the default theme. Do NOT use !important except',
        'inside @media (prefers-reduced-motion: reduce) or .low-power overrides.',
        'Include a @media (prefers-reduced-motion: reduce) block that removes all animations.',
        'Include a .low-power variant that disables animations without hiding visual elements.',

        'JS rules: wrap ALL code in an IIFE — (function(){ ... })();',
        'Skip execution on admin pages: if (document.body.classList.contains("admin-body")) return;',
        'Check and respect window.matchMedia("(prefers-reduced-motion: reduce)").matches.',
        'Do NOT use fetch(), localStorage, sessionStorage, or document.write.',
        'Do NOT use ES module syntax (import/export). Vanilla ES5/ES6 IIFE only.',
        'Animations MUST be infinite — never run once and leave a blank canvas.',

        'HTML rules: provide ONLY the content that goes directly inside <body>, immediately after',
        'the opening <body> tag. Use position:fixed elements with aria-hidden="true" for',
        'background overlays. Do not include <html>, <head>, <body>, <script>, or <style> tags.',
    ]);
}

function site_theme_refine_system_prompt(): string
{
    return implode(' ', [
        'You refine site-wide CSS, JavaScript, and HTML for a PHP CMS using a PLAN/PATCH protocol.',
        '',
        'You MUST follow this exact format:',
        'PLAN: (one or two lines describing what you will change and why)',
        'Then one or more PATCH blocks, each targeting exactly one file:',
        '',
        'PATCH css:',
        '<<<<<<< SEARCH',
        '(exact text to find)',
        '=======',
        '(replacement text)',
        '>>>>>>> REPLACE',
        '',
        'Use "css", "js", or "html" as the file identifier in each PATCH block.',
        'A PATCH block may only target one file. Use multiple PATCH blocks for multiple files.',
        'SEARCH text must be an exact substring of the current file content (whitespace-tolerant).',
        'SEARCH text must appear EXACTLY ONCE — if it is ambiguous, make it longer to be unique.',
        'Everything NOT in a PATCH block is GUARANTEED PRESERVED. Do not rewrite whole files.',
        '',
        'CSS rules: keep all styles scoped to [data-layout-theme] or named animation identifiers.',
        'JS rules: do NOT remove the admin-body guard, the prefersReducedMotion check, or the IIFE wrapper.',
        'HTML rules: keep aria-hidden="true" on all background overlay containers.',
    ]);
}

// ─── Code extraction ──────────────────────────────────────────────────────────

/**
 * Parse ```css, ```javascript/js, and ```html fenced blocks from an AI response.
 * Returns ['css' => string, 'js' => string, 'html' => string].
 */
function site_theme_extract_code_blocks(string $rawText): array
{
    $result = ['css' => '', 'js' => '', 'html' => ''];

    if (preg_match('/```css\s*([\s\S]*?)```/i', $rawText, $m)) {
        $result['css'] = trim($m[1]);
    }
    if (preg_match('/```(?:javascript|js)\s*([\s\S]*?)```/i', $rawText, $m)) {
        $result['js'] = trim($m[1]);
    }
    if (preg_match('/```html\s*([\s\S]*?)```/i', $rawText, $m)) {
        $result['html'] = trim($m[1]);
    }

    return $result;
}

// ─── Validation ───────────────────────────────────────────────────────────────

/**
 * Basic preflight checks on generated CSS/JS/HTML.
 * Throws InvalidArgumentException on failure.
 * Only checks for clearly dangerous or broken patterns — not full parsing.
 */
function site_theme_preflight(string $css, string $js, string $html): void
{
    if (strlen($css) > SITE_THEME_CODE_MAX_BYTES) {
        throw new InvalidArgumentException('CSS exceeds 200 KB limit. Please simplify the generated code.');
    }
    if (strlen($js) > SITE_THEME_CODE_MAX_BYTES) {
        throw new InvalidArgumentException('JavaScript exceeds 200 KB limit.');
    }
    if (strlen($html) > SITE_THEME_CODE_MAX_BYTES) {
        throw new InvalidArgumentException('HTML exceeds 200 KB limit.');
    }

    // JS safety checks
    if ($js !== '') {
        $jsLower = strtolower($js);
        if (str_contains($jsLower, 'document.write')) {
            throw new InvalidArgumentException('Generated JS must not use document.write.');
        }
        if (preg_match('/\bimport\s+[\{\*"\']/', $js)) {
            throw new InvalidArgumentException('Generated JS must not use ES module import statements. Use vanilla IIFE format.');
        }
        if (!str_contains($js, '(function') && !str_contains($js, '(()')) {
            throw new InvalidArgumentException('Generated JS must be wrapped in an IIFE — (function(){ ... })();');
        }
    }

    // CSS: check for balanced braces (rudimentary — catches obvious truncation)
    if ($css !== '') {
        $open  = substr_count($css, '{');
        $close = substr_count($css, '}');
        if ($open !== $close) {
            throw new InvalidArgumentException("CSS has mismatched braces ({$open} opening vs {$close} closing). The response may have been truncated.");
        }
    }
}

// ─── Retry / repair prompts ───────────────────────────────────────────────────

function site_theme_repair_prompt(string $basePrompt, ?string $previousRawResponse, string $error): string
{
    $lines = [
        "Your previous attempt produced invalid code: {$error}",
        'Fix the error and return the same three code blocks (```css, ```javascript, ```html).',
        'The original request was:',
        '',
        $basePrompt,
    ];

    if ($previousRawResponse !== null && $previousRawResponse !== '') {
        $lines[] = '';
        $lines[] = 'Your previous (failed) response was:';
        $lines[] = $previousRawResponse;
    }

    return implode("\n", $lines);
}

// ─── PLAN/PATCH extraction ────────────────────────────────────────────────────

function site_theme_extract_refine_plan(string $rawText): string
{
    if (preg_match('/^PLAN:\s*(.+?)(?=\nPATCH\s|\z)/is', $rawText, $m)) {
        return trim($m[1]);
    }
    return '';
}

/**
 * Parse PATCH blocks from the AI response.
 * Returns ['css' => [['search'=>str,'replace'=>str], ...], 'js'=>[...], 'html'=>[...]].
 */
function site_theme_extract_refine_patches(string $rawText): array
{
    $patches = ['css' => [], 'js' => [], 'html' => []];

    // Match: PATCH <file>:\n<<<<<<< SEARCH\n<search>\n=======\n<replace>\n>>>>>>> REPLACE
    $pattern = '/PATCH\s+(css|js|html)\s*:\s*\n<{7}\s*SEARCH\s*\n([\s\S]*?)\n={7}\s*\n([\s\S]*?)\n>{7}\s*REPLACE/i';

    preg_match_all($pattern, $rawText, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $file    = strtolower(trim($match[1]));
        $search  = $match[2];
        $replace = $match[3];

        if (isset($patches[$file])) {
            $patches[$file][] = ['search' => $search, 'replace' => $replace];
        }
    }

    return $patches;
}

/**
 * Apply parsed patches to current code map ['css'=>str,'js'=>str,'html'=>str].
 * Returns updated map. Throws on search-not-found or ambiguous match.
 */
function site_theme_apply_refine_patches(array $current, array $patches): array
{
    $result = $current;

    foreach ($patches as $file => $filePetches) {
        foreach ($filePetches as $patch) {
            $search  = $patch['search'];
            $replace = $patch['replace'];
            $code    = $result[$file] ?? '';

            [$foundCode, $matched] = site_theme_find_patch_match($code, $search);

            if (!$matched) {
                throw new InvalidArgumentException(
                    "PATCH for {$file}: SEARCH text did not match current code. The AI may have returned stale search text. Try requesting the change again."
                );
            }

            $count = substr_count($foundCode, $search);
            if ($count > 1) {
                throw new InvalidArgumentException(
                    "PATCH for {$file}: SEARCH text matched {$count} locations — it must be unique. Provide more surrounding context in the SEARCH block."
                );
            }

            $result[$file] = str_replace($search, $replace, $foundCode);
        }
    }

    return $result;
}

/**
 * Whitespace-tolerant match: normalize runs of whitespace/newlines for comparison.
 * Returns [normalizedCode, didMatch].
 */
function site_theme_find_patch_match(string $code, string $search): array
{
    // Exact match first
    if (str_contains($code, $search)) {
        return [$code, true];
    }

    // Whitespace-normalized match: collapse runs of \s+ to single space, compare
    $normalizeWs = static fn(string $s): string => preg_replace('/\s+/', ' ', $s) ?? $s;

    $normCode   = $normalizeWs($code);
    $normSearch = $normalizeWs($search);

    if (!str_contains($normCode, $normSearch)) {
        return [$code, false];
    }

    // Re-apply on the normalized version so str_replace works
    // (only safe when match count = 1 — caller checks ambiguity after)
    return [$normCode, true];
}

// ─── Refine user prompt ───────────────────────────────────────────────────────

function site_theme_refine_user_prompt(
    string $refinement,
    string $css,
    string $js,
    string $html,
    ?string $originalPrompt = null
): string {
    $parts = [];

    if ($originalPrompt !== null && $originalPrompt !== '') {
        $parts[] = "Original theme description: {$originalPrompt}";
        $parts[] = '';
    }

    $parts[] = "Refinement instruction: {$refinement}";
    $parts[] = '';
    $parts[] = 'Current CSS:';
    $parts[] = "```css\n{$css}\n```";
    $parts[] = '';
    $parts[] = 'Current JavaScript:';
    $parts[] = "```javascript\n{$js}\n```";
    $parts[] = '';
    $parts[] = 'Current HTML (body injection):';
    $parts[] = "```html\n{$html}\n```";

    return implode("\n", $parts);
}

function site_theme_refine_repair_prompt(
    string $refinement,
    ?string $previousRawResponse,
    string $error,
    string $css,
    string $js,
    string $html
): string {
    $lines = [
        "Your previous PLAN/PATCH response failed: {$error}",
        'Fix the PATCH blocks so that each SEARCH text exactly matches the current code shown below.',
        "Refinement instruction: {$refinement}",
        '',
        'Current CSS:',
        "```css\n{$css}\n```",
        '',
        'Current JavaScript:',
        "```javascript\n{$js}\n```",
        '',
        'Current HTML (body injection):',
        "```html\n{$html}\n```",
    ];

    if ($previousRawResponse !== null && $previousRawResponse !== '') {
        $lines[] = '';
        $lines[] = 'Your previous (failed) response:';
        $lines[] = $previousRawResponse;
    }

    return implode("\n", $lines);
}
