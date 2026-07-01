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

  function ensureInlineVrStyles() {
    if (document.getElementById("creatr-inline-vr-image-styles")) return;
    const style = document.createElement("style");
    style.id = "creatr-inline-vr-image-styles";
    style.textContent = `
      .creatr-inline-vr-image {
        position: relative;
        max-width: 100%;
      }
      .creatr-inline-vr-image.is-shrink {
        display: table;
      }
      .creatr-inline-vr-image.is-fill {
        display: block;
        width: 100%;
      }
      .creatr-inline-vr-image > img,
      .creatr-inline-vr-image > picture,
      .creatr-inline-vr-image > a,
      .creatr-inline-vr-image > picture > img,
      .creatr-inline-vr-image > a > img,
      .creatr-inline-vr-image > a > picture,
      .creatr-inline-vr-image > a > picture > img {
        display: block;
        max-width: 100%;
      }
      .creatr-inline-vr-btn {
        position: absolute;
        left: 16px;
        bottom: 16px;
        z-index: 5;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        background: rgba(0, 0, 0, 0.55);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 9999px;
        color: #fff;
        padding: 6px 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        text-decoration: none;
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        transition: background 0.2s, border-color 0.2s;
      }
      .creatr-inline-vr-btn:hover {
        background: rgba(0, 0, 0, 0.8);
        border-color: #f7f2e8;
      }
      .creatr-inline-vr-btn svg {
        width: 16px;
        height: 16px;
        fill: none;
        stroke: currentColor;
        stroke-width: 2;
        stroke-linecap: round;
        stroke-linejoin: round;
      }
      @media (max-width: 600px) {
        .creatr-inline-vr-btn {
          left: 12px;
          bottom: 12px;
          padding: 5px 10px;
          font-size: 10px;
        }
      }
    `;
    document.head.appendChild(style);
  }

  function encodeImageRef(src) {
    const encoded = btoa(src).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/g, "");
    return encoded;
  }

  function normalizeImageSrc(src) {
    if (!src) return null;
    const parsed = parseEmbedUrl(src);
    if (!parsed) return null;
    if (/^(javascript|data|vbscript):/i.test(parsed.protocol)) return null;
    if (parsed.origin === window.location.origin) {
      return `${parsed.pathname}${parsed.search}`;
    }
    return parsed.href;
  }

  function nonEmpty(value) {
    const text = String(value ?? "").trim();
    return text === "" ? "" : text;
  }

  function truncateMetadata(value, maxLength = 240) {
    const text = nonEmpty(value);
    if (text === "") return "";
    return text.length > maxLength ? `${text.slice(0, maxLength - 1).trimEnd()}…` : text;
  }

  function extractImageMetadata(img) {
    const figure = img.closest("figure");
    const figcaption = figure?.querySelector("figcaption");
    const slide = img.closest("[data-carousel-slide]");
    const card = img.closest(".piece-card");
    const title = truncateMetadata(
      img.getAttribute("data-creatr-vr-title")
      || slide?.getAttribute("data-title")
      || card?.querySelector("h2")?.textContent
      || img.getAttribute("title")
      || figcaption?.textContent
      || img.getAttribute("alt")
    );
    const alt = truncateMetadata(
      img.getAttribute("data-creatr-vr-alt")
      || img.getAttribute("alt")
    );
    const caption = truncateMetadata(
      img.getAttribute("data-creatr-vr-caption")
      || slide?.getAttribute("data-caption")
      || figcaption?.textContent
    );
    const description = truncateMetadata(
      img.getAttribute("data-creatr-vr-description")
      || card?.querySelector("p")?.textContent
      || caption
      || alt
    );
    return { title, alt, caption, description };
  }

  function shouldUpgradePlainImage(img) {
    if (!(img instanceof HTMLImageElement)) return false;
    if (img.dataset.creatrVrProcessed === "true") return false;
    if (img.closest("creatr-immersive-image, creatr-art-piece, creatr-exhibit-wall, .creatr-inline-vr-image")) return false;
    if (img.closest(".blog-featured-image")) return false;
    if (img.closest(".blog-card-image, .blog-card, .blog-category-grid, .portfolio-grid-3, .exhibits-grid, .collection-grid, .collection-thumb-wrap, .collection-detail-thumb, .archive-grid, .archive-card")) return false;
    if (img.closest(".piece-card") && img.dataset.creatrVrEligible !== "true") return false;
    if (img.closest(".managed-section-body, .work-content-slide, .work-description, .work-placard-notes")) return true;
    if (img.classList.contains("work-image")) return true;
    if (img.dataset.creatrVrEligible === "true") return true;
    return false;
  }

  function getWrapTarget(img) {
    const anchor = img.parentElement instanceof HTMLAnchorElement ? img.parentElement : null;
    if (anchor) return anchor;
    const picture = img.parentElement instanceof HTMLPictureElement ? img.parentElement : null;
    if (picture) return picture;
    return img;
  }

  function buildImmersiveImageHref(src, metadata) {
    const ref = encodeImageRef(src);
    const params = new URLSearchParams();
    params.set("returnTo", window.location.pathname + window.location.search);
    if (metadata.alt) params.set("alt", metadata.alt);
    if (metadata.title) params.set("title", metadata.title);
    if (metadata.caption) params.set("caption", metadata.caption);
    if (metadata.description) params.set("description", metadata.description);
    return `/immersive/images/${ref}?${params.toString()}`;
  }

  function upgradePlainImages() {
    ensureInlineVrStyles();
    const images = document.querySelectorAll("img");
    images.forEach((img) => {
      if (!shouldUpgradePlainImage(img)) return;
      const normalizedSrc = normalizeImageSrc(img.currentSrc || img.getAttribute("src") || "");
      if (!normalizedSrc) return;

      const wrapTarget = getWrapTarget(img);
      if (!wrapTarget || !wrapTarget.parentNode) return;

      const metadata = extractImageMetadata(img);
      const wrapper = document.createElement("span");
      const isFillLayout = img.classList.contains("work-image") || img.dataset.creatrVrEligible === "true";
      wrapper.className = `creatr-inline-vr-image ${isFillLayout ? "is-fill" : "is-shrink"}`;

      const button = document.createElement("a");
      button.className = "creatr-inline-vr-btn";
      button.href = buildImmersiveImageHref(normalizedSrc, metadata);
      button.target = "_parent";
      button.innerHTML = `
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
          <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
          <line x1="12" y1="22.08" x2="12" y2="12"></line>
        </svg>
        <span>VR</span>
      `;
      const labelTitle = metadata.title || metadata.alt || "image";
      button.setAttribute("aria-label", `Open immersive view for ${labelTitle}`);

      wrapTarget.parentNode.insertBefore(wrapper, wrapTarget);
      wrapper.appendChild(wrapTarget);
      wrapper.appendChild(button);
      img.dataset.creatrVrProcessed = "true";
      wrapTarget.dataset.creatrVrProcessed = "true";
    });
  }

  function startEmbedEnhancementObserver() {
    if (window.__creatrEmbedEnhancementObserverStarted) return;
    window.__creatrEmbedEnhancementObserverStarted = true;

    const observer = new MutationObserver((mutations) => {
      let shouldRefresh = false;
      for (const mutation of mutations) {
        if (mutation.type !== "childList") continue;
        if (mutation.addedNodes.length === 0) continue;
        shouldRefresh = true;
        break;
      }
      if (!shouldRefresh) return;
      upgradeIframes();
      upgradePlainImages();
    });

    observer.observe(document.body, { childList: true, subtree: true });
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
      installFullscreenWrapperProtocol(this);
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
    document.addEventListener("DOMContentLoaded", () => {
      upgradeIframes();
      upgradePlainImages();
      startEmbedEnhancementObserver();
    });
  } else {
    upgradeIframes();
    upgradePlainImages();
    startEmbedEnhancementObserver();
  }
})();
