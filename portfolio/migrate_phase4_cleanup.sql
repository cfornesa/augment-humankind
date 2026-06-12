-- Phase 4 cleanup: drop legacy path/subfolder columns from media_files.
-- Run ONLY after migrate_images_to_blob.php completes with 0 failures
-- AND all verification queries below return 0.
--
-- Verification (run these first):
--   SELECT COUNT(*) FROM media_files WHERE data IS NULL;
--   SELECT COUNT(*) FROM artworks
--     WHERE (thumbnail_type='upload' AND thumbnail_value LIKE '/uploads/%')
--        OR (piece_type='image_upload' AND piece_value LIKE '/uploads/%');
--   SELECT COUNT(*) FROM categories WHERE thumbnail_type='upload' AND thumbnail_value LIKE '/uploads/%';
--   SELECT COUNT(*) FROM exhibits   WHERE thumbnail_type='upload' AND thumbnail_value LIKE '/uploads/%';
--
-- After running this SQL, also:
--   1. Manually delete the contents of public/uploads/
--   2. Update trash.php media tab display (currently uses $item['path'] and $item['subfolder'])

ALTER TABLE media_files
    DROP COLUMN path,
    DROP COLUMN subfolder;
