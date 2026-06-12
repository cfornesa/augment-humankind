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
  - https://carlosmotta.com/projects — structural reference for the gallery: categorised thumbnail grid, clean visual hierarchy
  - https://carlosmotta.com/bio — structural reference for the bio page: flowing sectioned text, professional tone

- **Admired art, design work, or visual culture:**
  - Ancient astronomical manuscripts and celestial charts — objects that are simultaneously precise and otherworldly
  - The visual quality of gallery-lit artworks under warm spotlight against darkness — amber light emanating from objects, not falling on them
  - Roman letter forms and classical inscriptions — archaic authority without modernism

- **Admired writing or editorial voices:**
  - (To be filled in)

- **Logo:**
  - (None yet)

- **Existing brand materials:**
  - (None yet)

---

## Derived Identity
<!-- AGENT-PROPOSED, HUMAN-CONFIRMED
     The agent fills this section collaboratively after References exist,
     by asking questions and proposing observations. You confirm, correct,
     or expand each entry before it is considered stable.
     Do not fill this section yourself before discussing it with the agent —
     the value is in the derivation process, not the output alone. -->

- **What your references share:**
  Objects encountered rather than exhibited — a private world that admits visitors without performing for them. Works exist before the viewer arrives and continue after they leave. Structure is clear, but atmosphere is sovereign.

- **The tension you are navigating:**
  The archaic (manuscript, codex, celestial chart, classical inscription) placed in a black void with no ground, no gravity — historical visual language made alien and weightless. Ancient authority, cosmic displacement.

- **What you dislike in contrast:**
  The visual language of modern portfolio sites: rounded corners, gradient hero sections, geometric sans-serif neutrality, "clean and minimal" as a style rather than a discipline, white or off-white backgrounds, hover effects that confirm rather than disturb. Anything that reads as "I built this with a template."

- **The feeling on first load:**
  "You've entered somewhere. This was made, not generated."

---

## Declared Preferences
<!-- HUMAN-AUTHORED, after Derived Identity is complete.
     These are starting points, not permanent constraints.
     If you change your mind, update this section and note the
     change in DECISIONS.md. Do not move taste preferences to
     CONSTRAINTS.md unless they become technical requirements. -->

- **Color direction:**
  Pure black (`#000000`) background. Warm off-white parchment text (`#E8DFC0`). One accent — amber/gold (`#C89B3C`) — used for headings, active states, and glow effects. No other hues.

- **Type direction:**
  - Display / headings / navigation: **Cinzel Decorative** (archaic Roman capitals, dramatic, no modern warmth)
  - Body text / descriptions: **IM Fell English** (historical serif, imperfect letterforms, manuscript quality)
  - Metadata / catalog numbers / code: **Courier Prime** (monospace, precise, archival)
  Nothing geometric or neutral. Every typeface should read as having come from somewhere specific.

- **Layout disposition:**
  Irregular. Gallery grid with items at varying scales — no rigid columns. Works float in space rather than sitting in slots. Section labels as large typographic elements. No sidebars. Content within maximum width, generous horizontal margins.

- **Motion and interaction:**
  No decorative animation. CSS-only amber glow on artwork thumbnail hover — the glow emanates from the work itself, not cast on it. No JS-driven transitions. Hover states that reveal, not confirm.

- **What must never appear:**
  White or near-white backgrounds. Geometric neutral sans-serif (no Inter, Helvetica, system-ui as display type). Rounded corners on any UI element. Gradient hero sections. Auto-playing media. Emoji used decoratively. Stock photography. Any design element that reads as "modern web."

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

2026-06-04 · DIRECTION · Wants the site to feel like a world rather than a website — function serves atmosphere, not the other way around.
    [User: "This website should feel like it's set in my own world, rather than being rooted in reality."]

2026-06-04 · REFUSAL · "Rooted in reality" is explicitly what the design should not be.
    [User: "I want this website to have an extremely unconventional style to really feel like it's mine."]

2026-06-04 · TENSION · Chose the hybrid of Astral Catalog (floating void, catalog numbering) and Living Manuscript (archaic type, otherworldly artifact) when presented with three distinct directions — neither pure cosmic minimalism nor pure historical pastiche, but both at once.

2026-06-04 · DIRECTION · Artwork thumbnails in the gallery should be at irregular scales and positions — not snapped to a grid.

2026-06-04 · DIRECTION · Redesigned the admin Media Library to align with the "Celestial Archive" darkroom aesthetics, featuring a custom split layout, glowing image tiles, and deep crimson outlined danger buttons.

2026-06-04 · DIRECTION · Chose Pinyon Script (elegant copperplate calligraphy) for site-wide headings, paired with Lora for a highly stylized handwritten manuscript aesthetic.

2026-06-04 · DIRECTION · Requested a more colorful, combined celestial background featuring watercolor washes, dynamic nebulas, and coordinate grids while strictly enforcing hardware-accelerated, zero-repaint animations to preserve mobile device battery/resources.

2026-06-09 · DIRECTION · Chose a custom interactive tag-based search-and-select dropdown widget for multi-select inputs on the admin artwork form instead of standard checkboxes or native select fields.

2026-06-09 · DIRECTION · Chose a native HTML5 <dialog> element styled with a blur backdrop and dark celestial archive styling to handle inline category/exhibit renaming and confirmation overlay.

---

<!-- The agent holds the brush. You choose what gets painted.
     This document is how you tell the agent what you see. -->