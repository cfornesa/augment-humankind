# Replit Agent Context

See AGENTS.md for all project context, conventions, and task ownership.

<!-- AGENTS.md is the authoritative rule set for every agentic tool,
     including Replit Agent. Only add here what Replit needs that
     other tools do not. -->

**Replit is not a runtime target for this codebase.** Production runs on
Hostinger/PHP (see CONSTRAINTS.md and MEMORY.md). The `platform/` directory
is the exported, reference-only copy of a retired Replit/Node app — do not
treat it as the app to run, modify, or deploy, and never write to the legacy
platform database (`PLATFORM_*` env vars are read/export-only).

If this repo is opened in Replit anyway: the PHP app's docroot is `public/`;
local run command is `php -S 0.0.0.0:8080 -t public public/index.php`;
setup procedure is SETUP.md.
