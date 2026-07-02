<?php $pageTitle = 'Edit Template'; ob_start(); ?>
<style>
.template-editor-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(320px,0.8fr);gap:1rem;align-items:start}
.template-preview{border:3px solid var(--line);box-shadow:4px 4px 0 var(--line);height:420px;background:#111}
.template-preview iframe{width:100%;height:100%;border:0;display:block}
@media (max-width: 900px){.template-editor-grid{grid-template-columns:1fr}}
</style>
<div class="admin-section">
    <div class="admin-section-head">
        <div>
            <p class="eyebrow">Pieces</p>
            <h1><?= e($template['label']) ?></h1>
        </div>
        <a href="/admin/pieces?tab=templates" class="admin-btn admin-btn-ghost">Back</a>
    </div>
    <?php if ($templateError ?? null): ?><p class="admin-error"><?= e($templateError) ?></p><?php endif; ?>
    <form class="admin-form" method="post" action="/admin/pieces/templates/<?= (int) $template['id'] ?>/edit">
        <div class="template-editor-grid">
            <div>
                <div class="admin-tabs" role="tablist" aria-label="Template editor">
                    <button type="button" class="admin-tab active" data-tab="meta">Metadata</button>
                    <button type="button" class="admin-tab" data-tab="html">HTML</button>
                    <button type="button" class="admin-tab" data-tab="css">CSS</button>
                    <button type="button" class="admin-tab" data-tab="js">JS</button>
                </div>
                <div class="template-panel" data-panel="meta">
                    <div class="field-grid">
                        <div class="field">
                            <label for="label">Label</label>
                            <input id="label" name="label" required value="<?= e($template['label'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label>Mode</label>
                            <input readonly value="<?= e($template['generation_mode'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="field">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="3"><?= e($template['description'] ?? '') ?></textarea>
                    </div>
                    <label class="toggle-opt"><input type="checkbox" name="is_default" value="1" <?= !empty($template['is_default']) ? 'checked' : '' ?>> Default for this mode</label>
                    <label class="toggle-opt"><input type="checkbox" name="is_active" value="1" <?= !empty($template['is_active']) ? 'checked' : '' ?>> Active</label>
                </div>
                <div class="template-panel is-hidden" data-panel="html">
                    <div class="field">
                        <label for="html_code">HTML</label>
                        <textarea id="html_code" name="html_code" rows="18" class="code-field"><?= e($template['html_code'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="template-panel is-hidden" data-panel="css">
                    <div class="field">
                        <label for="css_code">CSS</label>
                        <textarea id="css_code" name="css_code" rows="18" class="code-field"><?= e($template['css_code'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="template-panel is-hidden" data-panel="js">
                    <div class="field">
                        <label for="js_code">JS</label>
                        <textarea id="js_code" name="js_code" rows="18" class="code-field"><?= e($template['js_code'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
            <div>
                <h2>Preview</h2>
                <div id="template-preview" class="template-preview"></div>
            </div>
        </div>
        <div class="form-actions">
            <button class="admin-btn" type="submit">Save Template</button>
        </div>
    </form>
</div>
<script src="/assets/js/admin-piece-capture.js?v=<?= (int) @filemtime(dirname(__DIR__, 4) . '/assets/js/admin-piece-capture.js') ?>"></script>
<script>
var RUNTIME_ORIGIN = <?= json_encode(((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')) ?>;
var templateEngine = <?= json_encode((string) ($template['engine'] ?? 'p5')) ?>;
var titleField = document.getElementById('label');
var htmlField = document.getElementById('html_code');
var cssField = document.getElementById('css_code');
var jsField = document.getElementById('js_code');
var preview = document.getElementById('template-preview');
function renderTemplatePreview(){
    if (!window.CreatrPieceCapture) return;
    preview.innerHTML = '';
    var iframe = document.createElement('iframe');
    iframe.sandbox = 'allow-scripts allow-same-origin';
    iframe.title = titleField.value || 'Template preview';
    iframe.srcdoc = window.CreatrPieceCapture.renderDocument({
        title: iframe.title,
        engine: templateEngine,
        html: htmlField.value,
        css: cssField.value,
        js: jsField.value,
        runtimeOrigin: RUNTIME_ORIGIN,
        preserveDrawingBuffer: true
    });
    preview.appendChild(iframe);
}
var previewTimeout = null;
[titleField, htmlField, cssField, jsField].forEach(function(field){
    field.addEventListener('input', function(){
        clearTimeout(previewTimeout);
        previewTimeout = setTimeout(renderTemplatePreview, 400);
    });
});
document.querySelectorAll('.admin-tab[data-tab]').forEach(function(tab){
    tab.addEventListener('click', function(){
        document.querySelectorAll('.admin-tab[data-tab]').forEach(function(item){ item.classList.remove('active'); });
        document.querySelectorAll('.template-panel').forEach(function(panel){ panel.classList.add('is-hidden'); });
        tab.classList.add('active');
        document.querySelector('.template-panel[data-panel="' + tab.dataset.tab + '"]')?.classList.remove('is-hidden');
    });
});
renderTemplatePreview();
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/../layout.php'; ?>
