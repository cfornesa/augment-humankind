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
  <!-- Filename if available in your repository.
       Example: assets/logo.svg
       Leave blank if none exists yet. -->

- **Existing brand materials:**
  <!-- Any other files — type specimens, color swatches, style guides —
       already committed to the repo. -->

---

## Derived Identity
<!-- AGENT-PROPOSED, HUMAN-CONFIRMED
     The agent fills this section collaboratively after References exist,
     by asking questions and proposing observations. You confirm, correct,
     or expand each entry before it is considered stable.
     Do not fill this section yourself before discussing it with the agent —
     the value is in the derivation process, not the output alone. -->

- **What your references share:**
  <!-- The common thread the agent observes across your inputs.
       Example: "High contrast, sparse layout, type as the primary
       visual element, no decorative illustration." -->

- **The tension you are navigating:**
  <!-- The productive contradiction in your references — the thing
       that makes your aesthetic specific rather than generic.
       Example: "Warm and personal, but not precious or nostalgic." -->

- **What you dislike in contrast:**
  <!-- Defined negatively. Taste is largely shaped by refusal.
       Be specific — "corporate" is a start, but "the visual language
       of SaaS landing pages: gradient CTAs, stock photography of
       smiling teams, artificial whitespace" is more useful.
       This section informs the agent's gallery options as strongly
       as positive preferences do. -->

- **The feeling on first load:**
  <!-- One to three words or a short sentence describing the immediate
       emotional register you want a first-time visitor to experience.
       Example: "Curious. Like finding someone's personal library." -->

---

## Declared Preferences
<!-- HUMAN-AUTHORED, after Derived Identity is complete.
     These are starting points, not permanent constraints.
     If you change your mind, update this section and note the
     change in DECISIONS.md. Do not move taste preferences to
     CONSTRAINTS.md unless they become technical requirements. -->

- **Color direction:**
  <!-- A palette, a mood, specific hex values, or a reference.
       Example: "Dark background. Warm off-white text. One accent
       color, used sparingly — leaning toward ochre or rust." -->

- **Type direction:**
  <!-- Specific typefaces, or a descriptive direction if typefaces
       are not yet chosen.
       Example: "Serif for body, monospace for metadata and code.
       Nothing geometric or neutral — something with visible history." -->

- **Layout disposition:**
  <!-- How you want space and content to relate.
       Example: "Generous margins. Text width constrained. No sidebars.
       Let the content breathe." -->

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

---

<!-- The agent holds the brush. You choose what gets painted.
     This document is how you tell the agent what you see. -->
