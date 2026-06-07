# Changelog

All notable changes to this project will be documented in this file.

## [1.3.1] - 2026-06-07

Developer-facing documentation release for the core policy engine.

- Added `CORE_POLICY_ENGINE.md` with plan config shape, method map, typed
  result DTO reference, adapter boundary checklist, validation commands, and
  release checklist.
- Linked the policy guide from README and API docs.
- Updated architecture docs to include the plan-policy layer.
- No runtime behavior changes.

## [1.1.0] - 2025-10-08

Initial public release candidate with production‑grade architecture, >80% test coverage, and framework adapter.

- Core
  - Domain‑Driven design with value objects, events, repositories
  - Rules engine (earning/redemption) with composable rules
  - Fraud detection primitives and audit service
  - Driver‑aware UPSERT for portability (MySQL + Pg/SQLite)
- Laravel Adapter
  - Service provider, config publish and migration loading
  - Config‑driven rule bootstrap (earning + redemption)
  - Schema builder migrations (accounts, transactions, audit)
- Testing & CI
  - 119 tests, 348 assertions; coverage ~82.4%
  - GitHub Actions coverage gate (>=80%)
  - DB matrix job for Postgres 16 and MySQL 8
  - PHPStan + PHP‑CS‑Fixer wired (non‑blocking in CI)
- Docs
  - Configuration guide, API getting started, examples, status snapshot
  - Premium pack plan and skeleton
- Premium Pack (skeleton)
  - Advanced tier + partner catalog earning rules
  - Campaign‑based redemption rule

[1.1.0]: https://github.com/mbsoft31/loyalty-rewards/releases/tag/v1.1.0
[1.3.1]: https://github.com/mbsoft31/loyalty-rewards/releases/tag/v1.3.1
