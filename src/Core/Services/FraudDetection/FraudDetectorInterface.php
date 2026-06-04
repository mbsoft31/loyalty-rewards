<?php

declare(strict_types=1);

namespace LoyaltyRewards\Core\Services\FraudDetection;

use LoyaltyRewards\Domain\Models\LoyaltyAccount;
use LoyaltyRewards\Domain\ValueObjects\Money;
use LoyaltyRewards\Domain\ValueObjects\TransactionContext;

interface FraudDetectorInterface
{
    public function analyze(
        LoyaltyAccount $account,
        Money $amount,
        TransactionContext $context
    ): FraudResult;
}
