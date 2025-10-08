<?php

use LoyaltyRewards\Infrastructure\Audit\AuditRecord;

describe('AuditRecord', function () {
    it('creates and serializes correctly', function () {
        $record = AuditRecord::create(
            'loyalty_account',
            'acc_1',
            'created',
            'user_1',
            ['k' => 'v'],
            '127.0.0.1',
            'UA'
        );

        $json = $record->jsonSerialize();
        expect($json['entity_type'])->toBe('loyalty_account');
        expect($json['entity_id'])->toBe('acc_1');
        expect($json['action'])->toBe('created');
        expect($json['data'])->toBe(['k' => 'v']);
        expect($json['ip_address'])->toBe('127.0.0.1');
        expect($json['user_agent'])->toBe('UA');
        expect($json['created_at'])->not->toBeNull();
    });
});
