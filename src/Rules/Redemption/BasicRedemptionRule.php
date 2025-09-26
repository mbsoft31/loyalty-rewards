<?php

declare(strict_types=1);

namespace LoyaltyRewards\Rules\Redemption;

use LoyaltyRewards\Rules\Contracts\RedemptionRuleInterface;
use LoyaltyRewards\Domain\ValueObjects\{Points, Money, Currency, TransactionContext};

class BasicRedemptionRule implements RedemptionRuleInterface
{
    public function __construct(
        private readonly Currency $currency,
        private readonly int $pointsPerDollar = 100, // 100 points = $1
        private readonly int $minimumPoints = 100
    ) {}

    public function calculateRedemptionValue(Points $points, TransactionContext $context): Money
    {
        $dollars = $points->value() / $this->pointsPerDollar;
        return Money::fromDollars($dollars, $this->currency);
    }

    public function canRedeem(Points $points, TransactionContext $context): bool
    {
        return $points->isGreaterThanOrEqual(Points::fromInt($this->minimumPoints));
    }

    public function getMinimumPoints(): Points
    {
        return Points::fromInt($this->minimumPoints);
    }

    public function getName(): string
    {
        return 'basic_redemption_rule';
    }
}
