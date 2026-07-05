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
