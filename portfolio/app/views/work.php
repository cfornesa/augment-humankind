<?php
$pageTitle  = ($artwork['title'] ?? 'Work') . ' ';
$activePage = 'gallery';
$metaTitle = ($artwork['title'] ?: 'Work') . ' — Fornesus Art';
$metaDescription = seo_excerpt($artwork['description'] ?? null, 170)
    ?: trim(($artwork['year'] ? $artwork['year'] . ' · ' : '') . ($artwork['categories'][0]['name'] ?? 'Artwork from Fornesus Art'));
$ogTitle = $metaTitle;
$ogDescription = $metaDescription;
$metaImage = Artwork::previewImage($artwork);
$metaImageAlt = $artwork['title'] ?: 'Artwork preview';
$canonicalUrl = seo_absolute_url('/work/' . $artwork['slug']);

$mediaItems = $mediaItems ?? ($artwork['media_items'] ?? Artwork::resolvedMediaItems($artwork));
$initialItem = $mediaItems[0] ?? null;

ob_start();
?>
<div class="work-page">
    <a href="/" class="work-back">&#8592; Return to the archive</a>

    <article class="work-detail" aria-labelledby="work-title">
        <div class="work-header">
            <h1 class="work-title" id="work-title"><?= htmlspecialchars($artwork['title']) ?></h1>
            <div class="work-meta-line">
                <?php if ($artwork['year']): ?>
                    <span class="work-year"><?= htmlspecialchars($artwork['year']) ?></span>
                <?php endif ?>
                <?php if (!empty($artwork['categories'])): ?>
                    <span class="work-cat-sep">&middot;</span>
                    <?php foreach ($artwork['categories'] as $i => $cat): ?>
                        <?php if ($i > 0): ?><span class="work-cat-sep">,</span><?php endif ?>
                        <a href="/category/<?= htmlspecialchars($cat['slug']) ?>" class="work-category">
                            <?= htmlspecialchars($cat['name']) ?>
                        </a>
                    <?php endforeach ?>
                <?php endif ?>
            </div>
        </div>

        <div class="work-piece-wrap">
            <?php if (!($pieceState['valid'] ?? false) || $initialItem === null): ?>
                <div class="work-piece-fallback" role="status">
                    <strong class="work-piece-fallback-title">Artwork display unavailable</strong>
                    <p><?= htmlspecialchars($pieceState['message'] ?? 'This artwork source could not be rendered.') ?></p>
                </div>
            <?php else: ?>
                <div class="work-carousel" data-artwork-carousel tabindex="0" aria-label="Artwork media carousel">
                    <div class="work-carousel-title" data-carousel-title><?= htmlspecialchars($initialItem['title'] ?? '') ?></div>
                    <button type="button" class="work-carousel-nav work-carousel-prev" data-carousel-prev aria-label="Show previous artwork slide">&#8592;</button>
                    <div class="work-carousel-stage">
                        <?php foreach ($mediaItems as $index => $item): ?>
                            <?php
                            $kind = $item['display_kind'] ?? 'image';
                            $isActive = $index === 0;
                            $sourceUrl = $item['source_url'] ?? '';
                            $posterUrl = $item['poster_url'] ?? '';
                            $altText = $item['alt_text'] ?: ($artwork['title'] ?? 'Artwork media');
                            ?>
                            <section
                                class="work-carousel-slide<?= $isActive ? ' is-active' : '' ?>"
                                data-carousel-slide
                                data-kind="<?= htmlspecialchars($kind) ?>"
                                data-source="<?= htmlspecialchars($sourceUrl) ?>"
                                data-poster="<?= htmlspecialchars($posterUrl) ?>"
                                data-alt="<?= htmlspecialchars($altText) ?>"
                                data-title="<?= htmlspecialchars($item['title'] ?? '') ?>"
                                data-caption="<?= htmlspecialchars($item['caption'] ?? '') ?>"
                                data-iframe-html="<?= htmlspecialchars($kind === 'iframe' ? ($item['iframe_html'] ?? '') : '') ?>"
                                aria-hidden="<?= $isActive ? 'false' : 'true' ?>"
                            >
                                <?php if ($isActive && $kind === 'image'): ?>
                                    <img
                                        src="<?= htmlspecialchars($sourceUrl) ?>"
                                        alt="<?= htmlspecialchars($altText) ?>"
                                        class="work-image"
                                        decoding="async"
                                        fetchpriority="high"
                                    >
                                <?php elseif ($isActive && $kind === 'video'): ?>
                                    <video
                                        class="work-video"
                                        controls
                                        preload="metadata"
                                        src="<?= htmlspecialchars($sourceUrl) ?>"
                                        <?= $posterUrl ? 'poster="' . htmlspecialchars($posterUrl) . '"' : '' ?>
                                    ></video>
                                <?php elseif ($isActive && $kind === 'iframe'): ?>
                                    <div class="work-embed">
                                        <?= $item['iframe_html'] /* admin-authored iframe embed */ ?>
                                    </div>
                                <?php else: ?>
                                    <div class="work-slide-placeholder">
                                        <span><?= htmlspecialchars(strtoupper($kind)) ?> loads when activated</span>
                                    </div>
                                <?php endif ?>
                            </section>
                        <?php endforeach ?>
                    </div>
                    <div class="work-carousel-caption" data-carousel-caption><?= htmlspecialchars($initialItem['caption'] ?? '') ?></div>
                    <button type="button" class="work-carousel-nav work-carousel-next" data-carousel-next aria-label="Show next artwork slide">&#8594;</button>
                    <?php if (count($mediaItems) > 1): ?>
                        <div class="work-carousel-dots" role="tablist" aria-label="Artwork slide chooser">
                            <?php foreach ($mediaItems as $index => $item): ?>
                                <button
                                    type="button"
                                    class="work-carousel-dot<?= $index === 0 ? ' is-active' : '' ?>"
                                    data-carousel-dot
                                    data-index="<?= $index ?>"
                                    role="tab"
                                    aria-selected="<?= $index === 0 ? 'true' : 'false' ?>"
                                    aria-label="Show slide <?= $index + 1 ?>"
                                ></button>
                            <?php endforeach ?>
                        </div>
                    <?php endif ?>
                </div>
            <?php endif ?>
            <div class="work-piece-glow" aria-hidden="true"></div>
        </div>

        <div class="work-info">
            <?php
            $placardRows = [
                'Name'       => $artwork['title'] ?? '',
                'Year'       => $artwork['year'] ?? '',
                'Artist'     => $artwork['artist_name'] ?? '',
                'Medium'     => $artwork['medium'] ?? '',
                'Dimensions' => $artwork['dimensions'] ?? '',
            ];
            $placardRows = array_filter($placardRows, fn ($v) => trim((string) $v) !== '');
            $hasPlacardNotes = trim((string) ($artwork['placard_notes'] ?? '')) !== '';
            ?>
            <?php if ($placardRows || $hasPlacardNotes): ?>
                <div class="work-placard">
                    <?php if ($placardRows): ?>
                        <dl class="work-placard-fields">
                            <?php foreach ($placardRows as $label => $value): ?>
                                <div class="work-placard-row">
                                    <dt><?= htmlspecialchars($label) ?></dt>
                                    <dd><?= htmlspecialchars($value) ?></dd>
                                </div>
                            <?php endforeach ?>
                        </dl>
                    <?php endif ?>
                    <?php if ($hasPlacardNotes): ?>
                        <div class="work-placard-notes">
                            <?= $artwork['placard_notes'] ?>
                        </div>
                    <?php endif ?>
                </div>
            <?php endif ?>
            <?php if ($artwork['description']): ?>
                <div class="work-description">
                    <?= $artwork['description'] ?>
                </div>
            <?php endif ?>
        </div>
    </article>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
