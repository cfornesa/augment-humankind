<?php

declare(strict_types=1);

$pageTitle = 'AI Generation Preview';
ob_start();

$previewPiece = ['title' => 'AI Generated ' . strtoupper($engine)];
$previewVersion = [
    'html_code' => $htmlCode,
    'css_code' => $cssCode,
    'generated_code' => $generatedCode,
    'engine' => $engine
];

$defaultTitle = 'AI ' . strtoupper($engine) . ' Piece - ' . date('M d, Y H:i');
?>
<div class="admin-container">
    <div class="admin-header-row">
        <h1>AI Generation Preview</h1>
        <div style="display: flex; gap: 0.5rem;">
            <a href="/admin/pieces/generate" class="admin-btn admin-btn-ghost">Discard &amp; Back</a>
        </div>
    </div>

    <div style="background: var(--ink); border: 1px solid var(--line); border-radius: 4px; padding: 0.5rem; margin-bottom: 2rem; box-shadow: var(--shadow);">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem; border-bottom: 1px solid var(--line); margin-bottom: 0.5rem; font-size: 0.85rem; color: #a1a1aa;">
            <span>Live Sandbox Preview (Engine: <strong><?= e(strtoupper($engine)) ?></strong>)</span>
            <button type="button" class="admin-btn admin-btn-ghost" style="padding: 2px 8px; font-size: 0.75rem;" onclick="reloadPreviewIframe()">Reload Sandbox</button>
        </div>
        <div id="preview-iframe-wrapper">
            <?= piece_render_iframe($previewPiece, $previewVersion, 450) ?>
        </div>
    </div>

    <form method="post" action="/admin/pieces/generate/save" class="admin-form">
        <!-- Hidden inputs for AI generation details -->
        <input type="hidden" name="engine" value="<?= e($engine) ?>">
        <input type="hidden" name="generation_vendor" value="<?= e($profile['vendor'] ?? '') ?>">
        <input type="hidden" name="generation_model" value="<?= e($profile['model'] ?? '') ?>">
        <input type="hidden" name="generation_attempt_count" value="<?= (int) $attemptCount ?>">
        <input type="hidden" name="profile_id" value="<?= (int) ($profileId ?? 0) ?>">
        <input type="hidden" name="persona_id" value="<?= (int) ($personaId ?? 0) ?>">

        <div class="admin-tabs piece-preview-tabs" role="tablist" style="margin-bottom: 1.5rem;">
            <button type="button" class="admin-tab active" data-tab="meta">Metadata</button>
            <button type="button" class="admin-tab" data-tab="html">HTML Code</button>
            <button type="button" class="admin-tab" data-tab="css">CSS Code</button>
            <button type="button" class="admin-tab" data-tab="js">JavaScript Code</button>
        </div>

        <!-- Metadata Tab -->
        <div id="tab-meta" class="piece-tab-panel">
            <div class="field">
                <label for="title">Title</label>
                <input id="title" name="title" type="text" required maxlength="255" value="<?= e($defaultTitle) ?>">
            </div>

            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="draft" selected>Draft (Recommended)</option>
                    <option value="active">Active (Visible in public list)</option>
                    <option value="archived">Archived</option>
                </select>
            </div>

            <div class="field">
                <label for="description">Description (Optional)</label>
                <textarea id="description" name="description" rows="3" placeholder="Brief details about what this piece shows or how it behaves..."></textarea>
            </div>

            <div class="field">
                <label for="prompt">Generation Prompt</label>
                <textarea id="prompt" name="prompt" rows="3" readonly style="background: rgba(255,255,255,0.05); color: var(--ink-soft); cursor: not-allowed;"><?= e($prompt) ?></textarea>
            </div>
        </div>

        <!-- HTML Code Tab -->
        <div id="tab-html" class="piece-tab-panel is-hidden">
            <div class="field">
                <label for="html_code">HTML Mounting Node</label>
                <textarea id="html_code" name="html_code" rows="16" class="code-field" style="font-family: monospace; font-size: 0.9rem;" oninput="updatePreview()"><?= e($htmlCode) ?></textarea>
                <small>The element(s) loaded into the page container.</small>
            </div>
        </div>

        <!-- CSS Code Tab -->
        <div id="tab-css" class="piece-tab-panel is-hidden">
            <div class="field">
                <label for="css_code">CSS Styles</label>
                <textarea id="css_code" name="css_code" rows="16" class="code-field" style="font-family: monospace; font-size: 0.9rem;" oninput="updatePreview()"><?= e($cssCode) ?></textarea>
                <small>Scoped CSS used to style the canvas or mounting nodes.</small>
            </div>
        </div>

        <!-- JS Code Tab -->
        <div id="tab-js" class="piece-tab-panel is-hidden">
            <div class="field">
                <label for="generated_code">JavaScript (window.sketch)</label>
                <textarea id="generated_code" name="generated_code" rows="16" class="code-field" style="font-family: monospace; font-size: 0.9rem;" oninput="updatePreview()"><?= e($generatedCode) ?></textarea>
                <small>The primary drawing/setup loops and handlers.</small>
            </div>
        </div>

        <input type="hidden" id="thumbnail_data" name="thumbnail_data" value="">

        <div class="form-actions" style="margin-top: 2rem; display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem;">
            <button type="submit" class="admin-btn">Save and Insert (Create Version 1)</button>
            <a href="/admin/pieces/generate" class="admin-btn admin-btn-ghost">Discard &amp; Restart</a>
            <span id="thumbnail-status" style="font-size: 0.8rem; color: var(--ink-soft);">Waiting for piece to render…</span>
        </div>
    </form>

    <script>
    // Computed server-side from the actual request (not window.location.origin):
    // the preview iframes below use srcdoc, which gets an opaque origin —
    // window.location.origin would literally be the string "null" inside
    // them, even with sandbox="allow-same-origin".
    var RUNTIME_ORIGIN = <?= json_encode(
        ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
    ) ?>;
    (function () {
        var tabs = document.querySelectorAll('.piece-preview-tabs .admin-tab');
        var panels = {
            meta: document.getElementById('tab-meta'),
            html: document.getElementById('tab-html'),
            css: document.getElementById('tab-css'),
            js: document.getElementById('tab-js')
        };
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                tabs.forEach(function (t) { t.classList.remove('active'); });
                tab.classList.add('active');
                Object.keys(panels).forEach(function (key) {
                    if (key === tab.dataset.tab) {
                        panels[key].classList.remove('is-hidden');
                    } else {
                        panels[key].classList.add('is-hidden');
                    }
                });
            });
        });
    })();

    function reloadPreviewIframe() {
        var wrapper = document.getElementById('preview-iframe-wrapper');
        if (!wrapper) return;
        var iframe = wrapper.querySelector('iframe');
        if (iframe) {
            iframe.srcdoc = iframe.srcdoc; // Triggers reload
        }
    }

    var updateTimeout = null;
    function updatePreview() {
        clearTimeout(updateTimeout);
        updateTimeout = setTimeout(function () {
            var html = document.getElementById('html_code').value;
            var css = document.getElementById('css_code').value;
            var js = document.getElementById('generated_code').value;
            var engine = document.querySelector('input[name="engine"]').value;

            // Call backend endpoint or rebuild local srcdoc structure
            var docTemplate = `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AI Generation Preview</title>
<style>
html,body{margin:0;padding:0;width:100%;height:100%;overflow:hidden;background:#0d0d0f;color:#fff;}
body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}
#runtime-root{width:100vw;height:100vh;overflow:hidden;}
#piece-error{position:fixed;inset:auto 1rem 1rem 1rem;z-index:9999;padding:1rem;border:1px solid #fca5a5;background:#450a0a;color:#fee2e2;font:14px/1.4 ui-monospace,SFMono-Regular,Menlo,monospace;white-space:pre-wrap;display:none;}
canvas{display:block;width:100%;height:100%;}
\${css}
</style>
</head>
<body>
<div id="runtime-root">\${html}</div>
<div id="piece-error" role="alert"></div>
<script type="importmap">
{
  "imports": {
    "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js",
    "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"
  }
}
</script>
<script>
const PIECE_ENGINE = \${JSON.stringify(engine)};
const PIECE_CODE = \${JSON.stringify(js)};
const PIECE_PRESERVE_DRAWING_BUFFER = true;
<\/script>
<script src="${RUNTIME_ORIGIN}/assets/js/piece-runtime.js"><\/script>
</body>
</html>`;

            var wrapper = document.getElementById('preview-iframe-wrapper');
            if (wrapper) {
                var iframe = wrapper.querySelector('iframe');
                if (iframe) {
                    iframe.srcdoc = docTemplate;
                }
            }
        }, 600);
    }

    // Auto-capture thumbnail from the preview iframe once the piece renders
    (function () {
        var captured = false;
        var thumbnailInput = document.getElementById('thumbnail_data');
        var statusEl = document.getElementById('thumbnail-status');
        var engine = document.querySelector('input[name="engine"]').value;

        function setStatus(msg, ok) {
            if (!statusEl) return;
            statusEl.textContent = msg;
            statusEl.style.color = ok ? '#10b981' : 'var(--ink-soft)';
        }

        function capture() {
            if (captured) return;
            captured = true;
            setTimeout(function () {
                try {
                    var wrapper = document.getElementById('preview-iframe-wrapper');
                    var iframe = wrapper ? wrapper.querySelector('iframe') : null;
                    if (!iframe || !iframe.contentDocument) {
                        setStatus('Thumbnail: preview not accessible');
                        return;
                    }
                    var canvas = iframe.contentDocument.querySelector('canvas');
                    if (!canvas) {
                        setStatus('Thumbnail: no canvas (SVG pieces use manual capture)');
                        return;
                    }
                    var dataUrl = canvas.toDataURL('image/png');
                    if (!dataUrl || dataUrl === 'data:,') {
                        setStatus('Thumbnail: canvas empty — will need manual capture');
                        return;
                    }
                    if (thumbnailInput) thumbnailInput.value = dataUrl;
                    setStatus('Thumbnail captured ✓', true);
                } catch (e) {
                    setStatus('Thumbnail: capture failed (' + e.message + ')');
                }
            }, engine === 'three' ? 3500 : 2000);
        }

        window.addEventListener('message', function (event) {
            if (event.data && event.data.type === 'sketch-status' && event.data.valid) {
                capture();
            }
        });

        // Fallback for engines that don't post sketch-status (e.g. SVG or p5 race)
        setTimeout(function () { if (!captured) capture(); }, 10000);
    })();
    </script>
</div>
<?php
$content = ob_get_clean();

require dirname(__DIR__) . '/layout.php';
