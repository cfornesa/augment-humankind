-- Migration: 2026-07-03 backfill persisted c2_interactive generation mode
--
-- Existing saved versions may still have engine='c2' even when their code is
-- clearly interactive. Runtime fallback continues to detect them heuristically,
-- but this backfill upgrades matching legacy rows into first-class persisted
-- generation_mode='c2_interactive' values so immersive/admin behavior no
-- longer depends on re-detecting interactivity on every read.
--
-- Documentation of record. The applying mechanism is the probe-guarded
-- "art piece version c2 interactive backfill (2026-07-03)" step in
-- scripts/setup-database.php (idempotent; safe to rerun).

UPDATE art_piece_versions
SET generation_mode = 'c2_interactive'
WHERE engine = 'c2'
  AND (generation_mode IS NULL OR generation_mode = '' OR generation_mode = 'c2')
  AND LOWER(CONCAT(COALESCE(generated_code, ''), '\n', COALESCE(html_code, ''))) REGEXP "(?:addEventListener[[:space:]]*\\([[:space:]]*['\"](?:pointerdown|pointerup|pointermove|mousedown|mouseup|mousemove|touchstart|touchmove|touchend|click)|on(?:click|mousedown|mouseup|mousemove|touchstart|touchmove|touchend|pointerdown|pointermove|pointerup)[[:space:]]*=)";
