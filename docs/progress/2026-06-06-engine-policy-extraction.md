# Plan Policy Engine Extraction

Date: 2026-06-06
Packages: loyalty-rewards
Status: completed

## Goal

Move the pure plan-policy behavior used by Laravel Pro into the framework-agnostic core package while preserving the existing rules engine, earning rules, redemption rules, value objects, and models.

## Completed

- Added the `LoyaltyRewards\Core\Policy` layer:
  - `PlanConfigFactory`
  - `PlanCatalog`
  - `PlanDefinition`
  - `CustomerLoyaltyState`
  - `PlanPolicyEngine`
- Added `FixedBonusRule` for Laravel Pro earning-rule parity.
- Moved pure plan behavior into core:
  - earning point calculation and pending policy,
  - redemption value and minimum-point policy,
  - tier progress and tier requirement checks,
  - reward lookup, display availability, redemption availability, and reward metadata,
  - mission board item/status/progress derivation,
  - badge requirement, progress source, scope, state, and earned timestamp normalization,
  - referral fallback config, code generation value, share URL, reward rule normalization, conversion stats, conversion status, and risk level.
- Used `DateTimeImmutable` inputs for mission expiry calculations so core tests can stay deterministic.

## Decisions

- Decision: keep `RulesEngine` as the calculation mechanism for configured earning rules.
  Rationale: this avoids replacing existing tested domain primitives while adding the plan-array adapter layer Laravel Pro needs.
- Decision: keep reward display availability and redemption availability as separate core methods.
  Rationale: the current API lists rewards with payload flags, but redemption must also enforce inventory state and remaining count.
- Decision: keep core ignorant of Laravel, Carbon, Eloquent, HTTP, config helpers, and database drivers.
  Rationale: `loyalty-rewards` remains the reusable policy engine.

## Files Changed

- `src/Core/Policy/CustomerLoyaltyState.php`
- `src/Core/Policy/PlanCatalog.php`
- `src/Core/Policy/PlanConfigFactory.php`
- `src/Core/Policy/PlanDefinition.php`
- `src/Core/Policy/PlanPolicyEngine.php`
- `src/Rules/Earning/FixedBonusRule.php`
- `tests/Unit/Core/Policy/PlanPolicyEngineTest.php`
- `tests/Unit/Rules/FixedBonusRuleTest.php`
- `docs/progress/2026-06-06-engine-policy-extraction.md`

## TDD / Validation

- Characterization coverage added for plan config normalization, earning, redemption, tiers, rewards, missions, badges, and referrals.
- `composer validate --strict` passed.
- `composer test` passed: 125 tests, 375 assertions.
- `composer stan` passed.
- `composer lint` passed.

## Notes and Risks

- `loyalty-rewards/.phpunit.cache/test-results` remains a generated local test artifact and was not treated as source.
- Laravel Pro validation used this local core checkout during adapter refactor verification.
- Release coordination should tag this core surface as `v1.2.0` before Laravel Pro permanently requires `mbsoft31/loyalty-rewards:^1.2`.
