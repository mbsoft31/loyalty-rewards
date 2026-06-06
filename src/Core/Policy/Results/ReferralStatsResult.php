<?php

declare(strict_types=1);

namespace LoyaltyRewards\Core\Policy\Results;

final readonly class ReferralStatsResult
{
    public function __construct(
        public int $totalReferrals,
        public int $convertedReferrals,
        public int $pendingReferrals,
        public ?string $nextRewardAt,
        public int $earnedPoints,
        public int $pendingPoints,
        public string $riskLevel,
    ) {}

    /**
     * @return array{
     *     status: array{total_referrals: int, converted_referrals: int, pending_referrals: int, next_reward_at: null|string},
     *     rewards: array{earned_points: int, pending_points: int, risk_level: string}
     * }
     */
    public function toArray(): array
    {
        return [
            'status' => [
                'total_referrals' => $this->totalReferrals,
                'converted_referrals' => $this->convertedReferrals,
                'pending_referrals' => $this->pendingReferrals,
                'next_reward_at' => $this->nextRewardAt,
            ],
            'rewards' => [
                'earned_points' => $this->earnedPoints,
                'pending_points' => $this->pendingPoints,
                'risk_level' => $this->riskLevel,
            ],
        ];
    }
}
