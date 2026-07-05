<?php

declare(strict_types=1);

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
{$pieceScriptTag}
{$bootstrap}
{$exportOverlayScript}
</body>
</html>
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
.immersive-export-actions{position:fixed;right:calc(1rem + env(safe-area-inset-right));bottom:calc(1rem + env(safe-area-inset-bottom));z-index:210;display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;justify-content:flex-end;max-width:min(24rem,calc(100vw - 2rem));}
.immersive-export-actions button{display:inline-flex;align-items:center;justify-content:center;width:2.9rem;height:2.9rem;border:1px solid rgba(255,255,255,0.16);border-radius:999px;background:rgba(0,0,0,0.62);color:#fff;padding:0;font:700 0.82rem/1 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;cursor:pointer;backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);}
.immersive-export-actions button:hover,.immersive-export-actions button:focus-visible{background:rgba(0,0,0,0.8);border-color:rgba(255,255,255,0.5);}
.immersive-export-actions button[disabled]{opacity:0.65;cursor:progress;}
.immersive-export-actions button svg{width:1.35rem;height:1.35rem;display:block;}
.c2-interactive-overlay{position:fixed;inset:0;z-index:180;background:#05070f;}
.c2-interactive-overlay[hidden]{display:none!important;}
.c2-interactive-overlay iframe{width:100%;height:100%;border:0;display:block;background:#05070f;}
.c2-interactive-overlay button{position:absolute;top:calc(1rem + env(safe-area-inset-top));right:calc(1rem + env(safe-area-inset-right));z-index:1;display:inline-flex;width:2.75rem;height:2.75rem;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,0.16);border-radius:999px;background:rgba(0,0,0,0.62);color:#fff;font-size:1.4rem;cursor:pointer;}
@media (max-width:640px){.immersive-export-actions{left:calc(1rem + env(safe-area-inset-left));right:calc(1rem + env(safe-area-inset-right));justify-content:center;}.immersive-export-actions button{flex:1 1 auto;}}
</style>
</head>
<body>
<div id="immersive-stage" tabindex="-1"></div>
<div id="piece-error" role="alert"></div>
<div id="c2-interactive-overlay" class="c2-interactive-overlay" hidden>
  <button id="c2-interactive-close" type="button" aria-label="Close interactive view">&times;</button>
  <iframe id="c2-interactive-frame" title="Interactive piece" sandbox="allow-scripts allow-same-origin"></iframe>
</div>
<div class="immersive-export-actions" role="toolbar" aria-label="Immersive piece actions">
  <button id="immersive-export-interact" type="button" aria-label="Open piece" hidden>
    <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
  </button>
  <button id="immersive-export-fullscreen" type="button" aria-label="Enter fullscreen">
    <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M16 3h3a2 2 0 0 1 2 2v3"/><path d="M21 16v3a2 2 0 0 1-2 2h-3"/><path d="M8 21H5a2 2 0 0 1-2-2v-3"/></svg>
  </button>
  <button id="immersive-export-png" type="button" aria-label="Download PNG">
    <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M9 4.5 7.8 6H5.5A2.5 2.5 0 0 0 3 8.5v9A2.5 2.5 0 0 0 5.5 20h13a2.5 2.5 0 0 0 2.5-2.5v-9A2.5 2.5 0 0 0 18.5 6h-2.3L15 4.5H9Zm3 4a4.75 4.75 0 1 1 0 9.5 4.75 4.75 0 0 1 0-9.5Zm0 1.75a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z"/></svg>
  </button>
</div>
<script>
function showPieceError(error){const el=document.getElementById('piece-error');if(!el)return;el.textContent=(error&&(error.stack||error.message))?(error.stack||error.message):String(error);el.style.display='block';}
window.addEventListener('error',event=>showPieceError(event.error||event.message));
window.addEventListener('unhandledrejection',event=>showPieceError(event.reason||'Unhandled promise rejection'));
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

const { mountAFrameImmersivePiece, mountGalleryPiece, mountThreeImmersivePiece } = await loadImmersiveRuntime();

const piece = {
  engine: {$jsonEngine},
  generationMode: {$jsonGenerationMode},
  title: {$jsonTitle},
  html: {$jsonHtml},
  css: {$jsonCss},
  code: {$jsonCode},
  fullViewSrcdoc: {$jsonFullView},
  initialViewState: {$jsonViewState},
  pngFilename: {$pngFilename}
};
const stage = document.getElementById('immersive-stage');
const overlay = document.getElementById('c2-interactive-overlay');
const overlayFrame = document.getElementById('c2-interactive-frame');
const overlayClose = document.getElementById('c2-interactive-close');
const interactBtn = document.getElementById('immersive-export-interact');
const fullscreenBtn = document.getElementById('immersive-export-fullscreen');
const pngBtn = document.getElementById('immersive-export-png');
let viewer = null;

function openInteractiveOverlay() {
  overlayFrame.srcdoc = piece.fullViewSrcdoc;
  overlay.hidden = false;
  overlayFrame.focus();
}
function closeInteractiveOverlay() {
  overlay.hidden = true;
  overlayFrame.removeAttribute('srcdoc');
  stage.focus();
}
overlayClose.addEventListener('click', closeInteractiveOverlay);
window.addEventListener('keydown', (event) => {
  if (event.key === 'Escape' && !overlay.hidden) closeInteractiveOverlay();
});

try {
  const controls = { showViewerControls: true, initialViewState: piece.initialViewState };
  if (piece.engine === 'three') {
    viewer = mountThreeImmersivePiece(stage, piece.code, piece.html, piece.css, showPieceError, controls);
  } else if (piece.engine === 'aframe') {
    viewer = mountAFrameImmersivePiece(stage, piece.code, piece.html, piece.css, showPieceError, controls);
  } else {
    const isInteractiveC2 = piece.generationMode === 'c2_interactive';
    viewer = mountGalleryPiece(stage, piece.code, piece.html, piece.css, piece.engine, piece.title, '', '', '', showPieceError, isInteractiveC2 ? openInteractiveOverlay : null, {
      ...controls,
      fullView: isInteractiveC2 ? null : { items: [{ type: 'iframe', srcdoc: piece.fullViewSrcdoc }] }
    });
    interactBtn.hidden = false;
    interactBtn.setAttribute('aria-label', isInteractiveC2 ? 'Open piece' : 'View full size');
    interactBtn.addEventListener('click', () => {
      if (isInteractiveC2) openInteractiveOverlay();
      else viewer?.openFullViewAt?.(0);
    });
  }
} catch (error) {
  showPieceError(error);
}

fullscreenBtn.addEventListener('click', async () => {
  try {
    if (document.fullscreenElement) {
      await document.exitFullscreen();
    } else if (document.documentElement.requestFullscreen) {
      await document.documentElement.requestFullscreen();
    }
  } catch (_) {}
});
document.addEventListener('fullscreenchange', () => {
  fullscreenBtn.setAttribute('aria-label', document.fullscreenElement ? 'Exit fullscreen' : 'Enter fullscreen');
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
pngBtn.addEventListener('click', async () => {
  if (pngBtn.disabled) return;
  const label = pngBtn.getAttribute('aria-label') || 'Download PNG';
  pngBtn.disabled = true;
  pngBtn.setAttribute('aria-label', 'Preparing PNG');
  try {
    let surface = null;
    if (!overlay.hidden && overlayFrame.contentDocument) {
      surface = Array.from(overlayFrame.contentDocument.querySelectorAll('canvas')).find((canvas) => canvas.getBoundingClientRect().width > 0 && canvas.getBoundingClientRect().height > 0);
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
    pngBtn.setAttribute('aria-label', label);
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
    $source = preg_replace('/^\s*export\s*\{\s*OrbitControls\s*\};?\s*$/m', '', $source) ?? $source;
    return $source;
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
    let isOrbitActive = false;
    let userHasInteracted = false;
    controls.addEventListener('start', () => { isOrbitActive = true; userHasInteracted = true; });
    controls.addEventListener('end', () => { isOrbitActive = false; });
    function animateControls() {
      const id = requestAnimationFrame(animateControls);
      rafIds.push(id);
      try {
        controls.update();
        if (isOrbitActive) userHasInteracted = true;
        if (!pieceDrivesOwnRender || userHasInteracted) {
          state.renderer.render(state.scene, state.camera);
        }
      } catch (error) {
        showPieceError(error);
      }
    }
    animateControls();
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
        'c2' => <<<'HTML'
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
  if (typeof window.sketch === 'function') window.sketch({ c2: window.c2, canvas, startFrame, loadImage, drawImage, drawImageCover });
} catch (error) { showPieceError(error); }
</script>
HTML,
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
    let isOrbitActive = false;
    let userHasInteracted = false;
    controls.addEventListener('start', () => { isOrbitActive = true; userHasInteracted = true; });
    controls.addEventListener('end', () => { isOrbitActive = false; });
    function animateControls() {
      const id = requestAnimationFrame(animateControls);
      rafIds.push(id);
      try {
        controls.update();
        if (isOrbitActive) userHasInteracted = true;
        if (!pieceDrivesOwnRender || userHasInteracted) {
          state.renderer.render(state.scene, state.camera);
        }
      } catch (error) {
        showPieceError(error);
      }
    }
    animateControls();
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

    $interactiveNote = piece_export_supports_screenshot_overlay($generationMode)
        ? "This export includes lower-left fullscreen and screenshot controls directly inside index.html.\n"
        : "This export includes a lower-left fullscreen control directly inside index.html. It does not include the screenshot control because this piece is not one of the interactive export modes.\n";

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
        . $interactiveNote
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

function piece_export_supports_screenshot_overlay(string $generationMode): bool
{
    return in_array($generationMode, ['c2_interactive', 'three', 'aframe'], true);
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

  if (!supportsScreenshot || !button) return;

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
        return scene.canvas;
      }
    }

    const selectors = generationMode === 'aframe'
      ? ['a-scene canvas', '.a-canvas', 'canvas']
      : ['canvas'];
    for (const selector of selectors) {
      const matches = Array.from(document.querySelectorAll(selector)).filter(isVisibleSurface);
      if (matches.length > 0) return matches[matches.length - 1];
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

  function exportSurface(surface) {
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

  async function exportSurfaceWithValidation(surface) {
    tryForceAframeRender(surface);
    const first = exportSurface(surface);
    if (generationMode !== 'aframe' || hasVisiblePixels(first)) {
      return first;
    }

    await wait(32);
    tryForceAframeRender(surface);
    await wait(32);
    const retry = exportSurface(surface);
    if (hasVisiblePixels(retry)) {
      return retry;
    }

    throw new Error('A-Frame could not produce a nonblank screenshot right now.');
  }

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

  button.addEventListener('click', async function () {
    if (button.disabled) return;
    const originalLabel = button.getAttribute('aria-label') || 'Take screenshot';
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    setStatus('');

    try {
      const surface = await waitForCaptureReady();
      if (!surface) {
        throw new Error('No live screenshot surface is available yet.');
      }
      const blob = await canvasToBlob(await exportSurfaceWithValidation(surface));
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
})();
</script>
HTML;
}
