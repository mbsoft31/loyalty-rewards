<?php

declare(strict_types=1);

namespace LoyaltyRewards\Infrastructure\Database;

use LoyaltyRewards\Domain\Repositories\AccountRepositoryInterface;
use LoyaltyRewards\Domain\Models\LoyaltyAccount;
use LoyaltyRewards\Domain\ValueObjects\{AccountId, CustomerId, Points};
use LoyaltyRewards\Domain\Enums\AccountStatus;
use LoyaltyRewards\Core\Exceptions\AccountNotFoundException;
use DateTimeImmutable;
use PDO;

readonly class DatabaseAccountRepository implements AccountRepositoryInterface
{
    public function __construct(
        private PDO                           $pdo,
        private DatabaseTransactionRepository $transactionRepository
    ) {}

    public function findById(AccountId $id): LoyaltyAccount
    {
        $sql = "SELECT * FROM loyalty_accounts WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id->toString()]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new AccountNotFoundException("Account not found: {$id}");
        }

        return $this->mapRowToAccount($row);
    }

    public function findByCustomerId(CustomerId $customerId): LoyaltyAccount
    {
        $sql = "SELECT * FROM loyalty_accounts WHERE customer_id = :customer_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['customer_id' => $customerId->toString()]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new AccountNotFoundException("Account not found for customer: {$customerId}");
        }

        return $this->mapRowToAccount($row);
    }

    public function findByCustomerIds(array $customerIds): array
    {
        if (empty($customerIds)) {
            return [];
        }

        $placeholders = str_repeat('?,', count($customerIds) - 1) . '?';
        $sql = "SELECT * FROM loyalty_accounts WHERE customer_id IN ({$placeholders}) ORDER BY created_at";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_map(fn($id) => (string) $id, $customerIds));

        $accounts = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $accounts[] = $this->mapRowToAccount($row);
        }

        return $accounts;
    }

    public function save(LoyaltyAccount $account): void
    {
        // Check if account exists
        $existsSql = "SELECT COUNT(*) FROM loyalty_accounts WHERE id = :id";
        $existsStmt = $this->pdo->prepare($existsSql);
        $existsStmt->execute(['id' => $account->getId()->toString()]);

        $exists = $existsStmt->fetchColumn() > 0;

        if ($exists) {
            $this->updateAccount($account);
        } else {
            $this->insertAccount($account);
        }

        // Save any transactions from the account events
        $this->saveAccountTransactions($account);
    }

    private function insertAccount(LoyaltyAccount $account): void
    {
        $sql = "
            INSERT INTO loyalty_accounts (
                id, customer_id, available_points, pending_points, lifetime_points, 
                status, created_at, last_activity_at
            ) VALUES (
                :id, :customer_id, :available_points, :pending_points, :lifetime_points,
                :status, :created_at, :last_activity_at
            )
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $account->getId()->toString(),
            'customer_id' => $account->getCustomerId()->toString(),
            'available_points' => $account->getAvailablePoints()->value(),
            'pending_points' => $account->getPendingPoints()->value(),
            'lifetime_points' => $account->getLifetimePoints()->value(),
            'status' => $account->getStatus()->value,
            'created_at' => $account->getCreatedAt()->format('Y-m-d H:i:s'),
            'last_activity_at' => $account->getLastActivityAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    private function updateAccount(LoyaltyAccount $account): void
    {
        $sql = "
            UPDATE loyalty_accounts SET 
                available_points = :available_points,
                pending_points = :pending_points,
                lifetime_points = :lifetime_points,
                status = :status,
                last_activity_at = :last_activity_at,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $account->getId()->toString(),
            'available_points' => $account->getAvailablePoints()->value(),
            'pending_points' => $account->getPendingPoints()->value(),
            'lifetime_points' => $account->getLifetimePoints()->value(),
            'status' => $account->getStatus()->value,
            'last_activity_at' => $account->getLastActivityAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    private function saveAccountTransactions(LoyaltyAccount $account): void
    {
        $transactions = [];

        // Extract transactions from domain events
        foreach ($account->getEvents() as $event) {
            if (isset($event->transaction)) {
                $transactions[] = $event->transaction;
            }
        }

        if (!empty($transactions)) {
            $this->transactionRepository->saveMany($transactions);
        }
    }

    public function delete(AccountId $id): void
    {
        $sql = "DELETE FROM loyalty_accounts WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id->toString()]);
    }

    public function findInactive(DateTimeImmutable $since): array
    {
        $sql = "
            SELECT * FROM loyalty_accounts 
            WHERE last_activity_at < :since 
               OR (last_activity_at IS NULL AND created_at < :since)
            ORDER BY last_activity_at ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['since' => $since->format('Y-m-d H:i:s')]);

        $accounts = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $accounts[] = $this->mapRowToAccount($row);
        }

        return $accounts;
    }

    public function findWithPendingPoints(): array
    {
        $sql = "SELECT * FROM loyalty_accounts WHERE pending_points > 0 ORDER BY created_at";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        $accounts = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $accounts[] = $this->mapRowToAccount($row);
        }

        return $accounts;
    }

    public function exists(CustomerId $customerId): bool
    {
        $sql = "SELECT COUNT(*) FROM loyalty_accounts WHERE customer_id = :customer_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['customer_id' => $customerId->toString()]);

        return $stmt->fetchColumn() > 0;
    }

    public function getTotalAccounts(): int
    {
        $sql = "SELECT COUNT(*) FROM loyalty_accounts";
        $stmt = $this->pdo->query($sql);

        return (int) $stmt->fetchColumn();
    }

    public function getTotalActiveAccounts(): int
    {
        $sql = "SELECT COUNT(*) FROM loyalty_accounts WHERE status = 'active'";
        $stmt = $this->pdo->query($sql);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @throws \DateMalformedStringException
     */
    private function mapRowToAccount(array $row): LoyaltyAccount
    {
        return new LoyaltyAccount(
            AccountId::fromString($row['id']),
            CustomerId::fromString($row['customer_id']),
            Points::fromInt($row['available_points']),
            Points::fromInt($row['pending_points']),
            Points::fromInt($row['lifetime_points']),
            AccountStatus::from($row['status']),
            new DateTimeImmutable($row['created_at']),
            $row['last_activity_at'] ? new DateTimeImmutable($row['last_activity_at']) : null
        );
    }
}
