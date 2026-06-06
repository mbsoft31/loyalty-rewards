<?php

declare(strict_types=1);

namespace LoyaltyRewards\Core\Policy\Results;

final readonly class RewardEligibilityResult
{
    public function __construct(
        public bool $isAvailable,
        public bool $isRedeemable,
        public string $requiredTier,
        public int $minimumPoints,
        public mixed $maxPerCustomer,
        public string $inventoryState,
        public mixed $inventoryRemaining,
    ) {}

    /**
     * @param  array<string, mixed>  $reward
     * @return array<string, mixed>
     */
    public function toPayload(array $reward, string $currency): array
    {
        return [
            'id' => (string) ($reward['id'] ?? ''),
            'key' => (string) ($reward['key'] ?? ''),
            'title' => (string) ($reward['title'] ?? ''),
            'description' => (string) ($reward['description'] ?? ''),
            'cost_points' => (int) ($reward['cost_points'] ?? 0),
            'currency' => $currency,
            'category' => (string) ($reward['category'] ?? 'general'),
            'estimated_value_cents' => (int) ($reward['estimated_value_cents'] ?? 0),
            'is_available' => $this->isAvailable,
            'eligibility' => [
                'required_tier' => $this->requiredTier,
                'min_points' => $this->minimumPoints,
                'max_per_customer' => $this->maxPerCustomer,
            ],
            'inventory' => [
                'state' => $this->inventoryState,
                'remaining' => $this->inventoryRemaining,
            ],
            'tags' => (array) ($reward['tags'] ?? []),
        ];
    }
}
