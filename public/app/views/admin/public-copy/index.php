<?php

declare(strict_types=1);

$pageTitle = 'Public Copy';

$validTabs = ['gallery', 'archives', 'detail', 'art-archives', 'shared-ui'];
$tab = (string) ($tab ?? 'gallery');
if (!in_array($tab, $validTabs, true)) {
    $tab = 'gallery';
}

$tabLabels = [
    'gallery'      => 'Portfolio Gallery',
    'archives'     => 'Portfolio Archives',
    'detail'       => 'Portfolio Detail Chrome',
    'art-archives' => 'Standalone Art Archives',
    'shared-ui'    => 'Shared Public UI',
];

// Find the section whose tab key matches the active tab.
$activeSection = null;
foreach ($sections as $section) {
    if (($section['tab'] ?? '') === $tab) {
        $activeSection = $section;
        break;
    }
}

ob_start();
?>
<div class="admin-container">
    <div class="admin-header-row">
        <h1>Public Copy</h1>
    </div>

    <p class="admin-hint">
        Edit visitor-facing system copy for the portfolio, public art surfaces, shared UI messaging,
        and the footer credit. Record-owned descriptions stay on their original content editors.
    </p>

    <?php if ($error): ?>
        <div class="form-status form-status-error" role="alert">
            <p><?= e((string) $error) ?></p>
        </div>
    <?php endif; ?>
    <?php if ($saved): ?>
        <div class="form-status form-status-success" role="status">
            <p>Public copy saved.</p>
        </div>
    <?php endif; ?>

    <nav class="admin-tabs" aria-label="Public copy sections">
        <?php foreach ($tabLabels as $tabKey => $tabLabel): ?>
            <a href="/admin/public-copy?tab=<?= e($tabKey) ?>"
               class="admin-tab <?= $tab === $tabKey ? 'active' : '' ?>"
               <?= $tab === $tabKey ? 'aria-current="page"' : '' ?>><?= e($tabLabel) ?></a>
        <?php endforeach ?>
    </nav>

    <?php
    $tabPageLinks = [
        'gallery'      => [['label' => 'Portfolio gallery', 'href' => '/portfolio']],
        'archives'     => [
            ['label' => 'Exhibit collections', 'href' => '/portfolio/exhibit-collections'],
            ['label' => 'Exhibits',            'href' => '/portfolio/exhibits'],
            ['label' => 'Platform collections','href' => '/portfolio/platform-collections'],
            ['label' => 'Art pieces',          'href' => '/portfolio/pieces'],
            ['label' => 'Art media',           'href' => '/portfolio/art-media'],
        ],
        'detail'       => [
            ['label' => 'Art media index',        'href' => '/portfolio/art-media'],
            ['label' => 'Exhibit collections',    'href' => '/portfolio/exhibit-collections'],
            ['label' => 'Exhibits',               'href' => '/portfolio/exhibits'],
        ],
        'art-archives' => [
            ['label' => 'Art pieces archive', 'href' => '/pieces'],
            ['label' => 'Collections archive','href' => '/collections'],
        ],
        'shared-ui'    => [
            ['label' => 'Art pieces archive', 'href' => '/pieces'],
        ],
    ];
    $currentLinks = $tabPageLinks[$tab] ?? [];
    ?>
    <?php if ($currentLinks !== []): ?>
        <p class="admin-hint" style="margin-top:0.75rem;">
            View <?= count($currentLinks) === 1 ? 'page' : 'pages' ?>:
            <?php foreach ($currentLinks as $i => $link): ?>
                <?= $i > 0 ? ' · ' : '' ?>
                <a href="<?= e($link['href']) ?>" target="_blank" rel="noopener noreferrer"><?= e($link['label']) ?></a>
            <?php endforeach ?>
        </p>
    <?php endif ?>

    <?php if ($activeSection !== null): ?>
        <form method="post" action="/admin/public-copy/save" class="admin-form">
            <input type="hidden" name="tab" value="<?= e($tab) ?>">
            <fieldset>
                <legend><?= e((string) $activeSection['title']) ?></legend>
                <?php
                $currentGroup = null;
                foreach ($activeSection['fields'] as $field):
                    $groupHeading = $field['group'] ?? null;
                    if ($groupHeading !== null && $groupHeading !== $currentGroup):
                        $currentGroup = $groupHeading;
                ?>
                    <h2 class="public-copy-group-heading"><?= e($groupHeading) ?></h2>
                <?php endif; ?>
                    <?php
                    $path = (string) $field['path'];
                    $value = $path === 'site_settings.footer_credit'
                        ? (string) ((SiteSettings::current() ?: [])['footer_credit'] ?? '')
                        : public_copy_value($path);
                    $rows = (int) ($field['rows'] ?? 0);
                    $fieldId = 'public-copy-' . preg_replace('/[^a-z0-9]+/i', '-', $path);
                    ?>
                    <div class="field">
                        <label for="<?= e($fieldId) ?>"><?= e((string) $field['label']) ?></label>
                        <?php if ($rows > 0): ?>
                            <textarea id="<?= e($fieldId) ?>"
                                      name="copy[<?= e($path) ?>]"
                                      rows="<?= $rows ?>"><?= e($value) ?></textarea>
                        <?php else: ?>
                            <input id="<?= e($fieldId) ?>"
                                   name="copy[<?= e($path) ?>]"
                                   type="text"
                                   maxlength="500"
                                   value="<?= e($value) ?>">
                        <?php endif; ?>
                        <?php if (!empty($field['help'])): ?>
                            <p class="admin-hint"><?= e((string) $field['help']) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </fieldset>

            <div class="form-actions">
                <button type="submit" class="admin-btn">Save <?= e($tabLabels[$tab]) ?></button>
            </div>
        </form>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
