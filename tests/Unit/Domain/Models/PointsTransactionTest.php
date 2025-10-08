<?php

use LoyaltyRewards\Tests\Support\Factories;

describe('PointsTransaction', function () {
    it('marks transaction as processed immutably', function () {
        $tx = Factories::pointsTransaction();
        expect($tx->isProcessed())->toBeFalse();
        $processed = $tx->markAsProcessed();
        expect($processed->isProcessed())->toBeTrue();
        expect($processed->getProcessedAt())->not->toBeNull();
        // original remains unchanged
        expect($tx->isProcessed())->toBeFalse();
    });
});

