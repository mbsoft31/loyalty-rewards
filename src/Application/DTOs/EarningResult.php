<?php

declare(strict_types=1);

namespace LoyaltyRewards\Application\DTOs;

use LoyaltyRewards\Domain\Models\PointsTransaction;
use LoyaltyRewards\Domain\ValueObjects\Points;
use JsonSerializable;

final readonly class EarningResult implements JsonSerializable
{
    public function __construct(
        public PointsTransaction $transaction,
        public Points $newAvailableBalance,
        public Points $newPendingBalance,
        public Points $pointsEarned
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'transaction' => $this->transaction,
            'new_available_balance' => $this->newAvailableBalance->value(),
            'new_pending_balance' => $this->newPendingBalance->value(),
            'points_earned' => $this->pointsEarned->value(),
        ];
    }
}
