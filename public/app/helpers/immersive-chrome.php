<?php
/**
 * Shared immersive stage toolbar (chrome) for every immersive surface:
 * /immersive/pieces, /immersive/collections, /immersive/images, and the
 * standalone exported documents built by piece-render.php.
 *
 * The toolbar anchors to the TOP of the stage so it never overlaps the
 * bottom-center "Enable Motion Controls" iOS permission button rendered by
 * the gyro controller in immersive-gallery.js. Wiring lives in
 * setupImmersiveStageChrome() (immersive-gallery.js); this file only renders
 * markup/CSS so all surfaces stay pixel-identical.
 */

function immersive_stage_hand_guide_css(): string
{
    return <<<'CSS'
.hand-guide-dialog[hidden]{display:none!important}.hand-guide-dialog{position:fixed;inset:0;z-index:2147483000;display:flex;align-items:stretch;justify-content:center;padding:max(1rem,env(safe-area-inset-top)) max(1rem,env(safe-area-inset-right)) max(1rem,env(safe-area-inset-bottom)) max(1rem,env(safe-area-inset-left));background:rgba(3,7,18,.82);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);pointer-events:auto}.hand-guide-card{position:relative;display:flex;flex-direction:column;width:min(100%,34rem);min-height:0;margin:auto;border:1px solid rgba(255,255,255,.18);border-radius:1.25rem;background:rgba(9,14,24,.98);box-shadow:0 24px 80px rgba(0,0,0,.5);color:#fff;overflow:hidden}.hand-guide-header{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 1rem .75rem}.hand-guide-title{margin:0;font:700 1.05rem/1.25 system-ui,sans-serif}.hand-guide-close{display:inline-flex;align-items:center;justify-content:center;width:2.75rem;height:2.75rem;border:1px solid rgba(255,255,255,.18);border-radius:.75rem;background:rgba(255,255,255,.06);color:#fff;font-size:1.4rem;cursor:pointer}.hand-guide-viewport{display:grid;flex:1;min-height:15rem;padding:1rem;overflow:auto}.hand-guide-slide{display:grid;align-content:center;justify-items:center;gap:1rem;text-align:center}.hand-guide-slide[hidden]{display:none}.hand-guide-gesture{display:flex;align-items:center;justify-content:center;width:5.5rem;height:5.5rem;border:1px solid rgba(255,255,255,.2);border-radius:50%;background:rgba(89,184,201,.15);color:#fff}.hand-guide-slide h3{margin:0;font:750 1.35rem/1.2 system-ui,sans-serif}.hand-guide-slide p{max-width:27rem;margin:0;color:rgba(255,255,255,.82);font:400 .98rem/1.55 system-ui,sans-serif}.hand-guide-footer{display:grid;grid-template-columns:2.75rem 1fr 2.75rem;align-items:center;gap:.75rem;padding:.8rem 1rem 1rem}.hand-guide-nav{display:inline-flex;align-items:center;justify-content:center;width:2.75rem;height:2.75rem;border:1px solid rgba(255,255,255,.18);border-radius:.75rem;background:rgba(255,255,255,.07);color:#fff;font-size:1.15rem;cursor:pointer}.hand-guide-nav:disabled{opacity:.35;cursor:default}.hand-guide-progress{display:flex;justify-content:center;gap:.4rem}.hand-guide-dot{width:.45rem;height:.45rem;border-radius:50%;background:rgba(255,255,255,.3)}.hand-guide-dot.is-active{background:#fff}.hand-guide-dialog :focus-visible{outline:2px solid #fff;outline-offset:2px}@media(max-width:600px){.hand-guide-dialog{padding:0}.hand-guide-card{width:100%;height:100%;border:0;border-radius:0}.hand-guide-viewport{min-height:0}.hand-guide-footer{padding-bottom:max(1rem,env(safe-area-inset-bottom))}}
CSS;
}

function immersive_stage_toolbar_css(): string
{
    $css = <<<'CSS'
.immersive-stage-toolbar {
  position: absolute;
  top: calc(0.75rem + env(safe-area-inset-top));
  left: calc(0.75rem + env(safe-area-inset-left));
  right: calc(0.75rem + env(safe-area-inset-right));
  z-index: 135;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  pointer-events: none;
}
.immersive-stage-toolbar-group {
  display: flex;
  align-items: flex-start;
  gap: 0.65rem;
  min-width: 0;
  pointer-events: auto;
}
.immersive-stage-toolbar-right {
  margin-left: auto;
}
.immersive-stage-vr-link {
  width: auto;
  min-width: 2.75rem;
  padding-inline: 0.7rem;
  color: #fff;
  font-size: 0.78rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-decoration: none;
}
.immersive-stage-gyro-slot {
  display: contents;
}
.immersive-stage-download-wrap {
  position: relative;
}
.immersive-stage-sound-wrap {
  position: relative;
  display: flex;
  align-items: center;
  gap: 0.3rem;
}
.immersive-stage-sound-panel-trigger {
  height: 1.7rem;
  width: 1.5rem;
  padding: 0;
}
.immersive-stage-sound-panel[hidden] {
  display: none;
}
.immersive-stage-sound-panel {
  position: absolute;
  top: calc(100% + 0.55rem);
  right: 0;
  width: 17rem;
  display: grid;
  gap: 0.7rem;
  padding: 0.85rem;
  border: 1px solid rgba(255, 255, 255, 0.14);
  border-radius: 1rem;
  background: rgba(9, 14, 24, 0.94);
  box-shadow: 0 18px 40px rgba(0, 0, 0, 0.4);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  z-index: 140;
  color: #fff;
}
.immersive-stage-sound-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.6rem;
  font-size: 0.8rem;
}
.immersive-stage-sound-row[hidden] {
  display: none !important;
}
.immersive-stage-sound-switch,
.immersive-stage-sound-keyboard-toggle {
  border: 1px solid rgba(255, 255, 255, 0.18);
  border-radius: 0.6rem;
  background: rgba(255, 255, 255, 0.06);
  color: #fff;
  font: inherit;
  font-size: 0.78rem;
  font-weight: 600;
  padding: 0.35rem 0.6rem;
  cursor: pointer;
}
.immersive-stage-sound-switch[aria-checked="true"],
.immersive-stage-sound-keyboard-toggle[aria-pressed="true"] {
  background: rgba(255, 255, 255, 0.22);
  border-color: #fff;
}
.immersive-stage-sound-volume {
  width: 100%;
  accent-color: #fff;
}
.immersive-voice-picker {
  display: grid;
  gap: 0.45rem;
}
.immersive-voice-picker-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.6rem;
  font-size: 0.8rem;
}
.immersive-voice-picker-row[hidden] {
  display: none !important;
}
.immersive-voice-picker-select {
  border: 1px solid rgba(255, 255, 255, 0.18);
  border-radius: 0.6rem;
  background: rgba(255, 255, 255, 0.06);
  color: #fff;
  font: inherit;
  font-size: 0.78rem;
  padding: 0.3rem 0.5rem;
}
.immersive-mic-fx-wrap {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.3rem 0.6rem;
}
.immersive-mic-fx-wrap[hidden] {
  display: none !important;
}
.immersive-mic-fx-label {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.75rem;
}
.immersive-piano-keys[hidden] {
  display: none;
}
.immersive-piano-octave-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  font-size: 0.78rem;
}
.immersive-piano-octave-btn {
  height: 1.6rem;
  width: 1.6rem;
  border: 1px solid rgba(255, 255, 255, 0.18);
  border-radius: 0.4rem;
  background: rgba(255, 255, 255, 0.08);
  color: #fff;
  font: inherit;
  font-weight: 700;
  cursor: pointer;
  line-height: 1;
}
.immersive-piano-octave-btn:hover,
.immersive-piano-octave-btn:focus-visible {
  background: rgba(255, 255, 255, 0.22);
  border-color: #fff;
}
.immersive-piano-octave-display {
  min-width: 1.4rem;
  text-align: center;
  font-weight: 600;
}
.immersive-piano-keys {
  position: relative;
  height: 4.5rem;
  display: flex;
  touch-action: none;
  user-select: none;
  -webkit-user-select: none;
}
.immersive-piano-key-white {
  position: relative;
  flex: 1 1 0;
  height: 100%;
  border: 1px solid rgba(0, 0, 0, 0.35);
  border-radius: 0 0 0.3rem 0.3rem;
  background: #f4f1e8;
  cursor: pointer;
  z-index: 1;
  touch-action: none;
}
.immersive-piano-key-white:hover,
.immersive-piano-key-white:focus-visible {
  background: #d8d4c4;
}
.immersive-piano-key-white:active,
.immersive-piano-key-white.is-pressed {
  background: #bbb7a8;
}
.immersive-piano-key-black {
  position: absolute;
  top: 0;
  width: 6%;
  height: 62%;
  transform: translateX(-50%);
  border: 1px solid rgba(0, 0, 0, 0.6);
  border-radius: 0 0 0.25rem 0.25rem;
  background: #17161a;
  cursor: pointer;
  z-index: 2;
  touch-action: none;
}
.immersive-piano-key-black:hover,
.immersive-piano-key-black:focus-visible {
  background: #3a3942;
}
.immersive-piano-key-black:active,
.immersive-piano-key-black.is-pressed {
  background: #5c5a69;
}
.immersive-stage-icon-btn,
.fullscreen-toggle-btn {
  position: static;
  display: inline-flex;
  height: 2.75rem;
  width: 2.75rem;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(255, 255, 255, 0.15);
  background: rgba(0, 0, 0, 0.55);
  color: #fff;
  border-radius: 0.75rem;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  cursor: pointer;
  transition: background 0.2s, border-color 0.2s, transform 0.2s;
}
.immersive-stage-icon-btn:hover,
.immersive-stage-icon-btn:focus-visible,
.fullscreen-toggle-btn:hover,
.fullscreen-toggle-btn:focus-visible,
.immersive-stage-icon-btn.is-active {
  background: rgba(0, 0, 0, 0.72);
  border-color: #fff;
}
.immersive-stage-icon-btn.is-active {
  transform: translateY(1px);
}
.immersive-stage-icon-btn svg,
.fullscreen-toggle-btn svg {
  flex-shrink: 0;
}
.immersive-stage-download-menu[hidden] {
  display: none;
}
.immersive-stage-download-menu {
  position: absolute;
  top: calc(100% + 0.55rem);
  left: 0;
  min-width: min(22rem, calc(100vw - 2rem));
  display: grid;
  gap: 0.45rem;
  padding: 0.55rem;
  border: 1px solid rgba(255, 255, 255, 0.14);
  border-radius: 1rem;
  background: rgba(9, 14, 24, 0.94);
  box-shadow: 0 18px 40px rgba(0, 0, 0, 0.4);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  z-index: 140;
}
.immersive-stage-download-heading {
  margin: 0.15rem 0 0.25rem;
  color: rgba(255, 255, 255, 0.78);
  font-size: 0.78rem;
}
.immersive-stage-download-choice {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  min-height: 2.75rem;
  padding: 0.45rem 0.6rem;
  border-radius: 0.7rem;
  color: #fff;
  font-size: 0.82rem;
  cursor: pointer;
}
.immersive-stage-download-choice:focus-within,
.immersive-stage-download-choice:hover {
  background: rgba(255, 255, 255, 0.08);
}
.immersive-stage-download-choice input {
  width: 1.1rem;
  height: 1.1rem;
  accent-color: #fff;
}
.immersive-stage-menu-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.6rem;
  width: 100%;
  box-sizing: border-box;
  min-height: 2.75rem;
  padding: 0.65rem 0.8rem;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 0.8rem;
  background: rgba(255, 255, 255, 0.05);
  color: #fff;
  font: inherit;
  font-size: 0.84rem;
  font-weight: 600;
  text-decoration: none;
  text-align: left;
  cursor: pointer;
}
.immersive-stage-menu-btn:hover,
.immersive-stage-menu-btn:focus-visible {
  background: rgba(255, 255, 255, 0.12);
  border-color: rgba(255, 255, 255, 0.28);
}
.immersive-stage-menu-btn svg {
  flex-shrink: 0;
}
@media (max-width: 700px), (max-height: 560px) {
  .immersive-stage-toolbar {
    top: calc(0.6rem + env(safe-area-inset-top));
    left: calc(0.6rem + env(safe-area-inset-left));
    right: calc(0.6rem + env(safe-area-inset-right));
    gap: 0.6rem;
  }
  .immersive-stage-toolbar-group {
    gap: 0.45rem;
  }
  .immersive-stage-icon-btn,
  .fullscreen-toggle-btn {
    height: 2.75rem;
    width: 2.75rem;
    border-radius: 0.7rem;
  }
  .immersive-stage-download-menu {
    min-width: min(20rem, calc(100vw - 2rem));
  }
  .immersive-stage-menu-btn {
    min-height: 2.55rem;
    font-size: 0.78rem;
  }
  .immersive-stage-sound-panel {
    width: min(17rem, calc(100vw - 2rem));
  }
}
CSS;
    return $css . immersive_stage_hand_guide_css();
}

function immersive_stage_toolbar_icon_svg(string $icon): string
{
    switch ($icon) {
        case 'interactive':
            return '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6h3a2 2 0 0 1 2 2v3"></path><path d="M9 18H6a2 2 0 0 1-2-2v-3"></path><path d="M8 12h8"></path><path d="m12 8 4 4-4 4"></path></svg>';
        case 'slideshow':
            return '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"/><line x1="7" y1="2" x2="7" y2="22"/><line x1="17" y1="2" x2="17" y2="22"/><line x1="2" y1="12" x2="22" y2="12"/><line x1="2" y1="7" x2="7" y2="7"/><line x1="17" y1="7" x2="22" y2="7"/><line x1="17" y1="17" x2="22" y2="17"/><line x1="2" y1="17" x2="7" y2="17"/></svg>';
        case 'download':
            return '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v11"></path><path d="m7 10 5 5 5-5"></path><path d="M5 20h14"></path></svg>';
        case 'download-small':
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v11"></path><path d="m7 10 5 5 5-5"></path><path d="M5 20h14"></path></svg>';
        case 'png':
            return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"></rect><path d="M8 13h8"></path><path d="M8 9h8"></path><path d="M8 17h5"></path></svg>';
        case 'screenshot':
            return '<svg viewBox="0 0 24 24" width="19" height="19" fill="currentColor" aria-hidden="true"><path d="M9 4.5 7.8 6H5.5A2.5 2.5 0 0 0 3 8.5v9A2.5 2.5 0 0 0 5.5 20h13a2.5 2.5 0 0 0 2.5-2.5v-9A2.5 2.5 0 0 0 18.5 6h-2.3L15 4.5H9Zm3 4a4.75 4.75 0 1 1 0 9.5 4.75 4.75 0 0 1 0-9.5Zm0 1.75a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z"/></svg>';
        case 'fullscreen':
            return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>';
        case 'sound-off':
            return '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4z"/><line x1="22" y1="9" x2="16" y2="15"/><line x1="16" y1="9" x2="22" y2="15"/></svg>';
        case 'sound-on':
            return '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4z"/><path d="M16 9a4 4 0 0 1 0 6"/><path d="M19 6a8 8 0 0 1 0 12"/></svg>';
        case 'chevron-down':
            return '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>';
        case 'hand':
            return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 11V5.5a1.5 1.5 0 0 1 3 0V10"/><path d="M10 10V3.5a1.5 1.5 0 0 1 3 0V10"/><path d="M13 10V4.5a1.5 1.5 0 0 1 3 0V11"/><path d="M16 11V7.5a1.5 1.5 0 0 1 3 0V14c0 4.4-2.6 7-7 7h-1.2a6 6 0 0 1-5.1-2.9L3.4 14a1.6 1.6 0 0 1 2.7-1.7L7 13.5V11Z"/></svg>';
        case 'view':
        default:
            return '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m7 15 3-3 2 2 3-4 2 3"></path></svg>';
    }
}

function immersive_stage_hand_guide_markup(string $idPrefix = 'immersive', string $buttonClass = 'immersive-stage-icon-btn', string $variant = ''): string
{
    $dialogId = htmlspecialchars($idPrefix . '-hand-guide', ENT_QUOTES, 'UTF-8');
    $buttonClass = htmlspecialchars($buttonClass, ENT_QUOTES, 'UTF-8');
    $handIcon = immersive_stage_toolbar_icon_svg('hand');
    $slides = [
        ['Look', 'Hold an open hand in view and move it smoothly. The camera follows your hand while filtering small tracking movements.'],
        ['Move', 'Point, hold briefly until “Point + pinch to move” appears, then pinch. Move your hand up or down to travel and sideways to strafe.'],
        ['Orbit', 'Hold an open or closed hand briefly, then pinch. Sweep your hand to orbit around the current view or artwork.'],
        ['Zoom', 'While pinching, move your hand closer to or farther from the camera to approach or pull away from the view.'],
        ['Stop safely', 'Release the pinch to stop immediately. If your hand leaves the camera frame, movement stops without resetting your current view.'],
    ];
    if ($variant === 'c2_interactive_latched') {
        $slides[] = ['Interaction pauses', 'The C2 artwork keeps animating but remains non-interactive whenever it is spatially displaced. Turning off Steer the piece freezes that displaced pose; it does not restore clicking, dragging, touch, or hand-pointer interaction.'];
        $slides[] = ['Return to interact', 'Choose Reset view after turning steering off to return to the original framed view and restore interaction. If steering is still on, Reset returns home without disabling steering, so the artwork remains ready for spatial movement and stays non-interactive.'];
    }
    $html = '<button type="button" class="' . $buttonClass . '" data-hand-guide-trigger aria-haspopup="dialog" aria-expanded="false" aria-controls="' . $dialogId . '" aria-label="Show hand gesture guide" title="Hand gesture guide">' . $handIcon . '</button>';
    $html .= '<div id="' . $dialogId . '" class="hand-guide-dialog" data-hand-guide-dialog role="dialog" aria-modal="true" aria-labelledby="' . $dialogId . '-title" hidden>';
    $html .= '<div class="hand-guide-card" data-hand-guide-card><div class="hand-guide-header"><h2 id="' . $dialogId . '-title" class="hand-guide-title">Hand gesture guide</h2><button type="button" class="hand-guide-close" data-hand-guide-close aria-label="Close hand gesture guide">×</button></div>';
    $html .= '<div class="hand-guide-viewport">';
    foreach ($slides as $index => [$title, $description]) {
        $hidden = $index === 0 ? '' : ' hidden';
        $html .= '<section class="hand-guide-slide" data-hand-guide-slide data-hand-guide-index="' . $index . '"' . $hidden . '><div class="hand-guide-gesture">' . $handIcon . '</div><h3>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h3><p>' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '</p></section>';
    }
    $html .= '</div><div class="hand-guide-footer"><button type="button" class="hand-guide-nav" data-hand-guide-prev aria-label="Previous gesture" disabled>←</button><div class="hand-guide-progress" aria-hidden="true">';
    foreach ($slides as $index => $_slide) {
        $html .= '<span class="hand-guide-dot' . ($index === 0 ? ' is-active' : '') . '" data-hand-guide-dot></span>';
    }
    $html .= '</div><button type="button" class="hand-guide-nav" data-hand-guide-next aria-label="Next gesture">→</button></div></div></div>';
    $html .= <<<'HTML'
<script>(function(){
  var script=document.currentScript,scope=script&&script.parentElement?script.parentElement:document;
  var dialog=scope.querySelector('[data-hand-guide-dialog]');
  if(!dialog||dialog.dataset.handGuideBound==='true')return;
  dialog.dataset.handGuideBound='true';
  var trigger=scope.querySelector('[data-hand-guide-trigger]');
  var card=dialog.querySelector('[data-hand-guide-card]');
  var closeBtn=dialog.querySelector('[data-hand-guide-close]');
  var prev=dialog.querySelector('[data-hand-guide-prev]');
  var next=dialog.querySelector('[data-hand-guide-next]');
  var slides=Array.prototype.slice.call(dialog.querySelectorAll('[data-hand-guide-slide]'));
  var dots=Array.prototype.slice.call(dialog.querySelectorAll('[data-hand-guide-dot]'));
  var index=0,lastFocus=null,previousOverflow='';
  function show(nextIndex){index=Math.max(0,Math.min(slides.length-1,nextIndex));slides.forEach(function(slide,i){slide.hidden=i!==index;});dots.forEach(function(dot,i){dot.classList.toggle('is-active',i===index);});prev.disabled=index===0;next.disabled=index===slides.length-1;}
  function open(){lastFocus=document.activeElement;previousOverflow=document.body.style.overflow;document.body.style.overflow='hidden';dialog.hidden=false;trigger.setAttribute('aria-expanded','true');show(0);closeBtn.focus();}
  function close(){if(dialog.hidden)return;dialog.hidden=true;trigger.setAttribute('aria-expanded','false');document.body.style.overflow=previousOverflow;if(lastFocus&&typeof lastFocus.focus==='function')lastFocus.focus();}
  function focusables(){return Array.prototype.slice.call(dialog.querySelectorAll('button:not([disabled]),[href],input:not([disabled]),[tabindex]:not([tabindex="-1"])')).filter(function(el){return !el.hidden;});}
  trigger.addEventListener('click',open);closeBtn.addEventListener('click',close);prev.addEventListener('click',function(){show(index-1);});next.addEventListener('click',function(){show(index+1);});
  dialog.addEventListener('click',function(event){if(event.target===dialog)close();});
  dialog.addEventListener('keydown',function(event){if(event.key==='Escape'){event.preventDefault();close();return;}if(event.key==='ArrowLeft'){event.preventDefault();show(index-1);return;}if(event.key==='ArrowRight'){event.preventDefault();show(index+1);return;}if(event.key!=='Tab')return;var items=focusables();if(!items.length)return;var first=items[0],last=items[items.length-1];if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus();}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus();}});
  show(0);
})();</script>
HTML;
    return $html;
}

/**
 * Renders `$count` note-trigger buttons for the sound panel's keyboard mode.
 * A fixed count (rather than looking up the piece's actual scale length) keeps
 * this markup helper independent of sonic_params — sonic-controller.js's
 * triggerNote() wraps the degree index by modulo scale length client-side, so
 * a 5-note pentatonic piece just repeats a couple of pitches across the 7
 * buttons rather than needing the exact count threaded through here.
 */
/**
 * Renders a one-octave piano keyboard (C to B, 7 white + 5 black keys) plus
 * an octave display and up/down buttons, for the sound popover's keyboard
 * mode. Plays chromatically (any semitone) through sonic-controller.js's
 * triggerChromaticNote()/setOctave(), independent of the piece's configured
 * scale (a piano plays any note, not just scale degrees).
 *
 * $cssPrefix/$dataPrefix let each surface (immersive popover, regular-view
 * popover) render the same layout under its own class/attribute namespace,
 * matching the parameterization pattern already used elsewhere in this file.
 */
function immersive_stage_piano_keyboard_markup(string $cssPrefix = 'immersive-piano', string $dataPrefix = 'data-immersive-piano'): string
{
    // 10 white keys (one octave plus a major third into the next) matching
    // PIANO_KEY_MAP's physical-keyboard span (sonic-controller.js) exactly —
    // a s d f g h j k l ; are all mapped to natural notes up to semitone 16,
    // so the on-screen piano needs to cover the same range or physical keys
    // beyond B would play notes with no corresponding visible key.
    $whiteNotes = ['C', 'D', 'E', 'F', 'G', 'A', 'B', 'C', 'D', 'E'];
    $whiteSemitones = [0, 2, 4, 5, 7, 9, 11, 12, 14, 16];
    // White-key index => sharp label for the black key immediately after it.
    // No black key after E (indexes 2, 9) or B (index 6) — real piano
    // spacing, repeated once the octave rolls over at index 7 (C again).
    $blackAfter = [0 => 'C♯', 1 => 'D♯', 3 => 'F♯', 4 => 'G♯', 5 => 'A♯', 7 => 'C♯', 8 => 'D♯'];

    $html = '<div class="' . $cssPrefix . '-octave-row">';
    $html .= '<button type="button" class="' . $cssPrefix . '-octave-btn" ' . $dataPrefix . '-octave-down aria-label="Octave down">−</button>';
    $html .= '<output class="' . $cssPrefix . '-octave-display" ' . $dataPrefix . '-octave-display>3</output>';
    $html .= '<button type="button" class="' . $cssPrefix . '-octave-btn" ' . $dataPrefix . '-octave-up aria-label="Octave up">+</button>';
    $html .= '</div>';

    $html .= '<div class="' . $cssPrefix . '-keys" ' . $dataPrefix . '-keys role="group" aria-label="Piano keyboard">';
    foreach ($whiteSemitones as $i => $semitone) {
        $html .= '<button type="button" class="' . $cssPrefix . '-key ' . $cssPrefix . '-key-white" ' . $dataPrefix . '-key data-semitone="' . $semitone . '" aria-label="Play ' . $whiteNotes[$i] . '"></button>';
        if (isset($blackAfter[$i])) {
            $blackSemitone = $semitone + 1;
            $leftPercent = ($i + 1) * (100 / 10);
            $html .= '<button type="button" class="' . $cssPrefix . '-key ' . $cssPrefix . '-key-black" ' . $dataPrefix . '-key data-semitone="' . $blackSemitone . '" style="left:' . $leftPercent . '%;" aria-label="Play ' . $blackAfter[$i] . '"></button>';
        }
    }
    $html .= '</div>';
    return $html;
}

/**
 * Renders three <select> dropdowns (ambient/movement/melodic), one per
 * sonification voice, letting a visitor pick their own per-voice instrument
 * for THIS SESSION ONLY — never persisted server-side (see
 * sonic-controller.js's setVoiceInstrument()/getVoiceInstrument()); the
 * caller is responsible for stashing the choice in localStorage and
 * restoring it. Each row is always present in the markup but should be
 * hidden per-voice when that voice's public visibility is off, matching
 * how the keyboard row already hides itself when extras.voices.melodic
 * is off.
 *
 * Options mirror SONIC_INSTRUMENTS in sonic-controller.js as a literal PHP
 * array (this file has no JS-parsing/build step available) — KEEP IN SYNC
 * if that map ever changes.
 *
 * $cssPrefix/$dataPrefix follow the same per-surface parameterization
 * pattern as immersive_stage_piano_keyboard_markup() above.
 */
function immersive_stage_voice_instrument_picker_markup(string $cssPrefix = 'immersive-voice-picker', string $dataPrefix = 'data-immersive-voice-picker'): string
{
    // KEEP IN SYNC with SONIC_INSTRUMENTS in public/assets/js/sonic-controller.js.
    $instruments = [
        'synth' => 'Synth', 'amsynth' => 'AM Synth', 'fmsynth' => 'FM Synth',
        'membranesynth' => 'Membrane', 'metalsynth' => 'Metal', 'plucksynth' => 'Plucked String',
        'duosynth' => 'Duo Synth',
    ];
    $voices = ['ambient' => 'Ambient', 'movement' => 'Movement', 'melodic' => 'Melodic'];

    $html = '<div class="' . $cssPrefix . '">';
    foreach ($voices as $voiceKey => $voiceLabel) {
        $selectId = $cssPrefix . '-' . $voiceKey;
        $html .= '<div class="' . $cssPrefix . '-row" ' . $dataPrefix . '-row="' . $voiceKey . '">';
        $html .= '<label for="' . $selectId . '">' . $voiceLabel . '</label>';
        $html .= '<select id="' . $selectId . '" class="' . $cssPrefix . '-select" ' . $dataPrefix . '-select data-voice="' . $voiceKey . '">';
        foreach ($instruments as $key => $label) {
            $html .= '<option value="' . $key . '">' . $label . '</option>';
        }
        $html .= '</select>';
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Renders the live human-voice input (mic) row plus its effects sub-panel —
 * a fourth audio layer mixed on top of the piece's own ambient/movement/
 * melodic voices, purely visitor-facing (session-local, off by default,
 * never persisted). Follows the same per-surface `$cssPrefix`/`$dataPrefix`
 * parameterization as immersive_stage_voice_instrument_picker_markup()
 * above so it can be reused verbatim in the regular view's popover.
 * The mic row itself is always rendered `hidden` — JS reveals it only when
 * CreatrSonicController's isMicSupported() check passes, so an unsupported
 * browser (or, in principle, a `file://` context lacking
 * navigator.mediaDevices) never shows a control that would just fail.
 */
function immersive_stage_mic_panel_markup(string $cssPrefix = 'immersive-mic', string $dataPrefix = 'data-immersive-mic', string $rowClass = 'immersive-stage-sound-row', string $toggleClass = 'immersive-stage-sound-keyboard-toggle'): string
{
    $effects = [
        'distortion' => 'Distortion', 'chorus' => 'Chorus', 'tremolo' => 'Tremolo',
        'pitch_shift' => 'Pitch shift', 'bitcrusher' => 'Bitcrusher',
        'flanger' => 'Flanger', 'ring_mod' => 'Ring mod',
    ];

    $html = '<div class="' . $rowClass . '" ' . $dataPrefix . '-row hidden>'
        . '<span>Microphone</span>'
        . '<button type="button" class="' . $toggleClass . '" ' . $dataPrefix . '-toggle aria-pressed="false">Live mic</button>'
        . '</div>';
    $html .= '<div class="' . $cssPrefix . '-fx-wrap" ' . $dataPrefix . '-fx hidden>';
    foreach ($effects as $key => $label) {
        $html .= '<label class="' . $cssPrefix . '-fx-label"><input type="checkbox" ' . $dataPrefix . '-fx-toggle data-effect="' . $key . '"> ' . $label . '</label>';
    }
    $html .= '</div>';
    return $html;
}

function immersive_stage_toolbar_attrs(array $attrs): string
{
    $out = '';
    foreach ($attrs as $name => $value) {
        if ($value === false || $value === null) {
            continue;
        }
        if ($value === true) {
            $out .= ' ' . $name;
            continue;
        }
        $out .= ' ' . $name . '="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"';
    }
    return $out;
}

/**
 * Options:
 * - view_action:    null (no button) | ['label' => string, 'icon' => 'interactive'|'view'|'slideshow',
 *                   'attrs' => array] — renders the view/slideshow trigger.
 * - vr_action:      null | ['href' => string, 'label' => string] — renders a
 *                   literal VR link in the left toolbar for embedded stages.
 * - download_items: null (no download menu) | list of
 *                   ['tag' => 'a'|'button', 'label' => string, 'icon' => string, 'attrs' => array].
 * - screenshot_action:  null (no button) | ['attrs' => array] — renders a standalone
 *   camera-icon "Take screenshot" button in the left toolbar group, next to the
 *   download menu trigger (not folded into it), matching the always-visible
 *   screenshot button pattern used by the regular (non-immersive) piece view.
 * - show_fullscreen:    bool (default true).
 * - fullscreen_onclick: string|null — inline handler; exports pass null and bind in JS.
 * - gyro_slot:          bool (default true) — reserves the slot the gyro ⟲ toggle mounts into.
 * - sound_action:        null (no sound button) | ['enabled' => bool] — renders the
 *   mute/unmute sound toggle plus an adjacent small chevron that expands a
 *   popover panel (volume slider, keyboard-mode toggle + note buttons). Only
 *   present when the piece carries sonification metadata. Mounts into the
 *   right toolbar group, immediately left of fullscreen. The mute button's
 *   own click handler is owned by createAudioController() (immersive-gallery.js);
 *   the panel is wired by `setupImmersiveStageChrome`'s `getAudioController` option.
 */
function immersive_stage_toolbar_markup(array $opts = []): string
{
    $viewAction = $opts['view_action'] ?? null;
    $vrAction = $opts['vr_action'] ?? null;
    $downloadItems = $opts['download_items'] ?? null;
    $downloadOptions = $opts['download_options'] ?? null;
    $screenshotAction = $opts['screenshot_action'] ?? null;
    $showFullscreen = $opts['show_fullscreen'] ?? true;
    $fullscreenOnclick = $opts['fullscreen_onclick'] ?? null;
    $gyroSlot = $opts['gyro_slot'] ?? true;
    $soundAction = $opts['sound_action'] ?? null;
    // Camera overlay permission (Metadata-tab camera_overlay column) — its
    // own flag, independent of the sound design, so a sound-less piece can
    // still get a controls panel with just the camera rows.
    $cameraView = !empty($opts['camera_view']);
    // Hand control (camera steering + tilt fallback) — from the capability
    // contract: unlocked by the camera permission OR the hand-tracking
    // voice on steerable engines, admin-toggleable.
    $handControl = !empty($opts['hand_control']);
    $handGuideVariant = (string) ($opts['hand_guide_variant'] ?? '');

    $html = '<div class="immersive-stage-toolbar" aria-label="Immersive piece controls">';
    $html .= '<div class="immersive-stage-toolbar-group immersive-stage-toolbar-group-left" role="toolbar" aria-label="Piece view and download controls">';

    if ($gyroSlot) {
        $html .= '<span class="immersive-stage-gyro-slot" data-immersive-gyro-slot></span>';
    }

    if (is_array($viewAction)) {
        $label = htmlspecialchars((string) ($viewAction['label'] ?? 'View piece full size'), ENT_QUOTES, 'UTF-8');
        $attrs = immersive_stage_toolbar_attrs($viewAction['attrs'] ?? []);
        $html .= '<button type="button" id="immersive-view-btn" class="immersive-stage-icon-btn" data-immersive-view-trigger aria-label="' . $label . '"' . $attrs . '>'
            . immersive_stage_toolbar_icon_svg((string) ($viewAction['icon'] ?? 'view'))
            . '</button>';
    }

    if (is_array($vrAction) && !empty($vrAction['href'])) {
        $href = htmlspecialchars((string) $vrAction['href'], ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars((string) ($vrAction['label'] ?? 'Open immersive VR view'), ENT_QUOTES, 'UTF-8');
        $html .= '<a class="immersive-stage-icon-btn immersive-stage-vr-link" href="' . $href . '" target="_blank" rel="noopener" aria-label="' . $label . '" title="' . $label . '"><span aria-hidden="true">VR</span></a>';
    }

    if (is_array($screenshotAction)) {
        $attrs = immersive_stage_toolbar_attrs($screenshotAction['attrs'] ?? []);
        $html .= '<button type="button" class="immersive-stage-icon-btn" aria-label="Take screenshot"' . $attrs . '>'
            . immersive_stage_toolbar_icon_svg('screenshot')
            . '</button>';
    }

    if (is_array($downloadOptions)) {
        $menuId = htmlspecialchars((string) ($downloadOptions['menu_id'] ?? 'immersive-download-menu'), ENT_QUOTES, 'UTF-8');
        $actions = is_array($downloadOptions['actions'] ?? null)
            ? $downloadOptions['actions']
            : [is_array($downloadOptions['action'] ?? null) ? $downloadOptions['action'] : []];
        $html .= '<div class="immersive-stage-download-wrap">';
        $html .= '<button type="button" class="immersive-stage-icon-btn" data-immersive-download-trigger aria-haspopup="true" aria-expanded="false" aria-controls="' . $menuId . '" aria-label="Open download menu">'
            . immersive_stage_toolbar_icon_svg('download')
            . '</button>';
        $estimateData = is_array($downloadOptions['estimates'] ?? null) ? $downloadOptions['estimates'] : [];
        $estimateVoiceCosts = htmlspecialchars(json_encode($estimateData['voice_costs'] ?? [], JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
        $html .= '<div id="' . $menuId . '" class="immersive-stage-download-menu" data-immersive-download-menu role="region" aria-label="ZIP download options"'
            . ' data-download-estimate-full="' . (int) ($estimateData['full_bytes'] ?? 0) . '"'
            . ' data-download-estimate-no-camera="' . (int) ($estimateData['no_camera_bytes'] ?? 0) . '"'
            . ' data-download-estimate-voice-costs="' . $estimateVoiceCosts . '" hidden>';
        $html .= '<p class="immersive-stage-download-heading">Include in this download:</p>';
        foreach (($downloadOptions['choices'] ?? []) as $choice) {
            $value = htmlspecialchars((string) ($choice['value'] ?? ''), ENT_QUOTES, 'UTF-8');
            $label = htmlspecialchars((string) ($choice['label'] ?? ''), ENT_QUOTES, 'UTF-8');
            if ($value === '' || $label === '') {
                continue;
            }
            $html .= '<label class="immersive-stage-download-choice"><input type="checkbox" data-piece-download-voice="' . $value . '" checked> <span>' . $label . '</span></label>';
        }
        foreach ($actions as $action) {
            $actionLabel = htmlspecialchars((string) ($action['label'] ?? 'Download ZIP'), ENT_QUOTES, 'UTF-8');
            $actionAttrs = immersive_stage_toolbar_attrs($action['attrs'] ?? []);
            $estimateLabel = str_contains(strtolower((string) ($action['label'] ?? '')), 'non-camera') ? 'no-camera' : 'full';
            $html .= '<a class="immersive-stage-menu-btn" data-piece-download-link data-piece-download-label="' . $estimateLabel . '"' . $actionAttrs . '>'
                . immersive_stage_toolbar_icon_svg('download-small')
                . '<span>' . $actionLabel . '</span></a>';
        }
        $html .= '</div></div>';
    } elseif (is_array($downloadItems) && count($downloadItems) === 1) {
        // A one-item menu is just an extra click for no reason — render the
        // single download directly as an icon button, matching view/
        // screenshot. Only worth a dropdown once there's an actual choice.
        $item = $downloadItems[0];
        $tag = ($item['tag'] ?? 'button') === 'a' ? 'a' : 'button';
        $label = htmlspecialchars((string) ($item['label'] ?? 'Download'), ENT_QUOTES, 'UTF-8');
        $attrs = immersive_stage_toolbar_attrs($item['attrs'] ?? []);
        $typeAttr = $tag === 'button' ? ' type="button"' : '';
        $html .= '<' . $tag . $typeAttr . ' class="immersive-stage-icon-btn" aria-label="' . $label . '"' . $attrs . '>'
            . immersive_stage_toolbar_icon_svg((string) ($item['icon'] ?? 'download-small'))
            . '</' . $tag . '>';
    } elseif (is_array($downloadItems) && $downloadItems !== []) {
        $html .= '<div class="immersive-stage-download-wrap">';
        $html .= '<button type="button" class="immersive-stage-icon-btn" data-immersive-download-trigger aria-haspopup="true" aria-expanded="false" aria-controls="immersive-download-menu" aria-label="Open download menu">'
            . immersive_stage_toolbar_icon_svg('download')
            . '</button>';
        $html .= '<div id="immersive-download-menu" class="immersive-stage-download-menu" data-immersive-download-menu role="menu" hidden>';
        foreach ($downloadItems as $item) {
            $tag = ($item['tag'] ?? 'button') === 'a' ? 'a' : 'button';
            $label = htmlspecialchars((string) ($item['label'] ?? 'Download'), ENT_QUOTES, 'UTF-8');
            $attrs = immersive_stage_toolbar_attrs($item['attrs'] ?? []);
            $typeAttr = $tag === 'button' ? ' type="button"' : '';
            $html .= '<' . $tag . $typeAttr . ' class="immersive-stage-menu-btn" role="menuitem"' . $attrs . '>'
                . immersive_stage_toolbar_icon_svg((string) ($item['icon'] ?? 'download-small'))
                . '<span>' . $label . '</span>'
                . '</' . $tag . '>';
        }
        $html .= '</div></div>';
    }

    $html .= '</div>';

    $soundEnabled = is_array($soundAction) && !empty($soundAction['enabled']);
    if ($showFullscreen || $soundEnabled || $cameraView || $handControl) {
        $html .= '<div class="immersive-stage-toolbar-group immersive-stage-toolbar-right">';

        if ($soundEnabled || $cameraView || $handControl) {
            $html .= '<div class="immersive-stage-sound-wrap">';
            if ($soundEnabled) {
                $html .= '<button type="button" id="immersive-sound-toggle" class="immersive-stage-icon-btn" data-immersive-sound-toggle aria-pressed="false" aria-label="Unmute sound">'
                    . immersive_stage_toolbar_icon_svg('sound-off')
                    . '</button>';
            }
            $html .= '<button type="button" class="immersive-stage-icon-btn immersive-stage-sound-panel-trigger" data-immersive-sound-panel-trigger aria-haspopup="true" aria-expanded="false" aria-controls="immersive-sound-panel" aria-label="Piece controls">'
                . immersive_stage_toolbar_icon_svg('chevron-down')
                . '</button>';
            $html .= '<div id="immersive-sound-panel" class="immersive-stage-sound-panel" data-immersive-sound-panel role="region" aria-label="Piece controls" hidden>';
            if ($soundEnabled) {
                $html .= '<div class="immersive-stage-sound-row">'
                    . '<span>Sound</span>'
                    . '<button type="button" class="immersive-stage-sound-switch" data-immersive-sound-mute-toggle role="switch" aria-checked="false">Off</button>'
                    . '</div>';
                $html .= '<div class="immersive-stage-sound-row">'
                    . '<label for="immersive-sound-volume" style="flex:0 0 auto;">Volume</label>'
                    . '<input type="range" id="immersive-sound-volume" class="immersive-stage-sound-volume" data-immersive-sound-volume min="0" max="100" step="1" value="50" aria-label="Volume">'
                    . '</div>';
                $html .= immersive_stage_voice_instrument_picker_markup();
                $html .= '<div class="immersive-stage-sound-row" data-immersive-sound-keyboard-row>'
                    . '<span>Keyboard</span>'
                    . '<button type="button" class="immersive-stage-sound-keyboard-toggle" data-immersive-sound-keyboard-toggle aria-pressed="false">Play notes</button>'
                    . '</div>';
                $html .= '<div class="immersive-piano-wrap" data-immersive-sound-keys hidden>'
                    . immersive_stage_piano_keyboard_markup()
                    . '</div>';
                $html .= '<div class="immersive-stage-sound-row" data-immersive-sound-hand-row hidden>'
                    . '<span>Hand-tracking</span>'
                    . '<button type="button" class="immersive-stage-sound-keyboard-toggle" data-immersive-sound-hand-toggle aria-pressed="false">Camera theremin</button>'
                    . '</div>';
            }
            if ($handControl) {
                // Toggle label defaults to piece steering; the gallery-room
                // surface (collection.php) overrides it to describe walking
                // the room instead.
                $handControlLabel = htmlspecialchars((string) ($opts['hand_control_label'] ?? 'Steer the piece'), ENT_QUOTES, 'UTF-8');
                $html .= '<div class="immersive-stage-sound-row" data-immersive-sound-hand-control-row hidden>'
                    . '<span>Hand control</span>'
                    . '<button type="button" class="immersive-stage-sound-keyboard-toggle" data-immersive-sound-hand-control-toggle aria-pressed="false">' . $handControlLabel . '</button>'
                    . '</div>';
                $html .= '<div class="immersive-stage-sound-row" data-immersive-reset-view-row>'
                    . '<span>View pose</span>'
                    . '<button type="button" class="immersive-stage-sound-keyboard-toggle" data-immersive-reset-view>Reset view</button>'
                    . '</div>';
            }
            if ($cameraView) {
                $html .= '<div class="immersive-stage-sound-row" data-immersive-sound-camera-bg-row hidden>'
                    . '<span>Camera view</span>'
                    . '<button type="button" class="immersive-stage-sound-keyboard-toggle" data-immersive-sound-camera-bg-toggle aria-pressed="false">Show camera</button>'
                    . '</div>';
                $html .= '<div class="immersive-stage-sound-row" data-immersive-sound-camera-opacity-row hidden>'
                    . '<label for="immersive-camera-opacity" style="flex:0 0 auto;">Camera opacity</label>'
                    . '<input type="range" id="immersive-camera-opacity" class="immersive-stage-sound-volume" data-immersive-sound-camera-opacity min="0" max="100" step="1" value="100" aria-label="Camera overlay opacity">'
                    . '</div>';
            }
            if ($soundEnabled) {
                $html .= immersive_stage_mic_panel_markup();
            }
            $html .= '</div>'; // .immersive-stage-sound-panel
            $html .= '</div>'; // .immersive-stage-sound-wrap
        }

        if ($handControl) {
            // Instruction only: opening this guide never enables tracking or
            // requests camera permission. Order is sound, controls, guide.
            $html .= immersive_stage_hand_guide_markup('immersive', 'immersive-stage-icon-btn', $handGuideVariant);
        }

        if ($showFullscreen) {
            $onclick = $fullscreenOnclick !== null
                ? ' onclick="' . htmlspecialchars($fullscreenOnclick, ENT_QUOTES, 'UTF-8') . '"'
                : '';
            $html .= '<button id="fullscreen-toggle-btn" class="fullscreen-toggle-btn"' . $onclick . ' aria-label="Expand immersive view">'
                . immersive_stage_toolbar_icon_svg('fullscreen')
                . '</button>';
        }

        $html .= '</div>';
    }

    $html .= '</div>';
    return $html;
}

/**
 * Critical CSS for the regular piece view (/pieces/{id}): sound panel,
 * piano, voice pickers, mic effects, download picker, and the fullscreen
 * overlay fallback. Inlined by views/pieces/show.php so the view never
 * depends on a (possibly stale-cached) external stylesheet for its chrome —
 * the same robustness pattern as immersive_stage_toolbar_css() on the
 * immersive surfaces. Single source: these rules were MOVED out of
 * public/assets/styles.css; do not re-add them there.
 */
function piece_view_critical_css(): string
{
    $css = <<<'CSS'
.piece-download-status {
    margin-top: 0.75rem;
    color: var(--ink-soft);
}

.piece-download-picker-wrap {
    position: relative;
    display: inline-flex;
    align-items: center;
}

.piece-export-overlay {
    position: absolute;
    top: 0.75rem;
    left: 0.75rem;
    z-index: 30;
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
}

.piece-export-icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 2.75rem;
    width: 2.75rem;
    padding: 0;
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 0.75rem;
    background: rgba(0, 0, 0, 0.55);
    color: #fff;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    cursor: pointer;
}

.piece-export-icon-btn:hover,
.piece-export-icon-btn:focus-visible {
    border-color: #fff;
    background: rgba(0, 0, 0, 0.72);
}

.piece-immersive-rail-link {
    flex: 0 0 auto;
    width: auto;
    min-width: 2.75rem;
    padding: 0 0.7rem;
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-decoration: none;
}

.piece-page-immersive-action {
    margin: 0 0 1rem;
}

.piece-page-immersive-action .piece-immersive-link {
    margin-top: 0;
}

.piece-export-icon-btn[disabled] {
    opacity: 0.6;
    cursor: progress;
}

.piece-download-picker[hidden] {
    display: none;
}

.piece-download-picker {
    position: absolute;
    top: 100%;
    left: 0;
    z-index: 40;
    margin-top: 0.55rem;
    display: grid;
    gap: 0.45rem;
    min-width: min(22rem, calc(100vw - 2rem));
    padding: 0.55rem;
    border: 1px solid rgba(255, 255, 255, 0.14);
    border-radius: 1rem;
    background: rgba(9, 14, 24, 0.94);
    color: #fff;
    box-shadow: 0 18px 40px rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    font-size: 0.82rem;
}

.piece-download-picker-heading {
    margin: 0.15rem 0 0.25rem;
    color: rgba(255, 255, 255, 0.78);
    font-size: 0.78rem;
}

.piece-download-picker-choice {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    min-height: 2.75rem;
    padding: 0.45rem 0.6rem;
    border-radius: 0.7rem;
    cursor: pointer;
}

.piece-download-picker-choice:hover,
.piece-download-picker-choice:focus-within {
    background: rgba(255, 255, 255, 0.08);
}

.piece-download-picker-choice input {
    width: 1.1rem;
    height: 1.1rem;
    accent-color: #fff;
}

.piece-download-picker-action {
    display: inline-flex;
    box-sizing: border-box;
    align-items: center;
    gap: 0.6rem;
    min-height: 2.75rem;
    padding: 0.65rem 0.8rem;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 0.8rem;
    background: rgba(255, 255, 255, 0.05);
    color: #fff;
    font-weight: 600;
    text-decoration: none;
    width: 100%;
}

.piece-download-picker-action:hover,
.piece-download-picker-action:focus-visible {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(255, 255, 255, 0.28);
}

/* Piece fullscreen overlay */

.piece-canvas-container {
    position: relative;
}

.piece-fullscreen-toggle {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    z-index: 10;
    display: inline-flex;
    height: 2.75rem;
    width: 2.75rem;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(255, 255, 255, 0.15);
    background: rgba(0, 0, 0, 0.55);
    color: #fff;
    border-radius: 0.75rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    cursor: pointer;
    transition: background 0.2s, border-color 0.2s;
}

.piece-fullscreen-toggle:hover,
.piece-fullscreen-toggle:focus-visible {
    background: rgba(0, 0, 0, 0.7);
    border-color: #fff;
}

.piece-sound-controls {
    position: absolute;
    top: 0.75rem;
    right: calc(0.75rem + 2.75rem + 0.5rem);
    z-index: 10;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.5rem;
}

.piece-stage-fullscreen .piece-sound-controls {
    top: calc(0.75rem + env(safe-area-inset-top));
    right: calc(0.75rem + env(safe-area-inset-right) + 2.75rem + 0.5rem);
}

.piece-sound-buttons {
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.piece-sound-toggle {
    display: inline-flex;
    height: 2.75rem;
    width: 2.75rem;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(255, 255, 255, 0.15);
    background: rgba(0, 0, 0, 0.55);
    color: #fff;
    border-radius: 0.75rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    cursor: pointer;
    transition: background 0.2s, border-color 0.2s;
}

.piece-sound-toggle:hover,
.piece-sound-toggle:focus-visible {
    background: rgba(0, 0, 0, 0.7);
    border-color: #fff;
}

.piece-sound-panel-trigger {
    display: inline-flex;
    height: 2.75rem;
    width: 2.75rem;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(255, 255, 255, 0.15);
    background: rgba(0, 0, 0, 0.55);
    color: #fff;
    border-radius: 0.75rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    cursor: pointer;
    padding: 0;
    transition: background 0.2s, border-color 0.2s;
}

.piece-sound-panel-trigger:hover,
.piece-sound-panel-trigger:focus-visible {
    background: rgba(0, 0, 0, 0.7);
    border-color: #fff;
}

.piece-sound-panel[hidden] {
    display: none;
}

.piece-sound-panel {
    width: 17rem;
    display: grid;
    gap: 0.7rem;
    padding: 0.85rem;
    border: 1px solid rgba(255, 255, 255, 0.14);
    border-radius: 1rem;
    background: rgba(9, 14, 24, 0.94);
    box-shadow: 0 18px 40px rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    color: #fff;
}

.piece-sound-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
    font-size: 0.8rem;
}

.piece-sound-switch,
.piece-sound-keyboard-toggle {
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 0.6rem;
    background: rgba(255, 255, 255, 0.06);
    color: #fff;
    font: inherit;
    font-size: 0.78rem;
    font-weight: 600;
    padding: 0.35rem 0.6rem;
    cursor: pointer;
}

.piece-sound-switch[aria-checked="true"],
.piece-sound-keyboard-toggle[aria-pressed="true"] {
    background: rgba(255, 255, 255, 0.22);
    border-color: #fff;
}

.piece-sound-volume {
    width: 100%;
    accent-color: #fff;
}

.piece-voice-picker {
    display: grid;
    gap: 0.45rem;
}

.piece-voice-picker-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
    font-size: 0.8rem;
}

.piece-voice-picker-row[hidden] {
    display: none !important;
}

.piece-voice-picker-select {
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 0.6rem;
    background: rgba(255, 255, 255, 0.06);
    color: #fff;
    font: inherit;
    font-size: 0.78rem;
    padding: 0.3rem 0.5rem;
}

.piece-mic-fx-wrap {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.3rem 0.6rem;
}

.piece-mic-fx-wrap[hidden] {
    display: none !important;
}

.piece-mic-fx-label {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
}

.piece-piano-octave-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    font-size: 0.78rem;
}

.piece-piano-octave-btn {
    height: 1.6rem;
    width: 1.6rem;
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 0.4rem;
    background: rgba(255, 255, 255, 0.08);
    color: #fff;
    font: inherit;
    font-weight: 700;
    cursor: pointer;
    line-height: 1;
}

.piece-piano-octave-btn:hover,
.piece-piano-octave-btn:focus-visible {
    background: rgba(255, 255, 255, 0.22);
    border-color: #fff;
}

.piece-piano-octave-display {
    min-width: 1.4rem;
    text-align: center;
    font-weight: 600;
}

.piece-piano-keys {
    position: relative;
    height: 4.5rem;
    display: flex;
    touch-action: none;
    user-select: none;
    -webkit-user-select: none;
}

.piece-piano-key-white {
    position: relative;
    flex: 1 1 0;
    height: 100%;
    border: 1px solid rgba(0, 0, 0, 0.35);
    border-radius: 0 0 0.3rem 0.3rem;
    background: #f4f1e8;
    cursor: pointer;
    z-index: 1;
    touch-action: none;
}

.piece-piano-key-white:hover,
.piece-piano-key-white:focus-visible {
    background: #d8d4c4;
}

.piece-piano-key-white:active,
.piece-piano-key-white.is-pressed {
    background: #bbb7a8;
}

.piece-piano-key-black {
    position: absolute;
    top: 0;
    width: 6%;
    height: 62%;
    transform: translateX(-50%);
    border: 1px solid rgba(0, 0, 0, 0.6);
    border-radius: 0 0 0.25rem 0.25rem;
    background: #17161a;
    cursor: pointer;
    z-index: 2;
    touch-action: none;
}

.piece-piano-key-black:hover,
.piece-piano-key-black:focus-visible {
    background: #3a3942;
}

.piece-piano-key-black:active,
.piece-piano-key-black.is-pressed {
    background: #5c5a69;
}

.piece-stage-fullscreen {
    /* Above every other fixed/floating chrome on the page (e.g. .theme-toggle
       at z-index: 9000) so the CSS-fallback fullscreen overlay (used when the
       Fullscreen API is unavailable/blocked, e.g. iPhone Safari) truly blocks
       interaction with anything underneath — matching what native
       requestFullscreen() already guarantees via the browser's top layer. */
    position: fixed;
    inset: 0;
    z-index: 9500;
    background: #0d0d0f;
}

.piece-stage-fullscreen .piece-canvas-container {
    height: 100%;
}

/* piece_render_iframe emits an inline height; !important wins over inline
   non-important styles, scoped to the fullscreen overlay only. */
.piece-stage-fullscreen .piece-canvas-container iframe {
    height: 100% !important;
}

.piece-stage-fullscreen .piece-action-row {
    display: none;
}

.piece-stage-fullscreen .piece-fullscreen-toggle {
    top: calc(0.75rem + env(safe-area-inset-top));
    right: calc(0.75rem + env(safe-area-inset-right));
}

.piece-stage-fullscreen .piece-export-overlay {
    position: fixed;
    top: calc(0.75rem + env(safe-area-inset-top));
    left: calc(0.75rem + env(safe-area-inset-left));
}

.piece-stage-fullscreen .piece-download-status {
    position: fixed;
    left: 1.25rem;
    right: 1.25rem;
    bottom: calc(4rem + env(safe-area-inset-bottom));
    z-index: 9501;
    color: #f8f5ee;
}

/* Belt-and-suspenders alongside the z-index bump above: also hide the
   floating theme toggle outright while the CSS-fallback fullscreen overlay
   is active, so it can't visually float over the piece even if some other
   future element ever out-ranks 9500. */
body.piece-fullscreen-locked .theme-toggle {
    display: none;
}

/* .piece-stage-fullscreen's own z-index only wins within the stacking
   context of its nearest positioned ancestor (<main>, position:relative;
   z-index:1) — it can never escape above <header> (z-index:30) or <footer>
   (z-index:1, but painted after <main> in DOM order so it still wins the
   tie) just by raising its own z-index further, since those are separate
   sibling stacking contexts under <body>. Raise <main>'s z-index above both
   while locked so its entire subtree (including the fullscreen overlay)
   actually paints on top of the whole page, not just within <main>. */
body.piece-fullscreen-locked main {
    z-index: 9600;
}

body.piece-fullscreen-locked {
    overflow: hidden;
}

@media (max-width: 700px), (max-height: 560px) {
    .piece-canvas-container {
        overflow: hidden;
        border-radius: 1rem;
    }

    .piece-fullscreen-toggle {
        top: 0.6rem;
        right: 0.6rem;
        height: 2.75rem;
        width: 2.75rem;
        border-radius: 0.7rem;
    }

    .piece-export-overlay {
        top: 0.6rem;
        left: 0.6rem;
        gap: 0.45rem;
    }

    .piece-export-icon-btn {
        height: 2.75rem;
        width: 2.75rem;
        border-radius: 0.7rem;
    }

    .piece-stage-fullscreen .piece-export-overlay {
        top: calc(0.6rem + env(safe-area-inset-top));
        left: calc(0.6rem + env(safe-area-inset-left));
    }

    .piece-stage-fullscreen .piece-download-status {
        left: max(0.85rem, env(safe-area-inset-left));
        right: max(0.85rem, env(safe-area-inset-right));
        bottom: calc(1rem + env(safe-area-inset-bottom));
    }
}
CSS;
    return $css . immersive_stage_hand_guide_css();
}
