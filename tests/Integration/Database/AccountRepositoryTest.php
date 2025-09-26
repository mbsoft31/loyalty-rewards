<?php

use LoyaltyRewards\Domain\Models\LoyaltyAccount;
use LoyaltyRewards\Domain\ValueObjects\{CustomerId, Points};
use LoyaltyRewards\Domain\Enums\AccountStatus;
use LoyaltyRewards\Core\Exceptions\AccountNotFoundException;
use LoyaltyRewards\Tests\Support\{DatabaseTestCase, Factories};

describe('Database Account Repository Integration', function () {
    beforeEach(function () {
        $this->truncateAllTables();
    });

    it('persists and retrieves account correctly', function () {
        $account = Factories::loyaltyAccount(
            customerId: CustomerId::fromString('test_customer_001'),
            availablePoints: Points::fromInt(500),
            pendingPoints: Points::fromInt(100)
        );

        // Save account
        $this->accountRepository->save($account);

        // Retrieve account
        $retrievedAccount = $this->accountRepository->findByCustomerId($account->getCustomerId());

        expect($retrievedAccount->getCustomerId()->toString())->toBe('test_customer_001');
        expect($retrievedAccount->getAvailablePoints())->toBePoints(500);
        expect($retrievedAccount->getPendingPoints())->toBePoints(100);
        expect($retrievedAccount->isActive())->toBeTrue();
    });

    it('updates existing account correctly', function () {
        $account = Factories::loyaltyAccount(
            availablePoints: Points::fromInt(1000)
        );

        // Save initial account
        $this->accountRepository->save($account);
        $initialId = $account->getId();

        // Modify account
        $account->earnPoints(Points::fromInt(500), Factories::transactionContext());
        $account->confirmPendingPoints();

        // Save updated account
        $this->accountRepository->save($account);

        // Retrieve updated account
        $updatedAccount = $this->accountRepository->findById($initialId);

        expect($updatedAccount->getAvailablePoints())->toBePoints(1500);
        expect($updatedAccount->getLifetimePoints())->toBePoints(500);
    });

    it('handles batch operations efficiently', function () {
        $accounts = [];

        // Create 100 accounts
        for ($i = 0; $i < 100; $i++) {
            $accounts[] = Factories::loyaltyAccount(
                customerId: CustomerId::fromString("batch_customer_{$i}"),
                availablePoints: Points::fromInt(rand(100, 1000))
            );
        }

        $startTime = microtime(true);

        // Save all accounts
        foreach ($accounts as $account) {
            $this->accountRepository->save($account);
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        // Performance assertion: Should save 100 accounts in less than 1 second
        expect($totalTime)->toBeLessThan(1.0);

        // Verify all accounts were saved
        expect($this->getTableRowCount('loyalty_accounts'))->toBe(100);
    });

    it('finds inactive accounts correctly', function () {
        // Create accounts with different activity dates
        $oldAccount = Factories::loyaltyAccount(CustomerId::fromString('old_customer'));
        $recentAccount = Factories::loyaltyAccount(CustomerId::fromString('recent_customer'));

        $this->accountRepository->save($oldAccount);
        $this->accountRepository->save($recentAccount);

        // Simulate old account with no recent activity
        $this->pdo->exec("
            UPDATE loyalty_accounts 
            SET last_activity_at = date('now', '-100 days') 
            WHERE customer_id = 'old_customer'
        ");

        // Find inactive accounts (older than 30 days)
        $cutoffDate = new DateTimeImmutable('-30 days');
        $inactiveAccounts = $this->accountRepository->findInactive($cutoffDate);

        expect($inactiveAccounts)->toHaveCount(1);
        expect($inactiveAccounts[0]->getCustomerId()->toString())->toBe('old_customer');
    });

    it('handles concurrent access safely', function () {
        $account = Factories::loyaltyAccount();
        $this->accountRepository->save($account);

        // Simulate concurrent reads
        $results = [];

        for ($i = 0; $i < 10; $i++) {
            $results[] = $this->accountRepository->findByCustomerId($account->getCustomerId());
        }

        // All reads should return consistent data
        foreach ($results as $result) {
            expect($result->getId())->toEqual($account->getId());
        }
    });

    it('throws exception for non-existent account', function () {
        $nonExistentCustomerId = CustomerId::fromString('non_existent_customer');

        expect(fn() => $this->accountRepository->findByCustomerId($nonExistentCustomerId))
            ->toThrow(AccountNotFoundException::class);
    });
})->uses(DatabaseTestCase::class);
