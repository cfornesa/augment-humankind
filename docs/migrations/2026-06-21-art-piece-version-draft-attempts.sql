-- Migration: 2026-06-21 add is_draft_attempt/attempt_sequence_token to art_piece_versions
-- Backs the AI Refine multi-attempt retry feature: every attempt (success
-- or failure) is persisted as its own version row so a partially-good
-- failed attempt is never silently discarded, but a draft row must never be
-- selectable as the piece's current version. attempt_sequence_token groups
-- every attempt belonging to one retry sequence (one "Request AI Changes"
-- or "Request Stronger Change" click and everything retried from it), so
-- accepting a successful attempt can find and delete exactly its failed
-- siblings, not every draft the piece has ever accumulated.
-- Named generically (not refine-specific) so AI piece generation can adopt
-- the same per-attempt-version pattern later without a second migration —
-- generation is not changed by this migration or by the feature it backs.
-- Both columns default/allow NULL so every existing row is unaffected and
-- remains exactly as "current-eligible" as it is today.

ALTER TABLE art_piece_versions
    ADD COLUMN is_draft_attempt TINYINT(1) NOT NULL DEFAULT 0 AFTER ai_persona_id,
    ADD COLUMN attempt_sequence_token VARCHAR(36) NULL AFTER is_draft_attempt;

CREATE INDEX idx_art_piece_versions_sequence_token
    ON art_piece_versions (attempt_sequence_token);
