<?php
$pageTitle  = 'Fornesus';
$activePage = 'gallery';
$metaTitle = 'Fornesus Art — Gallery';
$metaDescription = 'Browse exhibits and works from the Fornesus Art archive.';
$ogTitle = $metaTitle;
$ogDescription = $metaDescription;
$metaImage = $exhibits[0]['thumbnail_value'] ?? ($artworks[0]['thumbnail_value'] ?? null);
$metaImageAlt = $exhibits[0]['name'] ?? ($artworks[0]['title'] ?? 'Fornesus Art gallery preview');
$canonicalUrl = seo_absolute_url('/');

ob_start();
?>
<div class="gallery-page">

    <?php if (!empty($exhibits)): ?>
        <section class="gallery-section exhibits-strip" aria-labelledby="gallery-exhibits-heading">
            <div class="gallery-section-header">
                <h2 class="category-name" id="gallery-exhibits-heading">Exhibits</h2>
                <span class="section-rule" aria-hidden="true"></span>
            </div>
            <div class="artwork-grid">
                <?php foreach ($exhibits as $exIndex => $ex): ?>
                    <?php
                    $sizeClass = match ($exIndex % 7) {
                        0       => 'size-large',
                        1, 2    => 'size-small',
                        3       => 'size-medium',
                        4       => 'size-wide',
                        5, 6    => 'size-small',
                        default => 'size-medium',
                    };
                    ?>
                    <a href="/exhibit/<?= htmlspecialchars($ex['slug']) ?>" class="artwork-card <?= $sizeClass ?>" aria-label="View exhibit <?= htmlspecialchars($ex['name']) ?>">
                        <div class="artwork-thumb-wrap">
                            <?php if ($ex['thumbnail_value']): ?>
                                <img
                                    src="<?= htmlspecialchars($ex['thumbnail_value']) ?>"
                                    alt="<?= htmlspecialchars($ex['name']) ?>"
                                    loading="<?= $exIndex === 0 ? 'eager' : 'lazy' ?>"
                                    decoding="async"
                                    fetchpriority="<?= $exIndex === 0 ? 'high' : 'auto' ?>"
                                >
                            <?php else: ?>
                                <div class="collection-thumb-placeholder" aria-hidden="true"></div>
                            <?php endif ?>
                            <div class="artwork-glow" aria-hidden="true"></div>
                        </div>
                        <div class="artwork-meta">
                            <span class="artwork-title"><?= htmlspecialchars($ex['name']) ?></span>
                        </div>
                    </a>
                <?php endforeach ?>
            </div>
        </section>
    <?php endif ?>

    <section class="gallery-section" aria-labelledby="gallery-works-heading">
        <div class="gallery-section-header">
            <h2 class="category-name" id="gallery-works-heading">Works</h2>
            <span class="section-rule" aria-hidden="true"></span>
        </div>

        <?php if (empty($artworks)): ?>
            <p class="gallery-empty">No works have been added yet.</p>
        <?php else: ?>
            <div class="exhibits-grid" id="gallery-work-grid">
                <?php foreach ($artworks as $i => $work): ?>
                    <?php $hidden = $i >= 3 ? 'gallery-work-overflow' : ''; ?>
                    <a href="/work/<?= htmlspecialchars($work['slug']) ?>"
                       aria-label="View work <?= htmlspecialchars($work['title'] . ($work['year'] ? ', ' . $work['year'] : '')) ?>"
                       class="exhibit-card <?= $hidden ?>">
                        <div class="artwork-thumb-wrap">
                            <?php if ($work['thumbnail_value']): ?>
                                <img
                                    src="<?= htmlspecialchars($work['thumbnail_value']) ?>"
                                    alt="<?= htmlspecialchars($work['title']) ?>"
                                    loading="<?= $i < 2 ? 'eager' : 'lazy' ?>"
                                    decoding="async"
                                    fetchpriority="<?= $i === 0 ? 'high' : 'auto' ?>"
                                >
                            <?php else: ?>
                                <div class="collection-thumb-placeholder" aria-hidden="true"></div>
                            <?php endif ?>
                            <div class="artwork-glow" aria-hidden="true"></div>
                        </div>
                        <div class="artwork-meta">
                            <span class="artwork-title"><?= htmlspecialchars($work['title']) ?></span>
                            <?php if ($work['year']): ?>
                                <span class="artwork-year"><?= htmlspecialchars($work['year']) ?></span>
                            <?php endif ?>
                        </div>
                    </a>
                <?php endforeach ?>
            </div>
            <?php if (count($artworks) > 3): ?>
                <button
                    class="see-more-btn"
                    id="works-see-more"
                    type="button"
                    aria-expanded="false"
                    aria-controls="gallery-work-grid"
                >See More</button>
            <?php endif ?>
        <?php endif ?>
    </section>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
