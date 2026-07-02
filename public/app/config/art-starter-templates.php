<?php

declare(strict_types=1);

return [
    [
        'template_key' => 'p5_default',
        'engine' => 'p5',
        'generation_mode' => 'p5',
        'label' => 'P5.js generative flow field',
        'description' => 'A p5 instance-mode particle field using p.random, p.noise, HSB color, and responsive drawing.',
        'html_code' => '<div id="canvas-container"></div>',
        'css_code' => '#canvas-container{width:100%;height:100%;background:#08090d;}#canvas-container canvas{display:block;width:100%!important;height:100%!important;}',
        'js_code' => <<<'JS'
window.sketch = (p) => {
  const particles = [];
  const palette = [];

  p.setup = () => {
    const parent = p._userNode || document.getElementById('canvas-container');
    const w = Math.max(320, parent?.clientWidth || p.windowWidth || 960);
    const h = Math.max(240, parent?.clientHeight || p.windowHeight || 540);
    p.createCanvas(w, h);
    p.colorMode(p.HSB, 360, 100, 100, 100);
    p.noiseDetail(3, 0.55);
    palette.push(p.color(188, 76, 88, 82), p.color(43, 82, 96, 86), p.color(321, 72, 86, 78), p.color(268, 56, 92, 72));
    resetParticles();
    p.background(228, 35, 7);
  };

  p.windowResized = () => {
    const parent = p._userNode || document.getElementById('canvas-container');
    p.resizeCanvas(Math.max(320, parent?.clientWidth || p.windowWidth), Math.max(240, parent?.clientHeight || p.windowHeight));
    resetParticles();
    p.background(228, 35, 7);
  };

  function resetParticles() {
    particles.length = 0;
    const count = Math.floor(p.map(Math.min(p.width, p.height), 240, 900, 90, 220, true));
    for (let i = 0; i < count; i++) {
      particles.push({
        x: p.random(p.width),
        y: p.random(p.height),
        speed: p.random(0.7, 2.4),
        radius: p.random(1.2, 3.8),
        hueShift: p.random(360),
        life: p.random(30, 180)
      });
    }
  }

  p.draw = () => {
    p.noStroke();
    p.fill(228, 35, 7, 7);
    p.rect(0, 0, p.width, p.height);
    particles.forEach((particle, i) => {
      const scale = 0.0026;
      const angle = p.noise(particle.x * scale, particle.y * scale, p.frameCount * 0.004) * p.TWO_PI * 2.2;
      particle.x += Math.cos(angle) * particle.speed;
      particle.y += Math.sin(angle) * particle.speed;
      particle.life -= 1;
      if (particle.x < -20 || particle.x > p.width + 20 || particle.y < -20 || particle.y > p.height + 20 || particle.life <= 0) {
        particle.x = p.random(p.width);
        particle.y = p.random(p.height);
        particle.life = p.random(80, 220);
      }
      const c = palette[i % palette.length];
      p.stroke((p.hue(c) + particle.hueShift + p.frameCount * 0.25) % 360, p.saturation(c), p.brightness(c), 48);
      p.strokeWeight(particle.radius);
      p.point(particle.x, particle.y);
    });
  };
};
JS,
    ],
    [
        'template_key' => 'c2_default',
        'engine' => 'c2',
        'generation_mode' => 'c2',
        'label' => 'C2.js harmonic geometry',
        'description' => 'A lightweight c2.js kinetic geometry study using renderer primitives and frame-count animation.',
        'html_code' => '<canvas id="piece-canvas"></canvas>',
        'css_code' => '#piece-canvas{width:100%;height:100%;background:#08101c;}',
        'js_code' => <<<'JS'
window.sketch = (runtime) => {
  const { c2, canvas, startFrame } = runtime;
  const renderer = new c2.Renderer(canvas);
  const nodes = Array.from({ length: 28 }, (_, i) => ({
    phase: i * 0.37,
    ring: 0.18 + (i % 7) * 0.045,
    hue: 186 + i * 5
  }));

  startFrame((frameCount) => {
    renderer.clear('#08101c');
    const cx = canvas.width / 2;
    const cy = canvas.height / 2;
    const unit = Math.min(canvas.width, canvas.height);
    renderer.lineWidth(2);
    nodes.forEach((node, i) => {
      const t = frameCount * 0.012 + node.phase;
      const r = unit * node.ring;
      const x = cx + Math.cos(t) * r;
      const y = cy + Math.sin(t * 1.7) * r * 0.62;
      const next = nodes[(i + 5) % nodes.length];
      const nt = frameCount * 0.012 + next.phase;
      const nx = cx + Math.cos(nt) * unit * next.ring;
      const ny = cy + Math.sin(nt * 1.7) * unit * next.ring * 0.62;
      renderer.stroke('hsla(' + node.hue + ',80%,62%,0.30)');
      renderer.line(x, y, nx, ny);
      renderer.fill('hsla(' + (node.hue + 28) + ',80%,58%,0.72)');
      renderer.circle(x, y, 8 + Math.sin(t * 2) * 3 + i * 0.18);
    });
    renderer.stroke('rgba(247,231,186,0.32)');
    renderer.lineWidth(3);
    renderer.ellipse(cx, cy, unit * 0.33, unit * 0.18);
  });
};
JS,
    ],
    [
        'template_key' => 'c2_interactive_default',
        'engine' => 'c2',
        'generation_mode' => 'c2_interactive',
        'label' => 'C2.js interactive constellation',
        'description' => 'A pointer-reactive c2.js scene using native canvas events and persistent animated marks.',
        'html_code' => '<canvas id="piece-canvas"></canvas>',
        'css_code' => '#piece-canvas{width:100%;height:100%;background:#0d0b18;touch-action:none;cursor:crosshair;}',
        'js_code' => <<<'JS'
window.sketch = (runtime) => {
  const { c2, canvas, startFrame } = runtime;
  const renderer = new c2.Renderer(canvas);
  const marks = [];
  const addMark = (event) => {
    const rect = canvas.getBoundingClientRect();
    const x = (event.clientX - rect.left) * (canvas.width / rect.width);
    const y = (event.clientY - rect.top) * (canvas.height / rect.height);
    marks.push({ x, y, phase: marks.length * 0.7, hue: 34 + marks.length * 19 });
    if (marks.length > 32) marks.shift();
  };
  canvas.addEventListener('pointerdown', addMark);
  canvas.addEventListener('pointermove', (event) => { if (event.buttons) addMark(event); });
  if (marks.length === 0) {
    marks.push({ x: canvas.width * 0.5, y: canvas.height * 0.5, phase: 0, hue: 194 });
  }

  startFrame((frameCount) => {
    renderer.clear('#0d0b18');
    renderer.lineWidth(2);
    for (let i = 0; i < marks.length; i++) {
      const mark = marks[i];
      const next = marks[(i + 1) % marks.length];
      renderer.stroke('rgba(117,183,216,0.34)');
      renderer.line(mark.x, mark.y, next.x, next.y);
    }
    marks.forEach((mark, i) => {
      const pulse = 22 + Math.sin(frameCount * 0.075 + mark.phase) * 9;
      renderer.stroke('hsla(' + mark.hue + ',85%,70%,0.82)');
      renderer.fill('hsla(' + (mark.hue + 22) + ',80%,52%,0.28)');
      renderer.circle(mark.x, mark.y, pulse + i * 0.4);
      renderer.fill('hsla(' + (mark.hue + 180) + ',85%,72%,0.78)');
      renderer.circle(mark.x, mark.y, 5 + Math.cos(frameCount * 0.1 + i) * 2);
    });
  });
};
JS,
    ],
    [
        'template_key' => 'three_default',
        'engine' => 'three',
        'generation_mode' => 'three',
        'label' => 'Three.js instanced sculpture',
        'description' => 'A Three.js scene that demonstrates the app runtime contract, lighting, OrbitControls compatibility, and InstancedMesh.',
        'html_code' => '<div id="container"></div>',
        'css_code' => '#container{width:100%;height:100%;background:#05070d;}#container canvas{display:block;width:100%;height:100%;}',
        'js_code' => <<<'JS'
window.sketch = (runtime) => {
  const { THREE, canvas, startFrame, width, height } = runtime;
  const scene = new THREE.Scene();
  scene.background = new THREE.Color(0x05070d);
  scene.fog = new THREE.Fog(0x05070d, 7, 18);

  const camera = new THREE.PerspectiveCamera(48, width / height, 0.1, 100);
  camera.position.set(0, 2.4, 7.5);
  camera.lookAt(0, 0, 0);

  const renderer = new THREE.WebGLRenderer({ canvas, antialias: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
  renderer.setSize(width, height, false);
  renderer.outputColorSpace = THREE.SRGBColorSpace;

  scene.add(new THREE.HemisphereLight(0xf7e7ba, 0x162238, 1.1));
  const key = new THREE.DirectionalLight(0xffffff, 1.8);
  key.position.set(4, 7, 5);
  scene.add(key);

  const core = new THREE.Mesh(
    new THREE.IcosahedronGeometry(1.05, 2),
    new THREE.MeshStandardMaterial({ color: 0xd99638, metalness: 0.2, roughness: 0.38, emissive: 0x311308 })
  );
  scene.add(core);

  const count = 96;
  const geometry = new THREE.BoxGeometry(0.12, 0.12, 0.7);
  const material = new THREE.MeshStandardMaterial({ color: 0x75b7d8, metalness: 0.15, roughness: 0.48 });
  const instanced = new THREE.InstancedMesh(geometry, material, count);
  const dummy = new THREE.Object3D();
  const color = new THREE.Color();
  const offsets = [];
  for (let i = 0; i < count; i++) {
    const theta = i * 2.399963;
    const y = -2.2 + 4.4 * (i / (count - 1));
    const radius = Math.sqrt(Math.max(0.05, 1 - (y / 2.4) * (y / 2.4))) * 2.25;
    offsets.push({ theta, y, radius, phase: i * 0.13 });
    color.setHSL(0.53 + i / count * 0.18, 0.7, 0.56);
    instanced.setColorAt(i, color);
  }
  scene.add(instanced);

  const ring = new THREE.Mesh(
    new THREE.TorusGeometry(2.35, 0.018, 8, 160),
    new THREE.MeshBasicMaterial({ color: 0xf1d28a })
  );
  ring.rotation.x = Math.PI * 0.5;
  scene.add(ring);

  const clock = new THREE.Clock();
  startFrame(() => {
    const t = clock.getElapsedTime();
    core.rotation.x = t * 0.35;
    core.rotation.y = t * 0.55;
    ring.rotation.z = t * 0.18;
    offsets.forEach((item, i) => {
      const orbit = item.theta + t * 0.35;
      dummy.position.set(Math.cos(orbit) * item.radius, item.y + Math.sin(t + item.phase) * 0.08, Math.sin(orbit) * item.radius);
      dummy.lookAt(0, item.y * 0.25, 0);
      dummy.rotateX(Math.PI * 0.5);
      const s = 0.75 + Math.sin(t * 1.6 + item.phase) * 0.22;
      dummy.scale.set(1, 1, s);
      dummy.updateMatrix();
      instanced.setMatrixAt(i, dummy.matrix);
    });
    instanced.instanceMatrix.needsUpdate = true;
    renderer.render(scene, camera);
  });
};
JS,
    ],
    [
        'template_key' => 'aframe_default',
        'engine' => 'aframe',
        'generation_mode' => 'aframe',
        'label' => 'A-Frame spatial gallery',
        'description' => 'A declarative A-Frame room with primitives, lights, cursor interaction, and looping component animations.',
        'html_code' => '<a-scene id="scene" embedded background="color: #060812"><a-entity position="0 1.6 4"><a-camera look-controls="magicWindowTrackingEnabled: false"><a-cursor color="#f1d28a" fuse="false"></a-cursor></a-camera></a-entity><a-entity light="type: ambient; intensity: 0.55; color: #f7e7ba"></a-entity><a-entity light="type: directional; intensity: 1.2; color: #ffffff" position="3 5 4"></a-entity><a-plane rotation="-90 0 0" width="9" height="9" color="#111827" material="roughness: 0.8; metalness: 0.1"></a-plane><a-torus id="focus-knot" position="0 1.55 -2.8" radius="0.72" radius-tubular="0.055" color="#d99638" material="metalness: 0.35; roughness: 0.28"></a-torus><a-box class="plinth" position="-1.8 0.55 -2.6" depth="0.7" height="1.1" width="0.7" color="#75b7d8"></a-box><a-sphere class="plinth" position="1.8 0.75 -2.9" radius="0.46" color="#d7438a"></a-sphere><a-ring position="0 1.55 -3.05" radius-inner="1.35" radius-outer="1.39" color="#f1d28a"></a-ring></a-scene>',
        'css_code' => '#scene{width:100%;height:100%;}.a-canvas{width:100%!important;height:100%!important;}',
        'js_code' => <<<'JS'
window.sketch = ({ scene }) => {
  const knot = scene.querySelector('#focus-knot');
  if (knot) {
    knot.setAttribute('animation__spin', 'property: rotation; to: 0 360 360; loop: true; dur: 9000; easing: linear');
    knot.setAttribute('animation__hover', 'property: position; dir: alternate; loop: true; dur: 2400; easing: easeInOutSine; to: 0 1.82 -2.8');
    knot.addEventListener('mouseenter', () => knot.setAttribute('scale', '1.2 1.2 1.2'));
    knot.addEventListener('mouseleave', () => knot.setAttribute('scale', '1 1 1'));
  }
  scene.querySelectorAll('.plinth').forEach((entity, i) => {
    entity.setAttribute('animation__turn', 'property: rotation; to: 0 ' + (360 + i * 120) + ' 0; loop: true; dur: ' + (7000 + i * 900) + '; easing: linear');
  });
};
JS,
    ],
    [
        'template_key' => 'svg_default',
        'engine' => 'svg',
        'generation_mode' => 'svg',
        'label' => 'SVG kinetic poster',
        'description' => 'A crisp animated SVG using gradients, masks, CSS transforms, and a small runtime hook.',
        'html_code' => '<svg viewBox="0 0 800 600" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%" role="img" aria-label="Animated SVG kinetic poster"><defs><radialGradient id="glow" cx="50%" cy="50%" r="50%"><stop offset="0%" stop-color="#f1d28a"/><stop offset="55%" stop-color="#d7438a"/><stop offset="100%" stop-color="#111827"/></radialGradient><linearGradient id="stroke" x1="0%" x2="100%" y1="0%" y2="100%"><stop offset="0%" stop-color="#75b7d8"/><stop offset="50%" stop-color="#f1d28a"/><stop offset="100%" stop-color="#d99638"/></linearGradient></defs><rect width="800" height="600" fill="#08090d"/><g class="orbit" transform="translate(400 300)"><ellipse rx="260" ry="96" fill="none" stroke="url(#stroke)" stroke-width="7"/><ellipse rx="190" ry="70" fill="none" stroke="#75b7d8" stroke-width="3" opacity=".65"/><circle r="78" fill="url(#glow)"/></g><g class="glyphs" fill="#f7e7ba"><circle cx="156" cy="160" r="16"/><rect x="600" y="128" width="42" height="42" rx="4"/><path d="M615 430l36 62h-72z"/></g></svg>',
        'css_code' => 'svg{display:block;width:100%;height:100%;background:#08090d}.orbit{transform-origin:400px 300px;animation:orbitSpin 14s linear infinite}.orbit circle{animation:corePulse 3s ease-in-out infinite alternate}.glyphs>*{transform-box:fill-box;transform-origin:center;animation:glyphFloat 4s ease-in-out infinite alternate}.glyphs>*:nth-child(2){animation-delay:.6s}.glyphs>*:nth-child(3){animation-delay:1.2s}@keyframes orbitSpin{to{transform:translate(400px,300px) rotate(360deg)}}@keyframes corePulse{to{r:96;opacity:.72}}@keyframes glyphFloat{to{transform:translateY(-18px) scale(1.12);opacity:.58}}',
        'js_code' => 'window.sketch = () => {};',
    ],
];
