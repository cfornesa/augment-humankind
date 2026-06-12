<?php

declare(strict_types=1);

$path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';

$navigation = [
    '/' => 'Mission',
    '/services' => 'Services',
    '/notes' => 'Field Notes',
    '/contact' => 'Contact',
];

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
            <?php foreach ($navigation as $href => $label): ?>
                <a href="<?= e($href) ?>"<?= isActive($href, $path) ?>><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </header>

    <main id="main">
