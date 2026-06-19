-- Migration: 2026-06-19 media draft confirmation + linked video posters

ALTER TABLE media_files
    ADD COLUMN status ENUM('draft', 'ready') NOT NULL DEFAULT 'ready'
    AFTER alt_text;

ALTER TABLE media_files
    ADD COLUMN poster_media_file_id INT NULL DEFAULT NULL
    AFTER status;

ALTER TABLE media_files
    ADD COLUMN confirmed_at DATETIME NULL DEFAULT NULL
    AFTER poster_media_file_id;

ALTER TABLE media_files
    ADD KEY idx_media_files_status (status);

ALTER TABLE media_files
    ADD KEY idx_media_files_poster (poster_media_file_id);

UPDATE media_files
SET status = 'ready',
    confirmed_at = COALESCE(confirmed_at, created_at)
WHERE status <> 'ready'
   OR confirmed_at IS NULL;
