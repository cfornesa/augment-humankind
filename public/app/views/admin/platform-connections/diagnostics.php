<?php

declare(strict_types=1);

$pageTitle = 'Platform Connection Diagnostics';

ob_start();
?>
<div class="admin-container">
    <div class="admin-header-row">
        <h1>Platform Connection Diagnostics</h1>
    </div>

    <nav class="admin-tabs" aria-label="Platform connection tabs">
        <a href="/admin/platform-connections?tab=connections" class="admin-tab">Connections</a>
        <a href="/admin/platform-connections?tab=syndications" class="admin-tab">Syndications</a>
        <a href="/admin/platform-connections/diagnostics" class="admin-tab active">Diagnostics</a>
    </nav>

    <p class="admin-hint" style="margin-bottom: 1.5rem;">
        This page shows setup status for all 8 supported publishing providers.
        The first table covers OAuth apps. To enable those flows, register the
        <strong>Redirect URI</strong> in the provider's developer console and save the
        corresponding Client ID and Client Secret in
        <strong>Platform Connections → Configure App</strong>.
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
                    <td><?= e((string) ($r['label'] ?? ucwords(str_replace('-', ' ', $provider)))) ?></td>
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
            <li>Save the provider app credentials from the Platform Connections screen.</li>
            <li>Register the exact Redirect URI shown above in the provider's developer console.</li>
            <li>For WordPress.com and Blogger, add the blog URL when known so the callback can resolve the correct blog/site metadata.</li>
        </ul>
    </div>

    <h2 class="admin-subheading" style="margin-top: 2rem;">Credential-Based Connections</h2>
    <p class="admin-hint" style="margin-bottom: 1rem;">
        These platforms authenticate with app passwords or session cookies instead of
        OAuth, so they're not in the table above. Configure them from
        <strong>Platform Connections → Set up / Edit</strong>. Field status only shows
        whether a value is present, never the stored credential itself.
    </p>

    <table class="admin-table">
        <thead>
            <tr>
                <th>Platform</th>
                <th>Fields</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($credentialResults as $platform => $r): ?>
                <tr>
                    <td><?= e((string) $r['label']) ?></td>
                    <td>
                        <?php foreach ($r['fields'] as $fieldLabel => $isSet): ?>
                            <span class="status-badge <?= $isSet ? 'status-active' : 'status-draft' ?>" style="margin: 0 0.25rem 0.25rem 0;">
                                <?= e((string) $fieldLabel) ?>: <?= $isSet ? 'Set' : 'Missing' ?>
                            </span>
                        <?php endforeach; ?>
                    </td>
                    <td>
                        <?php if ($r['configured']): ?>
                            <span class="status-badge status-active">Configured</span>
                        <?php else: ?>
                            <span class="status-badge status-draft">Not configured</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
$content = ob_get_clean();

require dirname(__DIR__) . '/layout.php';
