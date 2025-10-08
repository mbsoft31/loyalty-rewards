<?php

use LoyaltyRewards\Domain\Enums\TransactionType;

describe('TransactionType', function () {
    it('identifies earning and spending types', function () {
        expect(TransactionType::EARN->isEarning())->toBeTrue();
        expect(TransactionType::REFUND->isEarning())->toBeTrue();
        expect(TransactionType::REDEEM->isSpending())->toBeTrue();
        expect(TransactionType::EXPIRE->isSpending())->toBeTrue();
        expect(TransactionType::ADJUSTMENT->isSpending())->toBeTrue();
    });

    it('provides descriptions', function () {
        expect(TransactionType::EARN->description())->toBe('Points Earned');
        expect(TransactionType::REDEEM->description())->toBe('Points Redeemed');
        expect(TransactionType::EXPIRE->description())->toBe('Points Expired');
    });
});

