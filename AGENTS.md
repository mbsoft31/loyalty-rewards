# Repository Guidelines

## Project Structure & Module Organization
- Source code lives in `src/` using DDD-style layers:
  - `src/Domain` (Entities, ValueObjects, Enums, Repositories[*Interface])
  - `src/Core` (Engine, Services, Exceptions)
  - `src/Rules` (Earning/Redemption rules, Contracts, Composites)
  - `src/Infrastructure` (Database, Audit adapters)
  - `src/Application` (DTOs)
- Tests in `tests/Unit`, `tests/Feature`, `tests/Integration/*`.
- Docs in `API.md`, `ARCHITECTURE.md`, `CONFIGURATION.md`, examples in `EXAMPLES.md`.

## Build, Test, and Development Commands
- Install: `composer install`
- Autoload (optimize): `composer dump-autoload -o`
- All tests (Pest + PHPUnit 12): `composer test`
- Unit only: `composer test:unit`
- Feature only: `composer test:feature`
- Coverage HTML (to `coverage/`): `composer test:coverage`
- Parallel tests: `composer test:parallel`
- Static analysis: `composer stan`
- Style check: `composer lint`
- Style fix: `composer lint:fix`
- Full local gate: `composer ci`
- Filter tests example: `vendor/bin/pest --filter LoyaltyServiceTest`

## Coding Style & Naming Conventions
- PHP ≥ 8.3, PSR-12. Use `declare(strict_types=1);` in all PHP files.
- Indentation: 4 spaces; 120 char soft wrap; prefer early returns.
- Names: Classes `PascalCase`; interfaces end with `Interface`; methods `camelCase`; constants `UPPER_SNAKE_CASE`.
- PSR-4 namespace root `LoyaltyRewards\` maps to `src/`.
- Prefer Value Objects from `src/Domain/ValueObjects` in public APIs; keep DTOs `final readonly` when possible.

## Testing Guidelines
- Framework: Pest 4 (on PHPUnit 12). Test files end with `*Test.php`.
- Mirror paths: `src/Domain/ValueObjects/Points.php` → `tests/Unit/ValueObjects/PointsTest.php`.
- Integration tests use in-memory SQLite (see `phpunit.xml`); ensure `pdo_sqlite` is enabled.
- Aim for ≥80% coverage on services/rules. Include edge cases and failure paths.
- Prefer TDD for behavior changes: add or update a failing focused test first, implement the smallest fix, then run focused and broader checks.

## Loyalty Workflow Rules
- Record implementation progress in the workspace progress docs after each completed slice.
- Keep this package focused on framework-agnostic core behavior. Laravel HTTP/API/product workflows belong in `../loyalty-laravel-pro`.
- For cross-package changes, document the contract in both package docs or in the workspace `docs/progress` note.

## Commit & Pull Request Guidelines
- Use Conventional Commits (e.g., `feat: add tier bonus rule`, `fix: correct redemption cap`).
- Commits: concise subject, useful body; mention breaking changes clearly.
- PRs: include summary, linked issues, screenshots/logs if relevant; update docs (`API.md`, `ARCHITECTURE.md`, `CONFIGURATION.md`); add/adjust tests; ensure CI passes.

## Security & Configuration Tips
- Never commit secrets. Tests run against SQLite `:memory:`; local files under `storage/` are for dev only.
- Required extensions: `pdo`, `pdo_sqlite`. Configure via `phpunit.xml` env when testing.
