<?php

declare(strict_types=1);

http_response_code(404);

$pageTitle = 'Not found | ' . app_site_name();
$pageDescription = 'The requested page could not be found.';
$bodyClass = 'page-404';
$canonicalUrl = null;
$ogImage = null;

require __DIR__ . '/partials/header.php';
?>
    <section class="page-hero" aria-labelledby="missing-title">
        <p class="eyebrow">404</p>
        <h1 id="missing-title">This page is not on the map.</h1>
        <p>The page may have moved, or the address may be incorrect.</p>
        <a class="button button-primary" href="/portfolio">Return to the gallery</a>
    </section>
<?php
require __DIR__ . '/partials/footer.php';
