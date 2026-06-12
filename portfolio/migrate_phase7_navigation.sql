ALTER TABLE pages
    ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL;

CREATE TABLE IF NOT EXISTS navigation_items (
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

INSERT INTO navigation_items
    (source_type, system_key, label, url, is_visible, sort_order)
SELECT 'system', 'gallery', 'Gallery', '/', 1, 0
WHERE NOT EXISTS (
    SELECT 1 FROM navigation_items WHERE source_type = 'system' AND system_key = 'gallery'
);

INSERT INTO navigation_items
    (source_type, system_key, label, url, is_visible, sort_order)
SELECT 'system', 'categories', 'Categories', '/categories', 1, 1
WHERE NOT EXISTS (
    SELECT 1 FROM navigation_items WHERE source_type = 'system' AND system_key = 'categories'
);

INSERT INTO navigation_items
    (source_type, system_key, label, url, is_visible, sort_order)
SELECT 'system', 'about', 'About', '/about', 0, 0
WHERE NOT EXISTS (
    SELECT 1 FROM navigation_items WHERE source_type = 'system' AND system_key = 'about'
);

INSERT INTO navigation_items
    (source_type, page_id, label, url, target, is_visible, sort_order)
SELECT
    'page',
    p.id,
    NULLIF(p.nav_label, ''),
    CONCAT('/', p.slug),
    NULL,
    p.show_in_nav,
    CASE
        WHEN p.show_in_nav = 1 THEN p.sort_order + 2
        ELSE p.sort_order + 1
    END
FROM pages p
LEFT JOIN navigation_items n
    ON n.source_type = 'page' AND n.page_id = p.id
WHERE n.id IS NULL
  AND p.deleted_at IS NULL;
