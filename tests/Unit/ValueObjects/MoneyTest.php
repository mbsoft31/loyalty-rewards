<?php

use LoyaltyRewards\Domain\ValueObjects\{Money, Currency, ConversionRate};

describe('Money Value Object', function () {
    it('creates money from dollars', function () {
        $money = Money::fromDollars(99.99, Currency::USD());

        expect($money->toDollars())->toBe(99.99);
        expect($money->amount())->toBe(9999);
        expect($money->currency()->code())->toBe('USD');
    });

    it('creates money from cents', function () {
        $money = Money::fromCents(1599, Currency::USD());

        expect($money->toDollars())->toBe(15.99);
        expect($money->amount())->toBe(1599);
    });

    it('creates zero money', function () {
        $money = Money::zero(Currency::USD());

        expect($money->toDollars())->toBe(0.0);
        expect($money->isZero())->toBeTrue();
    });

    it('throws exception for negative amounts', function () {
        expect(fn() => Money::fromDollars(-10.0, Currency::USD()))
            ->toThrow(InvalidArgumentException::class, 'Money amount cannot be negative');
    });

    it('adds money of same currency', function () {
        $money1 = Money::fromDollars(10.50, Currency::USD());
        $money2 = Money::fromDollars(5.25, Currency::USD());

        $result = $money1->add($money2);

        expect($result)->toBeMoney(15.75, 'USD');
    });

    it('throws exception when adding different currencies', function () {
        $money1 = Money::fromDollars(10.0, Currency::USD());
        $money2 = Money::fromDollars(10.0, Currency::EUR());

        expect(fn() => $money1->add($money2))
            ->toThrow(InvalidArgumentException::class, 'Cannot operate on different currencies');
    });

    it('subtracts money correctly', function () {
        $money1 = Money::fromDollars(20.0, Currency::USD());
        $money2 = Money::fromDollars(7.50, Currency::USD());

        $result = $money1->subtract($money2);

        expect($result)->toBeMoney(12.50, 'USD');
    });

    it('multiplies money correctly', function () {
        $money = Money::fromDollars(10.0, Currency::USD());

        $result = $money->multiply(2.5);

        expect($result)->toBeMoney(25.0, 'USD');
    });

    it('converts to points using conversion rate', function () {
        $money = Money::fromDollars(10.0, Currency::USD());
        $rate = ConversionRate::fromMultiplier(2.0);

        $points = $money->convertToPoints($rate);

        expect($points)->toBePoints(2000);
    });

    it('formats currency display correctly', function () {
        $money = Money::fromDollars(123.45, Currency::USD());

        expect((string) $money)->toBe('$123.45');
    });

    it('serializes to JSON correctly', function () {
        $money = Money::fromDollars(50.75, Currency::USD());

        $json = json_decode(json_encode($money), true);

        expect($json)->toHaveKey('amount_cents', 5075);
        expect($json)->toHaveKey('amount_dollars', 50.75);
        expect($json)->toHaveKey('currency', 'USD');
        expect($json)->toHaveKey('formatted', '$50.75');
    });
});
