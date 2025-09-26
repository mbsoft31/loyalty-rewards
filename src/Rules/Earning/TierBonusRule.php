<?php

declare(strict_types=1);

namespace LoyaltyRewards\Rules\Earning;

use LoyaltyRewards\Domain\ValueObjects\{Points, Money, TransactionContext, ConversionRate};

class TierBonusRule extends BaseEarningRule
{
    public function __construct(
        private readonly string $tier,
        private readonly float $bonusMultiplier,
        private readonly ConversionRate $baseRate,
        int $priority = 200
    ) {
        parent::__construct(
            "tier_{$tier}_bonus",
            "Earn {$bonusMultiplier}x bonus points for {$tier} tier",
            $priority
        );
    }

    public function calculatePoints(Money $amount, TransactionContext $context): Points
    {
        $basePoints = $amount->convertToPoints($this->baseRate);
        return $basePoints->multiply($this->bonusMultiplier);
    }

    public function isApplicable(TransactionContext $context): bool
    {
        return $context->get('tier') === $this->tier;
    }

    public function getTier(): string
    {
        return $this->tier;
    }

    public function getBonusMultiplier(): float
    {
        return $this->bonusMultiplier;
    }
}
