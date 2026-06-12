<?php
$pageTitle = 'Dashboard — Augment Humankind Admin';
ob_start();
?>
<div class="admin-section">
    <h1 class="admin-heading">Admin Dashboard</h1>
    <div class="dashboard-stats">
        <div class="stat-card">
            <span class="stat-num"><?= $artworkCount ?></span>
            <span class="stat-label">Works</span>
        </div>
        <div class="stat-card">
            <span class="stat-num"><?= $categoryCount ?></span>
            <span class="stat-label">Categories</span>
        </div>
        <div class="stat-card">
            <span class="stat-num"><?= $exhibitCount ?></span>
            <span class="stat-label">Exhibits</span>
        </div>
        <div class="stat-card">
            <span class="stat-num"><?= $pageCount ?></span>
            <span class="stat-label">Pages</span>
        </div>
    </div>
    <div class="dashboard-links">
        <a href="/" class="admin-btn admin-btn-ghost" target="_blank" rel="noopener">View Site &#8599;</a>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
