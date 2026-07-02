-- Version history for admin-editable site theme code (CSS/JS/HTML).
-- Mirrors art_piece_versions: draft attempts are persisted before validation
-- so partially-good AI attempts are never discarded. Promotes to live on accept.
CREATE TABLE site_theme_snapshots (
    id                     INT AUTO_INCREMENT PRIMARY KEY,
    snapshot_number        INT NOT NULL DEFAULT 1,
    label                  VARCHAR(255)  NULL,
    custom_css             MEDIUMTEXT    NULL,
    custom_js              MEDIUMTEXT    NULL,
    custom_html_body       MEDIUMTEXT    NULL,
    is_draft_attempt       TINYINT(1)   NOT NULL DEFAULT 0,
    attempt_sequence_token CHAR(36)      NULL,
    generation_prompt      TEXT          NULL,
    generation_vendor      VARCHAR(100)  NULL,
    generation_model       VARCHAR(200)  NULL,
    ai_profile_id          INT           NULL,
    ai_persona_id          INT           NULL,
    notes                  TEXT          NULL,
    created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sequence (attempt_sequence_token),
    INDEX idx_draft    (is_draft_attempt),
    INDEX idx_created  (created_at)
);
