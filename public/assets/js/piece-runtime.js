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
function isCmsMediaPath(src) {
  return typeof src === 'string' && /^\/(?:image\/[0-9]+|api\/media-assets\/[0-9]+|media\/[A-Za-z0-9._~/%+-]+)(?:\?[A-Za-z0-9._~%=&+-]*)?$/.test(src);
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
function signalCanvasReady(canvas) {
  if (canvas) canvas.dataset.creatrReady = '1';
  try { window.parent.postMessage({ type: 'sketch-status', valid: true }, '*'); } catch (_) {}
}
function bootCanvasRuntime(extra) {
  runPieceCode();
  if (typeof window.sketch !== 'function') return;
  const canvas = findCanvas(PIECE_ENGINE === 'c2' ? 'c2-canvas' : 'scene');
  // For c2, the canvas now has a fixed intrinsic resolution (sizeCanvas())
  // regardless of surface — but plain width:100%;height:100% still
  // non-uniformly stretches that bitmap to fill whatever shape box each
  // surface's container happens to be (a phone's narrow/tall public-view
  // iframe vs. a 16:9 thumbnail vs. a squarer admin preview pane). Confirmed
  // in a real piece's code (id 72): a face shape with a fixed 1:1.5
  // width:height ratio in canvas-pixel-space looked square in one surface,
  // oval in another, and severely vertically elongated in a third — same
  // underlying drawing, different non-uniform CSS stretch per box.
  // object-fit:contain preserves the canvas's native aspect ratio when
  // scaling to fit any container, eliminating that distortion.
  canvas.style.cssText = PIECE_ENGINE === 'c2'
    ? 'display:block;width:100%;height:100%;object-fit:contain;object-position:center;'
    : 'display:block;width:100%;height:100%;';
  sizeCanvas(canvas);
  window.addEventListener('resize', () => sizeCanvas(canvas));
  // Only the piece's own first real draw means there's something worth
  // capturing — wrap the startFrame handed to the sketch so the readiness
  // signal fires after its first tick actually runs, not merely once
  // bootstrapping has handed control to the piece.
  let readySignaled = false;
  const mediaContext = PIECE_ENGINE === 'c2' ? canvas.getContext('2d') : null;
  const imageCache = new Map();
  function loadImage(src) {
    if (!isCmsMediaPath(src)) {
      showPieceError('C2 media helpers may only load same-origin CMS media paths such as /image/2, /media/..., or /api/media-assets/2.');
      return null;
    }
    if (imageCache.has(src)) return imageCache.get(src);
    const image = new Image();
    image.decoding = 'async';
    image.loading = 'eager';
    image.dataset.creatrLoaded = '0';
    image.onload = () => { image.dataset.creatrLoaded = '1'; };
    image.onerror = () => showPieceError('Could not load CMS media asset: ' + src);
    image.src = src;
    imageCache.set(src, image);
    return image;
  }
  function drawImage(image, x, y, width, height) {
    if (!mediaContext || !image || image.dataset?.creatrLoaded !== '1') return false;
    try {
      mediaContext.drawImage(image, x, y, width, height);
      return true;
    } catch (error) {
      showPieceError(error);
      return false;
    }
  }
  function instrumentedStartFrame(callback) {
    return startFrame((count) => {
      callback(count);
      if (!readySignaled) { readySignaled = true; signalCanvasReady(canvas); }
    });
  }
  try { window.sketch({ canvas, startFrame: instrumentedStartFrame, loadImage, drawImage, ...(extra || {}) }); } catch (error) { showPieceError(error); }
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
        // p5's own frameCount only increments after a real draw() call —
        // wait for that instead of signaling right after setup(), when the
        // canvas exists but is still blank.
        const waitForFirstDraw = () => {
          if (instance.frameCount >= 1) {
            signalCanvasReady(parent.querySelector('canvas') || document.querySelector('canvas'));
          } else {
            requestAnimationFrame(waitForFirstDraw);
          }
        };
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
      if (typeof window.sketch === 'function') {
        window.sketch({ AFRAME: window.AFRAME, scene, startFrame });
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
        canvas.dataset.creatrReady = '1';
        signalCanvasReady(canvas);
      }

      scene.addEventListener('renderstart', signalAFrameReadyOnce, { once: true });
      scene.addEventListener('loaded', disableMotionTracking, { once: true });
      scene.addEventListener('loaded', () => {
        disableMotionTracking();
        requestAnimationFrame(() => requestAnimationFrame(signalAFrameReadyOnce));
      }, { once: true });
      requestAnimationFrame(disableMotionTracking);
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
    function signalThreeReadyOnce(source) {
      if (readySignaled) return;
      readySignaled = true;
      diag('signalThreeReadyOnce', { source: source || 'unknown' });
      signalCanvasReady(canvas);
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
    ensureFallbackLighting();
    autoFit();

    if (state.camera && state.renderer) {
      controls = new OrbitControls(state.camera, canvas);
      controls.enableDamping = true;
      controls.enablePan = true;
      const camDir = new mod.Vector3();
      state.camera.getWorldDirection(camDir);
      const camLen = state.camera.position.length();
      controls.target.copy(state.camera.position).addScaledVector(camDir, Math.max(camLen * 0.8, 3));
      autoFit();
      controls.update();

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
          controls.update();
          if (isOrbitActive) userHasInteracted = true;
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
}
