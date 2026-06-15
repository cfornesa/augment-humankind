<?php

declare(strict_types=1);

require dirname(__DIR__) . '/partials/header.php';
?>
<section class="page-hero" aria-labelledby="pieces-title">
    <p class="eyebrow">Generative Art</p>
    <h1 id="pieces-title">Art Pieces</h1>
    <p>Creative experiments and generative art works.</p>
</section>

<section class="managed-section">
    <div class="managed-section-body">
        <?php if (empty($pieces)): ?>
            <p>No art pieces published yet.</p>
        <?php else: ?>
            <div class="piece-grid">
                <?php foreach ($pieces as $piece): ?>
                    <article class="piece-card">
                        <?php if (!empty($piece['thumbnail_url'])): ?>
                            <img src="<?= e($piece['thumbnail_url']) ?>" alt="" loading="lazy">
                        <?php endif; ?>
                        <h2><a href="/pieces/<?= (int) $piece['id'] ?>"><?= e($piece['title'] ?? 'Untitled') ?></a></h2>
                        <?php if (!empty($piece['description'])): ?>
                            <p><?= e(seo_excerpt($piece['description'], 120) ?? '') ?></p>
                        <?php endif; ?>
                        <p class="piece-meta">
                            <?= (int) ($piece['version_count'] ?? 0) ?> version<?= ((int) ($piece['version_count'] ?? 0) === 1) ? '' : 's' ?>
                        </p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php
require dirname(__DIR__) . '/partials/footer.php';
