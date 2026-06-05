# SQLite-Only CI and README Cleanup

Date: 2026-06-05
Packages: loyalty-rewards
Branch: cdx/drop-sqlite-only-ci
Status: completed

## Goal

Finalize the cleanup by removing MySQL/PostgreSQL CI jobs, making integration tests run via SQLite, and ensuring README examples are executable and production-safe.

## Completed

- Removed MySQL and PostgreSQL GitHub Actions integration jobs from `.github/workflows/tests.yml`.
- Kept the main matrix job on SQLite (`pdo_sqlite`) and preserved quality gates:
  - `composer validate --strict`
  - `composer stan`
  - `composer lint`
  - `composer test`
  - coverage gate on PHP 8.3
- Replaced `tests/Integration/TodoIntegrationTest.php` with `tests/Integration/LoyaltyServiceIntegrationTest.php`.
- Updated README snippets and setup guidance to align with the SQLite-only execution profile and removed emoji-heavy headings.

## Decisions

- Decision: drop MySQL/PostgreSQL integration jobs from CI.
  Rationale: reduce external infrastructure dependency and keep package CI deterministic.
- Decision: keep MySQL/PostgreSQL connection code untouched for now.
  Rationale: no runtime behavior change was required for this slice and existing compatibility can be preserved.

## Files Changed

- `.github/workflows/tests.yml` — Removed external DB jobs and MySQL/PostgreSQL extensions.
- `README.md` — Cleaned setup examples and database examples to SQLite.
- `tests/Integration/LoyaltyServiceIntegrationTest.php` — Added real integration coverage file.
- `tests/Integration/TodoIntegrationTest.php` — Removed placeholder file.
- `docs/progress/2026-06-05-sqlite-ci-cleanup.md` — Added implementation slice record.

## TDD / Validation

- Red: no code-breaking failures expected from a docs/CI cleanup slice.
- Green: `composer test`
- Broader check: `composer stan`, `composer lint`, `composer ci` all passed.

## Notes and Risks

- The adapter package and database factories still contain MySQL/PostgreSQL references.
- If the roadmap requires complete PostgreSQL/MySQL removal later, we can follow this with a compatibility removal pass.
