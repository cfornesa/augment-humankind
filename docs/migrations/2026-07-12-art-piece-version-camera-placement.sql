-- Camera placement per piece version: where the visitor's camera renders
-- when the camera view is enabled. NULL = engine default (background for
-- Three.js/A-Frame's camera-attached blended quad, overlay for the 2D
-- engines' DOM <video>). Paired with the combined admin select that also
-- writes camera_overlay (availability): Default / On — background /
-- On — overlay / Off.
ALTER TABLE art_piece_versions
    ADD COLUMN camera_placement VARCHAR(16) NULL AFTER camera_overlay;

-- Camera option available by default on every engine: unset (NULL)
-- camera_overlay now reads as "toggle offered" for Three.js/A-Frame and
-- c2_interactive too, matching the p5/plain-c2/svg default shipped
-- 2026-07-11. The visitor still opts in per session; nothing auto-starts.
-- (No row backfill needed: the NULL semantics change lives in
-- piece_sound_capability_contract(); explicit 0 rows keep the camera off.)
