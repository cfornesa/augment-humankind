# Forms And Starter Templates

Forms are database-owned CMS records. The installer seeds a Contact Form and a
Newsletter Signup, and admins can edit their settings and fields under
`/admin/forms`.

Contact and ordinary forms email submissions to the configured recipient and do
not persist payloads. Newsletter Signup stores email addresses in
`newsletter_subscribers` with consent defaulting to true. Newsletter Signup is
the intentional storage exception: it does not require a recipient email and it
does not send email by default.

Art piece starter templates are database-owned records seeded during
installation. In `/admin/pieces`, the first subtab is `Art Pieces` and the
second subtab is `Templates`; `/admin/pieces/templates` redirects permanently
to `/admin/pieces?tab=templates` for bookmark durability. The Templates subtab
edits template metadata and HTML/CSS/JS in the same tabbed code view used by
generated pieces.

Each seeded starter template is usable without adding explicit templates after
setup. Templates may optionally demonstrate existing CMS media:

- `/image/2` is used as a resizable foreground/shape texture example.
- `/image/3` is used as a full-frame background example.

When generating or refining a piece from the admin UI, prompt language may
explicitly request existing CMS media in parallel forms:

- `image ID`, `photo ID`, and `picture ID` refer to `/image/[id]`
- `media asset ID` refers to `/api/media-assets/[id]`

These are parallel prompt affordances, not hidden aliases. If a prompt names
only an image/photo ID, generation may not silently switch to the media-asset
route. If a prompt names only a media asset ID, generation may not silently
switch to `/image/[id]`. Naming both forms explicitly allows both.

Image assets define source media only. Rendered size belongs to the engine's
drawing surface: p5/C2 draw calls, SVG `<image>` attributes, Three.js geometry
or camera-frame planes, and A-Frame rendered entity dimensions. Full-frame
background examples use cover-style sizing so the image spans the visible
canvas/frame.

Public piece pages expose `Download Piece`, which exports a portable ZIP bundle
for the current or selected version. `index.html` is the single manual entry
point; supporting runtime/media/source files load automatically from there. The
export omits CMS presentation controls, preserves interaction for Three.js,
A-Frame, and C2 interactive pieces, and is designed to be rehosted as ordinary
static web files. Interactive exports for those three modes also include the
local screenshot control inside `index.html`, and supported CMS media are
rewritten in a direct-open-safe way so the capture path works from that single
entry file.

Immersive piece pages and immersive collection galleries use the same download
route with `surface=immersive` when the user chooses `Download Piece` on a
single piece. Those ZIPs still use `index.html` as the manual entry point, but
open into the local immersive renderer with fullscreen and PNG controls,
preserve the serialized viewer state where available, and include a direct-open
runtime fallback for local file use. Platform collection galleries use
`/collections/[slug]/download` instead; that export is the full collection wall
with all supported pieces and images, not a selected-piece bundle.
