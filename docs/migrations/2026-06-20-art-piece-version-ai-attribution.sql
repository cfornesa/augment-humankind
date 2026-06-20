-- Migration: 2026-06-20 add ai_profile_id/ai_persona_id to art_piece_versions
-- Tracks which AI Profile (user_ai_vendor_settings) and AI Persona
-- (ai_personas) produced each version, so the piece's public/immersive pages
-- and the admin Versions list can disclose this. No FK constraint: deleting
-- an AI profile/persona later must never cascade into deleting historical
-- art piece data — display falls back to "(Blank)" when the id no longer
-- resolves, same as when it was never set.

ALTER TABLE art_piece_versions
    ADD COLUMN ai_profile_id INT NULL AFTER generation_model,
    ADD COLUMN ai_persona_id INT NULL AFTER ai_profile_id;
