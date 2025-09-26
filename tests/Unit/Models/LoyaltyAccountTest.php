<?php

use LoyaltyRewards\Domain\Models\LoyaltyAccount;
use LoyaltyRewards\Domain\ValueObjects\{Points, TransactionContext};
use LoyaltyRewards\Domain\Events\{PointsEarnedEvent, PointsRedeemedEvent, AccountCreatedEvent};
use LoyaltyRewards\Core\Exceptions\{InsufficientPointsException, InactiveAccountException};
use LoyaltyRewards\Tests\Support\Factories;

describe('LoyaltyAccount Model', function () {
    it('creates a new account', function () {
        $customerId = Factories::customerId();

        $account = LoyaltyAccount::create($customerId);

        expect($account->getCustomerId())->toEqual($customerId);
        expect($account->getAvailablePoints())->toBeZeroPoints();
        expect($account->getPendingPoints())->toBeZeroPoints();
        expect($account->isActive())->toBeTrue();
        expect($account->getEvents())->toHaveCount(1);
        expect($account->getEvents()[0])->toBeInstanceOf(AccountCreatedEvent::class);
    });

    it('earns points successfully', function () {
        $account = Factories::loyaltyAccount();
        $points = Factories::points(150);
        $context = Factories::transactionContext(['source' => 'purchase']);

        $transaction = $account->earnPoints($points, $context);

        expect($account->getPendingPoints())->toBePoints(150);
        expect($transaction->getPoints())->toBePoints(150);
        expect($account->getEvents())->toHaveCount(1);
        expect($account->getEvents()[0])->toBeInstanceOf(PointsEarnedEvent::class);
    });

    it('confirms pending points', function () {
        $account = Factories::loyaltyAccount();

        // Earn some points first
        $account->earnPoints(Factories::points(100), Factories::transactionContext());
        $account->clearEvents(); // Clear earning event

        // Confirm pending points
        $account->confirmPendingPoints();

        expect($account->getAvailablePoints())->toBePoints(100);
        expect($account->getPendingPoints())->toBeZeroPoints();
        expect($account->getLifetimePoints())->toBePoints(100);
    });

    it('redeems points successfully', function () {
        $account = Factories::loyaltyAccount(
            availablePoints: Factories::points(200)
        );

        $transaction = $account->redeemPoints(Factories::points(75));

        expect($account->getAvailablePoints())->toBePoints(125);
        expect($transaction->getPoints())->toBePoints(75);
        expect($account->getEvents())->toHaveCount(1);
        expect($account->getEvents()[0])->toBeInstanceOf(PointsRedeemedEvent::class);
    });

    it('throws exception when redeeming more points than available', function () {
        $account = Factories::loyaltyAccount(
            availablePoints: Factories::points(50)
        );

        expect(fn() => $account->redeemPoints(Factories::points(100)))
            ->toThrow(InsufficientPointsException::class, 'Insufficient points');
    });

    it('adjusts points with positive adjustment', function () {
        $account = Factories::loyaltyAccount(
            availablePoints: Factories::points(100)
        );

        $transaction = $account->adjustPoints(Factories::points(25), 'Bonus points');

        expect($account->getAvailablePoints())->toBePoints(125);
        expect($account->getLifetimePoints())->toBePoints(25);
    });

    it('adjusts points with negative adjustment', function () {
        $account = Factories::loyaltyAccount(
            availablePoints: Factories::points(100)
        );

        // Negative adjustment (subtract 30 points)
        $negativePoints = Points::fromInt(-30);
        $transaction = $account->adjustPoints($negativePoints, 'Point correction');

        expect($account->getAvailablePoints())->toBePoints(70);
    });

    it('expires points correctly', function () {
        $account = Factories::loyaltyAccount(
            availablePoints: Factories::points(200)
        );

        $transaction = $account->expirePoints(Factories::points(50));

        expect($account->getAvailablePoints())->toBePoints(150);
    });

    it('suspends and activates account', function () {
        $account = Factories::loyaltyAccount();

        expect($account->isActive())->toBeTrue();

        $account->suspend();
        expect($account->isActive())->toBeFalse();

        $account->activate();
        expect($account->isActive())->toBeTrue();
    });

    it('closes account and clears points', function () {
        $account = Factories::loyaltyAccount(
            availablePoints: Factories::points(500),
            pendingPoints: Factories::points(100)
        );

        $account->close();

        expect($account->isActive())->toBeFalse();
        expect($account->getAvailablePoints())->toBeZeroPoints();
        expect($account->getPendingPoints())->toBeZeroPoints();
    });

    it('throws exception when operating on inactive account', function () {
        $account = Factories::loyaltyAccount();
        $account->suspend();

        expect(fn() => $account->earnPoints(Factories::points(100), Factories::transactionContext()))
            ->toThrow(InactiveAccountException::class);
    });

    it('checks redemption eligibility correctly', function () {
        $accountWithPoints = Factories::loyaltyAccount(
            availablePoints: Factories::points(100)
        );
        $accountWithoutPoints = Factories::loyaltyAccount();
        $suspendedAccount = Factories::loyaltyAccount(
            availablePoints: Factories::points(100)
        );
        $suspendedAccount->suspend();

        expect($accountWithPoints->canRedeemPoints())->toBeTrue();
        expect($accountWithoutPoints->canRedeemPoints())->toBeFalse();
        expect($suspendedAccount->canRedeemPoints())->toBeFalse();
    });
});
