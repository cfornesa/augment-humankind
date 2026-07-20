'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const source = fs.readFileSync(path.join(__dirname, '..', 'public', 'assets', 'js', 'sonic-controller.js'), 'utf8');
let now = 0;
let rafId = 0;
const pagehideHandlers = [];
const documentHandlers = {};
const videos = [];
const streams = [];

function makeStream() {
  const track = { readyState: 'live', muted: false, stopped: false, stop() { this.stopped = true; this.readyState = 'ended'; } };
  const stream = {
    track,
    getVideoTracks() { return [track]; },
    getTracks() { return [track]; },
  };
  streams.push(stream);
  return stream;
}

const document = {
  body: { appendChild() {} },
  documentElement: { appendChild() {} },
  addEventListener(type, handler) {
    if (!documentHandlers[type]) documentHandlers[type] = [];
    documentHandlers[type].push(handler);
  },
  dispatchEvent(event) {
    (documentHandlers[event.type] || []).forEach(handler => handler(event));
  },
  getElementById() { return null; },
  getElementsByTagName() { return []; },
  createElement(tag) {
    if (tag !== 'video') return { style: {}, setAttribute() {}, remove() {} };
    const video = {
      style: {}, muted: false, playsInline: false, paused: false, ended: false,
      readyState: 4, videoWidth: 640, videoHeight: 480, srcObject: null, removed: false,
      setAttribute() {}, async play() { this.paused = false; }, remove() { this.removed = true; },
    };
    videos.push(video);
    return video;
  },
};

const window = {
  __CREATR_TEST__: true,
  location: { search: '', origin: 'http://example.test' },
  navigator: { mediaDevices: { async getUserMedia() { return makeStream(); } } },
  document,
  performance: { now: () => now },
  requestAnimationFrame() { return ++rafId; },
  cancelAnimationFrame() {},
  setTimeout,
  clearTimeout,
  URLSearchParams,
  CustomEvent: function CustomEvent(type, options) { this.type = type; this.detail = options && options.detail; },
  addEventListener(type, handler) { if (type === 'pagehide') pagehideHandlers.push(handler); },
  console,
};
window.window = window;

vm.runInNewContext(source, window, { filename: 'sonic-controller.js' });
const controller = window.CreatrSonicController;

function hand(x, y, options = {}) {
  const points = Array.from({ length: 21 }, (_, index) => ({ x: x + ((index % 4) * 0.025), y: y - (Math.floor(index / 4) * 0.025), z: 0 }));
  points[0] = { x, y, z: 0 };
  points[9] = { x, y: y - (options.scale || 0.2), z: 0 };
  points[4] = { x: x - 0.12, y: y - 0.08, z: 0 };
  points[8] = { x: x + 0.12, y: y - 0.08, z: 0 };
  if (options.pinched) points[4] = { x: points[8].x - 0.01, y: points[8].y, z: 0 };
  return points;
}

const testCases = [];
function test(name, fn) { testCases.push({ name, fn }); }

test('srcdoc context can enable tracing after the controller script loads', () => {
  assert.strictEqual(window.__pieceSteeringTrace, undefined);
  window.CREATR_PIECE_CONTEXT = { sonicDebug: true };
  controller.traceSteering('late-context', { active: true });
  assert.strictEqual(window.__pieceSteeringTrace.entries.length, 1);
  assert.strictEqual(window.__pieceSteeringTrace.entries[0].stage, 'late-context');
  window.__pieceSteeringTrace.clear();
});

test('slow sub-deadzone wrist motion accumulates into an orbit command', () => {
  const commands = [];
  const router = controller.createClutchedGestureRouter({ engine: 'aframe', onCommand: command => commands.push(command) });
  router.update(hand(0.5, 0.5), 10);
  for (let i = 1; i <= 14; i += 1) router.update(hand(0.5 + (i * 0.002), 0.5), 10 + (i * 16.67));
  const orbits = commands.filter(command => command.type === 'orbit');
  assert(orbits.length > 0, 'expected accumulated slow motion to emit');
  assert(orbits.some(command => Math.abs(command.yaw) > 0.008), 'expected a meaningful accumulated yaw');
});

test('stationary jitter remains inside the last-emitted anchor', () => {
  const commands = [];
  const router = controller.createClutchedGestureRouter({ engine: 'aframe', onCommand: command => commands.push(command) });
  router.update(hand(0.5, 0.5), 10);
  for (let i = 1; i <= 30; i += 1) router.update(hand(0.5 + (i % 2 ? 0.003 : -0.003), 0.5), 10 + (i * 16.67));
  assert.strictEqual(commands.filter(command => command.type === 'orbit').length, 0);
});

test('one large sample emits once and hand loss resets the anchor', () => {
  const commands = [];
  const router = controller.createClutchedGestureRouter({ engine: 'aframe', onCommand: command => commands.push(command) });
  router.update(hand(0.5, 0.5), 10);
  router.update(hand(0.6, 0.5), 27);
  assert.strictEqual(commands.filter(command => command.type === 'orbit').length, 1);
  router.update(null, 44);
  router.update(hand(0.8, 0.5), 61);
  assert.strictEqual(commands.filter(command => command.type === 'orbit').length, 1, 'first post-loss sample must establish a new anchor');
});

test('pinch dwell emits clutch start/stop and resets direct-look accumulation', () => {
  const commands = [];
  const router = controller.createClutchedGestureRouter({ engine: 'aframe', pinchDwellMs: 80, onCommand: command => commands.push(command) });
  router.update(hand(0.5, 0.5), 10);
  router.update(hand(0.51, 0.5, { pinched: true }), 30);
  router.update(hand(0.52, 0.5, { pinched: true }), 120);
  router.update(hand(0.52, 0.5), 140);
  assert(commands.some(command => command.type === 'start'));
  assert(commands.some(command => command.type === 'stop' && command.reason === 'release'));
  const before = commands.filter(command => command.type === 'orbit').length;
  router.update(hand(0.522, 0.5), 157);
  assert.strictEqual(commands.filter(command => command.type === 'orbit').length, before);
});

test('muted stream replacement preserves leases and video identity', async () => {
  const api = controller.__test;
  const firstVideo = await api.acquireSharedCamera();
  await api.acquireSharedCamera();
  const before = api.cameraState();
  before.stream.track.muted = true;
  const recoveredVideo = await api.acquireSharedCamera();
  const after = api.cameraState();
  assert.strictEqual(recoveredVideo, firstVideo);
  assert.strictEqual(after.video, firstVideo);
  assert.strictEqual(after.refs, 3);
  assert.notStrictEqual(after.stream, before.stream);
  api.releaseSharedCamera();
  assert.strictEqual(api.cameraState().refs, 2);
});

test('controller disposal never closes the document-shared landmarker', async () => {
  const api = controller.__test;
  let closes = 0;
  const landmarker = { detectForVideo() { return null; }, close() { closes += 1; } };
  api.useLandmarker(landmarker);
  const params = { scale: 'major', instrument: 'synth', extras: { voices: { hand_tracking: true } } };
  const first = controller.create(params, { allowHandControl: true });
  const second = controller.create(params, { allowHandControl: true });
  assert.strictEqual(await first.enableHandControl(), true);
  assert.strictEqual(await second.enableHandControl(), true);
  first.dispose();
  second.dispose();
  assert.strictEqual(closes, 0);
  api.disposeSharedLandmarker();
  assert.strictEqual(closes, 1);
});

test('cached hand-model preparation still announces ready', async () => {
  const states = [];
  const landmarker = { detectForVideo() { return null; }, close() {} };
  document.addEventListener('creatr-sonic-capability-state', event => states.push(event.detail));
  controller.__test.useLandmarker(landmarker);
  assert.strictEqual(await controller.preloadHandTracker(), true);
  assert(states.some(detail => detail.capability === 'hand_control_model' && detail.state === 'ready'));
});

(async function run() {
  for (const testCase of testCases) {
    try {
      await testCase.fn();
      console.log(`  ✓ ${testCase.name}`);
    } catch (error) {
      console.error(`  ✗ ${testCase.name}\n${error.stack || error}`);
      process.exitCode = 1;
    }
  }
})();
