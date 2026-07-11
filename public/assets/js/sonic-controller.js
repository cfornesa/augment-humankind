// Shared movement/keyboard sonification engine (Tone.js-backed), used by
// immersive-gallery.js (parent-page immersive views), piece-runtime.js (the
// sandboxed per-piece iframe), and piece-render.php's standalone/ZIP export
// bootstrap. Classic script (no ES import/export) so every one of those three
// contexts — including sandboxed srcdoc iframes and file:// exports — can
// load it the same way Tone.js itself is already loaded.
//
// This module owns only the sonification ENGINE (synth construction, scale
// walk, motion/idle note-triggering, volume, keyboard-mode note triggering).
// Each caller keeps its own enable/mute wiring (direct click listener,
// postMessage relay, or a self-owned button) because those differ
// meaningfully per context — see opts.getMover below.
(function (global) {
  'use strict';

  var SONIC_SCALES = {
    major: [0, 2, 4, 5, 7, 9, 11], minor: [0, 2, 3, 5, 7, 8, 10],
    pentatonic: [0, 2, 4, 7, 9], chromatic: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
    dorian: [0, 2, 3, 5, 7, 9, 10], phrygian: [0, 1, 3, 5, 7, 8, 10],
    lydian: [0, 2, 4, 6, 7, 9, 11], mixolydian: [0, 2, 4, 5, 7, 9, 10],
    wholetone: [0, 2, 4, 6, 8, 10],
  };
  var SONIC_INSTRUMENTS = {
    synth: 'Synth', amsynth: 'AMSynth', fmsynth: 'FMSynth',
    membranesynth: 'MembraneSynth', metalsynth: 'MetalSynth',
    plucksynth: 'PluckSynth', duosynth: 'DuoSynth',
  };

  var sonicDebugEnabled = false;
  try { sonicDebugEnabled = new URLSearchParams(global.location && global.location.search || '').get('sonicdebug') === '1'; } catch (_e) {}
  function sonicDebug(stage, detail) {
    if (!sonicDebugEnabled) return;
    var text = stage + (detail ? ': ' + detail : '');
    try { console.info('[sonicdebug]', text); } catch (_e) {}
    try {
      var panel = document.getElementById('creatr-sonic-debug');
      if (!panel) {
        panel = document.createElement('pre');
        panel.id = 'creatr-sonic-debug';
        panel.setAttribute('aria-live', 'polite');
        panel.style.cssText = 'position:fixed;left:0.5rem;right:0.5rem;bottom:0.5rem;z-index:2147483647;max-height:38vh;overflow:auto;margin:0;padding:0.65rem;border:1px solid #67e8f9;border-radius:0.5rem;background:rgba(3,7,18,.94);color:#cffafe;font:11px/1.4 ui-monospace,monospace;white-space:pre-wrap;pointer-events:none;';
        (document.body || document.documentElement).appendChild(panel);
      }
      panel.textContent += (panel.textContent ? '\n' : '') + new Date().toISOString().slice(11, 23) + ' ' + text;
    } catch (_e) {}
  }
  function capabilityState(capability, state, reason, fallback) {
    sonicDebug(capability + ' ' + state, reason || '');
    try {
      document.dispatchEvent(new CustomEvent('creatr-sonic-capability-state', {
        detail: { capability: capability, state: state, reason: reason || null, fallback: fallback || null }
      }));
    } catch (_e) {}
  }

  function createDeviceTiltController(onPoint) {
    var active = false;
    function onOrientation(event) {
      if (!active || typeof onPoint !== 'function') return;
      var gamma = Number(event.gamma);
      var beta = Number(event.beta);
      if (!isFinite(gamma) || !isFinite(beta)) return;
      var nx = Math.max(0, Math.min(1, 0.5 + gamma / 90));
      var ny = Math.max(0, Math.min(1, 0.5 + (beta - 45) / 90));
      try { onPoint(nx, ny); } catch (_e) {}
    }
    return {
      async enable() {
        if (active) return true;
        if (typeof global.DeviceOrientationEvent === 'undefined') return false;
        try {
          if (typeof global.DeviceOrientationEvent.requestPermission === 'function') {
            var permission = await global.DeviceOrientationEvent.requestPermission();
            if (permission !== 'granted') return false;
          }
          active = true;
          global.addEventListener('deviceorientation', onOrientation);
          capabilityState('hand_control', 'fallback', null, 'device_tilt');
          return true;
        } catch (_e) { return false; }
      },
      disable() {
        if (!active) return;
        active = false;
        global.removeEventListener('deviceorientation', onOrientation);
      },
      isActive: function () { return active; },
    };
  }

  function midiToFreq(m) { return 440 * Math.pow(2, (m - 69) / 12); }

  // Maps a 0-100 volume percent to a Tone.js dB value. 50 reproduces the
  // previously-hardcoded -6dB default so existing pieces don't change
  // loudness until a user actually touches the new slider.
  function percentToDb(percent) {
    var p = Math.max(0, Math.min(100, Number(percent)));
    if (p <= 0) return -Infinity;
    return p <= 50 ? (-40 + (p / 50) * 34) : (-6 + ((p - 50) / 50) * 12);
  }

  var _toneLoadPromise = null;
  function loadToneOnce(toneSrc) {
    if (global.Tone) return Promise.resolve(global.Tone);
    if (_toneLoadPromise) return _toneLoadPromise;
    _toneLoadPromise = new Promise(function (resolve, reject) {
      var s = document.createElement('script');
      s.src = toneSrc || '/assets/vendor/tone/Tone.js';
      s.onload = function () { global.Tone ? resolve(global.Tone) : reject(new Error('Tone.js loaded but window.Tone missing')); };
      s.onerror = function () {
        console.warn("Local Tone.js failed to load. Attempting CDN fallback...");
        var fallbackScript = document.createElement('script');
        fallbackScript.src = 'https://cdnjs.cloudflare.com/ajax/libs/tone/14.8.49/Tone.js';
        fallbackScript.onload = function () {
          if (global.Tone) {
            resolve(global.Tone);
          } else {
            reject(new Error('CDN Tone.js loaded but window.Tone missing'));
          }
        };
        fallbackScript.onerror = function () {
          reject(new Error('Tone.js failed to load from both local and CDN sources'));
        };
        document.head.appendChild(fallbackScript);
      };
      document.head.appendChild(s);
    });
    return _toneLoadPromise;
  }

  // Plucked-string approximation, standing in for Tone.PluckSynth (which
  // internally builds an AudioWorkletProcessor that browsers refuse to load
  // under file:// — a downloaded piece opened by double-clicking index.html
  // — so it never sounds under that context). An earlier version of this
  // function hand-built a true Karplus-Strong feedback loop (Noise -> Delay
  // -> Filter -> Gain, feeding back into the Delay) using only native Web
  // Audio nodes. That turned out to be fundamentally unreliable: a native
  // DelayNode+BiquadFilterNode feedback loop can go numerically unstable
  // under sustained, frequently-retriggered use ("BiquadFilterNode: state
  // is bad" in the console), and once that happens the node is permanently
  // silenced until torn down — which is exactly why real Tone.PluckSynth
  // needed a dedicated AudioWorklet in the first place rather than native
  // feedback nodes. Three separate mitigation attempts (frequency clamping,
  // lower feedback gain, muting the loop around retunes) all still hit the
  // same failure mode after continued use.
  //
  // This version has NO feedback loop at all, so that entire failure class
  // is structurally impossible: Tone.MonoSynth (oscillator -> filter, with
  // separate amplitude and filter envelopes) already ships in the vendored
  // Tone.js build and is the standard, well-tested way to fake a plucked/
  // percussive twang — bright attack, filter sweeping dark as the note
  // decays. It's a close approximation, not literal physical modeling, but
  // it can never diverge the way a feedback-based DSP loop can.
  function createPluckVoice(Tone) {
    return new Tone.MonoSynth({
      oscillator: { type: 'triangle' },
      envelope: { attack: 0.001, decay: 0.25, sustain: 0, release: 0.08 },
      filterEnvelope: {
        attack: 0.001, decay: 0.2, sustain: 0, release: 0.08,
        baseFrequency: 200, octaves: 5, exponent: 2,
      },
      filter: { type: 'lowpass', rolloff: -24, Q: 1 },
    });
  }

  // Builds one voice instance for the given Tone.js ctor name. PluckSynth is
  // special-cased to the hand-built Karplus-Strong voice above; every other
  // instrument type is built exactly as before via the real Tone.js class.
  function buildVoice(Tone, ctorName) {
    if (ctorName === 'PluckSynth') return createPluckVoice(Tone);
    var Ctor = Tone[ctorName] || Tone.Synth;
    return new Ctor();
  }

  // Hand-built flanger — Tone.js has no named Flanger class, but ships every
  // primitive the classic algorithm needs: an LFO modulating a short Delay's
  // delayTime, mixed wet+dry, with a feedback path for a stronger effect.
  // Returns a real Tone.Gain (`input`) so upstream .connect(node) calls work
  // via Tone's normal node-connection machinery; input.connect/dispose are
  // then overridden (after all internal wiring is done) so calls made ON
  // this node route to the wet/dry-summed output instead of the bare input.
  function createFlangerNode(Tone, cfg) {
    var input = new Tone.Gain(1);
    var delay = new Tone.Delay(cfg.depth);
    var lfo = new Tone.LFO(cfg.rate, 0, cfg.depth).start();
    var feedback = new Tone.Gain(cfg.feedback);
    var output = new Tone.Gain(1);
    lfo.connect(delay.delayTime);
    input.connect(delay);
    input.connect(output);
    delay.connect(output);
    delay.connect(feedback);
    feedback.connect(delay);
    input.connect = function (dest) { output.connect(dest); return input; };
    input.dispose = function () {
      try { lfo.dispose(); } catch (_e) {}
      try { delay.dispose(); } catch (_e) {}
      try { feedback.dispose(); } catch (_e) {}
      try { output.dispose(); } catch (_e) {}
      try { Tone.Gain.prototype.dispose.call(input); } catch (_e) {}
    };
    return input;
  }

  // Hand-built ring modulator — a carrier oscillator drives a Gain node's
  // own gain AudioParam, amplitude-modulating whatever's connected into it.
  // A single real Tone.Gain both receives input and produces output, so no
  // connect()/dispose() override is needed (unlike the flanger above).
  function createRingModNode(Tone, cfg) {
    var carrier = new Tone.Oscillator(cfg.frequency, 'sine').start();
    var ringGain = new Tone.Gain(0);
    carrier.connect(ringGain.gain);
    var disposeGain = ringGain.dispose.bind(ringGain);
    ringGain.dispose = function () {
      try { carrier.dispose(); } catch (_e) {}
      disposeGain();
    };
    return ringGain;
  }

  // Hand-built, worklet-free bitcrusher — Tone.js's native BitCrusher uses an
  // AudioWorkletNode that fails to load under the file:// protocol due to
  // browser security policies regarding Blob URLs/Workers. Instead, we use a
  // native WaveShaperNode mapped to a step curve (discretizing amplitude to
  // bits/quantization steps), which is 100% worklet-free and runs everywhere.
  function createBitCrusherNode(Tone, cfg) {
    var bits = cfg.bits !== undefined ? cfg.bits : 4;
    var steps = Math.pow(2, bits);
    var bufferSize = 4096;
    var curve = new Float32Array(bufferSize);
    for (var i = 0; i < bufferSize; i++) {
      var x = (i * 2) / (bufferSize - 1) - 1;
      curve[i] = Math.round(x * (steps / 2)) / (steps / 2);
    }
    return new Tone.WaveShaper(curve);
  }

  // Shared effect-node factory — used both for the admin-authored chain
  // (ensureSynth()'s maybeAddEffect) and the visitor-facing live mic effects
  // (enableMic()/setMicEffect()), so the two never drift into two different
  // implementations of the same seven effects.
  function createEffectNode(Tone, key, cfg) {
    switch (key) {
      case 'distortion': {
        var amt = typeof cfg.amount === 'number' ? cfg.amount : (cfg.amount !== undefined ? parseFloat(cfg.amount) : 0.4);
        return new Tone.Distortion({ distortion: isNaN(amt) ? 0.4 : amt });
      }
      case 'chorus': { var c = new Tone.Chorus(cfg.rate, 2.5, cfg.depth); c.start(); return c; }
      case 'tremolo': { var t = new Tone.Tremolo(cfg.rate, cfg.depth); t.start(); return t; }
      case 'pitch_shift': return new Tone.PitchShift(cfg.semitones);
      case 'bitcrusher': return createBitCrusherNode(Tone, cfg);
      case 'flanger': return createFlangerNode(Tone, cfg);
      case 'ring_mod': return createRingModNode(Tone, cfg);
      default: return null;
    }
  }

  // Sensible defaults for each live mic effect, matching
  // validate_art_piece_sonic_extras()'s admin-chain defaults, used when a
  // visitor flips an effect on via a plain on/off toggle with no exposed
  // parameter controls.
  var MIC_EFFECT_DEFAULTS = {
    distortion: { amount: 0.4 },
    chorus: { depth: 0.5, rate: 1.5 },
    tremolo: { depth: 0.5, rate: 5 },
    pitch_shift: { semitones: 0 },
    bitcrusher: { bits: 4 },
    flanger: { depth: 0.006, rate: 0.25, feedback: 0.5 },
    ring_mod: { frequency: 440 },
  };

  /**
   * create(sonicParams, opts)
   *
   * opts.getMover()   optional fn returning {position:{x,y,z}}. When present,
   *                   the engine drives its own requestAnimationFrame loop,
   *                   polling position deltas itself (piece-runtime.js's
   *                   iframe style — no host page to call update() for it).
   *                   When absent, the caller must call update(motionVector)
   *                   once per frame with a precomputed {dx,dy,dz} (the
   *                   immersive-gallery.js style, which already knows the
   *                   camera delta from its own render loop) — an idle
   *                   timer still runs internally either way.
   * opts.toneSrc      string override for the Tone.js <script> src.
   * opts.defaultVolume 0-100, default 50 (~ legacy -6dB).
   *
   * Three concurrent Tone.js voices (all built from the same sonicParams
   * instrument/scale/tempo — no schema fork), mixed through one shared
   * Tone.Volume bus so the single volume slider controls all of them
   * together:
   *   - ambient: a steady scale-walk ticker, paced only by tempo. Always on
   *     while enabled, runs continuously regardless of movement — it never
   *     stops or waits for stillness.
   *   - movement: motion-triggered notes (getMover deltas or externally
   *     supplied update()). Always on while enabled, runs independently of
   *     the ambient voice — the two layer on top of each other and never
   *     suppress one another, since each has its own monophonic synth.
   *   - melodic: driven directly by triggerNote() (keyboard buttons, and
   *     eventually hand-tracking) — never touched by the idle/motion timers.
   * setInputMode() no longer mutes anything; it's a UI-facing "what's the
   * current control source" flag for callers to key their popover UI off of.
   *
   * Returns null when sonicParams is missing/invalid — callers no-op safely.
   */
  function create(sonicParams, opts) {
    opts = opts || {};
    if (!sonicParams || typeof sonicParams !== 'object') return null;
    if (opts.getMover != null && typeof opts.getMover !== 'function') return null;

    var scaleName = SONIC_SCALES[sonicParams.scale] ? sonicParams.scale : 'major';
    var scale = SONIC_SCALES[scaleName];
    var instrumentKey = SONIC_INSTRUMENTS[sonicParams.instrument] ? sonicParams.instrument : 'synth';
    var instrumentCtorName = SONIC_INSTRUMENTS[instrumentKey];
    var tempo = Math.max(40, Math.min(220, Number(sonicParams.tempo) || 90));
    var minInterval = ((60 / tempo) * 1000) / 2; // ~eighth-note spacing
    var baseMidi = 48; // C3
    var drivesSelf = typeof opts.getMover === 'function';

    // Mechanical, non-AI-authored settings (public voice-visibility toggles +
    // admin-only synth tuning) nested under sonicParams.extras — see
    // validate_art_piece_sonic_extras() in art-piece-generation.php. Falls
    // back to "everything on, default tuning" for pieces saved before this
    // existed, so nothing regresses.
    var extras = (sonicParams.extras && typeof sonicParams.extras === 'object') ? sonicParams.extras : {};
    var voices = (extras.voices && typeof extras.voices === 'object') ? extras.voices : {};
    var voiceAmbient = voices.ambient !== false;
    var voiceMovement = voices.movement !== false;
    var synthExtras = (extras.synth && typeof extras.synth === 'object') ? extras.synth : {};
    var octaveMin = Number.isFinite(synthExtras.octave_min) ? synthExtras.octave_min : 1;
    var octaveMax = Number.isFinite(synthExtras.octave_max) ? synthExtras.octave_max : 5;
    var filterCutoff = Number.isFinite(synthExtras.filter_cutoff) ? synthExtras.filter_cutoff : 8000;
    var filterResonance = Number.isFinite(synthExtras.filter_resonance) ? synthExtras.filter_resonance : 1;
    var filterType = ['lowpass', 'highpass', 'bandpass'].indexOf(synthExtras.filter_type) >= 0 ? synthExtras.filter_type : 'lowpass';
    // Admin-only effects chain (Audio tab) — see
    // validate_art_piece_sonic_extras() in art-piece-generation.php for the
    // validated/clamped shape. Absent/malformed entries default to off.
    var effectsExtras = (synthExtras.effects && typeof synthExtras.effects === 'object') ? synthExtras.effects : {};
    // Admin-selected uploaded audio file looped as the ambient voice instead
    // of a synthesized instrument — see validate_art_piece_sonic_extras().
    // Movement/melodic voices are always synths regardless of this.
    var ambientSampleExtras = (synthExtras.ambient_sample && typeof synthExtras.ambient_sample === 'object') ? synthExtras.ambient_sample : {};
    var ambientIsSample = !!(ambientSampleExtras.enabled && ambientSampleExtras.media_id);

    var enabled = false, disposed = false;
    var bus = null, filter = null, ambientSynth = null, movementSynth = null, melodicSynth = null;
    var effectNodes = [];
    var handStream = null, handLandmarker = null, handVideoEl = null, handRafId = null, handNoteHeld = false;
    // The camera is a shared resource: the theremin, the hand-control
    // subscriber, and the camera-feed consumer each hold a reference; the
    // stream/video pair is opened once and torn down when the last holder
    // releases. handThereminOn gates the theremin mapping inside
    // handFrameStep(); handControlOn keeps the landmark loop alive for the
    // onHandFrame subscriber even with the theremin off.
    var handCameraRefs = 0, handThereminOn = false, handControlOn = false;
    var handFrameSubscriber = null;
    var lastIdleNoteAt = 0, lastMotionNoteAt = 0, walk = 0;
    var prevX = null, prevY = null, prevZ = null;
    var volumePercent = Math.max(0, Math.min(100, Number(opts.defaultVolume)) || 50);
    var inputMode = 'motion'; // 'motion' | 'keyboard' | 'hand' — UI flag only, gates nothing audio-side
    var currentOctave = Math.max(octaveMin, Math.min(octaveMax, 3)); // 3 matches the legacy baseMidi=48 (C3) default
    var rafId = null, idleTimer = null;
    // Visitor-chosen per-voice instrument overrides — session-local only (the
    // caller is responsible for persisting/restoring these, e.g. localStorage;
    // this engine never touches storage or the piece's authored sonicParams).
    // Missing keys mean "use the piece's authored instrumentKey".
    var voiceInstrumentOverrides = {};
    // Live human-voice input (mic) — a fourth layer, purely visitor-facing,
    // mixed on top of ambient/movement/melodic (connects straight to `bus`,
    // not through the synth-tuned `filter`). Off by default; never touches
    // sonicParams/the DB, and this engine holds no localStorage for it —
    // callers are expected to never restore an "on" state across page loads.
    var micNode = null, micEffectsState = {}, micEffectNodes = [];

    function applyVolume() {
      if (bus) bus.volume.value = percentToDb(volumePercent);
    }

    function playOn(voiceSynth, degree, octaveOffset) {
      if (!enabled || !voiceSynth || disposed) return;
      var idx = ((degree % scale.length) + scale.length) % scale.length;
      var midi = baseMidi + scale[idx] + 12 * (octaveOffset || 0);
      try { voiceSynth.triggerAttackRelease(midiToFreq(midi), '16n'); } catch (_e) {}
    }

    // Idle voice: plays a plain scale-walk pattern on its own steady
    // cadence, independent of motion — it never stops or waits for
    // stillness, so it keeps sounding underneath movement, keyboard, and
    // hand play alike. Skipped entirely when the admin has hidden the
    // ambient voice for this piece. When ambientIsSample is true, the
    // "ambient voice" is a looping Tone.Player instead of a note-triggered
    // synth (started once in ensureLoopStarted()/enable(), not here) — a
    // player doesn't take frequency/duration the way triggerAttackRelease
    // does, so this function has nothing to do in that mode.
    function ambientStep(now) {
      if (!voiceAmbient || ambientIsSample) return;
      if (now - lastIdleNoteAt >= minInterval) {
        lastIdleNoteAt = now;
        playOn(ambientSynth, walk++, 0);
      }
    }

    // Movement voice: motion-triggered notes from either the self-driven
    // getMover rAF loop or an externally-supplied update(motion) call. Always
    // active while enabled — never gated by inputMode. Skipped entirely when
    // the admin has hidden the movement voice for this piece.
    function movementStep(now) {
      if (!voiceMovement) return;
      if (!drivesSelf) return; // external-drive style feeds this via update() instead
      var mover = opts.getMover();
      if (!mover || !mover.position) return;
      var x = mover.position.x, y = mover.position.y, z = mover.position.z;
      if (prevX !== null) {
        var dx = x - prevX, dy = y - prevY, dz = z - prevZ;
        var speed = Math.hypot(dx, dy, dz);
        if (speed >= 0.002) {
          if (now - lastMotionNoteAt >= minInterval) {
            lastMotionNoteAt = now;
            var octave = Math.min(2, Math.floor(Math.abs(dy) * 25));
            playOn(movementSynth, walk++, octave);
          }
        }
      }
      prevX = x; prevY = y; prevZ = z;
    }

    function autoStep(now) {
      movementStep(now);
      ambientStep(now);
    }

    function rafLoop() {
      rafId = requestAnimationFrame(rafLoop);
      if (!enabled || !ambientSynth || disposed) return;
      autoStep(performance.now());
    }

    function idleLoop() {
      if (disposed) { idleTimer = null; return; }
      idleTimer = setTimeout(idleLoop, minInterval);
      if (!enabled || !ambientSynth) return;
      autoStep(performance.now());
    }

    function ensureLoopStarted() {
      if (drivesSelf) { if (!rafId) rafLoop(); }
      else { if (!idleTimer) idleLoop(); }
    }

    // --- Hand-tracking (camera theremin), via MediaPipe Tasks-Vision's -----
    // HandLandmarker, self-hosted under public/assets/vendor/mediapipe-hands/.
    // Wrist vertical position -> pitch (glides continuously, real theremin
    // feel, not discrete note triggers), wrist-to-middle-fingertip spread ->
    // volume of the melodic voice specifically (not the shared master bus),
    // so hand-tracking layers over the ambient/movement voices exactly like
    // keyboard mode does.
    var _handLandmarkerPromise = null, handLoaderRetryCount = 0, lastHandTimestamp = -1;
    function loadHandLandmarkerOnce(forceRetry) {
      if (forceRetry) _handLandmarkerPromise = null;
      if (_handLandmarkerPromise) return _handLandmarkerPromise;
      _handLandmarkerPromise = (async function () {
        // Version the default URLs with this script's own ?v= (set from file
        // mtime by piece-render.php). This is not cosmetic cache-busting:
        // hosts that once served .mjs as text/plain left browsers with a
        // poisoned cache entry whose Content-Type survives every 304
        // revalidation, permanently failing module import — only a changed
        // URL recovers. Callers that pass explicit opts version their own.
        var assetVersion = '';
        try {
          var scriptTags = document.getElementsByTagName('script');
          for (var si = scriptTags.length - 1; si >= 0; si--) {
            var versionMatch = /sonic-controller\.js\?v=([^&#]+)/.exec(scriptTags[si].src || '');
            if (versionMatch) { assetVersion = '?v=' + versionMatch[1]; break; }
          }
        } catch (_e) {}
        var visionSrc = opts.mediaPipeVisionSrc || ('/assets/vendor/mediapipe-hands/vision_bundle.mjs' + assetVersion);
        var wasmDir = opts.mediaPipeWasmDir || '/assets/vendor/mediapipe-hands/';
        var modelSrc = opts.mediaPipeModelSrc || ('/assets/vendor/mediapipe-hands/hand_landmarker.task' + assetVersion);

        try {
          sonicDebug('hand model local start', visionSrc);
          var vision = await import(visionSrc);
          var fileset = await vision.FilesetResolver.forVisionTasks(wasmDir);
          var localLandmarker = await vision.HandLandmarker.createFromOptions(fileset, {
            baseOptions: { modelAssetPath: modelSrc },
            runningMode: 'VIDEO',
            numHands: 1,
          });
          sonicDebug('hand model local ready');
          return localLandmarker;
        } catch (localError) {
          sonicDebug('hand model local failed', localError.message || String(localError));
          console.warn("Local MediaPipe assets failed to load. Attempting CDN fallback...", localError);
          try {
            var cdnVisionSrc = 'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.8/vision_bundle.mjs';
            var cdnWasmDir = 'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.8/wasm/';
            var cdnModelSrc = 'https://storage.googleapis.com/mediapipe-models/hand_landmarker/hand_landmarker/float16/1/hand_landmarker.task';

            var vision = await import(cdnVisionSrc);
            var fileset = await vision.FilesetResolver.forVisionTasks(cdnWasmDir);
            var cdnLandmarker = await vision.HandLandmarker.createFromOptions(fileset, {
              baseOptions: { modelAssetPath: cdnModelSrc },
              runningMode: 'VIDEO',
              numHands: 1,
            });
            sonicDebug('hand model CDN ready');
            return cdnLandmarker;
          } catch (cdnError) {
            console.error("CDN fallback also failed.", cdnError);
            document.dispatchEvent(new CustomEvent('creatr-hand-tracking-failed', {
              detail: { localError: localError.message, cdnError: cdnError.message }
            }));
            throw cdnError;
          }
        }
      })().catch(function (error) {
        _handLandmarkerPromise = null;
        throw error;
      });
      return _handLandmarkerPromise;
    }

    async function loadHandLandmarkerWithRetry() {
      try { return await loadHandLandmarkerOnce(false); }
      catch (firstError) {
        if (handLoaderRetryCount >= 1) throw firstError;
        handLoaderRetryCount += 1;
        sonicDebug('hand model retry', firstError.message || String(firstError));
        return loadHandLandmarkerOnce(true);
      }
    }

    // Opens (or re-references) the single shared camera stream + hidden
    // <video>. MUST be the FIRST await in any user-gesture-initiated enable
    // path: WebKit's transient-activation window is short, so getUserMedia
    // has to run before Tone.js or the MediaPipe model are loaded, not
    // after. The video element is appended to the DOM (hidden) — WebKit
    // decodes detached video elements unreliably.
    async function acquireHandCamera() {
      if (handVideoEl && handStream) { handCameraRefs += 1; return handVideoEl; }
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        throw new Error('navigator.mediaDevices.getUserMedia not supported or blocked');
      }
      var stream = await navigator.mediaDevices.getUserMedia({ video: true });
      sonicDebug('camera acquired', stream.getVideoTracks().map(function (t) { return t.readyState; }).join(','));
      if (disposed) { stream.getTracks().forEach(function (t) { t.stop(); }); throw new Error('disposed'); }
      handStream = stream;
      handVideoEl = document.createElement('video');
      handVideoEl.muted = true;
      handVideoEl.playsInline = true;
      handVideoEl.setAttribute('playsinline', '');
      handVideoEl.setAttribute('muted', '');
      handVideoEl.style.cssText = 'position:fixed;width:1px;height:1px;opacity:0;pointer-events:none;left:-10px;top:-10px;';
      handVideoEl.srcObject = handStream;
      (document.body || document.documentElement).appendChild(handVideoEl);
      await handVideoEl.play();
      sonicDebug('camera video playing', handVideoEl.videoWidth + 'x' + handVideoEl.videoHeight + ' ready=' + handVideoEl.readyState);
      handCameraRefs = 1;
      return handVideoEl;
    }

    function releaseHandCamera() {
      handCameraRefs = Math.max(0, handCameraRefs - 1);
      if (handCameraRefs > 0) return;
      if (handStream) { try { handStream.getTracks().forEach(function (t) { t.stop(); }); } catch (_e) {} handStream = null; }
      if (handVideoEl) { try { handVideoEl.remove(); } catch (_e) {} handVideoEl = null; }
    }

    function stopHandLoopIfIdle() {
      if (handThereminOn || handControlOn) return;
      if (handRafId) { cancelAnimationFrame(handRafId); handRafId = null; }
    }

    var handInferenceMode = 'video', handInferenceCanvas = null, handInferenceContext = null, handInferenceFrame = 0;
    function failHandInference(error) {
      var message = error && (error.message || String(error)) || 'Hand inference failed';
      if (handThereminOn) capabilityState('hand_tracking', 'unavailable', message);
      if (handControlOn) capabilityState('hand_control', 'unavailable', message, 'device_tilt');
      if (handNoteHeld) { try { melodicSynth && melodicSynth.triggerRelease(); } catch (_e) {} handNoteHeld = false; }
      var refsToRelease = (handThereminOn ? 1 : 0) + (handControlOn ? 1 : 0);
      handThereminOn = false;
      handControlOn = false;
      if (typeof handFrameSubscriber === 'function') { try { handFrameSubscriber(null); } catch (_e) {} }
      while (refsToRelease-- > 0) releaseHandCamera();
      stopHandLoopIfIdle();
    }

    function detectHandFrame(timestamp) {
      if (handInferenceMode === 'canvas') {
        handInferenceFrame += 1;
        if (handInferenceFrame % 3 !== 0) return undefined;
        if (!handInferenceCanvas) {
          handInferenceCanvas = document.createElement('canvas');
          handInferenceCanvas.width = 256;
          handInferenceCanvas.height = 256;
          handInferenceContext = handInferenceCanvas.getContext('2d', { alpha: false });
        }
        if (!handInferenceContext) throw new Error('Canvas 2D context unavailable for hand fallback');
        handInferenceContext.drawImage(handVideoEl, 0, 0, 256, 256);
        return handLandmarker.detectForVideo(handInferenceCanvas, timestamp);
      }
      return handLandmarker.detectForVideo(handVideoEl, timestamp);
    }

    function handFrameStep() {
      handRafId = requestAnimationFrame(handFrameStep);
      if (!handLandmarker || !handVideoEl || handVideoEl.readyState < 2) return;
      if (handVideoEl.videoWidth === 0 || handVideoEl.videoHeight === 0) return;
      
      var timestamp = performance.now();
      if (timestamp <= lastHandTimestamp) {
        timestamp = lastHandTimestamp + 1; // ensure strict monotonicity
      }
      lastHandTimestamp = timestamp;

      var result;
      try { result = detectHandFrame(timestamp); }
      catch (error) {
        if (handInferenceMode === 'video') {
          handInferenceMode = 'canvas';
          capabilityState('hand_tracking', 'loading', error.message || String(error), 'canvas');
          return;
        }
        failHandInference(error);
        return;
      }
      if (result === undefined) return;
      var hand = result && result.landmarks && result.landmarks[0];
      // Hand-control subscriber (piece interaction) sees every frame,
      // including hand-lost frames (null), independent of the theremin.
      if (handControlOn && typeof handFrameSubscriber === 'function') {
        try { handFrameSubscriber(hand || null); } catch (_e) {}
      }
      if (!hand || !handThereminOn || !enabled || !melodicSynth) {
        if (handNoteHeld) { try { melodicSynth.triggerRelease(); } catch (_e) {} handNoteHeld = false; }
        return;
      }
      var wrist = hand[0], midTip = hand[12];
      // y is 0 (top of frame) to 1 (bottom) — invert so raising the hand
      // raises pitch, matching a physical theremin's vertical antenna.
      var semitoneRange = (octaveMax - octaveMin + 1) * 12;
      var midi = 12 * (octaveMin + 1) + Math.max(0, Math.min(semitoneRange, (1 - wrist.y) * semitoneRange));
      var spread = Math.hypot(midTip.x - wrist.x, midTip.y - wrist.y);
      var volumeDb = -30 + Math.max(0, Math.min(1, (spread - 0.05) / 0.3)) * 30;
      try {
        if (melodicSynth.volume) melodicSynth.volume.value = volumeDb;
        if (!handNoteHeld) {
          melodicSynth.triggerAttack(midiToFreq(midi));
          handNoteHeld = true;
        } else if (melodicSynth.frequency && melodicSynth.frequency.rampTo) {
          melodicSynth.frequency.rampTo(midiToFreq(midi), 0.08);
        } else if (melodicSynth.setNote) {
          melodicSynth.setNote(midiToFreq(midi));
        }
      } catch (_e) {}
    }

    async function enableHandTracking() {
      if (disposed || !voices.hand_tracking) return false;
      if (handThereminOn) return true;
      var cameraHeld = false;
      try {
        capabilityState('hand_tracking', 'loading');
        // Camera FIRST — synchronously reachable from the invoking gesture
        // task, before the (slow) Tone.js and MediaPipe model loads consume
        // WebKit's transient-activation window.
        await acquireHandCamera();
        cameraHeld = true;
        if (disposed) throw new Error('disposed');
        await ensureSynth();
        if (disposed) throw new Error('disposed');
        handLandmarker = await loadHandLandmarkerWithRetry();
        if (disposed) throw new Error('disposed');
        handThereminOn = true;
        if (!handRafId) handFrameStep();
        capabilityState('hand_tracking', 'active');
        return true;
      } catch (_e) {
        if (cameraHeld) releaseHandCamera();
        handThereminOn = false;
        stopHandLoopIfIdle();
        document.dispatchEvent(new CustomEvent('creatr-hand-tracking-failed', {
          detail: { error: _e.message || String(_e) }
        }));
        capabilityState('hand_tracking', 'unavailable', _e.message || String(_e));
        return false;
      }
    }

    function disableHandTracking() {
      if (handNoteHeld) { try { melodicSynth && melodicSynth.triggerRelease && melodicSynth.triggerRelease(); } catch (_e) {} handNoteHeld = false; }
      if (melodicSynth && melodicSynth.volume) { try { melodicSynth.volume.value = 0; } catch (_e) {} }
      if (handThereminOn) { handThereminOn = false; releaseHandCamera(); }
      stopHandLoopIfIdle();
    }

    // Hand-control: keeps the shared camera + landmark loop running and
    // publishes each frame's landmarks (or null when no hand is visible) to
    // the onHandFrame subscriber, so a host surface can drive piece
    // interaction (orbit / pointer) from the same single camera pipeline
    // the theremin uses. Needs no audio — ensureSynth() is not called.
    async function enableHandControl() {
      if (disposed || !voices.hand_tracking) return false;
      if (handControlOn) return true;
      var cameraHeld = false;
      try {
        capabilityState('hand_control', 'loading');
        await acquireHandCamera();       // camera FIRST — same gesture rule
        cameraHeld = true;
        if (disposed) throw new Error('disposed');
        handLandmarker = await loadHandLandmarkerWithRetry();
        if (disposed) throw new Error('disposed');
        handControlOn = true;
        if (!handRafId) handFrameStep();
        capabilityState('hand_control', 'active');
        return true;
      } catch (_e) {
        if (cameraHeld) releaseHandCamera();
        handControlOn = false;
        stopHandLoopIfIdle();
        document.dispatchEvent(new CustomEvent('creatr-hand-tracking-failed', {
          detail: { error: _e.message || String(_e) }
        }));
        capabilityState('hand_control', 'unavailable', _e.message || String(_e), 'device_tilt');
        return false;
      }
    }

    function disableHandControl() {
      if (!handControlOn) return;
      handControlOn = false;
      if (typeof handFrameSubscriber === 'function') {
        try { handFrameSubscriber(null); } catch (_e) {}
      }
      releaseHandCamera();
      stopHandLoopIfIdle();
    }

    // Camera-feed consumer (e.g. a VideoTexture piece background): holds a
    // reference on the shared stream and hands back the hidden <video>.
    async function acquireCameraFeed() {
      if (disposed || !voices.hand_tracking) throw new Error('camera not available for this piece');
      return acquireHandCamera();
    }

    function releaseCameraFeed() {
      releaseHandCamera();
    }

    async function ensureSynth() {
      if (ambientSynth) return ambientSynth;
      var Tone = await loadToneOnce(opts.toneSrc);
      await Tone.start();
      if (disposed) return null;
      if (!bus) {
        bus = new Tone.Volume(percentToDb(volumePercent)).toDestination();
        // One shared filter shapes the combined timbre of all three voices —
        // admin-only cutoff/resonance/type tuning (extras.synth), applied
        // once at creation like instrument/scale/tempo.
        filter = new Tone.Filter(filterCutoff, filterType);
        filter.Q.value = filterResonance;
        // Admin-enabled effects, in a fixed order, inserted between filter
        // and bus. Only constructed for effects the admin actually turned
        // on, so a piece with no effects pays no extra audio-node cost.
        var chainTail = filter;
        ['distortion', 'chorus', 'tremolo', 'pitch_shift', 'bitcrusher', 'flanger', 'ring_mod'].forEach(function (key) {
          var cfg = effectsExtras[key];
          if (!cfg || !cfg.enabled) return;
          var node = createEffectNode(Tone, key, cfg);
          chainTail.connect(node);
          chainTail = node;
          effectNodes.push(node);
        });
        chainTail.connect(bus);
        ambientSynth = ambientIsSample
          ? new Tone.Player({
              url: '/media/' + ambientSampleExtras.media_id,
              loop: true,
              autostart: false,
              onerror: function () {}, // approximate rather than fail — piece plays on without the sample
              // The buffer loads asynchronously; if enable() already ran by
              // the time it's ready, start it now rather than waiting for
              // the next enable() call.
              onload: function () { if (enabled) { try { ambientSynth.start(); } catch (_e) {} } },
            }).connect(filter)
          : buildVoice(Tone, instrumentCtorName).connect(filter);
        movementSynth = buildVoice(Tone, instrumentCtorName).connect(filter);
        melodicSynth = buildVoice(Tone, instrumentCtorName).connect(filter);
      }
      return ambientSynth;
    }

    // Tears down and rebuilds the mic's effects chain from micEffectsState,
    // then reconnects straight into `bus`. Rebuilding wholesale (rather than
    // surgically inserting/removing one node) keeps ordering simple and
    // correct no matter what sequence effects are toggled in.
    function rebuildMicChain(Tone) {
      if (!micNode) return;
      try {
        if (micNode.disconnect) micNode.disconnect();
        else if (micNativeSource && micNativeSource.disconnect) micNativeSource.disconnect();
      } catch (_e) {}
      micEffectNodes.forEach(function (node) {
        try { node.disconnect(); } catch (_e) {}
        try { node.dispose && node.dispose(); } catch (_e) {}
      });
      micEffectNodes = [];
      var chainTail = micNativeSource || micNode;
      ['distortion', 'chorus', 'tremolo', 'pitch_shift', 'bitcrusher', 'flanger', 'ring_mod'].forEach(function (key) {
        var cfg = micEffectsState[key];
        if (!cfg || !cfg.enabled) return;
        var node = createEffectNode(Tone, key, cfg);
        Tone.connect(chainTail, node);
        chainTail = node;
        micEffectNodes.push(node);
      });
      Tone.connect(chainTail, bus);
    }

    var micStream = null, micNativeSource = null;

    async function enableMic() {
      if (disposed || micNode) return !!micNode;
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        document.dispatchEvent(new CustomEvent('creatr-mic-failed', {
          detail: { error: 'navigator.mediaDevices.getUserMedia not supported or blocked' }
        }));
        return false;
      }
      // Grab the mic permission as the FIRST await, inside the invoking
      // gesture task (WebKit's transient activation does not survive the
      // Tone.js load below). Tone.UserMedia.open() then reuses the already-
      // granted stream is retained and connected directly after Tone loads,
      // so there is no second capture request outside the activation window.
      capabilityState('mic', 'loading');
      var permissionStream = null;
      try {
        permissionStream = await navigator.mediaDevices.getUserMedia({ audio: true });
        sonicDebug('mic acquired', permissionStream.getAudioTracks().map(function (t) { return t.readyState + '/' + t.enabled; }).join(','));
      } catch (permError) {
        document.dispatchEvent(new CustomEvent('creatr-mic-failed', {
          detail: { error: permError.message || String(permError) }
        }));
        capabilityState('mic', 'unavailable', permError.message || String(permError));
        return false;
      }
      var releasePermissionStream = function () {
        if (!permissionStream) return;
        try { permissionStream.getTracks().forEach(function (t) { t.stop(); }); } catch (_e) {}
        permissionStream = null;
      };
      await ensureSynth();
      if (disposed || !bus) { releasePermissionStream(); return false; }
      var Tone = global.Tone;
      if (!Tone) {
        releasePermissionStream();
        document.dispatchEvent(new CustomEvent('creatr-mic-failed', {
          detail: { error: 'Tone.js unavailable' }
        }));
        return false;
      }
      try {
        var rawContext = Tone.context && (Tone.context.rawContext || Tone.context);
        if (!rawContext || !rawContext.createMediaStreamSource) throw new Error('MediaStreamAudioSourceNode unavailable');
        micStream = permissionStream;
        permissionStream = null;
        micNativeSource = rawContext.createMediaStreamSource(micStream);
        micNode = micNativeSource;
        rebuildMicChain(Tone);
        // iOS switches the audio session to play-and-record when the mic
        // opens, which can suspend/interrupt the AudioContext and kill
        // continuously-scheduled sources. Per-tick synth voices recover on
        // their next note; the context itself and a looping ambient
        // Tone.Player do not — recover both explicitly.
        recoverFromAudioSessionChange(Tone);
        capabilityState('mic', 'active');
        return true;
      } catch (_e) {
        releasePermissionStream();
        disableMic();
        try { recoverFromAudioSessionChange(Tone); } catch (_ignored) {}
        capabilityState('mic', 'unavailable', _e.message || String(_e));
        document.dispatchEvent(new CustomEvent('creatr-mic-failed', {
          detail: { error: _e.message || String(_e) }
        }));
        return false;
      }
    }

    // Resumes a suspended/interrupted context and restarts the looping
    // ambient sample if the session change stopped it. Also installs (once)
    // a statechange listener so the same recovery runs after any later
    // interruption — a phone call, Siri, another app taking the session.
    var audioRecoveryListenerInstalled = false;
    function recoverFromAudioSessionChange(Tone) {
      var doRecover = function () {
        try {
          var raw = Tone.context && (Tone.context.rawContext || Tone.context);
          if (raw && raw.state !== 'running' && raw.resume) raw.resume().catch(function () {});
        } catch (_e) {}
        if (enabled && ambientIsSample && ambientSynth && ambientSynth.loaded) {
          try { if (ambientSynth.state !== 'started') ambientSynth.start(); } catch (_e) {}
        }
      };
      doRecover();
      if (audioRecoveryListenerInstalled) return;
      try {
        var raw = Tone.context && (Tone.context.rawContext || Tone.context);
        if (raw && raw.addEventListener) {
          raw.addEventListener('statechange', function () {
            if (disposed) return;
            if (raw.state === 'running') doRecover();
            else if (raw.state !== 'closed' && raw.resume) raw.resume().catch(function () {});
          });
          audioRecoveryListenerInstalled = true;
        }
      } catch (_e) {}
    }

    function disableMic() {
      if (!micNode && !micStream) return;
      micEffectNodes.forEach(function (node) {
        try { node.disconnect(); } catch (_e) {}
        try { node.dispose && node.dispose(); } catch (_e) {}
      });
      micEffectNodes = [];
      try { micNativeSource && micNativeSource.disconnect(); } catch (_e) {}
      if (micStream) { try { micStream.getTracks().forEach(function (track) { track.stop(); }); } catch (_e) {} }
      micNativeSource = null;
      micStream = null;
      micNode = null;
    }

    return {
      async enable() {
        if (disposed) return false;
        // Warm the MediaPipe model in the background as soon as sound is on
        // for a hand-tracking piece: the (large) model + WASM load then
        // doesn't sit between the visitor's later theremin tap and
        // getUserMedia — the tap only has to grant the camera.
        if (voices.hand_tracking) { try { loadHandLandmarkerOnce().catch(function () {}); } catch (_e) {} }
        await ensureSynth();
        if (disposed || !ambientSynth) return false;
        enabled = true;
        if (ambientIsSample && ambientSynth.loaded) {
          try { ambientSynth.start(); } catch (_e) {}
        }
        ensureLoopStarted();
        return true;
      },
      disable() {
        enabled = false;
        if (ambientIsSample && ambientSynth) {
          try { ambientSynth.stop(); } catch (_e) {}
        }
      },
      isEnabled: function () { return enabled; },
      update: function (motion) {
        // External-drive style only (immersive-gallery.js) — controllers
        // that own their own getMover-driven rAF loop ignore this. Always
        // active while enabled — never gated by inputMode.
        if (!voiceMovement || drivesSelf || !enabled || !movementSynth || disposed) return;
        var dx = (motion && motion.dx) || 0, dy = (motion && motion.dy) || 0, dz = (motion && motion.dz) || 0;
        var speed = Math.hypot(dx, dy, dz);
        if (speed < 0.002) return;
        var now = performance.now();
        if (now - lastMotionNoteAt < minInterval) return;
        lastMotionNoteAt = now;
        var octave = Math.min(2, Math.floor(Math.abs(dy) * 25));
        playOn(movementSynth, walk++, octave);
      },
      setVolume: function (percent) {
        volumePercent = Math.max(0, Math.min(100, Number(percent) || 0));
        applyVolume();
      },
      getVolume: function () { return volumePercent; },
      setInputMode: function (mode) {
        inputMode = (mode === 'keyboard' || mode === 'hand') ? mode : 'motion';
      },
      getInputMode: function () { return inputMode; },
      getScaleLength: function () { return scale.length; },
      triggerNote: function (degree, octaveOffset) { playOn(melodicSynth, degree, octaveOffset); },
      // Chromatic (piano-key) triggering — plays any semitone, not just the
      // piece's configured scale degrees, through the same melodic voice.
      // Standard MIDI numbering (C4=60); currentOctave defaults to 3, which
      // maps to the legacy baseMidi=48 (C3) convention. semitoneIndex is NOT
      // wrapped to 0-11 — the physical-keyboard mapping deliberately spans
      // just past one octave (K/L/O/P land in the octave above), so values
      // up to ~16 are expected and should advance into the next octave
      // rather than wrapping back into the current one.
      triggerChromaticNote: function (semitoneIndex) {
        if (!enabled || !melodicSynth || disposed) return;
        var midi = 12 * (currentOctave + 1) + (Number(semitoneIndex) || 0);
        try { melodicSynth.triggerAttackRelease(midiToFreq(midi), '16n'); } catch (_e) {}
      },
      setOctave: function (octave) {
        currentOctave = Math.max(octaveMin, Math.min(octaveMax, Math.round(Number(octave) || currentOctave)));
      },
      getOctave: function () { return currentOctave; },
      getOctaveRange: function () { return { min: octaveMin, max: octaveMax }; },
      // Visitor-facing per-voice instrument override — rebuilds just the one
      // voice's synth in place, live, without disposing/recreating the whole
      // engine. Never touches sonicParams/the DB; callers own persisting the
      // choice (e.g. localStorage) across page loads. No-ops until the
      // engine has been enabled at least once (ensureSynth() must have run).
      setVoiceInstrument: function (voiceName, instrumentKey) {
        // A sample-backed ambient voice (Tone.Player) has no meaningful
        // synth-instrument choice — refuse the override rather than
        // silently swapping the admin-authored sample out from under it.
        if (voiceName === 'ambient' && ambientIsSample) return false;
        if (!SONIC_INSTRUMENTS[instrumentKey] || disposed || !ambientSynth) return false;
        var Tone = global.Tone;
        if (!Tone) return false;
        var ctorName = SONIC_INSTRUMENTS[instrumentKey];
        var newSynth = buildVoice(Tone, ctorName).connect(filter);
        if (voiceName === 'ambient') {
          try { ambientSynth.dispose && ambientSynth.dispose(); } catch (_e) {}
          ambientSynth = newSynth;
        } else if (voiceName === 'movement') {
          try { movementSynth.dispose && movementSynth.dispose(); } catch (_e) {}
          movementSynth = newSynth;
        } else if (voiceName === 'melodic') {
          try { melodicSynth.dispose && melodicSynth.dispose(); } catch (_e) {}
          melodicSynth = newSynth;
        } else {
          try { newSynth.dispose && newSynth.dispose(); } catch (_e) {}
          return false;
        }
        voiceInstrumentOverrides[voiceName] = instrumentKey;
        return true;
      },
      getVoiceInstrument: function (voiceName) {
        return voiceInstrumentOverrides[voiceName] || instrumentKey;
      },
      // Lets UI surfaces hide/disable the ambient instrument dropdown
      // outright rather than relying on setVoiceInstrument()'s refusal.
      isAmbientSample: function () { return ambientIsSample; },
      // Hand-tracking is only offered when extras.voices.hand_tracking is
      // true for this piece (enableHandTracking() itself also refuses
      // otherwise, as defense in depth against a caller that doesn't check).
      isHandTrackingAllowed: function () { return !!voices.hand_tracking; },
      enableHandTracking: enableHandTracking,
      disableHandTracking: disableHandTracking,
      // Hand-control (piece interaction) + camera-feed consumers — all three
      // camera users share one getUserMedia stream (ref-counted).
      enableHandControl: enableHandControl,
      disableHandControl: disableHandControl,
      isHandControlEnabled: function () { return handControlOn; },
      // cb receives the current hand's 21 normalized landmarks each frame,
      // or null on hand-lost frames. One subscriber; pass null to clear.
      onHandFrame: function (cb) { handFrameSubscriber = (typeof cb === 'function') ? cb : null; },
      acquireCameraFeed: acquireCameraFeed,
      releaseCameraFeed: releaseCameraFeed,
      getHandVideoElement: function () { return handVideoEl; },
      // Live human-voice input (mic) — visitor-facing, off by default, never
      // persisted by this engine. isMicSupported() is a plain feature check
      // (doesn't require Tone.js to be loaded yet) so UI surfaces can decide
      // whether to render the toggle as clickable before the visitor has
      // ever unmuted anything.
      isMicSupported: function () {
        return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
      },
      isMicEnabled: function () { return !!micNode; },
      enableMic: enableMic,
      disableMic: disableMic,
      // key: 'distortion'|'chorus'|'tremolo'|'pitch_shift'|'bitcrusher'|
      // 'flanger'|'ring_mod'. params optional — falls back to
      // MIC_EFFECT_DEFAULTS. No-ops (returns false) until the mic is on.
      setMicEffect: function (key, enabled, params) {
        if (!micNode || !MIC_EFFECT_DEFAULTS[key]) return false;
        var Tone = global.Tone;
        if (!Tone) return false;
        var defaults = MIC_EFFECT_DEFAULTS[key];
        var cfg = {};
        for (var k in defaults) { cfg[k] = (params && params[k] !== undefined) ? params[k] : defaults[k]; }
        cfg.enabled = !!enabled;
        micEffectsState[key] = cfg;
        rebuildMicChain(Tone);
        return true;
      },
      dispose: function () {
        disposed = true; enabled = false;
        if (rafId) { cancelAnimationFrame(rafId); rafId = null; }
        if (idleTimer) { clearTimeout(idleTimer); idleTimer = null; }
        disableHandTracking();
        disableHandControl();
        handCameraRefs = 0; releaseHandCamera(); // force-drop any feed holders
        disableMic();
        try { handLandmarker && handLandmarker.close && handLandmarker.close(); } catch (_e) {}
        try { ambientSynth && ambientSynth.dispose && ambientSynth.dispose(); } catch (_e) {}
        try { movementSynth && movementSynth.dispose && movementSynth.dispose(); } catch (_e) {}
        try { melodicSynth && melodicSynth.dispose && melodicSynth.dispose(); } catch (_e) {}
        try { filter && filter.dispose && filter.dispose(); } catch (_e) {}
        effectNodes.forEach(function (node) { try { node && node.dispose && node.dispose(); } catch (_e) {} });
        try { bus && bus.dispose && bus.dispose(); } catch (_e) {}
      },
    };
  }

  // Standard "typing keyboard as piano" layout: home row A S D F G H J K L ;
  // are the white keys (K L ; deliberately spill into the octave above,
  // matching a real piano's continuation), the row above — W E _ T Y U _ O P —
  // are the black/sharp keys in the gaps (no black key above the E-F or B-C
  // boundaries). Values are semitone offsets from the current octave's root,
  // fed straight into triggerChromaticNote().
  var PIANO_KEY_MAP = {
    a: 0, w: 1, s: 2, e: 3, d: 4, f: 5, t: 6, g: 7, y: 8, h: 9, u: 10, j: 11,
    k: 12, o: 13, l: 14, p: 15, ';': 16,
  };

  /**
   * Attaches a keydown listener mapping PIANO_KEY_MAP to
   * engine.triggerChromaticNote(). Callers should only call this while their
   * on-screen "keyboard mode" toggle is active, and call the returned
   * detach() the moment it's switched off (or the piece/popover unmounts) —
   * this is what keeps physical piano keys from ever fighting with WASD
   * camera-movement shortcuts, since the listener simply doesn't exist
   * except during deliberate piano play.
   */
  function attachPianoKeyListener(engine, onNoteStateChange) {
    function onKeyDown(event) {
      if (event.repeat) return;
      var target = event.target;
      if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable)) return;
      var key = event.key && event.key.length === 1 ? event.key.toLowerCase() : event.key;
      if (!Object.prototype.hasOwnProperty.call(PIANO_KEY_MAP, key)) return;
      event.preventDefault();
      var semitone = PIANO_KEY_MAP[key];
      engine.triggerChromaticNote(semitone);
      if (typeof onNoteStateChange === 'function') {
        onNoteStateChange(semitone, true);
      }
    }
    function onKeyUp(event) {
      var target = event.target;
      if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable)) return;
      var key = event.key && event.key.length === 1 ? event.key.toLowerCase() : event.key;
      if (!Object.prototype.hasOwnProperty.call(PIANO_KEY_MAP, key)) return;
      event.preventDefault();
      var semitone = PIANO_KEY_MAP[key];
      if (typeof onNoteStateChange === 'function') {
        onNoteStateChange(semitone, false);
      }
    }
    document.addEventListener('keydown', onKeyDown);
    document.addEventListener('keyup', onKeyUp);
    return function detach() {
      document.removeEventListener('keydown', onKeyDown);
      document.removeEventListener('keyup', onKeyUp);
    };
  }

  global.CreatrSonicController = {
    create: create,
    createDeviceTiltController: createDeviceTiltController,
    SONIC_SCALES: SONIC_SCALES,
    SONIC_INSTRUMENTS: SONIC_INSTRUMENTS,
    midiToFreq: midiToFreq,
    percentToDb: percentToDb,
    loadToneOnce: loadToneOnce,
    PIANO_KEY_MAP: PIANO_KEY_MAP,
    attachPianoKeyListener: attachPianoKeyListener,
  };
})(typeof window !== 'undefined' ? window : this);
