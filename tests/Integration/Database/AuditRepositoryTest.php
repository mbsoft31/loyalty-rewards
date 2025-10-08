<?php

use LoyaltyRewards\Infrastructure\Audit\AuditRecord;
use LoyaltyRewards\Domain\ValueObjects\AccountId;
use LoyaltyRewards\Tests\Support\DatabaseTestCase;

describe('Database Audit Repository Integration', function () {
    it('persists records and finds by action', function () {
        $accountId = AccountId::generate();

        $rec1 = AuditRecord::create('loyalty_account', $accountId->toString(), 'account_created');
        $rec2 = AuditRecord::create('loyalty_account', $accountId->toString(), 'points_earned', null, ['points' => 100]);

        $this->auditRepository->storeMany([$rec1, $rec2]);

        $found = $this->auditRepository->findByAction('points_earned', 10);
        expect($found)->toHaveCount(1);
        expect($found[0]->data['points'])->toBe(100);
    });

    it('finds by account and date range and deletes older', function () {
        $accountId = AccountId::generate();
        $older = AuditRecord::create('loyalty_account', $accountId->toString(), 'points_redeemed');
        $newer = AuditRecord::create('loyalty_account', $accountId->toString(), 'points_earned');
        $this->auditRepository->storeMany([$older, $newer]);
        // backdate the redeemed record by action
        $twoDaysAgo = (new DateTimeImmutable())->modify('-2 days')->format('Y-m-d H:i:s');
        $this->pdo->exec("UPDATE audit_logs SET created_at = '{$twoDaysAgo}' WHERE action = 'points_redeemed'");

        $byAccount = $this->auditRepository->findByAccount($accountId, 10);
        expect($byAccount)->toHaveCount(2);

        $from = (new DateTimeImmutable())->modify('-1 day');
        $to = new DateTimeImmutable();
        $within = $this->auditRepository->findByDateRange($from, $to, 10);
        // only the newer one is within last day
        expect($within)->not->toBeEmpty();

        // delete older than 1 day
        $deleted = $this->auditRepository->deleteOlderThan((new DateTimeImmutable())->modify('-1 day'));
        expect($deleted)->toBeGreaterThanOrEqual(1);
    });

    it('finds by customer id entity type', function () {
        $customerId = 'customer_abc';
        $rec = AuditRecord::create('loyalty_customer', $customerId, 'profile_updated');
        $this->auditRepository->store($rec);
        $rows = $this->auditRepository->findByCustomer(\LoyaltyRewards\Domain\ValueObjects\CustomerId::fromString($customerId));
        expect($rows)->toHaveCount(1);
        expect($rows[0]->action)->toBe('profile_updated');
    });
})->uses(DatabaseTestCase::class);
