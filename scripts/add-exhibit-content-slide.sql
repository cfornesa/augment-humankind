-- Standard MySQL does not support ADD COLUMN IF NOT EXISTS — run this only
-- once against a database that doesn't already have these columns.
ALTER TABLE exhibit_media_items
    MODIFY COLUMN media_kind ENUM('image','video','iframe','content') NOT NULL,
    ADD COLUMN content_html MEDIUMTEXT NULL DEFAULT NULL,
    ADD COLUMN content_wrapper_class VARCHAR(100) NULL DEFAULT NULL;
