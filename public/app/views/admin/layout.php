<?php
$currentUri = $_SERVER['REQUEST_URI'] ?? '/admin';
$adminIdentity = admin_identity();
$adminNavItems = function_exists('admin_navigation_ordered_items') ? admin_navigation_ordered_items() : [];
$tiptapCssVersion = ($needsEditor ?? false) ? @filemtime(dirname(__DIR__, 3) . '/assets/css/tiptap.css') : null;
$tiptapJsVersion = ($needsEditor ?? false) ? @filemtime(dirname(__DIR__, 3) . '/assets/js/tiptap-editor.js') : null;
$ownerAiPrefs = class_exists('PlatformUser') ? (PlatformUser::owner() ?: []) : [];
$aiPickerPersonas = [];
if (function_exists('ah_table_exists') && ah_table_exists('ai_personas')) {
    try {
        $aiPickerPersonas = db()->query('SELECT id, name FROM ai_personas ORDER BY name ASC')->fetchAll();
    } catch (Throwable) {
        $aiPickerPersonas = [];
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>
    document.documentElement.classList.add('js-enhanced');
    // Apply stored theme before first paint to prevent flash
    (function(){var t=localStorage.getItem('theme');if(t==='dark'||t==='light')document.documentElement.dataset.theme=t;})();
    </script>
    <title><?= htmlspecialchars($pageTitle ?? 'Admin — Augment Humankind', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/assets/styles.css">
    <link rel="stylesheet" href="/assets/admin.css">
    <?php if ($needsEditor ?? false): ?>
    <link rel="stylesheet" href="/assets/css/tiptap.css?v=<?= (int) $tiptapCssVersion ?>">
    <script type="importmap">
    {
      "imports": {
        "@tiptap/core":                   "https://esm.sh/@tiptap/core@2",
        "@tiptap/starter-kit":            "https://esm.sh/@tiptap/starter-kit@2",
        "@tiptap/extension-underline":    "https://esm.sh/@tiptap/extension-underline@2",
        "@tiptap/extension-text-style":   "https://esm.sh/@tiptap/extension-text-style@2",
        "@tiptap/extension-color":        "https://esm.sh/@tiptap/extension-color@2",
        "@tiptap/extension-highlight":    "https://esm.sh/@tiptap/extension-highlight@2",
        "@tiptap/extension-font-family":  "https://esm.sh/@tiptap/extension-font-family@2",
        "@tiptap/extension-link":         "https://esm.sh/@tiptap/extension-link@2",
        "@tiptap/extension-image":        "https://esm.sh/@tiptap/extension-image@2"
      }
    }
    </script>
    <?php endif ?>
    <?php
    // Inject SiteSettings color overrides so admin respects custom themes
    $_ahAdminS = (class_exists('SiteSettings') ? SiteSettings::current() : false) ?: [];
    $_ahAdminLightMap = [
        'color_background'             => '--paper',
        'color_foreground'             => '--ink',
        'color_muted'                  => '--paper-deep',
        'color_muted_foreground'       => '--ink-soft',
        'color_primary'                => '--green',
        'color_primary_foreground'     => '--green-fg',
        'color_secondary'              => '--cyan',
        'color_secondary_foreground'   => '--cyan-fg',
        'color_accent'                 => '--orange',
        'color_accent_foreground'      => '--orange-fg',
        'color_destructive'            => '--destructive',
        'color_destructive_foreground' => '--destructive-fg',
    ];
    $_ahAdminDarkMap = [
        'color_background_dark'             => '--paper',
        'color_foreground_dark'             => '--ink',
        'color_muted_dark'                  => '--paper-deep',
        'color_muted_foreground_dark'       => '--ink-soft',
        'color_primary_dark'                => '--green',
        'color_primary_foreground_dark'     => '--green-fg',
        'color_secondary_dark'              => '--cyan',
        'color_secondary_foreground_dark'   => '--cyan-fg',
        'color_accent_dark'                 => '--orange',
        'color_accent_foreground_dark'      => '--orange-fg',
        'color_destructive_dark'            => '--destructive',
        'color_destructive_foreground_dark' => '--destructive-fg',
    ];
    $_ahAdminLightVars = [];
    foreach ($_ahAdminLightMap as $_ahC => $_ahV) {
        if (!empty($_ahAdminS[$_ahC])) {
            $_ahAdminLightVars[] = $_ahV . ':hsl(' . htmlspecialchars((string) $_ahAdminS[$_ahC], ENT_QUOTES, 'UTF-8') . ')';
        }
    }
    $_ahAdminDarkVars = [];
    foreach ($_ahAdminDarkMap as $_ahC => $_ahV) {
        if (!empty($_ahAdminS[$_ahC])) {
            $_ahAdminDarkVars[] = $_ahV . ':hsl(' . htmlspecialchars((string) $_ahAdminS[$_ahC], ENT_QUOTES, 'UTF-8') . ')';
        }
    }
    unset($_ahAdminLightMap, $_ahAdminDarkMap, $_ahC, $_ahV);
    ?>
    <?php if ($_ahAdminLightVars !== [] || $_ahAdminDarkVars !== []): ?>
    <style>
        <?php if ($_ahAdminLightVars !== []): ?>
        :root:not([data-theme="dark"]){<?= implode(';', $_ahAdminLightVars) ?>}
        <?php endif ?>
        <?php if ($_ahAdminDarkVars !== []): ?>
        @media(prefers-color-scheme:dark){:root:not([data-theme="light"]){<?= implode(';', $_ahAdminDarkVars) ?>}}
        [data-theme="dark"]{<?= implode(';', $_ahAdminDarkVars) ?>}
        <?php endif ?>
    </style>
    <?php endif ?>
</head>
<body class="admin-body">
    <div class="admin-chrome">
        <header class="admin-header">
            <div class="admin-brand">
                <span class="admin-kicker">Administration</span>
                <a href="/admin" class="admin-site-link">Augment Humankind</a>
                <?php if ($adminIdentity): ?>
                    <span class="admin-kicker">Signed in as <?= htmlspecialchars($adminIdentity['display_name'], ENT_QUOTES, 'UTF-8') ?> via <?= htmlspecialchars(ucfirst($adminIdentity['provider']), ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif ?>
            </div>
            <button class="menu-toggle" aria-label="Toggle navigation" aria-expanded="false" aria-controls="admin-nav">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <nav class="admin-nav" id="admin-nav" aria-label="Admin navigation">
                <?php foreach ($adminNavItems as $item): ?>
                    <?php $isActive = admin_navigation_is_active($currentUri, (string) $item['href']); ?>
                    <a href="<?= htmlspecialchars((string) $item['href'], ENT_QUOTES, 'UTF-8') ?>" class="<?= $isActive ? 'active' : '' ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                        <span class="admin-nav-label"><?= htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                <?php endforeach ?>
                <a href="/admin/logout" class="admin-logout">Logout</a>
            </nav>
        </header>

        <main class="admin-main">
            <?= $content ?>
        </main>
    </div>

    <!-- Media Picker Modal -->
    <dialog id="media-picker-modal" aria-labelledby="media-picker-title">
        <div class="media-picker-header">
            <h2 id="media-picker-title">Media Library</h2>
            <button type="button" class="media-picker-close" aria-label="Close">&times;</button>
        </div>

        <nav class="media-picker-tabs" role="tablist">
            <button class="media-picker-tab active" role="tab" data-tab="select"
                    aria-selected="true" aria-controls="mp-panel-select">Select</button>
            <button class="media-picker-tab" role="tab" data-tab="upload"
                    aria-selected="false" aria-controls="mp-panel-upload">Upload</button>
            <button class="media-picker-tab" role="tab" data-tab="import"
                    aria-selected="false" aria-controls="mp-panel-import">Import</button>
        </nav>

        <!-- Select panel -->
        <div class="media-picker-panel" id="mp-panel-select" role="tabpanel">
            <div class="media-picker-grid"></div>
        </div>

        <!-- Upload panel -->
        <div class="media-picker-panel" id="mp-panel-upload" role="tabpanel" hidden>
            <div class="media-picker-dropzone" id="mp-dropzone" tabindex="0" role="button"
                 aria-label="Click or drag to select a media file">
                <p class="mp-dropzone-label">Drag a file here or click to choose one</p>
                <input type="file" class="media-picker-file-input" accept="image/*,video/mp4,video/webm,video/quicktime">
                <p class="media-picker-hint" id="mp-upload-hint">JPEG &middot; PNG &middot; GIF &middot; WebP &middot; AVIF &middot; MP4 &middot; WebM &middot; MOV &middot; max 64 MB</p>
            </div>
            <!-- File preview shown after selection -->
            <div class="mp-file-info" id="mp-file-info" hidden>
                <div class="mp-file-preview-wrap">
                    <img class="mp-file-thumb" id="mp-file-thumb" src="" alt="">
                </div>
                <div class="mp-file-meta">
                    <span class="mp-file-name" id="mp-file-name"></span>
                    <span class="mp-file-size" id="mp-file-size"></span>
                    <span class="mp-file-type" id="mp-file-type"></span>
                </div>
            </div>
            <div class="media-picker-panel-actions">
                <button type="button" class="admin-btn media-picker-upload-btn" id="mp-upload-btn" disabled>Upload</button>
            </div>
            <p class="media-picker-status" id="mp-upload-status" aria-live="polite"></p>
        </div>

        <!-- Import panel -->
        <div class="media-picker-panel" id="mp-panel-import" role="tabpanel" hidden>
            <div class="media-picker-import-row">
                <input type="url" class="media-picker-url-input" id="mp-import-url"
                       placeholder="https://example.com/image.jpg" autocomplete="off">
                <button type="button" class="admin-btn media-picker-import-btn">Import</button>
            </div>
            <p class="media-picker-hint">The asset is downloaded and stored in your media library. Max 64 MB.</p>
            <p class="media-picker-status" id="mp-import-status"></p>
        </div>

        <!-- Alt text field — shown when an image is selected on the Select tab -->
        <div class="media-picker-alt-row" id="mp-alt-row" hidden>
            <label for="mp-alt-input">Alt text <em>(describe the image for screen readers — leave blank if purely decorative)</em></label>
            <div style="display:flex;gap:0.5rem;align-items:center;">
                <input type="text" id="mp-alt-input" class="media-picker-url-input"
                       placeholder="e.g. A cityscape at night with red lanterns" maxlength="250" autocomplete="off" style="flex:1;">
                <button type="button" id="mp-alt-ai-btn" class="admin-btn admin-btn-ghost admin-btn-sm"
                        title="Generate alt text with AI (requires vision-capable profile)">✨</button>
            </div>
        </div>

        <div class="media-picker-footer">
            <button type="button" class="admin-btn admin-btn-ghost media-picker-cancel-btn">Cancel</button>
            <button type="button" class="admin-btn media-picker-select-btn" disabled>Select Asset</button>
        </div>
    </dialog>

    <!-- Art Piece / Platform Collection Picker Modal -->
    <dialog id="piece-picker-modal" aria-labelledby="piece-picker-title">
        <div class="media-picker-header">
            <h2 id="piece-picker-title">Insert Art Piece or Platform Collection</h2>
            <button type="button" class="media-picker-close" aria-label="Close">&times;</button>
        </div>

        <nav class="media-picker-tabs" role="tablist">
            <button class="media-picker-tab active" role="tab" data-tab="pieces"
                    aria-selected="true" aria-controls="pp-panel-pieces">Pieces</button>
            <button class="media-picker-tab" role="tab" data-tab="collections"
                    aria-selected="false" aria-controls="pp-panel-collections">Platform Collections</button>
        </nav>

        <div class="media-picker-panel" id="pp-panel-pieces" role="tabpanel">
            <div class="media-picker-grid piece-picker-grid"></div>
        </div>

        <div class="media-picker-panel" id="pp-panel-collections" role="tabpanel" hidden>
            <div class="media-picker-grid piece-picker-grid"></div>
        </div>

        <div class="media-picker-footer">
            <button type="button" class="admin-btn admin-btn-ghost piece-picker-cancel-btn">Cancel</button>
            <button type="button" class="admin-btn piece-picker-select-btn" disabled>Insert</button>
        </div>
    </dialog>

    <!-- iFrame Embed Picker Modal -->
    <dialog id="iframe-picker-modal" aria-labelledby="iframe-picker-title">
        <div class="media-picker-header">
            <h2 id="iframe-picker-title">Insert iFrame Embed</h2>
            <button type="button" class="media-picker-close" aria-label="Close">&times;</button>
        </div>

        <div class="media-picker-panel">
            <label for="iframe-picker-input" class="media-picker-field-label">iframe URL or full &lt;iframe&gt; HTML</label>
            <textarea id="iframe-picker-input" class="media-picker-textarea"
                      placeholder="https://example.com/embed or &lt;iframe src=&quot;...&quot;&gt;&lt;/iframe&gt;"></textarea>
        </div>

        <div class="media-picker-footer">
            <button type="button" class="admin-btn admin-btn-ghost iframe-picker-cancel-btn">Cancel</button>
            <button type="button" class="admin-btn iframe-picker-insert-btn">Insert</button>
        </div>
    </dialog>

    <!-- AI Profile Picker Modal -->
    <dialog id="ai-profile-picker-modal" aria-labelledby="ai-profile-picker-title">
        <div class="media-picker-header">
            <h2 id="ai-profile-picker-title">Improve with AI</h2>
            <button type="button" class="media-picker-close" aria-label="Close">&times;</button>
        </div>

        <div class="media-picker-panel">
            <label for="ai-profile-picker-select" class="media-picker-field-label">AI Profile / Vendor &amp; Model</label>
            <select
                id="ai-profile-picker-select"
                class="media-picker-select"
                data-preferred-text-profile-id="<?= (int) ($ownerAiPrefs['preferred_text_improve_profile_id'] ?? 0) ?>"
                data-preferred-alt-profile-id="<?= (int) ($ownerAiPrefs['preferred_alt_text_profile_id'] ?? 0) ?>"
                data-preferred-piece-profile-id="<?= (int) ($ownerAiPrefs['preferred_art_piece_profile_id'] ?? 0) ?>"
            >
                <option value="">Loading&hellip;</option>
            </select>
            <label for="ai-persona-picker-select" class="media-picker-field-label" style="margin-top:1rem;">AI Persona <span style="font-weight:400;color:var(--ink-soft);">(optional)</span></label>
            <select id="ai-persona-picker-select" class="media-picker-select">
                <option value="">None — use the base task prompt</option>
                <?php foreach ($aiPickerPersonas as $persona): ?>
                    <option value="<?= (int) $persona['id'] ?>"><?= htmlspecialchars((string) $persona['name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
            <p class="media-picker-hint" id="ai-profile-picker-hint">Pick the model/profile and optionally layer a persona on top of the task prompt.</p>
        </div>

        <div class="media-picker-footer">
            <button type="button" class="admin-btn admin-btn-ghost ai-profile-picker-cancel-btn">Cancel</button>
            <button type="button" class="admin-btn ai-profile-picker-select-btn" disabled>Use Settings</button>
        </div>
    </dialog>

    <button class="theme-toggle" id="admin-theme-toggle" type="button" aria-label="Toggle dark mode">
        <span class="theme-icon" aria-hidden="true"></span>
    </button>
    <script>
    (function(){
        var btn=document.getElementById('admin-theme-toggle');
        var root=document.documentElement;
        var icon=btn&&btn.querySelector('.theme-icon');
        function update(){
            var isDark=root.dataset.theme==='dark'||(root.dataset.theme!=='light'&&window.matchMedia('(prefers-color-scheme:dark)').matches);
            if(icon)icon.textContent=isDark?'☀':'☾';
        }
        if(btn){
            btn.addEventListener('click',function(){
                var isDark=root.dataset.theme==='dark'||(root.dataset.theme!=='light'&&window.matchMedia('(prefers-color-scheme:dark)').matches);
                var next=isDark?'light':'dark';
                root.dataset.theme=next;
                localStorage.setItem('theme',next);
                update();
            });
        }
        update();
        if(window.matchMedia){window.matchMedia('(prefers-color-scheme:dark)').addEventListener('change',update);}
    })();
    </script>
    <script src="/assets/js/main.js" defer></script>
    <?php if ($needsEditor ?? false): ?>
    <script type="module" src="/assets/js/tiptap-editor.js?v=<?= (int) $tiptapJsVersion ?>"></script>
    <?php endif ?>
</body>
</html>
