-- Promote custom_css from settings_json fallback to a first-class column.
-- Part of headless CMS compliance: all admin-editable fields must be
-- explicit, queryable columns rather than hidden in a JSON blob.
ALTER TABLE site_settings
    ADD COLUMN custom_css MEDIUMTEXT NULL AFTER palette;

-- One-time data migration: move any value already stored in settings_json
-- into the new column. No-op if custom_css was never saved via the fallback.
UPDATE site_settings
SET custom_css = JSON_UNQUOTE(JSON_EXTRACT(settings_json, '$.custom_css'))
WHERE JSON_EXTRACT(settings_json, '$.custom_css') IS NOT NULL
  AND id = 1;
