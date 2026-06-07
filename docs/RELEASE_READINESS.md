# Loyalty Rewards Release Readiness

This is the release gate for `mbsoft31/loyalty-rewards`. The package is the public,
framework-agnostic loyalty policy engine. A release is ready only when this checklist
is true from a clean checkout.

## Ownership Boundary

Core owns:

- Plan policy normalization and lookup.
- Earning, redemption, tier, reward, mission, badge, and referral policy math.
- Plain PHP DTOs, value objects, services, and typed policy results.
- Deterministic behavior that can be tested without Laravel, HTTP, Eloquent, Carbon,
  queues, cache, or database-specific assumptions.

Core must not own:

- Laravel service providers, config helpers, middleware, controllers, resources, UI,
  migrations, publish commands, route registration, or Eloquent persistence.
- Customer-facing API payload compatibility for Laravel Pro. Core can expose typed
  results; Pro maps those results to its public contracts.

## Required Local Gate

Run:

```powershell
composer ci
```

The `ci` script must include:

- `composer validate --strict`
- `composer lint`
- `composer stan`
- `composer test`
- `composer audit --format=plain`

For local release confidence, also run coverage before tagging:

```powershell
composer test:clover
```

## GitHub Actions Gate

The workflow must run the package `composer ci` script on:

- PHP 8.3
- PHP 8.4
- PHP 8.5

The PHP 8.3 job also runs coverage and enforces the configured statement coverage
threshold. Workflow and local gates should remain aligned so passing locally means
the same quality bar as CI, except for the CI-only coverage threshold.

## Release Rules

- Patch release: bug fix, docs, or internal hardening that preserves public APIs.
- Minor release: additive core DTOs, policy services, result fields, or new pure policy
  capabilities.
- Major release: renamed namespaces, removed public methods, changed result semantics,
  or changed required PHP major range.

Before tagging:

1. Confirm the working tree has no source changes except intentionally ignored generated
   artifacts.
2. Run `composer ci`.
3. Run `composer test:clover` when coverage is part of the release decision.
4. Confirm Laravel Pro still validates against the release tag when Pro depends on it.
5. Add a progress note under `docs/progress/`.

