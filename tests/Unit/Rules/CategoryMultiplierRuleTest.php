<?php

use LoyaltyRewards\Rules\Earning\CategoryMultiplierRule;
use LoyaltyRewards\Domain\ValueObjects\{Money, Currency, ConversionRate, TransactionContext};
use LoyaltyRewards\Tests\Support\Factories;

describe('CategoryMultiplierRule', function () {
    beforeEach(function () {
        $this->rule = new CategoryMultiplierRule(
            'electronics',
            2.0,
            ConversionRate::standard(),
            150
        );
    });

    it('calculates points with multiplier for matching category', function () {
        $money = Money::fromDollars(100.0, Currency::USD());
        $context = TransactionContext::create(['category' => 'electronics']);

        $points = $this->rule->calculatePoints($money, $context);

        expect($points)->toBePoints(20000); // $100 * 100 cents * 2.0 multiplier
    });

    it('is applicable for matching category', function () {
        $context = TransactionContext::create(['category' => 'electronics']);

        expect($this->rule->isApplicable($context))->toBeTrue();
    });

    it('is not applicable for different category', function () {
        $context = TransactionContext::create(['category' => 'books']);

        expect($this->rule->isApplicable($context))->toBeFalse();
    });

    it('is not applicable when no category specified', function () {
        $context = TransactionContext::create([]);

        expect($this->rule->isApplicable($context))->toBeFalse();
    });

    it('provides correct rule metadata', function () {
        expect($this->rule->getName())->toBe('category_electronics_multiplier');
        expect($this->rule->getDescription())->toContain('electronics');
        expect($this->rule->getPriority())->toBe(150);
        expect($this->rule->getCategory())->toBe('electronics');
        expect($this->rule->getMultiplier())->toBe(2.0);
    });
});
