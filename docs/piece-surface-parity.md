# Art Piece Surface Parity

This matrix is the maintained contract for translating an art piece across
live, embedded, immersive, and downloaded surfaces. A capability is offered
only when the piece's capability contract allows it; camera and microphone
features always require a visitor gesture and browser permission.

## Surface Contract

| Surface | Canonical experience | Controls and capture |
|---|---|---|
| Regular `/pieces/{id}` | Regular piece stage | Screenshot, ZIP, compact VR, fullscreen, permitted sound/camera/hand controls, a page-level Embed copier, plus an immersive link below Prompt |
| Regular embed `/embed/pieces/{id}` | The same regular stage partial | Identical stage controls; page-level title, Prompt link, metadata, comments, and admin actions omitted |
| TipTap/blog piece embed | Regular embed inside `creatr-art-piece` | Wrapper supplies lazy sizing/fullscreen promotion only; it must not duplicate stage controls |
| Embed (Custom) | `/immersive/pieces/{id}?embed=1` | Immersive renderer and shared immersive toolbar through the custom wrapper |
| Embed (CMS) | Custom plus `cms=1` | Same immersive capabilities with CMS-specific wrapper behavior |
| Regular ZIP | Portable regular piece | Engine/capability controls and PNG capture work offline where browser security permits |
| Immersive ZIP | Portable immersive piece | Shared immersive toolbar, view state, camera/hand controls, and PNG capture |
| Immersive collection | One gallery-room runtime | Room-level navigation/sound/capture; no per-tile camera or hand inference |

Regular collection pages and their embeds reuse that same immersive collection
renderer. The regular page supplies one surface-local Embed action and a
page-level immersive link; the immersive page supplies Custom/CMS embed actions.
The embedded stage fills its viewport without changing slideshow, item
selection, downloads, navigation, fullscreen, audio, or renderer suspension.

## Engine Capabilities

| Engine | Camera default | Hand steering | Camera capture composition |
|---|---|---|---|
| p5.js | Overlay | Framed sleep; lazy regular spatial shell; immersive room commands | Video composited above the canvas |
| C2.js | Overlay | Framed sleep; lazy regular spatial shell; immersive room commands | Video composited above the canvas |
| C2.js Interactive | Overlay | Lazy regular spatial shell with authored input paused; immersive pointer/room commands | Video composited above the canvas |
| SVG | Overlay | Framed sleep; lazy regular spatial shell; immersive room commands | Video composited above the SVG rendering |
| Three.js | Background | Clutched look/orbit/travel/zoom camera commands | Blended camera-attached quad; overlay placement uses DOM video |
| A-Frame | Background | Clutched look/orbit/travel/zoom camera commands | Blended camera-attached quad; overlay placement uses DOM video |

Authors may explicitly choose background, overlay, or Off. Background on a 2D
piece sits beneath its rendered surface and can be hidden by opaque artwork.
All active visible layers must be included in PNG capture in their on-screen
stacking order.

## Shared Capability Rules

- Sound, keyboard, theremin, microphone, hand control, camera view, placement,
  and opacity derive from `piece_sound_capability_contract()`.
- An unset camera availability offers the visitor-activated camera control on
  every engine; it does not open the camera automatically.
- Hand-tracking is a sound voice. Hand control is an independent steering
  capability available on Three.js, A-Frame, and C2 Interactive when allowed.
- Solo regular and immersive ZIPs bundle MediaPipe when hand tracking or hand
  control needs it. Collection ZIPs deliberately exclude both to bound size
  and avoid parallel inference.
- `static=1` immersive embeds are intentionally bare and are the documented
  exception to control parity.
- Embed controls are surface-local: regular pages copy the regular iframe;
  immersive pages copy only Custom/CMS immersive variants.

## Camera, Motion, and Download Variants

- Regular-family surfaces use `camera_overlay`/`camera_placement` and
  `regular_hand_motion`. Immersive-family surfaces use nullable immersive
  camera overrides (falling back to regular) and universally offer hand
  motion when the deployment feature is enabled.
- Camera theremin is an audio voice and requires an enabled sound design.
  Visual hand motion never depends on `sonic_params`; both capabilities may
  share one granted stream and inference loop.
- Device orientation supplies the base immersive orientation and hand motion
  adds a bounded offset. Flat regular p5/C2/C2-Interactive/SVG surfaces stay in
  their exact framed DOM until steering wakes a lazy Three.js presentation
  shell. Turning steering off freezes the current pose. Reset alone returns
  home; it never changes steering, camera visibility, authored animation, or
  sound. A home shell is disposed only when steering is already off.
- Compatible regular Three.js/A-Frame and immersive piece/collection surfaces
  route the existing single-hand landmark result through one clutched-gestural
  state machine. Open palm provides stabilized look; a dwell-confirmed pose is
  locked when pinch begins; wrist displacement drives orbit or travel; palm
  scale drives bounded zoom. Release and hand loss stop commands immediately.
  Interactive C2 retains authored pointer movement and pinch-to-pointer in its
  immersive interaction context. Only its regular spatial shell releases any
  held pointer and latches authored input off while animation continues.
  Steering Off freezes a displaced shell and remains non-interactive; Reset
  after steering Off returns to the frame and restores input. Reset while
  steering On returns home but keeps steering ready and input latched. These
  dedicated slides are absent from immersive C2, Three.js, and A-Frame guides.
- Those surfaces also expose a separate hand-icon instruction button. Its
  five-slide, mobile-first dialog documents Look, Move, Orbit, Zoom, and safe
  stopping; it never requests permission or changes tracking state. Live,
  embedded, and downloaded variants reuse the same markup/controller and keep
  Sound → controls → hand guide → fullscreen ordering where those controls
  are present.
- Full ZIP preserves the source surface, including the lazy flat spatial shell.
  Non-Camera ZIP remains permanently framed and removes camera
  rendering, camera theremin, visual hand motion, camera UI, and MediaPipe
  while retaining non-camera sound.
- Full ZIP spatial composition owns a stable reference to the canonical shared
  camera video, not its potentially throttled/occluded display clone. A
  dedicated Three.js `VideoTexture` plane renders that source while spatially
  active, avoiding fullscreen freezes caused by Canvas2D video-frame copying.
