(function (global) {
  'use strict';

  var THREE_MODULE = '/assets/vendor/piece-runtime/three/three.module.js';
  var threePromise = null;

  function loadThree() {
    if (global.THREE && global.THREE.WebGLRenderer) return Promise.resolve(global.THREE);
    if (!threePromise) threePromise = import(THREE_MODULE);
    return threePromise;
  }

  function create(options) {
    options = options || {};
    var getSurface = options.getSurface;
    var getCameraVideo = typeof options.getCameraVideo === 'function' ? options.getCameraVideo : function () { return null; };
    var getCameraOpacity = typeof options.getCameraOpacity === 'function' ? options.getCameraOpacity : function () { return 0.35; };
    var interactive = options.interactive === true;
    var cameraPlacement = options.cameraPlacement === 'background' ? 'background' : 'overlay';
    var state = 'sleep';
    var shell = null;
    var wakePromise = null;
    var sleepPromise = null;
    var frameId = 0;
    var resizeObserver = null;
    var previousComposeCapture = global.__creatrComposeCapture;
    var parentPositionWasChanged = false;
    var blockedSurface = null;
    var blockedPointerEvents = '';
    var steeringEnabled = false;

    function disposeShell() {
      if (!shell) return;
      cancelAnimationFrame(frameId);
      resizeObserver && resizeObserver.disconnect();
      resizeObserver = null;
      var el = surface();
      if (el) {
        el.style.visibility = shell.previousVisibility;
        setInteractionBlocked(false);
      }
      try { shell.texture.dispose(); } catch (_e) {}
      disposeCameraPlane();
      try { shell.material.dispose(); } catch (_e) {}
      try { shell.plane.geometry.dispose(); } catch (_e) {}
      try { shell.renderer.dispose(); } catch (_e) {}
      try { shell.canvas.remove(); } catch (_e) {}
      if (parentPositionWasChanged && shell.parent) shell.parent.style.position = shell.previousParentPosition;
      parentPositionWasChanged = false;
      shell = null;
    }

    function disposeCameraPlane() {
      if (!shell) return;
      if (shell.cameraPlane) {
        try { shell.scene.remove(shell.cameraPlane); } catch (_e) {}
        try { shell.cameraPlane.geometry.dispose(); } catch (_e) {}
        try { shell.cameraPlane.material.dispose(); } catch (_e) {}
        shell.cameraPlane = null;
      }
      if (shell.videoTexture) {
        try { shell.videoTexture.dispose(); } catch (_e) {}
        shell.videoTexture = null;
      }
      shell.cameraVideo = null;
    }

    function surface() { return typeof getSurface === 'function' ? getSurface() : null; }

    function cancelAuthoredInput(el) {
      if (!el) return;
      ['pointerup', 'pointercancel'].forEach(function (type) {
        try { el.dispatchEvent(new PointerEvent(type, { bubbles: true, isPrimary: true, pointerType: 'touch', buttons: 0 })); } catch (_e) {}
      });
    }

    function setInteractionBlocked(blocked) {
      if (!interactive) return;
      var el = surface();
      if (!el) return;
      if (blocked) {
        cancelAuthoredInput(el);
        if (!blockedSurface) { blockedSurface = el; blockedPointerEvents = el.style.pointerEvents; }
        el.style.pointerEvents = 'none';
        el.dataset.creatrSpatialInteraction = 'suspended';
      } else {
        var restore = blockedSurface || el;
        restore.style.pointerEvents = blockedPointerEvents;
        delete restore.dataset.creatrSpatialInteraction;
        blockedSurface = null;
        blockedPointerEvents = '';
      }
    }

    function syncBox() {
      if (!shell) return;
      var el = surface();
      var parent = el && el.parentElement;
      if (!el || !parent) return;
      var rect = el.getBoundingClientRect();
      var parentRect = parent.getBoundingClientRect();
      shell.canvas.style.left = (rect.left - parentRect.left) + 'px';
      shell.canvas.style.top = (rect.top - parentRect.top) + 'px';
      shell.canvas.style.width = rect.width + 'px';
      shell.canvas.style.height = rect.height + 'px';
      var width = Math.max(1, Math.round(rect.width * Math.min(global.devicePixelRatio || 1, 2)));
      var height = Math.max(1, Math.round(rect.height * Math.min(global.devicePixelRatio || 1, 2)));
      shell.renderer.setSize(width, height, false);
      shell.camera.aspect = width / height;
      shell.camera.updateProjectionMatrix();
      shell.composite.width = Math.max(1, el.width || width);
      shell.composite.height = Math.max(1, el.height || height);
      shell.plane.scale.set(shell.composite.width / shell.composite.height, 1, 1);
      if (shell.cameraPlane) shell.cameraPlane.scale.copy(shell.plane.scale);
    }

    function syncCameraPlane(video, opacity) {
      if (!shell) return;
      if (!video || video.readyState < 2 || !video.videoWidth || !video.videoHeight) {
        disposeCameraPlane();
        return;
      }
      if (shell.cameraVideo !== video || !shell.cameraPlane) {
        disposeCameraPlane();
        var T = shell.T;
        var videoTexture = new T.VideoTexture(video);
        if (T.SRGBColorSpace) videoTexture.colorSpace = T.SRGBColorSpace;
        videoTexture.wrapS = T.RepeatWrapping;
        videoTexture.repeat.x = -1;
        videoTexture.offset.x = 1;
        var videoMaterial = new T.MeshBasicMaterial({
          map: videoTexture,
          transparent: true,
          opacity: opacity,
          side: T.DoubleSide,
          depthTest: false,
          depthWrite: false,
          toneMapped: false,
          fog: false
        });
        var cameraPlane = new T.Mesh(new T.PlaneGeometry(2, 2), videoMaterial);
        cameraPlane.position.z = cameraPlacement === 'background' ? -0.01 : 0.01;
        cameraPlane.renderOrder = cameraPlacement === 'background' ? -1 : 1;
        cameraPlane.frustumCulled = false;
        cameraPlane.scale.copy(shell.plane.scale);
        shell.scene.add(cameraPlane);
        shell.cameraVideo = video;
        shell.videoTexture = videoTexture;
        shell.cameraPlane = cameraPlane;
      } else {
        shell.cameraPlane.material.opacity = opacity;
      }
    }

    function drawSource(ctx, el, width, height) {
      if (el instanceof HTMLCanvasElement) {
        ctx.drawImage(el, 0, 0, width, height);
        return;
      }
      if (el instanceof SVGElement && shell.svgRaster) {
        ctx.drawImage(shell.svgRaster, 0, 0, width, height);
      }
    }

    function refreshSvg(el) {
      if (!shell || !(el instanceof SVGElement) || shell.svgPending) return;
      var now = performance.now();
      if (now - shell.svgUpdatedAt < 100) return;
      shell.svgPending = true;
      shell.svgUpdatedAt = now;
      try {
        var source = new XMLSerializer().serializeToString(el);
        var image = new Image();
        image.onload = function () { if (shell) shell.svgRaster = image; shell && (shell.svgPending = false); URL.revokeObjectURL(image.src); };
        image.onerror = function () { if (shell) shell.svgPending = false; URL.revokeObjectURL(image.src); };
        image.src = URL.createObjectURL(new Blob([source], { type: 'image/svg+xml' }));
      } catch (_e) { shell.svgPending = false; }
    }

    function renderFrame() {
      if (!shell || (state !== 'waking' && state !== 'active' && state !== 'sleeping')) return;
      var el = surface();
      var ctx = shell.context;
      var width = shell.composite.width;
      var height = shell.composite.height;
      refreshSvg(el);
      ctx.clearRect(0, 0, width, height);
      var video = getCameraVideo();
      var requestedOpacity = Number(getCameraOpacity());
      var opacity = Number.isFinite(requestedOpacity) ? requestedOpacity : 0.35;
      syncCameraPlane(video, opacity);
      drawSource(ctx, el, width, height);
      shell.texture.needsUpdate = true;
      shell.renderer.render(shell.scene, shell.camera);
      frameId = requestAnimationFrame(renderFrame);
    }

    async function wake() {
      if (state === 'active') return true;
      if (wakePromise) return wakePromise;
      wakePromise = (async function () {
        state = 'waking';
        var el = surface();
        if (!el || !el.parentElement) { state = 'sleep'; return false; }
        setInteractionBlocked(true);
        var T = await loadThree();
        var parent = el.parentElement;
        var parentStyle = global.getComputedStyle(parent);
        var previousParentPosition = parent.style.position;
        if (parentStyle.position === 'static') { parent.style.position = 'relative'; parentPositionWasChanged = true; }
        var canvas = document.createElement('canvas');
        canvas.dataset.creatrSpatialCanvas = '';
        canvas.style.cssText = 'position:absolute;z-index:6;display:block;pointer-events:none;background:#05070f;';
        parent.appendChild(canvas);
        var renderer = new T.WebGLRenderer({ canvas: canvas, antialias: true, alpha: false, preserveDrawingBuffer: true });
        renderer.setPixelRatio(1);
        var scene = new T.Scene();
        scene.background = new T.Color(0x05070f);
        var camera = new T.PerspectiveCamera(40, 1, 0.1, 100);
        camera.position.set(0, 0, 2.75);
        var target = new T.Vector3(0, 0, 0);
        camera.lookAt(target);
        var composite = document.createElement('canvas');
        var context = composite.getContext('2d', { alpha: true });
        var texture = new T.CanvasTexture(composite);
        texture.colorSpace = T.SRGBColorSpace || texture.colorSpace;
        var material = new T.MeshBasicMaterial({ map: texture, side: T.DoubleSide, transparent: true });
        var plane = new T.Mesh(new T.PlaneGeometry(2, 2), material);
        scene.add(plane);
        shell = {
          T: T, canvas: canvas, renderer: renderer, scene: scene, camera: camera, target: target,
          composite: composite, context: context, texture: texture, material: material, plane: plane,
          parent: parent, previousParentPosition: previousParentPosition,
          initialCamera: camera.position.clone(), initialTarget: target.clone(), previousVisibility: el.style.visibility,
          previousPointerEvents: null, svgRaster: null, svgPending: false, svgUpdatedAt: 0,
          cameraVideo: null, cameraPlane: null, videoTexture: null
        };
        syncBox();
        renderer.render(scene, camera);
        el.style.visibility = 'hidden';
        resizeObserver = new ResizeObserver(syncBox);
        resizeObserver.observe(el);
        resizeObserver.observe(parent);
        state = 'active';
        renderFrame();
        return true;
      })().catch(function () {
        disposeShell();
        setInteractionBlocked(false);
        state = 'sleep';
        return false;
      }).finally(function () { wakePromise = null; });
      return wakePromise;
    }

    function animateHome() {
      if (!shell) return Promise.resolve(false);
      var fromCamera = shell.camera.position.clone();
      var fromTarget = shell.target.clone();
      var started = performance.now();
      return new Promise(function (resolve) {
        function step(now) {
          if (!shell) { resolve(false); return; }
          var t = Math.min(1, (now - started) / 360);
          var eased = 1 - Math.pow(1 - t, 3);
          shell.camera.position.lerpVectors(fromCamera, shell.initialCamera, eased);
          shell.target.lerpVectors(fromTarget, shell.initialTarget, eased);
          shell.camera.lookAt(shell.target);
          if (t < 1) requestAnimationFrame(step); else resolve(true);
        }
        requestAnimationFrame(step);
      });
    }

    async function sleep() {
      if (state === 'sleep') return true;
      if (sleepPromise) return sleepPromise;
      sleepPromise = (async function () {
        state = 'sleeping';
        await animateHome();
        if (!shell) { state = 'sleep'; return true; }
        disposeShell();
        state = 'sleep';
        return true;
      })().finally(function () { sleepPromise = null; });
      return sleepPromise;
    }

    async function resetView() {
      if (!shell) return true;
      await animateHome();
      if (!steeringEnabled) {
        disposeShell();
        state = 'sleep';
        return true;
      }
      state = 'active';
      return true;
    }

    function command(input) {
      if (!steeringEnabled || !shell || state !== 'active' || !input) return;
      var T = shell.T;
      if (input.type === 'look') {
        var offset = shell.camera.position.clone().sub(shell.target);
        var spherical = new T.Spherical().setFromVector3(offset);
        var theta = (input.x - 0.5) * Math.PI * 1.2;
        var phi = Math.PI / 2 + (input.y - 0.5) * Math.PI * 0.55;
        spherical.theta += (theta - spherical.theta) * 0.1;
        spherical.phi += (phi - spherical.phi) * 0.1;
        spherical.phi = Math.max(0.2, Math.min(Math.PI - 0.2, spherical.phi));
        shell.camera.position.copy(shell.target).add(offset.setFromSpherical(spherical));
      } else if (input.type === 'orbit') {
        var orbitOffset = shell.camera.position.clone().sub(shell.target);
        var orbit = new T.Spherical().setFromVector3(orbitOffset);
        orbit.theta += input.yaw || 0; orbit.phi = Math.max(0.2, Math.min(Math.PI - 0.2, orbit.phi + (input.pitch || 0)));
        shell.camera.position.copy(shell.target).add(orbitOffset.setFromSpherical(orbit));
      } else if (input.type === 'travel') {
        var forward = new T.Vector3(); shell.camera.getWorldDirection(forward); forward.y = 0; if (forward.lengthSq() > 1e-6) forward.normalize();
        var right = new T.Vector3(-forward.z, 0, forward.x);
        var shift = forward.multiplyScalar((input.forward || 0) * 0.1).add(right.multiplyScalar((input.right || 0) * 0.08));
        shell.camera.position.add(shift); shell.target.add(shift);
      } else if (input.type === 'zoom') {
        var zoomOffset = shell.camera.position.clone().sub(shell.target);
        zoomOffset.setLength(Math.max(0.45, Math.min(20, zoomOffset.length() * (1 - (input.delta || 0)))));
        shell.camera.position.copy(shell.target).add(zoomOffset);
      }
      shell.camera.lookAt(shell.target);
    }

    global.__creatrComposeCapture = async function (input) {
      if (shell && state !== 'sleep') {
        shell.renderer.render(shell.scene, shell.camera);
        return shell.canvas;
      }
      return typeof previousComposeCapture === 'function' ? previousComposeCapture(input) : input;
    };

    return {
      setHandSteering: function (active) {
        steeringEnabled = !!active;
        return steeringEnabled ? wake() : Promise.resolve(true);
      },
      handPoint: function (x, y) { if (steeringEnabled && state === 'active') command({ type: 'look', x: x, y: y }); },
      handCommand: command,
      handLost: function () {},
      resetView: resetView,
      isSpatialActive: function () { return state !== 'sleep'; },
      dispose: function () { steeringEnabled = false; return sleep(); },
    };
  }

  global.CreatrSpatialPresentation = { create: create };
})(typeof window !== 'undefined' ? window : this);
