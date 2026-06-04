<?php

declare(strict_types=1);

namespace LoyaltyRewards\Domain\Repositories;

use DateTimeImmutable;
use LoyaltyRewards\Domain\ValueObjects\AccountId;
use LoyaltyRewards\Domain\ValueObjects\CustomerId;
use LoyaltyRewards\Infrastructure\Audit\AuditRecord;

interface AuditRepositoryInterface
{
    public function store(AuditRecord $record): void;

    /**
     * @param  list<AuditRecord>  $records
     */
    public function storeMany(array $records): void;

    /**
     * @return list<AuditRecord>
     */
    public function findByAccount(AccountId $accountId, int $limit = 100): array;

    /**
     * @return list<AuditRecord>
     */
    public function findByCustomer(CustomerId $customerId, int $limit = 100): array;

    /**
     * @return list<AuditRecord>
     */
    public function findByDateRange(
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        int $limit = 1000
    ): array;

    /**
     * @return list<AuditRecord>
     */
    public function findByAction(string $action, int $limit = 100): array;

    public function deleteOlderThan(DateTimeImmutable $date): int;
}
