<?php

declare(strict_types=1);

/**
 * Simple RSS/Atom feed parser for feed ingestion.
 * Returns parsed items with title, content, link, published, guid.
 */
function parse_feed_xml(string $xml): array
{
    $items = [];
    $doc = simplexml_load_string($xml);
    if (!$doc) {
        return $items;
    }

    // Detect Atom vs RSS
    $isAtom = $doc->getName() === 'feed';

    if ($isAtom) {
        $entries = $doc->entry ?? [];
        foreach ($entries as $entry) {
            $title = (string) ($entry->title ?? 'Untitled');
            $content = (string) ($entry->content ?? $entry->summary ?? '');
            $link = '';
            foreach ($entry->link as $l) {
                $rel = (string) ($l['rel'] ?? 'alternate');
                if ($rel === 'alternate') {
                    $link = (string) ($l['href'] ?? '');
                    break;
                }
            }
            $published = (string) ($entry->published ?? $entry->updated ?? '');
            $guid = (string) ($entry->id ?? $link ?? '');
            $author = (string) ($entry->author->name ?? '');
            $categories = [];
            foreach ($entry->category ?? [] as $category) {
                $term = (string) ($category['term'] ?? $category['label'] ?? '');
                if ($term !== '') {
                    $categories[] = $term;
                }
            }

            if ($guid !== '') {
                $items[] = [
                    'title' => $title,
                    'content' => $content,
                    'content_text' => text_from_feed_html($content),
                    'link' => $link,
                    'published' => $published,
                    'guid' => $guid,
                    'author' => $author,
                    'categories' => array_values(array_unique($categories)),
                    'raw' => json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ];
            }
        }
    } else {
        // RSS 2.0
        $channel = $doc->channel ?? null;
        if ($channel) {
            $entries = $channel->item ?? [];
        } else {
            $entries = $doc->item ?? [];
        }
        foreach ($entries as $entry) {
            $namespaces = $entry->getNamespaces(true);
            $title = (string) ($entry->title ?? 'Untitled');
            $content = (string) ($entry->description ?? $entry->content ?? '');
            if (isset($namespaces['content'])) {
                $contentNs = $entry->children($namespaces['content']);
                if (!empty($contentNs->encoded)) {
                    $content = (string) $contentNs->encoded;
                }
            }
            $link = (string) ($entry->link ?? '');
            $published = (string) ($entry->pubDate ?? '');
            $guid = (string) ($entry->guid ?? $link ?? '');
            $author = (string) ($entry->author ?? $entry->children('dc', true)->creator ?? '');
            $categories = [];
            foreach ($entry->category ?? [] as $category) {
                $value = trim((string) $category);
                if ($value !== '') {
                    $categories[] = $value;
                }
            }

            if ($guid !== '') {
                $items[] = [
                    'title' => $title,
                    'content' => $content,
                    'content_text' => text_from_feed_html($content),
                    'link' => $link,
                    'published' => $published,
                    'guid' => $guid,
                    'author' => $author,
                    'categories' => array_values(array_unique($categories)),
                    'raw' => json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ];
            }
        }
    }

    return $items;
}

function text_from_feed_html(string $html): string
{
    return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
}

function feed_datetime(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $timestamp = strtotime($value);
    return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
}

/**
 * Fetch a feed URL and return the raw XML or null on failure.
 */
function fetch_feed(string $url): ?string
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: PhpCmsFeedFetcher/1.0\r\nAccept: application/rss+xml,application/atom+xml,application/xml,text/xml,*/*\r\n",
            'timeout' => 15,
            'follow_location' => 1,
            'max_redirects' => 3,
        ],
    ]);

    $xml = @file_get_contents($url, false, $context);
    return $xml !== false ? $xml : null;
}

/**
 * Check if a feed item has already been seen (processed or pending).
 */
function feed_item_seen(int $sourceId, string $guid): bool
{
    try {
        $stmt = db()->prepare(
            'SELECT 1 FROM feed_items_seen WHERE source_id = ? AND guid_hash = ? LIMIT 1'
        );
        $stmt->execute([$sourceId, md5($guid)]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

/**
 * Record a feed item as seen (pending import).
 */
function record_feed_item(int $sourceId, array $item): int
{
    $guid = (string) $item['guid'];
    $hash = md5($guid);
    $stmt = db()->prepare(
        'INSERT INTO feed_items_seen (source_id, guid_hash, seen_at)
         VALUES (?, ?, NOW())'
    );
    $stmt->execute([$sourceId, $hash]);
    $seenId = (int) db()->lastInsertId();

    if (feed_import_table_exists()) {
        $import = db()->prepare(
            'INSERT INTO feed_import_items
                (seen_id, source_id, guid, guid_hash, title, content, content_text,
                 source_url, author_name, published_at, raw_item_json, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $import->execute([
            $seenId,
            $sourceId,
            $guid,
            $hash,
            $item['title'] ?? null,
            $item['content'] ?? null,
            $item['content_text'] ?? text_from_feed_html((string) ($item['content'] ?? '')),
            $item['link'] ?? null,
            $item['author'] ?? null,
            feed_datetime($item['published'] ?? null),
            json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'pending',
        ]);
    }

    return $seenId;
}

/**
 * Ingest a feed: fetch, parse, and record new items as pending imports.
 * Returns array of newly recorded seen IDs.
 */
function ingest_feed(int $sourceId): array
{
    $source = FeedSource::find($sourceId);
    if (!$source) {
        return [];
    }

    $feedUrl = $source['feed_url'] ?? '';
    if ($feedUrl === '') {
        FeedSource::updateFetchStatus($sourceId, 'error', 'No feed URL configured.');
        return [];
    }

    $xml = fetch_feed($feedUrl);
    if ($xml === null) {
        FeedSource::updateFetchStatus($sourceId, 'error', 'Failed to fetch feed.');
        return [];
    }

    $items = parse_feed_xml($xml);
    if ($items === []) {
        FeedSource::updateFetchStatus($sourceId, 'success');
        return [];
    }

    $newIds = [];
    foreach ($items as $item) {
        $guid = $item['guid'];
        if (feed_item_seen($sourceId, $guid)) {
            continue;
        }
        $newIds[] = record_feed_item($sourceId, $item);
    }

    FeedSource::updateFetchStatus($sourceId, 'success');
    return $newIds;
}

function refresh_due_feeds(): int
{
    $count = 0;
    foreach (FeedSource::allEnabled() as $source) {
        if (!FeedSource::isDue($source)) {
            continue;
        }
        try {
            ingest_feed((int) $source['id']);
            $count++;
        } catch (Throwable) {
            // silently skip; updateFetchStatus already logged the error
        }
    }
    return $count;
}

function feed_import_table_exists(): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    try {
        $stmt = db()->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute(['feed_import_items']);
        return $exists = (bool) $stmt->fetchColumn();
    } catch (Throwable) {
        return $exists = false;
    }
}
