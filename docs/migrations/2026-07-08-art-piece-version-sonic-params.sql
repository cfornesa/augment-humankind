-- Migration: 2026-07-08 add sonic_params to art_piece_versions
-- Stores the optional Tone.js "sonic parameters" JSON ({tempo, scale,
-- instrument, feel}) emitted by AI generation/refine when the piece-sound
-- feature (ai_pieces_sound) is used. The immersive runtime reads this JSON to
-- drive movement-based sonification; generated piece code never touches audio.
-- Existing rows remain valid with NULL sonic_params (no sound) and are
-- unaffected until resaved with instrumentation enabled.

ALTER TABLE art_piece_versions
    ADD COLUMN sonic_params LONGTEXT NULL AFTER notes;
