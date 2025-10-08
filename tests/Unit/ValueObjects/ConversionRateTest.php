<?php

use LoyaltyRewards\Domain\ValueObjects\ConversionRate;

describe('ConversionRate', function () {
    it('creates standard and from multiplier', function () {
        $std = ConversionRate::standard();
        $custom = ConversionRate::fromMultiplier(2.5);
        expect($std->multiplier())->toBe(1.0);
        expect($custom->multiplier())->toBe(2.5);
    });

    it('creates from ratio and compares equal within tolerance', function () {
        $r = ConversionRate::fromRatio(4, 10); // 2.5
        expect($r->multiplier())->toBe(2.5);
        expect($r->equals(ConversionRate::fromMultiplier(2.5001)))->toBeTrue();
    });

    it('inverts and serializes', function () {
        $r = ConversionRate::fromMultiplier(4.0);
        expect($r->inverse()->multiplier())->toBe(0.25);
        expect($r->jsonSerialize())->toBe(4.0);
        expect((string)$r)->toBe('x4');
    });
});

