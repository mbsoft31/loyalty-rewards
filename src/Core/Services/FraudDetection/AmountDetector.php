<?php

declare(strict_types=1);

namespace LoyaltyRewards\Core\Services\FraudDetection;

use LoyaltyRewards\Domain\Models\LoyaltyAccount;
use LoyaltyRewards\Domain\ValueObjects\{Money, TransactionContext};

class AmountDetector
{
    public function __construct(
        private readonly float $suspiciousAmount = 1000.0,
        private readonly float $highRiskAmount = 5000.0
    ) {}

    public function analyze(
        LoyaltyAccount $account,
        Money $amount,
        TransactionContext $context
    ): FraudResult {
        $transactionAmount = $amount->toDollars();
        $reasons = [];
        $score = 0.0;

        if ($transactionAmount >= $this->highRiskAmount) {
            $reasons[] = 'Unusually high transaction amount';
            $score = 0.7;
        } elseif ($transactionAmount >= $this->suspiciousAmount) {
            $reasons[] = 'High transaction amount';
            $score = 0.3;
        }

        // Check if amount is unusual for this account
        $averageAmount = $context->get('account_average_amount', 100.0);
        if ($transactionAmount > ($averageAmount * 10)) {
            $reasons[] = 'Amount significantly higher than account average';
            $score += 0.4;
        }

        return new FraudResult($score, $reasons);
    }
}
