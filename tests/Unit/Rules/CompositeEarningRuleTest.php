<?php

use LoyaltyRewards\Domain\ValueObjects\{ConversionRate, Currency, Money, TransactionContext};
use LoyaltyRewards\Rules\Composites\CompositeEarningRule;
use LoyaltyRewards\Rules\Earning\{CategoryMultiplierRule, MinimumSpendRule};

describe('CompositeEarningRule', function () {
    beforeEach(function () {
        $this->composite = new CompositeEarningRule();
        $this->cat = new CategoryMultiplierRule('electronics', 2.0, ConversionRate::standard(), 200);
        $this->min = new MinimumSpendRule(Money::fromDollars(50, Currency::USD()), 1.2, ConversionRate::standard(), 150);
        $this->composite->addRule($this->cat);
        $this->composite->addRule($this->min);
    });

    it('is applicable if any sub-rule applies', function () {
        $ctx = TransactionContext::create(['category' => 'electronics']);
        expect($this->composite->isApplicable($ctx))->toBeTrue();
    });

    it('returns applicable rules subset', function () {
        $ctx = TransactionContext::create(['amount' => Money::fromDollars(80, Currency::USD())]);
        $rules = $this->composite->getApplicableRules($ctx);
        expect($rules)->toHaveCount(1);
        $first = array_values($rules)[0];
        expect($first->getName())->toContain('minimum_spend');
    });

    it('calculates total points from all applicable rules', function () {
        $amount = Money::fromDollars(100, Currency::USD());
        $ctx = TransactionContext::create(['category' => 'electronics', 'amount' => $amount]);
        $points = $this->composite->calculatePoints($amount, $ctx);
        // Category: 100*100*2.0 = 20000; MinimumSpend: 100*100*1.2 = 12000; Total = 32000
        expect($points->value())->toBe(32000);
    });

    it('removes rules by name', function () {
        $this->composite->removeRule($this->cat->getName());
        $ctx = TransactionContext::create(['category' => 'electronics']);
        expect($this->composite->isApplicable($ctx))->toBeFalse();
    });
});
