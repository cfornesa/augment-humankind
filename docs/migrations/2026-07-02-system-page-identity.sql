ALTER TABLE pages
  ADD COLUMN system_key VARCHAR(100) NULL AFTER id;

ALTER TABLE pages
  ADD UNIQUE KEY uniq_pages_system_key (system_key);

UPDATE pages
   SET system_key = 'home'
 WHERE slug = 'home'
   AND deleted_at IS NULL
   AND system_key IS NULL;

UPDATE pages
   SET system_key = 'about'
 WHERE slug = 'about'
   AND deleted_at IS NULL
   AND system_key IS NULL;

CREATE TABLE IF NOT EXISTS page_slug_redirects (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    old_slug    VARCHAR(255) NOT NULL,
    page_id     INT NOT NULL,
    system_key  VARCHAR(100) NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_page_slug_redirect_old_slug (old_slug),
    KEY idx_page_slug_redirect_page (page_id),
    FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
);
