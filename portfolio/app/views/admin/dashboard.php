<?php
$pageTitle = 'Dashboard — Fornesus Admin';
ob_start();
?>
<div class="admin-section">
    <h1 class="admin-heading">Archive Dashboard</h1>
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
            <span class="stat-num"><?= $messageCount ?></span>
            <span class="stat-label">Messages</span>
        </div>
        <div class="stat-card">
            <span class="stat-num"><?= $trashCount ?></span>
            <span class="stat-label">In Trash</span>
        </div>
    </div>
    <div class="dashboard-links">
        <a href="/admin/artworks/create" class="admin-btn">+ Add Work</a>
        <a href="/" class="admin-btn admin-btn-ghost" target="_blank">View Site ↗</a>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
