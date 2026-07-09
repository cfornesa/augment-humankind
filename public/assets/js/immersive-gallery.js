import * as THREE from 'https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js';
import { OrbitControls } from 'https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/controls/OrbitControls.js';

// GLTFLoader/OBJLoader for uploaded 3D models are loaded via a CONTAINED dynamic
// import() (never a static top-level import), so a broken/missing loader source
// only disables model loading and never aborts this whole module — the same
// rule the gyro DeviceOrientationControls handling follows. Attached to each
// piece's instrumented THREE so generated code can call `new THREE.GLTFLoader()`
// without a forbidden import/fetch token. Loaded once, module-wide.
let _GLTFLoaderCtor = null;
let _OBJLoaderCtor = null;
try {
  ({ GLTFLoader: _GLTFLoaderCtor } = await import('https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/loaders/GLTFLoader.js'));
  ({ OBJLoader: _OBJLoaderCtor } = await import('https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/loaders/OBJLoader.js'));
} catch (_e) {
  // 3D model loaders unavailable; model-free pieces are unaffected.
}
// DeviceOrientationControls was removed from three.js's own examples/jsm
// bundle as of 0.160.0 (confirmed: that path 404s on the CDN). A static
// top-level import of a 404'ing module aborts loading this ENTIRE file —
// breaking Three.js, A-Frame, and p5/c2/svg immersive mounting alike, since
// they're all exported from here. Loaded lazily inside setupGyroControls()
// instead, wrapped in try/catch, so a missing/broken source only disables
// the gyro feature itself and never the rest of this module.

// Constants
const WALL_CENTER = new THREE.Vector3(0, 1.35, -1.08);
const TARGET_OFFSET = new THREE.Vector3(0, -0.16, 0);
const MAX_ART_WIDTH = 6.4;
const MAX_ART_HEIGHT = 4.6;
const PRESENTATION_MAX_ART_WIDTH = 5.2;
const PRESENTATION_MAX_ART_HEIGHT = 3.9;

export const NORMALIZED_PRESENTATION_GALLERY_PROFILE = {
  maxArtWidth: PRESENTATION_MAX_ART_WIDTH,
  maxArtHeight: PRESENTATION_MAX_ART_HEIGHT,
  framingMultiplier: 1.58,
  targetOffset: { x: 0, y: 0, z: 0 },
  cameraYOffset: 0.02,
};

const WALL_FRAME_ART_WIDTH = 2.2;
const WALL_FRAME_ART_HEIGHT = 1.65;
const WALL_FRAME_SLOT_WIDTH = 3.2;
const WALL_FRAME_SLOT_HEIGHT = 2.4;
const WALL_LABEL_HEIGHT = WALL_FRAME_ART_WIDTH * (80 / 512); // = 0.34375
const WALL_LABEL_GAP = 0.08;
const EXHIBIT_FLOOR_CLEARANCE = 0.2;
export const EXHIBIT_FRAME_ASPECT = WALL_FRAME_ART_WIDTH / WALL_FRAME_ART_HEIGHT;

function readViewVector(value) {
  if (!value || typeof value !== "object") return null;
  const x = Number(value.x);
  const y = Number(value.y);
  const z = Number(value.z);
  if (![x, y, z].every(Number.isFinite)) return null;
  return new THREE.Vector3(x, y, z);
}

function applyShellViewState(shell, viewState) {
  const camera = readViewVector(viewState?.camera);
  const target = readViewVector(viewState?.target);
  if (!shell?.camera || !shell?.controls || !camera || !target) return false;
  shell.camera.position.copy(camera);
  shell.controls.target.copy(target);
  shell.camera.lookAt(target);
  shell.camera.updateMatrixWorld?.(true);
  shell.controls.update();
  return true;
}

function shellViewState(shell, extra = {}) {
  const state = { ...extra };
  if (shell?.camera) {
    state.camera = {
      x: Number(shell.camera.position.x.toFixed(5)),
      y: Number(shell.camera.position.y.toFixed(5)),
      z: Number(shell.camera.position.z.toFixed(5)),
    };
  }
  if (shell?.controls?.target) {
    state.target = {
      x: Number(shell.controls.target.x.toFixed(5)),
      y: Number(shell.controls.target.y.toFixed(5)),
      z: Number(shell.controls.target.z.toFixed(5)),
    };
  }
  return state;
}

function encodeViewState(viewState) {
  try {
    const json = JSON.stringify(viewState || {});
    const bytes = new TextEncoder().encode(json);
    let binary = "";
    bytes.forEach((byte) => { binary += String.fromCharCode(byte); });
    return btoa(binary).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/g, "");
  } catch (_) {
    return "";
  }
}

export function computeMountedArtworkLayout(aspect, profile = {}) {
  const safeAspect = Math.max(aspect, 0.35);
  const maxArtWidth = profile.maxArtWidth ?? MAX_ART_WIDTH;
  const maxArtHeight = profile.maxArtHeight ?? MAX_ART_HEIGHT;
  let width = maxArtWidth;
  let height = width / safeAspect;
  if (height > maxArtHeight) {
    height = maxArtHeight;
    width = height * safeAspect;
  }
  return { width, height, aspect: safeAspect };
}

export function isCompactImmersiveViewport(width) {
  return width < 1024;
}

function computeMountedArtCenterY(artHeight) {
  return Math.max(WALL_CENTER.y, artHeight / 2 + EXHIBIT_FLOOR_CLEARANCE);
}

export function computeExhibitGridCenterY(rows) {
  const gridRows = Math.max(1, rows);
  const minBottomSlotCenterY = (WALL_FRAME_ART_HEIGHT / 2) + (WALL_LABEL_HEIGHT / 2) + WALL_LABEL_GAP + EXHIBIT_FLOOR_CLEARANCE;
  return Math.max(WALL_CENTER.y, ((gridRows - 1) / 2) * WALL_FRAME_SLOT_HEIGHT + minBottomSlotCenterY);
}

export function computeExhibitBottomVisibleY(rows) {
  const gridRows = Math.max(1, rows);
  const gridCenterY = computeExhibitGridCenterY(gridRows);
  const bottomSlotCenterY = gridCenterY - (((gridRows - 1) / 2) * WALL_FRAME_SLOT_HEIGHT);
  return bottomSlotCenterY - (WALL_FRAME_ART_HEIGHT / 2) - (WALL_LABEL_HEIGHT / 2) - WALL_LABEL_GAP;
}

export function createFloorClickNavigation(camera, controls, floorMesh, domElement, options = {}) {
  const { minZ = 0.5, maxZ = 8, maxX = 8, duration = 350 } = options;
  const raycaster = new THREE.Raycaster();
  let animFromTarget = null, animToTarget = null, animFromCam = null, animToCam = null, animStart = 0;
  let downX = 0, downY = 0;

  function onPointerDown(e) { downX = e.clientX; downY = e.clientY; }

  function onPointerUp(e) {
    if (Math.hypot(e.clientX - downX, e.clientY - downY) >= 6) return;
    const rect = domElement.getBoundingClientRect();
    raycaster.setFromCamera(
      new THREE.Vector2(((e.clientX - rect.left) / rect.width) * 2 - 1, -((e.clientY - rect.top) / rect.height) * 2 + 1),
      camera,
    );
    const hits = raycaster.intersectObject(floorMesh, false);
    if (!hits.length) return;
    const hit = hits[0].point;
    const shift = new THREE.Vector3(
      Math.max(-maxX, Math.min(maxX, hit.x)) - camera.position.x,
      0,
      Math.max(minZ, Math.min(maxZ, hit.z)) - camera.position.z,
    );
    if (shift.lengthSq() < 0.003) return;
    animFromTarget = controls.target.clone();
    animToTarget = animFromTarget.clone().add(shift);
    animFromCam = camera.position.clone();
    animToCam = animFromCam.clone().add(shift);
    animStart = performance.now();
    controls.enabled = false;
  }

  function update() {
    if (!animFromTarget || !animToTarget) return;
    const t = Math.min((performance.now() - animStart) / duration, 1);
    const eased = 1 - (1 - t) ** 3;
    controls.target.lerpVectors(animFromTarget, animToTarget, eased);
    camera.position.lerpVectors(animFromCam, animToCam, eased);
    controls.update();
    if (t >= 1) {
      controls.enabled = true;
      animFromTarget = animToTarget = animFromCam = animToCam = null;
    }
  }

  function dispose() {
    domElement.removeEventListener("pointerdown", onPointerDown);
    domElement.removeEventListener("pointerup", onPointerUp);
    controls.enabled = true;
  }

  domElement.addEventListener("pointerdown", onPointerDown);
  domElement.addEventListener("pointerup", onPointerUp);
  return { update, dispose };
}

export function computeOrbitKeyboardMotion(forward, keys, speed) {
  const activeKeys = keys instanceof Set ? keys : new Set(keys);
  let fwdScale = 0, rightScale = 0;
  if (activeKeys.has("ArrowUp")) fwdScale += speed;
  if (activeKeys.has("ArrowDown")) fwdScale -= speed;
  if (activeKeys.has("ArrowLeft")) rightScale -= speed;
  if (activeKeys.has("ArrowRight")) rightScale += speed;
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

export function createKeyboardNavigation(controls, options = {}) {
  const { speed = 0.05, minX = -8, maxX = 8, minY = -Infinity, maxY = Infinity, minZ = 0.5, maxZ = Infinity, container, isEnabled } = options;
  const keys = new Set();
  const target = window;

  function mapMovementKey(e) {
    if (e.code === "KeyW") return "ArrowUp";
    if (e.code === "KeyS") return "ArrowDown";
    if (e.code === "KeyA") return "ArrowLeft";
    if (e.code === "KeyD") return "ArrowRight";
    if (e.key === "ArrowLeft" || e.key === "ArrowRight" || e.key === "ArrowUp" || e.key === "ArrowDown") return e.key;
    return null;
  }

  function keyboardNavEnabled() {
    if (typeof isEnabled === "function") return isEnabled() !== false;
    if (!container) return true;
    return container.dataset.keyboardNavigationDisabled !== "true";
  }

  function shouldIgnoreKeyEventTarget(eventTarget) {
    if (!(eventTarget instanceof Element)) return false;
    if (eventTarget.tagName === "IFRAME") return true;
    if (eventTarget instanceof HTMLInputElement || eventTarget instanceof HTMLTextAreaElement || eventTarget instanceof HTMLSelectElement) return true;
    if (eventTarget.isContentEditable) return true;
    return Boolean(eventTarget.closest("[contenteditable=\"true\"], [contenteditable=\"\"], input, textarea, select"));
  }

  function onKeyDown(e) {
    const mappedKey = mapMovementKey(e);
    if (!mappedKey) return;
    if (!keyboardNavEnabled() || shouldIgnoreKeyEventTarget(e.target)) return;
    e.preventDefault();
    keys.add(mappedKey);
  }

  function onKeyUp(e) {
    const mappedKey = mapMovementKey(e);
    if (!mappedKey) return;
    if (!keyboardNavEnabled() || shouldIgnoreKeyEventTarget(e.target)) {
      keys.delete(mappedKey);
      return;
    }
    keys.delete(mappedKey);
    keys.delete(e.key);
  }

  function clearKeys() {
    keys.clear();
  }

  function onWindowBlur() {
    clearKeys();
  }

  const _fwd = new THREE.Vector3();
  // `speed` is tuned as a per-tick step at a steady 60fps. animateControls()'s
  // actual tick rate varies with device/browser/fullscreen state, so scale by
  // elapsed real time to keep navigation speed consistent regardless of frame
  // rate — without this, the exact same key-hold duration could move the
  // camera a little or a lot depending on how fast frames happen to tick.
  const TARGET_FRAME_MS = 1000 / 60;
  const MAX_FRAME_SCALE = 4; // cap the catch-up after a stutter/tab-background gap
  let lastUpdateAt = null;

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
    controls.object.getWorldDirection(_fwd);
    const resolvedSpeed = (typeof speed === "function" ? speed(controls) : speed) * frameScale;
    const { dx, dy, dz } = computeOrbitKeyboardMotion(_fwd, keys, resolvedSpeed);
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

  function onContainerClick() { container?.focus(); }
  if (container) {
    container.tabIndex = 0;
    container.addEventListener("click", onContainerClick, { passive: true });
  }

  function dispose() {
    target.removeEventListener("keydown", onKeyDown);
    target.removeEventListener("keyup", onKeyUp);
    window.removeEventListener("blur", onWindowBlur);
    if (container) container.removeEventListener("click", onContainerClick);
    clearKeys();
  }

  target.addEventListener("keydown", onKeyDown);
  target.addEventListener("keyup", onKeyUp);
  window.addEventListener("blur", onWindowBlur);
  return { update, dispose, clearKeys };
}

export function createMountedGalleryShell(stage, aspect, profile = {}) {
  const layout = computeMountedArtworkLayout(aspect, profile);
  const canvas = document.createElement("canvas");
  canvas.style.width = "100%";
  canvas.style.height = "100%";
  canvas.style.display = "block";
  canvas.style.touchAction = "none";
  const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  stage.innerHTML = "";
  stage.appendChild(canvas);

  const scene = new THREE.Scene();
  scene.background = new THREE.Color("#f1ece2");
  scene.fog = new THREE.Fog("#f1ece2", 13, 34);

  const camera = new THREE.PerspectiveCamera(40, 1, 0.1, 100);
  const controls = new OrbitControls(camera, canvas);
  controls.enableDamping = true;
  controls.enablePan = true;
  controls.minPolarAngle = 0.01;
  controls.maxPolarAngle = Math.PI - 0.01;

  scene.add(new THREE.AmbientLight(0xffffff, 1.38));
  const keyLight = new THREE.DirectionalLight(0xfffcf6, 0.9);
  keyLight.position.set(0.8, 4.8, 5.2);
  scene.add(keyLight);
  const fillLight = new THREE.DirectionalLight(0xf3ede2, 0.35);
  fillLight.position.set(-3.1, 2.4, 1.8);
  scene.add(fillLight);

  const floor = new THREE.Mesh(
    new THREE.PlaneGeometry(22, 18),
    new THREE.MeshStandardMaterial({ color: "#d7d0c4", roughness: 0.98, metalness: 0 }),
  );
  floor.rotation.x = -Math.PI / 2;
  floor.position.set(0, 0, 1.9);
  scene.add(floor);

  const backWall = new THREE.Mesh(
    new THREE.PlaneGeometry(20, 11),
    new THREE.MeshStandardMaterial({ color: "#f8f5ee", roughness: 1, metalness: 0 }),
  );
  backWall.position.set(0, 2.7, -1.35);
  scene.add(backWall);

  const framePanel = new THREE.Mesh(
    new THREE.BoxGeometry(layout.width + 0.3, layout.height + 0.3, 0.05),
    new THREE.MeshStandardMaterial({ color: "#fcfaf6", roughness: 0.96, metalness: 0 }),
  );
  const artCenterY = computeMountedArtCenterY(layout.height);
  framePanel.position.set(WALL_CENTER.x, artCenterY, -1.16);
  scene.add(framePanel);

  const artMaterial = new THREE.MeshBasicMaterial({ color: "#ffffff" });
  const artMesh = new THREE.Mesh(
    new THREE.PlaneGeometry(layout.width, layout.height),
    artMaterial,
  );
  artMesh.position.set(WALL_CENTER.x, artCenterY, WALL_CENTER.z);
  scene.add(artMesh);

  const frameMesh = new THREE.Mesh(
    new THREE.BoxGeometry(layout.width + 0.12, layout.height + 0.12, 0.03),
    new THREE.MeshStandardMaterial({ color: "#d8d1c7", roughness: 0.92, metalness: 0 }),
  );
  frameMesh.position.set(WALL_CENTER.x, artCenterY, -1.12);
  scene.add(frameMesh);

  const shell = {
    canvas,
    renderer,
    scene,
    camera,
    controls,
    floor,
    backWall,
    framePanel,
    artMesh,
    artMaterial,
    frameMesh,
    layout,
    profile,
    artCenterY,
  };
  fitMountedGalleryCamera(shell, stage);
  return shell;
}

export function updateMountedGalleryLayout(shell, aspect) {
  const layout = computeMountedArtworkLayout(aspect, shell.profile);
  shell.layout = layout;
  const artCenterY = computeMountedArtCenterY(layout.height);
  shell.artCenterY = artCenterY;
  shell.artMesh.geometry.dispose();
  shell.artMesh.geometry = new THREE.PlaneGeometry(layout.width, layout.height);
  shell.artMesh.position.y = artCenterY;
  shell.frameMesh.geometry.dispose();
  shell.frameMesh.geometry = new THREE.BoxGeometry(layout.width + 0.12, layout.height + 0.12, 0.03);
  shell.frameMesh.position.y = artCenterY;
  shell.framePanel.geometry.dispose();
  shell.framePanel.geometry = new THREE.BoxGeometry(layout.width + 0.3, layout.height + 0.3, 0.05);
  shell.framePanel.position.y = artCenterY;
}

export function fitMountedGalleryCamera(shell, stage, framingMultiplier = shell.profile.framingMultiplier ?? 1.28, resetCamera = true) {
  const width = stage.clientWidth >= 50 ? stage.clientWidth : window.innerWidth;
  const height = stage.clientHeight >= 50 ? stage.clientHeight : window.innerHeight;
  shell.camera.aspect = width / Math.max(height, 1);
  shell.camera.updateProjectionMatrix();
  shell.renderer.setSize(width, height, false);

  const artCenterY = shell.artCenterY ?? WALL_CENTER.y;
  const targetOffset = shell.profile.targetOffset
    ? new THREE.Vector3(shell.profile.targetOffset.x, shell.profile.targetOffset.y, shell.profile.targetOffset.z)
    : TARGET_OFFSET;
  const artCenter = new THREE.Vector3(WALL_CENTER.x, artCenterY, WALL_CENTER.z);
  const target = artCenter.clone().add(targetOffset);
  const verticalFov = THREE.MathUtils.degToRad(shell.camera.fov);
  const horizontalFov = 2 * Math.atan(Math.tan(verticalFov / 2) * shell.camera.aspect);
  const distanceForHeight = (shell.layout.height / 2) / Math.tan(verticalFov / 2);
  const distanceForWidth = (shell.layout.width / 2) / Math.tan(horizontalFov / 2);
  const distance = Math.max(distanceForHeight, distanceForWidth) * framingMultiplier;

  shell.controls.minDistance = Math.max(1.25, distance * 0.34);
  shell.controls.maxDistance = Math.max(18, distance * 5.5);
  shell.controls.minPolarAngle = 0.01;
  shell.controls.maxPolarAngle = Math.PI - 0.01;

  if (resetCamera) {
    shell.camera.position.set(
      WALL_CENTER.x,
      artCenterY + (shell.profile.cameraYOffset ?? 0.2),
      WALL_CENTER.z + distance,
    );
    shell.camera.lookAt(target);
    shell.controls.target.copy(target);
  }
  shell.controls.update();
}

export function createPresentationSurface(width, height, padding = 48) {
  const canvas = document.createElement("canvas");
  canvas.width = width;
  canvas.height = height;
  const context = canvas.getContext("2d");
  if (!context) {
    throw new Error("Presentation surface could not create a 2D context.");
  }
  return { canvas, context, width, height, padding };
}

export function drawContainedIntoPresentationSurface(surface, sourceWidth, sourceHeight, draw, background = "#f8f5ee") {
  const ctx = surface.context;
  ctx.save();
  ctx.clearRect(0, 0, surface.width, surface.height);
  ctx.fillStyle = background;
  ctx.fillRect(0, 0, surface.width, surface.height);

  const availableWidth = Math.max(surface.width - (surface.padding * 2), 1);
  const availableHeight = Math.max(surface.height - (surface.padding * 2), 1);
  const aspect = sourceWidth / Math.max(sourceHeight, 1);
  let drawWidth = availableWidth;
  let drawHeight = drawWidth / Math.max(aspect, 0.0001);
  if (drawHeight > availableHeight) {
    drawHeight = availableHeight;
    drawWidth = drawHeight * aspect;
  }
  const x = (surface.width - drawWidth) / 2;
  const y = (surface.height - drawHeight) / 2;
  draw(ctx, x, y, drawWidth, drawHeight);
  ctx.restore();
}

export function syncThreeRendererBackground(renderer, scene, fallbackColor) {
  if (!renderer?.setClearColor) return;
  const background = scene?.background;
  if (background) {
    renderer.setClearColor(background, 1);
    renderer.setClearAlpha?.(1);
    return;
  }
  if (fallbackColor != null) {
    renderer.setClearColor(fallbackColor, 1);
    renderer.setClearAlpha?.(1);
    return;
  }
  renderer.setClearAlpha?.(0);
}

export function computeThreeAutoFitView(center, size, aspect, fovDegrees, compactViewport) {
  const verticalFov = (fovDegrees * Math.PI) / 180;
  const horizontalFov = 2 * Math.atan(Math.tan(verticalFov / 2) * Math.max(aspect, 0.1));
  const fitWidth = Math.max(size.x, size.z, 1);
  const fitHeight = Math.max(size.y, size.z * 1.08, 1);
  const distanceForHeight = (fitHeight / 2) / Math.tan(verticalFov / 2);
  const distanceForWidth = (fitWidth / 2) / Math.tan(horizontalFov / 2);
  const cameraZ = (Math.max(distanceForHeight, distanceForWidth) * (compactViewport ? 1.46 : 1.34)) / 3.5;
  const targetY = center.y + (fitHeight * (compactViewport ? 0.08 : 0.12));
  const cameraY = targetY + (fitHeight * (compactViewport ? 0.02 : 0.04));
  return { camera: { x: center.x, y: cameraY, z: center.z + cameraZ }, target: { x: center.x, y: targetY, z: center.z } };
}

export function engineLabel(engine) {
  if (engine === "p5") return "P5.js";
  if (engine === "c2") return "C2.js";
  if (engine === "three") return "Three.js";
  if (engine === "svg") return "SVG";
  if (engine === "aframe") return "A-Frame";
  return engine;
}

export function getProgressiveExhibitLiveBudget(viewportWidth, staticMode = false) {
  if (staticMode) return 1;
  if (viewportWidth < 640) return 1;   // mobile
  if (viewportWidth < 1180) return 2;  // tablet
  return 3;                            // desktop
}

function createImmersiveViewerControls(stageEl, handlers = {}) {
  if (!handlers.onZoomSliderInput) return null;
  const root = document.createElement("div");
  root.className = "immersive-viewer-controls";
  root.setAttribute("aria-label", "Viewer controls");
  root.style.cssText = "position:absolute;inset:0;z-index:132;pointer-events:none;opacity:0.34;transition:opacity 180ms ease;";
  let fadeTimer = 0;
  const activateControls = () => {
    root.style.opacity = "1";
    clearTimeout(fadeTimer);
    fadeTimer = setTimeout(() => { root.style.opacity = "0.34"; }, 2400);
  };
  ["pointermove", "pointerdown", "focusin", "mouseenter"].forEach((eventName) => {
    root.addEventListener(eventName, activateControls);
  });

  const leftEdge = document.createElement("div");
  leftEdge.className = "immersive-edge-hud immersive-edge-hud-left";
  leftEdge.style.cssText = "position:absolute;left:calc(0.55rem + env(safe-area-inset-left));top:50%;transform:translateY(-50%);display:flex;flex-direction:column;align-items:center;gap:0.55rem;pointer-events:auto;";
  root.appendChild(leftEdge);

  const movePad = document.createElement("div");
  movePad.className = "immersive-move-pad";
  movePad.style.cssText = "display:grid;grid-template-columns:repeat(3,2.25rem);grid-template-rows:repeat(3,2.25rem);gap:0.28rem;align-items:center;justify-items:center;";

  const floatPad = document.createElement("div");
  floatPad.className = "immersive-float-pad";
  floatPad.style.cssText = "display:flex;gap:0.35rem;";

  const zoomEdge = document.createElement("div");
  zoomEdge.className = "immersive-edge-hud immersive-edge-hud-right";
  zoomEdge.style.cssText = "position:absolute;right:calc(1rem + env(safe-area-inset-right));top:50%;width:2.75rem;transform:translateY(-50%);display:flex;flex-direction:column;align-items:center;gap:0.45rem;pointer-events:auto;";

  function createButton(parent, label, ariaLabel, onClick, options = {}) {
    const btn = document.createElement("button");
    let holdTimer = 0;
    let usedPointer = false;
    btn.type = "button";
    btn.textContent = label;
    btn.setAttribute("aria-label", ariaLabel);
    btn.style.cssText = "display:inline-flex;align-items:center;justify-content:center;height:2.25rem;width:2.25rem;border:1px solid rgba(255,255,255,0.2);background:rgba(0,0,0,0.42);color:#fff;border-radius:9999px;box-shadow:0 4px 12px rgba(0,0,0,0.45);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);font-size:1rem;font-weight:900;line-height:1;cursor:pointer;touch-action:none;user-select:none;-webkit-user-select:none;";
    if (options.gridArea) btn.style.gridArea = options.gridArea;
    const stopHold = () => {
      if (holdTimer) {
        clearInterval(holdTimer);
        holdTimer = 0;
      }
    };
    btn.addEventListener("pointerdown", (event) => {
      event.preventDefault();
      event.stopPropagation();
      usedPointer = true;
      onClick();
      if (options.hold) {
        stopHold();
        holdTimer = setInterval(onClick, 90);
      }
      try { btn.setPointerCapture(event.pointerId); } catch (_) {}
    });
    btn.addEventListener("pointerup", stopHold);
    btn.addEventListener("pointercancel", stopHold);
    btn.addEventListener("lostpointercapture", stopHold);
    btn.addEventListener("click", (event) => {
      event.preventDefault();
      event.stopPropagation();
      if (usedPointer) {
        usedPointer = false;
        return;
      }
      onClick();
    });
    parent.appendChild(btn);
    return btn;
  }

  if (handlers.onMoveForward && handlers.onMoveBackward && handlers.onMoveLeft && handlers.onMoveRight) {
    createButton(movePad, "^", "Move forward", handlers.onMoveForward, { hold: true, gridArea: "1 / 2" });
    createButton(movePad, "<", "Move left", handlers.onMoveLeft, { hold: true, gridArea: "2 / 1" });
    createButton(movePad, ">", "Move right", handlers.onMoveRight, { hold: true, gridArea: "2 / 3" });
    createButton(movePad, "v", "Move backward", handlers.onMoveBackward, { hold: true, gridArea: "3 / 2" });
    leftEdge.appendChild(movePad);
  }
  if (handlers.onFloatUp && handlers.onFloatDown) {
    createButton(floatPad, "Up", "Float up", handlers.onFloatUp, { hold: true });
    createButton(floatPad, "Dn", "Float down", handlers.onFloatDown, { hold: true });
    leftEdge.appendChild(floatPad);
  }

  const zoomIcon = document.createElement("span");
  zoomIcon.className = "immersive-zoom-icon";
  zoomIcon.setAttribute("aria-hidden", "true");
  zoomIcon.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="6"></circle><path d="m16 16 4 4"></path></svg>';
  zoomIcon.style.cssText = "display:inline-flex;align-items:center;justify-content:center;width:2.75rem;height:2.25rem;color:rgba(255,255,255,0.82);filter:drop-shadow(0 2px 6px rgba(0,0,0,0.65));";
  const sliderSlot = document.createElement("div");
  sliderSlot.className = "immersive-zoom-slider-slot";
  sliderSlot.style.cssText = "position:relative;width:2.75rem;height:9rem;display:flex;align-items:center;justify-content:center;";
  const zoomSlider = document.createElement("input");
  zoomSlider.type = "range";
  zoomSlider.min = "0";
  zoomSlider.max = "100";
  zoomSlider.value = String(handlers.initialZoomValue ?? 50);
  zoomSlider.setAttribute("aria-label", "Zoom");
  zoomSlider.className = "immersive-zoom-slider";
  zoomSlider.style.cssText = "position:absolute;left:50%;top:50%;width:9rem;height:2rem;transform:translate(-50%,-50%) rotate(-90deg);transform-origin:center;accent-color:#fff;cursor:pointer;touch-action:none;";
  zoomSlider.addEventListener("pointerdown", (event) => {
    event.stopPropagation();
    activateControls();
  });
  zoomSlider.addEventListener("input", (event) => {
    event.stopPropagation();
    handlers.onZoomSliderInput(Number(zoomSlider.value));
    activateControls();
  });
  sliderSlot.appendChild(zoomSlider);
  zoomEdge.appendChild(zoomIcon);
  zoomEdge.appendChild(sliderSlot);
  root.appendChild(zoomEdge);
  stageEl.appendChild(root);
  return {
    setZoomValue(value) {
      if (Number.isFinite(value)) zoomSlider.value = String(Math.max(0, Math.min(100, value)));
    },
    remove() {
      clearTimeout(fadeTimer);
      root.remove();
    },
  };
}

function createSharedGyroController(stageEl, camera, options = {}) {
  if (!stageEl || !camera) {
    return {
      update() {},
      dispose() {},
      isActive() { return false; },
      async setup() { return false; },
      requestCalibration() {},
    };
  }

  let deviceControls = null;
  let gyroActive = false;
  let gyroNeedsCalibration = false;
  let gyroToggleBtn = null;
  let enableMotionBtn = null;
  let disposed = false;
  let activationPromise = null;
  const baselineQuat = new THREE.Quaternion();
  const yawProbe = new THREE.Vector3();

  function requestCalibration() {
    baselineQuat.copy(camera.quaternion);
    gyroNeedsCalibration = true;
  }

  function hasDeviceOrientationAngles(deviceControlsRef) {
    const orientation = deviceControlsRef?.deviceOrientation;
    return !!orientation && (
      orientation.alpha !== null && orientation.alpha !== undefined
      || orientation.beta !== null && orientation.beta !== undefined
      || orientation.gamma !== null && orientation.gamma !== undefined
    );
  }

  function yawFromQuaternion(quaternion) {
    yawProbe.set(0, 0, -1).applyQuaternion(quaternion);
    yawProbe.y = 0;
    if (yawProbe.lengthSq() < 1e-8) return null;
    yawProbe.normalize();
    return Math.atan2(yawProbe.x, yawProbe.z);
  }

  function normalizeAngleRadians(angle) {
    let normalized = angle;
    while (normalized > Math.PI) normalized -= Math.PI * 2;
    while (normalized < -Math.PI) normalized += Math.PI * 2;
    return normalized;
  }

  function calibrateGyroToCurrentView() {
    if (!gyroNeedsCalibration || !deviceControls || !hasDeviceOrientationAngles(deviceControls)) return;

    const desiredYaw = yawFromQuaternion(baselineQuat);
    if (desiredYaw === null) {
      gyroNeedsCalibration = false;
      return;
    }

    deviceControls.alphaOffset = 0;
    deviceControls.update();
    const rawYaw = yawFromQuaternion(camera.quaternion);
    if (rawYaw === null) {
      camera.quaternion.copy(baselineQuat);
      gyroNeedsCalibration = false;
      return;
    }

    const yawDelta = normalizeAngleRadians(desiredYaw - rawYaw);
    let bestOffset = yawDelta;
    let bestError = Infinity;
    [yawDelta, -yawDelta].forEach((candidateOffset) => {
      deviceControls.alphaOffset = candidateOffset;
      deviceControls.update();
      const candidateYaw = yawFromQuaternion(camera.quaternion);
      if (candidateYaw === null) return;
      const candidateError = Math.abs(normalizeAngleRadians(desiredYaw - candidateYaw));
      if (candidateError < bestError) {
        bestError = candidateError;
        bestOffset = candidateOffset;
      }
    });

    deviceControls.alphaOffset = bestOffset;
    gyroNeedsCalibration = false;
  }

  function setGyroActive(nextActive) {
    gyroActive = nextActive;
    gyroToggleBtn?.setAttribute("aria-pressed", String(gyroActive));
    if (gyroToggleBtn) {
      gyroToggleBtn.style.background = gyroActive ? "rgba(89,184,201,0.85)" : "rgba(0,0,0,0.55)";
    }
    options.onStateChange?.(gyroActive);
  }

  function createGyroToggleButton() {
    const btn = document.createElement("button");
    btn.type = "button";
    btn.setAttribute("aria-label", "Toggle gyroscope camera control");
    btn.textContent = "⟲";
    // The stage toolbar reserves a slot so the gyro toggle never overlaps the
    // top-left control group; stages without a toolbar keep the absolute
    // top-left placement.
    const slot = document.querySelector("[data-immersive-gyro-slot]");
    const placement = slot
      ? "position:static;"
      : "position:absolute;top:calc(0.75rem + env(safe-area-inset-top));left:calc(0.75rem + env(safe-area-inset-left));z-index:130;";
    btn.style.cssText = placement + "display:inline-flex;align-items:center;justify-content:center;height:2.5rem;width:2.5rem;border:1px solid rgba(255,255,255,0.15);background:rgba(89,184,201,0.85);color:#fff;border-radius:9999px;font-size:1.1rem;cursor:pointer;";
    btn.addEventListener("click", () => {
      const nextActive = !gyroActive;
      if (nextActive) {
        requestCalibration();
        options.onActivated?.();
      }
      setGyroActive(nextActive);
    });
    (slot || stageEl).appendChild(btn);
    return btn;
  }

  function createEnableMotionButton(onGranted) {
    const btn = document.createElement("button");
    btn.type = "button";
    btn.textContent = "Enable Motion Controls";
    btn.style.cssText = "position:absolute;bottom:calc(1rem + env(safe-area-inset-bottom));left:50%;transform:translateX(-50%);z-index:130;border:1px solid rgba(255,255,255,0.15);background:rgba(0,0,0,0.65);color:#fff;padding:0.6rem 1.1rem;border-radius:9999px;font-size:0.85rem;cursor:pointer;";
    btn.addEventListener("click", async () => {
      try {
        const result = await DeviceOrientationEvent.requestPermission();
        if (result === "granted") {
          btn.remove();
          if (enableMotionBtn === btn) enableMotionBtn = null;
          await onGranted();
        }
      } catch (_) {
        // Leave the button in place so the user can try again later.
      }
    });
    stageEl.appendChild(btn);
    return btn;
  }

  function waitForRealOrientationData(timeoutMs = 1500) {
    return new Promise((resolve) => {
      const onFirstEvent = (event) => {
        if (event.alpha !== null || event.beta !== null || event.gamma !== null) {
          window.removeEventListener("deviceorientation", onFirstEvent);
          resolve(true);
        }
      };
      window.addEventListener("deviceorientation", onFirstEvent);
      setTimeout(() => {
        window.removeEventListener("deviceorientation", onFirstEvent);
        resolve(false);
      }, timeoutMs);
    });
  }

  async function activateGyro() {
    if (disposed) return false;
    if (activationPromise) return activationPromise;
    activationPromise = (async () => {
      if (!deviceControls) {
        const { DeviceOrientationControls } = await import("/assets/js/three-device-orientation-controls.js");
        deviceControls = new DeviceOrientationControls(camera);
      }
      requestCalibration();
      if (!gyroToggleBtn) gyroToggleBtn = createGyroToggleButton();
      options.onActivated?.();
      setGyroActive(true);
      return true;
    })();
    try {
      return await activationPromise;
    } finally {
      activationPromise = null;
    }
  }

  async function setup() {
    if (disposed || typeof window.DeviceOrientationEvent === "undefined") return false;
    try {
      if (typeof DeviceOrientationEvent.requestPermission === "function") {
        try {
          const result = await DeviceOrientationEvent.requestPermission();
          if (result === "granted") {
            return await activateGyro();
          }
        } catch (_) {
          // Fall through to the manual permission button.
        }
        if (!enableMotionBtn) {
          enableMotionBtn = createEnableMotionButton(activateGyro);
        }
        return false;
      }

      const hasRealOrientationData = await waitForRealOrientationData();
      if (hasRealOrientationData) {
        return await activateGyro();
      }
    } catch (_) {
      // A failed dynamic import or unsupported browser should only disable motion.
    }
    return false;
  }

  return {
    update() {
      if (!gyroActive || !deviceControls || disposed) return;
      if (hasDeviceOrientationAngles(deviceControls)) {
        calibrateGyroToCurrentView();
        deviceControls.update();
      }
    },
    dispose() {
      disposed = true;
      enableMotionBtn?.remove();
      gyroToggleBtn?.remove();
      deviceControls?.disconnect?.();
      enableMotionBtn = null;
      gyroToggleBtn = null;
      deviceControls = null;
      activationPromise = null;
      gyroActive = false;
      gyroNeedsCalibration = false;
    },
    isActive() {
      return gyroActive;
    },
    setup,
    requestCalibration,
  };
}

export function createReadOnlyFullViewOverlay(stageEl, items, options = {}) {
  const overlayItems = Array.isArray(items) ? items : [];
  const singleItemMode = options.layout === "single";
  // Piece full view keeps download controls in the stage toolbar instead of
  // duplicating them inside the overlay shell.
  const showDownloadControls = options.showDownloadControls !== false;
  const host = stageEl.parentElement ?? stageEl;
  if (!overlayItems.length) {
    return {
      openAt() {},
      close() {},
      remove() {},
      isOpen() { return false; },
    };
  }

  const root = document.createElement("div");
  root.hidden = true;
  root.setAttribute("aria-hidden", "true");
  root.style.cssText = "position:fixed;inset:0;z-index:145;background:rgba(5,7,15,0.92);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);display:none;align-items:stretch;justify-content:center;padding:1rem;pointer-events:auto;";

  const shell = document.createElement("div");
  shell.style.cssText = "position:relative;display:flex;flex-direction:column;width:min(100%,1100px);height:100%;max-height:100%;border:1px solid rgba(255,255,255,0.14);border-radius:1.2rem;background:rgba(9,14,24,0.96);box-shadow:0 24px 80px rgba(0,0,0,0.35);overflow:hidden;";
  root.appendChild(shell);

  const topBar = document.createElement("div");
  topBar.style.cssText = "display:flex;align-items:flex-start;justify-content:space-between;gap:0.75rem;padding:0.9rem 1rem;border-bottom:1px solid rgba(255,255,255,0.08);";
  shell.appendChild(topBar);

  const metaWrap = document.createElement("div");
  metaWrap.style.cssText = "min-width:0;display:flex;flex-direction:column;gap:0.2rem;";
  topBar.appendChild(metaWrap);

  const titleEl = document.createElement("div");
  titleEl.style.cssText = "font-size:1rem;font-weight:700;color:#f8f5ee;line-height:1.3;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;";
  metaWrap.appendChild(titleEl);

  const subtitleEl = document.createElement("div");
  subtitleEl.style.cssText = "font-size:0.76rem;letter-spacing:0.12em;text-transform:uppercase;color:rgba(255,255,255,0.54);";
  metaWrap.appendChild(subtitleEl);

  function chromeButton(label, ariaLabel) {
    const btn = document.createElement("button");
    btn.type = "button";
    btn.textContent = label;
    btn.setAttribute("aria-label", ariaLabel || label);
    btn.style.cssText = "display:inline-flex;align-items:center;justify-content:center;min-width:2.75rem;height:2.75rem;padding:0 0.85rem;border:1px solid rgba(255,255,255,0.16);border-radius:9999px;background:rgba(255,255,255,0.06);color:#fff;font-size:0.9rem;font-weight:600;cursor:pointer;";
    btn.addEventListener("pointerenter", () => { btn.style.background = "rgba(255,255,255,0.14)"; });
    btn.addEventListener("pointerleave", () => { btn.style.background = "rgba(255,255,255,0.06)"; });
    return btn;
  }

  const controlsWrap = document.createElement("div");
  controlsWrap.style.cssText = "display:flex;align-items:center;gap:0.5rem;flex-shrink:0;";
  topBar.appendChild(controlsWrap);

  const downloadPieceLink = document.createElement("a");
  downloadPieceLink.textContent = "Download Piece";
  downloadPieceLink.setAttribute("download", "");
  downloadPieceLink.style.cssText = "display:none;align-items:center;justify-content:center;min-height:2.75rem;padding:0 0.85rem;border:1px solid rgba(255,255,255,0.16);border-radius:9999px;background:rgba(255,255,255,0.06);color:#fff;font-size:0.9rem;font-weight:600;text-decoration:none;";
  controlsWrap.appendChild(downloadPieceLink);

  const downloadPngBtn = chromeButton("PNG", "Download PNG");
  controlsWrap.appendChild(downloadPngBtn);

  const prevBtn = chromeButton("Prev", "Show previous work");
  const nextBtn = chromeButton("Next", "Show next work");
  const closeBtn = chromeButton("×", "Close full view");
  controlsWrap.appendChild(prevBtn);
  controlsWrap.appendChild(nextBtn);
  controlsWrap.appendChild(closeBtn);

  const contentWrap = document.createElement("div");
  contentWrap.style.cssText = "flex:1;min-height:0;display:flex;align-items:center;justify-content:center;padding:1rem 1rem 0.75rem;";
  shell.appendChild(contentWrap);

  const footer = document.createElement("div");
  footer.style.cssText = "flex:0 0 auto;max-height:min(36vh,18rem);overflow:auto;padding:0 1rem 1rem;";
  shell.appendChild(footer);

  const descriptionEl = document.createElement("p");
  descriptionEl.style.cssText = "margin:0;color:rgba(255,255,255,0.78);font-size:0.95rem;line-height:1.6;display:none;";
  footer.appendChild(descriptionEl);

  let currentStartIndex = 0;
  let priorKeyboardDisabled = false;
  let previouslyFocused = null;
  let currentRenderedItem = null;

  function normalizeIndex(index) {
    const total = overlayItems.length;
    if (!total) return 0;
    return ((index % total) + total) % total;
  }

  function getColumns() {
    if (singleItemMode) return 1;
    return Math.min(getProgressiveExhibitLiveBudget(window.innerWidth), overlayItems.length);
  }

  function getStepSize() {
    return singleItemMode ? 1 : getColumns();
  }

  function clearViewport() {
    contentWrap.replaceChildren();
  }

  function renderItemIntoEl(item, containerEl) {
    if (item.type === "iframe" && item.srcdoc) {
      const interactive = item.interactive === true;
      const iframe = document.createElement("iframe");
      iframe.setAttribute("title", item.title || "Full view");
      iframe.setAttribute("sandbox", "allow-scripts allow-same-origin");
      iframe.setAttribute("tabindex", interactive ? "0" : "-1");
      iframe.srcdoc = item.srcdoc;
      iframe.style.cssText = "width:100%;height:100%;border:0;display:block;pointer-events:" + (interactive ? "auto" : "none") + ";background:#05070f;";
      containerEl.appendChild(iframe);
    } else if (item.type === "image" && item.src) {
      const image = document.createElement("img");
      image.src = item.src;
      image.alt = item.alt || item.title || "";
      image.style.cssText = "max-width:100%;max-height:100%;width:auto;height:auto;display:block;object-fit:contain;background:#05070f;margin:auto;";
      containerEl.appendChild(image);
    } else {
      const fallback = document.createElement("div");
      fallback.textContent = item.title || "Unable to preview this work.";
      fallback.style.cssText = "padding:1rem;color:#fff;font-weight:600;";
      containerEl.appendChild(fallback);
    }
  }

  function getActiveItem() {
    return overlayItems[normalizeIndex(currentStartIndex)] || null;
  }

  async function captureActiveItemPng() {
    const dl = window.CreatrPieceDownload;
    if (!dl) {
      throw new Error("PNG download tools are not available yet.");
    }
    const viewport = contentWrap.querySelector("[data-full-view-viewport]");
    const iframe = viewport?.querySelector("iframe");
    if (iframe?.contentDocument) {
      const iframeDoc = iframe.contentDocument;
      const exportCapture = iframe.contentWindow?.__creatrExportCapture;
      const surface = exportCapture && typeof exportCapture.getSurface === "function"
        ? await exportCapture.getSurface()
        : await dl.waitForCaptureReady(iframeDoc);
      return surface.type === "svg"
        ? dl.exportSvg(surface.node)
        : (((exportCapture && exportCapture.requiresCanvasValidation === true)
            || (iframeDoc.querySelector("a-scene#scene, a-scene") && surface.node instanceof HTMLCanvasElement))
          ? dl.exportCanvasWithValidation(iframeDoc, surface.node)
          : dl.exportCanvas(surface.node));
    }
    const image = viewport?.querySelector("img");
    if (image instanceof HTMLImageElement && image.complete && image.naturalWidth > 0) {
      const canvas = document.createElement("canvas");
      canvas.width = image.naturalWidth;
      canvas.height = image.naturalHeight;
      const context = canvas.getContext("2d");
      if (!context) throw new Error("PNG export is unavailable in this browser.");
      context.drawImage(image, 0, 0);
      return canvas;
    }
    throw new Error("No downloadable slide surface is available yet.");
  }

  function syncDownloadControls() {
    const item = getActiveItem();
    currentRenderedItem = item;
    const downloadUrl = typeof item?.download_url === "string" ? item.download_url : "";
    downloadPieceLink.href = downloadUrl;
    downloadPieceLink.style.display = showDownloadControls && downloadUrl ? "inline-flex" : "none";
    downloadPngBtn.style.display = showDownloadControls && (item?.type === "iframe" || item?.type === "image") ? "" : "none";
    if (typeof options.onActiveItemChange === "function") {
      options.onActiveItemChange(item, normalizeIndex(currentStartIndex));
    }
  }

  function renderCurrentItems() {
    clearViewport();
    const cols = getColumns();
    const multi = overlayItems.length > 1;
    prevBtn.style.display = multi ? "" : "none";
    nextBtn.style.display = multi ? "" : "none";

    if (cols === 1) {
      contentWrap.style.cssText = "flex:1;min-height:0;display:flex;align-items:center;justify-content:center;padding:1rem 1rem 0.75rem;";
      const viewport = document.createElement("div");
      viewport.dataset.fullViewViewport = "true";
      viewport.style.cssText = "width:100%;height:100%;display:flex;align-items:center;justify-content:center;border-radius:1rem;background:#05070f;overflow:hidden;";
      contentWrap.appendChild(viewport);
      const item = overlayItems[normalizeIndex(currentStartIndex)];
      renderItemIntoEl(item, viewport);
      titleEl.textContent = item.title || "";
      titleEl.style.display = titleEl.textContent ? "" : "none";
      subtitleEl.textContent = item.subtitle || "";
      subtitleEl.style.display = subtitleEl.textContent ? "" : "none";
      metaWrap.style.display = (titleEl.textContent || subtitleEl.textContent) ? "" : "none";
      descriptionEl.textContent = item.description || "";
      descriptionEl.style.display = descriptionEl.textContent ? "" : "none";
      syncDownloadControls();
    } else {
      contentWrap.style.cssText = `flex:1;min-height:0;display:grid;grid-template-columns:repeat(${cols},1fr);gap:0.75rem;padding:1rem 1rem 0.75rem;`;
      titleEl.textContent = "";
      subtitleEl.style.display = "none";
      descriptionEl.style.display = "none";
      syncDownloadControls();
      for (let i = 0; i < cols; i++) {
        const item = overlayItems[normalizeIndex(currentStartIndex + i)];
        const slot = document.createElement("div");
        slot.style.cssText = "display:flex;flex-direction:column;min-height:0;overflow:hidden;border-radius:0.75rem;background:#05070f;";
        const pieceEl = document.createElement("div");
        pieceEl.style.cssText = "flex:1;min-height:0;overflow:hidden;";
        renderItemIntoEl(item, pieceEl);
        slot.appendChild(pieceEl);
        const label = document.createElement("div");
        label.style.cssText = "flex:0 0 auto;padding:0.4rem 0.6rem;border-top:1px solid rgba(255,255,255,0.08);";
        const titleSpan = document.createElement("div");
        titleSpan.textContent = item.title || "";
        titleSpan.style.cssText = "font-size:0.8rem;font-weight:600;color:#f8f5ee;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;";
        label.appendChild(titleSpan);
        if (item.subtitle) {
          const subtitleSpan = document.createElement("div");
          subtitleSpan.textContent = item.subtitle;
          subtitleSpan.style.cssText = "font-size:0.68rem;letter-spacing:0.1em;text-transform:uppercase;color:rgba(255,255,255,0.48);";
          label.appendChild(subtitleSpan);
        }
        slot.appendChild(label);
        contentWrap.appendChild(slot);
      }
    }
  }

  let blockCloseUntil = 0;

  // The shared stage toolbar's sound button lives outside this overlay (a
  // sibling in the persistent stage chrome), so it stays in the DOM and
  // clickable behind the read-only modal unless we hide it explicitly here.
  // It belongs to the live interactive scene, not this static/read-only
  // slide view — sound stays exactly as the user set it, just not exposed
  // as a control while this overlay is up.
  function setSharedSoundToggleHidden(hide) {
    const toggleBtn = document.querySelector("[data-immersive-sound-toggle]");
    if (!toggleBtn) return;
    toggleBtn.style.display = hide ? "none" : "";
  }

  function openAt(index = 0) {
    currentStartIndex = normalizeIndex(index);
    priorKeyboardDisabled = stageEl.dataset.keyboardNavigationDisabled === "true";
    stageEl.dataset.keyboardNavigationDisabled = "true";
    previouslyFocused = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    renderCurrentItems();
    root.hidden = false;
    root.setAttribute("aria-hidden", "false");
    root.style.display = "flex";
    setSharedSoundToggleHidden(true);
    // Absorb any ghost/synthetic click events the browser fires after a
    // touch/pointerup. Must be set BEFORE the close handlers can fire.
    blockCloseUntil = Date.now() + 600;
    closeBtn.focus();
  }

  function close() {
    if (root.hidden && root.style.display === "none") return;
    root.hidden = true;
    root.setAttribute("aria-hidden", "true");
    root.style.display = "none";
    setSharedSoundToggleHidden(false);
    clearViewport();
    if (priorKeyboardDisabled) {
      stageEl.dataset.keyboardNavigationDisabled = "true";
    } else {
      delete stageEl.dataset.keyboardNavigationDisabled;
    }
    requestAnimationFrame(() => {
      if (previouslyFocused?.focus) {
        previouslyFocused.focus();
      } else {
        stageEl.focus?.();
      }
    });
  }

  function showPrevious() {
    currentStartIndex = normalizeIndex(currentStartIndex - getStepSize());
    renderCurrentItems();
  }

  function showNext() {
    currentStartIndex = normalizeIndex(currentStartIndex + getStepSize());
    renderCurrentItems();
  }

  function isOpen() {
    return root.hidden === false && root.style.display !== "none";
  }

  function onWindowKeyDown(event) {
    if (!isOpen()) return;
    if (event.key === "Escape") {
      event.preventDefault();
      close();
      return;
    }
    if (overlayItems.length > 1 && event.key === "ArrowLeft") {
      event.preventDefault();
      showPrevious();
      return;
    }
    if (overlayItems.length > 1 && event.key === "ArrowRight") {
      event.preventDefault();
      showNext();
    }
  }

  prevBtn.addEventListener("click", showPrevious);
  nextBtn.addEventListener("click", showNext);
  closeBtn.addEventListener("click", close);
  downloadPngBtn.addEventListener("click", async () => {
    if (downloadPngBtn.disabled) return;
    const originalLabel = downloadPngBtn.textContent;
    const item = getActiveItem();
    const filename = item?.png_filename || item?.title?.toLowerCase?.().replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "") + ".png" || "piece.png";
    downloadPngBtn.disabled = true;
    downloadPngBtn.textContent = "Preparing...";
    try {
      const dl = window.CreatrPieceDownload;
      const canvas = await captureActiveItemPng();
      const blob = await dl.canvasToBlob(canvas);
      dl.downloadBlob(blob, filename);
      downloadPngBtn.textContent = originalLabel;
    } catch (error) {
      downloadPngBtn.textContent = "Try Again";
      window.setTimeout(() => {
        if (!downloadPngBtn.disabled) downloadPngBtn.textContent = originalLabel;
      }, 1800);
      console.error(error);
    } finally {
      downloadPngBtn.disabled = false;
    }
  });
  root.addEventListener("click", (event) => {
    if (Date.now() < blockCloseUntil) return;   // absorb ghost clicks
    if (event.target === root) close();
  });
  shell.addEventListener("click", (event) => {
    event.stopPropagation();
  });
  let resizeRafId = 0;
  const onResize = () => {
    cancelAnimationFrame(resizeRafId);
    resizeRafId = requestAnimationFrame(() => { if (isOpen()) renderCurrentItems(); });
  };
  window.addEventListener("keydown", onWindowKeyDown);
  window.addEventListener("resize", onResize);
  host.appendChild(root);

  return {
    openAt,
    close,
    remove() {
      close();
      window.removeEventListener("keydown", onWindowKeyDown);
      window.removeEventListener("resize", onResize);
      root.remove();
    },
    isOpen,
  };
}

// Wires the immersive stage toolbar (view/slideshow trigger + download menu)
// rendered by immersive_stage_toolbar_markup(). Every lookup is null-safe so
// surfaces without a view button (three/aframe) or without a download menu
// (image view, static embeds) can share the same call.
export function setupImmersiveStageChrome(stageEl, options = {}) {
  const root = options.root ?? document;
  const viewTrigger = root.querySelector("[data-immersive-view-trigger]");
  const downloadTrigger = root.querySelector("[data-immersive-download-trigger]");
  const downloadMenu = root.querySelector("[data-immersive-download-menu]");
  const downloadWrap = downloadTrigger?.closest(".immersive-stage-download-wrap") ?? null;

  const onViewClick = () => options.onViewAction?.();
  viewTrigger?.addEventListener("click", onViewClick);

  function isMenuOpen() {
    return !!downloadMenu && !downloadMenu.hidden;
  }

  function setMenuOpen(open, { focusTrigger = false } = {}) {
    if (!downloadTrigger || !downloadMenu) return;
    downloadMenu.hidden = !open;
    downloadTrigger.setAttribute("aria-expanded", open ? "true" : "false");
    if (open) {
      downloadMenu.querySelector("a, button")?.focus?.();
    } else if (focusTrigger) {
      downloadTrigger.focus?.();
    }
  }

  const onDownloadToggle = () => setMenuOpen(!isMenuOpen());

  // Capture-phase pointerdown so an outside tap/click closes the menu before
  // whatever it lands on handles the event (mouse + touch + pen).
  const onDocumentPointerDown = (event) => {
    if (!isMenuOpen()) return;
    if (downloadWrap && event.target instanceof Node && downloadWrap.contains(event.target)) return;
    setMenuOpen(false);
  };

  const onDocumentKeyDown = (event) => {
    if (event.key === "Escape" && isMenuOpen()) {
      event.stopPropagation();
      setMenuOpen(false, { focusTrigger: true });
    }
  };

  // Let menu item handlers (download link URL rewriting, PNG capture) run
  // before the menu hides.
  const onMenuClick = (event) => {
    if (!(event.target instanceof Element)) return;
    if (!event.target.closest("a, button")) return;
    window.setTimeout(() => setMenuOpen(false), 120);
  };

  downloadTrigger?.addEventListener("click", onDownloadToggle);
  downloadMenu?.addEventListener("click", onMenuClick);
  if (downloadTrigger && downloadMenu) {
    document.addEventListener("pointerdown", onDocumentPointerDown, { capture: true });
    document.addEventListener("keydown", onDocumentKeyDown);
  }

  return {
    closeDownloadMenu() {
      setMenuOpen(false);
    },
    dispose() {
      viewTrigger?.removeEventListener("click", onViewClick);
      downloadTrigger?.removeEventListener("click", onDownloadToggle);
      downloadMenu?.removeEventListener("click", onMenuClick);
      if (downloadTrigger && downloadMenu) {
        document.removeEventListener("pointerdown", onDocumentPointerDown, { capture: true });
        document.removeEventListener("keydown", onDocumentKeyDown);
      }
    },
  };
}

export function selectProgressiveExhibitSlots(items, centers, target, liveBudget) {
  if (liveBudget <= 0) return new Set();
  return new Set(
    items
      .map((item, index) => {
        const center = centers[index];
        if (item.kind !== "piece" || !center) return null;
        const dx = center.x - target.x;
        const dy = center.y - target.y;
        const dz = center.z - target.z;
        return { index, distance: (dx * dx) + (dy * dy) + (dz * dz * 0.35) };
      })
      .filter((entry) => Boolean(entry))
      .sort((a, b) => a.distance - b.distance)
      .slice(0, liveBudget)
      .map((entry) => entry.index),
  );
}

// Helpers from art-piece-runtime / immersive-piece-runtime
export function sanitizeArtPieceHtml(htmlCode, fallbackHtml) {
  const source = htmlCode?.trim() ? htmlCode : fallbackHtml;
  const temp = document.createElement('div');
  temp.innerHTML = source;
  
  const walk = (node) => {
    if (node.nodeType === Node.ELEMENT_NODE) {
      const tag = node.tagName.toUpperCase();
      if (tag !== 'DIV' && tag !== 'CANVAS') {
        const frag = document.createDocumentFragment();
        while (node.firstChild) {
          frag.appendChild(walk(node.removeChild(node.firstChild)));
        }
        return frag;
      }
      const cloned = document.createElement(tag.toLowerCase());
      for (const attr of Array.from(node.attributes)) {
        const name = attr.name.toLowerCase();
        if (['id', 'class', 'style', 'width', 'height'].includes(name) || name.startsWith('data-')) {
          cloned.setAttribute(name, attr.value);
        }
      }
      while (node.firstChild) {
        cloned.appendChild(walk(node.removeChild(node.firstChild)));
      }
      return cloned;
    }
    return node.cloneNode(true);
  };

  const frag = document.createDocumentFragment();
  while (temp.firstChild) {
    frag.appendChild(walk(temp.removeChild(temp.firstChild)));
  }
  const wrapper = document.createElement('div');
  wrapper.appendChild(frag);
  return wrapper.innerHTML || fallbackHtml;
}

export function createImmersiveHost(htmlCode, cssCode, defaultHtml, size, engine) {
  const host = document.createElement("div");
  host.style.position = "fixed";
  host.style.left = "-10000px";
  host.style.top = "0";
  host.style.width = `${size.width}px`;
  host.style.height = `${size.height}px`;
  host.style.overflow = "hidden";
  host.style.pointerEvents = "none";

  const style = document.createElement("style");
  style.textContent = `
    canvas {
      display: block;
      max-width: none;
      position: static !important;
      top: auto !important;
      left: auto !important;
      bottom: auto !important;
      right: auto !important;
      z-index: auto !important;
    }
  `;
  host.appendChild(style);

  const markup = document.createElement("div");
  markup.style.width = "100%";
  markup.style.height = "100%";
  markup.innerHTML = engine === "svg"
    ? (htmlCode?.trim() ? htmlCode : defaultHtml)
    : sanitizeArtPieceHtml(htmlCode, defaultHtml);
  host.appendChild(markup);
  document.body.appendChild(host);
  return host;
}

export function resolveSketchFactory(code) {
  const previousSketch = window.sketch;
  try {
    try {
      const expressionFactory = new Function(`return (${code})`)();
      if (typeof expressionFactory === "function") {
        return expressionFactory;
      }
    } catch (e) {
      // Fall through
    }
    window.sketch = undefined;
    new Function(code)();
    if (typeof window.sketch === "function") {
      return window.sketch;
    }
    throw new Error("Generated code did not define window.sketch or evaluate to a function");
  } finally {
    if (previousSketch === undefined) {
      delete window.sketch;
    } else {
      window.sketch = previousSketch;
    }
  }
}

let aframeRuntimePromise = null;
function loadAFrameRuntime() {
  if (window.AFRAME) return Promise.resolve(window.AFRAME);
  if (aframeRuntimePromise) return aframeRuntimePromise;
  aframeRuntimePromise = new Promise((resolve, reject) => {
    const existing = Array.from(document.scripts).find((script) => script.src && script.src.endsWith("/assets/js/aframe.min.js"));
    if (existing) {
      existing.addEventListener("load", () => resolve(window.AFRAME), { once: true });
      existing.addEventListener("error", () => reject(new Error("Could not load self-hosted A-Frame runtime.")), { once: true });
      return;
    }
    const script = document.createElement("script");
    script.src = "/assets/js/aframe.min.js";
    script.onload = () => resolve(window.AFRAME);
    script.onerror = () => reject(new Error("Could not load self-hosted A-Frame runtime."));
    document.head.appendChild(script);
  });
  return aframeRuntimePromise;
}

let p5RuntimePromise = null;
function loadP5Runtime() {
  if (typeof window.p5 === "function") return Promise.resolve(window.p5);
  if (p5RuntimePromise) return p5RuntimePromise;
  p5RuntimePromise = new Promise((resolve, reject) => {
    const script = document.createElement("script");
    script.src = "https://cdnjs.cloudflare.com/ajax/libs/p5.js/1.9.0/p5.min.js";
    script.onload = () => {
      if (typeof window.p5 === "function") {
        resolve(window.p5);
      } else {
        reject(new Error("p5.js loaded but did not expose window.p5."));
      }
    };
    script.onerror = () => reject(new Error("Could not load p5.js runtime."));
    document.head.appendChild(script);
  });
  return p5RuntimePromise;
}

let c2RuntimePromise = null;
function loadC2Runtime() {
  if (window.c2 && typeof window.c2.Renderer === "function") return Promise.resolve(window.c2);
  if (c2RuntimePromise) return c2RuntimePromise;
  c2RuntimePromise = new Promise((resolve, reject) => {
    const script = document.createElement("script");
    script.src = "https://cdn.jsdelivr.net/npm/c2.js@1.0.9/dist/c2.min.js";
    script.onload = () => {
      if (window.c2 && typeof window.c2.Renderer === "function") {
        resolve(window.c2);
      } else {
        reject(new Error("c2.js loaded but did not expose window.c2.Renderer."));
      }
    };
    script.onerror = () => reject(new Error("Could not load c2.js runtime."));
    document.head.appendChild(script);
  });
  return c2RuntimePromise;
}

// KEEP IN SYNC (creatr-media-path-guard): piece-runtime.js / immersive-gallery.js
function isCmsMediaPath(src) {
  return typeof src === "string" && /^\/(?:image\/[0-9]+|api\/media-assets\/[0-9]+|media\/[A-Za-z0-9._~/%+-]+)(?:\?[A-Za-z0-9._~%=&+-]*)?$/.test(src);
}

// Server-side capture-safe rewriting substitutes data: URLs for CMS media
// refs before code reaches this helper — accept those alongside CMS paths.
function isInlineMediaSrc(src) {
  return typeof src === "string" && (/^data:image\//i.test(src) || /^blob:/i.test(src));
}

function normalizeCmsMediaPath(src) {
  if (typeof src !== "string" || src === "") return "";
  if (isCmsMediaPath(src)) return src;
  try {
    const resolved = new URL(src, window.location.href);
    if (resolved.origin !== window.location.origin) return "";
    const path = resolved.pathname + resolved.search;
    return isCmsMediaPath(path) ? path : "";
  } catch (_) {
    return "";
  }
}

function resolveRuntimeMediaSrc(src) {
  if (isInlineMediaSrc(src)) return src;
  return normalizeCmsMediaPath(src);
}

function describeMediaSrc(src) {
  if (typeof src !== "string") return String(src);
  return isInlineMediaSrc(src) ? src.slice(0, 64) + "… (inline media data)" : src;
}

function createC2MediaHelpers(canvas, onError = console.error) {
  const mediaContext = canvas.getContext("2d");
  const imageCache = new Map();

  function reportError(error) {
    if (typeof onError === "function") onError(error);
  }

  function loadImage(src) {
    const resolved = resolveRuntimeMediaSrc(src);
    if (!resolved) {
      reportError("C2 media helpers may only load same-origin CMS media paths such as /image/2, /media/..., or /api/media-assets/2.");
      return null;
    }
    src = resolved;
    if (imageCache.has(src)) return imageCache.get(src);
    const image = new Image();
    image.decoding = "async";
    image.loading = "eager";
    image.dataset.creatrLoaded = "0";
    // Same contract as piece-runtime.js loadImage: a Promise (await/.then
    // both work) carrying the element for the draw helpers to unwrap.
    const loaded = new Promise((resolve, reject) => {
      image.onload = () => { image.dataset.creatrLoaded = "1"; resolve(image); };
      image.onerror = () => {
        const message = "Could not load CMS media asset: " + describeMediaSrc(src);
        reportError(message);
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
    if (!mediaContext || !image || image.dataset?.creatrLoaded !== "1") return false;
    try {
      mediaContext.drawImage(image, x, y, width, height);
      return true;
    } catch (error) {
      reportError(error);
      return false;
    }
  }

  function drawImageCover(image, x, y, width, height) {
    image = resolveImageRef(image);
    if (!mediaContext || !image || image.dataset?.creatrLoaded !== "1") return false;
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
      reportError(error);
      return false;
    }
  }

  return { loadImage, drawImage, drawImageCover };
}

export function createFrameLabel(title, subtitle) {
  const cw = 512, ch = 80;
  const canvas = document.createElement("canvas");
  canvas.width = cw; canvas.height = ch;
  const ctx = canvas.getContext("2d");
  ctx.clearRect(0, 0, cw, ch);
  ctx.fillStyle = "rgba(255,255,255,0.82)";
  ctx.fillRect(0, 0, cw, ch);
  ctx.fillStyle = "rgba(0,0,0,0.82)";
  ctx.font = "bold 22px sans-serif";
  ctx.textBaseline = "middle";
  ctx.fillText(title.slice(0, 38), 16, ch * 0.38);
  ctx.fillStyle = "rgba(0,0,0,0.52)";
  ctx.font = "16px sans-serif";
  ctx.fillText(subtitle.slice(0, 42), 16, ch * 0.72);

  const texture = new THREE.CanvasTexture(canvas);
  const material = new THREE.MeshBasicMaterial({ map: texture, transparent: true, depthWrite: false });
  const mesh = new THREE.Mesh(new THREE.PlaneGeometry(WALL_FRAME_ART_WIDTH, WALL_LABEL_HEIGHT), material);
  return { mesh, material };
}

export function createMultiFrameExhibitWall(stage, frameCount, rows = 1, cols = frameCount, labels, options = {}) {
  const n = Math.max(1, frameCount);
  const gridRows = Math.max(1, rows);
  const gridCols = Math.max(1, cols);
  const labelPosition = options.labelPosition === "above" ? "above" : "below";
  const wallWidth = Math.max(22, gridCols * WALL_FRAME_SLOT_WIDTH + 2);
  const wallMeshHeight = Math.max(11, gridRows * WALL_FRAME_SLOT_HEIGHT + 5);
  const gridCenterY = computeExhibitGridCenterY(gridRows);

  const canvas = document.createElement("canvas");
  canvas.style.width = "100%"; canvas.style.height = "100%"; canvas.style.display = "block"; canvas.style.touchAction = "none";
  const urlParams = new URLSearchParams(window.location.search);
  const isCloseup = urlParams.get('closeup') === '1' || urlParams.get('thumbnail') === '1';
  const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true, preserveDrawingBuffer: isCloseup });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  stage.innerHTML = "";
  stage.appendChild(canvas);

  const scene = new THREE.Scene();
  scene.background = new THREE.Color("#f1ece2");
  scene.fog = new THREE.Fog("#f1ece2", Math.max(20, wallWidth + 4), Math.max(40, wallWidth * 3));

  const camera = new THREE.PerspectiveCamera(40, 1, 0.1, 200);
  const controls = new OrbitControls(camera, canvas);
  controls.enableDamping = true;
  controls.enablePan = true;
  controls.minPolarAngle = 0.01;
  controls.maxPolarAngle = Math.PI - 0.01;

  scene.add(new THREE.AmbientLight(0xffffff, 1.38));
  const keyLight = new THREE.DirectionalLight(0xfffcf6, 0.9);
  keyLight.position.set(0.8, 4.8, 5.2);
  scene.add(keyLight);
  const fillLight = new THREE.DirectionalLight(0xf3ede2, 0.35);
  fillLight.position.set(-3.1, 2.4, 1.8);
  scene.add(fillLight);

  const floor = new THREE.Mesh(
    new THREE.PlaneGeometry(wallWidth + 8, 18),
    new THREE.MeshStandardMaterial({ color: "#d7d0c4", roughness: 0.98, metalness: 0 }),
  );
  floor.rotation.x = -Math.PI / 2;
  floor.position.set(0, 0, 1.9);
  scene.add(floor);

  const backWall = new THREE.Mesh(
    new THREE.PlaneGeometry(wallWidth + 4, wallMeshHeight),
    new THREE.MeshStandardMaterial({ color: "#f8f5ee", roughness: 1, metalness: 0 }),
  );
  backWall.position.set(0, gridCenterY, -1.35);
  scene.add(backWall);

  const wallCenterZ = WALL_CENTER.z;
  const slots = [];
  for (let i = 0; i < n; i++) {
    const row = Math.floor(i / gridCols);
    const col = i % gridCols;
    const slotX = (col - (gridCols - 1) / 2) * WALL_FRAME_SLOT_WIDTH;
    const slotY = gridCenterY + ((gridRows - 1) / 2 - row) * WALL_FRAME_SLOT_HEIGHT;

    const framePanel = new THREE.Mesh(
      new THREE.BoxGeometry(WALL_FRAME_ART_WIDTH + 0.3, WALL_FRAME_ART_HEIGHT + 0.3, 0.05),
      new THREE.MeshStandardMaterial({ color: "#fcfaf6", roughness: 0.96, metalness: 0 }),
    );
    framePanel.position.set(slotX, slotY, -1.16);
    scene.add(framePanel);

    const artMaterial = new THREE.MeshBasicMaterial({ color: "#e8e4de" });
    const artMesh = new THREE.Mesh(new THREE.PlaneGeometry(WALL_FRAME_ART_WIDTH, WALL_FRAME_ART_HEIGHT), artMaterial);
    artMesh.position.set(slotX, slotY, wallCenterZ);
    scene.add(artMesh);

    const frameMesh = new THREE.Mesh(
      new THREE.BoxGeometry(WALL_FRAME_ART_WIDTH + 0.12, WALL_FRAME_ART_HEIGHT + 0.12, 0.03),
      new THREE.MeshStandardMaterial({ color: "#d8d1c7", roughness: 0.92, metalness: 0 }),
    );
    frameMesh.position.set(slotX, slotY, -1.12);
    scene.add(frameMesh);

    const slot = { artMesh, artMaterial, frameMesh, framePanel, center: { x: slotX, y: slotY, z: wallCenterZ } };

    const label = labels?.[i];
    if (label) {
      const { mesh: labelMesh, material: labelMaterial } = createFrameLabel(label.title, label.subtitle);
      const labelY = labelPosition === "above"
        ? slotY + WALL_FRAME_ART_HEIGHT / 2 + WALL_LABEL_HEIGHT / 2 + WALL_LABEL_GAP
        : slotY - WALL_FRAME_ART_HEIGHT / 2 - WALL_LABEL_HEIGHT / 2 - WALL_LABEL_GAP;
      labelMesh.position.set(slotX, labelY, wallCenterZ + 0.01);
      scene.add(labelMesh);
      slot.labelMesh = labelMesh;
      slot.labelMaterial = labelMaterial;
    }
    slots.push(slot);
  }

  const shell = { canvas, renderer, scene, camera, controls, floor, backWall, slots, gridRows, gridCols, gridCenterY };
  fitMultiFrameExhibitCamera(shell, stage);
  return shell;
}

export function fitMultiFrameExhibitCamera(shell, stage, resetCamera = true) {
  const width = stage.clientWidth >= 50 ? stage.clientWidth : window.innerWidth;
  const height = stage.clientHeight >= 50 ? stage.clientHeight : window.innerHeight;
  shell.camera.aspect = width / Math.max(height, 1);
  shell.camera.updateProjectionMatrix();
  shell.renderer.setSize(width, height, false);

  const { gridRows, gridCols, gridCenterY } = shell;
  const totalWidth = Math.max(WALL_FRAME_ART_WIDTH, (gridCols - 1) * WALL_FRAME_SLOT_WIDTH + WALL_FRAME_ART_WIDTH);
  const totalHeight = Math.max(WALL_FRAME_ART_HEIGHT, (gridRows - 1) * WALL_FRAME_SLOT_HEIGHT + WALL_FRAME_ART_HEIGHT);

  const target = new THREE.Vector3(0, gridCenterY - 0.16, WALL_CENTER.z);
  const verticalFov = THREE.MathUtils.degToRad(shell.camera.fov);
  const horizontalFov = 2 * Math.atan(Math.tan(verticalFov / 2) * shell.camera.aspect);
  const distanceForHeight = (totalHeight / 2) / Math.tan(verticalFov / 2);
  const distanceForWidth = (totalWidth / 2) / Math.tan(horizontalFov / 2);
  const urlParams = new URLSearchParams(window.location.search);
  const isCloseup = urlParams.get('closeup') === '1' || urlParams.get('thumbnail') === '1';
  const multiplier = isCloseup ? 0.55 : 1.45;
  const distance = Math.max(distanceForHeight, distanceForWidth) * multiplier;

  shell.controls.minDistance = Math.max(1.2, distance * 0.25);
  shell.controls.maxDistance = Math.max(20, distance * 6);
  shell.controls.minAzimuthAngle = -Math.PI;
  shell.controls.maxAzimuthAngle = Math.PI;

  if (resetCamera) {
    shell.camera.position.set(0, gridCenterY + 0.2, WALL_CENTER.z + distance);
    shell.camera.lookAt(target);
    shell.controls.target.copy(target);
  }
  shell.controls.update();
}

// --- Movement sonification (Tone.js) ---------------------------------------
// Self-hosted Tone.js is lazy-loaded only when a user enables sound, so pieces
// without sonification never pay for it. Sonic params ({tempo, scale,
// instrument, feel}) come from the piece's stored AI-generated sonic block; the
// generated piece code never touches audio.
const SONIC_SCALES = {
  major: [0, 2, 4, 5, 7, 9, 11], minor: [0, 2, 3, 5, 7, 8, 10],
  pentatonic: [0, 2, 4, 7, 9], chromatic: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
  dorian: [0, 2, 3, 5, 7, 9, 10], phrygian: [0, 1, 3, 5, 7, 8, 10],
  lydian: [0, 2, 4, 6, 7, 9, 11], mixolydian: [0, 2, 4, 5, 7, 9, 10],
  wholetone: [0, 2, 4, 6, 8, 10],
};
const SONIC_INSTRUMENTS = {
  synth: "Synth", amsynth: "AMSynth", fmsynth: "FMSynth",
  membranesynth: "MembraneSynth", metalsynth: "MetalSynth",
  plucksynth: "PluckSynth", duosynth: "DuoSynth",
};

let _toneLoadPromise = null;
function loadToneOnce() {
  if (window.Tone) return Promise.resolve(window.Tone);
  if (_toneLoadPromise) return _toneLoadPromise;
  _toneLoadPromise = new Promise((resolve, reject) => {
    const s = document.createElement("script");
    s.src = "/assets/vendor/tone/Tone.js";
    s.onload = () => (window.Tone ? resolve(window.Tone) : reject(new Error("Tone.js loaded but window.Tone missing")));
    s.onerror = () => reject(new Error("Tone.js failed to load"));
    document.head.appendChild(s);
  });
  return _toneLoadPromise;
}

function _sonicMidiToFreq(m) { return 440 * Math.pow(2, (m - 69) / 12); }

// Returns a controller with update(motionVector)/dispose(), bound to the
// shared toolbar's mute/unmute toggle (`[data-immersive-sound-toggle]`).
// Browsers block audio autoplay until a user gesture — tapping the unmute
// button IS that gesture (Tone.start() runs on the click). Mute stops
// triggering notes but keeps the synth instantiated for instant re-unmute.
// While enabled, an idle ticker plays a plain scale-walk pattern at rest;
// camera motion (via update()) modulates pitch/octave and resets the idle
// clock, so sound settles back to the idle pattern ~2s after motion stops.
// Returns null when there are no usable sonic params — callers no-op safely.
function createAudioController(sonicParams, stageEl) {
  if (!sonicParams || typeof sonicParams !== "object") return null;

  const scaleName = SONIC_SCALES[sonicParams.scale] ? sonicParams.scale : "major";
  const scale = SONIC_SCALES[scaleName];
  const instrumentKey = SONIC_INSTRUMENTS[sonicParams.instrument] ? sonicParams.instrument : "synth";
  const tempo = Math.max(40, Math.min(220, Number(sonicParams.tempo) || 90));
  const minInterval = ((60 / tempo) * 1000) / 2; // ~eighth-note spacing
  const baseMidi = 48; // C3

  let enabled = false, synth = null, disposed = false, lastNoteAt = 0, walk = 0;
  let lastMotionAt = 0, idleTimer = null;
  const IDLE_GAP_MS = 2000; // how long the camera must sit still before idle notes resume
  // The toolbar (immersive_stage_toolbar_markup) renders as a SIBLING of
  // stageEl, not a descendant — stageEl.querySelector can never find it, so
  // this looks it up from the document instead. One immersive stage per page.
  const toggleBtn = document.querySelector("[data-immersive-sound-toggle]") || null;

  function playIdleNote() {
    if (!enabled || !synth || disposed) return;
    const degree = walk++ % scale.length;
    const midi = baseMidi + scale[degree];
    try { synth.triggerAttackRelease(_sonicMidiToFreq(midi), "16n"); } catch (_e) {}
  }

  function idleTick() {
    if (disposed) { idleTimer = null; return; }
    idleTimer = setTimeout(idleTick, minInterval);
    if (!enabled || !synth) return;
    const now = performance.now();
    if (now - lastMotionAt >= IDLE_GAP_MS && now - lastNoteAt >= minInterval) {
      lastNoteAt = now;
      playIdleNote();
    }
  }

  function setBtnState(muted) {
    if (!toggleBtn) return;
    toggleBtn.setAttribute("aria-pressed", muted ? "false" : "true");
    toggleBtn.setAttribute("aria-label", muted ? "Unmute sound" : "Mute sound");
    const iconEl = toggleBtn.querySelector("svg, [aria-hidden='true']") || toggleBtn;
    // Render muted (off) state: bar-with-slash. Unmuted (on): waves.
    if (toggleBtn.replaceChildren) {
      const svgNS = "http://www.w3.org/2000/svg";
      const svg = document.createElementNS(svgNS, "svg");
      svg.setAttribute("viewBox", "0 0 24 24");
      svg.setAttribute("width", "19");
      svg.setAttribute("height", "19");
      svg.setAttribute("fill", "none");
      svg.setAttribute("stroke", "currentColor");
      svg.setAttribute("stroke-width", "1.9");
      svg.setAttribute("stroke-linecap", "round");
      svg.setAttribute("stroke-linejoin", "round");
      svg.setAttribute("aria-hidden", "true");
      const speaker = document.createElementNS(svgNS, "path");
      speaker.setAttribute("d", "M11 5 6 9H3v6h3l5 4z");
      svg.appendChild(speaker);
      if (muted) {
        const l1 = document.createElementNS(svgNS, "line");
        l1.setAttribute("x1", "22"); l1.setAttribute("y1", "9");
        l1.setAttribute("x2", "16"); l1.setAttribute("y2", "15");
        const l2 = document.createElementNS(svgNS, "line");
        l2.setAttribute("x1", "16"); l2.setAttribute("y1", "9");
        l2.setAttribute("x2", "22"); l2.setAttribute("y2", "15");
        svg.appendChild(l1); svg.appendChild(l2);
      } else {
        const w1 = document.createElementNS(svgNS, "path");
        w1.setAttribute("d", "M16 9a4 4 0 0 1 0 6");
        const w2 = document.createElementNS(svgNS, "path");
        w2.setAttribute("d", "M19 6a8 8 0 0 1 0 12");
        svg.appendChild(w1); svg.appendChild(w2);
      }
      toggleBtn.replaceChildren(svg);
    }
  }

  async function onToggleClick() {
    if (disposed) return;
    // Mute path: synth already exists, just turn off triggering.
    if (enabled && synth) {
      enabled = false;
      setBtnState(true);
      return;
    }
    // Unmute path: lazily load Tone.js on the first unmute (this click is
    // the autoplay-unlocking gesture Tone.start() requires).
    try {
      toggleBtn.disabled = true;
      const Tone = await loadToneOnce();
      await Tone.start();
      if (disposed) return;
      if (!synth) {
        const Ctor = Tone[SONIC_INSTRUMENTS[instrumentKey]] || Tone.Synth;
        synth = new Ctor().toDestination();
        if (synth.volume) synth.volume.value = -6;
      }
      enabled = true;
      setBtnState(false);
      lastMotionAt = 0; // let idle notes start immediately on unmute
      if (!idleTimer) idleTick();
    } catch (_e) {
      if (toggleBtn) {
        toggleBtn.setAttribute("aria-label", "Sound unavailable");
      }
    } finally {
      if (toggleBtn) toggleBtn.disabled = false;
    }
  }

  if (toggleBtn) {
    setBtnState(true);
    toggleBtn.addEventListener("click", onToggleClick);
  }

  return {
    update(motion) {
      if (!enabled || !synth || disposed) return;
      const dx = motion?.dx || 0, dy = motion?.dy || 0, dz = motion?.dz || 0;
      const speed = Math.hypot(dx, dy, dz);
      if (speed < 0.002) return; // near-still → let the idle ticker handle it
      lastMotionAt = performance.now();
      const now = lastMotionAt;
      if (now - lastNoteAt < minInterval) return; // rate-limit to tempo
      lastNoteAt = now;
      const degree = walk++ % scale.length;
      const octave = Math.min(2, Math.floor(Math.abs(dy) * 25));
      const midi = baseMidi + scale[degree] + 12 * octave;
      try { synth.triggerAttackRelease(_sonicMidiToFreq(midi), "16n"); } catch (_e) {}
    },
    dispose() {
      disposed = true; enabled = false;
      if (idleTimer) { clearTimeout(idleTimer); idleTimer = null; }
      if (toggleBtn) toggleBtn.removeEventListener("click", onToggleClick);
      try { synth?.dispose?.(); } catch (_e) {}
    },
  };
}

// Phase 1 Entry Point: mountThreeImmersivePiece
export function mountThreeImmersivePiece(stageEl, code, htmlCode, cssCode, onError = console.error, options = {}) {
  const runtimeSize = { width: 1280, height: 720 };
  const host = createImmersiveHost(htmlCode, cssCode, '<div id="container"></div>', runtimeSize);

  const canvas = host.querySelector("canvas") || document.createElement("canvas");
  canvas.width = runtimeSize.width;
  canvas.height = runtimeSize.height;
  canvas.style.width = "100%";
  canvas.style.height = "100%";
  canvas.style.display = "block";
  canvas.style.touchAction = "none";

  stageEl.innerHTML = "";
  Array.from(host.childNodes).map(node => node.cloneNode(true)).forEach(child => stageEl.appendChild(child));
  const mount = stageEl.querySelector("#container") || stageEl.querySelector("#canvas-container") || stageEl.querySelector("#sketch-container") || stageEl.querySelector(":scope > div") || stageEl;
  stageEl.querySelectorAll("canvas").forEach(c => { if (c !== canvas) c.remove(); });
  if (canvas.parentElement !== mount) mount.appendChild(canvas);

  let cleanup;
  let frameId = 0;
  let consecutiveErrors = 0;
  let controlFrame = 0;
  let pieceDrivesOwnRender = false;
  const stopFrameHandles = new Set();
  const state = { scene: null, camera: null, renderer: null, objects: [] };

  const instrumentedThree = { ...THREE };
  const OriginalScene = THREE.Scene;
  instrumentedThree.Scene = class extends OriginalScene {
    constructor(...args) { super(...args); state.scene = this; }
    add(...objects) {
      objects.forEach(obj => {
        if (obj?.geometry) {
          state.objects.push(obj);
          if (state.objects.length === 5000) {
            console.warn("Three.js immersive piece has 5000+ tracked mesh objects — render performance may degrade.");
          }
        }
      });
      return super.add(...objects);
    }
  };

  const OriginalPerspectiveCamera = THREE.PerspectiveCamera;
  instrumentedThree.PerspectiveCamera = class extends OriginalPerspectiveCamera {
    constructor(...args) { super(...args); state.camera = this; }
  };

  if ("OrthographicCamera" in THREE) {
    const OriginalOrthographicCamera = THREE.OrthographicCamera;
    instrumentedThree.OrthographicCamera = class extends OriginalOrthographicCamera {
      constructor(...args) { super(...args); state.camera = this; }
    };
  }

  const OriginalRenderer = THREE.WebGLRenderer;
  instrumentedThree.WebGLRenderer = class extends OriginalRenderer {
    constructor(input) {
      // preserveDrawingBuffer keeps the frame readable for PNG capture,
      // matching the regular-view three bootstrap in piece-runtime.js.
      super({ ...input, canvas, preserveDrawingBuffer: true });
      state.renderer = this;
      this.setPixelRatio?.(Math.min(window.devicePixelRatio, 2));
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

  // Loaders for uploaded 3D models (module-level, contained dynamic import).
  // Generated code references them as THREE.GLTFLoader / THREE.OBJLoader (no
  // import/fetch tokens → passes preflight); they load the model by its
  // /media/{id} URL at runtime. Absent only if their source failed to load.
  if (_GLTFLoaderCtor) instrumentedThree.GLTFLoader = _GLTFLoaderCtor;
  if (_OBJLoaderCtor) instrumentedThree.OBJLoader = _OBJLoaderCtor;

  window.THREE = instrumentedThree;

  function autoFitCamera(viewportWidth = stageEl.clientWidth || window.innerWidth) {
    if (!state.scene || !state.camera) return;
    const box = new THREE.Box3();
    if (state.scene.traverse) {
      state.scene.traverse(obj => {
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
            const worldSize = new THREE.Vector3();
            worldBox.getSize(worldSize);
            if (worldSize.x >= 30 || worldSize.y >= 30 || worldSize.z >= 30) return;
            if (obj.geometry.type === 'PlaneGeometry' || obj.geometry.type === 'PlaneBufferGeometry') {
              if (worldSize.x >= 15 || worldSize.y >= 15 || worldSize.z >= 15) return;
            }
            box.union(worldBox);
          }
        }
      });
    }
    if (box.isEmpty()) return;

    const center = new THREE.Vector3();
    box.getCenter(center);
    const size = new THREE.Vector3();
    box.getSize(size);
    if (state.camera.position.lengthSq() > 0.01) {
      if (controls) {
        controls.target.copy(center);
        controls.update();
        saveOrbitState();
      }
      return;
    }
    const nextView = computeThreeAutoFitView(
      center, size, state.camera.aspect || 1, state.camera.fov || 45, isCompactImmersiveViewport(viewportWidth)
    );
    state.camera.position.set(nextView.camera.x, nextView.camera.y, nextView.camera.z);
    state.camera.lookAt(nextView.target.x, nextView.target.y, nextView.target.z);

    const maxDim = Math.max(size.x, size.y, size.z) || 1;
    const dist = state.camera.position.distanceTo(center);
    state.camera.near = Math.max(0.01, dist / 1000);
    state.camera.far = Math.max(1000, dist * 100 + maxDim * 100);
    state.camera.updateProjectionMatrix?.();
    state.camera.updateMatrixWorld?.(true);

    if (controls) {
      controls.target.set(nextView.target.x, nextView.target.y, nextView.target.z);
      controls.update();
      saveOrbitState();
    }
  }

  const startFrame = (handler) => {
    pieceDrivesOwnRender = true;
    let frameCount = 0;
    let rafId = 0;
    function tick() {
      frameCount += 1;
      try {
        handler(frameCount);
      } catch (err) {
        // The piece's own render loop just died — hand rendering back to
        // the bootstrap loop so the canvas doesn't freeze on its last frame
        // with no further visual feedback (e.g. while dragging/orbiting).
        pieceDrivesOwnRender = false;
        onError(err);
        return;
      }
      if (frameCount === 15) autoFitCamera();
      rafId = window.requestAnimationFrame(tick);
    }
    rafId = window.requestAnimationFrame(tick);
    const stop = () => window.cancelAnimationFrame(rafId);
    stopFrameHandles.add(stop);
    return () => {
      stop();
      stopFrameHandles.delete(stop);
    };
  };

  let controls = null;
  let keyNav = null;
  let isOrbitActive = false;
  let userHasInteracted = false;
  let gyroController = null;
  let viewerControls = null;
  const audioController = createAudioController(options.sonicParams, stageEl);
  const _audioPrevPos = new THREE.Vector3();
  let _audioPrevInit = false;
  const _orbitCamPos = new THREE.Vector3();
  const _orbitTarget = new THREE.Vector3();
  const _buttonForward = new THREE.Vector3();
  const _buttonRight = new THREE.Vector3();
  let threeNavLimit = 5;

  let threeAnimFromTarget = null, threeAnimToTarget = null, threeAnimFromCam = null, threeAnimToCam = null, threeAnimStart = 0;
  const threeRaycaster = new THREE.Raycaster();
  const _pointerState = new Map();
  let _hadMultiTouchGesture = false;

  function saveOrbitState() {
    if (!controls || !state.camera) return;
    _orbitCamPos.copy(state.camera.position);
    _orbitTarget.copy(controls.target);
  }

  function cancelThreeNavigationAnimation() {
    threeAnimFromTarget = threeAnimToTarget = threeAnimFromCam = threeAnimToCam = null;
    if (controls) controls.enabled = true;
  }

  function reassertThreeCanvasContainment() {
    if (canvas.parentElement !== mount) mount.appendChild(canvas);
    if (canvas.style.position || canvas.style.zIndex || canvas.style.width !== "100%" || canvas.style.height !== "100%") {
      canvas.style.position = canvas.style.top = canvas.style.left = canvas.style.bottom = canvas.style.right = canvas.style.zIndex = canvas.style.pointerEvents = "";
      canvas.style.width = canvas.style.height = "100%";
    }
    canvas.style.touchAction = "none";
    stageEl.querySelectorAll("canvas").forEach(c => {
      if (c !== canvas) { c.style.display = "none"; c.setAttribute("aria-hidden", "true"); }
    });

    // Resolve back ground
    let bg = null;
    for (let el of [canvas, mount, stageEl.querySelector("div"), stageEl, host.querySelector("#container"), host]) {
      if (!el) continue;
      let val = el.style.backgroundColor || el.style.background;
      if (val && val.trim() !== "" && val.trim() !== "transparent" && val.trim() !== "rgba(0, 0, 0, 0)") { bg = val; break; }
      let cmp = window.getComputedStyle(el).backgroundColor;
      if (cmp && cmp.trim() !== "" && cmp.trim() !== "transparent" && cmp.trim() !== "rgba(0, 0, 0, 0)") { bg = cmp; break; }
    }
    bg = bg ?? "#000000";
    stageEl.style.background = bg;
  }

  function getThreeNavigationLimit() {
    const box = new THREE.Box3();
    if (state.scene?.traverse) {
      state.scene.traverse(obj => {
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

  function moveThreeOrbitTo(hitPoint) {
    if (!controls || !state.camera) return;
    const dx = hitPoint.x - controls.target.x;
    const dz = hitPoint.z - controls.target.z;
    const maxOffset = getThreeNavigationLimit();
    const shift = new THREE.Vector3(Math.max(-maxOffset, Math.min(maxOffset, dx)), 0, Math.max(-maxOffset, Math.min(maxOffset, dz)));
    if (shift.lengthSq() < 0.003) return;

    cancelThreeNavigationAnimation();
    threeAnimFromTarget = controls.target.clone();
    threeAnimToTarget = threeAnimFromTarget.clone().add(shift);
    threeAnimFromCam = state.camera.position.clone();
    threeAnimToCam = threeAnimFromCam.clone().add(shift);
    threeAnimStart = performance.now();
    controls.enabled = false;
  }

  function activeTouchPointerCount() {
    let count = 0;
    _pointerState.forEach(pointer => {
      if (pointer.pointerType === "touch") count += 1;
    });
    return count;
  }

  function onThreePointerDown(e) {
    _pointerState.set(e.pointerId, {
      pointerType: e.pointerType || "mouse",
      button: e.button,
      startX: e.clientX,
      startY: e.clientY,
      moved: false,
    });
    if ((e.pointerType || "mouse") === "touch" && activeTouchPointerCount() > 1) {
      _hadMultiTouchGesture = true;
    }
  }

  function onThreePointerMove(e) {
    const pointer = _pointerState.get(e.pointerId);
    if (!pointer) return;
    if (Math.hypot(e.clientX - pointer.startX, e.clientY - pointer.startY) >= 6) {
      pointer.moved = true;
    }
    if (pointer.pointerType === "touch" && activeTouchPointerCount() > 1) {
      _hadMultiTouchGesture = true;
    }
  }

  function clearThreePointer(e) {
    const pointer = _pointerState.get(e.pointerId);
    _pointerState.delete(e.pointerId);
    if (pointer?.pointerType === "touch" && activeTouchPointerCount() === 0) {
      _hadMultiTouchGesture = false;
    }
  }

  function onThreePointerUp(e) {
    if (!controls || !state.camera) return;
    const pointer = _pointerState.get(e.pointerId);
    const wasMultiTouch = _hadMultiTouchGesture || activeTouchPointerCount() > 1;
    clearThreePointer(e);
    if (!pointer || wasMultiTouch || pointer.button !== 0 || e.button !== 0 || pointer.moved) return;

    const rect = canvas.getBoundingClientRect();
    threeRaycaster.setFromCamera(
      new THREE.Vector2(((e.clientX - rect.left) / rect.width) * 2 - 1, -((e.clientY - rect.top) / rect.height) * 2 + 1),
      state.camera
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

  function applyThreeZoom(zoomScale) {
    // OrbitControls' own wheel/dolly handling changes camera.position
    // directly without dispatching "start"/"end", so isOrbitActive never
    // flips and saveOrbitState() never captures the new zoom distance —
    // the very next frame's "snap back to last saved state" logic then
    // reverts the zoom. Handling wheel ourselves (and saving state after)
    // closes that gap. Viewer buttons use this same path for parity.
    if (!controls || !state.camera || !Number.isFinite(zoomScale) || zoomScale <= 0) return;
    const cameraPosition = state.camera.position;
    const direction = cameraPosition.clone().sub(controls.target);
    const currentDistance = direction.length();
    if (currentDistance < 1e-6) return;
    const minDistance = controls.minDistance || 0.6;
    const maxDistance = controls.maxDistance || Math.max(40, currentDistance * 4);
    const nextDistance = Math.max(minDistance, Math.min(maxDistance, currentDistance * zoomScale));
    direction.setLength(nextDistance);
    cameraPosition.copy(controls.target).add(direction);
    controls.update();
    saveOrbitState();
    viewerControls?.setZoomValue(threeZoomValueFromDistance(nextDistance));
    userHasInteracted = true;
  }

  function threeZoomValueFromDistance(distance) {
    if (!controls || !Number.isFinite(distance)) return 50;
    const minDistance = controls.minDistance || 0.6;
    const maxDistance = controls.maxDistance || Math.max(40, distance * 4);
    if (maxDistance <= minDistance) return 50;
    return ((maxDistance - Math.max(minDistance, Math.min(maxDistance, distance))) / (maxDistance - minDistance)) * 100;
  }

  function applyThreeZoomValue(value) {
    if (!controls || !state.camera || !Number.isFinite(value)) return;
    const minDistance = controls.minDistance || 0.6;
    const currentDistance = state.camera.position.distanceTo(controls.target);
    const maxDistance = controls.maxDistance || Math.max(40, currentDistance * 4);
    const t = Math.max(0, Math.min(100, value)) / 100;
    const nextDistance = maxDistance - ((maxDistance - minDistance) * t);
    const direction = state.camera.position.clone().sub(controls.target);
    if (direction.lengthSq() < 1e-8) return;
    direction.setLength(nextDistance);
    state.camera.position.copy(controls.target).add(direction);
    controls.update();
    saveOrbitState();
    userHasInteracted = true;
  }

  function applyThreeDirectionalMove(forwardScale, rightScale) {
    if (!controls || !state.camera) return;
    cancelThreeNavigationAnimation();
    state.camera.getWorldDirection(_buttonForward);
    _buttonForward.y = 0;
    if (_buttonForward.lengthSq() < 1e-6) {
      _buttonForward.set(0, 0, -1);
    } else {
      _buttonForward.normalize();
    }
    _buttonRight.set(-_buttonForward.z, 0, _buttonForward.x);
    const step = Math.max(0.08, controls.target.distanceTo(state.camera.position) * 0.035);
    const shift = _buttonForward.multiplyScalar(forwardScale * step).add(_buttonRight.multiplyScalar(rightScale * step));
    if (shift.lengthSq() < 1e-8) return;
    const nextCamX = Math.max(-threeNavLimit, Math.min(threeNavLimit, state.camera.position.x + shift.x));
    const nextCamZ = Math.max(0.5, Math.min(threeNavLimit, state.camera.position.z + shift.z));
    const dx = nextCamX - state.camera.position.x;
    const dz = nextCamZ - state.camera.position.z;
    if (Math.abs(dx) < 1e-6 && Math.abs(dz) < 1e-6) return;
    state.camera.position.x += dx;
    state.camera.position.z += dz;
    controls.target.x += dx;
    controls.target.z += dz;
    controls.update();
    saveOrbitState();
    userHasInteracted = true;
  }

  function applyThreeFloatMove(verticalScale) {
    if (!controls || !state.camera || !Number.isFinite(verticalScale)) return;
    cancelThreeNavigationAnimation();
    const step = Math.max(0.08, controls.target.distanceTo(state.camera.position) * 0.03);
    const nextCamY = Math.max(0.1, Math.min(threeNavLimit, state.camera.position.y + (verticalScale * step)));
    const dy = nextCamY - state.camera.position.y;
    if (Math.abs(dy) < 1e-6) return;
    state.camera.position.y += dy;
    controls.target.y += dy;
    controls.update();
    saveOrbitState();
    userHasInteracted = true;
  }

  function onThreeWheel(e) {
    if (!controls || !state.camera) return;
    e.preventDefault();
    e.stopPropagation();
    applyThreeZoom(Math.exp(Math.max(-1, Math.min(1, e.deltaY / 600))));
  }

  function resize() {
    const width = stageEl.clientWidth || window.innerWidth;
    const height = stageEl.clientHeight || window.innerHeight;
    if (state.renderer?.setSize) state.renderer.setSize(width, height, false);
    if (state.camera) {
      if ("aspect" in state.camera) state.camera.aspect = width / Math.max(height, 1);
      state.camera.updateProjectionMatrix?.();
    }
  }

  function animateControls() {
    frameId = requestAnimationFrame(animateControls);
    try {
      controlFrame += 1;
      reassertThreeCanvasContainment();
      if (controls && state.camera) {
        if (!isOrbitActive) {
          state.camera.position.copy(_orbitCamPos);
          controls.target.copy(_orbitTarget);
        }
        let externalMotion = false;

        if (threeAnimToTarget && threeAnimFromTarget) {
          const t = Math.min((performance.now() - threeAnimStart) / 350, 1);
          const eased = 1 - (1 - t) ** 3;
          controls.target.lerpVectors(threeAnimFromTarget, threeAnimToTarget, eased);
          state.camera.position.lerpVectors(threeAnimFromCam, threeAnimToCam, eased);
          externalMotion = true;
          if (t >= 1) {
            controls.enabled = true;
            threeAnimFromTarget = threeAnimToTarget = threeAnimFromCam = threeAnimToCam = null;
          }
        }

        if (keyNav?.update()) {
          externalMotion = true;
        }

        // Always let OrbitControls reconcile its internal spherical state *after*
        // keyboard/click navigation has translated the camera + target together.
        // This prevents the next pan from reviving an older zoom distance.
        controls.update();

        // DeviceOrientationControls only ever touches camera.quaternion, never
        // position — it runs after OrbitControls.update() so a device's tilt
        // has the final say on look direction, while drag-to-pan/pinch-zoom
        // (a separate degree of freedom, untouched by this) keep working
        // exactly as before. enableRotate is already false (see setup below),
        // so there's no fight over rotation between the two.
        gyroController?.update();

        // Sonification leg: drive Tone.js from the same per-frame camera motion
        // the navigation legs above produced. No-ops until the user enables sound.
        if (audioController && state.camera) {
          if (_audioPrevInit) {
            audioController.update({
              dx: state.camera.position.x - _audioPrevPos.x,
              dy: state.camera.position.y - _audioPrevPos.y,
              dz: state.camera.position.z - _audioPrevPos.z,
            });
          }
          _audioPrevPos.copy(state.camera.position);
          _audioPrevInit = true;
        }

        // Many pieces script their own ambient camera motion every frame
        // (e.g. a slow sway via camera.position.x = ... ; camera.lookAt(...)),
        // and since only the piece's own render call paints pixels when
        // pieceDrivesOwnRender is true, that scripted view always wins over
        // whatever the user just dragged/panned/keyed to — drag interaction
        // looks completely inert even though state.camera *is* updating
        // correctly underneath. Once the user has ever taken control of the
        // camera, latch on a forced bootstrap render (below) for the rest of
        // the session so user input reliably wins from then on.
        if (isOrbitActive || externalMotion) {
          userHasInteracted = true;
        }

        saveOrbitState();
      }

      if (state.renderer && state.scene && state.camera) {
        if ("aspect" in state.camera) {
          const width = stageEl.clientWidth || window.innerWidth;
          const height = stageEl.clientHeight || window.innerHeight;
          const aspect = width / Math.max(height, 1);
          if (Math.abs(state.camera.aspect - aspect) > 0.001) {
            state.camera.aspect = aspect;
            state.camera.updateProjectionMatrix?.();
          }
        }

        state.renderer.autoClear = true;
        state.renderer.localClippingEnabled = false;
        if (state.renderer.shadowMap) state.renderer.shadowMap.enabled = false;

        // Fallback light
        let hasRealLight = false, hasFallback = false;
        state.scene.traverse(obj => {
          if (!obj.isLight) return;
          if (obj.name?.startsWith("__viewer_fallback_")) hasFallback = true;
          else hasRealLight = true;
        });
        if (hasRealLight) {
          state.scene.children.filter(obj => obj.name?.startsWith("__viewer_fallback_")).forEach(obj => state.scene.remove(obj));
        } else if (!hasFallback) {
          const amb = new THREE.AmbientLight(0xffffff, 0.7);
          amb.name = "__viewer_fallback_ambient__";
          state.scene.add(amb);
          const dir = new THREE.DirectionalLight(0xffffff, 0.8);
          dir.position.set(5, 10, 7.5);
          dir.name = "__viewer_fallback_dir__";
          state.scene.add(dir);
        }

        // Visibility/material normalization is expensive on large scenes — run
        // every frame during initial settle-in, then throttle to every 30th frame.
        if (controlFrame <= 60 || controlFrame % 30 === 0) {
          state.scene.traverse(object => {
            object.frustumCulled = false;
            object.layers?.enableAll?.();
            if ((object.isMesh || object.isLine || object.isPoints || object.isSprite) && object.visible === false) {
              object.visible = true;
            }
            if (object.material) {
              const mats = Array.isArray(object.material) ? object.material : [object.material];
              mats.forEach(mat => {
                if (!mat) return;
                mat.clippingPlanes = null;
                mat.clipIntersection = false;
                mat.visible = true;
                if (mat.opacity !== undefined && mat.opacity < 0.05) {
                  mat.opacity = 1; mat.transparent = false;
                }
              });
            }
          });
        }

        // Background sync
        let bg = null;
        for (let el of [canvas, mount, stageEl.querySelector("div"), stageEl, host.querySelector("#container"), host]) {
          if (!el) continue;
          let val = el.style.backgroundColor || el.style.background;
          if (val && val.trim() !== "" && val.trim() !== "transparent" && val.trim() !== "rgba(0, 0, 0, 0)") { bg = val; break; }
          let cmp = window.getComputedStyle(el).backgroundColor;
          if (cmp && cmp.trim() !== "" && cmp.trim() !== "transparent" && cmp.trim() !== "rgba(0, 0, 0, 0)") { bg = cmp; break; }
        }
        bg = bg ?? "#000000";
        syncThreeRendererBackground(state.renderer, state.scene, bg);
        // When the piece drives its own startFrame render loop (the documented
        // contract), it already calls renderer.render() every frame — rendering
        // here too would just duplicate that full-scene draw every tick. The
        // one exception is once the user has taken control of the camera
        // (userHasInteracted): our render call here runs after controls.update()
        // above, so it reflects the user's drag/key state, not the piece's own
        // scripted camera — that's the one that needs to reach the screen.
        if (!pieceDrivesOwnRender || userHasInteracted) {
          state.renderer.render(state.scene, state.camera);
        }
      }
      consecutiveErrors = 0;
    } catch (err) {
      consecutiveErrors += 1;
      if (consecutiveErrors === 1) onError(err);
      if (consecutiveErrors >= 5) cancelAnimationFrame(frameId);
    }
  }

  try {
    const sketchFactory = resolveSketchFactory(code);
    cleanup = sketchFactory({
      THREE: instrumentedThree,
      canvas,
      startFrame,
      size: runtimeSize,
      width: runtimeSize.width,
      height: runtimeSize.height,
    });

    if (!state.renderer || !state.camera) {
      throw new Error("This Three.js piece did not initialize a renderer and camera for immersive mode.");
    }

    reassertThreeCanvasContainment();
    requestAnimationFrame(() => resize());
    controls = new OrbitControls(state.camera, canvas);
    controls.enableDamping = true;
    controls.enablePan = true;
    controls.enableRotate = false;
    controls.screenSpacePanning = true;
    controls.mouseButtons.LEFT = THREE.MOUSE.PAN;
    controls.mouseButtons.MIDDLE = THREE.MOUSE.DOLLY;
    controls.mouseButtons.RIGHT = THREE.MOUSE.PAN;
    controls.touches.ONE = THREE.TOUCH.PAN;
    controls.touches.TWO = THREE.TOUCH.DOLLY_PAN;
    controls.minDistance = 0.6;
    if ("zoomToCursor" in controls) {
      controls.zoomToCursor = true;
    }
    const _initDir = new THREE.Vector3();
    state.camera.getWorldDirection(_initDir);
    const initialCamDist = state.camera.position.length();
    const targetDist = Math.max(initialCamDist * 0.8, 3);
    controls.target.copy(state.camera.position).addScaledVector(_initDir, targetDist);
    const initialTargetDist = state.camera.position.distanceTo(controls.target);
    controls.maxDistance = Math.max(40, initialTargetDist * 4);
    controls.update();
    if (applyShellViewState({ camera: state.camera, controls }, options.initialViewState)) {
      saveOrbitState();
      userHasInteracted = true;
    }

    threeNavLimit = getThreeNavigationLimit();
    keyNav = createKeyboardNavigation(controls, {
      container: stageEl,
      speed: (act) => Math.max(0.05, act.target.distanceTo(act.object.position) * 0.03),
      minX: -threeNavLimit,
      maxX: threeNavLimit,
      minZ: 0.5,
      maxZ: threeNavLimit,
    });

    _orbitCamPos.copy(state.camera.position);
    _orbitTarget.copy(controls.target);
    canvas.style.cursor = "grab";
    canvas.addEventListener("pointerdown", onThreePointerDown);
    canvas.addEventListener("pointermove", onThreePointerMove);
    canvas.addEventListener("pointerup", onThreePointerUp);
    canvas.addEventListener("pointercancel", clearThreePointer);
    canvas.addEventListener("lostpointercapture", clearThreePointer);
    canvas.addEventListener("wheel", onThreeWheel, { passive: false, capture: true });

    const preventNativeGesture = (event) => {
      if (event.cancelable) event.preventDefault();
    };
    canvas.addEventListener("gesturestart", preventNativeGesture);
    canvas.addEventListener("gesturechange", preventNativeGesture);
    canvas.addEventListener("gestureend", preventNativeGesture);
    canvas.addEventListener("touchmove", preventNativeGesture, { passive: false });

    if (options.showViewerControls) {
      viewerControls = createImmersiveViewerControls(stageEl, {
        initialZoomValue: threeZoomValueFromDistance(state.camera.position.distanceTo(controls.target)),
        onZoomSliderInput: (value) => applyThreeZoomValue(value),
        onMoveForward: () => applyThreeDirectionalMove(1, 0),
        onMoveBackward: () => applyThreeDirectionalMove(-1, 0),
        onMoveLeft: () => applyThreeDirectionalMove(0, -1),
        onMoveRight: () => applyThreeDirectionalMove(0, 1),
        onFloatUp: () => applyThreeFloatMove(1),
        onFloatDown: () => applyThreeFloatMove(-1),
      });
    }

    controls.addEventListener("start", () => {
      isOrbitActive = true;
      canvas.style.cursor = "grabbing";
    });
    controls.addEventListener("end", () => {
      isOrbitActive = false;
      canvas.style.cursor = "grab";
      saveOrbitState();
    });

    gyroController = createSharedGyroController(stageEl, state.camera, {
      onActivated() {
        // Tilting the phone to look around is taking control of the camera,
        // exactly as much as a drag or a key press is — without this, pieces
        // that drive their own render loop can silently overwrite the device
        // orientation before the user ever sees it.
        userHasInteracted = true;
      },
    });
    gyroController.setup();

    frameId = requestAnimationFrame(animateControls);

    const resizeObserver = new ResizeObserver(() => resize());
    resizeObserver.observe(stageEl);
    window.addEventListener("resize", resize);

    const destroy = () => {
      resizeObserver.disconnect();
      window.removeEventListener("resize", resize);
      cancelAnimationFrame(frameId);
      controls.dispose();
      viewerControls?.remove();
      gyroController?.dispose();
      audioController?.dispose();
      canvas.removeEventListener("pointerdown", onThreePointerDown);
      canvas.removeEventListener("pointermove", onThreePointerMove);
      canvas.removeEventListener("pointerup", onThreePointerUp);
      canvas.removeEventListener("pointercancel", clearThreePointer);
      canvas.removeEventListener("lostpointercapture", clearThreePointer);
      canvas.removeEventListener("wheel", onThreeWheel, { capture: true });
      canvas.removeEventListener("gesturestart", preventNativeGesture);
      canvas.removeEventListener("gesturechange", preventNativeGesture);
      canvas.removeEventListener("gestureend", preventNativeGesture);
      canvas.removeEventListener("touchmove", preventNativeGesture);
      stopFrameHandles.forEach(stop => stop());
      if (typeof cleanup === "function") cleanup();
      host.remove();
      stageEl.innerHTML = "";
    };
    return {
      destroy,
      // PNG capture: the stage canvas as currently seen (user's perspective).
      getCaptureSurface: () => ({
        canvas,
        beforeCapture: () => {
          try {
            if (state.renderer && state.scene && state.camera) {
              state.renderer.render(state.scene, state.camera);
            }
          } catch (_) {}
        },
      }),
      getViewState: () => shellViewState({ camera: state.camera, controls }),
    };
  } catch (err) {
    onError(err);
  }
}

export function mountAFrameImmersivePiece(stageEl, code, htmlCode, cssCode, onError = console.error, options = {}) {
  stageEl.innerHTML = "";

  const host = document.createElement("div");
  host.style.cssText = "position:absolute;inset:0;width:100%;height:100%;overflow:hidden;background:#000;";

  const style = document.createElement("style");
  style.textContent = `
    a-scene {
      display: block;
      width: 100%;
      height: 100%;
    }
    .a-canvas {
      display: block;
      width: 100% !important;
      height: 100% !important;
    }
    .a-enter-vr {
      display: none !important;
    }
    ${cssCode || ""}
  `;
  host.appendChild(style);

  const mount = document.createElement("div");
  mount.style.cssText = "position:absolute;inset:0;width:100%;height:100%;overflow:hidden;";
  host.appendChild(mount);
  stageEl.appendChild(host);

  let disposed = false;
  let scene = null;
  let resizeObserver = null;
  let viewerControls = null;
  let pointerTarget = null;
  let frameId = 0;
  const audioController = createAudioController(options.sonicParams, stageEl);
  let _aframeAudioPrev = null;
  const stopFrameHandles = [];
  const aframeNav = {
    animFrom: null,
    animTo: null,
    animStart: 0,
    pointer: null,
    hadMultiTouch: false,
    activeTouches: new Set(),
  };
  let aframeZoomSliderValue = 50;

  const startFrame = (handler) => {
    let frameCount = 0;
    let rafId = 0;
    function tick() {
      if (disposed) return;
      frameCount += 1;
      try {
        handler(frameCount);
      } catch (err) {
        onError(err);
        return;
      }
      rafId = requestAnimationFrame(tick);
    }
    rafId = requestAnimationFrame(tick);
    const stop = () => cancelAnimationFrame(rafId);
    stopFrameHandles.push(stop);
    return stop;
  };

  function resizeScene() {
    if (!scene) return;
    scene.style.width = `${Math.max(stageEl.clientWidth, 1)}px`;
    scene.style.height = `${Math.max(stageEl.clientHeight, 1)}px`;
    if (scene.renderer && typeof scene.resize === "function") {
      try {
        scene.resize();
      } catch (err) {
        onError(err);
      }
    }
    window.dispatchEvent(new Event("resize"));
  }

  function getAFrameThree() {
    return window.AFRAME?.THREE || window.THREE;
  }

  function getAFrameCameraObject() {
    if (scene?.camera) return scene.camera;
    const cameraEl = scene?.querySelector("[camera]") || scene?.querySelector("a-camera");
    return cameraEl?.object3D || null;
  }

  function getAFrameCameraMover() {
    const cameraObject = getAFrameCameraObject();
    if (!cameraObject) return null;
    const cameraEl = cameraObject.el || scene?.querySelector("[camera]") || scene?.querySelector("a-camera");
    return cameraEl?.object3D || cameraObject;
  }

  function moveAFrameCameraByWorldDelta(delta) {
    const THREE_NS = getAFrameThree();
    const mover = getAFrameCameraMover();
    if (!THREE_NS || !mover) return false;
    const worldPosition = new THREE_NS.Vector3();
    mover.getWorldPosition(worldPosition);
    worldPosition.add(delta);
    if (mover.parent) {
      mover.parent.worldToLocal(worldPosition);
    }
    mover.position.copy(worldPosition);
    return true;
  }

  function applyAFrameZoom(distance) {
    const THREE_NS = getAFrameThree();
    const cameraObject = getAFrameCameraObject();
    if (!THREE_NS || !cameraObject) return;
    const forward = new THREE_NS.Vector3();
    cameraObject.getWorldDirection(forward);
    moveAFrameCameraByWorldDelta(forward.multiplyScalar(distance));
  }

  function applyAFrameZoomSliderValue(value) {
    if (!Number.isFinite(value)) return;
    const nextValue = Math.max(0, Math.min(100, value));
    const delta = nextValue - aframeZoomSliderValue;
    aframeZoomSliderValue = nextValue;
    applyAFrameZoom(delta * 0.045);
  }

  function applyAFrameDirectionalMove(forwardScale, rightScale) {
    const THREE_NS = getAFrameThree();
    const cameraObject = getAFrameCameraObject();
    if (!THREE_NS || !cameraObject) return;
    const forward = new THREE_NS.Vector3();
    cameraObject.getWorldDirection(forward);
    forward.y = 0;
    if (forward.lengthSq() < 1e-6) {
      forward.set(0, 0, -1);
    } else {
      forward.normalize();
    }
    const right = new THREE_NS.Vector3(-forward.z, 0, forward.x);
    const step = 0.18;
    const shift = forward.multiplyScalar(forwardScale * step).add(right.multiplyScalar(rightScale * step));
    moveAFrameCameraByWorldDelta(shift);
  }

  function applyAFrameFloatMove(verticalScale) {
    const THREE_NS = getAFrameThree();
    if (!THREE_NS || !Number.isFinite(verticalScale)) return;
    moveAFrameCameraByWorldDelta(new THREE_NS.Vector3(0, verticalScale * 0.14, 0));
  }

  function applyAFrameInitialViewState() {
    const camera = readViewVector(options.initialViewState?.camera);
    const target = readViewVector(options.initialViewState?.target);
    const mover = getAFrameCameraMover();
    if (!camera || !mover) return false;
    if (mover.parent) {
      const localCamera = camera.clone();
      mover.parent.worldToLocal(localCamera);
      mover.position.copy(localCamera);
    } else {
      mover.position.copy(camera);
    }
    if (target && typeof mover.lookAt === "function") {
      mover.lookAt(target);
    }
    return true;
  }

  function getAFrameViewState() {
    const THREE_NS = getAFrameThree();
    const mover = getAFrameCameraMover();
    if (!THREE_NS || !mover) return {};
    const camera = new THREE_NS.Vector3();
    mover.getWorldPosition(camera);
    const forward = new THREE_NS.Vector3();
    const cameraObject = getAFrameCameraObject();
    cameraObject?.getWorldDirection?.(forward);
    const target = camera.clone().add(forward.lengthSq() > 0 ? forward.multiplyScalar(4) : new THREE_NS.Vector3(0, 0, -4));
    return shellViewState({ camera: { position: camera }, controls: { target } });
  }

  function activeAFrameTouchCount() {
    return aframeNav.activeTouches.size;
  }

  function onAFramePointerDown(event) {
    if ((event.pointerType || "mouse") === "touch") {
      aframeNav.activeTouches.add(event.pointerId);
      if (activeAFrameTouchCount() > 1) aframeNav.hadMultiTouch = true;
    }
    aframeNav.pointer = {
      id: event.pointerId,
      pointerType: event.pointerType || "mouse",
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
    if ((event.pointerType || "mouse") === "touch" && activeAFrameTouchCount() > 1) {
      aframeNav.hadMultiTouch = true;
    }
  }

  function clearAFramePointer(event) {
    if ((event.pointerType || "mouse") === "touch") {
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
    const rect = (pointerTarget || host).getBoundingClientRect();
    const raycaster = new THREE_NS.Raycaster();
    raycaster.setFromCamera(
      new THREE_NS.Vector2(((event.clientX - rect.left) / rect.width) * 2 - 1, -((event.clientY - rect.top) / rect.height) * 2 + 1),
      cameraObject,
    );

    let hitPoint = null;
    const hits = raycaster.intersectObjects(scene.object3D?.children || [], true)
      .filter(hit => {
        if (hit.object === cameraObject || cameraObject.children?.includes(hit.object)) return false;
        const tagName = hit.object.el?.tagName?.toUpperCase?.() || "";
        const name = (hit.object.name || hit.object.el?.id || "").toLowerCase();
        if (tagName === "A-SKY" || name.includes("sky") || name.includes("background") || name.includes("env")) return false;
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

  function animateAFrameViewerControls() {
    if (disposed) return;
    frameId = requestAnimationFrame(animateAFrameViewerControls);
    const THREE_NS = getAFrameThree();
    const mover = getAFrameCameraMover();
    // Sonification: sample camera world motion every frame (this runs even when
    // no click-to-walk animation is active). No-op until sound is enabled.
    if (audioController && THREE_NS && mover) {
      const wp = mover.getWorldPosition(new THREE_NS.Vector3());
      if (_aframeAudioPrev) {
        audioController.update({ dx: wp.x - _aframeAudioPrev.x, dy: wp.y - _aframeAudioPrev.y, dz: wp.z - _aframeAudioPrev.z });
      }
      _aframeAudioPrev = { x: wp.x, y: wp.y, z: wp.z };
    }
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
    pointerTarget = scene?.canvas || scene?.querySelector("canvas") || host;
    pointerTarget.style.touchAction = "none";
    pointerTarget.addEventListener("pointerdown", onAFramePointerDown);
    pointerTarget.addEventListener("pointermove", onAFramePointerMove);
    pointerTarget.addEventListener("pointerup", onAFramePointerUp);
    pointerTarget.addEventListener("pointercancel", clearAFramePointer);
    pointerTarget.addEventListener("lostpointercapture", clearAFramePointer);
  }

  loadAFrameRuntime().then(() => {
    if (disposed) return;
    mount.innerHTML = htmlCode?.trim() ? htmlCode : '<a-scene id="scene" embedded><a-sky color="#10151f"></a-sky><a-entity camera position="0 1.6 4"></a-entity></a-scene>';
    scene = mount.querySelector("a-scene#scene") || mount.querySelector("a-scene");
    if (!scene) {
      throw new Error('This A-Frame piece did not provide an <a-scene id="scene"> root.');
    }
    if (!scene.id) scene.id = "scene";
    if (!scene.hasAttribute("embedded")) scene.setAttribute("embedded", "");
    scene.setAttribute("vr-mode-ui", "enabled: false");
    // preserveDrawingBuffer keeps frames readable for PNG capture — same
    // renderer-attr merge as the regular-view aframe bootstrap in
    // piece-render.php (piece_export_bootstrap 'aframe').
    {
      const currentRenderer = scene.getAttribute("renderer");
      const rendererValue = typeof currentRenderer === "string" && currentRenderer.trim() !== ""
        ? currentRenderer.replace(/\s*;?\s*$/, "; ") + "preserveDrawingBuffer: true"
        : "preserveDrawingBuffer: true";
      scene.setAttribute("renderer", rendererValue);
    }
    scene.style.display = "block";
    scene.style.width = "100%";
    scene.style.height = "100%";

    if ((code || "").trim()) {
      const sketchFactory = resolveSketchFactory(code);
      const cleanup = sketchFactory({
        AFRAME: window.AFRAME,
        scene,
        startFrame,
        size: { width: Math.max(stageEl.clientWidth, 1), height: Math.max(stageEl.clientHeight, 1) },
      });
      if (typeof cleanup === "function") {
        stopFrameHandles.push(cleanup);
      }
    }

    resizeObserver = new ResizeObserver(resizeScene);
    resizeObserver.observe(stageEl);
    requestAnimationFrame(resizeScene);
    requestAnimationFrame(() => {
      bindAFramePointerControls();
      applyAFrameInitialViewState();
    });
    scene.addEventListener("renderstart", bindAFramePointerControls, { once: true });
    frameId = requestAnimationFrame(animateAFrameViewerControls);
    if (options.showViewerControls) {
      viewerControls = createImmersiveViewerControls(stageEl, {
        initialZoomValue: aframeZoomSliderValue,
        onZoomSliderInput: (value) => applyAFrameZoomSliderValue(value),
        onMoveForward: () => applyAFrameDirectionalMove(1, 0),
        onMoveBackward: () => applyAFrameDirectionalMove(-1, 0),
        onMoveLeft: () => applyAFrameDirectionalMove(0, -1),
        onMoveRight: () => applyAFrameDirectionalMove(0, 1),
        onFloatUp: () => applyAFrameFloatMove(1),
        onFloatDown: () => applyAFrameFloatMove(-1),
      });
    }
  }).catch(onError);

  const destroy = () => {
    disposed = true;
    cancelAnimationFrame(frameId);
    resizeObserver?.disconnect();
    viewerControls?.remove();
    if (pointerTarget) {
      pointerTarget.removeEventListener("pointerdown", onAFramePointerDown);
      pointerTarget.removeEventListener("pointermove", onAFramePointerMove);
      pointerTarget.removeEventListener("pointerup", onAFramePointerUp);
      pointerTarget.removeEventListener("pointercancel", clearAFramePointer);
      pointerTarget.removeEventListener("lostpointercapture", clearAFramePointer);
    }
    stopFrameHandles.forEach((stop) => stop());
    audioController?.dispose();
    try {
      scene?.pause?.();
    } catch (e) {}
    host.remove();
    stageEl.innerHTML = "";
  };
  return {
    destroy,
    // PNG capture: the WebXR scene canvas as currently seen. beforeCapture
    // forces a render so the frame is fresh even if the loop is idle —
    // same fallback as tryForceAframeRender in public-piece-download.js.
    getCaptureSurface: () => ({
      canvas: scene?.canvas || scene?.querySelector?.("canvas.a-canvas") || null,
      beforeCapture: () => {
        try {
          const renderer = scene?.renderer;
          const sceneObject = scene?.object3D;
          const camera = scene?.camera;
          if (renderer && sceneObject && camera) {
            renderer.render(sceneObject, camera);
          }
        } catch (_) {}
      },
      }),
    getViewState: getAFrameViewState,
  };
}

// Phase 2 Entry Point: mountGalleryPiece
export function mountGalleryPiece(stageEl, code, htmlCode, cssCode, engine, title, sourceUrl, prompt, description, onError = console.error, onInteractiveClick = null, options = {}) {
  const runtimeSize = { width: 1280, height: 720 };
  const presentationSurface = engine === "p5" ? createPresentationSurface(1200, 900, 72) : null;
  const shell = createMountedGalleryShell(
    stageEl,
    presentationSurface ? presentationSurface.width / presentationSurface.height : runtimeSize.width / runtimeSize.height,
    presentationSurface ? NORMALIZED_PRESENTATION_GALLERY_PROFILE : undefined
  );

  const host = createImmersiveHost(
    htmlCode, cssCode,
    engine === "p5" ? '<div id="canvas-container"></div>' : engine === "svg" ? '<svg viewBox="0 0 800 600" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%"></svg>' : '<canvas id="piece-canvas"></canvas>',
    runtimeSize,
    engine
  );

  let sourceCanvas = null;
  let artTexture = null;
  let frameId = 0;
  let detectCanvasTimer = null;
  let detectCanvasAttempts = 0;
  let stopSourceLoop = null;
  let p5Instance = null;
  let disposed = false;
  let viewerControls = null;
  let readOnlyOverlay = null;
  let gyroController = null;
  const audioController = createAudioController(options.sonicParams, stageEl);
  const _galleryAudioPrevPos = new THREE.Vector3();
  let _galleryAudioPrevInit = false;
  const galleryButtonForward = new THREE.Vector3();
  const galleryButtonRight = new THREE.Vector3();

  function syncCanvas(nextCanvas) {
    sourceCanvas = nextCanvas;
    const displayCanvas = presentationSurface?.canvas ?? nextCanvas;
    if (!artTexture) {
      artTexture = new THREE.CanvasTexture(displayCanvas);
      artTexture.colorSpace = THREE.SRGBColorSpace;
      shell.artMaterial.map = artTexture;
      shell.artMaterial.needsUpdate = true;
    }

    if (presentationSurface) {
      // Resolve background
      let bg = null;
      for (let el of [nextCanvas, nextCanvas.parentElement, host.querySelector("#canvas-container"), host]) {
        if (!el) continue;
        let val = el.style.backgroundColor || el.style.background;
        if (val && val.trim() !== "" && val.trim() !== "transparent" && val.trim() !== "rgba(0, 0, 0, 0)") { bg = val; break; }
        let cmp = window.getComputedStyle(el).backgroundColor;
        if (cmp && cmp.trim() !== "" && cmp.trim() !== "transparent" && cmp.trim() !== "rgba(0, 0, 0, 0)") { bg = cmp; break; }
      }
      bg = bg ?? "#05070f";
      drawContainedIntoPresentationSurface(
        presentationSurface, nextCanvas.width || runtimeSize.width, nextCanvas.height || runtimeSize.height,
        (ctx, x, y, width, height) => { ctx.drawImage(nextCanvas, x, y, width, height); },
        bg
      );
    }
    const width = displayCanvas.width || runtimeSize.width;
    const height = displayCanvas.height || runtimeSize.height;
    const aspect = Math.max(width / Math.max(height, 1), 0.45);
    updateMountedGalleryLayout(shell, aspect);
    fitMountedGalleryCamera(shell, stageEl);
  }

  function pollForCanvas(root, onMissing, preExisting) {
    let candidate = p5Instance?.canvas instanceof HTMLCanvasElement ? p5Instance.canvas : null;
    if (!candidate) candidate = root.querySelector("canvas") || host.querySelector("canvas");
    if (!candidate && preExisting) {
      candidate = Array.from(document.querySelectorAll("canvas")).find((c) => !preExisting.has(c)) || null;
      if (candidate && candidate.parentElement !== root) root.appendChild(candidate);
    }
    if (candidate instanceof HTMLCanvasElement) {
      if (candidate.width === 0 || candidate.height === 0) {
        candidate.width = runtimeSize.width;
        candidate.height = runtimeSize.height;
      }
      syncCanvas(candidate);
      return;
    }
    if (detectCanvasAttempts >= 80) { onError(onMissing); return; }
    detectCanvasAttempts += 1;
    detectCanvasTimer = window.setTimeout(() => pollForCanvas(root, onMissing, preExisting), 100);
  }

  function galleryZoomValueFromDistance(distance) {
    const minDistance = shell.controls.minDistance || 0.6;
    const maxDistance = shell.controls.maxDistance || Math.max(40, distance * 4);
    if (!Number.isFinite(distance) || maxDistance <= minDistance) return 50;
    return ((maxDistance - Math.max(minDistance, Math.min(maxDistance, distance))) / (maxDistance - minDistance)) * 100;
  }

  function syncViewerControlZoom() {
    viewerControls?.setZoomValue(galleryZoomValueFromDistance(shell.camera.position.distanceTo(shell.controls.target)));
  }

  function applyGalleryZoomValue(value) {
    if (!Number.isFinite(value)) return;
    const minDistance = shell.controls.minDistance || 0.6;
    const currentDistance = shell.camera.position.distanceTo(shell.controls.target);
    const maxDistance = shell.controls.maxDistance || Math.max(40, currentDistance * 4);
    const t = Math.max(0, Math.min(100, value)) / 100;
    const nextDistance = maxDistance - ((maxDistance - minDistance) * t);
    const direction = shell.camera.position.clone().sub(shell.controls.target);
    if (direction.lengthSq() < 1e-8) return;
    direction.setLength(nextDistance);
    shell.camera.position.copy(shell.controls.target).add(direction);
    shell.controls.update();
    syncViewerControlZoom();
  }

  function applyGalleryDirectionalMove(forwardScale, rightScale) {
    shell.camera.getWorldDirection(galleryButtonForward);
    galleryButtonForward.y = 0;
    if (galleryButtonForward.lengthSq() < 1e-6) {
      galleryButtonForward.set(0, 0, -1);
    } else {
      galleryButtonForward.normalize();
    }
    galleryButtonRight.set(-galleryButtonForward.z, 0, galleryButtonForward.x);
    const step = Math.max(0.08, shell.controls.target.distanceTo(shell.camera.position) * 0.035);
    const shift = galleryButtonForward.clone()
      .multiplyScalar(forwardScale * step)
      .add(galleryButtonRight.clone().multiplyScalar(rightScale * step));
    if (shift.lengthSq() < 1e-8) return;
    const nextCamX = Math.max(-8, Math.min(8, shell.camera.position.x + shift.x));
    const nextCamZ = Math.max(0.5, shell.camera.position.z + shift.z);
    const dx = nextCamX - shell.camera.position.x;
    const dz = nextCamZ - shell.camera.position.z;
    if (Math.abs(dx) < 1e-6 && Math.abs(dz) < 1e-6) return;
    shell.camera.position.x += dx;
    shell.camera.position.z += dz;
    shell.controls.target.x += dx;
    shell.controls.target.z += dz;
    shell.controls.update();
  }

  function applyGalleryFloatMove(verticalScale) {
    if (!Number.isFinite(verticalScale)) return;
    const step = Math.max(0.08, shell.controls.target.distanceTo(shell.camera.position) * 0.03);
    const nextCamY = Math.max(0.1, Math.min(24, shell.camera.position.y + (verticalScale * step)));
    const dy = nextCamY - shell.camera.position.y;
    if (Math.abs(dy) < 1e-6) return;
    shell.camera.position.y += dy;
    shell.controls.target.y += dy;
    shell.controls.update();
  }

  async function bootRuntime() {
    try {
      if (engine === "p5") {
        const P5 = await loadP5Runtime();
        const sketchFactory = resolveSketchFactory(code);
        const mountNode = host.querySelector("#canvas-container") || host.querySelector("#sketch-container") || host;
        const preExistingCanvases = new Set(document.querySelectorAll("canvas"));
        p5Instance = new P5(sketchFactory, mountNode);
        pollForCanvas(mountNode, "This p5 piece did not produce a canvas for immersive mode.", preExistingCanvases);
        return;
      }

      if (engine === "svg") {
        const shadowHost = document.createElement("div");
        shadowHost.style.cssText = `position:fixed;left:-10000px;top:0;width:${runtimeSize.width}px;height:${runtimeSize.height}px;pointer-events:none;`;
        const shadowRoot = shadowHost.attachShadow({ mode: "open" });
        if (cssCode) {
          const styleEl = document.createElement("style");
          styleEl.textContent = cssCode;
          shadowRoot.appendChild(styleEl);
        }
        const svgContainer = document.createElement("div");
        svgContainer.style.cssText = "width:100%;height:100%;";
        svgContainer.innerHTML = htmlCode?.trim() ? htmlCode : '<svg viewBox="0 0 800 600" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%"></svg>';
        shadowRoot.appendChild(svgContainer);
        document.body.appendChild(shadowHost);

        const svgEl = shadowRoot.querySelector("svg");
        if (!svgEl) { onError("This SVG piece has no <svg> element."); shadowHost.remove(); return; }

        const vb = svgEl.viewBox?.baseVal;
        const svgNatW = (vb && vb.width > 0) ? vb.width : runtimeSize.width;
        const svgNatH = (vb && vb.height > 0) ? vb.height : runtimeSize.height;
        const svgAspect = svgNatW / Math.max(svgNatH, 1);
        let canvasW = runtimeSize.width;
        let canvasH = Math.round(canvasW / svgAspect);
        if (canvasH < runtimeSize.height) { canvasH = runtimeSize.height; canvasW = Math.round(canvasH * svgAspect); }

        const svgCanvas = document.createElement("canvas");
        svgCanvas.width = canvasW;
        svgCanvas.height = canvasH;
        syncCanvas(svgCanvas);

        window.svgRoot = svgEl;
        const _origGetById = document.getElementById.bind(document);
        document.getElementById = function(id) {
          const found = _origGetById(id);
          if (!found && (id === "container" || id === "canvas-container" || id === "sketch-container")) return window.svgRoot ?? null;
          return found;
        };
        const _origQuerySelector = document.querySelector.bind(document);
        document.querySelector = function(sel) {
          const found = _origQuerySelector(sel);
          if (!found && sel === "svg") return window.svgRoot ?? null;
          return found;
        };

        const sketchFactory = resolveSketchFactory(code);
        if (typeof sketchFactory === "function") {
          try { sketchFactory(); } catch (_) {}
        }

        let drawPending = false;
        async function drawSvgSnapshot() {
          if (drawPending || disposed) return;
          drawPending = true;
          try {
            const svgClone = svgEl.cloneNode(true);
            const liveEls = Array.from(svgEl.querySelectorAll("*"));
            const cloneEls = Array.from(svgClone.querySelectorAll("*"));
            const propertiesToSync = ["transform", "transform-origin", "opacity", "fill", "stroke", "stroke-width", "stroke-dasharray", "stroke-dashoffset", "fill-opacity", "stroke-opacity", "cx", "cy", "r", "rx", "ry", "x", "y", "width", "height", "d", "stop-color", "stop-opacity", "offset", "filter", "clip-path", "mask", "display", "visibility"];
            liveEls.forEach((liveEl, i) => {
              const cloneEl = cloneEls[i];
              if (!cloneEl) return;
              const s = window.getComputedStyle(liveEl);
              propertiesToSync.forEach((prop) => {
                const val = s.getPropertyValue(prop);
                if (val !== undefined && val !== null && val !== "") {
                  if (prop === "transform" && (val === "none" || val === "matrix(1, 0, 0, 1, 0, 0)")) return;
                  if (prop === "opacity" && val === "1") return;
                  if (prop === "fill" && (val === "none" || val === "rgb(0, 0, 0)")) return;
                  if (prop === "stroke" && val === "none") return;
                  cloneEl.style.setProperty(prop, val);
                }
              });
            });
            const styleEl = document.createElementNS("http://www.w3.org/2000/svg", "style");
            styleEl.textContent = (cssCode || "") + "\n* { animation: none !important; transition: none !important; }";
            svgClone.insertBefore(styleEl, svgClone.firstChild);
            const serialized = new XMLSerializer().serializeToString(svgClone);
            const dataUrl = "data:image/svg+xml;charset=utf-8," + encodeURIComponent(serialized);
            await new Promise((resolve) => {
              const img = new Image();
              img.onload = () => {
                const ctx = svgCanvas.getContext("2d");
                if (ctx) { ctx.clearRect(0, 0, svgCanvas.width, svgCanvas.height); ctx.drawImage(img, 0, 0, svgCanvas.width, svgCanvas.height); }
                if (artTexture) artTexture.needsUpdate = true;
                resolve();
              };
              img.onerror = () => resolve();
              img.src = dataUrl;
            });
          } finally {
            drawPending = false;
          }
        }

        await drawSvgSnapshot();
        const intervalId = window.setInterval(() => { drawSvgSnapshot().catch(() => {}); }, 100);

        stopSourceLoop = () => {
          window.clearInterval(intervalId);
          document.getElementById = _origGetById;
          document.querySelector = _origQuerySelector;
          shadowHost.remove();
          delete window.svgRoot;
        };
        return;
      }

      // c2 and generic
      const c2Runtime = engine === "c2" ? await loadC2Runtime() : window.c2;
      const sketchFactory = resolveSketchFactory(code);
      const managedCanvas = host.querySelector("canvas") || document.createElement("canvas");
      managedCanvas.width = runtimeSize.width;
      managedCanvas.height = runtimeSize.height;
      managedCanvas.style.width = `${runtimeSize.width}px`;
      managedCanvas.style.height = `${runtimeSize.height}px`;
      if (!managedCanvas.parentNode) host.appendChild(managedCanvas);
      syncCanvas(managedCanvas);

      let rafId = 0;
      const startFrame = (handler) => {
        let frameCount = 0;
        function tick() {
          frameCount += 1;
          try { handler(frameCount); } catch (err) { onError(err); return; }
          rafId = window.requestAnimationFrame(tick);
        }
        rafId = window.requestAnimationFrame(tick);
        return () => window.cancelAnimationFrame(rafId);
      };

      const c2MediaHelpers = engine === "c2" ? createC2MediaHelpers(managedCanvas, onError) : {};
      const cleanup = sketchFactory({
        c2: c2Runtime,
        canvas: managedCanvas,
        startFrame,
        ...c2MediaHelpers,
        size: runtimeSize,
        width: runtimeSize.width,
        height: runtimeSize.height,
      });

      stopSourceLoop = typeof cleanup === "function" ? cleanup : () => window.cancelAnimationFrame(rafId);

    } catch (err) {
      onError(err);
    }
  }

  const floorNav = createFloorClickNavigation(shell.camera, shell.controls, shell.floor, stageEl);
  const keyNav = createKeyboardNavigation(shell.controls, { container: stageEl });
  gyroController = createSharedGyroController(stageEl, shell.camera);

  // Gallery pieces run in an off-screen canvas (createImmersiveHost) and are
  // texture-projected onto shell.artMesh, so no pointer events ever reach
  // the piece's own click/touch/drag listeners. A click on the art frame
  // dispatches to whichever fullscreen path applies: onInteractiveClick for
  // interactive C2 pieces, readOnlyOverlay.openAt(0) for static engines
  // (P5, SVG, non-interactive C2) that have a fullView overlay configured.
  let disposeInteractiveClick = null;
  if (typeof onInteractiveClick === "function" ||
      (Array.isArray(options.fullView?.items) && options.fullView.items.length > 0)) {
    const clickRaycaster = new THREE.Raycaster();
    let downX = 0, downY = 0;
    const onPointerDown = (e) => { downX = e.clientX; downY = e.clientY; };
    const onPointerUp = (e) => {
      if (Math.hypot(e.clientX - downX, e.clientY - downY) >= 6) return;
      const rect = stageEl.getBoundingClientRect();
      clickRaycaster.setFromCamera(
        new THREE.Vector2(((e.clientX - rect.left) / rect.width) * 2 - 1, -((e.clientY - rect.top) / rect.height) * 2 + 1),
        shell.camera,
      );
      if (clickRaycaster.intersectObject(shell.artMesh, false).length) {
        if (typeof onInteractiveClick === "function") {
          onInteractiveClick();
        } else {
          readOnlyOverlay?.openAt(0);
        }
      }
    };
    stageEl.addEventListener("pointerdown", onPointerDown);
    stageEl.addEventListener("pointerup", onPointerUp);
    disposeInteractiveClick = () => {
      stageEl.removeEventListener("pointerdown", onPointerDown);
      stageEl.removeEventListener("pointerup", onPointerUp);
    };
  }

  if (options.showViewerControls) {
    viewerControls = createImmersiveViewerControls(stageEl, {
      initialZoomValue: galleryZoomValueFromDistance(shell.camera.position.distanceTo(shell.controls.target)),
      onZoomSliderInput: (value) => applyGalleryZoomValue(value),
      onMoveForward: () => applyGalleryDirectionalMove(1, 0),
      onMoveBackward: () => applyGalleryDirectionalMove(-1, 0),
      onMoveLeft: () => applyGalleryDirectionalMove(0, -1),
      onMoveRight: () => applyGalleryDirectionalMove(0, 1),
      onFloatUp: () => applyGalleryFloatMove(1),
      onFloatDown: () => applyGalleryFloatMove(-1),
    });
  }

  if (Array.isArray(options.fullView?.items) && options.fullView.items.length > 0) {
    readOnlyOverlay = createReadOnlyFullViewOverlay(stageEl, options.fullView.items, options.fullView.overlayOptions || {});
  }

  function animate() {
    frameId = requestAnimationFrame(animate);
    floorNav.update();
    keyNav.update();
    const activeSourceCanvas = sourceCanvas;
    if (activeSourceCanvas && artTexture) {
      if (engine !== "p5") {
        const bg = activeSourceCanvas.style.background || activeSourceCanvas.style.backgroundColor;
        if (bg) {
          const ctx = activeSourceCanvas.getContext("2d");
          if (ctx) {
            ctx.save();
            ctx.globalCompositeOperation = "destination-over";
            ctx.fillStyle = bg;
            ctx.fillRect(0, 0, activeSourceCanvas.width, activeSourceCanvas.height);
            ctx.restore();
          }
        }
      }
      if (presentationSurface) {
        drawContainedIntoPresentationSurface(
          presentationSurface, activeSourceCanvas.width || runtimeSize.width, activeSourceCanvas.height || runtimeSize.height,
          (ctx, x, y, width, height) => { ctx.drawImage(activeSourceCanvas, x, y, width, height); },
          "#05070f"
        );
      }
      artTexture.needsUpdate = true;
    }
    shell.controls.update();
    gyroController?.update();
    // Sonification leg: drive Tone.js from the gallery-room camera motion, so
    // ANY piece type (p5/c2/svg shown as a wall, or three/aframe) sonifies in
    // the immersive view. No-op until the user enables sound.
    if (audioController && shell.camera) {
      if (_galleryAudioPrevInit) {
        audioController.update({
          dx: shell.camera.position.x - _galleryAudioPrevPos.x,
          dy: shell.camera.position.y - _galleryAudioPrevPos.y,
          dz: shell.camera.position.z - _galleryAudioPrevPos.z,
        });
      }
      _galleryAudioPrevPos.copy(shell.camera.position);
      _galleryAudioPrevInit = true;
    }
    syncViewerControlZoom();
    shell.renderer.render(shell.scene, shell.camera);
  }

  bootRuntime();
  fitMountedGalleryCamera(shell, stageEl);
  applyShellViewState(shell, options.initialViewState);
  gyroController.setup();
  animate();

  const resizeObserver = new ResizeObserver(() => fitMountedGalleryCamera(shell, stageEl, undefined, false));
  resizeObserver.observe(stageEl);

  function destroy() {
    disposed = true;
    resizeObserver.disconnect();
    if (detectCanvasTimer) window.clearTimeout(detectCanvasTimer);
    cancelAnimationFrame(frameId);
    artTexture?.dispose?.();
    shell.controls.dispose();
    shell.floor.geometry.dispose();
    shell.floor.material.dispose();
    shell.backWall.geometry.dispose();
    shell.backWall.material.dispose();
    shell.framePanel.geometry.dispose();
    shell.framePanel.material.dispose();
    shell.artMesh.geometry.dispose();
    shell.artMaterial.dispose();
    shell.frameMesh.geometry.dispose();
    shell.frameMesh.material.dispose();
    shell.renderer.dispose();
    stopSourceLoop?.();
    p5Instance?.remove?.();
    floorNav.dispose();
    keyNav.dispose();
    gyroController?.dispose();
    audioController?.dispose();
    viewerControls?.remove();
    readOnlyOverlay?.remove();
    disposeInteractiveClick?.();
    host.remove();
    stageEl.innerHTML = "";
  }

  return {
    destroy,
    openFullViewAt(index = 0) {
      readOnlyOverlay?.openAt(index);
    },
    closeFullView() {
      readOnlyOverlay?.close();
    },
    isFullViewOpen() {
      return readOnlyOverlay?.isOpen?.() ?? false;
    },
    // PNG capture: the gallery renderer as currently seen from the user's perspective.
    getCaptureSurface: () => ({
      canvas: shell.renderer.domElement,
      beforeCapture: () => {
        try {
          shell.renderer.render(shell.scene, shell.camera);
        } catch (_) {}
      },
    }),
    getViewState: () => shellViewState(shell),
  };
}

// Standalone image gallery mounting helper (for MediaAsset in Phase 2)
export function mountGalleryImage(stageEl, imageUrl, aspect, title, prompt, description) {
  const shell = createMountedGalleryShell(stageEl, aspect, NORMALIZED_PRESENTATION_GALLERY_PROFILE);
  const texture = new THREE.TextureLoader().load(imageUrl, () => {
    shell.artMaterial.map = texture;
    shell.artMaterial.needsUpdate = true;
  });

  const floorNav = createFloorClickNavigation(shell.camera, shell.controls, shell.floor, stageEl);
  const keyNav = createKeyboardNavigation(shell.controls, { container: stageEl });

  let frameId = 0;
  function animate() {
    frameId = requestAnimationFrame(animate);
    floorNav.update();
    keyNav.update();
    shell.controls.update();
    shell.renderer.render(shell.scene, shell.camera);
  }
  animate();

  const resizeObserver = new ResizeObserver(() => fitMountedGalleryCamera(shell, stageEl, undefined, false));
  resizeObserver.observe(stageEl);

  return () => {
    resizeObserver.disconnect();
    cancelAnimationFrame(frameId);
    texture.dispose();
    shell.controls.dispose();
    shell.floor.geometry.dispose();
    shell.floor.material.dispose();
    shell.backWall.geometry.dispose();
    shell.backWall.material.dispose();
    shell.framePanel.geometry.dispose();
    shell.framePanel.material.dispose();
    shell.artMesh.geometry.dispose();
    shell.artMaterial.dispose();
    shell.frameMesh.geometry.dispose();
    shell.frameMesh.material.dispose();
    shell.renderer.dispose();
    floorNav.dispose();
    keyNav.dispose();
    stageEl.innerHTML = "";
  };
}

// Phase 3 Entry Point: mountExhibitWall
export function mountExhibitWall(stageEl, items, rows, cols, options = {}) {
  const wallWidth = Math.max(22, cols * WALL_FRAME_SLOT_WIDTH + 2);
  const labels = items.map(item => ({
    title: item.title || "Untitled",
    subtitle: item.kind === "piece" ? engineLabel(item.engine) : "Image"
  }));

  const shell = createMultiFrameExhibitWall(stageEl, items.length, rows, cols, labels, {
    labelPosition: options.labelPosition,
  });
  const floorNav = createFloorClickNavigation(shell.camera, shell.controls, shell.floor, stageEl, { maxX: wallWidth / 2 });
  const keyNav = createKeyboardNavigation(shell.controls, { container: stageEl, minX: -wallWidth / 2, maxX: wallWidth / 2 });

  // Progressive rendering state
  const runtimeSize = { width: 400, height: 300 }; // small size for grid items
  const activeRuntimes = new Map(); // index -> { host, canvas, stop, texture, p5Instance }

  let frameId = 0;
  let disposed = false;
  let viewerControls = null;
  let readOnlyOverlay = null;
  let disposeSlotFullViewClick = null;
  let gyroController = null;
  const exhibitButtonForward = new THREE.Vector3();
  const exhibitButtonRight = new THREE.Vector3();
  gyroController = createSharedGyroController(stageEl, shell.camera);

  // Sonification: only the item nearest the current camera target sonifies
  // (mirrors the single-piece mounts, which have exactly one item to pick).
  // The controller is torn down and rebuilt whenever focus moves to a
  // different item, since createAudioController binds one fixed sonicParams
  // for its lifetime.
  let audioController = null;
  let audioControllerIndex = -1;
  let lastAudioRebindAt = 0;
  // Belt-and-suspenders alongside the distance margin below: a click's
  // unmute path awaits Tone.start(), which takes at least one more
  // animation frame to resolve — without a floor on how often the
  // controller can actually be rebuilt, a focus flip in that window can
  // dispose the very controller the click just enabled before it's audible.
  const AUDIO_REBIND_COOLDOWN_MS = 500;
  const exhibitAudioPrevPos = new THREE.Vector3();
  let exhibitAudioPrevInit = false;

  // A margin (not just raw nearest-wins) so OrbitControls damping near a
  // boundary between two similarly-distant slots doesn't flip the focused
  // index — and therefore the audio controller — every animation frame.
  // The current slot has to be measurably beaten before focus moves on.
  const FOCUS_SWITCH_MARGIN = 0.85;

  function computeFocusedSlotIndex(currentIndex = -1) {
    let closestIndex = -1;
    let minDistance = Infinity;
    let currentDistance = Infinity;
    const target = shell.controls.target;
    items.forEach((item, index) => {
      if (item.kind !== "piece" && item.kind !== "image") return;
      const slot = shell.slots[index];
      const center = slot?.center;
      if (!center) return;
      const dx = center.x - target.x;
      const dy = center.y - target.y;
      const dz = center.z - target.z;
      const dist = dx * dx + dy * dy + dz * dz * 0.35;
      if (dist < minDistance) { minDistance = dist; closestIndex = index; }
      if (index === currentIndex) currentDistance = dist;
    });
    if (currentIndex >= 0 && Number.isFinite(currentDistance) && closestIndex !== currentIndex) {
      // Require the new candidate to beat the current focus by a margin,
      // not just edge it out — otherwise stick with the current focus.
      if (minDistance >= currentDistance * FOCUS_SWITCH_MARGIN) return currentIndex;
    }
    return closestIndex;
  }

  function getLiveSlots() {
    const budget = getProgressiveExhibitLiveBudget(window.innerWidth);
    return selectProgressiveExhibitSlots(items, shell.slots.map(s => s.center), shell.controls.target, budget);
  }

  function exhibitZoomValueFromDistance(distance) {
    const minDistance = shell.controls.minDistance || 0.6;
    const maxDistance = shell.controls.maxDistance || Math.max(40, distance * 4);
    if (!Number.isFinite(distance) || maxDistance <= minDistance) return 50;
    return ((maxDistance - Math.max(minDistance, Math.min(maxDistance, distance))) / (maxDistance - minDistance)) * 100;
  }

  function syncExhibitViewerZoom() {
    viewerControls?.setZoomValue(exhibitZoomValueFromDistance(shell.camera.position.distanceTo(shell.controls.target)));
  }

  function applyExhibitZoomValue(value) {
    if (!Number.isFinite(value)) return;
    const minDistance = shell.controls.minDistance || 0.6;
    const currentDistance = shell.camera.position.distanceTo(shell.controls.target);
    const maxDistance = shell.controls.maxDistance || Math.max(40, currentDistance * 4);
    const t = Math.max(0, Math.min(100, value)) / 100;
    const nextDistance = maxDistance - ((maxDistance - minDistance) * t);
    const direction = shell.camera.position.clone().sub(shell.controls.target);
    if (direction.lengthSq() < 1e-8) return;
    direction.setLength(nextDistance);
    shell.camera.position.copy(shell.controls.target).add(direction);
    shell.controls.update();
    syncExhibitViewerZoom();
  }

  function applyExhibitDirectionalMove(forwardScale, rightScale) {
    shell.camera.getWorldDirection(exhibitButtonForward);
    exhibitButtonForward.y = 0;
    if (exhibitButtonForward.lengthSq() < 1e-6) {
      exhibitButtonForward.set(0, 0, -1);
    } else {
      exhibitButtonForward.normalize();
    }
    exhibitButtonRight.set(-exhibitButtonForward.z, 0, exhibitButtonForward.x);
    const step = Math.max(0.08, shell.controls.target.distanceTo(shell.camera.position) * 0.035);
    const shift = exhibitButtonForward.clone()
      .multiplyScalar(forwardScale * step)
      .add(exhibitButtonRight.clone().multiplyScalar(rightScale * step));
    if (shift.lengthSq() < 1e-8) return;
    const nextCamX = Math.max(-wallWidth / 2, Math.min(wallWidth / 2, shell.camera.position.x + shift.x));
    const nextCamZ = Math.max(0.5, shell.camera.position.z + shift.z);
    const dx = nextCamX - shell.camera.position.x;
    const dz = nextCamZ - shell.camera.position.z;
    if (Math.abs(dx) < 1e-6 && Math.abs(dz) < 1e-6) return;
    shell.camera.position.x += dx;
    shell.camera.position.z += dz;
    shell.controls.target.x += dx;
    shell.controls.target.z += dz;
    shell.controls.update();
  }

  function applyExhibitFloatMove(verticalScale) {
    if (!Number.isFinite(verticalScale)) return;
    const step = Math.max(0.08, shell.controls.target.distanceTo(shell.camera.position) * 0.03);
    const nextCamY = Math.max(0.1, Math.min(24, shell.camera.position.y + (verticalScale * step)));
    const dy = nextCamY - shell.camera.position.y;
    if (Math.abs(dy) < 1e-6) return;
    shell.camera.position.y += dy;
    shell.controls.target.y += dy;
    shell.controls.update();
  }

  function updateProgressiveLoading() {
    if (disposed) return;
    const liveSlots = getLiveSlots();
    
    // Boot up newly live slots
    liveSlots.forEach(async index => {
      const existing = activeRuntimes.get(index);
      if (existing) {
        if (!existing.failed) return; // already active and healthy
        // Previously-failed slot occupying this live budget slot — tear down and retry.
        existing.stop?.();
        existing.texture?.dispose();
        existing.host?.remove();
        activeRuntimes.delete(index);
      }

      const item = items[index];
      const slot = shell.slots[index];

      // Three.js / A-Frame: boot in off-screen srcdoc iframe, copy canvas to proxy
      if (item.engine === 'three' || item.engine === 'aframe') {
        const srcdoc = item.full_view?.srcdoc;
        if (!srcdoc) {
          activeRuntimes.set(index, { host: null, sourceCanvas: null, stop: () => {}, texture: null, p5Instance: null, svgInterval: null, failed: true });
          return;
        }

        const iframe = document.createElement('iframe');
        iframe.style.cssText = `position:fixed;left:-10000px;top:0;width:${runtimeSize.width}px;height:${runtimeSize.height}px;pointer-events:none;border:none;`;
        iframe.setAttribute('sandbox', 'allow-scripts allow-same-origin');
        document.body.appendChild(iframe);

        const proxyCanvas = document.createElement('canvas');
        proxyCanvas.width = runtimeSize.width;
        proxyCanvas.height = runtimeSize.height;

        let iframeCanvas = null;
        let liveTexture = null;
        let iframeRafId = 0;

        // Show thumbnail as placeholder while the iframe piece boots
        if (item.thumbnail_url) {
          new THREE.TextureLoader().load(item.thumbnail_url, (thumbTex) => {
            thumbTex.colorSpace = THREE.SRGBColorSpace;
            if (!liveTexture) {
              slot.artMaterial.map = thumbTex;
              slot.artMaterial.color.set('#ffffff');
              slot.artMaterial.needsUpdate = true;
            } else {
              thumbTex.dispose();
            }
          });
        }

        function syncFrame() {
          if (!iframeCanvas) {
            try {
              const candidate = iframe.contentDocument?.querySelector('canvas');
              if (candidate instanceof HTMLCanvasElement && candidate.width > 0 && candidate.height > 0) {
                iframeCanvas = candidate;
              }
            } catch (_) { /* cross-origin guard */ }
          }
          if (iframeCanvas) {
            try {
              const ctx = proxyCanvas.getContext('2d');
              ctx?.drawImage(iframeCanvas, 0, 0, proxyCanvas.width, proxyCanvas.height);
              if (!liveTexture) {
                liveTexture = new THREE.CanvasTexture(proxyCanvas);
                liveTexture.colorSpace = THREE.SRGBColorSpace;
                slot.artMaterial.map = liveTexture;
                slot.artMaterial.color.set('#ffffff');
                slot.artMaterial.needsUpdate = true;
                const r = activeRuntimes.get(index);
                if (r) r.texture = liveTexture;
              }
              liveTexture.needsUpdate = true;
            } catch (_) { /* tainted canvas guard */ }
          }
          iframeRafId = requestAnimationFrame(syncFrame);
        }

        activeRuntimes.set(index, {
          host: iframe,
          sourceCanvas: proxyCanvas,
          stop: () => { cancelAnimationFrame(iframeRafId); iframe.remove(); liveTexture?.dispose(); },
          texture: null,
          p5Instance: null,
          svgInterval: null,
          failed: false,
        });

        iframe.addEventListener('load', () => { iframeRafId = requestAnimationFrame(syncFrame); }, { once: true });
        iframe.srcdoc = srcdoc;
        return;
      }

      const host = createImmersiveHost(
        item.html_code, item.css_code,
        item.engine === "p5" ? '<div id="canvas-container"></div>' : item.engine === "svg" ? '<svg viewBox="0 0 800 600" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%"></svg>' : '<canvas id="piece-canvas"></canvas>',
        runtimeSize,
        item.engine
      );
      
      let sourceCanvas = null;
      let texture = null;
      let p5Instance = null;
      let stop = null;
      let svgInterval = null;

      function syncSlotCanvas(canvasElement) {
        sourceCanvas = canvasElement;
        texture = new THREE.CanvasTexture(canvasElement);
        texture.colorSpace = THREE.SRGBColorSpace;
        slot.artMaterial.map = texture;
        slot.artMaterial.needsUpdate = true;
      }

      activeRuntimes.set(index, {
        host,
        sourceCanvas: null,
        stop: () => host.remove(),
        texture: null,
        p5Instance: null,
        svgInterval: null,
        failed: false,
        pending: true,
      });

      try {
        if (item.engine === "p5") {
          const P5 = await loadP5Runtime();
          if (disposed || !activeRuntimes.has(index)) {
            host.remove();
            return;
          }
          const sketchFactory = resolveSketchFactory(item.generated_code);
          const mount = host.querySelector("#canvas-container") || host.querySelector("#sketch-container") || host;
          const preExistingCanvases = new Set(document.querySelectorAll("canvas"));
          p5Instance = new P5(sketchFactory, mount);

          let attempts = 0;
          const poll = () => {
            let canv = p5Instance?.canvas instanceof HTMLCanvasElement ? p5Instance.canvas : null;
            if (!canv) canv = mount.querySelector("canvas") || host.querySelector("canvas");
            if (!canv) {
              canv = Array.from(document.querySelectorAll("canvas")).find((c) => !preExistingCanvases.has(c)) || null;
              if (canv && canv.parentElement !== mount) mount.appendChild(canv);
            }
            if (canv) {
              if (canv.width === 0) { canv.width = runtimeSize.width; canv.height = runtimeSize.height; }
              syncSlotCanvas(canv);
              const runtime = activeRuntimes.get(index);
              if (runtime) { runtime.sourceCanvas = canv; runtime.texture = texture; runtime.failed = false; }
            } else if (attempts < 50) {
              attempts++;
              setTimeout(poll, 100);
            } else {
              console.warn(`Progressive slot ${index} (${item.title || "untitled"}, p5) never produced a canvas.`);
              const runtime = activeRuntimes.get(index);
              if (runtime) runtime.failed = true;
            }
          };
          poll();

          stop = () => {
            p5Instance?.remove?.();
          };
        } else if (item.engine === "svg") {
          const shadowHost = document.createElement("div");
          shadowHost.style.cssText = `position:fixed;left:-10000px;top:0;width:${runtimeSize.width}px;height:${runtimeSize.height}px;pointer-events:none;`;
          const shadowRoot = shadowHost.attachShadow({ mode: "open" });
          if (item.css_code) {
            const styleEl = document.createElement("style");
            styleEl.textContent = item.css_code;
            shadowRoot.appendChild(styleEl);
          }
          const svgContainer = document.createElement("div");
          svgContainer.innerHTML = item.html_code?.trim() ? item.html_code : '<svg viewBox="0 0 800 600" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%"></svg>';
          shadowRoot.appendChild(svgContainer);
          document.body.appendChild(shadowHost);

          const svgEl = shadowRoot.querySelector("svg");
          if (svgEl) {
            const svgCanvas = document.createElement("canvas");
            svgCanvas.width = runtimeSize.width;
            svgCanvas.height = runtimeSize.height;
            syncSlotCanvas(svgCanvas);

            window.svgRoot = svgEl;
            const sketchFactory = resolveSketchFactory(item.generated_code);
            if (typeof sketchFactory === "function") {
              try { sketchFactory(); } catch (sketchErr) {
                console.warn(`Progressive slot ${index} (${item.title || "untitled"}, svg) sketch init failed:`, sketchErr);
              }
            }

            let drawPending = false;
            const drawSvg = async () => {
              if (drawPending) return;
              drawPending = true;
              try {
                const svgClone = svgEl.cloneNode(true);
                const liveEls = Array.from(svgEl.querySelectorAll("*"));
                const cloneEls = Array.from(svgClone.querySelectorAll("*"));
                const props = ["transform", "transform-origin", "opacity", "fill", "stroke", "stroke-width", "cx", "cy", "r", "x", "y", "width", "height", "d", "stop-color", "offset", "filter", "display"];
                liveEls.forEach((liveEl, i) => {
                  const cloneEl = cloneEls[i];
                  if (!cloneEl) return;
                  const s = window.getComputedStyle(liveEl);
                  props.forEach(p => {
                    const val = s.getPropertyValue(p);
                    if (val) cloneEl.style.setProperty(p, val);
                  });
                });
                const styleEl = document.createElementNS("http://www.w3.org/2000/svg", "style");
                styleEl.textContent = "* { animation: none !important; transition: none !important; }";
                svgClone.insertBefore(styleEl, svgClone.firstChild);
                const serialized = new XMLSerializer().serializeToString(svgClone);
                const dataUrl = "data:image/svg+xml;charset=utf-8," + encodeURIComponent(serialized);
                await new Promise(res => {
                  const img = new Image();
                  img.onload = () => {
                    const ctx = svgCanvas.getContext("2d");
                    if (ctx) { ctx.clearRect(0, 0, svgCanvas.width, svgCanvas.height); ctx.drawImage(img, 0, 0, svgCanvas.width, svgCanvas.height); }
                    if (texture) texture.needsUpdate = true;
                    res();
                  };
                  img.onerror = () => res();
                  img.src = dataUrl;
                });
              } finally {
                drawPending = false;
              }
            };

            drawSvg();
            svgInterval = setInterval(drawSvg, 150);

            stop = () => {
              clearInterval(svgInterval);
              shadowHost.remove();
              delete window.svgRoot;
            };
          }
        } else {
          // c2 / three / generic
          const c2Runtime = item.engine === "c2" ? await loadC2Runtime() : window.c2;
          if (disposed || !activeRuntimes.has(index)) {
            host.remove();
            return;
          }
          const sketchFactory = resolveSketchFactory(item.generated_code);
          const managedCanvas = host.querySelector("canvas") || document.createElement("canvas");
          managedCanvas.width = runtimeSize.width;
          managedCanvas.height = runtimeSize.height;
          if (!managedCanvas.parentNode) host.appendChild(managedCanvas);
          syncSlotCanvas(managedCanvas);

          let rafId = 0;
          let tickErrors = 0;
          const startFrame = (handler) => {
            let count = 0;
            function tick() {
              count++;
              try {
                handler(count);
                tickErrors = 0;
              } catch (err) {
                tickErrors++;
                if (tickErrors === 1) {
                  console.warn(`Progressive slot ${index} (${item.title || "untitled"}, ${item.engine}) frame handler error:`, err);
                }
                if (tickErrors >= 5) return;
              }
              rafId = requestAnimationFrame(tick);
            }
            rafId = requestAnimationFrame(tick);
            return () => cancelAnimationFrame(rafId);
          };

          const c2MediaHelpers = item.engine === "c2" ? createC2MediaHelpers(managedCanvas, (err) => {
            console.warn(`Progressive slot ${index} (${item.title || "untitled"}, c2) media helper error:`, err);
          }) : {};
          if (!window.THREE) window.THREE = THREE;
          const cleanup = sketchFactory({
            THREE: THREE, // Hand global THREE for inline three pieces on exhibit walls
            c2: c2Runtime,
            canvas: managedCanvas,
            startFrame,
            ...c2MediaHelpers,
            size: runtimeSize,
            width: runtimeSize.width,
            height: runtimeSize.height
          });

          stop = () => {
            if (typeof cleanup === "function") cleanup();
            else cancelAnimationFrame(rafId);
          };
        }
      } catch (err) {
        console.warn(`Progressive slot ${index} (${item.title || "untitled"}, ${item.engine}) failed to boot:`, err);
        const runtime = activeRuntimes.get(index);
        if (runtime) runtime.failed = true;
      }

      const currentRuntime = activeRuntimes.get(index);
      activeRuntimes.set(index, {
        host,
        sourceCanvas,
        stop,
        texture,
        p5Instance,
        svgInterval,
        failed: Boolean(currentRuntime?.failed) || (!sourceCanvas && !stop),
        pending: false,
      });
    });

    // Tear down no-longer-live slots
    activeRuntimes.forEach((runtime, index) => {
      if (liveSlots.has(index)) return; // keeps being live
      
      // Stop and clean up
      runtime.stop?.();
      runtime.texture?.dispose();
      runtime.host?.remove();
      
      // Revert to thumbnail or solid color
      const item = items[index];
      const slot = shell.slots[index];
      slot.artMaterial.map = null;
      
      if (item.thumbnail_url && (item.thumbnail_url.startsWith("/") || item.thumbnail_url.startsWith("http"))) {
        new THREE.TextureLoader().load(item.thumbnail_url, tex => {
          // Check if slot wasn't booted back in the meantime
          if (!activeRuntimes.has(index)) {
            slot.artMaterial.map = tex;
            slot.artMaterial.needsUpdate = true;
          } else {
            tex.dispose();
          }
        });
      } else {
        slot.artMaterial.color.set("#e8e4de");
      }
      
      activeRuntimes.delete(index);
    });
  }

  // Populate images and non-live thumbnails on initial load
  items.forEach((item, index) => {
    const slot = shell.slots[index];
    if (item.kind === "image" && item.imageUrl) {
      new THREE.TextureLoader().load(item.imageUrl, tex => {
        slot.artMaterial.map = tex;
        slot.artMaterial.color.set("#ffffff");
        slot.artMaterial.needsUpdate = true;
      });
    } else if (item.kind === "piece" && item.thumbnail_url) {
      new THREE.TextureLoader().load(item.thumbnail_url, tex => {
        if (!activeRuntimes.has(index)) {
          slot.artMaterial.map = tex;
          slot.artMaterial.color.set("#ffffff");
          slot.artMaterial.needsUpdate = true;
        } else {
          tex.dispose();
        }
      });
    }
  });

  // Track if camera or target moved to update progressive loading
  const lastTarget = new THREE.Vector3().copy(shell.controls.target);

  if (options.showViewerControls) {
    viewerControls = createImmersiveViewerControls(stageEl, {
      initialZoomValue: exhibitZoomValueFromDistance(shell.camera.position.distanceTo(shell.controls.target)),
      onZoomSliderInput: (value) => applyExhibitZoomValue(value),
      onMoveForward: () => applyExhibitDirectionalMove(1, 0),
      onMoveBackward: () => applyExhibitDirectionalMove(-1, 0),
      onMoveLeft: () => applyExhibitDirectionalMove(0, -1),
      onMoveRight: () => applyExhibitDirectionalMove(0, 1),
      onFloatUp: () => applyExhibitFloatMove(1),
      onFloatDown: () => applyExhibitFloatMove(-1),
    });
  }

  const fullViewItems = items.map((item) => item?.full_view || null);
  const immersiveHrefs = items.map((item) => item?.immersive_href || null);
  let selectedSourceIndex = items.findIndex((item) => item?.download_url);
  if (selectedSourceIndex < 0) selectedSourceIndex = 0;
  const hasFullViewItems = fullViewItems.some((item) => Boolean(item));
  const hasImmersiveHrefs = immersiveHrefs.some((href) => Boolean(href));
  const slideshowEntries = fullViewItems
    .map((item, sourceIndex) => item ? { item, sourceIndex } : null)
    .filter((entry) => Boolean(entry));
  const slideshowIndexBySourceIndex = new Map(
    slideshowEntries.map((entry, slideshowIndex) => [entry.sourceIndex, slideshowIndex]),
  );
  if (hasFullViewItems) {
    readOnlyOverlay = createReadOnlyFullViewOverlay(stageEl, slideshowEntries.map((entry) => entry.item), {
      layout: "single",
      onActiveItemChange(item) {
        const matchedIndex = items.findIndex((candidate) => candidate?.full_view === item);
        if (matchedIndex >= 0) selectedSourceIndex = matchedIndex;
      },
    });
  }
  if (hasFullViewItems || hasImmersiveHrefs) {

    const clickRaycaster = new THREE.Raycaster();
    let downX = 0;
    let downY = 0;
    const onPointerDown = (event) => {
      downX = event.clientX;
      downY = event.clientY;
    };
    const onPointerUp = (event) => {
      if (readOnlyOverlay?.isOpen()) return;
      if (Math.hypot(event.clientX - downX, event.clientY - downY) >= 6) return;
      const rect = stageEl.getBoundingClientRect();
      clickRaycaster.setFromCamera(
        new THREE.Vector2(((event.clientX - rect.left) / rect.width) * 2 - 1, -((event.clientY - rect.top) / rect.height) * 2 + 1),
        shell.camera,
      );
      const slotMeshes = shell.slots.map((slot) => slot.artMesh);
      const hits = clickRaycaster.intersectObjects(slotMeshes, false);
      if (!hits.length) return;
      const slotIndex = slotMeshes.indexOf(hits[0].object);
      if (slotIndex < 0) return;
      selectedSourceIndex = slotIndex;
      if (fullViewItems[slotIndex]) {
        const slideshowIndex = slideshowIndexBySourceIndex.get(slotIndex);
        if (Number.isFinite(slideshowIndex)) {
          readOnlyOverlay.openAt(slideshowIndex);
          return;
        }
      }
      if (immersiveHrefs[slotIndex]) {
        window.location.assign(immersiveHrefs[slotIndex]);
      }
    };
    stageEl.addEventListener("pointerdown", onPointerDown);
    stageEl.addEventListener("pointerup", onPointerUp);
    disposeSlotFullViewClick = () => {
      stageEl.removeEventListener("pointerdown", onPointerDown);
      stageEl.removeEventListener("pointerup", onPointerUp);
    };
  }
  
  function animate() {
    frameId = requestAnimationFrame(animate);
    floorNav.update();
    keyNav.update();
    shell.controls.update();
    gyroController?.update();
    syncExhibitViewerZoom();

    // Sonification: rebind the controller whenever focus moves to a
    // different item, then drive it from wall-camera motion like the
    // single-piece mounts do.
    const focusedIndex = computeFocusedSlotIndex(audioControllerIndex);
    const nowTs = performance.now();
    if (focusedIndex !== audioControllerIndex && nowTs - lastAudioRebindAt >= AUDIO_REBIND_COOLDOWN_MS) {
      audioController?.dispose();
      const focusedItem = focusedIndex >= 0 ? items[focusedIndex] : null;
      audioController = createAudioController(focusedItem?.sonicParams, stageEl);
      audioControllerIndex = focusedIndex;
      lastAudioRebindAt = nowTs;
      exhibitAudioPrevInit = false;
    }
    if (audioController && shell.camera) {
      if (exhibitAudioPrevInit) {
        audioController.update({
          dx: shell.camera.position.x - exhibitAudioPrevPos.x,
          dy: shell.camera.position.y - exhibitAudioPrevPos.y,
          dz: shell.camera.position.z - exhibitAudioPrevPos.z,
        });
      }
      exhibitAudioPrevPos.copy(shell.camera.position);
      exhibitAudioPrevInit = true;
    }

    // Update live textures
    activeRuntimes.forEach((runtime) => {
      if (runtime.sourceCanvas && runtime.texture) {
        runtime.texture.needsUpdate = true;
      }
    });

    if (lastTarget.distanceTo(shell.controls.target) > 0.05) {
      lastTarget.copy(shell.controls.target);
      updateProgressiveLoading();
    }
    
    shell.renderer.render(shell.scene, shell.camera);
  }

  updateProgressiveLoading();
  gyroController.setup();
  applyShellViewState(shell, options.initialViewState);
  animate();

  const resizeObserver = new ResizeObserver(() => {
    fitMultiFrameExhibitCamera(shell, stageEl, false);
    updateProgressiveLoading();
  });
  resizeObserver.observe(stageEl);

  function destroy() {
    disposed = true;
    resizeObserver.disconnect();
    cancelAnimationFrame(frameId);
    audioController?.dispose();
    activeRuntimes.forEach(runtime => {
      runtime.stop?.();
      runtime.texture?.dispose();
      runtime.host?.remove();
    });
    activeRuntimes.clear();
    shell.controls.dispose();
    shell.floor.geometry.dispose();
    shell.floor.material.dispose();
    shell.backWall.geometry.dispose();
    shell.backWall.material.dispose();
    shell.slots.forEach(slot => {
      slot.artMesh.geometry.dispose();
      slot.artMaterial.dispose();
      slot.frameMesh.geometry.dispose();
      slot.frameMesh.material.dispose();
      slot.framePanel.geometry.dispose();
      slot.framePanel.material.dispose();
      if (slot.labelMesh) {
        slot.labelMesh.geometry.dispose();
        slot.labelMaterial.dispose();
      }
    });
    shell.renderer.dispose();
    floorNav.dispose();
    keyNav.dispose();
    gyroController?.dispose();
    viewerControls?.remove();
    readOnlyOverlay?.remove();
    disposeSlotFullViewClick?.();
    stageEl.innerHTML = "";
  }

  return {
    destroy,
    getSelectedItem() {
      return items[selectedSourceIndex] || null;
    },
    getActiveIndex() {
      // Return the index of the wall slot closest to the camera's look-at target.
      // Used by the slideshow button to open at the piece the user is focused on.
      const closestIndex = computeFocusedSlotIndex();
      return closestIndex >= 0 ? closestIndex : selectedSourceIndex;
    },
    getViewState() {
      return shellViewState(shell, { activeIndex: selectedSourceIndex });
    },
    getCaptureSurface: () => ({
      canvas: shell.renderer.domElement,
      beforeCapture: () => {
        try {
          shell.renderer.render(shell.scene, shell.camera);
        } catch (_) {}
      },
    }),
    openSlideshowAt(index = 0) {
      if (!slideshowEntries.length && !immersiveHrefs.some(Boolean)) return;
      const safeIndex = Math.max(0, Math.min(items.length - 1, index));
      selectedSourceIndex = safeIndex;
      const slideshowIndex = slideshowIndexBySourceIndex.get(safeIndex);
      if (Number.isFinite(slideshowIndex)) {
        readOnlyOverlay?.openAt(slideshowIndex);
        return;
      }
      // Pieces like Three.js and A-Frame can't render in the overlay — navigate to their
      // canonical immersive route instead (same as clicking them on the exhibit wall).
      if (immersiveHrefs[safeIndex]) {
        window.location.assign(immersiveHrefs[safeIndex]);
        return;
      }
      readOnlyOverlay?.openAt(0);
    },
    openFullViewAt(index = 0) {
      this.openSlideshowAt(index);
    },
    closeFullView() {
      readOnlyOverlay?.close();
    },
    isFullViewOpen() {
      return readOnlyOverlay?.isOpen?.() ?? false;
    },
  };
}
