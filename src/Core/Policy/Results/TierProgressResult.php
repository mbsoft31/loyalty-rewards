<?php

declare(strict_types=1);

namespace LoyaltyRewards\Core\Policy\Results;

final readonly class TierProgressResult
{
    /**
     * @param  array<int, array<string, mixed>>  $benefits
     */
    public function __construct(
        public string $currentTier,
        public string $currentTierLabel,
        public ?string $nextTier,
        public ?string $nextTierLabel,
        public int $currentValue,
        public int $targetValue,
        public int $pointsToNext,
        public float $progressPercent,
        public array $benefits,
        public ?string $period,
        public int $forecastPointsToNext30d,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'current_tier' => $this->currentTier,
            'current_tier_label' => $this->currentTierLabel,
            'next_tier' => $this->nextTier,
            'next_tier_label' => $this->nextTierLabel,
            'current_value' => $this->currentValue,
            'target_value' => $this->targetValue,
            'points_to_next' => $this->pointsToNext,
            'progress_percent' => $this->progressPercent,
            'benefits' => $this->benefits,
            'period' => $this->period,
            'forecast_points_to_next_30d' => $this->forecastPointsToNext30d,
        ];
    }
}
