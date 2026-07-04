-- Migration: 2026-07-03 add generation_mode to art_piece_versions
-- Persists the selected piece-generation mode separately from engine so
-- modes that intentionally collapse onto one runtime family (currently
-- c2_interactive -> c2) can still be honored exactly after save.
-- Existing rows remain valid with NULL generation_mode and continue to use
-- legacy fallback behavior until resaved.

ALTER TABLE art_piece_versions
    ADD COLUMN generation_mode VARCHAR(32) NULL AFTER generation_model;
