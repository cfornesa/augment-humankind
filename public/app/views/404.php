<?php

declare(strict_types=1);

http_response_code(404);

$pageTitle = 'Not found | ' . app_site_name();
$pageDescription = public_copy_value('public_art_copy.not_found.meta_description');
$bodyClass = 'page-404';
$canonicalUrl = null;
$ogImage = null;

require __DIR__ . '/partials/header.php';
?>
    <section class="page-hero" aria-labelledby="missing-title">
        <p class="eyebrow"><?= e(public_copy_value('public_art_copy.not_found.eyebrow')) ?></p>
        <h1 id="missing-title"><?= e(public_copy_value('public_art_copy.not_found.title')) ?></h1>
        <p><?= e(public_copy_value('public_art_copy.not_found.body')) ?></p>
        <a class="button button-primary" href="/portfolio"><?= e(public_copy_value('public_art_copy.not_found.cta_label')) ?></a>
    </section>
<?php
require __DIR__ . '/partials/footer.php';
