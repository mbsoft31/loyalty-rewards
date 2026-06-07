# Core Policy Docs Release

Date: 2026-06-07

## Objective

Revise the developer-facing core package surface, validate the public core
package, and cut a release without changing Laravel Pro or the demo.

## Scope

- Document the `LoyaltyRewards\Core\Policy` package for adapter maintainers.
- Keep runtime behavior unchanged.
- Keep `loyalty-laravel-pro` out of scope except for existing boundary context.
- Ignore the existing generated `.phpunit.cache/test-results` workspace noise.

## Changes

- Added `CORE_POLICY_ENGINE.md` with:
  - primary class responsibilities,
  - plan config shape,
  - customer state DTO guidance,
  - full `PlanPolicyEngine` method map,
  - typed result DTO field reference,
  - adapter boundary checklist,
  - deterministic testing notes,
  - compatibility policy,
  - validation and release checklist.
- Linked the guide from `README.md` and `API.md`.
- Updated `ARCHITECTURE.md` to include the plan-policy layer.
- Added `CHANGELOG.md` entry for `v1.3.1`.

## Validation

- `composer validate --strict` - passed
- `composer test` - passed, 128 tests, 390 assertions
- `composer stan` - passed, no errors
- `composer lint` - passed

## Release

Planned tag: `v1.3.1`

Rationale: patch release because this slice is documentation/release metadata
only and does not change runtime behavior or public API contracts.
