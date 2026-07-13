<?php

declare(strict_types=1);

require dirname(__DIR__) . '/partials/header.php';
$slug = (string) ($collection['slug'] ?? '');
$origin = ($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$titleSafe = htmlspecialchars((string) ($collection['name'] ?? 'Collection'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
$collectionEmbedSrc = $origin . '/immersive/collections/' . rawurlencode($slug) . '?embed=1';
$collectionEmbedIframe = sprintf(
    '<iframe src="%s" width="100%%" style="width:100%%;height:100dvh;min-height:300px;display:block;" title="%s" frameborder="0" loading="lazy" allowfullscreen allow="camera; microphone; fullscreen" sandbox="allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox allow-top-navigation-by-user-activation"></iframe>',
    $collectionEmbedSrc,
    $titleSafe
);
$collectionEmbedCode = sprintf(
    '<creatr-exhibit-wall slug="%s" origin="%s">%s</creatr-exhibit-wall><script src="%s/embed.js" defer></script>',
    htmlspecialchars($slug, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
    $origin,
    $collectionEmbedIframe,
    $origin
);
$embedCopyScriptVersion = (int) @filemtime(dirname(__DIR__, 3) . '/assets/js/public-piece-download.js');
?>
<section class="page-hero" aria-labelledby="collection-title">
    <p class="eyebrow"><?= e(public_copy_value('public_art_copy.collection_detail.eyebrow')) ?></p>
    <h1 id="collection-title"><?= e($collection['name'] ?? 'Untitled Collection') ?></h1>
    <?php if (!empty($collection['description'])): ?>
        <p><?= e($collection['description']) ?></p>
    <?php endif; ?>
    <?php if (!empty($collection['artist_statement'])): ?>
        <p><?= e($collection['artist_statement']) ?></p>
    <?php endif; ?>
</section>

<?php $status = $collection['status'] ?? 'active'; require dirname(__DIR__) . '/partials/status-banner.php'; ?>

<section class="collection-page-immersive-action" aria-label="Immersive collection view">
    <a href="/immersive/collections/<?= e($slug) ?>?returnTo=<?= rawurlencode($_SERVER['REQUEST_URI'] ?? '') ?>" target="_blank" rel="noopener" class="piece-immersive-link"><?= e(public_copy_value('public_art_copy.shared_ui.view_immersive_label')) ?></a>
</section>

<section class="piece-page-embed-actions" aria-label="Embed this collection">
    <button type="button" class="piece-page-embed-button" data-surface-embed-copy data-embed-code="<?= e($collectionEmbedCode) ?>">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
        <span>Embed</span>
    </button>
    <span class="piece-page-embed-status" data-surface-embed-status role="status" aria-live="polite"></span>
    <textarea class="piece-page-embed-manual" data-surface-embed-manual readonly hidden aria-label="Collection embed code for manual copying"></textarea>
</section>

<section class="managed-section">
    <div class="managed-section-body">
        <?php if (!empty($collection['iframe_code'])): ?>
            <div class="exhibit-embed-container" style="width: 100%; aspect-ratio: 16 / 9; min-height: 450px; background: #0a0a14; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.1); overflow: hidden; margin-bottom: 2rem;">
                <?= $collection['iframe_code'] ?>
            </div>
        <?php elseif (empty($items)): ?>
            <p><?= e(public_copy_value('public_art_copy.collection_detail.empty_state')) ?></p>
        <?php else: ?>
            <div class="piece-grid">
                <?php foreach ($items as $item): ?>
                    <?php if ($item['type'] === 'art_piece' && !empty($item['piece'])): ?>
                        <?php $piece = $item['piece']; ?>
                        <article class="piece-card">
                            <?php if (!empty($piece['thumbnail_url'])): ?>
                                <img src="<?= e($piece['thumbnail_url']) ?>" alt="<?= e((string)($piece['thumbnail_alt_text'] ?? $piece['title'] ?? '')) ?>" loading="lazy">
                            <?php endif; ?>
                            <h2><a href="/pieces/<?= (int) $piece['id'] ?>"><?= e($piece['title'] ?? 'Untitled') ?></a></h2>
                            <?php if (!empty($piece['description'])): ?>
                                <p><?= e(seo_excerpt($piece['description'], 120) ?? '') ?></p>
                            <?php endif; ?>
                        </article>
                    <?php elseif ($item['type'] === 'media_asset' && !empty($item['media'])): ?>
                        <?php $media = $item['media']; ?>
                        <article class="piece-card">
                            <img
                                src="<?= e($media['url'] ?: '/api/media-assets/' . (int) $media['id']) ?>"
                                alt="<?= e($media['alt_text'] ?? '') ?>"
                                data-creatr-vr-eligible="true"
                                data-creatr-vr-title="<?= e($media['title'] ?? 'Untitled Image') ?>"
                                data-creatr-vr-description="<?= e($media['alt_text'] ?? '') ?>"
                                loading="lazy"
                            >
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
echo '<script src="/assets/js/public-piece-download.js?v=' . $embedCopyScriptVersion . '"></script>';
echo '<script src="/embed.js" defer></script>';
require dirname(__DIR__) . '/partials/footer.php';
