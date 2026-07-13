-- Separate immersive camera presentation and regular hand motion from the
-- existing regular camera columns and from sonic_params.
ALTER TABLE art_piece_versions
    ADD COLUMN immersive_camera_overlay TINYINT(1) NULL AFTER camera_placement,
    ADD COLUMN immersive_camera_placement VARCHAR(16) NULL AFTER immersive_camera_overlay,
    ADD COLUMN regular_hand_motion TINYINT(1) NULL AFTER immersive_camera_placement;

-- Preserve an explicit legacy hand-control choice without retaining the
-- runtime/admin dependency on audio metadata. Missing choices remain NULL so
-- the compatible-engine default can evolve centrally.
UPDATE art_piece_versions
SET regular_hand_motion = CASE
    WHEN JSON_EXTRACT(sonic_params, '$.extras.voices.hand_control') = TRUE THEN 1
    WHEN JSON_EXTRACT(sonic_params, '$.extras.voices.hand_control') = FALSE THEN 0
    ELSE NULL
END
WHERE regular_hand_motion IS NULL
  AND sonic_params IS NOT NULL
  AND JSON_VALID(sonic_params)
  AND JSON_EXTRACT(sonic_params, '$.extras.voices.hand_control') IS NOT NULL;
