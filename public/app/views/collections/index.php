<?php

declare(strict_types=1);

require dirname(__DIR__) . '/partials/header.php';
?>
<section class="page-hero" aria-labelledby="collections-title">
    <p class="eyebrow">Curated Collections</p>
    <h1 id="collections-title">Collections</h1>
    <p>Curated collections of generative art pieces and images, viewable in an immersive 3D gallery.</p>
</section>

<section class="managed-section">
    <div class="managed-section-body">
        <?php if (empty($collections)): ?>
            <p>No collections published yet.</p>
        <?php else: ?>
            <div class="piece-grid">
                <?php foreach ($collections as $collection): ?>
                    <article class="piece-card">
                        <?php if (!empty($collection['thumbnail_url'])): ?>
                            <img src="<?= e($collection['thumbnail_url']) ?>" alt="" loading="lazy">
                        <?php endif; ?>
                        <h2><a href="/collections/<?= e($collection['slug']) ?>"><?= e($collection['name'] ?? 'Untitled Collection') ?></a></h2>
                        <?php if (!empty($collection['description'])): ?>
                            <p><?= e(seo_excerpt($collection['description'], 120) ?? '') ?></p>
                        <?php endif; ?>
                        <p class="piece-meta">
                            <?= (int) ($collection['item_count'] ?? 0) ?> item<?= ((int) ($collection['item_count'] ?? 0) === 1) ? '' : 's' ?>
                            &middot; <a href="/immersive/collections/<?= e($collection['slug']) ?>">View in VR</a>
                        </p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php
require dirname(__DIR__) . '/partials/footer.php';
