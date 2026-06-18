-- Add wrapper_class column to page_sections.
-- Allows the admin to select a CSS class that wraps a section's content
-- independently of the Tiptap editor, so structural styling survives edits.
--
-- Run once against the live database. Safe to run again — the column is
-- added only if it does not already exist (MySQL 8.0+). On older MySQL,
-- run scripts/apply-migration.php with this file instead.

ALTER TABLE page_sections
    ADD COLUMN IF NOT EXISTS wrapper_class VARCHAR(100) NULL DEFAULT NULL;
