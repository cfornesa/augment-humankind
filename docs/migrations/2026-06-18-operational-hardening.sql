CREATE TABLE IF NOT EXISTS request_rate_limits (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  scope VARCHAR(64) NOT NULL,
  subject_hash CHAR(64) NOT NULL,
  window_start DATETIME(3) NOT NULL,
  request_count INT NOT NULL DEFAULT 1,
  first_request_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  last_request_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  UNIQUE KEY request_rate_limits_scope_subject_window_unique (scope, subject_hash, window_start),
  KEY request_rate_limits_scope_window_idx (scope, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log_events (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  event_type VARCHAR(64) NOT NULL,
  scope VARCHAR(64) NOT NULL,
  actor_admin_identity_id INT NULL,
  subject_hash CHAR(64) NULL,
  target_type VARCHAR(64) NULL,
  target_id VARCHAR(191) NULL,
  outcome VARCHAR(32) NOT NULL,
  http_status SMALLINT NULL,
  metadata_json LONGTEXT NULL,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  KEY audit_log_events_scope_created_idx (scope, created_at),
  KEY audit_log_events_event_type_created_idx (event_type, created_at),
  KEY audit_log_events_actor_created_idx (actor_admin_identity_id, created_at),
  CONSTRAINT audit_log_events_actor_admin_identity_fk
    FOREIGN KEY (actor_admin_identity_id) REFERENCES admin_identities(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
