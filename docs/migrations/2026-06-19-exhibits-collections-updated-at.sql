-- Migration: 2026-06-19 add updated_at to exhibits and collections
-- These two tables only had created_at, so "newest" sorts on the public
-- portfolio could never reflect edits to an exhibit/exhibit-collection,
-- only its original creation time.

ALTER TABLE exhibits
    ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    AFTER created_at;

ALTER TABLE collections
    ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    AFTER created_at;
