# Constraints

<!-- One entry per constraint. Format:
     CONSTRAINT: [plain-language description]
     SCOPE: [what it applies to]
     SET: [date or "this session"]

     Constraints are permanent until explicitly lifted.
     See AGENTS.md → User Constraints for full rules. -->


<!-- An empty file is still valid and still required.
     Absence of entries means no constraints have been stated yet —
     not that this file is optional. The agent must create this
     file at project root the first time any constraint is stated,
     even if AGENTS.md is read-only. -->

CONSTRAINT: The platform database is live and must not be modified by this migration. Agents may read/export existing platform data for migration, but must not add, edit, delete, migrate in place, run schema changes against, or otherwise mutate the platform database.
SCOPE: Platform database access, migration scripts, reconciliation tooling, testing, and deployment procedure.
SET: 2026-06-14
