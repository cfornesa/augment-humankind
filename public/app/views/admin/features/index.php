<?php

declare(strict_types=1);

$pageTitle = 'Features';

$tab = $_GET['tab'] ?? 'pieces';
if (!in_array($tab, ['pieces', 'exhibits', 'blog', 'ai'], true)) {
    $tab = 'pieces';
}
$error = $_GET['error'] ?? null;
$saved = isset($_GET['saved']);

$tabLabels = [
    'pieces' => 'Art Pieces',
    'exhibits' => 'Exhibits',
    'blog' => 'Blog',
    'ai' => 'AI',
];

$storedValue = static function (string $key) use ($flags): bool {
    if (!array_key_exists($key, $flags)) {
        return true;
    }
    $value = filter_var($flags[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return $value === null ? true : $value;
};

$groupKeys = [];
foreach ($registry as $key => $meta) {
    $groupKeys[$meta['group']][] = $key;
}

$aiSections = [
    'Master Switch' => ['ai'],
    'Piece Code Generation' => ['ai_pieces_code', 'ai_pieces_p5', 'ai_pieces_c2', 'ai_pieces_c2_interactive', 'ai_pieces_three', 'ai_pieces_svg', 'ai_pieces_aframe'],
    'Theme Generation' => ['ai_theme'],
    'Image Description Generation' => ['ai_alt_text'],
    'Editor AI' => ['ai_editor', 'ai_text_pages', 'ai_text_blog', 'ai_text_exhibits', 'ai_text_platform_collections', 'ai_text_media'],
];

$renderToggle = static function (
    string $key,
    array $registry,
    array $flags,
    array $contentCounts,
    string $tab,
    callable $storedValue
): void {
    if (!isset($registry[$key])) {
        return;
    }
    $meta = $registry[$key];
    $checked = $storedValue($key);
    $parentsEnabled = true;
    $blockingParents = [];
    foreach ($meta['requires'] as $parent) {
        if (!feature_enabled($parent)) {
            $parentsEnabled = false;
            $blockingParents[] = $registry[$parent]['label'] ?? $parent;
        }
    }
    $sameTabParents = array_values(array_filter(
        $meta['requires'],
        static fn (string $parent): bool => ($registry[$parent]['group'] ?? '') === $tab
    ));
    $count = $contentCounts[$key] ?? null;
    $fieldId = 'feature-' . str_replace('_', '-', $key);
    ?>
    <div class="field feature-toggle-row">
        <label for="<?= e($fieldId) ?>" class="feature-switch-label">
            <input type="checkbox"
                   id="<?= e($fieldId) ?>"
                   name="features[<?= e($key) ?>]"
                   value="1"
                   class="feature-switch-input"
                   data-feature-key="<?= e($key) ?>"
                   <?= $sameTabParents !== [] ? 'data-requires="' . e(implode(' ', $sameTabParents)) . '"' : '' ?>
                   <?= $checked ? 'checked' : '' ?>
                   <?= $parentsEnabled ? '' : 'disabled' ?>>
            <span class="feature-switch-track" aria-hidden="true">
                <span class="feature-switch-thumb"></span>
            </span>
            <span class="feature-switch-copy">
                <span class="feature-switch-title"><?= e($meta['label']) ?></span>
                <span class="feature-switch-description"><?= e($meta['description']) ?></span>
            </span>
        </label>
        <?php if ($meta['requires'] !== []): ?>
            <p class="admin-hint feature-requires-note" data-requires-note-for="<?= e($key) ?>" <?= $parentsEnabled ? 'hidden' : '' ?>>
                Requires <?= e(implode(' and ', array_map(
                    static fn (string $parent): string => (string) ($registry[$parent]['label'] ?? $parent),
                    $meta['requires']
                ))) ?>.
                <?php if (!$parentsEnabled && $blockingParents !== []): ?>
                    Currently off: <?= e(implode(', ', $blockingParents)) ?>.
                <?php endif ?>
            </p>
        <?php endif ?>
        <?php if ($count !== null && $count > 0): ?>
            <p class="admin-hint">
                <?= (int) $count ?> existing item<?= $count === 1 ? '' : 's' ?>. If turned off,
                they keep their public URLs and stay editable — only new creation is blocked.
            </p>
        <?php endif ?>
    </div>
    <?php
};

ob_start();
?>
<div class="admin-container">
    <div class="admin-header-row">
        <h1>Features</h1>
    </div>

    <p class="admin-hint">
        Turning a feature off blocks creating new content and hides its empty sections from
        navigation. Existing published content keeps its public URLs and stays editable until
        you remove it.
    </p>

    <?php if ($error): ?>
        <div class="form-status form-status-error" role="alert">
            <p><?= e($error) ?></p>
        </div>
    <?php endif; ?>
    <?php if ($saved): ?>
        <div class="form-status form-status-success" role="status">
            <p>Feature settings saved.</p>
        </div>
    <?php endif; ?>

    <nav class="admin-tabs" aria-label="Feature groups">
        <?php foreach ($tabLabels as $tabKey => $tabLabel): ?>
            <a href="/admin/features?tab=<?= e($tabKey) ?>" class="admin-tab <?= $tab === $tabKey ? 'active' : '' ?>"<?= $tab === $tabKey ? ' aria-current="page"' : '' ?>><?= e($tabLabel) ?></a>
        <?php endforeach ?>
    </nav>

    <form method="post" action="/admin/features/save" class="admin-form" id="features-form">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <fieldset>
            <legend><?= e($tabLabels[$tab]) ?> features</legend>

            <?php if ($tab === 'ai'): ?>
                <div class="feature-section-list">
                    <?php foreach ($aiSections as $sectionLabel => $keys): ?>
                        <section class="feature-section" aria-labelledby="feature-section-<?= e(strtolower(str_replace(' ', '-', $sectionLabel))) ?>">
                            <h2 id="feature-section-<?= e(strtolower(str_replace(' ', '-', $sectionLabel))) ?>" class="feature-section-title"><?= e($sectionLabel) ?></h2>
                            <?php foreach ($keys as $key): ?>
                                <?php $renderToggle($key, $registry, $flags, $contentCounts, $tab, $storedValue); ?>
                            <?php endforeach ?>
                        </section>
                    <?php endforeach ?>
                </div>
            <?php else: ?>
                <?php foreach ($groupKeys[$tab] ?? [] as $key): ?>
                    <?php $renderToggle($key, $registry, $flags, $contentCounts, $tab, $storedValue); ?>
                <?php endforeach ?>
            <?php endif ?>

        </fieldset>

        <div class="admin-actions">
            <button type="submit" class="admin-btn">Save <?= e($tabLabels[$tab]) ?> Features</button>
        </div>
    </form>
</div>

<script>
(function () {
    var form = document.getElementById('features-form');
    if (!form) return;
    var boxes = form.querySelectorAll('input[type="checkbox"][data-feature-key]');

    function refresh() {
        boxes.forEach(function (box) {
            var requires = (box.dataset.requires || '').split(' ').filter(Boolean);
            if (requires.length === 0) return;
            var blocked = requires.some(function (parentKey) {
                var parentBox = form.querySelector('input[data-feature-key="' + parentKey + '"]');
                return parentBox ? !parentBox.checked || parentBox.disabled : false;
            });
            box.disabled = blocked;
            var note = form.querySelector('[data-requires-note-for="' + box.dataset.featureKey + '"]');
            if (note) note.hidden = !blocked;
        });
    }

    boxes.forEach(function (box) { box.addEventListener('change', refresh); });
    refresh();
})();
</script>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
