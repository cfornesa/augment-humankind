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
STATUS: Honored throughout — every DECISIONS.md session from 2026-06-14 through 2026-06-18 reaffirms read-only platform DB access; no write path to the platform database exists in any migration/readiness script.

CONSTRAINT: User-specific pages must be accessible under the path pattern "domain.com/user/{username}" (canonical; /profiles/{slug} is a redirect alias), and comment submission must restrict guest inputs to logged-in profiles or prompt them to authenticate to prevent spam.
SCOPE: Authentication controllers, user profile rendering, and comment submission handling.
SET: 2026-06-15
STATUS: /user/{username} routes implemented 2026-06-16. Signed-in-only comment submission and owner-only edit/delete controls are now implemented across posts, pieces, exhibits, and exhibit collections.

CONSTRAINT: Sign-in is site-wide. An active admin session satisfies the public user auth check. No separate re-login may be required when navigating from /admin to /user/* pages or any other public-facing authenticated surface.
SCOPE: auth.php helpers, all controllers that call user_logged_in() or current_user().
SET: 2026-06-16
STATUS: Implemented the same day it was set (DECISIONS.md 2026-06-16, "Session UX/Style Fixes: Auth Unification") — admin session is treated as a site-wide login; user_logged_in() and current_user() cover both sessions.

CONSTRAINT: The Home and About pages can never be deleted (soft or hard delete). Only these two pages — no others — carry this protection.
SCOPE: Page model (Page::PROTECTED_SLUGS, softDelete(), hardDelete()), Admin/PagesController delete/hardDelete/trashEmpty, and the admin pages list UI.
SET: 2026-06-19
STATUS: Implemented the same day it was set (DECISIONS.md 2026-06-19, "Home and About are now protected 'system pages'") — slug-based guard in Page.php, self-healing creation of the About page via Page::ensureSystemPages(), delete controls hidden in the admin UI, and direct delete attempts verified rejected against the live DB.
