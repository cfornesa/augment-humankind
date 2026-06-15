<?php

declare(strict_types=1);

require dirname(__DIR__) . '/partials/header.php';
?>
<section class="page-hero" aria-labelledby="collection-title">
    <p class="eyebrow">Collection</p>
    <h1 id="collection-title"><?= e($collection['name'] ?? 'Untitled Collection') ?></h1>
    <?php if (!empty($collection['description'])): ?>
        <p><?= e($collection['description']) ?></p>
    <?php endif; ?>
    <?php if (!empty($collection['artist_statement'])): ?>
        <p><?= e($collection['artist_statement']) ?></p>
    <?php endif; ?>
</section>

<section class="piece-stage" aria-label="Collection">
    <a href="/immersive/collections/<?= e($collection['slug']) ?>" target="_blank" rel="noopener" class="piece-immersive-link">View in Immersive / VR Mode</a>
</section>

<section class="managed-section">
    <div class="managed-section-body">
        <?php if (!empty($collection['iframe_code'])): ?>
            <div class="exhibit-embed-container" style="width: 100%; aspect-ratio: 16 / 9; min-height: 450px; background: #0a0a14; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.1); overflow: hidden; margin-bottom: 2rem;">
                <?= $collection['iframe_code'] ?>
            </div>
        <?php elseif (empty($items)): ?>
            <p>This collection has no items yet.</p>
        <?php else: ?>
            <div class="piece-grid">
                <?php foreach ($items as $item): ?>
                    <?php if ($item['type'] === 'art_piece' && !empty($item['piece'])): ?>
                        <?php $piece = $item['piece']; ?>
                        <article class="piece-card">
                            <?php if (!empty($piece['thumbnail_url'])): ?>
                                <img src="<?= e($piece['thumbnail_url']) ?>" alt="" loading="lazy">
                            <?php endif; ?>
                            <h2><a href="/pieces/<?= (int) $piece['id'] ?>"><?= e($piece['title'] ?? 'Untitled') ?></a></h2>
                            <?php if (!empty($piece['description'])): ?>
                                <p><?= e(seo_excerpt($piece['description'], 120) ?? '') ?></p>
                            <?php endif; ?>
                        </article>
                    <?php elseif ($item['type'] === 'media_asset' && !empty($item['media'])): ?>
                        <?php $media = $item['media']; ?>
                        <article class="piece-card">
                            <img src="<?= e($media['url'] ?: '/api/media-assets/' . (int) $media['id']) ?>" alt="<?= e($media['alt_text'] ?? '') ?>" loading="lazy">
                            <h2><?= e($media['title'] ?? 'Untitled Image') ?></h2>
                            <?php if (!empty($media['alt_text'])): ?>
                                <p><?= e($media['alt_text']) ?></p>
                            <?php endif; ?>
                        </article>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php
require dirname(__DIR__) . '/partials/footer.php';
