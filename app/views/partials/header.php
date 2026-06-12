<?php

declare(strict_types=1);

$path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';

require_once dirname(__DIR__, 2) . '/helpers/navigation.php';
$navigationItems = ah_public_navigation_items();

$bodyClass = $bodyClass ?? 'page-managed';
$ogTitle = $ogTitle ?? $pageTitle;
$ogDescription = $ogDescription ?? $pageDescription;
$ogImage = $ogImage ?? null;
$canonicalUrl = $canonicalUrl ?? null;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>document.documentElement.classList.add('js-enhanced');</script>
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <?php if ($canonicalUrl): ?>
        <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <?php endif; ?>
    <meta property="og:title" content="<?= e($ogTitle) ?>">
    <meta property="og:description" content="<?= e($ogDescription) ?>">
    <?php if ($ogImage): ?>
        <meta property="og:image" content="<?= e($ogImage) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body class="<?= e($bodyClass) ?>">
    <a class="skip-link" href="#main">Skip to content</a>

    <header class="site-header" aria-label="Site header">
        <a class="brand" href="/" aria-label="Augment Humankind home">
            <span class="brand-mark" aria-hidden="true">AH</span>
            <span class="brand-text">Augment Humankind</span>
        </a>
        <nav class="site-nav" aria-label="Primary navigation">
            <?php foreach ($navigationItems as $item): ?>
                <?php
                    $href = (string) ($item['url'] ?? '#');
                    $label = (string) ($item['label'] ?? $href);
                    $target = (string) ($item['target'] ?? '');
                ?>
                <a href="<?= e($href) ?>"<?= isActive($href, $path) ?><?= $target === '_blank' ? ' target="_blank" rel="noopener"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </header>

    <main id="main">
