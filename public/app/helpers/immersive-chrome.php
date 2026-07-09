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

function immersive_stage_toolbar_css(): string
{
    return <<<'CSS'
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
.immersive-stage-gyro-slot {
  display: contents;
}
.immersive-stage-download-wrap {
  position: relative;
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
  min-width: 13rem;
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
.immersive-stage-menu-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.6rem;
  width: 100%;
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
    height: 2.5rem;
    width: 2.5rem;
    border-radius: 0.7rem;
  }
  .immersive-stage-download-menu {
    min-width: min(12rem, calc(100vw - 2rem));
  }
  .immersive-stage-menu-btn {
    min-height: 2.55rem;
    font-size: 0.78rem;
  }
}
CSS;
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
        case 'fullscreen':
            return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>';
        case 'sound-off':
            return '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4z"/><line x1="22" y1="9" x2="16" y2="15"/><line x1="16" y1="9" x2="22" y2="15"/></svg>';
        case 'sound-on':
            return '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4z"/><path d="M16 9a4 4 0 0 1 0 6"/><path d="M19 6a8 8 0 0 1 0 12"/></svg>';
        case 'view':
        default:
            return '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m7 15 3-3 2 2 3-4 2 3"></path></svg>';
    }
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
 * - download_items: null (no download menu) | list of
 *                   ['tag' => 'a'|'button', 'label' => string, 'icon' => string, 'attrs' => array].
 * - show_fullscreen:    bool (default true).
 * - fullscreen_onclick: string|null — inline handler; exports pass null and bind in JS.
 * - gyro_slot:          bool (default true) — reserves the slot the gyro ⟲ toggle mounts into.
 * - sound_action:        null (no sound button) | ['enabled' => bool] — renders the
 *   mute/unmute sound toggle. Only present when the piece carries sonification
 *   metadata. The button mounts into the right toolbar group, immediately left of
 *   fullscreen. `setupImmersiveStageChrome` wires its click handler.
 */
function immersive_stage_toolbar_markup(array $opts = []): string
{
    $viewAction = $opts['view_action'] ?? null;
    $downloadItems = $opts['download_items'] ?? null;
    $showFullscreen = $opts['show_fullscreen'] ?? true;
    $fullscreenOnclick = $opts['fullscreen_onclick'] ?? null;
    $gyroSlot = $opts['gyro_slot'] ?? true;
    $soundAction = $opts['sound_action'] ?? null;

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

    if (is_array($downloadItems) && $downloadItems !== []) {
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

    if ($showFullscreen || is_array($soundAction)) {
        $html .= '<div class="immersive-stage-toolbar-group immersive-stage-toolbar-right">';

        if (is_array($soundAction) && !empty($soundAction['enabled'])) {
            $html .= '<button type="button" id="immersive-sound-toggle" class="immersive-stage-icon-btn" data-immersive-sound-toggle aria-pressed="false" aria-label="Unmute sound">'
                . immersive_stage_toolbar_icon_svg('sound-off')
                . '</button>';
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
