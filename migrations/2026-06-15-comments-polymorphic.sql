-- Part E: Polymorphic comments + comments_enabled toggles
-- Run AFTER 2026-06-14-platform-assimilation.sql

-- 1. Add polymorphic columns to comments
ALTER TABLE comments
    ADD COLUMN item_type VARCHAR(32) NULL AFTER post_id,
    ADD COLUMN item_id   INT          NULL AFTER item_type;

-- 2. Backfill existing post comments
UPDATE comments SET item_type = 'post', item_id = post_id WHERE post_id IS NOT NULL;

-- 3. Drop FK so post_id can become nullable
ALTER TABLE comments DROP FOREIGN KEY comments_post_id_fk;

-- 4. Make post_id nullable (kept for legacy reads; item_type/item_id are authoritative)
ALTER TABLE comments MODIFY post_id INT NULL;

-- 5. Composite index for polymorphic lookups
ALTER TABLE comments ADD INDEX comments_item_idx (item_type, item_id);

-- 6. Enable/disable comments per content item
ALTER TABLE posts              ADD COLUMN comments_enabled TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE pages              ADD COLUMN comments_enabled TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE art_pieces         ADD COLUMN comments_enabled TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE platform_collections ADD COLUMN comments_enabled TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE collections        ADD COLUMN comments_enabled TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE exhibits           ADD COLUMN comments_enabled TINYINT(1) NOT NULL DEFAULT 0;
