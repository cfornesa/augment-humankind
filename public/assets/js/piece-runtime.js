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
  if (!sonicParams || typeof sonicParams !== 'object') return null;
  if (getMover != null && typeof getMover !== 'function') return null;

  const pieceId = (pieceContext && pieceContext.pieceId != null) ? pieceContext.pieceId : null;
  let engine = null, disposed = false;
  let tiltController = null;

  // Tell the host page which voices are visible for this piece (the parent
  // has no direct access to sonicParams — it only relays postMessages) so
  // its popover can hide the keyboard/hand-tracking controls accordingly.
  (function notifyParentVoices() {
    const voices = (sonicParams.extras && sonicParams.extras.voices) || {};
    const ambientSample = (sonicParams.extras && sonicParams.extras.synth && sonicParams.extras.synth.ambient_sample) || {};
    try {
      if (window.parent && window.parent !== window) {
        window.parent.postMessage({
          type: 'creatr-sound-voices',
          voices: {
            ambient: voices.ambient !== false,
            movement: voices.movement !== false,
            melodic: voices.melodic !== false,
            hand_tracking: !!voices.hand_tracking,
          },
          ambientIsSample: !!(ambientSample.enabled && ambientSample.media_id),
          micSupported: !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia),
          // Engine-dependent camera capabilities (hooks are registered by
          // the active bootstrap before this controller is created).
          handControlSupported: !!(voices.hand_tracking && window.__pieceHandHooks && window.__pieceHandHooks.handPoint),
          cameraBgSupported: !!(voices.hand_tracking && window.__pieceHandHooks && window.__pieceHandHooks.setBackgroundVideo),
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
    return CSC.create(sonicParams, {
      getMover: getMover || undefined,
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
    if (disposed) return false;
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
    if (data.type === 'creatr-sound-camera-bg-toggle') {
      handleCameraBackgroundToggle(!!data.enabled);
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

  function handleHandControlToggle(on) {
    if (!on) {
      handControlBinding.detach();
      engine?.disableHandControl();
      notifyParentHandControlState(false);
      return;
    }
    const eng = ensureEngineSync();
    Promise.resolve(eng ? eng.enableHandControl() : false).then((controlOk) => {
      if (controlOk) handControlBinding.attach(engine);
      notifyParentHandControlState(controlOk);
    }).catch(() => notifyParentHandControlState(false));
  }

  async function handleTiltToggle(on) {
    if (!on) {
      tiltController?.disable();
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
    const ok = await tiltController.enable();
    notifyParentHandControlState(ok, ok ? 'device_tilt' : null);
    if (!ok) notifyParentCapabilityState({ capability: 'hand_control', state: 'unavailable', reason: 'Device motion permission denied' });
  }

  function handleCameraBackgroundToggle(on) {
    if (!on) {
      cameraBackgroundBinding.detach();
      engine?.releaseCameraFeed();
      notifyParentCameraBgState(false);
      return;
    }
    const eng = ensureEngineSync();
    Promise.resolve(eng ? eng.acquireCameraFeed() : Promise.reject(new Error('unavailable')))
      .then((video) => {
        const shown = cameraBackgroundBinding.attach(video);
        if (!shown) engine?.releaseCameraFeed();
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
        window.parent.postMessage({ type: 'creatr-sound-camera-bg-state', enabled: !!on }, '*');
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
    attach(eng) {
      if (this.active || !eng) return;
      this.active = true;
      eng.onHandFrame((hand) => {
        if (!this.active || !hand) return;
        const hooks = window.__pieceHandHooks;
        if (!hooks || typeof hooks.handPoint !== 'function') return;
        const wrist = hand[0];
        if (!wrist) return;
        try { hooks.handPoint(1 - wrist.x, wrist.y); } catch (_) {}
      });
    },
    detach() {
      if (!this.active) return;
      this.active = false;
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
    toggleHandControl: handleHandControlToggle,
    toggleTilt: handleTiltToggle,
    toggleCameraBackground: handleCameraBackgroundToggle,
  };

  return {
    dispose() {
      disposed = true;
      detachStandalonePianoKeys?.();
      handControlBinding.detach();
      cameraBackgroundBinding.detach();
      tiltController?.disable();
      document.removeEventListener('creatr-sonic-capability-state', onCapabilityState);
      engine?.dispose();
    },
  };
}
let pieceAudioController = null;
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
  if (PIECE_ENGINE === 'c2' && PIECE_SONIC) {
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
      // handlers and (via updateC2Mover) the movement voice. No camera
      // background here — the 2D canvas is opaque with no scene object.
      window.__pieceHandHooks = {
        engine: 'c2_interactive',
        handPoint(nx, ny) {
          const rect = canvas.getBoundingClientRect();
          if (!rect.width || !rect.height) return;
          const clientX = rect.left + nx * rect.width;
          const clientY = rect.top + ny * rect.height;
          updateC2Mover(clientX, clientY);
          try {
            canvas.dispatchEvent(new PointerEvent('pointermove', { clientX, clientY, bubbles: true }));
          } catch (_) {
            try {
              const ev = document.createEvent('MouseEvent');
              ev.initMouseEvent('mousemove', true, true, window, 0, clientX, clientY, clientX, clientY, false, false, false, false, 0, null);
              canvas.dispatchEvent(ev);
            } catch (_e) {}
          }
        },
      };
      pieceAudioController = createPieceRuntimeAudioController(PIECE_SONIC, () => c2Mover);
    } else {
      // Plain (non-interactive) c2 has no motion signal — idle-only pattern.
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
      if (PIECE_SONIC) {
        pieceAudioController = createPieceRuntimeAudioController(PIECE_SONIC, null);
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
        // Hooks first (capability handshake) — see the three bootstrap twin.
        window.__pieceHandHooks = {
          engine: 'aframe',
          handPoint(nx, ny) {
            const cameraObject = getAFrameCameraObject();
            if (!cameraObject) return;
            const desiredYaw = (0.5 - nx) * Math.PI * 1.5;
            const desiredPitch = (0.5 - ny) * Math.PI * 0.6;
            cameraObject.rotation.y += (desiredYaw - cameraObject.rotation.y) * 0.12;
            cameraObject.rotation.x += (desiredPitch - cameraObject.rotation.x) * 0.12;
          },
          _prevBackground: undefined,
          _videoTexture: null,
          setBackgroundVideo(video) {
            const THREE = window.AFRAME && window.AFRAME.THREE;
            const object3D = scene && scene.object3D;
            if (!THREE || !THREE.VideoTexture || !object3D) return false;
            this._prevBackground = object3D.background ?? null;
            this._videoTexture = new THREE.VideoTexture(video);
            if (THREE.SRGBColorSpace) this._videoTexture.colorSpace = THREE.SRGBColorSpace;
            object3D.background = this._videoTexture;
            return true;
          },
          clearBackgroundVideo() {
            const object3D = scene && scene.object3D;
            if (object3D) object3D.background = this._prevBackground ?? null;
            if (this._videoTexture) { try { this._videoTexture.dispose(); } catch (_) {} this._videoTexture = null; }
          },
        };
        if (PIECE_SONIC) {
          pieceAudioController = createPieceRuntimeAudioController(PIECE_SONIC, () => getAFrameCameraMover());
        }
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

    // Interaction/camera hooks for the shared hand-tracking pipeline —
    // registered BEFORE the audio controller so its capability handshake
    // (notifyParentVoices) can advertise them to the host page.
    window.__pieceHandHooks = {
      engine: 'three',
      // Wrist position (mirrored x, raw y in 0..1) steers the orbit like a
      // continuous drag: desired spherical angles around the current orbit
      // target, eased per frame so tracking jitter doesn't jolt the camera.
      handPoint(nx, ny) {
        if (!controls || !state.camera) return;
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
      _prevBackground: undefined,
      _videoTexture: null,
      setBackgroundVideo(video) {
        if (!state.scene || !mod.VideoTexture) return false;
        this._prevBackground = state.scene.background ?? null;
        this._videoTexture = new mod.VideoTexture(video);
        if (mod.SRGBColorSpace) this._videoTexture.colorSpace = mod.SRGBColorSpace;
        state.scene.background = this._videoTexture;
        return true;
      },
      clearBackgroundVideo() {
        if (state.scene) state.scene.background = this._prevBackground ?? null;
        if (this._videoTexture) { try { this._videoTexture.dispose(); } catch (_) {} this._videoTexture = null; }
      },
    };

    // Per-piece Tone.js sonification: muted by default, unmuted via a
    // postMessage from the parent page's sound toggle (no master switch).
    if (PIECE_SONIC && state.camera) {
      pieceAudioController = createPieceRuntimeAudioController(PIECE_SONIC, () => state.camera);
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
  // random-note pattern when sound is unmuted.
  if (PIECE_SONIC) {
    pieceAudioController = createPieceRuntimeAudioController(PIECE_SONIC, null);
  }
}
