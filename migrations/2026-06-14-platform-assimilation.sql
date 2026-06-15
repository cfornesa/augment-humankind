-- Platform assimilation migration for the current PHP target database.
-- Run only against the current PHP DB configured by DB_*.
-- Never run this file against the live platform DB configured by PLATFORM_*.

SET NAMES utf8mb4;

ALTER TABLE categories
  ADD COLUMN IF NOT EXISTS category_scope VARCHAR(32) NOT NULL DEFAULT 'portfolio',
  ADD COLUMN IF NOT EXISTS platform_source_id INT NULL,
  ADD COLUMN IF NOT EXISTS platform_original_slug VARCHAR(191) NULL,
  ADD COLUMN IF NOT EXISTS platform_created_at DATETIME(3) NULL,
  ADD COLUMN IF NOT EXISTS platform_updated_at DATETIME(3) NULL,
  ADD COLUMN IF NOT EXISTS created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  ADD COLUMN IF NOT EXISTS updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  ADD INDEX IF NOT EXISTS idx_categories_scope (category_scope),
  ADD UNIQUE KEY IF NOT EXISTS uniq_categories_platform_source (category_scope, platform_source_id);

UPDATE categories SET category_scope = 'portfolio' WHERE category_scope = '';

ALTER TABLE pages
  ADD COLUMN IF NOT EXISTS platform_source_id INT NULL,
  ADD COLUMN IF NOT EXISTS platform_original_slug VARCHAR(96) NULL,
  ADD COLUMN IF NOT EXISTS content_format VARCHAR(16) NOT NULL DEFAULT 'html',
  ADD COLUMN IF NOT EXISTS content_text TEXT NULL,
  ADD COLUMN IF NOT EXISTS author_user_id VARCHAR(191) NULL,
  ADD COLUMN IF NOT EXISTS platform_created_at DATETIME(3) NULL,
  ADD COLUMN IF NOT EXISTS platform_updated_at DATETIME(3) NULL,
  ADD UNIQUE KEY IF NOT EXISTS uniq_pages_platform_source (platform_source_id);

ALTER TABLE navigation_items
  ADD COLUMN IF NOT EXISTS platform_source_id INT NULL,
  ADD COLUMN IF NOT EXISTS platform_original_url VARCHAR(2048) NULL,
  ADD COLUMN IF NOT EXISTS platform_kind VARCHAR(32) NULL,
  ADD COLUMN IF NOT EXISTS open_in_new_tab TINYINT(1) NOT NULL DEFAULT 0,
  ADD UNIQUE KEY IF NOT EXISTS uniq_navigation_platform_source (platform_source_id);

CREATE TABLE IF NOT EXISTS users (
  id VARCHAR(191) NOT NULL PRIMARY KEY,
  platform_source_id VARCHAR(191) NULL,
  name VARCHAR(255) NULL,
  username VARCHAR(255) NULL,
  email VARCHAR(191) NULL,
  email_verified TIMESTAMP(3) NULL DEFAULT NULL,
  image VARCHAR(2048) NULL,
  bio TEXT NULL,
  website VARCHAR(2048) NULL,
  social_links JSON NULL,
  role VARCHAR(32) NOT NULL DEFAULT 'member',
  status VARCHAR(32) NOT NULL DEFAULT 'active',
  post_count INT NOT NULL DEFAULT 0,
  theme VARCHAR(32) NULL,
  palette VARCHAR(32) NULL,
  color_background VARCHAR(64) NULL,
  color_foreground VARCHAR(64) NULL,
  color_background_dark VARCHAR(64) NULL,
  color_foreground_dark VARCHAR(64) NULL,
  color_primary VARCHAR(64) NULL,
  color_primary_foreground VARCHAR(64) NULL,
  color_secondary VARCHAR(64) NULL,
  color_secondary_foreground VARCHAR(64) NULL,
  color_accent VARCHAR(64) NULL,
  color_accent_foreground VARCHAR(64) NULL,
  color_muted VARCHAR(64) NULL,
  color_muted_foreground VARCHAR(64) NULL,
  color_destructive VARCHAR(64) NULL,
  color_destructive_foreground VARCHAR(64) NULL,
  preferred_art_piece_profile_id INT NULL,
  preferred_text_improve_profile_id INT NULL,
  preferred_alt_text_profile_id INT NULL,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  last_login_at DATETIME(3) NULL,
  UNIQUE KEY users_email_unique (email),
  UNIQUE KEY users_username_unique (username),
  UNIQUE KEY users_platform_source_unique (platform_source_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounts (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id VARCHAR(191) NOT NULL,
  type VARCHAR(255) NOT NULL,
  provider VARCHAR(255) NOT NULL,
  provider_account_id VARCHAR(255) NOT NULL,
  refresh_token TEXT NULL,
  access_token TEXT NULL,
  expires_at INT NULL,
  token_type VARCHAR(255) NULL,
  scope VARCHAR(255) NULL,
  id_token TEXT NULL,
  session_state VARCHAR(255) NULL,
  UNIQUE KEY accounts_provider_provider_account_id_unique (provider, provider_account_id),
  KEY accounts_user_id_idx (user_id),
  CONSTRAINT accounts_user_id_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sessions (
  session_token VARCHAR(255) NOT NULL PRIMARY KEY,
  user_id VARCHAR(191) NOT NULL,
  expires TIMESTAMP(3) NOT NULL,
  KEY sessions_user_id_idx (user_id),
  CONSTRAINT sessions_user_id_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS verification_tokens (
  identifier VARCHAR(255) NOT NULL,
  token VARCHAR(255) NOT NULL,
  expires TIMESTAMP(3) NOT NULL,
  PRIMARY KEY (identifier, token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feed_sources (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  platform_source_id INT NULL,
  name VARCHAR(255) NOT NULL,
  author_name VARCHAR(255) NULL,
  username VARCHAR(100) NULL,
  bio TEXT NULL,
  image_url VARCHAR(2048) NULL,
  site_url VARCHAR(2048) NULL,
  feed_url VARCHAR(2048) NOT NULL,
  cadence VARCHAR(32) NOT NULL DEFAULT 'daily',
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  last_fetched_at DATETIME(3) NULL,
  next_fetch_at DATETIME(3) NULL,
  items_imported INT NOT NULL DEFAULT 0,
  last_status VARCHAR(32) NULL,
  last_error TEXT NULL,
  profile_photo_url VARCHAR(2048) NULL,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  UNIQUE KEY feed_sources_platform_source_unique (platform_source_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS posts (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  platform_source_id INT NULL,
  author_id VARCHAR(191) NOT NULL,
  author_user_id VARCHAR(191) NULL,
  author_name VARCHAR(255) NOT NULL,
  author_image_url VARCHAR(2048) NULL,
  title VARCHAR(500) NULL,
  content MEDIUMTEXT NOT NULL,
  content_text MEDIUMTEXT NULL,
  content_format VARCHAR(16) NOT NULL DEFAULT 'plain',
  status VARCHAR(16) NOT NULL DEFAULT 'published',
  source_feed_id INT NULL,
  source_guid VARCHAR(1024) NULL,
  source_canonical_url VARCHAR(2048) NULL,
  scheduled_at DATETIME(3) NULL,
  pending_platform_ids TEXT NULL,
  featured_image_url VARCHAR(2048) NULL,
  social_post_drafts TEXT NULL,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  deleted_at DATETIME(3) NULL,
  UNIQUE KEY posts_platform_source_unique (platform_source_id),
  KEY posts_status_idx (status),
  KEY posts_source_feed_idx (source_feed_id),
  KEY posts_author_id_idx (author_id),
  KEY posts_status_created_idx (status, created_at),
  FULLTEXT KEY posts_content_text_fulltext (content_text),
  CONSTRAINT posts_author_user_id_fk FOREIGN KEY (author_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT posts_source_feed_id_fk FOREIGN KEY (source_feed_id) REFERENCES feed_sources(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS post_categories (
  post_id INT NOT NULL,
  category_id INT NOT NULL,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (post_id, category_id),
  KEY post_categories_category_idx (category_id),
  CONSTRAINT post_categories_post_id_fk FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  CONSTRAINT post_categories_category_id_fk FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comments (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  platform_source_id INT NULL,
  post_id INT NOT NULL,
  author_id VARCHAR(191) NOT NULL,
  author_user_id VARCHAR(191) NULL,
  author_name VARCHAR(255) NOT NULL,
  author_image_url VARCHAR(2048) NULL,
  content TEXT NOT NULL,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  deleted_at DATETIME(3) NULL,
  UNIQUE KEY comments_platform_source_unique (platform_source_id),
  KEY comments_post_id_idx (post_id),
  CONSTRAINT comments_post_id_fk FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  CONSTRAINT comments_author_user_id_fk FOREIGN KEY (author_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reactions (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  platform_source_id INT NULL,
  post_id INT NOT NULL,
  user_id VARCHAR(191) NOT NULL,
  type VARCHAR(32) NOT NULL,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  UNIQUE KEY reactions_post_user_type_unique (post_id, user_id, type),
  UNIQUE KEY reactions_platform_source_unique (platform_source_id),
  CONSTRAINT reactions_post_id_fk FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  CONSTRAINT reactions_user_id_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feed_items_seen (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  platform_source_id INT NULL,
  source_id INT NOT NULL,
  guid_hash VARCHAR(191) NOT NULL,
  post_id INT NULL,
  seen_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  UNIQUE KEY feed_items_seen_source_guid_unique (source_id, guid_hash),
  UNIQUE KEY feed_items_seen_platform_source_unique (platform_source_id),
  CONSTRAINT feed_items_seen_source_id_fk FOREIGN KEY (source_id) REFERENCES feed_sources(id) ON DELETE CASCADE,
  CONSTRAINT feed_items_seen_post_id_fk FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feed_import_items (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  seen_id INT NOT NULL,
  source_id INT NOT NULL,
  guid VARCHAR(1024) NOT NULL,
  guid_hash VARCHAR(191) NOT NULL,
  title VARCHAR(500) NULL,
  content MEDIUMTEXT NULL,
  content_text MEDIUMTEXT NULL,
  source_url VARCHAR(2048) NULL,
  author_name VARCHAR(255) NULL,
  published_at DATETIME(3) NULL,
  raw_item_json JSON NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'pending',
  post_id INT NULL,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  UNIQUE KEY feed_import_seen_unique (seen_id),
  UNIQUE KEY feed_import_source_guid_unique (source_id, guid_hash),
  KEY feed_import_status_idx (status),
  CONSTRAINT feed_import_seen_id_fk FOREIGN KEY (seen_id) REFERENCES feed_items_seen(id) ON DELETE CASCADE,
  CONSTRAINT feed_import_source_id_fk FOREIGN KEY (source_id) REFERENCES feed_sources(id) ON DELETE CASCADE,
  CONSTRAINT feed_import_post_id_fk FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_settings (
  id INT NOT NULL PRIMARY KEY DEFAULT 1,
  theme VARCHAR(32) NOT NULL DEFAULT 'bauhaus',
  palette VARCHAR(32) NOT NULL DEFAULT 'bauhaus',
  site_title VARCHAR(255) NOT NULL DEFAULT 'Augment Humankind',
  hero_heading VARCHAR(255) NOT NULL DEFAULT '',
  hero_subheading TEXT NOT NULL,
  about_heading VARCHAR(255) NOT NULL DEFAULT '',
  about_body TEXT NOT NULL,
  copyright_line VARCHAR(255) NOT NULL DEFAULT '',
  footer_credit VARCHAR(255) NOT NULL DEFAULT '',
  cta_label VARCHAR(255) NOT NULL DEFAULT '',
  cta_href VARCHAR(2048) NOT NULL DEFAULT '/',
  logo_url VARCHAR(2048) NULL,
  logo_dark_url VARCHAR(2048) NULL,
  logo_layout VARCHAR(32) NOT NULL DEFAULT 'text_only',
  default_theme_mode VARCHAR(32) NOT NULL DEFAULT 'system',
  color_background VARCHAR(64) NULL,
  color_foreground VARCHAR(64) NULL,
  color_background_dark VARCHAR(64) NULL,
  color_foreground_dark VARCHAR(64) NULL,
  color_primary VARCHAR(64) NULL,
  color_primary_foreground VARCHAR(64) NULL,
  color_secondary VARCHAR(64) NULL,
  color_secondary_foreground VARCHAR(64) NULL,
  color_accent VARCHAR(64) NULL,
  color_accent_foreground VARCHAR(64) NULL,
  color_muted VARCHAR(64) NULL,
  color_muted_foreground VARCHAR(64) NULL,
  color_destructive VARCHAR(64) NULL,
  color_destructive_foreground VARCHAR(64) NULL,
  color_primary_dark VARCHAR(64) NULL,
  color_primary_foreground_dark VARCHAR(64) NULL,
  color_secondary_dark VARCHAR(64) NULL,
  color_secondary_foreground_dark VARCHAR(64) NULL,
  color_accent_dark VARCHAR(64) NULL,
  color_accent_foreground_dark VARCHAR(64) NULL,
  color_muted_dark VARCHAR(64) NULL,
  color_muted_foreground_dark VARCHAR(64) NULL,
  color_destructive_dark VARCHAR(64) NULL,
  color_destructive_foreground_dark VARCHAR(64) NULL,
  settings_json JSON NULL,
  updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_assets (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  asset_key VARCHAR(191) NOT NULL,
  filename VARCHAR(255) NULL,
  mime_type VARCHAR(64) NOT NULL,
  byte_size INT NULL,
  data LONGBLOB NULL,
  file_data LONGBLOB NULL,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  UNIQUE KEY site_assets_asset_key_unique (asset_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS media_assets (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  platform_source_id INT NULL,
  url VARCHAR(2048) NULL,
  filename VARCHAR(255) NOT NULL,
  mime_type VARCHAR(64) NOT NULL,
  byte_size INT NULL,
  alt_text VARCHAR(500) NULL,
  title VARCHAR(255) NULL,
  file_data LONGBLOB NULL,
  deleted_at DATETIME(3) NULL,
  uploaded_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  UNIQUE KEY media_assets_platform_source_unique (platform_source_id),
  KEY media_assets_uploaded_at_idx (uploaded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS profile_photo_assets (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  platform_source_id INT NULL,
  user_id VARCHAR(191) NOT NULL,
  url VARCHAR(2048) NULL,
  filename VARCHAR(255) NOT NULL,
  mime_type VARCHAR(64) NOT NULL,
  file_data MEDIUMBLOB NULL,
  uploaded_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  UNIQUE KEY profile_photo_assets_platform_source_unique (platform_source_id),
  KEY profile_photo_assets_user_id_idx (user_id),
  CONSTRAINT profile_photo_assets_user_id_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_ai_vendor_settings (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  platform_source_id INT NULL,
  user_id VARCHAR(191) NOT NULL,
  vendor VARCHAR(64) NOT NULL,
  profile_name VARCHAR(128) NOT NULL DEFAULT 'Default',
  endpoint_kind VARCHAR(32) NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  model VARCHAR(191) NULL,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  UNIQUE KEY uq_user_vendor_profile (user_id, vendor, profile_name),
  UNIQUE KEY user_ai_vendor_settings_platform_source_unique (platform_source_id),
  CONSTRAINT user_ai_vendor_settings_user_id_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_ai_vendor_keys (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  platform_source_id INT NULL,
  user_id VARCHAR(191) NOT NULL,
  vendor VARCHAR(64) NOT NULL,
  encrypted_api_key TEXT NOT NULL,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  UNIQUE KEY uq_user_vendor_key (user_id, vendor),
  UNIQUE KEY user_ai_vendor_keys_platform_source_unique (platform_source_id),
  CONSTRAINT user_ai_vendor_keys_user_id_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_connections (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  platform_source_id INT NULL,
  user_id VARCHAR(191) NULL,
  platform VARCHAR(64) NOT NULL,
  encrypted_access_token TEXT NULL,
  encrypted_refresh_token TEXT NULL,
  access_token_format VARCHAR(32) NOT NULL DEFAULT 'platform_encrypted',
  refresh_token_format VARCHAR(32) NOT NULL DEFAULT 'platform_encrypted',
  expires_at DATETIME(3) NULL,
  metadata JSON NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  UNIQUE KEY platform_connections_platform_source_unique (platform_source_id),
  UNIQUE KEY platform_connections_user_platform_unique (user_id, platform),
  CONSTRAINT platform_connections_user_id_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_oauth_apps (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  platform_source_id INT NULL,
  platform VARCHAR(32) NOT NULL,
  encrypted_client_id TEXT NULL,
  encrypted_client_secret TEXT NULL,
  blog_url VARCHAR(500) NULL,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  UNIQUE KEY platform_oauth_apps_platform_unique (platform),
  UNIQUE KEY platform_oauth_apps_platform_source_unique (platform_source_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS post_syndications (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  platform_source_id INT NULL,
  post_id INT NOT NULL,
  platform_connection_id INT NOT NULL,
  external_id VARCHAR(512) NULL,
  external_url VARCHAR(2048) NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'pending',
  error_message TEXT NULL,
  synced_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  UNIQUE KEY post_syndications_platform_source_unique (platform_source_id),
  UNIQUE KEY post_syndications_post_connection_unique (post_id, platform_connection_id),
  CONSTRAINT post_syndications_post_id_fk FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  CONSTRAINT post_syndications_connection_id_fk FOREIGN KEY (platform_connection_id) REFERENCES platform_connections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS art_pieces (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  platform_source_id INT NULL,
  owner_user_id VARCHAR(191) NULL,
  title VARCHAR(255) NOT NULL,
  prompt TEXT NULL,
  engine VARCHAR(16) NOT NULL DEFAULT 'p5',
  status VARCHAR(16) NOT NULL DEFAULT 'active',
  current_version_id INT NULL,
  platform_current_version_source_id INT NULL,
  thumbnail_url VARCHAR(2048) NULL,
  description TEXT NULL,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  deleted_at DATETIME(3) NULL,
  UNIQUE KEY art_pieces_platform_source_unique (platform_source_id),
  CONSTRAINT art_pieces_owner_user_id_fk FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS art_piece_versions (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  platform_source_id INT NULL,
  art_piece_id INT NOT NULL,
  version_number INT NOT NULL DEFAULT 1,
  prompt TEXT NULL,
  structured_spec TEXT NULL,
  html_code TEXT NULL,
  css_code TEXT NULL,
  generated_code TEXT NULL,
  engine VARCHAR(16) NOT NULL DEFAULT 'p5',
  generation_vendor VARCHAR(64) NULL,
  generation_model VARCHAR(191) NULL,
  validation_status VARCHAR(32) NOT NULL DEFAULT 'validated',
  generation_attempt_count INT NOT NULL DEFAULT 1,
  notes TEXT NULL,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  UNIQUE KEY art_piece_versions_platform_source_unique (platform_source_id),
  CONSTRAINT art_piece_versions_piece_id_fk FOREIGN KEY (art_piece_id) REFERENCES art_pieces(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_collections (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  platform_source_id INT NULL,
  slug VARCHAR(191) NOT NULL,
  name VARCHAR(255) NOT NULL,
  description TEXT NULL,
  artist_statement TEXT NULL,
  biography TEXT NULL,
  `rows` TINYINT NOT NULL DEFAULT 1,
  `cols` TINYINT NOT NULL DEFAULT 1,
  iframe_code TEXT NULL,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  deleted_at DATETIME(3) NULL,
  UNIQUE KEY platform_collections_platform_source_unique (platform_source_id),
  UNIQUE KEY platform_collections_slug_unique (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_collection_items (
  collection_id INT NOT NULL,
  item_type VARCHAR(32) NOT NULL,
  item_id INT NOT NULL,
  source_id INT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (collection_id, item_type, item_id),
  CONSTRAINT platform_collection_items_collection_id_fk FOREIGN KEY (collection_id) REFERENCES platform_collections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_migration_map (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  entity_type VARCHAR(64) NOT NULL,
  source_id VARCHAR(191) NOT NULL,
  target_table VARCHAR(64) NOT NULL,
  target_id VARCHAR(191) NOT NULL,
  notes TEXT NULL,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  UNIQUE KEY platform_migration_map_unique (entity_type, source_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
