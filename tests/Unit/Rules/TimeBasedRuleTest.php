<?php

use LoyaltyRewards\Domain\ValueObjects\{ConversionRate, Currency, Money, TransactionContext};
use LoyaltyRewards\Rules\Earning\TimeBasedRule;

describe('TimeBasedRule', function () {
    beforeEach(function () {
        $this->start = new DateTimeImmutable('2025-01-01 00:00:00');
        $this->end = new DateTimeImmutable('2025-12-31 23:59:59');
        $this->rule = new TimeBasedRule(
            $this->start,
            $this->end,
            2.0,
            ConversionRate::standard(),
            [],
            150
        );
    });

    it('calculates points with multiplier when applicable', function () {
        $money = Money::fromDollars(100.0, Currency::USD());
        $context = new TransactionContext([], new DateTimeImmutable('2025-06-01 12:00:00'));

        $points = $this->rule->calculatePoints($money, $context);

        expect($points)->toBePoints(20000); // $100 * 100 cents * 2.0
    });

    it('is applicable within the time window', function () {
        $context = new TransactionContext([], new DateTimeImmutable('2025-03-10 08:30:00'));
        expect($this->rule->isApplicable($context))->toBeTrue();
    });

    it('is not applicable before the start time', function () {
        $context = new TransactionContext([], new DateTimeImmutable('2024-12-31 23:59:00'));
        expect($this->rule->isApplicable($context))->toBeFalse();
    });

    it('is not applicable after the end time', function () {
        $context = new TransactionContext([], new DateTimeImmutable('2026-01-01 00:00:00'));
        expect($this->rule->isApplicable($context))->toBeFalse();
    });

    it('is applicable on inclusive boundaries', function () {
        $startContext = new TransactionContext([], $this->start);
        $endContext = new TransactionContext([], $this->end);

        expect($this->rule->isApplicable($startContext))->toBeTrue();
        expect($this->rule->isApplicable($endContext))->toBeTrue();
    });

    it('respects day-of-week filters', function () {
        // Only allow Sunday
        $rule = new TimeBasedRule(
            $this->start,
            $this->end,
            1.5,
            ConversionRate::standard(),
            ['sunday']
        );

        $sunday = new TransactionContext([], new DateTimeImmutable('2025-03-09 10:00:00')); // Sunday
        $monday = new TransactionContext([], new DateTimeImmutable('2025-03-10 10:00:00')); // Monday

        expect($rule->isApplicable($sunday))->toBeTrue();
        expect($rule->isApplicable($monday))->toBeFalse();
    });

    it('exposes metadata', function () {
        expect($this->rule->getPriority())->toBe(150);
        expect($this->rule->getMultiplier())->toBe(2.0);
        expect($this->rule->getStartTime())->toEqual($this->start);
        expect($this->rule->getEndTime())->toEqual($this->end);
    });
});
