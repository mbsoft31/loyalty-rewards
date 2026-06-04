<?php

declare(strict_types=1);

namespace LoyaltyRewards\Rules\Contracts;

use LoyaltyRewards\Domain\ValueObjects\Money;
use LoyaltyRewards\Domain\ValueObjects\Points;
use LoyaltyRewards\Domain\ValueObjects\TransactionContext;

interface RedemptionRuleInterface
{
    public function calculateRedemptionValue(Points $points, TransactionContext $context): Money;

    public function canRedeem(Points $points, TransactionContext $context): bool;

    public function getMinimumPoints(): Points;

    public function getName(): string;
}
