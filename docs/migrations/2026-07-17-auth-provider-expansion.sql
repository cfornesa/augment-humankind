-- Auth provider expansion: admin login via Microsoft, Facebook, and email
-- magic-link. Member-side needs no schema change (accounts.provider is
-- VARCHAR; verification_tokens already exists from 2026-06-14).
-- Mirrored by the probe-guarded "auth provider expansion (2026-07-17)" step
-- in scripts/setup-database.php.

ALTER TABLE admin_identities
  MODIFY provider ENUM('github', 'google', 'microsoft', 'facebook', 'email') NOT NULL;
