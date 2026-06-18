CREATE TABLE IF NOT EXISTS post_sections (
    id            INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
    post_id       INT           NOT NULL,
    heading       VARCHAR(255)  NULL DEFAULT NULL,
    content       TEXT          NOT NULL,
    wrapper_class VARCHAR(100)  NULL DEFAULT NULL,
    sort_order    INT           NOT NULL DEFAULT 0,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
);
