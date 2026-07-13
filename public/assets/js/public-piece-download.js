(function () {
    function setStatus(statusEl, message) {
        if (!statusEl) {
            return;
        }

        statusEl.textContent = message || '';
        statusEl.hidden = !message;
    }

    function waitForImage(image) {
        return new Promise((resolve, reject) => {
            image.onload = function () {
                resolve(image);
            };
            image.onerror = function () {
                reject(new Error('Could not prepare the image export.'));
            };
        });
    }

    function isVisibleSurface(node) {
        if (!node || node.nodeType !== 1 || typeof node.getBoundingClientRect !== 'function') {
            return false;
        }

        if (node.getAttribute('aria-hidden') === 'true') {
            return false;
        }

        const style = window.getComputedStyle(node);
        if (style.display === 'none' || style.visibility === 'hidden' || Number(style.opacity) === 0) {
            return false;
        }

        const rect = node.getBoundingClientRect();
        return rect.width > 0 && rect.height > 0;
    }

    function findSurface(doc) {
        const aframeScene = doc.querySelector('a-scene#scene, a-scene');
        const aframeCanvas = aframeScene && aframeScene.canvas instanceof HTMLCanvasElement && isVisibleSurface(aframeScene.canvas)
            ? aframeScene.canvas
            : null;
        if (aframeCanvas) {
            return { type: 'canvas', node: aframeCanvas };
        }

        const canvases = Array.from(doc.querySelectorAll('canvas')).filter(isVisibleSurface);
        if (canvases.length > 0) {
            return { type: 'canvas', node: canvases[canvases.length - 1] };
        }

        const svgs = Array.from(doc.querySelectorAll('svg')).filter(isVisibleSurface);
        if (svgs.length > 0) {
            return { type: 'svg', node: svgs[0] };
        }

        return null;
    }

    function readReadyState(doc) {
        const root = doc.documentElement;
        if (!root) {
            return { ready: false, settled: false, managedMedia: false, pendingMedia: 0 };
        }

        const managedMediaState = root.dataset.creatrManagedMediaState || '';
        const pendingMedia = Number(root.dataset.creatrManagedMediaPending || '0');
        return {
            ready: root.dataset.creatrReady === '1',
            settled: root.dataset.creatrSettled === '1',
            managedMedia: managedMediaState === 'pending' || managedMediaState === 'loaded',
            pendingMedia: Number.isFinite(pendingMedia) ? pendingMedia : 0
        };
    }

    function wait(ms) {
        return new Promise((resolve) => window.setTimeout(resolve, ms));
    }

    function tryForceAframeRender(doc, surface) {
        const scene = doc.querySelector('a-scene#scene, a-scene');
        if (!scene || !surface || scene.canvas !== surface) {
            return;
        }

        const renderer = scene.renderer;
        const object3D = scene.object3D;
        const camera = scene.camera || scene.cameraEl?.getObject3D?.('camera') || null;
        if (!renderer || !object3D || !camera || typeof renderer.render !== 'function') {
            return;
        }

        try {
            renderer.render(object3D, camera);
        } catch (_) {}
    }

    async function waitForCaptureReady(doc) {
        const timeoutMs = 12000;
        const startedAt = Date.now();

        while ((Date.now() - startedAt) < timeoutMs) {
            const state = readReadyState(doc);
            const surface = findSurface(doc);
            if (surface && state.ready && (!state.managedMedia || (state.settled && state.pendingMedia === 0))) {
                return surface;
            }
            await wait(150);
        }

        const surface = findSurface(doc);
        if (surface) {
            return surface;
        }

        throw new Error('No downloadable canvas or SVG is available yet.');
    }

    function downloadBlob(blob, filename) {
        const blobUrl = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = blobUrl;
        link.download = filename;
        link.rel = 'noopener';
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(blobUrl), 1000);
    }

    function canvasToBlob(canvas) {
        return new Promise((resolve, reject) => {
            if (typeof canvas.toBlob === 'function') {
                canvas.toBlob((blob) => {
                    if (blob) {
                        resolve(blob);
                        return;
                    }
                    reject(new Error('Could not create the PNG download.'));
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

    async function exportCanvas(canvas) {
        const rect = canvas.getBoundingClientRect();
        const width = Math.max(1, canvas.width || Math.round(rect.width) || 1);
        const height = Math.max(1, canvas.height || Math.round(rect.height) || 1);
        const exportCanvas = document.createElement('canvas');
        exportCanvas.width = width;
        exportCanvas.height = height;
        const context = exportCanvas.getContext('2d');
        if (!context) {
            throw new Error('PNG export is unavailable in this browser.');
        }
        context.drawImage(canvas, 0, 0, width, height);
        return exportCanvas;
    }

    function hasVisiblePixels(canvas) {
        const context = canvas.getContext('2d');
        if (!context) {
            return false;
        }

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

    async function exportCanvasWithValidation(doc, canvas) {
        const composeCapture = doc.defaultView && doc.defaultView.__creatrComposeCapture;
        if (typeof composeCapture === 'function') {
            canvas = await composeCapture(canvas);
        }
        tryForceAframeRender(doc, canvas);
        const first = await exportCanvas(canvas);
        if (!hasVisiblePixels(first)) {
            await wait(32);
            tryForceAframeRender(doc, canvas);
            await wait(32);
            const retry = await exportCanvas(canvas);
            if (hasVisiblePixels(retry)) {
                return retry;
            }
            throw new Error('A-Frame could not produce a nonblank PNG right now.');
        }
        return first;
    }

    async function exportSvg(svg) {
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
            const imageReady = waitForImage(image);
            image.src = svgUrl;
            await imageReady;

            const exportCanvas = document.createElement('canvas');
            exportCanvas.width = width;
            exportCanvas.height = height;
            const context = exportCanvas.getContext('2d');
            if (!context) {
                throw new Error('PNG export is unavailable in this browser.');
            }
            context.drawImage(image, 0, 0, width, height);
            return exportCanvas;
        } finally {
            URL.revokeObjectURL(svgUrl);
        }
    }

    async function capturePiece(root) {
        const frame = root.querySelector('[data-piece-download-frame]');
        if (!(frame instanceof HTMLIFrameElement)) {
            throw new Error('The piece preview is unavailable right now.');
        }

        const doc = frame.contentDocument;
        if (!doc) {
            throw new Error('The piece is still loading. Please try again.');
        }

        const surface = await waitForCaptureReady(doc);
        if (!surface) {
            throw new Error('No downloadable canvas or SVG is available yet.');
        }

        if (surface.type === 'svg') {
            let svgCanvas = await exportSvg(surface.node);
            if (typeof doc.defaultView.__creatrComposeCapture === 'function') {
                svgCanvas = await doc.defaultView.__creatrComposeCapture(svgCanvas);
            }
            return svgCanvas;
        }
        return exportCanvasWithValidation(doc, surface.node);
    }

    async function handleDownload(button) {
        const root = button.closest('[data-piece-download-root]');
        const statusEl = root ? root.querySelector('[data-piece-download-status]') : null;
        if (!root) {
            return;
        }

        const filename = button.dataset.downloadFilename || 'piece.png';
        const originalAriaLabel = button.getAttribute('aria-label');
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.setAttribute('aria-label', 'Preparing PNG');
        setStatus(statusEl, '');

        try {
            const exportCanvasEl = await capturePiece(root);
            const blob = await canvasToBlob(exportCanvasEl);
            downloadBlob(blob, filename);
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Could not download the PNG right now.';
            setStatus(
                statusEl,
                /tainted canvases/i.test(message)
                    ? 'This piece still contains an image or texture the browser will not export safely.'
                    : message
            );
        } finally {
            button.disabled = false;
            button.removeAttribute('aria-busy');
            if (originalAriaLabel === null) {
                button.removeAttribute('aria-label');
            } else {
                button.setAttribute('aria-label', originalAriaLabel);
            }
        }
    }

    document.querySelectorAll('[data-piece-download-trigger]').forEach((button) => {
        button.addEventListener('click', function () {
            if (button.disabled) {
                return;
            }
            handleDownload(button);
        });
    });

    function fallbackCopyText(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        const copied = document.execCommand('copy');
        textarea.remove();
        return copied;
    }

    document.querySelectorAll('[data-surface-embed-copy]').forEach((button) => {
        button.dataset.embedCopyBound = 'true';
        let statusTimeout = null;
        const showTemporaryStatus = (status, message) => {
            if (statusTimeout) window.clearTimeout(statusTimeout);
            status.textContent = message;
            statusTimeout = window.setTimeout(() => {
                status.textContent = '';
                statusTimeout = null;
            }, 3000);
        };
        button.addEventListener('click', async function () {
            const code = button.dataset.embedCode || '';
            const actions = button.closest('.piece-page-embed-actions') || document;
            const status = actions.querySelector('[data-surface-embed-status]');
            const manual = actions.querySelector('[data-surface-embed-manual]');
            if (!code || !status) {
                return;
            }
            try {
                if (!navigator.clipboard || typeof navigator.clipboard.writeText !== 'function') {
                    throw new Error('Clipboard API unavailable');
                }
                await navigator.clipboard.writeText(code);
                showTemporaryStatus(status, 'Embed code is ready to paste.');
                if (manual) manual.hidden = true;
            } catch (_) {
                if (fallbackCopyText(code)) {
                    showTemporaryStatus(status, 'Embed code is ready to paste.');
                    if (manual) manual.hidden = true;
                } else if (manual) {
                    manual.value = code;
                    manual.hidden = false;
                    manual.focus();
                    manual.select();
                    showTemporaryStatus(status, 'Clipboard access was blocked. Copy the selected embed code below.');
                } else {
                    showTemporaryStatus(status, 'Copy failed. Please try again.');
                }
            }
        });
    });

    // The sandboxed regular runtime emits only a semantic gesture mode; the
    // host owns presentation so the same feedback remains visible outside the
    // iframe and does not become part of the authored artwork or PNG capture.
    window.addEventListener('message', (event) => {
        if (event.data?.type !== 'creatr-hand-gesture-mode') return;
        const frame = document.querySelector('[data-piece-download-frame]');
        if (!frame || event.source !== frame.contentWindow) return;
        const host = frame.closest('.piece-canvas-container') || frame.parentElement;
        if (!host) return;
        let indicator = host.querySelector('[data-piece-hand-gesture-mode]');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.dataset.pieceHandGestureMode = '';
            indicator.setAttribute('role', 'status');
            indicator.setAttribute('aria-live', 'polite');
            indicator.style.cssText = 'position:absolute;left:50%;bottom:calc(1rem + env(safe-area-inset-bottom));transform:translateX(-50%);z-index:42;padding:.38rem .72rem;border:1px solid rgba(255,255,255,.2);border-radius:999px;background:rgba(0,0,0,.68);color:#fff;font:600 .72rem/1.2 system-ui,sans-serif;letter-spacing:.06em;text-transform:uppercase;pointer-events:none;';
            host.appendChild(indicator);
        }
        const labels = { look: 'Look', orbit: 'Orbit', travel: 'Move', 'travel-ready': 'Point + pinch to move', 'orbit-ready': 'Pinch to orbit' };
        indicator.textContent = labels[event.data.mode] || '';
        indicator.hidden = !labels[event.data.mode];
    });

    // Shared primitives for other piece surfaces (immersive view) that
    // capture from a live canvas or overlay iframe instead of the
    // [data-piece-download-frame] iframe handled above.
    window.CreatrPieceDownload = {
        canvasToBlob,
        downloadBlob,
        exportCanvas,
        exportCanvasWithValidation,
        exportSvg,
        findSurface,
        hasVisiblePixels,
        waitForCaptureReady,
    };
})();
