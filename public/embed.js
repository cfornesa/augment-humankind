(function() {
  if (customElements.get("creatr-art-piece")) return;

  function loadScript(src) {
    return new Promise((resolve, reject) => {
      let script = document.querySelector(`script[src="${src}"]`);
      if (script) {
        if (script.dataset.loaded === "true") {
          resolve();
        } else {
          script.addEventListener("load", () => resolve());
          script.addEventListener("error", () => reject(new Error("Failed to load script " + src)));
        }
        return;
      }
      script = document.createElement("script");
      script.src = src;
      script.dataset.loaded = "false";
      script.addEventListener("load", () => {
        script.dataset.loaded = "true";
        resolve();
      });
      script.addEventListener("error", () => reject(new Error("Failed to load script " + src)));
      document.head.appendChild(script);
    });
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function ensureImportMap() {
    let map = document.querySelector('script[type="importmap"]');
    if (!map) {
      map = document.createElement('script');
      map.type = 'importmap';
      map.textContent = JSON.stringify({
        imports: {
          three: "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js"
        }
      });
      document.head.appendChild(map);
    }
  }

  // Web Component for Art Piece
  class CreatrArtPiece extends HTMLElement {
    constructor() {
      super();
      this.attachShadow({ mode: "open" });
      this.isRendered = false;
      this.cleanup = null;
    }

    connectedCallback() {
      // Remove fallback iframe if any
      const fallback = this.querySelector("iframe");
      if (fallback) fallback.remove();

      if (this.isRendered) return;

      // Intersection Observer to lazy load
      this.observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            this.observer.disconnect();
            this.loadAndRender();
          }
        });
      }, { rootMargin: "250px 0px" });

      this.observer.observe(this);
    }

    disconnectedCallback() {
      if (this.observer) this.observer.disconnect();
      if (this.cleanup) {
        try { this.cleanup(); } catch (e) {}
      }
    }

    async loadAndRender() {
      const pieceId = this.getAttribute("piece-id");
      const version = this.getAttribute("version");
      let origin = this.getAttribute("origin") || window.location.origin;

      // Render loading state
      this.shadowRoot.innerHTML = `
        <style>
          :host {
            display: block;
            position: relative;
            width: 100%;
            aspect-ratio: 16 / 9;
            min-height: 300px;
            overflow: hidden;
            background: #0a0a14;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            font-family: system-ui, -apple-system, sans-serif;
          }
          .loader {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            color: #a1a1aa;
            font-size: 14px;
          }
        </style>
        <div class="loader">Loading piece...</div>
      `;

      try {
        const res = await fetch(`${origin}/embed/pieces/${pieceId}/data${version ? `?version=${version}` : ""}`);
        if (!res.ok) throw new Error("Piece not found");
        const data = await res.json();
        await this.renderPiece(data);
      } catch (e) {
        console.error(e);
        this.shadowRoot.innerHTML = `
          <style>
            :host {
              display: block;
              position: relative;
              width: 100%;
              aspect-ratio: 16 / 9;
              min-height: 300px;
              overflow: hidden;
              background: #0a0a14;
              border-radius: 12px;
              border: 1px solid rgba(255, 255, 255, 0.1);
              font-family: system-ui, -apple-system, sans-serif;
            }
            .error {
              display: flex;
              align-items: center;
              justify-content: center;
              width: 100%;
              height: 100%;
              color: #a1a1aa;
              padding: 1rem;
              text-align: center;
            }
          </style>
          <div class="error">Interactive piece failed to load.</div>
        `;
      }
    }

    async renderPiece(data) {
      ensureImportMap();
      const { engine, generatedCode, htmlCode, cssCode, title, id } = data;
      const version = this.getAttribute("version");
      const defaultContainerId = engine === "three" ? "container" : "canvas-container";
      const immersiveHref = `/immersive/pieces/${id}${version ? `?version=${version}` : ""}`;

      this.shadowRoot.innerHTML = `
        <style>
          :host {
            display: block;
            position: relative;
            width: 100%;
            aspect-ratio: 16 / 9;
            min-height: 300px;
            overflow: hidden;
            background: #0a0a14;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            font-family: system-ui, -apple-system, sans-serif;
          }
          #stage-container {
            width: 100%;
            height: 100%;
            position: relative;
            overflow: hidden;
          }
          #${defaultContainerId} {
            width: 100%;
            height: 100%;
          }
          canvas {
            display: block;
            width: 100%;
            height: 100%;
          }
          .vr-btn {
            position: absolute;
            bottom: 16px;
            left: 16px;
            z-index: 100;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.55);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            color: #fff;
            padding: 6px 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            text-decoration: none;
            backdrop-filter: blur(4px);
            transition: background 0.2s, border-color 0.2s;
          }
          .vr-btn:hover {
            background: rgba(0, 0, 0, 0.8);
            border-color: #f7f2e8;
          }
          .vr-btn svg {
            width: 16px;
            height: 16px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
            margin-right: 6px;
          }
          ${cssCode || ""}
        </style>
        <div id="stage-container">
          ${htmlCode || `<div id="${defaultContainerId}"></div>`}
          <a href="${immersiveHref}" class="vr-btn" target="_parent">
            <svg viewBox="0 0 24 24">
              <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
              <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
              <line x1="12" y1="22.08" x2="12" y2="12" />
            </svg>
            <span>VR</span>
          </a>
        </div>
      `;

      const container = this.shadowRoot.getElementById("stage-container");

      function resolveSketchFactory(code) {
        const prev = window.sketch;
        try {
          try {
            const expression = new Function(`return (${code})`)();
            if (typeof expression === "function") return expression;
          } catch(e) {}
          window.sketch = undefined;
          new Function(code)();
          if (typeof window.sketch === "function") return window.sketch;
          throw new Error("Sketch function not found");
        } finally {
          window.sketch = prev;
        }
      }

      if (engine === "p5") {
        await loadScript(`https://cdnjs.cloudflare.com/ajax/libs/p5.js/1.9.0/p5.min.js`);
        const p5Container = this.shadowRoot.getElementById("canvas-container");
        const sketch = resolveSketchFactory(generatedCode);
        const p5Instance = new window.p5(sketch, p5Container);
        this.cleanup = () => {
          try { p5Instance.remove(); } catch(e) {}
        };
      } else if (engine === "c2") {
        await loadScript(`https://cdn.jsdelivr.net/npm/c2.js@1.0.9/dist/c2.min.js`);
        const c2Container = this.shadowRoot.getElementById("canvas-container") || container;
        let canvas = c2Container.querySelector("canvas");
        if (!canvas) {
          canvas = document.createElement("canvas");
          c2Container.appendChild(canvas);
        }
        canvas.style.display = "block";
        canvas.width = c2Container.clientWidth || 800;
        canvas.height = c2Container.clientHeight || 450;

        let rafId = 0;
        const stopFrame = () => cancelAnimationFrame(rafId);
        const startFrame = (handler) => {
          let count = 0;
          const tick = () => {
            count++;
            handler(count);
            rafId = requestAnimationFrame(tick);
          };
          rafId = requestAnimationFrame(tick);
          return stopFrame;
        };

        const sketch = resolveSketchFactory(generatedCode);
        sketch({ c2: window.c2, canvas, startFrame });

        const handleResize = () => {
          if (canvas && c2Container) {
            canvas.width = c2Container.clientWidth;
            canvas.height = c2Container.clientHeight;
          }
        };
        window.addEventListener("resize", handleResize);

        this.cleanup = () => {
          window.removeEventListener("resize", handleResize);
          stopFrame();
        };
      } else if (engine === "three") {
        const THREE = await import("https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js");
        const threeContainer = this.shadowRoot.getElementById("container") || container;
        let canvas = threeContainer.querySelector("canvas");
        if (!canvas) {
          canvas = document.createElement("canvas");
          threeContainer.appendChild(canvas);
        }
        canvas.style.cssText = "display:block;width:100%;height:100%;";
        // Force layout so clientWidth/clientHeight are non-zero before sketch runs
        threeContainer.getBoundingClientRect();
        const _cw = threeContainer.clientWidth || 800;
        const _ch = threeContainer.clientHeight || 450;
        canvas.width = _cw;
        canvas.height = _ch;

        const state = { scene: null, camera: null, renderer: null };
        let controls = null;
        let rafIds = [];

        function autoFit() {
          if (!state.scene || !state.camera) return;
          const box = new THREE.Box3();
          state.scene.traverse((obj) => {
            if (obj.isHelper || obj.isLight || obj.isCamera) return;
            if ((obj.isMesh || obj.isLine || obj.isPoints || obj.isSprite) && obj.geometry) {
              obj.geometry.computeBoundingBox?.();
              if (obj.geometry.boundingBox)
                box.union(obj.geometry.boundingBox.clone().applyMatrix4(obj.matrixWorld));
            }
          });
          if (box.isEmpty()) return;
          const center = new THREE.Vector3(); box.getCenter(center);
          const size = new THREE.Vector3();   box.getSize(size);
          const maxDim = Math.max(size.x, size.y, size.z) || 1;
          const fov = state.camera.fov * (Math.PI / 180);
          const dist = Math.abs(maxDim / 2 / Math.tan(fov / 2)) * 2.2;
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
          const amb = new THREE.AmbientLight(0xffffff, 0.7);
          amb.name = '__viewer_fallback_ambient__';
          state.scene.add(amb);
          const dir = new THREE.DirectionalLight(0xffffff, 0.8);
          dir.position.set(5, 10, 7.5);
          dir.name = '__viewer_fallback_dir__';
          state.scene.add(dir);
        }

        function startFrame(handler) {
          let count = 0;
          function tick() {
            count++;
            handler(count);
            if (count === 15) autoFit();
            const id = requestAnimationFrame(tick);
            rafIds.push(id);
          }
          const id = requestAnimationFrame(tick);
          rafIds.push(id);
          return () => { rafIds.forEach(cancelAnimationFrame); rafIds = []; };
        }

        const instrumentedThree = { ...THREE };
        instrumentedThree.Scene = class extends THREE.Scene {
          constructor() { super(); state.scene = this; }
        };
        instrumentedThree.PerspectiveCamera = class extends THREE.PerspectiveCamera {
          constructor(...args) { super(...args); state.camera = this; }
        };
        instrumentedThree.WebGLRenderer = class extends THREE.WebGLRenderer {
          constructor(params) {
            super({ ...(params || {}), canvas });
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

        const { OrbitControls } = await import("https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/controls/OrbitControls.js");

        const sketch = resolveSketchFactory(generatedCode);
        sketch({ THREE: instrumentedThree, canvas, startFrame });

        if (state.camera && canvas) {
          controls = new OrbitControls(state.camera, canvas);
          controls.enableDamping = true;
          controls.enablePan = true;
          const _camDir = new THREE.Vector3();
          state.camera.getWorldDirection(_camDir);
          const _camLen = state.camera.position.length();
          controls.target
            .copy(state.camera.position)
            .addScaledVector(_camDir, Math.max(_camLen * 0.8, 3));
          autoFit();
          controls.update();

          const animateControls = () => {
            const id = requestAnimationFrame(animateControls);
            rafIds.push(id);
            ensureFallbackLighting();
            controls.update();
            if (state.renderer && state.scene && state.camera)
              state.renderer.render(state.scene, state.camera);
          };
          animateControls();
        }

        const handleResize = () => {
          if (state.renderer && state.camera && canvas && threeContainer) {
            const width = threeContainer.clientWidth;
            const height = threeContainer.clientHeight;
            state.camera.aspect = width / height;
            state.camera.updateProjectionMatrix();
            state.renderer.setSize(width, height);
          }
        };
        window.addEventListener("resize", handleResize);
        // Run once immediately so the drawing buffer matches the container
        handleResize();

        this.cleanup = () => {
          window.removeEventListener("resize", handleResize);
          rafIds.forEach(cancelAnimationFrame);
          controls?.dispose();
          state.renderer?.dispose();
        };
      } else if (engine === "svg") {
        const svgEl = this.shadowRoot.querySelector("svg");
        if (svgEl) {
          window.svgRoot = svgEl;
        }
        const sketch = resolveSketchFactory(generatedCode);
        if (typeof sketch === "function") {
          try { sketch(); } catch (_) {}
        }
      }

      this.isRendered = true;
    }
  }

  // Web Component for Immersive Image Gallery
  class CreatrImmersiveImage extends HTMLElement {
    constructor() {
      super();
      this.attachShadow({ mode: "open" });
      this.isMounted = false;
    }

    connectedCallback() {
      if (this.isMounted) return;

      const iframe = this.querySelector("iframe");
      if (!iframe) return;

      const src = iframe.getAttribute("src") || "";
      const ref = this.getAttribute("ref") || src.match(/\/immersive\/images\/([^/?#]+)/)?.[1];
      if (!ref) return;

      const template = document.createElement("template");
      template.appendChild(iframe.cloneNode(true));
      iframe.remove();

      const title = this.getAttribute("title") || iframe.getAttribute("title") || "Immersive image";
      const safeTitle = escapeHtml(title);
      const immersiveHref = `/immersive/images/${ref}`;

      this.shadowRoot.innerHTML = `
        <style>
          :host {
            display: block;
            position: relative;
            width: 100%;
            aspect-ratio: 16 / 9;
            min-height: 300px;
            overflow: hidden;
            background: #0a0a14;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            font-family: system-ui, -apple-system, sans-serif;
          }
          .mount, iframe {
            width: 100% !important;
            height: 100% !important;
            border: none !important;
            display: block !important;
          }
          .placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            color: #a1a1aa;
            font-size: 14px;
          }
          .vr-btn {
            position: absolute;
            bottom: 16px;
            left: 16px;
            z-index: 100;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.55);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            color: #fff;
            padding: 6px 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            text-decoration: none;
            backdrop-filter: blur(4px);
          }
        </style>
        <div class="mount">
          <div class="placeholder">Scroll to load Image: ${safeTitle}...</div>
        </div>
        <a href="${immersiveHref}" class="vr-btn" target="_parent">VR</a>
      `;

      const mountDiv = this.shadowRoot.querySelector(".mount");
      this.observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            this.observer.disconnect();
            const realIframe = template.content.firstElementChild.cloneNode(true);
            realIframe.removeAttribute("loading");
            mountDiv.innerHTML = "";
            mountDiv.appendChild(realIframe);
            this.isMounted = true;
          }
        });
      }, { rootMargin: "250px 0px" });

      this.observer.observe(this);
    }

    disconnectedCallback() {
      if (this.observer) this.observer.disconnect();
    }
  }

  // Web Component for Exhibit Wall
  class CreatrExhibitWall extends HTMLElement {
    constructor() {
      super();
      this.attachShadow({ mode: "open" });
      this.isMounted = false;
    }

    connectedCallback() {
      if (this.isMounted) return;

      const iframe = this.querySelector("iframe");
      if (!iframe) return;

      // Extract details
      const src = iframe.getAttribute("src") || "";
      const slug = this.getAttribute("slug") || src.match(/\/immersive\/exhibits\/([^/?#]+)/)?.[1];
      if (!slug) return;

      // Move iframe to a template element to prevent loading
      const template = document.createElement("template");
      template.appendChild(iframe.cloneNode(true));
      iframe.remove();

      const title = this.getAttribute("title") || `Exhibit: ${slug}`;
      const safeTitle = escapeHtml(title);
      const immersiveHref = `/immersive/exhibits/${slug}`;

      this.shadowRoot.innerHTML = `
        <style>
          :host {
            display: block;
            position: relative;
            width: 100%;
            aspect-ratio: 16 / 9;
            min-height: 300px;
            overflow: hidden;
            background: #0a0a14;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            font-family: system-ui, -apple-system, sans-serif;
          }
          .mount {
            width: 100%;
            height: 100%;
          }
          ::slotted(iframe), iframe {
            width: 100% !important;
            height: 100% !important;
            border: none !important;
            display: block !important;
          }
          .placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            color: #a1a1aa;
            font-size: 14px;
          }
          .vr-btn {
            position: absolute;
            bottom: 16px;
            left: 16px;
            z-index: 100;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.55);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            color: #fff;
            padding: 6px 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            text-decoration: none;
            backdrop-filter: blur(4px);
            transition: background 0.2s, border-color 0.2s;
          }
          .vr-btn:hover {
            background: rgba(0, 0, 0, 0.8);
            border-color: #f7f2e8;
          }
          .vr-btn svg {
            width: 16px;
            height: 16px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
            margin-right: 6px;
          }
        </style>
        <div class="mount">
          <div class="placeholder">Scroll to load Exhibit: ${safeTitle}...</div>
        </div>
        <a href="${immersiveHref}" class="vr-btn" target="_parent">
          <svg viewBox="0 0 24 24">
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
            <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
            <line x1="12" y1="22.08" x2="12" y2="12" />
          </svg>
          <span>VR</span>
        </a>
      `;

      const mountDiv = this.shadowRoot.querySelector(".mount");
      const origin = this.getAttribute("origin") || window.location.origin;

      this.observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            this.observer.disconnect();
            fetch(`${origin}/api/exhibits/${encodeURIComponent(slug)}`, { headers: { Accept: "application/json" } })
              .then(res => {
                if (!res.ok) throw new Error("exhibit not found");
                return res.json();
              })
              .then(data => {
                const exhibit = data.exhibit;
                if (exhibit && exhibit.iframe_code) {
                  mountDiv.innerHTML = exhibit.iframe_code;
                  this.isMounted = true;
                  return;
                }
                const realIframe = template.content.firstElementChild.cloneNode(true);
                realIframe.removeAttribute("loading");
                // Remove static flag if any to make it render live in 3D
                let srcUrl = realIframe.getAttribute("src");
                if (srcUrl) {
                  try {
                    const url = new URL(srcUrl, origin);
                    url.searchParams.delete("static");
                    url.searchParams.set("embed", "1");
                    realIframe.setAttribute("src", url.toString());
                  } catch(e) {}
                }
                mountDiv.innerHTML = "";
                mountDiv.appendChild(realIframe);
                this.isMounted = true;
              })
              .catch(() => {
                mountDiv.innerHTML = `<div class="placeholder">${safeTitle} is no longer available.</div>`;
                this.isMounted = true;
              });
          }
        });
      }, { rootMargin: "250px 0px" });

      this.observer.observe(this);
    }

    disconnectedCallback() {
      if (this.observer) this.observer.disconnect();
    }
  }

  customElements.define("creatr-art-piece", CreatrArtPiece);
  customElements.define("creatr-immersive-image", CreatrImmersiveImage);
  customElements.define("creatr-exhibit-wall", CreatrExhibitWall);

  // Upgrade existing standard iframes inside blog posts to custom elements
  function upgradeIframes() {
    const iframes = document.querySelectorAll("iframe");
    iframes.forEach(iframe => {
      const src = iframe.getAttribute("src") || "";
      
      // Check for piece embeds
      if (src.includes("/embed/pieces/")) {
        const meta = extractPieceEmbedMeta(src);
        if (meta) {
          const piece = document.createElement("creatr-art-piece");
          piece.setAttribute("piece-id", meta.id);
          if (meta.versionId) piece.setAttribute("version", meta.versionId);
          piece.setAttribute("origin", window.location.origin);
          piece.style.width = "100%";
          piece.style.aspectRatio = "16 / 9";
          piece.style.display = "block";
          
          iframe.replaceWith(piece);
        }
      }
      
      // Check for exhibit embeds
      else if (src.includes("/immersive/exhibits/")) {
        const match = src.match(/\/immersive\/exhibits\/([^/?#]+)/);
        if (match) {
          const slug = match[1];
          const title = iframe.getAttribute("title") || `Exhibit: ${slug}`;
          const exhibit = document.createElement("creatr-exhibit-wall");
          exhibit.setAttribute("slug", slug);
          exhibit.setAttribute("title", title);
          exhibit.setAttribute("origin", window.location.origin);
          exhibit.style.width = "100%";
          exhibit.style.aspectRatio = "16 / 9";
          exhibit.style.display = "block";
          
          const iframeClone = iframe.cloneNode(true);
          exhibit.appendChild(iframeClone);
          
          iframe.replaceWith(exhibit);
        }
      }

      // Check for immersive image embeds
      else if (src.includes("/immersive/images/")) {
        const match = src.match(/\/immersive\/images\/([^/?#]+)/);
        if (match) {
          const image = document.createElement("creatr-immersive-image");
          image.setAttribute("ref", match[1]);
          image.setAttribute("title", iframe.getAttribute("title") || "Immersive image");
          image.setAttribute("origin", window.location.origin);
          image.style.width = "100%";
          image.style.aspectRatio = "16 / 9";
          image.style.display = "block";

          const iframeClone = iframe.cloneNode(true);
          image.appendChild(iframeClone);

          iframe.replaceWith(image);
        }
      }
    });
  }

  function extractPieceEmbedMeta(src) {
    try {
      const url = new URL(src, window.location.origin);
      const parts = url.pathname.split('/');
      const piecesIndex = parts.indexOf('pieces');
      if (piecesIndex === -1 || parts[piecesIndex - 1] !== 'embed') return null;
      const id = parseInt(parts[piecesIndex + 1], 10);
      if (isNaN(id) || id <= 0) return null;
      const versionRaw = url.searchParams.get("version");
      const versionId = versionRaw ? parseInt(versionRaw, 10) : null;
      return {
        id,
        versionId: versionId && !isNaN(versionId) && versionId > 0 ? versionId : null,
        pieceOrigin: url.origin
      };
    } catch (e) {
      return null;
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", upgradeIframes);
  } else {
    upgradeIframes();
  }
})();
