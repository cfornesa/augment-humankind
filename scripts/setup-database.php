<?php

declare(strict_types=1);

/**
 * One-command idempotent database installer.
 *
 * Brings ANY database — completely empty, or an existing production DB built
 * up manually over time — to the full current schema. Every change is guarded
 * by an INFORMATION_SCHEMA probe, so the script is safe to re-run at any time
 * and never destroys data. This is the single alignment mechanism for
 * multi-site deployments of this codebase: copy the code, configure .env,
 * run this script.
 *
 *   php scripts/setup-database.php                          # apply anything missing
 *   php scripts/setup-database.php --dry-run                # report only, zero writes
 *   php scripts/setup-database.php --with-example-content   # also seed demo pages + theme
 *   php scripts/setup-database.php --yes                    # skip the existing-data confirmation
 *
 * Failsafe: before applying anything, the script scans the target database for
 * existing entries (admins, pages, posts, media, …). If any are found it prints
 * a summary and — when run interactively — asks for confirmation before
 * proceeding. Non-interactive runs (CI/cron) and --yes proceed after printing
 * the summary, keeping the documented `git pull && php scripts/setup-database.php`
 * upgrade path unattended-safe. The script itself is additive and never deletes
 * data either way.
 *
 * Process environment variables always win over .env, so a scratch database
 * can be targeted without touching the configured one:
 *
 *   DB_HOST=127.0.0.1 DB_NAME=scratch DB_USER=root DB_PASS=... php scripts/setup-database.php
 *
 * Forward convention: every future schema change ships as a new dated file in
 * docs/migrations/ (the record) plus one probe-guarded step appended to the
 * manifest below (the mechanism). schema.sql is frozen — do not roll new
 * changes into it.
 */

// ─── Environment (process env always wins over .env) ─────────────────────────

require_once __DIR__ . '/../public/app/helpers/env.php';
require_once __DIR__ . '/../public/app/helpers/art-piece-generation.php';

function loadEnvFile(string $path): void
{
    ah_load_env_file($path);
}

function envValue(string $key, string $default = ''): string
{
    return ah_env($key, $default);
}

function targetPdo(): PDO
{
    $host = envValue('DB_HOST', 'localhost');
    $port = envValue('DB_PORT', '3306');
    $name = envValue('DB_NAME');
    $user = envValue('DB_USER');
    $pass = envValue('DB_PASS');

    if ($name === '' || $user === '') {
        throw new RuntimeException('Missing DB_NAME or DB_USER (set in .env or process environment).');
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if (strtolower(envValue('DB_SSL')) === 'true') {
        if (class_exists('Pdo\\Mysql') && defined('Pdo\\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')) {
            $options[constant('Pdo\\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')] = false;
        } elseif (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
            $options[constant('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')] = false;
        }
    }

    return new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $pass, $options);
}

// ─── Probes ──────────────────────────────────────────────────────────────────

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}

function indexExists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1');
    $stmt->execute([$table, $index]);
    return (bool) $stmt->fetchColumn();
}

function foreignKeyExists(PDO $pdo, string $table, string $constraint): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY' LIMIT 1");
    $stmt->execute([$table, $constraint]);
    return (bool) $stmt->fetchColumn();
}

function columnIsNullable(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
    $stmt->execute([$table, $column]);
    return $stmt->fetchColumn() === 'YES';
}

function columnDataType(PDO $pdo, string $table, string $column): string
{
    $stmt = $pdo->prepare('SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
    $stmt->execute([$table, $column]);
    return (string) $stmt->fetchColumn();
}

// ─── Guarded operations (every write path checks Ctx::$dryRun) ───────────────

final class Ctx
{
    public function __construct(
        public PDO $pdo,
        public bool $dryRun,
    ) {
    }

    /** @var list<string> changes applied (or, in dry-run, missing) this step */
    public array $changes = [];

    public function apply(string $sql, string $desc): void
    {
        if (!$this->dryRun) {
            $this->pdo->exec($sql);
        }
        $this->changes[] = $desc;
    }
}

function ensureColumn(Ctx $ctx, string $table, string $column, string $definition): bool
{
    if (columnExists($ctx->pdo, $table, $column)) {
        return false;
    }
    $ctx->apply("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}", "column {$table}.{$column}");
    return true;
}

function ensureIndex(Ctx $ctx, string $table, string $index, string $definition): void
{
    if (indexExists($ctx->pdo, $table, $index)) {
        return;
    }
    $ctx->apply("ALTER TABLE `{$table}` ADD {$definition}", "index {$table}.{$index}");
}

/** Backfills run only when the guarding schema change was just applied (never
 *  against a live table that has since accumulated real edits). Skipped in
 *  dry-run because the schema change itself hasn't happened. */
function runBackfill(Ctx $ctx, string $sql, string $desc): void
{
    if ($ctx->dryRun) {
        return;
    }
    $ctx->pdo->exec($sql);
    $ctx->changes[] = $desc;
}

// ─── SQL file handling ───────────────────────────────────────────────────────

/**
 * Splits an SQL file into individual statements. Quote-aware: semicolons
 * inside '…', "…", or `…` never split (seed files embed HTML entities like
 * &rsquo; in string literals). Strips -- line comments and /* *​/ blocks
 * outside strings. No DELIMITER handling — no procedures exist in this repo.
 */
function splitSqlStatements(string $sql): array
{
    $statements = [];
    $current = '';
    $len = strlen($sql);
    $inString = null; // null | "'" | '"' | '`'

    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];

        if ($inString !== null) {
            $current .= $ch;
            if ($ch === '\\' && $inString !== '`' && $i + 1 < $len) {
                $current .= $sql[++$i]; // backslash escape inside '…' / "…"
            } elseif ($ch === $inString) {
                if ($i + 1 < $len && $sql[$i + 1] === $inString) {
                    $current .= $sql[++$i]; // doubled quote stays in string
                } else {
                    $inString = null;
                }
            }
            continue;
        }

        if ($ch === "'" || $ch === '"' || $ch === '`') {
            $inString = $ch;
            $current .= $ch;
            continue;
        }

        if ($ch === '-' && $i + 1 < $len && $sql[$i + 1] === '-') {
            while ($i < $len && $sql[$i] !== "\n") {
                $i++;
            }
            $current .= "\n";
            continue;
        }

        if ($ch === '/' && $i + 1 < $len && $sql[$i + 1] === '*') {
            $end = strpos($sql, '*/', $i + 2);
            $i = $end === false ? $len : $end + 1;
            continue;
        }

        if ($ch === ';') {
            $trimmed = trim($current);
            if ($trimmed !== '') {
                $statements[] = $trimmed;
            }
            $current = '';
            continue;
        }

        $current .= $ch;
    }

    $trimmed = trim($current);
    if ($trimmed !== '') {
        $statements[] = $trimmed;
    }

    return $statements;
}

function readSqlFile(string $relativePath): string
{
    $path = dirname(__DIR__) . '/' . $relativePath;
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException("Could not read {$relativePath}.");
    }
    return $sql;
}

/** Applies each CREATE TABLE in an SQL file, skipping tables that exist. */
function ensureTablesFromSql(Ctx $ctx, string $relativePath): void
{
    // MySQL 8/9 reject DEFAULT on the site_settings PK; the assimilation
    // applier has always stripped it the same way.
    $sql = str_replace('id INT NOT NULL PRIMARY KEY DEFAULT 1', 'id INT NOT NULL PRIMARY KEY', readSqlFile($relativePath));

    foreach (splitSqlStatements($sql) as $statement) {
        if (!preg_match('/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-z0-9_]+)`?/i', $statement, $m)) {
            continue; // non-CREATE statements in schema files are not expected; ignore
        }
        $table = $m[1];
        if (tableExists($ctx->pdo, $table)) {
            continue;
        }
        if (!$ctx->dryRun) {
            $ctx->pdo->exec($statement);
        }
        $ctx->changes[] = "table {$table}";
    }
}

// ─── Manifest ────────────────────────────────────────────────────────────────
// Ordered steps. Each closure probes for its changes and applies what is
// missing. Append new steps at the end (before "site_settings baseline row"
// stays fine too — order only matters for dependencies).

function manifest(): array
{
    return [

        ['core schema (schema.sql)', function (Ctx $ctx): void {
            ensureTablesFromSql($ctx, 'schema.sql');
        }],

        ['platform assimilation (2026-06-14)', function (Ctx $ctx): void {
            $scopeAdded = ensureColumn($ctx, 'categories', 'category_scope', "VARCHAR(32) NOT NULL DEFAULT 'portfolio'");
            ensureColumn($ctx, 'categories', 'platform_source_id', 'INT NULL');
            ensureColumn($ctx, 'categories', 'platform_original_slug', 'VARCHAR(191) NULL');
            ensureColumn($ctx, 'categories', 'platform_created_at', 'DATETIME(3) NULL');
            ensureColumn($ctx, 'categories', 'platform_updated_at', 'DATETIME(3) NULL');
            ensureColumn($ctx, 'categories', 'created_at', 'DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)');
            ensureColumn($ctx, 'categories', 'updated_at', 'DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)');
            ensureIndex($ctx, 'categories', 'idx_categories_scope', 'INDEX `idx_categories_scope` (`category_scope`)');
            ensureIndex($ctx, 'categories', 'uniq_categories_platform_source', 'UNIQUE KEY `uniq_categories_platform_source` (`category_scope`, `platform_source_id`)');

            ensureColumn($ctx, 'pages', 'platform_source_id', 'INT NULL');
            ensureColumn($ctx, 'pages', 'platform_original_slug', 'VARCHAR(96) NULL');
            ensureColumn($ctx, 'pages', 'content_format', "VARCHAR(16) NOT NULL DEFAULT 'html'");
            ensureColumn($ctx, 'pages', 'content_text', 'TEXT NULL');
            ensureColumn($ctx, 'pages', 'author_user_id', 'VARCHAR(191) NULL');
            ensureColumn($ctx, 'pages', 'platform_created_at', 'DATETIME(3) NULL');
            ensureColumn($ctx, 'pages', 'platform_updated_at', 'DATETIME(3) NULL');
            ensureIndex($ctx, 'pages', 'uniq_pages_platform_source', 'UNIQUE KEY `uniq_pages_platform_source` (`platform_source_id`)');

            ensureColumn($ctx, 'navigation_items', 'platform_source_id', 'INT NULL');
            ensureColumn($ctx, 'navigation_items', 'platform_original_url', 'VARCHAR(2048) NULL');
            ensureColumn($ctx, 'navigation_items', 'platform_kind', 'VARCHAR(32) NULL');
            ensureColumn($ctx, 'navigation_items', 'open_in_new_tab', 'TINYINT(1) NOT NULL DEFAULT 0');
            ensureIndex($ctx, 'navigation_items', 'uniq_navigation_platform_source', 'UNIQUE KEY `uniq_navigation_platform_source` (`platform_source_id`)');

            ensureTablesFromSql($ctx, 'migrations/2026-06-14-platform-assimilation.sql');

            ensureColumn($ctx, 'platform_collections', 'iframe_code', 'TEXT NULL');
            ensureColumn($ctx, 'platform_collections', 'thumbnail_url', 'VARCHAR(500) NULL AFTER iframe_code');
            ensureColumn($ctx, 'platform_connections', 'access_token_format', "VARCHAR(32) NOT NULL DEFAULT 'platform_encrypted'");
            ensureColumn($ctx, 'platform_connections', 'refresh_token_format', "VARCHAR(32) NOT NULL DEFAULT 'platform_encrypted'");

            if ($scopeAdded) {
                runBackfill($ctx, "UPDATE categories SET category_scope = 'portfolio' WHERE category_scope IS NULL OR category_scope = ''", 'backfill categories.category_scope');
            }
        }],

        ['comments polymorphic (2026-06-15)', function (Ctx $ctx): void {
            $added = ensureColumn($ctx, 'comments', 'item_type', 'VARCHAR(32) NULL AFTER post_id');
            ensureColumn($ctx, 'comments', 'item_id', 'INT NULL AFTER item_type');
            if ($added) {
                runBackfill($ctx, "UPDATE comments SET item_type = 'post', item_id = post_id WHERE post_id IS NOT NULL", 'backfill comments.item_type/item_id');
            }
            if (foreignKeyExists($ctx->pdo, 'comments', 'comments_post_id_fk')) {
                $ctx->apply('ALTER TABLE comments DROP FOREIGN KEY comments_post_id_fk', 'drop FK comments.comments_post_id_fk');
            }
            if (!columnIsNullable($ctx->pdo, 'comments', 'post_id')) {
                $ctx->apply('ALTER TABLE comments MODIFY post_id INT NULL', 'comments.post_id nullable');
            }
            ensureIndex($ctx, 'comments', 'comments_item_idx', 'INDEX comments_item_idx (item_type, item_id)');
            foreach (['posts', 'pages', 'art_pieces', 'platform_collections', 'collections', 'exhibits'] as $table) {
                ensureColumn($ctx, $table, 'comments_enabled', 'TINYINT(1) NOT NULL DEFAULT 0');
            }
        }],

        ['admin IA + canonical origin (2026-06-17)', function (Ctx $ctx): void {
            ensureColumn($ctx, 'site_settings', 'canonical_public_url', 'VARCHAR(255) NULL AFTER palette');
            ensureColumn($ctx, 'site_settings', 'admin_nav_order_json', 'LONGTEXT NULL AFTER canonical_public_url');
        }],

        ['AI personas + capabilities + alt text (2026-06-18)', function (Ctx $ctx): void {
            if (!tableExists($ctx->pdo, 'ai_personas')) {
                $ctx->apply(
                    'CREATE TABLE ai_personas (
                        id            INT          AUTO_INCREMENT PRIMARY KEY,
                        user_id       INT          NOT NULL,
                        name          VARCHAR(128) NOT NULL,
                        system_prompt TEXT         NOT NULL,
                        created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        KEY idx_ai_personas_user (user_id)
                    )',
                    'table ai_personas'
                );
            }
            ensureColumn($ctx, 'user_ai_vendor_settings', 'capabilities', "VARCHAR(128) NOT NULL DEFAULT 'text,code' AFTER enabled");
            ensureColumn($ctx, 'art_pieces', 'thumbnail_alt_text', 'VARCHAR(500) NULL DEFAULT NULL AFTER thumbnail_url');
            ensureColumn($ctx, 'media_files', 'title', 'VARCHAR(255) NULL DEFAULT NULL AFTER original_name');
            ensureColumn($ctx, 'media_files', 'alt_text', 'VARCHAR(500) NULL DEFAULT NULL');
        }],

        ['operational hardening (2026-06-18)', function (Ctx $ctx): void {
            ensureTablesFromSql($ctx, 'docs/migrations/2026-06-18-operational-hardening.sql');
        }],

        ['media draft confirm (2026-06-19)', function (Ctx $ctx): void {
            $added = ensureColumn($ctx, 'media_files', 'status', "ENUM('draft', 'ready') NOT NULL DEFAULT 'ready' AFTER alt_text");
            ensureColumn($ctx, 'media_files', 'poster_media_file_id', 'INT NULL DEFAULT NULL AFTER status');
            ensureColumn($ctx, 'media_files', 'confirmed_at', 'DATETIME NULL DEFAULT NULL AFTER poster_media_file_id');
            ensureIndex($ctx, 'media_files', 'idx_media_files_status', 'KEY idx_media_files_status (status)');
            ensureIndex($ctx, 'media_files', 'idx_media_files_poster', 'KEY idx_media_files_poster (poster_media_file_id)');
            if ($added) {
                runBackfill($ctx, "UPDATE media_files SET status = 'ready', confirmed_at = COALESCE(confirmed_at, created_at) WHERE status <> 'ready' OR confirmed_at IS NULL", 'backfill media_files ready state');
            }
        }],

        ['exhibits/collections updated_at (2026-06-19)', function (Ctx $ctx): void {
            ensureColumn($ctx, 'exhibits', 'updated_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at');
            ensureColumn($ctx, 'collections', 'updated_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at');
        }],

        ['portfolio status (2026-06-19)', function (Ctx $ctx): void {
            foreach (['exhibits', 'collections', 'platform_collections'] as $table) {
                ensureColumn($ctx, $table, 'status', "VARCHAR(16) NOT NULL DEFAULT 'active' AFTER deleted_at");
            }
        }],

        ['art piece version AI attribution (2026-06-20)', function (Ctx $ctx): void {
            ensureColumn($ctx, 'art_piece_versions', 'ai_profile_id', 'INT NULL AFTER generation_model');
            ensureColumn($ctx, 'art_piece_versions', 'ai_persona_id', 'INT NULL AFTER ai_profile_id');
        }],

        ['art piece version generation mode (2026-07-03)', function (Ctx $ctx): void {
            ensureColumn($ctx, 'art_piece_versions', 'generation_mode', 'VARCHAR(32) NULL AFTER generation_model');
        }],

        ['art piece version c2 interactive backfill (2026-07-03)', function (Ctx $ctx): void {
            if (!tableExists($ctx->pdo, 'art_piece_versions') || !columnExists($ctx->pdo, 'art_piece_versions', 'generation_mode')) {
                return;
            }
            runBackfill($ctx, art_piece_c2_interactive_backfill_sql(), 'backfill legacy c2 interactive generation_mode');
        }],

        ['art piece version draft attempts (2026-06-21)', function (Ctx $ctx): void {
            ensureColumn($ctx, 'art_piece_versions', 'is_draft_attempt', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER ai_persona_id');
            ensureColumn($ctx, 'art_piece_versions', 'attempt_sequence_token', 'VARCHAR(36) NULL AFTER is_draft_attempt');
            ensureIndex($ctx, 'art_piece_versions', 'idx_art_piece_versions_sequence_token', 'INDEX idx_art_piece_versions_sequence_token (attempt_sequence_token)');
        }],

        ['site_settings custom_css (2026-07-01)', function (Ctx $ctx): void {
            $added = ensureColumn($ctx, 'site_settings', 'custom_css', 'MEDIUMTEXT NULL AFTER palette');
            if ($added && columnExists($ctx->pdo, 'site_settings', 'settings_json')) {
                runBackfill($ctx, "UPDATE site_settings SET custom_css = JSON_UNQUOTE(JSON_EXTRACT(settings_json, '$.custom_css')) WHERE JSON_EXTRACT(settings_json, '$.custom_css') IS NOT NULL AND id = 1", 'migrate custom_css out of settings_json');
            }
        }],

        ['site_settings theme code columns (2026-07-01)', function (Ctx $ctx): void {
            ensureColumn($ctx, 'site_settings', 'custom_js', 'MEDIUMTEXT NULL AFTER custom_css');
            ensureColumn($ctx, 'site_settings', 'custom_html_body', 'MEDIUMTEXT NULL AFTER custom_js');
        }],

        ['site_theme_snapshots (2026-07-01)', function (Ctx $ctx): void {
            ensureTablesFromSql($ctx, 'docs/migrations/2026-07-01-site-theme-snapshots.sql');
        }],

        ['site_theme_code (2026-07-01)', function (Ctx $ctx): void {
            ensureTablesFromSql($ctx, 'docs/migrations/2026-07-01-site-theme-code.sql');
        }],

        ['post_sections', function (Ctx $ctx): void {
            ensureTablesFromSql($ctx, 'scripts/add-post-sections-table.sql');
        }],

        ['portfolio taxonomy (art_piece_categories)', function (Ctx $ctx): void {
            if (!tableExists($ctx->pdo, 'art_piece_categories')) {
                $ctx->apply(
                    'CREATE TABLE art_piece_categories (
                        art_piece_id INT NOT NULL,
                        category_id INT NOT NULL,
                        PRIMARY KEY (art_piece_id, category_id),
                        CONSTRAINT fk_art_piece_categories_piece FOREIGN KEY (art_piece_id) REFERENCES art_pieces(id) ON DELETE CASCADE,
                        CONSTRAINT fk_art_piece_categories_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
                    'table art_piece_categories'
                );
                if (columnExists($ctx->pdo, 'art_pieces', 'category_id')) {
                    runBackfill($ctx, 'INSERT IGNORE INTO art_piece_categories (art_piece_id, category_id) SELECT id, category_id FROM art_pieces WHERE category_id IS NOT NULL', 'backfill from art_pieces.category_id');
                }
            }
        }],

        ['portfolio ordering (sort_order)', function (Ctx $ctx): void {
            foreach (['platform_collections', 'art_pieces'] as $table) {
                if (ensureColumn($ctx, $table, 'sort_order', 'INT NOT NULL DEFAULT 0') && !$ctx->dryRun) {
                    $rows = $ctx->pdo->query("SELECT id FROM `{$table}` ORDER BY created_at DESC, id DESC")->fetchAll();
                    $update = $ctx->pdo->prepare("UPDATE `{$table}` SET sort_order = ? WHERE id = ?");
                    foreach ($rows as $index => $row) {
                        $update->execute([$index, $row['id']]);
                    }
                    $ctx->changes[] = "backfill {$table}.sort_order";
                }
            }
        }],

        ['system page identity (2026-07-02)', function (Ctx $ctx): void {
            // Schema only. The system-key backfill, alias claiming, and
            // duplicate quarantine self-heal at runtime via
            // Page::ensureSystemPages() (public/app/models/Page.php).
            ensureColumn($ctx, 'pages', 'system_key', 'VARCHAR(100) NULL AFTER id');
            ensureIndex($ctx, 'pages', 'uniq_pages_system_key', 'UNIQUE KEY uniq_pages_system_key (system_key)');
            if (!tableExists($ctx->pdo, 'page_slug_redirects')) {
                $ctx->apply(
                    'CREATE TABLE page_slug_redirects (
                        id          INT AUTO_INCREMENT PRIMARY KEY,
                        old_slug    VARCHAR(255) NOT NULL,
                        page_id     INT NOT NULL,
                        system_key  VARCHAR(100) NULL,
                        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY uniq_page_slug_redirect_old_slug (old_slug),
                        KEY idx_page_slug_redirect_page (page_id),
                        FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
                    )',
                    'table page_slug_redirects'
                );
            }
        }],

        ['page description section (2026-07-02)', function (Ctx $ctx): void {
            $added = ensureColumn($ctx, 'pages', 'description', 'TEXT NULL AFTER nav_label');
            ensureColumn($ctx, 'pages', 'show_description_section', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER description');
            if ($added && tableExists($ctx->pdo, 'site_settings') && columnExists($ctx->pdo, 'site_settings', 'about_body')) {
                runBackfill(
                    $ctx,
                    "UPDATE pages p
                     JOIN site_settings s ON s.id = 1
                     SET p.description = s.about_body, p.show_description_section = 1
                     WHERE p.system_key = 'about'
                       AND (p.description IS NULL OR p.description = '')
                       AND s.about_body <> ''",
                    'migrate about intro onto about-type page'
                );
            }
        }],

        ['page meta descriptions TEXT (2026-07-02)', function (Ctx $ctx): void {
            foreach (['meta_description', 'og_description'] as $column) {
                if (columnDataType($ctx->pdo, 'pages', $column) === 'varchar') {
                    $ctx->apply("ALTER TABLE pages MODIFY `{$column}` TEXT NULL", "pages.{$column} → TEXT");
                }
            }
        }],

        ['forms + art starter templates (2026-07-02)', function (Ctx $ctx): void {
            ensureColumn($ctx, 'page_sections', 'section_kind', "VARCHAR(32) NOT NULL DEFAULT 'content' AFTER page_id");
            ensureColumn($ctx, 'page_sections', 'form_id', 'INT NULL AFTER section_kind');
            ensureColumn($ctx, 'page_sections', 'config_json', 'LONGTEXT NULL AFTER form_id');
            ensureColumn($ctx, 'page_sections', 'is_required', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER config_json');

            if (!tableExists($ctx->pdo, 'forms')) {
                $ctx->apply(
                    "CREATE TABLE forms (
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
                    )",
                    'table forms'
                );
            }

            if (!tableExists($ctx->pdo, 'form_fields')) {
                $ctx->apply(
                    "CREATE TABLE form_fields (
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
                    )",
                    'table form_fields'
                );
            }

            if (!tableExists($ctx->pdo, 'newsletter_subscribers')) {
                $ctx->apply(
                    "CREATE TABLE newsletter_subscribers (
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
                    )",
                    'table newsletter_subscribers'
                );
            }

            if (!tableExists($ctx->pdo, 'art_piece_starter_templates')) {
                $ctx->apply(
                    "CREATE TABLE art_piece_starter_templates (
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
                    )",
                    'table art_piece_starter_templates'
                );
            }

            seedFormsAndStarterTemplates($ctx);
        }],

        ['search fulltext indexes (2026-07-03)', function (Ctx $ctx): void {
            $indexes = [
                ['art_pieces', 'art_pieces_search_fulltext', 'title, description, prompt'],
                ['platform_collections', 'platform_collections_search_fulltext', 'name, description, artist_statement'],
                ['collections', 'collections_search_fulltext', 'name, description'],
                ['exhibits', 'exhibits_search_fulltext', 'title, description'],
                ['pages', 'pages_search_fulltext', 'title, meta_description'],
            ];
            foreach ($indexes as [$table, $index, $columns]) {
                if (!tableExists($ctx->pdo, $table) || indexExists($ctx->pdo, $table, $index)) {
                    continue;
                }
                $ctx->apply(
                    "ALTER TABLE `{$table}` ADD FULLTEXT INDEX {$index} ({$columns})",
                    "fulltext {$table}"
                );
            }
        }],

        ['site_settings baseline row (id=1)', function (Ctx $ctx): void {
            $stmt = $ctx->pdo->query('SELECT 1 FROM site_settings WHERE id = 1 LIMIT 1');
            if ($stmt->fetchColumn()) {
                return;
            }
            // hero_subheading and about_body are TEXT NOT NULL with no
            // default, and the id default was stripped at table creation.
            $ctx->apply("INSERT INTO site_settings (id, site_title, hero_subheading, about_body) VALUES (1, 'My Site', '', '')", 'row site_settings id=1');
        }],

        ['footer_credit VARCHAR→TEXT (2026-07-06)', function (Ctx $ctx): void {
            // Widen footer_credit from VARCHAR(255) to TEXT so that
            // multi-anchor HTML credit lines don't get silently truncated.
            // columnnDataType returns 'varchar' for VARCHAR and 'text' for
            // TEXT/MEDIUMTEXT/LONGTEXT — skip if already at TEXT or wider.
            if (!tableExists($ctx->pdo, 'site_settings')) {
                return;
            }
            $type = columnDataType($ctx->pdo, 'site_settings', 'footer_credit');
            if ($type === '' || $type === 'text' || $type === 'mediumtext' || $type === 'longtext') {
                return;
            }
            $ctx->apply(
                "ALTER TABLE site_settings MODIFY COLUMN footer_credit TEXT NOT NULL DEFAULT ''",
                'site_settings.footer_credit VARCHAR→TEXT'
            );
        }],

        ['copyright_line VARCHAR→TEXT (2026-07-06)', function (Ctx $ctx): void {
            // Widen copyright_line from VARCHAR(255) to TEXT so that
            // HTML markup (links, emphasis) isn't silently truncated.
            if (!tableExists($ctx->pdo, 'site_settings')) {
                return;
            }
            $type = columnDataType($ctx->pdo, 'site_settings', 'copyright_line');
            if ($type === '' || $type === 'text' || $type === 'mediumtext' || $type === 'longtext') {
                return;
            }
            $ctx->apply(
                "ALTER TABLE site_settings MODIFY COLUMN copyright_line TEXT NOT NULL DEFAULT ''",
                'site_settings.copyright_line VARCHAR→TEXT'
            );
        }],

    ];
}

// ─── Content seeds (opt-in, probe-guarded, never overwrite) ──────────────────

function contentSeeds(Ctx $ctx, bool $enabled): void
{
    $label = 'content seeds';

    $homePagesMissing = !seedSlugExists($ctx->pdo, 'home');
    $phase2Missing = !seedSlugExists($ctx->pdo, 'services') && !seedSlugExists($ctx->pdo, 'notes');
    $celestialUnseeded = !siteHasCustomCss($ctx->pdo);
    $themeCodeMissing = tableExists($ctx->pdo, 'site_theme_code') && !themeCodeRowExists($ctx->pdo, 'celestial');

    if (!$enabled) {
        $pending = array_keys(array_filter([
            'home page' => $homePagesMissing,
            'services/notes pages' => $phase2Missing,
            'celestial theme' => $celestialUnseeded,
        ]));
        if ($pending !== []) {
            echo "content: not seeded (" . implode(', ', $pending) . ") — opt in with --with-example-content\n";
        }
        return;
    }

    if ($homePagesMissing) {
        applySeedSqlFile($ctx, 'seed_homepage.sql', 'seed home page');
    } else {
        echo "content: home page already present — skipped\n";
    }

    if ($phase2Missing) {
        applySeedSqlFile($ctx, 'seed_phase2_pages.sql', 'seed services/notes pages');
    } else {
        echo "content: services/notes pages already present — skipped\n";
    }

    if ($celestialUnseeded) {
        runSeedScript($ctx, 'scripts/seed-celestial-theme-code.php');
        runSeedScript($ctx, 'scripts/seed-theme-code-table.php');
    } elseif ($themeCodeMissing) {
        runSeedScript($ctx, 'scripts/seed-theme-code-table.php');
    } else {
        echo "content: celestial theme already seeded — skipped\n";
    }
}

function seedSlugExists(PDO $pdo, string $slug): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM pages WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    return (bool) $stmt->fetchColumn();
}

function siteHasCustomCss(PDO $pdo): bool
{
    if (!columnExists($pdo, 'site_settings', 'custom_css')) {
        return false;
    }
    $value = $pdo->query('SELECT custom_css FROM site_settings WHERE id = 1')->fetchColumn();
    return is_string($value) && trim($value) !== '';
}

function themeCodeRowExists(PDO $pdo, string $themeName): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM site_theme_code WHERE theme_name = ? LIMIT 1');
    $stmt->execute([$themeName]);
    return (bool) $stmt->fetchColumn();
}

function seedFormsAndStarterTemplates(Ctx $ctx): void
{
    if ($ctx->dryRun && (!tableExists($ctx->pdo, 'forms') || !tableExists($ctx->pdo, 'art_piece_starter_templates'))) {
        $ctx->changes[] = 'seed forms defaults';
        $ctx->changes[] = 'seed art starter templates';
        return;
    }

    seedDefaultForms($ctx);
    seedDefaultArtStarterTemplates($ctx);
}

function seedDefaultForms(Ctx $ctx): void
{
    $contactId = ensureFormSeed($ctx, [
        'form_key' => 'contact_form',
        'title' => 'Contact Form',
        'description' => 'Send a message using the configured recipient email.',
        'form_type' => 'email',
        'recipient_email' => getenv('CONTACT_TO_EMAIL') ?: null,
        'recaptcha_site_key' => getenv('RECAPTCHA_SITE_KEY') ?: null,
        'encrypted_recaptcha_secret' => encryptedSeedSecret(getenv('RECAPTCHA_SECRET_KEY') ?: ''),
        'recaptcha_minimum_score' => getenv('RECAPTCHA_MIN_SCORE') ?: '0.50',
        'success_message' => 'Thanks for reaching out. Your message was sent.',
        'submit_label' => 'Send inquiry',
    ]);
    ensureFormFields($ctx, $contactId, [
        ['name', 'Name', 'text', 1, 0, '', '', null],
        ['email', 'Email', 'email', 1, 1, '', '', null],
        ['organization', 'Organization', 'text', 0, 2, '', 'Optional', null],
        ['inquiry_type', 'Inquiry type', 'select', 1, 3, '', '', [
            ['value' => 'collaboration', 'label' => 'Collaboration'],
            ['value' => 'hiring', 'label' => 'Hiring'],
            ['value' => 'project_help', 'label' => 'Project help'],
            ['value' => 'strategy_help', 'label' => 'Strategy help'],
            ['value' => 'other', 'label' => 'Other'],
        ]],
        ['message', 'Message', 'textarea', 1, 4, '', '', null],
    ]);

    $newsletterId = ensureFormSeed($ctx, [
        'form_key' => 'newsletter_signup',
        'title' => 'Newsletter Signup',
        'description' => 'Collect email addresses for future updates.',
        'form_type' => 'newsletter',
        'recipient_email' => null,
        'recaptcha_site_key' => getenv('RECAPTCHA_SITE_KEY') ?: null,
        'encrypted_recaptcha_secret' => encryptedSeedSecret(getenv('RECAPTCHA_SECRET_KEY') ?: ''),
        'recaptcha_minimum_score' => getenv('RECAPTCHA_MIN_SCORE') ?: '0.50',
        'success_message' => 'Thanks for signing up.',
        'submit_label' => 'Sign up',
    ]);
    ensureFormFields($ctx, $newsletterId, [
        ['email', 'Email', 'email', 1, 0, '', '', null],
        ['consent', 'I consent to receive updates.', 'checkbox', 0, 1, 'Consent defaults to true for newsletter signups.', '', null],
    ]);

    ensureContactPageAndFormSection($ctx, $contactId);
}

function ensureFormSeed(Ctx $ctx, array $form): int
{
    $stmt = $ctx->pdo->prepare('SELECT id FROM forms WHERE form_key = ? LIMIT 1');
    $stmt->execute([$form['form_key']]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        backfillExistingFormSeed($ctx, (int) $existing, $form);
        return (int) $existing;
    }
    if ($ctx->dryRun) {
        $ctx->changes[] = 'seed form ' . $form['form_key'];
        return 0;
    }
    $insert = $ctx->pdo->prepare(
        'INSERT INTO forms
            (form_key, title, description, form_type, status, recipient_email,
             recaptcha_site_key, encrypted_recaptcha_secret, recaptcha_minimum_score,
             success_message, submit_label)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insert->execute([
        $form['form_key'],
        $form['title'],
        $form['description'],
        $form['form_type'],
        'active',
        $form['recipient_email'],
        $form['recaptcha_site_key'],
        $form['encrypted_recaptcha_secret'],
        $form['recaptcha_minimum_score'],
        $form['success_message'],
        $form['submit_label'],
    ]);
    $ctx->changes[] = 'seed form ' . $form['form_key'];
    return (int) $ctx->pdo->lastInsertId();
}

function backfillExistingFormSeed(Ctx $ctx, int $formId, array $form): void
{
    if ($ctx->dryRun) {
        return;
    }
    $stmt = $ctx->pdo->prepare(
        "UPDATE forms
            SET recipient_email = CASE WHEN (recipient_email IS NULL OR recipient_email = '') THEN ? ELSE recipient_email END,
                recaptcha_site_key = CASE WHEN (recaptcha_site_key IS NULL OR recaptcha_site_key = '') THEN ? ELSE recaptcha_site_key END,
                encrypted_recaptcha_secret = CASE WHEN (encrypted_recaptcha_secret IS NULL OR encrypted_recaptcha_secret = '') THEN ? ELSE encrypted_recaptcha_secret END,
                recaptcha_minimum_score = CASE WHEN recaptcha_minimum_score IS NULL THEN ? ELSE recaptcha_minimum_score END
          WHERE id = ?"
    );
    $stmt->execute([
        $form['recipient_email'],
        $form['recaptcha_site_key'],
        $form['encrypted_recaptcha_secret'],
        $form['recaptcha_minimum_score'],
        $formId,
    ]);
}

function ensureFormFields(Ctx $ctx, int $formId, array $fields): void
{
    if ($formId <= 0) {
        return;
    }
    $exists = $ctx->pdo->prepare('SELECT 1 FROM form_fields WHERE form_id = ? AND field_key = ? LIMIT 1');
    $insert = $ctx->pdo->prepare(
        'INSERT INTO form_fields
            (form_id, field_key, label, field_type, is_required, sort_order, help_text, placeholder, options_json)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($fields as [$key, $label, $type, $required, $sort, $help, $placeholder, $options]) {
        $exists->execute([$formId, $key]);
        if ($exists->fetchColumn()) {
            continue;
        }
        if ($ctx->dryRun) {
            $ctx->changes[] = 'seed form field ' . $key;
            continue;
        }
        $insert->execute([
            $formId,
            $key,
            $label,
            $type,
            $required,
            $sort,
            $help ?: null,
            $placeholder ?: null,
            $options === null ? null : json_encode($options, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
        $ctx->changes[] = 'seed form field ' . $key;
    }
}

function ensureContactPageAndFormSection(Ctx $ctx, int $contactFormId): void
{
    if ($contactFormId <= 0) {
        return;
    }
    $stmt = $ctx->pdo->query("SELECT id FROM pages WHERE system_key = 'contact' OR slug = 'contact' ORDER BY system_key = 'contact' DESC, id ASC LIMIT 1");
    $pageId = (int) $stmt->fetchColumn();
    if ($pageId <= 0) {
        if ($ctx->dryRun) {
            $ctx->changes[] = 'seed contact system page';
            return;
        }
        $insert = $ctx->pdo->prepare(
            "INSERT INTO pages (system_key, title, slug, status, template, nav_label, show_in_nav, sort_order)
             VALUES ('contact', 'Contact', 'contact', 'published', 'standard', 'Contact', 1, 20)"
        );
        $insert->execute();
        $pageId = (int) $ctx->pdo->lastInsertId();
        $ctx->changes[] = 'seed contact system page';
    } elseif (!$ctx->dryRun) {
        $ctx->pdo->prepare("UPDATE pages SET system_key = 'contact', status = 'published' WHERE id = ? AND (system_key IS NULL OR system_key = '')")->execute([$pageId]);
    }

    $exists = $ctx->pdo->prepare("SELECT 1 FROM page_sections WHERE page_id = ? AND section_kind = 'form' AND form_id = ? LIMIT 1");
    $exists->execute([$pageId, $contactFormId]);
    if ($exists->fetchColumn()) {
        return;
    }
    if ($ctx->dryRun) {
        $ctx->changes[] = 'seed contact form page section';
        return;
    }
    $insertSection = $ctx->pdo->prepare(
        "INSERT INTO page_sections (page_id, section_kind, form_id, heading, content, wrapper_class, sort_order, is_required)
         VALUES (?, 'form', ?, 'Contact Form', '', 'managed-section', 0, 1)"
    );
    $insertSection->execute([$pageId, $contactFormId]);
    $ctx->changes[] = 'seed contact form page section';
}

function encryptedSeedSecret(string $secret): ?string
{
    static $warned = false;

    $secret = trim($secret);
    if ($secret === '') {
        return null;
    }
    require_once __DIR__ . '/../public/app/helpers/encryption.php';
    try {
        return encrypt_string($secret, ai_encryption_key());
    } catch (Throwable) {
        if (!$warned) {
            $warned = true;
            fwrite(STDERR, "⚠ RECAPTCHA_SECRET_KEY is set but could not be encrypted (AI_SETTINGS_ENCRYPTION_KEY missing or invalid). Form secrets are seeded as NULL — set the key and re-run, or configure the secret in /admin/forms.\n");
        }
        return null;
    }
}

function seedDefaultArtStarterTemplates(Ctx $ctx): void
{
    foreach (defaultArtStarterTemplates() as $template) {
        $stmt = $ctx->pdo->prepare('SELECT 1 FROM art_piece_starter_templates WHERE template_key = ? LIMIT 1');
        $stmt->execute([$template['template_key']]);
        if ($stmt->fetchColumn()) {
            continue;
        }
        if ($ctx->dryRun) {
            $ctx->changes[] = 'seed art starter template ' . $template['template_key'];
            continue;
        }
        $insert = $ctx->pdo->prepare(
            'INSERT INTO art_piece_starter_templates
                (template_key, engine, generation_mode, label, description, html_code, css_code, js_code, is_default, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1)'
        );
        $insert->execute([
            $template['template_key'],
            $template['engine'],
            $template['generation_mode'],
            $template['label'],
            $template['description'],
            $template['html_code'],
            $template['css_code'],
            $template['js_code'],
        ]);
        $ctx->changes[] = 'seed art starter template ' . $template['template_key'];
    }
}

function defaultArtStarterTemplates(): array
{
    return require dirname(__DIR__) . '/public/app/config/art-starter-templates.php';
}

function applySeedSqlFile(Ctx $ctx, string $relativePath, string $desc): void
{
    if ($ctx->dryRun) {
        echo "content: would {$desc} ({$relativePath})\n";
        return;
    }
    foreach (splitSqlStatements(readSqlFile($relativePath)) as $statement) {
        $ctx->pdo->exec($statement);
    }
    echo "content: ✓ {$desc}\n";
}

/** Seed scripts load .env with a process-env-wins loader, so DB_* overrides
 *  set for this installer propagate into the child and hit the same DB. */
function runSeedScript(Ctx $ctx, string $relativePath): void
{
    if ($ctx->dryRun) {
        echo "content: would run {$relativePath}\n";
        return;
    }
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/' . $relativePath);
    passthru($cmd, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException("{$relativePath} exited with code {$exitCode}.");
    }
}

// ─── Existing-data pre-flight ────────────────────────────────────────────────

/**
 * Scan the target database for pre-existing entries so an operator pointing
 * this installer at the wrong (non-empty) database finds out before anything
 * is applied. Read-only; every probe is guarded by a table-existence check so
 * it works on a completely empty database.
 */
function preflightExistingData(PDO $pdo): array
{
    $found = [];

    $probes = [
        'admin_identities' => 'admin identities',
        'users' => 'users',
        'pages' => 'pages',
        'posts' => 'posts',
        'art_pieces' => 'art pieces',
        'exhibits' => 'exhibits',
        'media_files' => 'media files',
        'comments' => 'comments',
    ];

    foreach ($probes as $table => $label) {
        if (!tableExists($pdo, $table)) {
            continue;
        }
        $count = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        if ($count > 0) {
            $found[$label] = $count;
        }
    }

    return $found;
}

function confirmProceedOnTty(): bool
{
    if (!function_exists('stream_isatty') || !stream_isatty(STDIN)) {
        return true; // non-interactive (CI/cron): summary already printed, proceed
    }
    echo "Continue against this database? [y/N] ";
    $answer = strtolower(trim((string) fgets(STDIN)));
    return in_array($answer, ['y', 'yes'], true);
}

// ─── Runner ──────────────────────────────────────────────────────────────────

loadEnvFile(dirname(__DIR__) . '/.env');

$dryRun = in_array('--dry-run', $argv, true);
$withContent = in_array('--with-example-content', $argv, true);
$assumeYes = in_array('--yes', $argv, true);

try {
    $pdo = targetPdo();
    $pdo->exec('SET NAMES utf8mb4');

    $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
    echo ($dryRun ? 'DRY RUN — no changes will be made. ' : '') . "Target database: {$dbName}\n\n";

    $existing = preflightExistingData($pdo);
    if ($existing !== []) {
        $parts = [];
        foreach ($existing as $label => $count) {
            $parts[] = "{$count} {$label}";
        }
        echo "┌─────────────────────────────────────────────────────────────────┐\n";
        echo "│ WARNING: this database is NOT empty.                            │\n";
        echo "└─────────────────────────────────────────────────────────────────┘\n";
        echo 'Existing entries found: ' . implode(', ', $parts) . ".\n";
        echo "This installer is additive and idempotent — it never deletes data.\n";
        echo "Missing schema steps and seeds will be applied on top of the existing data.\n\n";

        if (!$dryRun && !$assumeYes && !confirmProceedOnTty()) {
            echo "Aborted. No changes were made. Re-run with --yes to skip this confirmation.\n";
            exit(0);
        }
    }

    $steps = manifest();
    $total = count($steps);
    $anyChanges = false;

    foreach ($steps as $i => [$label, $run]) {
        $ctx = new Ctx($pdo, $dryRun);
        $run($ctx);
        $num = str_pad((string) ($i + 1), 2, ' ', STR_PAD_LEFT);
        $dots = str_pad('', max(1, 44 - strlen($label)), '.');
        if ($ctx->changes === []) {
            echo "[{$num}/{$total}] {$label} {$dots} - already applied\n";
        } else {
            $anyChanges = true;
            $mark = $dryRun ? '✗ missing' : '✓ applied';
            echo "[{$num}/{$total}] {$label} {$dots} {$mark} (" . implode(', ', $ctx->changes) . ")\n";
        }
    }

    echo "\n";
    contentSeeds(new Ctx($pdo, $dryRun), $withContent);

    if ($dryRun) {
        echo $anyChanges
            ? "\nDry run complete: schema changes are pending. Run without --dry-run to apply.\n"
            : "\nDry run complete: schema is fully up to date.\n";
    } else {
        echo $anyChanges ? "\n✓ Done — schema is now up to date.\n" : "\n✓ Nothing to do — schema already up to date.\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, "✗ Setup failed: " . $e->getMessage() . "\n");
    fwrite(STDERR, "MySQL DDL auto-commits, so already-applied changes persist. Fix the cause and re-run — completed steps will be skipped.\n");
    exit(1);
}
