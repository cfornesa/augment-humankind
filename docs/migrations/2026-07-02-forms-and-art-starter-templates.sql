-- Migration: 2026-07-02 Forms CMS + art piece starter templates
-- Documentation of record. Applied by the probe-guarded manifest step in
-- scripts/setup-database.php.

ALTER TABLE page_sections
    ADD COLUMN section_kind VARCHAR(32) NOT NULL DEFAULT 'content' AFTER page_id,
    ADD COLUMN form_id INT NULL AFTER section_kind,
    ADD COLUMN config_json LONGTEXT NULL AFTER form_id,
    ADD COLUMN is_required TINYINT(1) NOT NULL DEFAULT 0 AFTER config_json;

CREATE TABLE forms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_key VARCHAR(100) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    form_type VARCHAR(32) NOT NULL DEFAULT 'email',
    status VARCHAR(16) NOT NULL DEFAULT 'active',
    recipient_email VARCHAR(255) NULL,
    recaptcha_site_key VARCHAR(255) NULL,
    encrypted_recaptcha_secret TEXT NULL,
    recaptcha_minimum_score DECIMAL(3,2) NOT NULL DEFAULT 0.50,
    success_message TEXT NULL,
    submit_label VARCHAR(100) NOT NULL DEFAULT 'Submit',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_forms_form_key (form_key)
);

CREATE TABLE form_fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_id INT NOT NULL,
    field_key VARCHAR(100) NOT NULL,
    label VARCHAR(255) NOT NULL,
    field_type VARCHAR(32) NOT NULL DEFAULT 'text',
    help_text TEXT NULL,
    placeholder VARCHAR(255) NULL,
    options_json LONGTEXT NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_form_fields_key (form_id, field_key),
    KEY idx_form_fields_form (form_id),
    FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE
);

CREATE TABLE newsletter_subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_id INT NOT NULL,
    page_id INT NULL,
    email VARCHAR(255) NOT NULL,
    consent TINYINT(1) NOT NULL DEFAULT 1,
    source_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_newsletter_subscriber_form_email (form_id, email),
    KEY idx_newsletter_subscribers_form (form_id),
    FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE,
    FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE SET NULL
);

CREATE TABLE art_piece_starter_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_key VARCHAR(100) NOT NULL,
    engine VARCHAR(32) NOT NULL,
    generation_mode VARCHAR(32) NOT NULL,
    label VARCHAR(255) NOT NULL,
    description TEXT NULL,
    html_code MEDIUMTEXT NULL,
    css_code MEDIUMTEXT NULL,
    js_code MEDIUMTEXT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_art_piece_starter_template_key (template_key),
    KEY idx_art_piece_starter_templates_mode (generation_mode, is_default, is_active)
);
