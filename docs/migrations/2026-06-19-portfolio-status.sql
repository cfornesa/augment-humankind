-- Migration: 2026-06-19 add status to exhibits, collections, platform_collections
-- These three tables had no draft/archived concept at all (only soft-delete
-- via deleted_at), unlike pages.status and art_pieces.status. Mirrors the
-- art_pieces convention (VARCHAR(16), default 'active') so the same
-- draft/archived banner and admin status select can apply consistently
-- across all portfolio content types.

ALTER TABLE exhibits
    ADD COLUMN status VARCHAR(16) NOT NULL DEFAULT 'active'
    AFTER deleted_at;

ALTER TABLE collections
    ADD COLUMN status VARCHAR(16) NOT NULL DEFAULT 'active'
    AFTER deleted_at;

ALTER TABLE platform_collections
    ADD COLUMN status VARCHAR(16) NOT NULL DEFAULT 'active'
    AFTER deleted_at;
