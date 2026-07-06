-- Migration: widen site_settings.footer_credit from VARCHAR(255) to TEXT
-- Reason: footer credit HTML can legitimately contain multiple anchor tags,
-- which easily exceeds 255 characters. TEXT supports up to 65 535 bytes.
-- This change is data-safe: MySQL widens the column without truncation.
-- Mechanism: probe-guarded step in scripts/setup-database.php

ALTER TABLE site_settings
    MODIFY COLUMN footer_credit TEXT NOT NULL DEFAULT '';
