(function () {
    'use strict';

    function seededRandom(seed) {
        var state = (Number(seed) || 1) >>> 0;
        return function () {
            state += 0x6D2B79F5;
            var t = state;
            t = Math.imul(t ^ (t >>> 15), t | 1);
            t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
            return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
        };
    }

    function renderDocument(source) {
        var title = source.title || 'Art piece';
        var engine = source.engine || 'p5';
        var html = source.html || '';
        var css = source.css || '';
        var js = source.js || '';
        var runtimeOrigin = source.runtimeOrigin || window.location.origin;
        var preserve = source.preserveDrawingBuffer !== false;
        var randomSeed = source.seed || 123456789;
        // Optional — lets piece-runtime.js's existing sound controller (idle
        // pattern, movement modulation, mute toggle) activate in this preview
        // the same way it does everywhere else, with no new audio code here.
        var sonicParams = (source.sonicParams && typeof source.sonicParams === 'object') ? source.sonicParams : null;
        var pieceContextScript = sonicParams
            ? 'window.CREATR_PIECE_CONTEXT = { sonic: ' + JSON.stringify(sonicParams) + ', c2Interactive: ' + JSON.stringify(!!source.c2Interactive) + ' };\n'
            : '';
        var aframeCss = engine === 'aframe'
            ? 'a-scene{display:block;width:100%;height:100%;}\n.a-canvas{display:block;width:100%!important;height:100%!important;}\n'
            : '';

        var shimScript = '';
        if (source.isCapture) {
            shimScript = '\n<script>\n(function() {\n  var activeTimers = {};\n  var timerId = 0;\n  var shimTicks = 0;\n  function diagPost(label, data) {\n    try { window.parent.postMessage({ type: "creatr-diag", label: label, data: data || null, t: performance.now() }, "*"); } catch (e) {}\n  }\n  diagPost("raf-shim-installed");\n  window.requestAnimationFrame = function(callback) {\n    var id = ++timerId;\n    activeTimers[id] = setTimeout(function() {\n      delete activeTimers[id];\n      shimTicks++;\n      if (shimTicks <= 5) diagPost("raf-shim-tick", { count: shimTicks });\n      callback(typeof performance !== "undefined" && performance.now ? performance.now() : Date.now());\n    }, 16);\n    return id;\n  };\n  window.cancelAnimationFrame = function(id) {\n    if (activeTimers[id] !== undefined) {\n      clearTimeout(activeTimers[id]);\n      delete activeTimers[id];\n    }\n  };\n})();\n<\/script>\n';
        }

        return '<!DOCTYPE html>\n<html lang="en">\n<head>\n<meta charset="utf-8">\n<meta name="viewport" content="width=device-width, initial-scale=1">\n<title>' + escapeHtml(title) + '</title>' + shimScript + '\n<script type="importmap">\n{\n  "imports": {\n    "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js",\n    "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"\n  }\n}\n<\/script>\n<style>\nhtml,body{margin:0;padding:0;width:100%;height:100%;overflow:hidden;background:#0d0d0f;color:#fff;}\nbody{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}\n#runtime-root{width:100vw;height:100vh;overflow:hidden;}\n#piece-error{position:fixed;inset:auto 1rem 1rem 1rem;z-index:9999;padding:1rem;border:1px solid #fca5a5;background:#450a0a;color:#fee2e2;font:14px/1.4 ui-monospace,SFMono-Regular,Menlo,monospace;white-space:pre-wrap;display:none;}\ncanvas{display:block;width:100%;height:100%;}\n' + aframeCss + css + '\n</style>\n</head>\n<body>\n<div id="runtime-root">' + html + '</div>\n<div id="piece-error" role="alert"></div>\n<script>\n(function(){\n  var state = ' + JSON.stringify(randomSeed >>> 0) + ';\n  Math.random = function(){\n    state += 0x6D2B79F5;\n    var t = state;\n    t = Math.imul(t ^ (t >>> 15), t | 1);\n    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);\n    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;\n  };\n})();\n' + pieceContextScript + 'const PIECE_ENGINE = ' + JSON.stringify(engine) + ';\nconst PIECE_CODE = ' + JSON.stringify(js) + ';\nconst PIECE_PRESERVE_DRAWING_BUFFER = ' + JSON.stringify(!!preserve) + ';\n<\/script>\n<script src="' + runtimeOrigin + '/assets/js/piece-runtime.js?v=' + Date.now() + '"><\/script>\n</body>\n</html>';
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function normalizeCmsMediaPath(src) {
        if (typeof src !== 'string' || src === '') return '';
        if (/^\/(?:image\/[0-9]+|api\/media-assets\/[0-9]+|media\/[A-Za-z0-9._~/%+-]+)(?:\?[A-Za-z0-9._~%=&+-]*)?$/.test(src)) {
            return src;
        }
        try {
            var resolved = new URL(src, window.location.href);
            if (resolved.origin !== window.location.origin) return '';
            var path = resolved.pathname + resolved.search;
            return /^\/(?:image\/[0-9]+|api\/media-assets\/[0-9]+|media\/[A-Za-z0-9._~/%+-]+)(?:\?[A-Za-z0-9._~%=&+-]*)?$/.test(path)
                ? path
                : '';
        } catch (_) {
            return '';
        }
    }

    function splitCssList(value) {
        var result = [];
        var current = '';
        var depth = 0;
        for (var i = 0; i < value.length; i++) {
            var ch = value[i];
            if (ch === '(') depth++;
            if (ch === ')') depth = Math.max(0, depth - 1);
            if (ch === ',' && depth === 0) {
                result.push(current.trim());
                current = '';
                continue;
            }
            current += ch;
        }
        if (current.trim()) result.push(current.trim());
        return result;
    }

    function extractCmsMediaUrls(value) {
        if (typeof value !== 'string' || value === '') return [];
        var urls = [];
        value.replace(/url\(([^)]+)\)/gi, function (_, rawUrl) {
            var cleaned = String(rawUrl || '').trim().replace(/^['"]|['"]$/g, '');
            var normalized = normalizeCmsMediaPath(cleaned);
            if (normalized) urls.push(normalized);
            return '';
        });
        return Array.from(new Set(urls));
    }

    function getCaptureSurfaceRoots(doc) {
        var roots = [];
        if (!doc) return roots;
        var runtimeRoot = doc.getElementById('runtime-root');
        var authoredRoot = runtimeRoot && runtimeRoot.firstElementChild ? runtimeRoot.firstElementChild : null;
        function addRoot(node) {
            if (node && node.nodeType === 1 && roots.indexOf(node) === -1) {
                roots.push(node);
            }
        }
        [doc.documentElement, doc.body, runtimeRoot, authoredRoot].forEach(addRoot);
        [doc.querySelector('canvas'), doc.querySelector('svg')].forEach(function (node) {
            while (node && node.nodeType === 1) {
                addRoot(node);
                if (node === runtimeRoot || node === doc.body || node === doc.documentElement) break;
                node = node.parentElement;
            }
        });
        return roots;
    }

    function colorHasVisibleAlpha(color) {
        if (typeof color !== 'string' || color === '' || color === 'transparent') return false;
        var match = color.match(/rgba?\(([^)]+)\)/i);
        if (!match) return true;
        var parts = match[1].split(',').map(function (part) { return parseFloat(part.trim()); });
        if (parts.length < 4) return true;
        return !isNaN(parts[3]) && parts[3] > 0;
    }

    function getNodeCaptureRect(node, doc, width, height) {
        var viewportWidth = Math.max(1, (doc && doc.documentElement ? doc.documentElement.clientWidth : 0) || width);
        var viewportHeight = Math.max(1, (doc && doc.documentElement ? doc.documentElement.clientHeight : 0) || height);
        var rect;
        try {
            rect = node.getBoundingClientRect();
        } catch (_) {
            rect = { left: 0, top: 0, width: viewportWidth, height: viewportHeight };
        }
        var left = Math.max(0, (rect.left / viewportWidth) * width);
        var top = Math.max(0, (rect.top / viewportHeight) * height);
        var rectWidth = Math.max(0, (rect.width / viewportWidth) * width);
        var rectHeight = Math.max(0, (rect.height / viewportHeight) * height);
        if (!rectWidth || !rectHeight) {
            return { left: 0, top: 0, width: width, height: height };
        }
        return { left: left, top: top, width: rectWidth, height: rectHeight };
    }

    function getComputedBackgroundLayers(doc, width, height) {
        return getCaptureSurfaceRoots(doc).map(function (node) {
            var style = window.getComputedStyle(node);
            var urls = extractCmsMediaUrls(style.backgroundImage || '');
            var colors = colorHasVisibleAlpha(style.backgroundColor || '') ? [style.backgroundColor] : [];
            if (!urls.length && !colors.length) return null;
            var sizeList = splitCssList(style.backgroundSize || '');
            var positionList = splitCssList(style.backgroundPosition || '');
            var repeatList = splitCssList(style.backgroundRepeat || '');
            return {
                rect: getNodeCaptureRect(node, doc, width, height),
                backgroundColor: colors[0] || '',
                backgrounds: urls.map(function (src, index) {
                    return {
                        src: src,
                        size: sizeList[index] || sizeList[0] || 'auto',
                        position: positionList[index] || positionList[0] || '50% 50%',
                        repeat: repeatList[index] || repeatList[0] || 'repeat'
                    };
                })
            };
        }).filter(Boolean);
    }

    function parseBackgroundPosition(position, rect, drawWidth, drawHeight) {
        var parts = String(position || '50% 50%').trim().split(/\s+/);
        var xToken = parts[0] || '50%';
        var yToken = parts[1] || parts[0] || '50%';
        function resolve(token, space, size) {
            if (/%$/.test(token)) {
                var percent = parseFloat(token);
                return isNaN(percent) ? (space - size) / 2 : (space - size) * (percent / 100);
            }
            if (token === 'left' || token === 'top') return 0;
            if (token === 'right' || token === 'bottom') return space - size;
            if (token === 'center') return (space - size) / 2;
            var value = parseFloat(token);
            return isNaN(value) ? (space - size) / 2 : value;
        }
        return {
            x: rect.left + resolve(xToken, rect.width, drawWidth),
            y: rect.top + resolve(yToken, rect.height, drawHeight)
        };
    }

    function computeBackgroundDrawRect(image, layer, rect) {
        var imageWidth = image.naturalWidth || image.width || rect.width;
        var imageHeight = image.naturalHeight || image.height || rect.height;
        var size = String(layer.size || 'auto').trim();
        var drawWidth = imageWidth;
        var drawHeight = imageHeight;
        if (size === 'cover') {
            var coverScale = Math.max(rect.width / imageWidth, rect.height / imageHeight);
            drawWidth = imageWidth * coverScale;
            drawHeight = imageHeight * coverScale;
        } else if (size === 'contain') {
            var containScale = Math.min(rect.width / imageWidth, rect.height / imageHeight);
            drawWidth = imageWidth * containScale;
            drawHeight = imageHeight * containScale;
        } else if (/%/.test(size)) {
            var sizeParts = size.split(/\s+/);
            var sizeX = parseFloat(sizeParts[0]);
            var sizeY = parseFloat(sizeParts[1] || sizeParts[0]);
            if (!isNaN(sizeX)) drawWidth = rect.width * (sizeX / 100);
            if (!isNaN(sizeY)) drawHeight = rect.height * (sizeY / 100);
        } else if (size !== 'auto') {
            var autoParts = size.split(/\s+/);
            var widthPx = parseFloat(autoParts[0]);
            var heightPx = parseFloat(autoParts[1] || '');
            if (!isNaN(widthPx)) {
                drawWidth = widthPx;
                drawHeight = imageHeight * (drawWidth / imageWidth);
            }
            if (!isNaN(heightPx)) {
                drawHeight = heightPx;
                if (isNaN(widthPx)) drawWidth = imageWidth * (drawHeight / imageHeight);
            }
        }
        var pos = parseBackgroundPosition(layer.position, rect, drawWidth, drawHeight);
        return { x: pos.x, y: pos.y, width: drawWidth, height: drawHeight };
    }

    function drawBackgroundImage(ctx, image, layer, rect) {
        var drawRect = computeBackgroundDrawRect(image, layer, rect);
        var repeat = String(layer.repeat || 'repeat');
        var repeatX = repeat === 'repeat' || repeat === 'repeat-x';
        var repeatY = repeat === 'repeat' || repeat === 'repeat-y';
        var tileWidth = Math.max(1, drawRect.width);
        var tileHeight = Math.max(1, drawRect.height);
        var startX = repeatX ? rect.left - tileWidth : drawRect.x;
        var endX = repeatX ? rect.left + rect.width + tileWidth : drawRect.x + 1;
        var startY = repeatY ? rect.top - tileHeight : drawRect.y;
        var endY = repeatY ? rect.top + rect.height + tileHeight : drawRect.y + 1;
        for (var x = startX; x < endX; x += tileWidth) {
            for (var y = startY; y < endY; y += tileHeight) {
                ctx.drawImage(image, x, y, tileWidth, tileHeight);
                if (!repeatY) break;
            }
            if (!repeatX) break;
        }
    }

    async function inlineSvgManagedImages(svgClone, liveSvg) {
        var cloneImages = Array.from(svgClone.querySelectorAll('image'));
        var liveImages = Array.from(liveSvg.querySelectorAll('image'));
        await Promise.all(cloneImages.map(async function (cloneImage, index) {
            var liveImage = liveImages[index] || cloneImage;
            var href = liveImage.getAttribute('href') || liveImage.getAttribute('xlink:href') || cloneImage.getAttribute('href') || cloneImage.getAttribute('xlink:href') || '';
            var normalized = normalizeCmsMediaPath(href);
            if (!normalized) return;
            var img = await loadImage(normalized);
            var canvas = document.createElement('canvas');
            canvas.width = Math.max(1, img.naturalWidth || img.width || 1);
            canvas.height = Math.max(1, img.naturalHeight || img.height || 1);
            var ctx = canvas.getContext('2d');
            if (!ctx) return;
            ctx.drawImage(img, 0, 0);
            var dataUrl = canvas.toDataURL('image/png');
            cloneImage.setAttribute('href', dataUrl);
            cloneImage.setAttributeNS('http://www.w3.org/1999/xlink', 'xlink:href', dataUrl);
        }));
    }

    function convertSvgToCanvas(svgElement, width, height) {
        return new Promise(function (resolve, reject) {
            (async function () {
                var svgClone = svgElement.cloneNode(true);
                svgClone.setAttribute('width', width);
                svgClone.setAttribute('height', height);
                if (!svgClone.getAttribute('viewBox')) {
                    var w = svgElement.getAttribute('width') || svgElement.clientWidth || width;
                    var h = svgElement.getAttribute('height') || svgElement.clientHeight || height;
                    svgClone.setAttribute('viewBox', '0 0 ' + w + ' ' + h);
                }

                var liveEls = [svgElement].concat(Array.from(svgElement.querySelectorAll('*')));
                var cloneEls = [svgClone].concat(Array.from(svgClone.querySelectorAll('*')));
                var props = ['transform', 'transform-origin', 'opacity', 'fill', 'stroke', 'stroke-width', 'cx', 'cy', 'r', 'x', 'y', 'width', 'height', 'd', 'stop-color', 'offset', 'filter', 'display'];
                liveEls.forEach(function (liveEl, i) {
                    var cloneEl = cloneEls[i];
                    if (!cloneEl) return;
                    var s = window.getComputedStyle(liveEl);
                    props.forEach(function (p) {
                        var val = s.getPropertyValue(p);
                        if (val) cloneEl.style.setProperty(p, val);
                    });
                });

                var styleEl = document.createElementNS('http://www.w3.org/2000/svg', 'style');
                styleEl.textContent = '* { animation: none !important; transition: none !important; }';
                svgClone.insertBefore(styleEl, svgClone.firstChild);
                await inlineSvgManagedImages(svgClone, svgElement);

                var serialized = new XMLSerializer().serializeToString(svgClone);
                var svgData = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(serialized);
                var img = new Image();
                img.onload = function () {
                    var canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    var ctx = canvas.getContext('2d');
                    if (ctx) {
                        ctx.fillStyle = '#0d0d0f';
                        ctx.fillRect(0, 0, width, height);
                        ctx.drawImage(img, 0, 0, width, height);
                    }
                    resolve(canvas);
                };
                img.onerror = function () {
                    reject(new Error('Failed to load SVG image source.'));
                };
                img.src = svgData;
            })().catch(function (error) {
                reject(error);
            });
        });
    }

    async function compositeVisibleSurface(doc, foregroundCanvas, width, height) {
        var layers = getComputedBackgroundLayers(doc, width, height);
        if (!layers.length) return foregroundCanvas;
        var canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        var ctx = canvas.getContext('2d');
        if (!ctx) return foregroundCanvas;
        for (var i = 0; i < layers.length; i++) {
            var layerGroup = layers[i];
            var rect = layerGroup.rect;
            if (layerGroup.backgroundColor) {
                ctx.fillStyle = layerGroup.backgroundColor;
                ctx.fillRect(rect.left, rect.top, rect.width, rect.height);
            }
            for (var j = 0; j < layerGroup.backgrounds.length; j++) {
                var bg = layerGroup.backgrounds[j];
                var image = await loadImage(bg.src);
                drawBackgroundImage(ctx, image, bg, rect);
            }
        }
        ctx.drawImage(foregroundCanvas, 0, 0, width, height);
        return canvas;
    }

    function wait(ms) {
        return new Promise(function (resolve) { setTimeout(resolve, ms); });
    }

    function getManagedMediaState(doc) {
        var docEl = doc && doc.documentElement ? doc.documentElement : null;
        return {
            root: docEl,
            requiresSettledMedia: !!(docEl && docEl.dataset.creatrManagedMedia === '1'),
            isSettled: !!(docEl && docEl.dataset.creatrSettled === '1'),
            renderReady: !!(docEl && docEl.dataset.creatrRenderReady === '1')
        };
    }

    // Waits for a piece rendering inside a REAL, genuinely visible iframe
    // (not the clipped 1px background one) to finish its first render —
    // WebKit reliably executes script (timers, requestAnimationFrame,
    // render() calls) inside a visible iframe, but was found to silently
    // skip the actual GPU paint for a canvas clipped into a near-zero-area
    // container, no matter how long capture waited or how correct the
    // script-side timing was. Call this before capture({ liveIframe }) so
    // that branch's immediate toDataURL() grabs a frame that has actually
    // been painted, instead of polling/guessing.
    function resolveWaitProfile(engine, profile) {
        var mode = profile === 'manual' ? 'manual' : 'default';
        if (engine === 'three' || engine === 'aframe') {
            return mode === 'manual'
                ? { warmupMs: 1500, maxAttempts: 1440 }
                : { warmupMs: 1000, maxAttempts: 720 };
        }
        return mode === 'manual'
            ? { warmupMs: 500, maxAttempts: 720 }
            : { warmupMs: 300, maxAttempts: 360 };
    }

    async function waitForRender(iframe, engine, options) {
        var opts = options || {};
        var waitProfile = resolveWaitProfile(engine, opts.waitProfile);
        var requireReadyMarker = (engine === 'p5' || engine === 'c2' || engine === 'three' || engine === 'aframe');
        var runtimeError = '';
        function onMessage(event) {
            if (!event || !event.data || event.source !== iframe.contentWindow) return;
            if (event.data.type === 'sketch-status' && event.data.valid === false) {
                runtimeError = event.data.error || 'Piece runtime failed.';
            }
        }
        window.addEventListener('message', onMessage);
        try {
            await new Promise(function (resolve) {
                if (iframe.contentDocument && iframe.contentDocument.readyState === 'complete') {
                    resolve();
                    return;
                }
                iframe.onload = resolve;
                setTimeout(resolve, 800);
            });
            // A genuinely visible iframe isn't subject to the throttling the
            // clipped background iframe needed a long fixed warm-up for —
            // these windows are deliberately shorter.
            await wait(waitProfile.warmupMs);

            // Checked from inside the iframe's own requestAnimationFrame
            // callback, not a setTimeout poll — a live device repro showed
            // a visually-correct render still come back solid black moments
            // later, consistent with WebKit discarding the WebGL buffer on
            // any brief backgrounding (app-switcher gestures, the system
            // screenshot UI, etc.) during a setTimeout-based gap. Resolving
            // synchronously inside the rAF callback that confirms readiness
            // means the caller's immediate toDataURL() read lands a
            // microtask later, not a poll-interval later.
            // Counted in animation frames (~16.67ms each at 60Hz), not ms —
            // chosen to preserve roughly the same total wait budget the old
            // 300ms-interval poll loop had (40x300ms=12s for three,
            // 20x300ms=6s otherwise), not the same attempt count.
            var maxAttempts = waitProfile.maxAttempts;
            await new Promise(function (resolve, reject) {
                var attempt = 0;
                var raf = (iframe.contentWindow && iframe.contentWindow.requestAnimationFrame) || window.requestAnimationFrame;
                function checkOnFrame() {
                    attempt++;
                    var doc = iframe.contentDocument;
                    if (doc) {
                        var mediaState = getManagedMediaState(doc);
                        var requiresSettledMedia = mediaState.requiresSettledMedia;
                        var isSettled = !requiresSettledMedia || mediaState.isSettled;
                        var canvas = doc.querySelector('canvas');
                        if (canvas && (!requireReadyMarker || canvas.dataset.creatrReady === '1') && (!requiresSettledMedia || canvas.dataset.creatrSettled === '1' || isSettled)) {
                            resolve();
                            return;
                        }
                        if (doc.querySelector('svg') && mediaState.root && mediaState.root.dataset.creatrReady === '1' && isSettled) {
                            resolve();
                            return;
                        }
                    }
                    if (runtimeError) {
                        reject(new Error(runtimeError));
                        return;
                    }
                    if (attempt >= maxAttempts) {
                        var timeoutDoc = iframe.contentDocument;
                        var timeoutState = getManagedMediaState(timeoutDoc);
                        if (timeoutState.requiresSettledMedia) {
                            if (timeoutState.renderReady) {
                                reject(new Error(runtimeError || 'Piece rendered, but its media-backed thumbnail never settled.'));
                                return;
                            }
                            reject(new Error(runtimeError || 'Piece never reached a settled media-backed render state.'));
                            return;
                        }
                        reject(new Error(runtimeError || 'Piece did not finish rendering in time.'));
                        return;
                    }
                    raf(checkOnFrame);
                }
                raf(checkOnFrame);
            });
        } finally {
            window.removeEventListener('message', onMessage);
        }
    }

    async function captureStableFrame(liveIframe, source, options) {
        var opts = options || {};
        var attempts = opts.attempts || 4;
        var sampleDelayMs = opts.sampleDelayMs || 200;
        var acceptBlankLike = opts.acceptBlankLike === true;
        var previousDataUrl = '';
        var previousResult = null;
        var previousAnalysis = null;
        for (var i = 0; i < attempts; i++) {
            var result = await capture(Object.assign({}, source, {
                liveIframe: liveIframe,
                width: source.width || 320,
                height: source.height || 180
            }));
            if (!result.ok) {
                return result;
            }
            if (!result.dataUrl || result.dataUrl === 'data:,' || result.dataUrl.length < 500) {
                await wait(sampleDelayMs);
                continue;
            }
            var analysis = await analyzeFrame(result.dataUrl);
            if (analysis.blankLike && !acceptBlankLike) {
                previousDataUrl = '';
                previousResult = null;
                previousAnalysis = analysis;
                await wait(sampleDelayMs);
                continue;
            }
            if (previousDataUrl) {
                var diff = await diffImages(previousDataUrl, result.dataUrl);
                if (!diff.significant) {
                    return {
                        ok: true,
                        dataUrl: result.dataUrl,
                        kind: result.kind,
                        error: null,
                        stability: {
                            acceptedBy: 'stable-rendered-frame',
                            diffPercent: diff.percent,
                            changedPixelPercent: diff.changedPixelPercent,
                            averageLuma: analysis.averageLuma,
                            darkPixelRatio: analysis.darkPixelRatio,
                            lumaRange: analysis.lumaRange
                        }
                    };
                }
            }
            previousDataUrl = result.dataUrl;
            previousResult = result;
            previousAnalysis = analysis;
            await wait(sampleDelayMs);
        }
        return {
            ok: false,
            dataUrl: '',
            kind: previousResult ? previousResult.kind : null,
            error: previousAnalysis && previousAnalysis.blankLike
                ? 'Piece kept producing blank or near-blank frames instead of a usable thumbnail.'
                : 'Piece kept rendering, but its thumbnail never converged to a stable frame.'
        };
    }

    async function captureVisibleSettledFrame(iframe, source) {
        var doc = iframe.contentDocument || (iframe.contentWindow ? iframe.contentWindow.document : null);
        var mediaState = getManagedMediaState(doc);
        var immediate = await capture(Object.assign({}, source, {
            liveIframe: iframe,
            width: source.width || 320,
            height: source.height || 180
        }));
        if (!immediate.ok) {
            return immediate;
        }
        var analysis = await analyzeFrame(immediate.dataUrl);
        if (!analysis.blankLike && (!mediaState.requiresSettledMedia || mediaState.isSettled)) {
            immediate.analysis = analysis;
            return immediate;
        }
        return captureStableFrame(iframe, source, {
            attempts: 5,
            sampleDelayMs: 250,
            acceptBlankLike: false
        });
    }

    // Builds a small, genuinely visible (not clipped, not inside any
    // display:none ancestor) overlay containing a fresh iframe, waits for
    // the piece to actually render, captures it via the proven liveIframe
    // path, then tears the overlay down. For every capture call site OTHER
    // than the Pieces list "Generate Thumbnail" button (index.php has its
    // own independent, already-proven overlay implementation — this
    // function does not call or share code with it, by design, so nothing
    // here can affect that button). Never throws; mirrors capture()'s own
    // {ok, dataUrl, kind, error} contract.
    async function captureWithOverlay(source, options) {
        var opts = options || {};
        var width = source.width || 320;
        var height = source.height || 180;
        var engine = source.engine || 'p5';
        var attemptProfiles = [opts.waitProfile || 'default'];
        if (attemptProfiles[attemptProfiles.length - 1] !== 'manual') {
            attemptProfiles.push('manual');
        }
        var lastError = null;

        for (var i = 0; i < attemptProfiles.length; i++) {
            var waitProfile = attemptProfiles[i];
            var overlay = document.createElement('div');
            overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:999999;display:flex;align-items:center;justify-content:center;';
            var box = document.createElement('div');
            box.style.cssText = 'background:#0d0d0f;border:1px solid #333;border-radius:4px;padding:0.75rem;box-shadow:0 8px 24px rgba(0,0,0,0.4);';
            var label = document.createElement('div');
            label.textContent = waitProfile === 'manual' ? 'Retrying thumbnail capture…' : 'Capturing thumbnail…';
            label.style.cssText = 'color:#a1a1aa;font-size:0.8rem;margin-bottom:0.5rem;text-align:center;';
            var iframe = document.createElement('iframe');
            iframe.style.cssText = 'width:' + width + 'px;height:' + height + 'px;border:0;display:block;';
            iframe.sandbox = 'allow-scripts allow-same-origin';
            box.appendChild(label);
            box.appendChild(iframe);
            overlay.appendChild(box);
            document.body.appendChild(overlay);

            try {
                iframe.srcdoc = renderDocument(Object.assign({}, source, { width: width, height: height }));
                await waitForRender(iframe, engine, { waitProfile: waitProfile });
                var result = await captureVisibleSettledFrame(iframe, Object.assign({}, source, { width: width, height: height }));
                if (result.ok) {
                    return result;
                }
                lastError = new Error(result.error || 'Thumbnail capture failed.');
            } catch (error) {
                var doc = iframe.contentDocument || (iframe.contentWindow ? iframe.contentWindow.document : null);
                var mediaState = getManagedMediaState(doc);
                if (mediaState.renderReady) {
                    var stabilized = await captureStableFrame(iframe, Object.assign({}, source, { width: width, height: height }), {
                        attempts: 6,
                        sampleDelayMs: waitProfile === 'manual' ? 300 : 220,
                        acceptBlankLike: false
                    });
                    if (stabilized.ok) {
                        return stabilized;
                    }
                    lastError = new Error(stabilized.error || (error && error.message) || 'Thumbnail capture failed.');
                } else {
                    lastError = error;
                }
            } finally {
                if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
            }
        }

        return { ok: false, dataUrl: '', kind: null, error: lastError && lastError.message ? lastError.message : String(lastError || 'Thumbnail capture failed.') };
    }

    async function capture(source) {
        var width = source.width || 960;
        var height = source.height || 540;
        var engine = source.engine || 'p5';

        // Direct capture from live preview iframe if available
        if (source.liveIframe) {
            try {
                var iframe = source.liveIframe;
                var doc = iframe.contentDocument || (iframe.contentWindow ? iframe.contentWindow.document : null);
                console.log('[DIAG] live-direct-capture-attempt', { hasDoc: !!doc, hasCanvas: !!(doc && doc.querySelector('canvas')), creatrReady: doc && doc.querySelector('canvas') ? doc.querySelector('canvas').dataset.creatrReady : null, lastDiagStage: doc && doc.documentElement ? doc.documentElement.dataset.creatrDiagLast : null });
                if (doc) {
                    var canvas = doc.querySelector('canvas');
                    if (canvas && typeof canvas.toDataURL === 'function') {
                        var composedCanvas = await compositeVisibleSurface(doc, canvas, width, height);
                        var dataUrl = composedCanvas.toDataURL('image/png');
                        console.log('[DIAG] live-direct-capture-dataurl', { length: dataUrl ? dataUrl.length : 0 });
                        if (dataUrl && dataUrl !== 'data:,' && dataUrl.length > 100) {
                            return { ok: true, dataUrl: dataUrl, kind: 'live-canvas', error: null };
                        }
                    }
                    var svg = doc.querySelector('svg');
                    if (svg) {
                        var canvasFromSvg = await convertSvgToCanvas(svg, width, height);
                        var composedSvgCanvas = await compositeVisibleSurface(doc, canvasFromSvg, width, height);
                        var dataUrlFromSvg = composedSvgCanvas.toDataURL('image/png');
                        if (dataUrlFromSvg && dataUrlFromSvg !== 'data:,' && dataUrlFromSvg.length > 100) {
                            return { ok: true, dataUrl: dataUrlFromSvg, kind: 'live-svg', error: null };
                        }
                    }
                }
            } catch (e) {
                console.warn('Live preview direct capture failed, falling back to background capture:', e);
            }
        }

        var container = document.createElement('div');
        var frame = document.createElement('iframe');
        var runtimeError = '';

        // Positioned on top (z-index: 999999) inside a 1px by 1px overflow-hidden
        // container. WebKit/Safari aggressively suspends network/module loading (dynamic imports)
        // and throttles rendering in hidden, occluded, or extremely scaled-down (e.g. scale(0.001))
        // iframes. By placing the iframe inside a 1x1 visible, opaque parent container on top,
        // WebKit sees the element as active and intersecting the viewport, preventing culling.
        // The iframe inside it is styled with its full size (960x540) to prevent layout collapsing,
        // while the container hides it visually and keeps it completely click-safe.
        container.style.cssText = 'position:fixed;left:0;top:0;width:1px;height:1px;overflow:hidden;z-index:999999;pointer-events:none;opacity:1;';
        frame.style.cssText = 'position:absolute;left:0;top:0;width:' + width + 'px;height:' + height + 'px;border:none;pointer-events:none;';
        frame.sandbox = 'allow-scripts allow-same-origin';
        
        container.appendChild(frame);
        document.body.appendChild(container);

        // TEMPORARY DIAGNOSTIC — remove alongside piece-runtime.js's diag()
        // calls once the blank/premature-capture root cause is confirmed.
        var captureStartedAt = performance.now();
        function diagLog(label, data) {
            console.log('[DIAG]', label, Math.round(performance.now() - captureStartedAt) + 'ms', data || '');
        }

        // TEMPORARY DIAGNOSTIC — logs every raw message this window receives
        // while capture is running, before any filtering, to tell apart
        // "piece-runtime.js's postMessage never arrived" from "it arrived
        // but was filtered out by the source/shape check below."
        var rawMessageCount = 0;
        function onAnyMessageDiag(event) {
            rawMessageCount++;
            if (rawMessageCount <= 30) {
                diagLog('raw-message', {
                    n: rawMessageCount,
                    type: event && event.data ? event.data.type : typeof (event && event.data),
                    sourceMatchesFrame: !!(event && event.source === frame.contentWindow),
                    origin: event ? event.origin : null
                });
            }
        }
        window.addEventListener('message', onAnyMessageDiag);

        function onMessage(event) {
            if (!event || !event.data || event.source !== frame.contentWindow) return;
            if (event.data.type === 'creatr-diag') {
                diagLog(event.data.label, event.data.data);
                return;
            }
            if (event.data.type === 'sketch-status' && event.data.valid === false) {
                runtimeError = event.data.error || 'Piece runtime failed.';
            }
        }

        window.addEventListener('message', onMessage);

        try {
            frame.srcdoc = renderDocument(Object.assign({}, source, { width: width, height: height, isCapture: true }));
            await new Promise(function (resolve) {
                frame.onload = resolve;
                setTimeout(resolve, 800);
            });

            await wait((engine === 'three' || engine === 'aframe') ? 6000 : 500);

            var requireReadyMarker = (engine === 'p5' || engine === 'c2' || engine === 'three' || engine === 'aframe');
            var canvas = null;
            var kind = 'canvas';

            // Three.js loads its module (plus OrbitControls, which itself
            // re-imports three) from a CDN inside the sandboxed iframe
            // before any canvas or sketch code runs at all — on a slow
            // mobile connection that import alone can outlast the previous
            // ~27s ceiling with no error and no canvas to show for it, since
            // nothing throws while the import promise is still pending.
            // Extending the ceiling only costs time on the genuinely-broken
            // path; the loop still breaks the moment a ready canvas/svg
            // appears.
            var maxAttempts = (engine === 'three' || engine === 'aframe') ? 70 : 40;

            for (var attempt = 0; attempt < maxAttempts && !canvas; attempt++) {
                await wait(500);
                var doc = frame.contentDocument;
                if (!doc) continue;

                var foundCanvas = doc.querySelector('canvas');
                if (attempt < 10 || attempt % 10 === 0) {
                    diagLog('poll-attempt', {
                        attempt: attempt,
                        foundCanvas: !!foundCanvas,
                        creatrReady: foundCanvas ? foundCanvas.dataset.creatrReady : null,
                        // DOM-read fallback — see piece-runtime.js's diag() —
                        // independent of whether postMessage relay works.
                        lastDiagStage: doc.documentElement ? doc.documentElement.dataset.creatrDiagLast : null
                    });
                }
                if (foundCanvas && (!requireReadyMarker || foundCanvas.dataset.creatrReady === '1')) {
                    canvas = foundCanvas;
                    kind = 'canvas';
                    diagLog('canvas-accepted', { attempt: attempt });
                    break;
                }

                var svg = doc.querySelector('svg');
                if (svg) {
                    canvas = await convertSvgToCanvas(svg, width, height);
                    kind = 'svg';
                    break;
                }

                if (runtimeError) {
                    throw new Error(runtimeError);
                }
            }

            if (!canvas) {
                throw new Error(runtimeError || 'No canvas or svg element found after waiting.');
            }

            var composedCanvas = await compositeVisibleSurface(frame.contentDocument, canvas, width, height);
            var dataUrl = composedCanvas.toDataURL('image/png');
            if (!dataUrl || dataUrl === 'data:,') {
                throw new Error('Canvas capture returned empty image data.');
            }
            // Rough proxy only: a near-blank/solid-color PNG compresses far
            // smaller than an actually-rendered scene — not proof on its
            // own, but a small dataUrlLength alongside a screenshot of the
            // resulting thumbnail is enough to confirm blankness.
            diagLog('capture-complete', { dataUrlLength: dataUrl.length });

            return { ok: true, dataUrl: dataUrl, kind: kind, error: null };
        } catch (error) {
            return { ok: false, dataUrl: '', kind: null, error: error.message || String(error) };
        } finally {
            window.removeEventListener('message', onMessage);
            window.removeEventListener('message', onAnyMessageDiag);
            if (container && container.parentNode) {
                container.parentNode.removeChild(container);
            } else if (frame.parentNode) {
                frame.parentNode.removeChild(frame);
            }
        }
    }

    function loadImage(src) {
        return new Promise(function (resolve, reject) {
            var img = new Image();
            img.onload = function () { resolve(img); };
            img.onerror = function () { reject(new Error('Failed to load image for comparison.')); };
            img.src = src;
        });
    }

    async function analyzeFrame(dataUrl) {
        var image = await loadImage(dataUrl);
        var width = 64;
        var height = 36;
        var canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        var ctx = canvas.getContext('2d', { willReadFrequently: true });
        if (!ctx) {
            throw new Error('Frame analysis canvas is unavailable.');
        }

        ctx.drawImage(image, 0, 0, width, height);
        var pixels = ctx.getImageData(0, 0, width, height).data;
        var totalPixels = width * height;
        var totalLuma = 0;
        var totalAlpha = 0;
        var totalLumaSquared = 0;
        var darkPixels = 0;
        var nonDarkPixels = 0;
        var minLuma = 255;
        var maxLuma = 0;

        for (var i = 0; i < pixels.length; i += 4) {
            var alpha = pixels[i + 3] / 255;
            var luma = ((pixels[i] * 0.2126) + (pixels[i + 1] * 0.7152) + (pixels[i + 2] * 0.0722)) * alpha;
            totalLuma += luma;
            totalAlpha += alpha;
            totalLumaSquared += luma * luma;
            if (luma <= 8) {
                darkPixels++;
            } else if (luma >= 24) {
                nonDarkPixels++;
            }
            if (luma < minLuma) minLuma = luma;
            if (luma > maxLuma) maxLuma = luma;
        }

        var averageLuma = totalLuma / totalPixels;
        var averageAlpha = totalAlpha / totalPixels;
        var darkPixelRatio = darkPixels / totalPixels;
        var nonDarkPixelRatio = nonDarkPixels / totalPixels;
        var lumaRange = maxLuma - minLuma;
        var lumaVariance = Math.max(0, (totalLumaSquared / totalPixels) - (averageLuma * averageLuma));
        var lumaStdDev = Math.sqrt(lumaVariance);
        var flatLowInfo = averageAlpha >= 0.98
            && lumaRange <= 6
            && lumaStdDev <= 1.5
            && nonDarkPixelRatio <= 0.01;
        var blankLike = averageAlpha < 0.05
            || (darkPixelRatio >= 0.985 && nonDarkPixelRatio <= 0.002 && lumaRange <= 10)
            || (averageLuma <= 4 && nonDarkPixelRatio <= 0.0015 && lumaRange <= 8)
            || flatLowInfo;

        return {
            averageLuma: averageLuma,
            averageAlpha: averageAlpha,
            darkPixelRatio: darkPixelRatio,
            nonDarkPixelRatio: nonDarkPixelRatio,
            lumaRange: lumaRange,
            lumaStdDev: lumaStdDev,
            flatLowInfo: flatLowInfo,
            blankLike: blankLike
        };
    }

    async function diffImages(beforeDataUrl, afterDataUrl) {
        var before = await loadImage(beforeDataUrl);
        var after = await loadImage(afterDataUrl);
        var width = 64;
        var height = 36;
        var canvas = document.createElement('canvas');
        canvas.width = width * 2;
        canvas.height = height;
        var ctx = canvas.getContext('2d', { willReadFrequently: true });
        if (!ctx) {
            throw new Error('Image comparison canvas is unavailable.');
        }

        ctx.drawImage(before, 0, 0, width, height);
        ctx.drawImage(after, width, 0, width, height);
        var a = ctx.getImageData(0, 0, width, height).data;
        var b = ctx.getImageData(width, 0, width, height).data;
        var total = 0;
        var changedPixels = 0;
        for (var i = 0; i < a.length; i += 4) {
            var diff = Math.abs(a[i] - b[i]) + Math.abs(a[i + 1] - b[i + 1]) + Math.abs(a[i + 2] - b[i + 2]);
            total += diff / (255 * 3);
            if (diff > 45) changedPixels++;
        }

        var pixels = width * height;
        var percent = (total / pixels) * 100;
        var changedPixelPercent = (changedPixels / pixels) * 100;
        return {
            percent: percent,
            changedPixelPercent: changedPixelPercent,
            significant: percent >= 2.5 || changedPixelPercent >= 4
        };
    }

    window.CreatrPieceCapture = {
        capture: capture,
        diffImages: diffImages,
        renderDocument: renderDocument,
        seededRandom: seededRandom,
        waitForRender: waitForRender,
        captureWithOverlay: captureWithOverlay
    };
})();
