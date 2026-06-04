<?php

declare(strict_types=1);

use LoyaltyRewards\Core\Engine\RulesEngine;
use LoyaltyRewards\Core\Services\AuditService;
use LoyaltyRewards\Core\Services\FraudDetectionService;
use LoyaltyRewards\Core\Services\LoyaltyService;
use LoyaltyRewards\Domain\ValueObjects\ConversionRate;
use LoyaltyRewards\Domain\ValueObjects\Currency;
use LoyaltyRewards\Domain\ValueObjects\Money;
use LoyaltyRewards\Domain\ValueObjects\Points;
use LoyaltyRewards\Domain\ValueObjects\TransactionContext;
use LoyaltyRewards\Rules\Earning\CategoryMultiplierRule;
use LoyaltyRewards\Rules\Earning\TierBonusRule;
use LoyaltyRewards\Rules\Redemption\BasicRedemptionRule;
use LoyaltyRewards\Tests\Support\DatabaseTestCase;
use LoyaltyRewards\Tests\Support\Factories;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;

uses(DatabaseTestCase::class);

describe('LoyaltyService Database Integration', function () {
    it('creates an account and runs a complete earn-confirm-redeem flow', function () {
        $customerId = Factories::customerId('customer_001');

        $rulesEngine = new RulesEngine(new NullLogger);
        $rulesEngine->addEarningRule(new CategoryMultiplierRule('electronics', 1.2, ConversionRate::standard()));
        $rulesEngine->addEarningRule(new TierBonusRule('gold', 1.5, ConversionRate::standard()));
        $rulesEngine->addRedemptionRule(new BasicRedemptionRule(Currency::USD(), 100, 100));

        $fraudDetection = new FraudDetectionService(new NullLogger);
        $auditService = new AuditService($this->auditRepository, new NullLogger);
        $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
        $eventDispatcher->shouldReceive('dispatch')->andReturnUsing(
            static fn (object $event): object => $event
        );

        $loyaltyService = new LoyaltyService(
            $this->accountRepository,
            $rulesEngine,
            $fraudDetection,
            $auditService,
            $eventDispatcher,
            new NullLogger
        );

        $account = $loyaltyService->createAccount($customerId);
        expect($account->getCustomerId()->toString())->toBe('customer_001');

        $amount = Money::fromDollars(100.0, Currency::USD());
        $basePoints = $amount->convertToPoints(ConversionRate::standard());
        $expectedEarnedPoints = $basePoints->multiply(1.2)->add($basePoints->multiply(1.5));

        $earnResult = $loyaltyService->earnPoints(
            $customerId,
            $amount,
            TransactionContext::earning('electronics', 'online', ['tier' => 'gold'])
        );
        expect($earnResult->newPendingBalance->value())->toBe($expectedEarnedPoints->value());
        expect($loyaltyService->getAccountBalance($customerId)->value())->toBe(0);

        $loyaltyService->confirmPendingPoints($customerId);
        expect($loyaltyService->getAccountBalance($customerId)->value())->toBe($expectedEarnedPoints->value());

        $redeemPoints = Points::fromInt(10_000);
        $redemption = $loyaltyService->redeemPoints(
            $customerId,
            $redeemPoints,
            TransactionContext::redemption(['channel' => 'checkout'])
        );

        expect($redemption->newAvailableBalance->value())->toBe($expectedEarnedPoints->subtract($redeemPoints)->value());
        expect($redemption->redemptionValue)->not->toBeNull();
        expect($redemption->redemptionValue->currency()->code())->toBe('USD');
        expect($redemption->redemptionValue->toDollars())->toBe(100.0);
        expect($this->transactionRepository->findByAccountId($account->getId()))->toHaveCount(2);
        expect($this->auditRepository->findByAction('points_redeemed', 10))->toHaveCount(1);
    });
});
