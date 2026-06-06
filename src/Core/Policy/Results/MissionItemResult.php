<?php

declare(strict_types=1);

namespace LoyaltyRewards\Core\Policy\Results;

final readonly class MissionItemResult
{
    public function __construct(
        public string $id,
        public string $key,
        public string $title,
        public string $description,
        public string $status,
        public int $current,
        public int $target,
        public float $progressPercent,
        public int $rewardPoints,
        public ?string $expiresAt,
        public ?string $nextRewardEligibleAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'current' => $this->current,
            'target' => $this->target,
            'progress_percent' => $this->progressPercent,
            'reward_points' => $this->rewardPoints,
            'expires_at' => $this->expiresAt,
            'next_reward_eligible_at' => $this->nextRewardEligibleAt,
        ];
    }
}
