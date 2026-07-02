CREATE TABLE site_theme_code (
    theme_name        VARCHAR(64)  NOT NULL,
    label             VARCHAR(191) NOT NULL DEFAULT '',
    custom_css        MEDIUMTEXT   NULL,
    custom_js         MEDIUMTEXT   NULL,
    custom_html_body  MEDIUMTEXT   NULL,
    default_css       MEDIUMTEXT   NULL,
    default_js        MEDIUMTEXT   NULL,
    default_html_body MEDIUMTEXT   NULL,
    is_builtin        TINYINT(1)   NOT NULL DEFAULT 1,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (theme_name)
);
