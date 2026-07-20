<?php

declare(strict_types=1);

require_once __DIR__ . '/immersive-chrome.php';
if (!function_exists('public_copy_value')) {
    require_once __DIR__ . '/public-copy.php';
}

/**
 * Rewrites every local media reference (/image/{id}, /media/…,
 * /api/media-assets/{id}) in the given strings to a data: URI. Shared by
 * piece_render_document()'s capture_safe_media mode and the immersive live
 * view, whose wall textures rasterize SVG through an <img> where external
 * refs are silently dropped — un-inlined media simply vanishes there.
 * Returns the strings in the same order they were given.
 */
function piece_inline_local_media(array $parts): array
{
    $mediaMap = piece_build_media_manifest($parts);
    if ($mediaMap === []) {
        return $parts;
    }
    return array_map(static function ($content) use ($mediaMap) {
        return piece_export_rewrite_media_refs((string) $content, static function (string $normalizedRef) use ($mediaMap): ?string {
            $asset = $mediaMap[$normalizedRef] ?? null;
            return is_array($asset) ? (string) ($asset['data_url'] ?? '') : null;
        });
    }, $parts);
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
                if (!is_array($asset)) {
                    return piece_request_origin() . $src;
                }
                $dataUrl = trim((string) ($asset['data_url'] ?? ''));
                // Large GLBs are deliberately not inlined in live/capture
                // documents to avoid exhausting PHP memory. Fall back to
                // the same-origin URL so A-Frame can fetch the binary.
                return $dataUrl !== '' ? $dataUrl : piece_request_origin() . $src;
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
    $contextGenerationMode = art_piece_version_generation_mode($version, $piece);
    $contextSonicDecoded = !empty($version['sonic_params']) ? json_decode((string) $version['sonic_params'], true) : null;
    $contextCapabilities = piece_sound_capability_contract(
        $contextGenerationMode,
        is_array($contextSonicDecoded) ? $contextSonicDecoded : [],
        piece_camera_overlay_enabled($version),
        piece_camera_placement($version),
        piece_regular_hand_motion_enabled($version)
    );
    $sonicDebug = isset($_GET['sonicdebug']) && (string) $_GET['sonicdebug'] === '1';
    $jsonContext = json_encode([
        'pieceId' => (int) ($piece['id'] ?? 0),
        'viewerMode' => (string) ($options['viewer_mode'] ?? 'default'),
        'interactive' => !empty($options['interactive']),
        'disableMotion' => !empty($options['disable_motion']),
        // c2_interactive pieces attach their own pointer handlers regardless
        // of viewer_mode/interactive above (that option controls unrelated
        // chrome), so the runtime needs this separately to know whether
        // pointer movement is a meaningful sonification signal for a c2 piece.
        'c2Interactive' => $contextGenerationMode === 'c2_interactive',
        // Camera overlay is a per-piece Metadata-tab permission now (see
        // piece_camera_overlay_enabled), no longer implied by the audio
        // hand-tracking voice — the runtime must know it even when sonic
        // is null so camera-only pieces still get the message bridge.
        'cameraOverlay' => !empty($contextCapabilities['camera_view']),
        'cameraOpacity' => !empty($contextCapabilities['camera_opacity']),
        // Where the feed renders when the visitor enables the camera:
        // 'background' or 'overlay' (already engine-defaulted by the
        // contract when the piece stores no explicit placement).
        'cameraPlacement' => (string) ($contextCapabilities['camera_placement'] ?? 'overlay'),
        // Hand control (camera steering + tilt fallback) rides the camera
        // permission or hand-tracking voice — see the capability contract;
        // needed even when sonic is null so steering works on sound-less
        // pieces.
        'handControl' => !empty($contextCapabilities['hand_control']),
        // srcdoc has an about:srcdoc URL, so it cannot see the outer page's
        // query string. Propagate the existing opt-in diagnostic flag.
        'sonicDebug' => $sonicDebug,
        // Sound is gated per-piece (no master switch), not per-engine — every
        // engine can carry sonic_params now. Three.js/A-Frame sonify camera
        // motion, c2_interactive sonifies pointer motion, everything else
        // (p5, plain c2, svg) has no motion signal on this view and plays a
        // random idle note pattern instead.
        'sonic' => !empty($version['sonic_params'])
            ? (($sonicDecoded = json_decode((string) $version['sonic_params'], true)) && ($sonicDecoded['enabled'] ?? true) !== false ? $sonicDecoded : null)
            : null,
        // Cache-busted by file mtime, matching piece-runtime.js's own ?v=
        // pattern — without this, sonic-controller.js/Tone.js were being
        // loaded from a fixed, unversioned URL, so browsers could keep
        // serving a stale cached copy of the sonification engine
        // indefinitely after a fix ships (silently reproducing already-
        // fixed bugs). $options overrides exist for bundle-mode callers
        // that need ZIP-local runtime/ paths instead.
        'sonicControllerSource' => !empty($options['sonic_controller_source'])
            ? (string) $options['sonic_controller_source']
            : ('/assets/js/sonic-controller.js?v=' . (int) @filemtime(dirname(__DIR__, 2) . '/assets/js/sonic-controller.js')),
        'toneSource' => !empty($options['tone_source'])
            ? (string) $options['tone_source']
            : ('/assets/vendor/tone/Tone.js?v=' . (int) @filemtime(dirname(__DIR__, 2) . '/assets/vendor/tone/Tone.js')),
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
    $aframeModelRuntimeVersion = (int) @filemtime(dirname(__DIR__, 2) . '/assets/js/aframe-model-runtime.js');
    $aframeModelRuntimeTag = $engine === 'aframe'
        ? '<script src="' . htmlspecialchars($requestOrigin . '/assets/js/aframe-model-runtime.js?v=' . $aframeModelRuntimeVersion, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '"></script>'
        : '';
    $spatialVersion = (int) @filemtime(dirname(__DIR__, 2) . '/assets/js/spatial-presentation.js');
    $spatialScriptUrl = htmlspecialchars($requestOrigin . '/assets/js/spatial-presentation.js?v=' . $spatialVersion, ENT_QUOTES | ENT_HTML5, 'UTF-8');

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
<script src="{$spatialScriptUrl}"></script>
{$aframeModelRuntimeTag}
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

/**
 * Resolves a media manifest entry to whatever a bundled export ZIP's
 * index.html/scripts/styles should actually reference. Prefers an inline
 * data: URI when one was built (small assets), otherwise falls back to the
 * asset's relative in-ZIP path (e.g. `media/media-196.glb`) — every export
 * writes the real file there regardless of whether it was also inlined (see
 * piece_export_build_manifest()'s $mediaFiles), so the relative path always
 * resolves correctly once extracted/opened via file://, unlike the
 * site-absolute /media/{id} reference the code originally contained (which
 * 404s/CORS-fails outside the live server). Only used by export/bundle
 * functions — piece_render_document()'s capture_safe_media mode (the live
 * same-origin admin preview iframe) intentionally keeps its own
 * data_url-or-original-URL fallback, since there is no bundled folder next
 * to that context and the absolute URL works fine there.
 */
function piece_export_asset_replacement(array $asset, string $fallback): string
{
    $dataUrl = (string) ($asset['data_url'] ?? '');
    if ($dataUrl !== '') {
        return $dataUrl;
    }
    $path = (string) ($asset['path'] ?? '');
    return $path !== '' ? $path : $fallback;
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
                    ? piece_export_asset_replacement($asset, (string) ($asset['path'] ?? ''))
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
                    ? piece_export_asset_replacement($asset, $src)
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
    $sonicScript = piece_export_sonic_script($engine, (string) ($version['sonic_params'] ?? ''), $runtimeMode, (int) ($piece['id'] ?? 0), $generationMode, piece_camera_overlay_enabled($version), piece_camera_placement($version), piece_regular_hand_motion_enabled($version));
    $exportCapabilities = piece_sound_capability_contract(
        $generationMode,
        is_array(json_decode((string) ($version['sonic_params'] ?? ''), true)) ? json_decode((string) ($version['sonic_params'] ?? ''), true) : [],
        piece_camera_overlay_enabled($version),
        piece_camera_placement($version),
        piece_regular_hand_motion_enabled($version)
    );
    $exportCameraPlacement = $exportCapabilities['camera_placement'];
    $flatSpatialExport = !empty($exportCapabilities['hand_control']) && in_array($generationMode, ['p5', 'c2', 'c2_interactive', 'svg'], true);
    if ($runtimeMode === 'bundle' && $flatSpatialExport) {
        $inlineRuntime .= "\n<script src=\"runtime/three/three.global.js\"></script>";
    }
    $spatialExportScript = '';
    if ($flatSpatialExport) {
        $spatialSource = piece_escape_inline_script(piece_export_runtime_source_file('assets/js/spatial-presentation.js'));
        $surfaceExpr = $generationMode === 'svg'
            ? "document.querySelector('#runtime-root svg') || document.querySelector('svg')"
            : "document.querySelector('#runtime-root canvas') || document.querySelector('canvas')";
        $spatialInteractive = $generationMode === 'c2_interactive' ? 'true' : 'false';
        $spatialPlacementJson = json_encode($exportCameraPlacement, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $spatialThreeModuleJson = json_encode($runtimeMode === 'bundle' ? 'runtime/three/three.module.js' : '', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $spatialExportScript = "<script>\n{$spatialSource}\n</script>\n<script>\n(function(){var baseHooks=window.__pieceHandHooks||{};var spatial=window.CreatrSpatialPresentation&&window.CreatrSpatialPresentation.create({getSurface:function(){return {$surfaceExpr};},interactive:{$spatialInteractive},cameraPlacement:{$spatialPlacementJson},threeModuleSrc:{$spatialThreeModuleJson},getCameraVideo:function(){return typeof baseHooks.getBackgroundVideo==='function'?baseHooks.getBackgroundVideo():baseHooks._cameraOverlay||null;},getCameraOpacity:function(){return typeof baseHooks.getBackgroundOpacity==='function'?baseHooks.getBackgroundOpacity():(baseHooks._cameraOpacity==null?0.35:baseHooks._cameraOpacity);}});if(spatial)window.__pieceHandHooks=Object.assign(baseHooks,spatial);})();\n</script>";
    }
    $handGuideVariant = $generationMode === 'c2_interactive' ? 'c2_interactive_latched' : 'default';
    $handGuideMarkup = !empty($exportCapabilities['hand_control'])
        ? '<style>' . immersive_stage_hand_guide_css() . '</style>' . immersive_stage_hand_guide_markup('offline', 'offline-sound-btn', $handGuideVariant)
        : '';
    $bootstrap = piece_export_bootstrap($engine, $generationMode, $runtimeMode, $exportCameraPlacement);
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
    $aframeModelRuntimeTag = $runtimeMode === 'bundle' && $engine === 'aframe'
        ? '<script src="runtime/aframe-model-runtime.js"></script>'
        : '';

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title}</title>
<link rel="icon" href="data:,">
{$bundleMeta}
{$aframeCaptureShim}
{$imports}
{$aframeModelRuntimeTag}
{$cssTag}
{$inlineRuntime}
</head>
<body>
<div id="runtime-root">{$html}</div>
<div id="piece-error" role="alert"></div>
{$exportOverlayMarkup}
{$handGuideMarkup}
<script>
function showPieceError(error){const el=document.getElementById('piece-error');if(!el)return;el.textContent=(error&&(error.stack||error.message))?(error.stack||error.message):String(error);el.style.display='block';}
function isNonImpactingRuntimeIssue(error,source){const m=typeof error?.message==='string'?error.message:String(error||'');const s=String(source||error?.fileName||'');return /^(?:chrome|moz|safari)-extension:/i.test(s)||/ResizeObserver loop (?:limit exceeded|completed with undelivered notifications)/i.test(m)||/Could not establish connection\. Receiving end does not exist/i.test(m)||/A listener indicated an asynchronous response.*message channel closed/i.test(m);}
window.addEventListener('error',event=>{const e=event.error||event.message;if(!isNonImpactingRuntimeIssue(e,event.filename))showPieceError(e);});
window.addEventListener('unhandledrejection',event=>{const r=event.reason;const m=typeof r?.message==='string'?r.message:String(r||'');if((r?.name==='AbortError'&&/worklet/i.test(m))||isNonImpactingRuntimeIssue(r)){event.preventDefault();return;}showPieceError(r||'Unhandled promise rejection');});
</script>
{$sonicScript}
{$pieceScriptTag}
{$bootstrap}
{$spatialExportScript}
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
 * button (+ volume/keyboard popover) directly, mirroring piece-runtime.js's
 * own standalone fallback button. The actual synth/scale/motion/idle/
 * volume/keyboard engine is delegated to the shared sonic-controller.js
 * (vendored into the export by piece_export_build_manifest), the same
 * module the live immersive views use — this function only builds the
 * self-mounted UI and the __creatrSonicSetMover indirection three/aframe
 * bootstraps feed a camera getter into once their scene exists. In bundle
 * mode Tone.js/sonic-controller.js are loaded from ZIP-local runtime/ paths
 * so direct-open file:// exports do not depend on the live site; in cdn
 * mode they load from the same self-hosted paths the live view uses.
 */
/**
 * Resolves the per-piece camera overlay permission. camera_overlay is a
 * dedicated version column (Metadata tab), decoupled from the audio
 * hand-tracking voice: 1 = on, 0 = off, NULL/absent = legacy behavior where
 * the camera followed hand_tracking for three/aframe/c2_interactive.
 */
function piece_camera_overlay_enabled(array $version, string $surface = 'regular'): ?bool
{
    if ($surface === 'immersive') {
        $immersive = $version['immersive_camera_overlay'] ?? null;
        if ($immersive !== null && $immersive !== '') return (int) $immersive === 1;
    }
    $raw = $version['camera_overlay'] ?? null;
    if ($raw !== null && $raw !== '') {
        return (int) $raw === 1;
    }

    return null;
}

/**
 * Resolves the stored per-piece camera placement ('background'/'overlay'),
 * or NULL when unset — piece_sound_capability_contract() then falls back to
 * the engine default (background for three/aframe, overlay for 2D).
 */
function piece_camera_placement(array $version, string $surface = 'regular'): ?string
{
    if ($surface === 'immersive') {
        $immersive = $version['immersive_camera_placement'] ?? null;
        if (in_array($immersive, ['background', 'overlay'], true)) return $immersive;
    }
    $raw = $version['camera_placement'] ?? null;
    return in_array($raw, ['background', 'overlay'], true) ? $raw : null;
}

function piece_regular_hand_motion_enabled(array $version): ?bool
{
    $raw = $version['regular_hand_motion'] ?? null;
    return ($raw === null || $raw === '') ? null : (int) $raw === 1;
}

function piece_sound_capability_contract(string $generationMode, array $sonicParams, ?bool $cameraOverlay = null, ?string $cameraPlacement = null, ?bool $handMotion = null): array
{
    $voices = is_array($sonicParams['extras']['voices'] ?? null) ? $sonicParams['extras']['voices'] : [];
    $interactiveC2 = $generationMode === 'c2_interactive';
    // NULL camera_overlay = the camera OPTION is available on every engine
    // (visitor-activated toggle; nothing auto-starts) — 2026-07-12 decision,
    // replacing the legacy follow-hand-tracking rule on three/aframe/
    // c2_interactive. An explicit 0 still turns the camera off per piece.
    $cameraView = $cameraOverlay ?? true;
    // No sonic content at all means no sound capability, full stop — this is
    // the single place that rule lives; callers must not re-derive it.
    $sound = $sonicParams !== [] && ($sonicParams['enabled'] ?? true) !== false;
    // Camera theremin is audio-only. A disabled/missing sound design can
    // never expose it, even if stale sonic JSON still carries the voice.
    $handTracking = $sound && !empty($voices['hand_tracking']);
    return [
        'sound' => $sound,
        'keyboard' => $sound && ($voices['melodic'] ?? true) !== false,
        'hand_tracking' => $handTracking,
        // Visual hand motion is a camera-domain presentation capability.
        // NULL defaults on; callers pass false for an explicit regular Off or
        // for camera-free exports. Immersive surfaces always pass true.
        'hand_control' => $handMotion ?? true,
        'camera_view' => $cameraView,
        // Every camera surface blends now: 2D engines via the DOM <video>
        // overlay's opacity, three/aframe via a camera-attached blended quad,
        // the immersive gallery room via the wall-projection material.
        'camera_opacity' => $cameraView,
        // Where the feed renders when enabled: 'background' (behind the
        // piece — the 3D engines' blended quad, or a DOM <video> behind a
        // 2D canvas) or 'overlay' (a DOM <video> above the piece). NULL
        // placement falls back to the engine default.
        'camera_placement' => in_array($cameraPlacement, ['background', 'overlay'], true)
            ? $cameraPlacement
            : art_piece_camera_placement_default($generationMode),
        'microphone' => $sound,
    ];
}

function piece_export_sonic_script(string $engine, string $sonicParamsJson, string $runtimeMode, int $pieceId = 0, string $generationMode = '', ?bool $cameraOverlay = null, ?string $cameraPlacement = null, ?bool $handMotion = null): string
{
    // Every engine can carry sonic_params (matches the live regular-view
    // gate in piece_render_document()). three/aframe get camera-driven
    // sonification via __creatrSonicSetMover (wired in piece_export_bootstrap);
    // p5/c2/svg have no motion signal in this export and get the idle
    // random-note pattern only (create()'s getMover-optional handling).
    $decoded = json_decode($sonicParamsJson, true);
    if (!is_array($decoded)) {
        $decoded = ['enabled' => false];
    }

    $capabilities = piece_sound_capability_contract($generationMode !== '' ? $generationMode : $engine, $decoded, $cameraOverlay, $cameraPlacement, $handMotion);
    if (!$capabilities['sound'] && !$capabilities['camera_view'] && !$capabilities['hand_control']) {
        return '';
    }

    $sonicJson = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $capabilitiesJson = json_encode($capabilities);
    $isBundle = $runtimeMode === 'bundle';
    // Cache-busted by file mtime in the live (non-bundle) case — see the
    // matching comment in piece_render_document(). Bundle mode always
    // fetches the ZIP-local copy, so it needs no versioning.
    $toneSrc = $isBundle
        ? 'runtime/tone/Tone.js'
        : rtrim(piece_request_origin(), '/') . '/assets/vendor/tone/Tone.js?v=' . (int) @filemtime(dirname(__DIR__, 2) . '/assets/vendor/tone/Tone.js');
    $sonicControllerSrc = $isBundle
        ? 'runtime/sonic-controller.js'
        : rtrim(piece_request_origin(), '/') . '/assets/js/sonic-controller.js?v=' . (int) @filemtime(dirname(__DIR__, 2) . '/assets/js/sonic-controller.js');
    // The ?v= on vision_bundle.mjs matters beyond ordinary cache-busting:
    // browsers that cached it while a host served .mjs as text/plain keep
    // failing module import off 304 revalidations forever (a 304 preserves
    // the stored Content-Type), so the URL itself must change to recover.
    // The wasm dir is a bare prefix (FilesetResolver appends filenames) and
    // cannot carry a version; its consumers tolerate a stale cached type.
    $mediaPipeVisionSrc = $isBundle
        ? 'runtime/mediapipe-hands/vision_bundle.mjs'
        : rtrim(piece_request_origin(), '/') . '/assets/vendor/mediapipe-hands/vision_bundle.mjs?v=' . (int) @filemtime(dirname(__DIR__, 2) . '/assets/vendor/mediapipe-hands/vision_bundle.mjs');
    $mediaPipeWasmDir = $isBundle
        ? 'runtime/mediapipe-hands/'
        : rtrim(piece_request_origin(), '/') . '/assets/vendor/mediapipe-hands/';
    $mediaPipeModelSrc = $isBundle
        ? 'runtime/mediapipe-hands/hand_landmarker.task'
        : rtrim(piece_request_origin(), '/') . '/assets/vendor/mediapipe-hands/hand_landmarker.task?v=' . (int) @filemtime(dirname(__DIR__, 2) . '/assets/vendor/mediapipe-hands/hand_landmarker.task');
    $toneSrcJson = json_encode($toneSrc);
    $sonicControllerSrcJson = json_encode($sonicControllerSrc);
    $mediaPipeVisionSrcJson = json_encode($mediaPipeVisionSrc, JSON_UNESCAPED_SLASHES);
    $mediaPipeWasmDirJson = json_encode($mediaPipeWasmDir, JSON_UNESCAPED_SLASHES);
    $mediaPipeModelSrcJson = json_encode($mediaPipeModelSrc, JSON_UNESCAPED_SLASHES);
    $pieceIdJson = json_encode($pieceId);
    // KEEP IN SYNC with SONIC_INSTRUMENTS in public/assets/js/sonic-controller.js.
    $instrumentOptionsJson = json_encode([
        ['synth', 'Synth'], ['amsynth', 'AM Synth'], ['fmsynth', 'FM Synth'],
        ['membranesynth', 'Membrane'], ['metalsynth', 'Metal'], ['plucksynth', 'Plucked String'],
        ['duosynth', 'Duo Synth'],
    ]);

    $handRowElementsScript = '';
    $handRowAppendScript = '';
    $handRowWiringScript = '';

    if ($capabilities['hand_tracking']) {
        $handRowElementsScript .= <<<'JS'

    var handRow = document.createElement('div');
    handRow.style.cssText = 'display:flex;align-items:center;justify-content:space-between;gap:0.5rem;';
    var handLabel = document.createElement('span');
    handLabel.textContent = 'Hand-tracking';
    var handToggle = document.createElement('button');
    handToggle.type = 'button';
    handToggle.className = 'offline-sound-btn';
    handToggle.textContent = 'Camera theremin';
    handToggle.setAttribute('aria-pressed', 'false');
    handToggle.style.cssText = 'border:1px solid rgba(255,255,255,0.18);border-radius:0.6rem;background:rgba(255,255,255,0.06);color:#fff;font:inherit;font-weight:600;padding:0.3rem 0.55rem;cursor:pointer;';
    handRow.appendChild(handLabel); handRow.appendChild(handToggle);

JS;
        $handRowAppendScript .= "\n    panel.appendChild(handRow);";
        $handRowWiringScript .= <<<'JS'

    handToggle.addEventListener('click', async function () {
      var turningOn = !engine || engine.getInputMode() !== 'hand';
      if (turningOn) {
        // Camera FIRST, inside this click's task: create the engine
        // synchronously (sonic-controller.js is a plain <script> above, so
        // window.CreatrSonicController is already present) and start
        // enableHandTracking(), whose first await is getUserMedia — WebKit's
        // transient activation does not survive the Tone.js load inside
        // ensureEnabled(). Denial/error silently reverts — no error banner.
        if (!engine && window.CreatrSonicController) {
          engine = window.CreatrSonicController.create(sonicParams, {
            getMover: function () { return getMover ? getMover() : null; },
            allowHandControl: !!capabilities.hand_control,
            toneSrc: window.__creatrToneSrc,
            mediaPipeVisionSrc: window.__creatrMediaPipeVisionSrc,
            mediaPipeWasmDir: window.__creatrMediaPipeWasmDir,
            mediaPipeModelSrc: window.__creatrMediaPipeModelSrc,
          });
        }
        var handPromise = engine ? engine.enableHandTracking() : null;
        var ok = await ensureEnabled();
        var handOk = ok && engine ? await (handPromise !== null ? handPromise : engine.enableHandTracking()) : false;
        if (handOk) {
          engine.setInputMode('hand');
          handToggle.setAttribute('aria-pressed', 'true');
        }
      } else {
        engine.disableHandTracking();
        engine.setInputMode('motion');
        handToggle.setAttribute('aria-pressed', 'false');
      }
    });

JS;
    }

    if ($capabilities['hand_control'] || $capabilities['camera_view']) {
        $handRowWiringScript .= <<<'JS'

    function createEngineSyncForCamera() {
      if (engine || !window.CreatrSonicController) return engine;
      // sonicParams may be a sound-less {enabled:false} stub here — the
      // engine then exists purely for the camera/hand-control pipeline
      // (allowHandControl mirrors the server contract), never audio:
      // ensureEnabled() refuses when !capabilities.sound.
      engine = window.CreatrSonicController.create(sonicParams, {
        getMover: function () { return getMover ? getMover() : null; },
        allowHandControl: !!capabilities.hand_control,
        toneSrc: window.__creatrToneSrc,
        mediaPipeVisionSrc: window.__creatrMediaPipeVisionSrc,
        mediaPipeWasmDir: window.__creatrMediaPipeWasmDir,
        mediaPipeModelSrc: window.__creatrMediaPipeModelSrc,
      });
      return engine;
    }

JS;
    }

    if ($capabilities['hand_control']) {
        $handRowElementsScript .= <<<'JS'

    var handControlRow = document.createElement('div');
    handControlRow.style.cssText = 'display:flex;align-items:center;justify-content:space-between;gap:0.5rem;';
    var handControlLabel = document.createElement('span');
    handControlLabel.textContent = 'Hand control';
    var handControlToggle = document.createElement('button');
    handControlToggle.type = 'button';
    handControlToggle.className = 'offline-sound-btn';
    handControlToggle.textContent = 'Steer the piece';
    handControlToggle.setAttribute('aria-pressed', 'false');
    handControlToggle.style.cssText = 'border:1px solid rgba(255,255,255,0.18);border-radius:0.6rem;background:rgba(255,255,255,0.06);color:#fff;font:inherit;font-weight:600;padding:0.3rem 0.55rem;cursor:pointer;';
    handControlRow.appendChild(handControlLabel); handControlRow.appendChild(handControlToggle);
    var handControlStatus = document.createElement('p');
    handControlStatus.setAttribute('role', 'status');
    handControlStatus.setAttribute('aria-live', 'polite');
    handControlStatus.setAttribute('aria-atomic', 'true');
    handControlStatus.hidden = true;
    handControlStatus.style.cssText = 'margin:-0.15rem 0 0;padding:0.48rem 0.58rem;border:1px solid rgba(103,232,249,0.48);border-radius:0.55rem;background:rgba(8,47,73,0.72);color:rgba(236,254,255,0.96);font-size:0.76rem;line-height:1.35;';
    var resetViewRow = document.createElement('div');
    resetViewRow.style.cssText = 'display:flex;align-items:center;justify-content:space-between;gap:0.5rem;';
    var resetViewLabel = document.createElement('span');
    resetViewLabel.textContent = 'View pose';
    var resetViewButton = document.createElement('button');
    resetViewButton.type = 'button';
    resetViewButton.className = 'offline-sound-btn';
    resetViewButton.textContent = 'Reset view';
    resetViewButton.style.cssText = 'border:1px solid rgba(255,255,255,0.18);border-radius:0.6rem;background:rgba(255,255,255,0.06);color:#fff;font:inherit;font-weight:600;padding:0.3rem 0.55rem;cursor:pointer;';
    resetViewRow.appendChild(resetViewLabel); resetViewRow.appendChild(resetViewButton);

JS;
        $handRowAppendScript .= "\n    panel.appendChild(handControlRow);\n    panel.appendChild(handControlStatus);\n    panel.appendChild(resetViewRow);";
        $handRowWiringScript .= <<<'JS'

    var handControlActive = false;
    var handControlActivationEpoch = 0;
    var tiltController = null;
    var gestureRouter = null;
    var gestureRouterHooks = null;
    var gestureRouterEngine = null;
    var gestureModeIndicator = null;
    var handControlStatusTimer = 0;
    var handControlPreparingAt = 0;
    var handPreparationPromise = null;
    function setHandControlStatus(message, removeAfter) {
      window.clearTimeout(handControlStatusTimer);
      // Status renders in the same bottom-center stage pill as the
      // gesture-mode labels — in the view, not the control panel. The panel
      // element stays as a hidden mirror for assistive tech.
      setGestureModeIndicatorText(message || '');
      handControlStatus.textContent = message || '';
      handControlStatus.hidden = true;
      if (message && removeAfter > 0) {
        handControlStatusTimer = window.setTimeout(function() {
          setGestureModeIndicatorText('');
          handControlStatus.textContent = '';
        }, removeAfter);
      }
    }
    function prepareHandControl() {
      handControlPreparingAt = Date.now();
      handControlStatus.setAttribute('aria-busy', 'true');
      handControlStatus.dataset.state = 'loading';
      setHandControlStatus('Preparing hand steering…', 0);
      if (handPreparationPromise) return handPreparationPromise;
      handPreparationPromise = loadSonicControllerOnce().then(function(CSC) {
        return CSC.preloadHandTracker ? CSC.preloadHandTracker({
          mediaPipeVisionSrc: window.__creatrMediaPipeVisionSrc,
          mediaPipeWasmDir: window.__creatrMediaPipeWasmDir,
          mediaPipeModelSrc: window.__creatrMediaPipeModelSrc
        }) : false;
      }).catch(function() { return false; });
      return handPreparationPromise;
    }
    function setGestureModeIndicator(mode) {
      if (!gestureModeIndicator) {
        gestureModeIndicator = document.createElement('div');
        gestureModeIndicator.setAttribute('role', 'status');
        gestureModeIndicator.setAttribute('aria-live', 'polite');
        gestureModeIndicator.style.cssText = 'position:fixed;left:50%;bottom:1rem;transform:translateX(-50%);z-index:2147483646;padding:.38rem .72rem;border:1px solid rgba(255,255,255,.2);border-radius:999px;background:rgba(0,0,0,.68);color:#fff;font:600 .72rem/1.2 system-ui,sans-serif;letter-spacing:.06em;text-transform:uppercase;pointer-events:none;';
        document.body.appendChild(gestureModeIndicator);
      }
      var labels = { look: 'Look', orbit: 'Orbit', travel: 'Move', 'travel-ready': 'Point + pinch to move', 'orbit-ready': 'Pinch to orbit' };
      gestureModeIndicator.textContent = labels[mode] || '';
      gestureModeIndicator.hidden = !labels[mode];
    }
    // Raw-text twin: steering status shares the gesture pill.
    function setGestureModeIndicatorText(text) {
      if (!gestureModeIndicator) setGestureModeIndicator(null);
      if (!gestureModeIndicator) return;
      gestureModeIndicator.textContent = text || '';
      gestureModeIndicator.hidden = !text;
    }
    handControlToggle.addEventListener('focus', prepareHandControl);
    handControlToggle.addEventListener('click', async function () {
      var turningOn = handControlToggle.getAttribute('aria-pressed') !== 'true';
      var activationEpoch = ++handControlActivationEpoch;
      if (handControlToggle.dataset.capabilityFallback === 'device_tilt') {
        if (!tiltController && window.CreatrSonicController && window.__pieceHandHooks) {
          tiltController = window.CreatrSonicController.createDeviceTiltController(function(nx, ny) {
            window.__pieceHandHooks.handPoint(nx, ny);
          });
        }
        var tiltOk = turningOn && tiltController ? await tiltController.enable() : false;
        if (!turningOn && tiltController) tiltController.disable();
        handControlToggle.setAttribute('aria-pressed', tiltOk ? 'true' : 'false');
        // Reset view stays available while tilt steering is armed — the
        // runtime ignores steering commands during the reset animation.
        resetViewButton.disabled = false;
        return;
      }
      if (!turningOn) {
        handControlActive = false;
        resetViewButton.disabled = false;
        if (engine) { engine.onHandFrame(null); engine.disableHandControl(); }
        var disabledHooks = window.__pieceHandHooks;
        if (disabledHooks && typeof disabledHooks.setHandSteering === 'function') {
          try { await disabledHooks.setHandSteering(false); } catch (_e) {}
        }
        if (gestureRouter) gestureRouter.reset('disabled');
        gestureRouter = null;
        gestureRouterHooks = null;
        gestureRouterEngine = null;
        setGestureModeIndicator('idle');
        handControlToggle.setAttribute('aria-pressed', 'false');
        return;
      }
      handControlToggle.setAttribute('aria-pressed', 'true');
      var eng = createEngineSyncForCamera();
      var controlOk = eng ? await eng.enableHandControl() : false;
      if (activationEpoch !== handControlActivationEpoch || handControlToggle.getAttribute('aria-pressed') !== 'true') {
        if (eng) { eng.onHandFrame(null); eng.disableHandControl(); }
        return;
      }
      if (controlOk) {
        var activeHooks = window.__pieceHandHooks;
        if (!activeHooks || typeof activeHooks.setHandSteering !== 'function') controlOk = false;
        else { try { controlOk = (await activeHooks.setHandSteering(true)) !== false; } catch (_e) { controlOk = false; } }
      }
      if (activationEpoch !== handControlActivationEpoch || !controlOk) {
        handControlActive = false;
        resetViewButton.disabled = false;
        if (eng) { eng.onHandFrame(null); eng.disableHandControl(); }
        handControlToggle.setAttribute('aria-pressed', 'false');
        return;
      }
      if (controlOk) {
        handControlActive = true;
        var handPinched = false;
        eng.onHandFrame(function (hand) {
          if (!handControlActive) return;
          var hooks = window.__pieceHandHooks;
          if (!hooks) return;
          if (typeof hooks.handCommand === 'function' && window.CreatrSonicController && window.CreatrSonicController.createClutchedGestureRouter) {
            if (!gestureRouter || gestureRouterHooks !== hooks || gestureRouterEngine !== hooks.engine) {
              if (gestureRouter) gestureRouter.reset('hook-changed');
              gestureRouterHooks = hooks;
              gestureRouterEngine = hooks.engine;
              gestureRouter = window.CreatrSonicController.createClutchedGestureRouter({
                engine: hooks.engine,
                onCommand: function(command) {
                  var currentHooks = window.__pieceHandHooks;
                  if (currentHooks && typeof currentHooks.handCommand === 'function') currentHooks.handCommand(command);
                },
                onMode: setGestureModeIndicator
              });
            }
            gestureRouter.update(hand);
            return;
          }
          if (!hand) {
            if (handPinched) { handPinched = false; try { hooks.handPress && hooks.handPress(false); } catch (_e) {} }
            return;
          }
          var wrist = hand[0];
          if (!hooks.handPoint || !wrist) return;
          try { hooks.handPoint(1 - wrist.x, wrist.y); } catch (_e) {}
          // Pinch (thumb tip <-> index tip) = pointer button, mirroring
          // handControlBinding in piece-runtime.js — keep in sync.
          if (typeof hooks.handPress === 'function' && hand[4] && hand[8]) {
            var gap = Math.hypot(hand[4].x - hand[8].x, hand[4].y - hand[8].y, (hand[4].z || 0) - (hand[8].z || 0));
            var next = handPinched ? gap < 0.09 : gap < 0.055;
            if (next !== handPinched) {
              handPinched = next;
              try { hooks.handPress(next); } catch (_e) {}
            }
          }
        });
        handControlToggle.setAttribute('aria-pressed', 'true');
      }
    });
    resetViewButton.addEventListener('click', async function () {
      if (handControlActive) return;
      var hooks = window.__pieceHandHooks;
      if (!hooks || typeof hooks.resetView !== 'function') return;
      resetViewButton.disabled = true;
      resetViewButton.setAttribute('aria-busy', 'true');
      try { await hooks.resetView(); } catch (_e) {}
      resetViewButton.disabled = false;
      resetViewButton.removeAttribute('aria-busy');
    });

JS;
    }

    if ($capabilities['camera_view']) {
        $handRowElementsScript .= <<<'JS'

    var cameraBgRow = document.createElement('div');
    cameraBgRow.style.cssText = 'display:flex;align-items:center;justify-content:space-between;gap:0.5rem;';
    var cameraBgLabel = document.createElement('span');
    cameraBgLabel.textContent = 'Camera view';
    var cameraBgToggle = document.createElement('button');
    cameraBgToggle.type = 'button';
    cameraBgToggle.className = 'offline-sound-btn';
    cameraBgToggle.textContent = 'Show camera';
    cameraBgToggle.setAttribute('aria-pressed', 'false');
    cameraBgToggle.style.cssText = 'border:1px solid rgba(255,255,255,0.18);border-radius:0.6rem;background:rgba(255,255,255,0.06);color:#fff;font:inherit;font-weight:600;padding:0.3rem 0.55rem;cursor:pointer;';
    cameraBgRow.appendChild(cameraBgLabel); cameraBgRow.appendChild(cameraBgToggle);
    var cameraOpacityRow = document.createElement('div');
    cameraOpacityRow.style.cssText = 'display:none;align-items:center;gap:0.5rem;';
    var cameraOpacityLabel = document.createElement('label'); cameraOpacityLabel.textContent = 'Camera opacity';
    var cameraOpacityInput = document.createElement('input');
    cameraOpacityInput.type = 'range'; cameraOpacityInput.min = '0'; cameraOpacityInput.max = '100'; cameraOpacityInput.value = '35';
    cameraOpacityInput.setAttribute('aria-label', 'Camera overlay opacity'); cameraOpacityInput.style.cssText = 'width:100%;';
    cameraOpacityRow.appendChild(cameraOpacityLabel); cameraOpacityRow.appendChild(cameraOpacityInput);

JS;
        $handRowAppendScript .= "\n    panel.appendChild(cameraBgRow);\n    panel.appendChild(cameraOpacityRow);";
        $handRowWiringScript .= <<<'JS'

    cameraBgToggle.addEventListener('click', async function () {
      var turningOn = cameraBgToggle.getAttribute('aria-pressed') !== 'true';
      var hooks = window.__pieceHandHooks;
      if (!turningOn) {
        if (hooks && hooks.clearBackgroundVideo) { try { hooks.clearBackgroundVideo(); } catch (_e) {} }
        try { window.CreatrSonicController?.cameraFeed.release(); } catch (_) {}
        cameraBgToggle.setAttribute('aria-pressed', 'false');
        cameraOpacityRow.style.display = 'none';
        return;
      }
      if (!hooks || !hooks.setBackgroundVideo) return;
      try {
        var CSC = await loadSonicControllerOnce();
        var video = await CSC.cameraFeed.acquire();
        var shown = !!hooks.setBackgroundVideo(video);
        if (!shown) { CSC.cameraFeed.release(); return; }
        cameraBgToggle.setAttribute('aria-pressed', 'true');
        cameraOpacityRow.style.display = typeof hooks.setBackgroundOpacity === 'function' ? 'flex' : 'none';
        // Initialize the slider from the hook's real default (0.35 for the
        // 2D DOM overlay, 1.0 for the 3D blended quad).
        if (typeof hooks.getBackgroundOpacity === 'function') {
          cameraOpacityInput.value = String(Math.round(hooks.getBackgroundOpacity() * 100));
        }
      } catch (_e) {}
    });
    cameraOpacityInput.addEventListener('input', function () {
      var hooks = window.__pieceHandHooks;
      if (hooks && typeof hooks.setBackgroundOpacity === 'function') hooks.setBackgroundOpacity(Number(cameraOpacityInput.value) / 100);
    });

JS;
    }

    if ($capabilities['hand_control'] || $capabilities['camera_view']) {
        $handRowWiringScript .= <<<'JS'

    window.addEventListener('pagehide', function () {
      var hooks = window.__pieceHandHooks;
      if (hooks && hooks.clearBackgroundVideo) { try { hooks.clearBackgroundVideo(); } catch (_e) {} }
      if (typeof handControlActive !== 'undefined') handControlActive = false;
      if (hooks && typeof hooks.setHandSteering === 'function') { try { hooks.setHandSteering(false); } catch (_e) {} }
      if (engine) {
        try { engine.onHandFrame(null); } catch(_e) {}
        try { engine.disableHandControl(); } catch(_e) {}
      }
      // The camera toggle acquires via the static cameraFeed (not the
      // engine), so release the same way.
      if (typeof cameraBgToggle !== 'undefined' && cameraBgToggle.getAttribute('aria-pressed') === 'true') {
        try { window.CreatrSonicController?.cameraFeed.release(); } catch (_e) {}
      }
      if (typeof tiltController !== 'undefined' && tiltController) tiltController.disable();
    }, { once: true });

JS;
    }

    if ($capabilities['hand_tracking'] || $capabilities['hand_control']) {
        $handRowWiringScript .= <<<'JS'

    document.addEventListener('creatr-sonic-capability-state', function(event) {
      var detail = event.detail || {};
      if (detail.capability === 'hand_control_model' || detail.capability === 'hand_control') {
        if (detail.state === 'loading') {
          if (!handControlPreparingAt) handControlPreparingAt = Date.now();
          handControlStatus.setAttribute('aria-busy', 'true');
          handControlStatus.dataset.state = 'loading';
          setHandControlStatus('Preparing hand steering…', 0);
        } else if (detail.state === 'ready' || detail.state === 'active') {
          handControlStatus.setAttribute('aria-busy', 'false');
          handControlStatus.dataset.state = 'ready';
          var elapsed = handControlPreparingAt ? Date.now() - handControlPreparingAt : 0;
          setHandControlStatus('Hand steering ready.', Math.max(1600, 2400 - elapsed));
          handControlPreparingAt = 0;
        } else if (detail.state === 'inactive') {
          setHandControlStatus('', 0);
        } else if (detail.state === 'unavailable') {
          handControlStatus.setAttribute('aria-busy', 'false');
          handControlStatus.dataset.state = 'unavailable';
          setHandControlStatus(detail.reason || 'Hand steering is unavailable on this device.', 0);
          handControlPreparingAt = 0;
        }
      }
      var target = detail.capability === 'hand_tracking' && typeof handToggle !== 'undefined' ? handToggle
        : detail.capability === 'hand_control' && typeof handControlToggle !== 'undefined' ? handControlToggle
        : detail.capability === 'mic' && typeof micToggle !== 'undefined' ? micToggle : null;
      if (!target) return;
      target.dataset.capabilityState = detail.state || '';
      target.setAttribute('aria-busy', detail.state === 'loading' ? 'true' : 'false');
      if (detail.state === 'loading' && detail.capability !== 'hand_control') target.disabled = true;
      if (detail.state === 'active') { target.disabled = false; target.setAttribute('aria-pressed', 'true'); }
      if (detail.state === 'inactive') { target.disabled = false; target.setAttribute('aria-pressed', 'false'); }
      if (detail.state === 'unavailable') {
        target.setAttribute('aria-pressed', 'false');
        target.title = detail.reason || 'Unavailable on this device';
        if (detail.capability === 'hand_control' && detail.fallback === 'device_tilt') {
          target.disabled = false;
          target.dataset.capabilityFallback = 'device_tilt';
          target.textContent = 'Use device tilt';
        } else {
          target.disabled = true;
          target.textContent = detail.capability === 'mic' ? 'Mic unavailable' : 'Hand tracking unavailable';
        }
      }
    });

JS;
    }

    return <<<HTML
<script src="{$toneSrc}"></script>
<script src="{$sonicControllerSrc}"></script>
<script>
window.__creatrToneSrc = {$toneSrcJson};
window.__creatrMediaPipeVisionSrc = {$mediaPipeVisionSrcJson};
window.__creatrMediaPipeWasmDir = {$mediaPipeWasmDirJson};
window.__creatrMediaPipeModelSrc = {$mediaPipeModelSrcJson};
(function () {
  var capabilities = {$capabilitiesJson};
  var sonicParams = {$sonicJson};
  // Camera view and hand control are valid for sound-less pieces. Keep the
  // silent controller alive for those capabilities so downloaded exports use
  // the same camera/gesture lifecycle as the live runtime.
  if (!sonicParams && !capabilities.camera_view && !capabilities.hand_control) return;
  sonicParams = sonicParams || { enabled: false };
  var pieceId = {$pieceIdJson};

  var getMover = null;
  window.__creatrSonicSetMover = function (fn) { getMover = fn; };

  // Visitor-chosen per-voice instrument overrides — session-local only,
  // never touches sonicParams/the DB. One localStorage entry per piece.
  function voiceInstrumentStorageKey() { return 'creatr-sonic-voice-instruments:' + pieceId; }
  function readVoiceInstrumentOverrides() {
    try {
      var raw = window.localStorage.getItem(voiceInstrumentStorageKey());
      var parsed = raw ? JSON.parse(raw) : null;
      return (parsed && typeof parsed === 'object') ? parsed : {};
    } catch (_e) {
      return {};
    }
  }
  function writeVoiceInstrumentOverride(voiceName, instrumentKey) {
    try {
      var overrides = readVoiceInstrumentOverrides();
      overrides[voiceName] = instrumentKey;
      window.localStorage.setItem(voiceInstrumentStorageKey(), JSON.stringify(overrides));
    } catch (_e) {}
  }

  var sonicControllerPromise = null;
  function loadSonicControllerOnce() {
    if (window.CreatrSonicController) return Promise.resolve(window.CreatrSonicController);
    if (sonicControllerPromise) return sonicControllerPromise;
    sonicControllerPromise = new Promise(function (resolve, reject) {
      var s = document.createElement('script');
      s.onload = function () { window.CreatrSonicController ? resolve(window.CreatrSonicController) : reject(new Error('sonic-controller.js loaded but window.CreatrSonicController missing')); };
      s.onerror = function () { reject(new Error('sonic-controller.js failed to load')); };
      s.src = {$sonicControllerSrcJson};
      document.head.appendChild(s);
    });
    return sonicControllerPromise;
  }

  var engine = null;

  var ICON_OFF = '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4z"></path><line x1="22" y1="9" x2="16" y2="15"></line><line x1="16" y1="9" x2="22" y2="15"></line></svg>';
  var ICON_ON = '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4z"></path><path d="M16 9a4 4 0 0 1 0 6"></path><path d="M19 6a8 8 0 0 1 0 12"></path></svg>';

  function mountUi() {
    var wrap = document.createElement('div');
    Object.assign(wrap.style, {
      position: 'fixed', top: 'calc(0.75rem + env(safe-area-inset-top))',
      right: 'calc(0.75rem + env(safe-area-inset-right))',
      zIndex: '200', display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: '0.5rem',
    });

    var row = document.createElement('div');
    row.style.cssText = 'display:flex;align-items:center;gap:0.3rem;';

    var btn = null;
    if (capabilities.sound) {
      btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'offline-sound-btn';
      btn.setAttribute('aria-pressed', 'false');
      btn.setAttribute('aria-label', 'Unmute sound');
      Object.assign(btn.style, {
        width: '2.75rem', height: '2.75rem',
        display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
        borderRadius: '0.75rem', border: '1px solid rgba(255,255,255,0.15)',
        background: 'rgba(0,0,0,0.55)', color: '#fff', cursor: 'pointer',
        boxShadow: '0 4px 12px rgba(0,0,0,0.5)', backdropFilter: 'blur(4px)',
      });
      btn.innerHTML = ICON_OFF;
    }

    var panelTrigger = document.createElement('button');
    panelTrigger.type = 'button';
    panelTrigger.className = 'offline-sound-btn';
    panelTrigger.setAttribute('aria-haspopup', 'true');
    panelTrigger.setAttribute('aria-expanded', 'false');
    panelTrigger.setAttribute('aria-label', 'Piece controls');
    Object.assign(panelTrigger.style, {
      width: '2.75rem', height: '2.75rem',
      display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
      borderRadius: '0.75rem', border: '1px solid rgba(255,255,255,0.15)',
      background: 'rgba(0,0,0,0.55)', color: '#fff', cursor: 'pointer', padding: '0',
      boxShadow: '0 4px 12px rgba(0,0,0,0.5)', backdropFilter: 'blur(4px)',
    });
    panelTrigger.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>';

    var panel = document.createElement('div');
    panel.id = 'piece-sound-panel';
    panel.setAttribute('role', 'region');
    panel.setAttribute('aria-label', 'Piece controls');
    Object.assign(panel.style, {
      display: 'none', flexDirection: 'column', gap: '0.6rem', width: '13rem',
      maxHeight: 'calc(100dvh - 5rem)', overflowY: 'auto',
      padding: '0.85rem', borderRadius: '1rem', border: '1px solid rgba(255,255,255,0.14)',
      background: 'rgba(9,14,24,0.94)', boxShadow: '0 18px 40px rgba(0,0,0,0.4)',
      backdropFilter: 'blur(8px)', color: '#fff', font: '12px/1.4 system-ui,sans-serif',
    });

    var style = document.createElement('style');
    style.textContent = `
      .offline-sound-btn {
        transition: background-color 0.15s ease, border-color 0.15s ease;
      }
      .offline-sound-btn:hover {
        background: rgba(255, 255, 255, 0.12) !important;
      }
      .offline-sound-btn[aria-pressed="true"] {
        background: rgba(255, 255, 255, 0.22) !important;
        border-color: #fff !important;
      }
      .offline-piano-keys {
        touch-action: none;
        user-select: none;
        -webkit-user-select: none;
      }
      .offline-key-white {
        flex: 1 1 0;
        height: 100%;
        border: 1px solid rgba(0, 0, 0, 0.35);
        border-radius: 0 0 0.3rem 0.3rem;
        background: #f4f1e8;
        cursor: pointer;
        touch-action: none;
      }
      .offline-key-white:hover {
        background: #d8d4c4;
      }
      .offline-key-white:active, .offline-key-white.is-pressed {
        background: #bbb7a8;
      }
      .offline-key-black {
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
      .offline-key-black:hover {
        background: #3a3942;
      }
      .offline-key-black:active, .offline-key-black.is-pressed {
        background: #5c5a69;
      }
    `;
    document.head.appendChild(style);

    var soundRow = document.createElement('div');
    soundRow.style.cssText = 'display:flex;align-items:center;justify-content:space-between;gap:0.5rem;';
    var soundLabel = document.createElement('span'); soundLabel.textContent = 'Sound';
    var soundSwitch = document.createElement('button'); soundSwitch.type = 'button'; soundSwitch.className = 'offline-sound-btn';
    soundSwitch.textContent = 'Off'; soundSwitch.setAttribute('role', 'switch'); soundSwitch.setAttribute('aria-checked', 'false');
    soundSwitch.style.cssText = 'border:1px solid rgba(255,255,255,0.18);border-radius:0.6rem;background:rgba(255,255,255,0.06);color:#fff;font:inherit;font-weight:600;padding:0.3rem 0.55rem;cursor:pointer;';
    soundRow.appendChild(soundLabel); soundRow.appendChild(soundSwitch);

    var volumeRow = document.createElement('div');
    volumeRow.style.cssText = 'display:flex;align-items:center;gap:0.5rem;';
    var volumeLabel = document.createElement('label');
    volumeLabel.textContent = 'Volume';
    var volumeInput = document.createElement('input');
    volumeInput.type = 'range'; volumeInput.min = '0'; volumeInput.max = '100'; volumeInput.value = '50';
    volumeInput.style.cssText = 'width:100%;';
    volumeRow.appendChild(volumeLabel); volumeRow.appendChild(volumeInput);

    var keyboardRow = document.createElement('div');
    keyboardRow.style.cssText = 'display:flex;align-items:center;justify-content:space-between;gap:0.5rem;';
    var keyboardLabel = document.createElement('span');
    keyboardLabel.textContent = 'Keyboard';
    var keyboardToggle = document.createElement('button');
    keyboardToggle.type = 'button';
    keyboardToggle.className = 'offline-sound-btn';
    keyboardToggle.textContent = 'Play notes';
    keyboardToggle.setAttribute('aria-pressed', 'false');
    keyboardToggle.style.cssText = 'border:1px solid rgba(255,255,255,0.18);border-radius:0.6rem;background:rgba(255,255,255,0.06);color:#fff;font:inherit;font-weight:600;padding:0.3rem 0.55rem;cursor:pointer;';
    keyboardRow.appendChild(keyboardLabel); keyboardRow.appendChild(keyboardToggle);
    var voicesConfig = (sonicParams.extras && sonicParams.extras.voices) || {};
    if (voicesConfig.melodic === false) keyboardRow.style.display = 'none';

    // Visitor-facing per-voice instrument picker — session-local only (see
    // readVoiceInstrumentOverrides()/writeVoiceInstrumentOverride() below,
    // never touches sonicParams/the DB), mirroring the live-view popover's
    // picker (immersive_stage_voice_instrument_picker_markup() in
    // immersive-chrome.php).
    var instrumentOptions = {$instrumentOptionsJson};
    var ambientSampleConfig = (sonicParams.extras && sonicParams.extras.synth && sonicParams.extras.synth.ambient_sample) || {};
    var ambientIsSample = !!(ambientSampleConfig.enabled && ambientSampleConfig.media_id);
    var voicePickerWrap = document.createElement('div');
    voicePickerWrap.style.cssText = 'display:flex;flex-direction:column;gap:0.45rem;';
    var voicePickerSelects = {};
    [['ambient', 'Ambient'], ['movement', 'Movement'], ['melodic', 'Melodic']].forEach(function (pair) {
      var voiceName = pair[0], label = pair[1];
      var pickerRow = document.createElement('div');
      pickerRow.style.cssText = 'display:flex;align-items:center;justify-content:space-between;gap:0.5rem;';
      // A sample-backed ambient voice has no meaningful synth-instrument
      // choice — hide the row outright rather than letting the visitor pick
      // something setVoiceInstrument() will just refuse.
      if (voicesConfig[voiceName] === false || (voiceName === 'ambient' && ambientIsSample)) pickerRow.style.display = 'none';
      var pickerLabel = document.createElement('span');
      pickerLabel.textContent = label;
      var select = document.createElement('select');
      select.style.cssText = 'border:1px solid rgba(255,255,255,0.18);border-radius:0.6rem;background:rgba(255,255,255,0.06);color:#fff;font:inherit;padding:0.25rem 0.4rem;';
      instrumentOptions.forEach(function (pair2) {
        var option = document.createElement('option');
        option.value = pair2[0];
        option.textContent = pair2[1];
        select.appendChild(option);
      });
      select.value = readVoiceInstrumentOverrides()[voiceName] || sonicParams.instrument || 'synth';
      select.addEventListener('change', function () {
        ensureEnabled().then(function (ok) {
          if (ok && engine && engine.setVoiceInstrument(voiceName, select.value)) {
            writeVoiceInstrumentOverride(voiceName, select.value);
          }
        });
      });
      voicePickerSelects[voiceName] = select;
      pickerRow.appendChild(pickerLabel);
      pickerRow.appendChild(select);
      voicePickerWrap.appendChild(pickerRow);
    });

    // Live human-voice input (mic) — a fourth layer mixed on top of the
    // piece's own voices, purely visitor-facing (session-local, off by
    // default, never persisted). Hidden outright when unsupported rather
    // than left clickable and failing silently on click.
    var micSupported = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    var micRow = document.createElement('div');
    micRow.style.cssText = 'display:' + (micSupported ? 'flex' : 'none') + ';align-items:center;justify-content:space-between;gap:0.5rem;';
    var micToggle = document.createElement('button');
    micToggle.type = 'button';
    micToggle.className = 'offline-sound-btn';
    micToggle.textContent = 'Live mic';
    micToggle.setAttribute('aria-pressed', 'false');
    micToggle.style.cssText = 'border:1px solid rgba(255,255,255,0.18);border-radius:0.6rem;background:rgba(255,255,255,0.06);color:#fff;font:inherit;font-weight:600;padding:0.3rem 0.55rem;cursor:pointer;';
    micRow.appendChild(Object.assign(document.createElement('span'), { textContent: 'Microphone' }));
    micRow.appendChild(micToggle);

    var micFxWrap = document.createElement('div');
    micFxWrap.style.cssText = 'display:none;grid-template-columns:1fr 1fr;gap:0.3rem 0.6rem;';
    var micFxCheckboxes = {};
    [['distortion', 'Distortion'], ['chorus', 'Chorus'], ['tremolo', 'Tremolo'], ['pitch_shift', 'Pitch shift'], ['bitcrusher', 'Bitcrusher'], ['flanger', 'Flanger'], ['ring_mod', 'Ring mod']].forEach(function (pair) {
      var key = pair[0], label = pair[1];
      var fxLabel = document.createElement('label');
      fxLabel.style.cssText = 'display:flex;align-items:center;gap:0.35rem;font-size:0.75rem;';
      var checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.addEventListener('change', function () {
        if (engine) engine.setMicEffect(key, checkbox.checked);
      });
      micFxCheckboxes[key] = checkbox;
      fxLabel.appendChild(checkbox);
      fxLabel.appendChild(document.createTextNode(label));
      micFxWrap.appendChild(fxLabel);
    });
    micToggle.addEventListener('click', async function () {
      if (engine && engine.isMicEnabled()) {
        engine.disableMic();
        micToggle.setAttribute('aria-pressed', 'false');
        micFxWrap.style.display = 'none';
        return;
      }
      var ok = await ensureEnabled();
      var micOk = ok && engine ? await engine.enableMic() : false;
      micToggle.setAttribute('aria-pressed', micOk ? 'true' : 'false');
      micFxWrap.style.display = micOk ? 'grid' : 'none';
    });

    var octaveRow = document.createElement('div');
    octaveRow.style.cssText = 'display:none;align-items:center;justify-content:center;gap:0.5rem;';
    var octaveDown = document.createElement('button'); octaveDown.type = 'button'; octaveDown.textContent = '−';
    var octaveUp = document.createElement('button'); octaveUp.type = 'button'; octaveUp.textContent = '+';
    var octaveDisplay = document.createElement('output'); octaveDisplay.textContent = '3';
    [octaveDown, octaveUp].forEach(function (b) { b.style.cssText = 'height:1.6rem;width:1.6rem;border:1px solid rgba(255,255,255,0.18);border-radius:0.4rem;background:rgba(255,255,255,0.08);color:#fff;font:inherit;font-weight:700;cursor:pointer;'; });
    octaveRow.appendChild(octaveDown); octaveRow.appendChild(octaveDisplay); octaveRow.appendChild(octaveUp);

    var keysWrap = document.createElement('div');
    keysWrap.className = 'offline-piano-keys';
    keysWrap.style.cssText = 'display:none;position:relative;height:4rem;';
    var whiteRow = document.createElement('div');
    whiteRow.style.cssText = 'display:flex;height:100%;';
    // 10 white keys (one octave plus a major third into the next), matching
    // PIANO_KEY_MAP's physical-keyboard span (sonic-controller.js) exactly.
    var whiteSemitones = [0, 2, 4, 5, 7, 9, 11, 12, 14, 16];
    var blackAfter = {0: 1, 1: 3, 3: 6, 4: 8, 5: 10, 7: 13, 8: 15};
    whiteSemitones.forEach(function (semitone, i) {
      var keyBtn = document.createElement('button');
      keyBtn.type = 'button';
      keyBtn.dataset.semitone = String(semitone);
      keyBtn.className = 'offline-key-white';
      whiteRow.appendChild(keyBtn);
      if (blackAfter[i] !== undefined) {
        var blackBtn = document.createElement('button');
        blackBtn.type = 'button';
        blackBtn.dataset.semitone = String(blackAfter[i]);
        blackBtn.className = 'offline-key-black';
        blackBtn.style.left = ((i + 1) * (100 / 10)) + '%';
        keysWrap.appendChild(blackBtn);
      }
    });
    keysWrap.insertBefore(whiteRow, keysWrap.firstChild);
    keysWrap.addEventListener('pointerdown', function (event) {
      var keyBtn = event.target.closest && event.target.closest('button[data-semitone]');
      if (!keyBtn) return;
      playChromaticNote(Number(keyBtn.dataset.semitone || 0));
    });

{$handRowElementsScript}

    if (capabilities.sound) {
      panel.appendChild(soundRow);
      panel.appendChild(volumeRow);
      panel.appendChild(voicePickerWrap);
      panel.appendChild(keyboardRow);
      panel.appendChild(octaveRow);
      panel.appendChild(keysWrap);
    }
{$handRowAppendScript}
    if (capabilities.sound) {
      panel.appendChild(micRow);
      panel.appendChild(micFxWrap);
    }

    if (btn) {
      row.appendChild(btn);
    }
    row.appendChild(panelTrigger);
    var handGuideTrigger = document.querySelector('[data-hand-guide-trigger]');
    if (handGuideTrigger) {
      Object.assign(handGuideTrigger.style, {
        width: '2.75rem', height: '2.75rem', display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
        borderRadius: '0.75rem', border: '1px solid rgba(255,255,255,0.15)', background: 'rgba(0,0,0,0.55)',
        color: '#fff', cursor: 'pointer', padding: '0', boxShadow: '0 4px 12px rgba(0,0,0,0.5)', backdropFilter: 'blur(4px)'
      });
      row.appendChild(handGuideTrigger);
    }
    wrap.appendChild(row);
    wrap.appendChild(panel);
    document.body.appendChild(wrap);

    async function ensureEnabled() {
      if (!capabilities.sound) return false;
      if (engine && engine.isEnabled()) return true;
      if (btn) btn.disabled = true;
      try {
        if (!engine) {
          var CSC = await loadSonicControllerOnce();
          engine = CSC.create(sonicParams, {
            getMover: function () { return getMover ? getMover() : null; },
            // The engine can be created by Sound or Live mic before the
            // visitor asks to steer. Preserve the server-granted steering
            // capability on that first creation path; otherwise
            // enableHandControl() correctly rejects this already-created
            // engine even though the same piece works when steering starts
            // first.
            allowHandControl: !!capabilities.hand_control,
            toneSrc: window.__creatrToneSrc,
            mediaPipeVisionSrc: window.__creatrMediaPipeVisionSrc,
            mediaPipeWasmDir: window.__creatrMediaPipeWasmDir,
            mediaPipeModelSrc: window.__creatrMediaPipeModelSrc,
          });
          if (!engine) return false;
        }
        var ok = await engine.enable();
        if (ok) {
          if (btn) {
            btn.setAttribute('aria-pressed', 'true');
            btn.setAttribute('aria-label', 'Mute sound');
            btn.innerHTML = ICON_ON;
          }
          if (soundSwitch) {
            soundSwitch.textContent = 'On'; soundSwitch.setAttribute('aria-checked', 'true');
          }
          if (volumeInput) volumeInput.value = String(engine.getVolume());
          var storedOverrides = readVoiceInstrumentOverrides();
          Object.keys(storedOverrides).forEach(function (voiceName) {
            engine.setVoiceInstrument(voiceName, storedOverrides[voiceName]);
            if (voicePickerSelects[voiceName]) voicePickerSelects[voiceName].value = storedOverrides[voiceName];
          });
        } else {
          if (btn) btn.setAttribute('aria-label', 'Sound unavailable');
        }
        return ok;
      } catch (_e) {
        if (btn) btn.setAttribute('aria-label', 'Sound unavailable');
        return false;
      } finally {
        if (btn) btn.disabled = false;
      }
    }

    async function playChromaticNote(semitoneIndex) {
      var ok = await ensureEnabled();
      if (ok && engine) engine.triggerChromaticNote(semitoneIndex);
    }

    var detachPianoKeys = null;

    if (btn) {
      btn.addEventListener('click', async function () {
        if (engine && engine.isEnabled()) {
          engine.disable();
          btn.setAttribute('aria-pressed', 'false');
          btn.setAttribute('aria-label', 'Unmute sound');
          btn.innerHTML = ICON_OFF;
          if (soundSwitch) {
            soundSwitch.textContent = 'Off'; soundSwitch.setAttribute('aria-checked', 'false');
          }
          return;
        }
        await ensureEnabled();
      });
    }
    if (soundSwitch && btn) {
      soundSwitch.addEventListener('click', function () { btn.click(); });
    }

    var panelOpen = false;
    panelTrigger.addEventListener('click', function () {
      panelOpen = !panelOpen;
      panel.style.display = panelOpen ? 'flex' : 'none';
      panelTrigger.setAttribute('aria-expanded', panelOpen ? 'true' : 'false');
      if (panelOpen && typeof prepareHandControl === 'function') prepareHandControl();
    });

    volumeInput.addEventListener('input', function () {
      if (engine) engine.setVolume(Number(volumeInput.value));
    });

    keyboardToggle.addEventListener('click', async function () {
      var next = engine && engine.getInputMode() === 'keyboard' ? 'motion' : 'keyboard';
      var keyboardOn = next === 'keyboard';
      keyboardToggle.setAttribute('aria-pressed', keyboardOn ? 'true' : 'false');
      octaveRow.style.display = keyboardOn ? 'flex' : 'none';
      keysWrap.style.display = keyboardOn ? 'block' : 'none';
      if (keyboardOn) {
        await ensureEnabled();
        if (detachPianoKeys) detachPianoKeys();
        detachPianoKeys = window.CreatrSonicController ? window.CreatrSonicController.attachPianoKeyListener(engine, function (semitone, pressed) {
          var k = keysWrap.querySelector('[data-semitone="' + semitone + '"]');
          if (k) k.classList.toggle('is-pressed', pressed);
        }) : null;
      } else {
        if (detachPianoKeys) detachPianoKeys();
        detachPianoKeys = null;
        keysWrap.querySelectorAll('[data-semitone]').forEach(function (k) { k.classList.remove('is-pressed'); });
      }
      if (engine) engine.setInputMode(next);
    });

{$handRowWiringScript}

    octaveDown.addEventListener('click', function () {
      if (!engine) return;
      engine.setOctave(engine.getOctave() - 1);
      octaveDisplay.textContent = String(engine.getOctave());
    });
    octaveUp.addEventListener('click', function () {
      if (!engine) return;
      engine.setOctave(engine.getOctave() + 1);
      octaveDisplay.textContent = String(engine.getOctave());
    });

    document.addEventListener('pointerdown', function (event) {
      if (!panelOpen) return;
      if (wrap.contains(event.target)) return;
      panelOpen = false;
      panel.style.display = 'none';
    }, { capture: true });
  }

  function showOfflineTroubleshooting(mode, errorDetail) {
    if (document.getElementById('creatr-offline-warning')) return;

    var banner = document.createElement('div');
    banner.id = 'creatr-offline-warning';
    Object.assign(banner.style, {
      position: 'fixed',
      bottom: '1rem',
      left: '50%',
      transform: 'translateX(-50%)',
      width: 'calc(100% - 2rem)',
      maxWidth: '32rem',
      backgroundColor: 'rgba(15, 23, 42, 0.96)',
      border: '1px solid rgba(248, 113, 113, 0.3)',
      borderRadius: '1rem',
      padding: '1.25rem',
      boxShadow: '0 20px 25px -5px rgba(0,0,0,0.5), 0 10px 10px -5px rgba(0,0,0,0.5)',
      color: '#f8fafc',
      fontFamily: 'system-ui, -apple-system, sans-serif',
      fontSize: '13px',
      lineHeight: '1.5',
      zIndex: '99999',
      backdropFilter: 'blur(12px)',
      display: 'flex',
      flexDirection: 'column',
      gap: '0.75rem',
      animation: 'creatrSlideUp 0.3s ease-out'
    });

    if (!document.getElementById('creatr-banner-styles')) {
      var styleEl = document.createElement('style');
      styleEl.id = 'creatr-banner-styles';
      styleEl.textContent = `
        @keyframes creatrSlideUp {
          from { transform: translate(-50%, 2rem); opacity: 0; }
          to { transform: translate(-50%, 0); opacity: 1; }
        }
        .creatr-code-box {
          background: rgba(0,0,0,0.4);
          border: 1px solid rgba(255,255,255,0.1);
          border-radius: 0.5rem;
          padding: 0.5rem;
          font-family: ui-monospace, monospace;
          font-size: 11px;
          color: #38bdf8;
          word-break: break-all;
          display: flex;
          justify-content: space-between;
          align-items: center;
        }
        .creatr-copy-btn {
          background: rgba(255,255,255,0.12);
          border: none;
          border-radius: 0.25rem;
          color: #fff;
          padding: 0.25rem 0.5rem;
          cursor: pointer;
          font-family: inherit;
          font-size: 10px;
          font-weight: 600;
        }
        .creatr-copy-btn:hover { background: rgba(255,255,255,0.22); }
      `;
      document.head.appendChild(styleEl);
    }

    var header = document.createElement('div');
    header.style.cssText = 'display:flex;align-items:center;justify-content:space-between;font-weight:600;color:#f87171;font-size:14px;';
    header.innerHTML = '<span>⚠️ Browser Security Restriction</span>';

    var closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.innerHTML = '✕';
    closeBtn.style.cssText = 'background:none;border:none;color:#94a3b8;cursor:pointer;font-size:16px;padding:0;line-height:1;';
    closeBtn.addEventListener('click', function() { banner.remove(); });
    header.appendChild(closeBtn);

    var body = document.createElement('div');
    var isFile = window.location.protocol === 'file:';
    var featureName = mode === 'mic' ? 'microphone (human voice)' : 'camera (theremin)';
    var text = '';
    if (isFile) {
      text = 'This page was opened directly via the <code>file://</code> protocol. Browsers restrict ' + featureName + ' access and local WebAssembly worker loading in this offline sandbox mode.';
    } else {
      text = 'Access to the ' + featureName + ' was blocked or is not supported in this browser context.';
    }
    if (errorDetail) {
      text += ' <span style="color:#94a3b8;font-size:11px;">(' + errorDetail + ')</span>';
    }
    body.innerHTML = text + '<br><br><strong>Solution:</strong> Serve this folder using a local web server to run it in a secure context:';

    var cmdTitle1 = document.createElement('div');
    cmdTitle1.style.cssText = 'font-weight:600;margin-top:0.25rem;color:#cbd5e1;';
    cmdTitle1.textContent = 'Using Node.js (recommended):';
    
    var cmdBox1 = document.createElement('div');
    cmdBox1.className = 'creatr-code-box';
    cmdBox1.innerHTML = '<span>npx http-server .</span>';
    var copyBtn1 = document.createElement('button');
    copyBtn1.className = 'creatr-copy-btn';
    copyBtn1.textContent = 'Copy';
    copyBtn1.addEventListener('click', function() {
      navigator.clipboard.writeText('npx http-server .');
      copyBtn1.textContent = 'Copied!';
      setTimeout(function() { copyBtn1.textContent = 'Copy'; }, 2000);
    });
    cmdBox1.appendChild(copyBtn1);

    var cmdTitle2 = document.createElement('div');
    cmdTitle2.style.cssText = 'font-weight:600;color:#cbd5e1;';
    cmdTitle2.textContent = 'Using Python:';

    var cmdBox2 = document.createElement('div');
    cmdBox2.className = 'creatr-code-box';
    cmdBox2.innerHTML = '<span>python -m http.server 8000</span>';
    var copyBtn2 = document.createElement('button');
    copyBtn2.className = 'creatr-copy-btn';
    copyBtn2.textContent = 'Copy';
    copyBtn2.addEventListener('click', function() {
      navigator.clipboard.writeText('python -m http.server 8000');
      copyBtn2.textContent = 'Copied!';
      setTimeout(function() { copyBtn2.textContent = 'Copy'; }, 2000);
    });
    cmdBox2.appendChild(copyBtn2);

    var instructions = document.createElement('div');
    instructions.style.cssText = 'font-size:11px;color:#94a3b8;margin-top:0.25rem;';
    instructions.innerHTML = 'After running either command, open the local address (e.g. <code>http://localhost:8080</code> or <code>http://localhost:8000</code>) in your browser.';

    banner.appendChild(header);
    banner.appendChild(body);
    banner.appendChild(cmdTitle1);
    banner.appendChild(cmdBox1);
    banner.appendChild(cmdTitle2);
    banner.appendChild(cmdBox2);
    banner.appendChild(instructions);

    document.body.appendChild(banner);
  }

  document.addEventListener('creatr-hand-tracking-failed', function(e) {
    showOfflineTroubleshooting('hand_tracking', e.detail.error || e.detail.cdnError);
  });
  document.addEventListener('creatr-mic-failed', function(e) {
    showOfflineTroubleshooting('mic', e.detail.error);
  });

  if (document.body) mountUi();
  else document.addEventListener('DOMContentLoaded', mountUi, { once: true });
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
                ? piece_export_asset_replacement($asset, (string) ($asset['path'] ?? ''))
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
                ? piece_export_asset_replacement($asset, $src)
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
    $sonicDecoded = !empty($version['sonic_params']) ? json_decode((string) $version['sonic_params'], true) : null;
    $hasSonic = $sonicDecoded && ($sonicDecoded['enabled'] ?? true) !== false;
    $jsonSonic = json_encode($hasSonic ? $sonicDecoded : null, $jsonFlags);
    $jsonPieceId = json_encode((int) ($piece['id'] ?? 0), $jsonFlags);
    // Camera/hand capabilities from the same contract as the live immersive
    // view, so the exported toolbar and chrome offer the same rows offline.
    $exportCapabilities = piece_sound_capability_contract(
        $generationMode,
        is_array($sonicDecoded) ? $sonicDecoded : [],
        piece_camera_overlay_enabled($version, 'immersive'),
        piece_camera_placement($version, 'immersive'),
        array_key_exists('hand_motion', $options) ? (bool) $options['hand_motion'] : true
    );
    $exportCameraView = !empty($exportCapabilities['camera_view']);
    $exportHandControl = !empty($exportCapabilities['hand_control']);
    $jsonCameraView = json_encode($exportCameraView, $jsonFlags);
    $jsonHandControl = json_encode($exportHandControl, $jsonFlags);

    // Shared top toolbar — identical placement/appearance to the live
    // immersive surfaces. Three/A-Frame pieces have no gallery full view, so
    // they render no view button; the download menu is PNG-only because a
    // standalone export cannot re-download itself offline. Camera/hand rows
    // follow $exportCapabilities (computed above with the JSON flags).
    $isInteractiveC2 = $generationMode === 'c2_interactive';
    $toolbarCss = immersive_stage_toolbar_css();
    $toolbarMarkup = immersive_stage_toolbar_markup([
        'view_action' => !in_array($engine, ['three', 'aframe'], true) ? [
            'label' => $isInteractiveC2 ? 'Open interactive view' : 'View piece full size',
            'icon' => $isInteractiveC2 ? 'interactive' : 'view',
        ] : null,
        'download_items' => null,
        'screenshot_action' => [
            'attrs' => [
                'data-immersive-download-png' => true,
                'data-download-filename' => piece_export_png_filename($piece),
            ],
        ],
        'sound_action' => $hasSonic ? ['enabled' => true] : null,
        'camera_view' => $exportCameraView,
        'hand_control' => $exportHandControl,
        'hand_guide_variant' => '',
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
<link rel="icon" href="data:,">
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
function isNonImpactingRuntimeIssue(error,source){const m=typeof error?.message==='string'?error.message:String(error||'');const s=String(source||error?.fileName||'');return /^(?:chrome|moz|safari)-extension:/i.test(s)||/ResizeObserver loop (?:limit exceeded|completed with undelivered notifications)/i.test(m)||/Could not establish connection\. Receiving end does not exist/i.test(m)||/A listener indicated an asynchronous response.*message channel closed/i.test(m);}
window.addEventListener('error',event=>{const e=event.error||event.message;if(!isNonImpactingRuntimeIssue(e,event.filename))showPieceError(e);});
window.addEventListener('unhandledrejection',event=>{const r=event.reason;const m=typeof r?.message==='string'?r.message:String(r||'');if((r?.name==='AbortError'&&/worklet/i.test(m))||isNonImpactingRuntimeIssue(r)){event.preventDefault();return;}showPieceError(r||'Unhandled promise rejection');});
</script>
<script>
{$downloadBridgeScript}
</script>
<script src="runtime/three/three.global.js"></script>
<script src="runtime/three/GLTFLoader.global.js"></script>
<script src="runtime/three/OrbitControls.global.js"></script>
<script src="runtime/three-device-orientation-controls.global.js"></script>
<script src="runtime/sonic-controller.js"></script>
<script src="runtime/immersive-gallery.global.js"></script>
<script>
const { mountAFrameImmersivePiece, mountGalleryPiece, mountThreeImmersivePiece, setupImmersiveStageChrome } = window.CreatrImmersiveGallery || {};
// Bundle-local paths so exported/offline pieces load sonification assets
// from the ZIP instead of the live site (mirrors runtime/tone/Tone.js above).
// The mediapipe-hands runtime/ files only exist in the ZIP when this piece's
// hand-tracking voice was enabled (piece_export_version_has_hand_tracking());
// harmless to set the overrides unconditionally since sonic-controller.js
// only loads them if the user actually activates hand-tracking mode.
window.__creatrSonicControllerSrc = 'runtime/sonic-controller.js';
window.__creatrToneSrc = 'runtime/tone/Tone.js';
window.__creatrMediaPipeVisionSrc = 'runtime/mediapipe-hands/vision_bundle.mjs';
window.__creatrMediaPipeWasmDir = 'runtime/mediapipe-hands/';
window.__creatrMediaPipeModelSrc = 'runtime/mediapipe-hands/hand_landmarker.task';
window.__creatrAFrameModelRuntimeSrc = 'runtime/aframe-model-runtime.js';

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
  sonicParams: {$jsonSonic},
  pieceId: {$jsonPieceId},
  cameraOverlay: {$jsonCameraView},
  handControl: {$jsonHandControl}
};
const stage = document.getElementById('immersive-stage');
const fullscreenBtn = document.getElementById('fullscreen-toggle-btn');
const pngBtn = document.querySelector('[data-immersive-download-png]');
let viewer = null;

try {
  const controls = { showViewerControls: true, initialViewState: piece.initialViewState, sonicParams: piece.sonicParams, pieceId: piece.pieceId, handControl: piece.handControl };
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
    },
    getAudioController: () => viewer?.getAudioController?.(),
    getPieceInteractionController: () => viewer?.getPieceInteractionController?.(),
    cameraOverlayAllowed: piece.cameraOverlay,
    handControlAllowed: piece.handControl,
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
  const dot = filename.lastIndexOf('.');
  const stem = dot > 0 ? filename.slice(0, dot) : filename;
  const extension = dot > 0 ? filename.slice(dot) : '.png';
  const now = new Date();
  const stamp = now.getFullYear()
    + String(now.getMonth() + 1).padStart(2, '0')
    + String(now.getDate()).padStart(2, '0') + '-'
    + String(now.getHours()).padStart(2, '0')
    + String(now.getMinutes()).padStart(2, '0')
    + String(now.getSeconds()).padStart(2, '0') + '-'
    + String(now.getMilliseconds()).padStart(3, '0');
  link.download = stem + '-' + stamp + extension;
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
  const labelEl = pngBtn.querySelector('span');
  const label = labelEl ? labelEl.textContent : '';
  const originalAriaLabel = pngBtn.getAttribute('aria-label') || 'Take screenshot';
  pngBtn.disabled = true;
  pngBtn.setAttribute('aria-busy', 'true');
  pngBtn.setAttribute('aria-label', 'Preparing PNG...');
  if (labelEl) {
    labelEl.textContent = 'Preparing PNG...';
  }
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
    pngBtn.removeAttribute('aria-busy');
    pngBtn.setAttribute('aria-label', originalAriaLabel);
    if (labelEl) {
      labelEl.textContent = label;
    }
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

    // Generated markup frequently leaves <a-asset-item> unclosed; libxml then
    // treats the unknown element as a container and swallows every following
    // sibling (other assets) into it. a-asset-item never has children, so
    // normalize each one to an explicitly closed empty tag before parsing.
    $html = (string) preg_replace('#</a-asset-item\s*>#i', '', $html);
    $html = (string) preg_replace('#<a-asset-item\b([^>]*?)/?>#i', '<a-asset-item$1></a-asset-item>', $html);

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

    // GLTF/GLB model assets must retain their actual URL. They are consumed
    // by A-Frame's gltf-model component, not as image textures; rewriting a
    // model src to an image id makes A-Frame fetch the HTML document as GLTF
    // and fails with "Unexpected token '<'".
    foreach ($xpath->query('.//a-asset-item[@src]', $assets) as $modelNode) {
        if (!$modelNode instanceof DOMElement) {
            continue;
        }
        $src = trim($modelNode->getAttribute('src'));
        if ($src !== '' && str_starts_with($src, '/')) {
            $modelNode->setAttribute('src', $resolver($src));
        }
        // A-Frame's asset preloader otherwise guesses JSON for extensionless
        // CMS URLs such as /media/196. The response is a valid GLB, but the
        // guess produces "Unexpected token 'g'" before GLTFLoader sees it.
        $modelNode->setAttribute(
            'type',
            preg_match('/\.gltf(?:\?|$)/i', $src) === 1
                ? 'model/gltf+json'
                : 'model/gltf-binary'
        );
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

        $tagName = strtolower($node->tagName);
        if ($tagName === 'img' || $tagName === 'a-asset-item') {
            $src = trim($node->getAttribute('src'));
            if ($tagName === 'img' && $src !== '' && str_starts_with($src, '/')) {
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

    // Generated markup sometimes authors a camera with look-controls disabled
    // (or missing entirely). Declaring any camera suppresses A-Frame's default
    // camera — the only other source of look-controls — leaving the piece with
    // no drag/mouse look at all. Hand steering bypasses look-controls, which
    // is why it still works on such pieces; force look-controls on so every
    // authored camera keeps pointer/touch movement.
    foreach ($xpath->query('.//a-camera | .//a-entity[@camera]', $scene) as $cameraNode) {
        if (!$cameraNode instanceof DOMElement) {
            continue;
        }

        $lookControls = trim((string) $cameraNode->getAttribute('look-controls'));
        if ($lookControls === '') {
            $cameraNode->setAttribute('look-controls', 'magicWindowTrackingEnabled: false');
            continue;
        }

        $updated = preg_replace('/\benabled\s*:\s*false\b/i', 'enabled: true', $lookControls);
        if (is_string($updated) && $updated !== $lookControls) {
            $cameraNode->setAttribute('look-controls', $updated);
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
        'aframe' => '<script src="https://aframe.io/releases/1.6.0/aframe.min.js"></script>'
            . '<script>'
            . 'if (window.AFRAME && window.AFRAME.components["wasd-controls"]) {'
            . '  const proto = window.AFRAME.components["wasd-controls"].Component.prototype;'
            . '  const origKeyDown = proto.onKeyDown;'
            . '  const origKeyUp = proto.onKeyUp;'
            . '  proto.onKeyDown = function(e) {'
            . '    if (e.code === "KeyW" || e.code === "KeyA" || e.code === "KeyS" || e.code === "KeyD") return;'
            . '    origKeyDown.call(this, e);'
            . '  };'
            . '  proto.onKeyUp = function(e) {'
            . '    if (e.code === "KeyW" || e.code === "KeyA" || e.code === "KeyS" || e.code === "KeyD") return;'
            . '    origKeyUp.call(this, e);'
            . '  };'
            . '}'
            . '</script>',
        default => '',
    };
}

function piece_export_inline_runtime_markup(string $engine): string
{
    $engine = strtolower($engine);
    if ($engine === 'svg') {
        return '';
    }

    if ($engine === 'three') {
        return '<script src="runtime/three/three.global.js"></script>' . "\n"
            . '<script src="runtime/three/GLTFLoader.global.js"></script>' . "\n"
            . '<script src="runtime/three/OrbitControls.global.js"></script>';
    }

    $source = piece_export_runtime_inline_source($engine);
    if ($source === '') {
        return '';
    }

    $runtimeTag = '';
    if ($engine === 'aframe') {
        $runtimeTag = '<script src="runtime/aframe-model-runtime.js"></script>\n';
        $source .= "\nif (window.AFRAME && window.AFRAME.components['wasd-controls']) {\n"
            . "  const proto = window.AFRAME.components['wasd-controls'].Component.prototype;\n"
            . "  const origKeyDown = proto.onKeyDown;\n"
            . "  const origKeyUp = proto.onKeyUp;\n"
            . "  proto.onKeyDown = function(e) {\n"
            . "    if (e.code === 'KeyW' || e.code === 'KeyA' || e.code === 'KeyS' || e.code === 'KeyD') return;\n"
            . "    origKeyDown.call(this, e);\n"
            . "  };\n"
            . "  proto.onKeyUp = function(e) {\n"
            . "    if (e.code === 'KeyW' || e.code === 'KeyA' || e.code === 'KeyS' || e.code === 'KeyD') return;\n"
            . "    origKeyUp.call(this, e);\n"
            . "  };\n"
            . "}";
    }

    return $runtimeTag . "<script>\n" . piece_escape_inline_script($source) . "\n</script>";
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

    $source = piece_export_strip_source_maps($source);

    return strtolower($engine) === 'aframe'
        ? piece_export_prepare_aframe_runtime($source)
        : $source;
}

function piece_export_runtime_source_file(string $relativePath): string
{
    $publicRoot = dirname(__DIR__, 2);
    $path = $publicRoot . '/' . ltrim($relativePath, '/');
    $source = @file_get_contents($path);
    if ($source === false) {
        throw new RuntimeException('Missing vendored runtime source for piece export: ' . $path);
    }

    $source = piece_export_strip_source_maps($source);

    return ltrim($relativePath, '/') === 'assets/js/aframe.min.js'
        ? piece_export_prepare_aframe_runtime($source)
        : $source;
}

/**
 * Strips `sourceMappingURL` comments from bundled runtime sources. When a
 * downloaded piece is opened from file://, Safari/WebKit refuses to load
 * missing .map files and logs confusing security/origin warnings; removing
 * the reference eliminates that class of errors entirely.
 */
function piece_export_strip_source_maps(string $source): string
{
    // Directives are comments occupying their own physical line. Do not
    // search for comment-shaped substrings anywhere in the source: minified
    // A-Frame contains `/*# sourceMappingURL=... */` inside an executable JS
    // string, and the previous unanchored block-comment branch deleted that
    // string fragment and made every exported A-Frame runtime invalid.
    return preg_replace(
        '/^[\t ]*(?:\/\/[#@][\t ]*sourceMappingURL=[^\r\n]*|\/\*[#@][\t ]*sourceMappingURL=.*?\*\/)[\t ]*(?:\r?\n|$)/m',
        '',
        $source
    ) ?? $source;
}

/**
 * Applies the direct-file compatibility fix required by A-Frame 1.6.0.
 *
 * Its bundled audio release path assigns `audio.src = ""`. Under file://,
 * WebKit resolves that empty URL to index.html and rejects the self-load as a
 * unique-origin request. Keep this count-asserted so an A-Frame upgrade stops
 * export with a useful diagnostic instead of silently shipping an unreviewed
 * or partially patched runtime.
 */
function piece_export_prepare_aframe_runtime(string $source): string
{
    $needle = 'this.release=function(){e.pause(),e.src=""}';
    $replacement = 'this.release=function(){e.pause(),e.removeAttribute("src"),e.load()}';
    $matches = substr_count($source, $needle);
    if ($matches !== 1) {
        throw new RuntimeException(
            'A-Frame export compatibility check failed: expected exactly one empty audio source cleanup, found '
            . $matches
            . '. Review the vendored A-Frame runtime before exporting it.'
        );
    }

    return str_replace($needle, $replacement, $source);
}

/**
 * Strips mid-file `export function`/`export const`/`export class`
 * declarations down to their bare form. This only handles exports that
 * appear *inside* a file (not the leading `import` or trailing
 * `export { ... };` block, which each *_global_source() function rewrites
 * itself into a different target shape). Missing one of these left a
 * literal `export` keyword in a generated classic script, which throws a
 * SyntaxError that aborts the whole file at parse time in the browser
 * (the original GLTFLoader.global.js bug) — this is the shared, generic
 * fix so any future upstream Three.js file shape change is covered too.
 */
function piece_export_strip_module_syntax(string $source): string
{
    $source = preg_replace('/^export\s+function\s+/m', 'function ', $source) ?? $source;
    $source = preg_replace('/^export\s+const\s+/m', 'const ', $source) ?? $source;
    $source = preg_replace('/^export\s+class\s+/m', 'class ', $source) ?? $source;

    return $source;
}

/**
 * Fails loudly (server-side, at export-generation time) if any literal
 * export/import keyword survived conversion to a classic script — rather
 * than silently shipping a broken download that throws in the user's
 * browser. Anchored so it doesn't false-positive on things like `.export`
 * property access or an identifier named `exportSomething`.
 */
function piece_export_assert_no_module_syntax(string $source, string $context): void
{
    if (preg_match('/(^|[^.\w$])(export|import)\s*[{*]|(^|[^.\w$])(export|import)\s+(function|const|class|default|let|var)\b/m', $source)) {
        throw new RuntimeException("Generated global script for {$context} still contains ES module syntax (export/import) after conversion — upstream vendor file shape may have changed.");
    }
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

function piece_export_three_global_source(): string
{
    $source = piece_export_runtime_source_file('assets/vendor/piece-runtime/three/three.module.js');
    $source = piece_export_strip_module_syntax($source);
    $source = preg_replace_callback('/\nexport\s*\{([^}]+)\};\s*$/s', static function (array $matches): string {
        $exports = array_filter(array_map('trim', explode(',', $matches[1])));
        $assignments = [];
        foreach ($exports as $export) {
            if (preg_match('/^(.+)\s+as\s+(.+)$/', $export, $aliasMatch)) {
                $assignments[] = trim($aliasMatch[2]) . ': ' . trim($aliasMatch[1]);
            } else {
                $assignments[] = $export;
            }
        }

        return "\nwindow.THREE = {\n  " . implode(",\n  ", $assignments) . "\n};\n";
    }, $source);

    if (!is_string($source) || strpos($source, 'window.THREE = {') === false) {
        throw new RuntimeException('Could not convert Three.js module source for direct-open export.');
    }
    piece_export_assert_no_module_syntax($source, 'three.global.js');

    return "(function(){\n'use strict';\n" . $source . "\n})();\n";
}

function piece_export_orbitcontrols_global_source(): string
{
    $source = piece_export_runtime_source_file('assets/vendor/piece-runtime/three/OrbitControls.js');
    $source = piece_export_strip_module_syntax($source);
    $source = preg_replace(
        '/^import\s*\{([^}]+)\}\s*from\s*[\'"]three[\'"];\s*/s',
        "const {\$1} = window.THREE;\n",
        $source
    ) ?? $source;
    $source = preg_replace('/\nexport\s*\{\s*OrbitControls\s*\};\s*$/s', "\nwindow.OrbitControls = OrbitControls;\n", $source) ?? $source;

    if (strpos($source, 'window.OrbitControls = OrbitControls;') === false) {
        throw new RuntimeException('Could not convert OrbitControls module source for direct-open export.');
    }
    piece_export_assert_no_module_syntax($source, 'OrbitControls.global.js');

    return "(function(){\n'use strict';\n" . $source . "\n})();\n";
}

function piece_export_gltfloader_global_source(): string
{
    $utilsSource = piece_export_runtime_source_file('assets/vendor/piece-runtime/three/utils/BufferGeometryUtils.js');
    $utilsSource = piece_export_strip_module_syntax($utilsSource);
    // var, not const: $utilsSource and $loaderSource are concatenated into
    // the SAME function scope below, and both files import overlapping THREE
    // symbols (e.g. BufferAttribute) from 'three' — two `const` destructures
    // of the same identifier in one scope is itself a SyntaxError. `var`
    // tolerates the redundant re-declaration (each assigns the same value).
    $utilsSource = preg_replace(
        '/^import\s*\{([^}]+)\}\s*from\s*[\'"]three[\'"];\s*/s',
        "var {\$1} = window.THREE;\n",
        $utilsSource
    ) ?? $utilsSource;
    $utilsSource = preg_replace_callback('/\nexport\s*\{([^}]+)\};\s*$/s', static function (array $matches): string {
        $exports = array_filter(array_map('trim', explode(',', $matches[1])));
        return "\nconst __CreatrBufferGeometryUtils = {\n  " . implode(",\n  ", $exports) . "\n};\n";
    }, $utilsSource) ?? $utilsSource;

    $loaderSource = piece_export_runtime_source_file('assets/vendor/piece-runtime/three/GLTFLoader.js');
    $loaderSource = piece_export_strip_module_syntax($loaderSource);
    // var, not const — see matching note above $utilsSource's import rewrite.
    $loaderSource = preg_replace(
        '/^import\s*\{([^}]+)\}\s*from\s*[\'"]three[\'"];\s*/s',
        "var {\$1} = window.THREE;\n",
        $loaderSource
    ) ?? $loaderSource;
    // var, not const — toTrianglesDrawMode is also declared as a plain
    // function by $utilsSource itself (after stripping its own `export`)
    // in this same shared scope; see the note on $utilsSource's import
    // rewrite above.
    $loaderSource = preg_replace(
        '/^import\s*\{\s*toTrianglesDrawMode\s*\}\s*from\s*[\'"]\.\/utils\/BufferGeometryUtils\.js[\'"];\s*/m',
        "var toTrianglesDrawMode = __CreatrBufferGeometryUtils.toTrianglesDrawMode;\n",
        $loaderSource
    ) ?? $loaderSource;
    $loaderSource = preg_replace(
        '/\nexport\s*\{\s*GLTFLoader\s*\};\s*$/s',
        "\nwindow.GLTFLoader = GLTFLoader;\nwindow.THREE.GLTFLoader = GLTFLoader;\n",
        $loaderSource
    ) ?? $loaderSource;

    if (strpos($utilsSource, '__CreatrBufferGeometryUtils') === false || strpos($loaderSource, 'window.THREE.GLTFLoader = GLTFLoader;') === false) {
        throw new RuntimeException('Could not convert GLTFLoader module source for direct-open export.');
    }
    piece_export_assert_no_module_syntax($utilsSource, 'GLTFLoader.global.js (BufferGeometryUtils)');
    piece_export_assert_no_module_syntax($loaderSource, 'GLTFLoader.global.js');

    return "(function(){\n'use strict';\n" . $utilsSource . "\n" . $loaderSource . "\n})();\n";
}

function piece_export_device_orientation_global_source(): string
{
    $source = piece_export_runtime_source_file('assets/js/three-device-orientation-controls.js');
    $source = preg_replace(
        '/^\/\/ Vendored(.+?)import\s*\{([^}]+)\}\s*from\s*[\'"]https:\/\/cdn\.jsdelivr\.net\/npm\/three@0\.160\.0\/build\/three\.module\.js[\'"];\s*/s',
        "// Vendored\$1const {\$2} = window.THREE;\n",
        $source
    ) ?? $source;
    $source = preg_replace('/\nexport\s*\{\s*DeviceOrientationControls\s*\};\s*$/s', "\nwindow.DeviceOrientationControls = DeviceOrientationControls;\n", $source) ?? $source;

    if (strpos($source, 'window.DeviceOrientationControls = DeviceOrientationControls;') === false) {
        throw new RuntimeException('Could not convert DeviceOrientationControls module source for direct-open export.');
    }

    return "(function(){\n'use strict';\n" . $source . "\n})();\n";
}

function piece_export_immersive_gallery_global_source(): string
{
    $source = piece_export_patched_immersive_gallery_source();
    $exportNames = [];
    if (preg_match_all('/^export\s+const\s+([A-Za-z_$][A-Za-z0-9_$]*)\s*=/m', $source, $matches)) {
        $exportNames = array_merge($exportNames, $matches[1]);
    }
    if (preg_match_all('/^export\s+function\s+([A-Za-z_$][A-Za-z0-9_$]*)\s*\(/m', $source, $matches)) {
        $exportNames = array_merge($exportNames, $matches[1]);
    }

    $source = preg_replace(
        "/^import \* as THREE from '\\.\/three\/three\\.module\\.js';\nimport \\{ OrbitControls \\} from '\\.\/three\/addons\/controls\/OrbitControls\\.js';\n/s",
        "const THREE = window.THREE;\nconst OrbitControls = window.OrbitControls;\n",
        $source
    ) ?? $source;
    $source = preg_replace(
        "/let _GLTFLoaderCtor = null;\ntry \\{\n  \\(\\{ GLTFLoader: _GLTFLoaderCtor \\} = await import\\('https:\\/\\/cdn\\.jsdelivr\\.net\\/npm\\/three@0\\.160\\.0\\/examples\\/jsm\\/loaders\\/GLTFLoader\\.js'\\)\\);\n\\} catch \\(_e\\) \\{\n  \\/\/ 3D model loader unavailable; model-free pieces are unaffected\\.\n\\}\n/s",
        "let _GLTFLoaderCtor = window.GLTFLoader || null;\n",
        $source
    ) ?? $source;
    $source = preg_replace(
        "/let _GLTFLoaderCtor = null;\ntry \\{\n  \\(\\{ GLTFLoader: _GLTFLoaderCtor \\} = await import\\('\\.\\/three\\/GLTFLoader\\.js'\\)\\);\n\\} catch \\(_e\\) \\{\n  \\/\/ 3D model loader unavailable; model-free pieces are unaffected\\.\n\\}\n/s",
        "let _GLTFLoaderCtor = window.GLTFLoader || null;\n",
        $source
    ) ?? $source;
    $source = str_replace(
        'const { DeviceOrientationControls } = await import("./three-device-orientation-controls.js");',
        'const DeviceOrientationControls = window.DeviceOrientationControls;',
        $source
    );
    $source = preg_replace('/^export\s+const\s+/m', 'const ', $source) ?? $source;
    $source = preg_replace('/^export\s+function\s+/m', 'function ', $source) ?? $source;

    $exports = array_values(array_unique($exportNames));
    if ($exports === []) {
        throw new RuntimeException('Could not find immersive gallery exports for direct-open export.');
    }

    return "(function(){\n'use strict';\n" . $source . "\nwindow.CreatrImmersiveGallery = {\n  " . implode(",\n  ", $exports) . "\n};\n})();\n";
}

function piece_export_download_bridge_script(): string
{
    return piece_escape_inline_script(
        piece_export_runtime_source_file('assets/js/public-piece-download.js')
    );
}

/**
 * Shared behavior for standalone A-Frame exports: uploaded GLB files have
 * arbitrary units/origins, so a successful load still needs diagnostics and
 * an idempotent fit before it can be trusted to be visible.
 */
function piece_export_aframe_model_diagnostics_script(): string
{
    return <<<'JS'
function installAFrameModelDiagnostics(scene) {
  if (window.CreatrAFrameModelRuntime?.install) {
    window.CreatrAFrameModelRuntime.install(scene);
    return;
  }
  const THREE_NS = window.AFRAME?.THREE || window.THREE;
  const emit = (entity, status, data = {}) => {
    const detail = { status, entityId: entity?.id || '', ...data };
    try { entity?.dispatchEvent(new CustomEvent('creatr-model-status', { detail })); } catch (_) {}
    try { window.parent.postMessage({ type: 'creatr-aframe-model', ...detail }, '*'); } catch (_) {}
  };
  const modelSource = (entity) => {
    const ref = String(entity?.getAttribute('gltf-model') || '');
    if (!ref.startsWith('#')) return ref || '(missing gltf-model source)';
    return String(document.getElementById(ref.slice(1))?.getAttribute('src') || ref);
  };
  const recoverBinaryModel = (entity, source, onSuccess, onFailure) => {
    const component = entity.components?.['gltf-model'];
    const loaderClass = window.AFRAME?.THREE?.GLTFLoader || window.THREE?.GLTFLoader;
    const loader = component?.loader || (loaderClass ? new loaderClass() : null);
    if (!loader || !source || source === '(missing gltf-model source)') {
      onFailure(new Error('A-Frame GLTFLoader.parse is unavailable.'));
      return;
    }
    fetch(source, { credentials: 'same-origin' }).then((response) => {
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      return response.arrayBuffer();
    }).then((bytes) => new Promise((resolve, reject) => {
      const basePath = source.slice(0, Math.max(0, source.search(/[?#]/) >= 0 ? source.search(/[?#]/) : source.length)).replace(/[^/]*$/, '');
      loader.parse(bytes, basePath, resolve, reject);
    })).then((gltf) => {
      const model = gltf?.scene || gltf?.scenes?.[0];
      if (!model) throw new Error('The parsed GLB did not contain a scene.');
      entity.setObject3D('mesh', model);
      if (entity.components?.['gltf-model']) entity.components['gltf-model'].model = model;
      onSuccess(model);
    }).catch(onFailure);
  };
  const fitEntity = (entity) => {
    const source = modelSource(entity);
    if (entity.__creatrModelFitted) return;
    const mesh = entity.getObject3D('mesh');
    if (!mesh || !THREE_NS?.Box3 || !THREE_NS?.Vector3 || !THREE_NS?.Matrix4) {
      emit(entity, 'invalid', { source, message: 'A-Frame loaded the model, but its mesh or bounds API is unavailable.' });
      return;
    }
    mesh.updateWorldMatrix?.(true, true);
    const box = new THREE_NS.Box3().setFromObject(mesh);
    const size = box.getSize(new THREE_NS.Vector3());
    const center = box.getCenter(new THREE_NS.Vector3());
    const maxDim = Math.max(size.x, size.y, size.z);
    if (!Number.isFinite(maxDim) || maxDim <= 0) {
      emit(entity, 'invalid', { source, message: 'A-Frame loaded the model, but its bounding box is empty.', dimensions: [size.x, size.y, size.z] });
      return;
    }
    center.applyMatrix4(new THREE_NS.Matrix4().copy(mesh.matrixWorld).invert());
    mesh.position.sub(center);
    const targetSize = 3;
    const fitScale = targetSize / maxDim;
    if (Number.isFinite(fitScale) && fitScale > 0) mesh.scale.multiplyScalar(fitScale);
    entity.__creatrModelFitted = true;
    emit(entity, 'loaded', { source, dimensions: [size.x, size.y, size.z], targetSize, fitScale, message: `Loaded and fitted ${source}.` });
  };
  const startBinaryFallback = (entity, source, initialMessage) => {
    if (entity.__creatrBinaryModelFallbackStarted) return;
    entity.__creatrBinaryModelFallbackStarted = true;
    recoverBinaryModel(entity, source, () => {
      fitEntity(entity);
      entity.emit('model-loaded', { format: 'gltf', model: entity.getObject3D('mesh') });
    }, (fallbackError) => {
      const text = `A-Frame model ${source} failed to load: ${initialMessage}; binary fallback failed: ${fallbackError?.message || fallbackError}`;
      emit(entity, 'error', { source, message: text });
      showPieceError(text);
    });
  };
  scene.querySelectorAll('[gltf-model]').forEach((entity) => {
    if (entity.__creatrModelDiagnosticsInstalled) return;
    entity.__creatrModelDiagnosticsInstalled = true;
    const source = modelSource(entity);
    entity.addEventListener('model-loaded', () => fitEntity(entity), { once: true });
    entity.addEventListener('model-error', (event) => {
      const message = event?.detail?.src?.message || event?.detail?.message || 'The GLB could not be parsed or fetched.';
      startBinaryFallback(entity, source, message);
    }, { once: true });
    setTimeout(() => {
      if (entity.getObject3D('mesh')) fitEntity(entity);
      else if (!/\.gltf(?:[?#]|$)/i.test(source)) startBinaryFallback(entity, source, 'A-Frame did not produce a model after the initial load attempt');
    }, 750);
  });
}
JS;
}

/**
 * c2/c2_interactive bootstrap for the standalone export. Mirrors
 * piece-runtime.js's bootCanvasRuntime(): c2_interactive pieces get a
 * pointer-position mover normalized to ~0..1 (same order of magnitude as
 * the three/aframe camera-position deltas createPieceRuntimeAudioController
 * was tuned against) wired to window.__creatrSonicSetMover, so a downloaded
 * c2_interactive piece with sound enabled gets pointer-modulated pitch
 * offline, not just the idle random-note pattern. Plain c2 skips the pointer
 * mover (no motion signal) but still gets the DOM camera overlay hooks. */
function piece_export_c2_bootstrap_script(bool $interactive, string $cameraPlacement = ''): string
{
    $domCameraOverlayScript = piece_export_dom_camera_overlay_script('canvas', $cameraPlacement === 'background' ? 'background' : 'overlay');
    $presentationTiltScript = $interactive ? '' : piece_export_presentation_tilt_script('canvas');
    $pointerWiring = $interactive ? <<<'JS'
  if (window.__creatrSonicSetMover) {
    const c2Mover = { position: { x: 0, y: 0, z: 0 } };
    updateC2Mover = (clientX, clientY) => {
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
    if (typeof src !== 'string' || src.trim() === '') {
      const message = 'Cannot load image with an empty source URL.';
      showPieceError(message);
      return Promise.reject(new Error(message));
    }
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
  let updateC2Mover = null;
  {$pointerWiring}
  let lastHandClient = null;
  let c2HandSteeringExclusive = false;
  const blockManualC2InputWhileSteering = (event) => {
    if (!c2HandSteeringExclusive || event.isTrusted === false) return;
    event.preventDefault?.();
    event.stopImmediatePropagation?.();
  };
  ['pointerdown', 'pointermove', 'pointerup', 'click', 'mousedown', 'mousemove', 'mouseup', 'touchstart', 'touchmove', 'touchend']
    .forEach((type) => canvas.addEventListener(type, blockManualC2InputWhileSteering, { capture: true, passive: false }));
  const dispatchHandPointer = (type, clientX, clientY) => {
    try { canvas.dispatchEvent(new PointerEvent(type, { clientX, clientY, bubbles: true, isPrimary: true, pointerType: 'touch', button: 0, buttons: type === 'pointerup' ? 0 : 1 })); } catch (_) {}
  };
  window.__pieceHandHooks = {
    engine: 'c2_interactive',
    setHandSteering(active) {
      c2HandSteeringExclusive = !!active;
      if (!c2HandSteeringExclusive && lastHandClient) {
        dispatchHandPointer('pointerup', lastHandClient.x, lastHandClient.y);
      }
      return true;
    },
    handPoint(nx, ny) {
      const rect = canvas.getBoundingClientRect();
      if (!rect.width || !rect.height) return;
      const clientX = rect.left + nx * rect.width;
      const clientY = rect.top + ny * rect.height;
      lastHandClient = { x: clientX, y: clientY };
      if (typeof updateC2Mover === 'function') {
        updateC2Mover(clientX, clientY);
      }
      dispatchHandPointer('pointermove', clientX, clientY);
    },
    // Pinch gesture → pointer button (see handControlBinding twin in
    // piece-runtime.js): drag-driven c2 sketches need pointerdown/up.
    handPress(down) {
      if (!c2HandSteeringExclusive || !lastHandClient) return;
      dispatchHandPointer(down ? 'pointerdown' : 'pointerup', lastHandClient.x, lastHandClient.y);
    },
  };
{$domCameraOverlayScript}
{$presentationTiltScript}
  if (typeof window.sketch === 'function') window.sketch({ c2: window.c2, canvas, startFrame, loadImage, drawImage, drawImageCover });
} catch (error) { showPieceError(error); }
</script>
HTML;
}


/**
 * Shared DOM camera overlay for the 2D-surface export bootstraps (p5, c2,
 * c2_interactive, svg): merges the overlay members into the bootstrap's
 * already-created window.__pieceHandHooks (engine/handPoint) and installs
 * __creatrComposeCapture. $getSurfaceExpr is a JS expression evaluating to
 * the piece's surface element. Export twin of createDomCameraOverlayHooks()
 * in piece-runtime.js — keep the behavior in sync.
 */
function piece_export_dom_camera_overlay_script(string $getSurfaceExpr, string $placement = 'overlay'): string
{
    $isBackgroundJson = json_encode($placement === 'background');
    return <<<JS
  (function () {
    var getSurface = function () { return {$getSurfaceExpr}; };
    // Background placement: the feed sits BEHIND the piece surface (only
    // visible through transparent regions) and starts opaque; overlay
    // placement blends a subtle feed above the piece. Keep in sync with
    // createDomCameraOverlayHooks() in piece-runtime.js.
    var cameraIsBackground = {$isBackgroundJson};
    var cameraOverlay = null;
    var cameraSourceVideo = null;
    var cameraOpacity = cameraIsBackground ? 1 : 0.35;
    var cameraResizeObserver = null;
    var cameraFullscreenSync = null;
    var cameraSyncFrame = 0;
    var cameraLastBox = '';
    function queueCameraOverlayBoxSync() {
      if (cameraSyncFrame) return;
      cameraSyncFrame = requestAnimationFrame(function () {
        cameraSyncFrame = 0;
        syncCameraOverlayBox();
      });
    }
    function syncCameraOverlayBox() {
      var surface = getSurface();
      if (!cameraOverlay || !surface || !surface.parentElement) return;
      // Rect-based (not offsetLeft/offsetWidth) so it also works for <svg>
      // roots, which have no HTMLElement offset geometry.
      var rect = surface.getBoundingClientRect();
      var parentRect = surface.parentElement.getBoundingClientRect();
      var layoutWidth = surface.offsetWidth || surface.clientWidth || rect.width;
      var layoutHeight = surface.offsetHeight || surface.clientHeight || rect.height;
      var layoutLeft = Number.isFinite(surface.offsetLeft) ? surface.offsetLeft : ((surface.x && surface.x.baseVal && surface.x.baseVal.value) || rect.left - parentRect.left);
      var layoutTop = Number.isFinite(surface.offsetTop) ? surface.offsetTop : ((surface.y && surface.y.baseVal && surface.y.baseVal.value) || rect.top - parentRect.top);
      var box = [layoutLeft, layoutTop, layoutWidth, layoutHeight]
        .map(function (value) { return Math.round(value * 100) / 100; }).join('|');
      if (box === cameraLastBox) return;
      cameraLastBox = box;
      var values = box.split('|');
      cameraOverlay.style.left = values[0] + 'px';
      cameraOverlay.style.top = values[1] + 'px';
      cameraOverlay.style.width = values[2] + 'px';
      cameraOverlay.style.height = values[3] + 'px';
    }
    window.__pieceHandHooks = Object.assign(window.__pieceHandHooks || {}, {
      setBackgroundVideo: function (video) {
        var surface = getSurface();
        if (!video || !surface || !surface.parentElement) return false;
        this.clearBackgroundVideo();
        var parent = surface.parentElement;
        if (getComputedStyle(parent).position === 'static') parent.style.position = 'relative';
        cameraOverlay = document.createElement('video');
        cameraOverlay.autoplay = true; cameraOverlay.muted = true; cameraOverlay.playsInline = true;
        cameraOverlay.srcObject = video.srcObject;
        cameraSourceVideo = video;
        cameraOverlay.style.cssText = 'position:absolute;transform:scaleX(-1);pointer-events:none;z-index:' + (cameraIsBackground ? '0' : '2') + ';';
        cameraOverlay.style.objectFit = 'cover';
        cameraOverlay.style.opacity = String(cameraOpacity);
        if (cameraIsBackground) {
          if (getComputedStyle(surface).position === 'static') surface.style.position = 'relative';
          if (!surface.style.zIndex || Number(surface.style.zIndex) < 1) surface.style.zIndex = '1';
          parent.insertBefore(cameraOverlay, parent.firstChild);
        } else {
          parent.appendChild(cameraOverlay);
        }
        syncCameraOverlayBox();
        if (typeof ResizeObserver === 'function') {
          cameraResizeObserver = new ResizeObserver(queueCameraOverlayBoxSync);
          cameraResizeObserver.observe(surface);
          cameraResizeObserver.observe(parent);
        }
        cameraFullscreenSync = queueCameraOverlayBoxSync;
        document.addEventListener('fullscreenchange', cameraFullscreenSync);
        document.addEventListener('webkitfullscreenchange', cameraFullscreenSync);
        cameraOverlay.play().catch(function () {});
        return true;
      },
      clearBackgroundVideo: function () {
        if (cameraResizeObserver) { cameraResizeObserver.disconnect(); cameraResizeObserver = null; }
        if (cameraSyncFrame) { cancelAnimationFrame(cameraSyncFrame); cameraSyncFrame = 0; }
        cameraLastBox = '';
        if (cameraFullscreenSync) {
          document.removeEventListener('fullscreenchange', cameraFullscreenSync);
          document.removeEventListener('webkitfullscreenchange', cameraFullscreenSync);
          cameraFullscreenSync = null;
        }
        if (cameraOverlay) { cameraOverlay.remove(); cameraOverlay = null; }
        cameraSourceVideo = null;
      },
      setBackgroundOpacity: function (value) {
        cameraOpacity = Math.max(0, Math.min(1, Number(value)));
        if (cameraOverlay) cameraOverlay.style.opacity = String(cameraOpacity);
      },
      getBackgroundOpacity: function () { return cameraOpacity; },
      getBackgroundVideo: function () { return cameraSourceVideo || cameraOverlay; },
    });
    window.__creatrComposeCapture = async function (baseCanvas) {
      if (!cameraOverlay) return baseCanvas;
      if (cameraOverlay.readyState < 2 || !cameraOverlay.videoWidth) throw new Error('The camera frame is not ready to capture yet.');
      var composed = document.createElement('canvas');
      composed.width = baseCanvas.width; composed.height = baseCanvas.height;
      var ctx = composed.getContext('2d');
      var drawCamera = function () {
        ctx.save(); ctx.globalAlpha = cameraOpacity; ctx.translate(composed.width, 0); ctx.scale(-1, 1);
        ctx.drawImage(cameraOverlay, 0, 0, composed.width, composed.height); ctx.restore();
      };
      // Match on-screen stacking: background bakes under the piece.
      if (cameraIsBackground) { drawCamera(); ctx.drawImage(baseCanvas, 0, 0); }
      else { ctx.drawImage(baseCanvas, 0, 0); drawCamera(); }
      return composed;
    };
    window.addEventListener('resize', syncCameraOverlayBox);
  })();
JS;
}

function piece_export_presentation_tilt_script(string $getSurfaceExpr): string
{
    return <<<JS
(function () {
  var targetX = 0, targetY = 0, currentX = 0, currentY = 0;
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var previousComposeCapture = window.__creatrComposeCapture;
  window.__creatrComposeCapture = async function (baseCanvas) {
    var source = typeof previousComposeCapture === 'function' ? await previousComposeCapture(baseCanvas) : baseCanvas;
    if (reduced || (Math.abs(currentX) < 0.01 && Math.abs(currentY) < 0.01)) return source;
    var width = source.width, height = source.height;
    var output = document.createElement('canvas'); output.width = width; output.height = height;
    var ctx = output.getContext('2d');
    if (!ctx || !width || !height) return source;
    var captureSurface = {$getSurfaceExpr};
    var captureBackground = captureSurface && captureSurface.parentElement ? getComputedStyle(captureSurface.parentElement).backgroundColor : 'rgb(0, 0, 0)';
    ctx.fillStyle = captureBackground === 'rgba(0, 0, 0, 0)' ? 'rgb(0, 0, 0)' : captureBackground;
    ctx.fillRect(0, 0, width, height);
    var pitch = currentX * Math.PI / 180, yaw = currentY * Math.PI / 180, perspective = 900;
    function project(x, y) {
      var cy = Math.cos(yaw), sy = Math.sin(yaw), cx = Math.cos(pitch), sx = Math.sin(pitch);
      var x1 = x * cy, z1 = -x * sy, y2 = y * cx - z1 * sx, z2 = y * sx + z1 * cx;
      var scale = perspective / Math.max(1, perspective - z2);
      return { x: width / 2 + x1 * scale, y: height / 2 + y2 * scale };
    }
    var tl = project(-width / 2, -height / 2), tr = project(width / 2, -height / 2);
    var bl = project(-width / 2, height / 2), br = project(width / 2, height / 2);
    function solveHomography(pairs) {
      var matrix = [];
      pairs.forEach(function (pair) {
        var x = pair.x, y = pair.y, u = pair.u, v = pair.v;
        matrix.push([x,y,1,0,0,0,-u*x,-u*y,u]);
        matrix.push([0,0,0,x,y,1,-v*x,-v*y,v]);
      });
      for (var col=0; col<8; col++) {
        var pivot=col;
        for (var row=col+1; row<8; row++) if (Math.abs(matrix[row][col])>Math.abs(matrix[pivot][col])) pivot=row;
        if (Math.abs(matrix[pivot][col])<1e-9) return null;
        var swap=matrix[col]; matrix[col]=matrix[pivot]; matrix[pivot]=swap;
        var divisor=matrix[col][col];
        for (var c=col; c<9; c++) matrix[col][c]/=divisor;
        for (var r=0; r<8; r++) {
          if (r===col) continue;
          var factor=matrix[r][col];
          for (var cc=col; cc<9; cc++) matrix[r][cc]-=factor*matrix[col][cc];
        }
      }
      return matrix.map(function (row) { return row[8]; });
    }
    var homography=solveHomography([
      {x:tl.x,y:tl.y,u:0,v:0},{x:tr.x,y:tr.y,u:width-1,v:0},
      {x:br.x,y:br.y,u:width-1,v:height-1},{x:bl.x,y:bl.y,u:0,v:height-1}
    ]);
    if (!homography) return source;
    var sourceContext=source.getContext('2d');
    if (!sourceContext) return source;
    var sourcePixels=sourceContext.getImageData(0,0,width,height);
    var outputPixels=ctx.getImageData(0,0,width,height);
    var src=sourcePixels.data,dst=outputPixels.data,quad=[tl,tr,br,bl];
    function insideQuad(x,y) {
      var sign=0;
      for (var i=0;i<4;i++) {
        var a=quad[i],b=quad[(i+1)%4];
        var cross=(b.x-a.x)*(y-a.y)-(b.y-a.y)*(x-a.x);
        if (Math.abs(cross)<1e-6) continue;
        var nextSign=cross>0?1:-1;
        if (sign&&nextSign!==sign) return false;
        sign=nextSign;
      }
      return true;
    }
    var minX=Math.max(0,Math.floor(Math.min.apply(null,quad.map(function(p){return p.x;}))));
    var maxX=Math.min(width-1,Math.ceil(Math.max.apply(null,quad.map(function(p){return p.x;}))));
    var minY=Math.max(0,Math.floor(Math.min.apply(null,quad.map(function(p){return p.y;}))));
    var maxY=Math.min(height-1,Math.ceil(Math.max.apply(null,quad.map(function(p){return p.y;}))));
    for (var yy=minY;yy<=maxY;yy++) {
      for (var xx=minX;xx<=maxX;xx++) {
        if (!insideQuad(xx+0.5,yy+0.5)) continue;
        var denominator=homography[6]*xx+homography[7]*yy+1;
        if (Math.abs(denominator)<1e-9) continue;
        var u=(homography[0]*xx+homography[1]*yy+homography[2])/denominator;
        var v=(homography[3]*xx+homography[4]*yy+homography[5])/denominator;
        if (u<0||v<0||u>width-1||v>height-1) continue;
        var x0=Math.floor(u),y0=Math.floor(v),x1=Math.min(width-1,x0+1),y1=Math.min(height-1,y0+1);
        var fx=u-x0,fy=v-y0,dstIndex=(yy*width+xx)*4;
        for (var channel=0;channel<4;channel++) {
          var p00=src[(y0*width+x0)*4+channel],p10=src[(y0*width+x1)*4+channel];
          var p01=src[(y1*width+x0)*4+channel],p11=src[(y1*width+x1)*4+channel];
          dst[dstIndex+channel]=(p00*(1-fx)+p10*fx)*(1-fy)+(p01*(1-fx)+p11*fx)*fy;
        }
      }
      if ((yy-minY)%96===95) await new Promise(function(resolve){setTimeout(resolve,0);});
    }
    ctx.putImageData(outputPixels,0,0);
    return output;
  };
  function tick() {
    var surface = {$getSurfaceExpr};
    currentX += (targetX - currentX) * 0.14;
    currentY += (targetY - currentY) * 0.14;
    if (surface) {
      var tiltTransform = reduced ? '' : 'perspective(900px) rotateX(' + currentX.toFixed(2) + 'deg) rotateY(' + currentY.toFixed(2) + 'deg)';
      surface.style.transformOrigin = '50% 50%';
      surface.style.transform = tiltTransform;
      var cameraOverlay = window.__pieceHandHooks && window.__pieceHandHooks._cameraOverlay;
      if (cameraOverlay) {
        cameraOverlay.style.transformOrigin = '50% 50%';
        cameraOverlay.style.transform = (tiltTransform ? tiltTransform + ' ' : '') + 'scaleX(-1)';
      }
    }
    requestAnimationFrame(tick);
  }
  window.__pieceHandHooks = Object.assign(window.__pieceHandHooks || {}, {
    handPoint: function (nx, ny) {
      targetY = Math.max(-8, Math.min(8, (nx - 0.5) * 16));
      targetX = Math.max(-6, Math.min(6, -(ny - 0.5) * 12));
    },
    handLost: function () { targetX = 0; targetY = 0; }
  });
  requestAnimationFrame(tick);
})();
JS;
}

/**
 * DOM camera overlay as object-literal members — the splice-compatible
 * variant of piece_export_dom_camera_overlay_script(), used when a 3D piece
 * stores overlay placement so its bootstrap gets a DOM <video> over the
 * WebGL canvas instead of the in-scene quad. Overlay semantics only.
 */
function piece_export_dom_camera_overlay_members(string $getSurfaceExpr): string
{
    return <<<JS
      _cameraOverlay: null,
      _cameraOpacity: 0.35,
      _surfaceEl() { return {$getSurfaceExpr}; },
      setBackgroundVideo(video) {
        const surface = this._surfaceEl();
        if (!video || !surface || !surface.parentElement) return false;
        this.clearBackgroundVideo();
        const parent = surface.parentElement;
        if (getComputedStyle(parent).position === 'static') parent.style.position = 'relative';
        const overlay = document.createElement('video');
        overlay.autoplay = true; overlay.muted = true; overlay.playsInline = true;
        overlay.srcObject = video.srcObject;
        overlay.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;z-index:2;transform:scaleX(-1);pointer-events:none;';
        overlay.style.objectFit = 'cover';
        overlay.style.opacity = String(this._cameraOpacity);
        parent.appendChild(overlay);
        this._cameraOverlay = overlay;
        overlay.play().catch(() => {});
        return true;
      },
      clearBackgroundVideo() {
        if (this._cameraOverlay) { this._cameraOverlay.remove(); this._cameraOverlay = null; }
      },
      setBackgroundOpacity(value) {
        this._cameraOpacity = Math.max(0, Math.min(1, Number(value)));
        if (this._cameraOverlay) this._cameraOverlay.style.opacity = String(this._cameraOpacity);
      },
      getBackgroundOpacity() { return this._cameraOpacity; },
JS;
}

/**
 * Camera-feed hook members for the 3D export bootstraps (Three.js/A-Frame):
 * a mirrored, blended, camera-attached background quad — the export twin of
 * piece-runtime.js's createCameraBlendQuadHooks(). Returned as object-literal
 * members so each bootstrap splices them into its own __pieceHandHooks.
 * $afterChangeJs lets the three bootstraps keep their `userHasInteracted`
 * bookkeeping on every camera state change.
 */
function piece_export_camera_blend_quad_members(string $threeExpr, string $sceneExpr, string $cameraExpr, string $afterChangeJs = ''): string
{
    return <<<JS
      _cameraQuad: null,
      _videoTexture: null,
      _cameraOpacity: 1,
      setBackgroundVideo(video) {
        // Locals deliberately named so they can never shadow the caller's
        // scope: the aframe expressions reference an outer `scene` variable,
        // and `const scene = scene && …` was a self-referential TDZ
        // ReferenceError that silently killed the camera toggle in regular
        // downloads (piece 109 regression).
        const T = {$threeExpr};
        const quadScene = {$sceneExpr};
        const quadCamera = {$cameraExpr};
        if (!video || !quadScene || !quadCamera || !T || !T.VideoTexture) return false;
        this.clearBackgroundVideo();
        const texture = new T.VideoTexture(video);
        if (T.SRGBColorSpace) texture.colorSpace = T.SRGBColorSpace;
        texture.wrapS = T.RepeatWrapping;
        texture.repeat.x = -1;
        texture.offset.x = 1;
        const material = new T.MeshBasicMaterial({
          map: texture, transparent: true, opacity: this._cameraOpacity,
          depthTest: false, depthWrite: false, toneMapped: false, fog: false,
        });
        const quad = new T.Mesh(new T.PlaneGeometry(1, 1), material);
        quad.renderOrder = -1;
        quad.frustumCulled = false;
        quad.onBeforeRender = (_renderer, _scene, renderCamera) => {
          const cam = renderCamera && renderCamera.isPerspectiveCamera ? renderCamera : null;
          if (!cam) return;
          const dist = Math.max(cam.near * 2, 0.05);
          quad.position.set(0, 0, -dist);
          const height = 2 * dist * Math.tan((cam.fov * Math.PI) / 360);
          quad.scale.set(height * (cam.aspect || 1), height, 1);
        };
        quadCamera.add(quad);
        this._cameraQuad = quad;
        this._videoTexture = texture;{$afterChangeJs}
        return true;
      },
      clearBackgroundVideo() {
        const quad = this._cameraQuad;
        if (quad) {
          try { quad.parent && quad.parent.remove(quad); } catch (_e) {}
          try { quad.geometry.dispose(); } catch (_e) {}
          try { quad.material.dispose(); } catch (_e) {}
          this._cameraQuad = null;
        }
        if (this._videoTexture) {
          try { this._videoTexture.dispose(); } catch (_e) {}
          this._videoTexture = null;
        }{$afterChangeJs}
      },
      setBackgroundOpacity(value) {
        this._cameraOpacity = Math.max(0, Math.min(1, Number(value)));
        if (this._cameraQuad) this._cameraQuad.material.opacity = this._cameraOpacity;
      },
      getBackgroundOpacity() { return this._cameraOpacity; },
JS;
}

function piece_export_bootstrap(string $engine, string $generationMode = '', string $runtimeMode = 'cdn', string $cameraPlacement = ''): string
{
    // Author-chosen overlay placement on the 3D engines swaps the in-scene
    // background quad for a DOM <video> over the WebGL canvas — mirrored
    // from piece-runtime.js's placement branch. Empty/'background' keeps the
    // quad (its renderOrder -1 draw already behaves as a background).
    $threeCameraQuadMembers = $cameraPlacement === 'overlay'
        ? piece_export_dom_camera_overlay_members('canvas')
        : piece_export_camera_blend_quad_members(
            'window.THREE',
            'state.scene',
            'state.camera',
            "\n        userHasInteracted = true;"
        );
    $aframeCameraQuadMembers = $cameraPlacement === 'overlay'
        ? piece_export_dom_camera_overlay_members("document.querySelector('a-scene canvas') || document.querySelector('canvas')")
        : piece_export_camera_blend_quad_members(
            'getAFrameThree()',
            'scene && scene.object3D',
            'getAFrameCameraObject()'
        );
    if ($engine === 'three' && $runtimeMode === 'bundle') {
        return <<<HTML
<script>
try {
  const THREE = window.THREE;
  const OrbitControls = window.OrbitControls;
  if (!THREE || !OrbitControls) throw new Error('Three.js export runtime did not load.');
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
  if (typeof window.sketch === 'function') {
    try {
      window.sketch({ THREE: instrumentedThree, canvas, startFrame, width: canvas.width, height: canvas.height, size: { width: canvas.width, height: canvas.height }, OrbitControls });
    } catch (artworkError) {
      // Preserve the platform interaction layer when an authored optional
      // effect fails after creating a usable scene/camera/renderer.
      showPieceError(artworkError);
    }
  }
  if (state.camera && state.renderer && state.scene) {
    const controls = new OrbitControls(state.camera, canvas);
    const initialCameraPosition = state.camera.position.clone();
    const initialControlsTarget = controls.target.clone();
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
    let handSteeringExclusive = false;
    let controlsEnabledBeforeHand = true;

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
      controls.enabled = handSteeringExclusive ? false : controlsEnabledBeforeHand;
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
      if (handSteeringExclusive) return;
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
      if (handSteeringExclusive) return;
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
      if (handSteeringExclusive) return;
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
            controls.enabled = handSteeringExclusive ? false : controlsEnabledBeforeHand;
            animFromTarget = animToTarget = animFromCam = animToCam = null;
          }
        }
        if (!handSteeringExclusive && updateKeyboardNavigation()) externalMotion = true;
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
    // Interaction/camera hooks for the shared hand-tracking pipeline —
    // export twin of piece-runtime.js's three bootstrap block.
    window.__pieceHandHooks = {
      engine: 'three',
      setHandSteering(active) {
        if (!controls || !state.camera) return false;
        const next = !!active;
        if (next === handSteeringExclusive) return true;
        if (next) {
          controlsEnabledBeforeHand = controls.enabled;
          controls.enabled = false;
          keyboardKeys.clear();
          pointerState.clear();
          isOrbitActive = false;
          animFromTarget = animToTarget = animFromCam = animToCam = null;
        } else {
          controls.enabled = controlsEnabledBeforeHand;
        }
        handSteeringExclusive = next;
        return true;
      },
      resetView() {
        if (!controls || !state.camera) return false;
        const fromCamera = state.camera.position.clone();
        const fromTarget = controls.target.clone();
        const homeCamera = initialCameraPosition.clone();
        const homeTarget = initialControlsTarget.clone();
        const started = performance.now();
        return new Promise((resolve) => {
          function step(now) {
            const t = Math.min(1, (now - started) / 360);
            const eased = 1 - Math.pow(1 - t, 3);
            state.camera.position.lerpVectors(fromCamera, homeCamera, eased);
            controls.target.lerpVectors(fromTarget, homeTarget, eased);
            controls.update();
            userHasInteracted = true;
            if (t < 1) requestAnimationFrame(step); else resolve(true);
          }
          requestAnimationFrame(step);
        });
      },
      handPoint(nx, ny) {
        if (!handSteeringExclusive || !controls || !state.camera) return;
        const T = window.THREE;
        if (!T || !T.Spherical) return;
        const target = controls.target;
        const offset = state.camera.position.clone().sub(target);
        const sph = new T.Spherical().setFromVector3(offset);
        const desiredTheta = (nx - 0.5) * Math.PI * 1.5;
        const desiredPhi = Math.PI / 2 + (ny - 0.5) * Math.PI * 0.7;
        sph.theta += (desiredTheta - sph.theta) * 0.12;
        sph.phi += (desiredPhi - sph.phi) * 0.12;
        sph.phi = Math.max(0.15, Math.min(Math.PI - 0.15, sph.phi));
        offset.setFromSpherical(sph);
        state.camera.position.copy(target).add(offset);
        controls.update();
        userHasInteracted = true;
      },
      handCommand(command) {
        if (!handSteeringExclusive || !controls || !state.camera || !command) return;
        if (command.type === 'look') { this.handPoint(command.x, command.y); return; }
        const T = window.THREE;
        const target = controls.target;
        if (command.type === 'orbit' && T && T.Spherical) {
          const offset = state.camera.position.clone().sub(target);
          const sph = new T.Spherical().setFromVector3(offset);
          sph.theta += command.yaw || 0;
          sph.phi = Math.max(0.15, Math.min(Math.PI - 0.15, sph.phi + (command.pitch || 0)));
          state.camera.position.copy(target).add(offset.setFromSpherical(sph));
        } else if (command.type === 'travel' && T && T.Vector3) {
          const forward = new T.Vector3();
          state.camera.getWorldDirection(forward); forward.y = 0;
          if (forward.lengthSq() > 1e-6) forward.normalize();
          const right = new T.Vector3(-forward.z, 0, forward.x);
          const delta = forward.multiplyScalar((command.forward || 0) * 0.11).add(right.multiplyScalar((command.right || 0) * 0.09));
          state.camera.position.add(delta); target.add(delta);
        } else if (command.type === 'zoom') {
          const offset = state.camera.position.clone().sub(target);
          offset.setLength(Math.max(0.35, Math.min(40, offset.length() * (1 - (command.delta || 0)))));
          state.camera.position.copy(target).add(offset);
        }
        controls.update();
        userHasInteracted = true;
      },
{$threeCameraQuadMembers}
    };
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
HTML;
    }

    $twoDPlacement = $cameraPlacement === 'background' ? 'background' : 'overlay';
    $p5OverlayScript = piece_export_dom_camera_overlay_script("parent.querySelector('canvas') || parent", $twoDPlacement);
    $svgOverlayScript = piece_export_dom_camera_overlay_script("parent.querySelector('svg') || parent", $twoDPlacement);
    $p5TiltScript = piece_export_presentation_tilt_script("parent.querySelector('canvas') || parent");
    $svgTiltScript = piece_export_presentation_tilt_script("parent.querySelector('svg') || parent");

    // The three-cdn and aframe arms below are nowdocs (their JS is full of
    // interpolation-hostile syntax), so the shared camera-quad members are
    // spliced in by placeholder after the match instead of `{$...}`.
    $bootstrap = match ($engine) {
        'p5' => <<<HTML
<script>
try {
  const parent = document.getElementById('canvas-container') || document.getElementById('runtime-root');
  if (typeof window.sketch === 'function' && typeof window.p5 === 'function') new window.p5(window.sketch, parent);
{$p5OverlayScript}
{$p5TiltScript}
} catch (error) { showPieceError(error); }
</script>
HTML,
        'c2' => piece_export_c2_bootstrap_script($generationMode === 'c2_interactive', $cameraPlacement),
        'three' => <<<'HTML'
<script type="module">
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
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
  instrumentedThree.GLTFLoader = GLTFLoader;
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
  if (typeof window.sketch === 'function') {
    try {
      window.sketch({ THREE: instrumentedThree, canvas, startFrame, width: canvas.width, height: canvas.height, size: { width: canvas.width, height: canvas.height }, OrbitControls });
    } catch (artworkError) {
      showPieceError(artworkError);
    }
  }
  if (state.camera && state.renderer && state.scene) {
    const controls = new OrbitControls(state.camera, canvas);
    const initialCameraPosition = state.camera.position.clone();
    const initialControlsTarget = controls.target.clone();
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
    let handSteeringExclusive = false;
    let controlsEnabledBeforeHand = true;

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
      controls.enabled = handSteeringExclusive ? false : controlsEnabledBeforeHand;
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
            controls.enabled = handSteeringExclusive ? false : controlsEnabledBeforeHand;
            animFromTarget = animToTarget = animFromCam = animToCam = null;
          }
        }
        if (!handSteeringExclusive && updateKeyboardNavigation()) externalMotion = true;
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
    // Interaction/camera hooks for the shared hand-tracking pipeline —
    // export twin of piece-runtime.js's three bootstrap block.
    window.__pieceHandHooks = {
      engine: 'three',
      setHandSteering(active) {
        if (!controls || !state.camera) return false;
        const next = !!active;
        if (next === handSteeringExclusive) return true;
        if (next) {
          controlsEnabledBeforeHand = controls.enabled;
          controls.enabled = false;
          keyboardKeys.clear();
          pointerState.clear();
          isOrbitActive = false;
          animFromTarget = animToTarget = animFromCam = animToCam = null;
        } else {
          controls.enabled = controlsEnabledBeforeHand;
        }
        handSteeringExclusive = next;
        return true;
      },
      resetView() {
        if (!controls || !state.camera) return false;
        const fromCamera = state.camera.position.clone();
        const fromTarget = controls.target.clone();
        const homeCamera = initialCameraPosition.clone();
        const homeTarget = initialControlsTarget.clone();
        const started = performance.now();
        return new Promise((resolve) => {
          function step(now) {
            const t = Math.min(1, (now - started) / 360);
            const eased = 1 - Math.pow(1 - t, 3);
            state.camera.position.lerpVectors(fromCamera, homeCamera, eased);
            controls.target.lerpVectors(fromTarget, homeTarget, eased);
            controls.update();
            userHasInteracted = true;
            if (t < 1) requestAnimationFrame(step); else resolve(true);
          }
          requestAnimationFrame(step);
        });
      },
      handPoint(nx, ny) {
        if (!handSteeringExclusive || !controls || !state.camera) return;
        const T = window.THREE;
        if (!T || !T.Spherical) return;
        const target = controls.target;
        const offset = state.camera.position.clone().sub(target);
        const sph = new T.Spherical().setFromVector3(offset);
        const desiredTheta = (nx - 0.5) * Math.PI * 1.5;
        const desiredPhi = Math.PI / 2 + (ny - 0.5) * Math.PI * 0.7;
        sph.theta += (desiredTheta - sph.theta) * 0.12;
        sph.phi += (desiredPhi - sph.phi) * 0.12;
        sph.phi = Math.max(0.15, Math.min(Math.PI - 0.15, sph.phi));
        offset.setFromSpherical(sph);
        state.camera.position.copy(target).add(offset);
        controls.update();
        userHasInteracted = true;
      },
      handCommand(command) {
        if (!handSteeringExclusive || !controls || !state.camera || !command) return;
        if (command.type === 'look') { this.handPoint(command.x, command.y); return; }
        const T = window.THREE;
        const target = controls.target;
        if (command.type === 'orbit' && T && T.Spherical) {
          const offset = state.camera.position.clone().sub(target);
          const sph = new T.Spherical().setFromVector3(offset);
          sph.theta += command.yaw || 0;
          sph.phi = Math.max(0.15, Math.min(Math.PI - 0.15, sph.phi + (command.pitch || 0)));
          state.camera.position.copy(target).add(offset.setFromSpherical(sph));
        } else if (command.type === 'travel' && T && T.Vector3) {
          const forward = new T.Vector3();
          state.camera.getWorldDirection(forward); forward.y = 0;
          if (forward.lengthSq() > 1e-6) forward.normalize();
          const right = new T.Vector3(-forward.z, 0, forward.x);
          const delta = forward.multiplyScalar((command.forward || 0) * 0.11).add(right.multiplyScalar((command.right || 0) * 0.09));
          state.camera.position.add(delta); target.add(delta);
        } else if (command.type === 'zoom') {
          const offset = state.camera.position.clone().sub(target);
          offset.setLength(Math.max(0.35, Math.min(40, offset.length() * (1 - (command.delta || 0)))));
          state.camera.position.copy(target).add(offset);
        }
        controls.update();
        userHasInteracted = true;
      },
{$threeCameraQuadMembers}
    };
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
{$aframeModelDiagnostics}
  if (scene) installAFrameModelDiagnostics(scene);
  function startFrame(callback) {
    let count = 0;
    function tick() {
      count++;
      try { callback(count); } catch (error) { showPieceError(error); return; }
      requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  }
  if (scene && typeof window.sketch === 'function') {
    try {
      window.sketch({ AFRAME: window.AFRAME, scene, startFrame });
    } catch (artworkError) {
      // Steering/manual ownership is platform infrastructure and must still
      // initialize when artwork-authored startup reports an error.
      showPieceError(artworkError);
    }
  }
  // Exports inline media as data: URLs, so asset images can be complete
  // before the sketch attached its 'load' listeners — replay the event for
  // those (twin of replayAFrameAssetImageLoads in piece-runtime.js).
  if (scene) {
    scene.querySelectorAll('a-assets img').forEach((img) => {
      if (img.complete && img.naturalWidth > 0) {
        try { img.dispatchEvent(new Event('load')); } catch (_) {}
      }
    });
  }
  if (scene) installAFrameModelDiagnostics(scene);
  if (scene) {
    let modelDiagnosticsAttempts = 0;
    const modelDiagnosticsTimer = setInterval(() => {
      installAFrameModelDiagnostics(scene);
      modelDiagnosticsAttempts += 1;
      if (modelDiagnosticsAttempts >= 20) clearInterval(modelDiagnosticsTimer);
    }, 250);
  }
  if (scene) {
    let pointerTarget = null;
    let frameId = 0;
    let initialAFramePose = null;
    let resettingView = false;
    let handSteeringExclusive = false;
    let aframeControlsBeforeHand = [];
    let aframeLookControlsEnabledBeforeHand = null;
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
      if (handSteeringExclusive) return;
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
      if (handSteeringExclusive) return;
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
      if (handSteeringExclusive) return;
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

    // Twin of piece-runtime.js clampAFrameGestureRoam — keep in sync.
    function clampAFrameGestureRoam(cameraObject) {
      if (!initialAFramePose || !cameraObject) return;
      const origin = initialAFramePose.position;
      const dx = cameraObject.position.x - origin.x;
      const dy = cameraObject.position.y - origin.y;
      const dz = cameraObject.position.z - origin.z;
      const dist = Math.hypot(dx, dy, dz);
      const maxRoam = 24;
      if (dist > maxRoam) {
        const s = maxRoam / dist;
        cameraObject.position.set(origin.x + dx * s, origin.y + dy * s, origin.z + dz * s);
      }
    }

    function captureInitialAFramePose() {
      if (initialAFramePose) return;
      const cameraObject = getAFrameCameraMover();
      if (!cameraObject) return;
      initialAFramePose = {
        position: cameraObject.position.clone(),
        quaternion: cameraObject.quaternion.clone(),
      };
    }

    scene.addEventListener('loaded', bindAFramePointerControls, { once: true });
    scene.addEventListener('loaded', () => {
      if (window.__creatrSonicSetMover) window.__creatrSonicSetMover(getAFrameCameraMover);
    }, { once: true });
    window.__pieceHandHooks = {
      engine: 'aframe',
      setHandSteering(active) {
        const next = !!active;
        if (next === handSteeringExclusive) return true;
        const cameraEl = scene.querySelector('[camera]') || scene.querySelector('a-camera');
        const lookControls = cameraEl?.components?.['look-controls'];
        if (next) {
          captureInitialAFramePose();
          // Disable look-controls at the data level (its tick gates on
          // data.enabled) — pausing the component is timing-fragile. Twin of
          // piece-runtime.js setHandSteering; keep in sync.
          aframeLookControlsEnabledBeforeHand = lookControls ? lookControls.data.enabled !== false : null;
          if (lookControls) cameraEl.setAttribute('look-controls', 'enabled', false);
          const wasd = cameraEl?.components?.['wasd-controls'];
          aframeControlsBeforeHand = wasd ? [{ component: wasd, wasPlaying: wasd.isPlaying !== false }] : [];
          aframeControlsBeforeHand.forEach(({ component }) => component?.pause?.());
          aframeNav.pointer = null;
          aframeNav.activeTouches.clear();
          aframeNav.animFrom = aframeNav.animTo = null;
        } else {
          // Ownership handoff: seed look-controls' pitch/yaw from the
          // hand-driven pose so manual dragging resumes from it (no snap).
          const mover = getAFrameCameraMover();
          if (lookControls && mover) {
            if (lookControls.pitchObject) lookControls.pitchObject.rotation.x = mover.rotation.x;
            if (lookControls.yawObject) lookControls.yawObject.rotation.y = mover.rotation.y;
          }
          if (lookControls && aframeLookControlsEnabledBeforeHand !== null) {
            cameraEl.setAttribute('look-controls', 'enabled', aframeLookControlsEnabledBeforeHand);
          }
          aframeLookControlsEnabledBeforeHand = null;
          aframeControlsBeforeHand.forEach(({ component, wasPlaying }) => {
            if (wasPlaying) component?.play?.();
          });
          aframeControlsBeforeHand = [];
        }
        handSteeringExclusive = next;
        return true;
      },
      resetView() {
        const cameraObject = getAFrameCameraMover();
        if (!cameraObject || !initialAFramePose) return false;
        resettingView = true;
        const fromPosition = cameraObject.position.clone();
        const fromQuaternion = cameraObject.quaternion.clone();
        const started = performance.now();
        return new Promise((resolve) => {
          function step(now) {
            const t = Math.min(1, (now - started) / 360);
            const eased = 1 - Math.pow(1 - t, 3);
            cameraObject.position.lerpVectors(fromPosition, initialAFramePose.position, eased);
            cameraObject.quaternion.slerpQuaternions(fromQuaternion, initialAFramePose.quaternion, eased);
            if (t < 1) requestAnimationFrame(step);
            else { resettingView = false; resolve(true); }
          }
          requestAnimationFrame(step);
        });
      },
      handPoint(nx, ny) {
        if (resettingView || !handSteeringExclusive) return;
        const cameraObject = getAFrameCameraMover();
        if (!cameraObject) return;
        const desiredYaw = -(nx - 0.5) * Math.PI * 1.5;
        const desiredPitch = -(ny - 0.5) * Math.PI * 0.7;
        cameraObject.rotation.order = 'YXZ';
        cameraObject.rotation.y += (desiredYaw - cameraObject.rotation.y) * 0.12;
        cameraObject.rotation.x += (desiredPitch - cameraObject.rotation.x) * 0.12;
        cameraObject.rotation.x = Math.max(-Math.PI * 0.45, Math.min(Math.PI * 0.45, cameraObject.rotation.x));
      },
      handCommand(command) {
        const cameraObject = getAFrameCameraMover();
        if (resettingView || !handSteeringExclusive || !cameraObject || !command) return;
        if (command.type === 'look') { this.handPoint(command.x, command.y); return; }
        if (command.type === 'orbit') {
          cameraObject.rotation.order = 'YXZ';
          cameraObject.rotation.y += command.yaw || 0;
          cameraObject.rotation.x = Math.max(-Math.PI * 0.45, Math.min(Math.PI * 0.45, cameraObject.rotation.x + (command.pitch || 0)));
        } else if (command.type === 'travel') {
          cameraObject.translateX((command.right || 0) * 0.08);
          cameraObject.translateZ(-(command.forward || 0) * 0.11);
          clampAFrameGestureRoam(cameraObject);
        } else if (command.type === 'zoom') {
          cameraObject.translateZ((command.delta || 0) * 1.4);
          clampAFrameGestureRoam(cameraObject);
        }
      },
{$aframeCameraQuadMembers}
    };
    scene.addEventListener('renderstart', () => { bindAFramePointerControls(); captureInitialAFramePose(); }, { once: true });
    frameId = requestAnimationFrame(animateAFramePointerNavigation);
    setTimeout(bindAFramePointerControls, 250);
  }
} catch (error) { showPieceError(error); }
</script>
HTML,
        default => <<<HTML
<script>
try {
  if (typeof window.sketch === 'function') window.sketch();

  const parent = document.getElementById('canvas-container') || document.getElementById('runtime-root');
{$svgOverlayScript}
{$svgTiltScript}
} catch (error) { showPieceError(error); }
</script>
HTML,
    };

    // Splice the shared camera-quad members into the nowdoc arms (the
    // literal `{$...}` text inside them is the placeholder; the interpolating
    // three-bundle arm above already resolved its copy natively).
    return str_replace(
        ['{$threeCameraQuadMembers}', '{$aframeCameraQuadMembers}', '{$aframeModelDiagnostics}'],
        [$threeCameraQuadMembers, $aframeCameraQuadMembers, piece_export_aframe_model_diagnostics_script()],
        $bootstrap
    );
}

/**
 * A one-click local server, bundled into every export ZIP, so
 * camera-based features (hand-tracking "Steer the piece", the theremin)
 * actually work offline. Browsers permanently refuse getUserMedia() for a
 * page opened directly as file:// — no client-side code can change that,
 * it's an intentional browser security boundary (a raw local HTML file
 * must never be able to silently access a webcam). http://127.0.0.1,
 * unlike file://, IS treated as a fully secure context, identical to the
 * live site — so serving the same extracted folder locally is the actual
 * fix, not a workaround. Requires only Python 3 (bundled with macOS; a
 * common default on Linux; installable on Windows), so no extra runtime
 * dependency is added beyond what most machines already have.
 */
function piece_export_local_server_files(): array
{
    $py = <<<'PY'
#!/usr/bin/env python3
"""Serves this exported folder over http://127.0.0.1 so camera-based
features (hand-tracking steering, theremin) work. Browsers block camera
access entirely when index.html is opened directly (file://) — this is a
browser security rule, not a bug in the export — but treat 127.0.0.1 as a
secure origin exactly like the live site. Requires only Python 3."""
import http.server
import os
import socket
import threading
import webbrowser

os.chdir(os.path.dirname(os.path.abspath(__file__)))


def find_free_port():
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
        s.bind(("127.0.0.1", 0))
        return s.getsockname()[1]


port = find_free_port()
url = "http://127.0.0.1:{}/index.html".format(port)
httpd = http.server.ThreadingHTTPServer(("127.0.0.1", port), http.server.SimpleHTTPRequestHandler)

print("Serving this piece at " + url)
print("Camera-based steering now works here (it cannot work when opening index.html directly).")
print("Press Ctrl+C to stop.")

threading.Timer(0.6, lambda: webbrowser.open(url)).start()
try:
    httpd.serve_forever()
except KeyboardInterrupt:
    pass
PY;

    $command = <<<'SH'
#!/bin/bash
cd "$(dirname "$0")"
python3 start-server.py || python start-server.py
SH;

    $bat = <<<'BAT'
@echo off
cd /d "%~dp0"
python start-server.py || py start-server.py
BAT;

    return [
        ['zip_path' => 'start-server.py', 'data' => $py],
        ['zip_path' => 'start-server.command', 'data' => $command, 'mode' => 0755],
        ['zip_path' => 'start-server.bat', 'data' => $bat],
    ];
}

/**
 * Estimates a ZIP without constructing the export manifest. This function is
 * called while live pages render, so it must never load media BLOBs or build
 * the full downloadable document. The actual download still uses the full
 * manifest; this estimate intentionally trades exact compression for a safe,
 * low-memory approximation.
 */
function piece_export_estimated_zip_bytes(array $piece, array $version, array $options = []): ?int
{
    static $cache = [];
    $cacheKey = sha1(serialize([
        (int) ($piece['id'] ?? 0),
        (int) ($version['id'] ?? 0),
        $options,
    ]));
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    try {
        $version = piece_export_apply_requested_voices($version, $options['requested_voices'] ?? null);
        $excludeCamera = !empty($options['exclude_camera']);
        if ($excludeCamera) {
            $version['camera_overlay'] = 0;
            $version['immersive_camera_overlay'] = 0;
            $version['regular_hand_motion'] = 0;
            $version = piece_export_force_voice_off($version, 'hand_tracking');
        }
        if (!empty($options['exclude_hand_tracking'])) {
            $version = piece_export_force_voice_off($version, 'hand_tracking');
            $version['regular_hand_motion'] = 0;
        }

        $html = (string) ($version['html_code'] ?? '');
        $css = (string) ($version['css_code'] ?? '');
        $code = (string) ($version['generated_code'] ?? '');
        $bytes = strlen($html) + strlen($css) + strlen($code);

        // Read only metadata for referenced media. Never call the export
        // resolver here: it selects the BLOB and was the source of the live
        // page memory exhaustion regression.
        foreach (piece_export_collect_media_refs([$html, $css, $code]) as $ref) {
            $path = (string) parse_url($ref, PHP_URL_PATH);
            $id = 0;
            $table = '';
            if (preg_match('#^/(?:image|media)/([0-9]+)$#', $path, $matches)) {
                $table = 'media_files';
                $id = (int) $matches[1];
            } elseif (preg_match('#^/api/media-assets/([0-9]+)$#', $path, $matches)) {
                $table = 'media_assets';
                $id = (int) $matches[1];
            }
            if ($table !== '' && $id > 0) {
                try {
                    $stmt = db()->prepare('SELECT byte_size FROM ' . $table . ' WHERE id = ? LIMIT 1');
                    $stmt->execute([$id]);
                    $bytes += max(0, (int) ($stmt->fetchColumn() ?: 0));
                } catch (Throwable $error) {
                    // Size metadata is optional; the label can still show a
                    // useful lower-bound estimate when the table is absent.
                }
            }
        }

        $generationMode = art_piece_version_generation_mode($version, $piece);
        $decodedSonic = json_decode((string) ($version['sonic_params'] ?? ''), true);
        $decodedSonic = is_array($decodedSonic) ? $decodedSonic : ['enabled' => false];
        $surface = strtolower(trim((string) ($options['surface'] ?? '')));
        $surfaceName = $surface === 'immersive' ? 'immersive' : 'regular';
        $capabilities = piece_sound_capability_contract(
            $generationMode,
            $decodedSonic,
            piece_camera_overlay_enabled($version, $surfaceName),
            piece_camera_placement($version, $surfaceName),
            ($excludeCamera || !empty($options['exclude_hand_tracking']))
                ? false
                : ($surfaceName === 'immersive' ? true : piece_regular_hand_motion_enabled($version))
        );
        $needsMediaPipe = piece_export_version_has_hand_tracking($version) || !empty($capabilities['hand_control']);
        $runtimeFiles = $surfaceName === 'immersive'
            ? piece_export_immersive_runtime_files((string) ($version['engine'] ?? $piece['engine'] ?? 'p5'), false)
            : piece_export_runtime_files((string) ($version['engine'] ?? $piece['engine'] ?? 'p5'));
        if ($surfaceName !== 'immersive' && !empty($capabilities['hand_control']) && in_array($generationMode, ['p5', 'c2', 'c2_interactive', 'svg'], true)) {
            $runtimeFiles[] = ['zip_path' => 'runtime/three/three.global.js', 'data' => piece_export_three_global_source()];
        }
        if ($surfaceName !== 'immersive' && (piece_export_version_has_enabled_sonic($version) || !empty($capabilities['camera_view']) || !empty($capabilities['hand_control']))) {
            $runtimeFiles[] = ['source_path' => dirname(__DIR__, 2) . '/assets/vendor/tone/Tone.js', 'data' => piece_export_runtime_source_file('assets/vendor/tone/Tone.js')];
            $runtimeFiles[] = ['source_path' => dirname(__DIR__, 2) . '/assets/js/sonic-controller.js', 'data' => piece_export_runtime_source_file('assets/js/sonic-controller.js')];
        }
        foreach ($runtimeFiles as $file) {
            $bytes += array_key_exists('data', $file)
                ? strlen((string) $file['data'])
                : max(0, (int) @filesize((string) ($file['source_path'] ?? '')));
        }
        if ($needsMediaPipe) {
            foreach (piece_export_mediapipe_hands_runtime_files() as $file) {
                // Only stat these large camera assets. The live estimate must
                // never load their contents into PHP memory.
                $bytes += max(0, (int) @filesize((string) ($file['source_path'] ?? '')));
            }
        }
        foreach (piece_export_local_server_files() as $file) {
            $bytes += strlen((string) ($file['data'] ?? ''));
        }

        // Account for generated toolbar/runtime markup without constructing
        // the media-expanded document. This keeps the estimate useful while
        // keeping live rendering safely below the normal memory limit.
        $bytes += 512 * 1024;
        $cache[$cacheKey] = $bytes + 64 * 1024;
    } catch (Throwable $error) {
        $cache[$cacheKey] = null;
    }

    return $cache[$cacheKey];
}

function piece_export_format_estimated_size(?int $bytes): string
{
    if ($bytes === null || $bytes < 1) {
        return 'size varies';
    }
    $megabytes = max(1, (int) round($bytes / (1024 * 1024)));
    return '≈' . $megabytes . ' MB';
}

function piece_export_download_estimates(array $piece, array $version, string $surface = ''): array
{
    $baseOptions = $surface !== '' ? ['surface' => $surface] : [];
    $fullBytes = piece_export_estimated_zip_bytes($piece, $version, $baseOptions);
    $noCameraBytes = piece_export_estimated_zip_bytes($piece, $version, $baseOptions + ['exclude_camera' => true]);
    $sonic = json_decode((string) ($version['sonic_params'] ?? ''), true);
    $voiceCosts = [];
    if (is_array($sonic)) {
        foreach (['melodic', 'hand_tracking'] as $voice) {
            $without = $sonic;
            $without['extras']['voices'][$voice] = false;
            $voiceCosts[$voice] = max(
                0,
                strlen(json_encode($sonic, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
                    - strlen(json_encode($without, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
            );
        }
    }
    if (!empty($voiceCosts['hand_tracking'])) {
        foreach (piece_export_mediapipe_hands_runtime_files() as $file) {
            $voiceCosts['hand_tracking'] += array_key_exists('data', $file)
                ? strlen((string) $file['data'])
                : max(0, (int) @filesize((string) ($file['source_path'] ?? '')));
        }
    }

    return [
        'full' => piece_export_format_estimated_size($fullBytes),
        'no_camera' => piece_export_format_estimated_size($noCameraBytes),
        'full_bytes' => $fullBytes,
        'no_camera_bytes' => $noCameraBytes,
        'voice_costs' => $voiceCosts,
    ];
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

    foreach (piece_export_local_server_files() as $serverFile) {
        if (!$zip->addFromString($serverFile['zip_path'], $serverFile['data'])) {
            $zip->close();
            @unlink($tempPath);
            throw new RuntimeException('Could not package local-server file: ' . $serverFile['zip_path']);
        }
        if (isset($serverFile['mode']) && method_exists($zip, 'setExternalAttributesName')) {
            $zip->setExternalAttributesName(
                $serverFile['zip_path'],
                ZipArchive::OPSYS_UNIX,
                (($serverFile['mode'] & 0xFFFF) << 16)
            );
        }
    }

    $zip->close();

    return [
        'filename' => !empty($options['exclude_camera'])
            ? piece_export_basename($piece) . '-no-camera.zip'
            : piece_export_filename($piece),
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
        if (isset($bundleFile['mode']) && method_exists($zip, 'setExternalAttributesName')) {
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
    // Once at the collection root, not per-piece — one local server here
    // covers the whole extracted folder, including every pieces/{slug}/
    // subpage. See piece_export_local_server_files()'s docblock for why
    // this is needed at all (getUserMedia is permanently blocked for
    // file://, unlike http://127.0.0.1).
    $bundleFiles = array_merge($bundleFiles, piece_export_local_server_files());

    foreach ($items as $item) {
        if (($item['type'] ?? '') === 'art_piece' && !empty($item['piece']) && !empty($item['version'])) {
            $piece = $item['piece'];
            $version = $item['version'];

            // exclude_hand_tracking keeps the ~19.4MB MediaPipe payload out of
            // every piece inside a collection ZIP, even where the admin
            // enabled hand-tracking for that piece individually — see
            // piece_export_force_voice_off() in piece_export_build_manifest().
            $pieceManifest = piece_export_build_manifest($piece, $version, ['surface' => '', 'exclude_hand_tracking' => true]);
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

    $manifest = [
        'document' => collection_export_document($collection, $items, $options),
        'bundle_files' => $bundleFiles,
        'runtime_files' => collection_export_runtime_files(),
    ];

    piece_export_validate_manifest($manifest, 'collection export');

    return $manifest;
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
        . "CAMERA-BASED FEATURES (hand-tracking steering, theremin):\n"
        . "Opening index.html directly (double-clicking it) cannot use your camera — browsers\n"
        . "permanently block camera access for a raw local file, no matter what the page does.\n"
        . "To use camera features, run the included local server instead:\n"
        . "  - macOS/Linux: double-click start-server.command (or run start-server.py)\n"
        . "  - Windows: double-click start-server.bat (or run start-server.py)\n"
        . "This opens the same gallery at http://127.0.0.1 in your browser, where camera access\n"
        . "works exactly like the live site. Requires Python 3 (already installed on macOS).\n"
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
        'download_items' => null,
        'screenshot_action' => [
            'attrs' => [
                'data-immersive-download-png' => true,
                'data-download-filename' => ((function_exists('slugify') ? slugify($titleText) : '') ?: 'collection-gallery') . '.png',
            ],
        ],
        'sound_action' => $hasAnySonic ? ['enabled' => true] : null,
        'camera_view' => true,
        // Walk-the-room hand navigation — always available in the self-contained
        // download (no feature flag system offline); mirrors the live collection
        // view's hand_control / hand_control_label / dedicatedHandControl pattern.
        'hand_control' => true,
        'hand_control_label' => 'Walk the room',
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
<link rel="icon" href="data:,">
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
function isNonImpactingRuntimeIssue(error,source){const m=typeof error?.message==='string'?error.message:String(error||'');const s=String(source||error?.fileName||'');return /^(?:chrome|moz|safari)-extension:/i.test(s)||/ResizeObserver loop (?:limit exceeded|completed with undelivered notifications)/i.test(m)||/Could not establish connection\. Receiving end does not exist/i.test(m)||/A listener indicated an asynchronous response.*message channel closed/i.test(m);}
window.addEventListener('error',event=>{const e=event.error||event.message;if(!isNonImpactingRuntimeIssue(e,event.filename))showCollectionError(e);});
window.addEventListener('unhandledrejection',event=>{const r=event.reason;const m=typeof r?.message==='string'?r.message:String(r||'');if((r?.name==='AbortError'&&/worklet/i.test(m))||isNonImpactingRuntimeIssue(r)){event.preventDefault();return;}showCollectionError(r||'Unhandled promise rejection');});
</script>
<script>
{$downloadBridgeScript}
</script>
<script src="runtime/three/three.global.js"></script>
<script src="runtime/three/GLTFLoader.global.js"></script>
<script src="runtime/three/OrbitControls.global.js"></script>
<script src="runtime/three-device-orientation-controls.global.js"></script>
<script src="runtime/immersive-gallery.global.js"></script>
<script>
const { mountExhibitWall, setupImmersiveStageChrome } = window.CreatrImmersiveGallery || {};
window.__creatrSonicControllerSrc = 'runtime/sonic-controller.js';
window.__creatrToneSrc = 'runtime/tone/Tone.js';
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
  },
  getAudioController: () => viewer?.getAudioController?.(),
  getPieceInteractionController: () => viewer?.getRoomInteractionController?.(),
  cameraOverlayAllowed: true,
  // Walk-the-room: matches the live collection's handControlAllowed +
  // dedicatedHandControl options so the hand-control row appears and routes
  // through the room's silent hand engine, not the audio controller.
  handControlAllowed: true,
  dedicatedHandControl: true,
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
  const dot = filename.lastIndexOf('.');
  const stem = dot > 0 ? filename.slice(0, dot) : filename;
  const extension = dot > 0 ? filename.slice(dot) : '.png';
  const now = new Date();
  const stamp = now.getFullYear()
    + String(now.getMonth() + 1).padStart(2, '0')
    + String(now.getDate()).padStart(2, '0') + '-'
    + String(now.getHours()).padStart(2, '0')
    + String(now.getMinutes()).padStart(2, '0')
    + String(now.getSeconds()).padStart(2, '0') + '-'
    + String(now.getMilliseconds()).padStart(3, '0');
  link.download = stem + '-' + stamp + extension;
  document.body.appendChild(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}
pngBtn?.addEventListener('click', async () => {
  if (pngBtn.disabled) return;
  const labelEl = pngBtn.querySelector('span');
  const label = labelEl ? labelEl.textContent : '';
  const originalAriaLabel = pngBtn.getAttribute('aria-label') || 'Take screenshot';
  pngBtn.disabled = true;
  pngBtn.setAttribute('aria-busy', 'true');
  pngBtn.setAttribute('aria-label', 'Preparing PNG...');
  if (labelEl) {
    labelEl.textContent = 'Preparing PNG...';
  }
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
    pngBtn.removeAttribute('aria-busy');
    pngBtn.setAttribute('aria-label', originalAriaLabel);
    if (labelEl) {
      labelEl.textContent = label;
    }
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
    // allowLargeInline: this payload is embedded into the downloadable
    // collection ZIP's self-contained index.html (collection_export_document()
    // — confirmed the only caller), opened via file://, same as a single-piece
    // export — never a live page. See piece_media_should_skip_inlining()'s
    // docblock for why exports need this and live views must not.
    $mediaMap = piece_build_media_manifest([$htmlCode, $cssCode, $jsCode, (string) ($piece['thumbnail_url'] ?? '')], true);
    $rewriteMedia = static function (string $content) use ($mediaMap): string {
        return piece_export_rewrite_media_refs($content, static function (string $normalizedRef) use ($mediaMap): ?string {
            $asset = $mediaMap[$normalizedRef] ?? null;
            return is_array($asset) ? piece_export_asset_replacement($asset, $normalizedRef) : null;
        });
    };

    $engine = strtolower((string) ($version['engine'] ?? $piece['engine'] ?? 'p5'));
    $html = $rewriteMedia($htmlCode);
    if ($engine === 'aframe') {
        $html = piece_aframe_normalize_texture_assets($html, static function (string $src) use ($mediaMap): string {
            $asset = $mediaMap[$src] ?? null;
            return is_array($asset) ? piece_export_asset_replacement($asset, $src) : $src;
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
        'sonicParams' => ($sonicParamsDecoded = !empty($version['sonic_params']) ? json_decode((string) $version['sonic_params'], true) : null) && ($sonicParamsDecoded['enabled'] ?? true) !== false ? $sonicParamsDecoded : null,
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

// Media whose kind/size makes data: URI inlining unsafe in a LIVE request
// context. Inlining exists ONLY for gallery-wall/SVG rasterization through
// an <img> tag (see piece_inline_local_media()'s docblock) — that rationale
// never applied to 3D models (THREE.GLTFLoader fetches the URL directly, no
// <img> step exists) and base64-encoding a large binary blob unconditionally,
// as this used to do, reliably exhausts PHP's memory_limit for any sizeable
// file. Confirmed in production: an 18.6MB GLB crashed
// immersive/piece.php's json_encode() with a raw, unhandled fatal error
// dumped mid-script, blanking the entire piece for every visitor.
//
// A downloaded/exported bundle is a DIFFERENT context: opened via file://,
// where fetch()/XHR to any local file — even a relative, co-bundled one —
// is blocked by the browser's CORS policy regardless of same-directory
// placement (confirmed: relative-path fix alone did not fix the downloaded
// ZIP). A data: URI is the only thing that loads there at all, so exports
// must inline 3D models despite the size, and the download/export request
// handlers (PiecesController::download(), CollectionsController's export
// route) raise PHP's memory_limit for just that one request to afford it —
// see $allowLargeInline below.
const PIECE_MEDIA_INLINE_MAX_BYTES = 4 * 1024 * 1024;

// Sanity ceiling even when $allowLargeInline is true — matches
// MODEL_MAX_BYTES (upload.php), the largest a model upload could ever be, so
// this never silently truncates a legitimately-uploaded asset but still
// won't attempt something absurd if that ceiling ever changes.
const PIECE_MEDIA_EXPORT_INLINE_MAX_BYTES = 64 * 1024 * 1024;

function piece_media_should_skip_inlining(array $asset, bool $allowLargeInline = false): bool
{
    $size = strlen((string) ($asset['data'] ?? ''));
    if ($allowLargeInline) {
        return $size > PIECE_MEDIA_EXPORT_INLINE_MAX_BYTES;
    }

    $mimeType = strtolower((string) ($asset['mime_type'] ?? ''));
    if ($mimeType === 'model/gltf-binary' || $mimeType === 'model/gltf+json') {
        return true;
    }

    return $size > PIECE_MEDIA_INLINE_MAX_BYTES;
}

function piece_build_media_manifest(array $contents, bool $allowLargeInline = false): array
{
    $mediaRefs = piece_export_collect_media_refs($contents);
    $mediaMap = [];
    foreach ($mediaRefs as $ref) {
        $asset = piece_export_resolve_media_ref($ref);
        piece_export_validate_media_payload($ref, $asset);
        $mediaMap[$ref] = [
            'path' => piece_export_media_zip_path($ref, $asset, array_column($mediaMap, 'path')),
            'data_url' => piece_media_should_skip_inlining($asset, $allowLargeInline)
                ? ''
                : piece_export_data_url((string) ($asset['mime_type'] ?? 'application/octet-stream'), (string) ($asset['data'] ?? '')),
        ];
    }

    return $mediaMap;
}

function piece_export_validate_media_payload(string $ref, array $asset): void
{
    $mimeType = strtolower(trim((string) ($asset['mime_type'] ?? '')));
    $data = (string) ($asset['data'] ?? '');
    if ($data === '') {
        throw new RuntimeException('Referenced media file is empty: ' . $ref);
    }
    if ($mimeType === 'model/gltf-binary' && substr($data, 0, 4) !== 'glTF') {
        throw new RuntimeException('Referenced GLB does not have a valid glTF binary header: ' . $ref);
    }
    if ($mimeType === 'model/gltf+json') {
        json_decode($data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Referenced GLTF JSON is invalid: ' . $ref);
        }
    }
}

/**
 * Narrows a version's sonic_params.extras.voices for a SPECIFIC export
 * request, without touching the database or the passed-in $version outside
 * this function's own return value. $requestedCsv is an optional
 * downloader-supplied comma list (e.g. "melodic,hand_tracking") of which
 * admin-ALLOWED optional voices to actually include in this particular
 * download — the admin's per-piece config is always a ceiling; this can
 * only narrow it, never expand it. `ambient`/`movement` always pass through
 * unchanged (no downloader-facing toggle exists for those). When
 * $requestedCsv is null (no downloader input at all — e.g. a
 * collection-internal call, or an old/external link with no query param),
 * the admin's config is used as-is, exactly like before this feature existed.
 */
function piece_export_apply_requested_voices(array $version, ?string $requestedCsv): array
{
    if ($requestedCsv === null) {
        return $version;
    }
    $raw = trim((string) ($version['sonic_params'] ?? ''));
    if ($raw === '') {
        return $version;
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return $version;
    }
    $adminVoices = is_array($decoded['extras']['voices'] ?? null) ? $decoded['extras']['voices'] : [];
    $requested = array_map('trim', explode(',', $requestedCsv));
    $decoded['extras']['voices']['melodic'] = ($adminVoices['melodic'] ?? true) !== false && in_array('melodic', $requested, true);
    $decoded['extras']['voices']['hand_tracking'] = !empty($adminVoices['hand_tracking']) && in_array('hand_tracking', $requested, true);
    $version['sonic_params'] = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return $version;
}

/**
 * Forces a single sonic_params.extras.voices.* key off for this specific
 * export, without touching the database. Narrower than
 * piece_export_apply_requested_voices() (which recomputes every
 * downloader-choosable voice) — used only to exclude hand-tracking from
 * collection exports regardless of the admin's per-piece config, leaving
 * every other voice (including keyboard) untouched.
 */
function piece_export_force_voice_off(array $version, string $voiceKey): array
{
    $raw = trim((string) ($version['sonic_params'] ?? ''));
    if ($raw === '') {
        return $version;
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return $version;
    }
    $decoded['extras']['voices'][$voiceKey] = false;
    $version['sonic_params'] = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return $version;
}

function piece_export_build_manifest(array $piece, array $version, array $options = []): array
{
    // Applied once, up top: every downstream read of $version['sonic_params']
    // in this function (document building, hand-tracking bundling gate) then
    // automatically respects the downloader's choice with no further changes,
    // since it's the same version array they'd otherwise have used unmodified.
    $version = piece_export_apply_requested_voices($version, $options['requested_voices'] ?? null);
    $excludeCamera = !empty($options['exclude_camera']);
    if ($excludeCamera) {
        $version['camera_overlay'] = 0;
        $version['immersive_camera_overlay'] = 0;
        $version['regular_hand_motion'] = 0;
        $version = piece_export_force_voice_off($version, 'hand_tracking');
    }
    // Collections pass this to keep hand-tracking out of every per-piece
    // export inside a collection ZIP regardless of the admin's config or any
    // downloader choice — see collection_export_build_manifest() below. This
    // is what keeps a collection's total size bounded no matter how many
    // hand-tracking-enabled pieces it contains.
    if (!empty($options['exclude_hand_tracking'])) {
        $version = piece_export_force_voice_off($version, 'hand_tracking');
        $version['regular_hand_motion'] = 0;
    }

    $htmlCode = (string) ($version['html_code'] ?? '');
    $cssCode = (string) ($version['css_code'] ?? '');
    $jsCode = (string) ($version['generated_code'] ?? '');

    // allowLargeInline: this manifest feeds a downloadable ZIP (index.html
    // opened via file://), which can only load large binaries — 3D models
    // in particular — as an inline data: URI; relative co-bundled file
    // paths alone still fail there since fetch()/XHR to any file:// target
    // is blocked by the browser regardless of same-directory placement. The
    // caller (PiecesController::download()) raises memory_limit for this
    // one request to afford it. Never pass true for a live-request
    // manifest — see piece_media_should_skip_inlining()'s docblock.
    $mediaMap = piece_build_media_manifest([$htmlCode, $cssCode, $jsCode], true);
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
    $generationMode = art_piece_version_generation_mode($version, $piece);
    $decodedSonic = json_decode((string) ($version['sonic_params'] ?? ''), true);
    if (!is_array($decodedSonic)) {
        $decodedSonic = ['enabled' => false];
    }
    $surfaceName = $immersive ? 'immersive' : 'regular';
    $capabilities = piece_sound_capability_contract(
        $generationMode,
        $decodedSonic,
        piece_camera_overlay_enabled($version, $surfaceName),
        piece_camera_placement($version, $surfaceName),
        ($excludeCamera || !empty($options['exclude_hand_tracking']))
            ? false
            : ($immersive ? true : piece_regular_hand_motion_enabled($version))
    );
    $cameraView = !empty($capabilities['camera_view']);
    // MediaPipe is needed by BOTH camera-based capabilities: the theremin
    // (hand_tracking voice) and hand control (camera steering) — which the
    // contract can grant via the camera permission on sound-less pieces.
    // The device-tilt fallback needs no assets; the primary path does.
    $needsMediaPipe = piece_export_version_has_hand_tracking($version) || !empty($capabilities['hand_control']);

    $runtimeFiles = $immersive
        ? piece_export_immersive_runtime_files((string) ($version['engine'] ?? $piece['engine'] ?? 'p5'), $needsMediaPipe)
        : piece_export_runtime_files((string) ($version['engine'] ?? $piece['engine'] ?? 'p5'));
    if (!$immersive && !empty($capabilities['hand_control']) && in_array($generationMode, ['p5', 'c2', 'c2_interactive', 'svg'], true)) {
        $runtimeFiles[] = ['zip_path' => 'runtime/three/three.global.js', 'data' => piece_export_three_global_source()];
    }

    if (!$immersive && (piece_export_version_has_enabled_sonic($version) || $cameraView || !empty($capabilities['hand_control']))) {
        $runtimeFiles[] = [
            'source_path' => dirname(__DIR__, 2) . '/assets/vendor/tone/Tone.js',
            'zip_path' => 'runtime/tone/Tone.js',
            'data' => piece_export_runtime_source_file('assets/vendor/tone/Tone.js'),
        ];
        $runtimeFiles[] = [
            'source_path' => dirname(__DIR__, 2) . '/assets/js/sonic-controller.js',
            'zip_path' => 'runtime/sonic-controller.js',
            'data' => piece_export_runtime_source_file('assets/js/sonic-controller.js'),
        ];
        // Bundled for the theremin (hand_tracking voice) AND for hand
        // control (camera steering), which the contract can grant without
        // any sound design. Collections force the hand-tracking voice off
        // via 'exclude_hand_tracking' (see collection_export_build_manifest())
        // to keep collection ZIP sizes bounded; single-piece ZIPs accept the
        // model whenever either capability is offered.
        if ($needsMediaPipe) {
            $runtimeFiles = array_merge($runtimeFiles, piece_export_mediapipe_hands_runtime_files());
        }
    }

    $manifest = [
        'document' => $immersive
            ? piece_export_immersive_document($piece, $version, [
                'media_map' => $mediaMap,
                'embed_media' => true,
                'view_state' => $viewState,
                'hand_motion' => !$excludeCamera,
            ])
            : piece_export_document($piece, $version, [
                'runtime_mode' => 'bundle',
                'media_map' => $mediaMap,
                'embed_media' => true,
                'css_href' => 'styles/piece.css',
                'script_src' => 'scripts/piece.js',
            ]),
        'bundle_files' => $bundleFiles,
        'runtime_files' => $runtimeFiles,
        'media_files' => $mediaFiles,
    ];

    piece_export_validate_manifest(
        $manifest,
        'piece export ' . (int) ($piece['id'] ?? 0)
    );

    return $manifest;
}

/**
 * Fail-closed validation for the final artifact manifest. This deliberately
 * validates emitted files rather than only stored piece code: runtime source
 * transforms and collection nesting happen after authored-code preflight and
 * can otherwise introduce download-only failures.
 */
function piece_export_validate_manifest(array $manifest, string $label = 'piece export'): void
{
    $document = (string) ($manifest['document'] ?? '');
    if (trim($document) === '') {
        throw new RuntimeException("Invalid {$label}: index.html is empty.");
    }
    piece_export_assert_no_empty_resource_references($document, "{$label} index.html", 'html');

    foreach (['bundle_files', 'runtime_files'] as $group) {
        foreach (($manifest[$group] ?? []) as $file) {
            $zipPath = ltrim((string) ($file['zip_path'] ?? ''), '/');
            if ($zipPath === '') {
                throw new RuntimeException("Invalid {$label}: a packaged file has no ZIP path.");
            }

            $extension = strtolower((string) pathinfo($zipPath, PATHINFO_EXTENSION));
            $isScript = in_array($extension, ['js', 'mjs'], true);
            $isHtml = in_array($extension, ['html', 'htm'], true);
            $isCss = $extension === 'css';
            if (!$isScript && !$isHtml && !$isCss) {
                continue;
            }

            if (array_key_exists('data', $file)) {
                $data = (string) $file['data'];
            } else {
                $sourcePath = (string) ($file['source_path'] ?? '');
                $data = $sourcePath !== '' ? (string) @file_get_contents($sourcePath) : '';
            }
            if (trim($data) === '') {
                throw new RuntimeException("Invalid {$label}: packaged {$zipPath} is empty.");
            }

            if ($isHtml) {
                piece_export_assert_no_empty_resource_references($data, "{$label} {$zipPath}", 'html');
            } elseif ($isCss) {
                piece_export_assert_no_empty_resource_references($data, "{$label} {$zipPath}", 'css');
            } elseif (piece_export_runtime_is_classic_script($zipPath)) {
                piece_export_assert_no_module_syntax($data, $zipPath);
            }
        }
    }
}

function piece_export_runtime_is_classic_script(string $zipPath): bool
{
    $normalized = strtolower(str_replace('\\', '/', $zipPath));
    if (str_ends_with($normalized, '.mjs')) {
        return false;
    }

    foreach ([
        '/three/three.module.js',
        '/three/gltfloader.js',
        '/three/utils/buffergeometryutils.js',
        '/three/addons/controls/orbitcontrols.js',
        '/immersive-gallery.js',
        '/three-device-orientation-controls.js',
        '/mediapipe-hands/vision_wasm_internal.js',
    ] as $moduleSuffix) {
        if (str_ends_with($normalized, $moduleSuffix)) {
            return false;
        }
    }

    return str_ends_with($normalized, '.js');
}

function piece_export_assert_no_empty_resource_references(string $source, string $label, string $type): void
{
    if ($type === 'html' && preg_match('/\b(?:src|href|xlink:href)\s*=\s*(["\'])\s*\1/i', $source)) {
        throw new RuntimeException("Invalid {$label}: empty resource URL attributes are not allowed.");
    }

    if ($type === 'css' && (
        preg_match('/\burl\s*\(\s*\)/i', $source)
        || preg_match('/\burl\s*\(\s*(["\'])\s*\1\s*\)/i', $source)
    )) {
        throw new RuntimeException("Invalid {$label}: empty CSS url() references are not allowed.");
    }
}

function piece_export_version_has_enabled_sonic(array $version): bool
{
    $raw = trim((string) ($version['sonic_params'] ?? ''));
    if ($raw === '') {
        return false;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) && ($decoded['enabled'] ?? true) !== false;
}

/**
 * Whether this version's hand-tracking voice is enabled — gates bundling the
 * ~19MB self-hosted MediaPipe HandLandmarker assets into the export, so an
 * export for a piece that doesn't use hand-tracking isn't bloated by it.
 */
function piece_export_version_has_hand_tracking(array $version): bool
{
    if (!piece_export_version_has_enabled_sonic($version)) {
        return false;
    }
    $decoded = json_decode((string) ($version['sonic_params'] ?? ''), true);
    return is_array($decoded) && !empty($decoded['extras']['voices']['hand_tracking']);
}

/**
 * The self-hosted MediaPipe HandLandmarker assets (~19.4MB) for camera
 * hand-tracking — vendored under public/assets/vendor/mediapipe-hands/,
 * bundled into an export only when that piece's hand-tracking voice is
 * enabled (see piece_export_version_has_hand_tracking()).
 */
function piece_export_mediapipe_hands_runtime_files(): array
{
    $vendorRoot = dirname(__DIR__, 2) . '/assets/vendor/mediapipe-hands';
    return [
        [
            'source_path' => $vendorRoot . '/vision_bundle.mjs',
            'zip_path' => 'runtime/mediapipe-hands/vision_bundle.mjs',
            'data' => piece_export_runtime_source_file('assets/vendor/mediapipe-hands/vision_bundle.mjs'),
        ],
        [
            'source_path' => $vendorRoot . '/vision_wasm_internal.js',
            'zip_path' => 'runtime/mediapipe-hands/vision_wasm_internal.js',
            'data' => piece_export_runtime_source_file('assets/vendor/mediapipe-hands/vision_wasm_internal.js'),
        ],
        ['source_path' => $vendorRoot . '/vision_wasm_internal.wasm', 'zip_path' => 'runtime/mediapipe-hands/vision_wasm_internal.wasm'],
        ['source_path' => $vendorRoot . '/hand_landmarker.task', 'zip_path' => 'runtime/mediapipe-hands/hand_landmarker.task'],
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

            return piece_export_asset_replacement($target, $normalizedRef);
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

            return piece_export_asset_replacement($target, $normalizedRef);
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
        . "CAMERA-BASED FEATURES (hand-tracking steering, theremin):\n"
        . "Opening index.html directly (double-clicking it) cannot use your camera — browsers\n"
        . "permanently block camera access for a raw local file, no matter what the page does.\n"
        . "To use camera features, run the included local server instead:\n"
        . "  - macOS/Linux: double-click start-server.command (or run start-server.py)\n"
        . "  - Windows: double-click start-server.bat (or run start-server.py)\n"
        . "This opens the same piece at http://127.0.0.1 in your browser, where camera access\n"
        . "works exactly like the live site. Requires Python 3 (already installed on macOS).\n"
        . "\n"
        . "Please note that the following server-dependent features require the CMS and cannot run offline:\n"
        . "- Re-downloading the ZIP bundle with different voice options.\n"
        . "- Interactive comments, version history, prompt metadata, and edit tools.\n"
        . "- Direct links to the immersive VR gallery view (the immersive view has its own standalone export structure instead).\n"
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
        'model/gltf-binary' => 'glb',
        'model/gltf+json' => 'gltf',
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
            [
                'source_path' => $vendorRoot . '/p5/p5.min.js',
                'zip_path' => 'runtime/p5/p5.min.js',
                'data' => piece_export_runtime_source_file('assets/vendor/piece-runtime/p5/p5.min.js'),
            ],
        ],
        'c2' => [
            [
                'source_path' => $vendorRoot . '/c2/c2.min.js',
                'zip_path' => 'runtime/c2/c2.min.js',
                'data' => piece_export_runtime_source_file('assets/vendor/piece-runtime/c2/c2.min.js'),
            ],
        ],
        'three' => [
            ['zip_path' => 'runtime/three/three.global.js', 'data' => piece_export_three_global_source()],
            ['zip_path' => 'runtime/three/GLTFLoader.global.js', 'data' => piece_export_gltfloader_global_source()],
            ['zip_path' => 'runtime/three/OrbitControls.global.js', 'data' => piece_export_orbitcontrols_global_source()],
        ],
        'aframe' => [
            [
                'source_path' => $publicRoot . '/assets/js/aframe.min.js',
                'zip_path' => 'runtime/aframe/aframe.min.js',
                'data' => piece_export_runtime_source_file('assets/js/aframe.min.js'),
            ],
            [
                'source_path' => $publicRoot . '/assets/js/aframe-model-runtime.js',
                'zip_path' => 'runtime/aframe-model-runtime.js',
                'data' => piece_export_runtime_source_file('assets/js/aframe-model-runtime.js'),
            ],
        ],
        default => [],
    };

    foreach ($runtimeFiles as $runtimeFile) {
        if (isset($runtimeFile['source_path']) && !is_file($runtimeFile['source_path'])) {
            throw new RuntimeException('Missing vendored runtime file for piece export: ' . $runtimeFile['source_path']);
        }
    }

    return $runtimeFiles;
}

function piece_export_immersive_runtime_files(string $engine, bool $handTrackingEnabled = false): array
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
            'zip_path' => 'runtime/immersive-gallery.global.js',
            'data' => piece_export_immersive_gallery_global_source(),
        ],
        [
            'zip_path' => 'runtime/three-device-orientation-controls.js',
            'data' => piece_export_patched_device_orientation_source(),
        ],
        [
            'zip_path' => 'runtime/three-device-orientation-controls.global.js',
            'data' => piece_export_device_orientation_global_source(),
        ],
        [
            'source_path' => $vendorRoot . '/three/three.module.js',
            'zip_path' => 'runtime/three/three.module.js',
            'data' => piece_export_runtime_source_file('assets/vendor/piece-runtime/three/three.module.js'),
        ],
        ['zip_path' => 'runtime/three/three.global.js', 'data' => piece_export_three_global_source()],
        [
            'source_path' => $vendorRoot . '/three/GLTFLoader.js',
            'zip_path' => 'runtime/three/GLTFLoader.js',
            'data' => piece_export_runtime_source_file('assets/vendor/piece-runtime/three/GLTFLoader.js'),
        ],
        [
            'source_path' => $vendorRoot . '/three/utils/BufferGeometryUtils.js',
            'zip_path' => 'runtime/three/utils/BufferGeometryUtils.js',
            'data' => piece_export_runtime_source_file('assets/vendor/piece-runtime/three/utils/BufferGeometryUtils.js'),
        ],
        ['zip_path' => 'runtime/three/GLTFLoader.global.js', 'data' => piece_export_gltfloader_global_source()],
        [
            'zip_path' => 'runtime/three/addons/controls/OrbitControls.js',
            'data' => piece_export_patched_orbitcontrols_source(),
        ],
        [
            'zip_path' => 'runtime/three/OrbitControls.global.js',
            'data' => piece_export_orbitcontrols_global_source(),
        ],
        // Sonification applies to every engine in the immersive view (not
        // just three/aframe), so Tone.js and the shared sonic-controller
        // engine are bundled unconditionally here, same as three.module.js
        // above. sonic-controller.js needs no source patching (unlike
        // immersive-gallery.js above) — its Tone.js/self src are resolved via
        // window.__creatrToneSrc/__creatrSonicControllerSrc, set by the
        // bootstrap script before mounting.
        [
            'source_path' => $publicRoot . '/assets/vendor/tone/Tone.js',
            'zip_path' => 'runtime/tone/Tone.js',
            'data' => piece_export_runtime_source_file('assets/vendor/tone/Tone.js'),
        ],
        [
            'source_path' => $publicRoot . '/assets/js/sonic-controller.js',
            'zip_path' => 'runtime/sonic-controller.js',
            'data' => piece_export_runtime_source_file('assets/js/sonic-controller.js'),
        ],
    ];

    if ($handTrackingEnabled) {
        $runtimeFiles = array_merge($runtimeFiles, piece_export_mediapipe_hands_runtime_files());
    }

    if ($engine === 'p5') {
        $runtimeFiles[] = [
            'source_path' => $vendorRoot . '/p5/p5.min.js',
            'zip_path' => 'runtime/p5/p5.min.js',
            'data' => piece_export_runtime_source_file('assets/vendor/piece-runtime/p5/p5.min.js'),
        ];
    } elseif ($engine === 'c2') {
        $runtimeFiles[] = [
            'source_path' => $vendorRoot . '/c2/c2.min.js',
            'zip_path' => 'runtime/c2/c2.min.js',
            'data' => piece_export_runtime_source_file('assets/vendor/piece-runtime/c2/c2.min.js'),
        ];
    } elseif ($engine === 'aframe') {
        $runtimeFiles[] = [
            'source_path' => $publicRoot . '/assets/js/aframe.min.js',
            'zip_path' => 'runtime/aframe/aframe.min.js',
            'data' => piece_export_runtime_source_file('assets/js/aframe.min.js'),
        ];
        $runtimeFiles[] = [
            'source_path' => $publicRoot . '/assets/js/aframe-model-runtime.js',
            'zip_path' => 'runtime/aframe-model-runtime.js',
            'data' => piece_export_runtime_source_file('assets/js/aframe-model-runtime.js'),
        ];
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
        "({ GLTFLoader: _GLTFLoaderCtor } = await import('https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/loaders/GLTFLoader.js'));" => "({ GLTFLoader: _GLTFLoaderCtor } = await import('./three/GLTFLoader.js'));",
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

  function isIPhoneWebKitBrowser() {
    if (typeof navigator === 'undefined') return false;
    const ua = navigator.userAgent || '';
    const maxTouchPoints = navigator.maxTouchPoints || 0;
    const isIPad = /\biPad\b/i.test(ua) || (/\bMacintosh\b/i.test(ua) && maxTouchPoints > 1);
    return /\biPhone\b/i.test(ua) && /AppleWebKit/i.test(ua) && !isIPad;
  }

  if (fullscreenButton) {
    function toggleCssFullscreen(forceState) {
      const active = typeof forceState === 'boolean' ? forceState : !document.documentElement.classList.contains('piece-export-fullscreen-active');
      document.documentElement.classList.toggle('piece-export-fullscreen-active', active);
      fullscreenButton.setAttribute('aria-label', active ? 'Exit fullscreen' : 'Enter fullscreen');
      window.dispatchEvent(new Event('resize'));
    }

    fullscreenButton.addEventListener('click', async function () {
      try {
        if (document.documentElement.classList.contains('piece-export-fullscreen-active')) {
          toggleCssFullscreen(false);
          return;
        }
        if (document.fullscreenElement) {
          await document.exitFullscreen();
          return;
        }
        if (isIPhoneWebKitBrowser()) {
          toggleCssFullscreen(true);
          return;
        }
        const target = document.documentElement;
        if (typeof target.requestFullscreen === 'function') {
          await target.requestFullscreen();
          return;
        }
        toggleCssFullscreen(true);
      } catch (error) {
        toggleCssFullscreen(true);
      }
    });

    document.addEventListener('fullscreenchange', function () {
      const isFs = Boolean(document.fullscreenElement);
      fullscreenButton.setAttribute('aria-label', isFs ? 'Exit fullscreen' : 'Enter fullscreen');
      if (!isFs) {
        document.documentElement.classList.remove('piece-export-fullscreen-active');
      }
      window.dispatchEvent(new Event('resize'));
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && document.documentElement.classList.contains('piece-export-fullscreen-active')) {
        if (document.fullscreenElement) {
          document.exitFullscreen().catch(() => {});
        } else {
          toggleCssFullscreen(false);
        }
      }
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
    if (typeof window.__creatrComposeCapture === 'function') {
      surface = await window.__creatrComposeCapture(surface);
    }
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
      if (surface.type === 'svg') {
        let canvas = await exportSvgSurface(surface.node);
        if (typeof window.__creatrComposeCapture === 'function') {
          canvas = await window.__creatrComposeCapture(canvas);
        }
        return canvas;
      }
      return exportSurfaceWithValidation(surface.node);
    }
  };

  function downloadBlob(blob) {
    const objectUrl = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = objectUrl;
    const dot = filename.lastIndexOf('.');
    const stem = dot > 0 ? filename.slice(0, dot) : filename;
    const extension = dot > 0 ? filename.slice(dot) : '.png';
    const now = new Date();
    const stamp = now.getFullYear()
      + String(now.getMonth() + 1).padStart(2, '0')
      + String(now.getDate()).padStart(2, '0') + '-'
      + String(now.getHours()).padStart(2, '0')
      + String(now.getMinutes()).padStart(2, '0')
      + String(now.getSeconds()).padStart(2, '0') + '-'
      + String(now.getMilliseconds()).padStart(3, '0');
    link.download = stem + '-' + stamp + extension;
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
        let exportCanvas = surface.type === 'svg'
          ? await exportSvgSurface(surface.node)
          : await exportSurfaceWithValidation(surface.node);
        if (surface.type === 'svg' && typeof window.__creatrComposeCapture === 'function') {
          exportCanvas = await window.__creatrComposeCapture(exportCanvas);
        }
        const blob = await canvasToBlob(exportCanvas);
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
