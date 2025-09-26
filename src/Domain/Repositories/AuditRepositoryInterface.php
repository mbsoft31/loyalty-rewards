<?php

declare(strict_types=1);

namespace LoyaltyRewards\Domain\Repositories;

use LoyaltyRewards\Infrastructure\Audit\AuditRecord;
use LoyaltyRewards\Domain\ValueObjects\{AccountId, CustomerId};
use DateTimeImmutable;

interface AuditRepositoryInterface
{
    public function store(AuditRecord $record): void;

    public function storeMany(array $records): void;

    /**
     * @return AuditRecord[]
     */
    public function findByAccount(AccountId $accountId, int $limit = 100): array;

    /**
     * @return AuditRecord[]
     */
    public function findByCustomer(CustomerId $customerId, int $limit = 100): array;

    /**
     * @return AuditRecord[]
     */
    public function findByDateRange(
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        int $limit = 1000
    ): array;

    /**
     * @return AuditRecord[]
     */
    public function findByAction(string $action, int $limit = 100): array;

    public function deleteOlderThan(DateTimeImmutable $date): int;
}
