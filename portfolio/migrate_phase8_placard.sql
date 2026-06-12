ALTER TABLE artworks
    ADD COLUMN artist_name VARCHAR(255) NULL AFTER title,
    ADD COLUMN medium VARCHAR(255) NULL AFTER year,
    ADD COLUMN dimensions VARCHAR(255) NULL AFTER medium,
    ADD COLUMN placard_notes TEXT NULL AFTER description;

ALTER TABLE artwork_media_items
    ADD COLUMN caption VARCHAR(250) NULL AFTER alt_text;
