(function() {
  if (customElements.get("creatr-art-piece")) return;

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function parseEmbedUrl(src) {
    try {
      return new URL(src, window.location.origin);
    } catch (e) {
      return null;
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
            max-width: 100%;
            aspect-ratio: 16 / 9;
            min-height: 300px;
            overflow: hidden;
            background: #0a0a14;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            font-family: system-ui, -apple-system, sans-serif;
          }
          @media (max-width: 600px) {
            :host {
              min-height: 180px !important;
            }
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
              max-width: 100%;
              aspect-ratio: 16 / 9;
              min-height: 300px;
              overflow: hidden;
              background: #0a0a14;
              border-radius: 12px;
              border: 1px solid rgba(255, 255, 255, 0.1);
              font-family: system-ui, -apple-system, sans-serif;
            }
            @media (max-width: 600px) {
              :host {
                min-height: 180px !important;
              }
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
      const { title, id } = data;
      const version = this.getAttribute("version");
      const origin = this.getAttribute("origin") || window.location.origin;
      const returnTo = encodeURIComponent(window.location.pathname + window.location.search);
      const immersiveHref = `/immersive/pieces/${id}?${version ? `version=${version}&` : ""}returnTo=${returnTo}`;
      const embedSrc = `${origin}/embed/pieces/${id}${version ? `?version=${version}` : ""}`;

      this.shadowRoot.innerHTML = `
        <style>
          :host {
            display: block;
            position: relative;
            width: 100%;
            max-width: 100%;
            aspect-ratio: 16 / 9;
            min-height: 300px;
            overflow: hidden;
            background: #0a0a14;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            font-family: system-ui, -apple-system, sans-serif;
          }
          @media (max-width: 600px) {
            :host {
              min-height: 180px !important;
            }
          }
          #stage-container {
            width: 100%;
            height: 100%;
            position: relative;
            overflow: hidden;
          }
          iframe {
            display: block;
            width: 100%;
            height: 100%;
            border: 0;
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
        <div id="stage-container">
          <iframe src="${escapeHtml(embedSrc)}" title="${escapeHtml(title || "Art piece")}" loading="lazy" sandbox="allow-scripts allow-same-origin"></iframe>
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
      template.content.appendChild(iframe.cloneNode(true));
      iframe.remove();

      const title = this.getAttribute("title") || iframe.getAttribute("title") || "Immersive image";
      const safeTitle = escapeHtml(title);
      const immersiveHref = `/immersive/images/${ref}?returnTo=${encodeURIComponent(window.location.pathname + window.location.search)}`;

      this.shadowRoot.innerHTML = `
        <style>
          :host {
            display: block;
            position: relative;
            width: 100%;
            max-width: 100%;
            aspect-ratio: 16 / 9;
            min-height: 300px;
            overflow: hidden;
            background: #0a0a14;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            font-family: system-ui, -apple-system, sans-serif;
          }
          @media (max-width: 600px) {
            :host {
              min-height: 180px !important;
            }
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

  // iOS Safari has no Fullscreen API at all (not even webkit-prefixed — it
  // only ever existed for <video>), so the nested immersive iframe's own
  // requestFullscreen() always rejects there. It falls back to a CSS overlay
  // sized to itself, but that's only as big as this wrapper's host element —
  // cropped/wrong if the host page embeds it at less than full size. This
  // listener promotes the wrapper itself to a true viewport-filling overlay
  // on the host page instead, escaping any clipping/transformed ancestor
  // containers, and demotes it back to its original position on exit.
  function installFullscreenWrapperProtocol(el) {
    let restoreParent = null;
    let restoreNextSibling = null;

    function enterWrapperFullscreen() {
      if (el.classList.contains("creatr-fullscreen")) return;
      restoreParent = el.parentNode;
      restoreNextSibling = el.nextSibling;
      el.classList.add("creatr-fullscreen");
      if (el.parentNode !== document.body) {
        document.body.appendChild(el);
      }
    }

    function exitWrapperFullscreen() {
      if (!el.classList.contains("creatr-fullscreen")) return;
      el.classList.remove("creatr-fullscreen");
      if (restoreParent && el.parentNode !== restoreParent) {
        restoreParent.insertBefore(el, restoreNextSibling);
      }
      restoreParent = null;
      restoreNextSibling = null;
    }

    window.addEventListener("message", (e) => {
      if (!e.data || e.data.type !== "creatr-toggle-fullscreen") return;
      if (e.data.value) {
        enterWrapperFullscreen();
      } else {
        exitWrapperFullscreen();
      }
    });
  }

  // Web Component for Exhibit Wall
  class CreatrExhibitWall extends HTMLElement {
    constructor() {
      super();
      this.attachShadow({ mode: "open" });
      this.isMounted = false;
      installFullscreenWrapperProtocol(this);
    }

    connectedCallback() {
      if (this.isMounted) return;

      const iframe = this.querySelector("iframe");
      if (!iframe) return;

      // Extract details
      const src = iframe.getAttribute("src") || "";
      const slug = this.getAttribute("slug") || src.match(/\/immersive\/(?:exhibits|collections)\/([^/?#]+)/)?.[1];
      if (!slug) return;

      // Move iframe to a template element to prevent loading
      const template = document.createElement("template");
      template.content.appendChild(iframe.cloneNode(true));
      iframe.remove();

      const title = this.getAttribute("title") || `Exhibit: ${slug}`;
      const safeTitle = escapeHtml(title);
      const immersiveHref = `/immersive/collections/${slug}?returnTo=${encodeURIComponent(window.location.pathname + window.location.search)}`;

      this.shadowRoot.innerHTML = `
        <style>
          :host {
            display: block;
            position: relative;
            width: 100%;
            max-width: 100%;
            aspect-ratio: 16 / 9;
            min-height: 300px;
            overflow: hidden;
            background: #0a0a14;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            font-family: system-ui, -apple-system, sans-serif;
          }
          @media (max-width: 600px) {
            :host {
              min-height: 180px !important;
            }
          }
          /* Promoted to a direct child of document.body while fullscreen —
             escapes any clipping/transformed ancestor on the host page (the
             original iOS Safari bug: a CSS-only "fake fullscreen" overlay
             nested inside a transformed container gets cropped). */
          :host(.creatr-fullscreen) {
            position: fixed !important;
            inset: 0 !important;
            width: 100dvw !important;
            height: 100dvh !important;
            max-width: none !important;
            aspect-ratio: auto !important;
            min-height: 0 !important;
            border: none !important;
            border-radius: 0 !important;
            z-index: 2147483647 !important;
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
            fetch(`${origin}/api/collections/${encodeURIComponent(slug)}`, { headers: { Accept: "application/json" } })
              .then(res => {
                if (!res.ok) throw new Error("exhibit not found");
                return res.json();
              })
              .then(data => {
                const exhibit = data.collection;
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
      const parsed = parseEmbedUrl(src);
      const isSameOrigin = parsed ? parsed.origin === window.location.origin : false;
      
      // Check for piece embeds
      if (isSameOrigin && parsed?.pathname.includes("/embed/pieces/")) {
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
      else if (isSameOrigin && (parsed?.pathname.includes("/immersive/exhibits/") || parsed?.pathname.includes("/immersive/collections/"))) {
        const match = parsed.pathname.match(/\/immersive\/(?:exhibits|collections)\/([^/?#]+)/);
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
      else if (isSameOrigin && parsed?.pathname.includes("/immersive/images/")) {
        const match = parsed.pathname.match(/\/immersive\/images\/([^/?#]+)/);
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
