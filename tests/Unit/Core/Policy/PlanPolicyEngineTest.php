<?php

use LoyaltyRewards\Core\Policy\CustomerLoyaltyState;
use LoyaltyRewards\Core\Policy\PlanConfigFactory;
use LoyaltyRewards\Core\Policy\PlanPolicyEngine;
use LoyaltyRewards\Core\Policy\Results\BadgeItemResult;
use LoyaltyRewards\Core\Policy\Results\MissionItemResult;
use LoyaltyRewards\Core\Policy\Results\ReferralStatsResult;
use LoyaltyRewards\Core\Policy\Results\RewardEligibilityResult;
use LoyaltyRewards\Core\Policy\Results\TierProgressResult;

function loyaltyPolicyEngine(array $plans, string $activePlan = 'starter'): PlanPolicyEngine
{
    return new PlanPolicyEngine(PlanConfigFactory::fromArray($plans, $activePlan));
}

function vipPolicyPlans(): array
{
    return [
        'starter' => [
            'currency' => 'USD',
            'points' => ['base_rate' => ['cents' => 1, 'points' => 1]],
            'earning_rules' => [],
            'redemption_rules' => [
                ['type' => 'basic', 'points_per_dollar' => 100, 'minimum_points' => 500],
            ],
        ],
        'vip_tiers' => [
            'currency' => 'USD',
            'points' => ['base_rate' => ['cents' => 1, 'points' => 1], 'pending_until' => 'order_fulfilled'],
            'tiers' => [
                'silver' => ['lifetime_points' => 0, 'multiplier' => 1.0],
                'gold' => ['lifetime_points' => 50000, 'multiplier' => 1.25],
                'platinum' => ['lifetime_points' => 150000, 'multiplier' => 1.5],
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
            'rewards' => [
                [
                    'id' => 'reward-free-shipping-vip',
                    'key' => 'free_shipping_vip',
                    'title' => 'Free Shipping',
                    'description' => 'Free shipping on your next order.',
                    'cost_points' => 5000,
                    'estimated_value_cents' => 1200,
                    'is_available_default' => true,
                    'inventory' => ['state' => 'in_stock', 'remaining' => null],
                    'eligibility' => ['required_tier' => 'gold', 'min_points' => 5000, 'max_per_customer' => 2],
                    'category' => 'shipping',
                    'tags' => ['shipping', 'vip'],
                ],
            ],
        ],
        'referral_growth' => [
            'currency' => 'USD',
            'earning_rules' => [
                ['type' => 'fixed_bonus', 'category' => 'referrer_conversion', 'points' => 7500],
                ['type' => 'fixed_bonus', 'category' => 'referee_first_purchase', 'points' => 2500],
            ],
            'abuse_controls' => [
                'max_referrals_per_customer_per_month' => 10,
                'require_unique_referee_email' => true,
                'require_first_paid_order' => true,
            ],
        ],
    ];
}

describe('PlanConfigFactory and PlanCatalog', function () {
    it('normalizes plan arrays into definitions', function () {
        $catalog = PlanConfigFactory::fromArray(vipPolicyPlans(), 'vip_tiers');

        expect($catalog->activeKey())->toBe('vip_tiers')
            ->and($catalog->has('starter'))->toBeTrue()
            ->and($catalog->get('vip_tiers')->currency())->toBe('USD')
            ->and($catalog->get('vip_tiers')->rewards())->toHaveCount(1);
    });
});

describe('PlanPolicyEngine earning and redemption policy', function () {
    it('calculates base fallback, category, minimum spend, fixed bonus, and tier bonus points', function () {
        $engine = loyaltyPolicyEngine(vipPolicyPlans(), 'vip_tiers');

        expect($engine->calculatePoints(1250, [], 'vip_tiers'))->toBe(1250)
            ->and($engine->calculatePoints(1000, ['category' => 'electronics'], 'vip_tiers'))->toBe(2000)
            ->and($engine->calculatePoints(10000, ['category' => 'electronics'], 'vip_tiers'))->toBe(32000)
            ->and($engine->calculatePoints(1000, ['category' => 'referrer_conversion'], 'vip_tiers'))->toBe(7500)
            ->and($engine->calculatePoints(1000, ['tier' => 'gold'], 'vip_tiers'))->toBe(1250);
    });

    it('calculates pending and redemption policy', function () {
        $engine = loyaltyPolicyEngine(vipPolicyPlans(), 'vip_tiers');

        expect($engine->pointsArePending('vip_tiers'))->toBeTrue()
            ->and($engine->pointsArePending('starter'))->toBeFalse()
            ->and($engine->minimumRedemptionPoints('vip_tiers'))->toBe(500)
            ->and($engine->calculateRedemptionValueCents(500, 'vip_tiers'))->toBe(500)
            ->and($engine->calculateRedemptionValueCents(100, 'vip_tiers'))->toBe(100);
    });
});

describe('PlanPolicyEngine tier and reward policy', function () {
    it('calculates tier progress and requirement checks', function () {
        $engine = loyaltyPolicyEngine(vipPolicyPlans(), 'vip_tiers');

        $progress = $engine->tierProgress(70000, 'vip_tiers');

        expect($progress)->not->toBeNull()
            ->and($progress['current_tier'])->toBe('gold')
            ->and($progress['next_tier'])->toBe('platinum')
            ->and($progress['points_to_next'])->toBe(80000)
            ->and($progress['progress_percent'])->toBe(20.0)
            ->and($engine->tierProgress(1234, 'starter'))->toBeNull()
            ->and($engine->meetsTierRequirement(70000, 'gold', 'vip_tiers'))->toBeTrue()
            ->and($engine->meetsTierRequirement(70000, 'platinum', 'vip_tiers'))->toBeFalse()
            ->and($engine->meetsTierRequirement(70000, 'diamond', 'vip_tiers'))->toBeFalse()
            ->and($engine->meetsTierRequirement(70000, '', 'vip_tiers'))->toBeTrue();
    });

    it('resolves reward payload and redemption availability separately', function () {
        $engine = loyaltyPolicyEngine(vipPolicyPlans(), 'vip_tiers');
        $state = CustomerLoyaltyState::make('cust-1', 'vip_tiers', 70000, 0, 70000);
        $reward = $engine->reward('free_shipping_vip', 'vip_tiers');

        $payload = $engine->rewardPayload($reward, $state, 'vip_tiers');
        expect($payload['is_available'])->toBeTrue()
            ->and($engine->rewardIsRedeemable($reward, $state, 'vip_tiers'))->toBeTrue();

        $soldOut = [...$reward, 'inventory' => ['state' => 'sold_out', 'remaining' => 0]];
        expect($engine->rewardPayload($soldOut, $state, 'vip_tiers')['is_available'])->toBeTrue()
            ->and($engine->rewardIsRedeemable($soldOut, $state, 'vip_tiers'))->toBeFalse();

        $lowTier = CustomerLoyaltyState::make('cust-2', 'vip_tiers', 70000, 0, 20000);
        expect($engine->rewardPayload($reward, $lowTier, 'vip_tiers')['is_available'])->toBeFalse()
            ->and($engine->rewardIsRedeemable($reward, $lowTier, 'vip_tiers'))->toBeFalse();
    });

    it('returns typed tier and reward results without changing legacy payload arrays', function () {
        $engine = loyaltyPolicyEngine(vipPolicyPlans(), 'vip_tiers');
        $state = CustomerLoyaltyState::make('cust-1', 'vip_tiers', 70000, 0, 70000);
        $reward = $engine->reward('free_shipping_vip', 'vip_tiers');

        $tierResult = $engine->tierProgressResult(70000, 'vip_tiers');
        $rewardEligibility = $engine->rewardEligibility($reward, $state, 'vip_tiers');

        expect($tierResult)->toBeInstanceOf(TierProgressResult::class)
            ->and($tierResult->toArray())->toBe($engine->tierProgress(70000, 'vip_tiers'))
            ->and($rewardEligibility)->toBeInstanceOf(RewardEligibilityResult::class)
            ->and($rewardEligibility->isAvailable)->toBeTrue()
            ->and($rewardEligibility->isRedeemable)->toBeTrue()
            ->and($rewardEligibility->toPayload($reward, 'USD'))->toBe($engine->rewardPayload($reward, $state, 'vip_tiers'));
    });
});

describe('PlanPolicyEngine mission and badge policy', function () {
    it('builds mission board items with deterministic time and clamped progress', function () {
        $engine = loyaltyPolicyEngine(vipPolicyPlans());
        $now = new DateTimeImmutable('2026-06-06T00:00:00+00:00');
        $missions = [
            ['id' => 'm1', 'key' => 'visit', 'title' => 'Visit', 'description' => 'Visit stores.', 'target' => 3, 'current' => 1, 'reward_points' => 100, 'expires_in_days' => 17],
            ['id' => 'm2', 'key' => 'review', 'title' => 'Review', 'description' => 'Review.', 'target' => 1, 'current' => 1, 'reward_points' => 50],
            ['id' => 'm3', 'key' => 'expired', 'title' => 'Expired', 'description' => 'Expired.', 'target' => 1, 'current' => 1, 'expires_at' => '2026-06-01T00:00:00.000Z'],
            ['id' => 'm4', 'key' => 'over', 'title' => 'Over', 'description' => 'Over target.', 'target' => 2, 'current' => 5],
        ];

        $board = $engine->missionBoard($missions, ['visit' => ['current' => 2]], '', 10, $now);

        expect($board['summary'])->toBe(['active' => 1, 'completed' => 2, 'expired' => 1])
            ->and($board['missions'][0]['current'])->toBe(2)
            ->and($board['missions'][0]['progress_percent'])->toBe(66.67)
            ->and($board['missions'][0]['expires_at'])->toBe('2026-06-23T00:00:00.000Z')
            ->and($board['missions'][1]['status'])->toBe('completed')
            ->and($board['missions'][2]['status'])->toBe('expired')
            ->and($board['missions'][3]['progress_percent'])->toBe(100.0);
    });

    it('builds badge state from available, pending, and lifetime point sources', function () {
        $engine = loyaltyPolicyEngine(vipPolicyPlans());
        $state = CustomerLoyaltyState::make('cust-1', 'referral_growth', 2000, 300, 10000, [
            'badges' => [
                'lifetime' => ['earned_at' => '2026-06-01T00:00:00.000Z'],
            ],
        ]);

        $available = $engine->badgeItem([
            'id' => 'b1',
            'badge_key' => 'available',
            'label' => 'Available',
            'requirement' => ['source' => 'available_points', 'required_points' => 3000],
            'meta' => ['earned_message' => 'Unlocked.'],
        ], $state, 'referral_growth');
        $pending = $engine->badgeItem([
            'id' => 'b2',
            'badge_key' => 'pending',
            'label' => 'Pending',
            'requirement' => ['source' => 'pending_points', 'required_points' => 300],
        ], $state, 'referral_growth');
        $lifetime = $engine->badgeItem([
            'id' => 'b3',
            'badge_key' => 'lifetime',
            'label' => 'Lifetime',
            'requirement' => ['source' => 'lifetime_points', 'required_points' => 10000],
        ], $state, 'referral_growth');

        expect($available['state'])->toBe('in_progress')
            ->and($available['progress'])->toBe(2000)
            ->and($available['earned_at'])->toBeNull()
            ->and($pending['state'])->toBe('unlocked')
            ->and($lifetime['state'])->toBe('unlocked')
            ->and($lifetime['earned_at'])->toBe('2026-06-01T00:00:00.000Z')
            ->and($engine->badgeScope('bad-input'))->toBe('all');
    });

    it('returns typed mission and badge results without changing legacy payload arrays', function () {
        $engine = loyaltyPolicyEngine(vipPolicyPlans());
        $now = new DateTimeImmutable('2026-06-06T00:00:00+00:00');
        $mission = ['id' => 'm1', 'key' => 'visit', 'title' => 'Visit', 'description' => 'Visit stores.', 'target' => 3, 'current' => 1, 'reward_points' => 100, 'expires_in_days' => 17];
        $state = CustomerLoyaltyState::make('cust-1', 'referral_growth', 2000, 300, 10000);
        $badge = [
            'id' => 'b1',
            'badge_key' => 'available',
            'label' => 'Available',
            'requirement' => ['source' => 'available_points', 'required_points' => 3000],
            'meta' => ['earned_message' => 'Unlocked.'],
        ];

        $missionResult = $engine->missionItemResult($mission, ['visit' => ['current' => 2]], $now);
        $badgeResult = $engine->badgeItemResult($badge, $state, 'referral_growth');

        expect($missionResult)->toBeInstanceOf(MissionItemResult::class)
            ->and($missionResult->toArray())->toBe($engine->missionItem($mission, ['visit' => ['current' => 2]], $now))
            ->and($badgeResult)->toBeInstanceOf(BadgeItemResult::class)
            ->and($badgeResult->toArray())->toBe($engine->badgeItem($badge, $state, 'referral_growth'));
    });
});

describe('PlanPolicyEngine referral policy', function () {
    it('derives referral config and computes codes, urls, stats, and risk', function () {
        $engine = loyaltyPolicyEngine(vipPolicyPlans(), 'referral_growth');
        $referrals = $engine->referrals('referral_growth');

        expect($referrals['reward_rule']['inviter_points'])->toBe(7500)
            ->and($referrals['reward_rule']['invitee_points'])->toBe(2500)
            ->and($referrals['monthly_cap'])->toBe(10);

        $code = $engine->referralCodeValue('referral_growth', 'account-1', 'customer-1', ['code_prefix' => 'FRI END!']);
        expect($code)->toBe('FRIEND-'.strtoupper(substr(hash('sha256', 'referral_growth|account-1|customer-1'), 0, 8)))
            ->and($engine->referralShareUrl(['share_url_base' => 'https://example.test/ref/{code}'], 'FRIEND ABC'))->toBe('https://example.test/ref/FRIEND%20ABC')
            ->and($engine->referralShareUrl(['share_url_base' => 'https://example.test/ref'], 'FRIEND ABC'))->toBe('https://example.test/ref/FRIEND%20ABC');

        $holdStats = $engine->referralConversionStats(6, 2, 4, ['inviter_points' => 100, 'hold_until_paid' => true]);
        $instantStats = $engine->referralConversionStats(20, 2, 4, ['inviter_points' => 100, 'hold_until_paid' => false]);

        expect($holdStats['rewards'])->toBe(['earned_points' => 200, 'pending_points' => 400, 'risk_level' => 'medium'])
            ->and($instantStats['rewards'])->toBe(['earned_points' => 600, 'pending_points' => 0, 'risk_level' => 'low'])
            ->and($engine->referralConversionStatus(['hold_until_paid' => true]))->toBe('pending')
            ->and($engine->referralConversionStatus(['hold_until_paid' => false]))->toBe('converted');
    });

    it('returns typed referral stats without changing legacy payload arrays', function () {
        $engine = loyaltyPolicyEngine(vipPolicyPlans(), 'referral_growth');
        $rewardRule = ['inviter_points' => 100, 'hold_until_paid' => true];

        $stats = $engine->referralConversionStatsResult(6, 2, 4, $rewardRule);

        expect($stats)->toBeInstanceOf(ReferralStatsResult::class)
            ->and($stats->toArray())->toBe($engine->referralConversionStats(6, 2, 4, $rewardRule))
            ->and($stats->earnedPoints)->toBe(200)
            ->and($stats->pendingPoints)->toBe(400)
            ->and($stats->riskLevel)->toBe('medium');
    });
});
