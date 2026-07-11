-- Per-piece camera overlay permission, decoupled from the audio hand-tracking
-- flag in sonic_params. NULL = legacy behavior (camera follows hand_tracking
-- for three/aframe/c2_interactive), 1 = on, 0 = off.
ALTER TABLE art_piece_versions
    ADD COLUMN camera_overlay TINYINT(1) NULL AFTER sonic_params;
