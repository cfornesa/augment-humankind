<?php

declare(strict_types=1);

$pageTitle = 'Site Identity';

ob_start();
$error = $_GET['error'] ?? null;
$tab = $_GET['tab'] ?? 'settings';
if (!in_array($tab, ['settings', 'design', 'assets', 'media'], true)) {
    $tab = 'settings';
}
?>
<div class="admin-container">
    <div class="admin-header-row">
        <h1>Site Identity</h1>
    </div>

    <?php if ($error): ?>
        <div class="form-status form-status-error" role="alert">
            <p><?= e($error) ?></p>
        </div>
    <?php endif; ?>

    <nav class="admin-tabs" aria-label="Site identity tabs">
        <a href="/admin/site-identity?tab=settings" class="admin-tab <?= $tab === 'settings' ? 'active' : '' ?>">Settings</a>
        <a href="/admin/site-identity?tab=design" class="admin-tab <?= $tab === 'design' ? 'active' : '' ?>">Design</a>
        <a href="/admin/site-identity?tab=assets" class="admin-tab <?= $tab === 'assets' ? 'active' : '' ?>">Assets</a>
        <a href="/admin/site-identity?tab=media" class="admin-tab <?= $tab === 'media' ? 'active' : '' ?>">Media Library</a>
    </nav>

    <?php if ($tab === 'settings'): ?>
        <form method="post" action="/admin/site-identity/settings" class="admin-form">
            <div class="field">
                <label for="site_title">Site Title</label>
                <input id="site_title" name="site_title" type="text" maxlength="255"
                       value="<?= e($settings['site_title'] ?? 'Augment Humankind') ?>">
            </div>
            <div class="field">
                <label for="hero_heading">Hero Heading</label>
                <input id="hero_heading" name="hero_heading" type="text" maxlength="255"
                       value="<?= e($settings['hero_heading'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="hero_subheading">Hero Subheading</label>
                <textarea id="hero_subheading" name="hero_subheading" rows="3"><?= e($settings['hero_subheading'] ?? '') ?></textarea>
            </div>
            <div class="field">
                <label for="about_heading">About Heading</label>
                <input id="about_heading" name="about_heading" type="text" maxlength="255"
                       value="<?= e($settings['about_heading'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="about_body">About Body</label>
                <textarea id="about_body" name="about_body" rows="4"><?= e($settings['about_body'] ?? '') ?></textarea>
            </div>
            <div class="field">
                <label for="copyright_line">Copyright Line</label>
                <input id="copyright_line" name="copyright_line" type="text" maxlength="255"
                       value="<?= e($settings['copyright_line'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="footer_credit">Footer Credit</label>
                <input id="footer_credit" name="footer_credit" type="text" maxlength="255"
                       value="<?= e($settings['footer_credit'] ?? '') ?>">
            </div>
            <div class="field-grid">
                <div class="field">
                    <label for="cta_label">CTA Label</label>
                    <input id="cta_label" name="cta_label" type="text" maxlength="255"
                           value="<?= e($settings['cta_label'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="cta_href">CTA URL</label>
                    <input id="cta_href" name="cta_href" type="text" maxlength="2048"
                           value="<?= e($settings['cta_href'] ?? '/') ?>">
                </div>
            </div>
            <div class="field">
                <label for="canonical_public_url">Canonical Public URL</label>
                <input id="canonical_public_url" name="canonical_public_url" type="url" maxlength="255"
                       value="<?= e($settings['canonical_public_url'] ?? '') ?>"
                       placeholder="https://augmenthumankind.com">
                <p class="admin-hint">Used for canonical tags, social cards, and outbound post links when publishing from local environments.</p>
            </div>
            <div class="form-actions">
                <button type="submit" class="admin-btn">Save Settings</button>
            </div>
        </form>
    <?php elseif ($tab === 'design'): ?>
        <form method="post" action="/admin/site-identity/settings" class="admin-form">
            <div class="field-grid">
                <div class="field">
                    <label for="design_logo_url">Logo (light mode)</label>
                    <div class="media-field-preview" id="design-logo-url-preview">
                        <?php if (!empty($settings['logo_url'])): ?>
                            <img src="<?= e($settings['logo_url']) ?>" alt="" style="max-height:60px;border:1px solid var(--line);">
                        <?php endif ?>
                    </div>
                    <input id="design_logo_url" name="logo_url" type="text" maxlength="2048" readonly
                           value="<?= e($settings['logo_url'] ?? '') ?>"
                           placeholder="No image selected">
                    <div class="media-field-actions">
                        <button type="button" class="picker-trigger"
                                data-picker-target="design_logo_url"
                                data-picker-preview="design-logo-url-preview">Choose Image</button>
                        <button type="button" class="admin-btn admin-btn-ghost admin-btn-sm"
                                data-clear-input="design_logo_url"
                                data-clear-preview="design-logo-url-preview">Clear</button>
                    </div>
                </div>
                <div class="field">
                    <label for="design_logo_dark_url">Logo (dark mode)</label>
                    <div class="media-field-preview" id="design-logo-dark-url-preview">
                        <?php if (!empty($settings['logo_dark_url'])): ?>
                            <img src="<?= e($settings['logo_dark_url']) ?>" alt="" style="max-height:60px;border:1px solid var(--line);">
                        <?php endif ?>
                    </div>
                    <input id="design_logo_dark_url" name="logo_dark_url" type="text" maxlength="2048" readonly
                           value="<?= e($settings['logo_dark_url'] ?? '') ?>"
                           placeholder="No image selected">
                    <div class="media-field-actions">
                        <button type="button" class="picker-trigger"
                                data-picker-target="design_logo_dark_url"
                                data-picker-preview="design-logo-dark-url-preview">Choose Image</button>
                        <button type="button" class="admin-btn admin-btn-ghost admin-btn-sm"
                                data-clear-input="design_logo_dark_url"
                                data-clear-preview="design-logo-dark-url-preview">Clear</button>
                    </div>
                </div>
            </div>
            <div class="field-grid">
                <div class="field">
                    <label for="design_logo_layout">Logo Layout</label>
                    <select id="design_logo_layout" name="logo_layout">
                        <option value="text_only" <?= ($settings['logo_layout'] ?? '') === 'text_only' ? 'selected' : '' ?>>Text Only</option>
                        <option value="image" <?= ($settings['logo_layout'] ?? '') === 'image' ? 'selected' : '' ?>>Image</option>
                        <option value="mixed" <?= ($settings['logo_layout'] ?? '') === 'mixed' ? 'selected' : '' ?>>Mixed</option>
                    </select>
                </div>
                <div class="field">
                    <label for="design_default_theme_mode">Default Theme</label>
                    <select id="design_default_theme_mode" name="default_theme_mode">
                        <option value="system" <?= ($settings['default_theme_mode'] ?? '') === 'system' ? 'selected' : '' ?>>System</option>
                        <option value="light" <?= ($settings['default_theme_mode'] ?? '') === 'light' ? 'selected' : '' ?>>Light</option>
                        <option value="dark" <?= ($settings['default_theme_mode'] ?? '') === 'dark' ? 'selected' : '' ?>>Dark</option>
                    </select>
                </div>
            </div>
            <div class="field-grid">
                <div class="field">
                    <label for="design_theme">Layout Theme</label>
                    <select id="design_theme" name="theme">
                        <option value="" <?= ($settings['theme'] ?? '') === '' ? 'selected' : '' ?>>(default)</option>
                        <?php foreach ($themeOptions as $val => $label): ?>
                            <option value="<?= e($val) ?>" <?= ($settings['theme'] ?? '') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="field">
                    <label for="design_palette">Color Palette</label>
                    <select id="design_palette" name="palette" data-palette-select>
                        <option value="" <?= ($settings['palette'] ?? '') === '' ? 'selected' : '' ?>>(custom — edit fields below)</option>
                        <option value="original" <?= ($settings['palette'] ?? '') === 'original' ? 'selected' : '' ?>>Original — Cream/navy/lime (site default)</option>
                        <option value="bauhaus" <?= ($settings['palette'] ?? '') === 'bauhaus' ? 'selected' : '' ?>>Bauhaus — Red, blue, yellow on black & white</option>
                        <option value="monochrome" <?= ($settings['palette'] ?? '') === 'monochrome' ? 'selected' : '' ?>>Monochrome — Pure greyscale</option>
                        <option value="newsprint" <?= ($settings['palette'] ?? '') === 'newsprint' ? 'selected' : '' ?>>Newsprint — Cream paper, black ink, red accent</option>
                        <option value="ocean" <?= ($settings['palette'] ?? '') === 'ocean' ? 'selected' : '' ?>>Ocean — Cool blues with teal accents</option>
                        <option value="forest" <?= ($settings['palette'] ?? '') === 'forest' ? 'selected' : '' ?>>Forest — Deep greens with earth tones</option>
                        <option value="sunset" <?= ($settings['palette'] ?? '') === 'sunset' ? 'selected' : '' ?>>Sunset — Warm orange and pink</option>
                        <option value="sepia" <?= ($settings['palette'] ?? '') === 'sepia' ? 'selected' : '' ?>>Sepia — Aged paper with brown ink</option>
                        <option value="high-contrast" <?= ($settings['palette'] ?? '') === 'high-contrast' ? 'selected' : '' ?>>High Contrast — Maximum contrast (WCAG)</option>
                        <option value="pastel" <?= ($settings['palette'] ?? '') === 'pastel' ? 'selected' : '' ?>>Pastel — Soft, low-saturation washes</option>
                    </select>
                </div>
            </div>
            <?php foreach ($colorGroups as $groupLabel => $cols): ?>
                <h3 style="margin: 1.5rem 0 0.75rem; font-size: 1rem; border-bottom: 1px solid var(--line); padding-bottom: 0.4rem;"><?= e($groupLabel) ?></h3>
                <div class="field-grid" style="grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));">
                    <?php foreach ($cols as $col => $label): ?>
                        <div class="field">
                            <label for="design_<?= e($col) ?>"><?= e($label) ?></label>
                            <div style="display:flex;gap:0.4rem;align-items:center;">
                                <input type="color" class="color-swatch" aria-label="Pick color for <?= e($label) ?>"
                                       data-hsl-target="design_<?= e($col) ?>" value="#808080"
                                       style="width:2.4rem;height:2.4rem;padding:0.15rem;border:2px solid var(--line);background:var(--paper);cursor:pointer;flex-shrink:0;">
                                <input id="design_<?= e($col) ?>" name="<?= e($col) ?>" type="text" maxlength="64"
                                       value="<?= e((string) ($settings[$col] ?? '')) ?>"
                                       placeholder="H S% L%"
                                       class="color-hsl-input"
                                       style="font-family:monospace;flex:1;min-width:0;">
                            </div>
                        </div>
                    <?php endforeach ?>
                </div>
            <?php endforeach ?>
            <!-- Live style preview -->
            <div style="margin-top:2rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem;flex-wrap:wrap;gap:0.5rem;">
                    <strong style="font-size:0.9rem;">Preview</strong>
                    <div style="display:flex;gap:0.4rem;">
                        <button type="button" id="preview-mode-light" class="admin-btn admin-btn-ghost admin-btn-sm">☀ Light</button>
                        <button type="button" id="preview-mode-dark" class="admin-btn admin-btn-ghost admin-btn-sm">☾ Dark</button>
                    </div>
                </div>
                <div id="style-preview" class="style-preview">
                    <div class="sp-header">
                        <span class="sp-brand">Site Name</span>
                        <span class="sp-nav-links">Coded Art · Feeds · Categories</span>
                    </div>
                    <div class="sp-body">
                        <h2 class="sp-heading">Your heading here</h2>
                        <p class="sp-text">Body copy — foreground text on background. This reflects the live color values.</p>
                        <p class="sp-muted">Muted text on muted background for secondary information.</p>
                        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:0.75rem;">
                            <span class="sp-btn sp-btn-primary">Primary</span>
                            <span class="sp-btn sp-btn-secondary">Secondary</span>
                            <span class="sp-btn sp-btn-accent">Accent</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-actions" style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:center;">
                <button type="submit" class="admin-btn">Save Design</button>
                <button type="button" id="reset-palette-btn" class="admin-btn admin-btn-ghost">Reset to palette defaults</button>
            </div>
        </form>
        <section class="nav-admin-board" aria-labelledby="admin-nav-order-heading" style="margin-top:2rem;">
            <div class="admin-section-head">
                <div>
                    <h2 class="admin-subheading" id="admin-nav-order-heading">Admin Navigation Order</h2>
                    <p class="admin-copy">Drag items into your preferred top-to-bottom order. The desktop sidebar, mobile hamburger menu, dashboard cards, and admin links in the public account menu will stay in sync.</p>
                </div>
                <span id="admin-nav-order-status" class="reorder-status" aria-live="polite"></span>
            </div>
            <table class="admin-table nav-admin-table">
                <thead>
                    <tr><th></th><th>Section</th><th>Purpose</th></tr>
                </thead>
                <tbody data-reorder-url="/admin/site-identity/navigation-order" data-reorder-status="admin-nav-order-status">
                    <?php foreach ($adminNavItems as $item): ?>
                        <tr data-id="<?= e($item['key']) ?>">
                            <td class="drag-handle" title="Drag to reorder">&#8597;</td>
                            <td><strong><?= e($item['label']) ?></strong></td>
                            <td><?= e($item['description']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php elseif ($tab === 'assets'): ?>
        <h2>Site Assets</h2>
        <form method="post" action="/admin/site-identity/assets" enctype="multipart/form-data" class="admin-form">
            <div class="field-grid">
                <div class="field">
                    <label for="asset_key">Asset Key</label>
                    <input id="asset_key" name="asset_key" type="text" required maxlength="191">
                </div>
                <div class="field">
                    <label for="asset_file">File</label>
                    <input id="asset_file" name="asset_file" type="file" required>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="admin-btn">Upload Asset</button>
            </div>
        </form>

        <?php if (empty($assets)): ?>
            <p>No site assets uploaded.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr><th>Key</th><th>Filename</th><th>Type</th><th>Size</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($assets as $asset): ?>
                        <tr>
                            <td><code><?= e($asset['asset_key']) ?></code></td>
                            <td><?= e($asset['filename'] ?? '') ?></td>
                            <td><?= e($asset['mime_type'] ?? '') ?></td>
                            <td><?= (int) ($asset['byte_size'] ?? 0) ?></td>
                            <td>
                                <form method="post" action="/admin/site-identity/assets/<?= (int) $asset['id'] ?>/delete" class="inline-form" onsubmit="return confirm('Delete this asset?')">
                                    <button type="submit" class="admin-link danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php else: ?>
        <h2>Media Library</h2>
        <?php if (empty($mediaAssets)): ?>
            <p>No media assets in the library.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr><th>Filename</th><th>Type</th><th>Alt</th><th>Title</th><th>Uploaded</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($mediaAssets as $ma): ?>
                        <tr>
                            <td><?= e($ma['filename'] ?? '') ?></td>
                            <td><?= e($ma['mime_type'] ?? '') ?></td>
                            <td><?= e($ma['alt_text'] ?? '') ?></td>
                            <td><?= e($ma['title'] ?? '') ?></td>
                            <td><?= e($ma['uploaded_at'] ?? '') ?></td>
                            <td>
                                <form method="post" action="/admin/site-identity/media/<?= (int) $ma['id'] ?>/delete" class="inline-form" onsubmit="return confirm('Move to trash?')">
                                    <button type="submit" class="admin-link danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
</div>
<script>
(function(){

// --- HSL <-> Hex conversion ---
function hue2rgb(p,q,t){if(t<0)t+=1;if(t>1)t-=1;if(t<1/6)return p+(q-p)*6*t;if(t<1/2)return q;if(t<2/3)return p+(q-p)*(2/3-t)*6;return p;}
function hslToHex(hslStr){
  var m=String(hslStr).match(/^([\d.]+)\s+([\d.]+)%?\s+([\d.]+)%?$/);
  if(!m)return null;
  var h=parseFloat(m[1])/360,s=parseFloat(m[2])/100,l=parseFloat(m[3])/100;
  var r,g,b;
  if(s===0){r=g=b=l;}else{var q=l<0.5?l*(1+s):l+s-l*s,p=2*l-q;r=hue2rgb(p,q,h+1/3);g=hue2rgb(p,q,h);b=hue2rgb(p,q,h-1/3);}
  return '#'+[r,g,b].map(function(x){return Math.round(x*255).toString(16).padStart(2,'0');}).join('');
}
function hexToHsl(hex){
  var r=parseInt(hex.slice(1,3),16)/255,g=parseInt(hex.slice(3,5),16)/255,b=parseInt(hex.slice(5,7),16)/255;
  var max=Math.max(r,g,b),min=Math.min(r,g,b),h,s,l=(max+min)/2;
  if(max===min){h=s=0;}else{var d=max-min;s=l>0.5?d/(2-max-min):d/(max+min);switch(max){case r:h=((g-b)/d+(g<b?6:0))/6;break;case g:h=((b-r)/d+2)/6;break;default:h=((r-g)/d+4)/6;}}
  return Math.round(h*360)+' '+Math.round(s*100)+'% '+Math.round(l*100)+'%';
}

// --- Palette data (from site-themes.ts) ---
var PALETTES = {
  original:{
    color_background:'40 49% 94%',color_foreground:'201 56% 19%',
    color_muted:'37 50% 88%',color_muted_foreground:'197 42% 32%',
    color_primary:'88 60% 53%',color_primary_foreground:'0 0% 100%',
    color_secondary:'189 51% 57%',color_secondary_foreground:'0 0% 100%',
    color_accent:'33 90% 60%',color_accent_foreground:'0 0% 0%',
    color_destructive:'0 72% 51%',color_destructive_foreground:'0 0% 100%',
    color_background_dark:'206 55% 11%',color_foreground_dark:'203 36% 90%',
    color_muted_dark:'206 45% 16%',color_muted_foreground_dark:'203 37% 65%',
    color_primary_dark:'88 60% 53%',color_primary_foreground_dark:'0 0% 0%',
    color_secondary_dark:'189 51% 57%',color_secondary_foreground_dark:'0 0% 0%',
    color_accent_dark:'33 90% 60%',color_accent_foreground_dark:'0 0% 0%',
    color_destructive_dark:'0 72% 51%',color_destructive_foreground_dark:'0 0% 100%'
  },
  bauhaus:{
    color_background:'234 100% 87%',color_foreground:'0 0% 0%',
    color_muted:'0 0% 90%',color_muted_foreground:'0 0% 20%',
    color_primary:'240 100% 35%',color_primary_foreground:'0 0% 100%',
    color_secondary:'280 100% 30%',color_secondary_foreground:'0 0% 100%',
    color_accent:'50 100% 50%',color_accent_foreground:'0 0% 0%',
    color_destructive:'0 100% 40%',color_destructive_foreground:'0 0% 100%',
    color_background_dark:'0 0% 5%',color_foreground_dark:'0 0% 95%',
    color_muted_dark:'0 0% 15%',color_muted_foreground_dark:'0 0% 80%',
    color_primary_dark:'240 100% 65%',color_primary_foreground_dark:'0 0% 0%',
    color_secondary_dark:'280 100% 70%',color_secondary_foreground_dark:'0 0% 0%',
    color_accent_dark:'50 100% 50%',color_accent_foreground_dark:'0 0% 0%',
    color_destructive_dark:'0 100% 55%',color_destructive_foreground_dark:'0 0% 100%'
  },
  monochrome:{
    color_background:'0 0% 98%',color_foreground:'0 0% 5%',
    color_muted:'0 0% 92%',color_muted_foreground:'0 0% 35%',
    color_primary:'0 0% 10%',color_primary_foreground:'0 0% 100%',
    color_secondary:'0 0% 30%',color_secondary_foreground:'0 0% 100%',
    color_accent:'0 0% 50%',color_accent_foreground:'0 0% 100%',
    color_destructive:'0 60% 45%',color_destructive_foreground:'0 0% 100%',
    color_background_dark:'0 0% 8%',color_foreground_dark:'0 0% 92%',
    color_muted_dark:'0 0% 15%',color_muted_foreground_dark:'0 0% 65%',
    color_primary_dark:'0 0% 90%',color_primary_foreground_dark:'0 0% 8%',
    color_secondary_dark:'0 0% 70%',color_secondary_foreground_dark:'0 0% 8%',
    color_accent_dark:'0 0% 50%',color_accent_foreground_dark:'0 0% 100%',
    color_destructive_dark:'0 60% 55%',color_destructive_foreground_dark:'0 0% 100%'
  },
  newsprint:{
    color_background:'43 35% 92%',color_foreground:'0 0% 10%',
    color_muted:'43 25% 85%',color_muted_foreground:'0 0% 35%',
    color_primary:'0 75% 40%',color_primary_foreground:'0 0% 100%',
    color_secondary:'0 0% 20%',color_secondary_foreground:'0 0% 100%',
    color_accent:'30 80% 45%',color_accent_foreground:'0 0% 100%',
    color_destructive:'0 72% 45%',color_destructive_foreground:'0 0% 100%',
    color_background_dark:'30 15% 12%',color_foreground_dark:'43 25% 88%',
    color_muted_dark:'30 10% 18%',color_muted_foreground_dark:'43 15% 65%',
    color_primary_dark:'0 65% 55%',color_primary_foreground_dark:'0 0% 100%',
    color_secondary_dark:'0 0% 70%',color_secondary_foreground_dark:'0 0% 10%',
    color_accent_dark:'30 70% 55%',color_accent_foreground_dark:'0 0% 0%',
    color_destructive_dark:'0 65% 55%',color_destructive_foreground_dark:'0 0% 100%'
  },
  ocean:{
    color_background:'200 30% 97%',color_foreground:'210 60% 15%',
    color_muted:'200 25% 90%',color_muted_foreground:'210 40% 35%',
    color_primary:'199 89% 40%',color_primary_foreground:'0 0% 100%',
    color_secondary:'175 60% 40%',color_secondary_foreground:'0 0% 100%',
    color_accent:'220 80% 55%',color_accent_foreground:'0 0% 100%',
    color_destructive:'0 72% 51%',color_destructive_foreground:'0 0% 100%',
    color_background_dark:'216 45% 10%',color_foreground_dark:'200 30% 92%',
    color_muted_dark:'216 35% 16%',color_muted_foreground_dark:'200 25% 65%',
    color_primary_dark:'199 80% 55%',color_primary_foreground_dark:'0 0% 0%',
    color_secondary_dark:'175 55% 50%',color_secondary_foreground_dark:'0 0% 0%',
    color_accent_dark:'220 70% 65%',color_accent_foreground_dark:'0 0% 0%',
    color_destructive_dark:'0 65% 55%',color_destructive_foreground_dark:'0 0% 100%'
  },
  forest:{
    color_background:'90 20% 96%',color_foreground:'140 40% 10%',
    color_muted:'90 15% 88%',color_muted_foreground:'140 30% 30%',
    color_primary:'130 45% 35%',color_primary_foreground:'0 0% 100%',
    color_secondary:'30 55% 40%',color_secondary_foreground:'0 0% 100%',
    color_accent:'80 50% 45%',color_accent_foreground:'0 0% 100%',
    color_destructive:'0 65% 45%',color_destructive_foreground:'0 0% 100%',
    color_background_dark:'140 30% 8%',color_foreground_dark:'90 20% 90%',
    color_muted_dark:'140 20% 14%',color_muted_foreground_dark:'90 15% 65%',
    color_primary_dark:'130 40% 50%',color_primary_foreground_dark:'0 0% 0%',
    color_secondary_dark:'30 50% 55%',color_secondary_foreground_dark:'0 0% 0%',
    color_accent_dark:'80 45% 55%',color_accent_foreground_dark:'0 0% 0%',
    color_destructive_dark:'0 60% 55%',color_destructive_foreground_dark:'0 0% 100%'
  },
  sunset:{
    color_background:'20 60% 97%',color_foreground:'330 40% 10%',
    color_muted:'20 40% 90%',color_muted_foreground:'330 25% 35%',
    color_primary:'15 90% 55%',color_primary_foreground:'0 0% 100%',
    color_secondary:'340 75% 55%',color_secondary_foreground:'0 0% 100%',
    color_accent:'45 95% 55%',color_accent_foreground:'0 0% 0%',
    color_destructive:'0 72% 51%',color_destructive_foreground:'0 0% 100%',
    color_background_dark:'335 40% 8%',color_foreground_dark:'20 50% 92%',
    color_muted_dark:'335 30% 14%',color_muted_foreground_dark:'20 30% 65%',
    color_primary_dark:'15 80% 60%',color_primary_foreground_dark:'0 0% 0%',
    color_secondary_dark:'340 65% 65%',color_secondary_foreground_dark:'0 0% 0%',
    color_accent_dark:'45 90% 60%',color_accent_foreground_dark:'0 0% 0%',
    color_destructive_dark:'0 65% 55%',color_destructive_foreground_dark:'0 0% 100%'
  },
  sepia:{
    color_background:'35 40% 93%',color_foreground:'25 40% 15%',
    color_muted:'35 30% 85%',color_muted_foreground:'25 30% 35%',
    color_primary:'20 55% 35%',color_primary_foreground:'35 40% 93%',
    color_secondary:'35 45% 45%',color_secondary_foreground:'0 0% 100%',
    color_accent:'15 70% 40%',color_accent_foreground:'0 0% 100%',
    color_destructive:'0 65% 40%',color_destructive_foreground:'0 0% 100%',
    color_background_dark:'25 35% 10%',color_foreground_dark:'35 30% 88%',
    color_muted_dark:'25 25% 16%',color_muted_foreground_dark:'35 20% 65%',
    color_primary_dark:'20 50% 55%',color_primary_foreground_dark:'0 0% 0%',
    color_secondary_dark:'35 40% 55%',color_secondary_foreground_dark:'0 0% 0%',
    color_accent_dark:'15 65% 55%',color_accent_foreground_dark:'0 0% 0%',
    color_destructive_dark:'0 60% 55%',color_destructive_foreground_dark:'0 0% 100%'
  },
  'high-contrast':{
    color_background:'0 0% 100%',color_foreground:'0 0% 0%',
    color_muted:'0 0% 94%',color_muted_foreground:'0 0% 20%',
    color_primary:'220 100% 30%',color_primary_foreground:'0 0% 100%',
    color_secondary:'0 0% 20%',color_secondary_foreground:'0 0% 100%',
    color_accent:'40 100% 35%',color_accent_foreground:'0 0% 0%',
    color_destructive:'0 100% 35%',color_destructive_foreground:'0 0% 100%',
    color_background_dark:'0 0% 0%',color_foreground_dark:'0 0% 100%',
    color_muted_dark:'0 0% 10%',color_muted_foreground_dark:'0 0% 80%',
    color_primary_dark:'220 100% 70%',color_primary_foreground_dark:'0 0% 0%',
    color_secondary_dark:'0 0% 80%',color_secondary_foreground_dark:'0 0% 0%',
    color_accent_dark:'40 100% 60%',color_accent_foreground_dark:'0 0% 0%',
    color_destructive_dark:'0 100% 60%',color_destructive_foreground_dark:'0 0% 0%'
  },
  pastel:{
    color_background:'300 30% 98%',color_foreground:'270 30% 20%',
    color_muted:'300 20% 92%',color_muted_foreground:'270 20% 45%',
    color_primary:'260 60% 70%',color_primary_foreground:'0 0% 100%',
    color_secondary:'180 50% 65%',color_secondary_foreground:'0 0% 100%',
    color_accent:'340 65% 70%',color_accent_foreground:'0 0% 100%',
    color_destructive:'0 65% 65%',color_destructive_foreground:'0 0% 100%',
    color_background_dark:'270 30% 12%',color_foreground_dark:'300 20% 92%',
    color_muted_dark:'270 20% 18%',color_muted_foreground_dark:'300 15% 70%',
    color_primary_dark:'260 55% 75%',color_primary_foreground_dark:'0 0% 0%',
    color_secondary_dark:'180 45% 70%',color_secondary_foreground_dark:'0 0% 0%',
    color_accent_dark:'340 60% 75%',color_accent_foreground_dark:'0 0% 0%',
    color_destructive_dark:'0 60% 65%',color_destructive_foreground_dark:'0 0% 100%'
  }
};

function syncSwatch(swatch){
  var field=document.getElementById(swatch.dataset.hslTarget);
  if(field&&field.value){var hex=hslToHex(field.value);if(hex)swatch.value=hex;}
}

// Preview sync
var PREVIEW_MAP = {
  color_background:'--sp-paper',color_foreground:'--sp-ink',
  color_muted:'--sp-paper-deep',color_muted_foreground:'--sp-ink-soft',
  color_primary:'--sp-primary',color_primary_foreground:'--sp-primary-fg',
  color_secondary:'--sp-secondary',color_secondary_foreground:'--sp-secondary-fg',
  color_accent:'--sp-accent',color_accent_foreground:'--sp-accent-fg'
};
var PREVIEW_MAP_DARK = {
  color_background_dark:'--sp-paper',color_foreground_dark:'--sp-ink'
};
var previewMode='light';
function syncPreview(){
  var p=document.getElementById('style-preview');
  if(!p)return;
  // Apply light vars first
  Object.keys(PREVIEW_MAP).forEach(function(col){
    var v=document.getElementById('design_'+col)||document.getElementById(col);
    if(v&&v.value) p.style.setProperty(PREVIEW_MAP[col],'hsl('+v.value+')');
  });
  // Overlay dark vars if previewing dark mode
  if(previewMode==='dark'){
    Object.keys(PREVIEW_MAP_DARK).forEach(function(col){
      var v=document.getElementById('design_'+col)||document.getElementById(col);
      if(v&&v.value) p.style.setProperty(PREVIEW_MAP_DARK[col],'hsl('+v.value+')');
    });
  }
}

function fillPalette(id){
  var p=PALETTES[id];
  if(!p)return;
  Object.keys(p).forEach(function(col){
    [col,'design_'+col].forEach(function(targetId){
      var field=document.getElementById(targetId);
      var swatch=document.querySelector('.color-swatch[data-hsl-target="'+targetId+'"]');
      if(field)field.value=p[col];
      if(swatch){var hex=hslToHex(p[col]);if(hex)swatch.value=hex;}
    });
  });
  syncPreview();
}

// Init swatches from existing field values
document.querySelectorAll('.color-swatch').forEach(syncSwatch);

// Swatch → HSL text field
document.querySelectorAll('.color-swatch').forEach(function(swatch){
  swatch.addEventListener('input',function(){
    var field=document.getElementById(swatch.dataset.hslTarget);
    if(field)field.value=hexToHsl(swatch.value);
    syncPreview();
  });
});

// HSL text field → swatch
document.querySelectorAll('.color-hsl-input').forEach(function(input){
  input.addEventListener('input',function(){
    var swatch=document.querySelector('.color-swatch[data-hsl-target="'+input.id+'"]');
    if(swatch&&input.value){var hex=hslToHex(input.value);if(hex)swatch.value=hex;}
    syncPreview();
  });
});

// Palette dropdown → fill all fields
var paletteSelect=document.querySelector('[data-palette-select]');
if(paletteSelect){
  paletteSelect.addEventListener('change',function(){
    if(this.value)fillPalette(this.value);
  });
}

// Reset to palette defaults
var resetBtn=document.getElementById('reset-palette-btn');
if(resetBtn){
  resetBtn.addEventListener('click',function(){
    var pal=paletteSelect?paletteSelect.value:'';
    if(pal)fillPalette(pal);
  });
}

// Preview mode toggle
var lightBtn=document.getElementById('preview-mode-light');
var darkBtn=document.getElementById('preview-mode-dark');
if(lightBtn)lightBtn.addEventListener('click',function(){ previewMode='light'; syncPreview(); });
if(darkBtn)darkBtn.addEventListener('click',function(){ previewMode='dark'; syncPreview(); });

syncPreview();

// Media picker clear buttons
document.querySelectorAll('[data-clear-input]').forEach(function(btn){
  btn.addEventListener('click',function(){
    var inp=document.getElementById(btn.dataset.clearInput);
    if(inp)inp.value='';
    var prev=document.getElementById(btn.dataset.clearPreview);
    if(prev)prev.innerHTML='';
  });
});

})();
</script>
<?php
$content = ob_get_clean();

require dirname(__DIR__) . '/layout.php';
