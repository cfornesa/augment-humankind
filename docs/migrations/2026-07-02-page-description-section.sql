-- Migration: 2026-07-02 per-page description + show-as-first-section toggle
-- Adds a dedicated description field to every page, editable in the admin
-- Metadata section, plus a per-page toggle that renders it as the page's
-- first section (mission-band with the page title as the H1). Defaults OFF
-- for all pages. Replaces the site-wide site_settings.about_body intro that
-- only the about-type system page could render: that page's intro migrates
-- into its own description with the toggle ON. site_settings.about_body and
-- about_heading remain as unused columns.
--
-- Documentation of record. Applied everywhere by the probe-guarded manifest
-- step in scripts/setup-database.php — do not run this file by hand on a
-- database the installer manages.

ALTER TABLE pages
    ADD COLUMN description TEXT NULL AFTER nav_label,
    ADD COLUMN show_description_section TINYINT(1) NOT NULL DEFAULT 0 AFTER description;

-- One-time backfill: move the old site-wide about intro onto the about-type
-- system page itself, preserving the rendered output exactly.
UPDATE pages p
JOIN site_settings s ON s.id = 1
SET p.description = s.about_body,
    p.show_description_section = 1
WHERE p.system_key = 'about'
  AND (p.description IS NULL OR p.description = '')
  AND s.about_body <> '';
