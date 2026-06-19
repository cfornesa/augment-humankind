<?php

declare(strict_types=1);

$pageTitle = 'Platform Collections';

$q    = $q    ?? '';
$sort = $sort ?? 'sort_order';
$dir  = $dir  ?? 'asc';

function pcol_sort_link(string $col, string $label, string $cur, string $curDir, array $carry): string {
    $next  = ($cur === $col && $curDir === 'asc') ? 'desc' : 'asc';
    $arrow = $cur === $col ? ($curDir === 'asc' ? ' &#8593;' : ' &#8595;') : '';
    $qs    = http_build_query(array_merge($carry, ['sort' => $col, 'dir' => $next]));
    return '<a href="?' . $qs . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . $arrow . '</a>';
}

$carry         = array_filter(['q' => $q], fn($v) => $v !== '');
$isDefaultSort = ($sort === 'sort_order');

ob_start();
?>
<div class="admin-container">
    <span id="reorder-status" class="reorder-status" aria-live="polite"></span>
    <div class="admin-header-row">
        <h1>Platform Collections</h1>
        <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
            <?php if (!empty($collections)): ?>
                <button type="button" id="btn-regen-all" class="admin-btn admin-btn-ghost">Regenerate All Thumbnails</button>
                <span id="regen-status" style="font-weight: bold; font-size: 0.9rem;"></span>
            <?php endif; ?>
            <a href="/admin/platform-collections/create" class="admin-btn">+ New Collection</a>
        </div>
    </div>

    <p>Curated collections migrated from the platform app. Manage their metadata, items, layouts, and external embeds here.</p>

    <form class="admin-filter-bar" action="/admin/platform-collections" method="get" role="search">
        <label class="sr-only" for="admin-pcol-q">Search collections</label>
        <input id="admin-pcol-q" class="admin-filter-input" name="q" type="search"
               value="<?= e($q) ?>" placeholder="Search name, description, piece titles…" autocomplete="off">
        <button class="admin-btn admin-btn-sm" type="submit">Filter</button>
        <?php if ($q !== '' || !$isDefaultSort): ?>
            <a href="/admin/platform-collections" class="admin-filter-reset">Reset view</a>
        <?php endif; ?>
    </form>

    <?php if (empty($collections)): ?>
        <p><?= $q !== '' ? 'No collections matched your search.' : 'No platform collections yet.' ?></p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th></th>
                    <th>Thumbnail</th>
                    <th><?= pcol_sort_link('name', 'Name', $sort, $dir, $carry) ?></th>
                    <th>Slug</th>
                    <th><?= pcol_sort_link('items', 'Items', $sort, $dir, $carry) ?></th>
                    <th><?= pcol_sort_link('created', 'Created', $sort, $dir, $carry) ?></th>
                    <th><?= pcol_sort_link('updated', 'Updated', $sort, $dir, $carry) ?></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody data-reorder-url="/admin/platform-collections/reorder" class="<?= !$isDefaultSort ? 'drag-handles-hidden' : '' ?>">
                <?php foreach ($collections as $collection): ?>
                    <tr data-id="<?= (int) $collection['id'] ?>" data-slug="<?= e($collection['slug'] ?? '') ?>">
                        <td class="drag-handle" title="Drag to reorder">&#8597;</td>
                        <td class="cell-thumb">
                            <?php if (!empty($collection['thumbnail_url'])): ?>
                                <img src="<?= e($collection['thumbnail_url']) ?>" alt="" loading="lazy" style="width: 60px; height: 45px; object-fit: cover;">
                            <?php endif; ?>
                        </td>
                        <td class="cell-title" data-label="Name"><?= e($collection['name'] ?? 'Untitled Collection') ?></td>
                        <td data-label="Slug"><code><?= e($collection['slug'] ?? '') ?></code></td>
                        <td data-label="Items"><?= (int) ($collection['item_count'] ?? 0) ?></td>
                        <td data-label="Created"><?= e($collection['created_at'] ?? '') ?></td>
                        <td data-label="Updated"><?= e($collection['updated_at'] ?? '') ?></td>
                        <td class="admin-actions-cell">
                                <a href="/collections/<?= e($collection['slug'] ?? '') ?>" target="_blank" class="admin-btn admin-btn-sm admin-btn-ghost">View</a>
                                <a href="/immersive/collections/<?= e($collection['slug'] ?? '') ?>" target="_blank" class="admin-btn admin-btn-sm admin-btn-ghost">Immersive</a>
                                <button type="button" class="admin-btn admin-btn-sm admin-btn-ghost btn-capture-collection-thumb" data-id="<?= (int) $collection['id'] ?>" data-slug="<?= e($collection['slug'] ?? '') ?>">Generate Thumbnail</button>
                                <a href="/admin/platform-collections/<?= (int) $collection['id'] ?>/edit" class="admin-btn admin-btn-sm admin-btn-ghost">Edit</a>
                                <form method="POST" action="/admin/platform-collections/<?= (int) $collection['id'] ?>/delete" style="display:inline;" onsubmit="return confirm('Move this collection to the recycle bin?')">
                                    <button type="submit" class="admin-btn admin-btn-sm admin-btn-danger">Delete</button>
                                </form>
                            </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
(function () {
    document.addEventListener('click', async function (event) {
        var btnIndividual = event.target.closest('.btn-capture-collection-thumb');
        if (btnIndividual) {
            event.preventDefault();
            var id = btnIndividual.dataset.id;
            var slug = btnIndividual.dataset.slug;
            var row = btnIndividual.closest('tr');
            var imgContainer = row.querySelector('td:nth-child(2)'); // second cell is the thumbnail cell
            var originalText = btnIndividual.textContent;
            btnIndividual.disabled = true;
            btnIndividual.textContent = 'Generating…';
            btnIndividual.style.color = 'var(--ink-soft)';

            var captureFrame = null;

            try {
                // Mount off-screen iframe
                captureFrame = document.createElement('iframe');
                captureFrame.style.cssText = 'position:fixed;left:-9999px;top:-9999px;width:960px;height:540px;border:none;';
                captureFrame.sandbox = 'allow-scripts allow-same-origin';
                document.body.appendChild(captureFrame);

                // Load collection in immersive mode with closeup parameter enabled
                captureFrame.src = '/immersive/collections/' + slug + '?embed=1&closeup=1';

                await new Promise(function (resolve) {
                    captureFrame.onload = resolve;
                    setTimeout(resolve, 1500);
                });

                // Wait 4s for Three.js wall setup and progressive assets load
                await new Promise(function (resolve) { setTimeout(resolve, 4000); });

                var iframeDoc = captureFrame.contentDocument || captureFrame.contentWindow.document;
                var canvas = iframeDoc && iframeDoc.querySelector('canvas');

                if (!canvas) {
                    throw new Error('No canvas found inside iframe');
                }

                var imageData = canvas.toDataURL('image/png');
                document.body.removeChild(captureFrame);
                captureFrame = null;

                // Upload base64 capture
                var formData = new FormData();
                formData.append('image_data', imageData);

                var uploadResp = await fetch('/admin/platform-collections/' + id + '/capture-thumbnail', {
                    method: 'POST',
                    body: formData
                });

                if (!uploadResp.ok) {
                    var err = await uploadResp.json();
                    throw new Error(err.error || 'Server error');
                }

                var res = await uploadResp.json();

                // Update row cell thumbnail image
                if (imgContainer) {
                    imgContainer.innerHTML = '<img src="' + res.url + '?t=' + Date.now() + '" alt="" loading="lazy" style="width: 60px; height: 45px; object-fit: cover; border: 1px solid var(--line); display: block;">';
                }

                btnIndividual.textContent = 'Generated!';
                btnIndividual.style.color = 'var(--green)';
                setTimeout(function () {
                    btnIndividual.textContent = originalText;
                    btnIndividual.style.color = '';
                    btnIndividual.disabled = false;
                }, 3000);
            } catch (err) {
                console.error('Individual capture failed for collection ID ' + id + ':', err);
                alert('Thumbnail generation failed: ' + err.message);
                btnIndividual.textContent = 'Failed';
                btnIndividual.style.color = 'var(--red)';
                setTimeout(function () {
                    btnIndividual.textContent = originalText;
                    btnIndividual.style.color = '';
                    btnIndividual.disabled = false;
                }, 3000);
                if (captureFrame && captureFrame.parentNode) {
                    document.body.removeChild(captureFrame);
                }
            }
            return;
        }

        var btnRegen = event.target.closest('#btn-regen-all');
        if (!btnRegen) return;

        event.preventDefault();
        console.log('Regenerate All Thumbnails click event intercepted.');

        var status = document.getElementById('regen-status');
        if (!status) {
            console.error('regen-status element not found in DOM.');
            alert('Configuration Error: regen-status container not found.');
            return;
        }

        var rows = document.querySelectorAll('tbody tr[data-id]');
        var collections = Array.from(rows).map(function (row) {
            return {
                id: row.dataset.id,
                slug: row.dataset.slug
            };
        });

        console.log('Found ' + collections.length + ' collections to process:', collections);

        if (collections.length === 0) {
            status.style.color = 'var(--ink)';
            status.textContent = 'No collections found.';
            return;
        }

        if (!confirm('Are you sure you want to regenerate thumbnails for all ' + collections.length + ' collections sequentially? This renders each collection in a background frame.')) {
            console.log('Regeneration cancelled by user.');
            return;
        }

        btnRegen.disabled = true;
        btnRegen.textContent = 'Regenerating…';
        status.style.color = 'var(--yellow)';

        var successCount = 0;
        var failCount = 0;

        for (var i = 0; i < collections.length; i++) {
            var col = collections[i];
            console.log('Regenerating collection ' + (i + 1) + '/' + collections.length + ' (ID: ' + col.id + ', Slug: ' + col.slug + ')');
            status.textContent = 'Processing ' + (i + 1) + '/' + collections.length + ' (ID: ' + col.id + ')';

            try {
                // Mount off-screen iframe
                var captureFrame = document.createElement('iframe');
                captureFrame.style.cssText = 'position:fixed;left:-9999px;top:-9999px;width:960px;height:540px;border:none;';
                captureFrame.sandbox = 'allow-scripts allow-same-origin';
                document.body.appendChild(captureFrame);

                // Load collection in immersive mode with closeup parameter enabled
                captureFrame.src = '/immersive/collections/' + col.slug + '?embed=1&closeup=1';

                await new Promise(function (resolve) {
                    captureFrame.onload = resolve;
                    // Safety fallback timeout
                    setTimeout(resolve, 1500);
                });

                // Wait 4s for Three.js wall setup and progressive assets load
                await new Promise(function (resolve) { setTimeout(resolve, 4000); });

                var iframeDoc = captureFrame.contentDocument || captureFrame.contentWindow.document;
                var canvas = iframeDoc && iframeDoc.querySelector('canvas');

                if (!canvas) {
                    throw new Error('No canvas found inside iframe');
                }

                var imageData = canvas.toDataURL('image/png');
                document.body.removeChild(captureFrame);

                // Upload base64 capture
                var formData = new FormData();
                formData.append('image_data', imageData);

                var uploadResp = await fetch('/admin/platform-collections/' + col.id + '/capture-thumbnail', {
                    method: 'POST',
                    body: formData
                });

                if (!uploadResp.ok) {
                    var err = await uploadResp.json();
                    throw new Error(err.error || 'Server error');
                }

                console.log('Successfully regenerated thumbnail for collection ID ' + col.id);
                successCount++;
            } catch (err) {
                console.error('Failed for collection ID ' + col.id + ':', err);
                failCount++;
                if (captureFrame && captureFrame.parentNode) {
                    document.body.removeChild(captureFrame);
                }
            }

            // Brief delay to allow garbage collection and WebGL context release
            await new Promise(function (resolve) { setTimeout(resolve, 500); });
        }

        status.style.color = failCount > 0 ? 'var(--red)' : 'var(--green)';
        status.textContent = 'Done! Success: ' + successCount + ', Failed: ' + failCount;
        btnRegen.disabled = false;
        btnRegen.textContent = 'Regenerate All Thumbnails';

        if (successCount > 0) {
            setTimeout(function () {
                window.location.reload();
            }, 2000);
        }
    });
})();
</script>
<?php
$content = ob_get_clean();

require dirname(__DIR__) . '/layout.php';
