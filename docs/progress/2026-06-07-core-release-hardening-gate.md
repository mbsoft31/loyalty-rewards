# Core Release Hardening Gate

Date: 2026-06-07
Package: `loyalty-rewards`
Status: implemented

## Goal

Make the core package release gate explicit and keep local validation aligned with
GitHub Actions.

## Completed

- Added `docs/RELEASE_READINESS.md` with the core ownership boundary, validation gate,
  release rules, and pre-tag checklist.
- Updated `.github/workflows/tests.yml` to run the package `composer ci` script across
  PHP 8.3, 8.4, and 8.5.
- Added workflow permissions, concurrency, `COMPOSER_NO_INTERACTION`, and matrix
  `fail-fast: false`.
- Kept the PHP 8.3 coverage job and coverage threshold after the regular package gate.

## Validation

Passed:

```powershell
composer validate --strict
composer test
composer stan
composer lint
composer audit --format=plain
```

Result:

- `composer validate --strict`: valid
- `composer test`: 128 tests, 390 assertions
- `composer stan`: no errors
- `composer lint`: passed
- `composer audit --format=plain`: no security vulnerability advisories found

## Notes

The only expected dirty artifact before this work was `.phpunit.cache/test-results`.
That generated file remains outside the source changes for this hardening slice.
