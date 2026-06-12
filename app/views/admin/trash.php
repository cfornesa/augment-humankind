<?php
$pageTitle = 'Recycle Bin — Augment Humankind Admin';
$tab       = $tab ?? 'artworks';

$tabs = [
    'artworks'   => ['label' => 'Works',      'items' => $artworks],
    'categories' => ['label' => 'Categories', 'items' => $categories],
    'exhibits'   => ['label' => 'Exhibits',   'items' => $exhibits],
    'media'      => ['label' => 'Media',       'items' => $mediaFiles],
];

ob_start();
?>
<div class="admin-section">
    <h1 class="admin-heading">Recycle Bin</h1>

    <nav class="trash-tabs">
        <?php foreach ($tabs as $key => $info): ?>
            <a href="/admin/trash?tab=<?= $key ?>"
               class="trash-tab <?= $tab === $key ? 'active' : '' ?>">
                <?= $info['label'] ?>
                <?php if ($count = count($info['items'])): ?>
                    <span class="trash-tab-count"><?= $count ?></span>
                <?php endif ?>
            </a>
        <?php endforeach ?>
    </nav>

    <?php
    $current = $tabs[$tab] ?? $tabs['artworks'];
    $items   = $current['items'];
    $type    = match ($tab) {
        'artworks'   => 'artwork',
        'categories' => 'category',
        'exhibits'   => 'exhibit',
        'media'      => 'media',
        default      => 'artwork',
    };
    ?>

    <?php if (empty($items)): ?>
        <p class="admin-empty">Nothing in this bin.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th><?= $tab === 'media' ? 'File' : ($tab === 'artworks' ? 'Title' : 'Name') ?></th>
                    <th>Deleted</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <?php if ($tab === 'media'): ?>
                                <span class="trash-media-path">ID <?= (int) $item['id'] ?></span>
                                <span class="admin-hint"> <?= htmlspecialchars($item['mime_type'] ?? '') ?></span>
                            <?php elseif ($tab === 'artworks'): ?>
                                <?= htmlspecialchars($item['title']) ?>
                                <?php if ($item['year']): ?>
                                    <span class="admin-hint"> — <?= htmlspecialchars($item['year']) ?></span>
                                <?php endif ?>
                            <?php else: ?>
                                <?= htmlspecialchars($item['name']) ?>
                            <?php endif ?>
                        </td>
                        <td class="admin-hint">
                            <?= date('Y-m-d H:i', strtotime($item['deleted_at'])) ?>
                        </td>
                        <td class="admin-actions">
                            <form method="POST" action="/admin/trash/restore" style="display:inline">
                                <input type="hidden" name="type" value="<?= $type ?>">
                                <input type="hidden" name="id"   value="<?= (int) $item['id'] ?>">
                                <button type="submit" class="admin-btn admin-btn-sm">Restore</button>
                            </form>
                            <form method="POST" action="/admin/trash/purge" style="display:inline"
                                  onsubmit="return confirm('Permanently delete this item? This cannot be undone.')">
                                <input type="hidden" name="type" value="<?= $type ?>">
                                <input type="hidden" name="id"   value="<?= (int) $item['id'] ?>">
                                <button type="submit" class="admin-del-btn">Delete permanently</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>

        <form method="POST" action="/admin/trash/empty" class="trash-empty-form"
              onsubmit="return confirm('Empty this entire tab? All items will be permanently deleted.')">
            <input type="hidden" name="type" value="<?= $tab ?>">
            <button type="submit" class="admin-btn admin-btn-ghost">Empty this tab</button>
        </form>
    <?php endif ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
