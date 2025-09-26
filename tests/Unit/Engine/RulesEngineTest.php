<?php

use LoyaltyRewards\Core\Engine\RulesEngine;
use LoyaltyRewards\Rules\Earning\CategoryMultiplierRule;
use LoyaltyRewards\Rules\Earning\TierBonusRule;
use LoyaltyRewards\Domain\ValueObjects\{Money, Currency, ConversionRate, TransactionContext};
use LoyaltyRewards\Tests\Support\Factories;

describe('RulesEngine', function () {
    beforeEach(function () {
        $this->rulesEngine = new RulesEngine();
    });

    it('calculates points with single rule', function () {
        $rule = new CategoryMultiplierRule(
            'electronics',
            2.0,
            ConversionRate::standard()
        );
        $this->rulesEngine->addEarningRule($rule);

        $money = Money::fromDollars(50.0, Currency::USD());
        $context = TransactionContext::create(['category' => 'electronics']);

        $points = $this->rulesEngine->calculateEarning($money, $context);

        expect($points)->toBePoints(10000); // $50 * 100 cents * 2.0
    });

    it('calculates points with multiple applicable rules', function () {
        $categoryRule = new CategoryMultiplierRule(
            'electronics',
            2.0,
            ConversionRate::standard(),
            100
        );
        $tierRule = new TierBonusRule(
            'gold',
            1.5,
            ConversionRate::standard(),
            200
        );

        $this->rulesEngine->addEarningRule($categoryRule);
        $this->rulesEngine->addEarningRule($tierRule);

        $money = Money::fromDollars(100.0, Currency::USD());
        $context = TransactionContext::create([
            'category' => 'electronics',
            'tier' => 'gold'
        ]);

        $points = $this->rulesEngine->calculateEarning($money, $context);

        // Both rules apply: $100 * 100 * 2.0 + $100 * 100 * 1.5 = 35,000
        expect($points)->toBePoints(35000);
    });

    it('returns zero points when no rules apply', function () {
        $rule = new CategoryMultiplierRule(
            'electronics',
            2.0,
            ConversionRate::standard()
        );
        $this->rulesEngine->addEarningRule($rule);

        $money = Money::fromDollars(50.0, Currency::USD());
        $context = TransactionContext::create(['category' => 'books']);

        $points = $this->rulesEngine->calculateEarning($money, $context);

        expect($points)->toBeZeroPoints();
    });

    it('removes rules correctly', function () {
        $rule = new CategoryMultiplierRule(
            'electronics',
            2.0,
            ConversionRate::standard()
        );
        $this->rulesEngine->addEarningRule($rule);

        expect($this->rulesEngine->getEarningRules())->toHaveCount(1);

        $this->rulesEngine->removeEarningRule('category_electronics_multiplier');

        expect($this->rulesEngine->getEarningRules())->toHaveCount(0);
    });

    it('gets applicable rules correctly', function () {
        $electronicsRule = new CategoryMultiplierRule(
            'electronics',
            2.0,
            ConversionRate::standard()
        );
        $booksRule = new CategoryMultiplierRule(
            'books',
            1.5,
            ConversionRate::standard()
        );

        $this->rulesEngine->addEarningRule($electronicsRule);
        $this->rulesEngine->addEarningRule($booksRule);

        $context = TransactionContext::create(['category' => 'electronics']);
        $applicableRules = $this->rulesEngine->getApplicableEarningRules($context);

        expect($applicableRules)->toHaveCount(1);
        expect($applicableRules[0]->getName())->toBe('category_electronics_multiplier');
    });
});
