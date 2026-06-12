CREATE TABLE categories (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    slug            VARCHAR(255) NOT NULL UNIQUE,
    thumbnail_type  ENUM('upload','link') NULL,
    thumbnail_value VARCHAR(500)          NULL,
    description     TEXT                  NULL,
    sort_order      INT DEFAULT 0,
    deleted_at      TIMESTAMP NULL DEFAULT NULL
);

CREATE TABLE artworks (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    category_id      INT NULL,
    title            VARCHAR(255) NOT NULL,
    artist_name      VARCHAR(255) NULL,
    slug             VARCHAR(255) NOT NULL UNIQUE,
    year             VARCHAR(10),
    medium           VARCHAR(255) NULL,
    dimensions       VARCHAR(255) NULL,
    description      TEXT,
    placard_notes    TEXT NULL,
    thumbnail_type   ENUM('upload','link') NULL DEFAULT NULL,
    thumbnail_value  VARCHAR(500)          NULL DEFAULT NULL,
    piece_type       ENUM('image_upload','image_link','embed') NOT NULL,
    piece_value      TEXT NOT NULL,
    sort_order       INT DEFAULT 0,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at       TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE TABLE artwork_media_items (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    artwork_id           INT NOT NULL,
    media_kind           ENUM('image', 'video', 'iframe') NOT NULL,
    media_file_id        INT NULL,
    iframe_html          MEDIUMTEXT NULL,
    poster_media_file_id INT NULL,
    alt_text             VARCHAR(250) NULL,
    title                VARCHAR(255) NULL,
    caption              VARCHAR(250) NULL,
    sort_order           INT NOT NULL DEFAULT 0,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (artwork_id) REFERENCES artworks(id) ON DELETE CASCADE
);

CREATE TABLE exhibits (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    slug            VARCHAR(255) NOT NULL UNIQUE,
    description     TEXT,
    thumbnail_type  ENUM('upload','link') NULL,
    thumbnail_value VARCHAR(500)          NULL,
    sort_order      INT DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at      TIMESTAMP NULL DEFAULT NULL
);

CREATE TABLE artwork_categories (
    artwork_id  INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (artwork_id, category_id),
    FOREIGN KEY (artwork_id)  REFERENCES artworks(id)   ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

CREATE TABLE exhibit_artworks (
    exhibit_id  INT NOT NULL,
    artwork_id  INT NOT NULL,
    sort_order  INT DEFAULT 0,
    PRIMARY KEY (exhibit_id, artwork_id),
    FOREIGN KEY (exhibit_id) REFERENCES exhibits(id)  ON DELETE CASCADE,
    FOREIGN KEY (artwork_id) REFERENCES artworks(id)  ON DELETE CASCADE
);

CREATE TABLE pages (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    title             VARCHAR(255) NOT NULL,
    slug              VARCHAR(255) NOT NULL UNIQUE,
    status            ENUM('published', 'draft') NOT NULL DEFAULT 'published',
    template          ENUM('standard', 'contact') NOT NULL DEFAULT 'standard',
    nav_label         VARCHAR(255) NULL,
    show_in_nav       TINYINT(1) NOT NULL DEFAULT 0,
    meta_title        VARCHAR(255) NULL,
    meta_description  VARCHAR(320) NULL,
    og_title          VARCHAR(255) NULL,
    og_description    VARCHAR(320) NULL,
    og_image          VARCHAR(500) NULL,
    sort_order        INT DEFAULT 0,
    deleted_at        TIMESTAMP NULL DEFAULT NULL,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE navigation_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    source_type ENUM('system', 'page', 'external') NOT NULL,
    system_key  VARCHAR(100) NULL,
    page_id     INT NULL,
    label       VARCHAR(255) NULL,
    url         VARCHAR(500) NULL,
    target      VARCHAR(20) NULL,
    is_visible  TINYINT(1) NOT NULL DEFAULT 1,
    sort_order  INT DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_navigation_system (system_key),
    UNIQUE KEY uniq_navigation_page (page_id),
    CONSTRAINT fk_navigation_page FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
);

CREATE TABLE page_sections (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    page_id     INT NOT NULL,
    heading     VARCHAR(255) NULL,
    content     TEXT NOT NULL,
    sort_order  INT DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
);

INSERT INTO pages
    (title, slug, status, template, nav_label, show_in_nav, meta_title, meta_description, og_title, og_description, sort_order)
VALUES
    ('Bio', 'bio', 'published', 'standard', 'Bio', 1, 'Bio — Fornesus Art', 'Biography and background from the Fornesus Art archive.', 'Bio — Fornesus Art', 'Biography and background from the Fornesus Art archive.', 0),
    ('Contact', 'contact', 'published', 'contact', 'Contact', 1, 'Contact — Fornesus Art', 'Send a message to Fornesus Art through the archive contact page.', 'Contact — Fornesus Art', 'Send a message to Fornesus Art through the archive contact page.', 1);

CREATE TABLE contact_messages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    email       VARCHAR(255) NOT NULL,
    message     TEXT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read     TINYINT(1) NOT NULL DEFAULT 0,
    is_flagged  TINYINT(1) NOT NULL DEFAULT 0,
    is_pinned   TINYINT(1) NOT NULL DEFAULT 0,
    sort_order  INT NOT NULL DEFAULT 0,
    deleted_at  TIMESTAMP NULL DEFAULT NULL
);

CREATE TABLE media_files (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    data          LONGBLOB NULL,
    mime_type     VARCHAR(50) NULL,
    byte_size     INT NULL,
    original_name VARCHAR(255) NULL,
    deleted_at    TIMESTAMP NULL DEFAULT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_deleted (deleted_at)
);

CREATE TABLE admin_identities (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    provider         ENUM('github', 'google') NOT NULL,
    provider_subject VARCHAR(255) NOT NULL,
    email            VARCHAR(255) NULL,
    display_name     VARCHAR(255) NOT NULL,
    avatar_url       VARCHAR(500) NULL,
    is_active        TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at    TIMESTAMP NULL DEFAULT NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_provider_subject (provider, provider_subject)
);
