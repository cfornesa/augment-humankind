<?php

declare(strict_types=1);

/**
 * Shared draft/archived notice, shown under the heading on a public show
 * page. Pass $status ('draft'|'archived'|'active'|'published'|null) — any
 * other value renders nothing.
 */

$statusBannerStatus = strtolower(trim((string) ($status ?? '')));

if ($statusBannerStatus === 'draft'): ?>
    <section class="form-status form-status-draft" aria-label="Draft notice">
        <h3>Draft</h3>
        <p><?= htmlspecialchars($statusBannerNote ?? 'This content is in draft status and will not appear in public listings.', ENT_QUOTES, 'UTF-8') ?></p>
    </section>
<?php elseif ($statusBannerStatus === 'archived'): ?>
    <section class="form-status form-status-archived" aria-label="Archived notice">
        <h3>Archived</h3>
        <p><?= htmlspecialchars($statusBannerNote ?? 'This content has been archived and will not appear in public listings.', ENT_QUOTES, 'UTF-8') ?></p>
    </section>
<?php endif;
unset($statusBannerStatus, $statusBannerNote, $status); ?>
