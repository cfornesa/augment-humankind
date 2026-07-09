(function () {
    const root = document.querySelector('[data-piece-download-root]');
    if (!root) {
        return;
    }

    const toggle = root.querySelector('[data-piece-fullscreen-toggle]');
    const bar = root.querySelector('[data-piece-fullscreen-bar]');
    const closeBtn = root.querySelector('[data-piece-fullscreen-close]');
    if (!toggle || !bar) {
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
        bar.hidden = !active;
        toggle.setAttribute('aria-expanded', active ? 'true' : 'false');
        toggle.setAttribute('aria-label', active ? 'Exit fullscreen' : 'Expand piece to fullscreen');
        if (active) {
            previouslyFocused = document.activeElement;
            const firstControl = bar.querySelector('a, button');
            if (firstControl) {
                firstControl.focus();
            }
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

    if (closeBtn) {
        closeBtn.addEventListener('click', exit);
    }

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
(function () {
    const root = document.querySelector('[data-piece-download-root]');
    const toggle = root && root.querySelector('[data-piece-sound-toggle]');
    const frame = root && root.querySelector('[data-piece-download-frame]');
    if (!toggle || !frame) {
        return;
    }

    const ICON_OFF = '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4z"/><line x1="22" y1="9" x2="16" y2="15"/><line x1="16" y1="9" x2="22" y2="15"/></svg>';
    const ICON_ON = '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4z"/><path d="M16 9a4 4 0 0 1 0 6"/><path d="M19 6a8 8 0 0 1 0 12"/></svg>';

    let enabled = false;

    function setVisualState(on) {
        enabled = on;
        toggle.setAttribute('aria-pressed', on ? 'true' : 'false');
        toggle.setAttribute('aria-label', on ? 'Mute sound' : 'Unmute sound');
        toggle.innerHTML = on ? ICON_ON : ICON_OFF;
    }

    toggle.addEventListener('click', () => {
        const nextEnabled = !enabled;
        const win = frame.contentWindow;
        if (!win) {
            return;
        }
        win.postMessage({ type: 'creatr-sound-toggle', enabled: nextEnabled }, '*');
        // Optimistic UI; corrected by the state message below if the iframe
        // rejects it (e.g. Tone.js failed to load).
        setVisualState(nextEnabled);
    });

    window.addEventListener('message', (event) => {
        if (event.source !== frame.contentWindow || !event.data || event.data.type !== 'creatr-sound-state') {
            return;
        }
        setVisualState(!!event.data.enabled);
    });
})();
