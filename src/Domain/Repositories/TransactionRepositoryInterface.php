<?php

declare(strict_types=1);

namespace LoyaltyRewards\Domain\Repositories;

use LoyaltyRewards\Domain\Models\PointsTransaction;
use LoyaltyRewards\Domain\ValueObjects\{TransactionId, AccountId, CustomerId};
use LoyaltyRewards\Domain\Enums\TransactionType;
use DateTimeImmutable;

interface TransactionRepositoryInterface
{
    public function findById(TransactionId $id): ?PointsTransaction;

    /**
     * @return PointsTransaction[]
     */
    public function findByAccountId(AccountId $accountId, int $limit = 100): array;

    /**
     * @return PointsTransaction[]
     */
    public function findByCustomerId(CustomerId $customerId, int $limit = 100): array;

    /**
     * @return PointsTransaction[]
     */
    public function findByType(TransactionType $type, int $limit = 100): array;

    /**
     * @return PointsTransaction[]
     */
    public function findByDateRange(
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        int $limit = 1000
    ): array;

    /**
     * @return PointsTransaction[]
     */
    public function findByAccountAndDateRange(
        AccountId $accountId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        int $limit = 100
    ): array;

    public function save(PointsTransaction $transaction): void;

    public function saveMany(array $transactions): void;

    /**
     * @return PointsTransaction[]
     */
    public function findPendingTransactions(): array;

    public function getTotalTransactions(): int;

    public function getTotalPointsEarned(): int;

    public function getTotalPointsRedeemed(): int;
}
