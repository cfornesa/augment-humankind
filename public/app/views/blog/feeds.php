<?php

declare(strict_types=1);

require dirname(__DIR__) . '/partials/header.php';
?>
<section class="page-hero" aria-labelledby="feeds-title">
    <p class="eyebrow">Subscribe</p>
    <h1 id="feeds-title">Feeds</h1>
    <p>Use the feed format your reader supports.</p>
</section>

<section class="managed-section">
    <div class="managed-section-body">
        <h2>Site-wide feeds</h2>
        <ul>
            <li><a href="/feed.xml">Atom feed</a> — all published posts</li>
            <li><a href="/feed.json">JSON Feed</a> — all published posts</li>
            <li><a href="/feeds/mf2">Microformats2 (mf2) JSON</a> — all published posts</li>
            <li><a href="/blog">HTML blog archive</a></li>
        </ul>

        <?php $feedCategories = BlogCategory::all(); ?>
        <?php if ($feedCategories !== []): ?>
            <h2>Category feeds</h2>
            <ul>
                <?php foreach ($feedCategories as $cat): ?>
                    <li>
                        <?= e($cat['name']) ?>
                        — <a href="/blog/category/<?= e($cat['slug']) ?>/feed.xml">Atom</a>
                        / <a href="/blog/category/<?= e($cat['slug']) ?>/feed.json">JSON</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <h2>Page feeds</h2>
        <p>Every published page also exposes its own single-entry feed at <code>/{slug}/feed.xml</code> and <code>/{slug}/feed.json</code>.</p>
    </div>
</section>
<?php
require dirname(__DIR__) . '/partials/footer.php';
