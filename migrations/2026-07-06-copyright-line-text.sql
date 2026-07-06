-- Migration: widen site_settings.copyright_line from VARCHAR(255) to TEXT
-- Reason: copyright_line now supports HTML markup (links, emphasis), which
-- can easily exceed 255 characters. TEXT supports up to 65 535 bytes.
-- This change is data-safe: MySQL widens the column without truncation.
-- Mechanism: probe-guarded step in scripts/setup-database.php

ALTER TABLE site_settings
    MODIFY COLUMN copyright_line TEXT NOT NULL DEFAULT '';
