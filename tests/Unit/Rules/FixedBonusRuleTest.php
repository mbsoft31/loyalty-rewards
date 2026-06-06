<?php

use LoyaltyRewards\Domain\ValueObjects\Currency;
use LoyaltyRewards\Domain\ValueObjects\Money;
use LoyaltyRewards\Domain\ValueObjects\Points;
use LoyaltyRewards\Domain\ValueObjects\TransactionContext;
use LoyaltyRewards\Rules\Earning\FixedBonusRule;

describe('FixedBonusRule', function () {
    it('returns the configured fixed bonus for matching category', function () {
        $rule = new FixedBonusRule('referrer_conversion', Points::fromInt(7500), 250);

        $points = $rule->calculatePoints(
            Money::fromCents(1000, Currency::USD()),
            TransactionContext::earning('referrer_conversion')
        );

        expect($rule->isApplicable(TransactionContext::earning('referrer_conversion')))->toBeTrue()
            ->and($rule->isApplicable(TransactionContext::earning('other')))->toBeFalse()
            ->and($points->value())->toBe(7500)
            ->and($rule->getPriority())->toBe(250)
            ->and($rule->getName())->toBe('fixed_bonus_referrer_conversion');
    });
});
