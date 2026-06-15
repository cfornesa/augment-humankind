<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/admin', PHP_URL_PATH) ?: '/admin';
$adminIdentity = admin_identity();

$adminNavItems = [
    '/admin' => 'Dashboard',
    '/admin/pages' => 'Pages',
    '/admin/posts' => 'Posts',
    '/admin/comments' => 'Comments',
    '/admin/feed-sources' => 'Feeds',
    '/admin/site-identity' => 'Identity',
    '/admin/user-profiles' => 'Users',
    '/admin/platform-connections' => 'Connections',
    '/admin/exhibits' => 'Exhibits',
    '/admin/pieces' => 'Pieces',
    '/admin/categories' => 'Categories',
    '/admin/collections' => 'Collections',
    '/admin/platform-collections' => 'Platform Collections',
    '/admin/media' => 'Media',
    '/admin/trash' => 'Trash',
    '/admin/navigation' => 'Navigation',
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle ?? 'Admin — Augment Humankind', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/assets/styles.css">
    <link rel="stylesheet" href="/assets/admin.css">
    <?php if ($needsEditor ?? false): ?>
    <link rel="stylesheet" href="/assets/css/tiptap.css">
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
</head>
<body class="admin-body">
    <header class="admin-header">
        <div class="admin-brand">
            <span class="admin-kicker">Administration</span>
            <a href="/admin" class="admin-site-link">Augment Humankind</a>
            <?php if ($adminIdentity): ?>
                <span class="admin-kicker">Signed in as <?= htmlspecialchars($adminIdentity['display_name'], ENT_QUOTES, 'UTF-8') ?> via <?= htmlspecialchars(ucfirst($adminIdentity['provider']), ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif ?>
        </div>
        <nav class="admin-nav" aria-label="Admin navigation">
            <?php foreach ($adminNavItems as $href => $label): ?>
                <?php $isActive = $currentPath === $href; ?>
                <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" class="<?= $isActive ? 'active' : '' ?>"<?= $isActive ? ' aria-current="page"' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
            <?php endforeach ?>
            <a href="/admin/logout" class="admin-logout">Logout</a>
        </nav>
    </header>

    <main class="admin-main">
        <?= $content ?>
    </main>

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
            <input type="text" id="mp-alt-input" class="media-picker-url-input"
                   placeholder="e.g. A cityscape at night with red lanterns" maxlength="250" autocomplete="off">
        </div>

        <div class="media-picker-footer">
            <button type="button" class="admin-btn admin-btn-ghost media-picker-cancel-btn">Cancel</button>
            <button type="button" class="admin-btn media-picker-select-btn" disabled>Select Asset</button>
        </div>
    </dialog>

    <!-- Art Piece / Exhibit Picker Modal -->
    <dialog id="piece-picker-modal" aria-labelledby="piece-picker-title">
        <div class="media-picker-header">
            <h2 id="piece-picker-title">Insert Art Piece or Exhibit</h2>
            <button type="button" class="media-picker-close" aria-label="Close">&times;</button>
        </div>

        <nav class="media-picker-tabs" role="tablist">
            <button class="media-picker-tab active" role="tab" data-tab="pieces"
                    aria-selected="true" aria-controls="pp-panel-pieces">Pieces</button>
            <button class="media-picker-tab" role="tab" data-tab="exhibits"
                    aria-selected="false" aria-controls="pp-panel-exhibits">Exhibits</button>
        </nav>

        <div class="media-picker-panel" id="pp-panel-pieces" role="tabpanel">
            <div class="media-picker-grid piece-picker-grid"></div>
        </div>

        <div class="media-picker-panel" id="pp-panel-exhibits" role="tabpanel" hidden>
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
            <select id="ai-profile-picker-select" class="media-picker-select">
                <option value="">Loading&hellip;</option>
            </select>
        </div>

        <div class="media-picker-footer">
            <button type="button" class="admin-btn admin-btn-ghost ai-profile-picker-cancel-btn">Cancel</button>
            <button type="button" class="admin-btn ai-profile-picker-select-btn" disabled>Use Profile</button>
        </div>
    </dialog>

    <script src="/assets/js/main.js" defer></script>
    <?php if ($needsEditor ?? false): ?>
    <script type="module" src="/assets/js/tiptap-editor.js"></script>
    <?php endif ?>
</body>
</html>
