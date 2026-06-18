<?php

declare(strict_types=1);

require dirname(__DIR__) . '/partials/header.php';
?>
<div class="managed-section" style="max-width: 680px; margin: 0 auto; padding: 2rem 1.5rem;">

    <h1 style="margin: 0 0 0.25rem; font-size: 1.6rem;">Profile Settings</h1>
    <p style="margin: 0 0 2rem; color: var(--ink-soft);">
        <a href="/user/<?= e($user['username'] ?? '') ?>" style="color: var(--ink-soft);">← View your profile</a>
    </p>

    <?php if (isset($_GET['success'])): ?>
        <div role="status" style="margin-bottom: 1.5rem; padding: 0.75rem 1rem; border: 2px solid var(--line); background: #e6f4e6; color: #1a4a1a;">
            <?php
            $successMsg = match($_GET['success']) {
                'profile' => 'Profile updated.',
                'photo'   => 'Profile photo updated.',
                default   => 'Style settings saved.',
            };
            echo e($successMsg);
            ?>
        </div>
    <?php endif ?>
    <?php if (isset($_GET['error'])): ?>
        <div role="alert" style="margin-bottom: 1.5rem; padding: 0.75rem 1rem; border: 2px solid var(--line); background: #fff1cd; color: #5a3a00;">
            <?= e((string) $_GET['error']) ?>
        </div>
    <?php endif ?>

    <section aria-labelledby="photo-section" style="margin-bottom: 3rem; padding-bottom: 2rem; border-bottom: 3px solid var(--line);">
        <h2 id="photo-section" style="margin: 0 0 1.25rem; font-size: 1.2rem;">Profile Photo</h2>
        <div style="display: flex; align-items: center; gap: 1.5rem; margin-bottom: 1.25rem;">
            <?php if (!empty($user['image'])): ?>
                <img src="<?= e((string) $user['image']) ?>" alt="Your current profile photo"
                     style="width: 72px; height: 72px; border-radius: 50%; border: 3px solid var(--line); object-fit: cover; flex-shrink: 0;">
            <?php else: ?>
                <div aria-hidden="true"
                     style="width: 72px; height: 72px; border-radius: 50%; border: 3px solid var(--line); background: var(--paper-deep); flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; color: var(--ink-soft);">
                    <?= e(mb_strtoupper(mb_substr((string) ($user['name'] ?? $user['username'] ?? '?'), 0, 1))) ?>
                </div>
            <?php endif ?>
            <div style="flex: 1; min-width: 0;">
                <p style="margin: 0 0 0.5rem; font-size: 0.9rem; color: var(--ink-soft);">JPEG, PNG, GIF, WebP, or AVIF. Max 5 MB.</p>
                <form method="POST" action="/user/settings/photo" enctype="multipart/form-data"
                      style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                    <input type="file" name="profile_photo" id="profile_photo" accept="image/*" required
                           style="font-family: inherit; font-size: 0.9rem;">
                    <button type="submit"
                            style="padding: 0.55rem 1.1rem; border: 3px solid var(--line); box-shadow: 3px 3px 0 var(--line); background: var(--ink); color: var(--paper); font-weight: 700; font-size: 0.9rem; cursor: pointer; font-family: inherit; white-space: nowrap;">
                        Upload
                    </button>
                </form>
            </div>
        </div>
    </section>

    <section aria-labelledby="profile-section" style="margin-bottom: 3rem; padding-bottom: 2rem; border-bottom: 3px solid var(--line);">
        <h2 id="profile-section" style="margin: 0 0 1.25rem; font-size: 1.2rem;">Profile</h2>
        <form method="POST" action="/user/settings/profile">
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <div>
                    <label for="name" style="display: block; font-weight: 700; margin-bottom: 0.3rem;">Display name</label>
                    <input id="name" name="name" type="text" maxlength="255" value="<?= e((string) ($user['name'] ?? '')) ?>"
                           style="width: 100%; padding: 0.6rem 0.75rem; border: 2px solid var(--line); background: var(--paper); color: var(--ink); font-family: inherit; font-size: 1rem;">
                </div>
                <div>
                    <label for="bio" style="display: block; font-weight: 700; margin-bottom: 0.3rem;">Bio <span style="font-weight: 400; color: var(--ink-soft);">(max 500 chars)</span></label>
                    <textarea id="bio" name="bio" rows="4" maxlength="500"
                              style="width: 100%; padding: 0.6rem 0.75rem; border: 2px solid var(--line); background: var(--paper); color: var(--ink); font-family: inherit; font-size: 1rem; resize: vertical;"><?= e((string) ($user['bio'] ?? '')) ?></textarea>
                </div>
                <div>
                    <label for="website" style="display: block; font-weight: 700; margin-bottom: 0.3rem;">Website</label>
                    <input id="website" name="website" type="url" maxlength="2048" value="<?= e((string) ($user['website'] ?? '')) ?>"
                           style="width: 100%; padding: 0.6rem 0.75rem; border: 2px solid var(--line); background: var(--paper); color: var(--ink); font-family: inherit; font-size: 1rem;"
                           placeholder="https://yoursite.com">
                </div>
                <div>
                    <button type="submit"
                            style="padding: 0.7rem 1.5rem; border: 3px solid var(--line); box-shadow: 4px 4px 0 var(--line); background: var(--ink); color: var(--paper); font-weight: 700; font-size: 1rem; cursor: pointer; font-family: inherit;">
                        Save profile
                    </button>
                </div>
            </div>
        </form>
    </section>

    <section aria-labelledby="style-section">
        <h2 id="style-section" style="margin: 0 0 0.5rem; font-size: 1.2rem;">Profile Style</h2>
        <p style="margin: 0 0 1.5rem; font-size: 0.9rem; color: var(--ink-soft);">These colors only apply to your public profile page. Pick a palette to auto-fill all fields, or click any swatch to select a color visually.</p>

        <form method="POST" action="/user/settings/style">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <label for="user_theme" style="display: block; font-weight: 700; margin-bottom: 0.3rem; font-size: 0.9rem;">Layout Theme</label>
                    <select id="user_theme" name="theme"
                            style="width: 100%; padding: 0.6rem 0.75rem; border: 2px solid var(--line); background: var(--paper); color: var(--ink); font-family: inherit; font-size: 0.95rem;">
                        <option value="">(default)</option>
                        <option value="bauhaus"<?= ($user['theme']??'')==='bauhaus'?' selected':'' ?>>Bauhaus — Heavy borders, hard shadows</option>
                        <option value="traditional"<?= ($user['theme']??'')==='traditional'?' selected':'' ?>>Traditional — Classic editorial</option>
                        <option value="minimalist"<?= ($user['theme']??'')==='minimalist'?' selected':'' ?>>Minimalist — Clean, no decoration</option>
                        <option value="academic"<?= ($user['theme']??'')==='academic'?' selected':'' ?>>Academic — Structured, formal</option>
                        <option value="airy"<?= ($user['theme']??'')==='airy'?' selected':'' ?>>Airy — Open whitespace</option>
                        <option value="nature"<?= ($user['theme']??'')==='nature'?' selected':'' ?>>Nature — Organic shapes</option>
                        <option value="comfort"<?= ($user['theme']??'')==='comfort'?' selected':'' ?>>Comfort — Soft, rounded</option>
                        <option value="audacious"<?= ($user['theme']??'')==='audacious'?' selected':'' ?>>Audacious — Bold, expressive</option>
                        <option value="artistic"<?= ($user['theme']??'')==='artistic'?' selected':'' ?>>Artistic — Creative, layered</option>
                    </select>
                </div>
                <div>
                    <label for="user_palette" style="display: block; font-weight: 700; margin-bottom: 0.3rem; font-size: 0.9rem;">Color Palette</label>
                    <select id="user_palette" name="palette" data-palette-select
                            style="width: 100%; padding: 0.6rem 0.75rem; border: 2px solid var(--line); background: var(--paper); color: var(--ink); font-family: inherit; font-size: 0.95rem;">
                        <option value="">(custom)</option>
                        <option value="original"<?= ($user['palette']??'')==='original'?' selected':'' ?>>Original — Cream/navy/lime</option>
                        <option value="bauhaus"<?= ($user['palette']??'')==='bauhaus'?' selected':'' ?>>Bauhaus — Red/blue/yellow tricolor</option>
                        <option value="monochrome"<?= ($user['palette']??'')==='monochrome'?' selected':'' ?>>Monochrome — Pure greyscale</option>
                        <option value="newsprint"<?= ($user['palette']??'')==='newsprint'?' selected':'' ?>>Newsprint — Cream paper, red accent</option>
                        <option value="ocean"<?= ($user['palette']??'')==='ocean'?' selected':'' ?>>Ocean — Cool blues and teal</option>
                        <option value="forest"<?= ($user['palette']??'')==='forest'?' selected':'' ?>>Forest — Deep greens, earth tones</option>
                        <option value="sunset"<?= ($user['palette']??'')==='sunset'?' selected':'' ?>>Sunset — Warm orange and pink</option>
                        <option value="sepia"<?= ($user['palette']??'')==='sepia'?' selected':'' ?>>Sepia — Aged paper, brown ink</option>
                        <option value="high-contrast"<?= ($user['palette']??'')==='high-contrast'?' selected':'' ?>>High Contrast — Maximum WCAG contrast</option>
                        <option value="pastel"<?= ($user['palette']??'')==='pastel'?' selected':'' ?>>Pastel — Soft, low-saturation washes</option>
                    </select>
                </div>
            </div>

            <?php
            $userColorGroups = [
                'Light Mode' => [
                    'color_background'             => 'Background',
                    'color_foreground'             => 'Foreground / ink',
                    'color_muted'                  => 'Muted background',
                    'color_muted_foreground'       => 'Muted foreground',
                    'color_primary'                => 'Primary',
                    'color_primary_foreground'     => 'Primary foreground',
                    'color_secondary'              => 'Secondary',
                    'color_secondary_foreground'   => 'Secondary foreground',
                    'color_accent'                 => 'Accent',
                    'color_accent_foreground'      => 'Accent foreground',
                    'color_destructive'            => 'Destructive',
                    'color_destructive_foreground' => 'Destructive foreground',
                ],
                'Dark Mode' => [
                    'color_background_dark'        => 'Background',
                    'color_foreground_dark'        => 'Foreground / ink',
                ],
            ];
            foreach ($userColorGroups as $groupLabel => $cols): ?>
            <h3 style="margin: 1.25rem 0 0.6rem; font-size: 0.95rem; font-weight: 700; border-bottom: 1px solid var(--line); padding-bottom: 0.3rem;"><?= e($groupLabel) ?></h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 0.75rem; margin-bottom: 0.5rem;">
                <?php foreach ($cols as $col => $label): ?>
                <div>
                    <label for="<?= e($col) ?>" style="display: block; font-weight: 700; margin-bottom: 0.25rem; font-size: 0.85rem;"><?= e($label) ?></label>
                    <div style="display:flex;gap:0.4rem;align-items:center;">
                        <input type="color" class="color-swatch" aria-label="Pick color for <?= e($label) ?>"
                               data-hsl-target="<?= e($col) ?>" value="#808080"
                               style="width:2.2rem;height:2.2rem;padding:0.1rem;border:2px solid var(--line);background:var(--paper);cursor:pointer;flex-shrink:0;">
                        <input id="<?= e($col) ?>" name="<?= e($col) ?>" type="text" maxlength="64"
                               value="<?= e((string) ($user[$col] ?? '')) ?>"
                               placeholder="H S% L%"
                               class="color-hsl-input"
                               style="flex:1;min-width:0;padding:0.45rem 0.6rem;border:2px solid var(--line);background:var(--paper);color:var(--ink);font-family:monospace;font-size:0.85rem;">
                    </div>
                </div>
                <?php endforeach ?>
            </div>
            <?php endforeach ?>

            <!-- Live style preview -->
            <div style="margin-top: 2rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem;flex-wrap:wrap;gap:0.5rem;">
                    <strong style="font-size:0.9rem;">Preview</strong>
                    <div style="display:flex;gap:0.4rem;">
                        <button type="button" id="preview-mode-light"
                                style="padding:0.3rem 0.75rem;border:2px solid var(--line);background:var(--paper);color:var(--ink);font-size:0.8rem;cursor:pointer;font-family:inherit;">
                            ☀ Light
                        </button>
                        <button type="button" id="preview-mode-dark"
                                style="padding:0.3rem 0.75rem;border:2px solid var(--line);background:var(--paper);color:var(--ink);font-size:0.8rem;cursor:pointer;font-family:inherit;">
                            ☾ Dark
                        </button>
                    </div>
                </div>
                <div id="style-preview" class="style-preview">
                    <div class="sp-header">
                        <span class="sp-brand">Augment Humankind</span>
                        <span class="sp-nav-links">Coded Art · Feeds · Categories</span>
                    </div>
                    <div class="sp-body">
                        <h2 class="sp-heading">Your profile heading</h2>
                        <p class="sp-text">Body text — foreground on background. Explore the visual rhythm of your chosen palette.</p>
                        <p class="sp-muted">Muted text on muted background for secondary information.</p>
                        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:0.75rem;">
                            <span class="sp-btn sp-btn-primary">Primary</span>
                            <span class="sp-btn sp-btn-secondary">Secondary</span>
                            <span class="sp-btn sp-btn-accent">Accent</span>
                        </div>
                    </div>
                </div>
            </div>

            <div style="margin-top: 1.5rem; display:flex; gap:0.75rem; flex-wrap:wrap; align-items:center;">
                <button type="submit"
                        style="padding: 0.7rem 1.5rem; border: 3px solid var(--line); box-shadow: 4px 4px 0 var(--line); background: var(--ink); color: var(--paper); font-weight: 700; font-size: 1rem; cursor: pointer; font-family: inherit;">
                    Save style
                </button>
                <button type="button" id="reset-palette-btn"
                        style="padding: 0.6rem 1.25rem; border: 2px solid var(--line); background: var(--paper); color: var(--ink); font-weight: 700; font-size: 0.95rem; cursor: pointer; font-family: inherit;">
                    Reset to palette defaults
                </button>
            </div>
        </form>
    </section>

    <section style="margin-top: 3rem; padding-top: 2rem; border-top: 2px solid var(--line);">
        <h2 style="margin: 0 0 0.75rem; font-size: 1rem; color: var(--ink-soft);">Account</h2>
        <a href="/user/logout"
           style="padding: 0.6rem 1.25rem; border: 2px solid var(--line); display: inline-block; text-decoration: none; color: var(--ink); font-weight: 700; background: var(--paper);"
           onclick="return confirm('Sign out?')">
            Sign out
        </a>
    </section>

</div>
<script>
(function(){

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

var PALETTES = {
  original:{color_background:'40 49% 94%',color_foreground:'201 56% 19%',color_muted:'37 50% 88%',color_muted_foreground:'197 42% 32%',color_primary:'88 60% 53%',color_primary_foreground:'0 0% 100%',color_secondary:'189 51% 57%',color_secondary_foreground:'0 0% 100%',color_accent:'33 90% 60%',color_accent_foreground:'0 0% 0%',color_destructive:'0 72% 51%',color_destructive_foreground:'0 0% 100%',color_background_dark:'206 55% 11%',color_foreground_dark:'203 36% 90%'},
  bauhaus:{color_background:'234 100% 87%',color_foreground:'0 0% 0%',color_muted:'0 0% 90%',color_muted_foreground:'0 0% 20%',color_primary:'240 100% 35%',color_primary_foreground:'0 0% 100%',color_secondary:'280 100% 30%',color_secondary_foreground:'0 0% 100%',color_accent:'50 100% 50%',color_accent_foreground:'0 0% 0%',color_destructive:'0 100% 40%',color_destructive_foreground:'0 0% 100%',color_background_dark:'0 0% 5%',color_foreground_dark:'0 0% 95%'},
  monochrome:{color_background:'0 0% 98%',color_foreground:'0 0% 5%',color_muted:'0 0% 92%',color_muted_foreground:'0 0% 35%',color_primary:'0 0% 10%',color_primary_foreground:'0 0% 100%',color_secondary:'0 0% 30%',color_secondary_foreground:'0 0% 100%',color_accent:'0 0% 50%',color_accent_foreground:'0 0% 100%',color_destructive:'0 60% 45%',color_destructive_foreground:'0 0% 100%',color_background_dark:'0 0% 8%',color_foreground_dark:'0 0% 92%'},
  newsprint:{color_background:'43 35% 92%',color_foreground:'0 0% 10%',color_muted:'43 25% 85%',color_muted_foreground:'0 0% 35%',color_primary:'0 75% 40%',color_primary_foreground:'0 0% 100%',color_secondary:'0 0% 20%',color_secondary_foreground:'0 0% 100%',color_accent:'30 80% 45%',color_accent_foreground:'0 0% 100%',color_destructive:'0 72% 45%',color_destructive_foreground:'0 0% 100%',color_background_dark:'30 15% 12%',color_foreground_dark:'43 25% 88%'},
  ocean:{color_background:'200 30% 97%',color_foreground:'210 60% 15%',color_muted:'200 25% 90%',color_muted_foreground:'210 40% 35%',color_primary:'199 89% 40%',color_primary_foreground:'0 0% 100%',color_secondary:'175 60% 40%',color_secondary_foreground:'0 0% 100%',color_accent:'220 80% 55%',color_accent_foreground:'0 0% 100%',color_destructive:'0 72% 51%',color_destructive_foreground:'0 0% 100%',color_background_dark:'216 45% 10%',color_foreground_dark:'200 30% 92%'},
  forest:{color_background:'90 20% 96%',color_foreground:'140 40% 10%',color_muted:'90 15% 88%',color_muted_foreground:'140 30% 30%',color_primary:'130 45% 35%',color_primary_foreground:'0 0% 100%',color_secondary:'30 55% 40%',color_secondary_foreground:'0 0% 100%',color_accent:'80 50% 45%',color_accent_foreground:'0 0% 100%',color_destructive:'0 65% 45%',color_destructive_foreground:'0 0% 100%',color_background_dark:'140 30% 8%',color_foreground_dark:'90 20% 90%'},
  sunset:{color_background:'20 60% 97%',color_foreground:'330 40% 10%',color_muted:'20 40% 90%',color_muted_foreground:'330 25% 35%',color_primary:'15 90% 55%',color_primary_foreground:'0 0% 100%',color_secondary:'340 75% 55%',color_secondary_foreground:'0 0% 100%',color_accent:'45 95% 55%',color_accent_foreground:'0 0% 0%',color_destructive:'0 72% 51%',color_destructive_foreground:'0 0% 100%',color_background_dark:'335 40% 8%',color_foreground_dark:'20 50% 92%'},
  sepia:{color_background:'35 40% 93%',color_foreground:'25 40% 15%',color_muted:'35 30% 85%',color_muted_foreground:'25 30% 35%',color_primary:'20 55% 35%',color_primary_foreground:'35 40% 93%',color_secondary:'35 45% 45%',color_secondary_foreground:'0 0% 100%',color_accent:'15 70% 40%',color_accent_foreground:'0 0% 100%',color_destructive:'0 65% 40%',color_destructive_foreground:'0 0% 100%',color_background_dark:'25 35% 10%',color_foreground_dark:'35 30% 88%'},
  'high-contrast':{color_background:'0 0% 100%',color_foreground:'0 0% 0%',color_muted:'0 0% 94%',color_muted_foreground:'0 0% 20%',color_primary:'220 100% 30%',color_primary_foreground:'0 0% 100%',color_secondary:'0 0% 20%',color_secondary_foreground:'0 0% 100%',color_accent:'40 100% 35%',color_accent_foreground:'0 0% 0%',color_destructive:'0 100% 35%',color_destructive_foreground:'0 0% 100%',color_background_dark:'0 0% 0%',color_foreground_dark:'0 0% 100%'},
  pastel:{color_background:'300 30% 98%',color_foreground:'270 30% 20%',color_muted:'300 20% 92%',color_muted_foreground:'270 20% 45%',color_primary:'260 60% 70%',color_primary_foreground:'0 0% 100%',color_secondary:'180 50% 65%',color_secondary_foreground:'0 0% 100%',color_accent:'340 65% 70%',color_accent_foreground:'0 0% 100%',color_destructive:'0 65% 65%',color_destructive_foreground:'0 0% 100%',color_background_dark:'270 30% 12%',color_foreground_dark:'300 20% 92%'}
};

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
var previewMode = 'light';
function syncPreview(){
  var p=document.getElementById('style-preview');
  if(!p)return;
  var map = previewMode==='dark' ? PREVIEW_MAP_DARK : PREVIEW_MAP;
  // Always set light vars first as fallback
  Object.keys(PREVIEW_MAP).forEach(function(col){
    var v=document.getElementById(col);
    if(v&&v.value) p.style.setProperty(PREVIEW_MAP[col],'hsl('+v.value+')');
  });
  // Overlay dark vars if in dark mode
  if(previewMode==='dark'){
    Object.keys(PREVIEW_MAP_DARK).forEach(function(col){
      var v=document.getElementById(col);
      if(v&&v.value) p.style.setProperty(PREVIEW_MAP_DARK[col],'hsl('+v.value+')');
    });
  }
}

document.querySelectorAll('.color-swatch').forEach(function(swatch){
  var field=document.getElementById(swatch.dataset.hslTarget);
  if(field&&field.value){var hex=hslToHex(field.value);if(hex)swatch.value=hex;}
});

document.querySelectorAll('.color-swatch').forEach(function(swatch){
  swatch.addEventListener('input',function(){
    var field=document.getElementById(swatch.dataset.hslTarget);
    if(field)field.value=hexToHsl(swatch.value);
    syncPreview();
  });
});

document.querySelectorAll('.color-hsl-input').forEach(function(input){
  input.addEventListener('input',function(){
    var swatch=document.querySelector('.color-swatch[data-hsl-target="'+input.id+'"]');
    if(swatch&&input.value){var hex=hslToHex(input.value);if(hex)swatch.value=hex;}
    syncPreview();
  });
});

function fillPalette(id){
  var p=PALETTES[id];if(!p)return;
  Object.keys(p).forEach(function(col){
    var field=document.getElementById(col);
    var swatch=document.querySelector('.color-swatch[data-hsl-target="'+col+'"]');
    if(field)field.value=p[col];
    if(swatch){var hex=hslToHex(p[col]);if(hex)swatch.value=hex;}
  });
  syncPreview();
}

var paletteSelect=document.querySelector('[data-palette-select]');
if(paletteSelect){
  paletteSelect.addEventListener('change',function(){ fillPalette(this.value); });
}

var resetBtn=document.getElementById('reset-palette-btn');
if(resetBtn){
  resetBtn.addEventListener('click',function(){
    var pal=paletteSelect?paletteSelect.value:'';
    if(pal)fillPalette(pal);
  });
}

var lightBtn=document.getElementById('preview-mode-light');
var darkBtn=document.getElementById('preview-mode-dark');
if(lightBtn)lightBtn.addEventListener('click',function(){ previewMode='light'; syncPreview(); });
if(darkBtn)darkBtn.addEventListener('click',function(){ previewMode='dark'; syncPreview(); });

syncPreview();
})();
</script>
<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
