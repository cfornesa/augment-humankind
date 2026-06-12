<?php
$pageTitle  = 'Categories — Fornesus Art';
$activePage = 'categories';
$metaTitle = 'Categories — Fornesus Art';
$metaDescription = 'Explore the categories that organize works within the Fornesus Art archive.';
$ogTitle = $metaTitle;
$ogDescription = $metaDescription;
$metaImage = $categories[0]['thumbnail_value'] ?? null;
$metaImageAlt = $categories[0]['name'] ?? 'Categories from Fornesus Art';
$canonicalUrl = seo_absolute_url('/categories');

ob_start();
?>
<div class="collection-page">
    <div class="collection-header">
        <h1 class="collection-title">Categories</h1>
    </div>

    <?php if (empty($categories)): ?>
        <p class="gallery-empty">No categories have been created yet.</p>
    <?php else: ?>
        <div class="collection-grid" aria-label="Category list">
            <?php foreach ($categories as $catIndex => $cat): ?>
                <a href="/category/<?= htmlspecialchars($cat['slug']) ?>" class="collection-card" aria-label="View category <?= htmlspecialchars($cat['name']) ?>">
                    <div class="collection-thumb-wrap">
                        <?php if ($cat['thumbnail_value']): ?>
                            <img
                                src="<?= htmlspecialchars($cat['thumbnail_value']) ?>"
                                alt="<?= htmlspecialchars($cat['name']) ?>"
                                loading="<?= $catIndex === 0 ? 'eager' : 'lazy' ?>"
                                decoding="async"
                                fetchpriority="<?= $catIndex === 0 ? 'high' : 'auto' ?>"
                            >
                        <?php else: ?>
                            <div class="collection-thumb-placeholder" aria-hidden="true"></div>
                        <?php endif ?>
                        <div class="artwork-glow" aria-hidden="true"></div>
                    </div>
                    <div class="collection-card-meta">
                        <span class="collection-card-name"><?= htmlspecialchars($cat['name']) ?></span>
                        <?php if ($cat['description']): ?>
                            <span class="collection-card-desc">
                                <?= htmlspecialchars(mb_substr($cat['description'], 0, 100)) ?><?= mb_strlen($cat['description']) > 100 ? '…' : '' ?>
                            </span>
                        <?php endif ?>
                    </div>
                </a>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
