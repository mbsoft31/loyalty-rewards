<?php

declare(strict_types=1);

namespace LoyaltyRewards\Core\Policy;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use LoyaltyRewards\Core\Engine\RulesEngine;
use LoyaltyRewards\Domain\ValueObjects\ConversionRate;
use LoyaltyRewards\Domain\ValueObjects\Currency;
use LoyaltyRewards\Domain\ValueObjects\Money;
use LoyaltyRewards\Domain\ValueObjects\Points;
use LoyaltyRewards\Domain\ValueObjects\TransactionContext;
use LoyaltyRewards\Rules\Earning\CategoryMultiplierRule;
use LoyaltyRewards\Rules\Earning\FixedBonusRule;
use LoyaltyRewards\Rules\Earning\MinimumSpendRule;
use LoyaltyRewards\Rules\Earning\TierBonusRule;
use LoyaltyRewards\Rules\Redemption\BasicRedemptionRule;
use Psr\Log\NullLogger;

final readonly class PlanPolicyEngine
{
    public function __construct(private PlanCatalog $catalog) {}

    public function catalog(): PlanCatalog
    {
        return $this->catalog;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function calculatePoints(int $amountCents, array $context = [], ?string $planKey = null): int
    {
        $plan = $this->catalog->get($planKey);
        $baseRate = $this->baseConversionRate($plan);
        $money = Money::fromCents(max(0, $amountCents), new Currency($plan->currency()));
        $context = [
            'category' => (string) ($context['category'] ?? 'default'),
            'tier' => (string) ($context['tier'] ?? ''),
            ...$context,
        ];
        $transactionContext = TransactionContext::create($context);
        $contextWithAmount = $transactionContext->with('amount', $money);
        $engine = $this->rulesEngineFor($plan);

        if ($engine->getApplicableEarningRules($contextWithAmount) === []) {
            return $money->convertToPoints($baseRate)->value();
        }

        return max(0, $engine->calculateEarning($money, $transactionContext)->value());
    }

    public function pointsArePending(?string $planKey = null): bool
    {
        $pendingUntil = (string) ($this->catalog->get($planKey)->points()['pending_until'] ?? 'immediate');

        return $pendingUntil !== 'immediate';
    }

    public function calculateRedemptionValueCents(int $points, ?string $planKey = null): int
    {
        $plan = $this->catalog->get($planKey);
        $rule = $this->firstRedemptionRule($plan);
        $engine = new RulesEngine(new NullLogger);
        $engine->addRedemptionRule(new BasicRedemptionRule(
            new Currency($plan->currency()),
            max(1, (int) ($rule['points_per_dollar'] ?? 100)),
            (int) ($rule['minimum_points'] ?? 100),
        ));

        $value = $engine->calculateRedemption(
            Points::fromInt(max(0, $points)),
            TransactionContext::redemption(['plan' => $plan->key()])
        );

        if ($value === null) {
            $pointsPerDollar = max(1, (int) ($rule['points_per_dollar'] ?? 100));

            return (int) round(($points / $pointsPerDollar) * 100);
        }

        return $value->amount();
    }

    public function minimumRedemptionPoints(?string $planKey = null): int
    {
        return (int) ($this->firstRedemptionRule($this->catalog->get($planKey))['minimum_points'] ?? 100);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function tiers(?string $planKey = null): array
    {
        return $this->catalog->get($planKey)->tiers();
    }

    public function hasTiers(?string $planKey = null): bool
    {
        return $this->tiers($planKey) !== [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rewards(?string $planKey = null): array
    {
        return $this->catalog->get($planKey)->rewards();
    }

    public function hasRewards(?string $planKey = null): bool
    {
        return $this->rewards($planKey) !== [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function missions(?string $planKey = null): array
    {
        return $this->catalog->get($planKey)->missions();
    }

    public function hasMissions(?string $planKey = null): bool
    {
        return $this->missions($planKey) !== [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function badges(?string $planKey = null): array
    {
        return $this->catalog->get($planKey)->badges();
    }

    public function hasBadges(?string $planKey = null): bool
    {
        return $this->badges($planKey) !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function referrals(?string $planKey = null): array
    {
        $planKey ??= $this->catalog->activeKey();
        $plan = $this->catalog->get($planKey);
        $referrals = $plan->referrals();

        if ($referrals !== []) {
            return $referrals;
        }

        if ($planKey === 'referral_growth') {
            return $this->derivedReferralGrowthConfig($plan);
        }

        return [];
    }

    public function hasReferrals(?string $planKey = null): bool
    {
        return $this->referrals($planKey) !== [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function reward(string $rewardKey, ?string $planKey = null): ?array
    {
        foreach ($this->rewards($planKey) as $reward) {
            if ((string) ($reward['key'] ?? '') === $rewardKey) {
                return $reward;
            }
        }

        return null;
    }

    public function meetsTierRequirement(int $lifetimePoints, string $requiredTier, ?string $planKey = null): bool
    {
        if ($requiredTier === '') {
            return true;
        }

        if (! $this->hasTiers($planKey)) {
            return false;
        }

        $progress = $this->tierProgress($lifetimePoints, $planKey);
        if ($progress === null || ! is_string($progress['current_tier'] ?? null)) {
            return false;
        }

        $rank = [];
        foreach ($this->normalizedTiers($this->tiers($planKey)) as $index => $tier) {
            $rank[(string) $tier['key']] = $index;
        }

        $currentTier = (string) $progress['current_tier'];

        return array_key_exists($requiredTier, $rank)
            && array_key_exists($currentTier, $rank)
            && $rank[$currentTier] >= $rank[$requiredTier];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function tierProgress(int $lifetimePoints, ?string $planKey = null): ?array
    {
        $tiers = $this->normalizedTiers($this->tiers($planKey));
        if ($tiers === []) {
            return null;
        }

        $currentIndex = 0;
        foreach ($tiers as $index => $tier) {
            if ($lifetimePoints >= (int) $tier['lifetime_points']) {
                $currentIndex = $index;

                continue;
            }

            break;
        }

        $currentTier = $tiers[$currentIndex];
        $nextTier = $tiers[$currentIndex + 1] ?? null;
        $currentThreshold = (int) $currentTier['lifetime_points'];
        $nextThreshold = $nextTier !== null ? (int) $nextTier['lifetime_points'] : $currentThreshold;
        $pointsToNext = $nextThreshold > $lifetimePoints ? $nextThreshold - $lifetimePoints : 0;
        $progressPercent = $nextTier === null
            ? 100.0
            : (($lifetimePoints - $currentThreshold) / max(1, $nextThreshold - $currentThreshold)) * 100;

        return [
            'current_tier' => (string) $currentTier['key'],
            'current_tier_label' => $this->humanizeTierLabel((string) $currentTier['key']),
            'next_tier' => $nextTier !== null ? (string) $nextTier['key'] : null,
            'next_tier_label' => $nextTier !== null ? $this->humanizeTierLabel((string) $nextTier['key']) : null,
            'current_value' => $lifetimePoints,
            'target_value' => $nextThreshold,
            'points_to_next' => $pointsToNext,
            'progress_percent' => (float) round(max(0.0, min(100.0, $progressPercent)), 2),
            'benefits' => $this->formatTierBenefits((array) $currentTier['benefits']),
            'period' => null,
            'forecast_points_to_next_30d' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $reward
     * @return array<string, mixed>
     */
    public function rewardPayload(array $reward, CustomerLoyaltyState $state, ?string $planKey = null): array
    {
        $planKey ??= $state->plan;
        $eligibility = (array) ($reward['eligibility'] ?? []);
        $inventory = (array) ($reward['inventory'] ?? []);
        $requiredTier = (string) ($eligibility['required_tier'] ?? '');
        $minimumPoints = (int) ($eligibility['min_points'] ?? 0);

        return [
            'id' => (string) ($reward['id'] ?? ''),
            'key' => (string) ($reward['key'] ?? ''),
            'title' => (string) ($reward['title'] ?? ''),
            'description' => (string) ($reward['description'] ?? ''),
            'cost_points' => (int) ($reward['cost_points'] ?? 0),
            'currency' => $this->catalog->get($planKey)->currency(),
            'category' => (string) ($reward['category'] ?? 'general'),
            'estimated_value_cents' => (int) ($reward['estimated_value_cents'] ?? 0),
            'is_available' => (bool) ($reward['is_available_default'] ?? true)
                && $state->lifetimePoints >= $minimumPoints
                && $this->meetsTierRequirement($state->lifetimePoints, $requiredTier, $planKey),
            'eligibility' => [
                'required_tier' => (string) ($eligibility['required_tier'] ?? ''),
                'min_points' => $minimumPoints,
                'max_per_customer' => $eligibility['max_per_customer'] ?? null,
            ],
            'inventory' => [
                'state' => (string) ($inventory['state'] ?? 'unavailable'),
                'remaining' => $inventory['remaining'] ?? null,
            ],
            'tags' => (array) ($reward['tags'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $reward
     */
    public function rewardIsRedeemable(array $reward, CustomerLoyaltyState $state, ?string $planKey = null): bool
    {
        $planKey ??= $state->plan;

        if (! (bool) ($reward['is_available_default'] ?? true)) {
            return false;
        }

        $inventory = (array) ($reward['inventory'] ?? []);
        $stateName = (string) ($inventory['state'] ?? 'unavailable');
        if (in_array($stateName, ['sold_out', 'unavailable'], true)) {
            return false;
        }

        if (($inventory['remaining'] ?? null) !== null && (int) $inventory['remaining'] < 1) {
            return false;
        }

        $eligibility = (array) ($reward['eligibility'] ?? []);
        $minimumPoints = (int) ($eligibility['min_points'] ?? 0);
        $requiredTier = (string) ($eligibility['required_tier'] ?? '');

        return $state->lifetimePoints >= $minimumPoints
            && $this->meetsTierRequirement($state->lifetimePoints, $requiredTier, $planKey);
    }

    /**
     * @param  array<string, mixed>  $reward
     * @return array<string, mixed>
     */
    public function rewardMetadata(array $reward, string $currency): array
    {
        return [
            'id' => (string) ($reward['id'] ?? ''),
            'key' => (string) ($reward['key'] ?? ''),
            'title' => (string) ($reward['title'] ?? ''),
            'category' => (string) ($reward['category'] ?? 'general'),
            'cost_points' => (int) ($reward['cost_points'] ?? 0),
            'estimated_value_cents' => (int) ($reward['estimated_value_cents'] ?? 0),
            'currency' => $currency,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $missions
     * @param  array<string, mixed>  $accountMissionProgress
     * @return array{missions: array<int, array<string, mixed>>, summary: array{active: int, completed: int, expired: int}}
     */
    public function missionBoard(
        array $missions,
        array $accountMissionProgress,
        string $requestedStatus = '',
        int $limit = 20,
        ?DateTimeImmutable $now = null,
    ): array {
        $items = [];
        $summary = [
            'active' => 0,
            'completed' => 0,
            'expired' => 0,
        ];

        foreach ($missions as $mission) {
            $item = $this->missionItem($mission, $accountMissionProgress, $now);

            if (array_key_exists($item['status'], $summary)) {
                $summary[$item['status']]++;
            }

            if ($requestedStatus !== '' && $item['status'] !== $requestedStatus) {
                continue;
            }

            $items[] = $item;
        }

        return [
            'missions' => array_slice($items, 0, max(1, $limit)),
            'summary' => $summary,
        ];
    }

    /**
     * @param  array<string, mixed>  $mission
     * @param  array<string, mixed>  $accountMissionProgress
     * @return array<string, mixed>
     */
    public function missionItem(array $mission, array $accountMissionProgress, ?DateTimeImmutable $now = null): array
    {
        $key = (string) ($mission['key'] ?? '');
        $progress = (array) ($accountMissionProgress[$key] ?? []);
        $target = max(1, (int) ($mission['target'] ?? 1));
        $current = max(0, (int) ($progress['current'] ?? ($mission['current'] ?? 0)));
        $expiresAt = $this->missionExpiresAt($mission, $now);
        $status = $this->missionStatus($current, $target, $expiresAt, $now);

        return [
            'id' => (string) ($mission['id'] ?? ''),
            'key' => $key,
            'title' => (string) ($mission['title'] ?? ''),
            'description' => (string) ($mission['description'] ?? ''),
            'status' => $status,
            'current' => $current,
            'target' => $target,
            'progress_percent' => (float) min(100.0, round(($current / $target) * 100, 2)),
            'reward_points' => (int) ($mission['reward_points'] ?? 0),
            'expires_at' => $expiresAt,
            'next_reward_eligible_at' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $badge
     * @return array<string, mixed>
     */
    public function badgeItem(array $badge, CustomerLoyaltyState $state, string $plan): array
    {
        $requirement = $this->badgeRequirement($badge);
        $target = (int) ($requirement['target'] ?? 0);
        $progressSource = (string) ($requirement['source'] ?? 'available_points');
        $rawProgress = $this->badgeProgressSource($progressSource, $state);
        $progress = $target > 0 ? max(0, min((int) $rawProgress, $target)) : 0;
        $badgeState = (array) ($state->metadata['badges'] ?? []);
        $badgeKey = (string) ($badge['badge_key'] ?? $badge['key'] ?? '');
        $badgeStateMeta = (array) ($badgeState[$badgeKey] ?? []);
        $stateName = $this->badgeState($target, $progress);
        $earnedAt = $stateName === 'unlocked' ? (string) ($badgeStateMeta['earned_at'] ?? '') : '';
        $meta = (array) ($badge['meta'] ?? []);
        $earnedMessage = (string) ($meta['earned_message'] ?? '');
        $requirementText = (string) ($requirement['label'] ?? '');

        return [
            'id' => (string) ($badge['id'] ?? ''),
            'badge_key' => $badgeKey,
            'label' => (string) ($badge['label'] ?? ''),
            'description' => (string) ($badge['description'] ?? ''),
            'icon_key' => (string) ($badge['icon_key'] ?? ''),
            'rarity' => (string) ($badge['rarity'] ?? 'common'),
            'state' => $stateName,
            'earned_at' => $earnedAt !== '' ? $earnedAt : null,
            'progress' => $progress,
            'target' => $target,
            'category' => (string) ($badge['category'] ?? ''),
            'reward_key' => $badge['reward_key'] ?? null,
            'requirement' => (array) ($badge['requirement'] ?? []),
            'plan' => $plan,
            'meta' => [
                'earned_message' => $requirementText !== '' ? "{$earnedMessage} {$requirementText}" : $earnedMessage,
            ],
        ];
    }

    public function badgeScope(string $scope): string
    {
        return match ($scope) {
            'earned', 'locked' => $scope,
            default => 'all',
        };
    }

    /**
     * @param  array<string, mixed>  $referrals
     */
    public function referralCodeValue(string $plan, string $accountId, string $customerId, array $referrals): string
    {
        $prefix = preg_replace('/[^A-Za-z0-9]/', '', (string) ($referrals['code_prefix'] ?? 'FRIEND'));
        $prefix = $prefix !== null && $prefix !== '' ? strtoupper($prefix) : 'FRIEND';
        $hash = strtoupper(substr(hash('sha256', "{$plan}|{$accountId}|{$customerId}"), 0, 8));

        return "{$prefix}-{$hash}";
    }

    /**
     * @param  array<string, mixed>  $referrals
     */
    public function referralShareUrl(array $referrals, string $code): string
    {
        $base = trim((string) ($referrals['share_url_base'] ?? ''));

        if ($base === '') {
            return '';
        }

        if (str_contains($base, '{code}')) {
            return str_replace('{code}', rawurlencode($code), $base);
        }

        return rtrim($base, '/').'/'.rawurlencode($code);
    }

    /**
     * @param  array<string, mixed>  $referrals
     * @return array<string, mixed>
     */
    public function referralRewardRule(array $referrals): array
    {
        $rule = (array) ($referrals['reward_rule'] ?? []);

        return [
            'inviter_points' => (int) ($rule['inviter_points'] ?? 0),
            'invitee_points' => (int) ($rule['invitee_points'] ?? 0),
            'hold_until_paid' => (bool) ($rule['hold_until_paid'] ?? true),
        ];
    }

    /**
     * @param  array<string, mixed>  $referrals
     * @return array<string, mixed>
     */
    public function referralAbuseHints(string $planKey, array $referrals): array
    {
        $abuseControls = $this->catalog->get($planKey)->abuseControls();
        $abuseHints = (array) ($referrals['abuse_hints'] ?? []);

        return [
            'monthly_cap' => (int) ($referrals['monthly_cap'] ?? $abuseControls['max_referrals_per_customer_per_month'] ?? 0),
            'require_unique_referee_email' => (bool) ($abuseHints['require_unique_referee_email'] ?? $abuseControls['require_unique_referee_email'] ?? false),
            'require_first_paid_order' => (bool) ($abuseHints['require_first_paid_order'] ?? $abuseControls['require_first_paid_order'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $rewardRule
     * @return array{
     *     status: array{total_referrals: int, converted_referrals: int, pending_referrals: int, next_reward_at: null|string},
     *     rewards: array{earned_points: int, pending_points: int, risk_level: string}
     * }
     */
    public function referralConversionStats(
        int $totalReferrals,
        int $convertedReferrals,
        int $pendingReferrals,
        array $rewardRule
    ): array {
        $holdUntilPaid = (bool) ($rewardRule['hold_until_paid'] ?? true);
        $inviterPoints = (int) ($rewardRule['inviter_points'] ?? 0);
        $earnedPoints = $convertedReferrals * $inviterPoints;
        $pendingPoints = $pendingReferrals * $inviterPoints;

        if (! $holdUntilPaid) {
            $earnedPoints = ($convertedReferrals + $pendingReferrals) * $inviterPoints;
            $pendingPoints = 0;
        }

        return [
            'status' => [
                'total_referrals' => $totalReferrals,
                'converted_referrals' => $convertedReferrals,
                'pending_referrals' => $pendingReferrals,
                'next_reward_at' => null,
            ],
            'rewards' => [
                'earned_points' => $earnedPoints,
                'pending_points' => $pendingPoints,
                'risk_level' => $this->referralRiskLevel($totalReferrals),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $rewardRule
     */
    public function referralConversionStatus(array $rewardRule): string
    {
        return (bool) ($rewardRule['hold_until_paid'] ?? true) ? 'pending' : 'converted';
    }

    private function rulesEngineFor(PlanDefinition $plan): RulesEngine
    {
        $engine = new RulesEngine(new NullLogger);
        $baseRate = $this->baseConversionRate($plan);
        $currency = new Currency($plan->currency());

        foreach ($plan->earningRules() as $rule) {
            $type = (string) ($rule['type'] ?? '');
            $priority = (int) ($rule['priority'] ?? 100);

            if ($type === 'category_multiplier') {
                $engine->addEarningRule(new CategoryMultiplierRule(
                    (string) ($rule['category'] ?? ''),
                    (float) ($rule['multiplier'] ?? 1.0),
                    $baseRate,
                    $priority,
                ));
            }

            if ($type === 'minimum_spend') {
                $engine->addEarningRule(new MinimumSpendRule(
                    Money::fromDollars((float) ($rule['minimum'] ?? 0), $currency),
                    (float) ($rule['multiplier'] ?? 1.0),
                    $baseRate,
                    $priority,
                ));
            }

            if ($type === 'fixed_bonus') {
                $engine->addEarningRule(new FixedBonusRule(
                    (string) ($rule['category'] ?? ''),
                    Points::fromInt(max(0, (int) ($rule['points'] ?? 0))),
                    $priority,
                ));
            }

            if ($type === 'tier_bonus') {
                $engine->addEarningRule(new TierBonusRule(
                    (string) ($rule['tier'] ?? ''),
                    (float) ($rule['multiplier'] ?? 1.0),
                    $baseRate,
                    $priority,
                ));
            }
        }

        return $engine;
    }

    private function baseConversionRate(PlanDefinition $plan): ConversionRate
    {
        $baseRate = (array) ($plan->points()['base_rate'] ?? ['cents' => 1, 'points' => 1]);

        return ConversionRate::fromRatio(
            max(1, (int) ($baseRate['cents'] ?? 1)),
            max(1, (int) ($baseRate['points'] ?? 1)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function firstRedemptionRule(PlanDefinition $plan): array
    {
        $rules = $plan->redemptionRules();

        return (array) ($rules[0] ?? []);
    }

    /**
     * @param  array<string, array<string, mixed>>  $tiers
     * @return array<int, array{key: string, lifetime_points: int, benefits: array<string, mixed>}>
     */
    private function normalizedTiers(array $tiers): array
    {
        $normalized = [];

        foreach ($tiers as $key => $tier) {
            $normalized[] = [
                'key' => (string) $key,
                'lifetime_points' => (int) ($tier['lifetime_points'] ?? 0),
                'benefits' => (array) $tier,
            ];
        }

        usort($normalized, fn (array $left, array $right): int => $left['lifetime_points'] <=> $right['lifetime_points']);

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $benefits
     * @return array<int, array<string, mixed>>
     */
    private function formatTierBenefits(array $benefits): array
    {
        $formatted = [];

        foreach ($benefits as $code => $value) {
            if ($code === 'lifetime_points') {
                continue;
            }

            $formatted[] = [
                'code' => (string) $code,
                'value' => $value,
            ];
        }

        return $formatted;
    }

    private function humanizeTierLabel(string $tierKey): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $tierKey));
    }

    /**
     * @param  array<string, mixed>  $mission
     */
    private function missionExpiresAt(array $mission, ?DateTimeImmutable $now = null): ?string
    {
        if (isset($mission['expires_at']) && (string) $mission['expires_at'] !== '') {
            return (string) $mission['expires_at'];
        }

        $days = (int) ($mission['expires_in_days'] ?? 0);
        if ($days <= 0) {
            return null;
        }

        $now ??= new DateTimeImmutable;

        return $this->formatIso($now->add(new DateInterval("P{$days}D")));
    }

    private function missionStatus(int $current, int $target, ?string $expiresAt, ?DateTimeImmutable $now = null): string
    {
        if ($expiresAt !== null) {
            $expires = new DateTimeImmutable($expiresAt);
            $now ??= new DateTimeImmutable;
            if ($now > $expires) {
                return 'expired';
            }
        }

        if ($current >= $target) {
            return 'completed';
        }

        return 'active';
    }

    private function formatIso(DateTimeImmutable $dateTime): string
    {
        return $dateTime
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s.v\Z');
    }

    /**
     * @param  array<string, mixed>  $badge
     * @return array<string, mixed>
     */
    private function badgeRequirement(array $badge): array
    {
        $requirement = (array) ($badge['requirement'] ?? []);
        $target = (int) ($requirement['required_points'] ?? $requirement['target'] ?? 0);
        $source = (string) ($requirement['source'] ?? 'available_points');
        $label = '';

        if ($target > 0) {
            $label = $source === 'lifetime_points' ? "{$target} lifetime points" : "{$target} points";
        }

        return [
            'source' => $source,
            'target' => max(0, $target),
            'label' => $label,
        ];
    }

    private function badgeProgressSource(string $source, CustomerLoyaltyState $state): int
    {
        return match ($source) {
            'lifetime_points' => $state->lifetimePoints,
            'pending_points' => $state->pendingPoints,
            default => $state->availablePoints,
        };
    }

    private function badgeState(int $target, int $progress): string
    {
        if ($target <= 0 || $progress >= $target) {
            return 'unlocked';
        }

        if ($progress > 0) {
            return 'in_progress';
        }

        return 'locked';
    }

    /**
     * @return array<string, mixed>
     */
    private function derivedReferralGrowthConfig(PlanDefinition $plan): array
    {
        $abuseControls = $plan->abuseControls();

        return [
            'share_url_base' => 'https://example.com/ref',
            'code_prefix' => 'FRIEND',
            'reward_rule' => [
                'inviter_points' => $this->fixedBonusPoints($plan, 'referrer_conversion'),
                'invitee_points' => $this->fixedBonusPoints($plan, 'referee_first_purchase'),
                'hold_until_paid' => true,
            ],
            'monthly_cap' => (int) ($abuseControls['max_referrals_per_customer_per_month'] ?? 0),
            'abuse_hints' => [
                'require_unique_referee_email' => (bool) ($abuseControls['require_unique_referee_email'] ?? false),
                'require_first_paid_order' => (bool) ($abuseControls['require_first_paid_order'] ?? false),
            ],
        ];
    }

    private function fixedBonusPoints(PlanDefinition $plan, string $category): int
    {
        foreach ($plan->earningRules() as $rule) {
            if ((string) ($rule['type'] ?? '') !== 'fixed_bonus') {
                continue;
            }

            if ((string) ($rule['category'] ?? '') === $category) {
                return (int) ($rule['points'] ?? 0);
            }
        }

        return 0;
    }

    private function referralRiskLevel(int $totalReferrals): string
    {
        if ($totalReferrals >= 20) {
            return 'low';
        }

        if ($totalReferrals >= 5) {
            return 'medium';
        }

        return 'low';
    }
}
