<?php

declare(strict_types=1);

$pageTitle = ($category['name'] ?: 'Category') . ' | Augment Humankind Portfolio';
$pageDescription = seo_excerpt($category['description'] ?? null, 170)
    ?: 'Works collected under the ' . ($category['name'] ?: 'selected') . ' category.';
$ogImage = $category['thumbnail_value'] ?: ($exhibits[0]['thumbnail_value'] ?? null);
$canonicalUrl = seo_absolute_url('/portfolio/category/' . $category['slug']);
$bodyClass = bodyClass('portfolio-category');

require __DIR__ . '/../partials/header.php';
?>
    <section class="collection-detail-page">
        <a href="/portfolio/categories" class="work-back">&#8592; All Categories</a>

        <div class="collection-detail-header" aria-labelledby="category-title">
            <?php if ($category['thumbnail_value']): ?>
                <div class="collection-detail-thumb">
                    <img
                        src="<?= e($category['thumbnail_value']) ?>"
                        alt="<?= e($category['name']) ?>"
                        decoding="async"
                    >
                </div>
            <?php endif ?>
            <div class="collection-detail-info">
                <h1 class="collection-detail-title" id="category-title"><?= e($category['name']) ?></h1>
                <?php if ($category['description']): ?>
                    <div class="collection-detail-desc">
                        <?= $category['description'] ?>
                    </div>
                <?php endif ?>
            </div>
        </div>

        <?php if (empty($exhibits)): ?>
            <p class="gallery-empty">No exhibits in this category yet.</p>
        <?php else: ?>
            <div class="artwork-grid collection-artworks" aria-label="Exhibits in this category">
                <?php foreach ($exhibits as $i => $ex): ?>
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
                    <a href="/portfolio/exhibit/<?= e($ex['slug']) ?>"
                       aria-label="View exhibit <?= e($ex['title'] . ($ex['year'] ? ', ' . $ex['year'] : '')) ?>"
                       class="artwork-card <?= $sizeClass ?>">
                        <div class="artwork-thumb-wrap">
                            <?php if ($ex['thumbnail_value']): ?>
                                <img
                                    src="<?= e($ex['thumbnail_value']) ?>"
                                    alt="<?= e($ex['title']) ?>"
                                    loading="<?= $i < 2 ? 'eager' : 'lazy' ?>"
                                    decoding="async"
                                >
                            <?php else: ?>
                                <div class="collection-thumb-placeholder" aria-hidden="true"></div>
                            <?php endif ?>
                        </div>
                        <div class="artwork-meta">
                            <span class="artwork-title"><?= e($ex['title']) ?></span>
                            <?php if ($ex['year']): ?>
                                <span class="artwork-year"><?= e($ex['year']) ?></span>
                            <?php endif ?>
                        </div>
                    </a>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </section>
<?php
require __DIR__ . '/../partials/footer.php';
