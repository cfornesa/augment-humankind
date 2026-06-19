-- Migration: 2026-06-18 AI Personas, AI Profile Capabilities, Piece Thumbnail Alt Text, Media Alt Text
-- Run ONCE on Hostinger phpMyAdmin after deploying the corresponding code changes.
-- NOTE: Standard MySQL does not support ADD COLUMN IF NOT EXISTS — run this only once.

-- AI Personas table: named system prompts reusable across piece generation
CREATE TABLE IF NOT EXISTS ai_personas (
    id            INT          AUTO_INCREMENT PRIMARY KEY,
    user_id       INT          NOT NULL,
    name          VARCHAR(128) NOT NULL,
    system_prompt TEXT         NOT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_ai_personas_user (user_id)
);

-- Add capabilities column to AI profiles.
-- Comma-separated list of capability tokens: text, code, vision
-- Default 'text,code' covers all existing profiles (they were created for text/code tasks).
ALTER TABLE user_ai_vendor_settings
    ADD COLUMN capabilities VARCHAR(128) NOT NULL DEFAULT 'text,code'
    AFTER enabled;

-- Add thumbnail alt text to art pieces.
-- Populated automatically from the generation prompt when a thumbnail is saved.
ALTER TABLE art_pieces
    ADD COLUMN thumbnail_alt_text VARCHAR(500) NULL DEFAULT NULL
    AFTER thumbnail_url;

-- Add alt text to native media uploads.
-- Allows storing and editing descriptive alt text for uploaded images.
ALTER TABLE media_files
    ADD COLUMN title VARCHAR(255) NULL DEFAULT NULL AFTER original_name,
    ADD COLUMN alt_text VARCHAR(500) NULL DEFAULT NULL;

-- NOTE: users.theme, users.palette, the users.color_* tokens, and the
-- users.preferred_*_profile_id columns are NOT added here. They're part of
-- the base `users` table definition in
-- migrations/2026-06-14-platform-assimilation.sql as of 2026-06-18 (verified
-- against a fresh install — adding them again here would fail with
-- "Duplicate column name"). If you're patching an existing database created
-- from an older copy of that migration that predates those columns, add them
-- by hand first, or re-run the current migrations/2026-06-14-platform-assimilation.sql
-- (its ALTER statements are not idempotent either — check column existence
-- before re-running against a database that already has them).
