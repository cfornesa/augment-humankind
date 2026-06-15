<?php

declare(strict_types=1);

$pageTitle = 'Platform Connection Diagnostics';

ob_start();
?>
<div class="admin-container">
    <div class="admin-header-row">
        <h1>Platform Connection Diagnostics</h1>
        <a href="/admin/platform-connections" class="admin-btn admin-btn-ghost">Back</a>
    </div>

    <p class="admin-hint" style="margin-bottom: 1.5rem;">
        This page shows the OAuth configuration status for each supported platform.
        To enable OAuth flows, register the <strong>Redirect URI</strong> in the provider's developer console
        and set the corresponding <code>CLIENT_ID</code> and <code>CLIENT_SECRET</code> environment variables.
    </p>

    <table class="admin-table">
        <thead>
            <tr>
                <th>Platform</th>
                <th>Client ID</th>
                <th>Client Secret</th>
                <th>Redirect URI</th>
                <th>Endpoint Reachable</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($results as $provider => $r): ?>
                <tr>
                    <td><?= e(ucwords(str_replace('-', ' ', $provider))) ?></td>
                    <td>
                        <?php if ($r['client_id_set']): ?>
                            <span class="status-badge status-active">Set</span>
                        <?php else: ?>
                            <span class="status-badge status-draft">Missing</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($r['client_secret_set']): ?>
                            <span class="status-badge status-active">Set</span>
                        <?php else: ?>
                            <span class="status-badge status-draft">Missing</span>
                        <?php endif; ?>
                    </td>
                    <td><code style="font-size: 0.8rem;"><?= e($r['redirect_uri']) ?></code></td>
                    <td>
                        <?php if ($r['endpoint_reachable']): ?>
                            <span class="status-badge status-active">Yes (<?= (int) ($r['endpoint_status'] ?? 0) ?>)</span>
                        <?php else: ?>
                            <span class="status-badge status-draft">No</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($r['configured'] && $r['endpoint_reachable']): ?>
                            <span class="status-badge status-active">Ready</span>
                        <?php elseif ($r['configured']): ?>
                            <span class="status-badge status-scheduled">Partial</span>
                        <?php else: ?>
                            <span class="status-badge status-draft">Not configured</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="admin-hint" style="margin-top: 1.5rem;">
        <strong>Setup checklist:</strong>
        <ul style="margin-top: 0.5rem; padding-left: 1.2rem;">
            <li>WordPress.com — <code>WORDPRESS_COM_CLIENT_ID</code> and <code>WORDPRESS_COM_CLIENT_SECRET</code></li>
            <li>Blogger — <code>BLOGGER_GOOGLE_CLIENT_ID</code> and <code>BLOGGER_GOOGLE_CLIENT_SECRET</code></li>
            <li>LinkedIn — <code>LINKEDIN_CLIENT_ID</code> and <code>LINKEDIN_CLIENT_SECRET</code></li>
            <li>Facebook — <code>FACEBOOK_CLIENT_ID</code> and <code>FACEBOOK_CLIENT_SECRET</code></li>
            <li>Instagram — <code>INSTAGRAM_CLIENT_ID</code> and <code>INSTAGRAM_CLIENT_SECRET</code> (falls back to Facebook credentials)</li>
        </ul>
    </div>
</div>
<?php
$content = ob_get_clean();

require dirname(__DIR__) . '/layout.php';
