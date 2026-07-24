function disableAFrameWASD() {
  if (window.AFRAME && window.AFRAME.components['wasd-controls']) {
    const proto = window.AFRAME.components['wasd-controls'].Component.prototype;
    const origKeyDown = proto.onKeyDown;
    const origKeyUp = proto.onKeyUp;
    proto.onKeyDown = function (e) {
      if (e.code === 'KeyW' || e.code === 'KeyA' || e.code === 'KeyS' || e.code === 'KeyD') return;
      origKeyDown.call(this, e);
    };
    proto.onKeyUp = function (e) {
      if (e.code === 'KeyW' || e.code === 'KeyA' || e.code === 'KeyS' || e.code === 'KeyD') return;
      origKeyUp.call(this, e);
    };
  }
}
disableAFrameWASD();
window.addEventListener('DOMContentLoaded', disableAFrameWASD);

// TEMPORARY DIAGNOSTIC — forwards timing events to the parent page's
// console (visible even when the iframe's own console context isn't
// selected) so a live device reproduction can show the real sequence of
// canvas-ready signals vs. actual rendered frames. Remove once the root
// cause of blank/premature thumbnail captures is confirmed and fixed.
function diag(label, data) {
  if (window.CREATR_PIECE_DIAGNOSTICS !== true) return;
  try { console.log('[DIAG]', label, data || ''); } catch (_) {}
  try { window.parent.postMessage({ type: 'creatr-diag', label, data: data || null, t: performance.now() }, '*'); } catch (_) {}
  // DOM-based fallback so a parent that never receives the postMessage
  // above (relay broken, sandboxing quirk, etc.) can still read the last
  // diagnostic stage reached by polling the iframe's own DOM directly —
  // no cross-window messaging involved at all.
  try { document.documentElement.dataset.creatrDiagLast = label; } catch (_) {}
}
function formatPieceError(error) {
  if (!error || typeof error !== 'object') return String(error);
  const name = error.name || 'Error';
  const headline = error.message ? name + ': ' + error.message : String(error);
  let stack = typeof error.stack === 'string' ? error.stack : '';
  // V8 stacks repeat "Name: message" as the first line — keep one copy only.
  if (stack.startsWith(headline)) stack = stack.slice(headline.length);
  if (!stack.trim()) return headline;
  // Label each frame so authored-piece frames stand out from runtime ones
  // (Safari/Firefox frames often have empty function names, rendering as "@…").
  const frames = stack.trim().split('\n').map((line) => {
    const t = line.trim();
    if (!t) return null;
    return (/piece-runtime\.js/.test(t) ? '[runtime] ' : '[sketch] ') + t;
  }).filter(Boolean).join('\n');
  return headline + '\n' + frames;
}
function showPieceError(error) {
  const el = document.getElementById('piece-error');
  if (!el) return;
  el.textContent = formatPieceError(error);
  el.style.display = 'block';
  try { window.parent.postMessage({ type: 'sketch-status', valid: false, error: el.textContent }, '*'); } catch (_) {}
}
function isNonImpactingRuntimeIssue(error, source) {
  const message = typeof error?.message === 'string' ? error.message : String(error || '');
  const origin = String(source || error?.fileName || '');
  if (/^(?:chrome|moz|safari)-extension:/i.test(origin)) return true;
  return /ResizeObserver loop (?:limit exceeded|completed with undelivered notifications)/i.test(message)
    || /Could not establish connection\. Receiving end does not exist/i.test(message)
    || /A listener indicated an asynchronous response.*message channel closed/i.test(message);
}
window.addEventListener('error', (event) => {
  const error = event.error || event.message;
  if (isNonImpactingRuntimeIssue(error, event.filename)) return;
  showPieceError(error);
});
// Tone.js's PluckSynth builds a LowpassCombFilter -> FeedbackCombFilter
// internally, backed by a real AudioWorkletProcessor (Karplus-Strong
// plucked-string synthesis needs a feedback delay line) — the only one of
// the 7 selectable instruments that touches a worklet at all. That
// registration is fire-and-forget deep inside PluckSynth's own constructor,
// so it can't be caught by this file's own try/catch around synth creation.
// Under file:// (e.g. a downloaded piece opened by double-clicking
// index.html instead of being served), the browser refuses to load the
// worklet's blob-URL module, producing an unhandled `AbortError: Unable to
// load a worklet's module.` that has nothing to do with a real piece
// failure — the piece still plays (only the comb filter's pitched
// resonance is missing). Filtered out here instead of surfacing the error
// banner for something that isn't one.
window.addEventListener('unhandledrejection', (event) => {
  const reason = event.reason;
  const message = typeof reason?.message === 'string' ? reason.message : String(reason || '');
  if (reason?.name === 'AbortError' && /worklet/i.test(message)) {
    event.preventDefault();
    return;
  }
  if (isNonImpactingRuntimeIssue(reason)) { event.preventDefault(); return; }
  showPieceError(reason || 'Unhandled promise rejection');
});
const pieceContext = window.CREATR_PIECE_CONTEXT || {};
const pieceDisableMotion = pieceContext.disableMotion === true;

// Movement sonification (Tone.js, via the shared CreatrSonicController
// engine) — per-piece, no master switch. The parent page's sound toggle
// button toggles audio (and, now, volume/input-mode/notes) via postMessage;
// this iframe owns audio (sandboxed with the render), reads camera/mover
// motion directly. Any engine can carry a `sonic` block now: three/aframe
// sonify camera motion, c2_interactive sonifies pointer motion, everything
// else has no motion signal here and gets the idle random-note pattern only.
const PIECE_SONIC = (pieceContext && pieceContext.sonic && typeof pieceContext.sonic === 'object') ? pieceContext.sonic : null;
const PIECE_C2_INTERACTIVE = pieceContext.c2Interactive === true;
// Per-piece camera overlay permission, resolved server-side by
// piece_sound_capability_contract() (Metadata-tab camera_overlay column,
// with a legacy fallback to the hand-tracking voice) — independent of
// PIECE_SONIC, so a piece with no sound at all can still offer the camera.
const PIECE_CAMERA_OVERLAY = pieceContext.cameraOverlay === true;
// Hand control (camera steering + device-tilt fallback), resolved server-side
// by the capability contract: available on steerable engines when the camera
// permission or the hand-tracking voice unlocks it — independent of sound.
const PIECE_HAND_CONTROL = pieceContext.handControl === true;
const PIECE_SONIC_DEBUG = pieceContext.sonicDebug === true;
function pieceSteeringTrace(stage, detail) {
  if (!PIECE_SONIC_DEBUG) return;
  const controllerTrace = window.CreatrSonicController?.traceSteering;
  if (typeof controllerTrace === 'function') {
    controllerTrace(stage, detail);
    return;
  }
  try {
    const trace = window.__pieceSteeringTrace || (window.__pieceSteeringTrace = {
      entries: [],
      clear() { this.entries.length = 0; },
      snapshot() { return this.entries.slice(); },
    });
    trace.entries.push({ t: Math.round(performance.now() * 10) / 10, stage, detail: detail == null ? null : JSON.parse(JSON.stringify(detail)) });
    if (trace.entries.length > 200) trace.entries.splice(0, trace.entries.length - 200);
  } catch (_) {}
}
// Where the camera feed renders when enabled — 'background' (behind the
// piece) or 'overlay' (blendable feed above it). Resolved server-side by the
// capability contract (per-piece camera_placement column, engine default
// otherwise: background for three/aframe, overlay for the 2D engines).
const PIECE_CAMERA_PLACEMENT = pieceContext.cameraPlacement === 'background' ? 'background' : 'overlay';

let _pieceSonicControllerPromise = null;
function pieceLoadSonicControllerOnce() {
  if (window.CreatrSonicController) return Promise.resolve(window.CreatrSonicController);
  if (_pieceSonicControllerPromise) return _pieceSonicControllerPromise;
  _pieceSonicControllerPromise = new Promise((resolve, reject) => {
    const src = (pieceContext && typeof pieceContext.sonicControllerSource === 'string' && pieceContext.sonicControllerSource !== '')
      ? pieceContext.sonicControllerSource
      : '/assets/js/sonic-controller.js';
    const s = document.createElement('script');
    s.src = src;
    s.onload = () => (window.CreatrSonicController ? resolve(window.CreatrSonicController) : reject(new Error('sonic-controller.js loaded but window.CreatrSonicController missing')));
    s.onerror = () => reject(new Error('sonic-controller.js failed to load'));
    document.head.appendChild(s);
  });
  return _pieceSonicControllerPromise;
}

// createPieceRuntimeAudioController(sonicParams, getMover) — muted by
// default; listens for `window` message events from the parent page's sound
// toggle button. On unmute, lazy-loads sonic-controller.js (and, inside it,
// Tone.js) — the postMessage handler runs in this iframe's own
// user-gesture-initiated task chain, so an actual user click on the parent
// toggle is the autoplay gesture — then delegates all synth/scale/motion/
// idle/volume/keyboard logic to the shared engine, driven from
// `getMover().position` deltas via the engine's own internal rAF loop.
// getMover is optional — pass null/undefined for engines with no motion
// signal on this view (p5, plain c2, svg); the idle pattern is then the only
// thing that ever plays, giving the piece a random-note ambience on unmute.
// Mirrors sonic-controller.js's SONIC_INSTRUMENTS keys — duplicated here
// (rather than force-loading the whole engine before the visitor has ever
// unmuted) since the standalone panel below needs the instrument list before
// sonic-controller.js is lazy-loaded, matching how PIANO_KEY_MAP is already
// duplicated the same way in piece-fullscreen.js.
const PIECE_SONIC_INSTRUMENTS = {
  synth: 'Synth', amsynth: 'AM Synth', fmsynth: 'FM Synth',
  membranesynth: 'Membrane', metalsynth: 'Metal', plucksynth: 'Plucked String',
  duosynth: 'Duo Synth',
};

// Visitor-chosen per-voice instrument overrides — session-local only, never
// touches sonicParams/the DB. One localStorage entry per piece.
function pieceVoiceInstrumentStorageKey(pieceId) {
  return 'creatr-sonic-voice-instruments:' + pieceId;
}
function readPieceVoiceInstrumentOverrides(pieceId) {
  if (pieceId == null) return {};
  try {
    const raw = window.localStorage.getItem(pieceVoiceInstrumentStorageKey(pieceId));
    const parsed = raw ? JSON.parse(raw) : null;
    return (parsed && typeof parsed === 'object') ? parsed : {};
  } catch (_e) {
    return {};
  }
}
function writePieceVoiceInstrumentOverride(pieceId, voiceName, instrumentKey) {
  if (pieceId == null) return;
  try {
    const overrides = readPieceVoiceInstrumentOverrides(pieceId);
    overrides[voiceName] = instrumentKey;
    window.localStorage.setItem(pieceVoiceInstrumentStorageKey(pieceId), JSON.stringify(overrides));
  } catch (_e) {}
}

function createPieceRuntimeAudioController(sonicParams, getMover) {
  // sonicParams may be null for a camera-only piece (camera overlay and/or
  // hand control enabled, no sound design): the controller still mounts to
  // run the postMessage bridge and camera/steering toggles, it just never
  // creates an AUDIBLE engine — see createEngineWith.
  const soundActive = !!(sonicParams && typeof sonicParams === 'object');
  if (!soundActive && !PIECE_CAMERA_OVERLAY && !PIECE_HAND_CONTROL) return null;
  if (!soundActive) sonicParams = null;
  if (getMover != null && typeof getMover !== 'function') return null;

  const pieceId = (pieceContext && pieceContext.pieceId != null) ? pieceContext.pieceId : null;
  let engine = null, disposed = false;
  let tiltController = null;

  // Tell the host page which voices are visible for this piece (the parent
  // has no direct access to sonicParams — it only relays postMessages) so
  // its popover can hide the keyboard/hand-tracking controls accordingly.
  (function notifyParentVoices() {
    const voices = (soundActive && sonicParams.extras && sonicParams.extras.voices) || {};
    const ambientSample = (soundActive && sonicParams.extras && sonicParams.extras.synth && sonicParams.extras.synth.ambient_sample) || {};
    try {
      if (window.parent && window.parent !== window) {
        window.parent.postMessage({
          type: 'creatr-sound-voices',
          voices: {
            ambient: soundActive && voices.ambient !== false,
            movement: soundActive && voices.movement !== false,
            melodic: soundActive && voices.melodic !== false,
            hand_tracking: !!(soundActive && voices.hand_tracking),
          },
          ambientIsSample: !!(ambientSample.enabled && ambientSample.media_id),
          micSupported: !!(soundActive && navigator.mediaDevices && navigator.mediaDevices.getUserMedia),
          // Engine-dependent camera capabilities (hooks are registered by
          // the active bootstrap before this controller is created). The
          // camera overlay is its own permission (PIECE_CAMERA_OVERLAY),
          // no longer implied by the hand-tracking voice.
          // Hand control rides the camera permission or hand-tracking voice
          // (PIECE_HAND_CONTROL reflects the server-side contract), not the
          // sound design — a sound-less piece can still steer.
          handControlSupported: !!(PIECE_HAND_CONTROL && window.__pieceHandHooks && window.__pieceHandHooks.handPoint),
          cameraBgSupported: !!(PIECE_CAMERA_OVERLAY && window.__pieceHandHooks && window.__pieceHandHooks.setBackgroundVideo),
          cameraOpacitySupported: !!(PIECE_CAMERA_OVERLAY && window.__pieceHandHooks && typeof window.__pieceHandHooks.setBackgroundOpacity === 'function'),
        }, '*');
      }
    } catch (_) {}
  })();

  // Kick the (audio-free, ~small) controller script load immediately so that
  // by the time the visitor taps a camera/mic toggle the engine can be
  // created SYNCHRONOUSLY inside the gesture task — WebKit's transient
  // activation does not survive awaits on script loads.
  pieceLoadSonicControllerOnce().catch(() => {});

  function createEngineWith(CSC) {
    // Sound-less pieces with hand control get a SILENT engine ({} params):
    // enableHandControl() never touches audio, and ensureEnabled() below
    // refuses to enable() it, so it can only ever run the camera/landmark
    // pipeline. Camera-only-without-hand-control pieces need no engine at
    // all (the overlay uses the static cameraFeed).
    if (!soundActive && !PIECE_HAND_CONTROL) return null;
    return CSC.create(soundActive ? sonicParams : {}, {
      getMover: getMover || undefined,
      allowHandControl: PIECE_HAND_CONTROL,
      toneSrc: (pieceContext && typeof pieceContext.toneSource === 'string' && pieceContext.toneSource !== '')
        ? pieceContext.toneSource
        : undefined,
    });
  }

  // Synchronous engine creation for gesture-critical paths; returns null if
  // the controller script hasn't finished loading yet (callers then fall
  // back to the async path and accept the activation risk).
  function ensureEngineSync() {
    if (disposed || engine) return engine;
    const CSC = window.CreatrSonicController;
    if (!CSC) return null;
    engine = createEngineWith(CSC);
    return engine;
  }

  async function ensureEnabled() {
    // A sound-less piece must never play audio, even if a forged
    // creatr-sound-toggle message arrives — its engine (if any) exists only
    // for hand control.
    if (disposed || !soundActive) return false;
    if (engine && engine.isEnabled()) return true;
    if (!engine) {
      const CSC = await pieceLoadSonicControllerOnce();
      if (disposed) return false;
      engine = createEngineWith(CSC);
      if (!engine) return false;
    }
    const ok = await engine.enable();
    if (ok) {
      const overrides = readPieceVoiceInstrumentOverrides(pieceId);
      Object.keys(overrides).forEach((voiceName) => {
        engine.setVoiceInstrument(voiceName, overrides[voiceName]);
      });
    }
    return ok;
  }

  function handleMessage(event) {
    if (!event || typeof event.data !== 'object') return;
    const data = event.data;
    // Source check: message must be from this window's parent (the host page
    // hosting the iframe) or this window itself (standalone export).
    if (window.parent && event.source && event.source !== window.parent && event.source !== window) return;

    if (data.type === 'creatr-sound-toggle') {
      const on = !!data.enabled;
      if (!on) {
        engine?.disable();
        notifyParentToggleState(false);
        return;
      }
      if (toggleBtn) toggleBtn.disabled = true;
      ensureEnabled().then((ok) => {
        notifyParentToggleState(ok, ok ? null : 'unavailable');
      }).catch(() => {
        notifyParentToggleState(false, 'unavailable');
      }).finally(() => {
        if (toggleBtn) toggleBtn.disabled = false;
      });
      return;
    }
    if (data.type === 'creatr-sound-volume') {
      engine?.setVolume(Number(data.percent));
      return;
    }
    if (data.type === 'creatr-sound-input-mode') {
      engine?.setInputMode(data.mode);
      return;
    }
    if (data.type === 'creatr-sound-note') {
      // Chromatic (piano-key) note, by semitone offset from the current
      // octave's root — see triggerChromaticNote() in sonic-controller.js.
      ensureEnabled().then((ok) => {
        if (ok) engine?.triggerChromaticNote(Number(data.semitone) || 0);
      });
      return;
    }
    if (data.type === 'creatr-sound-octave') {
      engine?.setOctave(Number(data.octave));
      return;
    }
    if (data.type === 'creatr-sound-voice-instrument') {
      if (engine && engine.setVoiceInstrument(data.voice, data.instrument)) {
        writePieceVoiceInstrumentOverride(pieceId, data.voice, data.instrument);
      }
      return;
    }
    if (data.type === 'creatr-sound-hand-toggle') {
      handleHandToggle(!!data.enabled);
      return;
    }
    if (data.type === 'creatr-sound-hand-control-toggle') {
      handleHandControlToggle(!!data.enabled);
      return;
    }
    if (data.type === 'creatr-hand-control-prepare') {
      prepareHandControl();
      return;
    }
    if (data.type === 'creatr-reset-view') {
      window.__pieceHandHooks?.resetView?.();
      return;
    }
    if (data.type === 'creatr-sound-camera-bg-toggle') {
      handleCameraBackgroundToggle(!!data.enabled);
      return;
    }
    if (data.type === 'creatr-sound-camera-bg-opacity') {
      window.__pieceHandHooks?.setBackgroundOpacity?.(Number(data.value));
      return;
    }
    if (data.type === 'creatr-sound-mic-toggle') {
      handleMicToggle(!!data.enabled);
      return;
    }
    if (data.type === 'creatr-sound-mic-fx') {
      engine?.setMicEffect(data.effect, data.enabled);
      return;
    }
  }

  // Gesture-critical toggles are shared between the postMessage relay and
  // the same-origin direct bridge (window.__creatrSonicGesture, called
  // synchronously from the parent page's click handler so WebKit's transient
  // activation reaches getUserMedia — postMessage alone does not carry it).
  function handleHandToggle(on) {
    if (!on) {
      engine?.disableHandTracking();
      engine?.setInputMode('motion');
      notifyParentHandState(false);
      return;
    }
    const eng = ensureEngineSync();
    // Camera first, inside the gesture task (enableHandTracking's first
    // await is getUserMedia); audio enablement follows.
    const handPromise = eng ? eng.enableHandTracking() : null;
    ensureEnabled().then(async (ok) => {
      const handOk = ok && engine
        ? await (handPromise !== null ? handPromise : engine.enableHandTracking())
        : false;
      if (handOk) engine.setInputMode('hand');
      notifyParentHandState(handOk);
    }).catch(() => notifyParentHandState(false));
  }

  function handleMicToggle(on) {
    if (!on) {
      engine?.disableMic();
      notifyParentMicState(false);
      return;
    }
    ensureEngineSync();
    ensureEnabled().then(async (ok) => {
      const micOk = ok && engine ? await engine.enableMic() : false;
      notifyParentMicState(micOk);
    }).catch(() => notifyParentMicState(false));
  }

  let handControlActivationEpoch = 0;
  function prepareHandControl() {
    if (!PIECE_HAND_CONTROL) return Promise.resolve(false);
    const run = (CSC) => CSC?.preloadHandTracker?.() || false;
    try {
      return Promise.resolve(window.CreatrSonicController ? run(window.CreatrSonicController) : pieceLoadSonicControllerOnce().then(run))
        .catch(() => false);
    } catch (_) {
      return Promise.resolve(false);
    }
  }
  function handleHandControlToggle(on) {
    const activationEpoch = ++handControlActivationEpoch;
    pieceSteeringTrace('toggle', { enabled: !!on, epoch: activationEpoch });
    if (on && !PIECE_HAND_CONTROL) {
      pieceSteeringTrace('toggle-rejected', { reason: 'capability-disabled' });
      // Same defense-in-depth as the camera toggle below: a forged
      // postMessage must not open the camera on a piece that doesn't allow
      // steering.
      notifyParentHandControlState(false);
      return;
    }
    if (!on) {
      handControlBinding.detach();
      engine?.disableHandControl();
      Promise.resolve(window.__pieceHandHooks?.setHandSteering?.(false)).catch(() => {});
      notifyParentHandControlState(false);
      return;
    }
    const eng = ensureEngineSync();
    pieceSteeringTrace('engine-ready', { available: !!eng });
    Promise.resolve(eng ? eng.enableHandControl() : false).then(async (controlOk) => {
      pieceSteeringTrace('engine-enabled', { enabled: !!controlOk });
      if (activationEpoch !== handControlActivationEpoch) {
        pieceSteeringTrace('toggle-abort', { reason: 'epoch-changed' });
        eng?.disableHandControl();
        return;
      }
      if (controlOk) {
        const steeringHook = window.__pieceHandHooks?.setHandSteering;
        pieceSteeringTrace('ownership-hook', { exists: typeof steeringHook === 'function', engine: window.__pieceHandHooks?.engine || null });
        // A steerable surface must explicitly claim/release manual-control
        // ownership. Treating a missing hook as success hid regular/export
        // parity gaps and left native controls fighting hand input.
        const steeringReady = typeof steeringHook === 'function'
          ? await Promise.resolve(steeringHook.call(window.__pieceHandHooks, true))
          : false;
        pieceSteeringTrace('ownership-result', { ready: steeringReady !== false });
        if (activationEpoch !== handControlActivationEpoch || steeringReady === false) {
          pieceSteeringTrace('toggle-abort', { reason: 'ownership-failed' });
          eng?.disableHandControl();
          await Promise.resolve(window.__pieceHandHooks?.setHandSteering?.(false));
          notifyParentHandControlState(false);
          return;
        }
        pieceSteeringTrace('binding-attach', null);
        handControlBinding.attach(engine);
      }
      notifyParentHandControlState(controlOk);
    }).catch((err) => {
      pieceSteeringTrace('toggle-error', { message: err?.message || String(err) });
      notifyParentHandControlState(false);
    });
  }

  async function handleTiltToggle(on) {
    if (!on) {
      tiltController?.disable();
      // Release steering ownership so manual controls resume at the tilted
      // pose — same handoff contract as the hand-control path.
      window.__pieceHandHooks?.setHandSteering?.(false);
      notifyParentHandControlState(false, 'device_tilt');
      return;
    }
    const CSC = window.CreatrSonicController;
    const hooks = window.__pieceHandHooks;
    if (!CSC?.createDeviceTiltController || !hooks?.handPoint) {
      notifyParentCapabilityState({ capability: 'hand_control', state: 'unavailable', reason: 'Device motion unavailable' });
      return;
    }
    if (!tiltController) {
      tiltController = CSC.createDeviceTiltController((nx, ny) => hooks.handPoint(nx, ny));
    }
    // handPoint/handCommand require steering ownership (handSteeringExclusive)
    // on the 3D engines; without this the tilt frames are silently dropped.
    hooks.setHandSteering?.(true);
    const ok = await tiltController.enable();
    if (!ok) hooks.setHandSteering?.(false);
    notifyParentHandControlState(ok, ok ? 'device_tilt' : null);
    if (!ok) notifyParentCapabilityState({ capability: 'hand_control', state: 'unavailable', reason: 'Device motion permission denied' });
  }

  // The overlay uses the module-level shared camera feed directly (same
  // ref-counted stream the hand pipeline uses) rather than the audio
  // engine, so it works on camera-only pieces where no engine ever exists.
  // getUserMedia must be the first await in the invoking gesture task — the
  // controller script is prefetched above so CSC is normally already here.
  let cameraFeedHeld = false;
  function handleCameraBackgroundToggle(on) {
    if (on && !PIECE_CAMERA_OVERLAY) {
      // Defense in depth: the host page never shows the toggle unless the
      // capability handshake advertised it, but a forged postMessage must
      // not be able to open the camera on a piece that doesn't allow it.
      notifyParentCameraBgState(false);
      return;
    }
    if (!on) {
      cameraBackgroundBinding.detach();
      if (cameraFeedHeld) {
        cameraFeedHeld = false;
        try { window.CreatrSonicController?.cameraFeed.release(); } catch (_) {}
      }
      notifyParentCameraBgState(false);
      return;
    }
    const CSC = window.CreatrSonicController;
    // Pre-warm the MediaPipe WASM compilation while the user has the camera
    // on but hasn't clicked Steer yet. The landmarker promise is cached at
    // module scope, so when Steer is clicked the WASM is already compiled.
    if (PIECE_HAND_CONTROL) {
      try { CSC?.preloadHandTracker?.(); } catch (_) {}
    }
    const acquire = CSC
      ? CSC.cameraFeed.acquire()
      : pieceLoadSonicControllerOnce().then((loaded) => loaded.cameraFeed.acquire());
    Promise.resolve(acquire)
      .then((video) => {
        cameraFeedHeld = true;
        const shown = cameraBackgroundBinding.attach(video);
        if (!shown) {
          cameraFeedHeld = false;
          try { window.CreatrSonicController?.cameraFeed.release(); } catch (_) {}
        }
        notifyParentCameraBgState(shown);
      })
      .catch(() => notifyParentCameraBgState(false));
  }

  function notifyParentHandState(on) {
    try {
      if (window.parent && window.parent !== window) {
        window.parent.postMessage({ type: 'creatr-sound-hand-state', enabled: !!on }, '*');
      }
    } catch (_) {}
  }

  function notifyParentMicState(on) {
    try {
      if (window.parent && window.parent !== window) {
        window.parent.postMessage({ type: 'creatr-sound-mic-state', enabled: !!on }, '*');
      }
    } catch (_) {}
  }

  function notifyParentToggleState(on, reason) {
    try {
      if (window.parent && window.parent !== window) {
        window.parent.postMessage({ type: 'creatr-sound-state', enabled: !!on, reason: reason || null }, '*');
      }
    } catch (_) {}
  }

  function notifyParentHandControlState(on, mode) {
    try {
      if (window.parent && window.parent !== window) {
        window.parent.postMessage({ type: 'creatr-sound-hand-control-state', enabled: !!on, mode: mode || 'hand' }, '*');
      }
    } catch (_) {}
  }

  function notifyParentCapabilityState(detail) {
    try {
      if (window.parent && window.parent !== window) {
        window.parent.postMessage({ type: 'creatr-sonic-capability-state', detail: detail || {} }, '*');
      }
    } catch (_) {}
  }

  const onCapabilityState = (event) => notifyParentCapabilityState(event.detail);
  document.addEventListener('creatr-sonic-capability-state', onCapabilityState);

  function notifyParentCameraBgState(on) {
    try {
      if (window.parent && window.parent !== window) {
        window.parent.postMessage({
          type: 'creatr-sound-camera-bg-state',
          enabled: !!on,
          opacitySupported: typeof window.__pieceHandHooks?.setBackgroundOpacity === 'function',
          // Lets the host initialize its slider to the hook's real default
          // (0.35 for the 2D DOM overlay, 1.0 for the 3D blended quad)
          // instead of assuming a hardcoded value.
          opacity: typeof window.__pieceHandHooks?.getBackgroundOpacity === 'function'
            ? window.__pieceHandHooks.getBackgroundOpacity()
            : null,
        }, '*');
      }
    } catch (_) {}
  }

  // Hand-control: feeds each landmark frame's wrist position (mirrored X so
  // moving the hand right steers right — the camera image is a mirror) into
  // the engine-specific interaction hook that the active bootstrap
  // registered on window.__pieceHandHooks (orbit for three, camera yaw/pitch
  // for aframe, synthetic pointer for interactive c2).
  const handControlBinding = {
    active: false,
    pinched: false,
    router: null,
    routerHooks: null,
    routerEngine: null,
    attach(eng) {
      if (this.active || !eng) return;
      this.active = true;
      this.pinched = false;
      eng.onHandFrame((hand) => {
        if (!this.active) return;
        const hooks = window.__pieceHandHooks;
        if (!hooks) return;
        if (typeof hooks.handCommand === 'function' && window.CreatrSonicController?.createClutchedGestureRouter) {
          if (!this.router || this.routerHooks !== hooks || this.routerEngine !== hooks.engine) {
            this.router?.reset?.('hook-changed');
            this.routerHooks = hooks;
            this.routerEngine = hooks.engine;
            pieceSteeringTrace('router-bind', { engine: hooks.engine || null, hookKeys: Object.keys(hooks) });
            this.router = window.CreatrSonicController.createClutchedGestureRouter({
              engine: hooks.engine,
              onCommand: (command) => {
                const activeHooks = window.__pieceHandHooks;
                pieceSteeringTrace('hook-command', { engine: activeHooks?.engine || null, command });
                activeHooks?.handCommand?.(command);
              },
              onMode: (mode) => {
                try { window.parent?.postMessage({ type: 'creatr-hand-gesture-mode', mode }, '*'); } catch (_) {}
              },
            });
          }
          this.router.update(hand);
          return;
        }
        if (!hand) {
          // Hand lost: release any held pinch so drag-driven sketches don't
          // keep drawing a phantom stroke.
          if (this.pinched) {
            this.pinched = false;
            try { hooks.handPress?.(false); } catch (_) {}
          }
          return;
        }
        if (typeof hooks.handPoint !== 'function') return;
        const wrist = hand[0];
        if (!wrist) return;
        try { hooks.handPoint(1 - wrist.x, wrist.y); } catch (_) {}
        // Pinch (thumb tip ↔ index tip) acts as the pointer button for
        // engines whose hook registers handPress — interactive c2 sketches
        // typically only respond to move while a pointer is DOWN (drag to
        // draw), so move-only steering never reaches them. Hysteresis keeps
        // the press from chattering at the threshold.
        if (typeof hooks.handPress === 'function') {
          const thumb = hand[4], index = hand[8];
          if (thumb && index) {
            const gap = Math.hypot(thumb.x - index.x, thumb.y - index.y, (thumb.z || 0) - (index.z || 0));
            const next = this.pinched ? gap < 0.09 : gap < 0.055;
            if (next !== this.pinched) {
              this.pinched = next;
              try { hooks.handPress(next); } catch (_) {}
            }
          }
        }
      });
    },
    detach() {
      if (!this.active) return;
      this.active = false;
      if (this.pinched) {
        this.pinched = false;
        try { window.__pieceHandHooks?.handPress?.(false); } catch (_) {}
      }
      this.router?.reset?.('disabled');
      this.router = null;
      this.routerHooks = null;
      this.routerEngine = null;
      engine?.onHandFrame(null);
    },
  };

  // Camera background: hands the shared hidden <video> to the bootstrap's
  // setBackgroundVideo hook (three/aframe VideoTexture); attach() returns
  // false where the active engine registered no such hook.
  const cameraBackgroundBinding = {
    active: false,
    attach(video) {
      const hooks = window.__pieceHandHooks;
      if (!video || !hooks || typeof hooks.setBackgroundVideo !== 'function') return false;
      let shown = false;
      try { shown = !!hooks.setBackgroundVideo(video); } catch (_) {}
      this.active = shown;
      return shown;
    },
    detach() {
      if (!this.active) return;
      this.active = false;
      const hooks = window.__pieceHandHooks;
      try { hooks && hooks.clearBackgroundVideo && hooks.clearBackgroundVideo(); } catch (_) {}
    },
  };

  // Inline sound toggle button for the standalone (file://) exported HTML,
  // where there is no parent hosting the iframe — root document owns UI.
  // Confirmed dead in practice: piece-runtime.js is only ever loaded inside
  // an iframe in this codebase, so `window.parent === window` never holds
  // and this whole branch never executes. Left as-is rather than deleted;
  // NOT extended with mic UI — the real downloaded-export mic UI lives in
  // piece_export_sonic_script()'s mountUi() in piece-render.php, which is
  // what /pieces/{id}/download actually ships.
  let toggleBtn = document.querySelector('[data-creatr-piece-sound-toggle]');
  let standaloneKeyboardToggle = null, standaloneKeysWrap = null, standaloneOctaveDisplay = null;
  let detachStandalonePianoKeys = null;
  if (!toggleBtn && window.parent === window) {
    const pianoStyle = document.createElement('style');
    pianoStyle.textContent = `
      .runtime-key-white {
        flex: 1 1 0;
        height: 100%;
        border: 1px solid rgba(0, 0, 0, 0.35);
        border-radius: 0 0 0.3rem 0.3rem;
        background: #f4f1e8;
        cursor: pointer;
        touch-action: none;
      }
      .runtime-key-white:hover {
        background: #d8d4c4;
      }
      .runtime-key-white:active, .runtime-key-white.is-pressed {
        background: #bbb7a8;
      }
      .runtime-key-black {
        position: absolute;
        top: 0;
        width: 6%;
        height: 62%;
        transform: translateX(-50%);
        border: 1px solid rgba(0, 0, 0, 0.6);
        border-radius: 0 0 0.25rem 0.25rem;
        background: #17161a;
        cursor: pointer;
        z-index: 2;
        touch-action: none;
      }
      .runtime-key-black:hover {
        background: #3a3942;
      }
      .runtime-key-black:active, .runtime-key-black.is-pressed {
        background: #5c5a69;
      }
    `;
    document.head.appendChild(pianoStyle);

    const wrap = document.createElement('div');
    Object.assign(wrap.style, {
      position: 'fixed', top: 'calc(0.75rem + env(safe-area-inset-top))',
      right: 'calc(0.75rem + env(safe-area-inset-right))',
      zIndex: '200', display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: '0.5rem',
    });

    const row = document.createElement('div');
    row.style.cssText = 'display:flex;align-items:center;gap:0.3rem;';

    toggleBtn = document.createElement('button');
    toggleBtn.type = 'button';
    toggleBtn.setAttribute('data-creatr-piece-sound-toggle', '');
    toggleBtn.setAttribute('aria-pressed', 'false');
    toggleBtn.setAttribute('aria-label', 'Unmute sound');
    Object.assign(toggleBtn.style, {
      width: '2.75rem', height: '2.75rem',
      display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
      borderRadius: '0.75rem', border: '1px solid rgba(255,255,255,0.15)',
      background: 'rgba(0,0,0,0.55)', color: '#fff', cursor: 'pointer',
      boxShadow: '0 4px 12px rgba(0,0,0,0.5)', backdropFilter: 'blur(4px)',
    });
    toggleBtn.innerHTML = '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4z"/><line x1="22" y1="9" x2="16" y2="15"/><line x1="16" y1="9" x2="22" y2="15"/></svg>';
    row.appendChild(toggleBtn);

    const panelTrigger = document.createElement('button');
    panelTrigger.type = 'button';
    panelTrigger.setAttribute('aria-haspopup', 'true');
    panelTrigger.setAttribute('aria-expanded', 'false');
    panelTrigger.setAttribute('aria-label', 'Sound settings');
    Object.assign(panelTrigger.style, {
      width: '2.75rem', height: '2.75rem',
      display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
      borderRadius: '0.75rem', border: '1px solid rgba(255,255,255,0.15)',
      background: 'rgba(0,0,0,0.55)', color: '#fff', cursor: 'pointer', padding: '0',
      boxShadow: '0 4px 12px rgba(0,0,0,0.5)', backdropFilter: 'blur(4px)',
    });
    panelTrigger.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>';
    row.appendChild(panelTrigger);

    wrap.appendChild(row);

    const panel = document.createElement('div');
    Object.assign(panel.style, {
      display: 'none', flexDirection: 'column', gap: '0.6rem', width: '13rem',
      padding: '0.85rem', borderRadius: '1rem', border: '1px solid rgba(255,255,255,0.14)',
      background: 'rgba(9,14,24,0.94)', boxShadow: '0 18px 40px rgba(0,0,0,0.4)',
      backdropFilter: 'blur(8px)', color: '#fff', font: '12px/1.4 system-ui,sans-serif',
    });
    const volumeRow = document.createElement('div');
    volumeRow.style.cssText = 'display:flex;align-items:center;gap:0.5rem;';
    const volumeInput = document.createElement('input');
    volumeInput.type = 'range'; volumeInput.min = '0'; volumeInput.max = '100'; volumeInput.value = '50';
    volumeInput.style.cssText = 'width:100%;';
    volumeRow.appendChild(Object.assign(document.createElement('label'), { textContent: 'Volume' }));
    volumeRow.appendChild(volumeInput);

    const keyboardRow = document.createElement('div');
    keyboardRow.style.cssText = 'display:flex;align-items:center;justify-content:space-between;gap:0.5rem;';
    standaloneKeyboardToggle = document.createElement('button');
    standaloneKeyboardToggle.type = 'button';
    standaloneKeyboardToggle.textContent = 'Play notes';
    standaloneKeyboardToggle.setAttribute('aria-pressed', 'false');
    standaloneKeyboardToggle.style.cssText = 'border:1px solid rgba(255,255,255,0.18);border-radius:0.6rem;background:rgba(255,255,255,0.06);color:#fff;font:inherit;font-weight:600;padding:0.3rem 0.55rem;cursor:pointer;';
    keyboardRow.appendChild(Object.assign(document.createElement('span'), { textContent: 'Keyboard' }));
    keyboardRow.appendChild(standaloneKeyboardToggle);
    const standaloneVoices = (sonicParams.extras && sonicParams.extras.voices) || {};
    if (standaloneVoices.melodic === false) keyboardRow.style.display = 'none';

    // Visitor-facing per-voice instrument picker — session-local only (see
    // readPieceVoiceInstrumentOverrides()/writePieceVoiceInstrumentOverride()
    // above), mirroring the parent-page popover's picker for the live view.
    const voicePickerWrap = document.createElement('div');
    voicePickerWrap.style.cssText = 'display:flex;flex-direction:column;gap:0.45rem;';
    const voicePickerSelects = {};
    [['ambient', 'Ambient'], ['movement', 'Movement'], ['melodic', 'Melodic']].forEach(([voiceName, label]) => {
      const row = document.createElement('div');
      row.style.cssText = 'display:flex;align-items:center;justify-content:space-between;gap:0.5rem;';
      if (standaloneVoices[voiceName] === false) row.style.display = 'none';
      const rowLabel = document.createElement('span');
      rowLabel.textContent = label;
      const select = document.createElement('select');
      select.style.cssText = 'border:1px solid rgba(255,255,255,0.18);border-radius:0.6rem;background:rgba(255,255,255,0.06);color:#fff;font:inherit;padding:0.25rem 0.4rem;';
      Object.keys(PIECE_SONIC_INSTRUMENTS).forEach((key) => {
        const option = document.createElement('option');
        option.value = key;
        option.textContent = PIECE_SONIC_INSTRUMENTS[key];
        select.appendChild(option);
      });
      select.value = readPieceVoiceInstrumentOverrides(pieceId)[voiceName] || sonicParams.instrument || 'synth';
      select.addEventListener('change', () => {
        ensureEnabled().then((ok) => {
          if (ok && engine && engine.setVoiceInstrument(voiceName, select.value)) {
            writePieceVoiceInstrumentOverride(pieceId, voiceName, select.value);
          }
        });
      });
      voicePickerSelects[voiceName] = select;
      row.appendChild(rowLabel);
      row.appendChild(select);
      voicePickerWrap.appendChild(row);
    });

    const octaveRow = document.createElement('div');
    octaveRow.style.cssText = 'display:none;align-items:center;justify-content:center;gap:0.5rem;';
    const octaveDown = document.createElement('button'); octaveDown.type = 'button'; octaveDown.textContent = '−';
    const octaveUp = document.createElement('button'); octaveUp.type = 'button'; octaveUp.textContent = '+';
    standaloneOctaveDisplay = document.createElement('output'); standaloneOctaveDisplay.textContent = '3';
    [octaveDown, octaveUp].forEach((b) => { b.style.cssText = 'height:1.6rem;width:1.6rem;border:1px solid rgba(255,255,255,0.18);border-radius:0.4rem;background:rgba(255,255,255,0.08);color:#fff;font:inherit;font-weight:700;cursor:pointer;'; });
    octaveRow.appendChild(octaveDown); octaveRow.appendChild(standaloneOctaveDisplay); octaveRow.appendChild(octaveUp);

    standaloneKeysWrap = document.createElement('div');
    standaloneKeysWrap.style.cssText = 'display:none;position:relative;height:4rem;';
    // 10 white keys (one octave plus a major third into the next), matching
    // PIANO_KEY_MAP's physical-keyboard span above exactly.
    const whiteSemitones = [0, 2, 4, 5, 7, 9, 11, 12, 14, 16];
    const blackAfter = { 0: 1, 1: 3, 3: 6, 4: 8, 5: 10, 7: 13, 8: 15 };
    const whiteRow = document.createElement('div');
    whiteRow.style.cssText = 'display:flex;height:100%;';
    whiteSemitones.forEach((semitone, i) => {
      const key = document.createElement('button');
      key.type = 'button';
      key.dataset.semitone = String(semitone);
      key.className = 'runtime-key-white';
      whiteRow.appendChild(key);
      if (blackAfter[i] !== undefined) {
        const blackKey = document.createElement('button');
        blackKey.type = 'button';
        blackKey.dataset.semitone = String(blackAfter[i]);
        blackKey.className = 'runtime-key-black';
        blackKey.style.left = ((i + 1) * (100 / 10)) + '%';
        standaloneKeysWrap.appendChild(blackKey);
      }
    });
    standaloneKeysWrap.insertBefore(whiteRow, standaloneKeysWrap.firstChild);

    panel.appendChild(volumeRow);
    panel.appendChild(voicePickerWrap);
    panel.appendChild(keyboardRow);
    panel.appendChild(octaveRow);
    panel.appendChild(standaloneKeysWrap);
    wrap.appendChild(panel);
    document.body.appendChild(wrap);

    let panelOpen = false;
    panelTrigger.addEventListener('click', () => {
      panelOpen = !panelOpen;
      panel.style.display = panelOpen ? 'flex' : 'none';
      panelTrigger.setAttribute('aria-expanded', panelOpen ? 'true' : 'false');
    });
    volumeInput.addEventListener('input', () => { engine?.setVolume(Number(volumeInput.value)); });
    standaloneKeyboardToggle.addEventListener('click', () => {
      const nextOn = standaloneKeyboardToggle.getAttribute('aria-pressed') !== 'true';
      standaloneKeyboardToggle.setAttribute('aria-pressed', nextOn ? 'true' : 'false');
      octaveRow.style.display = nextOn ? 'flex' : 'none';
      standaloneKeysWrap.style.display = nextOn ? 'block' : 'none';
      if (nextOn) {
        ensureEnabled().then(() => {
          detachStandalonePianoKeys?.();
          detachStandalonePianoKeys = window.CreatrSonicController
            ? window.CreatrSonicController.attachPianoKeyListener(engine, (semitone, pressed) => {
              const k = standaloneKeysWrap.querySelector('[data-semitone="' + semitone + '"]');
              if (k) k.classList.toggle('is-pressed', pressed);
            })
            : null;
        });
      } else {
        detachStandalonePianoKeys?.();
        detachStandalonePianoKeys = null;
        standaloneKeysWrap.querySelectorAll('[data-semitone]').forEach((k) => { k.classList.remove('is-pressed'); });
      }
      engine?.setInputMode(nextOn ? 'keyboard' : 'motion');
    });
    standaloneKeysWrap.addEventListener('click', (event) => {
      const keyBtn = event.target instanceof Element ? event.target.closest('button[data-semitone]') : null;
      if (!keyBtn) return;
      ensureEnabled().then((ok) => { if (ok) engine?.triggerChromaticNote(Number(keyBtn.dataset.semitone || 0)); });
    });
    octaveDown.addEventListener('click', () => {
      if (!engine) return;
      engine.setOctave(engine.getOctave() - 1);
      standaloneOctaveDisplay.textContent = String(engine.getOctave());
    });
    octaveUp.addEventListener('click', () => {
      if (!engine) return;
      engine.setOctave(engine.getOctave() + 1);
      standaloneOctaveDisplay.textContent = String(engine.getOctave());
    });
  }
  if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
      if (disposed) return;
      handleMessage({ source: window, data: { type: 'creatr-sound-toggle', enabled: !(engine && engine.isEnabled()) } });
    });
  }

  window.addEventListener('message', handleMessage);

  // Same-origin gesture bridge: the parent page's click handlers call these
  // synchronously (frame.contentWindow.__creatrSonicGesture.*) so WebKit's
  // transient activation reaches the getUserMedia calls inside — the
  // postMessage relay above stays as the fallback and for non-gesture
  // messages (volume, octave, effects).
  window.__creatrSonicGesture = {
    toggleSound(on) { handleMessage({ source: window, data: { type: 'creatr-sound-toggle', enabled: !!on } }); },
    toggleHand: handleHandToggle,
    toggleMic: handleMicToggle,
    prepareHandControl,
    toggleHandControl: handleHandControlToggle,
    resetView() { return window.__pieceHandHooks?.resetView?.(); },
    toggleTilt: handleTiltToggle,
    toggleCameraBackground: handleCameraBackgroundToggle,
  };

  return {
    dispose() {
      disposed = true;
      detachStandalonePianoKeys?.();
      try { window.__pieceHandHooks?.setHandSteering?.(false); } catch (_) {}
      handControlBinding.detach();
      cameraBackgroundBinding.detach();
      if (cameraFeedHeld) {
        cameraFeedHeld = false;
        try { window.CreatrSonicController?.cameraFeed.release(); } catch (_) {}
      }
      tiltController?.disable();
      document.removeEventListener('creatr-sonic-capability-state', onCapabilityState);
      engine?.dispose();
    },
  };
}
let pieceAudioController = null;

// Shared DOM camera overlay for the 2D-surface engines (p5, plain c2,
// c2_interactive, svg): a mirrored <video> absolutely positioned over the
// piece surface, plus the capture-compositing hook downloads use to bake
// the overlay into exported PNGs. Three/A-Frame keep their own
// scene-background (VideoTexture / <a-videosphere>-style) hooks instead.
// getSurface is a function so engines whose surface appears asynchronously
// (p5's canvas) can register before it exists.
function createDomCameraOverlayHooks(getSurface, placement = 'overlay') {
  const isBackground = placement === 'background';
  const hooks = {
    _cameraOverlay: null,
    _cameraSourceVideo: null,
    _cameraResizeObserver: null,
    _cameraFullscreenSync: null,
    _cameraSyncFrame: 0,
    _cameraLastBox: '',
    // Background placement starts opaque (it sits behind the piece and only
    // shows through transparent regions); the overlay starts subtle.
    _cameraOpacity: isBackground ? 1 : 0.35,
    setBackgroundVideo(video) {
      const surface = getSurface();
      if (!video || !surface || !surface.parentElement) return false;
      this.clearBackgroundVideo();
      const parent = surface.parentElement;
      if (getComputedStyle(parent).position === 'static') parent.style.position = 'relative';
      const overlay = document.createElement('video');
      overlay.autoplay = true;
      overlay.muted = true;
      overlay.playsInline = true;
      overlay.srcObject = video.srcObject;
      overlay.style.cssText = 'position:absolute;transform:scaleX(-1);pointer-events:none;z-index:' + (isBackground ? '0' : '2') + ';';
      overlay.style.objectFit = 'cover';
      overlay.style.opacity = String(this._cameraOpacity);
      if (isBackground) {
        // The video must sit BEHIND the piece surface: lift the surface into
        // its own stacking layer above the feed. Pieces that paint an opaque
        // background will still hide it — that's the documented caveat of
        // choosing background placement on a 2D engine.
        if (getComputedStyle(surface).position === 'static') surface.style.position = 'relative';
        if (!surface.style.zIndex || Number(surface.style.zIndex) < 1) surface.style.zIndex = '1';
        parent.insertBefore(overlay, parent.firstChild);
      } else {
        parent.appendChild(overlay);
      }
      this._cameraOverlay = overlay;
      this._cameraSourceVideo = video;
      this.syncBackgroundVideoBox();
      // Canvas/SVG dimensions can change without a window resize (responsive
      // embeds, fullscreen, engine auto-fit, CMS layout changes). Observe the
      // authoritative presentation geometry rather than freezing the first
      // measured rectangle. This exists only while the camera is active.
      if (typeof ResizeObserver === 'function') {
        this._cameraResizeObserver = new ResizeObserver(() => this.queueBackgroundVideoBoxSync());
        this._cameraResizeObserver.observe(surface);
        this._cameraResizeObserver.observe(parent);
      }
      this._cameraFullscreenSync = () => this.queueBackgroundVideoBoxSync();
      document.addEventListener('fullscreenchange', this._cameraFullscreenSync);
      document.addEventListener('webkitfullscreenchange', this._cameraFullscreenSync);
      overlay.play().catch(() => {});
      return true;
    },
    syncBackgroundVideoBox() {
      const overlay = this._cameraOverlay;
      const surface = getSurface();
      if (!overlay || !surface || !surface.parentElement) return;
      // Rect-based (not offsetLeft/offsetWidth) so it also works for <svg>
      // roots, which have no HTMLElement offset geometry.
      const rect = surface.getBoundingClientRect();
      const parentRect = surface.parentElement.getBoundingClientRect();
      // Prefer layout geometry so CSS presentation tilt does not inflate the
      // camera box and then get applied a second time. SVG roots lack offset
      // geometry, so fall back to client size and their x/y base values.
      const layoutWidth = surface.offsetWidth || surface.clientWidth || rect.width;
      const layoutHeight = surface.offsetHeight || surface.clientHeight || rect.height;
      const layoutLeft = Number.isFinite(surface.offsetLeft) ? surface.offsetLeft : (surface.x?.baseVal?.value || rect.left - parentRect.left);
      const layoutTop = Number.isFinite(surface.offsetTop) ? surface.offsetTop : (surface.y?.baseVal?.value || rect.top - parentRect.top);
      const box = [layoutLeft, layoutTop, layoutWidth, layoutHeight]
        .map(value => Math.round(value * 100) / 100).join('|');
      if (box === this._cameraLastBox) return;
      this._cameraLastBox = box;
      const [left, top, width, height] = box.split('|');
      overlay.style.left = left + 'px';
      overlay.style.top = top + 'px';
      overlay.style.width = width + 'px';
      overlay.style.height = height + 'px';
    },
    queueBackgroundVideoBoxSync() {
      if (this._cameraSyncFrame) return;
      this._cameraSyncFrame = requestAnimationFrame(() => {
        this._cameraSyncFrame = 0;
        this.syncBackgroundVideoBox();
      });
    },
    clearBackgroundVideo() {
      this._cameraResizeObserver?.disconnect();
      this._cameraResizeObserver = null;
      if (this._cameraSyncFrame) cancelAnimationFrame(this._cameraSyncFrame);
      this._cameraSyncFrame = 0;
      this._cameraLastBox = '';
      if (this._cameraFullscreenSync) {
        document.removeEventListener('fullscreenchange', this._cameraFullscreenSync);
        document.removeEventListener('webkitfullscreenchange', this._cameraFullscreenSync);
        this._cameraFullscreenSync = null;
      }
      this._cameraOverlay?.remove();
      this._cameraOverlay = null;
      this._cameraSourceVideo = null;
    },
    setBackgroundOpacity(value) {
      this._cameraOpacity = Math.max(0, Math.min(1, Number(value)));
      if (this._cameraOverlay) this._cameraOverlay.style.opacity = String(this._cameraOpacity);
    },
    getBackgroundOpacity() {
      return this._cameraOpacity;
    },
    getBackgroundVideo() {
      return this._cameraSourceVideo || this._cameraOverlay;
    },
  };
  // Capture compositing: downloads pass the (already-rasterized, for svg)
  // base canvas; the mirrored live camera frame is drawn over it at the
  // current overlay opacity so the PNG matches what's on screen.
  window.__creatrComposeCapture = async (baseCanvas) => {
    // createDomCameraOverlayHooks() is merged into __pieceHandHooks. Its
    // methods run with that merged object as `this`, so the active video is
    // stored there—not on this pre-merge `hooks` object captured by closure.
    // Read the authoritative installed hook first or camera-on screenshots
    // silently omit the feed while still capturing the artwork.
    const overlay = window.__pieceHandHooks?._cameraOverlay || hooks._cameraOverlay;
    if (!overlay) return baseCanvas;
    if (overlay.readyState < 2 || !overlay.videoWidth) throw new Error('The camera frame is not ready to capture yet.');
    const composed = document.createElement('canvas');
    composed.width = baseCanvas.width;
    composed.height = baseCanvas.height;
    const ctx = composed.getContext('2d');
    const captureOpacity = Number(window.__pieceHandHooks?._cameraOpacity ?? hooks._cameraOpacity);
    const drawCamera = () => {
      ctx.save();
      ctx.globalAlpha = Number.isFinite(captureOpacity) ? captureOpacity : 0.35;
      ctx.translate(composed.width, 0);
      ctx.scale(-1, 1);
      ctx.drawImage(overlay, 0, 0, composed.width, composed.height);
      ctx.restore();
    };
    // Match the on-screen stacking: background placement bakes the camera
    // under the piece, overlay placement bakes it on top.
    if (isBackground) {
      drawCamera();
      ctx.drawImage(baseCanvas, 0, 0);
    } else {
      ctx.drawImage(baseCanvas, 0, 0);
      drawCamera();
    }
    return composed;
  };
  window.addEventListener('resize', () => hooks.syncBackgroundVideoBox());
  return hooks;
}

// Merges the DOM overlay hooks into window.__pieceHandHooks (creating it if
// no interaction hooks were registered) when this piece allows the camera.
function registerDomCameraOverlay(getSurface) {
  if (!PIECE_CAMERA_OVERLAY) return;
  window.__pieceHandHooks = Object.assign(window.__pieceHandHooks || {}, createDomCameraOverlayHooks(getSurface, PIECE_CAMERA_PLACEMENT));
}

// Flat regular surfaces have no camera to orbit. Their visual hand-motion
// contract tilts only the presentation plane, leaving authored coordinates,
// bitmap dimensions, and capture composition unchanged.
function registerPresentationTilt(getSurface) {
  if (!PIECE_HAND_CONTROL) return;
  const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches;
  let raf = 0;
  let targetX = 0, targetY = 0, currentX = 0, currentY = 0;
  // Preserve whichever compositor was registered first (camera overlay when
  // active), then warp that complete bitmap. Canvas strip warping is
  // deterministic and needs no extra WebGL context or dependency.
  const previousComposeCapture = window.__creatrComposeCapture;
  window.__creatrComposeCapture = async (baseCanvas) => {
    const source = typeof previousComposeCapture === 'function'
      ? await previousComposeCapture(baseCanvas)
      : baseCanvas;
    if (reduced || (Math.abs(currentX) < 0.01 && Math.abs(currentY) < 0.01)) return source;
    const width = source.width, height = source.height;
    const output = document.createElement('canvas');
    output.width = width; output.height = height;
    const ctx = output.getContext('2d');
    if (!ctx || !width || !height) return source;
    const captureSurface = getSurface();
    const captureBackground = captureSurface?.parentElement
      ? getComputedStyle(captureSurface.parentElement).backgroundColor
      : 'rgb(0, 0, 0)';
    ctx.fillStyle = captureBackground === 'rgba(0, 0, 0, 0)' ? 'rgb(0, 0, 0)' : captureBackground;
    ctx.fillRect(0, 0, width, height);
    const pitch = currentX * Math.PI / 180;
    const yaw = currentY * Math.PI / 180;
    const perspective = 900;
    const project = (x, y) => {
      const cy = Math.cos(yaw), sy = Math.sin(yaw);
      const cx = Math.cos(pitch), sx = Math.sin(pitch);
      const x1 = x * cy;
      const z1 = -x * sy;
      const y2 = y * cx - z1 * sx;
      const z2 = y * sx + z1 * cx;
      const scale = perspective / Math.max(1, perspective - z2);
      return { x: width / 2 + x1 * scale, y: height / 2 + y2 * scale };
    };
    const tl = project(-width / 2, -height / 2);
    const tr = project(width / 2, -height / 2);
    const bl = project(-width / 2, height / 2);
    const br = project(width / 2, height / 2);
    const solveHomography = (pairs) => {
      const matrix = [];
      pairs.forEach(({ x, y, u, v }) => {
        matrix.push([x, y, 1, 0, 0, 0, -u * x, -u * y, u]);
        matrix.push([0, 0, 0, x, y, 1, -v * x, -v * y, v]);
      });
      for (let col = 0; col < 8; col += 1) {
        let pivot = col;
        for (let row = col + 1; row < 8; row += 1) {
          if (Math.abs(matrix[row][col]) > Math.abs(matrix[pivot][col])) pivot = row;
        }
        if (Math.abs(matrix[pivot][col]) < 1e-9) return null;
        [matrix[col], matrix[pivot]] = [matrix[pivot], matrix[col]];
        const divisor = matrix[col][col];
        for (let c = col; c < 9; c += 1) matrix[col][c] /= divisor;
        for (let row = 0; row < 8; row += 1) {
          if (row === col) continue;
          const factor = matrix[row][col];
          for (let c = col; c < 9; c += 1) matrix[row][c] -= factor * matrix[col][c];
        }
      }
      return matrix.map(row => row[8]);
    };
    const homography = solveHomography([
      { ...tl, u: 0, v: 0 }, { ...tr, u: width - 1, v: 0 },
      { ...br, u: width - 1, v: height - 1 }, { ...bl, u: 0, v: height - 1 },
    ]);
    if (!homography) return source;
    const sourceContext = source.getContext('2d');
    if (!sourceContext) return source;
    const sourcePixels = sourceContext.getImageData(0, 0, width, height);
    const outputPixels = ctx.getImageData(0, 0, width, height);
    const src = sourcePixels.data, dst = outputPixels.data;
    const quad = [tl, tr, br, bl];
    const insideQuad = (x, y) => {
      let sign = 0;
      for (let i = 0; i < 4; i += 1) {
        const a = quad[i], b = quad[(i + 1) % 4];
        const cross = (b.x - a.x) * (y - a.y) - (b.y - a.y) * (x - a.x);
        if (Math.abs(cross) < 1e-6) continue;
        const nextSign = cross > 0 ? 1 : -1;
        if (sign && nextSign !== sign) return false;
        sign = nextSign;
      }
      return true;
    };
    const minX = Math.max(0, Math.floor(Math.min(...quad.map(p => p.x))));
    const maxX = Math.min(width - 1, Math.ceil(Math.max(...quad.map(p => p.x))));
    const minY = Math.max(0, Math.floor(Math.min(...quad.map(p => p.y))));
    const maxY = Math.min(height - 1, Math.ceil(Math.max(...quad.map(p => p.y))));
    for (let y = minY; y <= maxY; y += 1) {
      for (let x = minX; x <= maxX; x += 1) {
        if (!insideQuad(x + 0.5, y + 0.5)) continue;
        const denominator = homography[6] * x + homography[7] * y + 1;
        if (Math.abs(denominator) < 1e-9) continue;
        const u = (homography[0] * x + homography[1] * y + homography[2]) / denominator;
        const v = (homography[3] * x + homography[4] * y + homography[5]) / denominator;
        if (u < 0 || v < 0 || u > width - 1 || v > height - 1) continue;
        const x0 = Math.floor(u), y0 = Math.floor(v), x1 = Math.min(width - 1, x0 + 1), y1 = Math.min(height - 1, y0 + 1);
        const fx = u - x0, fy = v - y0;
        const dstIndex = (y * width + x) * 4;
        for (let channel = 0; channel < 4; channel += 1) {
          const p00 = src[(y0 * width + x0) * 4 + channel];
          const p10 = src[(y0 * width + x1) * 4 + channel];
          const p01 = src[(y1 * width + x0) * 4 + channel];
          const p11 = src[(y1 * width + x1) * 4 + channel];
          dst[dstIndex + channel] = (p00 * (1 - fx) + p10 * fx) * (1 - fy) + (p01 * (1 - fx) + p11 * fx) * fy;
        }
      }
      if ((y - minY) % 96 === 95) await new Promise(resolve => setTimeout(resolve, 0));
    }
    ctx.putImageData(outputPixels, 0, 0);
    return output;
  };
  const apply = () => {
    const surface = getSurface();
    if (!surface) { raf = requestAnimationFrame(apply); return; }
    currentX += (targetX - currentX) * 0.14;
    currentY += (targetY - currentY) * 0.14;
    surface.style.transformOrigin = '50% 50%';
    const tiltTransform = reduced ? '' : `perspective(900px) rotateX(${currentX.toFixed(2)}deg) rotateY(${currentY.toFixed(2)}deg)`;
    surface.style.transform = tiltTransform;
    // Camera is a sibling presentation layer; mirror it and apply the same
    // tilt so live view and capture share one visible geometry.
    const cameraOverlay = window.__pieceHandHooks?._cameraOverlay;
    if (cameraOverlay) {
      cameraOverlay.style.transformOrigin = '50% 50%';
      cameraOverlay.style.transform = (tiltTransform ? tiltTransform + ' ' : '') + 'scaleX(-1)';
    }
    raf = requestAnimationFrame(apply);
  };
  raf = requestAnimationFrame(apply);
  window.__pieceHandHooks = Object.assign(window.__pieceHandHooks || {}, {
    handPoint(nx, ny) {
      targetY = Math.max(-8, Math.min(8, (nx - 0.5) * 16));
      targetX = Math.max(-6, Math.min(6, -(ny - 0.5) * 12));
    },
    handLost() { targetX = 0; targetY = 0; },
  });
  window.addEventListener('pagehide', () => cancelAnimationFrame(raf), { once: true });
}

function registerSpatialPresentation(getSurface, interactive) {
  if (!window.CreatrSpatialPresentation?.create) {
    registerPresentationTilt(getSurface);
    return;
  }
  const baseHooks = window.__pieceHandHooks || {};
  const spatial = window.CreatrSpatialPresentation.create({
    getSurface,
    interactive: interactive === true,
    cameraPlacement: PIECE_CAMERA_PLACEMENT,
    getCameraVideo: () => baseHooks.getBackgroundVideo?.() || baseHooks._cameraOverlay || null,
    getCameraOpacity: () => baseHooks._cameraOpacity ?? 0.35,
  });
  window.__pieceHandHooks = Object.assign(baseHooks, spatial);
  window.addEventListener('pagehide', () => spatial.dispose?.(), { once: true });
}

// Camera feed as a blended, camera-attached background quad for the 3D
// engines (Three.js, A-Frame). Unlike the old `scene.background =
// VideoTexture` approach this supports opacity: a mirrored full-frustum
// plane parented to the active camera, drawn first (renderOrder -1, no
// depth) so the piece's own scene renders over it — at opacity 1 it looks
// exactly like the old opaque background swap, and the slider blends it
// against whatever the piece renders behind (its own background/clear
// color). Being in-scene, screenshots capture it with no extra compositing.
function createCameraBlendQuadHooks(THREE_NS, getScene, getCamera) {
  return {
    _cameraQuad: null,
    _videoTexture: null,
    _cameraOpacity: 1,
    setBackgroundVideo(video) {
      const scene = getScene();
      const camera = getCamera();
      if (!video || !scene || !camera || !THREE_NS || !THREE_NS.VideoTexture) return false;
      this.clearBackgroundVideo();
      const texture = new THREE_NS.VideoTexture(video);
      if (THREE_NS.SRGBColorSpace) texture.colorSpace = THREE_NS.SRGBColorSpace;
      // Mirror horizontally — matches the 2D overlay's selfie-style view.
      texture.wrapS = THREE_NS.RepeatWrapping;
      texture.repeat.x = -1;
      texture.offset.x = 1;
      const material = new THREE_NS.MeshBasicMaterial({
        map: texture,
        transparent: true,
        opacity: this._cameraOpacity,
        depthTest: false,
        depthWrite: false,
        // The quad must never react to the piece's lights or tone mapping.
        toneMapped: false,
        fog: false,
      });
      const quad = new THREE_NS.Mesh(new THREE_NS.PlaneGeometry(1, 1), material);
      quad.renderOrder = -1;
      quad.frustumCulled = false;
      // Refit to the camera frustum every frame — pieces own their render
      // loops and may animate fov/aspect/near, so a one-time fit would drift.
      quad.onBeforeRender = (_renderer, _scene, renderCamera) => {
        const cam = renderCamera && renderCamera.isPerspectiveCamera ? renderCamera : null;
        if (!cam) return;
        const dist = Math.max(cam.near * 2, 0.05);
        quad.position.set(0, 0, -dist);
        const height = 2 * dist * Math.tan((cam.fov * Math.PI) / 360);
        quad.scale.set(height * (cam.aspect || 1), height, 1);
      };
      // Camera children only render when the camera itself is in the scene
      // graph — harmless if the piece already added it, but authored Three.js
      // sketches virtually never call scene.add(camera) themselves (it isn't
      // needed for rendering), which silently made the camera-quad an
      // orphaned child that never rendered — "Show camera" toggled with no
      // visible effect and no error. A-Frame cameras are already scene
      // members via their entity, so this is a no-op there.
      if (!camera.parent) scene.add(camera);
      camera.add(quad);
      this._cameraQuad = quad;
      this._videoTexture = texture;
      return true;
    },
    clearBackgroundVideo() {
      const quad = this._cameraQuad;
      if (quad) {
        try { quad.parent && quad.parent.remove(quad); } catch (_) {}
        try { quad.geometry.dispose(); } catch (_) {}
        try { quad.material.dispose(); } catch (_) {}
        this._cameraQuad = null;
      }
      if (this._videoTexture) {
        try { this._videoTexture.dispose(); } catch (_) {}
        this._videoTexture = null;
      }
    },
    setBackgroundOpacity(value) {
      this._cameraOpacity = Math.max(0, Math.min(1, Number(value)));
      if (this._cameraQuad) this._cameraQuad.material.opacity = this._cameraOpacity;
    },
    getBackgroundOpacity() {
      return this._cameraOpacity;
    },
  };
}

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
  if (PIECE_ENGINE === 'c2' && (PIECE_SONIC || PIECE_CAMERA_OVERLAY || PIECE_HAND_CONTROL)) {
    if (PIECE_C2_INTERACTIVE) {
      // Pointer position over the canvas, normalized to ~0..1 so deltas are
      // the same order of magnitude as the three/aframe camera-position
      // deltas the sonification math was tuned against.
      const c2Mover = { position: { x: 0, y: 0, z: 0 } };
      const updateC2Mover = (clientX, clientY) => {
        const rect = canvas.getBoundingClientRect();
        if (!rect.width || !rect.height) return;
        c2Mover.position.x = (clientX - rect.left) / rect.width;
        c2Mover.position.y = (clientY - rect.top) / rect.height;
      };
      canvas.addEventListener('pointermove', (e) => updateC2Mover(e.clientX, e.clientY));
      canvas.addEventListener('touchmove', (e) => {
        const t = e.touches && e.touches[0];
        if (t) updateC2Mover(t.clientX, t.clientY);
      }, { passive: true });
      // Hand control for interactive c2: the wrist becomes a synthetic
      // pointer over the canvas, driving both the piece's own pointer
      // handlers and (via updateC2Mover) the movement voice. The DOM camera
      // overlay hooks are merged in separately below when the piece allows
      // the camera.
      // Last synthetic pointer position, so a pinch press/release lands where
      // the hand currently is even if no move frame arrived in between.
      let lastHandClient = null;
      let c2HandSteeringExclusive = false;
      const blockManualC2InputWhileSteering = (event) => {
        // Hand frames are synthetic (isTrusted=false); cursor/touch input is
        // trusted. During steering, stop only the latter before authored
        // drag/click/action handlers see it.
        if (!c2HandSteeringExclusive || event.isTrusted === false) return;
        event.preventDefault?.();
        event.stopImmediatePropagation?.();
      };
      ['pointerdown', 'pointermove', 'pointerup', 'click', 'mousedown', 'mousemove', 'mouseup', 'touchstart', 'touchmove', 'touchend']
        .forEach((type) => canvas.addEventListener(type, blockManualC2InputWhileSteering, { capture: true, passive: false }));
      const dispatchHandPointer = (type, clientX, clientY) => {
        try {
          canvas.dispatchEvent(new PointerEvent(type, { clientX, clientY, bubbles: true, isPrimary: true, pointerType: 'touch', button: 0, buttons: type === 'pointerup' ? 0 : 1 }));
        } catch (_) {
          try {
            const ev = document.createEvent('MouseEvent');
            ev.initMouseEvent(type.replace('pointer', 'mouse'), true, true, window, 0, clientX, clientY, clientX, clientY, false, false, false, false, 0, null);
            canvas.dispatchEvent(ev);
          } catch (_e) {}
        }
      };
      window.__pieceHandHooks = {
        engine: 'c2_interactive',
        setHandSteering(active) {
          c2HandSteeringExclusive = !!active;
          if (!c2HandSteeringExclusive && lastHandClient) {
            dispatchHandPointer('pointerup', lastHandClient.x, lastHandClient.y);
          }
          return true;
        },
        handPoint(nx, ny) {
          const rect = canvas.getBoundingClientRect();
          if (!rect.width || !rect.height) return;
          const clientX = rect.left + nx * rect.width;
          const clientY = rect.top + ny * rect.height;
          lastHandClient = { x: clientX, y: clientY };
          updateC2Mover(clientX, clientY);
          dispatchHandPointer('pointermove', clientX, clientY);
        },
        // Pinch gesture → pointer button, so drag-driven interactive c2
        // sketches (which only act on move while pressed) respond to the
        // hand. Driven by handControlBinding's pinch detection.
        handPress(down) {
          if (!c2HandSteeringExclusive || !lastHandClient) return;
          dispatchHandPointer(down ? 'pointerdown' : 'pointerup', lastHandClient.x, lastHandClient.y);
        },
      };
      registerDomCameraOverlay(() => canvas);
      registerSpatialPresentation(() => canvas, true);
      pieceAudioController = createPieceRuntimeAudioController(PIECE_SONIC, () => c2Mover);
    } else {
      // Plain (non-interactive) c2 has no motion signal — idle-only pattern
      // when sound exists; the camera overlay works either way.
      registerDomCameraOverlay(() => canvas);
      registerSpatialPresentation(() => canvas, false);
      pieceAudioController = createPieceRuntimeAudioController(PIECE_SONIC, null);
    }
  }
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
      // p5 has no camera/pointer motion signal on this view — idle-only
      // random-note pattern when sound is unmuted (see createPieceRuntimeAudioController).
      if (PIECE_SONIC || PIECE_CAMERA_OVERLAY) {
        registerDomCameraOverlay(() => document.querySelector('#runtime-root canvas') || document.querySelector('canvas'));
        pieceAudioController = createPieceRuntimeAudioController(PIECE_SONIC, null);
      }
      registerSpatialPresentation(() => document.querySelector('#runtime-root canvas') || document.querySelector('canvas'), false);
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
  let initialAFramePose = null;
  let resettingView = false;
  // Gesture travel/zoom translate the camera freely; without a bound,
  // accidental command bursts can carry the view so far from the scene
  // that steering appears dead. Keep the camera within roaming range of
  // its authored starting pose (twin of the three hooks' travel clamp).
  function clampAFrameGestureRoam(cameraObject) {
    if (!initialAFramePose || !cameraObject) return;
    const origin = initialAFramePose.position;
    const dx = cameraObject.position.x - origin.x;
    const dy = cameraObject.position.y - origin.y;
    const dz = cameraObject.position.z - origin.z;
    const dist = Math.hypot(dx, dy, dz);
    const maxRoam = 24;
    if (dist > maxRoam) {
      const s = maxRoam / dist;
      cameraObject.position.set(origin.x + dx * s, origin.y + dy * s, origin.z + dz * s);
    }
  }
  const runtimeScript = document.currentScript && document.currentScript.src ? document.currentScript.src : window.location.href;
  const script = document.createElement('script');
  script.src = new URL('/assets/js/aframe.min.js', runtimeScript).toString();
  script.onload = () => {
    disableAFrameWASD();
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

      installAFrameModelDiagnostics(scene);
      runPieceCode();
      // Some generated sketches create their model entity inside window.sketch.
      // Re-scan after authored code has run; the per-entity marker keeps this
      // idempotent for markup that was already present.
      installAFrameModelDiagnostics(scene);
      installAFrameBackdropAspectFix(scene);
      let modelDiagnosticsAttempts = 0;
      const modelDiagnosticsTimer = setInterval(() => {
        installAFrameModelDiagnostics(scene);
        installAFrameBackdropAspectFix(scene);
        modelDiagnosticsAttempts += 1;
        if (modelDiagnosticsAttempts >= 20) clearInterval(modelDiagnosticsTimer);
      }, 250);
      const ready = createReadyController(() => scene.canvas || scene.querySelector('canvas') || document.querySelector('canvas'));
      // Capture diagnostics are non-critical. Some A-Frame scene wrappers are
      // rejected by MutationObserver in specific browsers; that must never
      // abort the interaction/steering bootstrap that follows.
      try { ready.noteInlineMedia(scene); } catch (_) {}
      if (typeof window.sketch === 'function') {
        // Artwork startup is not allowed to take the platform interaction
        // layer down with it. A partially-working sketch must still leave
        // manual controls available and must not prevent the steering hooks
        // below from registering.
        try {
          window.sketch({ AFRAME: window.AFRAME, scene, startFrame });
        } catch (error) {
          showPieceError(error);
        }
        replayAFrameAssetImageLoads(scene);
      }
      let pointerTarget = null;
      let frameId = 0;
      let handSteeringExclusive = false;
      let aframeControlsBeforeHand = [];
      let aframeLookControlsEnabledBeforeHand = null;
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

      function captureInitialAFramePose() {
        if (initialAFramePose) return true;
        const initialCameraObject = getAFrameCameraMover();
        if (!initialCameraObject) return false;
        initialAFramePose = {
          position: initialCameraObject.position.clone(),
          quaternion: initialCameraObject.quaternion.clone(),
        };
        pieceSteeringTrace('aframe-initial-pose', {
          position: initialAFramePose.position.toArray(),
          quaternion: initialAFramePose.quaternion.toArray(),
        });
        return true;
      }

      function snapshotAFrameSteeringPose() {
        if (!PIECE_SONIC_DEBUG) return null;
        const THREE_NS = getAFrameThree();
        const mover = getAFrameCameraMover();
        const renderCamera = getAFrameCameraObject();
        const cameraEl = scene?.querySelector('[camera]') || scene?.querySelector('a-camera');
        const lookControls = cameraEl?.components?.['look-controls'];
        const moverWorld = THREE_NS && mover ? mover.getWorldQuaternion(new THREE_NS.Quaternion()) : null;
        const renderWorld = THREE_NS && renderCamera ? renderCamera.getWorldQuaternion(new THREE_NS.Quaternion()) : null;
        return {
          moverUuid: mover?.uuid || null,
          renderCameraUuid: renderCamera?.uuid || null,
          renderCameraParentUuid: renderCamera?.parent?.uuid || null,
          moverRotation: mover ? [mover.rotation.x, mover.rotation.y, mover.rotation.z, mover.rotation.order] : null,
          moverWorldQuaternion: moverWorld?.toArray?.() || null,
          renderWorldQuaternion: renderWorld?.toArray?.() || null,
          lookEnabled: lookControls?.data?.enabled,
          lookPlaying: lookControls?.isPlaying,
        };
      }

      function activeAFrameTouchCount() {
        return aframeNav.activeTouches.size;
      }

      function onAFramePointerDown(event) {
        if (handSteeringExclusive) return;
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
        if (handSteeringExclusive) return;
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
        if (handSteeringExclusive) return;
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
      scene.addEventListener('renderstart', captureInitialAFramePose, { once: true });
      scene.addEventListener('loaded', disableMotionTracking, { once: true });
      scene.addEventListener('loaded', captureInitialAFramePose, { once: true });
      // A large document (e.g. an inlined multi-megabyte GLB data: URI in a
      // bundle export) can take long enough to parse/execute that A-Frame's
      // once-only 'renderstart' fires before this script reaches the
      // listener above, permanently missing it — Reset View then silently
      // does nothing forever. If the scene reports it already rendered,
      // capture now instead of waiting on an event that already happened.
      if (scene.renderStarted) {
        bindAFramePointerControls();
        captureInitialAFramePose();
      }
      let platformInteractionInitialized = false;
      function initializeAFramePlatformInteraction() {
        if (platformInteractionInitialized) return;
        try { ready.noteInlineMedia(scene); } catch (_) {}
        try { disableMotionTracking(); } catch (_) {}
        try { bindAFramePointerControls(); } catch (_) {}
        try { requestAnimationFrame(() => requestAnimationFrame(signalAFrameReadyOnce)); } catch (_) {}
        // Do NOT capture the initial pose here: this runs immediately, before
        // A-Frame has necessarily applied the authored <a-camera position="…">
        // attribute to its object3D, which previously captured the scene
        // origin (0,0,0) instead of the real starting pose — Reset View then
        // landed on the wrong (too-zoomed-in) camera position. The
        // `renderstart`/`loaded` listeners (and the renderStarted fallback
        // above) capture it once the first frame has actually rendered.
        // Hooks first (capability handshake) — see the three bootstrap twin.
        // Camera feed renders as a blended camera-attached quad (opacity
        // slider support) rather than an opaque scene.background swap.
        window.__pieceHandHooks = Object.assign(
          {
            engine: 'aframe',
            setHandSteering(active) {
              const next = !!active;
              pieceSteeringTrace('aframe-ownership', { requested: next, current: handSteeringExclusive });
              if (next === handSteeringExclusive) return true;
              const cameraEl = scene?.querySelector('[camera]') || scene?.querySelector('a-camera');
              const lookControls = cameraEl?.components?.['look-controls'];
              pieceSteeringTrace('aframe-controls', { cameraFound: !!cameraEl, lookControlsFound: !!lookControls, lookEnabled: lookControls?.data?.enabled });
              if (next) {
                captureInitialAFramePose();
                // look-controls' tick writes the camera entity's rotation
                // every frame from its internal pitch/yaw state; pausing the
                // component is timing-fragile (only works when it isPlaying
                // right now), so disable it at the data level — its tick
                // gates on data.enabled — like three's controls.enabled flag.
                aframeLookControlsEnabledBeforeHand = lookControls ? lookControls.data.enabled !== false : null;
                pieceSteeringTrace('aframe-controls-disable', { wasEnabled: aframeLookControlsEnabledBeforeHand });
                if (lookControls) cameraEl.setAttribute('look-controls', 'enabled', false);
                const wasd = cameraEl?.components?.['wasd-controls'];
                aframeControlsBeforeHand = wasd ? [{ component: wasd, wasPlaying: wasd.isPlaying !== false }] : [];
                aframeControlsBeforeHand.forEach(({ component }) => component?.pause?.());
                aframeNav.pointer = null;
                aframeNav.activeTouches.clear();
                aframeNav.animFrom = aframeNav.animTo = null;
              } else {
                // Ownership handoff, not a view reset: seed look-controls'
                // internal pitch/yaw from the hand-driven pose so manual
                // dragging resumes exactly where steering left the camera.
                const mover = getAFrameCameraMover();
                if (lookControls && mover) {
                  if (lookControls.pitchObject) lookControls.pitchObject.rotation.x = mover.rotation.x;
                  if (lookControls.yawObject) lookControls.yawObject.rotation.y = mover.rotation.y;
                }
                if (lookControls && aframeLookControlsEnabledBeforeHand !== null) {
                  cameraEl.setAttribute('look-controls', 'enabled', aframeLookControlsEnabledBeforeHand);
                }
                aframeLookControlsEnabledBeforeHand = null;
                aframeControlsBeforeHand.forEach(({ component, wasPlaying }) => {
                  if (wasPlaying) component?.play?.();
                });
                aframeControlsBeforeHand = [];
              }
              handSteeringExclusive = next;
              pieceSteeringTrace('aframe-ownership-applied', snapshotAFrameSteeringPose());
              return true;
            },
            handPoint(nx, ny) {
              if (resettingView || !handSteeringExclusive) return;
              const cameraObject = getAFrameCameraMover();
              if (!cameraObject) return;
              cameraObject.rotation.order = 'YXZ';
              const desiredYaw = (0.5 - nx) * Math.PI * 1.5;
              const desiredPitch = (0.5 - ny) * Math.PI * 0.6;
              cameraObject.rotation.y += (desiredYaw - cameraObject.rotation.y) * 0.12;
              cameraObject.rotation.x = Math.max(-Math.PI * 0.45, Math.min(Math.PI * 0.45,
                cameraObject.rotation.x + (desiredPitch - cameraObject.rotation.x) * 0.12));
            },
            handCommand(command) {
              if (resettingView || !handSteeringExclusive || !command) return;
              const cameraObject = getAFrameCameraMover();
              if (!cameraObject) return;
              const before = snapshotAFrameSteeringPose();
              if (command.type === 'look') {
                this.handPoint(command.x, command.y);
              } else if (command.type === 'orbit') {
                cameraObject.rotation.order = 'YXZ';
                cameraObject.rotation.y += command.yaw || 0;
                cameraObject.rotation.x = Math.max(-Math.PI * 0.45, Math.min(Math.PI * 0.45, cameraObject.rotation.x + (command.pitch || 0)));
              } else if (command.type === 'travel') {
                cameraObject.translateX((command.right || 0) * 0.08);
                cameraObject.translateZ(-(command.forward || 0) * 0.11);
                clampAFrameGestureRoam(cameraObject);
              } else if (command.type === 'zoom') {
                cameraObject.translateZ((command.delta || 0) * 1.4);
                clampAFrameGestureRoam(cameraObject);
              }
              pieceSteeringTrace('aframe-pose-applied', { command, before, after: snapshotAFrameSteeringPose() });
              if (PIECE_SONIC_DEBUG) {
                requestAnimationFrame(() => pieceSteeringTrace('aframe-pose-next-frame', snapshotAFrameSteeringPose()));
              }
            },
            resetView() {
              const cameraObject = getAFrameCameraMover();
              if (!cameraObject || !initialAFramePose) return Promise.resolve(false);
              resettingView = true;
              const fromPosition = cameraObject.position.clone();
              const fromQuaternion = cameraObject.quaternion.clone();
              const startedAt = performance.now();
              return new Promise((resolve) => {
                const step = (now) => {
                  const t = Math.min(1, (now - startedAt) / 360);
                  const eased = 1 - Math.pow(1 - t, 3);
                  cameraObject.position.lerpVectors(fromPosition, initialAFramePose.position, eased);
                  cameraObject.quaternion.slerpQuaternions(fromQuaternion, initialAFramePose.quaternion, eased);
                  if (t < 1) {
                    requestAnimationFrame(step);
                  } else {
                    resettingView = false;
                    resolve(true);
                  }
                };
                requestAnimationFrame(step);
              });
            },
          },
          PIECE_CAMERA_PLACEMENT === 'overlay'
            // Author chose overlay placement: a DOM <video> blended over the
            // WebGL canvas instead of the in-scene background quad.
            ? createDomCameraOverlayHooks(() => document.querySelector('a-scene canvas') || document.querySelector('canvas'), 'overlay')
            : createCameraBlendQuadHooks(
                window.AFRAME && window.AFRAME.THREE,
                () => scene && scene.object3D,
                () => getAFrameCameraObject()
              )
        );
        platformInteractionInitialized = true;
        if (PIECE_SONIC) {
          pieceAudioController = createPieceRuntimeAudioController(PIECE_SONIC, () => getAFrameCameraMover());
        } else if (PIECE_CAMERA_OVERLAY || PIECE_HAND_CONTROL) {
          // Camera-only piece: mount the controller for the camera toggle's
          // message bridge even though no audio engine will ever exist.
          pieceAudioController = createPieceRuntimeAudioController(null, null);
        }
      }
      // Steering is platform infrastructure, so it must not wait forever on
      // authored <a-assets> that fail to settle. Install the lazy camera hooks
      // now; repeat on `loaded` is harmless and preserves the fully-ready path.
      initializeAFramePlatformInteraction();
      scene.addEventListener('loaded', initializeAFramePlatformInteraction, { once: true });
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

// A-Frame's gltf-model component reports a successful network/parse load, but
// it does not frame the resulting object. Uploaded GLB files commonly carry
// arbitrary units and pivots, so a valid model can otherwise be present but
// invisible (or far outside the camera). Keep this safety net in the shared
// runtime so generated pieces and downloaded exports get the same behavior.
// Generated A-Frame markup often puts a CMS image on a backdrop plane with a
// hardcoded width/height (e.g. 72x46), stretching the image to an arbitrary
// aspect ratio. Once the underlying texture image is measurable, correct the
// plane's height to preserve the image's natural aspect. Only fires for
// CMS-served images; never touches a-sky (equirectangular by design).
// Capture-safe rendering inlines CMS media as data: URLs, so an <a-assets>
// <img> can finish loading before window.sketch attaches its 'load'
// listener — the event then never fires and sketches that size backdrops
// from it leave the plane at A-Frame's 1x1 default. Re-dispatch 'load' for
// images that are already complete when the sketch has run; images still
// loading fire their natural event later and are skipped here.
function replayAFrameAssetImageLoads(scene) {
  scene.querySelectorAll('a-assets img').forEach((img) => {
    if (img.complete && img.naturalWidth > 0) {
      try { img.dispatchEvent(new Event('load')); } catch (_) {}
    }
  });
}

function installAFrameBackdropAspectFix(scene) {
  const CMS_SRC = /^(?:https?:\/\/[^/]+)?\/(?:image|media|api\/media-assets)\//;

  function isCmsUrl(url) {
    if (typeof url !== 'string' || url === '') return false;
    try { return CMS_SRC.test(new URL(url, window.location.href).pathname); } catch (_) { return false; }
  }

  function resolveImage(el) {
    let src = null;
    try { src = el.getAttribute('src'); } catch (_) { return null; }
    if (src && typeof src === 'object' && typeof src.src === 'string') {
      return isCmsUrl(src.src) ? src : null;
    }
    if (typeof src !== 'string' || src === '') return null;
    if (src.startsWith('#')) {
      const asset = document.querySelector(src);
      if (!asset || asset.tagName !== 'IMG') return null;
      const assetSrc = asset.getAttribute('src') || asset.src || '';
      // Inlined CMS media arrives as a data: URL; it still deserves the
      // aspect correction that its original /image//media URL would get.
      return (isCmsUrl(assetSrc) || assetSrc.startsWith('data:image/')) ? asset : null;
    }
    if (!isCmsUrl(src)) return null;
    const img = new Image();
    img.src = src;
    return img;
  }

  scene.querySelectorAll('a-plane, a-image').forEach((el) => {
    if (el.dataset.creatrAspectChecked) return;
    el.dataset.creatrAspectChecked = '1';
    const img = resolveImage(el);
    if (!img) return;
    const apply = () => {
      const naturalAspect = img.naturalWidth > 0 && img.naturalHeight > 0
        ? img.naturalWidth / img.naturalHeight : 0;
      if (!naturalAspect) return;
      const width = parseFloat(el.getAttribute('width'));
      const height = parseFloat(el.getAttribute('height'));
      if (!(width > 0) || !(height > 0)) return;
      const current = width / height;
      if (Math.abs(current / naturalAspect - 1) <= 0.02) return;
      el.setAttribute('height', (width / naturalAspect).toFixed(3));
      diag('aframe-backdrop-aspect-fixed', { id: el.id || null, width, from: height, to: width / naturalAspect });
    };
    if (img.complete && img.naturalWidth > 0) apply();
    else img.addEventListener('load', apply, { once: true });
  });
}

function installAFrameModelDiagnostics(scene) {
  const THREE_NS = window.AFRAME?.THREE || window.THREE;
  const emit = (entity, status, data = {}) => {
    const detail = { status, entityId: entity?.id || '', ...data };
    try { entity?.dispatchEvent(new CustomEvent('creatr-model-status', { detail })); } catch (_) {}
    try { window.parent.postMessage({ type: 'creatr-aframe-model', ...detail }, '*'); } catch (_) {}
    try { diag(`aframe-model-${status}`, detail); } catch (_) {}
  };
  const modelSource = (entity) => {
    const ref = String(entity?.getAttribute('gltf-model') || '');
    if (!ref.startsWith('#')) return ref || '(missing gltf-model source)';
    const asset = document.getElementById(ref.slice(1));
    return String(asset?.getAttribute('src') || ref);
  };
  const recoverBinaryModel = (entity, source, onSuccess, onFailure) => {
    const component = entity.components?.['gltf-model'];
    const loaderClass = window.AFRAME?.THREE?.GLTFLoader || window.THREE?.GLTFLoader;
    const loader = component?.loader || (loaderClass ? new loaderClass() : null);
    if (!loader || !source || source === '(missing gltf-model source)') {
      onFailure(new Error('A-Frame GLTFLoader.parse is unavailable.'));
      return;
    }
    fetch(source, { credentials: 'same-origin' }).then((response) => {
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      return response.arrayBuffer();
    }).then((bytes) => new Promise((resolve, reject) => {
      const basePath = source.slice(0, Math.max(0, source.search(/[?#]/) >= 0 ? source.search(/[?#]/) : source.length)).replace(/[^/]*$/, '');
      loader.parse(bytes, basePath, resolve, reject);
    })).then((gltf) => {
      const model = gltf?.scene || gltf?.scenes?.[0];
      if (!model) throw new Error('The parsed GLB did not contain a scene.');
      entity.setObject3D('mesh', model);
      if (entity.components?.['gltf-model']) entity.components['gltf-model'].model = model;
      onSuccess(model);
    }).catch(onFailure);
  };
  const fitEntity = (entity) => {
    const source = modelSource(entity);
    if (entity.__creatrModelFitted) return;
    const mesh = entity.getObject3D('mesh');
    if (!mesh || !THREE_NS?.Box3 || !THREE_NS?.Vector3 || !THREE_NS?.Matrix4) {
      emit(entity, 'invalid', { source, message: 'A-Frame loaded the model, but its mesh or Three.js bounds API is unavailable.' });
      return;
    }
    mesh.updateWorldMatrix?.(true, true);
    const box = new THREE_NS.Box3().setFromObject(mesh);
    const size = box.getSize(new THREE_NS.Vector3());
    const center = box.getCenter(new THREE_NS.Vector3());
    const maxDim = Math.max(size.x, size.y, size.z);
    if (!Number.isFinite(maxDim) || maxDim <= 0) {
      emit(entity, 'invalid', { source, message: 'A-Frame loaded the model, but its bounding box is empty.', dimensions: [size.x, size.y, size.z] });
      return;
    }

    // Convert the world-space box center back to the model root's local space
    // before shifting the mesh. This keeps the authored entity position as a
    // composition offset instead of silently moving the entity itself.
    const inverse = new THREE_NS.Matrix4().copy(mesh.matrixWorld).invert();
    center.applyMatrix4(inverse);
    mesh.position.sub(center);

    // Idempotent with generated code that already applied the documented
    // target-size fit: once the model is roughly three scene units wide, this
    // multiplier is effectively 1 and does not double-scale it.
    const targetSize = 3;
    const factor = targetSize / maxDim;
    if (Number.isFinite(factor) && factor > 0) mesh.scale.multiplyScalar(factor);
    entity.__creatrModelFitted = true;
    emit(entity, 'loaded', {
      source,
      dimensions: [size.x, size.y, size.z],
      targetSize,
      fitScale: factor,
      message: `Loaded and fitted ${source}.`,
    });
  };
  const startBinaryFallback = (entity, source, initialMessage) => {
    if (entity.__creatrBinaryModelFallbackStarted) return;
    entity.__creatrBinaryModelFallbackStarted = true;
    recoverBinaryModel(entity, source, () => {
      fitEntity(entity);
      entity.emit('model-loaded', { format: 'gltf', model: entity.getObject3D('mesh') });
    }, (fallbackError) => {
      const detail = `${initialMessage}; binary fallback failed: ${fallbackError?.message || fallbackError}`;
      emit(entity, 'error', { source, message: `A-Frame model ${source} failed to load: ${detail}` });
      showPieceError(`A-Frame model ${source} failed to load: ${detail}`);
    });
  };

  scene.querySelectorAll('[gltf-model]').forEach((entity) => {
    if (entity.__creatrModelDiagnosticsInstalled) return;
    entity.__creatrModelDiagnosticsInstalled = true;
    const source = modelSource(entity);
    entity.addEventListener('model-loaded', () => fitEntity(entity), { once: true });
    entity.addEventListener('model-error', (event) => {
      const message = event?.detail?.src?.message || event?.detail?.message || 'The GLB could not be parsed or fetched.';
      startBinaryFallback(entity, source, message);
    }, { once: true });
    // If A-Frame finished before listeners were attached, handle the already
    // available mesh on the next task without disturbing normal boot order.
    setTimeout(() => {
      if (entity.getObject3D('mesh')) fitEntity(entity);
      else if (!/\.gltf(?:[?#]|$)/i.test(source)) startBinaryFallback(entity, source, 'A-Frame did not produce a model after the initial load attempt');
    }, 750);
  });
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
    let initialThreePose = null;
    let resettingView = false;
    let rafIds = [];
    let pieceDrivesOwnRender = false;
    // These are also read by the shared hand hooks below. They cannot live
    // inside the renderer setup block: the hook is deliberately registered
    // after that block so camera-overlay hooks can be merged into it.
    let pointerState = null;
    let keyNav = null;
    let animFromTarget = null;
    let animToTarget = null;
    let animFromCam = null;
    let animToCam = null;
    let isOrbitActive = false;
    let handSteeringExclusive = false;
    let controlsEnabledBeforeHand = true;
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
    // Attach the 3D-model loader so generated code can call
    // THREE.GLTFLoader (no import/fetch token → passes preflight). Loaded in
    // a separate catch-wrapped step so a loader CDN hiccup only disables
    // model loading, never the rest of the piece (same philosophy as the
    // gyro/DeviceOrientationControls handling). OBJ is not a supported
    // upload/generation format (dropped in favor of GLTF/GLB, which are
    // self-contained single files; OBJ typically needs companion
    // .mtl/texture files this app's upload flow doesn't support).
    try {
      const { GLTFLoader } = await import('https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/loaders/GLTFLoader.js');
      instrumentedThree.GLTFLoader = GLTFLoader;
    } catch (_e) {
      // 3D model loader unavailable; model-free pieces are unaffected.
    }
    window.THREE = instrumentedThree;
    // Keep platform camera ownership independent from artwork startup. Some
    // sketches create a usable scene/camera/renderer and then throw while
    // wiring an optional effect; steering and manual controls can still be
    // initialized safely from that usable state.
    try {
      window.sketch({ THREE: instrumentedThree, canvas, startFrame, width, height, size: { width, height }, OrbitControls });
    } catch (error) {
      showPieceError(error);
    }
    ready.noteInlineMedia(document);
    ensureFallbackLighting();
    autoFit();

    if (state.camera && state.renderer) {
      controls = new OrbitControls(state.camera, canvas);
      controls.enableDamping = true;
      controls.enablePan = true;
      initialThreePose = { camera: state.camera.position.clone(), target: controls.target.clone() };
      const threeRaycaster = new mod.Raycaster();
      pointerState = new Map();
      let hadMultiTouchGesture = false;
      let threeNavLimit = 5;
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
        if (handSteeringExclusive) return;
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
        if (handSteeringExclusive) return;
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
        if (handSteeringExclusive) return;
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
        isEnabled: () => !handSteeringExclusive,
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
              controls.enabled = handSteeringExclusive ? false : controlsEnabledBeforeHand;
              animFromTarget = animToTarget = animFromCam = animToCam = null;
            }
          }
          if (!handSteeringExclusive && keyNav?.update()) {
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

    // Interaction/camera hooks for the shared hand-tracking pipeline —
    // registered BEFORE the audio controller so its capability handshake
    // (notifyParentVoices) can advertise them to the host page. Camera feed
    // renders as a blended camera-attached quad (opacity slider support)
    // rather than an opaque scene.background swap.
    window.__pieceHandHooks = Object.assign(
      {
        engine: 'three',
        setHandSteering(active) {
          if (!controls || !state.camera) return false;
          const next = !!active;
          if (next === handSteeringExclusive) return true;
          if (next) {
            controlsEnabledBeforeHand = controls.enabled;
            controls.enabled = false;
            keyNav?.clearKeys?.();
            pointerState.clear();
            isOrbitActive = false;
            animFromTarget = animToTarget = animFromCam = animToCam = null;
          } else {
            controls.enabled = controlsEnabledBeforeHand;
          }
          handSteeringExclusive = next;
          return true;
        },
        // Wrist position (mirrored x, raw y in 0..1) steers the orbit like a
        // continuous drag: desired spherical angles around the current orbit
        // target, eased per frame so tracking jitter doesn't jolt the camera.
        handPoint(nx, ny) {
          if (resettingView || !handSteeringExclusive || !controls || !state.camera) return;
          const target = controls.target;
          const offset = state.camera.position.clone().sub(target);
          const sph = new mod.Spherical().setFromVector3(offset);
          const desiredTheta = (nx - 0.5) * Math.PI * 1.5;
          const desiredPhi = Math.PI / 2 + (ny - 0.5) * Math.PI * 0.7;
          sph.theta += (desiredTheta - sph.theta) * 0.12;
          sph.phi += (desiredPhi - sph.phi) * 0.12;
          sph.phi = Math.max(0.15, Math.min(Math.PI - 0.15, sph.phi));
          offset.setFromSpherical(sph);
          state.camera.position.copy(target).add(offset);
          controls.update();
        },
        handCommand(command) {
          if (resettingView || !handSteeringExclusive || !controls || !state.camera || !command) return;
          if (command.type === 'look') {
            this.handPoint(command.x, command.y);
            return;
          }
          const target = controls.target;
          if (command.type === 'orbit') {
            const offset = state.camera.position.clone().sub(target);
            const sph = new mod.Spherical().setFromVector3(offset);
            sph.theta += command.yaw || 0;
            sph.phi = Math.max(0.15, Math.min(Math.PI - 0.15, sph.phi + (command.pitch || 0)));
            offset.setFromSpherical(sph);
            state.camera.position.copy(target).add(offset);
          } else if (command.type === 'travel') {
            const forward = new mod.Vector3();
            state.camera.getWorldDirection(forward);
            forward.y = 0;
            if (forward.lengthSq() > 1e-6) forward.normalize();
            const right = new mod.Vector3(-forward.z, 0, forward.x);
            const delta = forward.multiplyScalar((command.forward || 0) * 0.11)
              .add(right.multiplyScalar((command.right || 0) * 0.09));
            state.camera.position.add(delta);
            target.add(delta);
            // Keep gesture travel within reach of the artwork: runaway
            // command bursts must never carry the view so far that the piece
            // vanishes and steering appears dead.
            if (initialThreePose) {
              const roam = target.clone().sub(initialThreePose.target);
              const maxRoam = Math.max(12, initialThreePose.camera.distanceTo(initialThreePose.target) * 4);
              if (roam.length() > maxRoam) {
                const pullback = roam.setLength(roam.length() - maxRoam);
                state.camera.position.sub(pullback);
                target.sub(pullback);
              }
            }
          } else if (command.type === 'zoom') {
            const offset = state.camera.position.clone().sub(target);
            // Bound zoom relative to the authored framing, not an absolute
            // world size — pieces live at wildly different scales.
            const baseDist = initialThreePose
              ? initialThreePose.camera.distanceTo(initialThreePose.target) : 5;
            const minDist = Math.max(0.35, baseDist * 0.2);
            const maxDist = Math.max(12, baseDist * 4);
            const distance = Math.max(minDist, Math.min(maxDist, offset.length() * (1 - (command.delta || 0))));
            offset.setLength(distance);
            state.camera.position.copy(target).add(offset);
          }
          controls.update();
        },
        resetView() {
          if (!initialThreePose || !state.camera || !controls) return Promise.resolve(false);
          resettingView = true;
          const fromCamera = state.camera.position.clone();
          const fromTarget = controls.target.clone();
          const startedAt = performance.now();
          return new Promise((resolve) => {
            const step = (now) => {
              const t = Math.min(1, (now - startedAt) / 360);
              const eased = 1 - Math.pow(1 - t, 3);
              state.camera.position.lerpVectors(fromCamera, initialThreePose.camera, eased);
              controls.target.lerpVectors(fromTarget, initialThreePose.target, eased);
              controls.update();
              if (t < 1) {
                requestAnimationFrame(step);
              } else {
                resettingView = false;
                resolve(true);
              }
            };
            requestAnimationFrame(step);
          });
        },
      },
      PIECE_CAMERA_PLACEMENT === 'overlay'
        // Author chose overlay placement: DOM <video> blended over the WebGL
        // canvas instead of the in-scene background quad.
        ? createDomCameraOverlayHooks(() => canvas, 'overlay')
        : createCameraBlendQuadHooks(mod, () => state.scene, () => state.camera)
    );

    // Per-piece Tone.js sonification: muted by default, unmuted via a
    // postMessage from the parent page's sound toggle (no master switch).
    // Camera-only pieces (no sonic block) still mount the controller so the
    // camera toggle's message bridge exists.
    if (PIECE_SONIC && state.camera) {
      pieceAudioController = createPieceRuntimeAudioController(PIECE_SONIC, () => state.camera);
    } else if (PIECE_CAMERA_OVERLAY || PIECE_HAND_CONTROL) {
      pieceAudioController = createPieceRuntimeAudioController(null, null);
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
  // SVG has no camera/pointer motion signal on this view — idle-only
  // random-note pattern when sound is unmuted. The camera overlay layers a
  // mirrored <video> over the <svg> root; capture compositing happens after
  // the downloader rasterizes the SVG to a canvas.
  if (PIECE_SONIC || PIECE_CAMERA_OVERLAY) {
    registerDomCameraOverlay(() => document.querySelector('svg'));
    pieceAudioController = createPieceRuntimeAudioController(PIECE_SONIC, null);
  }
  registerSpatialPresentation(() => document.querySelector('svg'), false);
}
