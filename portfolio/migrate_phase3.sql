-- Phase 3: Soft delete + media library
-- Run once against an existing Phase 1/2 database.

-- 1. Soft-delete columns
ALTER TABLE artworks   ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE categories ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE exhibits   ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL;

-- 2. Artwork thumbnails are now optional
ALTER TABLE artworks
    MODIFY thumbnail_type  ENUM('upload','link') NULL DEFAULT NULL,
    MODIFY thumbnail_value VARCHAR(500)          NULL DEFAULT NULL;

-- 3. Media files tracking table
CREATE TABLE media_files (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    path       VARCHAR(500) NOT NULL,
    subfolder  VARCHAR(100) NOT NULL,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_deleted (deleted_at)
);

-- 4. Populate media_files from existing uploaded artwork thumbnails
INSERT INTO media_files (path, subfolder, created_at)
    SELECT thumbnail_value, 'thumbnails', created_at
    FROM artworks
    WHERE thumbnail_type = 'upload' AND thumbnail_value IS NOT NULL;

-- 5. Populate from existing uploaded artwork pieces
INSERT INTO media_files (path, subfolder, created_at)
    SELECT piece_value, 'pieces', created_at
    FROM artworks
    WHERE piece_type = 'image_upload';

-- 6. Populate from existing uploaded category thumbnails
INSERT INTO media_files (path, subfolder, created_at)
    SELECT thumbnail_value, 'categorys', NOW()
    FROM categories
    WHERE thumbnail_type = 'upload' AND thumbnail_value IS NOT NULL;

-- 7. Populate from existing uploaded exhibit thumbnails
INSERT INTO media_files (path, subfolder, created_at)
    SELECT thumbnail_value, 'exhibits', NOW()
    FROM exhibits
    WHERE thumbnail_type = 'upload' AND thumbnail_value IS NOT NULL;
