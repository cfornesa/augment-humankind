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

        const surface = findSurface(doc);
        if (!surface) {
            throw new Error('No downloadable canvas or SVG is available yet.');
        }

        return surface.type === 'svg'
            ? exportSvg(surface.node)
            : exportCanvas(surface.node);
    }

    async function handleDownload(button) {
        const root = button.closest('[data-piece-download-root]');
        const statusEl = root ? root.querySelector('[data-piece-download-status]') : null;
        if (!root) {
            return;
        }

        const filename = button.dataset.downloadFilename || 'piece.png';
        const originalLabel = button.textContent;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.textContent = 'Preparing PNG...';
        setStatus(statusEl, '');

        try {
            const exportCanvasEl = await capturePiece(root);
            const blob = await canvasToBlob(exportCanvasEl);
            downloadBlob(blob, filename);
        } catch (error) {
            setStatus(statusEl, error instanceof Error ? error.message : 'Could not download the PNG right now.');
        } finally {
            button.disabled = false;
            button.removeAttribute('aria-busy');
            button.textContent = originalLabel;
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
})();
