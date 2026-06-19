<?php

declare(strict_types=1);

/**
 * Whether this deployment has completed first-run setup: at least one
 * admin has successfully logged in via OAuth (which requires DB_*,
 * GITHUB/GOOGLE_CLIENT_ID/SECRET, and an ADMIN_*_USERNAMES/EMAILS allowlist
 * entry to all already be configured correctly). Fails open (treats setup
 * as complete) on any DB error, so a database outage degrades to normal
 * "DB unavailable" behavior elsewhere rather than locking out the public
 * site behind a setup gate it can no longer evaluate.
 */
function site_bootstrap_complete(): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    if (!function_exists('ah_table_exists') || !ah_table_exists('admin_identities')) {
        return $cached = true;
    }

    try {
        $stmt = db()->query("SELECT 1 FROM admin_identities WHERE is_active = 1 LIMIT 1");
        return $cached = (bool) $stmt->fetchColumn();
    } catch (Throwable) {
        return $cached = true;
    }
}

/**
 * Onboarding checklist for the admin "Setup" screen. Each item is
 * informational, not a gate — the public-facing bootstrap gate above only
 * depends on site_bootstrap_complete().
 */
function site_bootstrap_checklist(): array
{
    $items = [];

    $items[] = [
        'label' => 'Admin sign-in configured',
        'done' => site_bootstrap_complete(),
        'detail' => 'At least one allowlisted GitHub/Google identity has signed in.',
        'href' => null,
    ];

    $settings = class_exists('SiteSettings') ? (SiteSettings::current() ?: []) : [];
    $items[] = [
        'label' => 'Site title set',
        'done' => trim((string) ($settings['site_title'] ?? '')) !== '',
        'detail' => 'Replace the default site name shown in page titles and the header.',
        'href' => '/admin/site-identity',
    ];

    $items[] = [
        'label' => 'Canonical public URL set',
        'done' => trim((string) ($settings['canonical_public_url'] ?? '')) !== '',
        'detail' => 'Used for canonical tags, feeds, and social card URLs. Falls back to the request host if unset.',
        'href' => '/admin/site-identity',
    ];

    $aiConfigured = false;
    if (function_exists('ah_table_exists') && ah_table_exists('user_ai_vendor_settings')) {
        try {
            $aiConfigured = (bool) db()->query('SELECT 1 FROM user_ai_vendor_settings WHERE enabled = 1 LIMIT 1')->fetchColumn();
        } catch (Throwable) {
            $aiConfigured = false;
        }
    }
    $items[] = [
        'label' => 'AI vendor configured (optional)',
        'done' => $aiConfigured,
        'detail' => 'Needed for AI text improvement, alt-text generation, and piece generation.',
        'href' => '/admin/user-profiles',
    ];

    $items[] = [
        'label' => 'Contact form configured (optional)',
        'done' => configValue('RECAPTCHA_SITE_KEY') !== '' && configValue('SMTP_HOST') !== '',
        'detail' => 'Set RECAPTCHA_SITE_KEY/SECRET_KEY and SMTP_* in .env to enable the /contact form.',
        'href' => null,
    ];

    return $items;
}
