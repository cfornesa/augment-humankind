<?php

declare(strict_types=1);

$pageTitle = ($collection['name'] ?: 'Collection') . ' | ' . app_site_name() . ' Portfolio';
$pageDescription = seo_excerpt($collection['description'] ?? null, 170)
    ?: 'Exhibits gathered in the ' . ($collection['name'] ?: 'selected') . ' collection.';
$ogImage = $collection['thumbnail_value'] ?: ($exhibits[0]['thumbnail_value'] ?? null);
$canonicalUrl = seo_absolute_url('/portfolio/collection/' . $collection['slug']);
$bodyClass = bodyClass('portfolio-exhibit');

require __DIR__ . '/../partials/header.php';
?>
    <section class="collection-detail-page">
        <a href="/portfolio/exhibit-collections" class="work-back">&#8592; <?= e(public_copy_value('portfolio_copy.detail.collection.back_label')) ?></a>

        <div class="collection-detail-header" aria-labelledby="exhibit-title">
            <?php if ($collection['thumbnail_value']): ?>
                <div class="collection-detail-thumb">
                    <img
                        src="<?= e($collection['thumbnail_value']) ?>"
                        alt="<?= e($collection['name']) ?>"
                        decoding="async"
                    >
                </div>
            <?php endif ?>
            <div class="collection-detail-info">
                <h1 class="collection-detail-title" id="exhibit-title"><?= e($collection['name']) ?></h1>
                <?php if ($collection['description']): ?>
                    <div class="collection-detail-desc">
                        <?= $collection['description'] ?>
                    </div>
                <?php endif ?>
            </div>
        </div>

        <?php $status = $collection['status'] ?? 'active'; require __DIR__ . '/../partials/status-banner.php'; ?>

        <?php if (empty($exhibits)): ?>
            <p class="gallery-empty"><?= e(public_copy_value('portfolio_copy.detail.collection.empty_state')) ?></p>
        <?php else: ?>
            <div class="artwork-grid collection-artworks" aria-label="Exhibits in this collection">
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

        <?php if (!empty($collection['comments_enabled'])): ?>
        <section class="comments-section blog-comments" aria-labelledby="collection-comments-title">
            <h2 id="collection-comments-title"><?= e(public_copy_value('public_art_copy.shared_ui.comments_heading')) ?></h2>
            <?php
            $commentsUrl = '/api/exhibit-collections/' . (string) $collection['slug'] . '/comments';
            $emptyCommentMessage = public_copy_value('public_art_copy.shared_ui.comments_empty');
            require dirname(__DIR__) . '/partials/comment-list.php';
            ?>
            <?php
            $commentUrl = $commentsUrl;
            $signinRedirect = $_SERVER['REQUEST_URI'] ?? ('/portfolio/collection/' . (string) $collection['slug']);
            require dirname(__DIR__) . '/partials/comment-form.php';
            ?>
        </section>
        <?php endif; ?>
    </section>
<?php
require __DIR__ . '/../partials/footer.php';
