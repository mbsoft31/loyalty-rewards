<?php

use LoyaltyRewards\Core\Services\LoyaltyService;
use LoyaltyRewards\Domain\Repositories\AccountRepositoryInterface;
use LoyaltyRewards\Core\Engine\RulesEngine;
use LoyaltyRewards\Core\Services\{FraudDetectionService, AuditService};
use LoyaltyRewards\Rules\Earning\CategoryMultiplierRule;
use LoyaltyRewards\Domain\ValueObjects\{Money, Currency, ConversionRate, TransactionContext};
use LoyaltyRewards\Tests\Support\Factories;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;

describe('LoyaltyService Integration', function () {
    beforeEach(function () {
        $this->accountRepository = mock(AccountRepositoryInterface::class);
        $this->rulesEngine = new RulesEngine(new NullLogger());
        $this->fraudDetection = mock(FraudDetectionService::class);
        $this->auditService = mock(AuditService::class);
        $this->eventDispatcher = mock(EventDispatcherInterface::class);

        $this->loyaltyService = new LoyaltyService(
            $this->accountRepository,
            $this->rulesEngine,
            $this->fraudDetection,
            $this->auditService,
            $this->eventDispatcher,
            new NullLogger()
        );
    });

    it('processes earning points successfully', function () {
        // Setup
        $customerId = Factories::customerId();
        $account = Factories::loyaltyAccount($customerId);
        $amount = Money::fromDollars(100.0, Currency::USD());
        $context = TransactionContext::create(['category' => 'electronics']);

        // Add earning rule
        $rule = new CategoryMultiplierRule(
            'electronics',
            2.0,
            ConversionRate::standard()
        );
        $this->rulesEngine->addEarningRule($rule);

        // Create a real FraudResult instead of mocking
        $fraudResult = new \LoyaltyRewards\Core\Services\FraudDetection\FraudResult(0.1, []);
        $this->fraudDetection->shouldReceive('analyze')
            ->andReturn($fraudResult);

        // Mock repository
        $this->accountRepository->shouldReceive('findByCustomerId')
            ->with($customerId)
            ->andReturn($account);
        $this->accountRepository->shouldReceive('save')
            ->with($account)
            ->once();

        // Mock audit service
        $this->auditService->shouldReceive('logPointsEarned')->once();

        // Mock event dispatcher
        $this->eventDispatcher->shouldReceive('dispatch')->once();

        // Execute
        $result = $this->loyaltyService->earnPoints($customerId, $amount, $context);

        // Assert
        expect($result)->toBeSuccessfulEarning()
            ->and($result->pointsEarned)->toBePoints(20000);// $100 * 100 * 2.0

    });

    it('processes redemption successfully', function () {
        // Setup
        $customerId = Factories::customerId();
        $account = Factories::loyaltyAccount(
            $customerId,
            availablePoints: Factories::points(1000)
        );
        $pointsToRedeem = Factories::points(500);

        // Add redemption rule to the engine
        $redemptionRule = new \LoyaltyRewards\Rules\Redemption\BasicRedemptionRule(Currency::USD(), 100, 100);
        $this->rulesEngine->addRedemptionRule($redemptionRule);

        // Mock repository
        $this->accountRepository->shouldReceive('findByCustomerId')
            ->with($customerId)
            ->andReturn($account);
        $this->accountRepository->shouldReceive('save')
            ->with($account)
            ->once();

        // Mock audit service
        $this->auditService->shouldReceive('logPointsRedeemed')->once();

        // Mock event dispatcher
        $this->eventDispatcher->shouldReceive('dispatch')->once();

        // Execute
        $result = $this->loyaltyService->redeemPoints($customerId, $pointsToRedeem);

        // Assert
        expect($result)->toBeSuccessfulRedemption();
        expect($result->newAvailableBalance)->toBePoints(500);
        expect($result->redemptionValue)->not->toBeNull();
    });


    it('creates new account successfully', function () {
        $customerId = Factories::customerId();

        // Mock repository - account doesn't exist, then save new one
        $this->accountRepository->shouldReceive('findByCustomerId')
            ->with($customerId)
            ->andThrow(new \LoyaltyRewards\Core\Exceptions\AccountNotFoundException());
        $this->accountRepository->shouldReceive('save')->once();

        // Mock audit service
        $this->auditService->shouldReceive('logAccountCreated')->once();

        // Mock event dispatcher
        $this->eventDispatcher->shouldReceive('dispatch')->once();

        // Execute
        $account = $this->loyaltyService->createAccount($customerId);

        // Assert
        expect($account->getCustomerId())->toEqual($customerId);
        expect($account->isActive())->toBeTrue();
    });
});
