-- Add custom_js and custom_html_body columns to site_settings.
-- Part of headless CMS compliance: site theme JS and background HTML
-- become admin-editable fields backed by proper DB columns, not static files.
-- SiteSettings::availableColumns() picks these up automatically at runtime.
ALTER TABLE site_settings
    ADD COLUMN custom_js        MEDIUMTEXT NULL AFTER custom_css,
    ADD COLUMN custom_html_body MEDIUMTEXT NULL AFTER custom_js;
