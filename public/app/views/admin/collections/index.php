<?php
$collectionLabel       = $collectionLabel       ?? 'Collection';
$collectionPlural      = $collectionPlural      ?? 'Collections';
$collectionCreatePath  = $collectionCreatePath  ?? '/admin/collections/create';
$collectionReorderPath = $collectionReorderPath ?? '/admin/collections/reorder';
$collectionIndexPath   = $collectionIndexPath   ?? '/admin/collections';
$collectionDeleteMessage = $collectionDeleteMessage ?? ('Move this ' . strtolower($collectionLabel) . ' to the recycle bin?');
$pageTitle = $collectionPlural . ' — ' . app_site_name() . ' Admin';

$q    = $q    ?? '';
$sort = $sort ?? 'sort_order';
$dir  = $dir  ?? 'asc';

function col_sort_link(string $col, string $label, string $cur, string $curDir, array $carry, string $base): string {
    $next  = ($cur === $col && $curDir === 'asc') ? 'desc' : 'asc';
    $arrow = $cur === $col ? ($curDir === 'asc' ? ' &#8593;' : ' &#8595;') : '';
    $qs    = http_build_query(array_merge($carry, ['sort' => $col, 'dir' => $next]));
    return '<a href="' . htmlspecialchars($base, ENT_QUOTES, 'UTF-8') . '?' . $qs . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . $arrow . '</a>';
}

$carry         = array_filter(['q' => $q], fn($v) => $v !== '');
$isDefaultSort = ($sort === 'sort_order');

ob_start();
?>
<div class="admin-section">
    <span id="reorder-status" class="reorder-status" aria-live="polite"></span>
    <div class="admin-section-head">
        <h1 class="admin-heading"><?= htmlspecialchars($collectionPlural) ?></h1>
        <a href="<?= htmlspecialchars($collectionCreatePath) ?>" class="admin-btn">+ New <?= htmlspecialchars($collectionLabel) ?></a>
    </div>

    <form class="admin-filter-bar" action="<?= htmlspecialchars($collectionIndexPath) ?>" method="get" role="search">
        <label class="sr-only" for="admin-col-q">Search <?= htmlspecialchars(strtolower($collectionPlural)) ?></label>
        <input id="admin-col-q" class="admin-filter-input" name="q" type="search"
               value="<?= e($q) ?>" placeholder="Search name, description…" autocomplete="off">
        <button class="admin-btn admin-btn-sm" type="submit">Filter</button>
        <?php if ($q !== '' || !$isDefaultSort): ?>
            <a href="<?= htmlspecialchars($collectionIndexPath) ?>" class="admin-filter-reset">Reset view</a>
        <?php endif; ?>
    </form>

    <?php if (empty($collections)): ?>
        <p class="admin-empty"><?= $q !== '' ? 'No ' . htmlspecialchars(strtolower($collectionPlural)) . ' matched your search.' : 'No ' . htmlspecialchars(strtolower($collectionPlural)) . ' yet.' ?></p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th></th>
                    <th><?= col_sort_link('name', 'Name', $sort, $dir, $carry, $collectionIndexPath) ?></th>
                    <th>Exhibits</th>
                    <th><?= col_sort_link('created', 'Created', $sort, $dir, $carry, $collectionIndexPath) ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="exhibits-sortable" data-reorder-url="<?= htmlspecialchars($collectionReorderPath) ?>" class="<?= !$isDefaultSort ? 'drag-handles-hidden' : '' ?>">
                <?php foreach ($collections as $col): ?>
                    <tr data-id="<?= $col['id'] ?>">
                        <td class="drag-handle" title="Drag to reorder">&#8597;</td>
                        <td class="cell-title" data-label="Name"><?= htmlspecialchars($col['name']) ?></td>
                        <td data-label="Exhibits"><?= (int) ($col['exhibit_count'] ?? 0) ?></td>
                        <td data-label="Created"><?= htmlspecialchars($col['created_at'] ?? '') ?></td>
                        <td class="admin-actions admin-actions-cell">
                            <a href="<?= htmlspecialchars($collectionIndexPath) ?>/<?= $col['id'] ?>/edit">Edit</a>
                            <form method="POST" action="<?= htmlspecialchars($collectionIndexPath) ?>/<?= $col['id'] ?>/delete"
                                  onsubmit="return confirm('<?= htmlspecialchars($collectionDeleteMessage, ENT_QUOTES, 'UTF-8') ?>')">
                                <button type="submit" class="admin-del-btn">Move to trash</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
