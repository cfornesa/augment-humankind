ALTER TABLE site_settings
    ADD COLUMN canonical_public_url VARCHAR(255) NULL AFTER palette,
    ADD COLUMN admin_nav_order_json LONGTEXT NULL AFTER canonical_public_url;
