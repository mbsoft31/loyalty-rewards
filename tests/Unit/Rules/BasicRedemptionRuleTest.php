<?php

use LoyaltyRewards\Domain\ValueObjects\Currency;
use LoyaltyRewards\Domain\ValueObjects\Points;
use LoyaltyRewards\Domain\ValueObjects\TransactionContext;
use LoyaltyRewards\Rules\Redemption\BasicRedemptionRule;

describe('BasicRedemptionRule', function () {
    beforeEach(function () {
        $this->rule = new BasicRedemptionRule(Currency::USD(), 100, 200);
    });

    it('calculates redemption value correctly', function () {
        $value = $this->rule->calculateRedemptionValue(Points::fromInt(500), TransactionContext::redemption());
        expect($value->toDollars())->toBe(5.0);
        expect($value->currency()->code())->toBe('USD');
    });

    it('respects minimum points threshold', function () {
        $ctx = TransactionContext::redemption();
        expect($this->rule->canRedeem(Points::fromInt(100), $ctx))->toBeFalse();
        expect($this->rule->canRedeem(Points::fromInt(200), $ctx))->toBeTrue();
    });

    it('exposes minimum points as Points value object', function () {
        expect($this->rule->getMinimumPoints()->value())->toBe(200);
        expect($this->rule->getName())->toBe('basic_redemption_rule');
    });
});
