import * as THREE from 'https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js';
import { OrbitControls } from 'https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/controls/OrbitControls.js';

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
  const { speed = 0.05, minX = -8, maxX = 8, minY = -Infinity, maxY = Infinity, minZ = 0.5, maxZ = Infinity, container } = options;
  const keys = new Set();

  function onKeyDown(e) {
    if (e.key === "ArrowLeft" || e.key === "ArrowRight" || e.key === "ArrowUp" || e.key === "ArrowDown" ||
        e.code === "KeyW" || e.code === "KeyA" || e.code === "KeyS" || e.code === "KeyD") {
      e.preventDefault();
      let mappedKey = e.key;
      if (e.code === "KeyW") mappedKey = "ArrowUp";
      if (e.code === "KeyS") mappedKey = "ArrowDown";
      if (e.code === "KeyA") mappedKey = "ArrowLeft";
      if (e.code === "KeyD") mappedKey = "ArrowRight";
      keys.add(mappedKey);
    }
  }

  function onKeyUp(e) {
    let mappedKey = e.key;
    if (e.code === "KeyW") mappedKey = "ArrowUp";
    if (e.code === "KeyS") mappedKey = "ArrowDown";
    if (e.code === "KeyA") mappedKey = "ArrowLeft";
    if (e.code === "KeyD") mappedKey = "ArrowRight";
    keys.delete(mappedKey);
    keys.delete(e.key);
  }

  const _fwd = new THREE.Vector3();

  function update() {
    if (!controls.enabled || keys.size === 0) return;
    controls.object.getWorldDirection(_fwd);
    const resolvedSpeed = typeof speed === "function" ? speed(controls) : speed;
    const { dx, dy, dz } = computeOrbitKeyboardMotion(_fwd, keys, resolvedSpeed);
    const newCamX = Math.max(minX, Math.min(maxX, controls.object.position.x + dx));
    const newCamY = Math.max(minY, Math.min(maxY, controls.object.position.y + dy));
    const newCamZ = Math.max(minZ, Math.min(maxZ, controls.object.position.z + dz));
    const actualDx = newCamX - controls.object.position.x;
    const actualDy = newCamY - controls.object.position.y;
    const actualDz = newCamZ - controls.object.position.z;
    if (Math.abs(actualDx) < 1e-6 && Math.abs(actualDy) < 1e-6 && Math.abs(actualDz) < 1e-6) return;
    controls.object.position.x = newCamX;
    controls.object.position.y = newCamY;
    controls.object.position.z = newCamZ;
    controls.target.x += actualDx;
    controls.target.y += actualDy;
    controls.target.z += actualDz;
  }

  function onContainerClick() { container?.focus(); }
  if (container) {
    container.tabIndex = 0;
    container.addEventListener("click", onContainerClick, { passive: true });
  }
  const target = container ?? window;

  function dispose() {
    target.removeEventListener("keydown", onKeyDown);
    target.removeEventListener("keyup", onKeyUp);
    if (container) container.removeEventListener("click", onContainerClick);
    keys.clear();
  }

  target.addEventListener("keydown", onKeyDown);
  target.addEventListener("keyup", onKeyUp);
  return { update, dispose };
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
  const cameraZ = Math.max(distanceForHeight, distanceForWidth) * (compactViewport ? 1.46 : 1.34);
  const targetY = center.y + (fitHeight * (compactViewport ? 0.08 : 0.12));
  const cameraY = targetY + (fitHeight * (compactViewport ? 0.02 : 0.04));
  return { camera: { x: center.x, y: cameraY, z: center.z + cameraZ }, target: { x: center.x, y: targetY, z: center.z } };
}

export function engineLabel(engine) {
  if (engine === "p5") return "P5.js";
  if (engine === "c2") return "C2.js";
  if (engine === "three") return "Three.js";
  if (engine === "svg") return "SVG";
  return engine;
}

export function getProgressiveExhibitLiveBudget(viewportWidth, staticMode = false) {
  if (staticMode) return 1;
  if (viewportWidth < 640) return 1;   // mobile
  if (viewportWidth < 1180) return 2;  // tablet
  return 3;                            // desktop
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
        while (node.firstChild) frag.appendChild(walk(node.firstChild));
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
        cloned.appendChild(walk(node.firstChild));
      }
      return cloned;
    }
    return node.cloneNode(true);
  };

  const frag = document.createDocumentFragment();
  while (temp.firstChild) {
    frag.appendChild(walk(temp.firstChild));
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

export function createMultiFrameExhibitWall(stage, frameCount, rows = 1, cols = frameCount, labels) {
  const n = Math.max(1, frameCount);
  const gridRows = Math.max(1, rows);
  const gridCols = Math.max(1, cols);
  const wallWidth = Math.max(22, gridCols * WALL_FRAME_SLOT_WIDTH + 2);
  const wallMeshHeight = Math.max(11, gridRows * WALL_FRAME_SLOT_HEIGHT + 5);
  const gridCenterY = computeExhibitGridCenterY(gridRows);

  const canvas = document.createElement("canvas");
  canvas.style.width = "100%"; canvas.style.height = "100%"; canvas.style.display = "block"; canvas.style.touchAction = "none";
  const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
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
      labelMesh.position.set(slotX, slotY - WALL_FRAME_ART_HEIGHT / 2 - WALL_LABEL_HEIGHT / 2 - WALL_LABEL_GAP, wallCenterZ + 0.01);
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
  const distance = Math.max(distanceForHeight, distanceForWidth) * 1.45;

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

// Phase 1 Entry Point: mountThreeImmersivePiece
export function mountThreeImmersivePiece(stageEl, code, htmlCode, cssCode, onError = console.error) {
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
      super({ ...input, canvas });
      state.renderer = this;
      this.setPixelRatio?.(Math.min(window.devicePixelRatio, 2));
    }
  };

  window.THREE = instrumentedThree;

  function autoFitCamera(viewportWidth = stageEl.clientWidth || window.innerWidth) {
    if (!state.scene || !state.camera) return;
    const box = new THREE.Box3();
    if (state.scene.traverse) {
      state.scene.traverse(obj => {
        if (obj.isHelper || obj.isLight || obj.isCamera) return;
        if ((obj.isMesh || obj.isLine || obj.isPoints || obj.isSprite) && obj.geometry) {
          obj.geometry.computeBoundingBox?.();
          if (obj.geometry.boundingBox) {
            box.union(obj.geometry.boundingBox.clone().applyMatrix4(obj.matrixWorld));
          }
        }
      });
    }
    if (box.isEmpty()) {
      try { box.setFromObject(state.scene); } catch (_) { return; }
    }
    if (box.isEmpty()) return;

    const center = new THREE.Vector3();
    box.getCenter(center);
    const size = new THREE.Vector3();
    box.getSize(size);
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
    let frameCount = 0;
    let rafId = 0;
    function tick() {
      frameCount += 1;
      handler(frameCount);
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
  const _orbitCamPos = new THREE.Vector3();
  const _orbitTarget = new THREE.Vector3();

  let threeAnimFromTarget = null, threeAnimToTarget = null, threeAnimFromCam = null, threeAnimToCam = null, threeAnimStart = 0;
  let threeDownX = 0, threeDownY = 0, threeDownButton = 0;
  const threeRaycaster = new THREE.Raycaster();
  const _activePointerIds = new Set();

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

  function onThreePointerDown(e) {
    _activePointerIds.add(e.pointerId);
    threeDownButton = e.button;
    threeDownX = e.clientX;
    threeDownY = e.clientY;
  }

  function onThreePointerUp(e) {
    if (!controls || !state.camera) return;
    const wasMultiTouch = _activePointerIds.size > 1;
    _activePointerIds.delete(e.pointerId);
    if (wasMultiTouch || threeDownButton !== 0 || e.button !== 0 || Math.hypot(e.clientX - threeDownX, e.clientY - threeDownY) >= 6) return;

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

  function onThreeWheel(e) {
    if (!controls || !state.camera) return;
    e.preventDefault();
    e.stopPropagation();
    const cameraPosition = state.camera.position;
    const direction = cameraPosition.clone().sub(controls.target);
    const currentDistance = direction.length();
    if (currentDistance < 1e-6) return;
    const minDistance = controls.minDistance || 0.6;
    const maxDistance = controls.maxDistance || Math.max(40, currentDistance * 4);
    const zoomScale = Math.exp(Math.max(-1, Math.min(1, e.deltaY / 600)));
    const nextDistance = Math.max(minDistance, Math.min(maxDistance, currentDistance * zoomScale));
    direction.setLength(nextDistance);
    cameraPosition.copy(controls.target).add(direction);
    controls.update();
    saveOrbitState();
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
        controls.update();

        if (threeAnimToTarget && threeAnimFromTarget) {
          const t = Math.min((performance.now() - threeAnimStart) / 350, 1);
          const eased = 1 - (1 - t) ** 3;
          controls.target.lerpVectors(threeAnimFromTarget, threeAnimToTarget, eased);
          state.camera.position.lerpVectors(threeAnimFromCam, threeAnimToCam, eased);
          controls.update();
          if (t >= 1) {
            controls.enabled = true;
            threeAnimFromTarget = threeAnimToTarget = threeAnimFromCam = threeAnimToCam = null;
            saveOrbitState();
          }
        }
        keyNav?.update();
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
        state.renderer.render(state.scene, state.camera);
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
    controls.minDistance = 0.6;
    const _initDir = new THREE.Vector3();
    state.camera.getWorldDirection(_initDir);
    const initialCamDist = state.camera.position.length();
    const targetDist = Math.max(initialCamDist * 0.8, 3);
    controls.target.copy(state.camera.position).addScaledVector(_initDir, targetDist);
    const initialTargetDist = state.camera.position.distanceTo(controls.target);
    controls.maxDistance = Math.max(40, initialTargetDist * 4);
    controls.update();

    const navLimit = getThreeNavigationLimit();
    keyNav = createKeyboardNavigation(controls, {
      container: stageEl,
      speed: (act) => Math.max(0.05, act.target.distanceTo(act.object.position) * 0.03),
      minX: -navLimit,
      maxX: navLimit,
      minZ: 0.5,
      maxZ: navLimit,
    });

    _orbitCamPos.copy(state.camera.position);
    _orbitTarget.copy(controls.target);
    canvas.addEventListener("pointerdown", onThreePointerDown);
    canvas.addEventListener("pointerup", onThreePointerUp);
    canvas.addEventListener("wheel", onThreeWheel, { passive: false, capture: true });

    controls.addEventListener("start", () => { isOrbitActive = true; });
    controls.addEventListener("end", () => { isOrbitActive = false; saveOrbitState(); });

    frameId = requestAnimationFrame(animateControls);

    const resizeObserver = new ResizeObserver(() => resize());
    resizeObserver.observe(stageEl);
    window.addEventListener("resize", resize);

    return () => {
      resizeObserver.disconnect();
      window.removeEventListener("resize", resize);
      cancelAnimationFrame(frameId);
      controls.dispose();
      canvas.removeEventListener("pointerdown", onThreePointerDown);
      canvas.removeEventListener("pointerup", onThreePointerUp);
      canvas.removeEventListener("wheel", onThreeWheel);
      stopFrameHandles.forEach(stop => stop());
      if (typeof cleanup === "function") cleanup();
      host.remove();
      stageEl.innerHTML = "";
    };
  } catch (err) {
    onError(err);
  }
}

// Phase 2 Entry Point: mountGalleryPiece
export function mountGalleryPiece(stageEl, code, htmlCode, cssCode, engine, title, sourceUrl, prompt, description, onError = console.error) {
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

  async function bootRuntime() {
    try {
      if (engine === "p5") {
        const sketchFactory = resolveSketchFactory(code);
        const mountNode = host.querySelector("#canvas-container") || host.querySelector("#sketch-container") || host;
        const preExistingCanvases = new Set(document.querySelectorAll("canvas"));
        p5Instance = new window.p5(sketchFactory, mountNode);
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

      const cleanup = sketchFactory({
        c2: window.c2,
        canvas: managedCanvas,
        startFrame,
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
    shell.renderer.render(shell.scene, shell.camera);
  }

  bootRuntime();
  fitMountedGalleryCamera(shell, stageEl);
  animate();

  const resizeObserver = new ResizeObserver(() => fitMountedGalleryCamera(shell, stageEl, undefined, false));
  resizeObserver.observe(stageEl);

  return () => {
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
    host.remove();
    stageEl.innerHTML = "";
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
export function mountExhibitWall(stageEl, items, rows, cols) {
  const wallWidth = Math.max(22, cols * WALL_FRAME_SLOT_WIDTH + 2);
  const labels = items.map(item => ({
    title: item.title || "Untitled",
    subtitle: item.kind === "piece" ? engineLabel(item.engine) : "Image"
  }));

  const shell = createMultiFrameExhibitWall(stageEl, items.length, rows, cols, labels);
  const floorNav = createFloorClickNavigation(shell.camera, shell.controls, shell.floor, stageEl, { maxX: wallWidth / 2 });
  const keyNav = createKeyboardNavigation(shell.controls, { container: stageEl, minX: -wallWidth / 2, maxX: wallWidth / 2 });

  // Progressive rendering state
  const runtimeSize = { width: 400, height: 300 }; // small size for grid items
  const activeRuntimes = new Map(); // index -> { host, canvas, stop, texture, p5Instance }

  let frameId = 0;
  
  function getLiveSlots() {
    const budget = getProgressiveExhibitLiveBudget(window.innerWidth);
    return selectProgressiveExhibitSlots(items, shell.slots.map(s => s.center), shell.controls.target, budget);
  }

  function updateProgressiveLoading() {
    const liveSlots = getLiveSlots();
    
    // Boot up newly live slots
    liveSlots.forEach(index => {
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

      try {
        if (item.engine === "p5") {
          const sketchFactory = resolveSketchFactory(item.generated_code);
          const mount = host.querySelector("#canvas-container") || host.querySelector("#sketch-container") || host;
          const preExistingCanvases = new Set(document.querySelectorAll("canvas"));
          p5Instance = new window.p5(sketchFactory, mount);

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

          const cleanup = sketchFactory({
            THREE: THREE, // Hand global THREE for inline three pieces on exhibit walls
            c2: window.c2,
            canvas: managedCanvas,
            startFrame,
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
      }

      activeRuntimes.set(index, { host, sourceCanvas, stop, texture, p5Instance, svgInterval, failed: !sourceCanvas && !stop });
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
  
  function animate() {
    frameId = requestAnimationFrame(animate);
    floorNav.update();
    keyNav.update();
    shell.controls.update();
    
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
  animate();

  const resizeObserver = new ResizeObserver(() => {
    fitMultiFrameExhibitCamera(shell, stageEl, false);
    updateProgressiveLoading();
  });
  resizeObserver.observe(stageEl);

  return () => {
    resizeObserver.disconnect();
    cancelAnimationFrame(frameId);
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
    stageEl.innerHTML = "";
  };
}
