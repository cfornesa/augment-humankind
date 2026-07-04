<article class="piece-card">
    <?php if (!empty($piece['thumbnail_url'])): ?>
        <img src="<?= e($piece['thumbnail_url']) ?>" alt="<?= e((string)($piece['thumbnail_alt_text'] ?? $piece['title'] ?? '')) ?>" loading="lazy">
    <?php endif; ?>
    <h2><a href="/pieces/<?= (int) $piece['id'] ?>"><?= e($piece['title'] ?? 'Untitled') ?></a></h2>
    <?php if (!empty($piece['description'])): ?>
        <p><?= e(seo_excerpt($piece['description'], 120) ?? '') ?></p>
    <?php endif; ?>
    <p class="piece-meta">
        <?= (int) ($piece['version_count'] ?? 0) ?> version<?= ((int) ($piece['version_count'] ?? 0) === 1) ? '' : 's' ?>
        &middot; <?= e(art_piece_effective_generation_mode_label($piece)) ?>
    </p>
</article>
