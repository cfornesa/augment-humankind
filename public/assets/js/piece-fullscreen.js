(function () {
    const root = document.querySelector('[data-piece-download-root]');
    if (!root) {
        return;
    }

    const toggle = root.querySelector('[data-piece-fullscreen-toggle]');
    if (!toggle) {
        return;
    }

    let isFullscreen = false;
    let previouslyFocused = null;

    // KEEP IN SYNC: same iPhone-WebKit detection as the immersive view
    // (views/immersive/piece.php) — the Fullscreen API is unavailable on
    // iPhone Safari, so we go straight to the CSS overlay there.
    function isIPhoneWebKitBrowser() {
        if (typeof navigator === 'undefined') return false;
        const ua = navigator.userAgent || '';
        const maxTouchPoints = navigator.maxTouchPoints || 0;
        const isIPad = /\biPad\b/i.test(ua) || (/\bMacintosh\b/i.test(ua) && maxTouchPoints > 1);
        return /\biPhone\b/i.test(ua) && /AppleWebKit/i.test(ua) && !isIPad;
    }

    function applyState(active) {
        if (active === isFullscreen) {
            return;
        }
        isFullscreen = active;
        root.classList.toggle('piece-stage-fullscreen', active);
        document.body.classList.toggle('piece-fullscreen-locked', active);
        toggle.setAttribute('aria-expanded', active ? 'true' : 'false');
        toggle.setAttribute('aria-label', active ? 'Exit fullscreen' : 'Expand piece to fullscreen');
        if (active) {
            previouslyFocused = document.activeElement;
            toggle.focus();
        } else if (previouslyFocused && typeof previouslyFocused.focus === 'function') {
            previouslyFocused.focus();
            previouslyFocused = null;
        }
        // Let the embedded piece react to the size change.
        window.dispatchEvent(new Event('resize'));
    }

    function enter() {
        if (isIPhoneWebKitBrowser() || typeof root.requestFullscreen !== 'function') {
            applyState(true);
            return;
        }
        root.requestFullscreen().then(() => {
            applyState(true);
        }).catch(() => {
            // Fullscreen API blocked or unsupported — CSS overlay fallback.
            applyState(true);
        });
    }

    function exit() {
        if (document.fullscreenElement) {
            document.exitFullscreen().catch(() => applyState(false));
            return;
        }
        applyState(false);
    }

    toggle.addEventListener('click', () => {
        if (isFullscreen) {
            exit();
        } else {
            enter();
        }
    });

    document.addEventListener('fullscreenchange', () => {
        applyState(!!document.fullscreenElement);
    });

    window.addEventListener('keydown', (event) => {
        // Needed for the CSS-fallback path; the native path already exits on
        // Escape and is reconciled via fullscreenchange above.
        if (event.key === 'Escape' && isFullscreen && !document.fullscreenElement) {
            applyState(false);
        }
    });
})();

// Regular-view sound toggle (Three.js/A-Frame only) — muted by default. The
// iframe (piece-runtime.js) owns Tone.js and playback; this button only
// posts the enable/mute request and reflects the state it echoes back, so a
// Tone.js load failure inside the iframe can't leave the button stuck "on".
// A separate small chevron opens a volume/keyboard popover (same pattern as
// the immersive toolbar's sound panel) — its controls post the analogous
// creatr-sound-volume/creatr-sound-input-mode/creatr-sound-note messages,
// each a thin pass-through inside the iframe to the shared sonic-controller
// engine.
(function () {
    const root = document.querySelector('[data-piece-download-root]');
    // toggle is null on camera-only pieces (camera overlay allowed, no sound
    // design) — the panel/camera wiring below must still run for them.
    const toggle = root && root.querySelector('[data-piece-sound-toggle]');
    const frame = root && root.querySelector('[data-piece-download-frame]');
    if (!root || !frame) {
        return;
    }

    const ICON_OFF = '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4z"/><line x1="22" y1="9" x2="16" y2="15"/><line x1="16" y1="9" x2="22" y2="15"/></svg>';
    const ICON_ON = '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4z"/><path d="M16 9a4 4 0 0 1 0 6"/><path d="M19 6a8 8 0 0 1 0 12"/></svg>';

    let enabled = false;

    function setVisualState(on) {
        enabled = on;
        if (toggle) {
            toggle.setAttribute('aria-pressed', on ? 'true' : 'false');
            toggle.setAttribute('aria-label', on ? 'Mute sound' : 'Unmute sound');
            toggle.innerHTML = on ? ICON_ON : ICON_OFF;
        }
        if (panelMuteToggle) {
            panelMuteToggle.setAttribute('aria-checked', on ? 'true' : 'false');
            panelMuteToggle.textContent = on ? 'On' : 'Off';
        }
    }

    function requestToggle(nextEnabled) {
        const win = frame.contentWindow;
        if (!win) {
            return;
        }
        win.postMessage({ type: 'creatr-sound-toggle', enabled: nextEnabled }, '*');
        // Optimistic UI; corrected by the state message below if the iframe
        // rejects it (e.g. Tone.js failed to load).
        setVisualState(nextEnabled);
    }

    toggle?.addEventListener('click', () => requestToggle(!enabled));

    window.addEventListener('message', (event) => {
        if (event.source !== frame.contentWindow || !event.data || event.data.type !== 'creatr-sound-state') {
            return;
        }
        setVisualState(!!event.data.enabled);
    });

    // --- Sound settings popover (volume / keyboard mode / piano keyboard) --
    const panelTrigger = root.querySelector('[data-piece-sound-panel-trigger]');
    const panel = root.querySelector('[data-piece-sound-panel]');
    const panelMuteToggle = root.querySelector('[data-piece-sound-mute-toggle]');
    const panelVolume = root.querySelector('[data-piece-sound-volume]');
    const panelKeyboardRow = root.querySelector('[data-piece-sound-keyboard-row]');
    const panelKeyboardToggle = root.querySelector('[data-piece-sound-keyboard-toggle]');
    const panelKeysWrap = root.querySelector('[data-piece-sound-keys]');
    const pianoKeysGroup = root.querySelector('[data-piece-piano-keys]');
    const pianoOctaveDisplay = root.querySelector('[data-piece-piano-octave-display]');
    const pianoOctaveDown = root.querySelector('[data-piece-piano-octave-down]');
    const pianoOctaveUp = root.querySelector('[data-piece-piano-octave-up]');
    const voicePickerRows = root.querySelectorAll('[data-piece-voice-picker-row]');
    const voicePickerSelects = root.querySelectorAll('[data-piece-voice-picker-select]');
    const micRow = root.querySelector('[data-piece-mic-row]');
    const micToggle = root.querySelector('[data-piece-mic-toggle]');
    const micFxWrap = root.querySelector('[data-piece-mic-fx]');
    const micFxToggles = root.querySelectorAll('[data-piece-mic-fx-toggle]');
    const pieceId = root.dataset.pieceId || null;
    if (!panelTrigger || !panel) {
        return;
    }

    // Visitor-chosen per-voice instrument overrides — session-local only,
    // never touches sonicParams/the DB. One localStorage entry per piece.
    function voiceInstrumentStorageKey() {
        return 'creatr-sonic-voice-instruments:' + pieceId;
    }
    function readVoiceInstrumentOverrides() {
        if (!pieceId) return {};
        try {
            const raw = window.localStorage.getItem(voiceInstrumentStorageKey());
            const parsed = raw ? JSON.parse(raw) : null;
            return (parsed && typeof parsed === 'object') ? parsed : {};
        } catch (_e) {
            return {};
        }
    }
    function writeVoiceInstrumentOverride(voiceName, instrumentKey) {
        if (!pieceId) return;
        try {
            const overrides = readVoiceInstrumentOverrides();
            overrides[voiceName] = instrumentKey;
            window.localStorage.setItem(voiceInstrumentStorageKey(), JSON.stringify(overrides));
        } catch (_e) {}
    }

    // The iframe (piece-runtime.js) knows sonicParams.extras.voices directly;
    // this parent page only relays postMessages, so it announces which
    // voices are visible once at startup — also the point at which we push
    // any stored per-voice instrument overrides into the (now-listening)
    // iframe, and sync the dropdowns to reflect them.
    window.addEventListener('message', (event) => {
        if (event.source !== frame.contentWindow || !event.data || event.data.type !== 'creatr-sound-voices') {
            return;
        }
        if (panelKeyboardRow) panelKeyboardRow.hidden = !event.data.voices.melodic;
        const overrides = readVoiceInstrumentOverrides();
        voicePickerRows.forEach((row) => {
            const voiceName = row.dataset.pieceVoicePickerRow;
            row.hidden = !event.data.voices[voiceName] || (voiceName === 'ambient' && event.data.ambientIsSample);
        });
        voicePickerSelects.forEach((select) => {
            const voiceName = select.dataset.voice;
            if (overrides[voiceName]) {
                select.value = overrides[voiceName];
                frame.contentWindow?.postMessage({ type: 'creatr-sound-voice-instrument', voice: voiceName, instrument: overrides[voiceName] }, '*');
            }
        });
        if (micRow) micRow.hidden = !event.data.micSupported;
        const handControlRowEl = root.querySelector('[data-piece-sound-hand-control-row]');
        if (handControlRowEl) handControlRowEl.hidden = !event.data.handControlSupported;
        const cameraBgRowEl = root.querySelector('[data-piece-sound-camera-bg-row]');
        if (cameraBgRowEl) cameraBgRowEl.hidden = !event.data.cameraBgSupported;
    });

    voicePickerSelects.forEach((select) => {
        select.addEventListener('change', () => {
            const voiceName = select.dataset.voice;
            frame.contentWindow?.postMessage({ type: 'creatr-sound-voice-instrument', voice: voiceName, instrument: select.value }, '*');
            writeVoiceInstrumentOverride(voiceName, select.value);
        });
    });

    function isPanelOpen() {
        return !panel.hidden;
    }

    function setPanelOpen(open) {
        panel.hidden = !open;
        panelTrigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    panelTrigger.addEventListener('click', () => setPanelOpen(!isPanelOpen()));

    panelMuteToggle?.addEventListener('click', () => requestToggle(!enabled));

    panelVolume?.addEventListener('input', (event) => {
        const win = frame.contentWindow;
        win?.postMessage({ type: 'creatr-sound-volume', percent: Number(event.target.value) }, '*');
    });

    // Same standard "typing keyboard as piano" layout as sonic-controller.js's
    // PIANO_KEY_MAP — duplicated here (rather than loading the whole shared
    // engine into this parent page just for a lookup table) since this
    // surface only ever relays postMessages, never touches Tone.js directly.
    const PIANO_KEY_MAP = {
        a: 0, w: 1, s: 2, e: 3, d: 4, f: 5, t: 6, g: 7, y: 8, h: 9, u: 10, j: 11,
        k: 12, o: 13, l: 14, p: 15, ';': 16,
    };
    let onPhysicalKeyDown = null;
    let onPhysicalKeyUp = null;

    function sendChromaticNote(semitone) {
        const win = frame.contentWindow;
        win?.postMessage({ type: 'creatr-sound-note', semitone }, '*');
    }

    function attachPhysicalPianoKeys() {
        detachPhysicalPianoKeys();
        onPhysicalKeyDown = (event) => {
            if (event.repeat) return;
            const target = event.target;
            if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable)) return;
            const key = event.key && event.key.length === 1 ? event.key.toLowerCase() : event.key;
            if (!Object.prototype.hasOwnProperty.call(PIANO_KEY_MAP, key)) return;
            event.preventDefault();
            const semitone = PIANO_KEY_MAP[key];
            sendChromaticNote(semitone);
            const keyBtn = pianoKeysGroup?.querySelector('[data-semitone="' + semitone + '"]');
            if (keyBtn) keyBtn.classList.add('is-pressed');
        };
        onPhysicalKeyUp = (event) => {
            const target = event.target;
            if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable)) return;
            const key = event.key && event.key.length === 1 ? event.key.toLowerCase() : event.key;
            if (!Object.prototype.hasOwnProperty.call(PIANO_KEY_MAP, key)) return;
            event.preventDefault();
            const semitone = PIANO_KEY_MAP[key];
            const keyBtn = pianoKeysGroup?.querySelector('[data-semitone="' + semitone + '"]');
            if (keyBtn) keyBtn.classList.remove('is-pressed');
        };
        document.addEventListener('keydown', onPhysicalKeyDown);
        document.addEventListener('keyup', onPhysicalKeyUp);
    }

    function detachPhysicalPianoKeys() {
        if (onPhysicalKeyDown) document.removeEventListener('keydown', onPhysicalKeyDown);
        if (onPhysicalKeyUp) document.removeEventListener('keyup', onPhysicalKeyUp);
        onPhysicalKeyDown = null;
        onPhysicalKeyUp = null;
        pianoKeysGroup?.querySelectorAll('[data-semitone]').forEach(k => k.classList.remove('is-pressed'));
    }

    panelKeyboardToggle?.addEventListener('click', () => {
        const nextOn = panelKeyboardToggle.getAttribute('aria-pressed') !== 'true';
        panelKeyboardToggle.setAttribute('aria-pressed', nextOn ? 'true' : 'false');
        if (panelKeysWrap) panelKeysWrap.hidden = !nextOn;
        if (nextOn) {
            // This click is the autoplay-unlocking gesture, so unmute now
            // too — otherwise physical key presses before ever clicking a
            // key would silently do nothing.
            if (!enabled) requestToggle(true);
            attachPhysicalPianoKeys();
        } else {
            detachPhysicalPianoKeys();
        }
        const win = frame.contentWindow;
        win?.postMessage({ type: 'creatr-sound-input-mode', mode: nextOn ? 'keyboard' : 'motion' }, '*');
    });

    pianoKeysGroup?.addEventListener('pointerdown', (event) => {
        const keyBtn = event.target instanceof Element ? event.target.closest('[data-piece-piano-key]') : null;
        if (!keyBtn) return;
        sendChromaticNote(Number(keyBtn.dataset.semitone || 0));
    });

    let currentOctaveDisplay = 3;
    pianoOctaveDown?.addEventListener('click', () => {
        currentOctaveDisplay -= 1;
        if (pianoOctaveDisplay) pianoOctaveDisplay.textContent = String(currentOctaveDisplay);
        frame.contentWindow?.postMessage({ type: 'creatr-sound-octave', octave: currentOctaveDisplay }, '*');
    });
    pianoOctaveUp?.addEventListener('click', () => {
        currentOctaveDisplay += 1;
        if (pianoOctaveDisplay) pianoOctaveDisplay.textContent = String(currentOctaveDisplay);
        frame.contentWindow?.postMessage({ type: 'creatr-sound-octave', octave: currentOctaveDisplay }, '*');
    });

    // Gesture-critical toggles (camera/mic getUserMedia inside the iframe):
    // postMessage does NOT carry WebKit's transient user activation across
    // the boundary, so call straight into the same-origin iframe from the
    // click task via the bridge piece-runtime.js exposes — falling back to
    // the old relay if the bridge isn't there yet (iframe still booting).
    function gestureCall(method, on, fallbackType) {
        const win = frame.contentWindow;
        if (!win) return;
        const bridge = win.__creatrSonicGesture;
        if (bridge && typeof bridge[method] === 'function') {
            try { bridge[method](on); return; } catch (_e) {}
        }
        win.postMessage({ type: fallbackType, enabled: on }, '*');
    }

    const panelHandToggle = root.querySelector('[data-piece-sound-hand-toggle]');
    panelHandToggle?.addEventListener('click', () => {
        const nextOn = panelHandToggle.getAttribute('aria-pressed') !== 'true';
        // On denial the iframe replies with enabled:false and this silently
        // reverts — no error banner.
        gestureCall('toggleHand', nextOn, 'creatr-sound-hand-toggle');
    });

    window.addEventListener('message', (event) => {
        if (event.source !== frame.contentWindow || !event.data || event.data.type !== 'creatr-sound-hand-state') {
            return;
        }
        panelHandToggle?.setAttribute('aria-pressed', event.data.enabled ? 'true' : 'false');
    });

    // Hand-control (piece interaction) and camera-background — same shared
    // camera pipeline as the theremin, each its own toggle. Rows are hidden
    // until the iframe's capability handshake says the active engine
    // supports them.
    const handControlToggle = root.querySelector('[data-piece-sound-hand-control-toggle]');
    const resetViewButton = root.querySelector('[data-piece-reset-view]');
    const cameraBgToggle = root.querySelector('[data-piece-sound-camera-bg-toggle]');
    const cameraOpacityRow = root.querySelector('[data-piece-sound-camera-opacity-row]');
    const cameraOpacity = root.querySelector('[data-piece-sound-camera-opacity]');

    handControlToggle?.addEventListener('click', () => {
        const nextOn = handControlToggle.getAttribute('aria-pressed') !== 'true';
        handControlToggle.setAttribute('aria-pressed', nextOn ? 'true' : 'false');
        if (handControlToggle.dataset.capabilityFallback === 'device_tilt') {
            gestureCall('toggleTilt', nextOn, 'creatr-sound-hand-control-toggle');
        } else {
            gestureCall('toggleHandControl', nextOn, 'creatr-sound-hand-control-toggle');
        }
    });
    resetViewButton?.addEventListener('click', async () => {
        resetViewButton.disabled = true;
        resetViewButton.setAttribute('aria-busy', 'true');
        try {
            const bridge = frame.contentWindow?.__creatrSonicGesture;
            if (bridge && typeof bridge.resetView === 'function') await bridge.resetView();
            else frame.contentWindow?.postMessage({ type: 'creatr-reset-view' }, '*');
        } finally {
            resetViewButton.disabled = false;
            resetViewButton.removeAttribute('aria-busy');
        }
    });
    cameraBgToggle?.addEventListener('click', () => {
        const nextOn = cameraBgToggle.getAttribute('aria-pressed') !== 'true';
        gestureCall('toggleCameraBackground', nextOn, 'creatr-sound-camera-bg-toggle');
    });
    cameraOpacity?.addEventListener('input', () => {
        frame.contentWindow?.postMessage({ type: 'creatr-sound-camera-bg-opacity', value: Number(cameraOpacity.value) / 100 }, '*');
    });
    window.addEventListener('message', (event) => {
        if (event.source !== frame.contentWindow || !event.data) return;
        if (event.data.type === 'creatr-sound-hand-control-state') {
            handControlToggle?.setAttribute('aria-pressed', event.data.enabled ? 'true' : 'false');
            if (handControlToggle && event.data.mode === 'device_tilt') {
                handControlToggle.dataset.capabilityFallback = 'device_tilt';
                handControlToggle.textContent = 'Use device tilt';
            }
        } else if (event.data.type === 'creatr-sound-camera-bg-state') {
            cameraBgToggle?.setAttribute('aria-pressed', event.data.enabled ? 'true' : 'false');
            if (cameraOpacityRow) cameraOpacityRow.hidden = !event.data.enabled || !event.data.opacitySupported;
            // Initialize the slider from the hook's real default (0.35 for
            // the 2D DOM overlay, 1.0 for the 3D blended quad).
            if (cameraOpacity && event.data.enabled && typeof event.data.opacity === 'number') {
                cameraOpacity.value = String(Math.round(event.data.opacity * 100));
            }
        }
    });

    // Live human-voice input (mic) — visitor-facing, off by default, never
    // persisted.
    micToggle?.addEventListener('click', () => {
        const nextOn = micToggle.getAttribute('aria-pressed') !== 'true';
        gestureCall('toggleMic', nextOn, 'creatr-sound-mic-toggle');
    });

    micFxToggles.forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            frame.contentWindow?.postMessage({ type: 'creatr-sound-mic-fx', effect: checkbox.dataset.effect, enabled: checkbox.checked }, '*');
        });
    });

    window.addEventListener('message', (event) => {
        if (event.source !== frame.contentWindow || !event.data || event.data.type !== 'creatr-sound-mic-state') {
            return;
        }
        micToggle?.setAttribute('aria-pressed', event.data.enabled ? 'true' : 'false');
        if (micFxWrap) micFxWrap.hidden = !event.data.enabled;
    });

    window.addEventListener('message', (event) => {
        if (event.source !== frame.contentWindow || event.data?.type !== 'creatr-sonic-capability-state') return;
        const detail = event.data.detail || {};
        const target = detail.capability === 'hand_tracking'
            ? panelHandToggle
            : detail.capability === 'hand_control'
                ? handControlToggle
                : detail.capability === 'mic'
                    ? micToggle
                    : null;
        if (!target) return;
        target.dataset.capabilityState = detail.state || '';
        target.setAttribute('aria-busy', detail.state === 'loading' ? 'true' : 'false');
        if (detail.state === 'loading') {
            target.disabled = detail.capability !== 'hand_control';
        } else if (detail.state === 'active') {
            target.disabled = false;
            target.setAttribute('aria-pressed', 'true');
        } else if (detail.state === 'inactive') {
            target.disabled = false;
            target.setAttribute('aria-pressed', 'false');
        } else if (detail.state === 'unavailable') {
            target.setAttribute('aria-pressed', 'false');
            target.title = detail.reason || 'Unavailable on this device';
            if (detail.capability === 'hand_control' && detail.fallback === 'device_tilt') {
                target.disabled = false;
                target.dataset.capabilityFallback = 'device_tilt';
                target.textContent = 'Use device tilt';
            } else {
                target.disabled = true;
                target.textContent = detail.capability === 'mic' ? 'Mic unavailable' : 'Hand tracking unavailable';
            }
        } else if (detail.state === 'fallback') {
            target.disabled = false;
            target.dataset.capabilityFallback = detail.fallback || '';
            target.textContent = detail.fallback === 'device_tilt' ? 'Use device tilt' : target.textContent;
            target.setAttribute('aria-pressed', 'true');
        }
    });

    document.addEventListener('pointerdown', (event) => {
        if (!isPanelOpen()) return;
        if (event.target instanceof Node && (panelTrigger.contains(event.target) || panel.contains(event.target))) return;
        setPanelOpen(false);
    }, { capture: true });
})();

// Download-options picker — lets the person downloading a piece narrow
// which optional sound panels (keyboard/hand-tracking) ride along in their
// own ZIP, bounded by whichever the admin already enabled (see
// piece_export_apply_requested_voices() in piece-render.php). Only rendered
// at all when the admin allowed at least one optional voice (show.php);
// The same canvas-mounted instance remains available in regular and
// fullscreen modes.
document.querySelectorAll('[data-piece-download-picker-wrap]').forEach((wrap) => {
    const links = wrap.querySelectorAll('[data-piece-download-link]');
    const trigger = wrap.querySelector('[data-piece-download-picker-trigger]');
    const picker = wrap.querySelector('[data-piece-download-picker]');
    const checkboxes = wrap.querySelectorAll('[data-piece-download-voice]');
    if (!links.length || !trigger || !picker) return;

    function updateHref() {
        const chosen = Array.from(checkboxes)
            .filter((cb) => cb.checked)
            .map((cb) => cb.dataset.pieceDownloadVoice)
            .filter((value) => value === 'melodic' || value === 'hand_tracking');
        links.forEach((link) => {
            const baseHref = link.dataset.baseHref || link.getAttribute('href') || '';
            link.dataset.baseHref = baseHref;
            const url = new URL(baseHref, window.location.href);
            url.searchParams.set('dl_voices', chosen.join(','));
            link.setAttribute('href', url.pathname + url.search);
        });
    }

    function formatEstimate(bytes) {
        const numeric = Number(bytes);
        if (!Number.isFinite(numeric) || numeric < 1) return 'size varies';
        return '≈' + Math.max(1, Math.round(numeric / (1024 * 1024))) + ' MB';
    }

    function updateEstimates() {
        const fullBase = Number(picker.dataset.downloadEstimateFull || 0);
        const noCameraBase = Number(picker.dataset.downloadEstimateNoCamera || 0);
        let voiceCosts = {};
        try { voiceCosts = JSON.parse(picker.dataset.downloadEstimateVoiceCosts || '{}') || {}; } catch (_) {}
        const reduction = Array.from(checkboxes)
            .filter((cb) => !cb.checked)
            .reduce((sum, cb) => sum + Number(voiceCosts[cb.dataset.pieceDownloadVoice] || 0), 0);
        const estimates = {
            full: formatEstimate(Math.max(0, fullBase - reduction)),
            'no-camera': formatEstimate(Math.max(0, noCameraBase - reduction)),
        };
        picker.querySelectorAll('[data-piece-download-label]').forEach((label) => {
            const kind = label.dataset.pieceDownloadLabel || 'full';
            label.textContent = 'Download ' + (kind === 'no-camera' ? 'Non-Camera' : 'Full') + ' ZIP (' + (estimates[kind] || 'size varies') + ')';
        });
    }

    function isOpen() {
        return !picker.hidden;
    }

    function setOpen(open, { restoreFocus = false } = {}) {
        picker.hidden = !open;
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) {
            picker.querySelector('input, a, button')?.focus?.();
        } else if (restoreFocus) {
            trigger.focus();
        }
    }

    trigger.addEventListener('click', () => setOpen(!isOpen()));
    checkboxes.forEach((cb) => cb.addEventListener('change', () => {
        updateHref();
        updateEstimates();
    }));
    updateEstimates();
    document.addEventListener('pointerdown', (event) => {
        if (!isOpen()) return;
        if (event.target instanceof Node && wrap.contains(event.target)) return;
        setOpen(false);
    }, { capture: true });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || !isOpen()) return;
        event.stopPropagation();
        setOpen(false, { restoreFocus: true });
    });
    links.forEach((link) => link.addEventListener('click', () => setOpen(false)));

    updateHref();
});
