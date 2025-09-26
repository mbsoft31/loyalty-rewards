<?php

declare(strict_types=1);

namespace LoyaltyRewards\Rules\Contracts;

use LoyaltyRewards\Domain\ValueObjects\{Points, Money, TransactionContext};

interface RedemptionRuleInterface
{
    public function calculateRedemptionValue(Points $points, TransactionContext $context): Money;
    public function canRedeem(Points $points, TransactionContext $context): bool;
    public function getMinimumPoints(): Points;
    public function getName(): string;
}
