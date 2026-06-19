-- Add wrapper_class column to page_sections.
-- Allows the admin to select a CSS class that wraps a section's content
-- independently of the Tiptap editor, so structural styling survives edits.
--
-- Standard MySQL does not support ADD COLUMN IF NOT EXISTS — run this only
-- once against a database that doesn't already have this column.

ALTER TABLE page_sections
    ADD COLUMN wrapper_class VARCHAR(100) NULL DEFAULT NULL;
