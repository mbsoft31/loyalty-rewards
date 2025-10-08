<?php

declare(strict_types=1);

namespace LoyaltyRewards\Rules\Contracts;

use LoyaltyRewards\Domain\ValueObjects\{Money, Points, TransactionContext};

interface EarningRuleInterface
{
    public function calculatePoints(Money $amount, TransactionContext $context): Points;
    public function isApplicable(TransactionContext $context): bool;
    public function getPriority(): int;
    public function getName(): string;
    public function getDescription(): string;
}
