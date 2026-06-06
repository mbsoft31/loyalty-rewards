<?php

declare(strict_types=1);

namespace LoyaltyRewards\Rules\Earning;

use LoyaltyRewards\Domain\ValueObjects\Money;
use LoyaltyRewards\Domain\ValueObjects\Points;
use LoyaltyRewards\Domain\ValueObjects\TransactionContext;

class FixedBonusRule extends BaseEarningRule
{
    public function __construct(
        private readonly string $category,
        private readonly Points $points,
        int $priority = 100
    ) {
        parent::__construct(
            "fixed_bonus_{$category}",
            "Earn {$points->value()} bonus points for {$category}",
            $priority
        );
    }

    public function calculatePoints(Money $amount, TransactionContext $context): Points
    {
        return $this->points;
    }

    public function isApplicable(TransactionContext $context): bool
    {
        return $context->getCategory() === $this->category;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getPoints(): Points
    {
        return $this->points;
    }
}
