<?php
$pageTitle = 'Dashboard — Augment Humankind Admin';
ob_start();
?>
<div class="admin-section">
    <h1 class="admin-heading">Admin Dashboard</h1>
    <?php
    $dashboardCounts = [
        'dashboard' => ['value' => $publishedPosts + $draftPosts + $scheduledPosts, 'label' => 'Overview'],
        'posts' => ['value' => $publishedPosts + $draftPosts + $scheduledPosts, 'label' => 'Posts'],
        'comments' => ['value' => $commentCount + $reactionCount, 'label' => 'Comments & Reactions'],
        'feed_sources' => ['value' => $feedSourceCount, 'label' => 'Feed Sources'],
        'feed_queue' => ['value' => $pendingFeeds, 'label' => 'Review Queue'],
        'identity' => ['value' => $assetCount, 'label' => 'Identity Assets'],
        'navigation' => ['value' => count(ah_public_navigation_items()), 'label' => 'Navigation'],
        'ai_settings' => ['value' => $aiProfileCount + $aiKeyCount, 'label' => 'AI Profiles & Keys'],
        'users' => ['value' => $userCount, 'label' => 'Users'],
        'connections' => ['value' => $connectionCount + $syndicationCount, 'label' => 'Connections'],
        'pages' => ['value' => $pageCount, 'label' => 'Pages'],
        'exhibits' => ['value' => $exhibitCount, 'label' => 'Exhibits'],
        'pieces' => ['value' => $pieceCount, 'label' => 'Pieces'],
        'categories' => ['value' => $categoryCount, 'label' => 'Categories'],
        'art_media' => ['value' => $artMediaCount, 'label' => 'Art Media'],
        'exhibit_collections' => ['value' => $collectionCount, 'label' => 'Exhibit Collections'],
        'platform_collections' => ['value' => $collectionCount, 'label' => 'Platform Collections'],
        'media' => ['value' => $mediaCount, 'label' => 'Media'],
        'trash' => ['value' => $trashCount, 'label' => 'In Trash'],
    ];
    ?>
    <div class="dashboard-stats">
        <?php foreach (admin_navigation_ordered_items() as $item): ?>
            <?php $card = $dashboardCounts[$item['key']] ?? null; ?>
            <?php if ($card === null) continue; ?>
            <a href="<?= e($item['href']) ?>" class="stat-card stat-card-link">
                <span class="stat-num"><?= (int) $card['value'] ?></span>
                <span class="stat-label"><?= e($item['label']) ?></span>
                <span class="admin-hint"><?= e($card['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
    <div class="dashboard-links">
        <a href="/" class="admin-btn admin-btn-ghost" target="_blank" rel="noopener">View Site &#8599;</a>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
