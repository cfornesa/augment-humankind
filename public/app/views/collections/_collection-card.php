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
