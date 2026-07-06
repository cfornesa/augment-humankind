// TEMPORARY DIAGNOSTIC — forwards timing events to the parent page's
// console (visible even when the iframe's own console context isn't
// selected) so a live device reproduction can show the real sequence of
// canvas-ready signals vs. actual rendered frames. Remove once the root
// cause of blank/premature thumbnail captures is confirmed and fixed.
function diag(label, data) {
  try { console.log('[DIAG]', label, data || ''); } catch (_) {}
  try { window.parent.postMessage({ type: 'creatr-diag', label, data: data || null, t: performance.now() }, '*'); } catch (_) {}
  // DOM-based fallback so a parent that never receives the postMessage
  // above (relay broken, sandboxing quirk, etc.) can still read the last
  // diagnostic stage reached by polling the iframe's own DOM directly —
  // no cross-window messaging involved at all.
  try { document.documentElement.dataset.creatrDiagLast = label; } catch (_) {}
}
function showPieceError(error) {
  const el = document.getElementById('piece-error');
  if (!el) return;
  el.textContent = (error && (error.stack || error.message)) ? (error.stack || error.message) : String(error);
  el.style.display = 'block';
  try { window.parent.postMessage({ type: 'sketch-status', valid: false, error: el.textContent }, '*'); } catch (_) {}
}
window.addEventListener('error', (event) => showPieceError(event.error || event.message));
window.addEventListener('unhandledrejection', (event) => showPieceError(event.reason || 'Unhandled promise rejection'));
const pieceContext = window.CREATR_PIECE_CONTEXT || {};
const pieceDisableMotion = pieceContext.disableMotion === true;
function runPieceCode() {
  try {
    const fn = new Function(PIECE_CODE + "\n//# sourceURL=piece-runtime.js");
    fn();
  } catch (error) {
    showPieceError(error);
  }
}
function findCanvas(id) {
  return document.getElementById(id) || document.querySelector('canvas') || (() => {
    const canvas = document.createElement('canvas');
    canvas.id = id;
    const parent = document.getElementById('container') || document.getElementById('canvas-container') || document.getElementById('sketch-container') || document.getElementById('runtime-root');
    parent.appendChild(canvas);
    return canvas;
  })();
}
function sizeCanvas(canvas) {
  if (PIECE_ENGINE === 'c2') {
    // Fixed canonical intrinsic resolution, matching what the Immersive
    // gallery view already hardcodes for c2 pieces (immersive-gallery.js's
    // runtimeSize). c2 sketches draw with literal screen-pixel coordinates,
    // and AI-generated code reliably mixes a few absolute-pixel touches
    // (confirmed in a real piece's code: `cx + 30`, fixed particle counts)
    // alongside otherwise-proportional ones — those only produce a visibly
    // different composition when canvas.width/height itself varies across
    // surfaces, as it did before this fix (320 on the thumbnail vs 1280 in
    // Immersive). canvas.style.width/height (set elsewhere) still fills
    // whatever container each surface provides — only the *intrinsic*
    // resolution the piece draws into is now fixed.
    canvas.width = 1280;
    canvas.height = 720;
    return;
  }
  const w = Math.max(1, canvas.parentElement?.clientWidth || window.innerWidth || 1280);
  const h = Math.max(1, canvas.parentElement?.clientHeight || window.innerHeight || 720);
  canvas.width = w;
  canvas.height = h;
}
// Sizes the canvas *element box* to the largest bitmap-aspect rectangle that
// fits its container (same geometry object-fit:contain produced), so
// getBoundingClientRect() matches the visible bitmap exactly. Generated
// c2_interactive sketches map pointer coordinates with
// (clientX - rect.left) * (canvas.width / rect.width) — the formula the
// generation prompt prescribes — which is only correct when the element box
// IS the bitmap box. With the previous width/height:100% + object-fit:contain
// styling, non-16:9 containers letterboxed the bitmap inside the element and
// every hit-test was skewed by the bar size (confirmed on piece 103: ±36
// canvas px in a 896×560 box — larger than its drag targets).
function fitCanvasBox(canvas) {
  const host = canvas.parentElement;
  if (!host) return;
  const hw = Math.max(1, host.clientWidth || window.innerWidth || 1280);
  const hh = Math.max(1, host.clientHeight || window.innerHeight || 720);
  const scale = Math.min(hw / canvas.width, hh / canvas.height);
  canvas.style.width = (canvas.width * scale) + 'px';
  canvas.style.height = (canvas.height * scale) + 'px';
}
// KEEP IN SYNC (creatr-media-path-guard): piece-runtime.js / immersive-gallery.js
function isCmsMediaPath(src) {
  return typeof src === 'string' && /^\/(?:image\/[0-9]+|api\/media-assets\/[0-9]+|media\/[A-Za-z0-9._~/%+-]+)(?:\?[A-Za-z0-9._~%=&+-]*)?$/.test(src);
}
// Capture-safe rendering (piece_render_document with capture_safe_media) and
// standalone exports rewrite CMS media refs to data: URLs server-side before
// this runtime ever sees them — those substituted values must pass the guard
// even though authors only ever write CMS paths.
function isInlineMediaSrc(src) {
  return typeof src === 'string' && (/^data:image\//i.test(src) || /^blob:/i.test(src));
}
function resolveRuntimeMediaSrc(src) {
  if (isInlineMediaSrc(src)) return src;
  return normalizeCmsMediaPath(src);
}
function describeMediaSrc(src) {
  if (typeof src !== 'string') return String(src);
  return isInlineMediaSrc(src) ? src.slice(0, 64) + '… (inline media data)' : src;
}
const nativeImageCtor = window.Image;
const managedMediaState = {
  used: false,
  pending: 0,
  settledCallbacks: new Set(),
  observers: new Set(),
  trackedRequests: new Set(),
  nextRequestId: 0,
};
function normalizeCmsMediaPath(src) {
  if (typeof src !== 'string' || src === '') return '';
  if (isCmsMediaPath(src)) return src;
  try {
    const resolved = new URL(src, window.location.href);
    if (resolved.origin !== window.location.origin) return '';
    const path = resolved.pathname + resolved.search;
    return isCmsMediaPath(path) ? path : '';
  } catch (_) {
    return '';
  }
}
function extractCmsMediaUrls(value) {
  if (typeof value !== 'string' || value === '') return [];
  const urls = [];
  value.replace(/url\(([^)]+)\)/gi, (_, rawUrl) => {
    const cleaned = String(rawUrl || '').trim().replace(/^['"]|['"]$/g, '');
    const normalized = normalizeCmsMediaPath(cleaned);
    if (normalized) urls.push(normalized);
    return '';
  });
  return Array.from(new Set(urls));
}
function updateManagedMediaDataset() {
  try {
    document.documentElement.dataset.creatrManagedMedia = managedMediaState.used ? '1' : '0';
    document.documentElement.dataset.creatrManagedMediaPending = String(managedMediaState.pending);
    document.documentElement.dataset.creatrManagedMediaState = managedMediaState.used
      ? (managedMediaState.pending > 0 ? 'pending' : 'loaded')
      : 'none';
  } catch (_) {}
}
function noteManagedMediaUsage(src) {
  if (!isCmsMediaPath(src) && !isInlineMediaSrc(src)) return false;
  managedMediaState.used = true;
  updateManagedMediaDataset();
  return true;
}
function onManagedMediaSettled(callback) {
  managedMediaState.settledCallbacks.add(callback);
  return () => managedMediaState.settledCallbacks.delete(callback);
}
function notifyManagedMediaSettled() {
  if (managedMediaState.pending !== 0) return;
  managedMediaState.settledCallbacks.forEach((callback) => {
    try { callback(); } catch (_) {}
  });
}
function trackManagedMediaRequest(element, src, opts) {
  const normalizedSrc = resolveRuntimeMediaSrc(src);
  if (!noteManagedMediaUsage(normalizedSrc)) return false;
  const options = opts || {};
  const requestKey = element
    ? ((element.__creatrManagedMediaKey ||= 'el-' + (++managedMediaState.nextRequestId))) + '|' + normalizedSrc
    : 'probe|' + normalizedSrc;
  if (managedMediaState.trackedRequests.has(requestKey)) {
    return true;
  }
  managedMediaState.trackedRequests.add(requestKey);
  if (element && element.__creatrManagedMediaSrc === src) {
    if (element.__creatrManagedMediaDone === true) {
      updateManagedMediaDataset();
      notifyManagedMediaSettled();
    }
    return true;
  }
  managedMediaState.pending += 1;
  updateManagedMediaDataset();
  let finished = false;
  function finish(status) {
    if (finished) return;
    finished = true;
    if (element) {
      element.__creatrManagedMediaDone = true;
      element.dataset.creatrManagedMediaStatus = status;
    }
    managedMediaState.pending = Math.max(0, managedMediaState.pending - 1);
    updateManagedMediaDataset();
    diag('managed-media-' + status, { src: normalizedSrc, pending: managedMediaState.pending });
    notifyManagedMediaSettled();
  }
  if (element) {
    const previousSrc = typeof element.getAttribute === 'function' ? (element.getAttribute('src') || '') : '';
    element.__creatrManagedMediaSrc = normalizedSrc;
    element.__creatrManagedMediaDone = false;
    const tag = (element.tagName || '').toLowerCase();
    const completeNow = tag === 'img' && previousSrc && element.complete === true;
    const loadedNow = completeNow && ((element.naturalWidth || 0) > 0 || (element.naturalHeight || 0) > 0);
    const failedNow = completeNow && !loadedNow;
    if (loadedNow) {
      finish('loaded');
      return true;
    }
    if (failedNow) {
      if (options.surfaceErrors !== false) {
        showPieceError('Could not load CMS media asset: ' + describeMediaSrc(normalizedSrc));
      }
      finish('error');
      return true;
    }
    element.addEventListener('load', () => finish('loaded'), { once: true });
    element.addEventListener('error', () => {
      if (options.surfaceErrors !== false) {
        showPieceError('Could not load CMS media asset: ' + describeMediaSrc(normalizedSrc));
      }
      finish('error');
    }, { once: true });
    return true;
  }
  const probe = new nativeImageCtor();
  probe.decoding = 'async';
  probe.loading = 'eager';
  probe.onload = () => finish('loaded');
  probe.onerror = () => {
    if (options.surfaceErrors !== false) {
      showPieceError('Could not load CMS media asset: ' + describeMediaSrc(normalizedSrc));
    }
    finish('error');
  };
  probe.src = normalizedSrc;
  return true;
}
window.Image = class CreatrTrackedImage extends nativeImageCtor {
  set src(value) {
    if (isCmsMediaPath(value)) {
      trackManagedMediaRequest(this, value);
    }
    super.src = value;
  }
  get src() {
    return super.src;
  }
};
updateManagedMediaDataset();
function startFrame(callback) {
  let count = 0;
  function tick() {
    count++;
    try { callback(count); } catch (error) { showPieceError(error); return; }
    requestAnimationFrame(tick);
  }
  requestAnimationFrame(tick);
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
  const activeKeys = keys instanceof Set ? keys : new Set(keys);
  let fwdScale = 0;
  let rightScale = 0;
  if (activeKeys.has('ArrowUp')) fwdScale += speed;
  if (activeKeys.has('ArrowDown')) fwdScale -= speed;
  if (activeKeys.has('ArrowLeft')) rightScale -= speed;
  if (activeKeys.has('ArrowRight')) rightScale += speed;
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
function createKeyboardNavigation(controls, options = {}) {
  const { speed = 0.05, minX = -8, maxX = 8, minY = -Infinity, maxY = Infinity, minZ = 0.5, maxZ = Infinity, container, isEnabled } = options;
  const keys = new Set();
  const target = window;
  const _forward = new window.THREE.Vector3();
  const TARGET_FRAME_MS = 1000 / 60;
  const MAX_FRAME_SCALE = 4;
  let lastUpdateAt = null;

  function keyboardNavEnabled() {
    if (typeof isEnabled === 'function') return isEnabled() !== false;
    if (!container) return true;
    return container.dataset.keyboardNavigationDisabled !== 'true';
  }

  function onKeyDown(event) {
    const mappedKey = mapMovementKey(event);
    if (!mappedKey) return;
    if (!keyboardNavEnabled() || shouldIgnoreKeyEventTarget(event.target)) return;
    event.preventDefault();
    keys.add(mappedKey);
  }

  function onKeyUp(event) {
    const mappedKey = mapMovementKey(event);
    if (!mappedKey) return;
    if (!keyboardNavEnabled() || shouldIgnoreKeyEventTarget(event.target)) {
      keys.delete(mappedKey);
      return;
    }
    keys.delete(mappedKey);
    keys.delete(event.key);
  }

  function clearKeys() {
    keys.clear();
  }

  function onWindowBlur() {
    clearKeys();
  }

  function update() {
    if (!keyboardNavEnabled()) {
      clearKeys();
      return false;
    }
    const now = performance.now();
    const frameScale = lastUpdateAt === null
      ? 1
      : Math.min(MAX_FRAME_SCALE, Math.max(0, (now - lastUpdateAt) / TARGET_FRAME_MS));
    lastUpdateAt = now;
    if (!controls.enabled || keys.size === 0) return false;
    controls.object.getWorldDirection(_forward);
    const resolvedSpeed = (typeof speed === 'function' ? speed(controls) : speed) * frameScale;
    const { dx, dy, dz } = computeOrbitKeyboardMotion(_forward, keys, resolvedSpeed);
    const newCamX = Math.max(minX, Math.min(maxX, controls.object.position.x + dx));
    const newCamY = Math.max(minY, Math.min(maxY, controls.object.position.y + dy));
    const newCamZ = Math.max(minZ, Math.min(maxZ, controls.object.position.z + dz));
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

  function onContainerClick() {
    container?.focus();
  }

  if (container) {
    container.tabIndex = 0;
    container.addEventListener('click', onContainerClick, { passive: true });
  }

  function dispose() {
    target.removeEventListener('keydown', onKeyDown);
    target.removeEventListener('keyup', onKeyUp);
    window.removeEventListener('blur', onWindowBlur);
    if (container) container.removeEventListener('click', onContainerClick);
    clearKeys();
  }

  target.addEventListener('keydown', onKeyDown);
  target.addEventListener('keyup', onKeyUp);
  window.addEventListener('blur', onWindowBlur);
  return { update, dispose, clearKeys };
}
function signalReady(target, options) {
  const config = options || {};
  const el = typeof target === 'function' ? target() : target;
  if (el && el.dataset) {
    el.dataset.creatrReady = '1';
    if (config.settled) {
      el.dataset.creatrSettled = '1';
    }
  }
  try {
    document.documentElement.dataset.creatrReady = '1';
    if (config.settled) {
      document.documentElement.dataset.creatrSettled = '1';
    }
  } catch (_) {}
  try { window.parent.postMessage({ type: 'sketch-status', valid: true }, '*'); } catch (_) {}
}
function trackInlineManagedMedia(root) {
  const scope = root || document;
  scope.querySelectorAll('img[src], image').forEach((node) => {
    const tag = (node.tagName || '').toLowerCase();
    const src = tag === 'image'
      ? (node.getAttribute('href') || node.getAttribute('xlink:href') || '')
      : (node.getAttribute('src') || '');
    if (!isCmsMediaPath(src)) return;
    if (tag === 'img') {
      trackManagedMediaRequest(node, src, { surfaceErrors: false });
    } else {
      trackManagedMediaRequest(null, src, { surfaceErrors: false });
    }
  });
}
function getManagedMediaRoots(root) {
  const roots = [];
  const runtimeRoot = document.getElementById('runtime-root');
  const authoredRoot = runtimeRoot && runtimeRoot.firstElementChild ? runtimeRoot.firstElementChild : null;
  const addRoot = (node) => {
    if (node && node.nodeType === 1 && !roots.includes(node)) {
      roots.push(node);
    }
  };
  [document.documentElement, document.body, runtimeRoot, root, authoredRoot].forEach(addRoot);
  [document.querySelector('canvas'), document.querySelector('svg')].forEach((node) => {
    while (node && node.nodeType === 1) {
      addRoot(node);
      if (node === runtimeRoot || node === document.body || node === document.documentElement) break;
      node = node.parentElement;
    }
  });
  return roots;
}
function trackBackgroundManagedMedia(root) {
  getManagedMediaRoots(root).forEach((node) => {
    let backgroundImage = '';
    try {
      backgroundImage = window.getComputedStyle(node).backgroundImage || '';
    } catch (_) {
      backgroundImage = '';
    }
    extractCmsMediaUrls(backgroundImage).forEach((src) => {
      trackManagedMediaRequest(null, src, { surfaceErrors: false });
    });
  });
}
function scanManagedMedia(root) {
  trackInlineManagedMedia(root);
  trackBackgroundManagedMedia(root);
}
function observeManagedMedia(root, onScan) {
  if (!root || typeof MutationObserver !== 'function' || !root.querySelectorAll || typeof root.nodeType !== 'number') return;
  const observer = new MutationObserver((mutations) => {
    let shouldRescan = false;
    mutations.forEach((mutation) => {
      if (mutation.type === 'attributes') {
        shouldRescan = true;
        return;
      }
      mutation.addedNodes.forEach((node) => {
        if (shouldRescan || !node || node.nodeType !== 1) return;
        const el = node;
        if (el.matches?.('img[src], image') || el.querySelector?.('img[src], image')) {
          shouldRescan = true;
        }
      });
    });
    if (shouldRescan) onScan(root);
  });
  observer.observe(root, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ['src', 'href', 'xlink:href', 'style', 'class'],
  });
  managedMediaState.observers.add(observer);
}
function createReadyController(target) {
  let renderObserved = false;
  let readySignaled = false;
  let settleScheduled = false;
  function finish(source, settled) {
    if (readySignaled) return;
    readySignaled = true;
    diag('capture-ready', { source, settled, managedMedia: managedMediaState.used, pending: managedMediaState.pending });
    signalReady(target, { settled });
  }
  function maybeFinish(source) {
    if (readySignaled || !renderObserved) return;
    if (!managedMediaState.used) {
      finish(source, false);
      return;
    }
    if (managedMediaState.pending > 0) {
      diag('capture-waiting-managed-media', { source, pending: managedMediaState.pending });
      return;
    }
    if (settleScheduled) return;
    settleScheduled = true;
    requestAnimationFrame(() => {
      requestAnimationFrame(() => finish(source, true));
    });
  }
  onManagedMediaSettled(() => {
    settleScheduled = false;
    maybeFinish('managed-media-settled');
  });
  return {
    markRendered(source) {
      renderObserved = true;
      try { document.documentElement.dataset.creatrRenderReady = '1'; } catch (_) {}
      maybeFinish(source);
    },
    noteInlineMedia(root) {
      scanManagedMedia(root);
      observeManagedMedia(root, scanManagedMedia);
      maybeFinish('inline-managed-media-scan');
    }
  };
}
function bootCanvasRuntime(extra) {
  runPieceCode();
  if (typeof window.sketch !== 'function') return;
  const canvas = findCanvas(PIECE_ENGINE === 'c2' ? 'c2-canvas' : 'scene');
  const ready = createReadyController(canvas);
  // For c2, the canvas now has a fixed intrinsic resolution (sizeCanvas())
  // regardless of surface — but plain width:100%;height:100% still
  // non-uniformly stretches that bitmap to fill whatever shape box each
  // surface's container happens to be (a phone's narrow/tall public-view
  // iframe vs. a 16:9 thumbnail vs. a squarer admin preview pane). Confirmed
  // in a real piece's code (id 72): a face shape with a fixed 1:1.5
  // width:height ratio in canvas-pixel-space looked square in one surface,
  // oval in another, and severely vertically elongated in a third — same
  // underlying drawing, different non-uniform CSS stretch per box.
  // fitCanvasBox() preserves the canvas's native aspect ratio when scaling to
  // fit any container (same contain geometry as before), while also keeping
  // the element box identical to the visible bitmap so the pointer-coordinate
  // formula generated c2_interactive sketches use stays accurate — see the
  // fitCanvasBox() comment. touch-action:none keeps pointermove drags working
  // on touchscreens instead of being swallowed by scroll gestures.
  if (PIECE_ENGINE === 'c2') {
    canvas.style.cssText = 'display:block;touch-action:none;';
    const host = canvas.parentElement;
    if (host) {
      host.style.display = 'flex';
      host.style.alignItems = 'center';
      host.style.justifyContent = 'center';
    }
  } else {
    canvas.style.cssText = 'display:block;width:100%;height:100%;';
  }
  sizeCanvas(canvas);
  if (PIECE_ENGINE === 'c2') fitCanvasBox(canvas);
  window.addEventListener('resize', () => {
    sizeCanvas(canvas);
    if (PIECE_ENGINE === 'c2') fitCanvasBox(canvas);
  });
  // Only the piece's own first real draw means there's something worth
  // capturing — wrap the startFrame handed to the sketch so the readiness
  // signal fires after its first tick actually runs, not merely once
  // bootstrapping has handed control to the piece.
  let readySignaled = false;
  const mediaContext = PIECE_ENGINE === 'c2' ? canvas.getContext('2d') : null;
  const imageCache = new Map();
  function loadImage(src) {
    const resolved = resolveRuntimeMediaSrc(src);
    if (!resolved) {
      showPieceError('C2 media helpers may only load same-origin CMS media paths such as /image/2, /media/..., or /api/media-assets/2.');
      return null;
    }
    src = resolved;
    if (imageCache.has(src)) return imageCache.get(src);
    const image = new Image();
    image.decoding = 'async';
    image.loading = 'eager';
    image.dataset.creatrLoaded = '0';
    // Generated sketches call this every way models guess at: `await
    // runtime.loadImage(...)`, `runtime.loadImage(...).then(...)`, and plain
    // `const img = runtime.loadImage(...)` handed straight to the draw
    // helpers. Return a Promise (so await/.then work natively) with the
    // element attached so the draw helpers can unwrap it synchronously.
    const loaded = new Promise((resolve, reject) => {
      image.onload = () => { image.dataset.creatrLoaded = '1'; resolve(image); };
      image.onerror = () => {
        const message = 'Could not load CMS media asset: ' + describeMediaSrc(src);
        showPieceError(message);
        reject(new Error(message));
      };
    });
    loaded.catch(() => {}); // already surfaced via showPieceError; avoid a duplicate unhandledrejection overlay
    loaded.__creatrImage = image;
    // The window.Image setter only tracks CMS paths — inline (data:/blob:)
    // srcs substituted by capture-safe rewriting must be tracked here so the
    // PNG capture poll waits for their async decode instead of racing it.
    if (isInlineMediaSrc(src)) {
      trackManagedMediaRequest(image, src, { surfaceErrors: false });
    }
    image.src = src;
    imageCache.set(src, loaded);
    return loaded;
  }
  function resolveImageRef(image) {
    return image && image.__creatrImage ? image.__creatrImage : image;
  }
  function drawImage(image, x, y, width, height) {
    image = resolveImageRef(image);
    if (!mediaContext || !image || image.dataset?.creatrLoaded !== '1') return false;
    try {
      mediaContext.drawImage(image, x, y, width, height);
      return true;
    } catch (error) {
      showPieceError(error);
      return false;
    }
  }
  function drawImageCover(image, x, y, width, height) {
    image = resolveImageRef(image);
    if (!mediaContext || !image || image.dataset?.creatrLoaded !== '1') return false;
    const sourceWidth = image.naturalWidth || image.width;
    const sourceHeight = image.naturalHeight || image.height;
    if (!sourceWidth || !sourceHeight || !width || !height) return false;
    const sourceAspect = sourceWidth / sourceHeight;
    const targetAspect = width / height;
    let sx = 0;
    let sy = 0;
    let sw = sourceWidth;
    let sh = sourceHeight;
    if (sourceAspect > targetAspect) {
      sw = sourceHeight * targetAspect;
      sx = (sourceWidth - sw) / 2;
    } else {
      sh = sourceWidth / targetAspect;
      sy = (sourceHeight - sh) / 2;
    }
    try {
      mediaContext.drawImage(image, sx, sy, sw, sh, x, y, width, height);
      return true;
    } catch (error) {
      showPieceError(error);
      return false;
    }
  }
  function instrumentedStartFrame(callback) {
    return startFrame((count) => {
      callback(count);
      if (!readySignaled) {
        readySignaled = true;
        ready.markRendered('canvas-startFrame-' + count);
      }
    });
  }
  try {
    window.sketch({ canvas, startFrame: instrumentedStartFrame, loadImage, drawImage, drawImageCover, ...(extra || {}) });
    ready.noteInlineMedia(document);
  } catch (error) { showPieceError(error); }
}
function bootP5() {
  const script = document.createElement('script');
  script.src = 'https://cdnjs.cloudflare.com/ajax/libs/p5.js/1.9.0/p5.min.js';
  script.onload = () => {
    runPieceCode();
    try {
      if (typeof window.sketch === 'function' && typeof window.p5 === 'function') {
        const parent = document.getElementById('container') || document.getElementById('canvas-container') || document.getElementById('sketch-container') || document.getElementById('runtime-root');
        const instance = new window.p5(window.sketch, parent);
        const ready = createReadyController(() => parent.querySelector('canvas') || document.querySelector('canvas'));
        // p5's own frameCount only increments after a real draw() call —
        // wait for that instead of signaling right after setup(), when the
        // canvas exists but is still blank.
        const waitForFirstDraw = () => {
          if (instance.frameCount >= 1) {
            ready.markRendered('p5-frame-' + instance.frameCount);
          } else {
            requestAnimationFrame(waitForFirstDraw);
          }
        };
        ready.noteInlineMedia(document);
        requestAnimationFrame(waitForFirstDraw);
      }
    } catch (error) { showPieceError(error); }
  };
  script.onerror = () => showPieceError('Could not load p5.js runtime.');
  document.head.appendChild(script);
}
function bootC2() {
  const script = document.createElement('script');
  script.src = 'https://cdn.jsdelivr.net/npm/c2.js@1.0.9/dist/c2.min.js';
  script.onload = () => {
    bootCanvasRuntime({ c2: window.c2 });
  };
  script.onerror = () => showPieceError('Could not load c2.js runtime.');
  document.head.appendChild(script);
}
function bootAFrame() {
  const runtimeScript = document.currentScript && document.currentScript.src ? document.currentScript.src : window.location.href;
  const script = document.createElement('script');
  script.src = new URL('/assets/js/aframe.min.js', runtimeScript).toString();
  script.onload = () => {
    try {
      let scene = document.querySelector('a-scene#scene') || document.querySelector('a-scene');
      if (!scene) {
        throw new Error('A-Frame piece did not provide an <a-scene id="scene"> root.');
      }
      if (!scene.id) scene.id = 'scene';
      if (!scene.hasAttribute('embedded')) scene.setAttribute('embedded', '');
      scene.style.width = '100%';
      scene.style.height = '100%';
      scene.style.display = 'block';
      if (pieceDisableMotion) {
        scene.setAttribute('device-orientation-permission-ui', 'enabled: false');
      }

      runPieceCode();
      const ready = createReadyController(() => scene.canvas || scene.querySelector('canvas') || document.querySelector('canvas'));
      ready.noteInlineMedia(scene);
      if (typeof window.sketch === 'function') {
        window.sketch({ AFRAME: window.AFRAME, scene, startFrame });
      }
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
        if (scene?.camera) return scene.camera;
        const cameraEl = scene?.querySelector('[camera]') || scene?.querySelector('a-camera');
        return cameraEl?.object3D || null;
      }

      function getAFrameCameraMover() {
        const cameraObject = getAFrameCameraObject();
        if (!cameraObject) return null;
        const cameraEl = cameraObject.el || scene?.querySelector('[camera]') || scene?.querySelector('a-camera');
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
        if (!THREE_NS || !scene || !pointer || wasMultiTouch || pointer.button !== 0 || event.button !== 0 || pointer.moved) return;
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

      function disableMotionTracking() {
        if (!pieceDisableMotion) return;
        const cameraEntities = new Set();
        if (scene.camera?.el) cameraEntities.add(scene.camera.el);
        scene.querySelectorAll('a-camera, [camera]').forEach((el) => cameraEntities.add(el));
        cameraEntities.forEach((el) => {
          const current = el.getAttribute('look-controls');
          if (current === null && el.tagName.toLowerCase() !== 'a-camera' && !el.hasAttribute('camera')) return;
          el.setAttribute('look-controls', 'magicWindowTrackingEnabled: false');
        });
      }

      let readySignaled = false;
      function signalAFrameReadyOnce() {
        if (readySignaled) return;
        const canvas = scene.canvas || scene.querySelector('canvas') || document.querySelector('canvas');
        if (!canvas) return;
        readySignaled = true;
        ready.markRendered('aframe-renderstart');
      }

      scene.addEventListener('renderstart', signalAFrameReadyOnce, { once: true });
      scene.addEventListener('renderstart', bindAFramePointerControls, { once: true });
      scene.addEventListener('loaded', disableMotionTracking, { once: true });
      scene.addEventListener('loaded', () => {
        ready.noteInlineMedia(scene);
        disableMotionTracking();
        bindAFramePointerControls();
        requestAnimationFrame(() => requestAnimationFrame(signalAFrameReadyOnce));
      }, { once: true });
      frameId = requestAnimationFrame(animateAFramePointerNavigation);
      requestAnimationFrame(disableMotionTracking);
      setTimeout(bindAFramePointerControls, 250);
      setTimeout(disableMotionTracking, 250);
      startFrame(() => signalAFrameReadyOnce());
      setTimeout(signalAFrameReadyOnce, 2500);
    } catch (error) {
      showPieceError(error);
    }
  };
  script.onerror = () => showPieceError('Could not load self-hosted A-Frame runtime.');
  document.head.appendChild(script);
}
async function bootThree() {
  // Created and inserted before the CDN imports below resolve, so a slow
  // network never makes capture/diagnostics see "no canvas at all" — only
  // the data-creatr-ready marker (set once the piece actually renders) is
  // gated on the imports finishing.
  const canvas = findCanvas('scene');
  diag('canvas-created');
  canvas.style.cssText = 'display:block;width:100%;height:100%;';
  sizeCanvas(canvas);
  // A scene with too many individual draw calls (thousands of separate
  // meshes) can exhaust WebGL resources and lose its context — this
  // doesn't throw and doesn't touch window.sketch's contract, so neither
  // the global error handler nor the typeof check below ever sees it;
  // left unhandled, the canvas just never gets marked ready and capture
  // times out with no explanation. Surface it as a real error instead.
  canvas.addEventListener('webglcontextlost', (event) => {
    event.preventDefault();
    showPieceError('WebGL context was lost — the scene is likely too complex to render (too many individual objects/draw calls).');
  });

  // Without this, a stalled/blocked CDN fetch (slow mobile network, or
  // WebKit throttling dynamic imports in a sandboxed/occluded iframe) never
  // throws — the import promise just sits pending — so capture's polling
  // loop times out with a generic "no canvas" message that misreports a
  // network stall as the piece having no canvas at all.
  let importsSettled = false;
  const importStallTimer = setTimeout(() => {
    if (!importsSettled) {
      showPieceError('Three.js failed to load from the CDN within 20s (slow or blocked network) — the piece itself was not the problem.');
    }
  }, 20000);

  try {
    const [mod, { OrbitControls }] = await Promise.all([
      import('https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js'),
      import('https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/controls/OrbitControls.js'),
    ]);
    importsSettled = true;
    clearTimeout(importStallTimer);
    window.THREE = mod;
    runPieceCode();
    if (typeof window.sketch !== 'function') return;
    const state = { scene: null, camera: null, renderer: null };
    let controls = null;
    let rafIds = [];
    let pieceDrivesOwnRender = false;
    let readySignaled = false;
    const ready = createReadyController(canvas);
    function signalThreeReadyOnce(source) {
      if (readySignaled) return;
      readySignaled = true;
      diag('signalThreeReadyOnce', { source: source || 'unknown' });
      ready.markRendered(source || 'unknown');
    }
    const instrumentedThree = { ...mod };
    instrumentedThree.Scene = class extends mod.Scene {
      constructor() { super(); state.scene = this; }
    };
    instrumentedThree.PerspectiveCamera = class extends mod.PerspectiveCamera {
      constructor(...args) { super(...args); state.camera = this; }
    };
    instrumentedThree.WebGLRenderer = class extends mod.WebGLRenderer {
      constructor(params) {
        super({ ...(params || {}), canvas, ...(window.PIECE_PRESERVE_DRAWING_BUFFER ? { preserveDrawingBuffer: true } : {}) });
        state.renderer = this;
        const _origSetSize = this.setSize.bind(this);
        this.setSize = (w, h) => _origSetSize(w, h, false);
        const _origRender = this.render.bind(this);
        this.render = (sc, cam) => {
          if (sc) state.scene = sc;
          if (cam) state.camera = cam;
          return _origRender(sc, cam);
        };
      }
    };
    const width = canvas.width || window.innerWidth || 1280;
    const height = canvas.height || window.innerHeight || 720;
    function autoFit() {
      if (!state.scene || !state.camera) return;
      const box = new mod.Box3();
      state.scene.traverse((obj) => {
        if (obj.isHelper || obj.isLight || obj.isCamera) return;
        if (obj.isPoints) return;
        if (obj.material) {
          const mat = obj.material;
          if (mat.side === 1 || (Array.isArray(mat) && mat.some(m => m.side === 1))) return;
        }
        const name = (obj.name || '').toLowerCase();
        if (name.includes('sky') || name.includes('background') || name.includes('env') || name.includes('floor') || name.includes('ground') || name.includes('grid') || name.includes('dome') || name.includes('space') || name.includes('star')) return;
        if ((obj.isMesh || obj.isLine || obj.isSprite) && obj.geometry) {
          obj.geometry.computeBoundingBox?.();
          if (obj.geometry.boundingBox) {
            const worldBox = obj.geometry.boundingBox.clone().applyMatrix4(obj.matrixWorld);
            const worldSize = new mod.Vector3();
            worldBox.getSize(worldSize);
            if (worldSize.x >= 30 || worldSize.y >= 30 || worldSize.z >= 30) return;
            if (obj.geometry.type === 'PlaneGeometry' || obj.geometry.type === 'PlaneBufferGeometry') {
              if (worldSize.x >= 15 || worldSize.y >= 15 || worldSize.z >= 15) return;
            }
            box.union(worldBox);
          }
        }
      });
      if (box.isEmpty()) return;
      const center = new mod.Vector3(); box.getCenter(center);
      const size = new mod.Vector3();   box.getSize(size);
      if (state.camera.position.lengthSq() > 0.01) {
        if (controls) { controls.target.copy(center); controls.update(); }
        return;
      }
      const maxDim = Math.max(size.x, size.y, size.z) || 1;
      const fov = state.camera.fov * (Math.PI / 180);
      const dist = Math.abs(maxDim / 2 / Math.tan(fov / 2)) * 0.63;
      state.camera.position.set(center.x + dist, center.y + dist * 0.4, center.z + dist);
      state.camera.lookAt(center);
      state.camera.updateMatrixWorld(true);
      if (controls) { controls.target.copy(center); controls.update(); }
    }
    function ensureFallbackLighting() {
      if (!state.scene?.traverse) return;
      let hasRealLight = false;
      let hasFallback = false;
      const fallbacks = [];
      state.scene.traverse((obj) => {
        if (!obj.isLight) return;
        if (obj.name?.startsWith('__viewer_fallback_')) { hasFallback = true; fallbacks.push(obj); }
        else hasRealLight = true;
      });
      if (hasRealLight) {
        fallbacks.forEach((obj) => state.scene.remove(obj));
        return;
      }
      if (hasFallback) return;
      const amb = new mod.AmbientLight(0xffffff, 0.7);
      amb.name = '__viewer_fallback_ambient__';
      state.scene.add(amb);
      const dir = new mod.DirectionalLight(0xffffff, 0.8);
      dir.position.set(5, 10, 7.5);
      dir.name = '__viewer_fallback_dir__';
      state.scene.add(dir);
    }
    function startFrame(handler) {
      pieceDrivesOwnRender = true;
      let count = 0;
      function tick() {
        count++;
        if (count <= 5) diag('piece-startFrame-tick', { count });
        try {
          handler(count);
        } catch (error) {
          // The piece's own render loop just died — hand rendering back to
          // the bootstrap loop so the canvas doesn't freeze on its last
          // frame with no further visual feedback (e.g. while dragging).
          pieceDrivesOwnRender = false;
          showPieceError(error);
          return;
        }
        signalThreeReadyOnce('piece-startFrame-tick-' + count);
        if (count === 15) autoFit();
        const id = requestAnimationFrame(tick);
        rafIds.push(id);
      }
      const id = requestAnimationFrame(tick);
      rafIds.push(id);
      return () => { rafIds.forEach((rafId) => cancelAnimationFrame(rafId)); rafIds = []; };
    }
    window.THREE = instrumentedThree;
    window.sketch({ THREE: instrumentedThree, canvas, startFrame, width, height, size: { width, height }, OrbitControls });
    ready.noteInlineMedia(document);
    ensureFallbackLighting();
    autoFit();

    if (state.camera && state.renderer) {
      controls = new OrbitControls(state.camera, canvas);
      controls.enableDamping = true;
      controls.enablePan = true;
      const threeRaycaster = new mod.Raycaster();
      const pointerState = new Map();
      let hadMultiTouchGesture = false;
      let threeNavLimit = 5;
      let keyNav = null;
      let animFromTarget = null;
      let animToTarget = null;
      let animFromCam = null;
      let animToCam = null;
      let animStart = 0;

      function getThreeNavigationLimit() {
        const box = new mod.Box3();
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
        const size = new mod.Vector3();
        box.getSize(size);
        return Math.max(size.x, size.z, 1) * 0.7;
      }

      function cancelThreeNavigationAnimation() {
        animFromTarget = animToTarget = animFromCam = animToCam = null;
        controls.enabled = true;
      }

      function moveThreeOrbitTo(hitPoint) {
        if (!hitPoint) return;
        const dx = hitPoint.x - controls.target.x;
        const dz = hitPoint.z - controls.target.z;
        const shift = new mod.Vector3(
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

      function activeTouchPointerCount() {
        let count = 0;
        pointerState.forEach((pointer) => {
          if (pointer.pointerType === 'touch') count += 1;
        });
        return count;
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
          new mod.Vector2(((event.clientX - rect.left) / rect.width) * 2 - 1, -((event.clientY - rect.top) / rect.height) * 2 + 1),
          state.camera,
        );

        let hitPoint = null;
        if (state.scene?.children?.length) {
          const hits = threeRaycaster.intersectObjects(state.scene.children, true);
          if (hits.length > 0) hitPoint = hits[0].point;
        }
        const floorPlane = new mod.Plane(new mod.Vector3(0, 1, 0), 0);
        const planeHit = new mod.Vector3();
        if (!hitPoint && threeRaycaster.ray.intersectPlane(floorPlane, planeHit)) {
          hitPoint = planeHit;
        }
        if (hitPoint) moveThreeOrbitTo(hitPoint);
      }

      const camDir = new mod.Vector3();
      state.camera.getWorldDirection(camDir);
      const camLen = state.camera.position.length();
      controls.target.copy(state.camera.position).addScaledVector(camDir, Math.max(camLen * 0.8, 3));
      autoFit();
      controls.update();
      threeNavLimit = getThreeNavigationLimit();
      keyNav = createKeyboardNavigation(controls, {
        container: canvas,
        speed: (act) => Math.max(0.05, act.target.distanceTo(act.object.position) * 0.03),
        minX: -threeNavLimit,
        maxX: threeNavLimit,
        minZ: 0.5,
        maxZ: threeNavLimit,
      });
      canvas.addEventListener('pointerdown', onThreePointerDown);
      canvas.addEventListener('pointermove', onThreePointerMove);
      canvas.addEventListener('pointerup', onThreePointerUp);
      canvas.addEventListener('pointercancel', clearThreePointer);
      canvas.addEventListener('lostpointercapture', clearThreePointer);

      let isOrbitActive = false;
      let userHasInteracted = false;
      controls.addEventListener('start', () => { isOrbitActive = true; });
      controls.addEventListener('end', () => { isOrbitActive = false; });

      // If the piece drives its own startFrame render loop (the documented
      // contract), this loop only needs to keep OrbitControls interactive —
      // re-rendering here too would just duplicate the piece's own draw call
      // every frame. The one exception: many pieces script their own ambient
      // camera motion every frame (e.g. camera.position.x = ...;
      // camera.lookAt(...)), and since only the piece's own render call
      // paints pixels while pieceDrivesOwnRender is true, that scripted view
      // always wins over whatever the user just dragged — drag looks
      // completely inert even though state.camera *is* updating correctly
      // underneath. Once the user has ever taken control of the camera,
      // latch a forced bootstrap render for the rest of the session so user
      // input reliably wins from then on (mirrors immersive-gallery.js).
      let consecutiveErrors = 0;
      let animateControlsTick = 0;
      const animateControls = () => {
        animateControlsTick++;
        if (animateControlsTick <= 5) diag('animateControls-tick', { count: animateControlsTick, pieceDrivesOwnRender, userHasInteracted });
        const id = requestAnimationFrame(animateControls);
        rafIds.push(id);
        try {
          ensureFallbackLighting();
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
          if (keyNav?.update()) {
            externalMotion = true;
          }
          controls.update();
          if (isOrbitActive || externalMotion) userHasInteracted = true;
          if (!pieceDrivesOwnRender || userHasInteracted) {
            state.renderer.render(state.scene, state.camera);
            signalThreeReadyOnce('animateControls-tick-' + animateControlsTick);
          }
          consecutiveErrors = 0;
        } catch (renderError) {
          consecutiveErrors++;
          if (consecutiveErrors === 1) showPieceError(renderError);
          if (consecutiveErrors >= 5) cancelAnimationFrame(id);
        }
      };
      animateControls();
    }

    window.addEventListener('resize', () => {
      sizeCanvas(canvas);
      if (state.renderer && state.camera) {
        state.camera.aspect = canvas.width / canvas.height;
        state.camera.updateProjectionMatrix();
        state.renderer.setSize(canvas.width, canvas.height, false);
      }
    });

    // Safety net: if the piece never renders via startFrame() or the
    // bootstrap's own animateControls() loop (e.g. it never constructs a
    // THREE.PerspectiveCamera/WebGLRenderer at all), signal anyway so
    // capture doesn't wait forever for a frame that will never come.
    diag('safety-net-reached', { readySignaled });
    signalThreeReadyOnce('safety-net');
  } catch (error) {
    clearTimeout(importStallTimer);
    showPieceError(error);
  }
}
if (PIECE_ENGINE === 'p5') {
  bootP5();
} else if (PIECE_ENGINE === 'three') {
  bootThree();
} else if (PIECE_ENGINE === 'c2') {
  bootC2();
} else if (PIECE_ENGINE === 'aframe') {
  bootAFrame();
} else {
  runPieceCode();
  if (typeof window.sketch === 'function') {
    try { window.sketch(); } catch (error) { showPieceError(error); }
  }
  const svg = document.querySelector('svg');
  if (svg) {
    const ready = createReadyController(svg);
    ready.noteInlineMedia(document);
    ready.markRendered('svg-document');
  }
}
