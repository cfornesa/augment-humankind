-- Migration: 2026-07-02 widen pages.meta_description / og_description to TEXT
-- Both were VARCHAR(320); MySQL silently truncated longer admin input
-- mid-word on save (non-strict mode), so meta and Open Graph descriptions
-- were cut off with no warning. TEXT removes the storage cap — search
-- engines and social scrapers apply their own display truncation, but the
-- stored value is now always exactly what the admin entered.
--
-- Documentation of record. Applied everywhere by the probe-guarded manifest
-- step in scripts/setup-database.php — do not run this file by hand on a
-- database the installer manages.

ALTER TABLE pages
    MODIFY meta_description TEXT NULL,
    MODIFY og_description TEXT NULL;
