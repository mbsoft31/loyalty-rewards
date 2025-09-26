<?php

use LoyaltyRewards\Domain\ValueObjects\Points;

describe('Points Value Object', function () {
    it('creates points with valid value', function () {
        $points = Points::fromInt(100);

        expect($points->value())->toBe(100);
    });

    it('creates zero points', function () {
        $points = Points::zero();

        expect($points)->toBeZeroPoints();
    });

    it('throws exception for negative points', function () {
        expect(fn() => Points::fromInt(-10))
            ->toThrow(InvalidArgumentException::class, 'Points cannot be negative');
    });

    it('adds points correctly', function () {
        $points1 = Points::fromInt(100);
        $points2 = Points::fromInt(50);

        $result = $points1->add($points2);

        expect($result)->toBePoints(150);
    });

    it('subtracts points correctly', function () {
        $points1 = Points::fromInt(100);
        $points2 = Points::fromInt(30);

        $result = $points1->subtract($points2);

        expect($result)->toBePoints(70);
    });

    it('throws exception when subtracting more than available', function () {
        $points1 = Points::fromInt(50);
        $points2 = Points::fromInt(100);

        expect(fn() => $points1->subtract($points2))
            ->toThrow(InvalidArgumentException::class, 'Cannot subtract more points than available');
    });

    it('multiplies points correctly', function () {
        $points = Points::fromInt(100);

        $result = $points->multiply(2.5);

        expect($result)->toBePoints(250);
    });

    it('throws exception for negative multiplier', function () {
        $points = Points::fromInt(100);

        expect(fn() => $points->multiply(-1.5))
            ->toThrow(InvalidArgumentException::class, 'Multiplier cannot be negative');
    });

    it('calculates percentage correctly', function () {
        $points = Points::fromInt(200);

        $result = $points->percentage(25.0);

        expect($result)->toBePoints(50);
    });

    it('compares points correctly', function () {
        $points1 = Points::fromInt(100);
        $points2 = Points::fromInt(150);
        $points3 = Points::fromInt(100);

        expect($points1->isLessThan($points2))->toBeTrue();
        expect($points2->isGreaterThan($points1))->toBeTrue();
        expect($points1->equals($points3))->toBeTrue();
        expect($points1->isGreaterThanOrEqual($points3))->toBeTrue();
    });

    it('serializes to JSON correctly', function () {
        $points = Points::fromInt(100);

        expect(json_encode($points))->toBe('100');
    });

    it('converts to string with formatting', function () {
        $points = Points::fromInt(1500);

        expect((string) $points)->toBe('1,500 points');
    });
});
