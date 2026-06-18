ALTER TABLE exhibit_media_items
    MODIFY COLUMN media_kind ENUM('image','video','iframe','content') NOT NULL,
    ADD COLUMN IF NOT EXISTS content_html MEDIUMTEXT NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS content_wrapper_class VARCHAR(100) NULL DEFAULT NULL;
