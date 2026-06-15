import { Editor, Extension, Node } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import Underline from '@tiptap/extension-underline'
import TextStyle from '@tiptap/extension-text-style'
import { Color } from '@tiptap/extension-color'
import Highlight from '@tiptap/extension-highlight'
import FontFamily from '@tiptap/extension-font-family'
import Link from '@tiptap/extension-link'
import Image from '@tiptap/extension-image'

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

// ─── Custom: Link with title attribute ───────────────────────────────────────

const LinkWithTitle = Link.configure({ openOnClick: false }).extend({
  addAttributes() {
    return {
      ...this.parent?.(),
      title: {
        default: null,
        parseHTML: el => el.getAttribute('title'),
        renderHTML: attrs => attrs.title ? { title: attrs.title } : {},
      },
    }
  },
})

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
  const linkHrefInput = document.createElement('input'); linkHrefInput.type = 'url'; linkHrefInput.className = 'tiptap-edit-input'; linkHrefInput.placeholder = 'https://…'
  const linkTitleLabel = document.createElement('span'); linkTitleLabel.className = 'tiptap-edit-label'; linkTitleLabel.textContent = 'Title'
  const linkTitleInput = document.createElement('input'); linkTitleInput.type = 'text'; linkTitleInput.className = 'tiptap-edit-input'; linkTitleInput.placeholder = 'Description (optional)'
  const linkUpdateBtn = document.createElement('button'); linkUpdateBtn.type = 'button'; linkUpdateBtn.className = 'admin-btn admin-btn-sm'; linkUpdateBtn.textContent = 'Update'
  const linkRemoveBtn = document.createElement('button'); linkRemoveBtn.type = 'button'; linkRemoveBtn.className = 'admin-btn admin-btn-ghost admin-btn-sm'; linkRemoveBtn.textContent = 'Remove link'

  linkPopover.appendChild(linkHrefLabel)
  linkPopover.appendChild(linkHrefInput)
  linkPopover.appendChild(linkTitleLabel)
  linkPopover.appendChild(linkTitleInput)
  linkPopover.appendChild(linkUpdateBtn)
  linkPopover.appendChild(linkRemoveBtn)
  document.body.appendChild(linkPopover)

  let linkPopoverOpen = false

  function openLinkPopover(anchorEl) {
    const rect = anchorEl.getBoundingClientRect()
    linkPopover.style.left = rect.left + 'px'
    linkPopover.style.top  = (rect.bottom + 4) + 'px'
    const attrs = editor.getAttributes('link')
    linkHrefInput.value  = attrs.href  || ''
    linkTitleInput.value = attrs.title || ''
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
      if (!node || node.tagName !== 'A') { linkTrigger.hidden = true; return }
      const rect = node.getBoundingClientRect()
      linkTrigger.style.left = (rect.right + 3) + 'px'
      linkTrigger.style.top  = (rect.top + (rect.height - 20) / 2) + 'px'
      linkTrigger.hidden = false
    } catch {
      linkTrigger.hidden = true
    }
  }

  linkTrigger.addEventListener('click', e => {
    e.stopPropagation()
    if (linkPopoverOpen) { closeLinkPopover(); return }
    openLinkPopover(linkTrigger)
  })

  linkUpdateBtn.addEventListener('click', () => {
    const href  = linkHrefInput.value.trim()
    const title = linkTitleInput.value.trim() || null
    if (!href) {
      editor.chain().focus().extendMarkRange('link').unsetLink().run()
    } else {
      editor.chain().focus().extendMarkRange('link').setLink({ href, target: '_blank', title }).run()
    }
    closeLinkPopover()
  })

  linkRemoveBtn.addEventListener('click', () => {
    editor.chain().focus().extendMarkRange('link').unsetLink().run()
    closeLinkPopover()
  })

  ;[linkHrefInput, linkTitleInput].forEach(inp => {
    inp.addEventListener('keydown', e => {
      if (e.key === 'Enter') { e.preventDefault(); linkUpdateBtn.click() }
      if (e.key === 'Escape') { e.preventDefault(); closeLinkPopover() }
    })
  })

  // Close link popover when clicking outside
  document.addEventListener('click', e => {
    if (linkPopoverOpen && !linkTrigger.contains(e.target) && !linkPopover.contains(e.target)) {
      closeLinkPopover()
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
  const improveBtn = icon('✨'); improveBtn.title = 'Improve selected text with AI'
  improveBtn.addEventListener('click', () => {
    const selection = editor.state.selection
    const from = selection.from
    const to = selection.to
    if (from === to) {
      alert('Select some text first to improve it.')
      return
    }
    const selectedText = editor.state.doc.textBetween(from, to, ' ')
    window.openAiProfilePicker(async profileId => {
      if (!profileId) return
      improveBtn.disabled = true
      improveBtn.textContent = '...'
      try {
        const fd = new FormData()
        fd.append('profile_id', profileId)
        fd.append('content', selectedText)
        fd.append('mode', 'html')
        const res = await fetch('/admin/ai/process', { method: 'POST', body: fd })
        const data = await res.json()
        if (data.result) {
          editor.chain().focus().insertContentAt({ from, to }, data.result).run()
        } else {
          alert('AI improvement failed: ' + (data.error || 'Unknown error'))
        }
      } catch (e) {
        alert('Error: ' + e.message)
      } finally {
        improveBtn.disabled = false
        improveBtn.innerHTML = '✨'
      }
    })
  })
  bar.appendChild(improveBtn)
  bar.appendChild(sep())

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
  linkBtn.addEventListener('click', () => {
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
    else { linkTrigger.hidden = true; if (!linkPopoverOpen) closeLinkPopover() }
  })
  editor.on('transaction', () => { if (!sourceMode) syncToolbar() })

  editor.on('destroy', () => { linkTrigger.remove(); linkPopover.remove() })

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

  let selectedUrl = null
  let selectedAsset = null
  let currentTab  = 'select'
  let currentMode = 'image'

  function switchTab(tabName) {
    currentTab = tabName
    tabs.forEach(t => { const a = t.dataset.tab === tabName; t.classList.toggle('active', a); t.setAttribute('aria-selected', a ? 'true' : 'false') })
    panels.forEach(p => { p.hidden = p.id !== `mp-panel-${tabName}` })
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
      media = document.createElement('video');
      media.src = f.legacy_url || f.url || `/media/${f.id}`;
      media.muted = true;
      media.preload = 'metadata';
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
      if (altRow && !_libraryMode && currentTab === 'select' && f.kind === 'image') {
        altRow.hidden = false
        altInput?.focus()
      } else if (altRow) {
        altRow.hidden = true
      }
    })
    item.addEventListener('dblclick', () => { if (!_libraryMode) confirmSelection() })
    return item
  }

  async function loadGrid(preselectUrl = null) {
    grid.innerHTML = ''; selectedUrl = null; selectedAsset = null; selectBtn.disabled = true
    if (altRow) altRow.hidden = true
    try {
      const res = await fetch('/admin/media/library')
      const files = await res.json()
      const filtered = files.filter(f => {
        if (currentMode === 'video') return f.kind === 'video'
        if (currentMode === 'media') return f.kind === 'image' || f.kind === 'video' || f.kind === 'iframe'
        return f.kind === 'image'
      })
      if (!filtered.length) { grid.innerHTML = `<p class="media-picker-empty">${pickerModeConfig().empty}</p>`; return }
      filtered.forEach(f => {
        const item = renderGridItem(f)
        grid.appendChild(item)
        const url = f.legacy_url || f.url || (f.kind === 'image' ? `/image/${f.id}` : `/media/${f.id}`)
        if (preselectUrl && url === preselectUrl) {
          item.classList.add('selected'); selectedUrl = url; selectedAsset = f; selectBtn.disabled = false
          if (altRow && !_libraryMode && currentTab === 'select' && f.kind === 'image') altRow.hidden = false
        }
      })
    } catch { grid.innerHTML = '<p class="media-picker-empty">Failed to load media library.</p>' }
  }

  function confirmSelection() {
    if (!selectedUrl || !_pickerCallback) return
    _pickerCallback({
      url: selectedUrl,
      alt: altInput?.value.trim() || '',
      id: selectedAsset?.id || null,
      kind: selectedAsset?.kind || currentMode,
      mime_type: selectedAsset?.mime_type || selectedAsset?.mime || '',
      legacy_url: selectedAsset?.legacy_url || (selectedAsset?.kind === 'image' ? selectedUrl : null),
    })
    _pickerCallback = null; dialog.close()
  }

  selectBtn.addEventListener('click', confirmSelection)

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
      await loadGrid(data.url); switchTab('select')
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
      await loadGrid(data.url); switchTab('select')
    } catch { setImportStatus('Import failed — check your connection.', true) }
    finally { if (importBtn) importBtn.disabled = false }
  })

  closeBtn?.addEventListener('click',  () => dialog.close())
  cancelBtn?.addEventListener('click', () => dialog.close())
  dialog.addEventListener('click', e => { if (e.target === dialog) dialog.close() })
  dialog.addEventListener('close', () => {
    _pickerCallback = null; selectedUrl = null
    if (altRow)  altRow.hidden = true
    if (altInput) altInput.value = ''
    if (_libraryMode) window.location.reload()
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
    clearFileInfo(); if (fileInput) fileInput.value = ''
    selectBtn.style.display = _libraryMode ? 'none' : ''
    selectBtn.disabled = true; selectedUrl = null
    switchTab(defaultTab); if (defaultTab === 'select') loadGrid()
    dialog.showModal()
  }
}

// ─── Art Piece / Exhibit Picker ────────────────────────────────────────────

const PIECE_ENGINE_LABELS = { p5: 'P5.js', c2: 'C2.js', three: 'Three.js', svg: 'SVG' }

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

function initAiProfilePicker() {
  const dialog    = document.getElementById('ai-profile-picker-modal')
  if (!dialog) return

  const select    = document.getElementById('ai-profile-picker-select')
  const closeBtn  = dialog.querySelector('.media-picker-close')
  const cancelBtn = dialog.querySelector('.ai-profile-picker-cancel-btn')
  const selectBtn = dialog.querySelector('.ai-profile-picker-select-btn')

  async function loadProfiles() {
    select.innerHTML = '<option value="">Loading…</option>'
    selectBtn.disabled = true
    try {
      const res = await fetch('/admin/ai/profiles')
      const profiles = await res.json()
      if (!profiles.length) {
        select.innerHTML = '<option value="">No AI profiles configured</option>'
        _aiProfilesLoaded = true
        return
      }
      select.innerHTML = ''
      profiles.forEach(p => {
        const o = document.createElement('option')
        o.value = String(p.id)
        o.textContent = `${p.profile_name} — ${p.vendor}/${p.model} (${p.user_name})`
        select.appendChild(o)
      })
      selectBtn.disabled = false
      _aiProfilesLoaded = true
    } catch {
      select.innerHTML = '<option value="">Failed to load AI profiles</option>'
    }
  }

  function confirmSelection() {
    if (!_aiProfilePickerCallback || !select.value) return
    const callback = _aiProfilePickerCallback
    _aiProfilePickerCallback = null
    const profileId = select.value
    dialog.close()
    callback(profileId)
  }

  selectBtn.addEventListener('click', confirmSelection)
  closeBtn?.addEventListener('click',  () => dialog.close())
  cancelBtn?.addEventListener('click', () => dialog.close())
  dialog.addEventListener('click', e => { if (e.target === dialog) dialog.close() })
  dialog.addEventListener('close', () => { _aiProfilePickerCallback = null })

  window.openAiProfilePicker = (callback) => {
    _aiProfilePickerCallback = callback
    if (!_aiProfilesLoaded) loadProfiles()
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

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('textarea[data-tiptap]').forEach(initTiptap)
  initMediaPicker()
  initPiecePicker()
  initIframePicker()
  initAiProfilePicker()
  initStandalonePickers()
})
