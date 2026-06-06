# Policy Result DTOs

Date: 2026-06-06
Package: loyalty-rewards
Status: completed

## Goal

Refine the plan-policy API so reusable policy decisions are exposed as typed result objects instead of only broad array payloads, while preserving Laravel Pro's existing array contracts.

## Completed

- Added typed policy result DTOs:
  - `RewardEligibilityResult`
  - `TierProgressResult`
  - `MissionItemResult`
  - `BadgeItemResult`
  - `ReferralStatsResult`
- Added typed `PlanPolicyEngine` methods:
  - `rewardEligibility()`
  - `tierProgressResult()`
  - `missionItemResult()`
  - `badgeItemResult()`
  - `referralConversionStatsResult()`
- Kept the existing array-returning methods as compatibility adapters:
  - `rewardPayload()`
  - `rewardIsRedeemable()`
  - `tierProgress()`
  - `missionItem()`
  - `badgeItem()`
  - `referralConversionStats()`
- Updated core tests to assert every typed result converts back to the same legacy array payload.

## Decisions

- Decision: DTOs expose `toArray()` or payload conversion methods rather than replacing existing public array methods.
  Rationale: Laravel Pro payload compatibility is the acceptance target for this slice.
- Decision: reward eligibility is represented separately from reward catalog payload shape.
  Rationale: core should expose the reusable decision (`isAvailable`, `isRedeemable`, tier/minimum/inventory facts), while adapters can still present the current resource shape.

## Files Changed

- `src/Core/Policy/PlanPolicyEngine.php`
- `src/Core/Policy/Results/RewardEligibilityResult.php`
- `src/Core/Policy/Results/TierProgressResult.php`
- `src/Core/Policy/Results/MissionItemResult.php`
- `src/Core/Policy/Results/BadgeItemResult.php`
- `src/Core/Policy/Results/ReferralStatsResult.php`
- `tests/Unit/Core/Policy/PlanPolicyEngineTest.php`
- `docs/progress/2026-06-06-policy-result-dtos.md`

## TDD / Validation

- Focused validation: `composer test -- --filter PlanPolicyEngine` passed.
- Static analysis: `composer stan` passed.

## Notes and Risks

- This is a new core API surface and should be tagged before Laravel Pro depends on the typed result methods.
- The broad array methods remain available for compatibility.
