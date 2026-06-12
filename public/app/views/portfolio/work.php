<?php

declare(strict_types=1);

$pageTitle = ($artwork['title'] ?: 'Work') . ' | Augment Humankind Portfolio';
$pageDescription = seo_excerpt($artwork['description'] ?? null, 170)
    ?: trim(($artwork['year'] ? $artwork['year'] . ' · ' : '') . ($artwork['categories'][0]['name'] ?? 'Artwork from the Augment Humankind portfolio'));
$ogImage = Artwork::previewImage($artwork);
$canonicalUrl = seo_absolute_url('/portfolio/work/' . $artwork['slug']);
$bodyClass = bodyClass('portfolio-work');

$mediaItems = $mediaItems ?? ($artwork['media_items'] ?? Artwork::resolvedMediaItems($artwork));
$initialItem = $mediaItems[0] ?? null;

require __DIR__ . '/../partials/header.php';
?>
    <section class="work-page">
        <a href="/portfolio" class="work-back">&#8592; Return to the gallery</a>

        <article class="work-detail" aria-labelledby="work-title">
            <div class="work-header">
                <h1 class="work-title" id="work-title"><?= e($artwork['title']) ?></h1>
                <div class="work-meta-line">
                    <?php if ($artwork['year']): ?>
                        <span class="work-year"><?= e($artwork['year']) ?></span>
                    <?php endif ?>
                    <?php if (!empty($artwork['categories'])): ?>
                        <span class="work-cat-sep">&middot;</span>
                        <?php foreach ($artwork['categories'] as $i => $cat): ?>
                            <?php if ($i > 0): ?><span class="work-cat-sep">,</span><?php endif ?>
                            <a href="/portfolio/category/<?= e($cat['slug']) ?>" class="work-category">
                                <?= e($cat['name']) ?>
                            </a>
                        <?php endforeach ?>
                    <?php endif ?>
                </div>
            </div>

            <div class="work-piece-wrap">
                <?php if ($initialItem === null): ?>
                    <div class="work-piece-fallback" role="status">
                        <strong class="work-piece-fallback-title">No media yet</strong>
                        <p>This work doesn't have any media added yet.</p>
                    </div>
                <?php else: ?>
                    <div class="work-carousel" data-artwork-carousel tabindex="0" aria-label="Artwork media carousel">
                        <div class="work-carousel-title" data-carousel-title><?= e($initialItem['title'] ?? '') ?></div>
                        <div class="work-carousel-stage">
                            <?php if (count($mediaItems) > 1): ?>
                                <button type="button" class="work-carousel-nav work-carousel-prev" data-carousel-prev aria-label="Show previous artwork slide">&#8592;</button>
                            <?php endif ?>
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
                                    data-kind="<?= e($kind) ?>"
                                    data-source="<?= e($sourceUrl) ?>"
                                    data-poster="<?= e($posterUrl) ?>"
                                    data-alt="<?= e($altText) ?>"
                                    data-title="<?= e($item['title'] ?? '') ?>"
                                    data-caption="<?= e($item['caption'] ?? '') ?>"
                                    data-iframe-html="<?= e($kind === 'iframe' ? ($item['iframe_html'] ?? '') : '') ?>"
                                    aria-hidden="<?= $isActive ? 'false' : 'true' ?>"
                                >
                                    <?php if ($isActive && $kind === 'image'): ?>
                                        <img
                                            src="<?= e($sourceUrl) ?>"
                                            alt="<?= e($altText) ?>"
                                            class="work-image"
                                            decoding="async"
                                            fetchpriority="high"
                                        >
                                    <?php elseif ($isActive && $kind === 'video'): ?>
                                        <video
                                            class="work-video"
                                            controls
                                            preload="metadata"
                                            src="<?= e($sourceUrl) ?>"
                                            <?= $posterUrl ? 'poster="' . e($posterUrl) . '"' : '' ?>
                                        ></video>
                                    <?php elseif ($isActive && $kind === 'iframe'): ?>
                                        <div class="work-embed">
                                            <?= $item['iframe_html'] ?? '' ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="work-slide-placeholder">
                                            <span><?= e(strtoupper($kind)) ?> loads when activated</span>
                                        </div>
                                    <?php endif ?>
                                </section>
                            <?php endforeach ?>
                            <?php if (count($mediaItems) > 1): ?>
                                <button type="button" class="work-carousel-nav work-carousel-next" data-carousel-next aria-label="Show next artwork slide">&#8594;</button>
                            <?php endif ?>
                        </div>
                        <div class="work-carousel-caption" data-carousel-caption><?= e($initialItem['caption'] ?? '') ?></div>
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
                                        <dt><?= e($label) ?></dt>
                                        <dd><?= e($value) ?></dd>
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
    </section>
<?php
require __DIR__ . '/../partials/footer.php';
