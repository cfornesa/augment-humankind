<?php
$pageTitle  = '404 — Fornesus Art';
$activePage = '';
$metaTitle = '404 — Fornesus Art';
$metaDescription = 'This page could not be found in the Fornesus Art archive.';
$ogTitle = $metaTitle;
$ogDescription = $metaDescription;
$metaRobots = 'noindex, nofollow';
$canonicalUrl = seo_current_url();

ob_start();
?>
<div class="error-page">
    <p class="error-code">404</p>
    <p class="error-message">This fragment does not exist in the archive.</p>
    <a href="/" class="error-back">&#8592; Return</a>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
