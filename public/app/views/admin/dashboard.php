<?php
$pageTitle = 'Dashboard — Augment Humankind Admin';
ob_start();
?>
<div class="admin-section">
    <h1 class="admin-heading">Admin Dashboard</h1>
    <div class="dashboard-stats">
        <div class="stat-card">
            <span class="stat-num"><?= $exhibitCount ?></span>
            <span class="stat-label">Exhibits</span>
        </div>
        <div class="stat-card">
            <span class="stat-num"><?= $categoryCount ?></span>
            <span class="stat-label">Categories</span>
        </div>
        <div class="stat-card">
            <span class="stat-num"><?= $collectionCount ?></span>
            <span class="stat-label">Collections</span>
        </div>
        <div class="stat-card">
            <span class="stat-num"><?= $pageCount ?></span>
            <span class="stat-label">Pages</span>
        </div>
        <div class="stat-card">
            <span class="stat-num"><?= $publishedPosts ?></span>
            <span class="stat-label">Published</span>
        </div>
        <div class="stat-card">
            <span class="stat-num"><?= $scheduledPosts ?></span>
            <span class="stat-label">Scheduled</span>
        </div>
        <div class="stat-card">
            <span class="stat-num"><?= $draftPosts ?></span>
            <span class="stat-label">Drafts</span>
        </div>
        <div class="stat-card">
            <span class="stat-num"><?= $commentCount ?></span>
            <span class="stat-label">Comments</span>
        </div>
        <div class="stat-card">
            <span class="stat-num"><?= $reactionCount ?></span>
            <span class="stat-label">Reactions</span>
        </div>
        <div class="stat-card">
            <span class="stat-num"><?= $connectionCount ?></span>
            <span class="stat-label">Connections</span>
        </div>
        <div class="stat-card">
            <span class="stat-num"><?= $syndicationCount ?></span>
            <span class="stat-label">Syndications</span>
        </div>
        <div class="stat-card">
            <span class="stat-num"><?= $pieceCount ?></span>
            <span class="stat-label">Pieces</span>
        </div>
        <div class="stat-card">
            <span class="stat-num"><?= $mediaCount ?></span>
            <span class="stat-label">Media</span>
        </div>
        <div class="stat-card">
            <span class="stat-num"><?= $assetCount ?></span>
            <span class="stat-label">Assets</span>
        </div>
        <div class="stat-card">
            <span class="stat-num"><?= $feedSourceCount ?></span>
            <span class="stat-label">Feed Sources</span>
        </div>
        <div class="stat-card">
            <span class="stat-num"><?= $pendingFeeds ?></span>
            <span class="stat-label">Pending Feeds</span>
        </div>
        <div class="stat-card">
            <span class="stat-num"><?= $trashCount ?></span>
            <span class="stat-label">In Trash</span>
        </div>
    </div>
    <div class="dashboard-links">
        <a href="/" class="admin-btn admin-btn-ghost" target="_blank" rel="noopener">View Site &#8599;</a>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
