import { Editor, Extension, Node } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import Underline from '@tiptap/extension-underline'
import TextStyle from '@tiptap/extension-text-style'
import { Color } from '@tiptap/extension-color'
import Highlight from '@tiptap/extension-highlight'
import FontFamily from '@tiptap/extension-font-family'
import Link from '@tiptap/extension-link'
import Image from '@tiptap/extension-image'

// ─── Feature flags (set as body data attributes by the admin layout) ─────────

const aiFlags = document.body?.dataset || {}
const AI_CONTEXT = aiFlags.aiContext || 'pages'
const AI_TEXT_ENABLED = aiFlags.aiText !== '0'
const AI_ALT_ENABLED = aiFlags.aiAlt !== '0'
const AI_TEXT_MEDIA_ENABLED = aiFlags.aiTextMedia !== '0'

function mediaAiEnabledForKind(kind) {
  return kind === 'image' ? AI_ALT_ENABLED : kind === 'video' ? AI_TEXT_MEDIA_ENABLED : false
}

// ─── Custom: Font Size via TextStyle ─────────────────────────────────────────

const FontSize = Extension.create({
  name: 'fontSize',
  addOptions() { return { types: ['textStyle'] } },
  addGlobalAttributes() {
    return [{
      types: this.options.types,
      attributes: {
        fontSize: {
          default: null,
          parseHTML: el => el.style.fontSize?.replace(/px$/, '') || null,
          renderHTML: attrs => attrs.fontSize ? { style: `font-size:${attrs.fontSize}px` } : {},
        },
      },
    }]
  },
  addCommands() {
    return { setFontSize: size => ({ chain }) => chain().setMark('textStyle', { fontSize: size || null }).run() }
  },
})

const IFRAME_DEFAULTS = {
  width: '100%',
  height: '960',
  loading: 'lazy',
  allow: 'fullscreen',
  style: 'width:100%;display:block;',
  frameborder: '0',
  allowfullscreen: true,
}

const IFRAME_ATTRIBUTE_NAMES = [
  'src',
  'title',
  'width',
  'height',
  'loading',
  'allow',
  'sandbox',
  'style',
  'frameborder',
]

function buildIframeAttrs(overrides = {}) {
  const attrs = { ...IFRAME_DEFAULTS, ...overrides }
  attrs.src = typeof attrs.src === 'string' ? attrs.src.trim() : ''
  attrs.title = typeof attrs.title === 'string' ? attrs.title.trim() : null
  attrs.width = typeof attrs.width === 'string' && attrs.width.trim() ? attrs.width.trim() : IFRAME_DEFAULTS.width
  attrs.height = typeof attrs.height === 'string' && attrs.height.trim() ? attrs.height.trim() : IFRAME_DEFAULTS.height
  attrs.loading = typeof attrs.loading === 'string' && attrs.loading.trim() ? attrs.loading.trim() : IFRAME_DEFAULTS.loading
  attrs.allow = typeof attrs.allow === 'string' && attrs.allow.trim() ? attrs.allow.trim() : IFRAME_DEFAULTS.allow
  attrs.sandbox = typeof attrs.sandbox === 'string' && attrs.sandbox.trim() ? attrs.sandbox.trim() : null
  attrs.style = typeof attrs.style === 'string' && attrs.style.trim() ? attrs.style.trim() : IFRAME_DEFAULTS.style
  attrs.frameborder = typeof attrs.frameborder === 'string' && attrs.frameborder.trim() ? attrs.frameborder.trim() : IFRAME_DEFAULTS.frameborder
  attrs.allowfullscreen = attrs.allowfullscreen !== false
  return attrs
}

function serializeIframeAttrs(attrs) {
  const htmlAttrs = {}
  const richEmbedClass = 'rich-embed-frame'

  Object.entries(attrs).forEach(([key, value]) => {
    if (key === 'allowfullscreen') {
      if (value) htmlAttrs.allowfullscreen = ''
      return
    }

    if (value == null) return

    const stringValue = String(value).trim()
    if (stringValue !== '') {
      htmlAttrs[key] = stringValue
    }
  })

  const existingClass = typeof htmlAttrs.class === 'string' ? htmlAttrs.class.trim() : ''
  htmlAttrs.class = existingClass
    ? Array.from(new Set(`${existingClass} ${richEmbedClass}`.split(/\s+/))).join(' ')
    : richEmbedClass

  return htmlAttrs
}

function looksLikeIframeMarkup(raw) {
  return /<iframe\b/i.test(raw) || /<\/iframe>/i.test(raw)
}

function isIframeSourceUrl(raw) {
  if (!raw) return false
  if (raw.startsWith('/')) return true

  try {
    const url = new URL(raw)
    return url.protocol === 'http:' || url.protocol === 'https:'
  } catch {
    return false
  }
}

function parseIframeMarkup(raw) {
  const doc = new DOMParser().parseFromString(raw, 'text/html')
  const iframe = doc.body.querySelector('iframe')

  if (!iframe) {
    return {
      ok: false,
      message: 'This embed draft does not contain a readable `<iframe>` element yet.',
    }
  }

  const src = iframe.getAttribute('src')?.trim() || ''
  if (!src) {
    return {
      ok: false,
      message: 'This iframe draft is missing a usable `src` attribute. You can correct it below and try again.',
    }
  }

  const attrs = {}
  IFRAME_ATTRIBUTE_NAMES.forEach(name => {
    const value = iframe.getAttribute(name)
    if (value != null) attrs[name] = value
  })
  attrs.allowfullscreen = iframe.hasAttribute('allowfullscreen')

  return {
    ok: true,
    attrs: buildIframeAttrs(attrs),
  }
}

function normalizeIframeInput(raw) {
  const value = typeof raw === 'string' ? raw.trim() : ''
  if (!value) {
    return {
      ok: false,
      message: 'Enter an iframe URL or the full `<iframe …></iframe>` embed HTML.',
    }
  }

  if (looksLikeIframeMarkup(value)) {
    return parseIframeMarkup(value)
  }

  if (!isIframeSourceUrl(value)) {
    return {
      ok: false,
      message: 'That draft is not a valid iframe URL yet. Use `https://…`, `http://…`, `/path`, or complete iframe HTML.',
    }
  }

  return {
    ok: true,
    attrs: buildIframeAttrs({ src: value }),
  }
}

function extractIframePasteDraft(html, text) {
  const htmlValue = typeof html === 'string' ? html.trim() : ''
  const textValue = typeof text === 'string' ? text.trim() : ''

  if (looksLikeIframeMarkup(htmlValue)) {
    const doc = new DOMParser().parseFromString(htmlValue, 'text/html')
    const iframe = doc.body.querySelector('iframe')
    return iframe?.outerHTML || htmlValue
  }

  if (looksLikeIframeMarkup(textValue)) {
    return textValue
  }

  return null
}

// ─── Custom: Iframe block ─────────────────────────────────────────────────────

const IframeNode = Node.create({
  name: 'iframe',
  group: 'block',
  atom: true,
  addAttributes() {
    return {
      src: { default: null },
      title: { default: null },
      width: { default: IFRAME_DEFAULTS.width },
      height: { default: IFRAME_DEFAULTS.height },
      loading: { default: IFRAME_DEFAULTS.loading },
      allow: { default: IFRAME_DEFAULTS.allow },
      sandbox: { default: null },
      style: { default: IFRAME_DEFAULTS.style },
      frameborder: { default: IFRAME_DEFAULTS.frameborder },
      allowfullscreen: {
        default: true,
        parseHTML: element => element.hasAttribute('allowfullscreen'),
      },
    }
  },
  parseHTML() { return [{ tag: 'iframe[src]' }] },
  renderHTML({ HTMLAttributes }) {
    return ['iframe', serializeIframeAttrs(buildIframeAttrs(HTMLAttributes))]
  },
  addNodeView() {
    return ({ node }) => {
      const wrap = document.createElement('div')
      const label = document.createElement('span')
      const meta = document.createElement('strong')
      const details = document.createElement('span')

      wrap.className = 'tiptap-iframe-preview'
      wrap.dataset.iframeSrc = node.attrs.src
      label.textContent = '⬛ iFrame'
      meta.textContent = node.attrs.title || node.attrs.src || 'Embed draft'
      details.textContent = node.attrs.title ? node.attrs.src : 'Rich-text embed'

      wrap.appendChild(label)
      wrap.appendChild(meta)
      wrap.appendChild(details)

      return { dom: wrap }
    }
  },
  addCommands() {
    return { setIframe: attrs => ({ commands }) => commands.insertContent({ type: this.name, attrs }) }
  },
})

// ─── Custom: Video block ─────────────────────────────────────────────────────
// Mirrors how images are inserted: src + a description attribute rendered as
// aria-label (the video equivalent of alt text — never AI-generated from
// scratch, only refined from text the admin already wrote).

const VideoNode = Node.create({
  name: 'video',
  group: 'block',
  atom: true,
  addAttributes() {
    return {
      src: { default: null },
      description: {
        default: null,
        parseHTML: el => el.getAttribute('aria-label'),
        renderHTML: attrs => attrs.description ? { 'aria-label': attrs.description } : {},
      },
    }
  },
  parseHTML() { return [{ tag: 'video[src]' }] },
  renderHTML({ HTMLAttributes }) {
    return ['video', { controls: 'true', style: 'max-width:100%;display:block;', ...HTMLAttributes }]
  },
  addCommands() {
    return { setVideo: attrs => ({ commands }) => commands.insertContent({ type: this.name, attrs }) }
  },
})

// ─── Custom: Content Card block ──────────────────────────────────────────────
// Teaches Tiptap about <div class="content-card"> so it survives round-trips
// between WYSIWYG and HTML source mode without being stripped.

const ContentCardNode = Node.create({
  name: 'contentCard',
  group: 'block',
  content: 'block+',
  defining: true,
  parseHTML() {
    return [{ tag: 'div.content-card' }]
  },
  renderHTML() {
    return ['div', { class: 'content-card' }, 0]
  },
})

// ─── Custom: Link with title attribute ───────────────────────────────────────

const LinkWithTitle = Link.extend({
  addAttributes() {
    return {
      ...this.parent?.(),
      title: {
        default: null,
        parseHTML: el => el.getAttribute('title'),
        renderHTML: attrs => attrs.title ? { title: attrs.title } : {},
      },
      target: {
        default: null,
        parseHTML: el => el.getAttribute('target'),
        renderHTML: attrs => attrs.target ? { target: attrs.target } : {},
      },
      alt: {
        default: null,
        parseHTML: el => el.getAttribute('alt'),
        renderHTML: attrs => attrs.alt ? { alt: attrs.alt } : {},
      },
    }
  },
}).configure({ openOnClick: false })

// ─── Custom: Image with NodeView edit button ──────────────────────────────────
// The NodeView wraps the image in a relative-positioned container and overlays
// a pencil icon at the bottom-right corner. Appears on hover, never in HTML output.

function makeImageWithEditButton() {
  return Image.extend({
    addNodeView() {
      return ({ node: initialNode, getPos, editor }) => {
        // editor is the real Tiptap instance — provided by Tiptap as a NodeView param
        let currentNode = initialNode

        // Wrapper
        const wrap = document.createElement('div')
        wrap.className = 'tiptap-image-wrap'

        // The image — draggable=false prevents the browser from starting a native
        // HTML drag when the user tries to drag-select nearby text.
        const img = document.createElement('img')
        img.style.maxWidth = '100%'
        img.style.display = 'block'
        img.draggable = false
        img.src = currentNode.attrs.src || ''
        img.alt = currentNode.attrs.alt || ''
        img.addEventListener('dragstart', e => e.preventDefault())
        wrap.appendChild(img)

        // Pencil icon button — bottom-right corner
        const editBtn = document.createElement('button')
        editBtn.type = 'button'
        editBtn.className = 'tiptap-img-edit-btn'
        editBtn.setAttribute('aria-label', 'Edit image alt text')
        editBtn.innerHTML = `<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>`
        wrap.appendChild(editBtn)

        // Alt text popover — two-row layout:
        //   Row 1: label | input | Save | ✕
        //   Row 2: delete link (visually separated and less prominent)
        const popover = document.createElement('div')
        popover.className = 'tiptap-img-popover'
        popover.hidden = true

        // Row 1
        const row1 = document.createElement('div')
        row1.className = 'tiptap-img-popover-row'

        const altLabel = document.createElement('span')
        altLabel.className = 'tiptap-edit-label'
        altLabel.textContent = 'Alt text'

        const altInput = document.createElement('input')
        altInput.type = 'text'
        altInput.className = 'tiptap-edit-input'
        altInput.placeholder = 'Describe this image for screen readers'
        altInput.maxLength = 250

        const aiAltBtn = document.createElement('button')
        aiAltBtn.type = 'button'
        aiAltBtn.className = 'admin-btn admin-btn-sm admin-btn-ghost'
        aiAltBtn.title = 'Generate alt text with AI (requires vision-capable profile)'
        aiAltBtn.textContent = '✨'

        aiAltBtn.addEventListener('click', e => {
          e.preventDefault(); e.stopPropagation()
          const imgSrc = currentNode?.attrs?.src
          if (!imgSrc) { return }
          window.openAiProfilePicker(async selection => {
            if (!selection?.profileId) return
            aiAltBtn.disabled = true
            aiAltBtn.textContent = '…'
            try {
              const fd = new FormData()
              fd.append('profile_id', selection.profileId)
              if (selection.personaId) fd.append('persona_id', selection.personaId)
              fd.append('image_url', imgSrc)
              if (altInput.value.trim()) fd.append('existing_alt_text', altInput.value.trim())
              const res = await fetch('/admin/ai/describe-image', { method: 'POST', body: fd })
              const data = await res.json()
              if (data.result) {
                altInput.value = data.result
                aiAltBtn.textContent = '✓'
                setTimeout(() => { aiAltBtn.textContent = '✨'; aiAltBtn.disabled = false }, 1500)
              } else {
                alert('Alt text generation failed: ' + (data.error || 'Unknown error'))
                aiAltBtn.textContent = '✨'
                aiAltBtn.disabled = false
              }
            } catch (err) {
              alert('Error: ' + err.message)
              aiAltBtn.textContent = '✨'
              aiAltBtn.disabled = false
            }
          }, { capability: 'vision', title: 'Generate Alt Text with AI', taskKey: 'alt-text' })
        })

        const updateBtn = document.createElement('button')
        updateBtn.type = 'button'
        updateBtn.className = 'admin-btn admin-btn-sm'
        updateBtn.textContent = 'Save'

        const closePopoverBtn = document.createElement('button')
        closePopoverBtn.type = 'button'
        closePopoverBtn.className = 'tiptap-img-popover-close'
        closePopoverBtn.setAttribute('aria-label', 'Close')
        closePopoverBtn.textContent = '✕'

        row1.appendChild(altLabel)
        row1.appendChild(altInput)
        if (AI_ALT_ENABLED) row1.appendChild(aiAltBtn)
        row1.appendChild(updateBtn)
        row1.appendChild(closePopoverBtn)

        // Row 2 — destructive action, visually distinct
        const row2 = document.createElement('div')
        row2.className = 'tiptap-img-popover-row tiptap-img-popover-row-danger'

        const removeBtn = document.createElement('button')
        removeBtn.type = 'button'
        removeBtn.className = 'tiptap-img-delete-btn'
        removeBtn.textContent = 'Delete image from editor'

        row2.appendChild(removeBtn)

        popover.appendChild(row1)
        popover.appendChild(row2)
        wrap.appendChild(popover)

        let popoverOpen = false

        function openPopover(e) {
          if (e) { e.preventDefault(); e.stopPropagation() }
          popoverOpen = true
          popover.hidden = false
          editBtn.classList.add('is-open')
          altInput.value = currentNode.attrs.alt || ''
          setTimeout(() => altInput.focus(), 0)
        }
        function closePopover() {
          popoverOpen = false
          popover.hidden = true
          editBtn.classList.remove('is-open')
        }

        editBtn.addEventListener('click', e => {
          e.preventDefault(); e.stopPropagation()
          popoverOpen ? closePopover() : openPopover(e)
        })

        closePopoverBtn.addEventListener('click', e => {
          e.preventDefault(); e.stopPropagation()
          closePopover()
        })

        updateBtn.addEventListener('click', e => {
          e.preventDefault(); e.stopPropagation()
          const pos = typeof getPos === 'function' ? getPos() : null
          if (pos != null) {
            // Dispatch directly via setNodeMarkup — reliable regardless of current selection
            const newAttrs = { ...currentNode.attrs, alt: altInput.value.trim() }
            editor.view.dispatch(editor.view.state.tr.setNodeMarkup(pos, null, newAttrs))
          }
          closePopover()
        })

        removeBtn.addEventListener('click', e => {
          e.preventDefault(); e.stopPropagation()
          if (!confirm('Remove this image from the editor?')) return
          const pos = typeof getPos === 'function' ? getPos() : null
          if (pos != null) {
            editor.view.dispatch(
              editor.view.state.tr.delete(pos, pos + currentNode.nodeSize)
            )
          }
        })

        altInput.addEventListener('keydown', e => {
          if (e.key === 'Enter') { e.preventDefault(); updateBtn.click() }
          if (e.key === 'Escape') { e.preventDefault(); closePopover() }
        })

        // Use mousedown (not click) so that drag-to-select text also closes the
        // popover — a drag produces mousedown+mouseup but no click event.
        const outsideHandler = e => { if (popoverOpen && !wrap.contains(e.target)) closePopover() }
        document.addEventListener('mousedown', outsideHandler)

        return {
          dom: wrap,
          update(updatedNode) {
            if (updatedNode.type.name !== 'image') return false
            currentNode = updatedNode
            img.src = updatedNode.attrs.src || ''
            img.alt = updatedNode.attrs.alt || ''
            return true
          },
          destroy() {
            document.removeEventListener('mousedown', outsideHandler)
          },
          stopEvent(e) {
            // Only intercept events on the edit button and popover;
            // let all other events (including mousedown for text selection) pass through.
            return !!e.target.closest?.('.tiptap-img-edit-btn, .tiptap-img-popover')
          },
          ignoreMutation: () => true,
        }
      }
    }
  })
}

// ─── Toolbar helpers ──────────────────────────────────────────────────────────

const FONT_FAMILIES = [
  ['', 'Default'],
  ['Georgia, serif', 'Georgia'],
  ['Arial, Helvetica, sans-serif', 'Sans-serif'],
  ['ui-monospace, "Courier New", monospace', 'Monospace'],
]

function icon(svg) {
  const b = document.createElement('button'); b.type = 'button'; b.className = 'tt-btn'; b.innerHTML = svg; return b
}
function sep() {
  const s = document.createElement('span'); s.className = 'tiptap-toolbar-sep'; return s
}

// ─── Init single Tiptap instance ─────────────────────────────────────────────

function initTiptap(textarea) {
  textarea.style.display = 'none'
  textarea.removeAttribute('required')

  const ImageWithEditButton = makeImageWithEditButton()

  const wrap = document.createElement('div')
  wrap.className = 'tiptap-wrap'

  const editorDiv = document.createElement('div')
  editorDiv.className = 'tiptap-editor'

  const sourceTa = document.createElement('textarea')
  sourceTa.className = 'tiptap-source'
  sourceTa.setAttribute('aria-label', 'HTML source')

  const iframeNotice = document.createElement('div')
  iframeNotice.className = 'tiptap-embed-notice'
  iframeNotice.hidden = true
  iframeNotice.setAttribute('aria-live', 'polite')

  const iframeNoticeText = document.createElement('p')
  iframeNoticeText.className = 'tiptap-embed-notice-text'

  const iframeDraft = document.createElement('textarea')
  iframeDraft.className = 'tiptap-embed-draft'
  iframeDraft.setAttribute('aria-label', 'Recoverable iframe draft')

  const iframeNoticeActions = document.createElement('div')
  iframeNoticeActions.className = 'tiptap-embed-notice-actions'

  const iframeNoticeApply = document.createElement('button')
  iframeNoticeApply.type = 'button'
  iframeNoticeApply.className = 'admin-btn admin-btn-sm'
  iframeNoticeApply.textContent = 'Open HTML Source With Draft'

  const iframeNoticeClear = document.createElement('button')
  iframeNoticeClear.type = 'button'
  iframeNoticeClear.className = 'admin-btn admin-btn-ghost admin-btn-sm'
  iframeNoticeClear.textContent = 'Clear Notice'

  iframeNoticeActions.appendChild(iframeNoticeApply)
  iframeNoticeActions.appendChild(iframeNoticeClear)
  iframeNotice.appendChild(iframeNoticeText)
  iframeNotice.appendChild(iframeDraft)
  iframeNotice.appendChild(iframeNoticeActions)

  // ── Floating link trigger + popover (appended to body, position:fixed) ──────
  let activeLinkEl = null
  const linkTrigger = document.createElement('button')
  linkTrigger.type = 'button'
  linkTrigger.className = 'tiptap-link-trigger'
  linkTrigger.setAttribute('aria-label', 'Edit link')
  linkTrigger.innerHTML = `<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>`
  linkTrigger.hidden = true
  document.body.appendChild(linkTrigger)

  const linkPopover = document.createElement('div')
  linkPopover.className = 'tiptap-link-popover'
  linkPopover.hidden = true

  const linkHrefLabel = document.createElement('span'); linkHrefLabel.className = 'tiptap-edit-label'; linkHrefLabel.textContent = 'URL'
  const linkHrefInput = document.createElement('input'); linkHrefInput.type = 'text'; linkHrefInput.className = 'tiptap-edit-input'; linkHrefInput.placeholder = '/path or https://…'

  const linkAltLabel = document.createElement('span'); linkAltLabel.className = 'tiptap-edit-label'; linkAltLabel.textContent = 'Alt'
  const linkAltInput = document.createElement('input'); linkAltInput.type = 'text'; linkAltInput.className = 'tiptap-edit-input'; linkAltInput.placeholder = 'Alt text (optional)'

  const linkTitleLabel = document.createElement('span'); linkTitleLabel.className = 'tiptap-edit-label'; linkTitleLabel.textContent = 'Title'
  const linkTitleInput = document.createElement('input'); linkTitleInput.type = 'text'; linkTitleInput.className = 'tiptap-edit-input'; linkTitleInput.placeholder = 'Description (optional)'

  const linkTargetLabel = document.createElement('span'); linkTargetLabel.className = 'tiptap-edit-label'; linkTargetLabel.textContent = 'Target'
  const linkTargetSelect = document.createElement('select'); linkTargetSelect.className = 'tiptap-edit-input'
  const optSelf = document.createElement('option'); optSelf.value = '_self'; optSelf.textContent = 'Same tab (_self)'
  const optBlank = document.createElement('option'); optBlank.value = '_blank'; optBlank.textContent = 'New tab (_blank)'
  linkTargetSelect.appendChild(optSelf)
  linkTargetSelect.appendChild(optBlank)

  const linkUpdateBtn = document.createElement('button'); linkUpdateBtn.type = 'button'; linkUpdateBtn.className = 'admin-btn admin-btn-sm'; linkUpdateBtn.textContent = 'Update'
  const linkRemoveBtn = document.createElement('button'); linkRemoveBtn.type = 'button'; linkRemoveBtn.className = 'admin-btn admin-btn-ghost admin-btn-sm'; linkRemoveBtn.textContent = 'Remove'

  const r1 = document.createElement('div'); r1.className = 'tiptap-link-popover-row'; r1.appendChild(linkHrefLabel); r1.appendChild(linkHrefInput)
  const r2 = document.createElement('div'); r2.className = 'tiptap-link-popover-row'; r2.appendChild(linkAltLabel); r2.appendChild(linkAltInput)
  const r3 = document.createElement('div'); r3.className = 'tiptap-link-popover-row'; r3.appendChild(linkTitleLabel); r3.appendChild(linkTitleInput)
  const r4 = document.createElement('div'); r4.className = 'tiptap-link-popover-row'; r4.appendChild(linkTargetLabel); r4.appendChild(linkTargetSelect)
  const r5 = document.createElement('div'); r5.className = 'tiptap-link-popover-actions'; r5.appendChild(linkUpdateBtn); r5.appendChild(linkRemoveBtn)

  linkPopover.appendChild(r1)
  linkPopover.appendChild(r2)
  linkPopover.appendChild(r3)
  linkPopover.appendChild(r4)
  linkPopover.appendChild(r5)
  document.body.appendChild(linkPopover)

  let linkPopoverOpen = false

  function openLinkPopover(anchorEl) {
    const rect = anchorEl.getBoundingClientRect()
    linkPopover.style.left = rect.left + 'px'
    linkPopover.style.top  = (rect.bottom + 4) + 'px'
    
    let href = ''
    let title = ''
    let alt = ''
    let target = '_self'

    if (activeLinkEl) {
      href = activeLinkEl.getAttribute('href') || ''
      title = activeLinkEl.getAttribute('title') || ''
      alt = activeLinkEl.getAttribute('alt') || ''
      target = activeLinkEl.getAttribute('target') || '_self'
    } else {
      const attrs = editor.getAttributes('link')
      href  = attrs.href  || ''
      title = attrs.title || ''
      alt   = attrs.alt   || ''
      target = attrs.target || '_self'
    }

    linkHrefInput.value  = href
    linkTitleInput.value = title
    linkAltInput.value   = alt
    linkTargetSelect.value = target
    linkPopover.hidden = false
    linkPopoverOpen = true
    linkHrefInput.focus()
  }
  function closeLinkPopover() {
    linkPopover.hidden = true
    linkPopoverOpen = false
  }

  function positionLinkTrigger() {
    try {
      const { from } = editor.state.selection
      // Walk from cursor position upward in the DOM to find the <a> element
      const domInfo = editor.view.domAtPos(from)
      let node = domInfo.node
      if (node.nodeType === 3) node = node.parentNode // text node → parent
      while (node && node.tagName !== 'A' && node !== editorDiv) node = node.parentNode
      if (!node || node.tagName !== 'A') { linkTrigger.hidden = true; activeLinkEl = null; return }
      activeLinkEl = node
      const rect = node.getBoundingClientRect()
      linkTrigger.style.left = (rect.right + 3) + 'px'
      linkTrigger.style.top  = (rect.top + (rect.height - 20) / 2) + 'px'
      linkTrigger.hidden = false
    } catch {
      linkTrigger.hidden = true
      activeLinkEl = null
    }
  }

  linkTrigger.addEventListener('click', e => {
    e.stopPropagation()
    if (linkPopoverOpen) { closeLinkPopover(); return }
    openLinkPopover(linkTrigger)
  })

  linkUpdateBtn.addEventListener('click', () => {
    const href   = linkHrefInput.value.trim()
    const title  = linkTitleInput.value.trim() || null
    const alt    = linkAltInput.value.trim() || null
    const target = linkTargetSelect.value || null
    if (!href) {
      editor.chain().focus().extendMarkRange('link').unsetLink().run()
      linkTrigger.hidden = true
      activeLinkEl = null
    } else {
      editor.chain().focus().extendMarkRange('link').setLink({ href, target, title, alt }).run()
    }
    closeLinkPopover()
  })

  linkRemoveBtn.addEventListener('click', () => {
    editor.chain().focus().extendMarkRange('link').unsetLink().run()
    linkTrigger.hidden = true
    activeLinkEl = null
    closeLinkPopover()
  })

  ;[linkHrefInput, linkAltInput, linkTitleInput, linkTargetSelect].forEach(inp => {
    inp.addEventListener('keydown', e => {
      if (e.key === 'Enter') { e.preventDefault(); linkUpdateBtn.click() }
      if (e.key === 'Escape') { e.preventDefault(); closeLinkPopover() }
    })
  })

  // Close link popover and hide link trigger when clicking outside
  document.addEventListener('click', e => {
    const clickedInsideEditor = editorDiv.contains(e.target)
    const clickedPopover = linkPopover.contains(e.target)
    const clickedTrigger = linkTrigger.contains(e.target)
    const clickedToolbar = e.target.closest('.tiptap-toolbar')

    if (!clickedInsideEditor && !clickedPopover && !clickedTrigger && !clickedToolbar) {
      closeLinkPopover()
      linkTrigger.hidden = true
      activeLinkEl = null
    } else if (linkPopoverOpen && !clickedTrigger && !clickedPopover) {
      closeLinkPopover()
      linkTrigger.hidden = true
      activeLinkEl = null
    }
  })

  // ── Editor ───────────────────────────────────────────────────────────────
  let sourceMode = false
  let pendingIframeDraft = ''
  let sizeDebounce = null

  function showIframeNotice(message, draft = '') {
    pendingIframeDraft = draft.trim()
    iframeNoticeText.textContent = message
    iframeDraft.value = pendingIframeDraft
    iframeDraft.hidden = pendingIframeDraft === ''
    iframeNotice.hidden = false
  }

  function hideIframeNotice() {
    pendingIframeDraft = ''
    iframeDraft.value = ''
    iframeDraft.hidden = true
    iframeNotice.hidden = true
  }

  const editor = new Editor({
    element: editorDiv,
    extensions: [
      StarterKit,
      Underline,
      TextStyle,
      FontSize,
      Color,
      Highlight.configure({ multicolor: true }),
      FontFamily,
      LinkWithTitle,
      ImageWithEditButton,
      IframeNode,
      VideoNode,
      ContentCardNode,
    ],
    content: textarea.value || '',
    editorProps: {
      handlePaste(view, event) {
        if (sourceMode) return false

        const draft = extractIframePasteDraft(
          event.clipboardData?.getData('text/html') || '',
          event.clipboardData?.getData('text/plain') || ''
        )

        if (!draft) return false

        const normalized = normalizeIframeInput(draft)
        event.preventDefault()

        if (normalized.ok) {
          hideIframeNotice()
          editor.chain().focus().setIframe(normalized.attrs).run()
          return true
        }

        showIframeNotice(normalized.message, draft)
        return true
      },
    },
  })
  function setSourceMode(nextMode, options = {}) {
    const { preserveSource = false } = options
    if (sourceMode === nextMode) return

    sourceMode = nextMode

    if (sourceMode) {
      if (!preserveSource) sourceTa.value = editor.getHTML()
      editorDiv.style.display = 'none'
      sourceTa.classList.add('visible')
      htmlBtn.classList.add('is-active')
      linkTrigger.hidden = true
      closeLinkPopover()
      bar.querySelectorAll('.tt-btn, .tt-select, .tt-number, .tt-color').forEach(el => {
        if (el !== htmlBtn) el.setAttribute('disabled', '')
      })
      return
    }

    editor.commands.setContent(sourceTa.value)
    sourceTa.classList.remove('visible')
    editorDiv.style.display = ''
    htmlBtn.classList.remove('is-active')
    bar.querySelectorAll('[disabled]').forEach(el => el.removeAttribute('disabled'))
  }

  // ── Toolbar ──────────────────────────────────────────────────────────────
  const bar = document.createElement('div')
  bar.className = 'tiptap-toolbar'

  // Headings
  const headSel = document.createElement('select')
  headSel.className = 'tt-select'
  ;[['', 'Paragraph'], ['1', 'H1'], ['2', 'H2'], ['3', 'H3'], ['4', 'H4']].forEach(([v, l]) => {
    const o = document.createElement('option'); o.value = v; o.textContent = l; headSel.appendChild(o)
  })
  headSel.addEventListener('change', () => {
    if (!headSel.value) editor.chain().focus().setParagraph().run()
    else editor.chain().focus().toggleHeading({ level: parseInt(headSel.value) }).run()
  })
  bar.appendChild(headSel); bar.appendChild(sep())

  // Font family
  const fontSel = document.createElement('select'); fontSel.className = 'tt-select'; fontSel.title = 'Font family'
  FONT_FAMILIES.forEach(([v, l]) => {
    const o = document.createElement('option'); o.value = v; o.textContent = l; fontSel.appendChild(o)
  })
  fontSel.addEventListener('change', () => {
    if (!fontSel.value) editor.chain().focus().unsetFontFamily().run()
    else editor.chain().focus().setFontFamily(fontSel.value).run()
  })
  bar.appendChild(fontSel)

  // Font size
  const sizeIn = document.createElement('input')
  sizeIn.type = 'number'; sizeIn.min = '8'; sizeIn.max = '96'; sizeIn.placeholder = 'px'
  sizeIn.className = 'tt-number'; sizeIn.title = 'Font size (px)'
  sizeIn.addEventListener('input', () => {
    clearTimeout(sizeDebounce)
    sizeDebounce = setTimeout(() => editor.chain().focus().setFontSize(sizeIn.value || null).run(), 350)
  })
  bar.appendChild(sizeIn); bar.appendChild(sep())

  // Bold / Italic / Underline / Strike
  const boldBtn = icon('<b>B</b>'); boldBtn.title = 'Bold'
  const italBtn = icon('<i>I</i>'); italBtn.title = 'Italic'
  const underBtn = icon('<u>U</u>'); underBtn.title = 'Underline'
  const strikeBtn = icon('<s>S</s>'); strikeBtn.title = 'Strikethrough'
  boldBtn.addEventListener('click',   () => editor.chain().focus().toggleBold().run())
  italBtn.addEventListener('click',   () => editor.chain().focus().toggleItalic().run())
  underBtn.addEventListener('click',  () => editor.chain().focus().toggleUnderline().run())
  strikeBtn.addEventListener('click', () => editor.chain().focus().toggleStrike().run())
  ;[boldBtn, italBtn, underBtn, strikeBtn].forEach(b => bar.appendChild(b))
  bar.appendChild(sep())

  // AI Improve Text
  const improveBtn = document.createElement('button')
  improveBtn.type = 'button'
  improveBtn.className = 'tt-btn tt-ai-btn'
  improveBtn.textContent = 'AI Improve'
  improveBtn.title = 'Improve selected text with AI, or the full document when nothing is selected'
  improveBtn.addEventListener('click', () => {
    const contentHtml = editor.getHTML()
    window.openAiProfilePicker(async selection => {
      if (!selection?.profileId) return
      improveBtn.disabled = true
      improveBtn.textContent = '...'
      try {
        const fd = new FormData()
        fd.append('profile_id', selection.profileId)
        if (selection.personaId) fd.append('persona_id', selection.personaId)
        fd.append('content', contentHtml)
        fd.append('mode', 'html')
        fd.append('context', AI_CONTEXT)
        const res = await fetch('/admin/ai/process', { method: 'POST', body: fd })
        const data = await res.json()
        if (data.result) {
          if (String(data.result).trim() === String(contentHtml).trim()) {
            alert('The AI returned the same content without any visible changes. Try a different persona or profile, or revise the prompt context first.')
          } else {
            editor.commands.setContent(data.result, false)
          }
        } else {
          alert('AI improvement failed: ' + (data.error || 'Unknown error'))
        }
      } catch (e) {
        alert('Error: ' + e.message)
      } finally {
        improveBtn.disabled = false
        improveBtn.textContent = 'AI Improve'
      }
    }, { capability: 'text', title: 'Improve Text with AI', taskKey: 'text-improve' })
  })
  if (AI_TEXT_ENABLED) {
    bar.appendChild(improveBtn)
    bar.appendChild(sep())
  }

  // Text color + Highlight
  const colorWrap = document.createElement('label'); colorWrap.className = 'tt-color-wrap'; colorWrap.title = 'Text color'; colorWrap.innerHTML = '<span>A</span>'
  const colorIn = document.createElement('input'); colorIn.type = 'color'; colorIn.className = 'tt-color'; colorIn.value = '#f5a23b'
  colorIn.addEventListener('input', () => editor.chain().focus().setColor(colorIn.value).run())
  colorWrap.appendChild(colorIn); bar.appendChild(colorWrap)

  const hlWrap = document.createElement('label'); hlWrap.className = 'tt-color-wrap'; hlWrap.title = 'Highlight color'; hlWrap.innerHTML = '<span>H</span>'
  const hlIn = document.createElement('input'); hlIn.type = 'color'; hlIn.className = 'tt-color'; hlIn.value = '#ffd85a'
  hlIn.addEventListener('input', () => editor.chain().focus().toggleHighlight({ color: hlIn.value }).run())
  hlWrap.appendChild(hlIn); bar.appendChild(hlWrap); bar.appendChild(sep())

  // Horizontal rule
  const hrBtn = icon('—'); hrBtn.title = 'Horizontal rule'
  hrBtn.addEventListener('click', () => editor.chain().focus().setHorizontalRule().run())
  bar.appendChild(hrBtn); bar.appendChild(sep())

  // Link — toolbar button shows/focuses the floating popover for new link insertion
  const linkBtn = icon('🔗'); linkBtn.title = 'Insert / edit link'
  linkBtn.addEventListener('click', e => {
    e.preventDefault()
    e.stopPropagation()
    if (linkPopoverOpen) {
      closeLinkPopover()
      return
    }
    editor.chain().focus().run()
    // Position popover near the toolbar button itself
    openLinkPopover(linkBtn)
  })
  bar.appendChild(linkBtn)

  // Image from library
  const imgBtn = icon('🖼'); imgBtn.title = 'Insert image from media library'
  imgBtn.addEventListener('click', () => {
    window.openMediaPicker(result => {
      editor.chain().focus().setImage({ src: result.url, alt: result.alt || '' }).run()
    })
  })
  bar.appendChild(imgBtn)

  // Video from library
  const videoBtn = icon('🎬'); videoBtn.title = 'Insert video from media library'
  videoBtn.addEventListener('click', () => {
    window.openMediaPicker(result => {
      editor.chain().focus().setVideo({ src: result.url, description: result.alt || '' }).run()
    }, 'select', { mode: 'video' })
  })
  bar.appendChild(videoBtn)

  // Art piece / collection embed from library
  const pieceBtn = icon('▦'); pieceBtn.title = 'Insert art piece or collection'
  pieceBtn.addEventListener('click', () => {
    window.openPiecePicker(result => {
      const attrs = result.type === 'collection'
        ? { src: `/immersive/collections/${result.slug}`, title: `Collection: ${result.title}` }
        : { src: `/embed/pieces/${result.id}`, title: result.title }
      editor.chain().focus().setIframe(buildIframeAttrs({
        ...attrs,
        style: 'width:100%;aspect-ratio:16/9;border:0;',
      })).run()
    })
  })
  bar.appendChild(pieceBtn)

  // iFrame
  const iframeBtn = icon('⬛'); iframeBtn.title = 'Insert iframe embed'
  iframeBtn.addEventListener('click', () => {
    window.openIframePicker(raw => {
      if (!raw) return

      const normalized = normalizeIframeInput(raw)
      if (normalized.ok) {
        hideIframeNotice()
        editor.chain().focus().setIframe(normalized.attrs).run()
        return
      }

      showIframeNotice(normalized.message, raw)
    })
  })
  bar.appendChild(iframeBtn); bar.appendChild(sep())

  // HTML source toggle
  const htmlBtn = icon('HTML'); htmlBtn.title = 'Toggle HTML source view'
  htmlBtn.addEventListener('click', () => {
    setSourceMode(!sourceMode)
  })
  bar.appendChild(htmlBtn)

  iframeNoticeApply.addEventListener('click', () => {
    if (!pendingIframeDraft) return

    if (!sourceMode) {
      setSourceMode(true)
    }

    const separator = sourceTa.value.trim() && !sourceTa.value.endsWith('\n') ? '\n' : ''
    sourceTa.value += `${separator}${pendingIframeDraft}`
    sourceTa.focus()
    sourceTa.selectionStart = sourceTa.selectionEnd = sourceTa.value.length
    hideIframeNotice()
  })

  iframeNoticeClear.addEventListener('click', hideIframeNotice)
  iframeDraft.addEventListener('input', () => {
    pendingIframeDraft = iframeDraft.value
  })

  // ── Toolbar sync ─────────────────────────────────────────────────────────
  function syncToolbar() {
    if (sourceMode) return
    boldBtn.classList.toggle('is-active', editor.isActive('bold'))
    italBtn.classList.toggle('is-active', editor.isActive('italic'))
    underBtn.classList.toggle('is-active', editor.isActive('underline'))
    strikeBtn.classList.toggle('is-active', editor.isActive('strike'))
    linkBtn.classList.toggle('is-active', editor.isActive('link'))

    const attrs = editor.getAttributes('textStyle')
    sizeIn.value = attrs.fontSize || ''
    fontSel.value = [...fontSel.options].find(o => o.value === (attrs.fontFamily || '')) ? (attrs.fontFamily || '') : ''

    if (editor.isActive('heading', { level: 1 })) headSel.value = '1'
    else if (editor.isActive('heading', { level: 2 })) headSel.value = '2'
    else if (editor.isActive('heading', { level: 3 })) headSel.value = '3'
    else if (editor.isActive('heading', { level: 4 })) headSel.value = '4'
    else headSel.value = ''
  }

  editor.on('selectionUpdate', () => {
    if (sourceMode) return
    syncToolbar()
    if (editor.isActive('link')) positionLinkTrigger()
    else { linkTrigger.hidden = true; activeLinkEl = null; if (!linkPopoverOpen) closeLinkPopover() }
  })
  editor.on('transaction', () => { if (!sourceMode) syncToolbar() })

  editor.on('blur', () => {
    setTimeout(() => {
      const activeEl = document.activeElement
      if (
        activeEl &&
        (linkTrigger.contains(activeEl) ||
         linkPopover.contains(activeEl) ||
         activeEl === linkTrigger ||
         activeEl === linkPopover)
      ) {
        return
      }
      if (!linkPopoverOpen) {
        linkTrigger.hidden = true
        activeLinkEl = null
      }
    }, 150)
  })

  const onScroll = () => {
    if (!linkPopoverOpen) {
      linkTrigger.hidden = true
      activeLinkEl = null
    } else {
      closeLinkPopover()
      linkTrigger.hidden = true
      activeLinkEl = null
    }
  }
  window.addEventListener('scroll', onScroll, { passive: true })

  editor.on('destroy', () => {
    linkTrigger.remove()
    linkPopover.remove()
    window.removeEventListener('scroll', onScroll)
  })

  // ── Assemble ─────────────────────────────────────────────────────────────
  wrap.appendChild(bar)
  wrap.appendChild(editorDiv)
  wrap.appendChild(sourceTa)
  wrap.appendChild(iframeNotice)
  textarea.parentNode.insertBefore(wrap, textarea)

  // ── Submit sync ──────────────────────────────────────────────────────────
  const form = textarea.closest('form')
  if (form) {
    form.addEventListener('submit', () => {
      textarea.value = sourceMode ? sourceTa.value : editor.getHTML()
    }, { capture: true })
  }

}

// ─── Media Picker ─────────────────────────────────────────────────────────────

let _pickerCallback = null
let _libraryMode    = false
let _pickerOptions  = { mode: 'image' }

function initMediaPicker() {
  const dialog    = document.getElementById('media-picker-modal')
  if (!dialog) return

  const tabs      = dialog.querySelectorAll('.media-picker-tab')
  const panels    = dialog.querySelectorAll('.media-picker-panel')
  const grid      = dialog.querySelector('.media-picker-grid')
  const closeBtn  = dialog.querySelector('.media-picker-close')
  const cancelBtn = dialog.querySelector('.media-picker-cancel-btn')
  const selectBtn = dialog.querySelector('.media-picker-select-btn')
  const altRow    = document.getElementById('mp-alt-row')
  const altInput  = document.getElementById('mp-alt-input')
  const altLabel  = altRow?.querySelector('label')
  const confirmPanel = document.getElementById('mp-panel-confirm')
  const confirmPreviewHost = document.getElementById('mp-confirm-preview-host')
  const confirmPreviewImg = document.getElementById('mp-confirm-preview-img')
  const confirmTitleInput = document.getElementById('mp-confirm-title')
  const confirmAltInput = document.getElementById('mp-confirm-alt')
  const confirmAltLabel = document.getElementById('mp-confirm-alt-label')
  const confirmAltHint = document.getElementById('mp-confirm-alt-hint')
  const confirmAiBtn = document.getElementById('mp-confirm-ai-btn')
  if (confirmAiBtn) confirmAiBtn.hidden = true
  const confirmStatus = document.getElementById('mp-confirm-status')
  const confirmMetaId = document.getElementById('mp-confirm-meta-id')
  const confirmMetaMime = document.getElementById('mp-confirm-meta-mime')
  const confirmMetaStatus = document.getElementById('mp-confirm-meta-status')
  const confirmPosterField = document.getElementById('mp-confirm-poster-field')
  const confirmPosterUrl = document.getElementById('mp-confirm-poster-url')
  const confirmPosterChooseBtn = document.getElementById('mp-confirm-poster-choose-btn')
  const confirmPosterClearBtn = document.getElementById('mp-confirm-poster-clear-btn')
  const confirmPosterFile = document.getElementById('mp-confirm-poster-file')
  const confirmPosterStatus = document.getElementById('mp-confirm-poster-status')

  function updateAltRowLabel(kind) {
    if (!altLabel) return
    if (kind === 'video') {
      altLabel.innerHTML = 'Description <em>(describe the video for screen readers — AI can only refine text you write, not watch the video)</em>'
      if (altInput) altInput.placeholder = 'e.g. A timelapse of a city skyline at sunset'
    } else {
      altLabel.innerHTML = 'Alt text <em>(describe the image for screen readers — leave blank if purely decorative)</em>'
      if (altInput) altInput.placeholder = 'e.g. A cityscape at night with red lanterns'
    }
    if (altAiBtn) altAiBtn.hidden = !mediaAiEnabledForKind(kind)
  }

  function updateConfirmAltCopy(kind) {
    if (!confirmAltLabel || !confirmAltHint || !confirmAltInput) return
    if (kind === 'video') {
      confirmAltLabel.textContent = 'Description'
      confirmAltHint.textContent = 'Write the description that should travel with this video everywhere it is reused. AI can only refine text you write; it cannot watch the video.'
      confirmAltInput.placeholder = 'Describe this video for screen readers and future reuse.'
      if (confirmAiBtn) {
        confirmAiBtn.title = 'Refine description with AI'
        confirmAiBtn.setAttribute('aria-label', 'Refine description with AI')
        confirmAiBtn.hidden = !mediaAiEnabledForKind(kind)
      }
    } else {
      confirmAltLabel.textContent = 'Description / Alt Text'
      confirmAltHint.textContent = 'Write the alt text/description now. The asset will stay draft-only until this text is saved successfully.'
      confirmAltInput.placeholder = 'Describe this image for screen readers and future reuse.'
      if (confirmAiBtn) {
        confirmAiBtn.title = 'Generate description with AI'
        confirmAiBtn.setAttribute('aria-label', 'Generate description with AI')
        confirmAiBtn.hidden = !mediaAiEnabledForKind(kind)
      }
    }
  }

  const dropzone  = dialog.querySelector('.media-picker-dropzone')
  const fileInput = dialog.querySelector('.media-picker-file-input')
  const uploadBtn = document.getElementById('mp-upload-btn')
  const uploadSt  = document.getElementById('mp-upload-status')
  const fileInfo  = document.getElementById('mp-file-info')
  const fileThumb = document.getElementById('mp-file-thumb')
  const fileName  = document.getElementById('mp-file-name')
  const fileSize  = document.getElementById('mp-file-size')
  const fileType  = document.getElementById('mp-file-type')
  const uploadHint = document.getElementById('mp-upload-hint')

  const urlInput  = document.getElementById('mp-import-url')
  const importBtn = dialog.querySelector('.media-picker-import-btn')
  const importSt  = document.getElementById('mp-import-status')
  const altAiBtn  = document.getElementById('mp-alt-ai-btn')

  if (altAiBtn) altAiBtn.hidden = true
  if (altAiBtn) {
    altAiBtn.addEventListener('click', () => {
      const imgSrc = selectedUrl
      if (!imgSrc) return
      const isVideo = selectedAsset?.kind === 'video'
      if (!mediaAiEnabledForKind(isVideo ? 'video' : 'image')) return
      if (isVideo && !altInput?.value.trim()) {
        alert('Write a description first — AI can only refine existing text for video, not invent one.')
        return
      }
      window.openAiProfilePicker(async selection => {
        if (!selection?.profileId) return
        altAiBtn.disabled = true
        altAiBtn.textContent = '…'
        try {
          const fd = new FormData()
          fd.append('profile_id', selection.profileId)
          if (selection.personaId) fd.append('persona_id', selection.personaId)
          let res
          if (isVideo) {
            fd.append('content', altInput.value.trim())
            fd.append('mode', 'text')
            fd.append('context', 'media')
            res = await fetch('/admin/ai/process', { method: 'POST', body: fd })
          } else {
            fd.append('image_url', imgSrc)
            if (altInput?.value.trim()) fd.append('existing_alt_text', altInput.value.trim())
            res = await fetch('/admin/ai/describe-image', { method: 'POST', body: fd })
          }
          const data = await res.json()
          if (data.result) {
            if (altInput) altInput.value = data.result
            altAiBtn.textContent = '✓'
            setTimeout(() => { altAiBtn.textContent = '✨'; altAiBtn.disabled = false }, 1500)
          } else {
            alert((isVideo ? 'Description refinement' : 'Alt text generation') + ' failed: ' + (data.error || 'Unknown error'))
            altAiBtn.textContent = '✨'
            altAiBtn.disabled = false
          }
        } catch (err) {
          alert('Error: ' + err.message)
          altAiBtn.textContent = '✨'
          altAiBtn.disabled = false
        }
      }, isVideo
        ? { title: 'Refine Video Description with AI', taskKey: 'video-description' }
        : { capability: 'vision', title: 'Generate Alt Text with AI', taskKey: 'alt-text' })
    })
  }

  let selectedUrl = null
  let selectedAsset = null
  let currentTab  = 'select'
  let currentMode = 'image'
  let draftAsset = null
  let confirmMode = false
  let posterSelectionMode = false
  let posterTargetAsset = null

  function setTabsVisible(visible) {
    tabs.forEach(tab => { tab.hidden = !visible })
  }

  function setPanelVisibility(tabName) {
    panels.forEach(panel => { panel.hidden = panel.id !== `mp-panel-${tabName}` })
  }

  function switchTab(tabName) {
    if (confirmMode) return
    currentTab = tabName
    tabs.forEach(t => { const a = t.dataset.tab === tabName; t.classList.toggle('active', a); t.setAttribute('aria-selected', a ? 'true' : 'false') })
    setPanelVisibility(tabName)
    const onSelect = tabName === 'select' && !_libraryMode
    selectBtn.style.display = onSelect ? '' : 'none'
    if (altRow) altRow.hidden = !(onSelect && selectedUrl)
  }

  tabs.forEach(t => t.addEventListener('click', () => switchTab(t.dataset.tab)))

  function pickerModeConfig() {
    if (currentMode === 'video') {
      return {
        accept: 'video/mp4,video/webm,video/quicktime',
        types: ['video/mp4', 'video/webm', 'video/quicktime'],
        limit: 64 * 1024 * 1024,
        hint: 'MP4 · WebM · QuickTime · max 64 MB',
        empty: 'No videos yet. Use Upload to add one.',
      }
    }

    if (currentMode === 'media') {
      return {
        accept: 'image/*,video/mp4,video/webm,video/quicktime',
        types: ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'video/mp4', 'video/webm', 'video/quicktime'],
        limit: 64 * 1024 * 1024,
        hint: 'Images max 8 MB · videos max 64 MB',
        empty: 'No media yet. Use Upload or Import to add some.',
      }
    }

    return {
      accept: 'image/*',
      types: ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'],
      limit: 8 * 1024 * 1024,
      hint: 'JPEG · PNG · GIF · WebP · AVIF · max 8 MB',
      empty: 'No images yet. Use Upload or Import to add some.',
    }
  }

  function clearConfirmPreview() {
    confirmPreviewHost?.querySelectorAll('video.dynamic-media-preview, iframe.dynamic-media-preview, .media-picker-video-thumb').forEach(node => node.remove())
    confirmPreviewImg?.classList.add('is-hidden')
    confirmPreviewImg?.removeAttribute('src')
    if (confirmPreviewImg) confirmPreviewImg.alt = ''
  }

  function renderVideoThumb(url) {
    const shell = document.createElement('div')
    shell.className = 'media-picker-video-thumb'
    shell.textContent = url ? 'Poster linked' : 'No poster'
    return shell
  }

  function hydrateConfirmPreview(asset) {
    clearConfirmPreview()
    if (!confirmPreviewHost) return
    if (!asset) return

    const isVideo = asset.kind === 'video' || (asset.mime_type || '').startsWith('video/')
    const isImage = asset.kind === 'image' || (asset.mime_type || '').startsWith('image/')
    if (isVideo) {
      const video = document.createElement('video')
      video.className = 'dynamic-media-preview'
      video.src = asset.url || `/media/${asset.id}`
      if (asset.poster_url) video.poster = asset.poster_url
      video.controls = true
      video.preload = 'metadata'
      confirmPreviewHost.appendChild(video)
      return
    }
    if (isImage) {
      if (confirmPreviewImg) {
        confirmPreviewImg.classList.remove('is-hidden')
        confirmPreviewImg.src = asset.legacy_url || asset.url || `/image/${asset.id}`
        confirmPreviewImg.alt = asset.alt_text || asset.title || 'Media preview'
      }
      return
    }
    confirmPreviewHost.appendChild(renderVideoThumb(null))
  }

  function updateConfirmPosterUi() {
    if (!confirmPosterField || !confirmPosterUrl || !draftAsset) return
    const isVideo = draftAsset.kind === 'video' || (draftAsset.mime_type || '').startsWith('video/')
    confirmPosterField.classList.toggle('is-hidden', !isVideo)
    if (!isVideo) return
    confirmPosterUrl.value = draftAsset.poster_url || ''
  }

  function enterConfirmMode(asset) {
    draftAsset = { ...asset }
    confirmMode = true
    if (!posterTargetAsset) {
      posterSelectionMode = false
    }
    setTabsVisible(false)
    setPanelVisibility('confirm')
    if (altRow) altRow.hidden = true
    selectBtn.style.display = ''
    selectBtn.disabled = false
    selectBtn.textContent = _pickerCallback ? 'Save & Select' : 'Confirm Asset'
    if (confirmTitleInput) confirmTitleInput.value = draftAsset.title || ''
    if (confirmAltInput) confirmAltInput.value = draftAsset.alt_text || ''
    if (confirmMetaId) confirmMetaId.textContent = draftAsset.id || '—'
    if (confirmMetaMime) confirmMetaMime.textContent = draftAsset.mime_type || 'Unknown'
    if (confirmMetaStatus) confirmMetaStatus.textContent = (draftAsset.status || 'draft').toUpperCase()
    if (confirmStatus) confirmStatus.textContent = ''
    if (confirmPosterStatus) confirmPosterStatus.textContent = ''
    updateConfirmAltCopy(draftAsset.kind)
    updateConfirmPosterUi()
    hydrateConfirmPreview(draftAsset)
  }

  function exitConfirmMode(defaultTab = 'select') {
    confirmMode = false
    posterSelectionMode = false
    setTabsVisible(true)
    draftAsset = null
    clearConfirmPreview()
    switchTab(defaultTab)
    selectBtn.textContent = 'Select Asset'
  }

  function beginPosterSelection() {
    if (!draftAsset) return
    posterTargetAsset = { ...draftAsset }
    posterSelectionMode = true
    confirmMode = false
    setTabsVisible(true)
    tabs.forEach(tab => {
      tab.hidden = tab.dataset.tab !== 'select'
    })
    currentMode = 'image'
    selectedUrl = null
    selectedAsset = null
    selectBtn.disabled = true
    selectBtn.style.display = ''
    selectBtn.textContent = 'Use Poster'
    setPanelVisibility('select')
    currentTab = 'select'
    tabs.forEach(t => {
      const active = t.dataset.tab === 'select'
      t.classList.toggle('active', active)
      t.setAttribute('aria-selected', active ? 'true' : 'false')
    })
    loadGrid()
  }

  function restoreDraftAfterPosterSelection() {
    if (posterTargetAsset) {
      draftAsset = { ...posterTargetAsset }
    }
    if (!draftAsset) return
    posterTargetAsset = null
    setTabsVisible(false)
    enterConfirmMode(draftAsset)
  }

  async function persistSelectedAssetMetadata() {
    if (!selectedAsset || !altInput || !['image', 'video'].includes(selectedAsset.kind || '')) {
      return true
    }
    const nextAlt = altInput.value.trim()
    const currentAlt = (selectedAsset.alt_text || '').trim()
    if (nextAlt === currentAlt) return true

    const fd = new FormData()
    fd.append('ajax', '1')
    fd.append('alt_text', nextAlt)
    if (selectedAsset.source === 'asset') {
      fd.append('title', selectedAsset.title || '')
      const res = await fetch(`/admin/media/asset/${String(selectedAsset.id).replace(/^asset-/, '')}/update`, { method: 'POST', body: fd, headers: { Accept: 'application/json' } })
      const data = await res.json()
      if (!data.ok) {
        setUploadStatus(data.error || 'Could not save description.', true)
        return false
      }
      selectedAsset = data.asset
      selectedUrl = data.asset.legacy_url || data.asset.url || selectedUrl
      return true
    }

    fd.append('title', selectedAsset.title || '')
    const res = await fetch(`/admin/media/${selectedAsset.id}/update`, { method: 'POST', body: fd, headers: { Accept: 'application/json' } })
    const data = await res.json()
    if (!data.ok) {
      setUploadStatus(data.error || 'Could not save description.', true)
      return false
    }
    selectedAsset = data.asset
    selectedUrl = data.asset.legacy_url || data.asset.url || selectedUrl
    return true
  }

  function renderGridItem(f) {
    const url = f.legacy_url || f.url || (f.kind === 'image' ? `/image/${f.id}` : `/media/${f.id}`)
    const item = document.createElement('div')
    item.className = 'media-picker-item'
    item.dataset.url = url
    item.dataset.id = String(f.id)
    item.dataset.kind = f.kind
    item.dataset.mime = f.mime_type || ''

    let media;
    if (f.kind === 'video') {
      if (f.poster_url) {
        media = document.createElement('img');
        media.src = f.poster_url;
      } else {
        media = renderVideoThumb(null);
      }
    } else if (f.kind === 'iframe') {
      media = document.createElement('div');
      media.className = 'media-picker-iframe-thumb';
      media.innerHTML = '&lt;/&gt; Embed';
      media.style.display = 'flex';
      media.style.alignItems = 'center';
      media.style.justifyContent = 'center';
      media.style.height = '100%';
      media.style.background = 'var(--paper)';
      media.style.color = 'var(--orange)';
      media.style.fontWeight = 'bold';
    } else {
      media = document.createElement('img');
      media.src = f.legacy_url || `/image/${f.id}`;
    }

    media.loading = 'lazy'
    media.alt = `Media ${f.id}`
    item.appendChild(media)

    item.addEventListener('click', () => {
      dialog.querySelectorAll('.media-picker-item').forEach(i => i.classList.remove('selected'))
      item.classList.add('selected')
      selectedUrl = url
      selectedAsset = f
      selectBtn.disabled = false
      if (altRow && !_libraryMode && currentTab === 'select' && (f.kind === 'image' || f.kind === 'video')) {
        altRow.hidden = false
        updateAltRowLabel(f.kind)
        if (altInput) altInput.value = f.alt_text || ''
        altInput?.focus()
      } else if (altRow) {
        altRow.hidden = true
      }
    })
    item.addEventListener('dblclick', () => { if (!_libraryMode) void confirmSelection() })
    return item
  }

  const PICKER_PAGE_SIZE = 48
  let _pickerAllItems = [], _pickerShown = 0, _pickerPreselectUrl = null

  // "Load more" button sits below the grid, never inside it
  const loadMoreBtn = document.createElement('button')
  loadMoreBtn.type = 'button'
  loadMoreBtn.className = 'admin-btn admin-btn-ghost'
  loadMoreBtn.style.cssText = 'display:none;margin:0.7rem auto'
  loadMoreBtn.textContent = 'Load more'
  grid.insertAdjacentElement('afterend', loadMoreBtn)

  function renderPickerBatch() {
    const batch = _pickerAllItems.slice(_pickerShown, _pickerShown + PICKER_PAGE_SIZE)
    batch.forEach(f => {
      const item = renderGridItem(f)
      grid.appendChild(item)
      const url = f.legacy_url || f.url || (f.kind === 'image' ? `/image/${f.id}` : `/media/${f.id}`)
      if (_pickerPreselectUrl && url === _pickerPreselectUrl) {
        item.classList.add('selected'); selectedUrl = url; selectedAsset = f; selectBtn.disabled = false
        if (altRow && !_libraryMode && currentTab === 'select' && (f.kind === 'image' || f.kind === 'video')) {
          altRow.hidden = false
          updateAltRowLabel(f.kind)
          if (altInput) altInput.value = f.alt_text || ''
        }
      }
    })
    _pickerShown += batch.length
    loadMoreBtn.style.display = _pickerShown < _pickerAllItems.length ? 'block' : 'none'
  }

  loadMoreBtn.addEventListener('click', renderPickerBatch)

  async function loadGrid(preselectUrl = null) {
    grid.innerHTML = ''; _pickerAllItems = []; _pickerShown = 0; _pickerPreselectUrl = preselectUrl
    loadMoreBtn.style.display = 'none'
    selectedUrl = null; selectedAsset = null; selectBtn.disabled = true
    if (altRow) altRow.hidden = true
    try {
      const res = await fetch('/admin/media/library')
      const files = await res.json()
      _pickerAllItems = files.filter(f => {
        if (currentMode === 'video') return f.kind === 'video'
        if (currentMode === 'media') return f.kind === 'image' || f.kind === 'video' || f.kind === 'iframe'
        return f.kind === 'image'
      })
      if (!_pickerAllItems.length) { grid.innerHTML = `<p class="media-picker-empty">${pickerModeConfig().empty}</p>`; return }
      renderPickerBatch()
    } catch { grid.innerHTML = '<p class="media-picker-empty">Failed to load media library.</p>' }
  }

  async function confirmSelection() {
    if (confirmMode && draftAsset) {
      const fd = new FormData()
      fd.append('title', confirmTitleInput?.value.trim() || '')
      fd.append('alt_text', confirmAltInput?.value.trim() || '')
      if (draftAsset.poster_media_file_id) fd.append('poster_media_file_id', String(draftAsset.poster_media_file_id))
      selectBtn.disabled = true
      if (confirmStatus) confirmStatus.textContent = 'Saving...'
      try {
        const res = await fetch(`/admin/media/${draftAsset.id}/confirm`, { method: 'POST', body: fd, headers: { Accept: 'application/json' } })
        const data = await res.json()
        if (!data.ok) {
          if (confirmStatus) confirmStatus.textContent = data.error || 'Could not confirm the asset.'
          return
        }
        if (posterTargetAsset) {
          posterTargetAsset.poster_media_file_id = data.asset.id
          posterTargetAsset.poster_url = data.asset.legacy_url || data.asset.url || ''
          restoreDraftAfterPosterSelection()
          return
        }
        draftAsset = data.asset
        if (_pickerCallback) {
          _pickerCallback({
            url: data.asset.url,
            alt: data.asset.alt_text || '',
            id: data.asset.id || null,
            kind: data.asset.kind || currentMode,
            mime_type: data.asset.mime_type || '',
            legacy_url: data.asset.legacy_url || (data.asset.kind === 'image' ? data.asset.url : null),
            poster_url: data.asset.poster_url || null,
          })
          _pickerCallback = null
        }
        dialog.close()
      } catch (err) {
        if (confirmStatus) confirmStatus.textContent = 'Could not confirm the asset.'
      } finally {
        selectBtn.disabled = false
      }
      return
    }

    if (posterSelectionMode) {
      if (selectedAsset?.kind !== 'image') return
      const nextTarget = posterTargetAsset || draftAsset
      if (!nextTarget) return
      nextTarget.poster_media_file_id = selectedAsset.id
      nextTarget.poster_url = selectedAsset.legacy_url || selectedAsset.url || ''
      posterTargetAsset = nextTarget
      restoreDraftAfterPosterSelection()
      return
    }

    if (!selectedUrl || !_pickerCallback) return
    const persisted = await persistSelectedAssetMetadata()
    if (!persisted) return
    _pickerCallback({
      url: selectedUrl,
      alt: selectedAsset?.alt_text || altInput?.value.trim() || '',
      id: selectedAsset?.id || null,
      kind: selectedAsset?.kind || currentMode,
      mime_type: selectedAsset?.mime_type || selectedAsset?.mime || '',
      legacy_url: selectedAsset?.legacy_url || (selectedAsset?.kind === 'image' ? selectedUrl : null),
      poster_url: selectedAsset?.poster_url || null,
    })
    _pickerCallback = null
    dialog.close()
  }

  selectBtn.addEventListener('click', () => { void confirmSelection() })

  // Upload
  function formatBytes(b) { return b < 1024 ? b + ' B' : b < 1048576 ? (b/1024).toFixed(1) + ' KB' : (b/1048576).toFixed(2) + ' MB' }
  function setUploadStatus(msg, err = false) { if (uploadSt) { uploadSt.textContent = msg; uploadSt.className = `media-picker-status ${err ? 'err' : 'ok'}` } }
  function showFileInfo(file) {
    const config = pickerModeConfig()
    const over = file.size > config.limit
    const bad = !config.types.includes(file.type)
    if (fileName) fileName.textContent = file.name
    if (fileSize) { fileSize.textContent = formatBytes(file.size); fileSize.classList.toggle('size-over', over) }
    if (fileType) fileType.textContent = file.type || 'unknown'
    if (fileInfo) { fileInfo.hidden = false; fileInfo.classList.toggle('is-error', over || bad) }
    if (over || bad) { setUploadStatus(over ? 'File exceeds the current size limit.' : `Unsupported type "${file.type}".`, true); if (uploadBtn) uploadBtn.disabled = true }
    else { setUploadStatus(''); if (uploadBtn) uploadBtn.disabled = false }
    if (fileThumb && file.type.startsWith('image/')) { const r = new FileReader(); r.onload = e => { fileThumb.src = e.target.result }; r.readAsDataURL(file) }
    if (fileThumb && !file.type.startsWith('image/')) fileThumb.src = ''
  }
  function clearFileInfo() {
    if (fileInfo) { fileInfo.hidden = true; fileInfo.classList.remove('is-error') }
    if (fileThumb) fileThumb.src = ''
    if (uploadBtn) uploadBtn.disabled = true
    setUploadStatus('')
  }

    if (dropzone) {
    dropzone.addEventListener('click', () => fileInput.click())
    dropzone.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileInput.click() } })
    dropzone.addEventListener('dragover', e => { e.preventDefault(); dropzone.classList.add('drag-over') })
    dropzone.addEventListener('dragleave', () => dropzone.classList.remove('drag-over'))
    dropzone.addEventListener('drop', e => { e.preventDefault(); dropzone.classList.remove('drag-over'); if (e.dataTransfer.files[0]) { fileInput.files = e.dataTransfer.files; showFileInfo(e.dataTransfer.files[0]) } })
    fileInput.addEventListener('change', () => { if (fileInput.files[0]) showFileInfo(fileInput.files[0]); else clearFileInfo() })
  }

  uploadBtn?.addEventListener('click', async () => {
    if (!fileInput?.files.length) { setUploadStatus('Choose a file first.', true); return }
    const file = fileInput.files[0]
    const config = pickerModeConfig()
    if (file.size > config.limit) { setUploadStatus('File exceeds the current limit.', true); return }
    uploadBtn.disabled = true; setUploadStatus('Uploading…')
    const fd = new FormData(); fd.append('media_file', file)
    try {
      const res = await fetch('/admin/media/upload', { method: 'POST', body: fd })
      const data = await res.json()
      if (!data.ok) { setUploadStatus(data.error || 'Upload failed.', true); return }
      setUploadStatus('Uploaded successfully.'); fileInput.value = ''; clearFileInfo()
      enterConfirmMode(data.asset)
    } catch { setUploadStatus('Upload failed — check your connection.', true) }
    finally { if (uploadBtn) uploadBtn.disabled = false }
  })

  // Import
  function setImportStatus(msg, err = false) { if (importSt) { importSt.textContent = msg; importSt.className = `media-picker-status ${err ? 'err' : 'ok'}` } }
  importBtn?.addEventListener('click', async () => {
    const url = urlInput?.value.trim(); if (!url) { setImportStatus('Enter a URL first.', true); return }
    importBtn.disabled = true; setImportStatus('Importing…')
    const fd = new FormData(); fd.append('url', url)
    try {
      const res = await fetch('/admin/media/import', { method: 'POST', body: fd })
      const data = await res.json()
      if (!data.ok) { setImportStatus(data.error || 'Import failed.', true); return }
      setImportStatus('Imported successfully.'); if (urlInput) urlInput.value = ''
      enterConfirmMode(data.asset)
    } catch { setImportStatus('Import failed — check your connection.', true) }
    finally { if (importBtn) importBtn.disabled = false }
  })

  async function discardCurrentDraft() {
    if (!draftAsset) return
    const res = await fetch(`/admin/media/${draftAsset.id}/discard`, { method: 'POST', headers: { Accept: 'application/json' } })
    const data = await res.json()
    if (!data.ok) {
      if (confirmStatus) confirmStatus.textContent = data.error || 'Could not discard the draft.'
      return
    }
    draftAsset = null
    dialog.close()
  }

  async function requestClose() {
    if (posterSelectionMode) {
      restoreDraftAfterPosterSelection()
      return
    }
    if (confirmMode && draftAsset) {
      const keep = window.confirm('Keep this draft asset for later? Choose Cancel/No to delete it now.')
      if (keep) {
        dialog.close()
      } else {
        await discardCurrentDraft()
      }
      return
    }
    dialog.close()
  }

  closeBtn?.addEventListener('click',  () => { void requestClose() })
  cancelBtn?.addEventListener('click', () => { void requestClose() })
  dialog.addEventListener('click', e => { if (e.target === dialog) void requestClose() })
  dialog.addEventListener('close', () => {
    _pickerCallback = null; selectedUrl = null
    if (altRow)  altRow.hidden = true
    if (altInput) altInput.value = ''
    if (confirmAltInput) confirmAltInput.value = ''
    if (confirmTitleInput) confirmTitleInput.value = ''
    if (confirmStatus) confirmStatus.textContent = ''
    if (confirmPosterStatus) confirmPosterStatus.textContent = ''
    draftAsset = null
    confirmMode = false
    posterSelectionMode = false
    posterTargetAsset = null
    setTabsVisible(true)
    tabs.forEach(tab => {
      if (tab.dataset.tab === 'import') {
        tab.hidden = currentMode === 'video'
      } else {
        tab.hidden = false
      }
    })
    clearConfirmPreview()
    if (_libraryMode) window.location.reload()
  })

  confirmPosterChooseBtn?.addEventListener('click', () => {
    beginPosterSelection()
  })

  confirmPosterClearBtn?.addEventListener('click', () => {
    if (!draftAsset) return
    draftAsset.poster_media_file_id = null
    draftAsset.poster_url = ''
    updateConfirmPosterUi()
    if (confirmPosterStatus) confirmPosterStatus.textContent = ''
  })

  confirmPosterFile?.addEventListener('change', async () => {
    if (!confirmPosterFile.files?.length) return
    const fd = new FormData()
    fd.append('media_file', confirmPosterFile.files[0])
    if (confirmPosterStatus) confirmPosterStatus.textContent = 'Uploading poster...'
    try {
      const res = await fetch('/admin/media/poster-upload', { method: 'POST', body: fd, headers: { Accept: 'application/json' } })
      const data = await res.json()
      if (!data.ok) {
        if (confirmPosterStatus) confirmPosterStatus.textContent = data.error || 'Poster upload failed.'
        return
      }
      if (draftAsset) {
        draftAsset.poster_media_file_id = data.asset.id
        draftAsset.poster_url = data.asset.legacy_url || data.asset.url || ''
        updateConfirmPosterUi()
      }
      if (confirmPosterStatus) confirmPosterStatus.textContent = 'Poster uploaded.'
    } catch (err) {
      if (confirmPosterStatus) confirmPosterStatus.textContent = 'Poster upload failed.'
    } finally {
      confirmPosterFile.value = ''
    }
  })

  confirmAiBtn?.addEventListener('click', () => {
    if (!draftAsset || !confirmAltInput) return
    const isVideo = draftAsset.kind === 'video' || (draftAsset.mime_type || '').startsWith('video/')
    if (!mediaAiEnabledForKind(isVideo ? 'video' : 'image')) return
    if (isVideo && !confirmAltInput.value.trim()) {
      if (confirmStatus) confirmStatus.textContent = 'Write a description first — AI can only refine existing text for video.'
      return
    }
    window.openAiProfilePicker(async selection => {
      if (!selection?.profileId) return
      confirmAiBtn.disabled = true
      try {
        const fd = new FormData()
        fd.append('profile_id', selection.profileId)
        if (selection.personaId) fd.append('persona_id', selection.personaId)
        let res
        if (isVideo) {
          fd.append('content', confirmAltInput.value.trim())
          fd.append('mode', 'text')
          fd.append('context', 'media')
          res = await fetch('/admin/ai/process', { method: 'POST', body: fd })
        } else {
          fd.append('image_url', draftAsset.url || `/media/${draftAsset.id}`)
          if (confirmAltInput.value.trim()) fd.append('existing_alt_text', confirmAltInput.value.trim())
          res = await fetch('/admin/ai/describe-image', { method: 'POST', body: fd })
        }
        const data = await res.json()
        if (data.result) {
          confirmAltInput.value = data.result
          if (confirmStatus) confirmStatus.textContent = isVideo ? 'Description refined. Confirm to save it.' : 'Description generated. Confirm to save it.'
        } else if (confirmStatus) {
          confirmStatus.textContent = data.error || 'AI request failed.'
        }
      } catch (err) {
        if (confirmStatus) confirmStatus.textContent = 'AI request failed.'
      } finally {
        confirmAiBtn.disabled = false
      }
    }, isVideo
      ? { title: 'Refine Video Description with AI', taskKey: 'video-description' }
      : { capability: 'vision', title: 'Generate Alt Text with AI', taskKey: 'alt-text' })
  })

  window.openMediaPicker = (callback = null, defaultTab = 'select', opts = {}) => {
    _pickerCallback = callback; _libraryMode = callback === null; _pickerOptions = { mode: opts.mode || 'image' }
    currentMode = _pickerOptions.mode
    const config = pickerModeConfig()
    if (fileInput) fileInput.setAttribute('accept', config.accept)
    if (uploadHint) uploadHint.textContent = config.hint
    tabs.forEach(tab => {
      if (tab.dataset.tab === 'import') {
        const hideImport = currentMode === 'video'
        tab.hidden = hideImport
        if (hideImport && defaultTab === 'import') defaultTab = 'select'
      }
    })
    setUploadStatus(''); setImportStatus('')
    if (altRow)  altRow.hidden = true
    if (altInput) altInput.value = ''
    if (confirmAltInput) confirmAltInput.value = ''
    if (confirmTitleInput) confirmTitleInput.value = ''
    clearFileInfo(); if (fileInput) fileInput.value = ''
    draftAsset = null
    confirmMode = false
    posterSelectionMode = false
    posterTargetAsset = null
    setTabsVisible(true)
    selectBtn.style.display = _libraryMode ? 'none' : ''
    selectBtn.textContent = 'Select Asset'
    selectBtn.disabled = true; selectedUrl = null
    switchTab(defaultTab); if (defaultTab === 'select') loadGrid()
    dialog.showModal()
  }
}

// ─── Art Piece / Exhibit Picker ────────────────────────────────────────────

const PIECE_ENGINE_LABELS = { p5: 'P5.js', c2: 'C2.js', three: 'Three.js', svg: 'SVG', aframe: 'A-Frame' }

let _piecePickerCallback = null

function initPiecePicker() {
  const dialog      = document.getElementById('piece-picker-modal')
  if (!dialog) return

  const tabs        = dialog.querySelectorAll('.media-picker-tab')
  const panels      = dialog.querySelectorAll('.media-picker-panel')
  const pieceGrid   = dialog.querySelector('#pp-panel-pieces .piece-picker-grid')
  const collectionGrid = dialog.querySelector('#pp-panel-collections .piece-picker-grid')
  const closeBtn    = dialog.querySelector('.media-picker-close')
  const cancelBtn   = dialog.querySelector('.piece-picker-cancel-btn')
  const selectBtn   = dialog.querySelector('.piece-picker-select-btn')

  let selected = null
  let piecesLoaded = false
  let collectionsLoaded = false

  function switchTab(tabName) {
    tabs.forEach(t => { const a = t.dataset.tab === tabName; t.classList.toggle('active', a); t.setAttribute('aria-selected', a ? 'true' : 'false') })
    panels.forEach(p => { p.hidden = p.id !== `pp-panel-${tabName}` })
    selected = null; selectBtn.disabled = true
    if (tabName === 'pieces' && !piecesLoaded) loadPieces()
    if (tabName === 'collections' && !collectionsLoaded) loadCollections()
  }

  tabs.forEach(t => t.addEventListener('click', () => switchTab(t.dataset.tab)))

  function renderItem(grid, { thumb, title, badge, onSelect }) {
    const item = document.createElement('div')
    item.className = 'piece-picker-item'

    const thumbEl = document.createElement('div')
    thumbEl.className = 'piece-picker-thumb'
    if (thumb) {
      const img = document.createElement('img')
      img.src = thumb; img.loading = 'lazy'; img.alt = ''
      thumbEl.appendChild(img)
    }
    item.appendChild(thumbEl)

    const label = document.createElement('div')
    label.className = 'piece-picker-label'
    const titleEl = document.createElement('span')
    titleEl.className = 'piece-picker-title'
    titleEl.textContent = title
    const badgeEl = document.createElement('span')
    badgeEl.className = 'piece-picker-engine'
    badgeEl.textContent = badge
    label.appendChild(titleEl); label.appendChild(badgeEl)
    item.appendChild(label)

    item.addEventListener('click', () => {
      grid.querySelectorAll('.piece-picker-item').forEach(i => i.classList.remove('selected'))
      item.classList.add('selected')
      selected = onSelect()
      selectBtn.disabled = false
    })
    item.addEventListener('dblclick', () => confirmSelection())
    return item
  }

  async function loadPieces() {
    pieceGrid.innerHTML = ''
    try {
      const res = await fetch('/admin/pieces/library')
      const pieces = await res.json()
      if (!pieces.length) { pieceGrid.innerHTML = '<p class="media-picker-empty">No art pieces yet.</p>'; piecesLoaded = true; return }
      pieces.forEach(p => pieceGrid.appendChild(renderItem(pieceGrid, {
        thumb: p.thumbnail_url,
        title: p.title || 'Untitled Piece',
        badge: PIECE_ENGINE_LABELS[p.engine] || String(p.engine || '').toUpperCase(),
        onSelect: () => ({ type: 'piece', id: p.id, title: p.title || 'Untitled Piece' }),
      })))
      piecesLoaded = true
    } catch { pieceGrid.innerHTML = '<p class="media-picker-empty">Failed to load art pieces.</p>' }
  }

  async function loadCollections() {
    collectionGrid.innerHTML = ''
    try {
      const res = await fetch('/admin/platform-collections/library')
      const collections = await res.json()
      if (!collections.length) { collectionGrid.innerHTML = '<p class="media-picker-empty">No collections yet.</p>'; collectionsLoaded = true; return }
      collections.forEach(col => collectionGrid.appendChild(renderItem(collectionGrid, {
        thumb: col.thumbnail_url,
        title: col.name || 'Untitled Collection',
        badge: `${col.item_count} item${col.item_count === 1 ? '' : 's'}`,
        onSelect: () => ({ type: 'collection', slug: col.slug, title: col.name || 'Untitled Collection' }),
      })))
      collectionsLoaded = true
    } catch { collectionGrid.innerHTML = '<p class="media-picker-empty">Failed to load collections.</p>' }
  }

  function confirmSelection() {
    if (!selected || !_piecePickerCallback) return
    _piecePickerCallback(selected)
    _piecePickerCallback = null; dialog.close()
  }

  selectBtn.addEventListener('click', confirmSelection)
  closeBtn?.addEventListener('click',  () => dialog.close())
  cancelBtn?.addEventListener('click', () => dialog.close())
  dialog.addEventListener('click', e => { if (e.target === dialog) dialog.close() })
  dialog.addEventListener('close', () => { _piecePickerCallback = null })

  window.openPiecePicker = (callback) => {
    _piecePickerCallback = callback
    switchTab('pieces')
    dialog.showModal()
  }
}

// ─── iFrame Embed Picker ──────────────────────────────────────────────────────

let _iframePickerCallback = null

function initIframePicker() {
  const dialog    = document.getElementById('iframe-picker-modal')
  if (!dialog) return

  const input     = document.getElementById('iframe-picker-input')
  const closeBtn  = dialog.querySelector('.media-picker-close')
  const cancelBtn = dialog.querySelector('.iframe-picker-cancel-btn')
  const insertBtn = dialog.querySelector('.iframe-picker-insert-btn')

  function confirmSelection() {
    if (!_iframePickerCallback) return
    const callback = _iframePickerCallback
    _iframePickerCallback = null
    const value = input.value
    dialog.close()
    callback(value)
  }

  insertBtn.addEventListener('click', confirmSelection)
  closeBtn?.addEventListener('click',  () => dialog.close())
  cancelBtn?.addEventListener('click', () => dialog.close())
  dialog.addEventListener('click', e => { if (e.target === dialog) dialog.close() })
  dialog.addEventListener('close', () => { _iframePickerCallback = null })

  window.openIframePicker = (callback) => {
    _iframePickerCallback = callback
    input.value = ''
    dialog.showModal()
    input.focus()
  }
}

// ─── AI Profile Picker ──────────────────────────────────────────────────────

let _aiProfilePickerCallback = null
let _aiProfilesLoaded = false
let _aiProfiles = []

function initAiProfilePicker() {
  const dialog    = document.getElementById('ai-profile-picker-modal')
  if (!dialog) return

  const titleEl   = dialog.querySelector('#ai-profile-picker-title')
  const select    = document.getElementById('ai-profile-picker-select')
  const personaSelect = document.getElementById('ai-persona-picker-select')
  const hintEl    = document.getElementById('ai-profile-picker-hint')
  const closeBtn  = dialog.querySelector('.media-picker-close')
  const cancelBtn = dialog.querySelector('.ai-profile-picker-cancel-btn')
  const selectBtn = dialog.querySelector('.ai-profile-picker-select-btn')
  let capNotice   = null
  let pickerOptions = {}

  async function loadProfiles() {
    select.innerHTML = '<option value="">Loading…</option>'
    selectBtn.disabled = true
    try {
      const res = await fetch('/admin/ai/profiles')
      _aiProfiles = await res.json()
      _aiProfilesLoaded = true
    } catch {
      select.innerHTML = '<option value="">Failed to load AI profiles</option>'
    }
  }

  function readPreferredProfileId(taskKey) {
    if (taskKey === 'alt-text') return Number(select?.dataset.preferredAltProfileId || 0) || null
    if (taskKey === 'piece') return Number(select?.dataset.preferredPieceProfileId || 0) || null
    return Number(select?.dataset.preferredTextProfileId || 0) || null
  }

  function storageKey(taskKey, field) {
    return taskKey ? `ah-ai-picker:${taskKey}:${field}` : ''
  }

  function populateSelect(capFilter) {
    const profiles = capFilter
      ? _aiProfiles.filter(p => (p.capabilities || 'text,code').split(',').map(s => s.trim()).includes(capFilter))
      : _aiProfiles
    select.innerHTML = ''
    if (!profiles.length) {
      select.innerHTML = '<option value="">No matching profiles</option>'
      selectBtn.disabled = true
      if (!capNotice) {
        capNotice = document.createElement('p')
        capNotice.style.cssText = 'color:#92400e;font-size:0.875rem;padding:0.5rem 0.75rem;background:rgba(234,179,8,0.12);border:1px solid rgba(234,179,8,0.4);border-radius:4px;margin-top:0.5rem;'
        select.insertAdjacentElement('afterend', capNotice)
      }
      capNotice.textContent = capFilter === 'vision'
        ? '⚠ No AI profiles with vision capability configured. Edit a profile under AI Settings → AI Profiles to enable vision.'
        : `⚠ No AI profiles with ${capFilter} capability configured.`
      capNotice.hidden = false
      return
    }
    if (capNotice) capNotice.hidden = true
    profiles.forEach(p => {
      const o = document.createElement('option')
      o.value = String(p.id)
      o.textContent = `${p.profile_name} — ${p.vendor}/${p.model} (${p.user_name})`
      select.appendChild(o)
    })
    const taskKey = pickerOptions.taskKey || ''
    const savedProfileId = taskKey ? window.localStorage.getItem(storageKey(taskKey, 'profile')) : null
    const preferredProfileId = pickerOptions.preferredProfileId || readPreferredProfileId(taskKey)
    const desiredProfileId = savedProfileId || (preferredProfileId ? String(preferredProfileId) : '')
    if (desiredProfileId && profiles.some(p => String(p.id) === desiredProfileId)) {
      select.value = desiredProfileId
    }
    if (personaSelect) {
      const savedPersonaId = taskKey ? window.localStorage.getItem(storageKey(taskKey, 'persona')) : null
      const preferredPersonaId = pickerOptions.preferredPersonaId ? String(pickerOptions.preferredPersonaId) : ''
      const desiredPersonaId = savedPersonaId || preferredPersonaId
      if (desiredPersonaId && [...personaSelect.options].some(option => option.value === desiredPersonaId)) {
        personaSelect.value = desiredPersonaId
      } else {
        personaSelect.value = ''
      }
    }
    selectBtn.disabled = false
  }

  function confirmSelection() {
    if (!_aiProfilePickerCallback || !select.value) return
    const callback = _aiProfilePickerCallback
    _aiProfilePickerCallback = null
    const profileId = select.value
    const personaId = personaSelect?.value || ''
    const taskKey = pickerOptions.taskKey || ''
    if (taskKey) {
      window.localStorage.setItem(storageKey(taskKey, 'profile'), profileId)
      if (personaId) window.localStorage.setItem(storageKey(taskKey, 'persona'), personaId)
      else window.localStorage.removeItem(storageKey(taskKey, 'persona'))
    }
    dialog.close()
    callback({ profileId, personaId })
  }

  selectBtn.addEventListener('click', confirmSelection)
  closeBtn?.addEventListener('click',  () => dialog.close())
  cancelBtn?.addEventListener('click', () => dialog.close())
  dialog.addEventListener('click', e => { if (e.target === dialog) dialog.close() })
  dialog.addEventListener('close', () => { _aiProfilePickerCallback = null })

  window.openAiProfilePicker = (callback, opts = {}) => {
    _aiProfilePickerCallback = callback
    pickerOptions = opts
    if (titleEl) titleEl.textContent = opts.title || 'Improve with AI'
    if (hintEl) {
      hintEl.textContent = opts.hint || 'Pick the model/profile and optionally layer a persona on top of the task prompt.'
    }
    const run = () => populateSelect(opts.capability || null)
    if (!_aiProfilesLoaded) loadProfiles().then(run)
    else run()
    dialog.showModal()
  }
}

// ─── Standalone image field pickers + clear buttons ──────────────────────────

function initStandalonePickers() {
  document.querySelectorAll('[data-picker-target]').forEach(btn => {
    btn.addEventListener('click', () => {
      const targetId  = btn.dataset.pickerTarget
      const targetIn  = document.getElementById(targetId)
      const radioName = btn.dataset.pickerRadio
      const radioVal  = btn.dataset.pickerRadioValue || 'link'
      const previewId = btn.dataset.pickerPreview
      const pickerMode = btn.dataset.pickerMode || 'image'

      window.openMediaPicker(result => {
        const url = typeof result === 'string' ? result : result.url
        if (targetIn) targetIn.value = url
        if (previewId) {
          const preview = document.getElementById(previewId)
          if (preview) {
            let img = preview.querySelector('img')
            if (!img) { img = document.createElement('img'); preview.appendChild(img) }
            img.src = url; img.alt = ''
          }
        }
        if (radioName) {
          const radio = document.querySelector(`input[name="${radioName}"][value="${radioVal}"]`)
          if (radio) { radio.checked = true; radio.dispatchEvent(new Event('change', { bubbles: true })) }
        }
      }, 'select', { mode: pickerMode })
    })
  })

  document.querySelectorAll('[data-clear-input]').forEach(btn => {
    btn.addEventListener('click', () => {
      const inp  = document.getElementById(btn.dataset.clearInput)
      const prev = document.getElementById(btn.dataset.clearPreview)
      if (inp) inp.value = ''
      if (prev) { const img = prev.querySelector('img'); if (img) img.remove() }
    })
  })
}

// ─── Bootstrap ───────────────────────────────────────────────────────────────

window.initTiptap = initTiptap

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('textarea[data-tiptap]').forEach(initTiptap)
  initMediaPicker()
  initPiecePicker()
  initIframePicker()
  initAiProfilePicker()
  initStandalonePickers()
})
