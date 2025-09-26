<?php

declare(strict_types=1);

namespace LoyaltyRewards\Application\DTOs;

use LoyaltyRewards\Domain\Models\PointsTransaction;
use LoyaltyRewards\Domain\ValueObjects\{Points, Money};
use JsonSerializable;

final readonly class RedemptionResult implements JsonSerializable
{
    public function __construct(
        public PointsTransaction $transaction,
        public Points $newAvailableBalance,
        public ?Money $redemptionValue
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'transaction' => $this->transaction,
            'new_available_balance' => $this->newAvailableBalance->value(),
            'redemption_value' => $this->redemptionValue?->jsonSerialize(),
        ];
    }
}
