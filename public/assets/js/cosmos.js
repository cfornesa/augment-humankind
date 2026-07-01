/* cosmos.js — Ambient space aesthetics: pulsating stars, shooting stars.
   Aesthetic-only. Does not read or write any data, touch the DOM state
   used by main.js, or interfere with admin functions. */

(function () {
    'use strict';

    // Skip entirely in admin panel
    if (document.body.classList.contains('admin-body')) return;
    var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var saveData = !!(navigator.connection && navigator.connection.saveData);
    var lowMemory = typeof navigator.deviceMemory === 'number' && navigator.deviceMemory <= 4;
    var lowCpu = typeof navigator.hardwareConcurrency === 'number' && navigator.hardwareConcurrency <= 4;
    var lowPowerMode = prefersReducedMotion || saveData || lowMemory || lowCpu;

    if (lowPowerMode) {
        document.documentElement.classList.add('low-power');
    }

    // ─── Pulsating DOM stars (reduced count, slower) ──────────────────────

    (function buildStars() {
        var container = document.createElement('div');
        container.id  = 'cosmos-stars';
        container.setAttribute('aria-hidden', 'true');
        document.body.appendChild(container);

        var count = lowPowerMode ? 14 : 28;

        for (var i = 0; i < count; i++) {
            var isBright = Math.random() < 0.07;
            var size     = isBright
                ? (2 + Math.random() * 1.2)
                : (0.7 + Math.random() * 0.9);

            var star = document.createElement('span');
            star.className = 'cosmos-star';

            // Select color based on astrophysical spectral distribution
            var rand = Math.random();
            var color, glowColor;
            if (rand < 0.08) { // O/B class blue-white stars
                color = 'rgba(165, 195, 255, 1)';
                glowColor = 'rgba(165, 195, 255, 0.45)';
            } else if (rand < 0.22) { // A class white stars
                color = 'rgba(255, 255, 255, 1)';
                glowColor = 'rgba(255, 255, 255, 0.5)';
            } else if (rand < 0.42) { // F/G class golden stars
                color = 'rgba(255, 220, 130, 1)';
                glowColor = 'rgba(255, 220, 130, 0.4)';
            } else if (rand < 0.72) { // K class orange stars
                color = 'rgba(255, 170, 90, 1)';
                glowColor = 'rgba(255, 170, 90, 0.35)';
            } else { // M class red-orange stars
                color = 'rgba(255, 110, 80, 1)';
                glowColor = 'rgba(255, 110, 80, 0.3)';
            }

            // Slow durations: 10–24 s — gentle shimmer, not a flicker
            var duration = lowPowerMode ? (14 + Math.random() * 16) : (10 + Math.random() * 14);
            var delay    = -(Math.random() * 15);

            star.style.cssText =
                'left:'               + (Math.random() * 100).toFixed(1) + '%;' +
                'top:'                + (Math.random() * 100).toFixed(1) + '%;' +
                'width:'              + size.toFixed(1) + 'px;'                  +
                'height:'             + size.toFixed(1) + 'px;'                  +
                'background:'         + color + ';'                              +
                'box-shadow: 0 0 4px 1px ' + glowColor + ';'                     +
                'animation-duration:' + duration.toFixed(1) + 's;'               +
                'animation-delay:'    + delay.toFixed(1) + 's;';

            container.appendChild(star);
        }
    })();

    // ─── Stagger artwork glow animations (::after lives on the card) ─────
    // CSS doesn't let us target ::after delay directly; inject a sheet instead.

    (function staggerGlows() {
        var cards = document.querySelectorAll('.artwork-card, .collection-card');
        if (!cards.length || lowPowerMode) return;
        var rules = [];
        cards.forEach(function (card, i) {
            var delay = ((i * 1.3) % 11).toFixed(1) + 's';
            // Unique class per card so we can write a targeted rule
            var cls = 'cosmos-gc-' + i;
            card.classList.add(cls);
            rules.push('.' + cls + '::after { animation-delay: ' + delay + '; }');
        });
        if (rules.length) {
            var style = document.createElement('style');
            style.textContent = rules.join('\n');
            document.head.appendChild(style);
        }
    })();

    if (lowPowerMode) return;

    // ─── Shooting stars (canvas — loop only runs when a star is active) ───

    var canvas = document.createElement('canvas');
    canvas.id  = 'cosmos-canvas';
    canvas.setAttribute('aria-hidden', 'true');
    canvas.style.cssText =
        'position:fixed;inset:0;pointer-events:none;z-index:9999;' +
        'width:100vw;height:100vh;';
    document.body.appendChild(canvas);

    var ctx = canvas.getContext('2d');

    function resize() {
        canvas.width  = window.innerWidth;
        canvas.height = window.innerHeight;
    }
    resize();
    window.addEventListener('resize', resize);

    var shootingStars = [];
    var loopRunning   = false;

    function spawnShootingStar() {
        var maxDim = Math.min(window.innerWidth, window.innerHeight);
        // Position at a random radius from the viewport center
        var r = maxDim * (0.15 + Math.random() * 0.4);
        var theta = Math.random() * Math.PI * 2;
        
        // Exact 10 seconds for one full 360deg rotation (600 frames at 60fps)
        var totalFrames = 600;
        var omega = (2 * Math.PI) / totalFrames;
        
        // Clockwise rotation (matching the star field rotation path)
        var direction = 1;
        var dr = 0; // perfect circle
        
        // Noticeable trail length (45 frames)
        var maxHistory = 45;
        
        // Starting color hue
        var startHue = Math.random() * 360;

        shootingStars.push({
            r: r,
            theta: theta,
            omega: omega,
            direction: direction,
            dr: dr,
            frame: 0,
            totalFrames: totalFrames,
            history: [],
            maxHistory: maxHistory,
            hue: startHue
        });

        if (!loopRunning) {
            loopRunning = true;
            requestAnimationFrame(loop);
        }
    }

    function drawShootingStars() {
        // Dynamic viewport center
        var cx = window.innerWidth / 2;
        var cy = window.innerHeight / 2;

        for (var i = shootingStars.length - 1; i >= 0; i--) {
            var s = shootingStars[i];
            
            // Calculate current coordinate in orbital path around viewport center
            var x = cx + s.r * Math.cos(s.theta);
            var y = cy + s.r * Math.sin(s.theta);
            
            s.history.push({x: x, y: y});
            if (s.history.length > s.maxHistory) {
                s.history.shift();
            }

            var progress = s.frame / s.totalFrames;
            // Fade in over first 10%, fade out over last 15% of the circle
            var alpha = progress < 0.1
                ? progress / 0.1
                : progress > 0.85
                    ? (1 - progress) / 0.15
                    : 1;

            if (s.history.length > 1) {
                var tailStart = s.history[0];
                var tg = ctx.createLinearGradient(tailStart.x, tailStart.y, x, y);
                
                var hue1 = Math.floor(s.hue);
                var hue2 = Math.floor(s.hue + 25) % 360; // slight color shift along tail
                
                tg.addColorStop(0,    'hsla(' + hue1 + ', 100%, 65%, 0)');
                tg.addColorStop(0.5,  'hsla(' + hue1 + ', 100%, 65%, ' + (alpha * 0.35) + ')');
                tg.addColorStop(0.85, 'hsla(' + hue2 + ', 100%, 75%, ' + (alpha * 0.75) + ')');
                tg.addColorStop(1,    'hsla(' + hue2 + ', 100%, 90%, ' + alpha + ')');

                ctx.beginPath();
                ctx.strokeStyle = tg;
                ctx.lineWidth = 2.6; // Thicker, more noticeable line
                ctx.moveTo(tailStart.x, tailStart.y);
                for (var j = 1; j < s.history.length; j++) {
                    ctx.lineTo(s.history[j].x, s.history[j].y);
                }
                ctx.stroke();
            }

            var headHue = Math.floor(s.hue + 25) % 360;
            var hg = ctx.createRadialGradient(x, y, 0, x, y, 7);
            hg.addColorStop(0, 'rgba(255, 255, 255, ' + alpha + ')');
            hg.addColorStop(0.35, 'hsla(' + headHue + ', 100%, 80%, ' + (alpha * 0.8) + ')');
            hg.addColorStop(1, 'hsla(' + headHue + ', 100%, 60%, 0)');
            
            ctx.fillStyle = hg;
            ctx.beginPath();
            ctx.arc(x, y, 7, 0, Math.PI * 2);
            ctx.fill();

            // Shift HSL hue dynamically
            s.hue = (s.hue + 1) % 360;

            // Orbit updates (clockwise)
            s.theta += s.omega * s.direction;
            s.frame++;

            if (s.frame >= s.totalFrames) {
                shootingStars.splice(i, 1);
            }
        }
    }

    function loop() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        drawShootingStars();

        if (shootingStars.length > 0) {
            requestAnimationFrame(loop);
        } else {
            loopRunning = false; // pause until next star spawns
        }
    }

    // 3 per minute: first after 6 s, then ~20 s apart with jitter
    setTimeout(spawnShootingStar, 6000);

    (function scheduleNext() {
        var delay = 17000 + Math.random() * 6000;
        setTimeout(function () {
            spawnShootingStar();
            scheduleNext();
        }, delay);
    })();

})();
