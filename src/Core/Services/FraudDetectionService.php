<?php

declare(strict_types=1);

namespace LoyaltyRewards\Core\Services;

use LoyaltyRewards\Domain\Models\LoyaltyAccount;
use LoyaltyRewards\Domain\ValueObjects\{Money, TransactionContext};
use LoyaltyRewards\Core\Services\FraudDetection\{FraudResult, VelocityDetector, AmountDetector};
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class FraudDetectionService
{
    private array $detectors = [];

    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger()
    ) {
        // Register default fraud detectors
        $this->detectors = [
            new VelocityDetector(),
            new AmountDetector(),
        ];
    }

    public function analyze(
        LoyaltyAccount $account,
        Money $amount,
        TransactionContext $context
    ): FraudResult {
        $results = [];
        $maxScore = 0;
        $reasons = [];

        foreach ($this->detectors as $detector) {
            $result = $detector->analyze($account, $amount, $context);
            $results[] = $result;

            if ($result->getScore() > $maxScore) {
                $maxScore = $result->getScore();
            }

            if ($result->isSuspicious()) {
                $reasons = array_merge($reasons, $result->getReasons());
            }
        }

        $overallResult = new FraudResult($maxScore, $reasons, $results);

        if ($overallResult->isSuspicious()) {
            $this->logger->warning('Fraud detection triggered', [
                'account_id' => $account->getId()->toString(),
                'customer_id' => $account->getCustomerId()->toString(),
                'amount' => $amount->toDollars(),
                'fraud_score' => $overallResult->getScore(),
                'reasons' => $overallResult->getReasons(),
            ]);
        }

        return $overallResult;
    }

    public function addDetector(object $detector): void
    {
        $this->detectors[] = $detector;
    }
}
