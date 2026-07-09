<?php

declare(strict_types=1);

require_once __DIR__ . '/immersive-chrome.php';
if (!function_exists('public_copy_value')) {
    require_once __DIR__ . '/public-copy.php';
}

function piece_render_document(array $piece, array $version, array $options = []): string
{
    $title = htmlspecialchars((string) ($piece['title'] ?? 'Art piece'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $engine = strtolower((string) ($version['engine'] ?? $piece['engine'] ?? 'p5'));
    $captureSafeMedia = !empty($options['capture_safe_media']);
    $html = (string) ($version['html_code'] ?? '');
    $css = (string) ($version['css_code'] ?? '');
    $code = (string) ($version['generated_code'] ?? '');
    $mediaMap = [];
    if ($captureSafeMedia) {
        $mediaMap = piece_build_media_manifest([$html, $css, $code]);
        $rewriteMedia = static function (string $content) use ($mediaMap): string {
            return piece_export_rewrite_media_refs($content, static function (string $normalizedRef) use ($mediaMap): ?string {
                $asset = $mediaMap[$normalizedRef] ?? null;
                if (!is_array($asset)) {
                    return null;
                }

                return (string) ($asset['data_url'] ?? '');
            });
        };
        $html = $rewriteMedia($html);
        $css = $rewriteMedia($css);
        $code = $rewriteMedia($code);
    }
    if ($engine === 'aframe') {
        $aframeResolver = static function (string $src) use ($captureSafeMedia, $mediaMap): string {
            if ($captureSafeMedia) {
                $asset = $mediaMap[$src] ?? null;
                return is_array($asset) ? (string) ($asset['data_url'] ?? $src) : $src;
            }

            return piece_request_origin() . $src;
        };
        $html = piece_aframe_normalize_texture_assets($html, $aframeResolver);
        $html = piece_aframe_add_crossorigin_to_asset_images($html);
    }
    $jsonCode = json_encode($code, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $jsonEngine = json_encode($engine);
    $jsonHtml = json_encode($html, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $jsonCss = json_encode($css, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $jsonContext = json_encode([
        'viewerMode' => (string) ($options['viewer_mode'] ?? 'default'),
        'interactive' => !empty($options['interactive']),
        'disableMotion' => !empty($options['disable_motion']),
        // c2_interactive pieces attach their own pointer handlers regardless
        // of viewer_mode/interactive above (that option controls unrelated
        // chrome), so the runtime needs this separately to know whether
        // pointer movement is a meaningful sonification signal for a c2 piece.
        'c2Interactive' => art_piece_version_generation_mode($version, $piece) === 'c2_interactive',
        // Sound is gated per-piece (no master switch), not per-engine — every
        // engine can carry sonic_params now. Three.js/A-Frame sonify camera
        // motion, c2_interactive sonifies pointer motion, everything else
        // (p5, plain c2, svg) has no motion signal on this view and plays a
        // random idle note pattern instead.
        'sonic' => !empty($version['sonic_params'])
            ? json_decode((string) $version['sonic_params'], true)
            : null,
        'toneSource' => !empty($options['tone_source']) ? (string) $options['tone_source'] : null,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $aframeCaptureShim = $engine === 'aframe' ? piece_aframe_capture_context_shim() : '';
    $aframeCss = $engine === 'aframe'
        ? "a-scene{display:block;width:100%;height:100%;}\n.a-canvas{display:block;width:100%!important;height:100%!important;}\n"
        : '';

    $requestOrigin = piece_request_origin();
    $baseTag = '<base href="' . htmlspecialchars(rtrim($requestOrigin, '/') . '/', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '">';
    // The base tag and our own runtime script must load from wherever THIS
    // request is actually being served — never from seo_origin() (the site's
    // configured canonical URL, which can differ from the actual host in
    // local/dev), and never from window.location.origin at runtime either:
    // this document is frequently embedded via <iframe srcdoc>
    // (piece_render_iframe() below), and srcdoc documents get an opaque
    // origin — window.location.origin literally evaluates to the string
    // "null" in that context, even with sandbox="allow-same-origin".
    // Computing it server-side from the actual request avoids both traps and
    // keeps root-relative CMS media paths on the preview host.
    // Cache-busted by file mtime, matching the ?v= pattern already used for
    // admin-piece-capture.js's own <script> tags. Without this, browsers
    // (WebKit/Safari especially) can keep serving a stale cached copy of
    // piece-runtime.js indefinitely after a deploy, since this URL was
    // previously requested with no version hint at all — every fix to the
    // runtime since whenever a device first cached it would silently never
    // take effect on that device.
    $runtimeVersion = (int) @filemtime(dirname(__DIR__, 2) . '/assets/js/piece-runtime.js');
    $runtimeScriptUrl = htmlspecialchars($requestOrigin . '/assets/js/piece-runtime.js?v=' . $runtimeVersion, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
{$baseTag}
<title>{$title}</title>
<script type="importmap">
{
  "imports": {
    "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js",
    "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"
  }
}
</script>
<style>
html,body{margin:0;padding:0;width:100%;height:100%;overflow:hidden;background:#0d0d0f;color:#fff;}
body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}
#runtime-root{width:100vw;height:100vh;overflow:hidden;}
#piece-error{position:fixed;inset:auto 1rem 1rem 1rem;z-index:9999;padding:1rem;border:1px solid #fca5a5;background:#450a0a;color:#fee2e2;font:14px/1.4 ui-monospace,SFMono-Regular,Menlo,monospace;white-space:pre-wrap;display:none;}
canvas{display:block;width:100%;height:100%;}
/* A-Frame's device-orientation-permission-ui dialog hardcodes a white card
   and bright accent buttons but never sets its own text color, so it
   inherited this document's color:#fff above and went invisible
   (white-on-white). This document has no theme toggle and no --panel-bg/
   --text-primary variables (unlike the immersive views, which get the same
   override using those) — literal colors here instead. */
.a-dialog{background-color:#162a3a !important;border:1px solid rgba(255,255,255,0.15);}
.a-dialog-text{color:#dde8ef !important;}
.a-dialog-button{color:#15374a !important;}
{$aframeCss}
{$css}
</style>
</head>
<body>
<div id="runtime-root">{$html}</div>
<div id="piece-error" role="alert"></div>
<script>
const PIECE_ENGINE = {$jsonEngine};
const PIECE_CODE = {$jsonCode};
const PIECE_HTML_CODE = {$jsonHtml};
const PIECE_CSS_CODE = {$jsonCss};
window.CREATR_PIECE_CONTEXT = {$jsonContext};
window.PIECE_PRESERVE_DRAWING_BUFFER = true;
</script>
{$aframeCaptureShim}
<script src="{$runtimeScriptUrl}"></script>
</body>
</html>
HTML;
}

function piece_render_iframe(array $piece, array $version, int $height = 520, array $attributes = []): string
{
    $srcdoc = htmlspecialchars(piece_render_document($piece, $version, ['capture_safe_media' => true]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $title = htmlspecialchars((string) ($piece['title'] ?? 'Art piece'), ENT_QUOTES, 'UTF-8');
    $attributeString = '';
    foreach ($attributes as $name => $value) {
        if (!is_string($name) || $name === '') {
            continue;
        }

        if ($value === null || $value === false) {
            continue;
        }

        if ($value === true) {
            $attributeString .= ' ' . htmlspecialchars($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            continue;
        }

        $attributeString .= ' ' . htmlspecialchars($name, ENT_QUOTES | ENT_HTML5, 'UTF-8')
            . '="' . htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '"';
    }

    return '<iframe srcdoc="' . $srcdoc . '" style="width:100%;height:' . $height . 'px;border:0;display:block;" sandbox="allow-scripts allow-same-origin" title="' . $title . '"' . $attributeString . '></iframe>';
}

function piece_export_filename(array $piece): string
{
    return piece_export_basename($piece) . '.zip';
}

function piece_export_basename(array $piece): string
{
    $base = function_exists('slugify') ? slugify((string) ($piece['title'] ?? '')) : '';
    if ($base === '') {
        $base = 'piece-' . (int) ($piece['id'] ?? 0);
    }

    return $base;
}

function piece_export_document(array $piece, array $version, array $options = []): string
{
    $title = htmlspecialchars((string) ($piece['title'] ?? 'Art piece'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $engine = strtolower((string) ($version['engine'] ?? $piece['engine'] ?? 'p5'));
    $generationMode = art_piece_version_generation_mode($version, $piece);
    $runtimeMode = ($options['runtime_mode'] ?? 'cdn') === 'bundle' ? 'bundle' : 'cdn';
    $mediaMap = is_array($options['media_map'] ?? null) ? $options['media_map'] : [];
    $embedMedia = !empty($options['embed_media']);
    $rewriteMedia = static function (string $content) use ($runtimeMode, $mediaMap, $embedMedia): string {
        if ($runtimeMode === 'bundle') {
            return piece_export_rewrite_media_refs($content, static function (string $normalizedRef) use ($mediaMap, $embedMedia): ?string {
                $asset = $mediaMap[$normalizedRef] ?? null;
                if (!is_array($asset)) {
                    return null;
                }

                return $embedMedia
                    ? (string) ($asset['data_url'] ?? '')
                    : (string) ($asset['path'] ?? '');
            });
        }

        return piece_export_rewrite_media_refs($content, static fn(string $normalizedRef): ?string => piece_request_origin() . $normalizedRef);
    };

    $html = $rewriteMedia((string) ($version['html_code'] ?? ''));
    if ($engine === 'aframe') {
        $aframeResolver = static function (string $src) use ($runtimeMode, $mediaMap, $embedMedia): string {
            if ($runtimeMode === 'bundle') {
                $asset = $mediaMap[$src] ?? null;
                if (!is_array($asset)) {
                    return $src;
                }

                return $embedMedia
                    ? (string) ($asset['data_url'] ?? $src)
                    : (string) ($asset['path'] ?? $src);
            }

            return piece_request_origin() . $src;
        };
        $html = piece_aframe_normalize_texture_assets($html, $aframeResolver);
        if ($runtimeMode !== 'bundle') {
            $html = piece_aframe_add_crossorigin_to_asset_images($html);
        }
    }
    $css = piece_escape_inline_css($rewriteMedia((string) ($version['css_code'] ?? '')));
    $code = piece_escape_inline_script($rewriteMedia((string) ($version['generated_code'] ?? '')));
    $imports = piece_export_imports($engine, $runtimeMode);
    $inlineRuntime = $runtimeMode === 'bundle' ? piece_export_inline_runtime_markup($engine) : '';
    $aframeCaptureShim = $engine === 'aframe' ? piece_aframe_capture_context_shim() : '';
    $sonicScript = piece_export_sonic_script($engine, (string) ($version['sonic_params'] ?? ''), $runtimeMode);
    $bootstrap = piece_export_bootstrap($engine, $generationMode, $runtimeMode);
    $exportOverlayCss = piece_export_screenshot_overlay_css($generationMode);
    $exportOverlayMarkup = piece_export_screenshot_overlay_markup($generationMode);
    $exportOverlayScript = piece_export_screenshot_overlay_script($piece, $generationMode);
    $cssHref = is_string($options['css_href'] ?? null) ? trim((string) $options['css_href']) : '';
    $scriptSrc = is_string($options['script_src'] ?? null) ? trim((string) $options['script_src']) : '';
    $cssTag = $cssHref !== ''
        ? '<link rel="stylesheet" href="' . htmlspecialchars($cssHref, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '">'
        : "<style>\nhtml,body{margin:0;padding:0;width:100%;height:100%;overflow:hidden;background:#0d0d0f;color:#fff;}\nbody{font-family:system-ui,-apple-system,BlinkMacSystemFont,\"Segoe UI\",sans-serif;}\n#runtime-root{width:100vw;height:100vh;overflow:hidden;}\n#piece-error{position:fixed;inset:auto 1rem 1rem 1rem;z-index:9999;padding:1rem;border:1px solid #fca5a5;background:#450a0a;color:#fee2e2;font:14px/1.4 ui-monospace,SFMono-Regular,Menlo,monospace;white-space:pre-wrap;display:none;}\ncanvas{display:block;width:100%;height:100%;}\n{$exportOverlayCss}\n{$css}\n</style>";
    $pieceScriptTag = $scriptSrc !== ''
        ? '<script src="' . htmlspecialchars($scriptSrc, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '"></script>'
        : "<script>\n{$code}\n</script>";
    $bundleMeta = $runtimeMode === 'bundle'
        ? '<meta name="creatr-piece-export" content="portable-bundle">'
        : '';

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title}</title>
{$bundleMeta}
{$aframeCaptureShim}
{$imports}
{$cssTag}
{$inlineRuntime}
</head>
<body>
<div id="runtime-root">{$html}</div>
<div id="piece-error" role="alert"></div>
{$exportOverlayMarkup}
<script>
function showPieceError(error){const el=document.getElementById('piece-error');if(!el)return;el.textContent=(error&&(error.stack||error.message))?(error.stack||error.message):String(error);el.style.display='block';}
window.addEventListener('error',event=>showPieceError(event.error||event.message));
window.addEventListener('unhandledrejection',event=>showPieceError(event.reason||'Unhandled promise rejection'));
</script>
{$sonicScript}
{$pieceScriptTag}
{$bootstrap}
{$exportOverlayScript}
</body>
</html>
HTML;
}

/**
 * Movement sonification for standalone/bundle exports (Three.js/A-Frame
 * only) — muted by default, no master switch. Unlike the live regular view
 * (piece-runtime.js, controlled via postMessage from a host page's button),
 * an export has no host page, so this owns and creates its own toggle
 * button directly, mirroring piece-runtime.js's own standalone fallback
 * button. In bundle mode Tone.js is inlined as a Blob URL (same trick
 * piece_export_bootstrap already uses for OrbitControls) so the exported
 * bundle needs no network; in cdn mode it's loaded from the same
 * self-hosted path the live view uses.
 */
function piece_export_sonic_script(string $engine, string $sonicParamsJson, string $runtimeMode): string
{
    // Every engine can carry sonic_params (matches the live regular-view
    // gate in piece_render_document()). three/aframe get camera-driven
    // sonification via __creatrSonicSetMover (wired in piece_export_bootstrap);
    // p5/c2/svg have no motion signal in this export and get the idle
    // random-note pattern only (see motionTick's getMover-optional handling
    // below).
    if (trim($sonicParamsJson) === '') {
        return '';
    }

    $decoded = json_decode($sonicParamsJson, true);
    if (!is_array($decoded)) {
        return '';
    }

    $sonicJson = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $toneSourceJson = $runtimeMode === 'bundle'
        ? json_encode(piece_export_runtime_source_file('assets/vendor/tone/Tone.js'), JSON_UNESCAPED_UNICODE)
        : 'null';
    $toneSrcJson = json_encode(rtrim(piece_request_origin(), '/') . '/assets/vendor/tone/Tone.js');

    return <<<HTML
<script>
window.__creatrSonicParams = {$sonicJson};
window.__creatrToneInlineSource = {$toneSourceJson};
window.__creatrToneSrc = {$toneSrcJson};
(function () {
  var sonicParams = window.__creatrSonicParams;
  if (!sonicParams) return;
  var SCALES = {
    major: [0, 2, 4, 5, 7, 9, 11], minor: [0, 2, 3, 5, 7, 8, 10],
    pentatonic: [0, 2, 4, 7, 9], chromatic: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
    dorian: [0, 2, 3, 5, 7, 9, 10], phrygian: [0, 1, 3, 5, 7, 8, 10],
    lydian: [0, 2, 4, 6, 7, 9, 11], mixolydian: [0, 2, 4, 5, 7, 9, 10],
    wholetone: [0, 2, 4, 6, 8, 10],
  };
  var INSTRUMENTS = {
    synth: 'Synth', amsynth: 'AMSynth', fmsynth: 'FMSynth',
    membranesynth: 'MembraneSynth', metalsynth: 'MetalSynth',
    plucksynth: 'PluckSynth', duosynth: 'DuoSynth',
  };
  var scale = SCALES[sonicParams.scale] || SCALES.major;
  var instrumentKey = INSTRUMENTS[sonicParams.instrument] ? sonicParams.instrument : 'synth';
  var tempo = Math.max(40, Math.min(220, Number(sonicParams.tempo) || 90));
  var minInterval = ((60 / tempo) * 1000) / 2;
  var baseMidi = 48;
  var enabled = false, synth = null, lastNoteAt = 0, walk = 0;
  var prevX = null, prevY = null, prevZ = null;
  var lastMotionAt = 0;
  var IDLE_GAP_MS = 2000;
  var getMover = null;
  window.__creatrSonicSetMover = function (fn) { getMover = fn; };

  function midiToFreq(m) { return 440 * Math.pow(2, (m - 69) / 12); }

  function loadToneOnce() {
    if (window.Tone) return Promise.resolve(window.Tone);
    return new Promise(function (resolve, reject) {
      var s = document.createElement('script');
      s.onload = function () { window.Tone ? resolve(window.Tone) : reject(new Error('Tone.js loaded but window.Tone missing')); };
      s.onerror = function () { reject(new Error('Tone.js failed to load')); };
      if (window.__creatrToneInlineSource) {
        var blob = new Blob([window.__creatrToneInlineSource], { type: 'text/javascript' });
        s.src = URL.createObjectURL(blob);
      } else {
        s.src = window.__creatrToneSrc;
      }
      document.head.appendChild(s);
    });
  }

  function motionTick() {
    requestAnimationFrame(motionTick);
    if (!enabled || !synth) return;
    var now = performance.now();
    var mover = getMover ? getMover() : null;
    if (mover && mover.position) {
      var x = mover.position.x, y = mover.position.y, z = mover.position.z;
      if (prevX !== null) {
        var dx = x - prevX, dy = y - prevY, dz = z - prevZ;
        var speed = Math.hypot(dx, dy, dz);
        if (speed >= 0.002) {
          lastMotionAt = now;
          if (now - lastNoteAt >= minInterval) {
            lastNoteAt = now;
            var degree = walk++ % scale.length;
            var octave = Math.min(2, Math.floor(Math.abs(dy) * 25));
            var midi = baseMidi + scale[degree] + 12 * octave;
            try { synth.triggerAttackRelease(midiToFreq(midi), '16n'); } catch (_e) {}
          }
        }
      }
      prevX = x; prevY = y; prevZ = z;
    }
    // Idle pattern: plain scale-walk notes once the mover (if any) has been
    // still for a beat — or always, for engines with no motion signal at
    // all — so toggled-on sound never sits in dead silence.
    if (now - lastMotionAt >= IDLE_GAP_MS && now - lastNoteAt >= minInterval) {
      lastNoteAt = now;
      var idleDegree = walk++ % scale.length;
      var idleMidi = baseMidi + scale[idleDegree];
      try { synth.triggerAttackRelease(midiToFreq(idleMidi), '16n'); } catch (_e) {}
    }
  }
  requestAnimationFrame(motionTick);

  var ICON_OFF = '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4z"></path><line x1="22" y1="9" x2="16" y2="15"></line><line x1="16" y1="9" x2="22" y2="15"></line></svg>';
  var ICON_ON = '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4z"></path><path d="M16 9a4 4 0 0 1 0 6"></path><path d="M19 6a8 8 0 0 1 0 12"></path></svg>';

  function mountButton() {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.setAttribute('aria-pressed', 'false');
    btn.setAttribute('aria-label', 'Unmute sound');
    Object.assign(btn.style, {
      position: 'fixed', top: 'calc(0.75rem + env(safe-area-inset-top))',
      right: 'calc(0.75rem + env(safe-area-inset-right))',
      zIndex: '200', width: '2.75rem', height: '2.75rem',
      display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
      borderRadius: '0.75rem', border: '1px solid rgba(255,255,255,0.15)',
      background: 'rgba(0,0,0,0.55)', color: '#fff', cursor: 'pointer',
      boxShadow: '0 4px 12px rgba(0,0,0,0.5)', backdropFilter: 'blur(4px)',
    });
    btn.innerHTML = ICON_OFF;
    document.body.appendChild(btn);
    btn.addEventListener('click', function () {
      var nextEnabled = !enabled;
      if (nextEnabled && !synth) {
        btn.disabled = true;
        loadToneOnce().then(function (Tone) { return Tone.start(); }).then(function () {
          var Ctor = window.Tone[INSTRUMENTS[instrumentKey]] || window.Tone.Synth;
          synth = new Ctor().toDestination();
          if (synth.volume) synth.volume.value = -6;
          enabled = true;
          lastMotionAt = 0; // let idle notes start immediately on unmute
          btn.setAttribute('aria-pressed', 'true');
          btn.setAttribute('aria-label', 'Mute sound');
          btn.innerHTML = ICON_ON;
        }).catch(function () {
          enabled = false;
        }).finally(function () {
          btn.disabled = false;
        });
        return;
      }
      enabled = nextEnabled;
      if (enabled) lastMotionAt = 0; // let idle notes start immediately on unmute
      btn.setAttribute('aria-pressed', enabled ? 'true' : 'false');
      btn.setAttribute('aria-label', enabled ? 'Mute sound' : 'Unmute sound');
      btn.innerHTML = enabled ? ICON_ON : ICON_OFF;
    });
  }

  if (document.body) mountButton();
  else document.addEventListener('DOMContentLoaded', mountButton, { once: true });
})();
</script>
HTML;
}

function piece_escape_inline_script(string $code): string
{
    return str_replace('</script', '<\/script', $code);
}

function piece_escape_inline_css(string $css): string
{
    return str_replace('</style', '<\/style', $css);
}

function piece_export_rewrite_media_refs(string $content, callable $resolver): string
{
    if ($content === '') {
        return $content;
    }

    return preg_replace_callback(
        '#(?<![A-Za-z0-9._~/-])/?(?:image/[0-9]+|api/media-assets/[0-9]+|media/[A-Za-z0-9._~/%+-]+)(?:\?[A-Za-z0-9._~%=&+-]*)?#',
        static function (array $match) use ($resolver): string {
            $normalizedRef = '/' . ltrim((string) ($match[0] ?? ''), '/');
            $replacement = $resolver($normalizedRef);
            return is_string($replacement) && $replacement !== '' ? $replacement : (string) ($match[0] ?? '');
        },
        $content
    ) ?? $content;
}

function piece_export_decode_view_state(string $encoded): array
{
    $encoded = trim($encoded);
    if ($encoded === '' || strlen($encoded) > 8192 || !preg_match('/^[A-Za-z0-9_-]+$/', $encoded)) {
        return [];
    }

    $padded = strtr($encoded, '-_', '+/');
    $padding = strlen($padded) % 4;
    if ($padding > 0) {
        $padded .= str_repeat('=', 4 - $padding);
    }

    $json = base64_decode($padded, true);
    if (!is_string($json) || $json === '') {
        return [];
    }

    $decoded = json_decode($json, true);
    return is_array($decoded) ? piece_export_sanitize_view_state($decoded) : [];
}

function piece_export_sanitize_view_state(array $state): array
{
    $clean = [];
    foreach (['camera', 'target'] as $key) {
        $value = $state[$key] ?? null;
        if (!is_array($value)) {
            continue;
        }
        $vector = [];
        foreach (['x', 'y', 'z'] as $axis) {
            $number = $value[$axis] ?? null;
            if (is_numeric($number) && is_finite((float) $number)) {
                $vector[$axis] = max(-100000, min(100000, (float) $number));
            }
        }
        if (count($vector) === 3) {
            $clean[$key] = $vector;
        }
    }

    $activeIndex = $state['activeIndex'] ?? null;
    if (is_numeric($activeIndex)) {
        $clean['activeIndex'] = max(0, min(10000, (int) $activeIndex));
    }

    return $clean;
}

function piece_export_immersive_document(array $piece, array $version, array $options = []): string
{
    $title = htmlspecialchars((string) ($piece['title'] ?? 'Art piece'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $engine = strtolower((string) ($version['engine'] ?? $piece['engine'] ?? 'p5'));
    $generationMode = art_piece_version_generation_mode($version, $piece);
    $mediaMap = is_array($options['media_map'] ?? null) ? $options['media_map'] : [];
    $embedMedia = !empty($options['embed_media']);
    $viewState = is_array($options['view_state'] ?? null) ? piece_export_sanitize_view_state($options['view_state']) : [];
    $rewriteMedia = static function (string $content) use ($mediaMap, $embedMedia): string {
        return piece_export_rewrite_media_refs($content, static function (string $normalizedRef) use ($mediaMap, $embedMedia): ?string {
            $asset = $mediaMap[$normalizedRef] ?? null;
            if (!is_array($asset)) {
                return null;
            }

            return $embedMedia
                ? (string) ($asset['data_url'] ?? '')
                : (string) ($asset['path'] ?? '');
        });
    };

    $html = $rewriteMedia((string) ($version['html_code'] ?? ''));
    if ($engine === 'aframe') {
        $html = piece_aframe_normalize_texture_assets($html, static function (string $src) use ($mediaMap, $embedMedia): string {
            $asset = $mediaMap[$src] ?? null;
            if (!is_array($asset)) {
                return $src;
            }

            return $embedMedia
                ? (string) ($asset['data_url'] ?? $src)
                : (string) ($asset['path'] ?? $src);
        });
    }

    $css = $rewriteMedia((string) ($version['css_code'] ?? ''));
    $code = $rewriteMedia((string) ($version['generated_code'] ?? ''));
    $fullViewDocument = piece_export_document($piece, $version, [
        'runtime_mode' => 'bundle',
        'media_map' => $mediaMap,
        'embed_media' => true,
    ]);
    $aframeCaptureShim = $engine === 'aframe' ? piece_aframe_capture_context_shim() : '';

    $jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    $jsonEngine = json_encode($engine, $jsonFlags);
    $jsonGenerationMode = json_encode($generationMode, $jsonFlags);
    $jsonTitle = json_encode((string) ($piece['title'] ?? 'Art piece'), $jsonFlags);
    $jsonHtml = json_encode($html, $jsonFlags);
    $jsonCss = json_encode($css, $jsonFlags);
    $jsonCode = json_encode($code, $jsonFlags);
    $jsonFullView = json_encode($fullViewDocument, $jsonFlags);
    $jsonViewState = json_encode($viewState, $jsonFlags);
    $pngFilename = json_encode(piece_export_png_filename($piece), $jsonFlags);
    $jsonEmbeddedThree = json_encode(piece_export_runtime_source_file('assets/vendor/piece-runtime/three/three.module.js'), $jsonFlags);
    $jsonEmbeddedOrbitControls = json_encode(piece_export_patched_orbitcontrols_source(), $jsonFlags);
    $jsonEmbeddedDeviceOrientation = json_encode(piece_export_patched_device_orientation_source(), $jsonFlags);
    $jsonEmbeddedImmersiveGallery = json_encode(piece_export_patched_immersive_gallery_source(), $jsonFlags);
    $downloadBridgeScript = piece_export_download_bridge_script();
    $hasSonic = !empty($version['sonic_params']);
    $jsonSonic = json_encode($hasSonic ? json_decode((string) $version['sonic_params'], true) : null, $jsonFlags);

    // Shared top toolbar — identical placement/appearance to the live
    // immersive surfaces. Three/A-Frame pieces have no gallery full view, so
    // they render no view button; the download menu is PNG-only because a
    // standalone export cannot re-download itself offline.
    $isInteractiveC2 = $generationMode === 'c2_interactive';
    $toolbarCss = immersive_stage_toolbar_css();
    $toolbarMarkup = immersive_stage_toolbar_markup([
        'view_action' => !in_array($engine, ['three', 'aframe'], true) ? [
            'label' => $isInteractiveC2 ? 'Open interactive view' : 'View piece full size',
            'icon' => $isInteractiveC2 ? 'interactive' : 'view',
        ] : null,
        'download_items' => [[
            'tag' => 'button',
            'label' => public_copy_value('public_art_copy.shared_ui.download_png_label'),
            'icon' => 'png',
            'attrs' => [
                'data-immersive-download-png' => true,
                'data-download-filename' => piece_export_png_filename($piece),
            ],
        ]],
        'sound_action' => $hasSonic ? ['enabled' => true] : null,
        'show_fullscreen' => true,
        'fullscreen_onclick' => null,
    ]);

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="creatr-piece-export" content="portable-immersive-bundle">
<title>{$title}</title>
{$aframeCaptureShim}
<style>
html,body{margin:0;padding:0;width:100%;height:100%;overflow:hidden;background:#05070f;color:#f8f5ee;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}
#immersive-stage{position:fixed;inset:0;width:100vw;height:100dvh;background:#000;overflow:hidden;}
#piece-error{position:fixed;left:1rem;right:1rem;bottom:5rem;z-index:220;display:none;padding:0.8rem 1rem;border:1px solid #fca5a5;border-radius:0.75rem;background:#450a0a;color:#fee2e2;font:13px/1.4 ui-monospace,SFMono-Regular,Menlo,monospace;white-space:pre-wrap;}
{$toolbarCss}
.immersive-stage-toolbar{position:fixed;}
</style>
</head>
<body>
<div id="immersive-stage" tabindex="-1"></div>
<div id="piece-error" role="alert"></div>
{$toolbarMarkup}
<script>
function showPieceError(error){const el=document.getElementById('piece-error');if(!el)return;el.textContent=(error&&(error.stack||error.message))?(error.stack||error.message):String(error);el.style.display='block';}
window.addEventListener('error',event=>showPieceError(event.error||event.message));
window.addEventListener('unhandledrejection',event=>showPieceError(event.reason||'Unhandled promise rejection'));
</script>
<script>
{$downloadBridgeScript}
</script>
<script type="module">
const embeddedRuntimeSources = {
  three: {$jsonEmbeddedThree},
  orbitControls: {$jsonEmbeddedOrbitControls},
  deviceOrientation: {$jsonEmbeddedDeviceOrientation},
  immersiveGallery: {$jsonEmbeddedImmersiveGallery}
};

function createRuntimeModuleUrl(source) {
  return URL.createObjectURL(new Blob([source], { type: 'text/javascript' }));
}

async function loadImmersiveRuntime() {
  try {
    return await import('./runtime/immersive-gallery.js');
  } catch (error) {
    const threeUrl = createRuntimeModuleUrl(embeddedRuntimeSources.three);
    const orbitUrl = createRuntimeModuleUrl(embeddedRuntimeSources.orbitControls.replace("from '../../three.module.js';", `from '\${threeUrl}';`));
    const deviceUrl = createRuntimeModuleUrl(embeddedRuntimeSources.deviceOrientation.replace("'./three/three.module.js'", `'\${threeUrl}'`));
    const gallerySource = embeddedRuntimeSources.immersiveGallery
      .replace("from './three/three.module.js';", `from '\${threeUrl}';`)
      .replace("from './three/addons/controls/OrbitControls.js';", `from '\${orbitUrl}';`)
      .replace('await import("./three-device-orientation-controls.js")', `await import('\${deviceUrl}')`);
    return await import(createRuntimeModuleUrl(gallerySource));
  }
}

const { mountAFrameImmersivePiece, mountGalleryPiece, mountThreeImmersivePiece, setupImmersiveStageChrome } = await loadImmersiveRuntime();

const piece = {
  engine: {$jsonEngine},
  generationMode: {$jsonGenerationMode},
  title: {$jsonTitle},
  html: {$jsonHtml},
  css: {$jsonCss},
  code: {$jsonCode},
  fullViewSrcdoc: {$jsonFullView},
  initialViewState: {$jsonViewState},
  pngFilename: {$pngFilename},
  sonicParams: {$jsonSonic}
};
const stage = document.getElementById('immersive-stage');
const fullscreenBtn = document.getElementById('fullscreen-toggle-btn');
const pngBtn = document.querySelector('[data-immersive-download-png]');
let viewer = null;

try {
  const controls = { showViewerControls: true, initialViewState: piece.initialViewState, sonicParams: piece.sonicParams };
  if (piece.engine === 'three') {
    viewer = mountThreeImmersivePiece(stage, piece.code, piece.html, piece.css, showPieceError, controls);
  } else if (piece.engine === 'aframe') {
    viewer = mountAFrameImmersivePiece(stage, piece.code, piece.html, piece.css, showPieceError, controls);
  } else {
    const isInteractiveC2 = piece.generationMode === 'c2_interactive';
    viewer = mountGalleryPiece(stage, piece.code, piece.html, piece.css, piece.engine, piece.title, '', '', '', showPieceError, null, {
      ...controls,
      fullView: {
        items: [{ type: 'iframe', srcdoc: piece.fullViewSrcdoc, interactive: isInteractiveC2, title: piece.title }],
        overlayOptions: { showDownloadControls: false }
      }
    });
  }
  setupImmersiveStageChrome(stage, {
    onViewAction() {
      viewer?.openFullViewAt?.(0);
    }
  });
} catch (error) {
  showPieceError(error);
}

fullscreenBtn?.addEventListener('click', async () => {
  try {
    if (document.fullscreenElement) {
      await document.exitFullscreen();
    } else if (document.documentElement.requestFullscreen) {
      await document.documentElement.requestFullscreen();
    }
  } catch (_) {}
});
document.addEventListener('fullscreenchange', () => {
  fullscreenBtn?.setAttribute('aria-label', document.fullscreenElement ? 'Exit fullscreen' : 'Enter fullscreen');
});

function wait(ms) { return new Promise((resolve) => setTimeout(resolve, ms)); }
function hasVisiblePixels(canvas) {
  const context = canvas.getContext('2d');
  if (!context) return false;
  const width = Math.max(1, canvas.width || 1);
  const height = Math.max(1, canvas.height || 1);
  for (let y = 0; y < Math.min(4, height); y++) {
    for (let x = 0; x < Math.min(4, width); x++) {
      const px = context.getImageData(Math.floor((x / 4) * width), Math.floor((y / 4) * height), 1, 1).data;
      if (px[3] !== 0 || px[0] !== 0 || px[1] !== 0 || px[2] !== 0) return true;
    }
  }
  return false;
}
async function canvasToBlob(canvas) {
  return new Promise((resolve, reject) => {
    canvas.toBlob((blob) => blob ? resolve(blob) : reject(new Error('Could not create the PNG download.')), 'image/png');
  });
}
function downloadBlob(blob, filename) {
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}
function exportCanvas(canvas) {
  const width = Math.max(1, canvas.width || Math.round(canvas.getBoundingClientRect().width) || 1);
  const height = Math.max(1, canvas.height || Math.round(canvas.getBoundingClientRect().height) || 1);
  const out = document.createElement('canvas');
  out.width = width;
  out.height = height;
  const context = out.getContext('2d');
  if (!context) throw new Error('PNG export is unavailable in this browser.');
  context.drawImage(canvas, 0, 0, width, height);
  return out;
}
pngBtn?.addEventListener('click', async () => {
  if (pngBtn.disabled) return;
  const labelEl = pngBtn.querySelector('span') || pngBtn;
  const label = labelEl.textContent;
  pngBtn.disabled = true;
  labelEl.textContent = 'Preparing PNG...';
  try {
    let surface = null;
    // Full-view overlay open: snapshot the user's current state from the
    // overlay iframe instead (covers interactive C2 pieces too).
    if (viewer?.isFullViewOpen?.()) {
      const overlayFrame = document.querySelector('[data-full-view-viewport] iframe');
      if (overlayFrame?.contentDocument) {
        surface = Array.from(overlayFrame.contentDocument.querySelectorAll('canvas')).find((canvas) => canvas.getBoundingClientRect().width > 0 && canvas.getBoundingClientRect().height > 0);
      }
    }
    if (!surface) {
      const capture = viewer?.getCaptureSurface?.();
      capture?.beforeCapture?.();
      surface = capture?.canvas || null;
    }
    if (!surface) throw new Error('No downloadable canvas is available yet.');
    let exported = exportCanvas(surface);
    if (!hasVisiblePixels(exported)) {
      await wait(120);
      viewer?.getCaptureSurface?.()?.beforeCapture?.();
      exported = exportCanvas(surface);
    }
    if (!hasVisiblePixels(exported)) throw new Error('Could not produce a non-blank PNG right now.');
    downloadBlob(await canvasToBlob(exported), piece.pngFilename);
  } catch (error) {
    showPieceError(error);
  } finally {
    pngBtn.disabled = false;
    labelEl.textContent = label;
  }
});
</script>
</body>
</html>
HTML;
}

function piece_request_origin(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function piece_aframe_add_crossorigin_to_asset_images(string $html): string
{
    if ($html === '' || stripos($html, '<img') === false) {
        return $html;
    }

    return preg_replace_callback('/<img\b[^>]*>/i', static function (array $match): string {
        $tag = $match[0];
        if (preg_match('/\bcrossorigin\s*=/i', $tag)) {
            return $tag;
        }
        return preg_replace('/\s*\/?>$/', ' crossorigin="anonymous"$0', $tag, 1) ?? $tag;
    }, $html) ?? $html;
}

function piece_aframe_normalize_texture_assets(string $html, callable $resolver): string
{
    if ($html === '' || stripos($html, '<a-scene') === false) {
        return $html;
    }

    $dom = new DOMDocument('1.0', 'UTF-8');
    $wrapped = '<div id="creatr-aframe-root">' . $html . '</div>';
    $internalErrors = libxml_use_internal_errors(true);
    $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($internalErrors);
    if (!$loaded) {
        return $html;
    }

    $xpath = new DOMXPath($dom);
    $root = $xpath->query('//*[@id="creatr-aframe-root"]')->item(0);
    $scene = $xpath->query('.//a-scene', $root)->item(0);
    if (!$root instanceof DOMElement || !$scene instanceof DOMElement) {
        return $html;
    }

    $assets = $xpath->query('.//a-assets', $scene)->item(0);
    if (!$assets instanceof DOMElement) {
        $assets = $dom->createElement('a-assets');
        if ($scene->firstChild) {
            $scene->insertBefore($assets, $scene->firstChild);
        } else {
            $scene->appendChild($assets);
        }
    }

    $assetMap = [];
    foreach ($xpath->query('.//img[@id]', $assets) as $imgNode) {
        if (!$imgNode instanceof DOMElement) {
            continue;
        }

        $assetId = trim($imgNode->getAttribute('id'));
        $src = trim($imgNode->getAttribute('src'));
        if ($assetId !== '') {
            $assetMap[$src] = $assetId;
        }
        if ($src !== '' && str_starts_with($src, '/')) {
            $imgNode->setAttribute('src', $resolver($src));
        }
    }

    $nextAssetNumber = 1;
    $ensureAsset = static function (string $src) use ($dom, $assets, &$assetMap, &$nextAssetNumber, $resolver): string {
        if (isset($assetMap[$src])) {
            return $assetMap[$src];
        }

        do {
            $assetId = 'creatr-export-asset-' . $nextAssetNumber;
            $nextAssetNumber += 1;
        } while (in_array($assetId, $assetMap, true));

        $img = $dom->createElement('img');
        $img->setAttribute('id', $assetId);
        $img->setAttribute('src', $resolver($src));
        $img->setAttribute('crossorigin', 'anonymous');
        $assets->appendChild($img);
        $assetMap[$src] = $assetId;

        return $assetId;
    };

    foreach ($xpath->query('.//*[@src]', $scene) as $node) {
        if (!$node instanceof DOMElement) {
            continue;
        }

        if (strtolower($node->tagName) === 'img') {
            $src = trim($node->getAttribute('src'));
            if ($src !== '' && str_starts_with($src, '/')) {
                $node->setAttribute('src', $resolver($src));
            }
            continue;
        }

        $src = trim($node->getAttribute('src'));
        if ($src === '' || !str_starts_with($src, '/')) {
            continue;
        }

        $node->setAttribute('src', '#' . $ensureAsset($src));
    }

    foreach ($xpath->query('.//*[@material]', $scene) as $node) {
        if (!$node instanceof DOMElement) {
            continue;
        }

        $material = (string) $node->getAttribute('material');
        if (!preg_match('/\bsrc\s*:\s*([^;]+)/i', $material, $matches)) {
            continue;
        }

        $src = trim((string) ($matches[1] ?? ''));
        if ($src === '' || !str_starts_with($src, '/')) {
            continue;
        }

        $replacement = '#' . $ensureAsset($src);
        $updatedMaterial = preg_replace('/(\bsrc\s*:\s*)([^;]+)/i', '$1' . $replacement, $material, 1);
        if (is_string($updatedMaterial) && $updatedMaterial !== '') {
            $node->setAttribute('material', $updatedMaterial);
        }
    }

    $result = '';
    foreach ($root->childNodes as $child) {
        $result .= $dom->saveHTML($child);
    }

    return $result !== '' ? $result : $html;
}

function piece_export_imports(string $engine, string $runtimeMode = 'cdn'): string
{
    if ($runtimeMode === 'bundle') {
        return '';
    }

    return match ($engine) {
        'p5' => '<script src="https://cdnjs.cloudflare.com/ajax/libs/p5.js/1.9.0/p5.min.js"></script>',
        'c2' => '<script src="https://cdn.jsdelivr.net/npm/c2.js@1.0.9/dist/c2.min.js"></script>',
        'three' => '<script type="importmap">' . "\n" . '{"imports":{"three":"https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js","three/addons/":"https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"}}' . "\n" . '</script>',
        'aframe' => '<script src="https://aframe.io/releases/1.6.0/aframe.min.js"></script>',
        default => '',
    };
}

function piece_export_inline_runtime_markup(string $engine): string
{
    $engine = strtolower($engine);
    if ($engine === 'three' || $engine === 'svg') {
        return '';
    }

    $source = piece_export_runtime_inline_source($engine);
    if ($source === '') {
        return '';
    }

    return "<script>\n" . piece_escape_inline_script($source) . "\n</script>";
}

function piece_export_runtime_inline_source(string $engine): string
{
    $publicRoot = dirname(__DIR__, 2);
    $vendorRoot = $publicRoot . '/assets/vendor/piece-runtime';

    $path = match (strtolower($engine)) {
        'p5' => $vendorRoot . '/p5/p5.min.js',
        'c2' => $vendorRoot . '/c2/c2.min.js',
        'aframe' => $publicRoot . '/assets/js/aframe.min.js',
        default => '',
    };

    if ($path === '') {
        return '';
    }

    $source = @file_get_contents($path);
    if ($source === false) {
        throw new RuntimeException('Missing vendored runtime source for piece export: ' . $path);
    }

    return $source;
}

function piece_export_runtime_source_file(string $relativePath): string
{
    $publicRoot = dirname(__DIR__, 2);
    $path = $publicRoot . '/' . ltrim($relativePath, '/');
    $source = @file_get_contents($path);
    if ($source === false) {
        throw new RuntimeException('Missing vendored runtime source for piece export: ' . $path);
    }

    return $source;
}

function piece_export_three_orbitcontrols_inline_source(): string
{
    $source = piece_export_runtime_source_file('assets/vendor/piece-runtime/three/OrbitControls.js');
    $source = preg_replace(
        '/from\s+[\'"]three[\'"]\s*;/',
        "from '__CREATR_THREE_BLOB__';",
        $source
    ) ?? $source;
    return $source;
}

function piece_export_download_bridge_script(): string
{
    return piece_escape_inline_script(
        piece_export_runtime_source_file('assets/js/public-piece-download.js')
    );
}

/**
 * c2/c2_interactive bootstrap for the standalone export. Mirrors
 * piece-runtime.js's bootCanvasRuntime(): c2_interactive pieces get a
 * pointer-position mover normalized to ~0..1 (same order of magnitude as
 * the three/aframe camera-position deltas createPieceRuntimeAudioController
 * was tuned against) wired to window.__creatrSonicSetMover, so a downloaded
 * c2_interactive piece with sound enabled gets pointer-modulated pitch
 * offline, not just the idle random-note pattern. Plain c2 has no motion
 * signal and is left exactly as before.
 */
function piece_export_c2_bootstrap_script(bool $interactive): string
{
    $pointerWiring = $interactive ? <<<'JS'
  if (window.__creatrSonicSetMover) {
    const c2Mover = { position: { x: 0, y: 0, z: 0 } };
    const updateC2Mover = (clientX, clientY) => {
      const rect = canvas.getBoundingClientRect();
      if (!rect.width || !rect.height) return;
      c2Mover.position.x = (clientX - rect.left) / rect.width;
      c2Mover.position.y = (clientY - rect.top) / rect.height;
    };
    canvas.addEventListener('pointermove', (e) => updateC2Mover(e.clientX, e.clientY));
    canvas.addEventListener('touchmove', (e) => {
      const t = e.touches && e.touches[0];
      if (t) updateC2Mover(t.clientX, t.clientY);
    }, { passive: true });
    window.__creatrSonicSetMover(() => c2Mover);
  }

JS
        : '';

    return <<<HTML
<script>
try {
  const canvas = document.getElementById('piece-canvas') || document.querySelector('canvas');
  const context = canvas.getContext('2d');
  const imageCache = new Map();
  function sizeCanvas() {
    const rect = canvas.getBoundingClientRect();
    canvas.width = Math.max(1, Math.floor(rect.width || window.innerWidth || 1280));
    canvas.height = Math.max(1, Math.floor(rect.height || window.innerHeight || 720));
  }
  function startFrame(callback) {
    let count = 0;
    function tick() {
      count++;
      try { callback(count); } catch (error) { showPieceError(error); return; }
      requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  }
  function loadImage(src) {
    if (imageCache.has(src)) return imageCache.get(src);
    const image = new Image();
    image.decoding = 'async';
    image.loading = 'eager';
    image.dataset.creatrLoaded = '0';
    const loaded = new Promise((resolve, reject) => {
      image.onload = () => { image.dataset.creatrLoaded = '1'; resolve(image); };
      image.onerror = () => {
        const message = 'Could not load image asset: ' + src;
        showPieceError(message);
        reject(new Error(message));
      };
    });
    loaded.catch(() => {});
    loaded.__creatrImage = image;
    image.src = src;
    imageCache.set(src, loaded);
    return loaded;
  }
  function resolveImageRef(image) {
    return image && image.__creatrImage ? image.__creatrImage : image;
  }
  function drawImage(image, x, y, width, height) {
    image = resolveImageRef(image);
    if (!image || image.dataset?.creatrLoaded !== '1') return false;
    context.drawImage(image, x, y, width, height);
    return true;
  }
  function drawImageCover(image, x, y, width, height) {
    image = resolveImageRef(image);
    if (!image || image.dataset?.creatrLoaded !== '1') return false;
    const sourceWidth = image.naturalWidth || image.width;
    const sourceHeight = image.naturalHeight || image.height;
    if (!sourceWidth || !sourceHeight || !width || !height) return false;
    const sourceAspect = sourceWidth / sourceHeight;
    const targetAspect = width / height;
    let sx = 0, sy = 0, sw = sourceWidth, sh = sourceHeight;
    if (sourceAspect > targetAspect) { sw = sourceHeight * targetAspect; sx = (sourceWidth - sw) / 2; }
    else { sh = sourceWidth / targetAspect; sy = (sourceHeight - sh) / 2; }
    context.drawImage(image, sx, sy, sw, sh, x, y, width, height);
    return true;
  }
  canvas.style.touchAction = 'none';
  sizeCanvas();
  window.addEventListener('resize', sizeCanvas);
{$pointerWiring}  if (typeof window.sketch === 'function') window.sketch({ c2: window.c2, canvas, startFrame, loadImage, drawImage, drawImageCover });
} catch (error) { showPieceError(error); }
</script>
HTML;
}

function piece_export_bootstrap(string $engine, string $generationMode = '', string $runtimeMode = 'cdn'): string
{
    if ($engine === 'three' && $runtimeMode === 'bundle') {
        $threeSource = json_encode(
            piece_export_runtime_source_file('assets/vendor/piece-runtime/three/three.module.js'),
            JSON_UNESCAPED_UNICODE
        );
        $orbitSource = json_encode(
            piece_export_three_orbitcontrols_inline_source(),
            JSON_UNESCAPED_UNICODE
        );

        return <<<HTML
<script type="module">
const creatrThreeSource = {$threeSource};
const creatrOrbitSource = {$orbitSource};
const creatrThreeUrl = URL.createObjectURL(new Blob([creatrThreeSource], { type: 'text/javascript' }));
const creatrOrbitPatched = creatrOrbitSource.replace(/__CREATR_THREE_BLOB__/g, creatrThreeUrl);
const creatrOrbitUrl = URL.createObjectURL(new Blob([creatrOrbitPatched], { type: 'text/javascript' }));
try {
  const THREE = await import(creatrThreeUrl);
  const { OrbitControls } = await import(creatrOrbitUrl);
  const canvas = document.getElementById('scene') || document.querySelector('canvas') || (() => {
    const created = document.createElement('canvas');
    created.id = 'scene';
    (document.getElementById('container') || document.getElementById('runtime-root')).appendChild(created);
    return created;
  })();
  canvas.style.display = 'block';
  canvas.style.width = '100%';
  canvas.style.height = '100%';
  canvas.style.touchAction = 'none';
  canvas.addEventListener('webglcontextlost', (event) => {
    event.preventDefault();
    showPieceError('WebGL context was lost. The scene may be too complex for this browser.');
  });
  function sizeCanvas() {
    const parent = canvas.parentElement || document.getElementById('runtime-root');
    canvas.width = Math.max(1, parent.clientWidth || window.innerWidth || 1280);
    canvas.height = Math.max(1, parent.clientHeight || window.innerHeight || 720);
  }
  const state = { scene: null, camera: null, renderer: null };
  const instrumentedThree = { ...THREE };
  instrumentedThree.Scene = class extends THREE.Scene {
    constructor(...args) { super(...args); state.scene = this; }
  };
  instrumentedThree.PerspectiveCamera = class extends THREE.PerspectiveCamera {
    constructor(...args) { super(...args); state.camera = this; }
  };
  instrumentedThree.WebGLRenderer = class extends THREE.WebGLRenderer {
    constructor(params) {
      super({ ...(params || {}), canvas, preserveDrawingBuffer: true });
      state.renderer = this;
      const originalSetSize = this.setSize.bind(this);
      this.setSize = (width, height) => originalSetSize(width, height, false);
      const originalRender = this.render.bind(this);
      this.render = (scene, camera) => {
        if (scene) state.scene = scene;
        if (camera) state.camera = camera;
        return originalRender(scene, camera);
      };
    }
  };
  let pieceDrivesOwnRender = false;
  let rafIds = [];
  function startFrame(callback) {
    pieceDrivesOwnRender = true;
    let count = 0;
    function tick() {
      count++;
      try {
        callback(count);
      } catch (error) {
        pieceDrivesOwnRender = false;
        showPieceError(error);
        return;
      }
      const id = requestAnimationFrame(tick);
      rafIds.push(id);
    }
    const id = requestAnimationFrame(tick);
    rafIds.push(id);
    return () => { rafIds.forEach((rafId) => cancelAnimationFrame(rafId)); rafIds = []; };
  }
  sizeCanvas();
  window.THREE = instrumentedThree;
  if (typeof window.sketch === 'function') window.sketch({ THREE: instrumentedThree, canvas, startFrame, width: canvas.width, height: canvas.height, size: { width: canvas.width, height: canvas.height }, OrbitControls });
  if (state.camera && state.renderer && state.scene) {
    const controls = new OrbitControls(state.camera, canvas);
    controls.enableDamping = true;
    controls.enablePan = true;
    const threeRaycaster = new THREE.Raycaster();
    const pointerState = new Map();
    const keyboardKeys = new Set();
    let hadMultiTouchGesture = false;
    let lastKeyUpdateAt = null;
    let threeNavLimit = 5;
    let animFromTarget = null;
    let animToTarget = null;
    let animFromCam = null;
    let animToCam = null;
    let animStart = 0;
    let isOrbitActive = false;
    let userHasInteracted = false;

    function getThreeNavigationLimit() {
      const box = new THREE.Box3();
      if (state.scene?.traverse) {
        state.scene.traverse((obj) => {
          if (obj.isHelper || obj.isLight || obj.isCamera) return;
          if ((obj.isMesh || obj.isLine || obj.isPoints || obj.isSprite) && obj.geometry) {
            obj.geometry.computeBoundingBox?.();
            if (obj.geometry.boundingBox) box.union(obj.geometry.boundingBox.clone().applyMatrix4(obj.matrixWorld));
          }
        });
      }
      if (box.isEmpty()) {
        try { box.setFromObject(state.scene); } catch (_) { return 5; }
      }
      if (box.isEmpty()) return 5;
      const size = new THREE.Vector3();
      box.getSize(size);
      return Math.max(size.x, size.z, 1) * 0.7;
    }

    function mapMovementKey(event) {
      if (event.code === 'KeyW') return 'ArrowUp';
      if (event.code === 'KeyS') return 'ArrowDown';
      if (event.code === 'KeyA') return 'ArrowLeft';
      if (event.code === 'KeyD') return 'ArrowRight';
      if (event.key === 'ArrowLeft' || event.key === 'ArrowRight' || event.key === 'ArrowUp' || event.key === 'ArrowDown') return event.key;
      return null;
    }

    function shouldIgnoreKeyEventTarget(eventTarget) {
      if (!(eventTarget instanceof Element)) return false;
      if (eventTarget.tagName === 'IFRAME') return true;
      if (eventTarget instanceof HTMLInputElement || eventTarget instanceof HTMLTextAreaElement || eventTarget instanceof HTMLSelectElement) return true;
      if (eventTarget.isContentEditable) return true;
      return Boolean(eventTarget.closest('[contenteditable="true"], [contenteditable=""], input, textarea, select'));
    }

    function computeOrbitKeyboardMotion(forward, keys, speed) {
      let fwdScale = 0, rightScale = 0;
      if (keys.has('ArrowUp')) fwdScale += speed;
      if (keys.has('ArrowDown')) fwdScale -= speed;
      if (keys.has('ArrowLeft')) rightScale -= speed;
      if (keys.has('ArrowRight')) rightScale += speed;
      if (fwdScale === 0 && rightScale === 0) return { dx: 0, dy: 0, dz: 0 };
      const horizontalLength = Math.sqrt(forward.x ** 2 + forward.z ** 2);
      const right = horizontalLength > 1e-6
        ? { x: -forward.z / horizontalLength, y: 0, z: forward.x / horizontalLength }
        : { x: 1, y: 0, z: 0 };
      return {
        dx: (forward.x * fwdScale) + (right.x * rightScale),
        dy: forward.y * fwdScale,
        dz: (forward.z * fwdScale) + (right.z * rightScale),
      };
    }

    function activeTouchPointerCount() {
      let count = 0;
      pointerState.forEach((pointer) => {
        if (pointer.pointerType === 'touch') count += 1;
      });
      return count;
    }

    function cancelThreeNavigationAnimation() {
      animFromTarget = animToTarget = animFromCam = animToCam = null;
      controls.enabled = true;
    }

    function moveThreeOrbitTo(hitPoint) {
      if (!hitPoint) return;
      const dx = hitPoint.x - controls.target.x;
      const dz = hitPoint.z - controls.target.z;
      const shift = new THREE.Vector3(
        Math.max(-threeNavLimit, Math.min(threeNavLimit, dx)),
        0,
        Math.max(-threeNavLimit, Math.min(threeNavLimit, dz)),
      );
      if (shift.lengthSq() < 0.003) return;
      cancelThreeNavigationAnimation();
      animFromTarget = controls.target.clone();
      animToTarget = animFromTarget.clone().add(shift);
      animFromCam = state.camera.position.clone();
      animToCam = animFromCam.clone().add(shift);
      animStart = performance.now();
      controls.enabled = false;
    }

    function onKeyDown(event) {
      const mappedKey = mapMovementKey(event);
      if (!mappedKey || shouldIgnoreKeyEventTarget(event.target)) return;
      event.preventDefault();
      keyboardKeys.add(mappedKey);
    }

    function onKeyUp(event) {
      const mappedKey = mapMovementKey(event);
      if (!mappedKey) return;
      keyboardKeys.delete(mappedKey);
      keyboardKeys.delete(event.key);
    }

    function onWindowBlur() {
      keyboardKeys.clear();
    }

    function updateKeyboardNavigation() {
      const now = performance.now();
      const TARGET_FRAME_MS = 1000 / 60;
      const MAX_FRAME_SCALE = 4;
      const frameScale = lastKeyUpdateAt === null
        ? 1
        : Math.min(MAX_FRAME_SCALE, Math.max(0, (now - lastKeyUpdateAt) / TARGET_FRAME_MS));
      lastKeyUpdateAt = now;
      if (!controls.enabled || keyboardKeys.size === 0) return false;
      const forward = new THREE.Vector3();
      controls.object.getWorldDirection(forward);
      const resolvedSpeed = Math.max(0.05, controls.target.distanceTo(controls.object.position) * 0.03) * frameScale;
      const { dx, dy, dz } = computeOrbitKeyboardMotion(forward, keyboardKeys, resolvedSpeed);
      const newCamX = Math.max(-threeNavLimit, Math.min(threeNavLimit, controls.object.position.x + dx));
      const newCamY = controls.object.position.y + dy;
      const newCamZ = Math.max(0.5, Math.min(threeNavLimit, controls.object.position.z + dz));
      const actualDx = newCamX - controls.object.position.x;
      const actualDy = newCamY - controls.object.position.y;
      const actualDz = newCamZ - controls.object.position.z;
      if (Math.abs(actualDx) < 1e-6 && Math.abs(actualDy) < 1e-6 && Math.abs(actualDz) < 1e-6) return false;
      controls.object.position.x = newCamX;
      controls.object.position.y = newCamY;
      controls.object.position.z = newCamZ;
      controls.target.x += actualDx;
      controls.target.y += actualDy;
      controls.target.z += actualDz;
      return true;
    }

    function onThreePointerDown(event) {
      pointerState.set(event.pointerId, {
        pointerType: event.pointerType || 'mouse',
        button: event.button,
        startX: event.clientX,
        startY: event.clientY,
        moved: false,
      });
      if ((event.pointerType || 'mouse') === 'touch' && activeTouchPointerCount() > 1) {
        hadMultiTouchGesture = true;
      }
    }

    function onThreePointerMove(event) {
      const pointer = pointerState.get(event.pointerId);
      if (!pointer) return;
      if (Math.hypot(event.clientX - pointer.startX, event.clientY - pointer.startY) >= 6) {
        pointer.moved = true;
      }
      if (pointer.pointerType === 'touch' && activeTouchPointerCount() > 1) {
        hadMultiTouchGesture = true;
      }
    }

    function clearThreePointer(event) {
      const pointer = pointerState.get(event.pointerId);
      pointerState.delete(event.pointerId);
      if (pointer?.pointerType === 'touch' && activeTouchPointerCount() === 0) {
        hadMultiTouchGesture = false;
      }
    }

    function onThreePointerUp(event) {
      const pointer = pointerState.get(event.pointerId);
      const wasMultiTouch = hadMultiTouchGesture || activeTouchPointerCount() > 1;
      clearThreePointer(event);
      if (!pointer || wasMultiTouch || pointer.button !== 0 || event.button !== 0 || pointer.moved) return;
      const rect = canvas.getBoundingClientRect();
      threeRaycaster.setFromCamera(
        new THREE.Vector2(((event.clientX - rect.left) / rect.width) * 2 - 1, -((event.clientY - rect.top) / rect.height) * 2 + 1),
        state.camera,
      );
      let hitPoint = null;
      if (state.scene?.children?.length) {
        const hits = threeRaycaster.intersectObjects(state.scene.children, true);
        if (hits.length > 0) hitPoint = hits[0].point;
      }
      const floorPlane = new THREE.Plane(new THREE.Vector3(0, 1, 0), 0);
      const planeHit = new THREE.Vector3();
      if (!hitPoint && threeRaycaster.ray.intersectPlane(floorPlane, planeHit)) {
        hitPoint = planeHit;
      }
      if (hitPoint) moveThreeOrbitTo(hitPoint);
    }

    threeNavLimit = getThreeNavigationLimit();
    canvas.tabIndex = 0;
    canvas.addEventListener('click', () => canvas.focus(), { passive: true });
    window.addEventListener('keydown', onKeyDown);
    window.addEventListener('keyup', onKeyUp);
    window.addEventListener('blur', onWindowBlur);
    canvas.addEventListener('pointerdown', onThreePointerDown);
    canvas.addEventListener('pointermove', onThreePointerMove);
    canvas.addEventListener('pointerup', onThreePointerUp);
    canvas.addEventListener('pointercancel', clearThreePointer);
    canvas.addEventListener('lostpointercapture', clearThreePointer);
    controls.addEventListener('start', () => { isOrbitActive = true; userHasInteracted = true; });
    controls.addEventListener('end', () => { isOrbitActive = false; });
    function animateControls() {
      const id = requestAnimationFrame(animateControls);
      rafIds.push(id);
      try {
        let externalMotion = false;
        if (animToTarget && animFromTarget) {
          const t = Math.min((performance.now() - animStart) / 350, 1);
          const eased = 1 - (1 - t) ** 3;
          controls.target.lerpVectors(animFromTarget, animToTarget, eased);
          state.camera.position.lerpVectors(animFromCam, animToCam, eased);
          externalMotion = true;
          if (t >= 1) {
            controls.enabled = true;
            animFromTarget = animToTarget = animFromCam = animToCam = null;
          }
        }
        if (updateKeyboardNavigation()) externalMotion = true;
        controls.update();
        if (isOrbitActive || externalMotion) userHasInteracted = true;
        if (!pieceDrivesOwnRender || userHasInteracted) {
          state.renderer.render(state.scene, state.camera);
        }
      } catch (error) {
        showPieceError(error);
      }
    }
    animateControls();
    if (window.__creatrSonicSetMover) window.__creatrSonicSetMover(() => state.camera);
  }
  window.addEventListener('resize', () => {
    sizeCanvas();
    if (state.camera && state.renderer) {
      state.camera.aspect = canvas.width / canvas.height;
      state.camera.updateProjectionMatrix();
      state.renderer.setSize(canvas.width, canvas.height, false);
    }
  });
} catch (error) { showPieceError(error); }
finally {
  window.addEventListener('unload', () => {
    URL.revokeObjectURL(creatrThreeUrl);
    URL.revokeObjectURL(creatrOrbitUrl);
  }, { once: true });
}
</script>
HTML;
    }

    return match ($engine) {
        'p5' => <<<'HTML'
<script>
try {
  const parent = document.getElementById('canvas-container') || document.getElementById('runtime-root');
  if (typeof window.sketch === 'function' && typeof window.p5 === 'function') new window.p5(window.sketch, parent);
} catch (error) { showPieceError(error); }
</script>
HTML,
        'c2' => piece_export_c2_bootstrap_script($generationMode === 'c2_interactive'),
        'three' => <<<'HTML'
<script type="module">
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
try {
  const canvas = document.getElementById('scene') || document.querySelector('canvas') || (() => {
    const created = document.createElement('canvas');
    created.id = 'scene';
    (document.getElementById('container') || document.getElementById('runtime-root')).appendChild(created);
    return created;
  })();
  canvas.style.display = 'block';
  canvas.style.width = '100%';
  canvas.style.height = '100%';
  canvas.style.touchAction = 'none';
  canvas.addEventListener('webglcontextlost', (event) => {
    event.preventDefault();
    showPieceError('WebGL context was lost. The scene may be too complex for this browser.');
  });
  function sizeCanvas() {
    const parent = canvas.parentElement || document.getElementById('runtime-root');
    canvas.width = Math.max(1, parent.clientWidth || window.innerWidth || 1280);
    canvas.height = Math.max(1, parent.clientHeight || window.innerHeight || 720);
  }
  const state = { scene: null, camera: null, renderer: null };
  const instrumentedThree = { ...THREE };
  instrumentedThree.Scene = class extends THREE.Scene {
    constructor(...args) { super(...args); state.scene = this; }
  };
  instrumentedThree.PerspectiveCamera = class extends THREE.PerspectiveCamera {
    constructor(...args) { super(...args); state.camera = this; }
  };
  instrumentedThree.WebGLRenderer = class extends THREE.WebGLRenderer {
    constructor(params) {
      super({ ...(params || {}), canvas, preserveDrawingBuffer: true });
      state.renderer = this;
      const originalSetSize = this.setSize.bind(this);
      this.setSize = (width, height) => originalSetSize(width, height, false);
      const originalRender = this.render.bind(this);
      this.render = (scene, camera) => {
        if (scene) state.scene = scene;
        if (camera) state.camera = camera;
        return originalRender(scene, camera);
      };
    }
  };
  let pieceDrivesOwnRender = false;
  let rafIds = [];
  function startFrame(callback) {
    pieceDrivesOwnRender = true;
    let count = 0;
    function tick() {
      count++;
      try {
        callback(count);
      } catch (error) {
        pieceDrivesOwnRender = false;
        showPieceError(error);
        return;
      }
      const id = requestAnimationFrame(tick);
      rafIds.push(id);
    }
    const id = requestAnimationFrame(tick);
    rafIds.push(id);
    return () => { rafIds.forEach((rafId) => cancelAnimationFrame(rafId)); rafIds = []; };
  }
  sizeCanvas();
  window.THREE = instrumentedThree;
  if (typeof window.sketch === 'function') window.sketch({ THREE: instrumentedThree, canvas, startFrame, width: canvas.width, height: canvas.height, size: { width: canvas.width, height: canvas.height }, OrbitControls });
  if (state.camera && state.renderer && state.scene) {
    const controls = new OrbitControls(state.camera, canvas);
    controls.enableDamping = true;
    controls.enablePan = true;
    const threeRaycaster = new THREE.Raycaster();
    const pointerState = new Map();
    const keyboardKeys = new Set();
    let hadMultiTouchGesture = false;
    let lastKeyUpdateAt = null;
    let threeNavLimit = 5;
    let animFromTarget = null;
    let animToTarget = null;
    let animFromCam = null;
    let animToCam = null;
    let animStart = 0;
    let isOrbitActive = false;
    let userHasInteracted = false;

    function getThreeNavigationLimit() {
      const box = new THREE.Box3();
      if (state.scene?.traverse) {
        state.scene.traverse((obj) => {
          if (obj.isHelper || obj.isLight || obj.isCamera) return;
          if ((obj.isMesh || obj.isLine || obj.isPoints || obj.isSprite) && obj.geometry) {
            obj.geometry.computeBoundingBox?.();
            if (obj.geometry.boundingBox) box.union(obj.geometry.boundingBox.clone().applyMatrix4(obj.matrixWorld));
          }
        });
      }
      if (box.isEmpty()) {
        try { box.setFromObject(state.scene); } catch (_) { return 5; }
      }
      if (box.isEmpty()) return 5;
      const size = new THREE.Vector3();
      box.getSize(size);
      return Math.max(size.x, size.z, 1) * 0.7;
    }

    function mapMovementKey(event) {
      if (event.code === 'KeyW') return 'ArrowUp';
      if (event.code === 'KeyS') return 'ArrowDown';
      if (event.code === 'KeyA') return 'ArrowLeft';
      if (event.code === 'KeyD') return 'ArrowRight';
      if (event.key === 'ArrowLeft' || event.key === 'ArrowRight' || event.key === 'ArrowUp' || event.key === 'ArrowDown') return event.key;
      return null;
    }

    function shouldIgnoreKeyEventTarget(eventTarget) {
      if (!(eventTarget instanceof Element)) return false;
      if (eventTarget.tagName === 'IFRAME') return true;
      if (eventTarget instanceof HTMLInputElement || eventTarget instanceof HTMLTextAreaElement || eventTarget instanceof HTMLSelectElement) return true;
      if (eventTarget.isContentEditable) return true;
      return Boolean(eventTarget.closest('[contenteditable="true"], [contenteditable=""], input, textarea, select'));
    }

    function computeOrbitKeyboardMotion(forward, keys, speed) {
      let fwdScale = 0, rightScale = 0;
      if (keys.has('ArrowUp')) fwdScale += speed;
      if (keys.has('ArrowDown')) fwdScale -= speed;
      if (keys.has('ArrowLeft')) rightScale -= speed;
      if (keys.has('ArrowRight')) rightScale += speed;
      if (fwdScale === 0 && rightScale === 0) return { dx: 0, dy: 0, dz: 0 };
      const horizontalLength = Math.sqrt(forward.x ** 2 + forward.z ** 2);
      const right = horizontalLength > 1e-6
        ? { x: -forward.z / horizontalLength, y: 0, z: forward.x / horizontalLength }
        : { x: 1, y: 0, z: 0 };
      return {
        dx: (forward.x * fwdScale) + (right.x * rightScale),
        dy: forward.y * fwdScale,
        dz: (forward.z * fwdScale) + (right.z * rightScale),
      };
    }

    function activeTouchPointerCount() {
      let count = 0;
      pointerState.forEach((pointer) => {
        if (pointer.pointerType === 'touch') count += 1;
      });
      return count;
    }

    function cancelThreeNavigationAnimation() {
      animFromTarget = animToTarget = animFromCam = animToCam = null;
      controls.enabled = true;
    }

    function moveThreeOrbitTo(hitPoint) {
      if (!hitPoint) return;
      const dx = hitPoint.x - controls.target.x;
      const dz = hitPoint.z - controls.target.z;
      const shift = new THREE.Vector3(
        Math.max(-threeNavLimit, Math.min(threeNavLimit, dx)),
        0,
        Math.max(-threeNavLimit, Math.min(threeNavLimit, dz)),
      );
      if (shift.lengthSq() < 0.003) return;
      cancelThreeNavigationAnimation();
      animFromTarget = controls.target.clone();
      animToTarget = animFromTarget.clone().add(shift);
      animFromCam = state.camera.position.clone();
      animToCam = animFromCam.clone().add(shift);
      animStart = performance.now();
      controls.enabled = false;
    }

    function onKeyDown(event) {
      const mappedKey = mapMovementKey(event);
      if (!mappedKey || shouldIgnoreKeyEventTarget(event.target)) return;
      event.preventDefault();
      keyboardKeys.add(mappedKey);
    }

    function onKeyUp(event) {
      const mappedKey = mapMovementKey(event);
      if (!mappedKey) return;
      keyboardKeys.delete(mappedKey);
      keyboardKeys.delete(event.key);
    }

    function onWindowBlur() {
      keyboardKeys.clear();
    }

    function updateKeyboardNavigation() {
      const now = performance.now();
      const TARGET_FRAME_MS = 1000 / 60;
      const MAX_FRAME_SCALE = 4;
      const frameScale = lastKeyUpdateAt === null
        ? 1
        : Math.min(MAX_FRAME_SCALE, Math.max(0, (now - lastKeyUpdateAt) / TARGET_FRAME_MS));
      lastKeyUpdateAt = now;
      if (!controls.enabled || keyboardKeys.size === 0) return false;
      const forward = new THREE.Vector3();
      controls.object.getWorldDirection(forward);
      const resolvedSpeed = Math.max(0.05, controls.target.distanceTo(controls.object.position) * 0.03) * frameScale;
      const { dx, dy, dz } = computeOrbitKeyboardMotion(forward, keyboardKeys, resolvedSpeed);
      const newCamX = Math.max(-threeNavLimit, Math.min(threeNavLimit, controls.object.position.x + dx));
      const newCamY = controls.object.position.y + dy;
      const newCamZ = Math.max(0.5, Math.min(threeNavLimit, controls.object.position.z + dz));
      const actualDx = newCamX - controls.object.position.x;
      const actualDy = newCamY - controls.object.position.y;
      const actualDz = newCamZ - controls.object.position.z;
      if (Math.abs(actualDx) < 1e-6 && Math.abs(actualDy) < 1e-6 && Math.abs(actualDz) < 1e-6) return false;
      controls.object.position.x = newCamX;
      controls.object.position.y = newCamY;
      controls.object.position.z = newCamZ;
      controls.target.x += actualDx;
      controls.target.y += actualDy;
      controls.target.z += actualDz;
      return true;
    }

    function onThreePointerDown(event) {
      pointerState.set(event.pointerId, {
        pointerType: event.pointerType || 'mouse',
        button: event.button,
        startX: event.clientX,
        startY: event.clientY,
        moved: false,
      });
      if ((event.pointerType || 'mouse') === 'touch' && activeTouchPointerCount() > 1) {
        hadMultiTouchGesture = true;
      }
    }

    function onThreePointerMove(event) {
      const pointer = pointerState.get(event.pointerId);
      if (!pointer) return;
      if (Math.hypot(event.clientX - pointer.startX, event.clientY - pointer.startY) >= 6) {
        pointer.moved = true;
      }
      if (pointer.pointerType === 'touch' && activeTouchPointerCount() > 1) {
        hadMultiTouchGesture = true;
      }
    }

    function clearThreePointer(event) {
      const pointer = pointerState.get(event.pointerId);
      pointerState.delete(event.pointerId);
      if (pointer?.pointerType === 'touch' && activeTouchPointerCount() === 0) {
        hadMultiTouchGesture = false;
      }
    }

    function onThreePointerUp(event) {
      const pointer = pointerState.get(event.pointerId);
      const wasMultiTouch = hadMultiTouchGesture || activeTouchPointerCount() > 1;
      clearThreePointer(event);
      if (!pointer || wasMultiTouch || pointer.button !== 0 || event.button !== 0 || pointer.moved) return;
      const rect = canvas.getBoundingClientRect();
      threeRaycaster.setFromCamera(
        new THREE.Vector2(((event.clientX - rect.left) / rect.width) * 2 - 1, -((event.clientY - rect.top) / rect.height) * 2 + 1),
        state.camera,
      );
      let hitPoint = null;
      if (state.scene?.children?.length) {
        const hits = threeRaycaster.intersectObjects(state.scene.children, true);
        if (hits.length > 0) hitPoint = hits[0].point;
      }
      const floorPlane = new THREE.Plane(new THREE.Vector3(0, 1, 0), 0);
      const planeHit = new THREE.Vector3();
      if (!hitPoint && threeRaycaster.ray.intersectPlane(floorPlane, planeHit)) {
        hitPoint = planeHit;
      }
      if (hitPoint) moveThreeOrbitTo(hitPoint);
    }

    threeNavLimit = getThreeNavigationLimit();
    canvas.tabIndex = 0;
    canvas.addEventListener('click', () => canvas.focus(), { passive: true });
    window.addEventListener('keydown', onKeyDown);
    window.addEventListener('keyup', onKeyUp);
    window.addEventListener('blur', onWindowBlur);
    canvas.addEventListener('pointerdown', onThreePointerDown);
    canvas.addEventListener('pointermove', onThreePointerMove);
    canvas.addEventListener('pointerup', onThreePointerUp);
    canvas.addEventListener('pointercancel', clearThreePointer);
    canvas.addEventListener('lostpointercapture', clearThreePointer);
    controls.addEventListener('start', () => { isOrbitActive = true; userHasInteracted = true; });
    controls.addEventListener('end', () => { isOrbitActive = false; });
    function animateControls() {
      const id = requestAnimationFrame(animateControls);
      rafIds.push(id);
      try {
        let externalMotion = false;
        if (animToTarget && animFromTarget) {
          const t = Math.min((performance.now() - animStart) / 350, 1);
          const eased = 1 - (1 - t) ** 3;
          controls.target.lerpVectors(animFromTarget, animToTarget, eased);
          state.camera.position.lerpVectors(animFromCam, animToCam, eased);
          externalMotion = true;
          if (t >= 1) {
            controls.enabled = true;
            animFromTarget = animToTarget = animFromCam = animToCam = null;
          }
        }
        if (updateKeyboardNavigation()) externalMotion = true;
        controls.update();
        if (isOrbitActive || externalMotion) userHasInteracted = true;
        if (!pieceDrivesOwnRender || userHasInteracted) {
          state.renderer.render(state.scene, state.camera);
        }
      } catch (error) {
        showPieceError(error);
      }
    }
    animateControls();
    if (window.__creatrSonicSetMover) window.__creatrSonicSetMover(() => state.camera);
  }
  window.addEventListener('resize', () => {
    sizeCanvas();
    if (state.camera && state.renderer) {
      state.camera.aspect = canvas.width / canvas.height;
      state.camera.updateProjectionMatrix();
      state.renderer.setSize(canvas.width, canvas.height, false);
    }
  });
} catch (error) { showPieceError(error); }
</script>
HTML,
        'aframe' => <<<'HTML'
<script>
try {
  const scene = document.querySelector('a-scene#scene') || document.querySelector('a-scene');
  if (scene) {
    const currentRenderer = scene.getAttribute('renderer');
    const rendererValue = typeof currentRenderer === 'string' && currentRenderer.trim() !== ''
      ? currentRenderer.replace(/\s*;?\s*$/, '; ') + 'preserveDrawingBuffer: true'
      : 'preserveDrawingBuffer: true';
    scene.setAttribute('renderer', rendererValue);
  }
  function startFrame(callback) {
    let count = 0;
    function tick() {
      count++;
      try { callback(count); } catch (error) { showPieceError(error); return; }
      requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  }
  if (scene && typeof window.sketch === 'function') window.sketch({ AFRAME: window.AFRAME, scene, startFrame });
  if (scene) {
    let pointerTarget = null;
    let frameId = 0;
    const aframeNav = {
      animFrom: null,
      animTo: null,
      animStart: 0,
      pointer: null,
      hadMultiTouch: false,
      activeTouches: new Set(),
    };

    function getAFrameThree() {
      return window.AFRAME?.THREE || window.THREE;
    }

    function getAFrameCameraObject() {
      if (scene.camera) return scene.camera;
      const cameraEl = scene.querySelector('[camera]') || scene.querySelector('a-camera');
      return cameraEl?.object3D || null;
    }

    function getAFrameCameraMover() {
      const cameraObject = getAFrameCameraObject();
      if (!cameraObject) return null;
      const cameraEl = cameraObject.el || scene.querySelector('[camera]') || scene.querySelector('a-camera');
      return cameraEl?.object3D || cameraObject;
    }

    function activeAFrameTouchCount() {
      return aframeNav.activeTouches.size;
    }

    function onAFramePointerDown(event) {
      if ((event.pointerType || 'mouse') === 'touch') {
        aframeNav.activeTouches.add(event.pointerId);
        if (activeAFrameTouchCount() > 1) aframeNav.hadMultiTouch = true;
      }
      aframeNav.pointer = {
        id: event.pointerId,
        pointerType: event.pointerType || 'mouse',
        button: event.button,
        startX: event.clientX,
        startY: event.clientY,
        moved: false,
      };
    }

    function onAFramePointerMove(event) {
      if (!aframeNav.pointer || aframeNav.pointer.id !== event.pointerId) return;
      if (Math.hypot(event.clientX - aframeNav.pointer.startX, event.clientY - aframeNav.pointer.startY) >= 6) {
        aframeNav.pointer.moved = true;
      }
      if ((event.pointerType || 'mouse') === 'touch' && activeAFrameTouchCount() > 1) {
        aframeNav.hadMultiTouch = true;
      }
    }

    function clearAFramePointer(event) {
      if ((event.pointerType || 'mouse') === 'touch') {
        aframeNav.activeTouches.delete(event.pointerId);
        if (activeAFrameTouchCount() === 0) aframeNav.hadMultiTouch = false;
      }
      if (aframeNav.pointer?.id === event.pointerId) {
        aframeNav.pointer = null;
      }
    }

    function moveAFrameViewTo(hitPoint) {
      const THREE_NS = getAFrameThree();
      const mover = getAFrameCameraMover();
      if (!THREE_NS || !mover || !hitPoint) return;
      const cameraWorld = new THREE_NS.Vector3();
      mover.getWorldPosition(cameraWorld);
      const shift = new THREE_NS.Vector3(
        Math.max(-12, Math.min(12, hitPoint.x - cameraWorld.x)),
        0,
        Math.max(-12, Math.min(12, hitPoint.z - cameraWorld.z)),
      );
      if (shift.lengthSq() < 0.003) return;
      aframeNav.animFrom = cameraWorld.clone();
      aframeNav.animTo = cameraWorld.clone().add(shift);
      aframeNav.animStart = performance.now();
    }

    function onAFramePointerUp(event) {
      const THREE_NS = getAFrameThree();
      const pointer = aframeNav.pointer;
      const wasMultiTouch = aframeNav.hadMultiTouch || activeAFrameTouchCount() > 1;
      clearAFramePointer(event);
      if (!THREE_NS || !pointer || wasMultiTouch || pointer.button !== 0 || event.button !== 0 || pointer.moved) return;
      const cameraObject = getAFrameCameraObject();
      if (!cameraObject) return;
      const rect = (pointerTarget || scene.canvas || scene).getBoundingClientRect();
      const raycaster = new THREE_NS.Raycaster();
      raycaster.setFromCamera(
        new THREE_NS.Vector2(((event.clientX - rect.left) / rect.width) * 2 - 1, -((event.clientY - rect.top) / rect.height) * 2 + 1),
        cameraObject,
      );

      let hitPoint = null;
      const hits = raycaster.intersectObjects(scene.object3D?.children || [], true)
        .filter((hit) => {
          if (hit.object === cameraObject || cameraObject.children?.includes(hit.object)) return false;
          const tagName = hit.object.el?.tagName?.toUpperCase?.() || '';
          const name = (hit.object.name || hit.object.el?.id || '').toLowerCase();
          if (tagName === 'A-SKY' || name.includes('sky') || name.includes('background') || name.includes('env')) return false;
          return true;
        });
      if (hits.length > 0) {
        hitPoint = hits[0].point;
      } else {
        const floorPlane = new THREE_NS.Plane(new THREE_NS.Vector3(0, 1, 0), 0);
        const planeHit = new THREE_NS.Vector3();
        if (raycaster.ray.intersectPlane(floorPlane, planeHit)) hitPoint = planeHit;
      }
      if (hitPoint) moveAFrameViewTo(hitPoint);
    }

    function animateAFramePointerNavigation() {
      frameId = requestAnimationFrame(animateAFramePointerNavigation);
      const THREE_NS = getAFrameThree();
      const mover = getAFrameCameraMover();
      if (!THREE_NS || !mover || !aframeNav.animFrom || !aframeNav.animTo) return;
      const t = Math.min((performance.now() - aframeNav.animStart) / 350, 1);
      const eased = 1 - (1 - t) ** 3;
      const nextWorld = new THREE_NS.Vector3().lerpVectors(aframeNav.animFrom, aframeNav.animTo, eased);
      if (mover.parent) {
        mover.parent.worldToLocal(nextWorld);
      }
      mover.position.copy(nextWorld);
      if (t >= 1) {
        aframeNav.animFrom = aframeNav.animTo = null;
      }
    }

    function bindAFramePointerControls() {
      if (pointerTarget) return;
      pointerTarget = scene.canvas || scene.querySelector('canvas') || scene;
      pointerTarget.style.touchAction = 'none';
      pointerTarget.addEventListener('pointerdown', onAFramePointerDown);
      pointerTarget.addEventListener('pointermove', onAFramePointerMove);
      pointerTarget.addEventListener('pointerup', onAFramePointerUp);
      pointerTarget.addEventListener('pointercancel', clearAFramePointer);
      pointerTarget.addEventListener('lostpointercapture', clearAFramePointer);
    }

    scene.addEventListener('loaded', bindAFramePointerControls, { once: true });
    scene.addEventListener('loaded', () => {
      if (window.__creatrSonicSetMover) window.__creatrSonicSetMover(getAFrameCameraMover);
    }, { once: true });
    scene.addEventListener('renderstart', bindAFramePointerControls, { once: true });
    frameId = requestAnimationFrame(animateAFramePointerNavigation);
    setTimeout(bindAFramePointerControls, 250);
  }
} catch (error) { showPieceError(error); }
</script>
HTML,
        default => <<<'HTML'
<script>
try { if (typeof window.sketch === 'function') window.sketch(); } catch (error) { showPieceError(error); }
</script>
HTML,
    };
}

function piece_export_bundle(array $piece, array $version, array $options = []): array
{
    $manifest = piece_export_build_manifest($piece, $version, $options);
    $tempPath = tempnam(sys_get_temp_dir(), 'piece-export-');
    if ($tempPath === false) {
        throw new RuntimeException('Could not create a temporary file for the piece export.');
    }

    $zip = new ZipArchive();
    if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        @unlink($tempPath);
        throw new RuntimeException('Could not create the piece ZIP export.');
    }

    $zip->addFromString('index.html', $manifest['document']);

    foreach ($manifest['bundle_files'] as $bundleFile) {
        if (!$zip->addFromString($bundleFile['zip_path'], $bundleFile['data'])) {
            $zip->close();
            @unlink($tempPath);
            throw new RuntimeException('Could not package bundle file: ' . $bundleFile['zip_path']);
        }

        if (isset($bundleFile['mode']) && is_int($bundleFile['mode']) && method_exists($zip, 'setExternalAttributesName')) {
            $zip->setExternalAttributesName(
                $bundleFile['zip_path'],
                ZipArchive::OPSYS_UNIX,
                (($bundleFile['mode'] & 0xFFFF) << 16)
            );
        }
    }

    foreach ($manifest['runtime_files'] as $runtimeFile) {
        if (isset($runtimeFile['data'])) {
            $added = $zip->addFromString($runtimeFile['zip_path'], (string) $runtimeFile['data']);
        } else {
            $added = $zip->addFile($runtimeFile['source_path'], $runtimeFile['zip_path']);
        }
        if (!$added) {
            $zip->close();
            @unlink($tempPath);
            throw new RuntimeException('Could not package runtime file: ' . $runtimeFile['zip_path']);
        }
    }

    foreach ($manifest['media_files'] as $mediaFile) {
        if (!$zip->addFromString($mediaFile['zip_path'], $mediaFile['data'])) {
            $zip->close();
            @unlink($tempPath);
            throw new RuntimeException('Could not package media file: ' . $mediaFile['zip_path']);
        }
    }

    $zip->close();

    return [
        'filename' => piece_export_filename($piece),
        'path' => $tempPath,
    ];
}

function collection_export_bundle(array $collection, array $items, array $options = []): array
{
    $manifest = collection_export_build_manifest($collection, $items, $options);
    $tempPath = tempnam(sys_get_temp_dir(), 'collection-export-');
    if ($tempPath === false) {
        throw new RuntimeException('Could not create a temporary file for the collection export.');
    }

    $zip = new ZipArchive();
    if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        @unlink($tempPath);
        throw new RuntimeException('Could not create the collection ZIP export.');
    }

    $zip->addFromString('index.html', $manifest['document']);

    foreach ($manifest['bundle_files'] as $bundleFile) {
        if (isset($bundleFile['data'])) {
            $added = $zip->addFromString($bundleFile['zip_path'], (string) $bundleFile['data']);
        } else {
            $added = $zip->addFile($bundleFile['source_path'], $bundleFile['zip_path']);
        }
        if (!$added) {
            $zip->close();
            @unlink($tempPath);
            throw new RuntimeException('Could not package collection bundle file: ' . $bundleFile['zip_path']);
        }
    }

    foreach ($manifest['runtime_files'] as $runtimeFile) {
        if (isset($runtimeFile['data'])) {
            $added = $zip->addFromString($runtimeFile['zip_path'], (string) $runtimeFile['data']);
        } else {
            $added = $zip->addFile($runtimeFile['source_path'], $runtimeFile['zip_path']);
        }
        if (!$added) {
            $zip->close();
            @unlink($tempPath);
            throw new RuntimeException('Could not package collection runtime file: ' . $runtimeFile['zip_path']);
        }
    }

    $zip->close();

    return [
        'filename' => collection_export_filename($collection),
        'path' => $tempPath,
    ];
}

function collection_export_filename(array $collection): string
{
    $base = function_exists('slugify') ? slugify((string) ($collection['name'] ?? '')) : '';
    if ($base === '') {
        $base = (string) ($collection['slug'] ?? '');
    }
    if ($base === '') {
        $base = 'collection-' . (int) ($collection['id'] ?? 0);
    }

    return $base . '-gallery.zip';
}

function collection_export_build_manifest(array $collection, array $items, array $options = []): array
{
    $bundleFiles = [
        [
            'zip_path' => 'README.txt',
            'data' => collection_export_readme($collection),
        ],
    ];

    foreach ($items as $item) {
        if (($item['type'] ?? '') === 'art_piece' && !empty($item['piece']) && !empty($item['version'])) {
            $piece = $item['piece'];
            $version = $item['version'];

            $pieceManifest = piece_export_build_manifest($piece, $version, ['surface' => '']);
            $folder = pathinfo(piece_export_filename($piece), PATHINFO_FILENAME);
            if ($folder === '') {
                $folder = 'piece-' . (int) ($piece['id'] ?? 0);
            }

            // Add index.html
            $bundleFiles[] = [
                'zip_path' => 'pieces/' . $folder . '/index.html',
                'data' => $pieceManifest['document'],
            ];

            // Add bundle files
            foreach ($pieceManifest['bundle_files'] as $bf) {
                $bundleFiles[] = [
                    'zip_path' => 'pieces/' . $folder . '/' . $bf['zip_path'],
                    'data' => $bf['data'],
                ];
            }

            // Add runtime files
            foreach ($pieceManifest['runtime_files'] as $rf) {
                if (isset($rf['data'])) {
                    $bundleFiles[] = [
                        'zip_path' => 'pieces/' . $folder . '/' . $rf['zip_path'],
                        'data' => $rf['data'],
                    ];
                } else {
                    $bundleFiles[] = [
                        'zip_path' => 'pieces/' . $folder . '/' . $rf['zip_path'],
                        'source_path' => $rf['source_path'],
                    ];
                }
            }

            // Add media files
            foreach ($pieceManifest['media_files'] as $mf) {
                $bundleFiles[] = [
                    'zip_path' => 'pieces/' . $folder . '/' . $mf['zip_path'],
                    'data' => $mf['data'],
                ];
            }
        }
    }

    return [
        'document' => collection_export_document($collection, $items, $options),
        'bundle_files' => $bundleFiles,
        'runtime_files' => collection_export_runtime_files(),
    ];
}

function collection_export_readme(array $collection): string
{
    $title = trim((string) ($collection['name'] ?? 'Collection'));
    if ($title === '') {
        $title = 'Collection';
    }

    return "EXPORT: {$title}\n"
        . "\n"
        . "Open index.html to run this full collection gallery.\n"
        . "\n"
        . "This is a collection-wall export, not a single-piece export. The gallery includes all supported exported collection items, fullscreen, slideshow/full-view behavior, and PNG capture from the rendered gallery view.\n"
        . "\n"
        . "Other files are supporting runtime files only. You should not need to manually open any file besides index.html.\n";
}

function collection_export_runtime_files(): array
{
    $runtimeFiles = [];
    foreach (['p5', 'c2', 'aframe'] as $engine) {
        foreach (piece_export_immersive_runtime_files($engine) as $runtimeFile) {
            $runtimeFiles[(string) $runtimeFile['zip_path']] = $runtimeFile;
        }
    }

    return array_values($runtimeFiles);
}

function collection_export_document(array $collection, array $items, array $options = []): string
{
    $titleText = trim((string) ($collection['name'] ?? 'Collection'));
    if ($titleText === '') {
        $titleText = 'Collection';
    }
    $title = htmlspecialchars($titleText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $rows = isset($collection['rows']) && (int) $collection['rows'] > 0 ? (int) $collection['rows'] : 1;
    $cols = isset($collection['cols']) && (int) $collection['cols'] > 0 ? (int) $collection['cols'] : max(1, count($items));
    $viewState = piece_export_decode_view_state((string) ($options['view_state'] ?? ''));
    $jsItems = collection_export_items_payload($items);

    $jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    $jsonItems = json_encode($jsItems, $jsonFlags);
    $jsonRows = json_encode($rows, $jsonFlags);
    $jsonCols = json_encode($cols, $jsonFlags);
    $jsonViewState = json_encode($viewState, $jsonFlags);
    $jsonPngFilename = json_encode((function_exists('slugify') ? slugify($titleText) : '') ?: 'collection-gallery', $jsonFlags);
    $jsonEmbeddedThree = json_encode(piece_export_runtime_source_file('assets/vendor/piece-runtime/three/three.module.js'), $jsonFlags);
    $jsonEmbeddedOrbitControls = json_encode(piece_export_patched_orbitcontrols_source(), $jsonFlags);
    $jsonEmbeddedDeviceOrientation = json_encode(piece_export_patched_device_orientation_source(), $jsonFlags);
    $jsonEmbeddedImmersiveGallery = json_encode(piece_export_patched_immersive_gallery_source(), $jsonFlags);
    $downloadBridgeScript = piece_export_download_bridge_script();
    $hasAnySonic = false;
    foreach ($jsItems as $jsItem) {
        if (!empty($jsItem['sonicParams'])) {
            $hasAnySonic = true;
            break;
        }
    }

    // Shared top toolbar — same placement/appearance as the live collection
    // surface; the download menu is PNG-only because a standalone export
    // cannot re-download itself offline.
    $toolbarCss = immersive_stage_toolbar_css();
    $toolbarMarkup = immersive_stage_toolbar_markup([
        'view_action' => [
            'label' => 'View slideshow',
            'icon' => 'slideshow',
        ],
        'download_items' => [[
            'tag' => 'button',
            'label' => public_copy_value('public_art_copy.shared_ui.download_png_label'),
            'icon' => 'png',
            'attrs' => [
                'data-immersive-download-png' => true,
                'data-download-filename' => ((function_exists('slugify') ? slugify($titleText) : '') ?: 'collection-gallery') . '.png',
            ],
        ]],
        'sound_action' => $hasAnySonic ? ['enabled' => true] : null,
        'show_fullscreen' => true,
        'fullscreen_onclick' => null,
    ]);

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="creatr-collection-export" content="portable-immersive-collection">
<title>{$title}</title>
<style>
html,body{margin:0;padding:0;width:100%;height:100%;overflow:hidden;background:#05070f;color:#f8f5ee;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}
#immersive-stage{position:fixed;inset:0;width:100vw;height:100dvh;background:#000;overflow:hidden;}
#collection-error{position:fixed;left:1rem;right:1rem;bottom:5rem;z-index:220;display:none;padding:0.8rem 1rem;border:1px solid #fca5a5;border-radius:0.75rem;background:#450a0a;color:#fee2e2;font:13px/1.4 ui-monospace,SFMono-Regular,Menlo,monospace;white-space:pre-wrap;}
{$toolbarCss}
.immersive-stage-toolbar{position:fixed;}
</style>
</head>
<body>
<div id="immersive-stage" tabindex="-1"></div>
<div id="collection-error" role="alert"></div>
{$toolbarMarkup}
<script>
function showCollectionError(error){const el=document.getElementById('collection-error');if(!el)return;el.textContent=(error&&(error.stack||error.message))?(error.stack||error.message):String(error);el.style.display='block';}
window.addEventListener('error',event=>showCollectionError(event.error||event.message));
window.addEventListener('unhandledrejection',event=>showCollectionError(event.reason||'Unhandled promise rejection'));
</script>
<script>
{$downloadBridgeScript}
</script>
<script type="module">
const embeddedRuntimeSources = {
  three: {$jsonEmbeddedThree},
  orbitControls: {$jsonEmbeddedOrbitControls},
  deviceOrientation: {$jsonEmbeddedDeviceOrientation},
  immersiveGallery: {$jsonEmbeddedImmersiveGallery}
};
function createRuntimeModuleUrl(source) {
  return URL.createObjectURL(new Blob([source], { type: 'text/javascript' }));
}
async function loadImmersiveRuntime() {
  try {
    return await import('./runtime/immersive-gallery.js');
  } catch (error) {
    const threeUrl = createRuntimeModuleUrl(embeddedRuntimeSources.three);
    const orbitUrl = createRuntimeModuleUrl(embeddedRuntimeSources.orbitControls.replace("from '../../three.module.js';", `from '\${threeUrl}';`));
    const deviceUrl = createRuntimeModuleUrl(embeddedRuntimeSources.deviceOrientation.replace("'./three/three.module.js'", `'\${threeUrl}'`));
    const gallerySource = embeddedRuntimeSources.immersiveGallery
      .replace("from './three/three.module.js';", `from '\${threeUrl}';`)
      .replace("from './three/addons/controls/OrbitControls.js';", `from '\${orbitUrl}';`)
      .replace('await import("./three-device-orientation-controls.js")', `await import('\${deviceUrl}')`);
    return await import(createRuntimeModuleUrl(gallerySource));
  }
}
const { mountExhibitWall, setupImmersiveStageChrome } = await loadImmersiveRuntime();
const stage = document.getElementById('immersive-stage');
const rows = {$jsonRows};
const cols = {$jsonCols};
const items = {$jsonItems};
const initialViewState = {$jsonViewState};
const pngFilename = {$jsonPngFilename} + '.png';
let viewer = null;
try {
  viewer = mountExhibitWall(stage, items, rows, cols, { showViewerControls: true, initialViewState });
} catch (error) {
  showCollectionError(error);
}
const fullscreenBtn = document.getElementById('fullscreen-toggle-btn');
const pngBtn = document.querySelector('[data-immersive-download-png]');
setupImmersiveStageChrome(stage, {
  onViewAction() {
    const activeIndex = viewer?.getActiveIndex?.();
    if (Number.isFinite(activeIndex)) {
      viewer?.openSlideshowAt?.(activeIndex);
      return;
    }
    const state = viewer?.getViewState?.() || {};
    viewer?.openSlideshowAt?.(Number.isFinite(Number(state.activeIndex)) ? Number(state.activeIndex) : 0);
  }
});
fullscreenBtn?.addEventListener('click', async () => {
  try {
    if (document.fullscreenElement) await document.exitFullscreen();
    else if (document.documentElement.requestFullscreen) await document.documentElement.requestFullscreen();
  } catch (_) {}
});
document.addEventListener('fullscreenchange', () => {
  fullscreenBtn?.setAttribute('aria-label', document.fullscreenElement ? 'Exit fullscreen' : 'Enter fullscreen');
});
function wait(ms) { return new Promise((resolve) => setTimeout(resolve, ms)); }
function hasVisiblePixels(canvas) {
  const context = canvas.getContext('2d');
  if (!context) return false;
  const width = Math.max(1, canvas.width || 1);
  const height = Math.max(1, canvas.height || 1);
  for (let y = 0; y < Math.min(4, height); y++) {
    for (let x = 0; x < Math.min(4, width); x++) {
      const px = context.getImageData(Math.floor((x / 4) * width), Math.floor((y / 4) * height), 1, 1).data;
      if (px[3] !== 0 || px[0] !== 0 || px[1] !== 0 || px[2] !== 0) return true;
    }
  }
  return false;
}
function exportCanvas(canvas) {
  const width = Math.max(1, canvas.width || Math.round(canvas.getBoundingClientRect().width) || 1);
  const height = Math.max(1, canvas.height || Math.round(canvas.getBoundingClientRect().height) || 1);
  const out = document.createElement('canvas');
  out.width = width;
  out.height = height;
  const context = out.getContext('2d');
  if (!context) throw new Error('PNG export is unavailable in this browser.');
  context.drawImage(canvas, 0, 0, width, height);
  return out;
}
async function canvasToBlob(canvas) {
  return new Promise((resolve, reject) => {
    canvas.toBlob((blob) => blob ? resolve(blob) : reject(new Error('Could not create the PNG download.')), 'image/png');
  });
}
function downloadBlob(blob, filename) {
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}
pngBtn?.addEventListener('click', async () => {
  if (pngBtn.disabled) return;
  const labelEl = pngBtn.querySelector('span') || pngBtn;
  const label = labelEl.textContent;
  pngBtn.disabled = true;
  labelEl.textContent = 'Preparing PNG...';
  try {
    const capture = viewer?.getCaptureSurface?.();
    capture?.beforeCapture?.();
    const surface = capture?.canvas || null;
    if (!surface) throw new Error('No downloadable canvas is available yet.');
    let exported = exportCanvas(surface);
    if (!hasVisiblePixels(exported)) {
      await wait(120);
      capture?.beforeCapture?.();
      exported = exportCanvas(surface);
    }
    if (!hasVisiblePixels(exported)) throw new Error('Could not produce a non-blank PNG right now.');
    downloadBlob(await canvasToBlob(exported), pngFilename);
  } catch (error) {
    showCollectionError(error);
  } finally {
    pngBtn.disabled = false;
    labelEl.textContent = label;
  }
});
</script>
</body>
</html>
HTML;
}

function collection_export_items_payload(array $items): array
{
    $payload = [];
    foreach ($items as $item) {
        if (($item['type'] ?? '') === 'art_piece' && !empty($item['piece']) && !empty($item['version'])) {
            $payload[] = collection_export_piece_item_payload($item['piece'], $item['version']);
        } elseif (($item['type'] ?? '') === 'media_asset' && !empty($item['media'])) {
            $media = $item['media'];
            $imageUrl = collection_export_media_asset_url($media);
            $payload[] = [
                'kind' => 'image',
                'title' => $media['title'] ?? 'Untitled Image',
                'imageUrl' => $imageUrl,
                'alt_text' => $media['alt_text'] ?? '',
                'description' => $media['alt_text'] ?? '',
                'full_view' => [
                    'type' => 'image',
                    'src' => $imageUrl,
                    'alt' => ($media['alt_text'] ?? '') !== '' ? $media['alt_text'] : ($media['title'] ?? 'Untitled Image'),
                    'title' => $media['title'] ?? 'Untitled Image',
                    'subtitle' => 'Image',
                ],
            ];
        }
    }

    return $payload;
}

function collection_export_piece_item_payload(array $piece, array $version): array
{
    $htmlCode = (string) ($version['html_code'] ?? '');
    $cssCode = (string) ($version['css_code'] ?? '');
    $jsCode = (string) ($version['generated_code'] ?? '');
    $mediaMap = piece_build_media_manifest([$htmlCode, $cssCode, $jsCode, (string) ($piece['thumbnail_url'] ?? '')]);
    $rewriteMedia = static function (string $content) use ($mediaMap): string {
        return piece_export_rewrite_media_refs($content, static function (string $normalizedRef) use ($mediaMap): ?string {
            $asset = $mediaMap[$normalizedRef] ?? null;
            return is_array($asset) ? (string) ($asset['data_url'] ?? '') : null;
        });
    };

    $engine = strtolower((string) ($version['engine'] ?? $piece['engine'] ?? 'p5'));
    $html = $rewriteMedia($htmlCode);
    if ($engine === 'aframe') {
        $html = piece_aframe_normalize_texture_assets($html, static function (string $src) use ($mediaMap): string {
            $asset = $mediaMap[$src] ?? null;
            return is_array($asset) ? (string) ($asset['data_url'] ?? $src) : $src;
        });
    }

    $generationMode = art_piece_version_generation_mode($version, $piece);
    $itemEngineLabel = art_piece_generation_mode_label($generationMode);
    $thumbnailUrl = collection_export_media_url((string) ($piece['thumbnail_url'] ?? ''));
    $pngFilenameBase = pathinfo(piece_export_filename($piece), PATHINFO_FILENAME);
    $piecePngFilename = ($pngFilenameBase !== '' ? $pngFilenameBase : 'piece-' . (int) ($piece['id'] ?? 0)) . '.png';
    $pieceDescription = (string) ($piece['description'] ?? '');

    return [
        'kind' => 'piece',
        'piece_id' => (int) ($piece['id'] ?? 0),
        'version_id' => (int) ($version['id'] ?? 0),
        'title' => $piece['title'] ?? 'Untitled Piece',
        'engine' => $engine,
        'thumbnail_url' => $thumbnailUrl,
        'html_code' => $html,
        'css_code' => $rewriteMedia($cssCode),
        'generated_code' => $rewriteMedia($jsCode),
        'description' => $pieceDescription,
        'png_filename' => $piecePngFilename,
        'full_view' => [
            'type' => 'iframe',
            'interactive' => in_array($generationMode, ['three', 'aframe', 'c2_interactive'], true),
            'srcdoc' => piece_export_document($piece, $version, [
                'runtime_mode' => 'bundle',
                'media_map' => $mediaMap,
                'embed_media' => true,
            ]),
            'title' => $piece['title'] ?? 'Untitled Piece',
            'subtitle' => $itemEngineLabel,
            'png_filename' => $piecePngFilename,
        ],
        'sonicParams' => !empty($version['sonic_params']) ? json_decode((string) $version['sonic_params'], true) : null,
    ];
}

function collection_export_media_asset_url(array $media): string
{
    if (!empty($media['file_data'])) {
        return piece_export_data_url(
            (string) ($media['mime_type'] ?? 'application/octet-stream'),
            (string) ($media['file_data'] ?? '')
        );
    }

    return collection_export_media_url((string) (($media['url'] ?? '') ?: ('/api/media-assets/' . (int) ($media['id'] ?? 0))));
}

function collection_export_media_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    $path = (string) parse_url($url, PHP_URL_PATH);
    if ($path !== '' && preg_match('#^/(?:image/[0-9]+|media/[A-Za-z0-9._~/%+-]+|api/media-assets/[0-9]+)$#', $path)) {
        try {
            $asset = piece_export_resolve_media_ref($path);
            return piece_export_data_url((string) ($asset['mime_type'] ?? 'application/octet-stream'), (string) ($asset['data'] ?? ''));
        } catch (Throwable) {
            return $url;
        }
    }

    return $url;
}

function piece_build_media_manifest(array $contents): array
{
    $mediaRefs = piece_export_collect_media_refs($contents);
    $mediaMap = [];
    foreach ($mediaRefs as $ref) {
        $asset = piece_export_resolve_media_ref($ref);
        $mediaMap[$ref] = [
            'path' => piece_export_media_zip_path($ref, $asset, array_column($mediaMap, 'path')),
            'data_url' => piece_export_data_url((string) ($asset['mime_type'] ?? 'application/octet-stream'), (string) ($asset['data'] ?? '')),
        ];
    }

    return $mediaMap;
}

function piece_export_build_manifest(array $piece, array $version, array $options = []): array
{
    $htmlCode = (string) ($version['html_code'] ?? '');
    $cssCode = (string) ($version['css_code'] ?? '');
    $jsCode = (string) ($version['generated_code'] ?? '');

    $mediaMap = piece_build_media_manifest([$htmlCode, $cssCode, $jsCode]);
    $mediaFiles = [];
    foreach ($mediaMap as $ref => $mediaEntry) {
        $asset = piece_export_resolve_media_ref($ref);
        $mediaFiles[] = [
            'zip_path' => (string) $mediaEntry['path'],
            'data' => $asset['data'],
        ];
    }

    $bundleFiles = piece_export_bundle_files($piece, $version, $mediaMap);

    $surface = strtolower(trim((string) ($options['surface'] ?? '')));
    $immersive = $surface === 'immersive';
    $viewState = $immersive ? piece_export_decode_view_state((string) ($options['view_state'] ?? '')) : [];

    return [
        'document' => $immersive
            ? piece_export_immersive_document($piece, $version, [
                'media_map' => $mediaMap,
                'embed_media' => true,
                'view_state' => $viewState,
            ])
            : piece_export_document($piece, $version, [
                'runtime_mode' => 'bundle',
                'media_map' => $mediaMap,
                'embed_media' => true,
                'css_href' => 'styles/piece.css',
                'script_src' => 'scripts/piece.js',
            ]),
        'bundle_files' => $bundleFiles,
        'runtime_files' => $immersive
            ? piece_export_immersive_runtime_files((string) ($version['engine'] ?? $piece['engine'] ?? 'p5'))
            : piece_export_runtime_files((string) ($version['engine'] ?? $piece['engine'] ?? 'p5')),
        'media_files' => $mediaFiles,
    ];
}

function piece_export_bundle_files(array $piece, array $version, array $mediaMap): array
{
    $generationMode = art_piece_version_generation_mode($version, $piece);

    return [
        [
            'zip_path' => 'styles/piece.css',
            'data' => piece_export_stylesheet_content($piece, $version, $mediaMap),
        ],
        [
            'zip_path' => 'scripts/piece.js',
            'data' => piece_export_script_content($piece, $version, $mediaMap),
        ],
        [
            'zip_path' => 'README.txt',
            'data' => piece_export_readme($piece, $generationMode),
        ],
    ];
}

function piece_export_stylesheet_content(array $piece, array $version, array $mediaMap): string
{
    $engine = strtolower((string) ($version['engine'] ?? $piece['engine'] ?? 'p5'));
    $css = piece_export_rewrite_media_refs(
        (string) ($version['css_code'] ?? ''),
        static function (string $normalizedRef) use ($mediaMap): string {
            $target = $mediaMap[$normalizedRef] ?? null;
            if (!is_array($target)) {
                return $normalizedRef;
            }

            return (string) ($target['data_url'] ?? $normalizedRef);
        }
    );
    $baseCss = <<<'CSS'
html,body{margin:0;padding:0;width:100%;height:100%;overflow:hidden;background:#0d0d0f;color:#fff;}
body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}
#runtime-root{width:100vw;height:100vh;overflow:hidden;}
#piece-error{position:fixed;inset:auto 1rem 1rem 1rem;z-index:9999;padding:1rem;border:1px solid #fca5a5;background:#450a0a;color:#fee2e2;font:14px/1.4 ui-monospace,SFMono-Regular,Menlo,monospace;white-space:pre-wrap;display:none;}
canvas{display:block;width:100%;height:100%;}
CSS;
    if ($engine === 'aframe') {
        $baseCss .= "\na-scene{display:block;width:100%;height:100%;}\n.a-canvas{display:block;width:100%!important;height:100%!important;}\n";
    }

    $baseCss .= "\n" . piece_export_screenshot_overlay_css(art_piece_version_generation_mode($version, $piece));

    return rtrim($baseCss . "\n\n" . $css) . "\n";
}

function piece_export_script_content(array $piece, array $version, array $mediaMap): string
{
    $code = piece_export_rewrite_media_refs(
        (string) ($version['generated_code'] ?? ''),
        static function (string $normalizedRef) use ($mediaMap): string {
            $target = $mediaMap[$normalizedRef] ?? null;
            if (!is_array($target)) {
                return $normalizedRef;
            }

            return (string) ($target['data_url'] ?? $normalizedRef);
        }
    );

    if (trim($code) === '') {
        return "// This exported piece does not include custom JavaScript yet.\n";
    }

    return rtrim($code) . "\n";
}

function piece_export_readme(array $piece, string $generationMode): string
{
    $title = trim((string) ($piece['title'] ?? 'Art piece'));
    if ($title === '') {
        $title = 'Art piece';
    }

    return "EXPORT: {$title}\n"
        . "\n"
        . "Open index.html to run this piece.\n"
        . "\n"
        . "Other files are supporting files only. You should not need to manually open any file besides index.html.\n"
        . "\n"
        . "This bundle still includes:\n"
        . "- styles/piece.css for editable styling\n"
        . "- scripts/piece.js for editable piece logic\n"
        . "- runtime/ and media/ as portable supporting assets for rehosting\n"
        . "\n"
        . "This export includes lower-left fullscreen and screenshot controls directly inside index.html.\n"
        . "\n"
        . "Open index.html after editing those files if you want to create a revised version of the piece.\n";
}

function piece_export_collect_media_refs(array $contents): array
{
    $refs = [];
    foreach ($contents as $content) {
        if (!is_string($content) || $content === '') {
            continue;
        }

        if (!preg_match_all(
            '#(?<![A-Za-z0-9._~/-])/?(?:image/[0-9]+|api/media-assets/[0-9]+|media/[A-Za-z0-9._~/%+-]+)(?:\?[A-Za-z0-9._~%=&+-]*)?#',
            $content,
            $matches
        )) {
            continue;
        }

        foreach ($matches[0] as $match) {
            $refs['/' . ltrim((string) $match, '/')] = true;
        }
    }

    return array_keys($refs);
}

function piece_export_resolve_media_ref(string $ref): array
{
    $path = (string) parse_url($ref, PHP_URL_PATH);
    if ($path === '') {
        throw new RuntimeException('Unsupported media reference in piece export: ' . $ref);
    }

    if (preg_match('#^/image/([0-9]+)$#', $path, $matches)) {
        $file = MediaFile::getData((int) $matches[1]);
        if (!$file || !str_starts_with((string) ($file['mime_type'] ?? ''), 'image/')) {
            throw new RuntimeException('Referenced image asset could not be exported: ' . $ref);
        }

        return [
            'kind' => 'image',
            'id' => (int) $matches[1],
            'mime_type' => (string) ($file['mime_type'] ?? 'application/octet-stream'),
            'filename' => (string) (($file['original_name'] ?? '') ?: ('image-' . $matches[1])),
            'data' => (string) ($file['data'] ?? ''),
        ];
    }

    if (preg_match('#^/media/([0-9]+)$#', $path, $matches)) {
        $file = MediaFile::getData((int) $matches[1]);
        if (!$file) {
            throw new RuntimeException('Referenced media file could not be exported: ' . $ref);
        }

        return [
            'kind' => 'media',
            'id' => (int) $matches[1],
            'mime_type' => (string) ($file['mime_type'] ?? 'application/octet-stream'),
            'filename' => (string) (($file['original_name'] ?? '') ?: ('media-' . $matches[1])),
            'data' => (string) ($file['data'] ?? ''),
        ];
    }

    if (preg_match('#^/media/([^/]+)$#', $path, $matches)) {
        $asset = MediaAsset::findByFilename($matches[1]);
        if (!$asset || empty($asset['file_data'])) {
            throw new RuntimeException('Referenced media asset filename could not be exported: ' . $ref);
        }

        return [
            'kind' => 'media-asset',
            'id' => (int) ($asset['id'] ?? 0),
            'mime_type' => (string) ($asset['mime_type'] ?? 'application/octet-stream'),
            'filename' => (string) (($asset['filename'] ?? '') ?: ('media-asset-' . ($asset['id'] ?? '0'))),
            'data' => (string) ($asset['file_data'] ?? ''),
        ];
    }

    if (preg_match('#^/api/media-assets/([0-9]+)$#', $path, $matches)) {
        $asset = MediaAsset::find((int) $matches[1]);
        if (!$asset || empty($asset['file_data'])) {
            throw new RuntimeException('Referenced media asset could not be exported: ' . $ref);
        }

        return [
            'kind' => 'media-asset',
            'id' => (int) $matches[1],
            'mime_type' => (string) ($asset['mime_type'] ?? 'application/octet-stream'),
            'filename' => (string) (($asset['filename'] ?? '') ?: ('media-asset-' . $matches[1])),
            'data' => (string) ($asset['file_data'] ?? ''),
        ];
    }

    throw new RuntimeException('Unsupported media reference in piece export: ' . $ref);
}

function piece_export_media_zip_path(string $ref, array $asset, array $existingMap): string
{
    $extension = piece_export_filename_extension((string) ($asset['mime_type'] ?? ''), (string) ($asset['filename'] ?? ''));
    $kind = (string) ($asset['kind'] ?? 'media');
    $id = (int) ($asset['id'] ?? 0);
    $base = match ($kind) {
        'image' => 'image-' . $id,
        'media-asset' => 'media-asset-' . $id,
        default => 'media-' . $id,
    };

    $candidate = 'media/' . $base . ($extension !== '' ? '.' . $extension : '');
    $used = array_values($existingMap);
    $suffix = 2;
    while (in_array($candidate, $used, true)) {
        $candidate = 'media/' . $base . '-' . $suffix . ($extension !== '' ? '.' . $extension : '');
        $suffix += 1;
    }

    return $candidate;
}

function piece_export_filename_extension(string $mimeType, string $filename = ''): string
{
    $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
    if ($extension !== '') {
        return preg_replace('/[^a-z0-9]+/', '', $extension) ?? $extension;
    }

    return match (strtolower($mimeType)) {
        'image/jpeg', 'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'audio/mpeg' => 'mp3',
        'audio/ogg' => 'ogg',
        'application/json' => 'json',
        'text/plain' => 'txt',
        default => 'bin',
    };
}

function piece_export_data_url(string $mimeType, string $data): string
{
    $mimeType = trim($mimeType) !== '' ? $mimeType : 'application/octet-stream';
    return 'data:' . $mimeType . ';base64,' . base64_encode($data);
}

function piece_export_runtime_files(string $engine): array
{
    $publicRoot = dirname(__DIR__, 2);
    $vendorRoot = $publicRoot . '/assets/vendor/piece-runtime';
    $runtimeFiles = match (strtolower($engine)) {
        'p5' => [
            ['source_path' => $vendorRoot . '/p5/p5.min.js', 'zip_path' => 'runtime/p5/p5.min.js'],
        ],
        'c2' => [
            ['source_path' => $vendorRoot . '/c2/c2.min.js', 'zip_path' => 'runtime/c2/c2.min.js'],
        ],
        'three' => [
            ['source_path' => $vendorRoot . '/three/three.module.js', 'zip_path' => 'runtime/three/three.module.js'],
            ['source_path' => $vendorRoot . '/three/OrbitControls.js', 'zip_path' => 'runtime/three/addons/controls/OrbitControls.js'],
        ],
        'aframe' => [
            ['source_path' => $publicRoot . '/assets/js/aframe.min.js', 'zip_path' => 'runtime/aframe/aframe.min.js'],
        ],
        default => [],
    };

    foreach ($runtimeFiles as $runtimeFile) {
        if (!is_file($runtimeFile['source_path'])) {
            throw new RuntimeException('Missing vendored runtime file for piece export: ' . $runtimeFile['source_path']);
        }
    }

    return $runtimeFiles;
}

function piece_export_immersive_runtime_files(string $engine): array
{
    $publicRoot = dirname(__DIR__, 2);
    $vendorRoot = $publicRoot . '/assets/vendor/piece-runtime';
    $engine = strtolower($engine);
    $runtimeFiles = [
        [
            'zip_path' => 'runtime/immersive-gallery.js',
            'data' => piece_export_patched_immersive_gallery_source(),
        ],
        [
            'zip_path' => 'runtime/three-device-orientation-controls.js',
            'data' => piece_export_patched_device_orientation_source(),
        ],
        ['source_path' => $vendorRoot . '/three/three.module.js', 'zip_path' => 'runtime/three/three.module.js'],
        [
            'zip_path' => 'runtime/three/addons/controls/OrbitControls.js',
            'data' => piece_export_patched_orbitcontrols_source(),
        ],
        // Sonification applies to every engine in the immersive view (not
        // just three/aframe), so Tone.js is bundled unconditionally here,
        // same as three.module.js above.
        ['source_path' => $publicRoot . '/assets/vendor/tone/Tone.js', 'zip_path' => 'runtime/tone/Tone.js'],
    ];

    if ($engine === 'p5') {
        $runtimeFiles[] = ['source_path' => $vendorRoot . '/p5/p5.min.js', 'zip_path' => 'runtime/p5/p5.min.js'];
    } elseif ($engine === 'c2') {
        $runtimeFiles[] = ['source_path' => $vendorRoot . '/c2/c2.min.js', 'zip_path' => 'runtime/c2/c2.min.js'];
    } elseif ($engine === 'aframe') {
        $runtimeFiles[] = ['source_path' => $publicRoot . '/assets/js/aframe.min.js', 'zip_path' => 'runtime/aframe/aframe.min.js'];
    }

    foreach ($runtimeFiles as $runtimeFile) {
        if (isset($runtimeFile['source_path']) && !is_file($runtimeFile['source_path'])) {
            throw new RuntimeException('Missing vendored runtime file for immersive piece export: ' . $runtimeFile['source_path']);
        }
    }

    return $runtimeFiles;
}

function piece_export_patched_immersive_gallery_source(): string
{
    $source = piece_export_runtime_source_file('assets/js/immersive-gallery.js');
    $replacements = [
        "import * as THREE from 'https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js';" => "import * as THREE from './three/three.module.js';",
        "import { OrbitControls } from 'https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/controls/OrbitControls.js';" => "import { OrbitControls } from './three/addons/controls/OrbitControls.js';",
        'await import("/assets/js/three-device-orientation-controls.js")' => 'await import("./three-device-orientation-controls.js")',
        'script.src = "/assets/js/aframe.min.js";' => 'script.src = "runtime/aframe/aframe.min.js";',
        'script.src = "https://cdnjs.cloudflare.com/ajax/libs/p5.js/1.9.0/p5.min.js";' => 'script.src = "runtime/p5/p5.min.js";',
        'script.src = "https://cdn.jsdelivr.net/npm/c2.js@1.0.9/dist/c2.min.js";' => 'script.src = "runtime/c2/c2.min.js";',
        'script.src && script.src.endsWith("/assets/js/aframe.min.js")' => 'script.src && script.src.endsWith("runtime/aframe/aframe.min.js")',
        's.src = "/assets/vendor/tone/Tone.js";' => 's.src = "runtime/tone/Tone.js";',
    ];

    return strtr($source, $replacements);
}

function piece_export_patched_device_orientation_source(): string
{
    $source = piece_export_runtime_source_file('assets/js/three-device-orientation-controls.js');
    return str_replace(
        "'https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js'",
        "'./three/three.module.js'",
        $source
    );
}

function piece_export_patched_orbitcontrols_source(): string
{
    $source = piece_export_runtime_source_file('assets/vendor/piece-runtime/three/OrbitControls.js');
    return str_replace(
        "from 'three';",
        "from '../../three.module.js';",
        $source
    );
}

function piece_export_png_filename(array $piece): string
{
    return piece_export_basename($piece) . '.png';
}

// Every generation mode renders to a <canvas> or <svg>, and
// piece_export_screenshot_overlay_script() already finds either generically
// (document.querySelectorAll('canvas'/'svg')) and captures with plain
// canvas.toDataURL()/toBlob() — there's no engine-specific capture
// requirement beyond aframe's additive-only preserveDrawingBuffer shim.
function piece_export_supports_screenshot_overlay(string $generationMode): bool
{
    return in_array($generationMode, art_piece_supported_generation_modes(), true);
}

function piece_aframe_capture_context_shim(): string
{
    return <<<'HTML'
<script>
(function () {
  if (window.__creatrAframeCapturePatched) return;
  window.__creatrAframeCapturePatched = true;
  const originalGetContext = HTMLCanvasElement.prototype.getContext;
  HTMLCanvasElement.prototype.getContext = function (type, options) {
    const normalizedType = typeof type === 'string' ? type.toLowerCase() : '';
    if (normalizedType === 'webgl' || normalizedType === 'webgl2' || normalizedType === 'experimental-webgl') {
      const nextOptions = Object.assign({}, options || {}, { preserveDrawingBuffer: true });
      return originalGetContext.call(this, type, nextOptions);
    }
    return originalGetContext.call(this, type, options);
  };
})();
</script>
HTML;
}

function piece_export_screenshot_overlay_css(string $generationMode): string
{
    $screenshotCss = piece_export_supports_screenshot_overlay($generationMode)
        ? <<<'CSS'
#piece-export-screenshot-btn{display:inline-flex;}
CSS
        : '';

    return <<<CSS
#piece-export-screenshot-shell{position:fixed;left:1rem;bottom:1rem;z-index:10000;display:flex;flex-direction:column;align-items:flex-start;gap:0.5rem;pointer-events:none;}
#piece-export-control-row{display:flex;align-items:center;gap:0.5rem;pointer-events:auto;}
#piece-export-screenshot-btn,#piece-export-fullscreen-btn{align-items:center;justify-content:center;width:3rem;height:3rem;border:1px solid rgba(255,255,255,0.22);border-radius:999px;background:rgba(10,12,20,0.72);color:#f6f2dd;box-shadow:0 12px 30px rgba(0,0,0,0.28);backdrop-filter:blur(10px);cursor:pointer;pointer-events:auto;}
#piece-export-screenshot-btn{display:none;}
#piece-export-fullscreen-btn{display:inline-flex;}
#piece-export-screenshot-btn:hover,#piece-export-fullscreen-btn:hover{background:rgba(21,26,40,0.88);}
#piece-export-screenshot-btn:focus-visible,#piece-export-fullscreen-btn:focus-visible{outline:2px solid #f6f2dd;outline-offset:2px;}
#piece-export-screenshot-btn[disabled],#piece-export-fullscreen-btn[disabled]{opacity:0.72;cursor:progress;}
#piece-export-screenshot-btn svg,#piece-export-fullscreen-btn svg{width:1.35rem;height:1.35rem;display:block;}
#piece-export-screenshot-btn svg{fill:currentColor;}
#piece-export-fullscreen-btn svg{fill:none;stroke:currentColor;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round;}
#piece-export-screenshot-status{max-width:min(20rem,calc(100vw - 2rem));padding:0.45rem 0.7rem;border-radius:0.8rem;background:rgba(10,12,20,0.72);color:#f6f2dd;font:13px/1.35 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;pointer-events:auto;}
#piece-export-screenshot-status[hidden]{display:none;}
.piece-export-sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;}
.piece-export-fullscreen-active #runtime-root{width:100vw;height:100dvh;}
{$screenshotCss}
CSS;
}

function piece_export_screenshot_overlay_markup(string $generationMode): string
{
    $screenshotButton = piece_export_supports_screenshot_overlay($generationMode)
        ? <<<'HTML'
  <button id="piece-export-screenshot-btn" type="button" aria-label="Take screenshot">
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M9 4.5 7.8 6H5.5A2.5 2.5 0 0 0 3 8.5v9A2.5 2.5 0 0 0 5.5 20h13a2.5 2.5 0 0 0 2.5-2.5v-9A2.5 2.5 0 0 0 18.5 6h-2.3L15 4.5H9Zm3 4a4.75 4.75 0 1 1 0 9.5 4.75 4.75 0 0 1 0-9.5Zm0 1.75a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z"/>
    </svg>
    <span class="piece-export-sr-only">Take screenshot</span>
  </button>
HTML
        : '';

    return <<<HTML
<div id="piece-export-screenshot-shell" aria-live="polite">
  <div id="piece-export-control-row">
{$screenshotButton}
    <button id="piece-export-fullscreen-btn" type="button" aria-label="Enter fullscreen">
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M16 3h3a2 2 0 0 1 2 2v3"/><path d="M21 16v3a2 2 0 0 1-2 2h-3"/><path d="M8 21H5a2 2 0 0 1-2-2v-3"/>
      </svg>
      <span class="piece-export-sr-only">Enter fullscreen</span>
    </button>
  </div>
  <div id="piece-export-screenshot-status" role="status" hidden></div>
</div>
HTML;
}

function piece_export_screenshot_overlay_script(array $piece, string $generationMode): string
{
    $jsonFilename = json_encode(piece_export_png_filename($piece), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $jsonMode = json_encode($generationMode, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $supportsScreenshot = piece_export_supports_screenshot_overlay($generationMode) ? 'true' : 'false';

    return <<<HTML
<script>
(function () {
  const generationMode = {$jsonMode};
  const filename = {$jsonFilename};
  const supportsScreenshot = {$supportsScreenshot};
  const button = document.getElementById('piece-export-screenshot-btn');
  const fullscreenButton = document.getElementById('piece-export-fullscreen-btn');
  const status = document.getElementById('piece-export-screenshot-status');
  if (!status) return;

  function setStatus(message) {
    status.textContent = message || '';
    status.hidden = !message;
  }

  if (fullscreenButton) {
    fullscreenButton.addEventListener('click', async function () {
      try {
        if (document.fullscreenElement) {
          await document.exitFullscreen();
          return;
        }
        const target = document.documentElement;
        if (typeof target.requestFullscreen === 'function') {
          await target.requestFullscreen();
          return;
        }
        document.documentElement.classList.add('piece-export-fullscreen-active');
        setStatus('Fullscreen is not available in this browser; expanded the piece instead.');
      } catch (error) {
        document.documentElement.classList.add('piece-export-fullscreen-active');
      }
    });
    document.addEventListener('fullscreenchange', function () {
      fullscreenButton.setAttribute('aria-label', document.fullscreenElement ? 'Exit fullscreen' : 'Enter fullscreen');
      document.documentElement.classList.toggle('piece-export-fullscreen-active', Boolean(document.fullscreenElement));
    });
  }

  function isVisibleSurface(node) {
    if (!node || node.nodeType !== 1 || typeof node.getBoundingClientRect !== 'function') return false;
    const style = window.getComputedStyle(node);
    if (style.display === 'none' || style.visibility === 'hidden' || Number(style.opacity) === 0) return false;
    const rect = node.getBoundingClientRect();
    return rect.width > 0 && rect.height > 0;
  }

  function findCaptureSurface() {
    if (generationMode === 'aframe') {
      const scene = document.querySelector('a-scene#scene') || document.querySelector('a-scene');
      if (scene && scene.canvas && isVisibleSurface(scene.canvas)) {
        return { type: 'canvas', node: scene.canvas };
      }
    }

    const selectors = generationMode === 'aframe'
      ? ['a-scene canvas', '.a-canvas', 'canvas']
      : ['canvas'];
    for (const selector of selectors) {
      const matches = Array.from(document.querySelectorAll(selector)).filter(isVisibleSurface);
      if (matches.length > 0) {
        return { type: 'canvas', node: matches[matches.length - 1] };
      }
    }

    const svgs = Array.from(document.querySelectorAll('svg')).filter(isVisibleSurface);
    if (svgs.length > 0) {
      return { type: 'svg', node: svgs[0] };
    }

    return null;
  }

  function tryForceAframeRender(surface) {
    if (generationMode !== 'aframe') return;
    const scene = document.querySelector('a-scene#scene') || document.querySelector('a-scene');
    if (!scene || !surface || scene.canvas !== surface) return;
    const renderer = scene.renderer;
    const object3D = scene.object3D;
    const camera = scene.camera || scene.cameraEl?.getObject3D?.('camera') || null;
    if (!renderer || !object3D || !camera || typeof renderer.render !== 'function') return;
    try {
      renderer.render(object3D, camera);
    } catch (_) {}
  }

  function hasVisiblePixels(canvas) {
    const context = canvas.getContext('2d');
    if (!context) return false;
    const width = Math.max(1, canvas.width || 1);
    const height = Math.max(1, canvas.height || 1);
    const sampleX = Math.max(1, Math.min(4, width));
    const sampleY = Math.max(1, Math.min(4, height));
    for (let row = 0; row < sampleY; row += 1) {
      for (let col = 0; col < sampleX; col += 1) {
        const x = Math.min(width - 1, Math.floor((col / sampleX) * width));
        const y = Math.min(height - 1, Math.floor((row / sampleY) * height));
        const pixel = context.getImageData(x, y, 1, 1).data;
        if (pixel[3] !== 0 || pixel[0] !== 0 || pixel[1] !== 0 || pixel[2] !== 0) {
          return true;
        }
      }
    }
    return false;
  }

  function wait(ms) {
    return new Promise((resolve) => window.setTimeout(resolve, ms));
  }

  async function waitForCaptureReady() {
    const deadline = Date.now() + 12000;
    while (Date.now() < deadline) {
      const surface = findCaptureSurface();
      if (!surface) {
        await wait(120);
        continue;
      }

      if (generationMode === 'aframe') {
        const scene = document.querySelector('a-scene#scene') || document.querySelector('a-scene');
        const loaded = !scene || scene.hasLoaded || (typeof scene.is === 'function' && scene.is('loaded'));
        if (!loaded) {
          await wait(120);
          continue;
        }
      }

      return surface;
    }

    return findCaptureSurface();
  }

  async function getCaptureSurface() {
    const surface = await waitForCaptureReady();
    if (!surface) {
      throw new Error('No live screenshot surface is available yet.');
    }
    return surface;
  }

  function canvasToBlob(canvas) {
    return new Promise((resolve, reject) => {
      if (typeof canvas.toBlob === 'function') {
        canvas.toBlob((blob) => {
          if (blob) {
            resolve(blob);
            return;
          }
          reject(new Error('Could not create the screenshot file.'));
        }, 'image/png');
        return;
      }

      try {
        const dataUrl = canvas.toDataURL('image/png');
        const base64 = dataUrl.split(',')[1] || '';
        const bytes = atob(base64);
        const array = new Uint8Array(bytes.length);
        for (let index = 0; index < bytes.length; index += 1) {
          array[index] = bytes.charCodeAt(index);
        }
        resolve(new Blob([array], { type: 'image/png' }));
      } catch (error) {
        reject(error);
      }
    });
  }

  function exportCanvasSurface(surface) {
    const rect = surface.getBoundingClientRect();
    const width = Math.max(1, surface.width || Math.round(rect.width) || 1);
    const height = Math.max(1, surface.height || Math.round(rect.height) || 1);
    const exportCanvas = document.createElement('canvas');
    exportCanvas.width = width;
    exportCanvas.height = height;
    const context = exportCanvas.getContext('2d');
    if (!context) {
      throw new Error('Screenshot export is unavailable in this browser.');
    }
    context.drawImage(surface, 0, 0, width, height);
    return exportCanvas;
  }

  async function exportSvgSurface(svg) {
    const rect = svg.getBoundingClientRect();
    const width = Math.max(1, Math.round(rect.width) || svg.viewBox?.baseVal?.width || 1);
    const height = Math.max(1, Math.round(rect.height) || svg.viewBox?.baseVal?.height || 1);
    const clone = svg.cloneNode(true);
    clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
    clone.setAttribute('width', String(width));
    clone.setAttribute('height', String(height));
    if (!clone.getAttribute('viewBox')) {
      clone.setAttribute('viewBox', '0 0 ' + width + ' ' + height);
    }

    const svgBlob = new Blob([new XMLSerializer().serializeToString(clone)], { type: 'image/svg+xml;charset=utf-8' });
    const svgUrl = URL.createObjectURL(svgBlob);
    try {
      const image = new Image();
      const imageReady = new Promise((resolve, reject) => {
        image.onload = function () {
          resolve(image);
        };
        image.onerror = function () {
          reject(new Error('Could not prepare the SVG export.'));
        };
      });
      image.src = svgUrl;
      await imageReady;

      const exportCanvas = document.createElement('canvas');
      exportCanvas.width = width;
      exportCanvas.height = height;
      const context = exportCanvas.getContext('2d');
      if (!context) {
        throw new Error('Screenshot export is unavailable in this browser.');
      }
      context.drawImage(image, 0, 0, width, height);
      return exportCanvas;
    } finally {
      URL.revokeObjectURL(svgUrl);
    }
  }

  async function exportSurfaceWithValidation(surface) {
    tryForceAframeRender(surface);
    const first = exportCanvasSurface(surface);
    if (generationMode !== 'aframe' || hasVisiblePixels(first)) {
      return first;
    }

    await wait(32);
    tryForceAframeRender(surface);
    await wait(32);
    const retry = exportCanvasSurface(surface);
    if (hasVisiblePixels(retry)) {
      return retry;
    }

    throw new Error('A-Frame could not produce a nonblank screenshot right now.');
  }

  window.__creatrExportCapture = {
    generationMode,
    requiresCanvasValidation: generationMode === 'aframe',
    getSurface: getCaptureSurface,
    captureCanvas: async function () {
      const surface = await getCaptureSurface();
      return surface.type === 'svg'
        ? exportSvgSurface(surface.node)
        : exportSurfaceWithValidation(surface.node);
    }
  };

  function downloadBlob(blob) {
    const objectUrl = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = objectUrl;
    link.download = filename;
    link.rel = 'noopener';
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
  }

  if (supportsScreenshot && button) {
    button.addEventListener('click', async function () {
      if (button.disabled) return;
      const originalLabel = button.getAttribute('aria-label') || 'Take screenshot';
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
      setStatus('');

      try {
        const surface = await getCaptureSurface();
        const blob = await canvasToBlob(surface.type === 'svg'
          ? await exportSvgSurface(surface.node)
          : await exportSurfaceWithValidation(surface.node));
        downloadBlob(blob);
      } catch (error) {
        const message = error instanceof Error ? error.message : 'Could not take a screenshot right now.';
        setStatus(/tainted canvases/i.test(message)
          ? 'This piece still contains an image or texture the browser will not export safely.'
          : message);
      } finally {
        button.disabled = false;
        button.removeAttribute('aria-busy');
        button.setAttribute('aria-label', originalLabel);
      }
    });
  }
})();
</script>
HTML;
}
