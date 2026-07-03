# GitHub Copilot Instructions

This project uses AGENTS.md as its standing instruction set.
Read AGENTS.md at the project root before responding to any request.

<!-- AGENTS.md is the authoritative rule set for every agentic tool,
     including Copilot. Modes, the Seven Rules, skills, memory files,
     and the coupled-CMS Project Specific Rules live there. Only add
     here what Copilot needs that other tools do not. -->

## Instruction Priority

When instructions conflict, resolve in this order:

1. Explicit statement from the person during the session
2. SESSION CONSTRAINTS or PHASE CONSTRAINTS block in the opening prompt
3. AGENTS.md Seven Rules
4. Loaded skill content
5. These Copilot instructions

## Project Memory Files

Read at session start, in this order: `AGENTS.md`, `MEMORY.md`,
`DECISIONS.md`, `CONSTRAINTS.md` (`DESIGN.md` only when design work
occurs). Their governance table is in AGENTS.md → Memory Files.

## Skills

Skills live in `.agents/skills/` (one directory per skill containing
`SKILL.md`). The authoritative load-on-demand table is in AGENTS.md →
Skills; never pre-load.

## Copilot-Specific Mode Mapping

Copilot maps its states onto AGENTS.md → Mode as:

- **Chat mode** = Interactive: full question and gallery protocols.
  Ask before building. Wait for answers.
- **Inline suggestion mode** = Inline Edit: mechanical changes only.
  No architectural decisions via inline suggestion — surface
  significant choices as a chat comment instead.
- **Copilot Workspace plan generation** = Plan/Propose: present the
  plan as a gallery of approaches; do not implement until the person
  approves a direction. The Plan/Propose gallery-suppression note in
  AGENTS.md → Mode applies.

## Reminders (already binding via AGENTS.md — restated for inline contexts)

- Never break a public URL; keep `GET /export.json`, `GET /feed.xml`,
  and `GET /feed.json` functional at all times.
- Never edit AGENTS.md without explicit human instruction.
- Never auto-syndicate content. Syndication is always human-initiated.
- This is a coupled multi-site CMS: schema changes dual-ship
  (docs/migrations file + setup-database.php manifest step), no
  site-specific content in code, new features register feature flags.
  See AGENTS.md → Project Specific Rules and SETUP.md.
