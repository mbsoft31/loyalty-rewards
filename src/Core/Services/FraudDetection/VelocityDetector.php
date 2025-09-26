<?php

declare(strict_types=1);

namespace LoyaltyRewards\Core\Services\FraudDetection;

use LoyaltyRewards\Domain\Models\LoyaltyAccount;
use LoyaltyRewards\Domain\ValueObjects\{Money, TransactionContext};

class VelocityDetector
{
    public function __construct(
        private readonly int $maxDailyTransactions = 50,
        private readonly float $maxDailyAmount = 10000.0
    ) {}

    public function analyze(
        LoyaltyAccount $account,
        Money $amount,
        TransactionContext $context
    ): FraudResult {
        // In a real implementation, you'd query transaction history
        // For now, we'll simulate based on context data

        $recentTransactionCount = $context->get('recent_transaction_count', 0);
        $recentTotalAmount = $context->get('recent_total_amount', 0.0);

        $reasons = [];
        $score = 0.0;

        // Check transaction count velocity
        if ($recentTransactionCount > $this->maxDailyTransactions) {
            $reasons[] = 'High transaction frequency';
            $score += 0.4;
        } elseif ($recentTransactionCount > ($this->maxDailyTransactions * 0.7)) {
            $reasons[] = 'Elevated transaction frequency';
            $score += 0.2;
        }

        // Check amount velocity
        if ($recentTotalAmount > $this->maxDailyAmount) {
            $reasons[] = 'High daily transaction volume';
            $score += 0.5;
        } elseif ($recentTotalAmount > ($this->maxDailyAmount * 0.7)) {
            $reasons[] = 'Elevated daily transaction volume';
            $score += 0.3;
        }

        return new FraudResult($score, $reasons);
    }
}
