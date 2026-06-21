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

        return '<!DOCTYPE html>\n<html lang="en">\n<head>\n<meta charset="utf-8">\n<meta name="viewport" content="width=device-width, initial-scale=1">\n<title>' + escapeHtml(title) + '</title>\n<script type="importmap">\n{\n  "imports": {\n    "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js",\n    "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"\n  }\n}\n<\/script>\n<style>\nhtml,body{margin:0;padding:0;width:100%;height:100%;overflow:hidden;background:#0d0d0f;color:#fff;}\nbody{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}\n#runtime-root{width:100vw;height:100vh;overflow:hidden;}\n#piece-error{position:fixed;inset:auto 1rem 1rem 1rem;z-index:9999;padding:1rem;border:1px solid #fca5a5;background:#450a0a;color:#fee2e2;font:14px/1.4 ui-monospace,SFMono-Regular,Menlo,monospace;white-space:pre-wrap;display:none;}\ncanvas{display:block;width:100%;height:100%;}\n' + css + '\n</style>\n</head>\n<body>\n<div id="runtime-root">' + html + '</div>\n<div id="piece-error" role="alert"></div>\n<script>\n(function(){\n  var state = ' + JSON.stringify(randomSeed >>> 0) + ';\n  Math.random = function(){\n    state += 0x6D2B79F5;\n    var t = state;\n    t = Math.imul(t ^ (t >>> 15), t | 1);\n    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);\n    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;\n  };\n})();\nconst PIECE_ENGINE = ' + JSON.stringify(engine) + ';\nconst PIECE_CODE = ' + JSON.stringify(js) + ';\nconst PIECE_PRESERVE_DRAWING_BUFFER = ' + JSON.stringify(!!preserve) + ';\n<\/script>\n<script src="' + runtimeOrigin + '/assets/js/piece-runtime.js"><\/script>\n</body>\n</html>';
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function convertSvgToCanvas(svgElement, width, height) {
        return new Promise(function (resolve, reject) {
            try {
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
            } catch (error) {
                reject(error);
            }
        });
    }

    function wait(ms) {
        return new Promise(function (resolve) { setTimeout(resolve, ms); });
    }

    async function capture(source) {
        var width = source.width || 960;
        var height = source.height || 540;
        var engine = source.engine || 'p5';
        var frame = document.createElement('iframe');
        var runtimeError = '';

        frame.style.cssText = 'position:fixed;left:0;top:0;width:' + width + 'px;height:' + height + 'px;border:none;opacity:0;pointer-events:none;z-index:-1;';
        frame.sandbox = 'allow-scripts allow-same-origin';
        document.body.appendChild(frame);

        function onMessage(event) {
            if (!event || !event.data || event.source !== frame.contentWindow) return;
            if (event.data.type === 'sketch-status' && event.data.valid === false) {
                runtimeError = event.data.error || 'Piece runtime failed.';
            }
        }

        window.addEventListener('message', onMessage);

        try {
            frame.srcdoc = renderDocument(Object.assign({}, source, { width: width, height: height }));
            await new Promise(function (resolve) {
                frame.onload = resolve;
                setTimeout(resolve, 800);
            });

            await wait(engine === 'three' ? 6000 : 500);

            var requireReadyMarker = (engine === 'p5' || engine === 'c2' || engine === 'three');
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
            var maxAttempts = engine === 'three' ? 70 : 40;

            for (var attempt = 0; attempt < maxAttempts && !canvas; attempt++) {
                await wait(500);
                var doc = frame.contentDocument;
                if (!doc) continue;

                var foundCanvas = doc.querySelector('canvas');
                if (foundCanvas && (!requireReadyMarker || foundCanvas.dataset.creatrReady === '1')) {
                    canvas = foundCanvas;
                    kind = 'canvas';
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

            var dataUrl = canvas.toDataURL('image/png');
            if (!dataUrl || dataUrl === 'data:,') {
                throw new Error('Canvas capture returned empty image data.');
            }

            return { ok: true, dataUrl: dataUrl, kind: kind, error: null };
        } catch (error) {
            return { ok: false, dataUrl: '', kind: null, error: error.message || String(error) };
        } finally {
            window.removeEventListener('message', onMessage);
            if (frame.parentNode) {
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
        seededRandom: seededRandom
    };
})();
