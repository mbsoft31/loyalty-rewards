<?php

use LoyaltyRewards\Core\Services\AuditService;
use LoyaltyRewards\Domain\Repositories\AuditRepositoryInterface;
use LoyaltyRewards\Tests\Support\Factories;
use LoyaltyRewards\Domain\ValueObjects\{Money, Currency, Points, TransactionContext};

describe('AuditService', function () {
    beforeEach(function () {
        $this->repo = mock(AuditRepositoryInterface::class);
        $this->service = new AuditService($this->repo, new \Psr\Log\NullLogger());
        $this->account = Factories::loyaltyAccount();
        $this->transaction = Factories::pointsTransaction($this->account->getId());
    });

    it('logs account creation', function () {
        $this->repo->shouldReceive('store')->once();
        $this->service->logAccountCreated($this->account);
        expect(true)->toBeTrue();
    });

    it('logs points earned', function () {
        $this->repo->shouldReceive('store')->once();
        $this->service->logPointsEarned(
            $this->account,
            $this->transaction,
            Money::fromDollars(10.0, Currency::USD())
        );
        expect(true)->toBeTrue();
    });

    it('logs points redeemed', function () {
        $this->repo->shouldReceive('store')->once();
        $value = Money::fromDollars(5.0, Currency::USD());
        $this->service->logPointsRedeemed($this->account, $this->transaction, $value);
        expect(true)->toBeTrue();
    });

    it('logs points confirmed', function () {
        $this->repo->shouldReceive('store')->once();
        $this->service->logPointsConfirmed($this->account, Points::fromInt(50));
        expect(true)->toBeTrue();
    });

    it('logs fraud attempts', function () {
        $this->repo->shouldReceive('store')->once();
        $amount = Money::fromDollars(5000.0, Currency::USD());
        $ctx = TransactionContext::create(['recent_total_amount' => 12000.0]);
        $fraud = new LoyaltyRewards\Core\Services\FraudDetection\FraudResult(0.9, ['test']);
        $this->service->logFraudAttempt($this->account, $amount, $ctx, $fraud);
        expect(true)->toBeTrue();
    });
});

