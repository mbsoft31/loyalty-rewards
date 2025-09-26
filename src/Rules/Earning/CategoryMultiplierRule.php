<?php

declare(strict_types=1);

namespace LoyaltyRewards\Rules\Earning;

use LoyaltyRewards\Domain\ValueObjects\{Points, Money, TransactionContext, ConversionRate};

class CategoryMultiplierRule extends BaseEarningRule
{
    public function __construct(
        private readonly string $category,
        private readonly float $multiplier,
        private readonly ConversionRate $baseRate,
        int $priority = 100
    ) {
        parent::__construct(
            "category_{$category}_multiplier",
            "Earn {$multiplier}x points for {$category} purchases",
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
        return $context->getCategory() === $this->category;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getMultiplier(): float
    {
        return $this->multiplier;
    }
}
