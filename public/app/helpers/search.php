<?php

declare(strict_types=1);

/**
 * Site search helpers — PHP port of the retired app's post-search helper
 * (parseSearchQuery / buildSearchSnippet), preserved before deletion.
 *
 * - search_parse_query() turns a raw query string into a MySQL
 *   boolean-mode expression plus normalized terms for highlighting and
 *   LIKE fallbacks.
 * - search_snippet() renders a short excerpt centered on the first
 *   matched term with <mark> around each occurrence. The string is
 *   HTML-safe by construction (escape first, then wrap) — callers must
 *   echo it raw, never re-wrap it in e().
 */

const SEARCH_MAX_QUERY_LENGTH = 200;
const SEARCH_SNIPPET_RADIUS = 80;
const SEARCH_SNIPPET_MAX_LENGTH = 220;

// InnoDB's default innodb_ft_min_token_size is 3. Length-3 tokens emit
// BOTH a FULLTEXT branch and a LIKE branch (OR-composed), so 3-char
// queries keep matching even on a server where the minimum is bumped to 4.
const SEARCH_FULLTEXT_MIN_LEN = 3;
const SEARCH_LIKE_FALLBACK_MAX_LEN = 3;

/**
 * @return array{boolean: string, terms: string[], like_terms: string[]}|null
 *   boolean    — expression for MATCH(...) AGAINST(? IN BOOLEAN MODE):
 *                required prefix words (+word*) and required exact
 *                phrases (+"hello world"). Empty string when every
 *                token is below the FULLTEXT minimum length.
 *   terms      — lowercased, deduped words for snippet highlighting.
 *   like_terms — tokens short enough to need a LIKE '%term%' fallback.
 */
function search_parse_query(string $raw): ?array
{
    if (mb_strlen($raw) > SEARCH_MAX_QUERY_LENGTH) {
        $raw = mb_substr($raw, 0, SEARCH_MAX_QUERY_LENGTH);
    }
    if (trim($raw) === '') {
        return null;
    }

    // Pass 1: pull balanced "..." segments out as exact phrases, scrubbing
    // boolean operators inside them so an inner '*' can't become a wildcard.
    $phrases = [];
    $remainder = preg_replace_callback('/"([^"]*)"/u', static function (array $m) use (&$phrases): string {
        $cleaned = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', str_replace(
            ['+', '-', '>', '<', '(', ')', '~', '@', '*'],
            ' ',
            $m[1]
        ))));
        if ($cleaned !== '') {
            $phrases[] = $cleaned;
        }
        return ' ';
    }, $raw) ?? $raw;

    // Pass 2: strip remaining boolean operators (and stray unbalanced ")
    // from the unquoted words.
    $cleaned = trim((string) preg_replace('/\s+/u', ' ', str_replace(
        ['+', '-', '>', '<', '(', ')', '~', '@', '"', '*'],
        ' ',
        $remainder
    )));

    $seen = [];
    $terms = [];
    foreach ($phrases as $phrase) {
        foreach (explode(' ', $phrase) as $word) {
            if ($word === '' || isset($seen[$word])) {
                continue;
            }
            $seen[$word] = true;
            $terms[] = $word;
        }
    }
    $unquoted = [];
    foreach (explode(' ', $cleaned) as $word) {
        $word = mb_strtolower($word);
        if ($word === '' || isset($seen[$word])) {
            continue;
        }
        $seen[$word] = true;
        $terms[] = $word;
        $unquoted[] = $word;
    }
    if ($terms === []) {
        return null;
    }

    $fulltextParts = [];
    $likeTerms = [];
    foreach ($phrases as $phrase) {
        $fulltextParts[] = '+"' . $phrase . '"';
    }
    foreach ($unquoted as $term) {
        if (mb_strlen($term) >= SEARCH_FULLTEXT_MIN_LEN) {
            $fulltextParts[] = '+' . $term . '*';
        }
        if (mb_strlen($term) <= SEARCH_LIKE_FALLBACK_MAX_LEN) {
            $likeTerms[] = $term;
        }
    }
    $boolean = implode(' ', $fulltextParts);

    if ($boolean === '' && $likeTerms === []) {
        return null;
    }

    return ['boolean' => $boolean, 'terms' => $terms, 'like_terms' => $likeTerms];
}

/**
 * Boolean-mode expression for MATCH ... AGAINST, or null when the query
 * has no FULLTEXT-eligible tokens (callers then keep their LIKE path).
 */
function search_boolean_expression(string $query): ?string
{
    $parsed = search_parse_query($query);
    if ($parsed === null || $parsed['boolean'] === '') {
        return null;
    }
    return $parsed['boolean'];
}

function search_like_pattern(string $term): string
{
    return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';
}

/**
 * Adds per-token LIKE recall predicates for short search terms.
 *
 * @param string[] $columns trusted SQL column expressions
 * @param string[] $likeTerms normalized terms from search_parse_query()
 */
function search_like_recall_clause(array $columns, array $likeTerms, array &$params): string
{
    $clauses = [];
    $seen = [];
    foreach ($likeTerms as $term) {
        $term = (string) $term;
        if ($term === '' || isset($seen[$term])) {
            continue;
        }
        $seen[$term] = true;

        $columnClauses = [];
        foreach ($columns as $column) {
            $columnClauses[] = "LOWER({$column}) LIKE LOWER(?)";
            $params[] = search_like_pattern($term);
        }
        if ($columnClauses !== []) {
            $clauses[] = '(' . implode(' OR ', $columnClauses) . ')';
        }
    }

    return implode(' OR ', $clauses);
}

/**
 * HTML-safe snippet centered on the first matched term, with <mark>
 * around each (case-insensitive) occurrence. Empty text returns ''.
 * No terms returns the escaped leading slice.
 */
function search_snippet(?string $text, array $terms, int $radius = SEARCH_SNIPPET_RADIUS, int $max = SEARCH_SNIPPET_MAX_LENGTH): string
{
    $source = trim((string) $text);
    if ($source === '') {
        return '';
    }

    if ($terms === []) {
        $slice = mb_substr($source, 0, $max);
        return htmlspecialchars($slice, ENT_QUOTES, 'UTF-8')
            . (mb_strlen($source) > mb_strlen($slice) ? '…' : '');
    }

    $firstIdx = -1;
    foreach ($terms as $term) {
        $idx = mb_stripos($source, (string) $term);
        if ($idx !== false && ($firstIdx === -1 || $idx < $firstIdx)) {
            $firstIdx = $idx;
        }
    }

    $length = mb_strlen($source);
    $start = 0;
    $end = min($length, $max);
    $prefix = '';
    $suffix = $length > $end ? '…' : '';

    if ($firstIdx !== -1) {
        $start = max(0, $firstIdx - $radius);
        $end = min($length, $start + $max);
        if ($end - $start < $max) {
            $start = max(0, $end - $max);
        }
        $prefix = $start > 0 ? '…' : '';
        $suffix = $end < $length ? '…' : '';
    }

    $window = mb_substr($source, $start, $end - $start);
    $escaped = htmlspecialchars($window, ENT_QUOTES, 'UTF-8');

    // Highlight the escaped window: escape each term the same way, then
    // preg_quote it, so the pattern matches the escaped text safely.
    $patternParts = [];
    foreach ($terms as $term) {
        $escapedTerm = htmlspecialchars((string) $term, ENT_QUOTES, 'UTF-8');
        if ($escapedTerm !== '') {
            $patternParts[] = preg_quote($escapedTerm, '/');
        }
    }
    if ($patternParts !== []) {
        $escaped = (string) preg_replace(
            '/(' . implode('|', $patternParts) . ')/iu',
            '<mark>$1</mark>',
            $escaped
        );
    }

    return $prefix . $escaped . $suffix;
}
