# Current Project State

## Active Context
- **Current Phase**: Phase 4 — Institutional Preloader & Visual Experience
- **Current Milestone**: Redesigning Atmospheric SOA/ITER Preloader with Active Motion & Guaranteed Dismissal
- **Status**: Executing GSD phase spec `.planning/phases/04-landing-preloader.md` based on user visual feedback.

---

## Recent Decisions
- **2026-10-05**: Initialized workspace with GSD (Spec-Driven Development) framework.
- **2026-10-05**: Configured Apache & MySQL on XAMPP; created database `research_profile_db`.
- **2026-10-05**: Built complete application (11 DB tables, multi-role auth, assistant delegation, Google Scholar UI, directory, admin panel).
- **2026-10-05**: Conducted comprehensive CodeRabbit Quality & Security Gate audit:
  - Enforced strict HTTP POST method on all deletion and revocation mutations.
  - Hardened session cookies with `HttpOnly`, `SameSite=Lax`, and `session.use_strict_mode=1`.
  - Added URL protocol sanitization (`safe_url()` and `safe_orcid()`) to prevent XSS via `javascript:` links.
  - Eliminated raw database exception disclosures in `config/database.php`.
  - Added `.coderabbit.yaml` repository configuration with assertive review instructions.
  - Added `.github/workflows/security-quality.yml` GitHub Actions CI workflow.

---

## Blockers & Risks
- *None identified.*

---

## Next Steps
1. Create `database/schema.sql` and run `database/migrate.php` to establish all 11 tables and seed ITER departments.
2. Build security utilities (`includes/helpers.php`, `includes/csrf.php`, `includes/auth.php`).
3. Scaffold academic navigation shell (`includes/header.php`, `includes/footer.php`).
4. Implement and verify authentication (`login.php`, `register.php`, `logout.php`).
