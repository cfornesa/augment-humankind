-- Make the visitor-activated camera overlay available by default on the
-- non-interactive/2D piece modes. Explicit author Off values remain intact;
-- steerable Three.js, A-Frame, and C2 Interactive modes retain their existing
-- NULL = follow-hand-tracking behavior.
UPDATE art_piece_versions
SET camera_overlay = 1
WHERE camera_overlay IS NULL
  AND COALESCE(NULLIF(generation_mode, ''), engine) IN ('p5', 'c2', 'svg');
