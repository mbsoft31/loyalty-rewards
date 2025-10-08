<?php

use LoyaltyRewards\Domain\ValueObjects\{ConversionRate, Currency, Money, TransactionContext};
use LoyaltyRewards\Rules\Earning\TierBonusRule;

describe('TierBonusRule', function () {
    beforeEach(function () {
        $this->rule = new TierBonusRule(
            'gold',
            1.25,
            ConversionRate::standard(),
            200
        );
    });

    it('is applicable for matching tier', function () {
        $context = TransactionContext::create(['tier' => 'gold']);
        expect($this->rule->isApplicable($context))->toBeTrue();
    });

    it('is not applicable for different tier', function () {
        $context = TransactionContext::create(['tier' => 'silver']);
        expect($this->rule->isApplicable($context))->toBeFalse();
    });

    it('calculates bonus points using multiplier', function () {
        $amount = Money::fromDollars(80.0, Currency::USD());
        $context = TransactionContext::create(['tier' => 'gold']);
        $points = $this->rule->calculatePoints($amount, $context);
        expect($points)->toBePoints(10000); // 80 * 100 * 1.25
    });
});
