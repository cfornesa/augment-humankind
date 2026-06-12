<?php
$pageTitle  = ($category['name'] ?? 'Category') . ' — Fornesus Art';
$activePage = 'categories';
$metaTitle = ($category['name'] ?: 'Category') . ' — Fornesus Art';
$metaDescription = seo_excerpt($category['description'] ?? null, 170)
    ?: 'Works collected under the ' . ($category['name'] ?: 'selected') . ' category.';
$ogTitle = $metaTitle;
$ogDescription = $metaDescription;
$metaImage = $category['thumbnail_value'] ?: ($artworks[0]['thumbnail_value'] ?? null);
$metaImageAlt = $category['name'] ?: ($artworks[0]['title'] ?? 'Category preview');
$canonicalUrl = seo_absolute_url('/category/' . $category['slug']);

ob_start();
?>
<div class="collection-detail-page">
    <a href="/categories" class="work-back">&#8592; All Categories</a>

    <div class="collection-detail-header" aria-labelledby="category-title">
        <?php if ($category['thumbnail_value']): ?>
            <div class="collection-detail-thumb">
                <img
                    src="<?= htmlspecialchars($category['thumbnail_value']) ?>"
                    alt="<?= htmlspecialchars($category['name']) ?>"
                    decoding="async"
                    fetchpriority="high"
                >
                <div class="work-piece-glow" aria-hidden="true"></div>
            </div>
        <?php endif ?>
        <div class="collection-detail-info">
            <h1 class="collection-detail-title" id="category-title"><?= htmlspecialchars($category['name']) ?></h1>
            <?php if ($category['description']): ?>
                <div class="collection-detail-desc">
                    <?= $category['description'] ?>
                </div>
            <?php endif ?>
        </div>
    </div>

    <?php if (empty($artworks)): ?>
        <p class="gallery-empty">No works in this category yet.</p>
    <?php else: ?>
        <div class="artwork-grid collection-artworks" aria-label="Works in this category">
            <?php foreach ($artworks as $i => $work): ?>
                <?php
                $sizeClass = match ($i % 7) {
                    0       => 'size-large',
                    1, 2    => 'size-small',
                    3       => 'size-medium',
                    4       => 'size-wide',
                    5, 6    => 'size-small',
                    default => 'size-medium',
                };
                ?>
                <a href="/work/<?= htmlspecialchars($work['slug']) ?>"
                   aria-label="View work <?= htmlspecialchars($work['title'] . ($work['year'] ? ', ' . $work['year'] : '')) ?>"
                   class="artwork-card <?= $sizeClass ?>">
                    <div class="artwork-thumb-wrap">
                        <?php if ($work['thumbnail_value']): ?>
                            <img
                                src="<?= htmlspecialchars($work['thumbnail_value']) ?>"
                                alt="<?= htmlspecialchars($work['title']) ?>"
                                loading="<?= $i < 2 ? 'eager' : 'lazy' ?>"
                                decoding="async"
                                fetchpriority="<?= $i === 0 ? 'high' : 'auto' ?>"
                            >
                        <?php else: ?>
                            <div class="collection-thumb-placeholder" aria-hidden="true"></div>
                        <?php endif ?>
                        <div class="artwork-glow" aria-hidden="true"></div>
                    </div>
                    <div class="artwork-meta">
                        <span class="artwork-title"><?= htmlspecialchars($work['title']) ?></span>
                        <?php if ($work['year']): ?>
                            <span class="artwork-year"><?= htmlspecialchars($work['year']) ?></span>
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
