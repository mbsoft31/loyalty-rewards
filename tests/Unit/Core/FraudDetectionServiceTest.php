<?php

use LoyaltyRewards\Core\Services\FraudDetection\FraudResult;
use LoyaltyRewards\Core\Services\FraudDetectionService;
use LoyaltyRewards\Domain\ValueObjects\{Currency, Money, TransactionContext};
use LoyaltyRewards\Tests\Support\Factories;
use Psr\Log\NullLogger;

describe('FraudDetectionService', function () {
    beforeEach(function () {
        $this->service = new FraudDetectionService(new NullLogger());
        $this->account = Factories::loyaltyAccount();
    });

    it('returns negligible risk for normal transactions', function () {
        $amount = Money::fromDollars(50.0, Currency::USD());
        $ctx = TransactionContext::create([
            'recent_transaction_count' => 1,
            'recent_total_amount' => 50.0,
            'account_average_amount' => 60.0,
        ]);

        $result = $this->service->analyze($this->account, $amount, $ctx);
        expect($result)->toBeInstanceOf(FraudResult::class);
        expect($result->getScore())->toBeGreaterThanOrEqual(0.0);
        $level = $result->getRiskLevel();
        expect(in_array($level, ['negligible','low'], true))->toBeTrue();
        expect($result->shouldBlock())->toBeFalse();
    });

    it('flags medium risk when velocity exceeds threshold', function () {
        $amount = Money::fromDollars(10.0, Currency::USD());
        $ctx = TransactionContext::create([
            'recent_transaction_count' => 5,
            'recent_total_amount' => 12000.0, // triggers velocity amount 0.5
        ]);

        $result = $this->service->analyze($this->account, $amount, $ctx);
        expect($result->isSuspicious())->toBeTrue();
        expect($result->getRiskLevel())->toBe('medium');
        expect($result->shouldBlock())->toBeFalse();
    });

    it('blocks high risk amounts far above average', function () {
        $amount = Money::fromDollars(6000.0, Currency::USD());
        $ctx = TransactionContext::create([
            'account_average_amount' => 100.0, // +0.4
        ]);

        $result = $this->service->analyze($this->account, $amount, $ctx);
        expect($result->getScore())->toBeGreaterThanOrEqual(0.8);
        expect($result->getRiskLevel())->toBe('high');
        expect($result->shouldBlock())->toBeTrue();
    });
});
