# DESIGN.md — Creative Identity Document

<!-- GOVERNANCE
     This file is owned by the human. Sections marked HUMAN-AUTHORED
     are filled in by you, ideally before the first build session, or
     collaboratively with an AI assistant in a dedicated conversation.
     Sections marked AGENT-PROPOSED are populated by the agent during
     sessions and confirmed by you — the same pattern as MEMORY.md.

     The agent reads this file at every session start.
     The agent never asks design questions out of sequence:
       1. References must exist before Derived Identity is attempted.
       2. Derived Identity must exist before Declared Preferences are prompted.
       3. Observed Taste is queued during sessions and proposed at session end.

     If this file is empty or incomplete, the agent asks for References
     before any other design question. It never asks for Declared
     Preferences first.

     Taste constraints recorded here are distinct from technical
     constraints in CONSTRAINTS.md. Do not move entries between files
     unless a taste preference becomes a technical requirement. -->

---

## References
<!-- HUMAN-AUTHORED
     The most important section. Fill this before anything else.
     The agent derives everything downstream from what you put here.
     Screenshots and art files should be committed to your repository.
     URLs are acceptable if files are not available. -->

- **Admired applications or websites:**
  <!-- Filenames of screenshots in your repo, or URLs.
       Example: screenshots/notion-dark.png, https://example.com -->

- **Admired art, design work, or visual culture:**
  <!-- Filenames, URLs, or plain descriptions.
       Example: Saul Bass film posters, brutalist typography,
       screenshots/bauhaus-ref.jpg -->

- **Admired writing or editorial voices:**
  <!-- Publications, authors, or specific pieces whose tone or
       structure you want to echo. These inform copy direction
       as much as visual references do. -->

- **Logo:**
  `public/assets/friendly-guide.png` — the robot mascot already used as the brand mark.

- **Existing brand materials:**
  `public/assets/styles.css` (the "Pareto" design tokens) and the live site itself —
  designated by the person as the working reference point as of 2026-06-19, rather
  than external admired sites/art/writing (those remain open below).

---

## Derived Identity
<!-- AGENT-PROPOSED, HUMAN-CONFIRMED
     The agent fills this section collaboratively after References exist,
     by asking questions and proposing observations. You confirm, correct,
     or expand each entry before it is considered stable.
     Do not fill this section yourself before discussing it with the agent —
     the value is in the derivation process, not the output alone. -->

**What the system holds constant (confirmed 2026-07-02):**
This is no longer one site's aesthetic — it is a design system for a
multi-site CMS. What stays constant across every theme: accessibility as a
floor (semantic HTML, contrast, keyboard nav); the person's authored art and
words as the only content that matters — no AI-slop registers, no stock
filler; structure carrying credibility (a theme may be hard-edged like Pareto
or atmospheric like Celestial, but it must feel considered and durable, never
like a template with decoration bolted on); and a refusal of
attention-economy patterns regardless of register. Themes are instances:
Pareto (hard borders, offset shadows, geometric confidence) and Celestial
(parchment, script-and-serif, cosmic ambient motion) are both valid
expressions of the same system. "What you dislike in contrast" and "The
feeling on first load" below are system-level; "What your references share"
and "The tension you are navigating" describe the Pareto instance as
originally derived.

### Theme instance: Pareto (derived 2026-06-19)

- **What your references share:**
  A structural confidence that doesn't rely on decoration: thick, consistent borders
  and hard, offset shadows (no blur, no gradients) on every interactive surface,
  paired with oversized, heavy-weight type that takes up space without apology. The
  mascot is the one soft, hand-drawn element inside an otherwise rigid, almost
  engineering-diagram structure — everything else is geometric, high-contrast, and
  deliberately unfussy. Accent color is used sparingly and consistently rather than
  as a mood — a small, disciplined set of signals (button, card, highlight), not a
  palette doing emotional work.

- **The tension you are navigating:**
  The site presents as a personal, approachable field guide — friendly mascot,
  plain-spoken and slightly philosophical copy, an inviting surface — but underneath,
  it's a technically dense publishing and syndication system: a CMS, multi-platform
  syndication adapters, RSS/Atom/JSON feeds, AI-assisted piece generation, an
  immersive gallery. The visual language has to do double duty: stay warm enough
  that the depth of the system doesn't read as cold or corporate, while staying
  structured enough that a visitor senses they've found something considered and
  durable — not a template with a cute logo bolted on, and not an AI-slop content
  mill either. The hard borders and confident scale carry that second half of the
  message; the mascot alone couldn't.

- **What you dislike in contrast:**
  The generic "AI-startup" visual register: soft gradients, glassmorphic blur,
  glowing abstract orbs/blobs, thin weightless type floating in excessive empty
  whitespace. Decorative AI-generated filler imagery used as background or stock-in
  dressing rather than the actual authored art pieces — this would directly
  contradict the standing "person is always the named author" constraint
  (CONSTRAINTS.md). Corporate SaaS landing-page tropes: stock photography of smiling
  teams, buzzword copy ("unlock", "supercharge", "seamless", "revolutionize"),
  gradient call-to-action buttons, testimonial carousels. Engagement-bait
  content-hub patterns: autoplaying video, infinite scroll with no end state, fake
  urgency banners, notification-bait UI — the attention-economy playbook, even
  though this site is technically a content/syndication hub that could be tempted
  toward them.

- **The feeling on first load:**
  Welcomed, then quietly reassured. The mascot and direct, human-voiced copy should
  make a visitor feel let into something personal rather than marketed at. The
  structural rigor underneath — borders, shadows, scale — should register, even
  subconsciously, as "a real person built something real here," giving the warmth
  credibility instead of undercutting it.

### Theme instance: Celestial (adopted 2026-07-01)

An atmospheric register built on the same system constants: warm parchment
and black-void surfaces, Pinyon Script headings over Lora body text, amber
accents, and ambient cosmic motion (nebula washes, twinkling stars, shooting
comets) that respects `prefers-reduced-motion`. All Celestial code lives in
the database (`site_settings` theme code / `custom_js`), seeded only via
`--with-example-content` — it is a selectable theme, never a hardcoded
default. Where Pareto carries credibility through rigid structure, Celestial
carries it through considered atmosphere; both refuse the AI-slop and
attention-economy registers listed above.

---

## Declared Preferences
<!-- HUMAN-AUTHORED, after Derived Identity is complete.
     These are starting points, not permanent constraints.
     If you change your mind, update this section and note the
     change in DECISIONS.md. Do not move taste preferences to
     CONSTRAINTS.md unless they become technical requirements. -->

- **Color direction:**
  Fluid, customizable light and dark mode colors configured dynamically through the admin dashboard (Site Identity → Design). All background, foreground, primary, secondary, muted, accent, and destructive color variables are mapped to database settings using CSS custom properties (`--sp-*`), allowing full palette overrides on a per-deployment basis.

- **Type direction:**
  <!-- Specific typefaces, or a descriptive direction if typefaces
       are not yet chosen.
       Example: "Serif for body, monospace for metadata and code.
       Nothing geometric or neutral — something with visible history." -->

- **Layout disposition:**
  Selectable site layouts leveraging 10 built-in theme presets (including Bauhaus/Pareto, Celestial, traditional, academic, minimalist, and comfort) which can be customized or extended with inject-ready custom CSS, JS, and HTML body wrappers stored in the database.

- **Motion and interaction:**
  <!-- Your position on animation, transitions, and interactive behavior.
       Example: "No decorative animation. Transitions only where they
       carry meaning. Fast." -->

- **What must never appear:**
  <!-- Visual or tonal elements that would immediately feel wrong.
       These are taste refusals, not technical constraints.
       Example: "Stock photography. Gradient hero sections.
       Auto-playing anything. Emoji used decoratively." -->

---

## Observed Taste
<!-- AGENT-PROPOSED, HUMAN-CONFIRMED
     Populated during sessions when the agent notices a signal —
     an enthusiasm, a complaint, a reference made in passing,
     an implied direction not yet consciously claimed.
     Proposed at session end alongside MEMORY.md updates.
     Format mirrors MEMORY.md:

     YYYY-MM-DD · CATEGORY · Observation in one sentence.
         [Optional: the exact exchange or context that surfaced it]

     Valid categories:
     INFLUENCE · REFUSAL · TENSION · VOICE · DIRECTION

     Examples:
     2026-04-10 · REFUSAL · Finds AI-generated imagery dishonest
         rather than merely aesthetically displeasing.
         [User: "it's not that I dislike how it looks, I dislike
         what it means"]
     2026-04-10 · TENSION · Wants the site to feel personal but
         is resistant to anything that reads as self-indulgent.
     2026-04-10 · INFLUENCE · Referenced Saul Bass twice without
         being asked about visual influences.

     Keep under 50 entries. When approaching the limit, ask the
     user to review — consolidate stable patterns into Derived
     Identity and archive older entries to docs/design-archive.md. -->

2026-06-11 · DIRECTION · Chose a mascot-forward Fieldguide direction over a more conventional consulting-site posture.
    [Context: selected Fieldguide positioning, journal-ready routes, and mascot energy for `Friendly Guide.png` during the Augment Humankind v1 site plan.]
2026-06-15 · DIRECTION · Chose Overlay transition model for immersive views, allowing instant, smooth fullscreen toggle without reloading pages or resetting WebGL rendering contexts.
2026-06-15 · DIRECTION · Aligned post button layout to balance admin actions (Edit/Expand) in the header with sharing/engagement actions below the content.
2026-06-16 · DIRECTION · Added inline manual generation action links for both Art Pieces and Platform Collections, prioritizing instant feedback and granular control over bulk-only regeneration.
2026-06-16 · DIRECTION · Preferred lightweight parity over a heavier moderation UI for comment ownership and table actions, keeping controls icon-forward and visually consistent across public and admin surfaces.
2026-06-16 · DIRECTION · Preferred inline reader states that behave like condensed single-post views: content replaces previews, primary toggles remain visible in both states, and editing interfaces stay hidden until explicitly invoked.
2026-06-19 · DIRECTION · Rejected the oversized "stacked-word" narrow heading treatment (h1 capped at 10ch, with 14ch/16ch variants on page-hero and gallery/collection titles) sitewide in favor of full-width wrapping — bold/oversized type should still read at scale, but natural line breaks now take priority over dramatic narrow stacking.
2026-06-19 · TENSION · Explicitly redirected a Derived Identity draft away from specific color names toward structure and feel — taste here should be described by borders, shadows, scale, and tone, not by which hues are in play.
2026-07-01 · DIRECTION · Adopted the Celestial theme (script fonts, parchment, cosmic ambient motion) as the live site's register — a deliberate departure from Pareto's hard-edged geometry, executed entirely through DB-stored theme code rather than code changes.
2026-07-02 · DIRECTION · When asked whether Celestial superseded the Pareto identity, chose "both are themes, not identity" — DESIGN.md now describes the multi-site CMS design system, with Pareto and Celestial as two valid instances of shared constants.
    [Context: portable-CMS readiness session; chose the design-system framing over "Celestial is the new direction" and "Pareto stands".]

---

<!-- The agent holds the brush. You choose what gets painted.
     This document is how you tell the agent what you see. -->
