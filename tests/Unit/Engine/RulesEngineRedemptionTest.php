<?php

use LoyaltyRewards\Core\Engine\RulesEngine;
use LoyaltyRewards\Rules\Redemption\BasicRedemptionRule;
use LoyaltyRewards\Domain\ValueObjects\{Points, Currency, TransactionContext};
use Psr\Log\NullLogger;

describe('RulesEngine Redemption', function () {
    beforeEach(function () {
        $this->engine = new RulesEngine(new NullLogger());
    });

    it('returns null value and false canRedeem when no rules apply', function () {
        $ctx = TransactionContext::redemption();
        expect($this->engine->canRedeem(Points::fromInt(100), $ctx))->toBeFalse();
        expect($this->engine->calculateRedemption(Points::fromInt(100), $ctx))->toBeNull();
    });

    it('calculates redemption when a rule is present', function () {
        $rule = new BasicRedemptionRule(Currency::USD(), 100, 100);
        $this->engine->addRedemptionRule($rule);
        $ctx = TransactionContext::redemption();
        expect($this->engine->canRedeem(Points::fromInt(500), $ctx))->toBeTrue();
        $value = $this->engine->calculateRedemption(Points::fromInt(500), $ctx);
        expect($value)->not->toBeNull();
        expect($value->toDollars())->toBe(5.0);
    });
});

