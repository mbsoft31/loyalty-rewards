# Core Policy Engine Developer Guide

This guide documents the framework-agnostic policy layer introduced under
`LoyaltyRewards\Core\Policy`. It is intended for package authors and adapter
maintainers who need to evaluate loyalty plans without depending on Laravel,
Eloquent, HTTP requests, queues, or database state.

## Scope

The policy engine owns pure loyalty decisions:

- Point earning math from plan rules.
- Pending-point policy.
- Redemption value and minimum redemption policy.
- Tier progress and tier requirement checks.
- Reward catalog eligibility and redeemability.
- Mission item status and progress calculation.
- Badge item state and progress calculation.
- Referral code, share URL, reward rule, abuse hint, status, stats, and risk
  math.

The policy engine does not own persistence, idempotency, transactions, audit
logs, API response resources, validation, migrations, publish hooks, or UI
components. Adapters such as Laravel Pro should map their framework models into
core DTOs, call the engine, then map results back into their public payloads.

## Primary Classes

| Class | Responsibility |
| --- | --- |
| `PlanConfigFactory` | Normalizes raw plan config arrays into a `PlanCatalog`. |
| `PlanCatalog` | Holds named `PlanDefinition` objects and the active plan key. |
| `PlanDefinition` | Read-only accessor around one normalized plan config. |
| `CustomerLoyaltyState` | Plain PHP state object for customer points, plan, and metadata. |
| `PlanPolicyEngine` | Pure policy service for earning, redemption, tiers, rewards, missions, badges, and referrals. |

Typed result objects live in `LoyaltyRewards\Core\Policy\Results`:

- `RewardEligibilityResult`
- `TierProgressResult`
- `MissionItemResult`
- `BadgeItemResult`
- `ReferralStatsResult`

Each result exposes scalar properties and a payload conversion method. Existing
array-returning methods remain available for compatibility.

## Minimal Wiring

```php
<?php

declare(strict_types=1);

use LoyaltyRewards\Core\Policy\CustomerLoyaltyState;
use LoyaltyRewards\Core\Policy\PlanConfigFactory;
use LoyaltyRewards\Core\Policy\PlanPolicyEngine;

$plans = [
    'restaurant_visits' => [
        'currency' => 'USD',
        'points' => [
            'base_rate' => ['cents' => 1, 'points' => 1],
            'pending_until' => 'immediate',
        ],
        'earning_rules' => [
            ['type' => 'category_multiplier', 'category' => 'dine_in', 'multiplier' => 2.0],
            ['type' => 'minimum_spend', 'minimum' => 50.00, 'multiplier' => 1.2],
            ['type' => 'fixed_bonus', 'category' => 'mission_claim', 'points' => 500],
            ['type' => 'tier_bonus', 'tier' => 'gold', 'multiplier' => 1.25],
        ],
        'redemption_rules' => [
            ['type' => 'basic', 'points_per_dollar' => 100, 'minimum_points' => 500],
        ],
        'tiers' => [
            'bronze' => ['lifetime_points' => 0],
            'gold' => ['lifetime_points' => 50000, 'priority_support' => true],
        ],
    ],
];

$catalog = PlanConfigFactory::fromArray($plans, 'restaurant_visits');
$policy = new PlanPolicyEngine($catalog);

$state = CustomerLoyaltyState::make(
    customerId: 'customer_123',
    plan: 'restaurant_visits',
    availablePoints: 12000,
    pendingPoints: 0,
    lifetimePoints: 64000,
    metadata: [],
);

$earned = $policy->calculatePoints(
    amountCents: 7500,
    context: ['category' => 'dine_in', 'tier' => 'gold'],
    planKey: 'restaurant_visits',
);

$tier = $policy->tierProgressResult($state->lifetimePoints, $state->plan);

echo $earned;
echo $tier?->currentTier;
```

## Plan Config Shape

The core accepts Laravel Pro-style plan arrays. Unknown keys are retained in the
plan config but ignored by policy methods that do not need them.

```php
$plans = [
    'plan_key' => [
        'currency' => 'USD',
        'points' => [
            'base_rate' => ['cents' => 1, 'points' => 1],
            'pending_until' => 'immediate',
        ],
        'earning_rules' => [
            ['type' => 'category_multiplier', 'category' => 'electronics', 'multiplier' => 2.0],
            ['type' => 'minimum_spend', 'minimum' => 50.00, 'multiplier' => 1.2],
            ['type' => 'fixed_bonus', 'category' => 'referrer_conversion', 'points' => 7500],
            ['type' => 'tier_bonus', 'tier' => 'gold', 'multiplier' => 1.25],
        ],
        'redemption_rules' => [
            ['type' => 'basic', 'points_per_dollar' => 100, 'minimum_points' => 500],
        ],
        'tiers' => [
            'silver' => ['lifetime_points' => 0],
            'gold' => ['lifetime_points' => 50000],
        ],
        'rewards' => [
            [
                'id' => 'reward-free-shipping',
                'key' => 'free_shipping',
                'title' => 'Free Shipping',
                'description' => 'Free shipping on the next order.',
                'cost_points' => 5000,
                'estimated_value_cents' => 1200,
                'is_available_default' => true,
                'inventory' => ['state' => 'in_stock', 'remaining' => null],
                'eligibility' => [
                    'required_tier' => 'gold',
                    'min_points' => 5000,
                    'max_per_customer' => 1,
                ],
                'tags' => ['shipping'],
            ],
        ],
        'missions' => [
            [
                'id' => 'mission-weekly-visit',
                'key' => 'weekly_visit',
                'title' => 'Visit this week',
                'description' => 'Complete one store visit.',
                'target' => 1,
                'reward_points' => 500,
                'expires_in_days' => 7,
            ],
        ],
        'badges' => [
            [
                'id' => 'badge-first-referral',
                'badge_key' => 'first-referral',
                'label' => 'First Referral',
                'requirement' => [
                    'source' => 'lifetime_points',
                    'required_points' => 7500,
                ],
            ],
        ],
        'referrals' => [
            'code_prefix' => 'FRIEND',
            'share_url_base' => 'https://example.test/r/{code}',
            'monthly_cap' => 10,
            'reward_rule' => [
                'inviter_points' => 7500,
                'invitee_points' => 2500,
                'hold_until_paid' => true,
            ],
        ],
        'abuse_controls' => [
            'max_referrals_per_customer_per_month' => 10,
            'require_unique_referee_email' => true,
            'require_first_paid_order' => true,
        ],
    ],
];
```

## Customer State

`CustomerLoyaltyState` is the only customer object expected by policy methods.

```php
$state = CustomerLoyaltyState::make(
    customerId: 'customer_123',
    plan: 'plan_key',
    availablePoints: 5000,
    pendingPoints: 1000,
    lifetimePoints: 25000,
    metadata: [
        'badges' => [
            'first-referral' => ['earned_at' => '2026-06-01T00:00:00.000Z'],
        ],
        'missions' => [
            'weekly_visit' => ['current' => 1],
        ],
    ],
);
```

Adapters own the mapping from persistence into this DTO. Core deliberately does
not know about model classes, database column names, or metadata storage format
beyond the arrays passed into policy calls.

## Method Map

### Earning and Redemption

| Method | Return | Notes |
| --- | --- | --- |
| `calculatePoints(int $amountCents, array $context = [], ?string $planKey = null)` | `int` | Applies plan earning rules. If no rule matches, falls back to the plan base rate. Negative input is clamped to zero. |
| `pointsArePending(?string $planKey = null)` | `bool` | `false` only when `points.pending_until` is `immediate`. |
| `minimumRedemptionPoints(?string $planKey = null)` | `int` | Reads the first redemption rule minimum, defaulting to 100. |
| `calculateRedemptionValueCents(int $points, ?string $planKey = null)` | `int` | Uses the first basic redemption rule and falls back to `points_per_dollar` math. |

### Tiers

| Method | Return | Notes |
| --- | --- | --- |
| `tiers(?string $planKey = null)` | `array` | Raw normalized tier definitions. |
| `hasTiers(?string $planKey = null)` | `bool` | `true` when at least one tier exists. |
| `tierProgressResult(int $lifetimePoints, ?string $planKey = null)` | `?TierProgressResult` | Preferred typed API. Returns `null` for plans without tiers. |
| `tierProgress(int $lifetimePoints, ?string $planKey = null)` | `?array` | Compatibility wrapper around `tierProgressResult()`. |
| `meetsTierRequirement(int $lifetimePoints, string $requiredTier, ?string $planKey = null)` | `bool` | Empty requirement is always allowed. Unknown requirements are rejected. |

### Rewards

| Method | Return | Notes |
| --- | --- | --- |
| `rewards(?string $planKey = null)` | `array` | Raw reward definitions. |
| `hasRewards(?string $planKey = null)` | `bool` | `true` when at least one reward exists. |
| `reward(string $rewardKey, ?string $planKey = null)` | `?array` | Finds one reward by `key`. |
| `rewardEligibility(array $reward, CustomerLoyaltyState $state, ?string $planKey = null)` | `RewardEligibilityResult` | Preferred typed API. Separates availability from redeemability. |
| `rewardPayload(array $reward, CustomerLoyaltyState $state, ?string $planKey = null)` | `array` | Compatibility payload used by adapters. |
| `rewardIsRedeemable(array $reward, CustomerLoyaltyState $state, ?string $planKey = null)` | `bool` | Checks availability plus inventory state. |
| `rewardMetadata(array $reward, string $currency)` | `array` | Stable metadata for persistence/audit context. |

Availability is customer-facing eligibility. Redeemability also requires
inventory to allow redemption. A sold-out reward may still be visible as
available to the customer but not redeemable.

### Missions

| Method | Return | Notes |
| --- | --- | --- |
| `missions(?string $planKey = null)` | `array` | Raw mission definitions. |
| `hasMissions(?string $planKey = null)` | `bool` | `true` when at least one mission exists. |
| `missionItemResult(array $mission, array $accountMissionProgress, ?DateTimeImmutable $now = null)` | `MissionItemResult` | Preferred typed API. Pass `$now` in tests for deterministic expiration. |
| `missionItem(array $mission, array $accountMissionProgress, ?DateTimeImmutable $now = null)` | `array` | Compatibility wrapper around `missionItemResult()`. |
| `missionBoard(array $missions, array $accountMissionProgress, string $requestedStatus = '', int $limit = 20, ?DateTimeImmutable $now = null)` | `array` | Builds mission list plus active/completed/expired summary. |

Core calculates status and progress only. Claim state, duplicate claim
protection, wallet transactions, and audit records belong to adapters.

### Badges

| Method | Return | Notes |
| --- | --- | --- |
| `badges(?string $planKey = null)` | `array` | Raw badge definitions. |
| `hasBadges(?string $planKey = null)` | `bool` | `true` when at least one badge exists. |
| `badgeItemResult(array $badge, CustomerLoyaltyState $state, string $plan)` | `BadgeItemResult` | Preferred typed API. |
| `badgeItem(array $badge, CustomerLoyaltyState $state, string $plan)` | `array` | Compatibility wrapper around `badgeItemResult()`. |
| `badgeScope(string $scope)` | `string` | Normalizes to `all`, `earned`, or `locked`. |

Core determines whether requirements are satisfied. Persisting `earned_at`,
ensuring a badge is awarded once, and preserving earned state after point spend
are adapter responsibilities.

Supported badge requirement sources:

- `available_points`
- `pending_points`
- `lifetime_points`

### Referrals

| Method | Return | Notes |
| --- | --- | --- |
| `referrals(?string $planKey = null)` | `array` | Raw referral config, or derived fallback for `referral_growth`. |
| `hasReferrals(?string $planKey = null)` | `bool` | `true` when referral config is present or derived. |
| `referralCodeValue(string $plan, string $accountId, string $customerId, array $referrals)` | `string` | Sanitizes prefix and appends deterministic hash. |
| `referralShareUrl(array $referrals, string $code)` | `string` | Replaces `{code}` or appends the encoded code to the base URL. |
| `referralRewardRule(array $referrals)` | `array` | Normalizes inviter/invitee points and hold policy. |
| `referralAbuseHints(string $planKey, array $referrals)` | `array` | Merges referral hints with plan abuse controls. |
| `referralConversionStatsResult(int $totalReferrals, int $convertedReferrals, int $pendingReferrals, array $rewardRule)` | `ReferralStatsResult` | Preferred typed API. |
| `referralConversionStats(...)` | `array` | Compatibility wrapper around `referralConversionStatsResult()`. |
| `referralConversionStatus(array $rewardRule)` | `string` | `pending` when `hold_until_paid` is true, otherwise `converted`. |

Referral creation, uniqueness, conversion confirmation, duplicate confirmation
handling, point mutation, and wallet event payloads remain adapter concerns.

## Typed Results

### `RewardEligibilityResult`

Properties:

- `isAvailable`
- `isRedeemable`
- `requiredTier`
- `minimumPoints`
- `maxPerCustomer`
- `inventoryState`
- `inventoryRemaining`

Use `toPayload(array $reward, string $currency)` when an adapter needs the
legacy reward catalog payload.

### `TierProgressResult`

Properties:

- `currentTier`
- `currentTierLabel`
- `nextTier`
- `nextTierLabel`
- `currentValue`
- `targetValue`
- `pointsToNext`
- `progressPercent`
- `benefits`
- `period`
- `forecastPointsToNext30d`

Use `toArray()` for the legacy tier payload.

### `MissionItemResult`

Properties:

- `id`
- `key`
- `title`
- `description`
- `status`
- `current`
- `target`
- `progressPercent`
- `rewardPoints`
- `expiresAt`
- `nextRewardEligibleAt`

Use `toArray()` for the legacy mission payload.

### `BadgeItemResult`

Properties:

- `id`
- `badgeKey`
- `label`
- `description`
- `iconKey`
- `rarity`
- `state`
- `earnedAt`
- `progress`
- `target`
- `category`
- `rewardKey`
- `requirement`
- `plan`
- `meta`

Use `toArray()` for the legacy badge payload.

### `ReferralStatsResult`

Properties:

- `totalReferrals`
- `convertedReferrals`
- `pendingReferrals`
- `nextRewardAt`
- `earnedPoints`
- `pendingPoints`
- `riskLevel`

Use `toArray()` for the legacy referral stats payload.

## Adapter Boundary Checklist

When integrating this package into a framework package:

1. Load framework config arrays and pass them through `PlanConfigFactory`.
2. Keep a framework-facing service, such as `PlanRegistry`, as the stable
   adapter entry point.
3. Map account models into `CustomerLoyaltyState` before calling core.
4. Prefer typed result methods inside adapter code.
5. Map typed results back to existing HTTP/resource payloads in one explicit
   mapper.
6. Keep database locks, transactions, idempotency, duplicate-error mapping,
   metadata writes, audit logs, and UI events in the adapter.
7. Keep docs and OpenAPI in the adapter when a framework payload changes.

## Deterministic Tests

Pass `DateTimeImmutable` into mission methods when expiration is involved:

```php
$now = new DateTimeImmutable('2026-06-07T00:00:00+00:00');

$item = $policy->missionItemResult(
    mission: $mission,
    accountMissionProgress: ['weekly_visit' => ['current' => 1]],
    now: $now,
);
```

This keeps mission status and `expires_at` assertions stable.

## Compatibility Policy

Core result DTOs are the preferred internal API. Array-returning methods are
kept as compatibility wrappers for adapters that already expose those arrays as
HTTP or UI payloads. Removing or reshaping compatibility arrays is a breaking
change and should be reserved for a major version.

Patch and minor releases may add:

- New typed result objects.
- New scalar properties on typed result objects when backwards compatible.
- New policy methods.
- New ignored config keys.
- Additional documentation and tests.

Patch and minor releases should not:

- Add framework dependencies.
- Require Laravel, Carbon, Eloquent, HTTP, queue, or database classes.
- Move persistence/idempotency concerns into core.
- Change public array payload keys consumed by adapters.

## Validation

Run the full core gate before releasing:

```bash
composer validate --strict
composer test
composer stan
composer lint
```

For a complete CI-style local run:

```bash
composer ci
```

## Release Checklist

1. Confirm `git status --short` only contains intended core changes.
2. Run the validation commands above.
3. Commit the changes.
4. Create an annotated SemVer tag. Composer reads package versions from tags.
5. Push the branch and tag.

Example:

```bash
git commit -m "Document core policy engine"
git tag -a v1.3.1 -m "v1.3.1"
git push origin main
git push origin v1.3.1
```
