<?php
$pageTitle = 'Exhibits — ' . app_site_name() . ' Admin';

$q    = $q    ?? '';
$sort = $sort ?? 'sort_order';
$dir  = $dir  ?? 'asc';

function exhibit_sort_link(string $col, string $label, string $cur, string $curDir, array $carry): string {
    $next  = ($cur === $col && $curDir === 'asc') ? 'desc' : 'asc';
    $arrow = $cur === $col ? ($curDir === 'asc' ? ' &#8593;' : ' &#8595;') : '';
    $qs    = http_build_query(array_merge($carry, ['sort' => $col, 'dir' => $next]));
    return '<a href="?' . $qs . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . $arrow . '</a>';
}

$carry         = array_filter(['q' => $q], fn($v) => $v !== '');
$isDefaultSort = ($sort === 'sort_order');

ob_start();
?>
<div class="admin-section">
    <span id="reorder-status" class="reorder-status" aria-live="polite"></span>
    <div class="admin-section-head">
        <h1 class="admin-heading">Exhibits</h1>
        <?php if (feature_enabled('exhibits')): ?>
            <a href="/admin/exhibits/create" class="admin-btn">+ Add Exhibit</a>
        <?php endif ?>
    </div>

    <?= feature_disabled_notice('exhibits') ?>

    <form class="admin-filter-bar" action="/admin/exhibits" method="get" role="search">
        <label class="sr-only" for="admin-exhibits-q">Search exhibits</label>
        <input id="admin-exhibits-q" class="admin-filter-input" name="q" type="search"
               value="<?= e($q) ?>" placeholder="Search title, description…" autocomplete="off">
        <button class="admin-btn admin-btn-sm" type="submit">Filter</button>
        <?php if ($q !== '' || !$isDefaultSort): ?>
            <a href="/admin/exhibits" class="admin-filter-reset">Reset view</a>
        <?php endif; ?>
    </form>

    <?php if (empty($exhibits)): ?>
        <p class="admin-empty"><?= $q !== '' ? 'No exhibits matched your search.' : 'No exhibits yet.' ?></p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th></th>
                    <th><?= exhibit_sort_link('title', 'Title', $sort, $dir, $carry) ?></th>
                    <th>Year</th>
                    <th>Category</th>
                    <th>Collection</th>
                    <th>Slides</th>
                    <th><?= exhibit_sort_link('created', 'Created', $sort, $dir, $carry) ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="artworks-sortable" data-reorder-url="/admin/exhibits/reorder" class="<?= !$isDefaultSort ? 'drag-handles-hidden' : '' ?>">
                <?php foreach ($exhibits as $ex): ?>
                    <tr data-id="<?= $ex['id'] ?>">
                        <td class="drag-handle" title="Drag to reorder">&#8597;</td>
                        <td class="cell-title" data-label="Title">
                            <a href="/portfolio/exhibit/<?= htmlspecialchars($ex['slug']) ?>" target="_blank">
                                <?= htmlspecialchars($ex['title']) ?>
                            </a>
                        </td>
                        <td data-label="Year"><?= htmlspecialchars($ex['year'] ?? '') ?></td>
                        <td data-label="Category"><?= htmlspecialchars(implode(', ', array_column($ex['categories'] ?? [], 'name')) ?: '—') ?></td>
                        <td data-label="Collection"><?= htmlspecialchars(implode(', ', array_column($ex['collections'] ?? [], 'name')) ?: '—') ?></td>
                        <td data-label="Slides"><?= count($ex['media_items'] ?? Exhibit::resolvedMediaItems($ex)) ?></td>
                        <td data-label="Created"><?= htmlspecialchars($ex['created_at'] ?? '') ?></td>
                        <td class="admin-actions admin-actions-cell">
                            <a href="/admin/exhibits/<?= $ex['id'] ?>/edit">Edit</a>
                            <form method="POST" action="/admin/exhibits/<?= $ex['id'] ?>/delete"
                                  onsubmit="return confirm('Move this exhibit to the recycle bin?')">
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
