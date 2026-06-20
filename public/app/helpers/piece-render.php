<?php

declare(strict_types=1);

function piece_render_document(array $piece, array $version): string
{
    $title = htmlspecialchars((string) ($piece['title'] ?? 'Art piece'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $engine = strtolower((string) ($version['engine'] ?? $piece['engine'] ?? 'p5'));
    $html = (string) ($version['html_code'] ?? '');
    $css = (string) ($version['css_code'] ?? '');
    $code = (string) ($version['generated_code'] ?? '');
    $jsonCode = json_encode($code, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $jsonEngine = json_encode($engine);
    $jsonHtml = json_encode($html, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $jsonCss = json_encode($css, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $origin = function_exists('seo_origin') ? seo_origin() : '';
    $baseTag = $origin ? '<base href="' . htmlspecialchars(rtrim($origin, '/') . '/', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '">' : '';
    // Our own runtime script must load from wherever THIS request is
    // actually being served — never from $origin/seo_origin() above (the
    // site's configured canonical URL, which can differ from the actual
    // host in local/dev), and never from window.location.origin at runtime
    // either: this document is frequently embedded via <iframe srcdoc>
    // (piece_render_iframe() below), and srcdoc documents get an opaque
    // origin — window.location.origin literally evaluates to the string
    // "null" in that context, even with sandbox="allow-same-origin".
    // Computing it server-side from the actual request avoids both traps.
    $requestScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $requestHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $runtimeScriptUrl = htmlspecialchars($requestScheme . '://' . $requestHost . '/assets/js/piece-runtime.js', ENT_QUOTES | ENT_HTML5, 'UTF-8');

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
</script>
<script src="{$runtimeScriptUrl}"></script>
</body>
</html>
HTML;
}

function piece_render_iframe(array $piece, array $version, int $height = 520): string
{
    $srcdoc = htmlspecialchars(piece_render_document($piece, $version), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $title = htmlspecialchars((string) ($piece['title'] ?? 'Art piece'), ENT_QUOTES, 'UTF-8');
    return '<iframe srcdoc="' . $srcdoc . '" style="width:100%;height:' . $height . 'px;border:0;display:block;" sandbox="allow-scripts allow-same-origin" title="' . $title . '"></iframe>';
}
