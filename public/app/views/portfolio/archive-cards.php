<?php

declare(strict_types=1);

foreach ($items as $item):
    if ($itemType === 'collections'):
?>
        <a href="/portfolio/collection/<?= e($item['slug']) ?>" class="artwork-card" aria-label="View exhibit collection <?= e($item['name']) ?>">
            <div class="artwork-thumb-wrap">
                <?php if (!empty($item['preview_image'])): ?>
                    <img src="<?= e($item['preview_image']) ?>" alt="<?= e($item['name']) ?>" loading="lazy" decoding="async">
                <?php else: ?>
                    <div class="collection-thumb-placeholder" aria-hidden="true"></div>
                <?php endif ?>
            </div>
            <div class="artwork-meta">
                <span class="artwork-title"><?= e($item['name']) ?></span>
                <?php if (!empty($item['summary'])): ?>
                    <span class="collection-card-desc"><?= e($item['summary']) ?></span>
                <?php endif ?>
                <span class="artwork-type"><?= (int) ($item['exhibit_count'] ?? 0) ?> exhibit<?= ((int) ($item['exhibit_count'] ?? 0) === 1) ? '' : 's' ?></span>
            </div>
        </a>
<?php
    elseif ($itemType === 'exhibits'):
?>
        <a href="/portfolio/exhibit/<?= e($item['slug']) ?>" class="exhibit-card" aria-label="View exhibit <?= e($item['title']) ?>">
            <div class="artwork-thumb-wrap">
                <?php if (!empty($item['preview_image'])): ?>
                    <img src="<?= e($item['preview_image']) ?>" alt="<?= e($item['title']) ?>" loading="lazy" decoding="async">
                <?php else: ?>
                    <div class="collection-thumb-placeholder" aria-hidden="true"></div>
                <?php endif ?>
            </div>
            <div class="artwork-meta">
                <span class="artwork-title"><?= e($item['title']) ?></span>
                <?php if (!empty($item['summary'])): ?>
                    <span class="collection-card-desc"><?= e($item['summary']) ?></span>
                <?php endif ?>
                <?php if (!empty($item['year'])): ?>
                    <span class="artwork-year"><?= e($item['year']) ?></span>
                <?php endif ?>
            </div>
        </a>
<?php
    elseif ($itemType === 'platform-collections'):
?>
        <a href="/collections/<?= e($item['slug']) ?>" class="artwork-card" aria-label="View platform collection <?= e($item['name']) ?>">
            <div class="artwork-thumb-wrap">
                <?php if (!empty($item['thumbnail_url'])): ?>
                    <img src="<?= e($item['thumbnail_url']) ?>" alt="<?= e($item['name']) ?>" loading="lazy" decoding="async">
                <?php else: ?>
                    <div class="collection-thumb-placeholder" aria-hidden="true"></div>
                <?php endif ?>
            </div>
            <div class="artwork-meta">
                <span class="artwork-title"><?= e($item['name']) ?></span>
                <?php if (!empty($item['summary'])): ?>
                    <span class="collection-card-desc"><?= e($item['summary']) ?></span>
                <?php endif ?>
                <span class="artwork-type"><?= (int) ($item['item_count'] ?? 0) ?> item<?= ((int) ($item['item_count'] ?? 0) === 1) ? '' : 's' ?></span>
            </div>
        </a>
<?php
    elseif ($itemType === 'pieces'):
?>
        <a href="/pieces/<?= (int) $item['id'] ?>" class="artwork-card" aria-label="View art piece <?= e($item['title'] ?? 'Untitled') ?>">
            <div class="artwork-thumb-wrap">
                <?php if (!empty($item['thumbnail_url'])): ?>
                    <img src="<?= e($item['thumbnail_url']) ?>" alt="<?= e($item['title'] ?? 'Untitled') ?>" loading="lazy" decoding="async">
                <?php else: ?>
                    <div class="collection-thumb-placeholder" aria-hidden="true"></div>
                <?php endif ?>
            </div>
            <div class="artwork-meta">
                <span class="artwork-title"><?= e($item['title'] ?? 'Untitled') ?></span>
                <?php if (!empty($item['summary'])): ?>
                    <span class="collection-card-desc"><?= e($item['summary']) ?></span>
                <?php endif ?>
                <span class="artwork-type"><?= e(art_piece_effective_generation_mode_label($item)) ?></span>
            </div>
        </a>
<?php
    elseif ($itemType === 'art-media'):
?>
        <a href="/portfolio/art-media/<?= e($item['slug']) ?>" class="artwork-card" aria-label="View art medium <?= e($item['name']) ?>">
            <div class="artwork-thumb-wrap">
                <?php if (!empty($item['thumbnail_value'])): ?>
                    <img src="<?= e($item['thumbnail_value']) ?>" alt="<?= e($item['name']) ?>" loading="lazy" decoding="async">
                <?php else: ?>
                    <div class="collection-thumb-placeholder" aria-hidden="true"></div>
                <?php endif ?>
            </div>
            <div class="artwork-meta">
                <span class="artwork-title"><?= e($item['name']) ?></span>
                <?php if (!empty($item['description'])): ?>
                    <span class="collection-card-desc"><?= e(seo_excerpt($item['description'], 140) ?? '') ?></span>
                <?php endif ?>
            </div>
        </a>
<?php
    endif;
endforeach;
