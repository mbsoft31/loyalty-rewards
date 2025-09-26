<?php

declare(strict_types=1);

namespace LoyaltyRewards\Domain\Repositories;

use LoyaltyRewards\Domain\Models\LoyaltyAccount;
use LoyaltyRewards\Domain\ValueObjects\{AccountId, CustomerId};
use LoyaltyRewards\Core\Exceptions\AccountNotFoundException;

interface AccountRepositoryInterface
{
    /**
     * @throws AccountNotFoundException
     */
    public function findById(AccountId $id): LoyaltyAccount;

    /**
     * @throws AccountNotFoundException
     */
    public function findByCustomerId(CustomerId $customerId): LoyaltyAccount;

    /**
     * @return LoyaltyAccount[]
     */
    public function findByCustomerIds(array $customerIds): array;

    public function save(LoyaltyAccount $account): void;

    public function delete(AccountId $id): void;

    /**
     * @return LoyaltyAccount[]
     */
    public function findInactive(\DateTimeImmutable $since): array;

    /**
     * @return LoyaltyAccount[]
     */
    public function findWithPendingPoints(): array;

    public function exists(CustomerId $customerId): bool;

    public function getTotalAccounts(): int;

    public function getTotalActiveAccounts(): int;
}
