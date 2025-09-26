<?php

declare(strict_types=1);

namespace LoyaltyRewards\Rules\Earning;

use LoyaltyRewards\Domain\ValueObjects\{Points, Money, TransactionContext, ConversionRate};

class MinimumSpendRule extends BaseEarningRule
{
    public function __construct(
        private readonly Money $minimumAmount,
        private readonly float $multiplier,
        private readonly ConversionRate $baseRate,
        int $priority = 125
    ) {
        parent::__construct(
            "minimum_spend_{$minimumAmount->amount()}",
            "Earn {$multiplier}x points on orders over {$minimumAmount}",
            $priority
        );
    }

    public function calculatePoints(Money $amount, TransactionContext $context): Points
    {
        $basePoints = $amount->convertToPoints($this->baseRate);
        return $basePoints->multiply($this->multiplier);
    }

    public function isApplicable(TransactionContext $context): bool
    {
        $amount = $context->get('amount');

        if (!$amount instanceof Money) {
            return false;
        }

        return $amount->isGreaterThanOrEqual($this->minimumAmount);
    }

    public function getMinimumAmount(): Money
    {
        return $this->minimumAmount;
    }

    public function getMultiplier(): float
    {
        return $this->multiplier;
    }
}
