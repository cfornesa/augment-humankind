ALTER TABLE media_files
    ADD COLUMN byte_size INT NULL AFTER mime_type,
    ADD COLUMN original_name VARCHAR(255) NULL AFTER byte_size;

UPDATE media_files
SET byte_size = OCTET_LENGTH(data)
WHERE data IS NOT NULL AND byte_size IS NULL;

CREATE TABLE admin_identities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider ENUM('github', 'google') NOT NULL,
    provider_subject VARCHAR(255) NOT NULL,
    email VARCHAR(255) NULL,
    display_name VARCHAR(255) NOT NULL,
    avatar_url VARCHAR(500) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_provider_subject (provider, provider_subject),
    KEY idx_admin_identities_active (is_active)
);

CREATE TABLE artwork_media_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    artwork_id INT NOT NULL,
    media_kind ENUM('image', 'video', 'iframe') NOT NULL,
    media_file_id INT NULL,
    iframe_html MEDIUMTEXT NULL,
    poster_media_file_id INT NULL,
    alt_text VARCHAR(250) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_artwork_media_artwork FOREIGN KEY (artwork_id) REFERENCES artworks(id) ON DELETE CASCADE,
    CONSTRAINT fk_artwork_media_file FOREIGN KEY (media_file_id) REFERENCES media_files(id) ON DELETE SET NULL,
    CONSTRAINT fk_artwork_media_poster FOREIGN KEY (poster_media_file_id) REFERENCES media_files(id) ON DELETE SET NULL
);

CREATE INDEX idx_artwork_media_sort ON artwork_media_items (artwork_id, sort_order, id);
