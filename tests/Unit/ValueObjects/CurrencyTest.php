<?php

use LoyaltyRewards\Domain\ValueObjects\Currency;

describe('Currency Value Object', function () {
    it('creates valid currencies', function () {
        $usd = Currency::USD();
        $eur = Currency::EUR();
        $gbp = Currency::GBP();

        expect($usd->code())->toBe('USD');
        expect($eur->code())->toBe('EUR');
        expect($gbp->code())->toBe('GBP');
    });

    it('throws exception for invalid currency codes', function () {
        // Test for too long currency code
        expect(fn() => new Currency('TOOLONG'))
            ->toThrow(InvalidArgumentException::class, 'Currency code must be 3 characters')
            ->and(fn() => new Currency('US')) // Test for too short currency code
            ->toThrow(InvalidArgumentException::class, 'Currency code must be 3 characters')
            ->and(fn() => new Currency('XXX')) // Test for unsupported but valid length currency code
            ->toThrow(InvalidArgumentException::class, 'Unsupported currency code');
    });


    it('formats amounts correctly', function () {
        $usd = Currency::USD();
        $eur = Currency::EUR();
        $gbp = Currency::GBP();

        expect($usd->format(123.45))->toBe('$123.45');
        expect($eur->format(123.45))->toBe('€123.45');
        expect($gbp->format(123.45))->toBe('£123.45');
    });

    it('provides currency metadata', function () {
        $usd = Currency::USD();

        expect($usd->name())->toBe('US Dollar');
        expect($usd->symbol())->toBe('$');
        expect($usd->decimals())->toBe(2);
    });

    it('compares currencies correctly', function () {
        $usd1 = Currency::USD();
        $usd2 = new Currency('USD');
        $eur = Currency::EUR();

        expect($usd1->equals($usd2))->toBeTrue();
        expect($usd1->equals($eur))->toBeFalse();
    });

    it('lists supported currencies', function () {
        $supported = Currency::supportedCurrencies();

        expect($supported)->toContain('USD', 'EUR', 'GBP', 'NGN');
    });
});
