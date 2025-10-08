<?php

use LoyaltyRewards\Application\DTOs\{EarningResult, RedemptionResult};
use LoyaltyRewards\Tests\Support\Factories;
use LoyaltyRewards\Domain\ValueObjects\{Points, Money, Currency};

describe('Application DTOs', function () {
    it('serializes EarningResult to json', function () {
        $tx = Factories::pointsTransaction();
        $res = new EarningResult($tx, Points::fromInt(150), Points::fromInt(50), Points::fromInt(100));
        $json = $res->jsonSerialize();
        expect($json['new_available_balance'])->toBe(150);
        expect($json['new_pending_balance'])->toBe(50);
        expect($json['points_earned'])->toBe(100);
    });

    it('serializes RedemptionResult to json', function () {
        $tx = Factories::pointsTransaction();
        $value = Money::fromDollars(10.0, Currency::USD());
        $res = new RedemptionResult($tx, Points::fromInt(400), $value);
        $json = $res->jsonSerialize();
        expect($json['new_available_balance'])->toBe(400);
        expect($json['redemption_value']['amount_dollars'])->toBe(10.0);
        expect($json['redemption_value']['currency'])->toBe('USD');
    });

    it('serializes RedemptionResult with null redemption value', function () {
        $tx = Factories::pointsTransaction();
        $res = new RedemptionResult($tx, Points::fromInt(250), null);
        $json = $res->jsonSerialize();
        expect($json['new_available_balance'])->toBe(250);
        expect($json['redemption_value'])->toBeNull();
        $encoded = json_encode($res);
        expect($encoded)->toContain('"new_available_balance":250');
    });

    it('json-encodes EarningResult including transaction', function () {
        $tx = Factories::pointsTransaction();
        $res = new EarningResult($tx, Points::fromInt(150), Points::fromInt(50), Points::fromInt(100));
        $encoded = json_encode($res);
        expect($encoded)->toContain('"new_available_balance":150');
        expect($encoded)->toContain('"new_pending_balance":50');
        expect($encoded)->toContain('"points_earned":100');
    });
});
