<?php

declare(strict_types=1);

namespace LoyaltyRewards\Application\DTOs;

use JsonSerializable;
use LoyaltyRewards\Domain\Models\PointsTransaction;
use LoyaltyRewards\Domain\ValueObjects\{Money, Points};

final readonly class RedemptionResult implements JsonSerializable
{
    public function __construct(
        public PointsTransaction $transaction,
        public Points $newAvailableBalance,
        public ?Money $redemptionValue
    ) {
    }

    /**
     * @return array{transaction: PointsTransaction, new_available_balance: int, redemption_value: array|null}
     */
    public function jsonSerialize(): array
    {
        return [
            'transaction' => $this->transaction,
            'new_available_balance' => $this->newAvailableBalance->value(),
            'redemption_value' => $this->redemptionValue?->jsonSerialize(),
        ];
    }
}
