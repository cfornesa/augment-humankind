-- Phase 2 migration — run against an existing database.
-- Safe to run more than once only if columns/tables don't already exist.

ALTER TABLE categories
    ADD COLUMN thumbnail_type  ENUM('upload','link') NULL AFTER slug,
    ADD COLUMN thumbnail_value VARCHAR(500)          NULL AFTER thumbnail_type,
    ADD COLUMN description     TEXT                  NULL AFTER thumbnail_value;

CREATE TABLE IF NOT EXISTS exhibits (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    slug            VARCHAR(255) NOT NULL UNIQUE,
    description     TEXT,
    thumbnail_type  ENUM('upload','link') NULL,
    thumbnail_value VARCHAR(500)          NULL,
    sort_order      INT DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS exhibit_artworks (
    exhibit_id  INT NOT NULL,
    artwork_id  INT NOT NULL,
    sort_order  INT DEFAULT 0,
    PRIMARY KEY (exhibit_id, artwork_id),
    FOREIGN KEY (exhibit_id) REFERENCES exhibits(id)  ON DELETE CASCADE,
    FOREIGN KEY (artwork_id) REFERENCES artworks(id)  ON DELETE CASCADE
);
