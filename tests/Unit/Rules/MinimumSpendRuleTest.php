<?php

use LoyaltyRewards\Domain\ValueObjects\ConversionRate;
use LoyaltyRewards\Domain\ValueObjects\Currency;
use LoyaltyRewards\Domain\ValueObjects\Money;
use LoyaltyRewards\Domain\ValueObjects\TransactionContext;
use LoyaltyRewards\Rules\Earning\MinimumSpendRule;

describe('MinimumSpendRule', function () {
    beforeEach(function () {
        $this->minimum = Money::fromDollars(50.0, Currency::USD());
        $this->rule = new MinimumSpendRule(
            $this->minimum,
            1.5,
            ConversionRate::standard(),
            125
        );
    });

    it('is applicable when amount meets minimum', function () {
        $context = TransactionContext::create(['amount' => Money::fromDollars(60.0, Currency::USD())]);
        expect($this->rule->isApplicable($context))->toBeTrue();
    });

    it('is not applicable when amount is below minimum', function () {
        $context = TransactionContext::create(['amount' => Money::fromDollars(40.0, Currency::USD())]);
        expect($this->rule->isApplicable($context))->toBeFalse();
    });

    it('calculates points using multiplier', function () {
        $amount = Money::fromDollars(100.0, Currency::USD());
        $context = TransactionContext::create(['amount' => $amount]);
        $points = $this->rule->calculatePoints($amount, $context);
        expect($points)->toBePoints(15000); // 100 * 100 * 1.5
    });
});
