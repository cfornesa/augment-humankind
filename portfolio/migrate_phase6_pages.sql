CREATE TABLE IF NOT EXISTS pages (
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
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS page_sections (
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
SELECT
    'Bio', 'bio', 'published', 'standard', 'Bio', 1,
    'Bio — Fornesus Art',
    'Biography and background from the Fornesus Art archive.',
    'Bio — Fornesus Art',
    'Biography and background from the Fornesus Art archive.',
    0
WHERE NOT EXISTS (SELECT 1 FROM pages WHERE slug = 'bio');

INSERT INTO pages
    (title, slug, status, template, nav_label, show_in_nav, meta_title, meta_description, og_title, og_description, sort_order)
SELECT
    'Contact', 'contact', 'published', 'contact', 'Contact', 1,
    'Contact — Fornesus Art',
    'Send a message to Fornesus Art through the archive contact page.',
    'Contact — Fornesus Art',
    'Send a message to Fornesus Art through the archive contact page.',
    1
WHERE NOT EXISTS (SELECT 1 FROM pages WHERE slug = 'contact');

INSERT INTO page_sections (page_id, heading, content, sort_order)
SELECT p.id, b.heading, b.content, b.sort_order
FROM bio_sections b
JOIN pages p ON p.slug = 'bio'
WHERE NOT EXISTS (
    SELECT 1
    FROM page_sections ps
    WHERE ps.page_id = p.id
);
