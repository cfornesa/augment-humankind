-- Migration: 2026-07-13 add art_piece_version_media_refs
-- Structured media references selected via the "Add media reference" picker
-- in the piece generate/refine forms, replacing free-text prompt parsing as
-- the primary way a piece names a specific CMS media asset. Each row is one
-- picker selection: which media_files row, what the admin said it should be
-- used for (intent_text), and its display order. Scoped to a version (not
-- the piece) so regenerate/refine history keeps the refs that were actually
-- in play for that generation. Existing pieces have zero rows here and keep
-- working through the legacy free-text extractor (art_piece_extract_prompt_media_refs).

CREATE TABLE art_piece_version_media_refs (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    art_piece_version_id  INT NOT NULL,
    media_file_id         INT NOT NULL,
    intent_text           TEXT NULL,
    sort_order            INT NOT NULL DEFAULT 0,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_art_piece_version_media_refs_version (art_piece_version_id),
    KEY idx_art_piece_version_media_refs_media (media_file_id),
    CONSTRAINT fk_art_piece_version_media_refs_version FOREIGN KEY (art_piece_version_id) REFERENCES art_piece_versions(id) ON DELETE CASCADE,
    CONSTRAINT fk_art_piece_version_media_refs_media FOREIGN KEY (media_file_id) REFERENCES media_files(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
