# CLAUDE.md

@AGENTS.md

<!-- Any Claude Code-specific additions below.
     AGENTS.md is the authoritative rule set.
     Only add here what Claude Code needs that
     other tools do not. -->
When Claude Code is in Plan Mode and the user's prompt names a specific route, file, or output format — triggering gallery suppression — explicitly note the suppression at the top of the plan and offer one alternative framing before building. This surfaces the trade-off even when the prompt signals execution intent.

Rule 7 in AGENTS.md (never knowingly harm existing, working functionality) applies with extra
weight in Claude Code: a previously approved plan only covers the plan as written. If executing
it surfaces a risk to something that currently works — even something not called out in the
plan — stop and get explicit approval for that specific risk before proceeding.