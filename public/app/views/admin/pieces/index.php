<?php

declare(strict_types=1);

$pageTitle = 'Pieces';

$q      = $q      ?? '';
$engine = $engine ?? '';
$sort   = $sort   ?? 'sort_order';
$dir    = $dir    ?? 'asc';
$tab    = $tab    ?? 'art-pieces';
$templates = $templates ?? [];
$templatesTableReady = $templatesTableReady ?? true;

function pieces_sort_link(string $col, string $label, string $cur, string $curDir, array $carry): string {
    $next  = ($cur === $col && $curDir === 'asc') ? 'desc' : 'asc';
    $arrow = $cur === $col ? ($curDir === 'asc' ? ' &#8593;' : ' &#8595;') : '';
    $qs    = http_build_query(array_merge($carry, ['sort' => $col, 'dir' => $next]));
    return '<a href="?' . $qs . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . $arrow . '</a>';
}

$carry         = array_filter(['q' => $q, 'engine' => $engine], fn($v) => $v !== '');
$isDefaultSort = ($sort === 'sort_order');

ob_start();
?>
<div class="admin-container">
    <span id="reorder-status" class="reorder-status" aria-live="polite"></span>
    <div class="admin-header-row">
        <h1>Art Pieces</h1>
        <?php if ($tab === 'art-pieces'): ?>
        <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
            <?php if (!empty($pieces)): ?>
                <button type="button" id="btn-regen-all" class="admin-btn admin-btn-ghost">Regenerate All Thumbnails</button>
                <span id="regen-status" style="font-weight: bold; font-size: 0.9rem;"></span>
            <?php endif; ?>
            <?php if (feature_enabled('ai_pieces_code') && feature_any_ai_piece_generation_mode_enabled()): ?>
                <a href="/admin/pieces/generate" class="admin-btn">Generate with AI</a>
            <?php endif ?>
            <?php if (feature_enabled('pieces')): ?>
                <a href="/admin/pieces/create" class="admin-btn admin-btn-ghost">Create Piece</a>
            <?php endif ?>
        </div>
        <?php endif; ?>
    </div>

    <?= feature_disabled_notice('pieces') ?>

    <nav class="admin-tabs" aria-label="Pieces tabs">
        <a href="/admin/pieces?tab=art-pieces" class="admin-tab <?= $tab === 'art-pieces' ? 'active' : '' ?>">Art Pieces</a>
        <a href="/admin/pieces?tab=templates" class="admin-tab <?= $tab === 'templates' ? 'active' : '' ?>">Templates</a>
    </nav>

    <?php if ($tab === 'templates'): ?>
        <?php if (!$templatesTableReady): ?>
            <p class="admin-empty">Starter templates are not installed in this database yet. Run the database setup script to create and seed them.</p>
        <?php elseif (empty($templates)): ?>
            <p class="admin-empty">No starter templates have been seeded yet. Run the database setup script.</p>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead><tr><th>Template</th><th>Mode</th><th>Default</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($templates as $template): ?>
                        <tr>
                            <td><strong><?= e($template['label']) ?></strong><br><code><?= e($template['template_key']) ?></code></td>
                            <td><?= e($template['generation_mode']) ?></td>
                            <td><?= !empty($template['is_default']) ? 'Yes' : 'No' ?></td>
                            <td><?= !empty($template['is_active']) ? 'Active' : 'Inactive' ?></td>
                            <td><a href="/admin/pieces/templates/<?= (int) $template['id'] ?>/edit" class="admin-link">Edit</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php else: ?>
    <form class="admin-filter-bar" action="/admin/pieces" method="get" role="search">
        <input type="hidden" name="tab" value="art-pieces">
        <label class="sr-only" for="admin-pieces-q">Search pieces</label>
        <input id="admin-pieces-q" class="admin-filter-input" name="q" type="search"
               value="<?= e($q) ?>" placeholder="Search title, description, prompt…" autocomplete="off">
        <select name="engine" class="admin-filter-select" aria-label="Engine">
            <option value="" <?= $engine === '' ? 'selected' : '' ?>>All engines</option>
            <option value="p5"    <?= $engine === 'p5'    ? 'selected' : '' ?>>P5.js</option>
            <option value="c2"    <?= $engine === 'c2'    ? 'selected' : '' ?>>C2.js</option>
            <option value="three" <?= $engine === 'three' ? 'selected' : '' ?>>Three.js</option>
            <option value="svg"   <?= $engine === 'svg'   ? 'selected' : '' ?>>SVG</option>
            <option value="aframe" <?= $engine === 'aframe' ? 'selected' : '' ?>>A-Frame</option>
        </select>
        <button class="admin-btn admin-btn-sm" type="submit">Filter</button>
        <?php if ($q !== '' || $engine !== '' || !$isDefaultSort): ?>
            <a href="/admin/pieces" class="admin-filter-reset">Reset view</a>
        <?php endif; ?>
    </form>

    <?php if (empty($pieces)): ?>
        <p><?= ($q !== '' || $engine !== '') ? 'No pieces matched your filters.' : ('No art pieces yet.' . (feature_enabled('pieces') ? ' <a href="/admin/pieces/create">Create the first one</a>.' : '')) ?></p>
    <?php else: ?>
        <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th></th>
                    <th>Thumbnail</th>
                    <th><?= pieces_sort_link('title', 'Title', $sort, $dir, $carry) ?></th>
                    <th>ID</th>
                    <th><?= pieces_sort_link('engine', 'Engine', $sort, $dir, $carry) ?></th>
                    <th>Art Media</th>
                    <th><?= pieces_sort_link('status', 'Status', $sort, $dir, $carry) ?></th>
                    <th>Versions</th>
                    <th><?= pieces_sort_link('created', 'Created', $sort, $dir, $carry) ?></th>
                    <th><?= pieces_sort_link('updated', 'Updated', $sort, $dir, $carry) ?></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody data-reorder-url="/admin/pieces/reorder" class="<?= !$isDefaultSort ? 'drag-handles-hidden' : '' ?>">
                <?php foreach ($pieces as $piece): ?>
                    <tr data-id="<?= (int) $piece['id'] ?>">
                        <td class="drag-handle" title="Drag to reorder">&#8597;</td>
                        <td class="cell-thumb" style="width: 70px;">
                            <?php if (!empty($piece['thumbnail_url'])): ?>
                                <img src="<?= e($piece['thumbnail_url']) ?>" alt="<?= e((string)($piece['thumbnail_alt_text'] ?? $piece['title'] ?? '')) ?>" loading="lazy" style="width: 60px; height: 45px; object-fit: cover; border: 1px solid var(--line); display: block;">
                            <?php else: ?>
                                <div class="empty-thumb-placeholder" data-piece-id="<?= (int) $piece['id'] ?>" style="width: 60px; height: 45px; border: 1px dashed var(--line); background: var(--paper); display: flex; align-items: center; justify-content: center; font-size: 10px; color: var(--ink-soft);">None</div>
                            <?php endif; ?>
                        </td>
                        <td class="cell-title" data-label="Title"><?= e($piece['title'] ?? 'Untitled') ?></td>
                        <td data-label="ID"><code><?= (int) $piece['id'] ?></code></td>
                        <td data-label="Engine"><?= e(strtoupper($piece['engine'] ?? 'p5')) ?></td>
                        <td data-label="Art Media">
                            <?php if (empty($piece['categories'])): ?>
                                <span class="admin-hint">None</span>
                            <?php else: ?>
                                <?= e(implode(', ', array_column($piece['categories'], 'name'))) ?>
                            <?php endif; ?>
                        </td>
                        <td data-label="Status">
                            <span class="status-badge status-<?= e($piece['status'] ?? 'active') ?>">
                                <?= e($piece['status'] ?? 'active') ?>
                            </span>
                        </td>
                        <td data-label="Versions"><?= (int) ($piece['version_count'] ?? 0) ?></td>
                        <td data-label="Created"><?= e($piece['created_at'] ?? '') ?></td>
                        <td data-label="Updated"><?= e($piece['updated_at'] ?? '') ?></td>
                        <td class="admin-actions-cell">
                                <a href="/pieces/<?= (int) $piece['id'] ?>" target="_blank" class="admin-btn admin-btn-sm admin-btn-ghost">View</a>
                                <a href="/immersive/pieces/<?= (int) $piece['id'] ?>" target="_blank" class="admin-btn admin-btn-sm admin-btn-ghost">Immersive</a>
                                <button type="button" class="admin-btn admin-btn-sm admin-btn-ghost btn-capture-piece-thumb" data-id="<?= (int) $piece['id'] ?>">Generate Thumbnail</button>
                                <a href="/admin/pieces/<?= (int) $piece['id'] ?>/versions" class="admin-btn admin-btn-sm admin-btn-ghost">Versions</a>
                                <a href="/admin/pieces/<?= (int) $piece['id'] ?>/edit" class="admin-btn admin-btn-sm admin-btn-ghost">Edit</a>
                                <?php if (($piece['status'] ?? 'active') === 'draft'): ?>
                                    <form method="post" action="/admin/pieces/<?= (int) $piece['id'] ?>/set-status" class="inline-form">
                                        <input type="hidden" name="status" value="active">
                                        <button type="submit" class="admin-btn admin-btn-sm">Publish</button>
                                    </form>
                                <?php elseif (($piece['status'] ?? 'active') === 'active'): ?>
                                    <form method="post" action="/admin/pieces/<?= (int) $piece['id'] ?>/set-status" class="inline-form">
                                        <input type="hidden" name="status" value="archived">
                                        <button type="submit" class="admin-btn admin-btn-sm admin-btn-ghost">Archive</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="/admin/pieces/<?= (int) $piece['id'] ?>/set-status" class="inline-form">
                                        <input type="hidden" name="status" value="active">
                                        <button type="submit" class="admin-btn admin-btn-sm admin-btn-ghost">Restore</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" action="/admin/pieces/<?= (int) $piece['id'] ?>/delete" class="inline-form" onsubmit="return confirm('Move this piece to trash?')">
                                    <button type="submit" class="admin-btn admin-btn-sm admin-btn-danger">Delete</button>
                                </form>
                            </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($tab === 'art-pieces'): ?>
<script src="/assets/js/admin-piece-capture.js?v=<?= (int) @filemtime(dirname(__DIR__, 4) . '/assets/js/admin-piece-capture.js') ?>"></script>
<script>
(function () {
    // A real, genuinely visible iframe — WebKit was found to silently skip
    // the actual GPU paint for a canvas clipped into a near-zero-area
    // container (the old background-capture approach), even while running
    // that iframe's script normally. Capturing from a real visible iframe
    // is the one mechanism already proven reliable (generate-preview.php,
    // the editor's live preview) — this gives this page the same thing,
    // sized small enough to stay out of the way on mobile.
    function createCaptureOverlay() {
        var overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:99999;display:flex;align-items:center;justify-content:center;';
        var box = document.createElement('div');
        box.style.cssText = 'background:var(--ink,#0d0d0f);border:1px solid var(--line,#333);border-radius:4px;padding:0.75rem;box-shadow:0 8px 24px rgba(0,0,0,0.4);';
        var label = document.createElement('div');
        label.textContent = 'Capturing thumbnail…';
        label.style.cssText = 'color:var(--ink-soft,#a1a1aa);font-size:0.8rem;margin-bottom:0.5rem;text-align:center;';
        var iframe = document.createElement('iframe');
        iframe.style.cssText = 'width:320px;height:180px;border:0;display:block;';
        iframe.sandbox = 'allow-scripts allow-same-origin';
        box.appendChild(label);
        box.appendChild(iframe);
        overlay.appendChild(box);
        document.body.appendChild(overlay);
        return {
            iframe: iframe,
            remove: function () {
                if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
            }
        };
    }

    // Reusable core capture and upload routine
    async function runCaptureForId(id, cellThumb, btnIndividual) {
        var originalText = btnIndividual ? btnIndividual.textContent : '';
        if (btnIndividual) {
            btnIndividual.disabled = true;
            btnIndividual.textContent = 'Generating…';
            btnIndividual.style.color = 'var(--ink-soft)';
        }
        if (cellThumb) {
            cellThumb.style.opacity = '0.5';
            var placeholder = cellThumb.querySelector('.empty-thumb-placeholder');
            if (placeholder) {
                placeholder.textContent = 'Capturing…';
            }
        }

        var overlay = null;
        try {
            // Fetch piece details
            var resp = await fetch('/embed/pieces/' + id + '/data');
            if (!resp.ok) {
                throw new Error('Fetch failed: ' + resp.status);
            }
            var data = await resp.json();

            if (!data.generatedCode && !data.htmlCode) {
                throw new Error('This piece has no code yet — open it in Edit and add JS/HTML before generating a thumbnail.');
            }

            var engine = data.engine || 'p5';
            overlay = createCaptureOverlay();
            overlay.iframe.srcdoc = window.CreatrPieceCapture.renderDocument({
                title: data.title || 'Art piece',
                engine: engine,
                html: data.htmlCode || '',
                css: data.cssCode || '',
                js: data.generatedCode || '',
                runtimeOrigin: window.location.origin,
                preserveDrawingBuffer: true,
                seed: 8383,
                width: 320,
                height: 180
            });
            await window.CreatrPieceCapture.waitForRender(overlay.iframe, engine);

            var capture = await window.CreatrPieceCapture.capture({
                engine: engine,
                liveIframe: overlay.iframe,
                width: 320,
                height: 180
            });
            if (!capture.ok) {
                throw new Error(capture.error || 'Thumbnail capture failed.');
            }

            // Upload
            var formData = new FormData();
            formData.append('image_data', capture.dataUrl);

            var uploadResp = await fetch('/admin/pieces/' + id + '/capture-thumbnail', {
                method: 'POST',
                body: formData
            });

            if (!uploadResp.ok) {
                var err = await uploadResp.json();
                throw new Error(err.error || 'Server error');
            }

            var res = await uploadResp.json();
            
            // Update cell thumbnail
            if (cellThumb) {
                cellThumb.innerHTML = '<img src="' + res.url + '?t=' + Date.now() + '" alt="" loading="lazy" style="width: 60px; height: 45px; object-fit: cover; border: 1px solid var(--line); display: block;">';
                cellThumb.style.opacity = '';
            }
            
            if (btnIndividual) {
                btnIndividual.textContent = 'Generated!';
                btnIndividual.style.color = 'var(--green)';
                setTimeout(function () {
                    btnIndividual.textContent = originalText;
                    btnIndividual.style.color = '';
                    btnIndividual.disabled = false;
                }, 3000);
            }
        } catch (err) {
            console.error('Capture failed for ID ' + id + ':', err);
            if (cellThumb) {
                cellThumb.style.opacity = '';
                var placeholder = cellThumb.querySelector('.empty-thumb-placeholder');
                if (placeholder) {
                    placeholder.textContent = 'Failed';
                }
            }
            if (btnIndividual) {
                btnIndividual.textContent = 'Failed';
                btnIndividual.style.color = 'var(--red)';
                setTimeout(function () {
                    btnIndividual.textContent = originalText;
                    btnIndividual.style.color = '';
                    btnIndividual.disabled = false;
                }, 3000);
            }
            throw err;
        } finally {
            if (overlay) overlay.remove();
        }
    }

    // Register click via event delegation on document
    document.addEventListener('click', async function (event) {
        var btnIndividual = event.target.closest('.btn-capture-piece-thumb');
        if (btnIndividual) {
            event.preventDefault();
            var id = btnIndividual.dataset.id;
            var row = btnIndividual.closest('tr');
            var cellThumb = row ? row.querySelector('.cell-thumb') : null;
            try {
                await runCaptureForId(id, cellThumb, btnIndividual);
            } catch (err) {
                alert('Thumbnail generation failed: ' + err.message);
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
        var pieceIds = Array.from(rows).map(function (row) {
            return row.dataset.id;
        });

        console.log('Found ' + pieceIds.length + ' pieces to process:', pieceIds);

        if (pieceIds.length === 0) {
            status.style.color = 'var(--ink)';
            status.textContent = 'No pieces found.';
            return;
        }

        if (!confirm('Are you sure you want to regenerate thumbnails for all ' + pieceIds.length + ' pieces sequentially? A brief preview overlay will show each piece rendering as its thumbnail is captured.')) {
            console.log('Regeneration cancelled by user.');
            return;
        }

        btnRegen.disabled = true;
        btnRegen.textContent = 'Regenerating…';
        status.style.color = 'var(--yellow)';

        var successCount = 0;
        var failCount = 0;

        for (var i = 0; i < pieceIds.length; i++) {
            var id = pieceIds[i];
            console.log('Regenerating piece ' + (i + 1) + '/' + pieceIds.length + ' (ID: ' + id + ')');
            status.textContent = 'Processing ' + (i + 1) + '/' + pieceIds.length + ' (ID: ' + id + ')';

            var row = document.querySelector('tbody tr[data-id="' + id + '"]');
            var cellThumb = row ? row.querySelector('.cell-thumb') : null;
            var btnIndividual = row ? row.querySelector('.btn-capture-piece-thumb') : null;

            try {
                await runCaptureForId(id, cellThumb, btnIndividual);
                successCount++;
            } catch (err) {
                console.error('Failed for piece ID ' + id + ':', err);
                failCount++;
            }

            // Brief delay to allow garbage collection and context release
            await new Promise(function (resolve) { setTimeout(resolve, 500); });
        }

        status.style.color = failCount > 0 ? 'var(--red)' : 'var(--green)';
        status.textContent = 'Done! Success: ' + successCount + ', Failed: ' + failCount;
        btnRegen.disabled = false;
        btnRegen.textContent = 'Regenerate All Thumbnails';
    });

    // Sequential Auto-Capture Queue for empty placeholders
    async function runAutoCaptureQueue() {
        var placeholders = Array.from(document.querySelectorAll('.empty-thumb-placeholder'));
        if (placeholders.length === 0) return;
        
        console.log('Auto-capture queue started for ' + placeholders.length + ' placeholder(s).');
        for (var i = 0; i < placeholders.length; i++) {
            var placeholder = placeholders[i];
            var id = placeholder.dataset.pieceId;
            var cellThumb = placeholder.closest('.cell-thumb');
            var row = placeholder.closest('tr');
            var btnIndividual = row ? row.querySelector('.btn-capture-piece-thumb') : null;

            try {
                await runCaptureForId(id, cellThumb, btnIndividual);
            } catch (err) {
                console.error('Auto-capture failed for piece ID ' + id + ':', err);
            }
            await new Promise(function (resolve) { setTimeout(resolve, 500); });
        }
        console.log('Auto-capture queue complete.');
    }

    // Run queue on load (after a short delay to let page settle)
    window.addEventListener('load', function () {
        setTimeout(runAutoCaptureQueue, 1000);
    });
})();
</script>
<?php endif; ?>
<?php
$content = ob_get_clean();

require dirname(__DIR__) . '/layout.php';
