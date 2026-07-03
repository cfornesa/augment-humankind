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

CONSTRAINT: Sign-in is site-wide. An active admin session satisfies the public user auth check, and vice versa for identities that pass the admin allowlist. No separate re-login may be required when navigating from /admin to /user/* pages or any other public-facing authenticated surface, and logging out from either side must end both sessions.
SCOPE: auth.php helpers, AuthController/UserAuthController OAuth callbacks, all controllers that call user_logged_in() or current_user().
SET: 2026-06-16
STATUS: Implemented 2026-06-16, but regressed silently by 2026-06-19 — admin login was setting only admin_identity_id (not user_id), and user_logout() was clearing only the member session keys, leaving admin_identity_id intact and causing current_user()'s admin-fallback to silently re-log the user back in after a public-side logout. Re-fixed same day (DECISIONS.md 2026-06-19, "OAuth Callback Unification + Site-Wide Session Symmetry") — login and logout are now symmetric across both sessions in both directions, with the admin-granting direction from member login gated by the existing oauth_allowed_identity() allowlist check. Watch for this regression pattern any time auth.php, AuthController, or UserAuthController are touched in isolation from each other.

CONSTRAINT: The Home and About pages can never be deleted (soft or hard delete). Only these two pages — no others — carry this protection.
SCOPE: Page model (Page::PROTECTED_SLUGS, softDelete(), hardDelete()), Admin/PagesController delete/hardDelete/trashEmpty, and the admin pages list UI.
SET: 2026-06-19
STATUS: Implemented the same day it was set (DECISIONS.md 2026-06-19, "Home and About are now protected 'system pages'") — slug-based guard in Page.php, self-healing creation of the About page via Page::ensureSystemPages(), delete controls hidden in the admin UI, and direct delete attempts verified rejected against the live DB.

CONSTRAINT: Admin and member OAuth login (GitHub and Google) must share a single callback URL per provider (/auth/{provider}/callback), never a separate callback path per login surface. This app only holds 2 registered OAuth apps (one GitHub, one Google); splitting the callback path per flow would require 4 registered redirect URIs instead of 2, and the user has explicitly rejected creating additional OAuth apps to work around this.
SCOPE: public/app/helpers/oauth.php (shared_oauth_redirect_uri()), SharedAuthController.php, AuthController::handleCallback()/oauthStart(), UserAuthController::handleCallback()/oauthStart(), router.php auth routes.
SET: 2026-06-19
STATUS: Implemented the same day it was set (DECISIONS.md 2026-06-19, "OAuth Callback Unification + Site-Wide Session Symmetry") — both login surfaces now build the same shared_oauth_redirect_uri() and dispatch through SharedAuthController based on pending session state, not URL path.

CONSTRAINT: Feed-imported and other external HTML is intentionally rendered unsanitized so external HTML can execute. HTML authoring and approval paths are admin-only; the accepted risk includes third-party feed HTML executing site-wide and in the admin moderation view, including invisible-vector moderation risk.
SCOPE: Feed ingest, feed approval, stored post rendering, and admin moderation views that display external HTML.
SET: 2026-07-03
STATUS: Owner accepted the risk during the platform-harvest search upgrade; no sanitizer should be added unless this constraint is explicitly revisited.
