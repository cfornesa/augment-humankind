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

-- Add per-user profile theme and palette metadata.
ALTER TABLE users
    ADD COLUMN theme VARCHAR(32) NULL DEFAULT NULL
    AFTER image,
    ADD COLUMN palette VARCHAR(32) NULL DEFAULT NULL
    AFTER theme;

-- Add per-user public profile color tokens.
ALTER TABLE users
    ADD COLUMN color_background VARCHAR(64) NULL DEFAULT NULL AFTER palette,
    ADD COLUMN color_foreground VARCHAR(64) NULL DEFAULT NULL AFTER color_background,
    ADD COLUMN color_muted VARCHAR(64) NULL DEFAULT NULL AFTER color_foreground,
    ADD COLUMN color_muted_foreground VARCHAR(64) NULL DEFAULT NULL AFTER color_muted,
    ADD COLUMN color_primary VARCHAR(64) NULL DEFAULT NULL AFTER color_muted_foreground,
    ADD COLUMN color_primary_foreground VARCHAR(64) NULL DEFAULT NULL AFTER color_primary,
    ADD COLUMN color_secondary VARCHAR(64) NULL DEFAULT NULL AFTER color_primary_foreground,
    ADD COLUMN color_secondary_foreground VARCHAR(64) NULL DEFAULT NULL AFTER color_secondary,
    ADD COLUMN color_accent VARCHAR(64) NULL DEFAULT NULL AFTER color_secondary_foreground,
    ADD COLUMN color_accent_foreground VARCHAR(64) NULL DEFAULT NULL AFTER color_accent,
    ADD COLUMN color_destructive VARCHAR(64) NULL DEFAULT NULL AFTER color_accent_foreground,
    ADD COLUMN color_destructive_foreground VARCHAR(64) NULL DEFAULT NULL AFTER color_destructive,
    ADD COLUMN color_background_dark VARCHAR(64) NULL DEFAULT NULL AFTER color_destructive_foreground,
    ADD COLUMN color_foreground_dark VARCHAR(64) NULL DEFAULT NULL AFTER color_background_dark;

-- Add preferred AI profile selections for admin surfaces.
ALTER TABLE users
    ADD COLUMN preferred_art_piece_profile_id INT NULL DEFAULT NULL AFTER color_foreground_dark,
    ADD COLUMN preferred_text_improve_profile_id INT NULL DEFAULT NULL AFTER preferred_art_piece_profile_id,
    ADD COLUMN preferred_alt_text_profile_id INT NULL DEFAULT NULL AFTER preferred_text_improve_profile_id;
