-- Phase 4: Add BLOB storage columns to media_files
-- Run BEFORE deploying new code.
-- Safe to inspect; NOT idempotent — run once only.
--
-- Prerequisites:
--   SHOW VARIABLES LIKE 'max_allowed_packet';  -- must be >= 55MB
--   innodb_redo_log_capacity (MySQL 8.0.30+) must be >= ~500MB for 50MB blobs
--
-- path and subfolder are kept until migrate_phase4_cleanup.sql runs.

ALTER TABLE media_files
    ADD COLUMN data      LONGBLOB    NULL AFTER subfolder,
    ADD COLUMN mime_type VARCHAR(50) NULL AFTER data;
