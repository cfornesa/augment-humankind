-- 2026-07-03 — FULLTEXT indexes for site-wide ranked search.
--
-- The global /search page and embedded archive/blog search bars gain a
-- true relevance sort using MATCH ... AGAINST (IN BOOLEAN MODE). Posts
-- already carry posts_content_text_fulltext (2026-06-14 platform
-- assimilation); this migration extends ranking to the remaining
-- searchable content types. Each index covers exactly the columns the
-- corresponding model's search method MATCHes against.
--
-- Documentation of record. The applying mechanism is the probe-guarded
-- "search fulltext indexes (2026-07-03)" step in scripts/setup-database.php
-- (idempotent; skips indexes that already exist).

ALTER TABLE art_pieces
    ADD FULLTEXT INDEX art_pieces_search_fulltext (title, description, prompt);

ALTER TABLE platform_collections
    ADD FULLTEXT INDEX platform_collections_search_fulltext (name, description, artist_statement);

ALTER TABLE collections
    ADD FULLTEXT INDEX collections_search_fulltext (name, description);

ALTER TABLE exhibits
    ADD FULLTEXT INDEX exhibits_search_fulltext (title, description);

ALTER TABLE pages
    ADD FULLTEXT INDEX pages_search_fulltext (title, meta_description);
